/**
 * @file
 * Regression coverage for GameShell interaction-state helpers.
 *
 * Run with:
 *   node tests/hexmap_v2_interaction_state_test.js
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

class TestBus {
  constructor() {
    this.handlers = new Map();
    this.events = [];
  }

  on(name, handler) {
    if (!this.handlers.has(name)) {
      this.handlers.set(name, []);
    }
    this.handlers.get(name).push(handler);
    return () => {
      const next = (this.handlers.get(name) || []).filter((candidate) => candidate !== handler);
      this.handlers.set(name, next);
    };
  }

  emit(name, payload) {
    this.events.push({ name, payload });
    (this.handlers.get(name) || []).forEach((handler) => handler(payload));
  }
}

function extractMethodSource(source, methodSignature) {
  const start = source.indexOf(methodSignature);
  if (start === -1) {
    throw new Error(`Could not find method: ${methodSignature}`);
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
    throw new Error(`Could not find method body: ${methodSignature}`);
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

  throw new Error(`Could not extract method: ${methodSignature}`);
}

function extractNamedFunctionSource(source, functionName) {
  const signature = `function ${functionName}(`;
  const start = source.indexOf(signature);
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

  throw new Error(`Could not extract function: ${functionName}`);
}

const sourcePath = path.resolve(__dirname, '../js/v2/GameShell.js');
const source = fs.readFileSync(sourcePath, 'utf8');
const bindInteractionSource = extractMethodSource(source, '  _bindInteractionEvents() {')
  .replace('  _bindInteractionEvents() {', 'function _bindInteractionEvents() {');
const setSelectedHexSource = extractMethodSource(source, '  setSelectedHex(q, r, options = {}) {')
  .replace('  setSelectedHex(q, r, options = {}) {', 'function setSelectedHex(q, r, options = {}) {');
const getHexDetailSource = extractMethodSource(source, '  getHexDetail(q, r) {')
  .replace('  getHexDetail(q, r) {', 'function getHexDetail(q, r) {');
const setActiveRoomSource = extractMethodSource(source, '  setActiveRoom(roomId) {')
  .replace('  setActiveRoom(roomId) {', 'function setActiveRoom(roomId) {');
const getEntityDisplayNameSource = extractNamedFunctionSource(source, '_getEntityDisplayName');

const factory = new Function(`
function _buildRoomConnections(roomId, mapVisualState) {
  return [{ roomId, marker: mapVisualState?.marker || null }];
}
${getEntityDisplayNameSource}
${bindInteractionSource}
${setSelectedHexSource}
${getHexDetailSource}
${setActiveRoomSource}
return { _bindInteractionEvents, setSelectedHex, getHexDetail, setActiveRoom };
`);

const { _bindInteractionEvents, setSelectedHex, getHexDetail, setActiveRoom } = factory();

console.log('\n=== Hexmap V2 interaction state ===');

{
  const bus = new TestBus();
  const state = {};
  const shell = {
    bus,
    _busUnsubs: [],
    _setStateValue(key, value) {
      state[key] = value;
    },
    getHexDetail(q, r) {
      return { q, r };
    },
    tryTransitionAtHex() {
      return false;
    },
    getEntitiesAtHex() {
      return [];
    },
    deselectEntity() {
      state.selectedEntity = null;
    },
    setSelectedHex,
  };

  _bindInteractionEvents.call(shell);
  bus.emit('hex:clicked', { q: 4, r: 5, button: 0, entities: [] });

  const detailEvents = bus.events.filter((event) => event.name === 'hex:details');
  assert(detailEvents.length === 1, 'click selection emits hex-details exactly once');
  assert(state.selectedHex?.q === 4 && state.selectedHex?.r === 5, 'click selection persists selected-hex state');
}

{
  const bus = new TestBus();
  const state = {
    selectedEntity: { id: 'entity-2' },
  };
  const makeEntity = (id, name, entityType, team) => ({
    id,
    dcEntityType: entityType,
    getComponent(componentName) {
      if (componentName === 'IdentityComponent') {
        return { name, entityType };
      }
      if (componentName === 'CombatComponent') {
        return { team };
      }
      return null;
    },
  });

  const shell = {
    bus,
    _busUnsubs: [],
    _setStateValue(key, value) {
      state[key] = value;
    },
    _getStateValue(key) {
      return state[key];
    },
    getHexDetail(q, r) {
      return { q, r };
    },
    tryTransitionAtHex() {
      return false;
    },
    getEntitiesAtHex() {
      return [];
    },
    deselectEntity() {
      state.selectedEntity = null;
    },
    selectEntity() {},
    setSelectedHex,
  };

  _bindInteractionEvents.call(shell);
  bus.emit('hex:clicked', {
    q: 1,
    r: 1,
    button: 0,
    entities: [
      makeEntity('entity-1', 'Guard', 'npc', 'enemy'),
      makeEntity('entity-2', 'Scout', 'npc', 'ally'),
    ],
  });

  const contentsEvent = bus.events.find((event) => event.name === 'hex:contents');
  assert(contentsEvent?.payload?.occupants?.[0]?.name === 'Guard', 'hex contents exposes occupant names using the status-panel contract');
  assert(contentsEvent?.payload?.occupants?.[0]?.typeLabel === 'npc', 'hex contents exposes occupant type labels using the status-panel contract');
  assert(contentsEvent?.payload?.occupants?.[0]?.teamLabel === 'enemy', 'hex contents exposes occupant team labels using the status-panel contract');
  assert(contentsEvent?.payload?.occupants?.[1]?.isSelected === true, 'hex contents marks the currently selected occupant');
}

{
  const bus = new TestBus();
  const state = {};
  const shell = {
    bus,
    _setStateValue(key, value) {
      state[key] = value;
    },
    getHexDetail(q, r) {
      return { q, r };
    },
  };

  setSelectedHex.call(shell, 8, 9, { emitDetails: false });
  const detailEvents = bus.events.filter((event) => event.name === 'hex:details');
  assert(detailEvents.length === 0, 'setSelectedHex can suppress detail emission when callers already emit details');
  assert(state.selectedHex?.q === 8 && state.selectedHex?.r === 9, 'setSelectedHex still stores selected coordinates when detail emission is suppressed');
}

{
  const shell = {
    getActiveRoomHex() {
      return {
        terrain: 'stone',
        lighting: 'dim',
        elevation_ft: 5,
        objects: [{ label: 'Crate' }, { object_id: 'door_arch' }],
      };
    },
    getActiveRoomData() {
      return { name: 'Hallway' };
    },
    resolveNavigationCapabilityAtHex() {
      return {
        type: 'door',
        target_room_id: 'north_room',
        available: false,
        blocked_reason: 'locked',
      };
    },
    getEntitiesAtHex() {
      return [{
        getComponent(name) {
          return name === 'IdentityComponent' ? { name: 'Guard' } : null;
        },
      }];
    },
    getObstacleMobilityAtHex() {
      return { passable: false };
    },
    resolveActiveRoomId() {
      return 'hallway';
    },
  };

  const detail = getHexDetail.call(shell, 1, 2);
  assert(Array.isArray(detail.entities) && detail.entities[0] === 'Guard', 'hex detail exposes entity labels instead of raw entity objects');
  assert(Array.isArray(detail.objects) && detail.objects.join(', ') === 'Crate, door_arch', 'hex detail exposes object labels instead of raw object records');
  assert(detail.connection === 'door -> north_room (locked)', 'hex detail formats connection status for status-panel rendering');
}

{
  const bus = new TestBus();
  const calls = [];
  const shell = {
    bus,
    mapVisualState: { marker: 'test-connection' },
    getVisualRooms() {
      return { target_room: { name: 'Target Room', image_url: '/room.png' } };
    },
    getVisualOccupants() {
      return [
        { room_id: 'target_room', label: 'Merchant' },
        { room_id: 'other_room', label: 'Other' },
      ];
    },
    isVisualOccupantVisible(occupant) {
      return occupant.label === 'Merchant';
    },
    _setStateValue(key, value) {
      calls.push(['state', key, value]);
    },
    _syncActiveRoomEntities(roomId) {
      calls.push(['sync', roomId]);
    },
  };

  setActiveRoom.call(shell, 'target_room');

  const roomChanged = bus.events.find((event) => event.name === 'room:changed');
  const occupantsChanged = bus.events.find((event) => event.name === 'room:occupants-changed');
  assert(calls.some((call) => call[0] === 'sync' && call[1] === 'target_room'), 'setActiveRoom re-syncs active-room entities during transitions');
  assert(Array.isArray(roomChanged?.payload?.connections) && roomChanged.payload.connections.length === 1, 'setActiveRoom emits room-change payloads with canonical connections');
  assert(Array.isArray(occupantsChanged?.payload?.occupants) && occupantsChanged.payload.occupants.length === 1, 'setActiveRoom re-broadcasts visible occupants for room-driven panels');
}

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
