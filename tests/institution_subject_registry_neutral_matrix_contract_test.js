/**
 * @file
 * Contract test: subject registry institutional authority boundaries.
 *
 * Run with:
 *   node tests/institution_subject_registry_neutral_matrix_contract_test.js
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

const source = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/CampaignSubjectRegistryService.php'),
  'utf8',
);

console.log('\n=== Institution subject registry neutral matrix contract ===');

assert(
  source.includes('class CampaignSubjectRegistryService')
    && source.includes('resolveOrCreateInstitutionSubject(')
    && source.includes('syncInstitutionParentRelationship('),
  'Registry remains responsible for institution identity + hierarchy management'
);

assert(
  !source.includes('backfillNeutralInstitutionDispositionMatrix(')
    && !source.includes('syncInstitutionNeutralDispositionForSubject(')
    && !source.includes('upsertNeutralInstitutionDispositionEdge('),
  'Registry does not own institution-matrix seeding or edge mutation responsibilities'
);

assert(
  !source.includes("'relationship_type' => 'institution_disposition'")
    && !source.includes("'relationship_type' => 'institution_sentiment'"),
  'Registry no longer writes institution->institution disposition edges directly'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
