/**
 * @file
 * Regression coverage for V2 legacy service parity helpers in GameShell.
 *
 * Run with:
 *   node tests/hexmap_v2_service_parity_test.js
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

function extractNamedFunctionSource(source, functionName) {
  const anchor = `function ${functionName}(`;
  const start = source.indexOf(anchor);
  if (start === -1) {
    throw new Error(`Could not find function: ${functionName}`);
  }

  let braceStart = -1;
  let parenDepth = 0;
  for (let index = start; index < source.length; index++) {
    const char = source[index];
    if (char === '(') {
      parenDepth++;
    } else if (char === ')') {
      parenDepth = Math.max(0, parenDepth - 1);
    } else if (char === '{' && parenDepth === 0) {
      braceStart = index;
      break;
    }
  }
  if (braceStart === -1) {
    throw new Error(`Could not find function body: ${functionName}`);
  }

  let depth = 0;
  for (let index = braceStart; index < source.length; index++) {
    const char = source[index];
    if (char === '{') {
      depth++;
    } else if (char === '}') {
      depth--;
      if (depth === 0) {
        return source.slice(start, index + 1);
      }
    }
  }

  throw new Error(`Could not find closing brace for function: ${functionName}`);
}

const sourcePath = path.resolve(__dirname, '../js/v2/GameShell.js');
const source = fs.readFileSync(sourcePath, 'utf8');

const helperNames = [
  '_getPresentationObjectDefinitions',
  '_getVisualOccupants',
  '_isVisualOccupantVisible',
  '_parseVisualHexId',
  '_getConnectionRoomId',
  '_getConnectionHex',
  '_getActiveRoomData',
  '_getActiveRoomHex',
  '_buildActiveRoomOccupantSummary',
  '_getObjectDefinition',
  '_buildObstacleMobilityProfile',
  '_getObstacleMobilityAtHex',
  '_getAxialLine',
  '_hasLineOfSight',
  '_getHostileTargets',
  '_findLaunchPlayerEntity',
];

const factory = new Function(`
${helperNames.map((name) => extractNamedFunctionSource(source, name)).join('\n')}
return {
  ${helperNames.join(',\n  ')}
};
`);

const helpers = factory();

function makeEntity(id, components = {}, extras = {}) {
  return {
    id,
    ...extras,
    getComponent(name) {
      return components[name] || null;
    },
  };
}

console.log('\n=== Hexmap V2 legacy service parity helpers ===');

{
  const room = {
    hexes: [
      { q: 0, r: 0, objects: [] },
      { q: 1, r: 0, objects: [{ object_id: 'stone_wall', category: 'wall' }] },
      { q: 2, r: 0, objects: [] },
    ],
  };
  const definitions = {
    stone_wall: {
      category: 'wall',
      movement: { passable: false, blocks_movement: true },
      tags: ['wall'],
    },
  };

  const obstacle = helpers._getObstacleMobilityAtHex(room, definitions, 1, 0);
  assert(obstacle?.passable === false && obstacle?.isWall === true, 'derives obstacle mobility from authored room hex objects');

  const movementSystem = {
    hexDistance(fromQ, fromR, toQ, toR) {
      return Math.max(Math.abs(fromQ - toQ), Math.abs(fromR - toR), Math.abs((fromQ + fromR) - (toQ + toR)));
    },
  };

  const blocked = helpers._hasLineOfSight(0, 0, 2, 0, (q, r) => helpers._getObstacleMobilityAtHex(room, definitions, q, r), movementSystem);
  const clear = helpers._hasLineOfSight(0, 0, 0, 2, () => null, movementSystem);
  assert(blocked === false, 'blocks line of sight through an impassable obstacle');
  assert(clear === true, 'preserves clear line of sight when no obstacle lies on the axial line');
}

{
  const occupants = [
    { room_id: 'room_hall', occupant_type: 'player_character', label: 'Keith', visible: true },
    { room_id: 'room_hall', occupant_type: 'npc', label: 'Innkeeper', hidden: true, detected: false },
    { room_id: 'room_hall', occupant_type: 'creature', label: 'Bat', visible: true },
    { room_id: 'room_hall', occupant_type: 'npc', label: 'Innkeeper', detected: true },
    { room_id: 'room_other', occupant_type: 'npc', label: 'Elsewhere', visible: true },
  ];

  const summary = helpers._buildActiveRoomOccupantSummary(
    'room_hall',
    occupants,
    helpers._isVisualOccupantVisible,
  );

  assert(summary.includes('Party present: Keith'), 'includes visible PCs in the active-room summary');
  assert(summary.includes('NPCs present: Innkeeper'), 'includes detected NPCs while deduping duplicate names');
  assert(summary.includes('Other creatures present: Bat'), 'includes visible creatures in the active-room summary');
  assert(!summary.includes('Elsewhere'), 'excludes occupants from other rooms');
}

{
  const visibleInActiveRoom = helpers._isVisualOccupantVisible(
    { room_id: 'market', visible: false, state: { hidden: false } },
    'market',
  );
  const hiddenInActiveRoom = helpers._isVisualOccupantVisible(
    { room_id: 'market', visible: false, state: { hidden: true, detected: false } },
    'market',
  );
  const visibleElsewhere = helpers._isVisualOccupantVisible(
    { room_id: 'other_room', visible: false, state: { hidden: false } },
    'market',
  );

  assert(visibleInActiveRoom === true, 'keeps active-room occupants visible when legacy visible=false is stale');
  assert(hiddenInActiveRoom === false, 'still hides active-room occupants when hidden and not detected');
  assert(visibleElsewhere === false, 'keeps non-active-room occupants hidden when visible=false');
}

{
  const actor = makeEntity(1, {
    PositionComponent: { q: 0, r: 0 },
    CombatComponent: {
      team: 'player',
      isHostileTo(targetCombat) {
        return String(targetCombat?.team || '') === 'enemy';
      },
    },
  });
  const hostileNear = makeEntity(2, {
    PositionComponent: { q: 1, r: 0 },
    CombatComponent: { team: 'enemy' },
    StatsComponent: { currentHp: 5, isAlive: () => true },
  });
  const hostileFar = makeEntity(3, {
    PositionComponent: { q: 3, r: 0 },
    CombatComponent: { team: 'enemy' },
    StatsComponent: { currentHp: 5, isAlive: () => true },
  });
  const ally = makeEntity(4, {
    PositionComponent: { q: 1, r: 1 },
    CombatComponent: { team: 'player' },
    StatsComponent: { currentHp: 5, isAlive: () => true },
  });
  const entityManager = {
    getEntitiesWith() {
      return [actor, hostileFar, ally, hostileNear];
    },
  };
  const movementSystem = {
    hexDistance(fromQ, fromR, toQ, toR) {
      return Math.max(Math.abs(fromQ - toQ), Math.abs(fromR - toR), Math.abs((fromQ + fromR) - (toQ + toR)));
    },
  };

  const hostileTargets = helpers._getHostileTargets(actor, entityManager, movementSystem, (fromQ, fromR, toQ) => toQ !== 3 || fromQ !== 0 || fromR !== 0);
  assert(hostileTargets.length === 1, 'filters out non-hostile and line-of-sight-blocked combat targets');
  assert(hostileTargets[0].target.id === 2 && hostileTargets[0].distance === 1, 'returns hostile targets sorted by nearest distance');
}

{
  const launchEntity = makeEntity(10, {
    PositionComponent: { q: 4, r: 2 },
    CombatComponent: { team: 'player', isPlayerTeam: () => true },
  }, {
    dcCharacterId: 55,
  });
  const fallbackPlayer = makeEntity(11, {
    PositionComponent: { q: 0, r: 0 },
    CombatComponent: { team: 'player', isPlayerTeam: () => true },
  });
  const entityManager = {
    getEntitiesWith() {
      return [fallbackPlayer, launchEntity];
    },
  };

  const entity = helpers._findLaunchPlayerEntity(entityManager, { start_q: 4, start_r: 2 }, 55);
  assert(entity?.id === 10, 'prefers the player entity standing on the launch start hex');
}

{
  const familiarOnStartHex = makeEntity(12, {
    PositionComponent: { q: 4, r: 2 },
    CombatComponent: { team: 'player', isPlayerTeam: () => true },
  }, {
    dcEntityRef: 'familiar-1033',
    dcCharacterId: 55,
    dcStatePayload: {
      metadata: {
        team: 'player',
        character_id: 55,
        follower_kind: 'familiar',
        owner_character_id: 55,
      },
    },
  });
  const launchPlayer = makeEntity(13, {
    PositionComponent: { q: 5, r: 2 },
    CombatComponent: { team: 'player', isPlayerTeam: () => true },
  }, {
    dcEntityRef: 'pc-723-1033',
    dcCharacterId: 55,
    dcStatePayload: {
      entity_type: 'player_character',
      metadata: {
        team: 'player',
        character_id: 55,
      },
    },
  });
  const entityManager = {
    getEntitiesWith() {
      return [familiarOnStartHex, launchPlayer];
    },
  };

  const entity = helpers._findLaunchPlayerEntity(entityManager, { start_q: 4, start_r: 2 }, 55);
  assert(entity?.id === 13, 'excludes familiar-style allies when resolving the canonical launch player entity');
}

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
