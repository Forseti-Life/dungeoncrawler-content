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
const { loadModuleExport } = require('./helpers/js-module.js');
const { installDom } = require('./helpers/fake-dom.js');

function loadClass(relPath, className) {
  return loadModuleExport(relPath, className);
}

const PortraitPanel  = loadClass('../js/v2/panels/PortraitPanel.js',  'PortraitPanel');
const MerchantPanel  = loadClass('../js/v2/panels/MerchantPanel.js',  'MerchantPanel');
const GameEventBus   = loadClass('../js/v2/GameEventBus.js',          'GameEventBus');

// Panels resolve their elements via document.getElementById(), building output
// with createElement()/appendChild(). Model that faithfully with the shared
// fake DOM so the tests observe what the panels actually render.
const PORTRAIT_IDS = [
  'npc-portraits-panel',
  'npc-portraits-name',
  'npc-portraits-meta',
  'npc-portraits-status',
  'npc-portraits-grid',
  'npc-portraits-placeholder',
  'npc-portraits-placeholder-text',
];

const MERCHANT_IDS = [
  'merchant-panel-portrait-wrap',
  'merchant-panel-portrait',
  'merchant-panel-name',
  'merchant-panel-summary',
  'merchant-entity-select',
  'merchant-item-filter',
  'merchant-backroom-search',
  'merchant-panel-status',
  'merchant-player-currency',
  'merchant-panel-grid',
  'merchant-panel-empty',
  'merchant-stock-list',
  'merchant-sell-list',
];

// Short assertion keys -> real element ids in the Twig template.
const ALIASES = {
  'room-name': 'npc-portraits-name',
  count: 'npc-portraits-meta',
  status: 'npc-portraits-status',
  grid: 'npc-portraits-grid',
  placeholder: 'npc-portraits-placeholder',
  'placeholder-text': 'npc-portraits-placeholder-text',
  name: 'merchant-panel-name',
  role: 'merchant-panel-summary',
  select: 'merchant-entity-select',
  filter: 'merchant-item-filter',
  'player-currency': 'merchant-player-currency',
  empty: 'merchant-panel-empty',
  'stock-grid': 'merchant-stock-list',
  'sell-list': 'merchant-sell-list',
};

function makeContainer() {
  const dom = installDom([...PORTRAIT_IDS, ...MERCHANT_IDS]);
  const container = dom.document.body;
  container._elements = new Proxy({}, {
    get(_t, key) {
      const id = ALIASES[key] || key;
      return dom.document.getElementById(id);
    },
  });
  return container;
}

function makeOccupant({ id = 'o1', label = 'Aria', type = 'pc', portraitUrl = null, role = '', isMerchant = false, stock = [], roomId = 'room-1' } = {}) {
  return {
    occupant_id:   id,
    content_id:    id,
    room_id:       roomId,
    label,
    occupant_type: type === 'pc' ? 'player_character' : type,
    presentation: {
      portrait_url: portraitUrl,
      role,
      is_merchant:  isMerchant,
      stock,
      player_currency: { gp: 50, sp: 10, cp: 5 },
    },
  };
}

/**
 * PortraitPanel sources occupants from canonical state via
 * `stateManager.hexmap.getVisualOccupants()`, not from the event payload; the
 * event only carries the room id. Model that state manager here.
 */
function makeStateManager(occupants = [], roomId = 'room-1', rooms = {}) {
  return {
    hexmap: {
      resolveActiveRoomId: () => roomId,
      getVisualRooms: () => rooms,
      getVisualOccupants: () => occupants,
      isVisualOccupantVisible: () => true,
      getObjectDefinition: () => null,
      spriteService: { getCachedUrl: () => null },
    },
  };
}

function mountPortraitPanel(occupants, roomName = 'Current room') {
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new PortraitPanel(container, bus);
  panel.init({}, makeStateManager(occupants, 'room-1', { 'room-1': { name: roomName } }));
  return { bus, container, panel };
}

// ---------------------------------------------------------------------------
// PortraitPanel tests
// ---------------------------------------------------------------------------

