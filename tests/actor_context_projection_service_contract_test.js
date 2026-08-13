/**
 * @file
 * Contract test: canonical actor-context projection service foundation.
 *
 * Run with:
 *   node tests/actor_context_projection_service_contract_test.js
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
  path.resolve(__dirname, '../src/Service/ActorContextProjectionService.php'),
  'utf8',
);
const servicesSource = fs.readFileSync(
  path.resolve(__dirname, '../dungeoncrawler_content.services.yml'),
  'utf8',
);

console.log('\n=== Actor context projection service contract ===');

assert(
  source.includes('public function buildDispositionSummary(')
    && source.includes('public function buildAggressionSummary(array $policy): array')
    && source.includes('public function buildStanceSummary(array $character_data = [], int $campaign_id = 0, string $entity_ref = \'\'): array'),
  'Projection service exposes disposition/aggression/stance summary builders'
);

assert(
  source.includes('protected StanceStateStoreService $stanceStateStoreService;')
    && source.includes('StanceStateStoreService $stance_state_store_service')
    && source.includes('$this->stanceStateStoreService = $stance_state_store_service;')
    && servicesSource.includes('dungeoncrawler_content.actor_context_projection_service:')
    && servicesSource.includes("- '@dungeoncrawler_content.stance_state_store_service'"),
  'Projection service depends on stance state-store authority via DI wiring'
);

assert(
  source.includes('InstitutionDispositionScoreAssemblerService|ActorNarrativeContextService|null $institution_disposition_score_assembler_service = NULL')
    && source.includes('InstitutionDispositionScoreAssemblerService|ActorNarrativeContextService|null $actor_narrative_context_service = NULL')
    && source.includes('foreach ([')
    && source.includes('$this->institutionDispositionScoreAssemblerService = $institution_score_assembler;')
    && source.includes('$this->actorNarrativeContextService = $actor_narrative_context;'),
  'Projection service constructor is backward-compatible with stale DI argument ordering to prevent runtime TypeError 500s'
);

assert(
  source.includes('public function buildCombatEntrySummary(')
    && source.includes("'aggression' => $this->buildAggressionSummary($policy),"),
  'Projection service exposes combat-entry summary with aggression projection'
);

assert(
  source.includes('public function buildResolvedActorContext(')
    && source.includes('$resolved_disposition_by_target = $this->buildResolvedDispositionByTarget($campaign_id, $entity_ref, $target_entity_refs);')
    && source.includes("'disposition' => $this->buildDispositionSummary(")
    && source.includes("'resolved_disposition_by_target' => $resolved_disposition_by_target,")
    && source.includes("'aggression' => $this->buildAggressionSummary($policy)")
    && source.includes("'stance' => $this->buildStanceSummary($character_data, $campaign_id, $entity_ref)")
    && source.includes("'relationship_attitudes' => $this->projectRelationshipAttitudesFromResolvedDispositionMap($resolved_disposition_by_target)"),
  'Projection service exposes unified resolved actor-context projection'
);

assert(
  source.includes('public function buildRelationshipAttitudesSummary(int $campaign_id, string $entity_ref, array $target_entity_refs = []): array')
    && source.includes('$resolved_disposition_map = $this->buildResolvedDispositionByTarget(')
    && source.includes('protected function projectRelationshipAttitudesFromResolvedDispositionMap(array $resolved_disposition_map): array')
    && source.includes("DispositionAuthorityContract::normalizeAttitudeLabel((string) ($dto['effective_disposition_label'] ?? ''))"),
  'Projection service derives relationship-attitude projection from canonical resolved disposition authority'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
