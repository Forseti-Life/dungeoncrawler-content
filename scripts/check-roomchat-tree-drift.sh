#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

CANONICAL_SRC="$ROOT_DIR/src"
LEGACY_MIRROR_SRC="$ROOT_DIR/sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src"

if [ ! -d "$CANONICAL_SRC" ]; then
  echo "ERROR: Canonical source directory missing: $CANONICAL_SRC" >&2
  exit 2
fi

declare -a CANONICAL_FILES=()
while IFS= read -r rel; do
  [ -n "$rel" ] || continue
  CANONICAL_FILES+=("$rel")
done < <(
  {
    find "$CANONICAL_SRC/Controller" -maxdepth 1 -type f -name 'RoomChat*.php' 2>/dev/null || true
    find "$CANONICAL_SRC/Service" -maxdepth 1 -type f -name 'RoomChat*.php' 2>/dev/null || true
    find "$CANONICAL_SRC/Service/RoomChat" -type f -name '*.php' 2>/dev/null || true
  } | sed "s#^$CANONICAL_SRC/##" | sort -u
)

if [ "${#CANONICAL_FILES[@]}" -eq 0 ]; then
  echo "ERROR: No canonical RoomChat files found under src/." >&2
  exit 2
fi

declare -A CANONICAL_SET=()
for rel in "${CANONICAL_FILES[@]}"; do
  CANONICAL_SET["$rel"]=1
done

failures=0

if [ -d "$LEGACY_MIRROR_SRC" ]; then
  echo "DRIFT: retired legacy mirror path present: sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src" >&2
  failures=$((failures + 1))
fi

if [ "$failures" -gt 0 ]; then
  echo "FAIL: RoomChat source-of-truth drift detected ($failures issue(s)); single-tree canonical src policy violated." >&2
  exit 1
fi

echo "PASS: RoomChat single-tree canonical src policy is valid."
