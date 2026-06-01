/**
 * @file
 * Lightweight regressions for canonical map_visual_state bootstrap helpers.
 *
 * Run with:
 *   node tests/hexmap_visual_state_bootstrap_test.js
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

function extractFunctionExpressionSource(source, anchor, functionName) {
  const start = source.indexOf(anchor);
  if (start === -1) {
    throw new Error(`Could not find function anchor: ${anchor}`);
  }

  const functionStart = source.indexOf('function', start);
  if (functionStart === -1) {
    throw new Error(`Could not parse function expression: ${anchor}`);
  }

  let braceStart = -1;
  let parenDepth = 0;
  for (let index = functionStart; index < source.length; index++) {
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
    throw new Error(`Could not find function body for: ${anchor}`);
  }

  const header = source.slice(start, braceStart).trim();
  let depth = 0;
  for (let index = braceStart; index < source.length; index++) {
    const char = source[index];
    if (char === '{') {
      depth++;
    } else if (char === '}') {
      depth--;
      if (depth === 0) {
        const body = source.slice(braceStart + 1, index);
        const normalizedHeader = header
          .replace(/^[^:]+:\s*/, '')
          .replace(/^async function\s*/, `async function ${functionName}`)
          .replace(/^function\s*/, `function ${functionName}`);
        return `${normalizedHeader} {${body}}`;
      }
    }
  }

  throw new Error(`Could not find closing brace for function expression: ${anchor}`);
}

function extractMethodSource(source, anchor, functionName) {
  const start = source.indexOf(anchor);
  if (start === -1) {
    throw new Error(`Could not find method anchor: ${anchor}`);
  }

  const braceStart = source.indexOf('{', start);
  if (braceStart === -1) {
    throw new Error(`Could not find method body for: ${anchor}`);
  }

  const header = source.slice(start, braceStart).trim();
  const argsStart = header.indexOf('(');
  const argsEnd = header.lastIndexOf(')');
  if (argsStart === -1 || argsEnd === -1 || argsEnd < argsStart) {
    throw new Error(`Could not parse method signature: ${anchor}`);
  }

  const args = header.slice(argsStart + 1, argsEnd);

  let depth = 0;
  for (let index = braceStart; index < source.length; index++) {
    const char = source[index];
    if (char === '{') {
      depth++;
    } else if (char === '}') {
      depth--;
      if (depth === 0) {
        const body = source.slice(braceStart + 1, index);
        return `function ${functionName}(${args}) {${body}}`;
      }
    }
  }

  throw new Error(`Could not find closing brace for method: ${anchor}`);
}

const sourcePath = path.resolve(__dirname, '../js/hexmap.js');
const source = fs.readFileSync(sourcePath, 'utf8');
const getVisualRoomsSource = extractFunctionExpressionSource(source, 'getVisualRooms: function () {', 'getVisualRooms');
const getPresentationObjectDefinitionsSource = extractFunctionExpressionSource(source, 'getPresentationObjectDefinitions: function () {', 'getPresentationObjectDefinitions');
const hasVisualOccupantsSource = extractFunctionExpressionSource(source, 'hasVisualOccupants: function () {', 'hasVisualOccupants');
const getVisualOccupantsSource = extractFunctionExpressionSource(source, 'getVisualOccupants: function () {', 'getVisualOccupants');
const isVisualOccupantVisibleSource = extractFunctionExpressionSource(source, 'isVisualOccupantVisible: function (occupant) {', 'isVisualOccupantVisible');
const getInspectorEntitiesSource = extractFunctionExpressionSource(source, 'getInspectorEntities: function () {', 'getInspectorEntities');
const getVisualConnectionsSource = extractFunctionExpressionSource(source, 'getVisualConnections: function () {', 'getVisualConnections');
const parseVisualHexIdSource = extractFunctionExpressionSource(source, 'parseVisualHexId: function (hexId) {', 'parseVisualHexId');
const getConnectionRoomIdSource = extractFunctionExpressionSource(source, 'getConnectionRoomId: function (connection, side) {', 'getConnectionRoomId');
const getConnectionHexSource = extractFunctionExpressionSource(source, 'getConnectionHex: function (connection, side) {', 'getConnectionHex');
const resolveActiveRoomIdSource = extractFunctionExpressionSource(source, 'resolveActiveRoomId: function () {', 'resolveActiveRoomId');
const getActiveRoomDataSource = extractFunctionExpressionSource(source, 'getActiveRoomData: function () {', 'getActiveRoomData');
const getActiveRoomHexSource = extractFunctionExpressionSource(source, 'getActiveRoomHex: function (q, r) {', 'getActiveRoomHex');
const buildActiveRoomOccupantSummarySource = extractFunctionExpressionSource(source, 'buildActiveRoomOccupantSummary: function () {', 'buildActiveRoomOccupantSummary');
const getObjectDefinitionSource = extractFunctionExpressionSource(source, 'getObjectDefinition: function (contentId) {', 'getObjectDefinition');
const buildObstacleMobilityProfileSource = extractFunctionExpressionSource(source, 'buildObstacleMobilityProfile: function (objectDefinition, metadata = {}, contentId = \'\') {', 'buildObstacleMobilityProfile');
const describeEntitiesAtHexSource = extractFunctionExpressionSource(source, 'describeEntitiesAtHex: function (q, r) {', 'describeEntitiesAtHex');
const describeConnectionAtHexSource = extractFunctionExpressionSource(source, 'describeConnectionAtHex: function (q, r) {', 'describeConnectionAtHex');
const getObjectLabelAtHexSource = extractFunctionExpressionSource(source, 'getObjectLabelAtHex: function (q, r) {', 'getObjectLabelAtHex');
const getObjectIdAtHexSource = extractFunctionExpressionSource(source, 'getObjectIdAtHex: function (q, r) {', 'getObjectIdAtHex');
const getObstacleMobilityAtHexSource = extractFunctionExpressionSource(source, 'getObstacleMobilityAtHex: function (q, r) {', 'getObstacleMobilityAtHex');
const resolveVisitedRoomEntryHexSource = extractFunctionExpressionSource(source, 'resolveVisitedRoomEntryHex: function (roomId) {', 'resolveVisitedRoomEntryHex');
const resolveNavigationCapabilitiesSource = extractFunctionExpressionSource(source, 'resolveNavigationCapabilities: function (roomId) {', 'resolveNavigationCapabilities');
const collectInteractableEntriesForActionRailSource = extractFunctionExpressionSource(source, 'collectInteractableEntriesForActionRail: function (actor = null) {', 'collectInteractableEntriesForActionRail');
const applyWorldDeltaSource = extractFunctionExpressionSource(source, 'applyWorldDelta: function (worldDelta) {', 'applyWorldDelta');
const applyDungeonDataSource = extractFunctionExpressionSource(source, 'applyDungeonData: function () {', 'applyDungeonData');
const renderDungeonStateInspectorSource = extractFunctionExpressionSource(source, 'renderDungeonStateInspector: function () {', 'renderDungeonStateInspector');
const collectNavigateLocationGroupsSource = extractMethodSource(source, 'collectNavigateLocationGroups(context) {', 'collectNavigateLocationGroups');
const collectVisitedNavigateLocationGroupsSource = extractMethodSource(source, 'collectVisitedNavigateLocationGroups(context, campaignId) {', 'collectVisitedNavigateLocationGroups');
const loadRoomPortraitsPanelSource = extractMethodSource(source, 'loadRoomPortraitsPanel(roomId = null) {', 'loadRoomPortraitsPanel');
const buildRoomPortraitEntriesSource = extractMethodSource(source, 'buildRoomPortraitEntries(roomId = null) {', 'buildRoomPortraitEntries');
const buildRoomMerchantEntriesSource = extractMethodSource(source, 'buildRoomMerchantEntries(roomId = null) {', 'buildRoomMerchantEntries');
const executeDirectNavigateSource = extractMethodSource(source, 'async executeDirectNavigate(button) {', 'executeDirectNavigate');
const handleActionRailDirectActionSource = extractMethodSource(source, 'handleActionRailDirectAction(actionKey, button = null) {', 'handleActionRailDirectAction');
const applyPlayerAutomationRoomTransitionSource = extractFunctionExpressionSource(source, 'applyPlayerAutomationRoomTransition: function (events = []) {', 'applyPlayerAutomationRoomTransition');
const prefetchConnectedRoomContextSource = extractMethodSource(source, 'prefetchConnectedRoomContext(limit = 2) {', 'prefetchConnectedRoomContext');
const findLaunchPlayerEntitySource = extractFunctionExpressionSource(source, 'findLaunchPlayerEntity: function () {', 'findLaunchPlayerEntity');

