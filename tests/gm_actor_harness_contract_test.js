/**
 * @file
 * Contract coverage for GM actor harness and privileged tool surface.
 *
 * Run with:
 *   node tests/gm_actor_harness_contract_test.js
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

const harnessSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmActorHarnessService.php'),
  'utf8'
);
const runtimeSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmActorRuntimeService.php'),
  'utf8'
);
const transportSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmActorChatTransportService.php'),
  'utf8'
);
const toolSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmToolExecutionService.php'),
  'utf8'
);

console.log('\n=== GM actor harness contract ===');

assert(
  harnessSource.includes("protected const HARNESS_CONTRACT_VERSION = 'gm-actor-harness-v1';"),
  'GM actor harness publishes gm-actor-harness-v1 contract'
);
assert(
  harnessSource.includes('$this->runtime->handlePlayerRoomChat('),
  'GM actor harness delegates room-chat runtime execution through GmActorRuntimeService'
);
assert(
  harnessSource.includes('public function replayRoomChat(')
    && harnessSource.includes('iterations')
    && harnessSource.includes('results'),
  'GM actor harness exposes replay API for repeated room-chat execution'
);
assert(
  runtimeSource.includes("$chat_result['gm_actor_runtime'] = [")
    && runtimeSource.includes("protected const RUNTIME_CONTRACT_VERSION = 'gm-actor-runtime-v1';"),
  'GM runtime emits gm_actor_runtime metadata envelope'
);
assert(
  runtimeSource.includes('$this->chatTransport->postValidatedPlayerRoomChat(')
    && transportSource.includes("$this->roomChatService->postMessage(")
    && transportSource.includes("'_validated_encounter_room_chat' => TRUE"),
  'GM runtime routes room-chat through dedicated GM transport adapter with validated bypass contract'
);
assert(
  toolSource.includes("public const TOOL_CONTRACT_VERSION = 'gm-tool-contract-v1';"),
  'GM tool execution service publishes tool contract version'
);
assert(
  toolSource.includes("'modify_dungeon_state'")
    && toolSource.includes("'modify_storyline_state'")
    && toolSource.includes("'modify_setting_variable'")
    && toolSource.includes("'query_campaign_database'")
    && toolSource.includes("'modify_campaign_character_instance'")
    && toolSource.includes("'modify_campaign_room_state'")
    && toolSource.includes("'modify_campaign_quest_progress'")
    && toolSource.includes("'modify_campaign_storyline_instance'")
    && toolSource.includes("'modify_campaign_relationships'")
    && toolSource.includes("'modify_campaign_item_instances'")
    && toolSource.includes("'modify_campaign_settings_and_flags'")
    && toolSource.includes("'modify_campaign_connections_and_locations'")
    && toolSource.includes("'modify_campaign_storyline_artifacts'")
    && toolSource.includes("'modify_campaign_quest_artifacts'")
    && toolSource.includes("'modify_campaign_encounter_instances'")
    && toolSource.includes("'modify_room_state'")
    && toolSource.includes("'modify_actor_state'")
    && toolSource.includes("'modify_inventory'")
    && toolSource.includes("'modify_quest_state'")
    && toolSource.includes("'modify_encounter_state'")
    && toolSource.includes("'modify_world_flag'"),
  'GM tool execution service defines canonical privileged and campaign instance tool IDs'
);
assert(
  toolSource.includes("'modify_room_state'")
    && toolSource.includes("'modify_actor_state'")
    && toolSource.includes("'modify_inventory'")
    && toolSource.includes("'modify_quest_state'")
    && toolSource.includes("'modify_encounter_state'")
    && toolSource.includes("'modify_world_flag'"),
  'GM tool execution service defines canonical privileged tool IDs'
);
assert(
  toolSource.includes('Unsupported GM tool')
    && toolSource.includes('GM tool execution requires actor_role=gm.')
    && toolSource.includes('gm_actor_id and gm_character_id principal context')
    && toolSource.includes('Campaign table tools require ownership_domain=normalized_tables.')
    && toolSource.includes('Dungeon mutation tools require ownership_domain=dungeon_blob.')
    && toolSource.includes('requires correlation_id in context or payload')
    && toolSource.includes('startTransaction()')
    && toolSource.includes("insert('dc_gm_mutation_audit')")
    && toolSource.includes('applyCampaignTablePatch')
    && toolSource.includes('resolveAllowedTargetTable')
    && toolSource.includes("['update', 'insert', 'upsert', 'delete']")
    && toolSource.includes('Campaign table upsert requires non-empty keys object.')
    && toolSource.includes('query_campaign_database requires non-empty filters object.')
    && toolSource.includes('loadLatestDungeonRecord')
    && toolSource.includes('persistDungeonRecord'),
  'GM tool execution enforces GM authority and supports fail-fast update/insert/upsert/delete mutations'
);
assert(
  toolSource.includes('dc_campaign_storyline_links')
    && toolSource.includes('dc_campaign_storyline_log')
    && toolSource.includes('dc_campaign_objective_refs')
    && toolSource.includes('dc_campaign_locations')
    && toolSource.includes('dc_campaign_quest_log')
    && toolSource.includes('dc_campaign_quest_rewards_claimed')
    && toolSource.includes('dc_campaign_quest_confirmations')
    && toolSource.includes('dc_campaign_encounter_instances')
    && toolSource.includes('dc_campaign_encounter_templates'),
  'GM campaign artifact tools scope updates to approved storyline/quest/encounter tables'
);
assert(
  toolSource.includes('applyDungeonStatePatch')
    && toolSource.includes('applyStorylinePatch'),
  'GM tool execution includes dedicated dungeon and storyline mutation handlers'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
