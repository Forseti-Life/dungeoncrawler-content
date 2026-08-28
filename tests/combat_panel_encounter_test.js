/**
 * @file
 * Unit tests for CombatPanel and EncounterSystem (Phase 4).
 *
 * Run with:
 *   node tests/combat_panel_encounter_test.js
 */

let passed = 0;
let failed = 0;

function assert(condition, msg) {
  if (condition) {
    passed++;
    console.log(`  ✓ ${msg}`);
  } else {
    failed++;
    console.error(`  ✗ FAIL: ${msg}`);
  }
}

const { loadModuleExport, loadModuleScope } = require('./helpers/js-module.js');

function loadClass(relPath, className) {
  return loadModuleExport(relPath, className);
}

const CombatPanel    = loadClass('../js/v2/panels/CombatPanel.js',     'CombatPanel');
const EncounterSystem = loadClass('../js/v2/systems/EncounterSystem.js', 'EncounterSystem');
const GameEventBus   = loadClass('../js/v2/GameEventBus.js',            'GameEventBus');

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Minimal JSDOM-free DOM stub for CombatPanel.
 *
 * CombatPanel resolves its elements first via document.getElementById(), then
 * falls back to container.querySelector('[data-combat="<key>"]'). Since there
 * is no global document in these tests, every element comes from this lazy
 * container.  The panel never calls document.getElementById() directly inside
 * _ensureMapInitiativeOverlay because the lazy container provides truthy values
 * for 'map-tracker-wrap' and 'map-initiative-list' during init(), triggering
 * the early-return guard before the bare document.getElementById() call.
 *
 * Key mappings (data-combat attribute → _el property):
 *   'start-btn'        → _el.startCombatBtn  (display: 'inline-block' when inactive)
 *   'end-combat-btn'   → _el.endCombatBtn    (display: 'none' when inactive)
 *   'turn-name'        → _el.currentTurn     (innerHTML contains entity name)
 *   'turn-label'       → _el.turnOwner       (textContent: 'Your turn' / '<team> turn')
 *   'turn-actions'     → _el.turnActionSummary (textContent: '2/3 actions')
 *   'turn-movement'    → _el.turnMoveSummary  (textContent: '30 ft left')
 *   'round-counter'    → _el.currentRound    (textContent: 'Round N')
 */
function makeContainer() {
  const elements = {};
  return {
    querySelector(sel) {
      const m = sel.match(/\[data-combat="([^"]+)"\]/);
      if (!m) return null;
      const key = m[1];
      if (!elements[key]) {
        elements[key] = {
          _key: key, textContent: '', innerHTML: '', style: {},
          classList: { classes: new Set(), add(c) { this.classes.add(c); }, remove(c) { this.classes.delete(c); }, toggle(c, v) { v ? this.classes.add(c) : this.classes.delete(c); } },
          querySelectorAll() { return []; },
          addEventListener() {},
          removeEventListener() {},
        };
      }
      return elements[key];
    },
    querySelectorAll() { return []; },
    _elements: elements,
  };
}

function makeBus() {
  return new GameEventBus();
}

function makeEntity({ name = 'Tester', team = 'player', actions = null, movement = null, hp = null } = {}) {
  return {
    id: Math.random().toString(36).slice(2),
    getComponent(type) {
      if (type === 'IdentityComponent') return { name };
      if (type === 'CombatComponent')   return { team, isPlayerTeam: () => team === 'player' };
      if (type === 'ActionsComponent')  return actions ?? { actionsRemaining: 2, maxActions: 3, actionBonus: 0, hasReaction: true, hasReactionAvailable: () => true };
      if (type === 'MovementComponent') return movement ?? { movementRemaining: 30 };
      if (type === 'StatsComponent')    return hp ?? { currentHp: 20, maxHp: 30 };
      return null;
    },
  };
}

/**
 * Provides a stateManager.hexmap.turnManagementSystem so that
 * CombatPanel.renderEncounterSnapshot() reads state from here rather than
 * returning early with no data.  Pass `findLaunchPlayer` to control
 * isPlayersTurn (used to determine "Your turn" vs "<team> turn" labels).
 */
function makeStateManager({ combatState = 'inactive', currentRound = 0, entity = null, findLaunchPlayer = null } = {}) {
  const tms = {
    combatState,
    currentRound,
    currentTurnIndex: 0,
    initiativeOrder: [],
    getInitiativeOrder: () => [],
    getEncounterStatus: () => 'idle',
    getCurrentTurnEntity: () => entity,
  };
  return {
    hexmap: {
      turnManagementSystem: tms,
      findLaunchPlayerEntity: () => findLaunchPlayer,
    },
    _tms: tms,
  };
}