console.log('\n=== PortraitPanel — empty room renders placeholder ===');
{
  const { bus, container } = mountPortraitPanel([]);
  bus.emit('room:occupants-changed', { roomId: 'room-1' });

  assert(
    container._elements['count']?.textContent === 'Portraits for PCs and NPCs in the active room.',
    'meta shows the empty-room summary'
  );
  assert(container._elements['grid']?.hidden === true, 'grid hidden when empty');
  assert(container._elements['placeholder']?.hidden === false, 'placeholder visible when empty');
}

console.log('\n=== PortraitPanel — canonical occupants render cards ===');
{
  const occupants = [
    makeOccupant({ id: 'pc1',  label: 'Aria',  type: 'pc',  portraitUrl: '/portraits/aria.jpg', role: 'Fighter' }),
    makeOccupant({ id: 'npc1', label: 'Grusk', type: 'npc', role: 'Blacksmith' }),
  ];
  const { bus, container } = mountPortraitPanel(occupants, 'The Forge');
  bus.emit('room:occupants-changed', { roomId: 'room-1' });

  assert(container._elements['room-name']?.textContent === 'The Forge', 'room-name set');
  assert(container._elements['count']?.textContent === '2 room portraits', 'meta counts room portraits');

  const html = container._elements['grid']?.innerHTML ?? '';
  assert(html.includes('Aria'),                'portrait grid includes PC name');
  assert(html.includes('Grusk'),               'portrait grid includes NPC name');
  assert(html.includes('Fighter'),             'portrait grid shows role summary');
  assert(html.includes('/portraits/aria.jpg'), 'portrait grid shows image URL');
  assert(container._elements['grid']?.hidden === false, 'grid visible when occupants present');
}

console.log('\n=== PortraitPanel — PCs sort before NPCs ===');
{
  const occupants = [
    makeOccupant({ id: 'n1', label: 'Zara', type: 'npc' }),
    makeOccupant({ id: 'p1', label: 'Abel', type: 'pc'  }),
  ];
  const { bus, container } = mountPortraitPanel(occupants);
  bus.emit('room:occupants-changed', { roomId: 'room-1' });

  const html = container._elements['grid']?.innerHTML ?? '';
  const posAbel = html.indexOf('Abel');
  const posZara = html.indexOf('Zara');
  assert(posAbel !== -1 && posZara !== -1 && posAbel < posZara, 'PC sorts before NPC');
}

console.log('\n=== PortraitPanel — duplicate occupant_ids deduped ===');
{
  const occupants = [
    makeOccupant({ id: 'x1', label: 'Talia', type: 'pc' }),
    makeOccupant({ id: 'x1', label: 'Talia', type: 'pc' }),
  ];
  const { bus, container } = mountPortraitPanel(occupants);
  bus.emit('room:occupants-changed', { roomId: 'room-1' });

  assert(container._elements['count']?.textContent === '1 room portrait', 'duplicates deduped');
}

console.log('\n=== PortraitPanel — portrait placeholder when no URL ===');
{
  const { bus, container } = mountPortraitPanel([
    makeOccupant({ id: 'q1', label: 'Wren', type: 'npc' }),
  ]);
  bus.emit('room:occupants-changed', { roomId: 'room-1' });

  const html = container._elements['grid']?.innerHTML ?? '';
  assert(html.includes('npc-portrait-card__placeholder'), 'placeholder div shown for missing portrait');
  assert(html.includes('>W<'), 'initial letter shown in placeholder');
}

console.log('\n=== PortraitPanel — destroy stops updates ===');
{
  const { bus, container, panel } = mountPortraitPanel([
    makeOccupant({ id: 'a1', label: 'Ghost', type: 'npc' }),
  ]);
  panel.destroy();
  bus.emit('room:occupants-changed', { roomId: 'room-1' });

  assert(
    (container._elements['grid']?.innerHTML ?? '') === '',
    'destroyed panel does not update'
  );
}

// ---------------------------------------------------------------------------
// MerchantPanel tests
//
// The panel derives merchant *candidates* from room occupants, but stock and
// pricing come from a merchant context assembled by loadMerchantPanel() (an
// authoritative server fetch). Tests therefore drive candidates through the
// occupants event and the rendered trade view through renderMerchantPanel().
// ---------------------------------------------------------------------------

