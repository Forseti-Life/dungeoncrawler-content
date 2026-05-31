#!/usr/bin/env bash
# lint-scripts.sh — detect recurring shell script bug patterns.
# Exit 0 = clean. Exit 1 = issues found.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

ISSUES_FILE="$(mktemp)"
trap 'rm -f "$ISSUES_FILE"' EXIT

report() { echo "LINT: $1"; echo "$1" >> "$ISSUES_FILE"; }

while IFS= read -r f; do
  # 1. Word-split: for x in $(fn) or for x in $var
  if grep -nP 'for \w+ in \$\(' "$f" 2>/dev/null | grep -v ':[[:space:]]*#' | grep -qv '# lint-ok'; then
    grep -nP 'for \w+ in \$\(' "$f" | grep -v ':[[:space:]]*#' | grep -v '# lint-ok' | while IFS= read -r hit; do
      report "$f:$hit [word-split: use while IFS= read -r]"
    done
  fi

  # 2. shopt -s nullglob without paired restore
  if grep -q 'shopt -s nullglob' "$f"; then
    if ! grep -q 'shopt -p nullglob\|shopt -u nullglob\|eval.*_ng' "$f"; then
      report "$f [shopt nullglob set without save/restore]"
    fi
  fi

  # 3. Bare grep under pipefail (grep without || true)
  if grep -nP '^\s+\w+=\$\(grep ' "$f" 2>/dev/null | grep -qv '|| true\||| echo\|# lint-ok'; then
    grep -nP '^\s+\w+=\$\(grep ' "$f" | grep -v '|| true\||| echo\|# lint-ok' | while IFS= read -r hit; do
      report "$f:$hit [bare grep in assignment under pipefail: add || true]"
    done
  fi

  # 4. GNU-only find -printf (portability)
  if grep -nP 'find .* -printf' "$f" 2>/dev/null | grep -qv '# lint-ok'; then
    grep -nP 'find .* -printf' "$f" | grep -v '# lint-ok' | while IFS= read -r hit; do
      report "$f:$hit [GNU-only find -printf: use sed 's|.*/||' or stat-c loop]"
    done
  fi

  # 5. inbox mkdir without roi.txt write
  # Heuristic: creates a new inbox item (cat > .../command.md heredoc) but no roi.txt write.
  # Excludes scripts that only archive processed commands (mkdir inbox/processed + cp command.md).
  if grep -q 'mkdir -p.*inbox' "$f"; then
    if grep -qP 'cat\s*>' "$f" && grep -q 'command\.md' "$f" && ! grep -q 'roi\.txt' "$f"; then
      report "$f [inbox mkdir without roi.txt write]"
    fi
  fi

  # 6. mktemp without trap EXIT cleanup (tmpdir leak on abnormal exit)
  if grep -q 'mktemp' "$f" && ! grep -q 'trap' "$f"; then
    report "$f [mktemp without trap EXIT cleanup: tmpdir leaks on error/kill]"
  fi

done < <(find scripts -name '*.sh' -not -path '*/\.*')

# 7. Python heredoc syntax check (catches indentation/syntax bugs like consume-forseti-replies.sh 2026-02-28)
while IFS= read -r issue; do
  echo "LINT: $issue"
  echo "$issue" >> "$ISSUES_FILE"
done < <(python3 - <<'PY' 2>/dev/null
import re, pathlib, sys

non_py_delimiters = {'MD', 'EOF', 'MARKDOWN', 'HELP', 'HTML', 'JSON', 'YAML', 'BASH', 'SH', 'TEXT', 'TXT', 'SQL'}

for sh in sorted(pathlib.Path('scripts').glob('*.sh')):
    src = sh.read_text(encoding='utf-8', errors='ignore')
    pattern = re.compile(r"python3\s[^<\n]*<<'([A-Z_0-9]+)'\n(.*?)\n\1\n", re.DOTALL)
    for m in pattern.finditer(src):
        delim = m.group(1)
        if delim in non_py_delimiters:
            continue
        code = m.group(2)
        if '<<<<<<<' in code:
            lstart = src[:m.start()].count('\n') + 1
            sys.stdout.write(f"{sh}:~line {lstart} [unresolved git conflict marker in Python heredoc]\n")
            continue
        try:
            compile(code, str(sh), 'exec')
        except SyntaxError as e:
            lstart = src[:m.start()].count('\n') + 1
            sys.stdout.write(f"{sh}:~line {lstart} [Python heredoc SyntaxError at relative line {e.lineno}: {e.msg}]\n")
PY
)

# Python syntax check: py_compile all scripts/*.py and scripts/lib/*.py
while IFS= read -r pyf; do
  if ! python3 -m py_compile "$pyf" 2>/dev/null; then
    err="$(python3 -m py_compile "$pyf" 2>&1 || true)"
    report "$pyf [Python syntax error: $err]"
  fi
done < <(find scripts -name '*.py' -not -path '*/\.*' -not -path '*/__pycache__/*')

issues="$(wc -l < "$ISSUES_FILE")"
if [ "$issues" -eq 0 ]; then
  echo "lint-scripts: OK (no issues found)"
  exit 0
else
  echo "lint-scripts: $issues issue(s) found"
  exit 1
fi