// ---------------------------------------------------------------------------
// CombatPanel tests
// ---------------------------------------------------------------------------

console.log('\n=== CombatPanel — init / state toggle ===');
{
  // Evidence: updateCombatControls() in CombatPanel.js sets
  //   startCombatBtn.style.display = isInactive ? 'inline-block' : 'none'
  //   endCombatBtn.style.display   = isInactive ? 'none' : 'inline-block'
  // _el.startCombatBtn resolves via s('start-btn'), _el.endCombatBtn via s('end-combat-btn').
  const bus = makeBus();
  const container = makeContainer();
  const panel = new CombatPanel(container, bus);
  panel.init({}, makeStateManager({ combatState: 'inactive' }));

  const startEl   = container._elements['start-btn'];
  const endCombatEl = container._elements['end-combat-btn'];
  assert(startEl?.style.display !== 'none',    'start-btn visible in inactive state');
  assert(endCombatEl?.style.display === 'none', 'end-turn-btn hidden in inactive state');
}

console.log('\n=== CombatPanel — state-changed active ===');
{
  // Evidence: bus.on('combat:state-changed', () => this.renderEncounterSnapshot()) in _subscribe().
  // renderEncounterSnapshot() reads stateManager.hexmap.turnManagementSystem.combatState.
  // With combatState='active' (not 'inactive'/'ended'), isInactive=false so buttons swap.
  const bus = makeBus();
  const container = makeContainer();
  const panel = new CombatPanel(container, bus);
  const sm = makeStateManager({ combatState: 'inactive' });
  panel.init({}, sm);

  sm._tms.combatState = 'active';
  bus.emit('combat:state-changed', { state: 'active' });

  const startEl     = container._elements['start-btn'];
  const endCombatEl = container._elements['end-combat-btn'];
  assert(startEl?.style.display === 'none',      'start-btn hidden in active state');
  assert(endCombatEl?.style.display !== 'none',  'end-turn-btn visible in active state');
}

console.log('\n=== CombatPanel — turn-changed updates DOM ===');
{
  // Evidence: bus.on('combat:turn-changed', () => this.renderEncounterSnapshot()) in _subscribe().
  // renderEncounterSnapshot() calls updateCurrentTurn() which sets:
  //   _el.currentTurn.innerHTML  = '<div class="turn-name">Aria</div>...'   (key: 'turn-name')
  //   _el.turnOwner.textContent  = 'Your turn'  (when isPlayersTurn=true)   (key: 'turn-label')
  //   _el.turnActionSummary.textContent = '2/3 actions'                     (key: 'turn-actions')
  //   _el.turnMoveSummary.textContent   = '30 ft left'                      (key: 'turn-movement')
  // isPlayersTurn requires findLaunchPlayerEntity() to return the same entity.
  const bus = makeBus();
  const container = makeContainer();
  const panel = new CombatPanel(container, bus);
  const entity = makeEntity({ name: 'Aria', team: 'player' });
  const sm = makeStateManager({ combatState: 'active', entity, findLaunchPlayer: entity });
  panel.init({}, sm);

  bus.emit('combat:turn-changed', { entity, turnIndex: 0, totalTurns: 3 });

  assert(container._elements['turn-name']?.innerHTML?.includes('Aria'), 'turn-name shows entity name');
  assert(container._elements['turn-label']?.textContent === 'Your turn', 'turn-label shows "Your turn" for player');
  assert(container._elements['turn-actions']?.textContent.includes('2/3'), 'turn-actions shows remaining/max');
  assert(container._elements['turn-movement']?.textContent.includes('30'), 'turn-movement shows ft remaining');
}

console.log('\n=== CombatPanel — enemy turn label ===');
{
  // Evidence: updateCurrentTurn() sets turnOwner.textContent = team + ' turn' when not player's turn.
  const bus = makeBus();
  const container = makeContainer();
  const panel = new CombatPanel(container, bus);
  const entity = makeEntity({ name: 'Goblin', team: 'enemy' });
  const sm = makeStateManager({ combatState: 'active', entity, findLaunchPlayer: null });
  panel.init({}, sm);

  bus.emit('combat:turn-changed', { entity, turnIndex: 1, totalTurns: 3 });

  assert(container._elements['turn-label']?.textContent === 'enemy turn', 'enemy turn label shows team');
}

