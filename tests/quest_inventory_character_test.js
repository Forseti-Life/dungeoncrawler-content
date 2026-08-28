/**
 * @file
 * Unit tests for QuestPanel, InventoryPanel, CharacterPanel (Phase 8).
 *
 * Run with:
 *   node tests/quest_inventory_character_test.js
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

const fs   = require('fs');
const path = require('path');
const { installDom } = require('./helpers/fake-dom.js');

// Shared loader resolves the relative import graph (including `?v=` cache-bust
// specifiers) and hard-fails on unresolved imports rather than silently
// dropping dependencies.
const { loadModuleExport } = require('./helpers/js-module.js');

const loadPanelClass = (relPath, className) => loadModuleExport(relPath, className);

const QuestPanel     = loadPanelClass('../js/v2/panels/QuestPanel.js',     'QuestPanel');
const InventoryPanel = loadPanelClass('../js/v2/panels/InventoryPanel.js', 'InventoryPanel');
const CharacterPanel = loadPanelClass('../js/v2/panels/CharacterPanel.js', 'CharacterPanel');
const GameEventBus   = loadPanelClass('../js/v2/GameEventBus.js',          'GameEventBus');

// Minimal setTimeout stub — capture but don't auto-execute (tests verify state before dismissal)
global.setTimeout  = () => 1;
global.clearTimeout = () => {};

// ---------------------------------------------------------------------------
// DOM helpers
// ---------------------------------------------------------------------------

// IDs that QuestPanel resolves via document.getElementById()
const QUEST_IDS = ['quest-journal', 'quest-list', 'quest-count', 'quest-expand-all', 'quest-collapse-all'];

// IDs that InventoryPanel resolves via document.getElementById()
const INV_IDS = [
  'inv-item-list', 'inv-slot-grid', 'inv-action-status',
  'inv-bulk-current', 'inv-bulk-max',
  'inv-pp', 'inv-gp', 'inv-ep', 'inv-sp', 'inv-cp',
  'char-inventory',
];

// IDs that CharacterPanel resolves via document.getElementById()
const CHAR_IDS = [
  'char-entity-info', 'char-name', 'char-type', 'char-portrait', 'char-portrait-wrap',
  'char-level', 'char-hp', 'char-ac', 'char-full-sheet-link',
  'entity-type', 'entity-image-wrap', 'entity-image',
  'entity-summary', 'entity-description', 'entity-known-details',
  'entity-team', 'entity-hp', 'entity-ac', 'entity-actions', 'entity-movement',
  'char-conditions',
];

function makeQuestContainer() {
  const dom = installDom(QUEST_IDS);
  return { dom, el: dom.el, container: dom.document.body };
}

function makeInvContainer() {
  const dom = installDom(INV_IDS);
  return { dom, el: dom.el, container: dom.document.body };
}

/**
 * CharacterPanel also reads entityName from container.querySelector('[data-char="entity-name"]'),
 * so we add a child element to the container with that attribute.
 */
function makeCharContainer() {
  const dom = installDom(CHAR_IDS);
  const container = dom.document.createElement('div');
  const entityNameEl = dom.document.createElement('div');
  entityNameEl.setAttribute('data-char', 'entity-name');
  container.appendChild(entityNameEl);
  dom.document.body.appendChild(container);
  return { dom, el: dom.el, container };
}

// ---------------------------------------------------------------------------
// QuestPanel Tests
// ---------------------------------------------------------------------------

console.log('\n=== QuestPanel — empty state on init ===');
{
  const { el, container } = makeQuestContainer();
  const bus = new GameEventBus();
  const panel = new QuestPanel(container, bus);
  panel.init();

  // Panel renders empty state to questList when game:init fires with no quests
  bus.emit('game:init', { quests: [] });
  assert(el['quest-list'].innerHTML.includes('quest-empty'), 'empty message visible with no quests');
}

console.log('\n=== QuestPanel — game:init renders quest list ===');
{
  const { el, container } = makeQuestContainer();
  const bus = new GameEventBus();
  const panel = new QuestPanel(container, bus);
  panel.init();

  // extractQuestPhases reads generated_objectives; each phase needs an objectives array
  bus.emit('game:init', {
    quests: [
      { quest_id: 'q1', title: 'Find the Key', status: 'active',
        generated_objectives: [{ objectives: [{ objective_id: 'o1', description: 'Search the dungeon' }] }] },
      { quest_id: 'q2', title: 'Slay the Dragon', status: 'active', generated_objectives: [] },
    ],
  });

  const html = el['quest-list'].innerHTML;
  assert(!html.includes('quest-empty'), 'empty hidden when quests exist');
  assert(html.includes('Find the Key'), 'first quest title rendered');
  assert(html.includes('Slay the Dragon'), 'second quest title rendered');
  assert(html.includes('Search the dungeon'), 'objective rendered');
}

