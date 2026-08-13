/**
 * @file
 * Contract test: encounter stride emits canonical movement packet metadata.
 *
 * Run with:
 *   node tests/combat_movement_packet_contract_test.js
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

console.log('\n=== Combat movement packet contract ===');

const contractSource = read('../src/Service/CombatResolutionContractService.php');
assert(
  contractSource.includes('MOVEMENT_PACKET_CONTRACT_VERSION')
    && contractSource.includes('buildMovementResolutionPacket(')
    && contractSource.includes("'kind' => 'movement_resolution'"),
  'CombatResolutionContractService defines canonical movement packet contract'
);

const executorSource = read('../src/Service/EncounterActionExecutor.php');
assert(
  executorSource.includes("buildMovementResolutionPacket(\n      $actor_id,\n      $is_forced ? 'forced' : 'stride',")
    && executorSource.includes("'movement_packet' => $movement_packet")
    && executorSource.includes("'from_hex' => $resolved_from_hex")
    && executorSource.includes("'to_hex' => $resolved_to_hex"),
  'EncounterActionExecutor emits movement_packet and normalized from/to hex values'
);

const phaseHandlerSource = read('../src/Service/EncounterPhaseHandler.php');
assert(
  phaseHandlerSource.includes("buildEvent('stride'")
    && phaseHandlerSource.includes("'movement_packet' => $movement_packet")
    && phaseHandlerSource.includes("'distance_ft' => $result['distance_ft'] ?? NULL")
    && phaseHandlerSource.includes("'is_forced' => !empty($result['is_forced'])"),
  'EncounterPhaseHandler validates and forwards movement_packet, distance, and forced flags in stride events'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