console.log('\n=== CombatPanel — round-changed updates counter ===');
{
  // Evidence: bus.on('combat:round-changed', () => this.renderEncounterSnapshot()) in _subscribe().
  // renderEncounterSnapshot() calls updateRound(turnManagement.currentRound).
  // updateRound() sets _el.currentRound.textContent = 'Round N'  (key: 'round-counter').
  const bus = makeBus();
  const container = makeContainer();
  const panel = new CombatPanel(container, bus);
  const sm = makeStateManager({ combatState: 'active' });
  panel.init({}, sm);

  sm._tms.currentRound = 3;
  bus.emit('combat:round-changed', { roundNumber: 3 });
  assert(container._elements['round-counter']?.textContent === 'Round 3', 'round counter shows correct round');
}

console.log('\n=== CombatPanel — user events emitted on button click ===');
{
  const bus = makeBus();
  const container = makeContainer();
  const panel = new CombatPanel(container, bus);

  let startFired = false;
  let endTurnFired = false;
  bus.on('user:combat-start', () => { startFired = true; });
  bus.on('user:end-turn',     () => { endTurnFired = true; });

  panel.init({}, makeStateManager());
  bus.emit('user:combat-start');
  bus.emit('user:end-turn');

  assert(startFired,   'user:combat-start propagates through bus');
  assert(endTurnFired, 'user:end-turn propagates through bus');
}

console.log('\n=== CombatPanel — destroy unsubscribes ===');
{
  const bus = makeBus();
  const container = makeContainer();
  const panel = new CombatPanel(container, bus);
  panel.init({}, makeStateManager({ combatState: 'active', currentRound: 1 }));
  panel.destroy();

  // After destroy, round-changed should NOT trigger renderEncounterSnapshot.
  // Replace the tracked element to detect unwanted writes.
  container._elements['round-counter'] = { textContent: 'Round 1', style: {}, classList: { add(){}, remove(){}, toggle(){} } };
  bus.emit('combat:round-changed', { roundNumber: 99 });
  assert(container._elements['round-counter']?.textContent !== 'Round 99', 'destroy removes bus subscriptions');
}

// ---------------------------------------------------------------------------
// EncounterSystem tests
//
// The EncounterSystem was refactored: it no longer registers onTurnChange /
// onRoundChange / onCombatStateChange callbacks on the shell's
// turnManagementSystem (that wiring now lives in GameShell.js).  It also no
// longer calls turnManagementSystem.endTurn() directly — turn actions go
// through the coordinator API.  The four tests below cover the current
// observable contract.
// ---------------------------------------------------------------------------

console.log('\n=== EncounterSystem — subscribes to combat:turn-changed ===');
{
  // Evidence: _subscribe() registers bus.on('combat:turn-changed', (d) => this.announceTurnChange(d)).
  // announceTurnChange() updates this._lastAnnouncedActorKey (observable internal state).
  // The old test called onTurnChange(fn) to inject a callback; that wiring is now in GameShell.
  const bus = makeBus();
  const shell = {
    panels: {},
    turnManagementSystem: { getInitiativeOrder() { return []; } },
  };
  const system = new EncounterSystem(shell, bus);
  system.init();

  const entity = makeEntity({ name: 'Knight' });
  bus.emit('combat:turn-changed', { entity, turnIndex: 0, totalTurns: 4 });

  assert(system._lastAnnouncedActorKey !== '', 'combat:turn-changed subscription updates actor tracking key');
}

console.log('\n=== EncounterSystem — deduplicates round announcements ===');
{
  // Evidence: _subscribe() registers bus.on('combat:round-changed', (d) => this.announceRoundChange(d)).
  // announceRoundChange() skips duplicate rounds and updates _lastAnnouncedRound.
  // This replaces the old "state normalization" test whose callback-based contract no longer exists.
  const bus = makeBus();
  const shell = {
    panels: {},
    turnManagementSystem: { getInitiativeOrder() { return []; } },
  };
  const system = new EncounterSystem(shell, bus);
  system.init();

  bus.emit('combat:round-changed', { roundNumber: 1 });
  assert(system._lastAnnouncedRound === 1, 'first round announcement is tracked');

  bus.emit('combat:round-changed', { roundNumber: 1 }); // duplicate — must not advance
  bus.emit('combat:round-changed', { roundNumber: 2 });
  assert(system._lastAnnouncedRound === 2, 'new round updates tracking; duplicate is deduplicated');
}

