/**
 * @file
 * Contract test: fifth 10-lane medicine/thievery/wand batch.
 *
 * Run with:
 *   node tests/combat_batch10_medicine_thievery_wand_envelope_contract_test.js
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

function read(relPath) {
  return fs.readFileSync(path.resolve(__dirname, relPath), 'utf8');
}

function readEncounterPhaseHandlerCompositeSource() {
  const serviceDir = path.resolve(__dirname, '../src/Service');
  const phaseHandlerSource = fs.readdirSync(serviceDir)
    .filter((name) => name === 'EncounterPhaseHandler.php' || (name.startsWith('EncounterPhaseHandler') && name.endsWith('Trait.php')))
    .sort()
    .map((name) => fs.readFileSync(path.join(serviceDir, name), 'utf8'))
    .join('\n');
  return phaseHandlerSource;
}

console.log('\n=== Combat batch-10 medicine/thievery/wand envelope contract ===');

const phaseHandlerSource = readEncounterPhaseHandlerCompositeSource();

assert(
  phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'administer_first_aid'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'treat_poison'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'battle_medicine'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'recall_knowledge'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'palm_object'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'steal'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'disable_device'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'pick_lock'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'cast_from_wand'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'overcharge_wand'"),
  'All 10 medicine/thievery/wand lanes emit canonical execution requests'
);

assert(
  phaseHandlerSource.includes("buildEvent('administer_first_aid', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('treat_poison', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('battle_medicine', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('recall_knowledge', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('palm_object', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('steal', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('disable_device', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('pick_lock', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('cast_from_wand', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('overcharge_wand', 'encounter', $actor_id, ["),
  'All 10 medicine/thievery/wand lanes emit canonical event metadata'
);

assert(
  phaseHandlerSource.includes("'execution_request' => $execution_request")
    && phaseHandlerSource.includes("'resolution_envelope' => $resolution_envelope"),
  'Batch lanes expose canonical execution_request and resolution_envelope payloads'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
