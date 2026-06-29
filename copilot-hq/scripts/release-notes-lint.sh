#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

target="${1:-}"
if [ -z "$target" ]; then
  echo "Usage: $0 <release-candidate-dir|release-notes-file>" >&2
  exit 2
fi

if [ -d "$target" ]; then
  file="$target/05-release-notes.md"
else
  file="$target"
fi

if [ ! -f "$file" ]; then
  echo "ERROR: release notes not found: $file" >&2
  exit 2
fi

required_sections=(
  "Metadata"
  "Summary"
  "Change Log by Stream"
  "User-visible changes"
  "Admin / operational changes"
  "Verification evidence"
  "Risk / caveats"
  "Rollback"
  "Known issues / follow-ups"
  "Signoffs"
)

missing=0
for section in "${required_sections[@]}"; do
  if ! grep -qE "^## ${section}$" "$file"; then
    echo "MISSING SECTION: ## ${section}" >&2
    missing=1
  fi
done

section_body() {
  local section="$1"
  awk -v sec="$section" '
    $0 ~ "^## " sec "$" { in_sec=1; next }
    in_sec && $0 ~ /^## / { exit }
    in_sec { print }
  ' "$file"
}

has_meaningful_content() {
  grep -Ev '^[[:space:]]*$|^[[:space:]]*[-*][[:space:]]*$|^[[:space:]]*[-*][[:space:]]*\.{3}[[:space:]]*$|^[[:space:]]*[-*][[:space:]]*(TBD|TODO|What shipped \(1–5 bullets\))[[:space:]]*$' | grep -q '[[:alnum:]]'
}

has_filled_bullet_values() {
  grep -E '^[[:space:]]*[-*][[:space:]]+.*[^:[:space:]][[:space:]]*$' \
    | grep -Ev '^[[:space:]]*[-*][[:space:]]*(\.\.\.|TBD|TODO|What shipped \(1–5 bullets\))[[:space:]]*$' \
    | grep -q .
}

critical_sections=(
  "Metadata"
  "Summary"
  "Verification evidence"
  "Signoffs"
)

for section in "${critical_sections[@]}"; do
  if ! section_body "$section" | has_meaningful_content; then
    echo "INSUFFICIENT CONTENT: ## ${section}" >&2
    missing=1
  fi
done

for section in "Metadata" "Verification evidence" "Signoffs"; do
  if ! section_body "$section" | has_filled_bullet_values; then
    echo "UNFILLED VALUES: ## ${section} (expected at least one populated bullet value)" >&2
    missing=1
  fi
done

if [ "$missing" -ne 0 ]; then
  echo "FAIL: release notes quality gate failed ($file)" >&2
  exit 1
fi

echo "OK: release notes quality gate passed ($file)"
