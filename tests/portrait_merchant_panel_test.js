/**
 * @file
 * Unit tests for PortraitPanel and MerchantPanel (Phase 6).
 *
 * Run with:
 *   node tests/portrait_merchant_panel_test.js
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

const PortraitPanel  = loadClass('../js/v2/panels/PortraitPanel.js',  'PortraitPanel');
const MerchantPanel  = loadClass('../js/v2/panels/MerchantPanel.js',  'MerchantPanel');
const GameEventBus   = loadClass('../js/v2/GameEventBus.js',          'GameEventBus');

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeContainer() {
  const elements = {};
  const makeEl = (key) => {
    if (!elements[key]) {
      elements[key] = {
        _key: key, textContent: '', innerHTML: '', src: '', alt: '', value: '',
        hidden: false, disabled: false, style: {},
        dataset: {}, _listeners: {},
        addEventListener(evt, fn) { (this._listeners[evt] = this._listeners[evt] || []).push(fn); },
        querySelectorAll(sel) {
          if (sel === '.merchant-item-card') {
            const html = elements['stock-grid']?.innerHTML ?? '';
            const cards = [];
            let m; const re = /data-search-text="([^"]*)"/g;
            while ((m = re.exec(html)) !== null) {
              const hidden = false;
              cards.push({ textContent: m[1], dataset: { searchText: m[1] }, hidden, set hidden(v) {} });
            }
            return cards;
          }
          if (sel === '[data-merchant-buy]') return [];
          return [];
        },
        querySelector() { return null; },
        closest() { return null; },
      };
    }
    return elements[key];
  };

  return {
    querySelector(sel) {
      const m1 = sel.match(/\[data-portrait="([^"]+)"\]/);
      if (m1) return makeEl(m1[1]);
      const m2 = sel.match(/\[data-merchant="([^"]+)"\]/);
      if (m2) return makeEl(m2[1]);
      return null;
    },
    addEventListener() {},
    querySelectorAll() { return []; },
    _elements: elements,
  };
}

function makeOccupant({ id = 'o1', label = 'Aria', type = 'pc', portraitUrl = null, role = '', isMerchant = false, stock = [] } = {}) {
  return {
    occupant_id:   id,
    content_id:    id,
    label,
    occupant_type: type,
    presentation: {
      portrait_url: portraitUrl,
      role,
      is_merchant:  isMerchant,
      stock,
      player_currency: { gp: 50, sp: 10, cp: 5 },
    },
  };
}

// ---------------------------------------------------------------------------
// PortraitPanel tests
// ---------------------------------------------------------------------------

console.log('\n=== PortraitPanel — empty room renders placeholder ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new PortraitPanel(container, bus);
  panel.init();

  assert(container._elements['count']?.textContent === '0 occupants', 'count shows 0 occupants');
  assert(container._elements['grid']?.hidden !== false, 'grid hidden when empty');
  assert(container._elements['placeholder']?.hidden === false, 'placeholder visible when empty');
}

console.log('\n=== PortraitPanel — room:occupants-changed renders cards ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new PortraitPanel(container, bus);
  panel.init();

  const occupants = [
    makeOccupant({ id: 'pc1', label: 'Aria',   type: 'pc',  portraitUrl: '/portraits/aria.jpg', role: 'Fighter' }),
    makeOccupant({ id: 'npc1', label: 'Grusk', type: 'npc', role: 'Blacksmith' }),
  ];
  bus.emit('room:occupants-changed', { occupants, roomName: 'The Forge' });

  assert(container._elements['room-name']?.textContent === 'The Forge', 'room-name set');
  assert(container._elements['count']?.textContent === '2 occupants', 'count shows 2');

  const html = container._elements['grid']?.innerHTML ?? '';
  assert(html.includes('Aria'),       'portrait grid includes PC name');
  assert(html.includes('Grusk'),      'portrait grid includes NPC name');
  assert(html.includes('Fighter'),    'portrait grid shows role');
  assert(html.includes('/portraits/aria.jpg'), 'portrait grid shows image URL');
  assert(!container._elements['grid']?.hidden, 'grid visible when occupants present');
}

console.log('\n=== PortraitPanel — PCs sort before NPCs ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new PortraitPanel(container, bus);
  panel.init();

  const occupants = [
    makeOccupant({ id: 'n1', label: 'Zara',  type: 'npc' }),
    makeOccupant({ id: 'p1', label: 'Abel',  type: 'pc' }),
  ];
  bus.emit('room:occupants-changed', { occupants });

  const html = container._elements['grid']?.innerHTML ?? '';
  const posAbel = html.indexOf('Abel');
  const posZara = html.indexOf('Zara');
  assert(posAbel < posZara && posAbel !== -1, 'PC sorts before NPC');
}

console.log('\n=== PortraitPanel — duplicate occupant_ids deduped ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new PortraitPanel(container, bus);
  panel.init();

  const occupants = [
    makeOccupant({ id: 'x1', label: 'Talia', type: 'pc' }),
    makeOccupant({ id: 'x1', label: 'Talia', type: 'pc' }), // duplicate
  ];
  bus.emit('room:occupants-changed', { occupants });

  assert(container._elements['count']?.textContent === '1 occupant', 'duplicates deduped');
}

console.log('\n=== PortraitPanel — portrait placeholder when no URL ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new PortraitPanel(container, bus);
  panel.init();

  bus.emit('room:occupants-changed', { occupants: [makeOccupant({ id: 'q1', label: 'Wren', type: 'npc' })] });

  const html = container._elements['grid']?.innerHTML ?? '';
  assert(html.includes('npc-portrait-card__placeholder'), 'placeholder div shown for missing portrait');
  assert(html.includes('>W<') || html.includes('>W&'), 'initial letter shown in placeholder');
}

console.log('\n=== PortraitPanel — destroy stops updates ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new PortraitPanel(container, bus);
  panel.init();
  panel.destroy();

  bus.emit('room:occupants-changed', { occupants: [makeOccupant({ id: 'a1', label: 'Ghost' })] });
  assert(container._elements['count']?.textContent === '0 occupants', 'destroyed panel does not update');
}

// ---------------------------------------------------------------------------
// MerchantPanel tests
// ---------------------------------------------------------------------------

console.log('\n=== MerchantPanel — no merchants renders empty state ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new MerchantPanel(container, bus);
  panel.init();

  bus.emit('room:occupants-changed', { occupants: [makeOccupant({ id: 'p1', type: 'pc' })] });

  assert(container._elements['empty']?.hidden === false, 'empty message shown when no merchant');
  assert((container._elements['stock-grid']?.innerHTML ?? '') === '', 'stock grid empty');
}

console.log('\n=== MerchantPanel — renders merchant name and stock ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new MerchantPanel(container, bus);
  panel.init();

  const stock = [
    { item_id: 's1', item_name: 'Iron Sword', price: 15, quantity: 3, description: 'A sturdy blade', item_type: 'weapon' },
    { item_id: 's2', item_name: 'Health Potion', price: 5, quantity: 10, description: 'Restores 2d8+4 HP', item_type: 'consumable' },
  ];
  const occupants = [
    makeOccupant({ id: 'm1', label: 'Grak', type: 'npc', isMerchant: true, role: 'Armorer', stock }),
  ];
  bus.emit('room:occupants-changed', { occupants });

  assert(container._elements['name']?.textContent === 'Grak', 'merchant name rendered');
  assert(container._elements['role']?.textContent === 'Armorer', 'merchant role rendered');
  assert(container._elements['player-currency']?.textContent.includes('50 gp'), 'player currency shown');

  const html = container._elements['stock-grid']?.innerHTML ?? '';
  assert(html.includes('Iron Sword'),    'stock includes first item');
  assert(html.includes('Health Potion'), 'stock includes second item');
  assert(html.includes('15 gp'),         'item price shown');
}

console.log('\n=== MerchantPanel — user:merchant-selected switches merchant ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new MerchantPanel(container, bus);
  panel.init();

  const stock1 = [{ item_id: 'a1', item_name: 'Axe', price: 20, quantity: 1, item_type: 'weapon' }];
  const stock2 = [{ item_id: 'b1', item_name: 'Bow', price: 25, quantity: 2, item_type: 'weapon' }];
  const occupants = [
    makeOccupant({ id: 'M1', label: 'Hilde', type: 'npc', isMerchant: true, stock: stock1 }),
    makeOccupant({ id: 'M2', label: 'Finn',  type: 'npc', isMerchant: true, stock: stock2 }),
  ];
  bus.emit('room:occupants-changed', { occupants });

  // Switch to second merchant via bus
  bus.emit('user:merchant-selected', { occupantId: 'M2' });

  const html = container._elements['stock-grid']?.innerHTML ?? '';
  assert(html.includes('Bow'), 'switched to second merchant stock');
  assert(!html.includes('Axe'), 'first merchant stock not shown after switch');
}

console.log('\n=== MerchantPanel — destroy unsubscribes ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new MerchantPanel(container, bus);
  panel.init();
  panel.destroy();

  const occ = [makeOccupant({ id: 'm9', label: 'Ghost', isMerchant: true, stock: [] })];
  bus.emit('room:occupants-changed', { occupants: occ });

  assert(container._elements['name']?.textContent === '', 'destroyed panel does not update');
}

console.log('\n=== MerchantPanel — non-merchant occupants ignored ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new MerchantPanel(container, bus);
  panel.init();

  const occupants = [
    makeOccupant({ id: 'p1', label: 'Lena', type: 'pc',  isMerchant: false }),
    makeOccupant({ id: 'n1', label: 'Guard', type: 'npc', isMerchant: false }),
  ];
  bus.emit('room:occupants-changed', { occupants });

  assert(container._elements['empty']?.hidden === false, 'no-merchant state shown when all occupants are non-merchants');
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
