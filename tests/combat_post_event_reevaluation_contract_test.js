/**
 * @file
 * Contract test: room-scene combat initiation should reevaluate visible
 * hostiles for coercive/scripted or explicit post-event escalation signals.
 *
 * Run with:
 *   node tests/combat_post_event_reevaluation_contract_test.js
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
  path.resolve(__dirname, '../src/Service/GmOrchestrationBrokerService.php'),
  'utf8',
);

console.log('\n=== Combat post-event reevaluation contract ===');

assert(
  source.includes('$inferred_signal = $this->resolveAggressionSignalFromCombatPayload($combat, []);')
    && source.includes("$reevaluate_visible_hostiles = !empty($combat['post_event_reevaluation'])")
    && source.includes("|| !empty($combat['explicit_attack_declared'])")
    && source.includes("|| in_array($inferred_signal, ['coercive_threat', 'scripted_trigger'], TRUE);")
    && source.includes('if ($force_all_hostiles || $reevaluate_visible_hostiles) {')
    && source.includes('return $hostiles;'),
  'Combat enemy resolution reevaluates visible hostiles for post-event coercive/scripted escalation signals'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
