#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

NODE_BIN="${NODE_BIN:-node}"

TESTS=(
  "tests/room_scene_damage_hostility_escalation_contract_test.js"
  "tests/room_scene_bootstrap_hydration_contract_test.js"
  "tests/room_scene_stale_encounter_repair_contract_test.js"
  "tests/room_scene_spell_legality_contract_test.js"
  "tests/undead_startup_hostility_seed_contract_test.js"
  "tests/room_scene_hostility_drift_warning_contract_test.js"
  "tests/room_scene_hostility_rca_capture_contract_test.js"
  "tests/institution_membership_neutral_defaults_contract_test.js"
)

echo "=== 886 regression gate ==="
for test_file in "${TESTS[@]}"; do
  echo "--- ${test_file}"
  "${NODE_BIN}" "${test_file}"
done

echo "PASS: 886 regression gate"
