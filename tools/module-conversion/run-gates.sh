#!/usr/bin/env bash
set -euo pipefail

DRUSH_BIN="${DRUSH_BIN:-/var/www/html/dungeoncrawler/vendor/bin/drush}"
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

if [[ ! -x "$DRUSH_BIN" ]]; then
  echo "error: DRUSH_BIN is not executable: $DRUSH_BIN" >&2
  exit 1
fi

GATES=(
  "$REPO_ROOT/tests/module_conversion_gate_contract_test.php"
  "$REPO_ROOT/tests/module_conversion_full_validation_test.php"
  "$REPO_ROOT/tests/module_conversion_campaign_launch_runtime_gate_test.php"
  "$REPO_ROOT/tests/module_conversion_publication_catalog_audit_test.php"
  "$REPO_ROOT/tests/module_conversion_campaign_create_selector_gate_test.php"
  "$REPO_ROOT/tests/module_conversion_campaign_settings_selector_gate_test.php"
  "$REPO_ROOT/tests/module_conversion_playability_audit_test.php"
  "$REPO_ROOT/tests/module_conversion_generic_fixture_release_audit_test.php"
)

for gate in "${GATES[@]}"; do
  if [[ ! -f "$gate" ]]; then
    echo "error: gate script not found: $gate" >&2
    exit 1
  fi
done

echo "Running module conversion gate suite (${#GATES[@]} scripts)"
for gate in "${GATES[@]}"; do
  echo "==> $(basename "$gate")"
  "$DRUSH_BIN" --no-interaction php:script "$gate"
done

echo "Module conversion gate suite passed."
