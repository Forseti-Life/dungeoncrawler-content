'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');

const roomService = read('src/Service/RoomEditorService.php');
const dungeonService = read('src/Service/DungeonEditorService.php');
const controller = read('src/Controller/RoomEditorController.php');
const shell = read('js/v2/editor/RoomEditorShell.js');
const dungeonShell = read('js/v2/editor/DungeonEditorShell.js');
const projection = read('js/v2/editor/DungeonMapProjection.js');
const entry = read('js/room-editor.js');

assert(roomService.includes("['district_drafts'] ="), 'street room drafts expose editable parent district drafts');
assert(dungeonService.includes('districtDraftsForRoom'), 'district draft discovery is owned by DungeonEditorService');
assert(controller.includes('dungeonDescribe') && controller.includes('dungeonCommand'), 'Room Editor uses the existing Dungeon Editor read and command APIs');
assert(shell.includes('restore_published_base'), 'untouched stale street drafts restore the dungeon-pinned room version');
assert(shell.includes("this.bus.emit('map:changed'"), 'district rooms are projected onto the Room Editor canvas');
assert(shell.includes('projectDungeonMap(') && dungeonShell.includes('projectDungeonMap('), 'Dungeon and Room editors share one map projection');
assert(projection.includes('coordinateSpec') && projection.includes('basePlacementId'), 'the shared projection supports room-local district composition');
assert(shell.includes("this._sendDistrictCommand(\n      'move_room_placement'"), 'district rooms move through the Dungeon Editor command authority');
assert(shell.includes("this._sendDistrictCommand(\n      'rotate_room_placement'"), 'district rooms rotate through the Dungeon Editor command authority');
assert(entry.includes('20261128-district-composition-1'), 'the Room Editor entry point busts the district-composition module cache');

console.log('=== Room Editor district composition ===\nAll district composition contract assertions passed.');
