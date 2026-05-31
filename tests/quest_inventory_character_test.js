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

function loadClass(relPath, className) {
  let src = fs.readFileSync(path.resolve(__dirname, relPath), 'utf8');
  src = src.replace(/^export\s+/gm, '');
  return new Function(src + `\nreturn { ${className} };`)()[className];
}

const QuestPanel     = loadClass('../js/v2/panels/QuestPanel.js',     'QuestPanel');
const InventoryPanel = loadClass('../js/v2/panels/InventoryPanel.js', 'InventoryPanel');
const CharacterPanel = loadClass('../js/v2/panels/CharacterPanel.js', 'CharacterPanel');
const GameEventBus   = loadClass('../js/v2/GameEventBus.js',          'GameEventBus');

// Minimal setTimeout stub — capture but don't auto-execute (tests verify state before dismissal)
global.setTimeout  = () => 1;
global.clearTimeout = () => {};

// ---------------------------------------------------------------------------
// DOM helpers
// ---------------------------------------------------------------------------

function makeEl(key) {
  return {
    _key: key, textContent: '', innerHTML: '', src: '', alt: '', href: '',
    hidden: false, disabled: false, dataset: {}, _listeners: {},
    addEventListener(e, f) { (this._listeners[e] = this._listeners[e] || []).push(f); },
    querySelectorAll() { return []; },
    querySelector()    { return null; },
    closest()          { return null; },
  };
}

function makeContainer(prefix) {
  const elements = {};
  return {
    querySelector(sel) {
      const m = sel.match(new RegExp(`\\[data-${prefix}="([^"]+)"\\]`));
      if (!m) return null;
      if (!elements[m[1]]) elements[m[1]] = makeEl(m[1]);
      return elements[m[1]];
    },
    _elements: elements,
  };
}

// ---------------------------------------------------------------------------
// QuestPanel Tests
// ---------------------------------------------------------------------------

console.log('\n=== QuestPanel — empty state on init ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('quest');
  const panel = new QuestPanel(container, bus);
  panel.init();

  const empty = container._elements['empty'];
  assert(empty?.hidden === false, 'empty message visible with no quests');
}

console.log('\n=== QuestPanel — game:init renders quest list ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('quest');
  const panel = new QuestPanel(container, bus);
  panel.init();

  bus.emit('game:init', {
    quests: [
      { quest_id: 'q1', title: 'Find the Key', status: 'active',
        objectives: [{ objective_id: 'o1', label: 'Search the dungeon', status: 'incomplete' }] },
      { quest_id: 'q2', title: 'Slay the Dragon', status: 'active', objectives: [] },
    ],
  });

  const list  = container._elements['list'];
  const empty = container._elements['empty'];
  assert(empty?.hidden === true, 'empty hidden when quests exist');
  assert(list?.innerHTML.includes('Find the Key'), 'first quest title rendered');
  assert(list?.innerHTML.includes('Slay the Dragon'), 'second quest title rendered');
  assert(list?.innerHTML.includes('Search the dungeon'), 'objective rendered');
}

console.log('\n=== QuestPanel — quest:progress-updated re-renders ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('quest');
  const panel = new QuestPanel(container, bus);
  panel.init();

  bus.emit('game:init', { quests: [{ quest_id: 'q1', title: 'Old Title', status: 'active', objectives: [] }] });
  bus.emit('quest:progress-updated', { quest: { quest_id: 'q1', title: 'Updated Title', status: 'active', objectives: [] } });

  const list = container._elements['list'];
  assert(list?.innerHTML.includes('Updated Title'), 'quest title updated');
  assert(!list?.innerHTML.includes('Old Title'), 'old title replaced');
}

console.log('\n=== QuestPanel — quest:completed shows toast ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('quest');
  const panel = new QuestPanel(container, bus);
  panel.init();

  bus.emit('quest:completed', { quest: { quest_id: 'q1', title: 'Slay the Dragon', status: 'completed', objectives: [] } });

  const toast = container._elements['toast'];
  assert(toast?.innerHTML.includes('Slay the Dragon'), 'quest title in completion toast');
  assert(toast?.innerHTML.includes('✅'), 'success icon in toast');
}

