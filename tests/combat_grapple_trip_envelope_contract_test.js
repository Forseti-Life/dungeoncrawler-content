/**
 * @file
 * Contract test: grapple/trip lanes emit canonical execution/envelope contracts.
 *
 * Run with:
 *   node tests/combat_grapple_trip_envelope_contract_test.js
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

console.log('\n=== Combat grapple/trip envelope contract ===');

const phaseHandlerSource = read('../src/Service/EncounterPhaseHandler.php');

assert(
  phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'grapple'")
    && phaseHandlerSource.includes("'execution_request' => $execution_request")
    && phaseHandlerSource.includes("'resolution_envelope' => $resolution_envelope")
    && phaseHandlerSource.includes("buildEvent('grapple'"),
  'Grapple lane emits canonical execution request and resolution envelope metadata'
);

assert(
  phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'trip'")
    && phaseHandlerSource.includes("'damage_packet' => $damage_packet")
    && phaseHandlerSource.includes("buildEvent('trip'")
    && phaseHandlerSource.includes("'state_effect_packets' => $state_effect_packets"),
  'Trip lane emits canonical execution/envelope metadata with damage/state-effect packets'
);

assert(
  phaseHandlerSource.includes('$this->unifiedStateEffectEngine->buildStateEffectChangePacket(')
    && phaseHandlerSource.includes('$this->unifiedDamageEngine->buildDamageApplicationPacket('),
  'Grapple/trip lanes route packet construction through unified engines'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
