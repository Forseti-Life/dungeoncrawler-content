/**
 * @file
 * Contract test: NPC attention scoring should resolve attitude from
 * canonical disposition authority before profile fallback.
 *
 * Run with:
 *   node tests/npc_attention_disposition_cutover_contract_test.js
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
  path.resolve(__dirname, '../src/Service/NpcAttentionService.php'),
  'utf8',
);

console.log('\n=== NPC attention disposition cutover contract ===');

assert(
  source.includes('$resolved_attitude = $this->resolveNpcAttitudeForScoring($npc_profile, $game_state);')
    && source.includes('$personality_alignment = $this->scorePersonalityAlignment('),
  'Attention score pipeline resolves canonical attitude before personality-alignment scoring'
);

assert(
  source.includes('protected function resolveNpcAttitudeForScoring(array $npc_profile, array $game_state): string')
    && source.includes("if ($campaign_id <= 0 || $entity_ref === '' || !\\Drupal::hasService('dungeoncrawler_content.actor_disposition_service')) {")
    && source.includes('$summary = $service->getDispositionSummary($campaign_id, $entity_ref, $live_entity);'),
  'Attention scoring can read attitude from ActorDispositionService with safe fallback conditions'
);

assert(
  source.includes('protected function normalizeAttentionAttitude(string $value): string')
    && source.includes("['friendly', 'helpful', 'neutral', 'suspicious', 'hostile']"),
  'Attention scoring normalizes canonical attitude values through a dedicated helper'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
