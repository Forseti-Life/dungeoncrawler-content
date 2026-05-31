/**
 * @file
 * Phase 10 — Integration smoke tests.
 *
 * Validates the event-bus contracts across key Phase 10 additions:
 *   - PlayerAutomation start/stop/turn-changed relay
 *   - QuestSystem seed-from-game:init, room:changed broadcast, entity:interacted relay
 *   - dom-utils helpers (pure functions)
 *   - quest-utils helpers (pure functions)
 *   - spell-utils helpers (pure functions)
 *
 * Does NOT test PIXI canvas or require a real browser.
 * Run with:
 *   node tests/integration_smoke_test.js
 */

'use strict';

const fs   = require('fs');
const path = require('path');

let passed = 0;
let failed = 0;

function assert(condition, msg) {
  if (condition) {
    passed++;
    console.log(`  ✓ ${msg}`);
  } else {
    failed++;
    console.error(`  ✗ ${msg}`);
  }
}

// ---------------------------------------------------------------------------
// Helpers — load ES module source as CJS (strip export keywords)
// ---------------------------------------------------------------------------

function loadModule(relPath) {
  const src = fs.readFileSync(path.resolve(__dirname, relPath), 'utf8');
  const stripped = src.replace(/^export\s+(?:default\s+)?/gm, '');
  // return an object of all top-level declarations
  // eslint-disable-next-line no-new-func
  return new Function(stripped + '\nreturn { PlayerAutomation, QuestSystem, GameEventBus, clearChildren, createElement, setVisible, scrollToBottom, debounce, esc, buildObjectiveTree, isQuestComplete, getQuestProgress, flattenObjectiveTree, getAvailableSpells, getSpellActionCost, getSpellRanks, canCast };')();
}

// We load each file separately so undefined names don't throw
function loadSrc(relPath, exportNames) {
  const src = fs.readFileSync(path.resolve(__dirname, relPath), 'utf8');
  const stripped = src.replace(/^export\s+(?:default\s+)?/gm, '');
  const ret = exportNames.map(n => `typeof ${n} !== 'undefined' ? ${n} : undefined`).join(', ');
  // eslint-disable-next-line no-new-func
  const result = new Function(stripped + `\nreturn [${ret}];`)();
  const obj = {};
  exportNames.forEach((n, i) => { obj[n] = result[i]; });
  return obj;
}

const { GameEventBus } = loadSrc('../js/v2/GameEventBus.js', ['GameEventBus']);

function makeBus() { return new GameEventBus(); }

// ============================================================================
// dom-utils
// ============================================================================

console.log('\n=== dom-utils ===');
{
  const {
    clearChildren, createElement, setVisible, scrollToBottom, debounce, esc,
  } = loadSrc('../js/v2/utils/dom-utils.js', [
    'clearChildren', 'createElement', 'setVisible', 'scrollToBottom', 'debounce', 'esc',
  ]);

  // Mock minimal DOM for Node.js
  function makeEl(tag = 'div') {
    return {
      tag, children: [], firstChild: null, hidden: false, scrollTop: 0, scrollHeight: 200,
      className: '', dataset: {},
      setAttribute(k, v) { this[k] = v; },
      removeChild(c) { this.children = this.children.filter(x => x !== c); this._sync(); },
      _sync() { this.firstChild = this.children[0] ?? null; },
    };
  }

  // Stub document.createElement
  global.document = {
    createElement(tag) {
      const el = makeEl(tag);
      el.textContent = '';
      return el;
    }
  };

  {
    const el = makeEl();
    el.children = [makeEl('span'), makeEl('span')];
    el._sync();
    clearChildren(el);
    assert(el.firstChild === null, 'clearChildren removes all children');
  }

  {
    const el = createElement('div', { className: 'foo' }, 'hello');
    assert(el.className === 'foo', 'createElement sets className');
    assert(el.textContent === 'hello', 'createElement sets textContent');
  }

  {
    const el = makeEl();
    setVisible(el, false);
    assert(el.hidden === true, 'setVisible(el, false) sets hidden=true');
    setVisible(el, true);
    assert(el.hidden === false, 'setVisible(el, true) sets hidden=false');
  }

  {
    const el = makeEl();
    el.scrollHeight = 500;
    scrollToBottom(el);
    assert(el.scrollTop === 500, 'scrollToBottom sets scrollTop to scrollHeight');
  }

  {
    assert(esc('<script>') === '&lt;script&gt;', 'esc escapes < and >');
    assert(esc('"hello"') === '&quot;hello&quot;', 'esc escapes double quotes');
    assert(esc('a & b') === 'a &amp; b', 'esc escapes ampersand');
    assert(esc(null) === '', 'esc handles null → empty string');
  }

  {
    let count = 0;
    const fn = debounce(() => count++, 50);
    fn(); fn(); fn();
    // synchronous calls should not have fired yet
    assert(count === 0, 'debounce suppresses rapid calls synchronously');
  }
}