const factory = new Function(`
${getVisualRoomsSource}
${getPresentationObjectDefinitionsSource}
${hasVisualOccupantsSource}
${getVisualOccupantsSource}
${isVisualOccupantVisibleSource}
${getInspectorEntitiesSource}
${getVisualConnectionsSource}
${parseVisualHexIdSource}
${getConnectionRoomIdSource}
${getConnectionHexSource}
${resolveActiveRoomIdSource}
${getActiveRoomDataSource}
${getActiveRoomHexSource}
${buildActiveRoomOccupantSummarySource}
${getObjectDefinitionSource}
${buildObstacleMobilityProfileSource}
${describeEntitiesAtHexSource}
${describeConnectionAtHexSource}
${getObjectLabelAtHexSource}
${getObjectIdAtHexSource}
${getObstacleMobilityAtHexSource}
${resolveVisitedRoomEntryHexSource}
${resolveNavigationCapabilitiesSource}
${collectInteractableEntriesForActionRailSource}
${applyWorldDeltaSource}
${applyDungeonDataSource}
${renderDungeonStateInspectorSource}
${collectNavigateLocationGroupsSource}
${collectVisitedNavigateLocationGroupsSource}
${loadRoomPortraitsPanelSource}
${buildRoomPortraitEntriesSource}
${buildRoomMerchantEntriesSource}
${executeDirectNavigateSource}
${handleActionRailDirectActionSource}
${applyPlayerAutomationRoomTransitionSource}
${prefetchConnectedRoomContextSource}
${findLaunchPlayerEntitySource}
return {
  getVisualRooms,
  getPresentationObjectDefinitions,
  hasVisualOccupants,
  getVisualOccupants,
  isVisualOccupantVisible,
  getInspectorEntities,
  getVisualConnections,
  parseVisualHexId,
  getConnectionRoomId,
  getConnectionHex,
  resolveActiveRoomId,
  getActiveRoomData,
  getActiveRoomHex,
  buildActiveRoomOccupantSummary,
  getObjectDefinition,
  buildObstacleMobilityProfile,
  describeEntitiesAtHex,
  describeConnectionAtHex,
  getObjectLabelAtHex,
  getObjectIdAtHex,
  getObstacleMobilityAtHex,
  resolveVisitedRoomEntryHex,
  resolveNavigationCapabilities,
  collectInteractableEntriesForActionRail,
  applyWorldDelta,
  applyDungeonData,
  renderDungeonStateInspector,
  collectNavigateLocationGroups,
  collectVisitedNavigateLocationGroups,
  loadRoomPortraitsPanel,
  buildRoomPortraitEntries,
  buildRoomMerchantEntries,
  executeDirectNavigate,
  handleActionRailDirectAction,
  applyPlayerAutomationRoomTransition,
  prefetchConnectedRoomContext,
  findLaunchPlayerEntity
};
`);

const methods = factory();

console.log('\n=== Hexmap canonical visual-state bootstrap ===');

{
  const makeEntity = (label, payload, q, r) => ({
    label,
    dcStatePayload: payload,
    dcCharacterId: payload?.state?.metadata?.campaign_character_id || 0,
    getComponent(name) {
      if (name === 'PositionComponent') {
        return { q, r };
      }
      if (name === 'CombatComponent') {
        return null;
      }
      return null;
    },
  });
  const npc = makeEntity('Marta', {
    entity_type: 'npc',
    state: { metadata: { team: 'neutral', campaign_character_id: 415 } },
  }, 3, 0);
  const pc = makeEntity('Burasco', {
    entity_type: 'player_character',
    state: { metadata: { team: 'player', campaign_character_id: 417 } },
  }, 0, 0);
  const context = {
    launchContext: { start_q: 0, start_r: 0 },
    resolveLaunchCharacterStateId: () => 417,
    entityManager: {
      getEntitiesWith(...components) {
        assert(components.length === 1 && components[0] === 'PositionComponent', 'Launch-player lookup does not require combat components');
        return [npc, pc];
      },
    },
  };

  assert(methods.findLaunchPlayerEntity.call(context) === pc, 'Launch-player lookup finds social-room player entities without CombatComponent');
}

{
  const context = {
    activeRoomId: 'visual-room',
    mapVisualState: {
      occupants: {
        party: [
          {
            room_id: 'visual-room',
            occupant_type: 'player_character',
            label: 'Keith',
          },
        ],
        entities: [
          {
            room_id: 'visual-room',
            occupant_type: 'npc',
            label: 'Hidden Guard',
            visible: false,
            state: { hidden: true },
          },
          {
            room_id: 'visual-room',
            occupant_type: 'npc',
            label: 'Town Guard',
          },
          {
            room_id: 'visual-room',
            occupant_type: 'creature',
            label: 'Wolf',
          },
        ],
      },
    },
    dungeonData: {
      entities: [
        {
          entity_type: 'npc',
          placement: { room_id: 'visual-room', hex: { q: 1, r: 1 } },
          state: { metadata: { display_name: 'Legacy Guard' } },
          entity_ref: { content_id: 'legacy_guard' },
        },
      ],
    },
  };

  context.hasVisualOccupants = methods.hasVisualOccupants.bind(context);
  context.getVisualOccupants = methods.getVisualOccupants.bind(context);
  context.isVisualOccupantVisible = methods.isVisualOccupantVisible.bind(context);
  context.resolveActiveRoomId = methods.resolveActiveRoomId.bind(context);
  context.getObjectDefinition = methods.getObjectDefinition.bind(context);

  const summary = methods.buildActiveRoomOccupantSummary.call(context);

  assert(summary === 'Party present: Keith. NPCs present: Town Guard. Other creatures present: Wolf', 'Active room occupant summary prefers canonical visual occupants over payload entities');
}

{
  const context = {
    activeRoomId: 'visual-room',
    mapVisualState: {
      occupants: {
        entities: [
          {
            room_id: 'visual-room',
            occupant_type: 'npc',
            label: 'Hidden Guard',
            visible: false,
            state: { hidden: true },
          },
        ],
      },
    },
    dungeonData: {
      entities: [
        {
          entity_type: 'npc',
          placement: { room_id: 'visual-room', hex: { q: 1, r: 1 } },
          state: { metadata: { display_name: 'Legacy Guard' } },
          entity_ref: { content_id: 'legacy_guard' },
        },
      ],
    },
  };

  context.hasVisualOccupants = methods.hasVisualOccupants.bind(context);
  context.getVisualOccupants = methods.getVisualOccupants.bind(context);
  context.isVisualOccupantVisible = methods.isVisualOccupantVisible.bind(context);
  context.resolveActiveRoomId = methods.resolveActiveRoomId.bind(context);
  context.getObjectDefinition = methods.getObjectDefinition.bind(context);

  const summary = methods.buildActiveRoomOccupantSummary.call(context);

  assert(summary === '', 'Active room occupant summary only reflects canonical visible occupants');
}

{
  const context = {
    activeRoomId: 'visual-room',
    mapVisualState: {
      occupants: {
        entities: [
          {
            room_id: 'visual-room',
            occupant_type: 'npc',
            label: 'Detected Sneak',
            visible: true,
            state: { hidden: true },
          },
          {
            room_id: 'visual-room',
            occupant_type: 'npc',
            label: 'Still Hidden',
            visible: false,
            state: { hidden: true },
          },
        ],
      },
    },
    dungeonData: {},
  };

  context.hasVisualOccupants = methods.hasVisualOccupants.bind(context);
  context.getVisualOccupants = methods.getVisualOccupants.bind(context);
  context.isVisualOccupantVisible = methods.isVisualOccupantVisible.bind(context);
  context.resolveActiveRoomId = methods.resolveActiveRoomId.bind(context);
  context.getObjectDefinition = methods.getObjectDefinition.bind(context);

  const summary = methods.buildActiveRoomOccupantSummary.call(context);

  assert(summary === 'NPCs present: Detected Sneak', 'Active room occupant summary shows detected hidden occupants but suppresses undetected hidden occupants');
}

{
  const context = {
    mapVisualState: {
      map_meta: { active_room_id: 'visual-room' },
      topology: {
        rooms: {
          'visual-room': { room_id: 'visual-room', name: 'Visual Room', hexes: [] },
        },
      },
    },
    dungeonData: {
      active_room_id: 'legacy-room',
      rooms: {
        'legacy-room': { room_id: 'legacy-room', name: 'Legacy Room', hexes: [] },
      },
    },
    launchContext: { room_id: 'launch-room' },
    stateManager: {
      get(key) {
        return key === 'activeRoomId' ? null : undefined;
      },
    },
  };

  context.getVisualRooms = methods.getVisualRooms.bind(context);
  context.resolveActiveRoomId = methods.resolveActiveRoomId.bind(context);

  const roomId = methods.resolveActiveRoomId.call(context);
  const room = methods.getActiveRoomData.call(context);

  assert(roomId === 'visual-room', 'Active room resolution prefers canonical map_visual_state');
  assert(room?.name === 'Visual Room', 'Active room data prefers canonical visual room payload');
}

{
  const context = {
    mapVisualState: {},
    dungeonData: {
      active_room_id: 'legacy-room',
      rooms: {
        'legacy-room': { room_id: 'legacy-room', name: 'Legacy Room', hexes: [] },
      },
    },
    launchContext: {},
    stateManager: {
      get() {
        return null;
      },
    },
  };

  context.getVisualRooms = methods.getVisualRooms.bind(context);
  context.resolveActiveRoomId = methods.resolveActiveRoomId.bind(context);

  const roomId = methods.resolveActiveRoomId.call(context);
  const room = methods.getActiveRoomData.call(context);

  assert(roomId === null, 'Active room resolution ignores legacy payload room ids when canonical topology is absent');
  assert(room === null, 'Active room data does not fall back to legacy payload rooms');
}