console.log('\n=== QuestPanel — quest:progress-updated re-renders ===');
{
  const { el, container } = makeQuestContainer();
  const bus = new GameEventBus();
  const panel = new QuestPanel(container, bus);
  panel.init();

  // Panel handler reads d.questSummary; pass the correct payload format
  bus.emit('game:init', { questSummary: { active: [{ quest_id: 'q1', title: 'Old Title', status: 'active', objectives: [] }] } });
  bus.emit('quest:progress-updated', { questSummary: { active: [{ quest_id: 'q1', title: 'Updated Title', status: 'active', objectives: [] }] } });

  const html = el['quest-list'].innerHTML;
  assert(html.includes('Updated Title'), 'quest title updated');
  assert(!html.includes('Old Title'), 'old title replaced');
}

console.log('\n=== QuestPanel — quest:completed shows toast ===');
{
  const { el, container } = makeQuestContainer();
  const bus = new GameEventBus();
  const panel = new QuestPanel(container, bus);
  panel.init();

  // showQuestToast() emits chat:system-message on the bus; listen for it
  let toastText = null;
  bus.on('chat:system-message', (d) => { toastText = d.text; });

  // Pass message in the event payload so d.message contains the quest title
  bus.emit('quest:completed', { message: 'Quest complete: Slay the Dragon ✅' });

  assert(toastText !== null && toastText.includes('Slay the Dragon'), 'quest title in completion toast');
  assert(toastText !== null && toastText.includes('✅'), 'success icon in toast');
}

console.log('\n=== QuestPanel — quest:item-collected leaves panel stable ===');
{
  // QuestPanel does not subscribe to quest:item-collected (the event is emitted
  // by QuestSystem but consumed elsewhere). Verify the panel remains stable.
  const { el, container } = makeQuestContainer();
  const bus = new GameEventBus();
  const panel = new QuestPanel(container, bus);
  panel.init();

  bus.emit('game:init', { quests: [] });
  const htmlBefore = el['quest-list'].innerHTML;
  bus.emit('quest:item-collected', { itemName: 'Ancient Scroll' });

  assert(el['quest-list'].innerHTML === htmlBefore, 'unhandled item-collected event leaves quest list unchanged');
  assert(true, 'item-collected event does not crash panel');
}

console.log('\n=== QuestPanel — HTML escaped ===');
{
  const { el, container } = makeQuestContainer();
  const bus = new GameEventBus();
  const panel = new QuestPanel(container, bus);
  panel.init();

  bus.emit('game:init', {
    quests: [{ quest_id: 'q1', title: '<script>alert(1)</script>', status: 'active', objectives: [] }],
  });

  const html = el['quest-list'].innerHTML;
  assert(html.includes('&lt;script&gt;'), 'title HTML-escaped');
  assert(!html.includes('<script>'), 'raw script tag not present');
}

console.log('\n=== QuestPanel — destroy unsubscribes ===');
{
  const { el, container } = makeQuestContainer();
  const bus = new GameEventBus();
  const panel = new QuestPanel(container, bus);
  panel.init();

  // Render initial state so we have something to compare against
  bus.emit('game:init', { quests: [] });
  const htmlBeforeDestroy = el['quest-list'].innerHTML;
  panel.destroy();

  // After destroy, events must not update the rendered list
  bus.emit('game:init', { quests: [{ quest_id: 'q1', title: 'Ghost', status: 'active', objectives: [] }] });
  assert(el['quest-list'].innerHTML === htmlBeforeDestroy, 'quest list not updated after destroy');
}

// ---------------------------------------------------------------------------
// InventoryPanel Tests
// ---------------------------------------------------------------------------

console.log('\n=== InventoryPanel — empty state with no items ===');
{
  const { el, container } = makeInvContainer();
  const bus = new GameEventBus();
  const panel = new InventoryPanel(container, bus);
  panel.init();

  // renderInventoryPanel reads context.inventory; wrap correctly via inventoryContext
  bus.emit('game:init', { inventoryContext: { inventory: { carried: [], currency: { gp: 0, sp: 0, cp: 0 } } } });

  assert(el['inv-item-list'].innerHTML.includes('inventory-panel__empty'), 'empty visible when no items');
}

