/**
 * @file
 * Contract test: CombatEntryService must return projection summaries.
 *
 * Run with:
 *   node tests/combat_entry_projection_contract_test.js
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

const combatEntrySource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/CombatEntryService.php'),
  'utf8',
);
const brokerSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmOrchestrationBrokerService.php'),
  'utf8',
);

console.log('\n=== Combat entry projection contract ===');

assert(
  combatEntrySource.includes('ActorContextProjectionService $actor_context_projection_service')
    && combatEntrySource.includes('$aggression_summary = $this->actorContextProjectionService->buildAggressionSummary($policy);'),
  'CombatEntryService injects projection authority and derives aggression summary'
);

assert(
  combatEntrySource.includes("'combat_entry_summary' => $combat_entry_summary")
    && combatEntrySource.includes("'aggression_summary' => $aggression_summary"),
  'CombatEntryService returns projection summaries on combat entry outcomes'
);

assert(
  brokerSource.includes("'aggression_summary' => is_array($entry['aggression_summary'] ?? NULL) ? $entry['aggression_summary'] : NULL")
    && brokerSource.includes("'combat_entry_summary' => is_array($entry['combat_entry_summary'] ?? NULL) ? $entry['combat_entry_summary'] : NULL"),
  'Broker forwards combat-entry projection summaries to canonical action result consumers'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}