{
  const context = {
    activeRoomId: 'visual-room',
    mapVisualState: {
      topology: {
        rooms: {
          'visual-room': {
            room_id: 'visual-room',
            name: 'Visual Room',
            hexes: [
              {
                q: 5,
                r: 6,
                objects: [
                  {
                    object_id: 'brazier',
                  },
                ],
              },
            ],
          },
        },
      },
      presentation: {
        object_definitions: {
          brazier: {
            object_id: 'brazier',
            label: 'Bronze Brazier',
          },
        },
      },
    },
    dungeonData: {},
    entityManager: null,
  };

  context.getVisualRooms = methods.getVisualRooms.bind(context);
  context.resolveActiveRoomId = methods.resolveActiveRoomId.bind(context);
  context.getActiveRoomData = methods.getActiveRoomData.bind(context);
  context.getActiveRoomHex = methods.getActiveRoomHex.bind(context);
  context.getPresentationObjectDefinitions = methods.getPresentationObjectDefinitions.bind(context);
  context.getObjectDefinition = methods.getObjectDefinition.bind(context);

  const label = methods.getObjectLabelAtHex.call(context, 5, 6);
  const objectId = methods.getObjectIdAtHex.call(context, 5, 6);

  assert(label === 'Bronze Brazier', 'Object labels prefer canonical room hex objects and presentation definitions');
  assert(objectId === 'brazier', 'Object ids resolve from canonical room hex objects before payload fallback');
}

{
  const context = {
    activeRoomId: 'visual-room',
    stateManager: { get() { return null; } },
    launchContext: {},
    dungeonData: {
      entities: [
        {
          entity_type: 'npc',
          placement: { room_id: 'visual-room', hex: { q: 5, r: 6 } },
          state: { metadata: { display_name: 'Legacy Guard' } },
          entity_ref: { content_id: 'legacy_guard' },
        },
      ],
    },
    entityManager: null,
  };

  context.getVisualRooms = methods.getVisualRooms.bind(context);
  context.resolveActiveRoomId = methods.resolveActiveRoomId.bind(context);
  context.getActiveRoomData = methods.getActiveRoomData.bind(context);
  context.getActiveRoomHex = methods.getActiveRoomHex.bind(context);
  context.getPresentationObjectDefinitions = methods.getPresentationObjectDefinitions.bind(context);
  context.getObjectDefinition = methods.getObjectDefinition.bind(context);

  const label = methods.getObjectLabelAtHex.call(context, 5, 6);
  const objectId = methods.getObjectIdAtHex.call(context, 5, 6);

  assert(label === null, 'Object labels do not treat payload NPC occupants as obstacle objects');
  assert(objectId === null, 'Object ids do not consult payload entities when canonical room objects are absent');
}

{
  const context = {
    activeRoomId: 'visual-room',
    mapVisualState: {
      topology: {
        rooms: {
          'visual-room': {
            room_id: 'visual-room',
            name: 'Visual Room',
            hexes: [
              {
                q: 8,
                r: 1,
                objects: [
                  {
                    object_id: 'stone_wall',
                    category: 'obstacle',
                  },
                ],
              },
            ],
          },
        },
      },
      presentation: {
        object_definitions: {
          stone_wall: {
            object_id: 'stone_wall',
            category: 'obstacle',
            movement: {
              passable: false,
              blocks_movement: true,
            },
          },
        },
      },
    },
    dungeonData: {},
  };

  context.getVisualRooms = methods.getVisualRooms.bind(context);
  context.resolveActiveRoomId = methods.resolveActiveRoomId.bind(context);
  context.getActiveRoomData = methods.getActiveRoomData.bind(context);
  context.getActiveRoomHex = methods.getActiveRoomHex.bind(context);
  context.getPresentationObjectDefinitions = methods.getPresentationObjectDefinitions.bind(context);
  context.getObjectDefinition = methods.getObjectDefinition.bind(context);
  context.buildObstacleMobilityProfile = methods.buildObstacleMobilityProfile.bind(context);

  const profile = methods.getObstacleMobilityAtHex.call(context, 8, 1);

  assert(profile?.passable === false && profile?.movable === false, 'Obstacle mobility falls back to canonical room objects and presentation movement data');
}

{
  const context = {
    activeRoomId: 'visual-room',
    mapVisualState: {
      topology: {
        rooms: {
          'visual-room': {
            room_id: 'visual-room',
            name: 'Visual Room',
            hexes: [
              {
                q: 1,
                r: 2,
                objects: [
                  {
                    object_id: 'statue',
                    type: 'statue',
                    blocks_movement: true,
                  },
                ],
              },
            ],
          },
        },
      },
      presentation: {
        object_definitions: {
          statue: {
            object_id: 'statue',
            label: 'Stone Statue',
          },
        },
      },
    },
    dungeonData: {},
  };

  context.getVisualRooms = methods.getVisualRooms.bind(context);
  context.resolveActiveRoomId = methods.resolveActiveRoomId.bind(context);
  context.getActiveRoomData = methods.getActiveRoomData.bind(context);
  context.getActiveRoomHex = methods.getActiveRoomHex.bind(context);
  context.getPresentationObjectDefinitions = methods.getPresentationObjectDefinitions.bind(context);
  context.getObjectDefinition = methods.getObjectDefinition.bind(context);
  context.buildObstacleMobilityProfile = methods.buildObstacleMobilityProfile.bind(context);

  const profile = methods.getObstacleMobilityAtHex.call(context, 1, 2);

  assert(profile?.passable === false, 'Obstacle mobility respects inline canonical room-object blocking flags');
}

{
  const context = {
    activeRoomId: 'visual-room',
    mapVisualState: {},
    dungeonData: {
      entities: [
        {
          entity_type: 'obstacle',
          placement: { room_id: 'visual-room', hex: { q: 8, r: 1 } },
          entity_ref: { content_id: 'legacy-wall' },
          state: { metadata: { is_wall: true } },
        },
      ],
    },
  };

  context.getVisualRooms = methods.getVisualRooms.bind(context);
  context.resolveActiveRoomId = methods.resolveActiveRoomId.bind(context);
  context.getActiveRoomData = methods.getActiveRoomData.bind(context);
  context.getActiveRoomHex = methods.getActiveRoomHex.bind(context);
  context.getPresentationObjectDefinitions = methods.getPresentationObjectDefinitions.bind(context);
  context.getObjectDefinition = methods.getObjectDefinition.bind(context);
  context.buildObstacleMobilityProfile = methods.buildObstacleMobilityProfile.bind(context);

  const profile = methods.getObstacleMobilityAtHex.call(context, 8, 1);

  assert(profile === null, 'Obstacle mobility does not fall back to legacy payload obstacles when canonical room objects are absent');
}

{
  const context = {
    activeRoomId: 'visual-room',
    mapVisualState: {
      presentation: {
        object_definitions: {
          brazier: {
            object_id: 'brazier',
            label: 'Bronze Brazier',
          },
        },
      },
      occupants: {
        party: [],
        entities: [
          {
            occupant_id: 'occ-1',
            occupant_type: 'npc',
            content_id: 'archivist',
            room_id: 'visual-room',
            placement: { q: 9, r: 4 },
            label: 'Archivist',
            presentation: { badge: 'ally' },
          },
        ],
      },
    },
    dungeonData: {
      object_definitions: {
        stale: { object_id: 'stale', label: 'Should Not Win' },
      },
    },
  };

  context.getPresentationObjectDefinitions = methods.getPresentationObjectDefinitions.bind(context);
  context.hasVisualOccupants = methods.hasVisualOccupants.bind(context);
  context.getVisualOccupants = methods.getVisualOccupants.bind(context);
  context.isVisualOccupantVisible = methods.isVisualOccupantVisible.bind(context);
  context.getInspectorEntities = methods.getInspectorEntities.bind(context);

  const defs = methods.getPresentationObjectDefinitions.call(context);
  const inspectorEntities = methods.getInspectorEntities.call(context);

  assert(defs.brazier?.label === 'Bronze Brazier', 'Presentation object definitions prefer canonical visual state');
  assert(inspectorEntities.length === 1 && inspectorEntities[0]?.displayName === 'Archivist', 'Inspector entities normalize canonical occupants');
  assert(inspectorEntities[0]?.team === 'ally', 'Canonical occupant badges flow into inspector entity records');
}

