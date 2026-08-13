/**
 * @file
 * Contract test: combat movement writes through to dc_campaign_characters.
 *
 * Run with:
 *   node tests/combat_runtime_character_position_sync_contract_test.js
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

const source = fs.readFileSync(path.resolve(__dirname, '../src/Service/EncounterActionExecutor.php'), 'utf8');

console.log('\n=== Combat runtime character position sync contracts ===');

assert(
  source.includes('syncCampaignCharacterPlacement(')
    && source.includes("$this->syncCampaignCharacterPlacement(")
    && source.includes("->condition('instance_id', $actor_id)"),
  'combat movement invokes campaign-character placement synchronization keyed by actor instance id'
);

assert(
  source.includes("->fields([")
    && source.includes("'position_q' => $q")
    && source.includes("'position_r' => $r")
    && source.includes("'last_room_id' => $room_id !== '' ? $room_id : NULL")
    && source.includes("$state_data['placement']['hex'] = ["),
  'dc_campaign_characters hot columns and state_data placement are updated together'
);

console.log('\n=======================================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
