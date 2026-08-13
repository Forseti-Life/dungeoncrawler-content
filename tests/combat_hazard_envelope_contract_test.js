/**
 * @file
 * Contract test: terrain hazard forced-movement path emits execution/envelope contracts.
 *
 * Run with:
 *   node tests/combat_hazard_envelope_contract_test.js
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

console.log('\n=== Combat hazard envelope contract ===');

const phaseHandlerSource = read('../src/Service/EncounterPhaseHandler.php');

assert(
  phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'terrain_hazard'")
    && phaseHandlerSource.includes('$resolution_envelope = $this->combatResolutionContractService->buildResolutionEnvelope('),
  'Terrain hazard path builds canonical execution request and resolution envelope'
);

assert(
  phaseHandlerSource.includes("'hazard_execution_request' => $hazard_execution_request")
    && phaseHandlerSource.includes("'hazard_resolution_envelope' => $hazard_resolution_envelope")
    && phaseHandlerSource.includes("'execution_request' => $hazard_execution_request")
    && phaseHandlerSource.includes("'resolution_envelope' => $hazard_resolution_envelope"),
  'Shove/hazard-triggered events carry hazard execution and envelope contract payloads'
);

assert(
  phaseHandlerSource.includes("requireOptionalContractPayload(\n              $terrain_hazard['execution_request'] ?? NULL,")
    && phaseHandlerSource.includes("requireOptionalContractPayload(\n              $terrain_hazard['resolution_envelope'] ?? NULL,"),
  'Hazard contract payloads are validated before emission'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
