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
    && commandSource.includes("buildNavigationCapabilitiesWithRoadNetwork("),
  'Snapshot command emits deterministic wayfinding from canonical navigation capabilities'
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
  runnerSource.includes("dc:actor-harness-snapshot")
    && runnerSource.includes("dc:actor-harness-action")
    && runnerSource.includes("dc:gm-actor-run"),
  'Runner uses snapshot/action/chat command tools for gameplay orchestration'
);

assert(
  runnerSource.includes('validate_action_intent_contract')
    && runnerSource.includes('invalid_action_intent_contract:')
    && runnerSource.includes('invalid_decider_response:')
    && runnerSource.includes('action_execution_failed:')
    && runnerSource.includes('chat_execution_failed:')
    && runnerSource.includes('action_intent_params_missing_')
    && runnerSource.includes('"transition": ("target_room_id",)')
    && runnerSource.includes('action_intent_transition_target_same_room')
    && runnerSource.includes('action_intent_transition_no_connected_rooms')
    && runnerSource.includes('action_intent_transition_target_not_connected')
    && runnerSource.includes('action_intent_params_missing_talk_message')
    && runnerSource.includes('Bootstrap mode requires --uid')
    && runnerSource.includes('--uid=')
    && runnerSource.includes('graph.add_edge("execute_action", "assess")')
    && runnerSource.includes('graph.add_edge("execute_chat", "assess")'),
  'Runner validates action contract, requires bootstrap owner uid, and routes every action/chat turn through assess'
);

assert(
  runnerSource.includes('allowed_action_ids')
    && runnerSource.includes('required_action_intent_keys_for_action_mode')
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
  runnerSource.includes('ask_eldric_for_work')
    && runnerSource.includes('ask_marta_for_work')
    && runnerSource.includes('ask_gribbles_for_work')
    && runnerSource.includes('search_room_for_items')
    && runnerSource.includes('turn_in_items_to_eldric')
    && runnerSource.includes('turn_in_items_to_marta')
    && runnerSource.includes('turn_in_items_to_gribbles')
    && runnerSource.includes('ask_eldric_for_more_work')
    && !runnerSource.includes('chat_eldric_more_work')
    && !runnerSource.includes('chat_eldric_storyline_lead')
    && !runnerSource.includes('chat_gribbles_jobs')
    && !runnerSource.includes('chat_eldric_jobs'),
  'Runner hardcodes the Burasco scripted action sequence in the requested order'
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
    && runnerSource.includes('"mode": "chat"')
    && runnerSource.includes('gm_clue_requested'),
  'Runner requests deterministic GM clues after actor-to-actor talk before resuming normal decisioning'
);

assert(
  runnerSource.includes('summarize_issue_payload')
    && runnerSource.includes('"last_result_summary"')
    && runnerSource.includes('issue_summary_path')
    && !runnerSource.includes('github-issues-upsert.py'),
  'Runner writes compact local issue summaries and does not invoke privileged GitHub issue upsert'
);

assert(
  runtimeAdapterSource.includes('getActionAvailabilityForActor')
    && runtimeAdapterSource.includes("'action_contract' => $action_contract"),
  'Snapshot exposes actor-scoped available_actions and action_contract'
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
  runnerSource.includes('FORBIDDEN_ACTOR_RUNTIME_ENV_VARS')
    && runnerSource.includes('ALLOWED_ACTOR_RUNTIME_ENV_VARS')
    && runnerSource.includes('enforce_actor_runtime_secret_boundary()')
    && runnerSource.includes('build_actor_runtime_env()')
    && runnerSource.includes('env=build_actor_runtime_env()')
    && runnerSource.includes('actor_runtime_secret_boundary_violation:')
    && !runnerSource.includes('shell=True'),
  'Runner enforces actor runtime secret boundary and runs subprocesses with a sanitized allowlisted environment'
);

assert(
  runnerSource.includes("genai_wrapper.py")
    && runnerSource.includes("HQ_AGENTIC_BACKEND")
    && !runnerSource.includes("OPENAI_API_KEY")
    && !runnerSource.includes("api.openai.com"),
  'Runner uses shared GenAI routing contract and does not call OpenAI directly'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