{
  const context = {
    mapVisualState: {},
    dungeonData: {
      object_definitions: {
        stale: { object_id: 'stale', label: 'Legacy Only' },
      },
      entities: [
        {
          entity_type: 'npc',
          placement: { room_id: 'legacy-room', hex: { q: 1, r: 1 } },
          entity_ref: { content_id: 'legacy_guard' },
          state: { metadata: { display_name: 'Legacy Guard' } },
        },
      ],
    },
  };

  context.getPresentationObjectDefinitions = methods.getPresentationObjectDefinitions.bind(context);
  context.hasVisualOccupants = methods.hasVisualOccupants.bind(context);
  context.getVisualOccupants = methods.getVisualOccupants.bind(context);
  context.isVisualOccupantVisible = methods.isVisualOccupantVisible.bind(context);
  context.getInspectorEntities = methods.getInspectorEntities.bind(context);

  const defs = methods.getPresentationObjectDefinitions.call(context);
  const inspectorEntities = methods.getInspectorEntities.call(context);

  assert(Object.keys(defs).length === 0, 'Presentation object definitions ignore legacy payload definitions when canonical presentation is absent');
  assert(inspectorEntities.length === 0, 'Inspector entities do not fall back to legacy payload entities');
}

{
  const elements = new Map();
  [
    'dungeon-state-summary',
    'dungeon-objects-grid',
    'dungeon-entities-summary',
    'dungeon-entities-analysis',
    'dungeon-entities-grid',
    'dungeon-state-json',
  ].forEach((id) => {
    elements.set(id, { textContent: '', innerHTML: '' });
  });

  global.document = {
    getElementById(id) {
      return elements.get(id) || null;
    },
  };

  const context = {
    activeRoomId: 'visual-room',
    mapVisualState: {
      schema_version: '1.0.0',
      topology: {
        rooms: {
          'visual-room': {
            room_id: 'visual-room',
            name: 'Visual Room',
            hexes: [
              { q: 1, r: 1, objects: [{ object_id: 'brazier' }] },
              { q: 2, r: 2, objects: [{ object_id: 'brazier' }, { object_id: 'crate' }] },
            ],
          },
        },
      },
      presentation: {
        object_definitions: {
          brazier: { object_id: 'brazier', label: 'Bronze Brazier', category: 'fixture', visual: { sprite_id: 'sprite-brazier' } },
          crate: { object_id: 'crate', label: 'Wooden Crate', category: 'container', visual: { sprite_id: 'sprite-crate' } },
          unused: { object_id: 'unused', label: 'Unused', category: 'misc' },
        },
      },
      occupants: {
        entities: [
          {
            occupant_id: 'occ-1',
            occupant_type: 'npc',
            content_id: 'archivist',
            room_id: 'visual-room',
            placement: { q: 4, r: 4 },
            label: 'Archivist',
            presentation: { badge: 'ally' },
          },
        ],
      },
    },
    dungeonData: {
      entities: [
        {
          entity_type: 'obstacle',
          placement: { room_id: 'visual-room', hex: { q: 9, r: 9 } },
          entity_ref: { content_id: 'legacy-only' },
        },
      ],
    },
  };

  context.getVisualRooms = methods.getVisualRooms.bind(context);
  context.getPresentationObjectDefinitions = methods.getPresentationObjectDefinitions.bind(context);
  context.hasVisualOccupants = methods.hasVisualOccupants.bind(context);
  context.getVisualOccupants = methods.getVisualOccupants.bind(context);
  context.isVisualOccupantVisible = methods.isVisualOccupantVisible.bind(context);
  context.getInspectorEntities = methods.getInspectorEntities.bind(context);

  methods.renderDungeonStateInspector.call(context);

  assert(elements.get('dungeon-state-summary').textContent.includes('Objects Used: 2/3'), 'Dungeon state inspector counts canonical room hex objects as used definitions');
  assert(elements.get('dungeon-objects-grid').innerHTML.includes('Used:</strong> 2 placed'), 'Dungeon state inspector counts repeated canonical object placements');
  assert(!elements.get('dungeon-objects-grid').innerHTML.includes('legacy-only'), 'Dungeon state inspector ignores stale payload obstacle usage when canonical room hex objects exist');

  delete global.document;
}

{
  const context = {
    activeRoomId: 'visual-room',
    mapVisualState: {
      topology: {
        connections: [
          {
            connection_id: 'door-a-b',
            from_room_id: 'visual-room',
            to_room_id: 'next-room',
            from_hex_id: 'visual-room:2:3',
            to_hex_id: 'next-room:0:0',
            type: 'door',
            is_passable: true,
            is_discovered: true,
          },
        ],
      },
    },
  };

  context.getVisualConnections = methods.getVisualConnections.bind(context);
  context.parseVisualHexId = methods.parseVisualHexId.bind(context);
  context.getConnectionRoomId = methods.getConnectionRoomId.bind(context);
  context.getConnectionHex = methods.getConnectionHex.bind(context);
  context.hasVisualOccupants = methods.hasVisualOccupants.bind(context);
  context.getVisualOccupants = methods.getVisualOccupants.bind(context);
  context.isVisualOccupantVisible = methods.isVisualOccupantVisible.bind(context);

  const description = methods.describeConnectionAtHex.call(context, 2, 3);
  assert(description === 'door -> next-room (passable, discovered)', 'Connection hover details resolve from canonical visual hex ids');
}

{
  const context = {
    activeRoomId: 'visual-room',
    mapVisualState: {
      topology: {
        rooms: {
          'visual-room': { room_id: 'visual-room', name: 'Visual Room', entry_hex: { q: 2, r: 3 } },
          'next-room': { room_id: 'next-room', name: 'Next Room', entry_hex: { q: 7, r: 8 } },
        },
        connections: [
          {
            connection_id: 'door-a-b',
            from_room_id: 'visual-room',
            to_room_id: 'next-room',
            from_hex_id: 'visual-room:2:3',
            to_hex_id: 'next-room:7:8',
            type: 'door',
            is_passable: true,
            is_discovered: true,
          },
        ],
      },
    },
    dungeonData: {},
  };

  context.getVisualRooms = methods.getVisualRooms.bind(context);
  context.getVisualConnections = methods.getVisualConnections.bind(context);
  context.parseVisualHexId = methods.parseVisualHexId.bind(context);
  context.getConnectionRoomId = methods.getConnectionRoomId.bind(context);
  context.getConnectionHex = methods.getConnectionHex.bind(context);
  context.hasVisualOccupants = methods.hasVisualOccupants.bind(context);
  context.getVisualOccupants = methods.getVisualOccupants.bind(context);
  context.isVisualOccupantVisible = methods.isVisualOccupantVisible.bind(context);

  const capabilities = methods.resolveNavigationCapabilities.call(context);
  const entryHex = methods.resolveVisitedRoomEntryHex.call(context, 'next-room');

  assert(capabilities.length === 1, 'Navigation capabilities are derived from canonical visual connections');
  assert(capabilities[0]?.target_room_id === 'next-room', 'Navigation capability keeps the canonical target room');
  assert(capabilities[0]?.origin_hex?.q === 2 && capabilities[0]?.target_hex?.r === 8, 'Navigation capability decodes canonical endpoint hex ids');
  assert(entryHex?.q === 7 && entryHex?.r === 8, 'Visited-room entry resolution uses canonical visual connection endpoints');
}

{
  const context = {
    activeRoomId: 'visual-room',
    mapVisualState: {
      topology: {
        rooms: {
          'visual-room': { room_id: 'visual-room', name: 'Visual Room', hexes: [{ q: 2, r: 3, is_entry: true }] },
          'quiet-room': { room_id: 'quiet-room', name: 'Quiet Room', hexes: [{ q: 11, r: 12, is_entry: true }, { q: 12, r: 12 }] },
        },
        connections: [],
      },
    },
  };

  context.getVisualRooms = methods.getVisualRooms.bind(context);
  context.getVisualConnections = methods.getVisualConnections.bind(context);
  context.parseVisualHexId = methods.parseVisualHexId.bind(context);
  context.getConnectionRoomId = methods.getConnectionRoomId.bind(context);
  context.getConnectionHex = methods.getConnectionHex.bind(context);

  const entryHex = methods.resolveVisitedRoomEntryHex.call(context, 'quiet-room');

  assert(entryHex?.q === 11 && entryHex?.r === 12, 'Visited-room entry resolution falls back to canonical room entry hexes instead of 0,0');
}

{
  const context = {
    activeRoomId: 'legacy-room',
    mapVisualState: {},
    dungeonData: {
      connections: [
        {
          connection_id: 'legacy-door',
          from_room: 'legacy-room',
          to_room: 'legacy-next',
          from_hex: { q: 4, r: 5 },
          to_hex: { q: 6, r: 7 },
          type: 'door',
          is_passable: true,
          is_discovered: true,
        },
      ],
    },
  };

  context.getVisualConnections = methods.getVisualConnections.bind(context);
  context.parseVisualHexId = methods.parseVisualHexId.bind(context);
  context.getConnectionRoomId = methods.getConnectionRoomId.bind(context);
  context.getConnectionHex = methods.getConnectionHex.bind(context);
  context.hasVisualOccupants = methods.hasVisualOccupants.bind(context);
  context.getVisualOccupants = methods.getVisualOccupants.bind(context);
  context.isVisualOccupantVisible = methods.isVisualOccupantVisible.bind(context);

  const description = methods.describeConnectionAtHex.call(context, 4, 5);
  const capabilities = methods.resolveNavigationCapabilities.call(context);

  assert(description === null, 'Connection hover details ignore legacy payload connections when canonical topology is absent');
  assert(Array.isArray(capabilities) && capabilities.length === 0, 'Navigation capabilities no longer fall back to legacy room/hex connections');
}