// ============================================================================
// quest-utils
// ============================================================================

console.log('\n=== quest-utils ===');
{
  const {
    buildObjectiveTree, isQuestComplete, getQuestProgress, flattenObjectiveTree,
  } = loadSrc('../js/v2/utils/quest-utils.js', [
    'buildObjectiveTree', 'isQuestComplete', 'getQuestProgress', 'flattenObjectiveTree',
  ]);

  const flat = [
    { objective_id: 'o1', title: 'Root', status: 'complete' },
    { objective_id: 'o2', title: 'Child', parent_id: 'o1', status: 'complete' },
    { objective_id: 'o3', title: 'Root2', status: 'incomplete' },
  ];

  {
    const tree = buildObjectiveTree(flat);
    assert(tree.length === 2, 'buildObjectiveTree: 2 top-level roots');
    assert(tree[0].children.length === 1, 'buildObjectiveTree: child nested under parent');
  }

  {
    const quest = { objectives: flat };
    assert(!isQuestComplete(quest), 'isQuestComplete → false when one objective incomplete');
    const completeQuest = { objectives: flat.map(o => ({ ...o, status: 'complete' })) };
    assert(isQuestComplete(completeQuest), 'isQuestComplete → true when all complete');
  }

  {
    const progress = getQuestProgress({ objectives: flat });
    assert(progress.total === 3, 'getQuestProgress total = 3');
    assert(progress.completed === 2, 'getQuestProgress completed = 2');
    assert(progress.percent === 67, 'getQuestProgress percent ≈ 67');
  }

  {
    const tree  = buildObjectiveTree(flat);
    const nodes = flattenObjectiveTree(tree);
    assert(nodes.length === 3, 'flattenObjectiveTree returns all nodes');
  }
}

// ============================================================================
// spell-utils
// ============================================================================

console.log('\n=== spell-utils ===');
{
  const {
    getSpellActionCost, canCast, getAvailableSpells, getSpellRanks,
  } = loadSrc('../js/v2/utils/spell-utils.js', [
    'getSpellActionCost', 'canCast', 'getAvailableSpells', 'getSpellRanks',
  ]);

  {
    assert(getSpellActionCost({ action_cost: 1 }) === 1, 'getSpellActionCost 1');
    assert(getSpellActionCost({ action_cost: 3 }) === 3, 'getSpellActionCost 3');
    assert(getSpellActionCost({}) === 2, 'getSpellActionCost default 2');
  }

  const char = { spell_slots: { 1: 2, 2: 1 }, spells: [
    { spell_id: 's1', name: 'Magic Missile', action_cost: 1, rank: 1 },
    { spell_id: 's2', name: 'Fireball',      action_cost: 2, rank: 3 },
  ]};

  {
    const r = canCast(char, { rank: 1 }, 1);
    assert(r.canCast === true, 'canCast rank 1 with slots available');
    const r2 = canCast(char, { rank: 1 }, 3);
    assert(r2.canCast === false, 'canCast rank 3 with no slots');
  }

  {
    const spells = getAvailableSpells(char);
    // Magic Missile rank 1 → slot available; Fireball rank 3 → no slot
    assert(spells.length === 1, 'getAvailableSpells returns spells with available slots');
    assert(spells[0].spell_id === 's1', 'getAvailableSpells returns Magic Missile');
  }

  {
    const ranks = getSpellRanks(char, { rank: 1 });
    assert(ranks.includes(1) && ranks.includes(2), 'getSpellRanks returns ranks with slots >= base');
  }
}

// ============================================================================
// PlayerAutomation
// ============================================================================

console.log('\n=== PlayerAutomation ===');
{
  const { PlayerAutomation } = loadSrc('../js/v2/systems/PlayerAutomation.js', ['PlayerAutomation']);

  let timerFired = false;
  global.setTimeout  = (fn, ms) => { timerFired = true; return 42; };
  global.clearTimeout = () => {};

  {
    const bus    = makeBus();
    const shell  = {};
    const pa     = new PlayerAutomation(shell, bus);
    pa.init();

    const events = [];
    bus.on('automation:started', (d) => events.push({ e: 'started', ...d }));
    bus.on('automation:stopped', (d) => events.push({ e: 'stopped', ...d }));

    bus.emit('user:automation-start');
    assert(events.some(e => e.e === 'started'), 'user:automation-start fires automation:started');

    bus.emit('user:automation-stop');
    assert(events.some(e => e.e === 'stopped'), 'user:automation-stop fires automation:stopped');
  }

  {
    const bus    = makeBus();
    const pa     = new PlayerAutomation({}, bus);
    pa.init();

    const queued = [];
    bus.on('automation:step-queued', (d) => queued.push(d));

    // Simulate automated entity
    const entity = {
      id: 'e1',
      getComponent(name) {
        if (name === 'CombatComponent')  return { isPlayerTeam: () => true };
        if (name === 'IdentityComponent') return { entityId: 'e1' };
        return null;
      },
    };

    pa.start();
    timerFired = false;
    bus.emit('combat:turn-changed', { entity });
    assert(timerFired, 'automation step timer is set on player-team turn');
    assert(queued.length === 1 && queued[0].entityId === 'e1', 'automation:step-queued emitted with entityId');
  }

  {
    const bus = makeBus();
    const pa  = new PlayerAutomation({}, bus);
    pa.init();
    // Not started — turn-changed should NOT queue
    const queued = [];
    bus.on('automation:step-queued', (d) => queued.push(d));
    const entity = { id: 'e2', getComponent(name) {
      if (name === 'CombatComponent') return { isPlayerTeam: () => true };
      return null;
    }};
    timerFired = false;
    bus.emit('combat:turn-changed', { entity });
    assert(!timerFired && queued.length === 0, 'automation inactive → no step queued on turn-changed');
  }

  {
    const bus = makeBus();
    const pa  = new PlayerAutomation({}, bus);
    pa.init();
    pa.start();

    // NPC entity → should NOT queue
    const queued = [];
    bus.on('automation:step-queued', (d) => queued.push(d));
    const npc = { id: 'npc1', getComponent(name) {
      if (name === 'CombatComponent') return { isPlayerTeam: () => false };
      return null;
    }};
    timerFired = false;
    bus.emit('combat:turn-changed', { entity: npc });
    assert(!timerFired, 'NPC turn does not trigger automation step');
  }
}

