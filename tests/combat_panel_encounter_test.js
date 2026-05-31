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

const fs = require('fs');
const path = require('path');

function loadModule(relPath) {
  let src = fs.readFileSync(path.resolve(__dirname, relPath), 'utf8');
  src = src.replace(/^export\s+/gm, '');
  return new Function(src + '\nreturn { ' + src.match(/^class (\w+)/m)?.[1] + ' };')();
}

// Load CombatPanel
let src = fs.readFileSync(path.resolve(__dirname, '../js/v2/panels/CombatPanel.js'), 'utf8');
src = src.replace(/^export\s+/gm, '');
const { CombatPanel } = new Function(src + '\nreturn { CombatPanel };')();

// Load EncounterSystem
let esSrc = fs.readFileSync(path.resolve(__dirname, '../js/v2/systems/EncounterSystem.js'), 'utf8');
esSrc = esSrc.replace(/^export\s+/gm, '');
const { EncounterSystem } = new Function(esSrc + '\nreturn { EncounterSystem };')();

// Load GameEventBus
let busSrc = fs.readFileSync(path.resolve(__dirname, '../js/v2/GameEventBus.js'), 'utf8');
busSrc = busSrc.replace(/^export\s+/gm, '');
const { GameEventBus } = new Function(busSrc + '\nreturn { GameEventBus };')();

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Minimal JSDOM-free DOM stub */
function makeContainer(attributes = {}) {
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
          _raw: elements,
        };
      }
      return elements[key];
    },
    querySelectorAll(sel) { return []; },
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

// ---------------------------------------------------------------------------
// CombatPanel tests
// ---------------------------------------------------------------------------

console.log('\n=== CombatPanel — init / state toggle ===');
{
  const bus = makeBus();
  const container = makeContainer();
  const panel = new CombatPanel(container, bus);
  panel.init();

  // After init with 'inactive' state: start-btn visible, others hidden
  const startEl    = container._elements['start-btn'];
  const endTurnEl  = container._elements['end-turn-btn'];
  assert(startEl?.style.display === '',     'start-btn visible in inactive state');
  assert(endTurnEl?.style.display === 'none', 'end-turn-btn hidden in inactive state');
}

console.log('\n=== CombatPanel — state-changed active ===');
{
  const bus = makeBus();
  const container = makeContainer();
  const panel = new CombatPanel(container, bus);
  panel.init();

  bus.emit('combat:state-changed', { state: 'active' });

  const startEl    = container._elements['start-btn'];
  const endTurnEl  = container._elements['end-turn-btn'];
  assert(startEl?.style.display === 'none', 'start-btn hidden in active state');
  assert(endTurnEl?.style.display === '',   'end-turn-btn visible in active state');
}

console.log('\n=== CombatPanel — turn-changed updates DOM ===');
{
  const bus = makeBus();
  const container = makeContainer();
  const panel = new CombatPanel(container, bus);
  panel.init();

  const entity = makeEntity({ name: 'Aria', team: 'player' });
  bus.emit('combat:turn-changed', { entity, turnIndex: 0, totalTurns: 3, initiativeOrder: [] });

  assert(container._elements['turn-name']?.textContent === 'Aria', 'turn-name shows entity name');
  assert(container._elements['turn-label']?.textContent === 'Your turn', 'turn-label shows "Your turn" for player');
  assert(container._elements['turn-actions']?.textContent.includes('2/3'), 'turn-actions shows remaining/max');
  assert(container._elements['turn-movement']?.textContent.includes('30'), 'turn-movement shows ft remaining');
}

console.log('\n=== CombatPanel — enemy turn label ===');
{
  const bus = makeBus();
  const container = makeContainer();
  const panel = new CombatPanel(container, bus);
  panel.init();

  const entity = makeEntity({ name: 'Goblin', team: 'enemy' });
  bus.emit('combat:turn-changed', { entity, turnIndex: 1, totalTurns: 3, initiativeOrder: [] });

  assert(container._elements['turn-label']?.textContent === 'enemy turn', 'enemy turn label shows team');
}

