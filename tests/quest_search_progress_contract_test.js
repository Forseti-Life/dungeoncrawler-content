/**
 * @file
 * Focused regression for Search collectible quest targeting.
 *
 * Run with:
 *   node tests/quest_search_progress_contract_test.js
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

const source = fs.readFileSync(path.resolve(__dirname, '../src/Service/ExplorationPhaseHandler.php'), 'utf8');

console.log('\n=== Search quest progress contract ===');

assert(
  source.includes("->condition('q.status', ['active'], 'IN')"),
  'Search collectible quest targeting only scans active quests'
);

assert(
  source.includes("&& in_array($location, $room_ids, TRUE)") &&
  !source.includes('$quest_room_ids = [];'),
  'Search collectible quest targeting requires canonical objective location metadata instead of read-time fallback'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
