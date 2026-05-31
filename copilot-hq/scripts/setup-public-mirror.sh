#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TARGET_DIR="${1:-}"
REMOTE_URL="${2:-}"

if [ -z "$TARGET_DIR" ]; then
  echo "Usage: $0 <public-mirror-path> [remote-url]" >&2
  exit 2
fi

mkdir -p "$TARGET_DIR"

if [ ! -d "$TARGET_DIR/.git" ]; then
  git -C "$TARGET_DIR" init >/dev/null 2>&1
fi

if [ -n "$REMOTE_URL" ]; then
  if git -C "$TARGET_DIR" remote get-url origin >/dev/null 2>&1; then
    git -C "$TARGET_DIR" remote set-url origin "$REMOTE_URL"
  else
    git -C "$TARGET_DIR" remote add origin "$REMOTE_URL"
  fi
fi

"$ROOT_DIR/scripts/export-public-mirror.sh" "$TARGET_DIR"

cat <<EOF

Public mirror initialized.

Recommended next steps:
  cd "$TARGET_DIR"
  git status --short
  git add -A
  git commit -m "Initial public mirror export"

If a remote is configured:
  git push -u origin HEAD
EOF
