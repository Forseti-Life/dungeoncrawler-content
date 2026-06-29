#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IGNORE_FILE="$ROOT_DIR/.public-mirror-ignore"
TARGET_DIR="${1:-}"

if [ -z "$TARGET_DIR" ]; then
  echo "Usage: $0 <public-mirror-path>" >&2
  exit 2
fi

if ! command -v rsync >/dev/null 2>&1; then
  echo "ERROR: rsync is required." >&2
  exit 1
fi

if [ ! -f "$IGNORE_FILE" ]; then
  echo "ERROR: missing ignore file: $IGNORE_FILE" >&2
  exit 1
fi

mkdir -p "$TARGET_DIR"

if [ ! -d "$TARGET_DIR/.git" ]; then
  git -C "$TARGET_DIR" init >/dev/null 2>&1 || true
fi

# Sync private repo -> public mirror working tree using denylist policy.
rsync -a --delete --delete-excluded \
  --exclude-from="$IGNORE_FILE" \
  "$ROOT_DIR/" "$TARGET_DIR/"

# Keep only public-safe scaffold placeholders in the mirror.
mkdir -p "$TARGET_DIR/tmp"
: > "$TARGET_DIR/tmp/.gitkeep"

printf 'Synced public mirror to: %s\n' "$TARGET_DIR"
printf 'Next: (cd %s && git status --short)\n' "$TARGET_DIR"
