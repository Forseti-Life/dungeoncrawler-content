/**
 * @file
 * Contract test: gathered room NPC profiles should carry
 * disposition-authoritative attitudes.
 *
 * Run with:
 *   node tests/room_chat_npc_profile_disposition_cutover_contract_test.js
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

const dialogueSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatServiceNpcDialogueAndQuestLeadTrait.php'),
  'utf8',
);
const conversationSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatServiceConversationStateAndQuestActivationTrait.php'),
  'utf8',
);
const channelSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatServiceChannelAndSessionTrait.php'),
  'utf8',
);

console.log('\n=== Room chat NPC profile disposition cutover contract ===');

assert(
  dialogueSource.includes('$this->enrichGatheredNpcProfileWithDisposition($campaign_id, $ref, $entity, $profile)')
    && dialogueSource.includes('protected function gatherRoomNpcsWithProfiles(int $campaign_id, string $room_id, array $dungeon_data): array'),
  'Room NPC gather flow enriches profile attitude through disposition authority'
);

assert(
  conversationSource.includes('protected function enrichGatheredNpcProfileWithDisposition(')
    && conversationSource.includes('$resolved_attitude = $this->resolveActorDispositionAttitude($campaign_id, $entity_ref, [')
    && conversationSource.includes("$profile['attitude'] = $resolved_attitude;"),
  'Profile enrichment helper resolves and writes canonical disposition attitude'
);

assert(
  channelSource.includes('$initial_attitude = $this->resolveActorDispositionAttitude(')
    && channelSource.includes("'initial_attitude' => $initial_attitude,"),
  'Direct actor-channel profile seeding uses disposition-authoritative initial attitude'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
