/*
 * Contract test: V2 room connections come from per-room exits.
 */
const assert = require('assert');
const fs = require('fs');
const path = require('path');

function extractBuildRoomConnections(gameShellSource) {
  const marker = 'function _buildRoomConnections(roomId, mapVisualState)';
  const start = gameShellSource.indexOf(marker);
  if (start < 0) {
    throw new Error('Could not find function _buildRoomConnections(roomId, mapVisualState) in GameShell.js');
  }

  const openBrace = gameShellSource.indexOf('{', start);
  if (openBrace < 0) {
    throw new Error('Could not find opening brace for _buildRoomConnections');
  }

  let i = openBrace + 1;
  let depth = 1;
  while (i < gameShellSource.length && depth > 0) {
    const ch = gameShellSource[i];
    if (ch === '{') depth += 1;
    else if (ch === '}') depth -= 1;
    i += 1;
  }

  if (depth !== 0) {
    throw new Error('Unbalanced braces while extracting _buildRoomConnections');
  }

  const body = gameShellSource.slice(openBrace + 1, i - 1);
  // eslint-disable-next-line no-new-func
  return new Function('roomId', 'mapVisualState', body);
}

(function run() {
  const srcPath = path.join(__dirname, '..', 'js', 'v2', 'GameShell.js');
  const src = fs.readFileSync(srcPath, 'utf8');
  const buildRoomConnections = extractBuildRoomConnections(src);

  const mapVisualState = {
    topology: {
      rooms: {
        'room-a': {
          name: 'room-a',
          exits: [
            {
              connection_id: 'c1',
              type: 'open_passage',
              target_room_id: 'room-b',
              origin_hex: { hex_id: 'room-a:0:0', q: 0, r: 0 },
              target_hex: { hex_id: 'room-b:1:0', q: 1, r: 0 },
              is_passable: true,
              is_discovered: true,
              visibility_state: 'visible',
            },
          ],
        },
        'room-b': {
          name: 'room-b',
          exits: [],
        },
      },
      // Legacy global connections should not be required anymore.
      connections: [
        {
          connection_id: 'legacy',
          from_room_id: 'room-a',
          to_room_id: 'room-z',
          type: 'open_passage',
          is_passable: false,
        },
      ],
    },
  };

  const connections = buildRoomConnections('room-a', mapVisualState);
  assert.strictEqual(connections.length, 1);
  assert.deepStrictEqual(connections[0], {
    connection_id: 'c1',
    room_id: 'room-b',
    room_name: 'room-b',
    type: 'open_passage',
  });

  console.log('OK room exits contract');
})();
