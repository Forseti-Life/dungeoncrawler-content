/**
 * @file
 * Contract test: final legacy lane convergence batch.
 *
 * Run with:
 *   node tests/combat_batch09_final_legacy_envelope_contract_test.js
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
  const phaseHandlerSource = fs.readFileSync(path.join(serviceDir, 'EncounterPhaseHandler.php'), 'utf8');
  const traitSource = fs.readdirSync(serviceDir)
    .filter((name) => name.startsWith('EncounterPhaseHandler') && name.endsWith('Trait.php'))
    .sort()
    .map((name) => fs.readFileSync(path.join(serviceDir, name), 'utf8'))
    .join('\n');
  return `${phaseHandlerSource}\n${traitSource}`;
}

console.log('\n=== Combat final legacy lane envelope contract ===');

const phaseHandlerSource = readEncounterPhaseHandlerCompositeSource();

assert(
  phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'activate_talisman'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'burrow'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'fly'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'mount'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'dismount'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'interact'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'talk'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'skill'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'feat'"),
  'Final 9 legacy lanes emit canonical execution requests'
);

assert(
  phaseHandlerSource.includes("buildEvent('activate_talisman', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('burrow', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('fly', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('mount', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('dismount', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('interact', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('talk', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('skill', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('feat', 'encounter', $actor_id, ["),
  'Final 9 legacy lanes emit canonical event metadata'
);

assert(
  phaseHandlerSource.includes("'movement_execution_request' => $movement_execution_request")
    && phaseHandlerSource.includes("'movement_resolution_envelope' => $movement_resolution_envelope"),
  'Burrow/fly lanes preserve nested stride contract forwarding'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