{
  const context = {
    activeRoomId: 'visual-room',
    mapVisualState: {
      occupants: {
        party: [],
        entities: [
          {
            occupant_id: 'occ-1',
            occupant_type: 'npc',
            content_id: 'archivist',
            room_id: 'visual-room',
            placement: { q: 1, r: 2 },
            label: 'Archivist',
            presentation: { badge: 'ally' },
          },
        ],
      },
    },
    dungeonData: {},
    entityManager: null,
  };

  context.hasVisualOccupants = methods.hasVisualOccupants.bind(context);
  context.getVisualOccupants = methods.getVisualOccupants.bind(context);
  context.isVisualOccupantVisible = methods.isVisualOccupantVisible.bind(context);

  const labels = methods.describeEntitiesAtHex.call(context, 1, 2);
  assert(Array.isArray(labels) && labels[0] === 'Archivist (ally)', 'Hex entity descriptions prefer canonical occupants before raw payload fallback');
}

{
  const context = {
    activeRoomId: 'visual-room',
    mapVisualState: {
      occupants: {
        party: [],
        entities: [
          {
            occupant_id: 'occ-hidden',
            occupant_type: 'npc',
            content_id: 'hidden_sneak',
            room_id: 'visual-room',
            placement: { q: 1, r: 2 },
            label: 'Hidden Sneak',
            visible: false,
            presentation: { badge: 'enemy' },
          },
          {
            occupant_id: 'occ-detected',
            occupant_type: 'npc',
            content_id: 'detected_sneak',
            room_id: 'visual-room',
            placement: { q: 1, r: 2 },
            label: 'Detected Sneak',
            visible: true,
            state: { hidden: true },
            presentation: { badge: 'enemy' },
          },
        ],
      },
    },
    dungeonData: {},
    entityManager: null,
  };

  context.hasVisualOccupants = methods.hasVisualOccupants.bind(context);
  context.getVisualOccupants = methods.getVisualOccupants.bind(context);
  context.isVisualOccupantVisible = methods.isVisualOccupantVisible.bind(context);

  const labels = methods.describeEntitiesAtHex.call(context, 1, 2);
  assert(Array.isArray(labels) && labels.length === 1 && labels[0] === 'Detected Sneak (enemy)', 'Hex entity descriptions honor hidden/detected canonical occupant visibility');
}

{
  const context = {
    activeRoomId: 'visual-room',
    mapVisualState: {},
    dungeonData: {
      entities: [
        {
          entity_type: 'npc',
          placement: { room_id: 'visual-room', hex: { q: 1, r: 2 } },
          entity_ref: { content_id: 'legacy_guard' },
          state: { metadata: { display_name: 'Legacy Guard' } },
        },
      ],
    },
    entityManager: null,
  };

  context.hasVisualOccupants = methods.hasVisualOccupants.bind(context);
  context.getVisualOccupants = methods.getVisualOccupants.bind(context);
  context.isVisualOccupantVisible = methods.isVisualOccupantVisible.bind(context);

  const labels = methods.describeEntitiesAtHex.call(context, 1, 2);
  assert(Array.isArray(labels) && labels.length === 0, 'Hex entity descriptions do not fall back to legacy payload entities');
}

{
  const panel = {
    elements: {
      npcPortraitsPanel: {},
      npcPortraitsName: { textContent: '' },
      npcPortraitsMeta: { textContent: '' },
      npcPortraitsStatus: { textContent: '' },
      npcPortraitsPlaceholderText: { textContent: '' },
      npcPortraitsGrid: { innerHTML: '', hidden: false },
    },
    stateManager: {
      hexmap: {
        dungeonData: {
          rooms: {
            room_a: {
              room_id: 'room_a',
              name: 'Payload Room Name',
            },
          },
        },
        getVisualRooms() {
          return {
            room_a: {
              room_id: 'room_a',
              name: 'Canonical Room Name',
            },
          };
        },
        resolveActiveRoomId() {
          return 'room_a';
        },
      },
    },
    buildRoomPortraitEntries() {
      return [];
    },
    formatPortraitsMeta(room, count) {
      return `${room?.name || 'unknown'}:${count}`;
    },
  };

  methods.loadRoomPortraitsPanel.call(panel);

  assert(panel.elements.npcPortraitsName.textContent === 'Canonical Room Name', 'Portrait panel prefers canonical room names');
  assert(panel.elements.npcPortraitsMeta.textContent === 'Canonical Room Name:0', 'Portrait panel meta uses canonical room data');
}

{
  const panel = {
    elements: {
      npcPortraitsPanel: {},
      npcPortraitsName: { textContent: '' },
      npcPortraitsMeta: { textContent: '' },
      npcPortraitsStatus: { textContent: '' },
    },
    stateManager: {
      hexmap: {
        dungeonData: {
          rooms: {
            legacy_room: { room_id: 'legacy_room', name: 'Legacy Room' },
          },
        },
        getVisualRooms() {
          return {};
        },
        resolveActiveRoomId() {
          return 'legacy_room';
        },
        getActiveRoomData() {
          return null;
        },
      },
    },
    buildRoomPortraitEntries() {
      return [];
    },
    formatPortraitsMeta(room, count) {
      return `${room?.name || 'unknown'}:${count}`;
    },
  };

  methods.loadRoomPortraitsPanel.call(panel);

  assert(panel.elements.npcPortraitsName.textContent === 'Current room', 'Portrait panel does not revive legacy payload-only room names');
  assert(panel.elements.npcPortraitsMeta.textContent === 'unknown:0', 'Portrait panel meta stays empty-state when canonical room data is absent');
}

{
  const chatLines = [];
  let refreshed = false;
  let ended = false;
  let navigatedRoomId = null;
  let dungeonSwitch = null;
  const panel = {
    beginActionRailRequest() {
      return true;
    },
    endActionRailRequest() {
      ended = true;
    },
    getActionRailContext() {
      return {
        hexmap: {
          dungeonData: { rooms: {} },
          getVisualRooms() {
            return {
              visual_room: { room_id: 'visual_room', name: 'Visual Room' },
            };
          },
          navigateToVisitedRoom(roomId) {
            navigatedRoomId = roomId;
            return true;
          },
        },
      };
    },
    navigateToDungeonContext(payload) {
      dungeonSwitch = payload;
    },
    appendChatLine(author, message, tone) {
      chatLines.push({ author, message, tone });
    },
    refreshActionRail() {
      refreshed = true;
    },
  };

  methods.executeDirectNavigate.call(panel, {
    dataset: {
      roomId: 'visual_room',
      roomName: 'Visual Room',
      originQ: '',
      originR: '',
      mapId: '',
      dungeonLevelId: '',
    },
  });

  assert(navigatedRoomId === 'visual_room', 'Direct navigation accepts canonical-only visited rooms');
  assert(dungeonSwitch === null, 'Direct navigation stays in-place for canonical same-map rooms');
  assert(refreshed === true, 'Direct navigation refreshes the action rail after canonical-room navigation');
  assert(ended === true, 'Direct navigation always ends the action-rail request');
  assert(chatLines.some((entry) => entry.message === 'Navigating to Visual Room.'), 'Direct navigation announces canonical-room navigation success');
}

{
  const chatLines = [];
  let ended = false;
  let dungeonSwitch = null;
  const panel = {
    beginActionRailRequest() {
      return true;
    },
    endActionRailRequest() {
      ended = true;
    },
    getActionRailContext() {
      return {
        hexmap: {
          dungeonData: { map_id: 'current-map' },
          launchContext: {},
          getVisualRooms() {
            return {};
          },
        },
      };
    },
    navigateToDungeonContext(payload) {
      dungeonSwitch = payload;
    },
    appendChatLine(author, message, tone) {
      chatLines.push({ author, message, tone });
    },
    refreshActionRail() {},
  };

  methods.executeDirectNavigate.call(panel, {
    dataset: {
      roomId: 'remote-room',
      roomName: 'Remote Room',
      originQ: '',
      originR: '',
      mapId: 'remote-map',
      dungeonLevelId: 'remote-level',
    },
    closest() {
      return null;
    },
  });

  assert(dungeonSwitch?.map_id === 'remote-map' && dungeonSwitch?.room_id === 'remote-room', 'Direct navigation restores visited-location dungeon switches from the old hexmap');
  assert(dungeonSwitch?.dungeon_level_id === 'remote-level', 'Direct navigation preserves visited destination dungeon level context');
  assert(chatLines.some((entry) => entry.message.includes('Remote Room')), 'Direct navigation announces remote visited destination navigation');
  assert(ended === true, 'Direct remote navigation ends the action-rail request');
}

