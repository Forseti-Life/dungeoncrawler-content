#!/usr/bin/env bash
set -euo pipefail

# Contrast contract guard:
# - No inline styles in theme Twig templates
# - No hard-coded text colors in component SCSS

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

fail=0

echo "[contrast-check] Checking inline styles in templates..."
if grep -RIn 'style="' templates >/tmp/dc-inline-styles.txt 2>/dev/null; then
  echo "[contrast-check] FAIL: inline template styles found"
  cat /tmp/dc-inline-styles.txt
  fail=1
else
  echo "[contrast-check] OK: no inline template styles"
fi

echo "[contrast-check] Checking hard-coded text colors in governed component SCSS..."
mapfile -t TARGET_FILES < <(find src/scss/components -maxdepth 1 -type f -name '*.scss' ! -name '_animated-header.scss' | sort)

if grep -nE -- '(^|\s)color\s*:\s*(#[0-9a-fA-F]{3,8}|rgb\(|rgba\(|hsl\(|hsla\()' "${TARGET_FILES[@]}" >/tmp/dc-hardcoded-text-colors.txt 2>/dev/null; then
  echo "[contrast-check] FAIL: hard-coded text colors found in components"
  cat /tmp/dc-hardcoded-text-colors.txt
  fail=1
else
  echo "[contrast-check] OK: governed component text colors use tokens/classes"
fi

if [[ "$fail" -ne 0 ]]; then
  echo "[contrast-check] FAILED"
  exit 1
fi

echo "[contrast-check] PASSED"
