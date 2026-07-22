/**
 * Contract coverage for starter tavern seed standardization.
 *
 * Run with:
 *   node tests/starter_tavern_seed_contract_test.js
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

function loadJson(relativePath) {
  return JSON.parse(
    fs.readFileSync(path.resolve(__dirname, '..', relativePath), 'utf8')
  );
}

console.log('\n=== Starter tavern seed contract ===');

const roomTemplates = loadJson('config/examples/templates/dungeoncrawler_content_rooms/default_room_templates.json');
const roomStates = loadJson('config/examples/templates/dungeoncrawler_content_room_states/default_room_state_templates.json');
const logTemplates = loadJson('config/examples/templates/dungeoncrawler_content_log/default_log_templates.json');
const dungeonTemplates = loadJson('config/examples/templates/dungeoncrawler_content_dungeons/default_dungeon_templates.json');
const characterTemplates = loadJson('config/examples/templates/dungeoncrawler_content_characters/default_character_templates.json');

assert(
  (roomTemplates.rows || []).some((row) => row.room_id === 'tavern_entrance'),
  'starter room templates include tavern_entrance'
);

assert(
  !(roomTemplates.rows || []).some((row) => row.room_id === 'tpl_room_tavern_entrance'),
  'starter room templates do not include legacy tpl_room_tavern_entrance'
);

assert(
  (roomStates.rows || []).some((row) => row.room_id === 'tavern_entrance'),
  'starter room state templates use tavern_entrance'
);

assert(
  !(roomStates.rows || []).some((row) => row.room_id === 'tpl_room_tavern_entrance'),
  'starter room state templates do not use legacy tpl_room_tavern_entrance'
);

assert(
  (logTemplates.rows || []).some((row) => row.room_id === 'tavern_entrance'),
  'starter log templates use tavern_entrance'
);

assert(
  !(logTemplates.rows || []).some((row) => row.room_id === 'tpl_room_tavern_entrance'),
  'starter log templates do not use legacy tpl_room_tavern_entrance'
);

assert(
  dungeonTemplates.rows?.[0]?.dungeon_data?.entry_room === 'tavern_entrance',
  'starter dungeon template entry room is tavern_entrance'
);

const starterNpcRefs = (characterTemplates.rows || [])
  .filter((row) => ['tavern_keeper', 'scholar_npc', 'scholar'].includes(row.instance_id))
  .map((row) => row.location_ref);

assert(
  starterNpcRefs.length === 3 && starterNpcRefs.every((locationRef) => locationRef === 'tavern_entrance'),
  'starter NPC templates all anchor to tavern_entrance'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