{
  const chatLines = [];
  let ended = false;
  let navigatedRoomId = null;
  const panel = {
    beginActionRailRequest() {
      return true;
    },
    endActionRailRequest() {
      ended = true;
    },
    getActionRailContext() {
      return {
        hexmap: {
          dungeonData: {
            rooms: {
              legacy_room: { room_id: 'legacy_room', name: 'Legacy Room' },
            },
          },
          getVisualRooms() {
            return {};
          },
          navigateToVisitedRoom(roomId) {
            navigatedRoomId = roomId;
            return true;
          },
        },
      };
    },
    appendChatLine(author, message, tone) {
      chatLines.push({ author, message, tone });
    },
    refreshActionRail() {},
  };

  methods.executeDirectNavigate.call(panel, {
    dataset: {
      roomId: 'legacy_room',
      roomName: 'Legacy Room',
      originQ: '',
      originR: '',
    },
  });

  assert(navigatedRoomId === null, 'Direct navigation does not revive payload-only visited rooms');
  assert(chatLines.some((entry) => entry.message === 'That destination is not navigable right now.'), 'Direct navigation reports canonical-only room misses');
  assert(ended === true, 'Direct navigation still ends requests for payload-only room misses');
}

{
  const searchButton = { dataset: {} };
  let searchedWithButton = null;
  let chatLine = null;
  const panel = {
    getActionRailContext() {
      return {
        hexmap: {},
      };
    },
    executeDirectSearch(button) {
      searchedWithButton = button;
    },
    appendChatLine(author, message, tone) {
      chatLine = { author, message, tone };
    },
  };

  methods.handleActionRailDirectAction.call(panel, 'search', searchButton);

  assert(searchedWithButton === searchButton, 'Action-rail Search direct action executes the room search with the clicked button');
  assert(chatLine === null, 'Action-rail Search no longer stops at guidance text');
}

assert(source.includes("const actionSearchBtn = document.getElementById('action-search');"), 'Standalone #action-search button is explicitly bound');
assert(source.includes('await self.uiManager?.executeDirectSearch(this);'), 'Standalone #action-search button executes the shared search action');

{
  let selectedEntity = null;
  let setActiveRoomId = null;
  let updatedLaunchRoomId = null;
  const context = {
    activeRoomId: 'start_room',
    playerAutomation: { profile: { actor: 'hero' } },
    resolvePlayerAutomationEntity() {
      return { id: 'controlled-entity' };
    },
    stateManager: {
      get(key) {
        return key === 'selectedEntity' ? null : undefined;
      },
    },
    selectEntity(entity) {
      selectedEntity = entity;
    },
    navigateToVisitedRoom() {
      return false;
    },
    getVisualRooms() {
      return {
        visual_room: { room_id: 'visual_room', name: 'Visual Room' },
      };
    },
    dungeonData: { rooms: {} },
    setActiveRoom(roomId) {
      setActiveRoomId = roomId;
    },
    updateLaunchLocationContext(roomId) {
      updatedLaunchRoomId = roomId;
    },
  };

  const transitioned = methods.applyPlayerAutomationRoomTransition.call(context, [
    { type: 'room_entered', data: { to_room: 'visual_room' } },
  ]);

  assert(transitioned === true, 'Automation room transition accepts canonical-only target rooms');
  assert(selectedEntity && selectedEntity.id === 'controlled-entity', 'Automation room transition selects the controlled entity before switching rooms');
  assert(setActiveRoomId === 'visual_room', 'Automation room transition activates canonical-only target rooms');
  assert(updatedLaunchRoomId === 'visual_room', 'Automation room transition updates launch context for canonical-only target rooms');
}

{
  const warmedChats = [];
  const warmedViews = [];
  const panel = {
    stateManager: {
      hexmap: {
        characterData: { id: 42 },
        dungeonData: { connections: [] },
        resolveCampaignId() {
          return 77;
        },
        resolveActiveRoomId() {
          return 'room_a';
        },
        getVisualConnections() {
          return [
            { from_room_id: 'room_a', to_room_id: 'room_b', is_passable: true },
            { from_room_id: 'room_c', to_room_id: 'room_a', is_passable: true },
          ];
        },
        getConnectionRoomId(connection, side) {
          return side === 'from' ? connection.from_room_id : connection.to_room_id;
        },
      },
    },
    fetchRoomChatHistoryForContext(context) {
      warmedChats.push(context);
      return Promise.resolve();
    },
    fetchRoomViewPayload(campaignId, roomId) {
      warmedViews.push({ campaignId, roomId });
      return Promise.resolve();
    },
  };

  methods.prefetchConnectedRoomContext.call(panel, 2);

  assert(warmedChats.length === 2, 'Connected-room prefetch warms chat context for canonical-only adjacent rooms');
  assert(warmedChats.some((entry) => entry.roomId === 'room_b' && entry.campaignId === 77 && entry.characterId === 42), 'Connected-room prefetch includes outgoing canonical neighbors');
  assert(warmedChats.some((entry) => entry.roomId === 'room_c' && entry.campaignId === 77 && entry.characterId === 42), 'Connected-room prefetch includes incoming canonical neighbors');
  assert(warmedViews.length === 2, 'Connected-room prefetch warms room views for canonical-only adjacent rooms');
  assert(warmedViews.some((entry) => entry.campaignId === 77 && entry.roomId === 'room_b'), 'Connected-room prefetch warms outgoing canonical room views');
  assert(warmedViews.some((entry) => entry.campaignId === 77 && entry.roomId === 'room_c'), 'Connected-room prefetch warms incoming canonical room views');
}

{
  const context = {
    activeRoomId: 'visual-room',
    mapVisualState: {
      topology: {
        rooms: {
          'visual-room': {
            room_id: 'visual-room',
            name: 'Visual Room',
            hexes: [
              {
                q: 2,
                r: 2,
                objects: [
                  {
                    object_id: 'oak-door',
                    label: 'Oak Door',
                    category: 'door',
                    blocks_movement: true,
                  },
                ],
              },
              {
                q: 4,
                r: 4,
                objects: [
                  {
                    object_id: 'potion',
                    label: 'Potion',
                    category: 'item',
                    collectible: true,
                  },
                ],
              },
            ],
            interactables: [
              {
                id: 'rune-plinth',
                label: 'Rune Plinth',
                description: 'Covered in glyphs.',
                options: ['Inspect', 'Touch'],
                position: { q: 6, r: 6 },
              },
            ],
          },
        },
        connections: [
          {
            connection_id: 'door-a-b',
            from_room_id: 'visual-room',
            to_room_id: 'next-room',
            from_hex_id: 'visual-room:8:8',
            to_hex_id: 'next-room:9:9',
            type: 'door',
            is_passable: false,
          },
        ],
      },
      occupants: {
        entities: [
          {
            occupant_id: 'npc-1',
            occupant_type: 'npc',
            content_id: 'archivist',
            room_id: 'visual-room',
            placement: { q: 3, r: 3 },
            label: 'Archivist',
          },
          {
            occupant_id: 'npc-hidden',
            occupant_type: 'npc',
            content_id: 'hidden_sneak',
            room_id: 'visual-room',
            placement: { q: 5, r: 5 },
            label: 'Hidden Sneak',
            visible: false,
          },
          {
            occupant_id: 'npc-detected',
            occupant_type: 'npc',
            content_id: 'detected_sneak',
            room_id: 'visual-room',
            placement: { q: 5, r: 6 },
            label: 'Detected Sneak',
            visible: true,
            state: { hidden: true },
          },
        ],
      },
      presentation: {
        object_definitions: {
          'oak-door': { object_id: 'oak-door', label: 'Oak Door', category: 'door' },
          potion: { object_id: 'potion', label: 'Potion', category: 'item', collectible: true },
        },
      },
    },
    dungeonData: {
      entities: [
        {
          entity_type: 'npc',
          placement: { room_id: 'visual-room', hex: { q: 10, r: 10 } },
          entity_ref: { content_id: 'legacy_guard' },
          state: { metadata: { display_name: 'Legacy Guard' } },
        },
      ],
      rooms: {
        'visual-room': {
          room_id: 'visual-room',
          interactables: ['Legacy Lever'],
        },
      },
    },
    questData: {},
    movementSystem: {
      hexDistance(aq, ar, bq, br) {
        return Math.max(Math.abs(aq - bq), Math.abs(ar - br), Math.abs((aq + ar) - (bq + br)));
      },
    },
  };

  const actor = {
    getComponent(name) {
      if (name === 'PositionComponent') {
        return { q: 3, r: 2 };
      }
      return null;
    },
  };

  context.getVisualRooms = methods.getVisualRooms.bind(context);
  context.getPresentationObjectDefinitions = methods.getPresentationObjectDefinitions.bind(context);
  context.hasVisualOccupants = methods.hasVisualOccupants.bind(context);
  context.getVisualOccupants = methods.getVisualOccupants.bind(context);
  context.isVisualOccupantVisible = methods.isVisualOccupantVisible.bind(context);
  context.getActiveRoomData = methods.getActiveRoomData.bind(context);
  context.resolveActiveRoomId = methods.resolveActiveRoomId.bind(context);
  context.getVisualConnections = methods.getVisualConnections.bind(context);
  context.parseVisualHexId = methods.parseVisualHexId.bind(context);
  context.getConnectionRoomId = methods.getConnectionRoomId.bind(context);
  context.getConnectionHex = methods.getConnectionHex.bind(context);
  context.buildObstacleMobilityProfile = methods.buildObstacleMobilityProfile.bind(context);

  const entries = methods.collectInteractableEntriesForActionRail.call(context, actor);
  const titles = entries.map((entry) => entry.title);

  assert(titles.includes('Archivist'), 'Action-rail interactables include canonical occupants');
  assert(titles.includes('Detected Sneak') && !titles.includes('Hidden Sneak'), 'Action-rail interactables honor hidden/detected canonical occupant visibility');
  assert(titles.includes('Oak Door'), 'Action-rail interactables include canonical room objects');
  assert(titles.includes('Potion'), 'Action-rail interactables include canonical collectible items');
  assert(titles.includes('Rune Plinth'), 'Action-rail interactables include canonical authored room interactables');
  assert(titles.includes('Passage to next-room'), 'Action-rail interactables include canonical room connections');
  assert(!titles.includes('Legacy Guard') && !titles.includes('Legacy Lever'), 'Action-rail interactables do not revive legacy payload entities or room interactables');
}

