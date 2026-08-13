/**
 * @file
 * Contract test: disposition mutation classifier service.
 *
 * Run with:
 *   node tests/disposition_mutation_classifier_contract_test.js
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

const classifierSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/DispositionMutationClassifierService.php'),
  'utf8',
);
const executorSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/EncounterActionExecutor.php'),
  'utf8',
);
const servicesSource = fs.readFileSync(
  path.resolve(__dirname, '../dungeoncrawler_content.services.yml'),
  'utf8',
);

console.log('\n=== Disposition mutation classifier contract ===');

assert(
  classifierSource.includes('class DispositionMutationClassifierService')
    && classifierSource.includes('classifyActionMutationScope(')
    && classifierSource.includes("'strike'")
    && classifierSource.includes("'talk'")
    && classifierSource.includes("'demoralize'")
    && classifierSource.includes("'cast_spell'")
    && classifierSource.includes("'conversation'")
    && classifierSource.includes("'intimidation_critical_success'")
    && classifierSource.includes("'intimidation_critical_failure'")
    && classifierSource.includes("'negative_effect_spell'")
    && classifierSource.includes("'apply_institution_disposition' => FALSE"),
  'Classifier service defines centralized action mutation scope contract for encounter actions'
);

assert(
  executorSource.includes('applyClassifiedDispositionMutation(')
    && executorSource.includes('dispositionMutationClassifierService')
    && executorSource.includes("'strike'")
    && executorSource.includes("'talk'")
    && executorSource.includes("'cast_spell'")
    && executorSource.includes('classifyActionMutationScope('),
  'Encounter action executor routes strike/spell/talk mutation decisions through classifier before trigger application'
);

assert(
  servicesSource.includes('dungeoncrawler_content.disposition_mutation_classifier_service:')
    && servicesSource.includes("- '@?dungeoncrawler_content.disposition_mutation_classifier_service'"),
  'Service wiring registers classifier service and injects it into encounter action executor'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
