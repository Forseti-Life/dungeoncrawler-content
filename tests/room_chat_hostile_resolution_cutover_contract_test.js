/**
 * @file
 * Contract test: room-chat hostile target resolution should prefer
 * canonical disposition/relationship authority over pure team heuristics.
 *
 * Run with:
 *   node tests/room_chat_hostile_resolution_cutover_contract_test.js
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
  path.resolve(__dirname, '../src/Service/RoomChatServiceIntentAndDeterminismTrait.php'),
  'utf8',
);

console.log('\n=== Room chat hostile-resolution cutover contract ===');

assert(
  source.includes("if (\\Drupal::hasService('dungeoncrawler_content.disposition_resolver_service'))")
    && source.includes('$resolved = $disposition_resolver->resolveDispositionMap($campaign_id, $source_ref, $targets, [')
    && source.includes("$hostile_flag = (bool) ($dto['policy_flags']['hostile'] ?? FALSE);")
    && source.includes('if ($hostile_flag || $this->isHostileDispositionScore($effective_score)) {'),
  'Room chat classifies hostility from canonical resolved disposition policy flags/scores'
);

assert(
  source.includes("$summary = $actor_disposition->getDispositionSummary($campaign_id, $source_ref, $entity);")
    && source.includes("DispositionAuthorityContract::attitudeToScore((string) ($summary['current_attitude'] ?? ''))")
    && source.includes('$relationship_attitude->resolveEdgeDispositionDetails($source_ref, $target_ref, $campaign_id)')
    && source.includes("DispositionAuthorityContract::attitudeToScore((string) ($edge['attitude'] ?? ''))"),
  'Room chat fallback authorities project hostility via score conversion (no label-only gate)'
);

assert(
  source.includes('protected function isHostileDispositionScore(int $score): bool')
    && source.includes('return DispositionAuthorityContract::isHostileScore($score);'),
  'Room chat hostility threshold is centralized through numeric disposition score gate'
);

assert(
  source.includes('if ($canonical_hostiles !== []) {')
    && source.includes('if ($hostiles !== []) {'),
  'Room chat prefers canonical hostile resolution before legacy team fallback'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
