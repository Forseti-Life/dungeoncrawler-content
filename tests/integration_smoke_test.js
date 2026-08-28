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

// Resolve each module's relative import graph so shared helpers stay defined;
// stripping imports outright silently removed real dependencies.
const { loadModuleScope } = require('./helpers/js-module.js');

function loadSrc(relPath, exportNames) {
  return loadModuleScope(relPath, exportNames);
}

const { GameEventBus } = loadSrc('../js/v2/GameEventBus.js', ['GameEventBus']);

function makeBus() { return new GameEventBus(); }

// ============================================================================
// dom-utils
// ============================================================================

console.log('\n=== dom-utils ===');
{
  // dom-utils was narrowed to tooltip helpers in the v2 migration; the former
  // generic helpers (clearChildren/createElement/setVisible/scrollToBottom/
  // debounce/esc) were removed as dead code and have no remaining callers.
  const {
    escapeTooltipAttr, slugifyTooltipKey, uniqueTooltipStrings,
    flattenTooltipBuckets, tooltipSourceMatches, formatTooltipActionCost,
  } = loadSrc('../js/v2/utils/dom-utils.js', [
    'escapeTooltipAttr', 'slugifyTooltipKey', 'uniqueTooltipStrings',
    'flattenTooltipBuckets', 'tooltipSourceMatches', 'formatTooltipActionCost',
  ]);

  {
    const escaped = escapeTooltipAttr('<b>"x"</b>');
    assert(!escaped.includes('<b>'), 'escapeTooltipAttr escapes angle brackets');
    assert(!escaped.includes('"'), 'escapeTooltipAttr escapes double quotes');
  }

  {
    assert(slugifyTooltipKey('Iron Sword!') === 'iron-sword', 'slugifyTooltipKey slugifies');
    assert(slugifyTooltipKey('  --Mixed CASE--  ') === 'mixed-case', 'slugifyTooltipKey trims separators');
    assert(slugifyTooltipKey(null) === '', 'slugifyTooltipKey handles null');
  }

  {
    const out = uniqueTooltipStrings(['a', ' a ', 'b', '', null]);
    assert(out.length === 2, 'uniqueTooltipStrings dedupes and drops empties');
    assert(out[0] === 'a' && out[1] === 'b', 'uniqueTooltipStrings trims values');
    assert(uniqueTooltipStrings('nope').length === 0, 'uniqueTooltipStrings handles non-arrays');
  }

  {
    assert(flattenTooltipBuckets([1, 2]).length === 2, 'flattenTooltipBuckets passes arrays through');
    assert(flattenTooltipBuckets({ a: [1], b: [2, 3] }).length === 3, 'flattenTooltipBuckets flattens buckets');
    assert(flattenTooltipBuckets(null).length === 0, 'flattenTooltipBuckets handles null');
  }

  {
    assert(tooltipSourceMatches('feat-1', 'feat') === true, 'tooltipSourceMatches matches prefixed ids');
    assert(tooltipSourceMatches('feat', 'feat') === true, 'tooltipSourceMatches matches exact ids');
    assert(tooltipSourceMatches('feature-1', 'feat') === false, 'tooltipSourceMatches requires a separator');
    assert(tooltipSourceMatches(null, 'feat') === false, 'tooltipSourceMatches handles null');
  }

  {
    assert(formatTooltipActionCost(1) === '1 action', 'formatTooltipActionCost singular');
    assert(formatTooltipActionCost(2) === '2 actions', 'formatTooltipActionCost plural');
    assert(formatTooltipActionCost('free_action') === 'free action', 'formatTooltipActionCost humanises strings');
    assert(formatTooltipActionCost(null) === '', 'formatTooltipActionCost handles null');
  }
}


// ============================================================================
// quest-utils
// ============================================================================

