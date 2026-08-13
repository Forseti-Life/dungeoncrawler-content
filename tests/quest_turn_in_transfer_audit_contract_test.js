/**
 * @file
 * Contract test: quest turn-in collectible transfers emit correlation-ready audits.
 *
 * Run with:
 *   node tests/quest_turn_in_transfer_audit_contract_test.js
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

const questTrackerSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/QuestTrackerService.php'),
  'utf8'
);

console.log('\n=== Quest turn-in transfer audit contract ===');

assert(
  questTrackerSource.includes('recordQuestTurnInTransferAudit(')
    && questTrackerSource.includes("uniqid('quest_turn_in_', TRUE)")
    && questTrackerSource.includes("'quest_turn_in_transfer'")
    && questTrackerSource.includes("'transfer_correlation_id' => $transfer_correlation_id"),
  'QuestTrackerService emits quest-turn-in transfer logs with a transfer correlation id'
);

assert(
  questTrackerSource.includes("'quest_turn_in_transfer:' . $event")
    && questTrackerSource.includes("'event' => $event")
    && questTrackerSource.includes("'objective_id' => $objective_id"),
  'Quest turn-in transfer audit rows include event/objective context for RCA'
);

assert(
  questTrackerSource.includes("'no_candidates'")
    && questTrackerSource.includes("'item_transferred'")
    && questTrackerSource.includes("'incomplete'")
    && questTrackerSource.includes("'complete'"),
  'Quest turn-in transfer audits cover no-candidate, per-item transfer, incomplete, and complete outcomes'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}

