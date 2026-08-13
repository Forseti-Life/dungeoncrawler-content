/**
 * @file
 * Contract test: encounter AI integration context includes canonical
 * resolved actor-context slices and DI wiring.
 *
 * Run with:
 *   node tests/encounter_ai_integration_resolved_context_contract_test.js
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
  path.resolve(__dirname, '../src/Service/EncounterAiIntegrationService.php'),
  'utf8',
);
const servicesSource = fs.readFileSync(
  path.resolve(__dirname, '../dungeoncrawler_content.services.yml'),
  'utf8',
);
const contractHashMethodMatch = source.match(/protected function buildResolvedActorContextContractHash[\s\S]*?return hash\('sha256', \$encoded\);\n  \}/);
const contractHashMethodSource = contractHashMethodMatch ? contractHashMethodMatch[0] : '';

console.log('\n=== Encounter AI integration resolved context contract ===');

assert(
  source.includes('protected ?ActorContextProjectionService $actorContextProjectionService;')
    && source.includes('$resolved_actor_context = $this->buildResolvedActorProjection(')
    && source.includes("'resolved_actor_context' => $resolved_actor_context,"),
  'EncounterAiIntegrationService derives and forwards resolved_actor_context in encounter context'
);

assert(
  source.includes("'disposition_summary' => is_array($resolved_actor_context['disposition'] ?? NULL) ? $resolved_actor_context['disposition'] : NULL,")
    && source.includes("'aggression_summary' => is_array($resolved_actor_context['aggression'] ?? NULL) ? $resolved_actor_context['aggression'] : NULL,")
    && source.includes("'stance_summary' => is_array($resolved_actor_context['stance'] ?? NULL) ? $resolved_actor_context['stance'] : NULL,")
    && source.includes("'combat_entry_summary' => $combat_entry_summary,")
    && source.includes("'resolved_disposition_by_target' => is_array($resolved_actor_context['resolved_disposition_by_target'] ?? NULL)")
    && source.includes("'relationship_attitudes' => is_array($resolved_actor_context['relationship_attitudes'] ?? NULL)")
    && source.includes("'current_actor_tactical_intent' => $current_actor_tactical_intent,")
    && source.includes("'resolved_actor_context_contract_version' => self::RESOLVED_ACTOR_CONTEXT_CONTRACT_VERSION,")
    && source.includes("'resolved_actor_context_contract_hash' => $resolved_actor_context_contract_hash,")
    && source.includes('protected function loadCombatEntrySummaryFromRoom(int $campaign_id, string $room_id): ?array')
    && source.includes('protected function resolveCurrentActorTacticalIntent(array $current_actor, array $resolved_actor_context): array')
    && source.includes('protected function buildResolvedActorContextContractHash(array $resolved_actor_context): string'),
  'EncounterAiIntegrationService exposes canonical resolved summaries plus tactical-intent, combat-entry summary, and context contract metadata for downstream AI consumers'
);
assert(
  contractHashMethodSource.includes("'resolved_disposition_by_target' => is_array($resolved_actor_context['resolved_disposition_by_target'] ?? NULL)")
    && !contractHashMethodSource.includes('relationship_attitudes'),
  'Resolved actor-context contract hash is anchored to resolver-authoritative slices, excluding compatibility relationship_attitudes labels'
);

assert(
  source.includes('protected function buildResolvedActorProjection(int $campaign_id, array $current_actor, string $actor_id, array $participants = []): array')
    && source.includes('$target_entity_refs[] = $candidate;')
    && source.includes('return $this->actorContextProjectionService->buildResolvedActorContext($campaign_id, $entity_ref, $live_entity, $character_data, [], $target_entity_refs);'),
  'EncounterAiIntegrationService resolves projection context via ActorContextProjectionService'
);

assert(
  source.includes('$most_hostile_target_score = $this->resolveMostHostileDispositionScore($resolved_disposition_by_target);')
    && source.includes('$has_hostile_target_flag = $this->hasHostileDispositionPolicyFlag($resolved_disposition_by_target);')
    && source.includes('$has_hostile_disposition = $has_hostile_target_flag')
    && source.includes('protected function hasHostileDispositionPolicyFlag(array $resolved_disposition_by_target): bool')
    && source.includes('protected function resolveMostHostileDispositionScore(array $resolved_disposition_by_target): ?int')
    && source.includes('protected function isHostileDispositionScore(int $score): bool'),
  'Tactical intent fallback prefers resolver policy_flags and retains score-first hostility posture'
);

assert(
  servicesSource.includes('dungeoncrawler_content.encounter_ai_integration:')
    && servicesSource.includes("- '@dungeoncrawler_content.actor_context_projection_service'")
    && servicesSource.includes("- '@dungeoncrawler_content.aggression_state_store_service'"),
  'Service wiring injects actor context projection and aggression state-store services into encounter AI integration service'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