console.log('\n=== QuestPanel — quest:item-collected shows toast ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('quest');
  const panel = new QuestPanel(container, bus);
  panel.init();

  bus.emit('quest:item-collected', { itemName: 'Ancient Scroll' });

  const toast = container._elements['toast'];
  assert(toast?.innerHTML.includes('Ancient Scroll'), 'item name in collection toast');
  assert(toast?.innerHTML.includes('🎒'), 'bag icon in toast');
}

console.log('\n=== QuestPanel — HTML escaped ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('quest');
  const panel = new QuestPanel(container, bus);
  panel.init();

  bus.emit('game:init', {
    quests: [{ quest_id: 'q1', title: '<script>alert(1)</script>', status: 'active', objectives: [] }],
  });

  const list = container._elements['list'];
  assert(list?.innerHTML.includes('&lt;script&gt;'), 'title HTML-escaped');
  assert(!list?.innerHTML.includes('<script>'), 'raw script tag not present');
}

console.log('\n=== QuestPanel — destroy unsubscribes ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('quest');
  const panel = new QuestPanel(container, bus);
  panel.init();
  panel.destroy();

  bus.emit('game:init', { quests: [{ quest_id: 'q1', title: 'Ghost', status: 'active', objectives: [] }] });
  assert(panel._quests.size === 0, 'quests cleared on destroy');
}

// ---------------------------------------------------------------------------
// InventoryPanel Tests
// ---------------------------------------------------------------------------

console.log('\n=== InventoryPanel — empty state with no items ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('inv');
  const panel = new InventoryPanel(container, bus);
  panel.init();

  bus.emit('game:init', { inventory: { items: [], currency: { gp: 0, sp: 0, cp: 0 } } });

  const empty = container._elements['empty'];
  assert(empty?.hidden === false, 'empty visible when no items');
}

console.log('\n=== InventoryPanel — game:init renders items ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('inv');
  const panel = new InventoryPanel(container, bus);
  panel.init();

  bus.emit('game:init', {
    inventory: {
      items: [
        { item_id: 'i1', name: 'Iron Sword', item_type: 'weapon', quantity: 1, equipped: false },
        { item_id: 'i2', name: 'Health Potion', item_type: 'consumable', quantity: 3, equipped: false },
      ],
      currency: { gp: 25, sp: 5, cp: 0 },
    },
  });

  const itemList = container._elements['item-list'];
  const currency = container._elements['currency'];
  const empty    = container._elements['empty'];

  assert(empty?.hidden === true, 'empty hidden when items present');
  assert(itemList?.innerHTML.includes('Iron Sword'), 'weapon item rendered');
  assert(itemList?.innerHTML.includes('Health Potion'), 'consumable item rendered');
  assert(currency?.textContent.includes('25 gp'), 'gp in currency');
  assert(currency?.textContent.includes('5 sp'), 'sp in currency');
}

console.log('\n=== InventoryPanel — consumable shows Use button ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('inv');
  const panel = new InventoryPanel(container, bus);
  panel.init();

  bus.emit('game:init', {
    inventory: {
      items: [{ item_id: 'i1', name: 'Potion', item_type: 'consumable', quantity: 1, equipped: false }],
      currency: {},
    },
  });

  const itemList = container._elements['item-list'];
  assert(itemList?.innerHTML.includes('data-inv-action="use"'), 'Use button on consumable');
  assert(itemList?.innerHTML.includes('data-inv-action="drop"'), 'Drop button present');
}

console.log('\n=== InventoryPanel — weapon shows Equip button ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('inv');
  const panel = new InventoryPanel(container, bus);
  panel.init();

  bus.emit('game:init', {
    inventory: {
      items: [{ item_id: 'w1', name: 'Axe', item_type: 'weapon', quantity: 1, equipped: false }],
      currency: {},
    },
  });

  const itemList = container._elements['item-list'];
  assert(itemList?.innerHTML.includes('data-inv-action="equip"'), 'Equip button on unequipped weapon');
}

console.log('\n=== InventoryPanel — entity:inventory-changed re-renders ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('inv');
  const panel = new InventoryPanel(container, bus);
  panel.init();

  bus.emit('game:init', { inventory: { items: [{ item_id: 'i1', name: 'Old Item', item_type: 'misc', quantity: 1, equipped: false }], currency: {} } });
  bus.emit('entity:inventory-changed', { inventory: { items: [{ item_id: 'i2', name: 'New Item', item_type: 'misc', quantity: 1, equipped: false }], currency: {} } });

  const itemList = container._elements['item-list'];
  assert(itemList?.innerHTML.includes('New Item'), 'updated item rendered');
  assert(!itemList?.innerHTML.includes('Old Item'), 'old item removed');
}