{
  let painted = 0;
  let fogRefreshed = 0;
  const context = {
    activeRoomId: 'visual-room',
    mapVisualState: {
      topology: {
        rooms: {
          'visual-room': {
            room_id: 'visual-room',
            hexes: [
              {
                q: 2,
                r: 2,
                objects: [
                  { object_id: 'oak-door', category: 'door', blocks_movement: true, movable: false },
                  { object_id: 'crate', category: 'obstacle', blocks_movement: true, movable: true },
                ],
              },
              {
                q: 3,
                r: 2,
                objects: [],
              },
            ],
          },
        },
        connections: [
          {
            connection_id: 'door-a-b',
            from_room_id: 'visual-room',
            to_room_id: 'next-room',
            from_hex_id: 'visual-room:2:2',
            to_hex_id: 'next-room:4:4',
            is_passable: false,
            is_discovered: false,
          },
        ],
      },
    },
    dungeonData: {
      connections: [
        {
          connection_id: 'door-a-b',
          from_room: 'visual-room',
          to_room: 'next-room',
          from_hex: { q: 2, r: 2 },
          to_hex: { q: 4, r: 4 },
          is_passable: false,
          is_discovered: false,
        },
      ],
      entities: [
        {
          entity_type: 'obstacle',
          placement: { room_id: 'visual-room', hex: { q: 2, r: 2 } },
          state: { metadata: { passable: false } },
        },
      ],
    },
    paintActiveRoom() {
      painted += 1;
    },
    refreshFogOfWar() {
      fogRefreshed += 1;
    },
    findObstacleEntityAtHex() {
      return null;
    },
  };

  context.getVisualRooms = methods.getVisualRooms.bind(context);
  context.getVisualConnections = methods.getVisualConnections.bind(context);
  context.parseVisualHexId = methods.parseVisualHexId.bind(context);
  context.getConnectionRoomId = methods.getConnectionRoomId.bind(context);
  context.getConnectionHex = methods.getConnectionHex.bind(context);

  methods.applyWorldDelta.call(context, {
    type: 'open_passage',
    room_id: 'visual-room',
    connection_id: 'door-a-b',
    target_hex: { q: 2, r: 2 },
  });
  methods.applyWorldDelta.call(context, {
    type: 'open_door',
    room_id: 'visual-room',
    target_hex: { q: 2, r: 2 },
  });
  methods.applyWorldDelta.call(context, {
    type: 'move_object',
    room_id: 'visual-room',
    target_hex: { q: 2, r: 2 },
    destination_hex: { q: 3, r: 2 },
  });

  assert(context.mapVisualState.topology.connections[0].is_passable === true, 'World deltas update canonical connection passability');
  assert(context.mapVisualState.topology.connections[0].is_discovered === true, 'World deltas update canonical connection discovery state');
  assert(context.mapVisualState.topology.rooms['visual-room'].hexes[0].objects[0].passable === true, 'World deltas update canonical door/object passability');
  assert(context.mapVisualState.topology.rooms['visual-room'].hexes[0].objects.length === 1, 'World deltas remove moved canonical objects from the source hex');
  assert(context.mapVisualState.topology.rooms['visual-room'].hexes[1].objects[0]?.object_id === 'crate', 'World deltas move canonical objects to the destination hex');
  assert(painted === 3 && fogRefreshed === 3, 'World deltas still refresh room rendering and fog');
}

{
  const context = {
    hexmap: {
      dungeonData: {
        rooms: {
          legacy_room: {
            room_id: 'legacy_room',
            name: 'Legacy Room',
            state: { explored: false },
          },
        },
        location_history: [],
      },
      getVisualRooms() {
        return {
          visual_room: {
            room_id: 'visual_room',
            name: 'Visual Room',
            state: { explored: true },
          },
        };
      },
      resolveActiveRoomId() {
        return 'active-room';
      },
      resolveNavigationCapabilities() {
        return [];
      },
      resolveVisitedRoomEntryHex(roomId) {
        return roomId === 'visual_room' ? { q: 3, r: 4 } : null;
      },
    },
  };

  const groups = methods.collectNavigateLocationGroups(context);
  const locations = groups.flatMap((group) => group.locations);

  assert(locations.some((location) => location.roomId === 'visual_room'), 'Navigation location groups prefer canonical visual rooms over payload rooms');
  assert(!locations.some((location) => location.roomId === 'legacy_room'), 'Navigation location groups stop surfacing stale payload-only rooms when canonical rooms exist');
}

{
  const context = {
    hexmap: {
      dungeonData: {
        rooms: {
          legacy_room: { room_id: 'legacy_room', name: 'Legacy Room' },
        },
      },
      getVisualRooms() {
        return {};
      },
      resolveActiveRoomId() {
        return null;
      },
      resolveNavigationCapabilities() {
        return [];
      },
      resolveVisitedRoomEntryHex() {
        return null;
      },
    },
  };

  const groups = methods.collectNavigateLocationGroups(context);
  const locations = groups.flatMap((group) => group.locations);

  assert(locations.length === 0, 'Navigation location groups stay empty when only legacy payload rooms exist');
}

{
  const context = {
    hexmap: {
      dungeonData: { map_id: 'current-map' },
      launchContext: {},
      getVisualRooms() {
        return {
          'active-room': { room_id: 'active-room', name: 'Active Room', state: { explored: true } },
          'direct-room': { room_id: 'direct-room', name: 'Direct Room', state: { explored: true } },
        };
      },
      resolveActiveRoomId() {
        return 'active-room';
      },
      resolveNavigationCapabilities() {
        return [{
          connection_id: 'active-direct',
          target_room_id: 'direct-room',
          available: true,
          origin_hex: { q: 1, r: 2 },
          target_hex: { q: 3, r: 4 },
        }];
      },
      resolveVisitedRoomEntryHex() {
        return null;
      },
    },
  };
  const panel = {
    navigateLocationsCampaignId: 99,
    navigateLocationGroups: [
      {
        dungeonId: 'current-map',
        dungeonName: 'Current Dungeon',
        mapId: 'current-map',
        dungeonLevelId: 'current-level',
        locations: [
          { roomId: 'active-room', roomName: 'Active Room', lastVisitedLabel: 'Visited today' },
          { roomId: 'direct-room', roomName: 'Direct Room', lastVisitedLabel: 'Visited today' },
          { roomId: 'visited-room', roomName: 'Visited Room', lastVisitedLabel: 'Visited yesterday' },
        ],
      },
      {
        dungeonId: 'remote-map',
        dungeonName: 'Remote Dungeon',
        mapId: 'remote-map',
        dungeonLevelId: 'remote-level',
        locations: [
          { roomId: 'remote-room', roomName: 'Remote Room', lastVisitedLabel: 'Visited last week' },
        ],
      },
    ],
    stateManager: { get() { return null; } },
    collectNavigateLocationGroups(ctx) {
      return methods.collectNavigateLocationGroups(ctx);
    },
  };

  const groups = methods.collectVisitedNavigateLocationGroups.call(panel, context, 99);
  const locations = groups.flatMap((group) => group.locations);

  assert(locations.some((location) => location.roomId === 'visited-room'), 'Visited navigation restores same-map history destinations not covered by direct routes');
  assert(locations.some((location) => location.roomId === 'remote-room' && location.mapId === 'remote-map'), 'Visited navigation restores cross-dungeon destinations from the old hexmap');
  assert(!locations.some((location) => location.roomId === 'active-room'), 'Visited navigation filters the current active room');
  assert(!locations.some((location) => location.roomId === 'direct-room'), 'Visited navigation deduplicates canonical direct routes');
}

{
  let selectedRoomId = null;
  let inspectorRendered = false;
  const context = {
    activeRoomId: 'stale-room',
    mapVisualState: {
      schema_version: '1.0.0',
      topology: {
        rooms: {
          'visual-room': { room_id: 'visual-room', name: 'Visual Room', hexes: [] },
        },
      },
    },
    dungeonData: {},
    stateManager: { get() { return null; } },
    launchContext: {},
    getVisualRooms: null,
    resolveActiveRoomId: null,
    setActiveRoom(roomId) {
      selectedRoomId = roomId;
    },
    renderDungeonStateInspector() {
      inspectorRendered = true;
    },
  };

  context.getVisualRooms = methods.getVisualRooms.bind(context);
  context.resolveActiveRoomId = methods.resolveActiveRoomId.bind(context);

  methods.applyDungeonData.call(context);

  assert(selectedRoomId === 'visual-room', 'Map application replaces a stale active room with a valid canonical room');
  assert(inspectorRendered === true, 'Initial map application still refreshes the state inspector');
}

