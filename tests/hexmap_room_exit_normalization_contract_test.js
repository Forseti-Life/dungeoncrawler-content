/*
 * Contract test: HexMapController must preserve per-room exits and derive
 * runtime connections from those exits when legacy/global connections are absent.
 */
const assert = require('assert');
const fs = require('fs');
const path = require('path');

(function run() {
  const sourcePath = path.join(__dirname, '..', 'src', 'Controller', 'HexMapController.php');
  const source = fs.readFileSync(sourcePath, 'utf8');

  assert(
    source.includes("'exits' => $this->normalizeRoomExits("),
    'normalizeDungeonPayload should preserve canonical room exits on normalized rooms',
  );

  assert(
    source.includes("if ($connections === []) {\n      $connections = $this->synthesizeConnectionsFromRoomExits($rooms);\n    }"),
    'normalizeDungeonPayload should synthesize connection rows from room exits when global connections are absent',
  );

  assert(
    source.includes('protected function synthesizeConnectionsFromRoomExits(array $rooms): array'),
    'HexMapController should expose a dedicated room-exit connection synthesis helper',
  );

  assert(
    source.includes("return trim((string) ($exit['target_room_id'] ?? '')) !== '';"),
    'HexMapController exit validation should honor canonical per-room exits when global connections are incomplete',
  );

  assert(
    source.includes("'hexmapDungeonData' => $client_dungeon_payload"),
    'hexmap_v2 page bootstrap should attach the slim client dungeon payload instead of the full normalized dungeon blob',
  );

  assert(
    source.includes("'dungeon_payload' => $this->buildClientBootstrapDungeonPayload($hexmap_state['dungeon_payload'])"),
    'visual-state bundle responses should return the same slim dungeon bootstrap contract',
  );

  assert(
    source.includes('protected function buildClientBootstrapDungeonPayload(array $dungeon_payload): array'),
    'HexMapController should define a dedicated slim bootstrap payload helper',
  );

  assert(
    source.includes("'map_visual_state' => $client_visual_map_state"),
    'hexmap_v2 page bootstrap should attach the slim client visual state payload',
  );

  assert(
    source.includes("'map_visual_state' => $this->buildClientBootstrapMapVisualState($hexmap_state['map_visual_state'])"),
    'visual-state bundle responses should return the same slim visual state payload',
  );

  assert(
    source.includes('protected function buildClientBootstrapMapVisualState(array $visual_map_state): array'),
    'HexMapController should define an active-room-only visual bootstrap helper',
  );

  console.log('OK hexmap room exit normalization contract');
})();
