/**
 * @file
 * Regression coverage for server-authoritative encounter logging contracts.
 *
 * Run with:
 *   node tests/encounter_system_logging_contract_test.js
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
    this.events = [];
  }

  emit(name, payload) {
    this.events.push({ name, payload });
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

const sourcePath = path.resolve(__dirname, '../js/v2/systems/EncounterSystem.js');
const source = fs.readFileSync(sourcePath, 'utf8');
const resolveEntityNameSource = extractMethodSource(source, '  _resolveEntityName(entity) {')
  .replace('  _resolveEntityName(entity) {', 'function _resolveEntityName(entity) {');
const announceRoundChangeSource = extractMethodSource(source, '  announceRoundChange(data = {}) {')
  .replace('  announceRoundChange(data = {}) {', 'function announceRoundChange(data = {}) {');
const announceTurnChangeSource = extractMethodSource(source, '  announceTurnChange(data = {}) {')
  .replace('  announceTurnChange(data = {}) {', 'function announceTurnChange(data = {}) {');
const endCurrentTurnSource = extractMethodSource(source, '  async endCurrentTurn(data = {}) {')
  .replace('  async endCurrentTurn(data = {}) {', 'async function endCurrentTurn(data = {}) {');

const factory = new Function(`
${resolveEntityNameSource}
${announceRoundChangeSource}
${announceTurnChangeSource}
${endCurrentTurnSource}
return { _resolveEntityName, announceRoundChange, announceTurnChange, endCurrentTurn };
`);

const { _resolveEntityName, announceRoundChange, announceTurnChange, endCurrentTurn } = factory();

(async () => {
  console.log('\n=== Encounter system logging contract ===');

  {
    const bus = new TestBus();
    const infoLogs = [];
    const originalInfo = console.info;
    console.info = (...args) => infoLogs.push(args);

    try {
      const system = {
        bus,
        shell: { turnManagementSystem: { currentRound: 3 } },
        _lastAnnouncedRound: null,
        _lastAnnouncedActorKey: '',
        _resolveEntityName,
      };

      announceRoundChange.call(system, { roundNumber: 3 });
      announceTurnChange.call(system, { entity: { id: 'npc-1', getComponent() { return { name: 'Bandit' }; } }, turnIndex: 1, totalTurns: 4 });

      assert(bus.events.length === 0, 'round/turn observer methods do not emit chat messages directly');
      assert(infoLogs.some((entry) => entry[0] === '[EncounterFlow] round_start'), 'round changes are traced in the console');
      assert(infoLogs.some((entry) => entry[0] === '[EncounterFlow] turn_start'), 'turn changes are traced in the console');
    } finally {
      console.info = originalInfo;
    }
  }

  {
    const bus = new TestBus();
    const infoLogs = [];
    const warnLogs = [];
    const originalInfo = console.info;
    const originalWarn = console.warn;
    console.info = (...args) => infoLogs.push(args);
    console.warn = (...args) => warnLogs.push(args);

    try {
      const coordinator = {
        api: {
          async sendAction() {
            return {
              success: true,
              events: [],
              game_state: { encounter_id: 42 },
            };
          },
        },
        applyAuthoritativeUpdate() {},
        phaseManager: { stateVersion: 7 },
      };

      const coordinatorCalls = [];
      const system = {
        bus,
        _beginActionRailRequest() { return true; },
        _endActionRailRequest() {},
        async _sendCoordinatorActionWithResync(resyncCoordinator, type, actorRef, params) {
          coordinatorCalls.push({ resyncCoordinator, type, actorRef, params });
          return resyncCoordinator.api.sendAction({
            type,
            actor_ref: actorRef,
            params,
            client_state_version: resyncCoordinator.phaseManager?.stateVersion || 0,
          });
        },
        _getActionRailContext() {
          return {
            actorRef: 'hero-1',
            actorLabel: 'Hero',
            characterId: 9,
            runtimeContext: { roomId: 'room-a' },
            availableActions: ['end_turn'],
            hexmap: {
              gameCoordinator: coordinator,
              resolveActiveRoomId() { return 'room-a'; },
            },
          };
        },
        announceGameState() {},
        _refreshActionRail() {},
      };

      await endCurrentTurn.call(system, {});

      assert(coordinatorCalls.length === 1, 'end-turn requests coordinator actions through the resync helper');
      assert(coordinatorCalls[0].type === 'end_turn', 'end-turn defaults to the end_turn action when choose_not_to_act is unavailable');
      assert(bus.events.filter((event) => event.name === 'chat:system-message').length === 0, 'end-turn without authoritative events does not fabricate client chat lines');
      assert(infoLogs.some((entry) => entry[0] === '[EncounterFlow] turn_action_ack'), 'end-turn acknowledgements are traced in the console');
      assert(warnLogs.some((entry) => entry[0] === '[EncounterFlow] missing authoritative turn events'), 'missing authoritative turn events are surfaced as console warnings');
    } finally {
      console.info = originalInfo;
      console.warn = originalWarn;
    }
  }

  console.log(`\nPassed: ${passed}`);
  console.log(`Failed: ${failed}`);

  if (failed > 0) {
    process.exit(1);
  }
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