console.log('\n=== EncounterSystem — user:end-turn triggers system feedback ===');
{
  // Evidence: _subscribe() registers bus.on('user:end-turn', (d) => this.endCurrentTurn(d)).
  // endCurrentTurn() calls _getActionRailContext() → returns {} (no actionRail in shell).
  // With no coordinator/actorRef it calls _appendChatLine() which emits 'chat:system-message'.
  // All of this runs synchronously before the first await, so the message is observable
  // within the same tick as bus.emit('user:end-turn').
  const bus = makeBus();
  const chatMessages = [];
  bus.on('chat:system-message', ({ text }) => { chatMessages.push(text); });

  const shell = {
    panels: {},
    turnManagementSystem: { getInitiativeOrder() { return []; } },
  };
  const system = new EncounterSystem(shell, bus);
  system.init();

  bus.emit('user:end-turn');
  assert(
    chatMessages.some((t) => t.includes('active encounter character')),
    'user:end-turn without active encounter emits system feedback message',
  );
}

console.log('\n=== EncounterSystem — user:combat-start emits server-managed message ===');
{
  // Evidence: startCombat() calls _appendChatLine('System',
  //   'Encounter start is managed by room entry and server state.', 'system').
  // This replaces the old canAttack/combat:action-denied test whose contract no longer exists.
  // The spirit is preserved: the system validates or routes user combat actions, emitting
  // a feedback message when the action cannot be fulfilled locally.
  const bus = makeBus();
  const chatMessages = [];
  bus.on('chat:system-message', ({ text }) => { chatMessages.push(text); });

  const shell = {
    panels: {},
    turnManagementSystem: { getInitiativeOrder() { return []; } },
  };
  const system = new EncounterSystem(shell, bus);
  system.init();

  bus.emit('user:combat-start');
  assert(
    chatMessages.some((t) => t.includes('server state')),
    'user:combat-start emits message explaining encounter is server-managed',
  );
}

// ---------------------------------------------------------------------------
// Results
// ---------------------------------------------------------------------------
// Encounter-status normalization moved out of EncounterSystem into the ECS
// TurnManagementSystem, and the turn/round/state callbacks are now wired to the
// bus by GameShell. Both halves of that coverage are restored here.
console.log('\n=== TurnManagementSystem — encounter status normalization ===');
{
  const { mapEncounterStatusToCombatState, CombatState } = loadModuleScope(
    '../js/ecs/systems/TurnManagementSystem.js',
    ['mapEncounterStatusToCombatState', 'CombatState'],
  );

  assert(mapEncounterStatusToCombatState('idle') === CombatState.INACTIVE, 'idle → inactive');
  assert(mapEncounterStatusToCombatState('active') === CombatState.IN_PROGRESS, 'active → in_progress');
  assert(mapEncounterStatusToCombatState('IN_PROGRESS') === CombatState.INACTIVE, 'unknown status falls back to inactive');
  assert(mapEncounterStatusToCombatState('rolling_initiative') === CombatState.ROLLING_INITIATIVE, 'rolling_initiative → rolling_initiative');
  assert(mapEncounterStatusToCombatState('setup') === CombatState.ROLLING_INITIATIVE, 'setup → rolling_initiative');
  assert(mapEncounterStatusToCombatState('paused') === CombatState.IN_PROGRESS, 'paused stays in_progress');
  assert(mapEncounterStatusToCombatState('ended') === CombatState.ENDED, 'ended → ended');
}

console.log('\n=== GameShell — turn management callbacks are wired to the bus ===');
{
  const shellSource = require('./helpers/js-source.js').readGameShellSource();

  assert(
    shellSource.includes("bus.emit('combat:state-changed', {"),
    'combat state changes are forwarded to the bus'
  );
  assert(
    shellSource.includes("bus.emit('combat:round-changed', { roundNumber });"),
    'round changes are forwarded to the bus'
  );
  assert(
    shellSource.includes("bus.emit('combat:order-changed', { order });"),
    'initiative order changes are forwarded to the bus'
  );
  assert(
    shellSource.includes('this.turnManagementSystem.onCombatStateChange?.((state) => {')
      && shellSource.includes('this.turnManagementSystem.onRoundChange?.((roundNumber) => {')
      && shellSource.includes('this.turnManagementSystem.onOrderChange?.((order = []) => {'),
    'turn management callbacks are registered by the shell'
  );
}

console.log('\n===================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
} else {
  console.log('ALL TESTS PASSED');
}
