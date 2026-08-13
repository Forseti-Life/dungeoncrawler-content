/**
 * @file
 * Contract test: institutional score assembler seam.
 *
 * Run with:
 *   node tests/institution_disposition_score_assembler_contract_test.js
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

const assemblerSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/InstitutionDispositionScoreAssemblerService.php'),
  'utf8',
);
const matrixSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RelationshipsMatrixReadModelService.php'),
  'utf8',
);
const servicesSource = fs.readFileSync(
  path.resolve(__dirname, '../dungeoncrawler_content.services.yml'),
  'utf8',
);

console.log('\n=== Institution disposition score assembler contract ===');

assert(
  assemblerSource.includes('class InstitutionDispositionScoreAssemblerService')
    && assemblerSource.includes('buildActorTargetInstitutionAdjustment(')
    && assemblerSource.includes("'actor_sentiment' => 'institution_membership_service'")
    && assemblerSource.includes("'institution_matrix' => 'institution_disposition_matrix_service'")
    && assemblerSource.includes("'assembler' => 'institution_disposition_score_assembler'"),
  'Assembler exposes canonical actor-target institutional adjustment with explicit authority metadata'
);

assert(
  assemblerSource.includes('buildActorSentimentComponent(')
    && assemblerSource.includes('buildInstitutionMatrixComponent(')
    && assemblerSource.includes('resolveActorSentimentsCached(')
    && assemblerSource.includes('resolveActorMembershipsCached(')
    && assemblerSource.includes('loadMatrixEdgeCached(')
    && assemblerSource.includes("'actor_sentiment_component' =>")
    && assemblerSource.includes("'institution_matrix_component' =>")
    && assemblerSource.includes("'actor_sentiment_component' => self::ACTOR_COMPONENT_WEIGHT")
    && assemblerSource.includes("'institution_matrix_component' => self::MATRIX_COMPONENT_WEIGHT"),
  'Assembler computes explicit actor-sentiment and institution-matrix components with stable weights and request-scope caching'
);

assert(
  matrixSource.includes('institutionDispositionScoreAssemblerService')
    && matrixSource.includes('->buildActorTargetInstitutionAdjustment($campaign_id, $source_ref, $target_ref);')
    && !matrixSource.includes('buildInstitutionAdjustmentBreakdown('),
  'Relationships matrix read model delegates institutional adjustment to the shared assembler'
);

assert(
  matrixSource.includes('InstitutionDispositionScoreAssemblerService|InstitutionMembershipService|null $institution_disposition_score_assembler_service = NULL')
    && matrixSource.includes('RelationshipsActorIdentityResolverService|InstitutionDispositionMatrixService|null $relationships_actor_identity_resolver = NULL')
    && matrixSource.includes('new InstitutionDispositionScoreAssemblerService('),
  'Relationships matrix read model constructor tolerates legacy DI argument ordering and can synthesize assembler dependency safely'
);

assert(
  servicesSource.includes('dungeoncrawler_content.institution_disposition_score_assembler:')
    && servicesSource.includes('class: Drupal\\dungeoncrawler_content\\Service\\InstitutionDispositionScoreAssemblerService')
    && servicesSource.includes("- '@dungeoncrawler_content.institution_disposition_score_assembler'"),
  'Service wiring registers assembler and injects it into relationships matrix read model'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