console.log('\n=== quest-utils ===');
{
  // The v2 migration replaced the old objective-tree helpers
  // (buildObjectiveTree/isQuestComplete/getQuestProgress/flattenObjectiveTree)
  // with normalization + flattening helpers; these cover the current exports.
  const {
    resolveQuestTitle, escapeQuestHtml, extractQuestPhases, flattenQuestObjectives,
  } = loadSrc('../js/v2/utils/quest-utils.js', [
    'resolveQuestTitle', 'escapeQuestHtml', 'extractQuestPhases', 'flattenQuestObjectives',
  ]);

  {
    assert(resolveQuestTitle({ title: 'Rescue' }) === 'Rescue', 'resolveQuestTitle prefers title');
    assert(resolveQuestTitle({ quest_name: 'Fallback' }) === 'Fallback', 'resolveQuestTitle falls back to quest_name');
    assert(resolveQuestTitle(null) === 'Unknown Quest', 'resolveQuestTitle handles null');
  }

  {
    const escaped = escapeQuestHtml('<img src=x onerror=1>');
    assert(!escaped.includes('<img'), 'escapeQuestHtml escapes markup');
  }

  {
    assert(extractQuestPhases({ generated_objectives: [{ a: 1 }] }).length === 1, 'extractQuestPhases prefers generated_objectives');
    assert(extractQuestPhases({ objective_states: [{ objectives: [] }] }).length === 1, 'extractQuestPhases falls back to objective_states');
    assert(extractQuestPhases(null).length === 0, 'extractQuestPhases handles null');
  }

  {
    const objectives = [
      { objective_id: 'o1', completed: false },
      { objective_id: 'o2', completed: true },
      { objective_id: 'o3', children: [{ objective_id: 'o4', completed: false }] },
    ];
    const open = flattenQuestObjectives(objectives);
    assert(open.length === 2, 'flattenQuestObjectives omits completed leaves by default');
    assert(open.some(o => o.objective_id === 'o4'), 'flattenQuestObjectives descends into children');

    const all = flattenQuestObjectives(objectives, { includeCompleted: true });
    assert(all.length === 3, 'flattenQuestObjectives can include completed leaves');
    assert(flattenQuestObjectives(null).length === 0, 'flattenQuestObjectives handles null');
  }
}

// ============================================================================
// spell-utils
// ============================================================================

console.log('\n=== spell-utils ===');
{
  // getAvailableSpells/getSpellActionCost/getSpellRanks/canCast were removed in
  // the v2 migration; rank formatting is the surviving public surface.
  const {
    getSpellRankNumber, formatOrdinalRank, formatSpellRankLabel,
  } = loadSrc('../js/v2/utils/spell-utils.js', [
    'getSpellRankNumber', 'formatOrdinalRank', 'formatSpellRankLabel',
  ]);

  {
    assert(getSpellRankNumber('cantrip') === 0, 'getSpellRankNumber maps cantrip to 0');
    assert(getSpellRankNumber('third_level') === 3, 'getSpellRankNumber maps named ranks');
    assert(getSpellRankNumber('First') === 1, 'getSpellRankNumber is case-insensitive');
  }

  {
    assert(formatOrdinalRank(1) === '1st', 'formatOrdinalRank 1st');
    assert(formatOrdinalRank(2) === '2nd', 'formatOrdinalRank 2nd');
    assert(formatOrdinalRank(3) === '3rd', 'formatOrdinalRank 3rd');
    assert(formatOrdinalRank(4) === '4th', 'formatOrdinalRank 4th');
    assert(formatOrdinalRank(11) === '11th', 'formatOrdinalRank handles the teens');
  }

  {
    assert(formatSpellRankLabel(0) === 'Cantrips', 'formatSpellRankLabel labels cantrips');
    assert(formatSpellRankLabel(3) === '3rd', 'formatSpellRankLabel short form');
    assert(formatSpellRankLabel(3, { longForm: true }) === '3rd Level', 'formatSpellRankLabel long form');
    assert(formatSpellRankLabel('second') === '2nd', 'formatSpellRankLabel accepts rank keys');
  }
}