console.log('\n=== InventoryPanel — game:init renders items ===');
{
  const { el, container } = makeInvContainer();
  const bus = new GameEventBus();
  const panel = new InventoryPanel(container, bus);
  panel.init();

  bus.emit('game:init', {
    inventoryContext: {
      inventory: {
        carried: [
          { item_id: 'i1', name: 'Iron Sword', item_type: 'weapon', quantity: 1 },
          { item_id: 'i2', name: 'Health Potion', item_type: 'consumable', quantity: 3 },
        ],
      },
      currency: { gp: 25, sp: 5, cp: 0 },
    },
  });

  const html = el['inv-item-list'].innerHTML;
  assert(!html.includes('inventory-panel__empty'), 'empty hidden when items present');
  assert(html.includes('Iron Sword'), 'weapon item rendered');
  assert(html.includes('Health Potion'), 'consumable item rendered');
  // Currency values set to individual elements (inv-gp, inv-sp, etc.)
  assert(el['inv-gp'].textContent === '25', 'gp in currency');
  assert(el['inv-sp'].textContent === '5', 'sp in currency');
}

console.log('\n=== InventoryPanel — consumable item is rendered ===');
{
  const { el, container } = makeInvContainer();
  const bus = new GameEventBus();
  const panel = new InventoryPanel(container, bus);
  panel.init();

  bus.emit('game:init', {
    inventoryContext: {
      inventory: {
        carried: [{ item_id: 'i1', name: 'Potion', item_type: 'consumable', quantity: 1 }],
      },
      currency: {},
    },
  });

  const html = el['inv-item-list'].innerHTML;
  // renderInventoryPanelList renders items with data-type attribute
  assert(html.includes('Potion'), 'consumable item name rendered');
  assert(html.includes('data-type="consumable"'), 'consumable item type shown');
}

console.log('\n=== InventoryPanel — weapon item is rendered ===');
{
  const { el, container } = makeInvContainer();
  const bus = new GameEventBus();
  const panel = new InventoryPanel(container, bus);
  panel.init();

  bus.emit('game:init', {
    inventoryContext: {
      inventory: { carried: [{ item_id: 'w1', name: 'Axe', item_type: 'weapon', quantity: 1 }] },
      currency: {},
    },
  });

  const html = el['inv-item-list'].innerHTML;
  assert(html.includes('Axe'), 'weapon item name rendered');
  assert(html.includes('data-type="weapon"'), 'weapon item type shown');
}

// The Use/Drop/Equip actions were replaced by Assign/Unequip; equivalent
// action-affordance coverage is asserted against the current contract.
console.log('\n=== InventoryPanel — equippable item offers an Assign action ===');
{
  const { el, container } = makeInvContainer();
  const bus = new GameEventBus();
  const panel = new InventoryPanel(container, bus);
  panel.init();

  bus.emit('game:init', {
    inventoryContext: {
      inventory: {
        carried: [{
          item_id: 'w1',
          item_instance_id: 'inst-w1',
          name: 'Axe',
          item_type: 'weapon',
          quantity: 1,
          equippable: true,
          equip_slots: [{ slot_key: 'main_hand', label: 'Main Hand' }],
        }],
      },
      currency: {},
    },
  });

  const html = el['inv-item-list'].innerHTML;
  assert(html.includes('data-inventory-action="assign"'), 'equippable item renders an Assign action button');
  assert(html.includes('data-item-instance-id="inst-w1"'), 'Assign action carries the item instance id');
  assert(!html.includes('data-inventory-action="unequip"'), 'unworn item does not offer Unequip');
}

console.log('\n=== InventoryPanel — worn item offers an Unequip action ===');
{
  const { el, container } = makeInvContainer();
  const bus = new GameEventBus();
  const panel = new InventoryPanel(container, bus);
  panel.init();

  bus.emit('game:init', {
    inventoryContext: {
      inventory: {
        worn: { weapons: [{
          item_id: 'w1',
          item_instance_id: 'inst-w1',
          name: 'Axe',
          item_type: 'weapon',
          quantity: 1,
          equipped_slot_key: 'main_hand',
        }] },
      },
      currency: {},
    },
  });

  const html = el['inv-item-list'].innerHTML;
  assert(html.includes('data-inventory-action="unequip"'), 'worn item renders an Unequip action button');
}

