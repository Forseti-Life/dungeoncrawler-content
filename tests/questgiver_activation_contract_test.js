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

const roomChatSource = require('./helpers/php-source.js').readGmPipelineSource();
// Lead-to-offered promotion and quest start moved out of the GM pipeline into
// the dedicated quest touchpoint service.
const questTouchpointSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/QuestTouchpointService.php'),
  'utf8'
);
const questTemplates = JSON.parse(fs.readFileSync(path.resolve(__dirname, '../content/quest_templates.json'), 'utf8'));
const roomTemplates = JSON.parse(fs.readFileSync(path.resolve(__dirname, '../config/examples/templates/dungeoncrawler_content_rooms/default_room_templates.json'), 'utf8'));

console.log('\n=== Questgiver activation contract ===');

assert(
  questTouchpointSource.includes("if (in_array($status, ['lead', 'available'], TRUE)) {")
    && questTouchpointSource.includes("'offered',")
    && questTouchpointSource.includes('$this->storylineQuestLifecycleService->setQuestStatusByQuestId(')
    && questTouchpointSource.includes('if ($this->questTracker->startQuest($campaign_id, $quest_id, $character_id)) {'),
  'Direct questgiver talk promotes lead quests to offered and then starts them'
);
assert(
  roomChatSource.includes('didQuestgiverSpeakForQuest($campaign_id, $quest, $message_entries)')
    && roomChatSource.includes('protected function didQuestgiverSpeakForQuest'),
  'Dialogue surfacing checks whether the speaking NPC is the quest giver before auto-starting the quest'
);
assert(
  roomChatSource.indexOf('buildAvailableQuestgiverQuestDialogue($campaign_id, $entity_ref, $display_name, $room_id, $dungeon_data, $character_id)') <
    roomChatSource.indexOf('buildBrokeredStorylineLeadDialogue($campaign_id, $entity_ref, $display_name, $player_message, $room_id, $dungeon_data, $character_id)'),
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
// The single-journal seed was deliberately expanded back into a multi-candidate
// spellbook hunt (commit b266af217ad, "Expand Marta and Gribbles default starter
// seeds"). target_count stays 1, so any one candidate completes the quest.
assert(martaQuest.title === 'Recover Lost Spellbook', 'Marta offers the spellbook recovery quest');

const spellbookItems = (tavernRoom.contents_data?.items || []).filter((item) => item.quest_association === 'collect_spellbooks');
assert(spellbookItems.length > 0, 'Tavern seeds at least one item associated to the collect_spellbooks quest');
assert(
  spellbookItems.some((item) => item.name === "Marta's Journal"),
  "Marta's Journal remains one of the collect_spellbooks candidates"
);
assert(
  spellbookItems.every((item) => item.type === 'collectible_item'),
  'Every collect_spellbooks candidate is a collectible item'
);
assert(
  spellbookItems.every((item) => (item.tags || []).includes('collect_spellbooks')),
  'Every collect_spellbooks candidate carries the quest tag'
);
assert(
  new Set(spellbookItems.map((item) => item.content_id)).size === spellbookItems.length,
  'collect_spellbooks candidates have unique content ids'
);
assert(
  spellbookItems.every((item) => Number.isFinite(Number(item.position?.q)) && Number.isFinite(Number(item.position?.r))),
  'Every collect_spellbooks candidate is placed on a hex'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
