/**
 * @file
 * Contract test: deterministic disposition trigger catalog wiring.
 *
 * Run with:
 *   node tests/disposition_trigger_catalog_contract_test.js
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

const catalogSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/DispositionTriggerCatalog.php'),
  'utf8',
);
const triggerSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/DispositionTriggerService.php'),
  'utf8',
);
const actorDispositionSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/ActorDispositionService.php'),
  'utf8',
);
const relationshipSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RelationshipAttitudeService.php'),
  'utf8',
);
const servicesSource = fs.readFileSync(
  path.resolve(__dirname, '../dungeoncrawler_content.services.yml'),
  'utf8',
);

console.log('\n=== Disposition trigger catalog contract ===');

assert(
  catalogSource.includes('final class DispositionTriggerCatalog')
    && catalogSource.includes("'diplomacy_success'")
    && catalogSource.includes("'attack'")
    && catalogSource.includes("'damage'")
    && catalogSource.includes("'negative_effect_spell'")
    && catalogSource.includes("'combat_initiation_declared'")
    && catalogSource.includes("'intimidation_critical_success'")
    && catalogSource.includes("'intimidation_failure'")
    && catalogSource.includes("'intimidation_critical_failure'")
    && catalogSource.includes('resolveFamilyFallback(')
    && catalogSource.includes('resolveOutcomeScaledEntry(')
    && catalogSource.includes("str_contains($event, 'aid')")
    && catalogSource.includes("str_contains($event, 'perform')")
    && catalogSource.includes("str_contains($event, 'deception')")
    && catalogSource.includes("str_contains($event, 'sense_motive')")
    && catalogSource.includes("str_contains($event, 'command_animal')")
    && catalogSource.includes("str_contains($event, 'persuasion')")
    && catalogSource.includes("'theft'")
    && catalogSource.includes("'conversation', 'small_talk' => self::entry($event, 1, 2, TRUE, 300)")
    && catalogSource.includes("'actor_delta'")
    && catalogSource.includes("'relationship_delta'")
    && catalogSource.includes("'relationship_score_override'"),
  'Trigger catalog defines canonical event types with actor/relationship mutation contracts including violent-action overrides'
);

assert(
  triggerSource.includes('class DispositionTriggerService')
    && triggerSource.includes('normalizeTrigger(string $event_type, array $event_context = []): array')
    && triggerSource.includes('repeat_window_sec')
    && triggerSource.includes('relationship_score_override')
    && triggerSource.includes('idempotency_key'),
  'Trigger service normalizes trigger deltas and relationship override contracts with deterministic idempotency keys'
);

assert(
  actorDispositionSource.includes('DispositionTriggerService')
    && actorDispositionSource.includes('$this->dispositionTriggerService->normalizeTrigger(')
    && actorDispositionSource.includes('$this->dispositionEventStoreService->hasDispositionEventIdempotencyKey(')
    && actorDispositionSource.includes("'idempotency_key' => $trigger_idempotency_key")
    && actorDispositionSource.includes("'trigger' => $trigger")
    && actorDispositionSource.includes('applyTriggerRelationshipMutation('),
  'ActorDispositionService consumes deterministic trigger normalization and enforces durable idempotency before mutation'
);

assert(
  relationshipSource.includes('applyRelationshipDispositionDelta(')
    && relationshipSource.includes('hasRelationshipAttitudeEventIdempotencyKey(')
    && relationshipSource.includes("'idempotency_key' => $idempotency_key")
    && relationshipSource.includes("'score_source' => 'relationship_state_score'"),
  'RelationshipAttitudeService exposes deterministic relationship delta mutation API with relationship-edge idempotency enforcement'
);

assert(
  servicesSource.includes('dungeoncrawler_content.disposition_trigger_service:')
    && servicesSource.includes("- '@dungeoncrawler_content.disposition_trigger_service'"),
  'Service wiring registers trigger service and injects it into actor disposition authority'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
