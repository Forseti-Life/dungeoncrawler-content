'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const dungeonShell = read('js/v2/editor/DungeonEditorShell.js');
const statusCard = read('js/v2/editor/EditorDraftStatusCard.js');
const storyline = read('js/gm-storyline-builder.js');
const dungeonSurface = read('src/Service/EditorGm/DungeonEditorGmSurface.php');
const storylineSurface = read('src/Service/EditorGm/StorylineBuilderGmSurface.php');
const harness = read('src/Service/EditorGm/EditorGmHarnessService.php');
const roomChildTool = read('src/Service/EditorGm/Tool/Dungeon/RevalidateChildRoomsTool.php');

assert(dungeonSurface.includes('RevalidateChildRoomsTool'), 'Dungeon GM registers child room validation');
assert(storylineSurface.includes('RevalidateChildDungeonsTool'), 'Storyline GM registers child dungeon validation');
assert(dungeonShell.includes('revalidate_child_rooms'), 'Dungeon sidebar revalidates rooms through the GM tool');
assert(statusCard.includes('Revalidate') && statusCard.includes('onRevalidate'), 'linked room rows expose a revalidation button');
assert(storyline.includes('revalidate_child_dungeons'), 'Storyline linked dungeon rows expose child revalidation');
assert(storyline.includes('Validating…'), 'Storyline revalidation has visible waiting feedback');
assert(!roomChildTool.includes('RoomEditorService'), 'Dungeon tool delegates validation instead of reaching the Room authority');
assert(harness.includes('executeChildValidation'), 'Harness executes lower-tier validation through the child surface');
assert(harness.includes('validationForRoom') && harness.includes('validationForDungeon'), 'Harness calls each owning child agent surface');

console.log('=== Child validation controls ===\nAll child validation contract assertions passed.');
