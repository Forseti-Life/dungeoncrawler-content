/**
 * @file
 * Contract test: encounter runtime fails loudly on mixed-shape packet payloads.
 *
 * Run with:
 *   node tests/combat_strict_contract_payload_guard_test.js
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

console.log('\n=== Combat strict contract payload guard ===');

const phaseHandlerSource = readEncounterPhaseHandlerCompositeSource();

assert(
  phaseHandlerSource.includes('protected function requireOptionalContractPayload(')
    && phaseHandlerSource.includes('Invalid %s payload kind')
    && phaseHandlerSource.includes('Invalid %s contract version'),
  'EncounterPhaseHandler defines strict payload validator with kind/version enforcement'
);

assert(
  phaseHandlerSource.includes("CombatResolutionContractService::EXECUTION_REQUEST_CONTRACT_VERSION")
    && phaseHandlerSource.includes("CombatResolutionContractService::RESOLUTION_ENVELOPE_CONTRACT_VERSION")
    && phaseHandlerSource.includes("CombatResolutionContractService::DAMAGE_PACKET_CONTRACT_VERSION")
    && phaseHandlerSource.includes("CombatResolutionContractService::MOVEMENT_PACKET_CONTRACT_VERSION"),
  'Encounter action routes validate execution, envelope, damage, and movement payload versions'
);

assert(
  !phaseHandlerSource.includes("is_array($result['execution_request'] ?? NULL)")
    && !phaseHandlerSource.includes("is_array($result['resolution_envelope'] ?? NULL)")
    && !phaseHandlerSource.includes("is_array($result['damage_packet'] ?? NULL)")
    && !phaseHandlerSource.includes("is_array($result['movement_packet'] ?? NULL)"),
  'Encounter action routes no longer silently null mixed-shape packet payloads'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