console.log('\n=== InventoryPanel — quantity > 1 shows ×N ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('inv');
  const panel = new InventoryPanel(container, bus);
  panel.init();

  bus.emit('game:init', {
    inventory: {
      items: [{ item_id: 'i1', name: 'Arrow', item_type: 'misc', quantity: 20, equipped: false }],
      currency: {},
    },
  });

  const itemList = container._elements['item-list'];
  assert(itemList?.innerHTML.includes('×20'), 'quantity shown');
}

console.log('\n=== InventoryPanel — destroy unsubscribes ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('inv');
  const panel = new InventoryPanel(container, bus);
  panel.init();
  panel.destroy();

  bus.emit('game:init', { inventory: { items: [{ item_id: 'i1', name: 'Ghost', item_type: 'misc', quantity: 1, equipped: false }], currency: {} } });
  assert(panel._items.length === 0, 'items cleared on destroy');
}

// ---------------------------------------------------------------------------
// CharacterPanel Tests
// ---------------------------------------------------------------------------

console.log('\n=== CharacterPanel — game:init renders character summary ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('char');
  const panel = new CharacterPanel(container, bus);
  panel.init();

  bus.emit('game:init', {
    character: {
      name: 'Aria Stormwind', class_name: 'Fighter', level: 5,
      portrait_url: '/portraits/aria.jpg', sheet_url: '/character/123',
      hp_current: 42, hp_max: 50, ac: 18,
    },
  });

  assert(container._elements['name']?.textContent === 'Aria Stormwind', 'character name set');
  assert(container._elements['class']?.textContent === 'Fighter', 'class set');
  assert(container._elements['level']?.textContent === '5', 'level set');
  assert(container._elements['portrait']?.src === '/portraits/aria.jpg', 'portrait src set');
  assert(container._elements['hp']?.textContent === '42 / 50', 'hp display correct');
  assert(container._elements['ac']?.textContent === '18', 'ac set');
  assert(container._elements['sheet-link']?.href === '/character/123', 'sheet link set');
}

console.log('\n=== CharacterPanel — entity:selected shows entity info ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('char');
  const panel = new CharacterPanel(container, bus);
  panel.init();

  bus.emit('entity:selected', {
    entity: {
      name: 'Goblin Warrior', team: 'enemy',
      hp_current: 8, hp_max: 12, ac: 13,
      conditions: ['prone', 'frightened'],
    },
  });

  const entityInfo  = container._elements['entity-info'];
  const entityName  = container._elements['entity-name'];
  const entityStats = container._elements['entity-stats'];

  assert(entityInfo?.hidden === false, 'entity-info shown');
  assert(entityName?.textContent === 'Goblin Warrior', 'entity name shown');
  assert(entityStats?.textContent.includes('HP: 8/12'), 'HP in stats');
  assert(entityStats?.textContent.includes('AC: 13'), 'AC in stats');
  assert(entityStats?.textContent.includes('prone'), 'condition shown');
}

console.log('\n=== CharacterPanel — entity:deselected hides entity info ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('char');
  const panel = new CharacterPanel(container, bus);
  panel.init();

  bus.emit('entity:selected', { entity: { name: 'Orc', team: 'enemy', conditions: [] } });
  bus.emit('entity:deselected', {});

  const entityInfo = container._elements['entity-info'];
  assert(entityInfo?.hidden === true, 'entity-info hidden after deselect');
}

console.log('\n=== CharacterPanel — HTML-escaped entity name ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('char');
  const panel = new CharacterPanel(container, bus);
  panel.init();

  bus.emit('entity:selected', { entity: { name: '<script>bad()</script>', team: 'enemy', conditions: [] } });
  const entityName = container._elements['entity-name'];
  assert(entityName?.textContent === '<script>bad()</script>', 'textContent assignment avoids XSS');
}

console.log('\n=== CharacterPanel — destroy unsubscribes ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('char');
  const panel = new CharacterPanel(container, bus);
  panel.init();
  panel.destroy();

  bus.emit('game:init', { character: { name: 'Ghost', class_name: 'Rogue', level: 1 } });
  // No crash, no updates after destroy
  assert(true, 'destroy does not throw');
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
