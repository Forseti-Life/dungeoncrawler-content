/**
 * @file
 * Contract test: runtime PC placement anchors new placements to room entry hex.
 *
 * Run with:
 *   node tests/runtime_pc_entry_hex_placement_contract_test.js
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

const source = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/CampaignCharacterRuntimeSyncService.php'),
  'utf8'
);

console.log('\n=== Runtime PC entry placement contracts ===');

assert(
  source.includes('protected function resolveCharacterPlacement(array $dungeon_payload, string $room_id, array $record, array $occupied): array {')
    && source.includes('$entry_hex = $this->resolveRoomEntryHexCoordinate($dungeon_payload, $room_id);')
    && source.includes('return $this->findAdjacentCompanionHex(')
    && source.includes('$occupied,')
    && source.includes('FALSE'),
  'runtime PC placement falls back to room entry anchor and adjacent spillover without occupied-owner overlap fallback'
);

assert(
  source.includes('protected function resolveRoomEntryHexCoordinate(array $dungeon_payload, string $room_id): ?array {')
    && source.includes("$is_entry = !empty($hex['is_entry']) || !empty($hex['entry']);")
    && source.includes("->condition('c.cell_role', 'entry_gateway')"),
  'room entry anchor resolution prefers authored entry hex and sparse entry_gateway fallback'
);

console.log('\n===========================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
