/**
 * @file
 * Unit tests for ActionRailPanel (Phase 5).
 *
 * Run with:
 *   node tests/action_rail_panel_test.js
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

// Load modules
function loadClass(relPath, className) {
  let src = fs.readFileSync(path.resolve(__dirname, relPath), 'utf8');
  src = src.replace(/^export\s+/gm, '');
  return new Function(src + `\nreturn { ${className} };`)()[className];
}

const ActionRailPanel = loadClass('../js/v2/panels/ActionRailPanel.js', 'ActionRailPanel');
const GameEventBus   = loadClass('../js/v2/GameEventBus.js', 'GameEventBus');

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeEntity({ name = 'Hero', team = 'player', actionsRemaining = 2, maxActions = 3, movementRemaining = 30, hp = 20, maxHp = 30, entityType = 'character' } = {}) {
  return {
    id: Math.random().toString(36).slice(2),
    getComponent(type) {
      if (type === 'IdentityComponent') return { name, entityType, isCreature: () => true };
      if (type === 'CombatComponent')   return { team, isPlayerTeam: () => team === 'player' };
      if (type === 'ActionsComponent')  return { actionsRemaining, maxActions, actionBonus: 0, hasReaction: true };
      if (type === 'MovementComponent') return { movementRemaining };
      if (type === 'StatsComponent')    return { currentHp: hp, maxHp, isAlive: () => hp > 0 };
      return null;
    },
  };
}

/** Minimal container DOM stub */
function makeContainer() {
  const elements = {};
  const clicked = [];

  const makeEl = (key) => {
    if (!elements[key]) {
      elements[key] = {
        _key: key,
        textContent: '',
        innerHTML: '',
        style: {},
        hidden: false,
        disabled: false,
        value: '',
        dataset: {},
        classList: {
          classes: new Set(),
          add(c) { this.classes.add(c); },
          remove(c) { this.classes.delete(c); },
          toggle(c, v) { v ? this.classes.add(c) : this.classes.delete(c); },
          contains(c) { return this.classes.has(c); },
        },
        _listeners: {},
        addEventListener(evt, fn) { (this._listeners[evt] = this._listeners[evt] || []).push(fn); },
        querySelectorAll(sel) {
          const m = sel.match(/\[data-action-rail-category\]/);
          return m ? [] : [];
        },
        querySelector() { return null; },
        prepend() {},
        append() {},
      };
    }
    return elements[key];
  };

  const container = {
    querySelector(sel) {
      const m = sel.match(/\[data-action-rail="([^"]+)"\]/);
      return m ? makeEl(m[1]) : null;
    },
    querySelectorAll() { return []; },
    _elements: elements,
    _clicked: clicked,
  };

  return container;
}

// ---------------------------------------------------------------------------
// Tests — init and header rendering
// ---------------------------------------------------------------------------

console.log('\n=== ActionRailPanel — init renders actor header ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new ActionRailPanel(container, bus);
  panel.init();

  // No actor yet → "No actor"
  assert(container._elements['actor-name']?.textContent === 'No actor', 'default actor name is "No actor"');
  assert(container._elements['status']?.textContent !== '', 'status text renders');
}

console.log('\n=== ActionRailPanel — combat:turn-changed sets actor ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new ActionRailPanel(container, bus);
  panel.init();

  const entity = makeEntity({ name: 'Rogue' });
  bus.emit('combat:turn-changed', { entity });

  assert(container._elements['actor-name']?.textContent === 'Rogue', 'actor-name updates from bus event');
  assert(container._elements['status']?.textContent.includes('2/3'), 'status shows action economy');
  assert(container._elements['status']?.textContent.includes('30'), 'status shows movement');
}

console.log('\n=== ActionRailPanel — entity:selected sets player actor ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new ActionRailPanel(container, bus);
  panel.init();

  const ally = makeEntity({ name: 'Paladin', team: 'player' });
  bus.emit('entity:selected', { entity: ally });

  assert(container._elements['actor-name']?.textContent === 'Paladin', 'actor set from entity:selected for player');
}

console.log('\n=== ActionRailPanel — entity:selected ignores enemy ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new ActionRailPanel(container, bus);
  panel.init();

  const hero = makeEntity({ name: 'Hero', team: 'player' });
  bus.emit('combat:turn-changed', { entity: hero });

  const goblin = makeEntity({ name: 'Goblin', team: 'enemy' });
  bus.emit('entity:selected', { entity: goblin });

  // Actor should NOT change to enemy
  assert(container._elements['actor-name']?.textContent === 'Hero', 'enemy selection does not change actor');
}

console.log('\n=== ActionRailPanel — room:entities-changed updates entity list ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new ActionRailPanel(container, bus);
  panel.init();

  const entities = [makeEntity({ name: 'Troll', team: 'enemy' })];
  bus.emit('room:entities-changed', { entities });

  assert(panel._roomEntities.length === 1, 'room entities stored from bus event');
}