function mountMerchantPanel(occupants = []) {
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new MerchantPanel(container, bus);
  panel.init();
  bus.emit('room:occupants-changed', { roomId: 'room-1', occupants });
  return { bus, container, panel };
}

function makeMerchantContext({ name = 'Grak', role = 'Armorer', stock = [], currencyLabel = '50 gp' } = {}) {
  return {
    merchant: { name, role },
    player: { currency_label: currencyLabel, sellable_inventory: [] },
    stock,
  };
}

console.log('\n=== MerchantPanel — no merchants renders empty state ===');
{
  const { container } = mountMerchantPanel([makeOccupant({ id: 'p1', type: 'pc' })]);

  assert(container._elements['empty']?.hidden === false, 'empty message shown when no merchant');
  assert(
    container._elements['empty']?.textContent === 'No merchant is present in this room.',
    'empty state names the missing merchant condition'
  );
}

console.log('\n=== MerchantPanel — renders merchant name, role and stock ===');
{
  const stock = [
    { item_id: 's1', name: 'Iron Sword',    price_label: '15 gp', quantity_available: 3 },
    { item_id: 's2', name: 'Health Potion', price_label: '5 gp',  quantity_available: 10 },
  ];
  const { container, panel } = mountMerchantPanel([
    makeOccupant({ id: 'm1', label: 'Grak', type: 'npc', isMerchant: true, role: 'Armorer' }),
  ]);
  panel.renderMerchantPanel(makeMerchantContext({ stock }));

  assert(container._elements['name']?.textContent === 'Grak', 'merchant name rendered');
  assert(
    container._elements['role']?.textContent.includes('Armorer'),
    'merchant role rendered in the summary line'
  );
  assert(
    container._elements['player-currency']?.textContent.includes('50 gp'),
    'player currency shown'
  );

  const html = container._elements['stock-grid']?.innerHTML ?? '';
  assert(html.includes('Iron Sword'),    'stock includes first item');
  assert(html.includes('Health Potion'), 'stock includes second item');
  assert(html.includes('15 gp'),         'item price shown');
  assert(container._elements['empty']?.hidden === true, 'empty state hidden once a merchant is loaded');
}

console.log('\n=== MerchantPanel — switching merchants swaps stock ===');
{
  const { container, panel } = mountMerchantPanel([
    makeOccupant({ id: 'm1', label: 'Grak',  type: 'npc', isMerchant: true, role: 'Armorer' }),
    makeOccupant({ id: 'm2', label: 'Vella', type: 'npc', isMerchant: true, role: 'Alchemist' }),
  ]);

  panel.renderMerchantPanel(makeMerchantContext({
    stock: [{ item_id: 's1', name: 'Iron Sword', price_label: '15 gp' }],
  }));
  assert(
    (container._elements['stock-grid']?.innerHTML ?? '').includes('Iron Sword'),
    'first merchant stock rendered'
  );

  panel.renderMerchantPanel(makeMerchantContext({
    name: 'Vella',
    role: 'Alchemist',
    stock: [{ item_id: 's9', name: 'Elixir of Sight', price_label: '30 gp' }],
  }));
  const html = container._elements['stock-grid']?.innerHTML ?? '';
  assert(html.includes('Elixir of Sight'), 'switched to second merchant stock');
  assert(!html.includes('Iron Sword'),     'previous merchant stock cleared');
}

console.log('\n=== MerchantPanel — room merchants offered as candidates ===');
{
  const { container } = mountMerchantPanel([
    makeOccupant({ id: 'm1', label: 'Grak',  type: 'npc', isMerchant: true }),
    makeOccupant({ id: 'm2', label: 'Vella', type: 'npc', isMerchant: true }),
    makeOccupant({ id: 'p1', label: 'Aria',  type: 'pc' }),
  ]);

  const options = container._elements['select']?.innerHTML ?? '';
  assert(options.includes('Grak') && options.includes('Vella'), 'merchant select lists both merchants');
  assert(!options.includes('Aria'), 'non-merchant occupants excluded from merchant select');
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
