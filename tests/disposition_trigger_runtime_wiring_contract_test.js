/**
 * @file
 * Contract test: runtime trigger wiring for relationship overrides.
 *
 * Run with:
 *   node tests/disposition_trigger_runtime_wiring_contract_test.js
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

const actorDispositionSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/ActorDispositionService.php'),
  'utf8',
);
const encounterExecutorSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/EncounterActionExecutor.php'),
  'utf8',
);
const encounterPhaseHandlerSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/EncounterPhaseHandler.php'),
  'utf8',
);
const roomChatChannelSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatServiceChannelAndSessionTrait.php'),
  'utf8',
);
const roomChatInterjectionSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatServiceNpcInterjectionTrait.php'),
  'utf8',
);
const servicesSource = fs.readFileSync(
  path.resolve(__dirname, '../dungeoncrawler_content.services.yml'),
  'utf8',
);

console.log('\n=== Disposition trigger runtime wiring contract ===');

assert(
  actorDispositionSource.includes('applyTriggerRelationshipMutation(')
    && actorDispositionSource.includes('relationship_score_override')
    && actorDispositionSource.includes('->upsertRelationshipAttitude(')
    && actorDispositionSource.includes('->applyRelationshipDispositionDelta(')
    && actorDispositionSource.includes("'trigger_event_type'"),
  'ActorDispositionService applies trigger-driven relationship mutation, including score override and delta paths'
);

assert(
  actorDispositionSource.includes('RelationshipsActorIdentityResolverService')
    && actorDispositionSource.includes('resolveInstitutionActorIdentity(')
    && actorDispositionSource.includes('$relationship_mutation_applied = $this->applyTriggerRelationshipMutation(')
    && actorDispositionSource.includes('relationship_trigger_applied'),
  'ActorDispositionService resolves canonical source/target actor identities and records relationship trigger application context'
);

assert(
  encounterExecutorSource.includes('applyDispositionTriggerMutation(')
    && encounterExecutorSource.includes("'attack'")
    && encounterExecutorSource.includes("'negative_effect_spell'")
    && encounterExecutorSource.includes("'talk'")
    && encounterExecutorSource.includes('target_entity_ref'),
  'EncounterActionExecutor emits attack, negative spell, and talk trigger events with direct target context'
);

assert(
  encounterPhaseHandlerSource.includes('routeRequestIntentExecution(')
    && encounterPhaseHandlerSource.includes('diplomacy_critical_success')
    && encounterPhaseHandlerSource.includes('diplomacy_success')
    && encounterPhaseHandlerSource.includes('diplomacy_failure')
    && encounterPhaseHandlerSource.includes('Encounter request against')
    && encounterPhaseHandlerSource.includes('applyDispositionEvent('),
  'EncounterPhaseHandler routes request degree outcomes into deterministic diplomacy trigger events'
);

assert(
  encounterPhaseHandlerSource.includes('routeFeintIntentExecution(')
    && encounterPhaseHandlerSource.includes('deception_critical_success')
    && encounterPhaseHandlerSource.includes('deception_success')
    && encounterPhaseHandlerSource.includes('deception_failure')
    && encounterPhaseHandlerSource.includes('deception_critical_failure')
    && encounterPhaseHandlerSource.includes('Encounter feint against')
    && encounterPhaseHandlerSource.includes('applyDispositionEvent('),
  'EncounterPhaseHandler routes feint degree outcomes into deterministic deception trigger events'
);

assert(
  encounterPhaseHandlerSource.includes('routeCreateDiversionIntentExecution(')
    && encounterPhaseHandlerSource.includes('encounter_create_diversion')
    && encounterPhaseHandlerSource.includes('Encounter create diversion')
    && encounterPhaseHandlerSource.includes('deception_critical_success')
    && encounterPhaseHandlerSource.includes('applyDispositionEvent('),
  'EncounterPhaseHandler routes create-diversion outcomes into deterministic deception trigger events'
);

assert(
  encounterPhaseHandlerSource.includes('routeSenseMotiveIntentExecution(')
    && encounterPhaseHandlerSource.includes('sense_motive_critical_success')
    && encounterPhaseHandlerSource.includes('sense_motive_success')
    && encounterPhaseHandlerSource.includes('sense_motive_failure')
    && encounterPhaseHandlerSource.includes('sense_motive_critical_failure')
    && encounterPhaseHandlerSource.includes('Encounter sense motive against')
    && encounterPhaseHandlerSource.includes('encounter_sense_motive'),
  'EncounterPhaseHandler routes sense-motive outcomes into deterministic sense-motive trigger events'
);

assert(
  encounterPhaseHandlerSource.includes('routePerformIntentExecution(')
    && encounterPhaseHandlerSource.includes('perform_critical_success')
    && encounterPhaseHandlerSource.includes('perform_success')
    && encounterPhaseHandlerSource.includes('perform_failure')
    && encounterPhaseHandlerSource.includes('perform_critical_failure')
    && encounterPhaseHandlerSource.includes('Encounter perform')
    && encounterPhaseHandlerSource.includes('encounter_perform'),
  'EncounterPhaseHandler routes perform outcomes into deterministic perform trigger events'
);

assert(
  encounterPhaseHandlerSource.includes('routeAidIntentExecution(')
    && encounterPhaseHandlerSource.includes('aid_critical_success')
    && encounterPhaseHandlerSource.includes('aid_success')
    && encounterPhaseHandlerSource.includes('aid_failure')
    && encounterPhaseHandlerSource.includes('aid_critical_failure')
    && encounterPhaseHandlerSource.includes('Encounter aid for')
    && encounterPhaseHandlerSource.includes('encounter_aid'),
  'EncounterPhaseHandler routes aid outcomes into deterministic aid trigger events'
);

assert(
  encounterPhaseHandlerSource.includes('routeAidSetupIntentExecution(')
    && encounterPhaseHandlerSource.includes('aid_setup_prepared')
    && encounterPhaseHandlerSource.includes('Encounter aid setup for')
    && encounterPhaseHandlerSource.includes('encounter_aid_setup'),
  'EncounterPhaseHandler routes aid-setup coordination into deterministic trigger events'
);

assert(
  encounterPhaseHandlerSource.includes('routeCommandAnimalIntentExecution(')
    && encounterPhaseHandlerSource.includes('command_animal_critical_success')
    && encounterPhaseHandlerSource.includes('command_animal_success')
    && encounterPhaseHandlerSource.includes('command_animal_failure')
    && encounterPhaseHandlerSource.includes('command_animal_critical_failure')
    && encounterPhaseHandlerSource.includes('encounter_command_animal'),
  'EncounterPhaseHandler routes command-animal outcomes into deterministic command-animal trigger events'
);

assert(
  encounterPhaseHandlerSource.includes('routeAdministerFirstAidIntentExecution(')
    && encounterPhaseHandlerSource.includes('administer_first_aid_critical_success')
    && encounterPhaseHandlerSource.includes('administer_first_aid_success')
    && encounterPhaseHandlerSource.includes('administer_first_aid_failure')
    && encounterPhaseHandlerSource.includes('administer_first_aid_critical_failure')
    && encounterPhaseHandlerSource.includes('encounter_administer_first_aid'),
  'EncounterPhaseHandler routes administer-first-aid outcomes into deterministic care trigger events'
);

assert(
  encounterPhaseHandlerSource.includes('routeTreatPoisonIntentExecution(')
    && encounterPhaseHandlerSource.includes('treat_poison_critical_success')
    && encounterPhaseHandlerSource.includes('treat_poison_success')
    && encounterPhaseHandlerSource.includes('treat_poison_failure')
    && encounterPhaseHandlerSource.includes('treat_poison_critical_failure')
    && encounterPhaseHandlerSource.includes('encounter_treat_poison'),
  'EncounterPhaseHandler routes treat-poison outcomes into deterministic care trigger events'
);

assert(
  encounterPhaseHandlerSource.includes('routeBattleMedicineIntentExecution(')
    && encounterPhaseHandlerSource.includes('battle_medicine_critical_success')
    && encounterPhaseHandlerSource.includes('battle_medicine_success')
    && encounterPhaseHandlerSource.includes('battle_medicine_failure')
    && encounterPhaseHandlerSource.includes('battle_medicine_critical_failure')
    && encounterPhaseHandlerSource.includes('encounter_battle_medicine'),
  'EncounterPhaseHandler routes battle-medicine outcomes into deterministic care trigger events'
);

assert(
  encounterPhaseHandlerSource.includes('routePointOutIntentExecution(')
    && encounterPhaseHandlerSource.includes('point_out_success')
    && encounterPhaseHandlerSource.includes('encounter_point_out')
    && encounterPhaseHandlerSource.includes('Encounter point out for allies against'),
  'EncounterPhaseHandler routes point-out coordination into deterministic trigger events'
);

assert(
  encounterPhaseHandlerSource.includes('routeDemoralizeIntentExecution(')
    && encounterPhaseHandlerSource.includes('intimidation_critical_success')
    && encounterPhaseHandlerSource.includes('intimidation_success')
    && encounterPhaseHandlerSource.includes('intimidation_failure')
    && encounterPhaseHandlerSource.includes('intimidation_critical_failure')
    && encounterPhaseHandlerSource.includes('applyDispositionEvent('),
  'EncounterPhaseHandler routes demoralize degree outcomes into deterministic disposition trigger events'
);

assert(
  roomChatChannelSource.includes('generateChannelNpcReply(')
    && roomChatChannelSource.includes('room_channel_reply')
    && roomChatChannelSource.includes("applyDispositionEvent(")
    && roomChatChannelSource.includes("'conversation'")
    && roomChatChannelSource.includes('target_entity_ref'),
  'RoomChat channel NPC replies route conversation disposition through ActorDispositionService with target context'
);

assert(
  roomChatChannelSource.includes('broadcastNpcEvent(')
    && roomChatChannelSource.includes('room_npc_broadcast_event')
    && roomChatChannelSource.includes('dungeoncrawler_content.actor_disposition_service')
    && roomChatChannelSource.includes('broadcastEventToNpcs(')
    && roomChatChannelSource.includes('applyDispositionEvent('),
  'RoomChat NPC broadcast events prefer ActorDispositionService deterministic mutations with psychology fallback'
);

assert(
  roomChatInterjectionSource.includes('buildNpcInterjectionMessage(')
    && roomChatInterjectionSource.includes('room_interjection')
    && roomChatInterjectionSource.includes("applyDispositionEvent(")
    && roomChatInterjectionSource.includes("'conversation'"),
  'RoomChat NPC interjections route conversation disposition through ActorDispositionService'
);

assert(
  servicesSource.includes('dungeoncrawler_content.encounter_action_executor:')
    && servicesSource.includes("- '@?dungeoncrawler_content.actor_disposition_service'")
    && servicesSource.includes('dungeoncrawler_content.actor_disposition_service:')
    && servicesSource.includes("- '@?dungeoncrawler_content.relationship_attitude_service'")
    && servicesSource.includes("- '@?dungeoncrawler_content.relationships_actor_identity_resolver'"),
  'Service wiring injects trigger and relationship mutation dependencies into runtime execution path'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
