#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

CANONICAL_SRC="$ROOT_DIR/src"
RUNTIME_SRC="$ROOT_DIR/sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src"

if [ ! -d "$CANONICAL_SRC" ]; then
  echo "ERROR: Canonical source directory missing: $CANONICAL_SRC" >&2
  exit 2
fi

if [ ! -d "$RUNTIME_SRC" ]; then
  echo "ERROR: Runtime mirror directory missing: $RUNTIME_SRC" >&2
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

for rel in "${CANONICAL_FILES[@]}"; do
  canonical_file="$CANONICAL_SRC/$rel"
  runtime_file="$RUNTIME_SRC/$rel"
  canonical_repo_path="src/$rel"
  runtime_repo_path="sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/$rel"

  if [ ! -f "$runtime_file" ]; then
    echo "DRIFT: runtime mirror file missing: $runtime_repo_path"
    failures=$((failures + 1))
    continue
  fi

  if ! cmp -s "$canonical_file" "$runtime_file"; then
    echo "DRIFT: file content mismatch: $canonical_repo_path <-> $runtime_repo_path"
    failures=$((failures + 1))
  fi
done

declare -a RUNTIME_FILES=()
while IFS= read -r rel; do
  [ -n "$rel" ] || continue
  RUNTIME_FILES+=("$rel")
done < <(
  {
    find "$RUNTIME_SRC/Controller" -maxdepth 1 -type f -name 'RoomChat*.php' 2>/dev/null || true
    find "$RUNTIME_SRC/Service" -maxdepth 1 -type f -name 'RoomChat*.php' 2>/dev/null || true
    find "$RUNTIME_SRC/Service/RoomChat" -type f -name '*.php' 2>/dev/null || true
  } | sed "s#^$RUNTIME_SRC/##" | sort -u
)

for rel in "${RUNTIME_FILES[@]}"; do
  if [ "${CANONICAL_SET[$rel]+set}" != "set" ]; then
    echo "DRIFT: runtime mirror has extra RoomChat file not present in canonical src: sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/$rel"
    failures=$((failures + 1))
  fi
done

if [ "$failures" -gt 0 ]; then
  echo "FAIL: RoomChat source-of-truth drift detected ($failures issue(s))." >&2
  exit 1
fi

echo "PASS: RoomChat canonical src tree and runtime mirror are in sync."
