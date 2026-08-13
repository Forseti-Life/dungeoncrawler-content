/**
 * @file
 * RCA contract checks for new-campaign runtime spawn anchoring.
 *
 * Run with:
 *   node tests/runtime_spawn_entry_anchor_rca_contract_test.js
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

const controllerSource = fs.readFileSync(path.resolve(__dirname, '../src/Controller/HexMapController.php'), 'utf8');
const resolverSource = fs.readFileSync(path.resolve(__dirname, '../src/Service/CampaignCharacterRuntimeResolverService.php'), 'utf8');
const syncSource = fs.readFileSync(path.resolve(__dirname, '../src/Service/CampaignCharacterRuntimeSyncService.php'), 'utf8');

console.log('\n=== Runtime spawn entry-anchor RCA contracts ===');

assert(
  controllerSource.includes("->condition('campaign_id', [0, $campaign_id], 'IN')")
    && controllerSource.includes("->condition('type', 'pc')"),
  'launch runtime materialization only resolves same-campaign/library player-character rows'
);

assert(
  resolverSource.includes('$entry_placement = $this->resolveRoomEntryPlacementFromSparseStorage($room_id);')
    && resolverSource.includes('protected function resolveRoomEntryPlacementFromSparseStorage(string $room_id): ?array {')
    && resolverSource.includes("->condition('cell_role', 'entry_gateway')"),
  'runtime resolver has explicit canonical room-entry placement fallback for first-time campaign rows'
);

assert(
  resolverSource.includes('$fields[\'position_q\'] = 0;')
    && resolverSource.includes('$fields[\'position_r\'] = 0;')
    && resolverSource.includes('$fields[\'position_h3\'] = \'\';')
    && resolverSource.includes('$fields[\'last_room_id\'] = \'\';')
    && resolverSource.includes('$fields[\'location_ref\'] = \'\';'),
  'follower clone upsert clears source-campaign placement fields before target-campaign hydration'
);

assert(
  syncSource.includes('$has_canonical_runtime_placement = $has_runtime_room && ($has_runtime_h3 || $has_runtime_hex);')
    && syncSource.includes('$state_placement_present = FALSE;')
    && syncSource.includes('if (!$has_canonical_runtime_placement && !$state_placement_present) {')
    && syncSource.includes('return NULL;')
    && syncSource.includes('bool $allow_owner_fallback = TRUE')
    && syncSource.includes('if ($allow_owner_fallback && $this->canResolvePlacementH3FromPayloadOrSparse')
    && syncSource.includes('if (!isset($hex[\'q\'], $hex[\'r\'])) {'),
  'follower/adjacency placement requires canonical evidence and supports non-overlap fallback mode for PC spillover'
);

console.log('\n================================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
