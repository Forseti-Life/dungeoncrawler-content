/**
 * @file
 * Contract test: coordinator specialized mutation hydration lane usage.
 *
 * Run with:
 *   node tests/game_coordinator_mutation_hydration_lane_contract_test.js
 */

const fs = require('fs');
const path = require('path');

const source = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GameCoordinatorService.php'),
  'utf8'
);

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

function sectionBetween(startNeedle, endNeedle) {
  const start = source.indexOf(startNeedle);
  if (start < 0) return '';
  const end = source.indexOf(endNeedle, start + startNeedle.length);
  return end < 0 ? source.slice(start) : source.slice(start, end);
}

console.log('\n=== Coordinator mutation hydration lane contract ===');

const processActionSection = sectionBetween(
  'public function processAction(int $campaign_id, array $intent): array {',
  'public function getEncounterProgressState(int $campaign_id): array {'
);
const transitionSection = sectionBetween(
  'public function transitionPhase(int $campaign_id, string $target_phase, array $context = []): array {',
  'public function startCombatEncounter(int $campaign_id, array $context = []): array {'
);
const combatSection = sectionBetween(
  'public function startCombatEncounter(int $campaign_id, array $context = []): array {',
  'public function getEventsSince(int $campaign_id, int $since_cursor = 0): array {'
);

assert(
  processActionSection.includes('resolveMutationExecutionContext('),
  'processAction uses specialized mutation execution context hydration lane'
);
assert(
  transitionSection.includes('resolveMutationExecutionContext('),
  'transitionPhase uses specialized mutation execution context hydration lane'
);
assert(
  combatSection.includes('resolveMutationExecutionContext('),
  'startCombatEncounter uses specialized mutation execution context hydration lane'
);
assert(
  !processActionSection.includes('$this->loadDungeonData(')
    && !transitionSection.includes('$this->loadDungeonData(')
    && !combatSection.includes('$this->loadDungeonData('),
  'normal mutation coordinator entrypoints do not directly call loadDungeonData'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
