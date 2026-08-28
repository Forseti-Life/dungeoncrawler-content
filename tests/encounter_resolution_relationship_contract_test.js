/**
 * @file
 * Contract test: encounter end applies survivor relationship/institution updates.
 *
 * Run with:
 *   node tests/encounter_resolution_relationship_contract_test.js
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

const combatEngineSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/CombatEngine.php'),
  'utf8',
);

console.log('\n=== Encounter resolution relationship contract ===');

assert(
  combatEngineSource.includes('private const ENCOUNTER_SURVIVOR_PAIR_LIMIT = 20;')
    && combatEngineSource.includes('private const ENCOUNTER_SAME_SIDE_DELTA = 20;')
    && combatEngineSource.includes('private const ENCOUNTER_OPPOSITE_SIDE_DELTA = -20;')
    && combatEngineSource.includes('private const ENCOUNTER_INSTITUTION_DELTA = 12;'),
  'Combat engine defines deterministic survivor relationship/institution resolution thresholds'
);

assert(
  combatEngineSource.includes('$this->applyEncounterRelationshipResolution($encounter);')
    && combatEngineSource.includes('protected function applyEncounterRelationshipResolution(array $encounter): void'),
  'Combat engine runs survivor relationship resolution when an encounter ends'
);

assert(
  combatEngineSource.includes('protected function applyPairwiseSurvivorRelationshipResolution(int $campaign_id, int $encounter_id, array $survivors): void')
    && combatEngineSource.includes('$same_side ? self::ENCOUNTER_SAME_SIDE_DELTA : self::ENCOUNTER_OPPOSITE_SIDE_DELTA')
    && combatEngineSource.includes("'relationship_type' => 'combat'")
    && combatEngineSource.includes("'mutation_source' => 'encounter_survivor_resolution'"),
  'Small survivor groups get pairwise combat relationship edge deltas by side alignment'
);

assert(
  combatEngineSource.includes('protected function applyLargeEncounterInstitutionResolution(int $campaign_id, int $encounter_id, array $survivors): void')
    && combatEngineSource.includes('listActorInstitutionMemberships($campaign_id, $source_type, $source_id)')
    && combatEngineSource.includes('listActorInstitutionSentiments($campaign_id, $source_type, $source_id)')
    && combatEngineSource.includes('mutateInstitutionSentiment(')
    && combatEngineSource.includes("'Large-encounter survivor institution cohesion adjustment.'"),
  'Large survivor groups get institution-level positive sentiment adjustments'
);

assert(
  combatEngineSource.includes('protected function isPartyInstitutionMembership(array $membership): bool')
    && combatEngineSource.includes("in_array($sentiment_domain, ['ancestry', 'profession'], TRUE)")
    && combatEngineSource.includes("str_starts_with($target_id, 'institution_ancestry_')")
    && combatEngineSource.includes("str_starts_with($target_id, 'institution_profession_')"),
  'Combat engine excludes ancestry/profession institutions from large-group survivor adjustments'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
