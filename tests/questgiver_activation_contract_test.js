/**
 * @file
 * Focused regressions for questgiver activation and Marta journal quest data.
 *
 * Run with:
 *   node tests/questgiver_activation_contract_test.js
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

const roomChatSource = fs.readFileSync(path.resolve(__dirname, '../src/Service/RoomChatService.php'), 'utf8');
const questTemplates = JSON.parse(fs.readFileSync(path.resolve(__dirname, '../content/quest_templates.json'), 'utf8'));
const roomTemplates = JSON.parse(fs.readFileSync(path.resolve(__dirname, '../config/examples/templates/dungeoncrawler_content_rooms/default_room_templates.json'), 'utf8'));

console.log('\n=== Questgiver activation contract ===');

assert(
  roomChatSource.includes("if ($status === 'lead') {")
    && roomChatSource.includes("->fields(['status' => 'offered'])")
    && roomChatSource.includes("if ($status === 'offered') {")
    && roomChatSource.includes("$this->questTracker->startQuest($campaign_id, $quest_id, $character_id);"),
  'Direct questgiver talk promotes lead quests to offered and then starts them'
);
assert(
  roomChatSource.includes('didQuestgiverSpeakForQuest($campaign_id, $quest, $message_entries)')
    && roomChatSource.includes('protected function didQuestgiverSpeakForQuest'),
  'Dialogue surfacing checks whether the speaking NPC is the quest giver before auto-starting the quest'
);
assert(
  roomChatSource.indexOf('buildAvailableQuestgiverQuestDialogue($campaign_id, $entity_ref, $display_name, $room_id, $dungeon_data)') <
    roomChatSource.indexOf('buildBrokeredStorylineLeadDialogue($campaign_id, $entity_ref, $display_name)'),
  'Direct authored questgiver offers are prioritized before brokered storyline lead chatter'
);

const collectSpellbooks = questTemplates.find((entry) => entry.template_id === 'collect_spellbooks') || {};
assert(collectSpellbooks.name === 'Recover {item_name}', 'Collect Spellbooks template uses the recover journal quest name');
const collectObjective = collectSpellbooks?.objectives_schema?.[0]?.objectives?.[0] || {};
assert(collectObjective.target_count === 1, 'Collect Spellbooks template target_count is fixed at 1');
assert(String(collectObjective.item || '').includes('{item_name}'), 'Collect Spellbooks template still uses item_name placeholder');
assert((collectSpellbooks?.rewards_schema?.xp?.base ?? null) === 73, 'Collect Spellbooks template uses fixed XP reward');
assert((collectSpellbooks?.rewards_schema?.xp?.per_level ?? null) === 0, 'Collect Spellbooks template disables per-level XP scaling');
assert((collectSpellbooks?.rewards_schema?.gold?.base ?? null) === 6, 'Collect Spellbooks template uses fixed gold reward');
assert((collectSpellbooks?.rewards_schema?.gold?.per_level ?? null) === 0, 'Collect Spellbooks template disables per-level gold scaling');
assert(Array.isArray(collectSpellbooks?.rewards_schema?.items) && collectSpellbooks.rewards_schema.items[0]?.item_id === 'healing_potion_minor', 'Collect Spellbooks template uses a fixed healing potion reward');

const tavernRoom = (roomTemplates.rows || []).find((room) => room.room_id === 'tavern_entrance') || {};
const martaQuest = (tavernRoom.contents_data?.npcs || []).flatMap((npc) => npc.quests || []).find((quest) => quest.quest_id === 'collect_spellbooks') || {};
assert(martaQuest.title === "Recover Marta's Journal", 'Marta offers the journal-specific quest title');

const journalItems = (tavernRoom.contents_data?.items || []).filter((item) => item.quest_association === 'collect_spellbooks');
assert(journalItems.length === 1, 'Only one tavern item remains associated to the collect_spellbooks quest');
assert(journalItems[0]?.name === "Marta's Journal", 'The associated tavern quest item is Marta\'s Journal');

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
