#!/usr/bin/env bash
set -euo pipefail

DEFAULT_HQ_DIR="${HQ_DEPLOY_DIR:-${REPO_DEPLOY_DIR:-$HOME/forseti.life}/copilot-hq}"
HQ_DIR="${1:-$DEFAULT_HQ_DIR}"

if [ ! -d "$HQ_DIR" ]; then
  echo "ERROR: HQ directory not found: $HQ_DIR" >&2
  exit 1
fi

echo "=== Writeability check ==="
echo "host=$(hostname -f 2>/dev/null || hostname)"
echo "user=$(whoami)"
echo "HQ_DIR=$HQ_DIR"

check_path() {
  local target="$1"
  if [ ! -d "$target" ]; then
    echo "missing: $target"
    return 0
  fi

  local tf="$target/.write_test_$$"
  if ( : > "$tf" ) 2>/dev/null; then
    echo "writable: $target"
    rm -f "$tf"
  else
    echo "NOT writable: $target"
  fi
}

check_path "$HQ_DIR/sessions"
check_path "$HQ_DIR/inbox"
check_path "$HQ_DIR/tmp"
check_path "$HQ_DIR/inbox/responses"

echo
echo "=== Optional www-data check ==="
if command -v sudo >/dev/null 2>&1; then
  for target in "$HQ_DIR/sessions" "$HQ_DIR/inbox" "$HQ_DIR/tmp"; do
    if [ -d "$target" ]; then
      if sudo -u www-data test -w "$target" 2>/dev/null; then
        echo "www-data writable: $target"
      else
        echo "www-data NOT writable: $target"
      fi
    fi
  done
else
  echo "sudo not available; skipped www-data writeability checks"
fi

echo "DONE: writeability check complete"