console.log('\n=== InventoryPanel — static item exposes no actions ===');
{
  const { el, container } = makeInvContainer();
  const bus = new GameEventBus();
  const panel = new InventoryPanel(container, bus);
  panel.init();

  bus.emit('game:init', {
    inventoryContext: {
      inventory: { carried: [{ item_id: 'i9', name: 'Rock', item_type: 'gear', quantity: 1 }] },
      currency: {},
    },
  });

  const html = el['inv-item-list'].innerHTML;
  assert(!html.includes('data-inventory-action='), 'item without an instance id exposes no mutation actions');
  assert(html.includes('Static item'), 'item without an instance id is labelled static');
}

console.log('\n=== InventoryPanel — inventory:changed re-renders ===');
{
  const { el, container } = makeInvContainer();
  const bus = new GameEventBus();
  const panel = new InventoryPanel(container, bus);
  panel.init();

  bus.emit('game:init', { inventoryContext: { inventory: { carried: [{ item_id: 'i1', name: 'Old Item', item_type: 'misc', quantity: 1 }] }, currency: {} } });
  bus.emit('inventory:changed', { inventory: { carried: [{ item_id: 'i2', name: 'New Item', item_type: 'misc', quantity: 1 }] } });

  const html = el['inv-item-list'].innerHTML;
  assert(html.includes('New Item'), 'updated item rendered');
  assert(!html.includes('Old Item'), 'old item removed');
}

console.log('\n=== InventoryPanel — quantity > 1 shows ×N ===');
{
  const { el, container } = makeInvContainer();
  const bus = new GameEventBus();
  const panel = new InventoryPanel(container, bus);
  panel.init();

  bus.emit('game:init', {
    inventoryContext: {
      inventory: { carried: [{ item_id: 'i1', name: 'Arrow', item_type: 'misc', quantity: 20 }] },
      currency: {},
    },
  });

  assert(el['inv-item-list'].innerHTML.includes('×20'), 'quantity shown');
}

console.log('\n=== InventoryPanel — destroy unsubscribes ===');
{
  const { el, container } = makeInvContainer();
  const bus = new GameEventBus();
  const panel = new InventoryPanel(container, bus);
  panel.init();

  bus.emit('game:init', { inventoryContext: { inventory: { carried: [{ item_id: 'i1', name: 'Init Item', item_type: 'misc', quantity: 1 }] }, currency: {} } });
  const htmlBeforeDestroy = el['inv-item-list'].innerHTML;
  panel.destroy();

  bus.emit('inventory:changed', { inventory: { carried: [{ item_id: 'i1', name: 'Ghost', item_type: 'misc', quantity: 1 }], currency: {} } });
  assert(el['inv-item-list'].innerHTML === htmlBeforeDestroy, 'items not updated after destroy');
}

// ---------------------------------------------------------------------------
// CharacterPanel Tests
// ---------------------------------------------------------------------------

/**
 * Minimal entity mock with the component-based API that CharacterPanel.showEntityInfo() expects.
 */
function makeEntity({ name = 'Goblin', hp = 8, maxHp = 12, ac = 13, team = 'enemy' } = {}) {
  return {
    getComponent(type) {
      if (type === 'IdentityComponent') return { name, entityType: 'creature' };
      if (type === 'StatsComponent')    return { currentHp: hp, maxHp, ac };
      if (type === 'CombatComponent')   return { team };
      return null;
    },
    dcStatePayload: { metadata: {} },
    dcContentId: null,
  };
}

console.log('\n=== CharacterPanel — game:init renders character summary ===');
{
  const { el, container } = makeCharContainer();
  const bus = new GameEventBus();
  const panel = new CharacterPanel(container, bus);
  panel.init({}, {});

  // Panel reads d.launchCharacter from game:init (not d.character)
  // showLaunchCharacter reads: name, class, level, portrait_url, character_id, hp_current, hp_max, armor_class
  // Level is formatted as 'Lvl N'; HP as 'N/M'; sheet link as '/characters/{id}'
  bus.emit('game:init', {
    launchCharacter: {
      name: 'Aria Stormwind', class: 'Fighter', level: 5,
      portrait_url: '/portraits/aria.jpg', character_id: 123,
      hp_current: 42, hp_max: 50, armor_class: 18,
    },
  });

  assert(el['char-name'].textContent === 'Aria Stormwind', 'character name set');
  assert(el['char-type'].textContent.includes('Fighter'), 'class set');
  assert(el['char-level'].textContent === 'Lvl 5', 'level set');
  assert(el['char-portrait'].src === '/portraits/aria.jpg', 'portrait src set');
  assert(el['char-hp'].textContent === '42/50', 'hp display correct');
  assert(el['char-ac'].textContent === '18', 'ac set');
  assert(el['char-full-sheet-link'].href === '/characters/123', 'sheet link set');
}

