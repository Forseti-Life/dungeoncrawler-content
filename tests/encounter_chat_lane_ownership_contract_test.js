/**
 * @file
 * Contract coverage for encounter chat lane ownership routing.
 *
 * Run with:
 *   node tests/encounter_chat_lane_ownership_contract_test.js
 */

const fs = require('fs');
const path = require('path');

let passed = 0;
let failed = 0;

function assert(condition, message) {
  if (condition) {
    passed++;
    console.log(`  ✓ ${message}`);
  } else {
    failed++;
    console.error(`  ✗ ${message}`);
  }
}

const gmSubsystemSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GameMasterSubsystemService.php'),
  'utf8'
);

console.log('\n=== Encounter chat lane ownership contract ===');

assert(
  gmSubsystemSource.includes('if (!empty($route[\'deterministic\'])) {')
    && gmSubsystemSource.includes('$action_response = $this->coordinator->processAction($campaign_id, $route[\'intent\']);'),
  'Deterministic/action-backed room chat routes through authoritative processAction lane'
);
assert(
  gmSubsystemSource.includes('$projected = $this->applyActorResponseProjection(')
    && gmSubsystemSource.includes("return $this->appendInvocationTiming($projected, 'gm_subsystem', $timings, $overall_started_at);")
    && gmSubsystemSource.includes('protected function applyActorResponseProjection('),
  'Deterministic lane responses are projected through actor-scoped response seam before returning'
);
assert(
  gmSubsystemSource.includes("if ($response_mode === 'dual_transition') {")
    && gmSubsystemSource.includes("unset($result['dungeon_data'], $result['debug_trace']);"),
  'Deterministic dual-transition mode strips implicit top-level heavy legacy fields'
);
assert(
  gmSubsystemSource.includes('protected function shouldIncludeLegacyOverlay(string $response_mode, array $options): bool {')
    && gmSubsystemSource.includes("return $response_mode === 'legacy';")
    && gmSubsystemSource.includes('Invalid room chat response mode'),
  'Dual-transition mode does not emit compatibility overlays unless explicitly requested and invalid modes are rejected'
);
assert(
  gmSubsystemSource.includes('$chat_result = $this->gmActorHarness->handlePlayerRoomChat('),
  'Free player room chat routes through GM actor harness lane'
);
assert(
  gmSubsystemSource.includes('WORKFLOW_AUTHORITATIVE_ROOM_ACTION')
    && gmSubsystemSource.includes('WORKFLOW_AUTHORITATIVE_ROOM_CHAT'),
  'GM subsystem preserves explicit workflow family separation for action vs chat paths'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