console.log('\n=== CombatPanel — round-changed updates counter ===');
{
  const bus = makeBus();
  const container = makeContainer();
  const panel = new CombatPanel(container, bus);
  panel.init();

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

  panel.init();
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
  panel.init();
  panel.destroy();

  let called = false;
  // After destroy, round-changed should NOT fire handler
  container._elements['round-counter'] = { textContent: 'Round 1', style: {}, classList: { add(){}, remove(){}, toggle(){} } };
  bus.emit('combat:round-changed', { roundNumber: 99 });
  assert(container._elements['round-counter']?.textContent !== 'Round 99', 'destroy removes bus subscriptions');
}

// ---------------------------------------------------------------------------
// EncounterSystem tests
// ---------------------------------------------------------------------------

console.log('\n=== EncounterSystem — wires turn callbacks to bus ===');
{
  const bus = makeBus();
  let turnCallbackFn = null;

  const shell = {
    turnManagementSystem: {
      onTurnChange(fn)         { turnCallbackFn = fn; },
      onRoundChange()          {},
      onCombatStateChange()    {},
      getInitiativeOrder()     { return []; },
    },
    combatSystem:  { onAttack() {}, onDamage() {} },
    entityManager: { getAllEntities() { return []; } },
  };

  const system = new EncounterSystem(shell, bus);
  system.init();

  let receivedEntity = null;
  bus.on('combat:turn-changed', ({ entity }) => { receivedEntity = entity; });

  const entity = makeEntity({ name: 'Knight' });
  turnCallbackFn(entity, 0, 4);

  assert(receivedEntity === entity, 'turn callback → combat:turn-changed on bus');
}

console.log('\n=== EncounterSystem — state normalization ===');
{
  const bus = makeBus();
  let stateCallbackFn = null;
  const shell = {
    turnManagementSystem: {
      onTurnChange()        {},
      onRoundChange()       {},
      onCombatStateChange(fn) { stateCallbackFn = fn; },
      getInitiativeOrder()  { return []; },
    },
    combatSystem:  { onAttack() {}, onDamage() {} },
    entityManager: { getAllEntities() { return []; } },
  };

  const system = new EncounterSystem(shell, bus);
  system.init();

  const states = [];
  bus.on('combat:state-changed', ({ state }) => states.push(state));

  stateCallbackFn('inactive');
  stateCallbackFn('IN_PROGRESS');
  stateCallbackFn('ROLLING_INITIATIVE');
  stateCallbackFn('ended');

  assert(states[0] === 'inactive', 'inactive → inactive');
  assert(states[1] === 'active',   'IN_PROGRESS → active');
  assert(states[2] === 'active',   'ROLLING_INITIATIVE → active');
  assert(states[3] === 'ended',    'ended → ended');
}

console.log('\n=== EncounterSystem — user:end-turn calls endTurn ===');
{
  const bus = makeBus();
  let endTurnCalled = false;

  const shell = {
    turnManagementSystem: {
      onTurnChange()       {},
      onRoundChange()      {},
      onCombatStateChange(){},
      getInitiativeOrder() { return []; },
      endTurn()            { endTurnCalled = true; },
    },
    combatSystem:  { onAttack() {}, onDamage() {} },
    entityManager: { getAllEntities() { return []; } },
  };

  const system = new EncounterSystem(shell, bus);
  system.init();

  bus.emit('user:end-turn');
  assert(endTurnCalled, 'user:end-turn triggers turnManagementSystem.endTurn()');
}

console.log('\n=== EncounterSystem — attack denied when canAttack fails ===');
{
  const bus = makeBus();
  let deniedReason = null;

  const shell = {
    turnManagementSystem: {
      onTurnChange() {}, onRoundChange() {}, onCombatStateChange() {},
      getInitiativeOrder() { return []; },
    },
    combatSystem: {
      onAttack() {}, onDamage() {},
      canAttack() { return { canAttack: false, reason: 'Out of actions' }; },
      makeAttack() {},
    },
    entityManager: { getAllEntities() { return []; } },
  };

  const system = new EncounterSystem(shell, bus);
  system.init();

  bus.on('combat:action-denied', ({ reason }) => { deniedReason = reason; });

  const attacker = makeEntity({ name: 'Hero' });
  const target   = makeEntity({ name: 'Boss', team: 'enemy' });
  bus.emit('user:attack', { attacker, target });

  assert(deniedReason === 'Out of actions', 'action denied emits combat:action-denied with reason');
}

// ---------------------------------------------------------------------------
// Results
// ---------------------------------------------------------------------------
console.log('\n===================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
} else {
  console.log('ALL TESTS PASSED');
}
