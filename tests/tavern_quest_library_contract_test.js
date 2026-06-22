/**
 * Focused regression for the canonical tavern quest library counts.
 *
 * Run with:
 *   node tests/tavern_quest_library_contract_test.js
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

const questTemplates = JSON.parse(
  fs.readFileSync(path.resolve(__dirname, '../content/quest_templates.json'), 'utf8')
);
const roomTemplates = JSON.parse(
  fs.readFileSync(
    path.resolve(
      __dirname,
      '../config/examples/templates/dungeoncrawler_content_rooms/default_room_templates.json'
    ),
    'utf8'
  )
);

console.log('\n=== Tavern quest library contract ===');

const wineQuest = questTemplates.find((entry) => entry.template_id === 'gather_wine');
const torchQuest = questTemplates.find((entry) => entry.template_id === 'gather_torch_components');
const tavernRoom = roomTemplates.rows.find((row) => row.room_id === 'tavern_entrance');

assert(
  wineQuest?.objectives_schema?.[0]?.objectives?.[0]?.target_count === 2,
  'gather_wine requires exactly 2 items in the canonical quest library'
);

assert(
  torchQuest?.objectives_schema?.[0]?.objectives?.[0]?.target_count === 2,
  'gather_torch_components requires exactly 2 items in the canonical quest library'
);

const tavernItems = Array.isArray(tavernRoom?.contents_data?.items) ? tavernRoom.contents_data.items : [];
const wineItems = tavernItems.filter((item) => item.quest_association === 'gather_wine');
const torchItems = tavernItems.filter((item) => item.quest_association === 'gather_torch_components');

assert(
  wineItems.length === 2,
  'canonical tavern room contains exactly 2 wine quest items'
);

assert(
  torchItems.length === 2,
  'canonical tavern room contains exactly 2 torch-component quest items'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
