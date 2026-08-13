/**
 * @file
 * Contract test: combat enemy resolution should use canonical hostility
 * authorities before team-label fallback.
 *
 * Run with:
 *   node tests/combat_enemy_hostility_resolution_cutover_contract_test.js
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
  path.resolve(__dirname, '../src/Service/GmOrchestrationBrokerService.php'),
  'utf8',
);

console.log('\n=== Combat enemy hostility resolution cutover contract ===');

assert(
  source.includes('public function resolveCombatEnemyEntities(string $room_id, array $dungeon_data, array $combat): array')
    && source.includes('$is_hostile = $this->isHostileCombatTargetCandidate($campaign_id, $source_entity_ref, $entity);'),
  'Combat enemy resolution routes hostile candidate classification through dedicated helper'
);

assert(
  source.includes('protected function isHostileCombatTargetCandidate(int $campaign_id, string $source_entity_ref, array $entity): bool')
    && source.includes('$summary = $this->getActorDispositionService()->getDispositionSummary($campaign_id, $target_ref, $entity);')
    && source.includes('DispositionAuthorityContract::isHostileScore($target_score)')
    && source.includes('$edge_details = $this->getRelationshipAttitudeService()->resolveEdgeDispositionDetails($source_entity_ref, $target_ref, $campaign_id);')
    && source.includes('DispositionAuthorityContract::isHostileScore($edge_score)'),
  'Hostility classification uses canonical disposition and relationship attitude authorities'
);

assert(
  source.includes("$team = strtolower((string) ($entity['state']['metadata']['team'] ?? $entity['team'] ?? ''));")
    && source.includes("return in_array($team, ['hostile', 'enemy', 'monsters'], TRUE);"),
  'Team-label matching is retained only as fallback for non-canonical contexts'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
