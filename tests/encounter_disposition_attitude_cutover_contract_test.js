/**
 * @file
 * Contract test: encounter attitude consumers should prefer
 * ActorDispositionService before psychology fallback.
 *
 * Run with:
 *   node tests/encounter_disposition_attitude_cutover_contract_test.js
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
  path.resolve(__dirname, '../src/Service/EncounterPhaseHandler.php'),
  'utf8',
);

console.log('\n=== Encounter disposition attitude cutover contract ===');

assert(
  source.includes('protected function resolveNpcAttitude(array $params, ?string $target_id, int $campaign_id): ?string')
    && source.includes('$actor_disposition = $this->resolveActorDispositionService();')
    && source.includes("$summary = $actor_disposition->getDispositionSummary($campaign_id, (string) $npc_target_id);")
    && source.includes('$profile = $this->psychologyService->loadProfile($campaign_id, (string) $npc_target_id);'),
  'Social DC attitude resolution prefers disposition state before psychology fallback'
);

assert(
  source.includes('protected function buildNpcTacticalIntentContract(string $entity_id, array $game_state, int $campaign_id): array')
    && source.includes('$entity_ref = $this->resolveCombatantEntityRef($entity_id, $game_state);')
    && source.includes("$entity_ref !== '' ? $entity_ref : $entity_id")
    && source.includes("foreach (['current_attitude', 'attitude', 'initial_attitude'] as $attitude_key)")
    && source.includes("$attitude = $attitude ?? 'indifferent';"),
  'NPC tactical intent attitude resolves from canonical entity_ref disposition with normalized profile fallback ladder'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
