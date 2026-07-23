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
    !source.includes("$connections = $this->mergeConnectionsWithRoomExits($rooms, $connections);"),
    'normalizeDungeonPayload should no longer recover graph connections from room exits during the read path',
  );

  assert(
    source.includes("$decoded = $this->runtimeGraphAssembler->buildRuntimeGraph("),
    'HexMapController should rebuild read-path graph payloads through RuntimeGraphAssemblerService before normalization',
  );

  assert(
    !source.includes('protected function mergeConnectionsWithRoomExits(array $rooms, array $connections): array'),
    'HexMapController should not expose a room-exit connection merge helper once assembler-backed graph input is authoritative',
  );

  assert(
    !source.includes('protected function synthesizeConnectionsFromRoomExits(array $rooms): array'),
    'HexMapController should not synthesize runtime graph connections from room exits on the read path',
  );

  assert(
    !source.includes('prunePayloadConnectionsToMaterializedRooms('),
    'HexMapController should not prune payload connections just because adjacent rooms are not yet materialized',
  );

  assert(
    !source.includes('Hexmap injected self-exit for single-room dungeon payload'),
    'HexMapController should not inject synthetic self-exits to repair missing graph topology',
  );

  assert(
    source.includes('Runtime graph contract violation: campaign graph is missing connector coverage for rooms: %s'),
    'HexMapController should hard-fail when authoritative connector coverage is missing instead of repairing from room exits',
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
