/**
 * @file
 * Contract test: active stances should be force-terminated on combat defeat.
 *
 * Run with:
 *   node tests/stance_forced_termination_on_defeat_contract_test.js
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

const runtimeSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/StanceRuntimeService.php'),
  'utf8',
);
const encounterSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/EncounterPhaseHandler.php'),
  'utf8',
);

console.log('\n=== Stance forced termination on defeat contract ===');

assert(
  runtimeSource.includes('public function clearAllStances(')
    && runtimeSource.includes("$character_data['stance_state']['active_stances'] = [];")
    && runtimeSource.includes(" 'stance_forced_termination'")
    && !runtimeSource.includes("$character_data['som_state']['arcane_cascade_active'] = FALSE;"),
  'StanceRuntimeService provides canonical all-stance forced-termination operation without legacy som_state writes'
);

assert(
  encounterSource.includes('$this->applyForcedStanceTerminationOnDefeat($entity_id, $campaign_id, $dungeon_data, $events);')
    && encounterSource.includes('protected function applyForcedStanceTerminationOnDefeat(string $entity_id, int $campaign_id, array $dungeon_data, array &$events): void')
    && encounterSource.includes('$updated_state = $this->stanceRuntimeService->clearAllStances(')
    && encounterSource.includes("$has_arcane = $this->stanceRuntimeService->isStanceActive($character_state, 'arcane_cascade');")
    && !encounterSource.includes("!empty($character_state['som_state']['arcane_cascade_active'])")
    && encounterSource.includes("GameEventLogger::buildEvent('stance_forced_termination', 'encounter', $entity_id,"),
  'Encounter defeat handling force-terminates active stances through canonical stance runtime without som_state reads'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
