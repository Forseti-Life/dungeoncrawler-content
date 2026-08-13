/**
 * @file
 * Contract test: encounter actor context includes resolved projection surface.
 *
 * Run with:
 *   node tests/encounter_actor_context_projection_contract_test.js
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
  path.resolve(__dirname, '../src/Service/EncounterActorContextBuilder.php'),
  'utf8',
);
const servicesSource = fs.readFileSync(
  path.resolve(__dirname, '../dungeoncrawler_content.services.yml'),
  'utf8',
);

console.log('\n=== Encounter actor context projection contract ===');

assert(
  source.includes('protected ?ActorContextProjectionService $actorContextProjectionService;')
    && source.includes('protected function buildResolvedActorProjection(string $entity_id, string $entity_ref, array $game_state): array')
    && source.includes("'resolved_actor_context' => $resolved_actor_context,"),
  'EncounterActorContextBuilder can derive resolved actor projection context'
);

assert(
  source.includes("'disposition_summary' => is_array($resolved_actor_context['disposition'] ?? NULL) ? $resolved_actor_context['disposition'] : NULL,")
    && source.includes("'aggression_summary' => is_array($resolved_actor_context['aggression'] ?? NULL) ? $resolved_actor_context['aggression'] : NULL,")
    && source.includes("'stance_summary' => is_array($resolved_actor_context['stance'] ?? NULL) ? $resolved_actor_context['stance'] : NULL,")
    && source.includes("'resolved_disposition_by_target' => is_array($resolved_actor_context['resolved_disposition_by_target'] ?? NULL)")
    && source.includes("'relationship_attitudes' => is_array($resolved_actor_context['relationship_attitudes'] ?? NULL)")
    && source.includes('$target_entity_refs[] = $candidate;')
    && source.includes('$character_data,')
    && source.includes('[],')
    && source.includes('$target_entity_refs'),
  'EncounterActorContextBuilder exposes resolved disposition/aggression/stance summaries plus per-target resolved disposition maps for encounter AI consumers'
);

assert(
  servicesSource.includes('dungeoncrawler_content.encounter_actor_context_builder:')
    && servicesSource.includes("- '@dungeoncrawler_content.actor_context_projection_service'"),
  'Service wiring injects actor context projection service into encounter actor context builder'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
