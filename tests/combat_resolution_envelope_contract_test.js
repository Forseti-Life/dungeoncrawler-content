/**
 * @file
 * Contract test: canonical execution request + resolution envelope seams.
 *
 * Run with:
 *   node tests/combat_resolution_envelope_contract_test.js
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

console.log('\n=== Combat resolution envelope contract ===');

const contractSource = read('../src/Service/CombatResolutionContractService.php');
assert(
  contractSource.includes('EXECUTION_REQUEST_CONTRACT_VERSION')
    && contractSource.includes('RESOLUTION_ENVELOPE_CONTRACT_VERSION')
    && contractSource.includes('STATE_EFFECT_PACKET_CONTRACT_VERSION')
    && contractSource.includes('REACTION_PACKET_CONTRACT_VERSION')
    && contractSource.includes('buildCombatExecutionRequest(')
    && contractSource.includes('buildResolutionEnvelope(')
    && contractSource.includes('buildStateEffectChangePacket(')
    && contractSource.includes('buildReactionResolutionPacket('),
  'CombatResolutionContractService defines execution, envelope, state-effect, and reaction contracts'
);

const executorSource = read('../src/Service/EncounterActionExecutor.php');
assert(
  executorSource.includes("'execution_request' => $execution_request")
    && executorSource.includes("'resolution_envelope' => $this->combatResolutionContractService->buildResolutionEnvelope(")
    && executorSource.includes("'movement_packet' => $movement_packet")
    && executorSource.includes("'damage_packet' => $damage_packet"),
  'EncounterActionExecutor attaches execution_request and resolution_envelope on primary combat actions'
);

const phaseHandlerSource = read('../src/Service/EncounterPhaseHandler.php');
assert(
  phaseHandlerSource.includes('requireOptionalContractPayload(')
    && phaseHandlerSource.includes("'execution_request' => $execution_request")
    && phaseHandlerSource.includes("'resolution_envelope' => $resolution_envelope")
    && phaseHandlerSource.includes("'state_effect_packet' => $state_effect_packet")
    && phaseHandlerSource.includes("'state_effect_packets' => $state_effect_packets")
    && phaseHandlerSource.includes('buildReactionResolutionPacket('),
  'EncounterPhaseHandler validates and forwards resolution-envelope/state-effect/reaction metadata into encounter events'
);

const chatPanelSource = read('../js/v2/panels/ChatPanel.js');
assert(
  chatPanelSource.includes('const resolutionEnvelope = (data?.resolution_envelope')
    && chatPanelSource.includes("const damagePacket = findResolutionPacket('damage_application')")
    && chatPanelSource.includes("const movementPacket = findResolutionPacket('movement_resolution')")
    && chatPanelSource.includes("const stateEffectPacket = findResolutionPacket('state_effect_change')")
    && chatPanelSource.includes("const reactionPacket = findResolutionPacket('reaction_resolution')")
    && chatPanelSource.includes("case 'grapple':")
    && chatPanelSource.includes("case 'trip':"),
  'ChatPanel consumes resolution-envelope packet metadata first, with fallback packet fields for compatibility'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
