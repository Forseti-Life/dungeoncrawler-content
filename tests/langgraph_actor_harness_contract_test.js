/**
 * @file
 * Contract checks for LangGraph actor harness deterministic wayfinding.
 *
 * Run with:
 *   node tests/langgraph_actor_harness_contract_test.js
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

const commandSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Commands/ActorHarnessLangGraphCommands.php'),
  'utf8'
);
const runnerSource = fs.readFileSync(
  path.resolve(__dirname, '../scripts/langgraph-actor-harness-run.py'),
  'utf8'
);
const scriptedSequenceSource = fs.readFileSync(
  path.resolve(__dirname, '../scripts/langgraph_actor_harness/scripted_sequence.py'),
  'utf8'
);
const securityBoundarySource = fs.readFileSync(
  path.resolve(__dirname, '../scripts/langgraph_actor_harness/security_boundary.py'),
  'utf8'
);
const burascoScriptConfigRaw = fs.readFileSync(
  path.resolve(__dirname, '../config/harness/burasco-script-v1.json'),
  'utf8'
);
const burascoScriptConfig = JSON.parse(burascoScriptConfigRaw);
const harnessDecisionSchemaRaw = fs.readFileSync(
  path.resolve(__dirname, '../config/schemas/harness_decision.schema.json'),
  'utf8'
);
const combinedHarnessSource = [runnerSource, scriptedSequenceSource, securityBoundarySource].join('\n');
const runtimeAdapterSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/PlayerAgentRuntimeAdapter.php'),
  'utf8'
);
const gameCoordinatorSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GameCoordinatorService.php'),
  'utf8'
);
const roomChatServiceSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatService.php'),
  'utf8'
);
const roomChatDeterminismSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatServiceIntentAndDeterminismTrait.php'),
  'utf8'
);
const navigationRuntimeSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/NavigationRuntimeService.php'),
  'utf8'
);
const mapGeneratorSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/MapGeneratorService.php'),
  'utf8'
);
const dungeonPayloadPersistenceSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/DungeonPayloadStatePersistenceService.php'),
  'utf8'
);
const encounterPhaseHandlerSource = [
  '../src/Service/EncounterPhaseHandler.php',
  '../src/Service/EncounterPhaseHandlerRouteExecutionSupportTrait.php',
  '../src/Service/EncounterPhaseHandlerRouteExecutionCorePartATrait.php',
  '../src/Service/GameEventLogger.php',
]
  .map((filePath) => fs.readFileSync(path.resolve(__dirname, filePath), 'utf8'))
  .join('\n');
const drushServices = fs.readFileSync(
  path.resolve(__dirname, '../drush.services.yml'),
  'utf8'
);

console.log('\n=== LangGraph actor harness contract ===');

assert(
  drushServices.includes('dungeoncrawler_content.commands.actor_harness_langgraph')
    && drushServices.includes("@dungeoncrawler_content.navigation_service"),
  'Drush command wiring includes actor harness service and navigation dependency'
);

assert(
  commandSource.includes("deterministic_wayfinding")
    && commandSource.includes("resolveDeterministicWayfinding(")
    && commandSource.includes("buildNavigationCapabilitiesWithRoadNetwork(")
    && commandSource.includes("deterministic_actor_decision")
    && commandSource.includes("deterministic_actor_process_flow"),
  'Snapshot command emits deterministic wayfinding plus portable deterministic actor-process-flow decisions'
);

assert(
  commandSource.includes("no_available_quest_destination_capability")
    && commandSource.includes("extractObjectiveDestinationReference("),
  'Command enforces unresolved quest destination contract with explicit failure reason'
);

assert(
  runnerSource.includes("deterministic_wayfinding_transition")
    && runnerSource.includes("objective_wayfinding_unresolved"),
  'LangGraph runner prioritizes deterministic transition and hard-fails unresolved waypoint objectives'
);

assert(
  runnerSource.includes("deterministic_actor_decision")
    && runnerSource.includes("invalid_deterministic_actor_decision_contract:")
    && runnerSource.includes('stamp_decision_metadata(state, deterministic_actor_decision)'),
  'LangGraph runner consumes portable deterministic actor-process-flow decisions before LLM fallback'
);

assert(
  runnerSource.includes("dc:actor-harness-snapshot")
    && runnerSource.includes("dc:actor-harness-action"),
  'Runner uses snapshot/action command tools for gameplay orchestration'
);

assert(
  runnerSource.includes('validate_tool_decision_contract')
    && runnerSource.includes('invalid_tool_decision_contract:')
    && runnerSource.includes('invalid_decider_response:')
    && runnerSource.includes('action_execution_failed:')
    && runnerSource.includes('tool_payload_params_missing_')
    && runnerSource.includes('"transition": ("target_room_id",)')
    && runnerSource.includes('tool_payload_transition_target_same_room')
    && runnerSource.includes('tool_payload_params_missing_talk_message')
    && runnerSource.includes('Bootstrap mode requires --uid')
    && runnerSource.includes('--uid=')
    && runnerSource.includes('graph.add_edge("execute_action", "assess")'),
  'Runner validates tool-decision contract, requires bootstrap owner uid, and routes execution turns through assess'
);

assert(
  runnerSource.includes('required_tool_payload_keys_for_tool_decision')
    && runnerSource.includes('HARNESS_DECIDER_MAX_TOKENS')
    && runnerSource.includes('summarize_active_objective')
    && runnerSource.includes('summarize_quest_context')
    && runnerSource.includes('summarize_storyline_context')
    && runnerSource.includes('objective_action')
    && runnerSource.includes('source_template_id')
    && runnerSource.includes('current_phase')
    && runnerSource.includes('normalize_decision_for_harness_actor'),
  'Runner prompt enforces explicit action-intent schema with compact objective-focused context'
);

assert(
  harnessDecisionSchemaRaw.includes('"title": "Harness Decision Contract"')
    && harnessDecisionSchemaRaw.includes('"decision_type"')
    && harnessDecisionSchemaRaw.includes('"tool_payload"'),
  'Shared harness decision schema is present for cross-language contract alignment'
);

assert(
  runnerSource.includes('load_scripted_testing_steps(REPO_ROOT)')
    && scriptedSequenceSource.includes('burasco-script-v1.json')
    && Array.isArray(burascoScriptConfig.steps)
    && burascoScriptConfig.steps.some((step) => step.id === 'ask_eldric_any_work_or_quests')
    && burascoScriptConfig.steps.some((step) => step.id === 'ask_marta_any_work')
    && burascoScriptConfig.steps.some((step) => step.id === 'ask_gribbles_any_work')
    && burascoScriptConfig.steps.some((step) => step.id === 'search_room_for_items')
    && burascoScriptConfig.steps.some((step) => step.id === 'ask_gribbles_your_stuff')
    && burascoScriptConfig.steps.some((step) => step.id === 'ask_marta_your_stuff')
    && burascoScriptConfig.steps.some((step) => step.id === 'ask_eldric_your_stuff')
    && burascoScriptConfig.steps.some((step) => step.id === 'ask_eldric_any_other_quests')
    && burascoScriptConfig.steps.some((step) => step.id === 'navigate_to_absalom_streets')
    && burascoScriptConfig.steps.some((step) => step.id === 'navigate_to_grandmas_parlor')
    && burascoScriptConfig.steps.some((step) => step.id === 'ask_grandma_any_work_for_me')
    && burascoScriptConfig.steps.some((step) => step.id === 'navigate_back_to_absalom_streets')
    && burascoScriptConfig.steps.some((step) => step.id === 'navigate_to_graveyard')
    && burascoScriptConfig.steps.some((step) => step.id === 'navigate_to_crypt_entrance')
    && burascoScriptConfig.steps.some((step) => step.id === 'ask_what_are_we_doing_here')
    && !burascoScriptConfigRaw.includes('chat_eldric_more_work')
    && !burascoScriptConfigRaw.includes('chat_eldric_storyline_lead')
    && !burascoScriptConfigRaw.includes('chat_gribbles_jobs')
    && !burascoScriptConfigRaw.includes('chat_eldric_jobs'),
  'Runner externalizes the Burasco scripted action sequence into versioned config with the requested order'
);

assert(
  runnerSource.includes('normalize_objective_list(quest.get("current_objectives"))')
    && !runnerSource.includes('normalize_objective_list(quest.get("objective_states"))')
    && !runnerSource.includes('normalize_objective_list(quest.get("generated_objectives"))')
    && runnerSource.includes('authoritative runtime objective feed')
    && runnerSource.includes('SCRIPTED_TARGET_QUEST_TEMPLATES')
    && runnerSource.includes('validate_scripted_target_state')
    && runnerSource.includes('scripted_target_state_complete')
    && runnerSource.includes('scripted_target_state_incomplete:')
    && runnerSource.includes('scripted_target_state_missing_quests:')
    && runnerSource.includes('DEFAULT_STORYLINE_QUEST_ID')
    && runnerSource.includes('build_default_storyline_objective')
    && runnerSource.includes('resolve_visible_eldric')
    && runnerSource.includes('deterministic_default_storyline_seed')
    && runnerSource.includes('deterministic_default_storyline_seed_end_turn')
    && runnerSource.includes('"type": "talk"')
    && runnerSource.includes('"target": target_entity_instance_id')
    && runnerSource.includes('quest_context')
    && runnerSource.includes('storyline_context')
    && runnerSource.includes('Manual operator analysis helper')
    && runnerSource.includes('campaign-analysis.py collects campaign logs')
    && runnerSource.includes('not passed to the decider runtime')
    && runnerSource.includes('default_storyline_seed_unresolved')
    && runnerSource.includes('default_storyline_seed_checks')
    && runnerSource.includes('no_open_objectives')
    && runnerSource.includes('if current_objective is not None:')
    && runnerSource.includes('current_objective: dict[str, Any] | None'),
  'Objective selector standardizes on current_objectives and seeds Eldric storyline objective via targeted action when none are open'
);

assert(
  runnerSource.includes('actor_talked_to_other_actor')
    && runnerSource.includes('deterministic_gm_clue_request_after_actor_talk')
    && runnerSource.includes('"type": "talk"')
    && runnerSource.includes('gm_clue_requested'),
  'Runner requests deterministic GM clues via talk action after actor-to-actor talk before resuming normal decisioning'
);

assert(
  runnerSource.includes('summarize_issue_payload')
    && runnerSource.includes('"last_result_summary"')
    && runnerSource.includes('issue_summary_path')
    && !runnerSource.includes('github-issues-upsert.py'),
  'Runner writes compact local issue summaries and does not invoke privileged GitHub issue upsert'
);

assert(
  runtimeAdapterSource.includes('getRuntimeReadState($campaign_id, $actor_id)')
    && runtimeAdapterSource.includes("$available_actions = is_array($state_payload['available_actions'] ?? NULL)")
    && runtimeAdapterSource.includes("$action_contract = is_array($state_payload['action_contract'] ?? NULL)")
    && runtimeAdapterSource.includes("'available_actions' => $available_actions")
    && runtimeAdapterSource.includes("'action_contract' => $action_contract"),
  'Snapshot exposes actor-scoped available_actions and action_contract from canonical runtime read state'
);

assert(
  gameCoordinatorSource.includes('FORBIDDEN_INTENT_CAPABILITY_KEYS')
    && gameCoordinatorSource.includes('validateActorCapabilityBoundary($intent)')
    && gameCoordinatorSource.includes('actor_capability_violation:')
    && gameCoordinatorSource.includes('findForbiddenCapabilityKeyPath')
    && gameCoordinatorSource.includes("'command'")
    && gameCoordinatorSource.includes("'subprocess'"),
  'Coordinator enforces deny-by-default actor capability gate at canonical action ingress'
);

assert(
  roomChatDeterminismSource.includes('function hasVisitedDestinationName')
    && roomChatServiceSource.includes('enforceTraitContracts')
    && roomChatServiceSource.includes('missing required method')
    && roomChatServiceSource.includes('getResolvedRoomExits() is required')
    && roomChatServiceSource.includes('buildCanonicalNavigationActionPayload() is required')
    && roomChatDeterminismSource.includes('navigationRuntimeService->buildCanonicalNavigationActionPayload')
    && navigationRuntimeSource.includes('public function buildCanonicalNavigationActionPayload'),
  'RoomChat deterministic trait dependencies are explicitly contract-checked at service construction'
);

assert(
  combinedHarnessSource.includes('FORBIDDEN_ACTOR_RUNTIME_ENV_VARS')
    && combinedHarnessSource.includes('ALLOWED_ACTOR_RUNTIME_ENV_VARS')
    && runnerSource.includes('enforce_actor_runtime_secret_boundary()')
    && runnerSource.includes('build_actor_runtime_env()')
    && runnerSource.includes('env=build_actor_runtime_env()')
    && combinedHarnessSource.includes('actor_runtime_secret_boundary_violation:')
    && !combinedHarnessSource.includes('shell=True'),
  'Runner and shared security module enforce actor runtime secret boundary with a sanitized allowlisted environment'
);

assert(
  runnerSource.includes("genai_wrapper.py")
    && runnerSource.includes("HQ_AGENTIC_BACKEND")
    && !runnerSource.includes("OPENAI_API_KEY")
    && !runnerSource.includes("api.openai.com"),
  'Runner uses shared GenAI routing contract and does not call OpenAI directly'
);

assert(
  dungeonPayloadPersistenceSource.includes("unset($payload['rooms'], $payload['connections'], $payload['entities'])")
    && dungeonPayloadPersistenceSource.includes('room_ids cannot be empty')
    && dungeonPayloadPersistenceSource.includes('active_room_id %s is not present in room_ids'),
  'Dungeon payload persistence enforces identifier-only storage and hard-fails invalid room membership'
);

assert(
  mapGeneratorSource.includes('normalizeCampaignRoomContentsReferences')
    && mapGeneratorSource.includes('contents_data.%s[%d] is missing content identifier'),
  'Campaign room persistence normalizes contents_data to identifier-oriented references and hard-fails missing identifiers'
);

assert(
  encounterPhaseHandlerSource.includes('resolveLeadSeekTalkTargetEntityId')
    && encounterPhaseHandlerSource.includes('$lead_seek_counts[$lead_source_id]')
    && encounterPhaseHandlerSource.includes('lead_source_id')
    && !encounterPhaseHandlerSource.includes('$lead_seek_counts[$actor_id]'),
  'Room-scene social progression tracks lead-seeking exhaustion by talk target lead-source id, not acting actor id'
);

assert(
  encounterPhaseHandlerSource.includes("'room_id' => $resolved_room_id !== '' ? $resolved_room_id : NULL")
    && encounterPhaseHandlerSource.includes("'room_id' => $room_id !== '' ? $room_id : NULL"),
  'Encounter Search events stamp room_id so transcript filtering stays room-scoped across room transitions'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
