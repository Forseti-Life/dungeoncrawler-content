/**
 * @file
 * Contract test: runtime read model includes actor relationship attitude map.
 *
 * Run with:
 *   node tests/runtime_read_relationship_attitudes_projection_contract_test.js
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
  path.resolve(__dirname, '../src/Service/RuntimeStateReadModelAssembler.php'),
  'utf8',
);
const servicesSource = fs.readFileSync(
  path.resolve(__dirname, '../dungeoncrawler_content.services.yml'),
  'utf8',
);

console.log('\n=== Runtime read relationship-attitudes projection contract ===');

assert(
  source.includes("'resolved_disposition_by_target' => $resolved_disposition_by_target,")
    && source.includes('protected function loadActorResolvedDispositionByTarget(')
    && source.includes('$this->dispositionResolverService->resolveDispositionMap('),
  'RuntimeStateReadModelAssembler projects canonical resolved disposition map for actor-visible targets'
);

assert(
  source.includes("'relationship_attitudes' => $relationship_attitudes,")
    && source.includes('protected function projectRelationshipAttitudesFromResolvedDisposition(')
    && source.includes("'effective_disposition_score' => $score,"),
  'RuntimeStateReadModelAssembler derives relationship attitude compatibility map from resolved disposition first'
);

assert(
  source.includes('protected ?RelationshipAttitudeService $relationshipAttitudeService;')
    && source.includes('protected ?DispositionResolverService $dispositionResolverService;')
    && source.includes("$this->relationshipAttitudeService = $relationship_attitude_service")
    && source.includes("$this->dispositionResolverService = $disposition_resolver_service")
    && source.includes('protected function resolveStrongestRelationshipDispositionFromService(')
    && source.includes('$this->relationshipAttitudeService->resolveEdgeDispositionDetails($source_ref, $target_ref, $campaign_id);')
    && servicesSource.includes('dungeoncrawler_content.runtime_state_read_model_assembler:')
    && servicesSource.includes("- '@dungeoncrawler_content.relationship_attitude_service'")
    && servicesSource.includes("- '@dungeoncrawler_content.disposition_resolver_service'"),
  'RuntimeStateReadModelAssembler uses canonical resolver authority with relationship-attitude fallback'
);

assert(
  source.includes("$registry = is_array($state['relationship_attitude_state'] ?? NULL)")
    && source.includes("tableExists('dc_relationship_attitude_state')")
    && source.includes('protected function resolveStrongestRelationshipAttitudeFromTable(')
    && source.includes('protected function resolveStrongestRelationshipAttitude(array $registry, array $source_candidates, array $target_candidates): string')
    && source.includes("'target_entity_id' => $target_entity_id,"),
  'RuntimeStateReadModelAssembler resolves relationship attitudes from canonical table with registry fallback'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
