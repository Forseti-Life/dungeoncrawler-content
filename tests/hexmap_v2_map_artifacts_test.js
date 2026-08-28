/**
 * @file
 * Regression coverage for V2 map artifact bootstrap.
 *
 * Run with:
 *   node tests/hexmap_v2_map_artifacts_test.js
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

// Projection helpers were extracted out of GameShell.js into
// shell/GameShellProjectionHelpers.js. Read both so source extraction keeps
// working regardless of which file currently hosts the function.
const sourcePaths = [
  path.resolve(__dirname, '../js/v2/GameShell.js'),
  path.resolve(__dirname, '../js/v2/shell/GameShellProjectionHelpers.js'),
];
const source = sourcePaths.map((p) => fs.readFileSync(p, 'utf8')).join('\n\n');

const factory = new Function(`
${extractNamedFunctionSource(source, '_isPlainObject')}
${extractNamedFunctionSource(source, '_getPresentationObjectDefinitions')}
${extractNamedFunctionSource(source, '_getVisualOccupants')}
${extractNamedFunctionSource(source, '_isVisualOccupantVisible')}
${extractNamedFunctionSource(source, '_buildRenderableEntityBlueprints')}
${extractNamedFunctionSource(source, '_buildVisualOccupantIndex')}
${extractNamedFunctionSource(source, '_resolveVisualOccupant')}
${extractNamedFunctionSource(source, '_normalizeRenderableEntityType')}
${extractNamedFunctionSource(source, '_normalizeRenderableEntityTeam')}
${extractNamedFunctionSource(source, '_buildRenderableEntityKey')}
${extractNamedFunctionSource(source, '_buildRenderableProjectionKey')}
${extractNamedFunctionSource(source, '_buildLogicalActorIdentityKey')}
${extractNamedFunctionSource(source, '_buildActorPortraitSpriteId')}
return {
  _buildRenderableEntityBlueprints,
};
`);

const { _buildRenderableEntityBlueprints } = factory();

console.log('\n=== Hexmap V2 map artifact bootstrap ===');

{
  const dungeonData = {
    entities: [
      {
        entity_type: 'npc',
        instance_id: 'npc-1',
        entity_ref: { content_id: 'barkeep_npc', content_type: 'npc' },
        placement: { room_id: 'room_tavern', hex: { q: 1, r: 0 } },
        state: {
          metadata: {
            display_name: 'Barkeep',
            team: 'neutral',
            movement_speed: 25,
          },
        },
      },
      {
        entity_type: 'item',
        instance_id: 'clue-1',
        entity_ref: { content_id: 'quest_clue', content_type: 'item' },
        placement: { room_id: 'room_tavern', hex: { q: 2, r: 0 } },
        state: {
          metadata: {
            display_name: 'Quest Clue',
            collectible: true,
          },
        },
      },
    ],
  };

  const mapVisualState = {
    presentation: {
      object_definitions: {
        tavern_table: {
          label: 'Tavern Table',
          category: 'obstacle',
          visual: { color: '#8b5e3c' },
        },
        quest_clue: {
          label: 'Quest Clue',
          category: 'quest_item',
          visual: { color: '#f59e0b' },
        },
        barkeep_npc: {
          label: 'Barkeep',
          category: 'npc',
          visual: { color: '#22c55e' },
        },
      },
    },
    occupants: {
      entities: [
        { occupant_id: 'npc-1', content_id: 'barkeep_npc', visible: true },
        { occupant_id: 'clue-1', content_id: 'quest_clue', visible: true },
      ],
    },
    topology: {
      rooms: {
        room_tavern: {
          hexes: [
            {
              q: 0,
              r: 0,
              objects: [
                {
                  object_id: 'tavern_table',
                  label: 'Tavern Table',
                  category: 'obstacle',
                  visual: { color: '#8b5e3c' },
                },
              ],
            },
            {
              q: 2,
              r: 0,
              objects: [
                {
                  object_id: 'quest_clue',
                  label: 'Quest Clue',
                  category: 'quest_item',
                  collectible: true,
                  visual: { color: '#f59e0b' },
                },
              ],
            },
          ],
        },
      },
    },
  };

  const blueprints = _buildRenderableEntityBlueprints(
    dungeonData,
    'room_tavern',
    { character_id: 0 },
    mapVisualState,
  );

  assert(blueprints.length === 3, 'includes payload occupants plus non-duplicated room hex objects');

  const barkeep = blueprints.find((entry) => entry.instanceId === 'npc-1');
  assert(!!barkeep, 'keeps NPC payload entities in the active room');
  assert(barkeep?.entityType === 'npc', 'maps NPC payload entity types correctly');

  const clue = blueprints.find((entry) => entry.instanceId === 'clue-1');
  assert(!!clue, 'keeps item payload entities in the active room');
  assert(clue?.entityType === 'item', 'maps collectible payload entities to item render types');

  const table = blueprints.find((entry) => entry.instanceId === 'room-object:room_tavern:0:0:tavern_table:0');
  assert(!!table, 'adds authored room hex objects as renderable map artifacts');
  assert(table?.entityType === 'obstacle', 'maps room obstacle objects to obstacle render types');
}

{
  const dungeonData = {
    object_definitions: {
      legacy_guard: {
        label: 'Legacy Guard',
        category: 'npc',
        visual: { sprite_id: 'legacy-guard-sprite' },
      },
    },
    entities: [
      {
        entity_type: 'npc',
        instance_id: 'guard-1',
        entity_ref: { content_id: 'legacy_guard', content_type: 'npc' },
        placement: { room_id: 'room_contract', hex: { q: 2, r: 3 } },
        state: { metadata: { display_name: 'Guard' } },
      },
    ],
  };

  const blueprints = _buildRenderableEntityBlueprints(
    dungeonData,
    'room_contract',
    { character_id: 0 },
    { topology: { rooms: { room_contract: { hexes: [] } } }, occupants: { entities: [] } },
  );

  assert(blueprints.length === 1, 'keeps payload entities when canonical presentation object definitions are absent');
  assert(blueprints[0]?.render?.spriteKey === null, 'ignores legacy payload object definitions when canonical presentation definitions are absent');
}

{
  const mapVisualState = {
    presentation: {
      object_definitions: {
        sage_npc: {
          label: 'Library Sage',
          category: 'npc',
          visual: { sprite_id: 'sprite-sage', color: '#7c3aed' },
        },
      },
    },
    occupants: {
      entities: [
        {
          occupant_id: 'sage-1',
          occupant_type: 'npc',
          content_id: 'sage_npc',
          room_id: 'room_library',
          label: 'Library Sage',
          visible: true,
          placement: { q: 6, r: 2 },
          presentation: { badge: 'ally' },
        },
      ],
    },
    topology: {
      rooms: {
        room_library: { hexes: [] },
      },
    },
  };

  const blueprints = _buildRenderableEntityBlueprints({}, 'room_library', { character_id: 0 }, mapVisualState);
  assert(blueprints.length === 1, 'creates renderable map entities from canonical visual occupants even without payload entities');
  assert(blueprints[0]?.entityType === 'npc', 'maps occupant-only NPC records to npc render entities');
  assert(blueprints[0]?.render?.spriteKey === 'sprite-sage', 'uses presentation object definitions for occupant-only sprite contracts');
}

{
  const mapVisualState = {
    occupants: {
      party: [
        {
          occupant_id: 'party-eldric',
          room_id: 'room_square',
          placement: { q: 1, r: 1 },
          label: 'Eldric',
          visible: true,
        },
      ],
    },
    topology: {
      rooms: {
        room_square: { hexes: [] },
      },
    },
  };

  const blueprints = _buildRenderableEntityBlueprints(
    {},
    'room_square',
    {
      character_id: 42,
      name: 'Eldric',
      portrait_sprite_id: 'portrait-eldric',
    },
    mapVisualState,
  );

  assert(blueprints.length === 1, 'creates renderable map entities from canonical party occupants without explicit occupant_type');
  assert(blueprints[0]?.entityType === 'player_character', 'infers player_character type for canonical party occupants');
  assert(blueprints[0]?.render?.spriteKey === 'portrait-eldric', 'uses the launch-character portrait sprite contract for canonical party occupants');
}

{
  const dungeonData = {
    entities: [
      {
        entity_type: 'npc',
        instance_id: 'goblin-a',
        entity_ref: { content_id: 'goblin_scout', content_type: 'npc' },
        placement: { room_id: 'room_stack', hex: { q: 3, r: 1 } },
        state: { metadata: { display_name: 'Goblin A' } },
      },
      {
        entity_type: 'npc',
        instance_id: 'goblin-b',
        entity_ref: { content_id: 'goblin_scout', content_type: 'npc' },
        placement: { room_id: 'room_stack', hex: { q: 3, r: 1 } },
        state: { metadata: { display_name: 'Goblin B' } },
      },
    ],
  };

  const blueprints = _buildRenderableEntityBlueprints(
    dungeonData,
    'room_stack',
    { character_id: 0 },
    { occupants: { entities: [] }, topology: { rooms: { room_stack: { hexes: [] } } } },
  );

  assert(blueprints.length === 2, 'keeps multiple payload entities with the same content id on one hex');
}

{
  const mapVisualState = {
    occupants: { entities: [] },
    topology: {
      rooms: {
        room_storage: {
          hexes: [
            {
              q: 4,
              r: 2,
              objects: [
                { object_id: 'barrel', label: 'Barrel', category: 'obstacle' },
                { object_id: 'barrel', label: 'Barrel', category: 'obstacle' },
              ],
            },
          ],
        },
      },
    },
  };

  const blueprints = _buildRenderableEntityBlueprints({}, 'room_storage', { character_id: 0 }, mapVisualState);
  const barrels = blueprints.filter((entry) => entry.contentId === 'barrel');
  assert(barrels.length === 2, 'keeps multiple authored room objects with the same content id on one hex');
}

{
  const dungeonData = {
    entities: [
      {
        entity_type: 'npc',
        instance_id: 'guard-a',
        entity_ref: { content_id: 'city_guard', content_type: 'npc' },
        placement: { room_id: 'room_a', hex: { q: 1, r: 1 } },
        state: { metadata: { display_name: 'Guard A' } },
      },
    ],
  };

  const mapVisualState = {
    occupants: {
      entities: [
        {
          occupant_id: 'guard-b',
          content_id: 'city_guard',
          room_id: 'room_b',
          placement: { q: 5, r: 5 },
          visible: false,
        },
      ],
    },
    topology: { rooms: { room_a: { hexes: [] } } },
  };

  const blueprints = _buildRenderableEntityBlueprints(dungeonData, 'room_a', { character_id: 0 }, mapVisualState);
  assert(blueprints.length === 1, 'does not hide an entity from another room that shares the same content id');
}

{
  const dungeonData = {
    entities: [
      {
        entity_type: 'player_character',
        instance_id: 'pc-816-992',
        entity_ref: { content_id: 'pc-816-992', content_type: 'player_character' },
        placement: { room_id: 'room_dupes', hex: { q: 0, r: 0 } },
        state: {
          metadata: {
            display_name: 'Burasco',
            team: 'player',
            character_id: 992,
            campaign_character_id: 992,
            source_character_id: 4773,
            runtime_entity_id: 'pc-816-992',
          },
        },
      },
      {
        entity_type: 'player_character',
        instance_id: 'pc-816-811',
        entity_ref: { content_id: 'pc-816-811', content_type: 'player_character' },
        placement: { room_id: 'room_dupes', hex: { q: 1, r: 0 } },
        state: {
          metadata: {
            display_name: 'Burasco',
            team: 'player',
            character_id: 811,
            campaign_character_id: 811,
            source_character_id: 4773,
            runtime_entity_id: 'pc-816-811',
          },
        },
      },
      {
        entity_type: 'npc',
        instance_id: 'familiar-4773',
        entity_ref: { content_id: 'familiar_weasel', content_type: 'npc' },
        placement: { room_id: 'room_dupes', hex: { q: 2, r: 0 } },
        state: {
          metadata: {
            display_name: 'Mimi',
            team: 'ally',
            character_id: 931,
            campaign_character_id: 931,
            runtime_entity_id: 'familiar-4773',
            follower_kind: 'familiar',
            owner_character_id: 992,
            owner_source_character_id: 4773,
            follower_source_character_id: 3239,
          },
        },
      },
      {
        entity_type: 'npc',
        instance_id: 'familiar-4773-dup',
        entity_ref: { content_id: 'familiar_weasel', content_type: 'npc' },
        placement: { room_id: 'room_dupes', hex: { q: 3, r: 0 } },
        state: {
          metadata: {
            display_name: 'Mimi',
            team: 'ally',
            character_id: 944,
            campaign_character_id: 944,
            runtime_entity_id: 'familiar-4773-dup',
            follower_kind: 'familiar',
            owner_character_id: 992,
            owner_source_character_id: 4773,
            follower_source_character_id: 3239,
          },
        },
      },
    ],
  };

  const blueprints = _buildRenderableEntityBlueprints(
    dungeonData,
    'room_dupes',
    { id: 4773, character_id: 4773, name: 'Burasco' },
    { topology: { rooms: { room_dupes: { hexes: [] } } }, occupants: { entities: [] } },
  );

  assert(blueprints.length === 2, 'dedupes duplicate logical actors from payload entities before map render');
  assert(blueprints[0]?.instanceId === 'pc-816-992', 'keeps the first logical PC instance when duplicates share one source character');
  assert(blueprints[1]?.instanceId === 'familiar-4773', 'keeps the first logical follower instance when duplicates share one follower identity');
}

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
