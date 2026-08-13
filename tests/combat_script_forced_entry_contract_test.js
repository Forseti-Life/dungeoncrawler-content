/**
 * @file
 * Contract test: scripted/forced combat initiation should be able to resolve
 * all hostile room entities via canonical combat-entry flow.
 *
 * Run with:
 *   node tests/combat_script_forced_entry_contract_test.js
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

console.log('\n=== Combat scripted-forced entry contract ===');

assert(
  source.includes('if ($requested_ids === [] && $requested_names === []) {')
    && source.includes("$force_all_hostiles = !empty($combat['force_all_hostiles'])")
    && source.includes("|| !empty($combat['script_forced'])")
    && source.includes("|| !empty($combat['scenario_forced']);")
    && source.includes('if ($force_all_hostiles || $reevaluate_visible_hostiles) {')
    && source.includes('return $hostiles;'),
  'Combat initiation can route scripted/forced entries to all resolved hostile entities when explicit targets are omitted'
);

assert(
  source.includes('return count($hostiles) === 1 ? $hostiles : [];'),
  'Default no-target behavior remains conservative when forced/scripted flags are absent'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
