/**
 * @file
 * Contract test: canonical resolved disposition DTO authority.
 *
 * Run with:
 *   node tests/disposition_resolver_contract_test.js
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

const resolverSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/DispositionResolverService.php'),
  'utf8',
);
const sceneSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/DispositionSceneContextService.php'),
  'utf8',
);
const projectionSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/ActorContextProjectionService.php'),
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
const authorityContractSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/DispositionAuthorityContract.php'),
  'utf8',
);

console.log('\n=== Disposition resolver DTO contract ===');

assert(
  resolverSource.includes('class DispositionResolverService')
    && resolverSource.includes('resolveActorTargetDisposition(')
    && resolverSource.includes("'effective_disposition_score'")
    && resolverSource.includes("'effective_disposition_label'")
    && resolverSource.includes("'score_confidence'")
    && resolverSource.includes("'policy_flags' => [")
    && resolverSource.includes("'hostile' => $is_hostile")
    && resolverSource.includes("'attack_authorized_candidate' => $is_hostile && $confidence >= 50")
    && resolverSource.includes("'components'")
    && resolverSource.includes("'authority' => ["),
  'DispositionResolverService defines canonical resolved disposition DTO with authority metadata'
);

assert(
  authorityContractSource.includes('public const HOSTILE_SCORE_THRESHOLD = -70;')
    && authorityContractSource.includes('public static function isHostileScore(int $score): bool')
    && resolverSource.includes('$is_hostile = DispositionAuthorityContract::isHostileScore($effective_score);')
    && resolverSource.includes("'hostility_gate' => $is_hostile,"),
  'Disposition authority contract owns hostility threshold and resolver uses its helper'
);

assert(
  sceneSource.includes('class DispositionSceneContextService')
    && sceneSource.includes("'situational_score'")
    && sceneSource.includes("'recent_impulse_score'"),
  'Scene context service resolves transient disposition inputs'
);

assert(
  projectionSource.includes('buildResolvedDispositionByTarget(')
    && projectionSource.includes('dispositionResolverService->resolveDispositionMap(')
    && projectionSource.includes("'resolved_disposition_by_target' =>"),
  'Actor context projection consumes canonical resolver for per-target resolved disposition maps'
);

assert(
  matrixSource.includes('dispositionResolverService->resolveActorTargetDisposition(')
    && matrixSource.includes("'resolver_snapshot' => $resolver_dto"),
  'Relationships matrix read-model consumes canonical resolver snapshot for final score/label authority'
);

assert(
  servicesSource.includes('dungeoncrawler_content.disposition_scene_context_service:')
    && servicesSource.includes('dungeoncrawler_content.disposition_resolver_service:')
    && servicesSource.includes("- '@dungeoncrawler_content.disposition_resolver_service'"),
  'Service wiring registers scene context + resolver and injects resolver into projection/matrix builders'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
