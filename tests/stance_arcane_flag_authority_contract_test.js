/**
 * @file
 * Contract test: Arcane Cascade active-flag reads should prefer
 * stance runtime authority.
 *
 * Run with:
 *   node tests/stance_arcane_flag_authority_contract_test.js
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

const stanceRuntimeSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/StanceRuntimeService.php'),
  'utf8',
);
const encounterSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/EncounterPhaseHandler.php'),
  'utf8',
);
const somSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Controller/SomController.php'),
  'utf8',
);

console.log('\n=== Stance arcane-flag authority contract ===');

assert(
  stanceRuntimeSource.includes('public function isStanceActive(array $character_data, string $stance_id): bool')
    && stanceRuntimeSource.includes('return FALSE;')
    && !stanceRuntimeSource.includes("return $stance_id === 'arcane_cascade'"),
  'StanceRuntimeService exposes canonical stance-active check from runtime stance-state only (no som_state fallback)'
);

assert(
  stanceRuntimeSource.includes("'arcane_cascade_active' => $this->isStanceActive($character_data, 'arcane_cascade'),"),
  'Stance event/state summary projection derives Arcane Cascade flag through stance runtime authority'
);

assert(
  encounterSource.includes("'arcane_cascade_active' => $this->stanceRuntimeService->isStanceActive($character_state, 'arcane_cascade'),")
    && !encounterSource.includes(": !empty($character_state['som_state']['arcane_cascade_active'])"),
  'Encounter stance transition output uses stance runtime authority without som_state fallback'
);

assert(
  somSource.includes('$stance_summary = $this->actorContextProjectionService->buildStanceSummary($data, $campaign_id, $entity_ref);')
    && somSource.includes("'arcane_cascade_active'  => !empty($stance_summary['arcane_cascade_active']),"),
  'SOM Arcane Cascade response reads active flag from canonical stance summary projection'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