// ============================================================================
// QuestSystem
// ============================================================================

console.log('\n=== QuestSystem ===');
{
  const { QuestSystem } = loadSrc('../js/v2/systems/QuestSystem.js', ['QuestSystem']);

  {
    const bus    = makeBus();
    const shell  = {};
    const qs     = new QuestSystem(shell, bus);
    qs.init();

    const initQuests = [];
    bus.on('game:init-quests', ({ quests }) => initQuests.push(...quests));

    bus.emit('game:init', { dungeonData: { quests: [
      { quest_id: 'q1', title: 'Find the gem', objectives: [] },
      { quest_id: 'q2', title: 'Slay the dragon', objectives: [] },
    ]}});

    assert(initQuests.length === 2, 'game:init seeds QuestSystem with 2 quests');
  }

  {
    const bus  = makeBus();
    const qs   = new QuestSystem({}, bus);
    qs.init();

    const updates = [];
    bus.on('quest:progress-updated', (d) => updates.push(d));

    bus.emit('game:init', { dungeonData: { quests: [{ quest_id: 'q1', title: 'T', objectives: [] }] } });
    updates.length = 0;

    bus.emit('room:changed', {});
    assert(updates.length === 1, 'room:changed re-broadcasts active quests');
  }

  {
    const bus  = makeBus();
    const qs   = new QuestSystem({}, bus);
    qs.init();

    const collected = [];
    bus.on('quest:item-collected', (d) => collected.push(d));

    const collectible = {
      id: 'gem1',
      getComponent(name) {
        if (name === 'IdentityComponent') return { tags: ['quest-collectible'], displayName: 'Ruby Gem' };
        return null;
      },
    };
    bus.emit('entity:interacted', { entity: collectible });
    assert(collected.length === 1 && collected[0].itemName === 'Ruby Gem', 'quest-collectible interaction fires quest:item-collected');
  }

  {
    const bus  = makeBus();
    const qs   = new QuestSystem({}, bus);
    qs.init();

    const collected = [];
    bus.on('quest:item-collected', (d) => collected.push(d));

    const nonCollectible = {
      id: 'npc1',
      getComponent(name) {
        if (name === 'IdentityComponent') return { tags: ['npc'], displayName: 'Goblin' };
        return null;
      },
    };
    bus.emit('entity:interacted', { entity: nonCollectible });
    assert(collected.length === 0, 'non-collectible interaction does not fire quest:item-collected');
  }

  {
    // updateQuest() emits completed on first transition to 'completed'
    const bus  = makeBus();
    const qs   = new QuestSystem({}, bus);
    qs.init();

    bus.emit('game:init', { dungeonData: { quests: [{ quest_id: 'q1', title: 'T', status: 'active', objectives: [] }] } });

    const completed = [];
    const updated   = [];
    bus.on('quest:completed',        (d) => completed.push(d));
    bus.on('quest:progress-updated', (d) => updated.push(d));

    qs.updateQuest({ quest_id: 'q1', title: 'T', status: 'completed', objectives: [] });
    assert(completed.length === 1, 'updateQuest fires quest:completed on status → completed');

    completed.length = 0;
    qs.updateQuest({ quest_id: 'q1', title: 'T', status: 'completed', objectives: [] });
    assert(completed.length === 0, 'updateQuest does not re-fire quest:completed on duplicate');
  }
}

// ============================================================================
// Summary
// ============================================================================

console.log(`\n${'─'.repeat(50)}`);
console.log(`Results: ${passed} passed, ${failed} failed`);
process.exit(failed > 0 ? 1 : 0);