console.log('\n=== PlayerAutomation ===');
{
  const { PlayerAutomation } = loadSrc('../js/v2/systems/PlayerAutomation.js', ['PlayerAutomation']);
  const { installDom } = require('./helpers/fake-dom.js');

  // The ECS start/stop turn-stepping API (start/stop, automation:started,
  // automation:step-queued, combat:turn-changed) was removed in the v2
  // migration; PlayerAutomation now owns footer section state and the
  // deferred room-chat queue.

  {
    const dom = installDom();
    global.window = { matchMedia: () => ({ matches: true }) };

    const footer = dom.document.createElement('div');
    const section = dom.document.createElement('div');
    const chevron = dom.document.createElement('span');
    chevron.classList.add('action-section__chevron');
    chevron.textContent = '▾';
    section.appendChild(chevron);

    const pa = new PlayerAutomation({}, makeBus());
    pa.applyInitialSectionState(footer, [section]);

    assert(section.classList.contains('collapsed'), 'mobile viewport collapses action sections');
    assert(chevron.textContent === '▸', 'collapsed section shows the collapsed chevron');
    assert(footer.dataset.initialStateApplied === 'true', 'footer is marked so initial state applies once');

    // Second call must be a no-op even if the section was re-expanded.
    section.classList.remove('collapsed');
    pa.applyInitialSectionState(footer, [section]);
    assert(!section.classList.contains('collapsed'), 'initial section state is applied only once');
  }

  {
    global.window = { matchMedia: () => ({ matches: false }) };
    const dom = installDom();
    const footer = dom.document.createElement('div');
    const section = dom.document.createElement('div');

    const pa = new PlayerAutomation({}, makeBus());
    pa.applyInitialSectionState(footer, [section]);
    assert(!section.classList.contains('collapsed'), 'desktop viewport leaves action sections expanded');
  }

  {
    const bus = makeBus();
    const messages = [];
    bus.on('chat:system-message', (d) => messages.push(d));

    const pa = new PlayerAutomation({ panels: {} }, bus);
    pa._appendChatLine('System', 'queued turn failed', 'system');

    assert(messages.length === 1, '_appendChatLine emits a chat:system-message');
    assert(messages[0].text === 'queued turn failed', 'chat:system-message carries the message text');
    assert(messages[0].speaker === 'System', 'chat:system-message carries the speaker');
    assert(messages[0].authority === 'authoritative', 'automation chat lines are authoritative');
    assert(messages[0].source === 'player-automation', 'chat:system-message is attributed to player-automation');
  }

  {
    // Deferred queue: nothing to flush must not touch the chat panel.
    const statusUpdates = [];
    const shell = { panels: { chat: { updateQueuedChatStatus: (n) => statusUpdates.push(n) } } };
    const pa = new PlayerAutomation(shell, makeBus());
    pa.roomChatBusy = false;
    pa.roomChatDeferredMessages = [];
    void pa.flushDeferredRoomMessages(1, 'room-1');
    assert(statusUpdates.length === 0, 'empty deferred queue does not post a status update');

    // A busy pipeline must not drain the queue concurrently.
    pa.roomChatBusy = true;
    pa.roomChatDeferredMessages = [{ message: 'hi' }];
    void pa.flushDeferredRoomMessages(1, 'room-1');
    assert(pa.roomChatDeferredMessages.length === 1, 'busy pipeline leaves the deferred queue intact');
    assert(statusUpdates.length === 0, 'busy pipeline does not post a status update');
  }

  {
    // destroy() must release every bus subscription it took out.
    const pa = new PlayerAutomation({}, makeBus());
    let released = 0;
    pa._unsubs = [() => { released += 1; }, () => { released += 1; }];
    pa.destroy();
    assert(released === 2, 'destroy() releases all subscriptions');
    assert(pa._unsubs.length === 0, 'destroy() clears the subscription list');
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