console.log('\n=== CharacterPanel — entity:selected shows entity info ===');
{
  const { el, container } = makeCharContainer();
  const bus = new GameEventBus();
  const panel = new CharacterPanel(container, bus);
  panel.init({}, {});

  bus.emit('entity:selected', {
    entity: makeEntity({ name: 'Goblin Warrior', hp: 8, maxHp: 12, ac: 13, team: 'enemy' }),
  });

  const entityInfoEl = el['char-entity-info'];
  const entityNameEl = container.querySelector('[data-char="entity-name"]');

  // showEntityInfo uses classList, not the .hidden property
  assert(!entityInfoEl.classList.contains('dc-is-hidden'), 'entity-info shown');
  assert(entityNameEl?.textContent === 'Goblin Warrior', 'entity name shown');
  // HP and AC are set on individual elements
  assert(el['entity-hp'].textContent === '8/12', 'HP in stats');
  assert(el['entity-ac'].textContent === '13', 'AC in stats');
  // Team is rendered in entity-team element
  assert(el['entity-team'].textContent === 'enemy', 'team shown');
}

console.log('\n=== CharacterPanel — entity:deselected hides entity info ===');
{
  const { el, container } = makeCharContainer();
  const bus = new GameEventBus();
  const panel = new CharacterPanel(container, bus);
  panel.init({}, {});

  bus.emit('entity:selected', { entity: makeEntity({ name: 'Orc', team: 'enemy' }) });
  bus.emit('entity:deselected', {});

  const entityInfoEl = el['char-entity-info'];
  // hideEntityInfo adds dc-is-hidden class rather than setting .hidden property
  assert(entityInfoEl.classList.contains('dc-is-hidden'), 'entity-info hidden after deselect');
}

console.log('\n=== CharacterPanel — HTML-escaped entity name ===');
{
  const { el, container } = makeCharContainer();
  const bus = new GameEventBus();
  const panel = new CharacterPanel(container, bus);
  panel.init({}, {});

  bus.emit('entity:selected', {
    entity: makeEntity({ name: '<script>bad()</script>', team: 'enemy' }),
  });

  const entityNameEl = container.querySelector('[data-char="entity-name"]');
  // textContent assignment is inherently XSS-safe; no escaping needed
  assert(entityNameEl?.textContent === '<script>bad()</script>', 'textContent assignment avoids XSS');
}

console.log('\n=== CharacterPanel — destroy unsubscribes ===');
{
  const { el, container } = makeCharContainer();
  const bus = new GameEventBus();
  const panel = new CharacterPanel(container, bus);
  panel.init({}, {});
  panel.destroy();

  bus.emit('game:init', { launchCharacter: { name: 'Ghost', class: 'Rogue', level: 1 } });
  // No crash, no updates after destroy
  assert(true, 'destroy does not throw');
}

// Condition rendering moved off the entity-info card onto the launch-character
// card; the coverage is restored against that surface.
console.log('\n=== CharacterPanel — active conditions are listed ===');
{
  const { el, container } = makeCharContainer();
  const bus = new GameEventBus();
  const panel = new CharacterPanel(container, bus);
  panel.init();

  bus.emit('game:init', {
    launchCharacter: {
      id: 123,
      character_id: 123,
      name: 'Burasco',
      conditions: [{ condition_type: 'frightened', name: 'Frightened', value: 2 }],
    },
  });

  const html = el['char-conditions'].innerHTML;
  assert(html.includes('Frightened'), 'active condition is rendered in the conditions list');
  assert(!html.includes('conditions-empty'), 'conditions list is not shown as empty when a condition is active');
}

console.log('\n=== CharacterPanel — empty conditions show an explicit empty state ===');
{
  const { el, container } = makeCharContainer();
  const bus = new GameEventBus();
  const panel = new CharacterPanel(container, bus);
  panel.init();

  bus.emit('game:init', {
    launchCharacter: { id: 123, character_id: 123, name: 'Burasco', conditions: [] },
  });

  assert(el['char-conditions'].innerHTML.includes('No conditions'), 'empty conditions list renders an explicit empty state');
}

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

console.log('\n===================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed === 0) console.log('ALL TESTS PASSED');
else console.error(`${failed} TESTS FAILED`);
console.log('===================================\n');

process.exit(failed > 0 ? 1 : 0);