console.log('\n=== ActionRailPanel — room:changed updates connections ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new ActionRailPanel(container, bus);
  panel.init();

  const connections = [{ roomId: '42', roomName: 'Vault', direction: 'north' }];
  bus.emit('room:changed', { connections });

  assert(panel._connections.length === 1, 'connections stored from bus event');
  assert(panel._connections[0].roomName === 'Vault', 'connection data preserved');
}

console.log('\n=== ActionRailPanel — navigate panel HTML ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new ActionRailPanel(container, bus);
  panel.init();

  panel._connections = [
    { roomId: '10', roomName: 'Armory', direction: 'east' },
    { roomId: '11', roomName: 'Chapel' },
  ];
  panel._activeCategory = 'navigate';
  panel._renderSubPanel();

  const html = container._elements['panel-body']?.innerHTML ?? '';
  assert(html.includes('Armory'),  'navigate panel includes room name');
  assert(html.includes('Chapel'),  'navigate panel includes second room');
  assert(html.includes('navigate'), 'navigate panel has execute buttons');
}

console.log('\n=== ActionRailPanel — attack panel HTML with targets ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new ActionRailPanel(container, bus);
  panel.init();

  const hero   = makeEntity({ name: 'Hero',   team: 'player', actionsRemaining: 2 });
  const goblin = makeEntity({ name: 'Goblin', team: 'enemy' });
  panel._actor       = hero;
  panel._roomEntities = [hero, goblin];
  panel._activeCategory = 'attack';
  panel._renderSubPanel();

  const html = container._elements['panel-body']?.innerHTML ?? '';
  assert(html.includes('Goblin'), 'attack panel shows hostile target name');
  assert(!html.includes('Hero'),  'attack panel excludes actor from targets');
}

console.log('\n=== ActionRailPanel — attack panel empty with no hostiles ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new ActionRailPanel(container, bus);
  panel.init();

  const hero = makeEntity({ name: 'Hero', team: 'player' });
  panel._actor = hero;
  panel._roomEntities = [hero];
  panel._activeCategory = 'attack';
  panel._renderSubPanel();

  const html = container._elements['panel-body']?.innerHTML ?? '';
  assert(html.toLowerCase().includes('no hostile'), 'attack panel shows empty state when no hostiles');
}

console.log('\n=== ActionRailPanel — skills panel renders 10 skills ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new ActionRailPanel(container, bus);
  panel._activeCategory = 'skills';
  panel.init();

  const html = container._elements['panel-body']?.innerHTML ?? '';
  const count = (html.match(/data-rail-execute="skill"/g) || []).length;
  assert(count === 10, `skills panel renders 10 skill entries (got ${count})`);
}

console.log('\n=== ActionRailPanel — user:attack emitted on execute ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();

  // Real container with actual click handling
  let capturedAttack = null;
  bus.on('user:attack', (data) => { capturedAttack = data; });

  const panel = new ActionRailPanel(container, bus);
  const hero   = makeEntity({ name: 'Hero',   team: 'player' });
  const goblin = makeEntity({ name: 'Goblin', team: 'enemy'  });
  panel._actor = hero;
  panel._roomEntities = [hero, goblin];

  // Simulate _handleExecute directly
  const fakeBtn = { dataset: { railExecute: 'attack', targetEntityId: goblin.id } };
  panel._handleExecute(fakeBtn);

  assert(capturedAttack !== null,              'user:attack was emitted');
  assert(capturedAttack.attacker === hero,     'attacker is actor entity');
  assert(capturedAttack.targetEntityId === goblin.id, 'correct target id');
}

console.log('\n=== ActionRailPanel — user:navigate-to-room emitted ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  let capturedNav = null;
  bus.on('user:navigate-to-room', (data) => { capturedNav = data; });

  const panel = new ActionRailPanel(container, bus);
  const fakeBtn = { dataset: { railExecute: 'navigate', roomId: '42', connectionId: 'c1' } };
  panel._handleExecute(fakeBtn);

  assert(capturedNav !== null,          'user:navigate-to-room emitted');
  assert(capturedNav.roomId === '42',   'correct roomId');
  assert(capturedNav.connectionId === 'c1', 'correct connectionId');
}

console.log('\n=== ActionRailPanel — destroy removes subscriptions ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new ActionRailPanel(container, bus);
  panel.init();
  panel.destroy();

  const entity = makeEntity({ name: 'Ghost' });
  bus.emit('combat:turn-changed', { entity });

  // actor should NOT have updated (destroyed panel ignored event)
  assert(panel._actor !== entity, 'destroyed panel ignores bus events');
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
