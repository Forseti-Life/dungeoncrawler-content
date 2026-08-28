/**
 * @file
 * Contract test: institution disposition matrix service authority.
 *
 * Run with:
 *   node tests/institution_disposition_matrix_service_contract_test.js
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
  path.resolve(__dirname, '../src/Service/InstitutionDispositionMatrixService.php'),
  'utf8',
);
const servicesSource = fs.readFileSync(
  path.resolve(__dirname, '../dungeoncrawler_content.services.yml'),
  'utf8',
);

console.log('\n=== Institution disposition matrix service contract ===');

assert(
  source.includes('class InstitutionDispositionMatrixService')
    && source.includes("protected const MATRIX_DOMAINS = ['ancestry', 'profession'];")
    && source.includes("protected const RELATIONSHIP_TYPE = 'institution_disposition';"),
  'Matrix service defines canonical ancestry/profession scope and institution_disposition edge type'
);

assert(
  source.includes('public function loadInstitutionDispositionEdge(')
    && source.includes('public function upsertInstitutionDispositionEdge(')
    && source.includes('public function ensureDefaultInstitutionDispositionEdge(')
    && source.includes('public function seedNeutralDefaultsForCampaign(')
    && source.includes('public function mutateInstitutionDispositionEdge('),
  'Matrix service exposes canonical load/upsert/ensure/seed/mutate operations'
);

assert(
  source.includes("'source_type' => 'institution',")
    && source.includes("'target_type' => 'institution',")
    && source.includes("'relationship_type' => self::RELATIONSHIP_TYPE,")
    && source.includes("'edge_kind' => self::EDGE_KIND,")
    && source.includes("'authority_scope' => self::AUTHORITY_SCOPE,")
    && source.includes("'matrix_state' => $mutated ? 'mutated' : 'defaulted',"),
  'Matrix service writes canonical institution->institution edges with explicit matrix-state authority metadata'
);

assert(
  source.includes('institution--%s--%s--institution--%s')
    && source.includes('foreach ($ancestries as $ancestry)')
    && source.includes('foreach ($professions as $profession)')
    && source.includes('ensureSeededPolicyInstitutionDispositionEdge('),
  'Matrix service seeds deterministic directed ancestry<->profession defaults for each campaign'
);

assert(
  source.includes("protected const DEFAULT_UNDEAD_TARGET_BIAS_SCORE = -2000;")
    && source.includes("protected const DEFAULT_UNDEAD_SOURCE_OTHER_ANCESTRY_BIAS_SCORE = -2000;")
    && source.includes("protected const PROFESSION_TARGET_BIAS = [")
    && source.includes("'rogue' => -5")
    && source.includes("'witch' => -5")
    && source.includes("'cleric' => 5")
    && source.includes("'bard' => 5")
    && source.includes("protected const ANCESTRY_DIRECTED_BIAS = [")
    && source.includes('resolveAncestryDirectedBias('),
  'Matrix service encodes profession and ancestry prior policies including universal ancestry->Undead hostility'
);

assert(
  servicesSource.includes('dungeoncrawler_content.institution_disposition_matrix:')
    && servicesSource.includes('class: Drupal\\dungeoncrawler_content\\Service\\InstitutionDispositionMatrixService'),
  'Service wiring registers the institution disposition matrix service'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
