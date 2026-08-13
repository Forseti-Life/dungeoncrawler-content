/**
 * @file
 * Contract test: encounter shove forced movement hazard consequences.
 *
 * Run with:
 *   node tests/forced_movement_hazard_contract_test.js
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

console.log('\n=== Forced movement hazard contract ===');

const phaseHandlerSource = read('../src/Service/EncounterPhaseHandler.php');
assert(
  phaseHandlerSource.includes('routeShoveIntentExecution(')
    && phaseHandlerSource.includes('resolveForcedShoveDestinationHex(')
    && phaseHandlerSource.includes("'is_forced' => TRUE")
    && phaseHandlerSource.includes("'forced_movement' => !empty($forced_result['stride'])"),
  'shove route resolves forced movement destination and executes forced stride semantics'
);

assert(
  phaseHandlerSource.includes('resolveEncounterTerrainHazardForMovement(')
    && phaseHandlerSource.includes("'instance_id' => 'terrain:lava'")
    && phaseHandlerSource.includes("$this->hpManager->applyDamage(")
    && phaseHandlerSource.includes('$this->combatResolutionContractService->buildDamageApplicationPacket('),
  'forced movement into lava resolves hazard damage through HP manager and emits damage packet contract'
);

assert(
  phaseHandlerSource.includes("GameEventLogger::buildEvent('hazard_triggered'")
    && phaseHandlerSource.includes("'damage_packet' => $hazard_damage_packet")
    && phaseHandlerSource.includes('syncEncounterParticipantsToDungeonData('),
  'hazard-triggered encounter events and participant/runtime HP sync are emitted after forced movement damage'
);

const chatPanelSource = read('../js/v2/panels/ChatPanel.js');
assert(
  chatPanelSource.includes("case 'shove':")
    && chatPanelSource.includes('pushed ${Math.floor(pushedFeet)} ft')
    && chatPanelSource.includes('forced_to'),
  'chat fallback formats shove with forced displacement details'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