{
  let selectedRoomId = null;
  let inspectorRendered = false;
  const context = {
    activeRoomId: 'stale-room',
    mapVisualState: {
      schema_version: '1.0.0',
      topology: {
        rooms: {},
      },
    },
    dungeonData: {
      rooms: {
        legacy_room: { room_id: 'legacy_room', name: 'Legacy Room', hexes: [] },
      },
    },
    stateManager: { get() { return null; } },
    launchContext: {},
    getVisualRooms: null,
    resolveActiveRoomId: null,
    setActiveRoom(roomId) {
      selectedRoomId = roomId;
    },
    renderDungeonStateInspector() {
      inspectorRendered = true;
    },
  };

  context.getVisualRooms = methods.getVisualRooms.bind(context);
  context.resolveActiveRoomId = methods.resolveActiveRoomId.bind(context);

  methods.applyDungeonData.call(context);

  assert(selectedRoomId === null, 'Map application ignores legacy payload-only rooms during strict canonical bootstrap');
  assert(inspectorRendered === false, 'Map application stays idle when canonical rooms are absent');
}

// ── Portrait panel — canonical occupant cutover ──────────────────────────────

{
  // PC and NPC occupants are returned for the matching room from canonical state.
  const context = {
    stateManager: {
      hexmap: {
        mapVisualState: {
          occupants: {
            party: [
              {
                occupant_id: 'pc-1',
                occupant_type: 'player_character',
                content_id: 'hero',
                room_id: 'room-a',
                label: 'Hero',
                visible: true,
                presentation: { portrait_url: 'https://example.com/hero.png', role: '', is_merchant: false },
              },
            ],
            entities: [
              {
                occupant_id: 'npc-1',
                occupant_type: 'npc',
                content_id: 'guard',
                room_id: 'room-a',
                label: 'Guard',
                visible: true,
                presentation: { portrait_url: null, role: 'guard', is_merchant: false },
              },
              {
                occupant_id: 'npc-2',
                occupant_type: 'npc',
                content_id: 'shadow',
                room_id: 'room-b',
                label: 'Shadow',
                visible: true,
                presentation: { portrait_url: null, role: '', is_merchant: false },
              },
            ],
          },
        },
        hasVisualOccupants: methods.hasVisualOccupants,
        getVisualOccupants: methods.getVisualOccupants,
        isVisualOccupantVisible: methods.isVisualOccupantVisible,
        getObjectDefinition() { return null; },
        resolveActiveRoomId() { return 'room-a'; },
      },
    },
  };

  const entries = methods.buildRoomPortraitEntries.call(context, 'room-a');
  assert(entries.length === 2, 'Portrait entries includes PC and NPC from active room');
  const pc = entries.find((e) => e.entityId === 'pc-1');
  const npc = entries.find((e) => e.entityId === 'npc-1');
  assert(pc?.kind === 'PC', 'PC occupant mapped to PC kind');
  assert(npc?.kind === 'NPC', 'NPC occupant mapped to NPC kind');
  assert(pc?.portraitUrl === 'https://example.com/hero.png', 'Portrait URL sourced from canonical presentation');
  assert(npc?.summary === 'guard', 'NPC role sourced from canonical presentation.role');
  assert(entries[0].kind === 'PC', 'PCs sorted before NPCs in portrait entries');
}

{
  // Occupants from a different room are excluded.
  const context = {
    stateManager: {
      hexmap: {
        mapVisualState: {
          occupants: {
            party: [],
            entities: [
              {
                occupant_id: 'npc-other',
                occupant_type: 'npc',
                content_id: 'traveler',
                room_id: 'room-b',
                label: 'Traveler',
                visible: true,
                presentation: { portrait_url: null, role: '', is_merchant: false },
              },
            ],
          },
        },
        hasVisualOccupants: methods.hasVisualOccupants,
        getVisualOccupants: methods.getVisualOccupants,
        isVisualOccupantVisible: methods.isVisualOccupantVisible,
        getObjectDefinition() { return null; },
        resolveActiveRoomId() { return 'room-a'; },
      },
    },
  };

  const entries = methods.buildRoomPortraitEntries.call(context, 'room-a');
  assert(entries.length === 0, 'Occupants in other rooms excluded from portrait entries');
}

{
  // Hidden occupants (visible: false) are excluded.
  const context = {
    stateManager: {
      hexmap: {
        mapVisualState: {
          occupants: {
            party: [],
            entities: [
              {
                occupant_id: 'npc-hidden',
                occupant_type: 'npc',
                content_id: 'ghost',
                room_id: 'room-a',
                label: 'Ghost',
                visible: false,
                presentation: { portrait_url: null, role: '', is_merchant: false },
              },
            ],
          },
        },
        hasVisualOccupants: methods.hasVisualOccupants,
        getVisualOccupants: methods.getVisualOccupants,
        isVisualOccupantVisible: methods.isVisualOccupantVisible,
        getObjectDefinition() { return null; },
        resolveActiveRoomId() { return 'room-a'; },
      },
    },
  };

  const entries = methods.buildRoomPortraitEntries.call(context, 'room-a');
  assert(entries.length === 0, 'Hidden occupants excluded from portrait entries');
}

// ── Merchant panel — canonical is_merchant cutover ───────────────────────────

{
  // Merchant occupants (is_merchant: true) are returned; non-merchants are excluded.
  const context = {
    stateManager: {
      hexmap: {
        mapVisualState: {
          occupants: {
            party: [],
            entities: [
              {
                occupant_id: 'npc-merchant',
                occupant_type: 'npc',
                content_id: 'blacksmith',
                room_id: 'room-a',
                label: 'Anvil',
                visible: true,
                presentation: { portrait_url: 'https://example.com/anvil.png', role: 'blacksmith', is_merchant: true },
              },
              {
                occupant_id: 'npc-guard',
                occupant_type: 'npc',
                content_id: 'guard',
                room_id: 'room-a',
                label: 'Guard',
                visible: true,
                presentation: { portrait_url: null, role: 'guard', is_merchant: false },
              },
            ],
          },
        },
        hasVisualOccupants: methods.hasVisualOccupants,
        getVisualOccupants: methods.getVisualOccupants,
        isVisualOccupantVisible: methods.isVisualOccupantVisible,
        resolveActiveRoomId() { return 'room-a'; },
      },
    },
  };

  const entries = methods.buildRoomMerchantEntries.call(context, 'room-a');
  assert(entries.length === 1, 'Merchant panel returns only is_merchant occupants');
  assert(entries[0].entityId === 'npc-merchant', 'Correct merchant occupant returned');
  assert(entries[0].name === 'Anvil', 'Merchant name sourced from canonical label');
  assert(entries[0].summary === 'blacksmith', 'Merchant summary sourced from canonical presentation.role');
  assert(entries[0].portraitUrl === 'https://example.com/anvil.png', 'Merchant portrait URL from canonical presentation');
}

{
  // No merchants in room returns empty array.
  const context = {
    stateManager: {
      hexmap: {
        mapVisualState: {
          occupants: {
            party: [],
            entities: [
              {
                occupant_id: 'npc-guard',
                occupant_type: 'npc',
                content_id: 'guard',
                room_id: 'room-a',
                label: 'Guard',
                visible: true,
                presentation: { portrait_url: null, role: 'guard', is_merchant: false },
              },
            ],
          },
        },
        hasVisualOccupants: methods.hasVisualOccupants,
        getVisualOccupants: methods.getVisualOccupants,
        isVisualOccupantVisible: methods.isVisualOccupantVisible,
        resolveActiveRoomId() { return 'room-a'; },
      },
    },
  };

  const entries = methods.buildRoomMerchantEntries.call(context, 'room-a');
  assert(entries.length === 0, 'Merchant panel returns empty when no merchants in room');
}

{
  // Merchant in different room is excluded.
  const context = {
    stateManager: {
      hexmap: {
        mapVisualState: {
          occupants: {
            party: [],
            entities: [
              {
                occupant_id: 'npc-far-merchant',
                occupant_type: 'npc',
                content_id: 'trader',
                room_id: 'room-b',
                label: 'Trader',
                visible: true,
                presentation: { portrait_url: null, role: 'trader', is_merchant: true },
              },
            ],
          },
        },
        hasVisualOccupants: methods.hasVisualOccupants,
        getVisualOccupants: methods.getVisualOccupants,
        isVisualOccupantVisible: methods.isVisualOccupantVisible,
        resolveActiveRoomId() { return 'room-a'; },
      },
    },
  };

  const entries = methods.buildRoomMerchantEntries.call(context, 'room-a');
  assert(entries.length === 0, 'Merchant in different room excluded from merchant panel');
}

console.log('\n============================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
