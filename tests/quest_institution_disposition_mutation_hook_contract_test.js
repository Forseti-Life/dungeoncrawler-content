/**
 * @file
 * Contract test: quest/storyline institutional mutation hook.
 *
 * Run with:
 *   node tests/quest_institution_disposition_mutation_hook_contract_test.js
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

const serviceSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/QuestInstitutionDispositionMutationService.php'),
  'utf8',
);
const coreFlowSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatServiceCoreFlowTrait.php'),
  'utf8',
);
const servicesSource = fs.readFileSync(
  path.resolve(__dirname, '../dungeoncrawler_content.services.yml'),
  'utf8',
);

console.log('\n=== Quest institutional mutation hook contract ===');

assert(
  serviceSource.includes('class QuestInstitutionDispositionMutationService')
    && serviceSource.includes('applyQuestInstitutionDispositionMutations(')
    && serviceSource.includes('mutateInstitutionDispositionEdge(')
    && serviceSource.includes('quest_storyline_mutation')
    && serviceSource.includes('loadInstitutionDispositionEdge(')
    && serviceSource.includes('last_quest_mutation_key'),
  'Quest mutation service applies explicit institution_disposition matrix mutations through canonical matrix authority with duplicate-application guardrails'
);

assert(
  coreFlowSource.includes('applyQuestInstitutionDispositionMutationsFromQuestUpdates(')
    && coreFlowSource.includes("institution_disposition_mutations")
    && coreFlowSource.includes('dungeoncrawler_content.quest_institution_disposition_mutation')
    && coreFlowSource.includes('applyQuestInstitutionDispositionMutations('),
  'Room chat core flow applies explicit quest update institutional mutations through the dedicated quest mutation hook'
);

assert(
  servicesSource.includes('dungeoncrawler_content.quest_institution_disposition_mutation:')
    && servicesSource.includes('class: Drupal\\dungeoncrawler_content\\Service\\QuestInstitutionDispositionMutationService')
    && servicesSource.includes("- '@dungeoncrawler_content.institution_disposition_matrix'"),
  'Service wiring registers quest institutional mutation hook and its matrix-authority dependency'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
