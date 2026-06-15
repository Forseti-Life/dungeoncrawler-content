/**
 * @file
 * Regression coverage for the merchant-panel inventory refresh loop.
 *
 * Run with:
 *   node tests/merchant_panel_inventory_refresh_loop_test.js
 */

const fs = require('fs');
const path = require('path');

global.escapeQuestHtml = (value) => String(value ?? '');
global.escapeTooltipAttr = (value) => String(value ?? '');
global.collectInventoryItems = () => [];
global.normalizeInventoryState = (inventory = {}, currency = {}) => ({
  ...inventory,
  currency: inventory?.currency || currency || {},
});

global.document = {
  body: { dataset: {} },
  getElementById() { return null; },
  addEventListener() {},
  removeEventListener() {},
};

global.window = {
  addEventListener() {},
  removeEventListener() {},
  setTimeout,
  clearTimeout,
  getComputedStyle() {
    return { display: 'block' };
  },
};

function loadClass(relPath, className) {
  let src = fs.readFileSync(path.resolve(__dirname, relPath), 'utf8');
  src = src.replace(/^import[\s\S]*?;\s*$/gm, '');
  src = src.replace(/^export\s+/gm, '');
  return new Function(src + `\nreturn { ${className} };`)()[className];
}

const MerchantPanel = loadClass('../js/v2/panels/MerchantPanel.js', 'MerchantPanel');
const GameEventBus = loadClass('../js/v2/GameEventBus.js', 'GameEventBus');

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
  console.log(`✓ ${message}`);
}

function makeMerchantOccupant() {
  return {
    occupant_id: 'npc_tavern_keeper',
    content_id: 'npc_tavern_keeper',
    room_id: 'tavern_entrance',
    label: 'Eldric',
    presentation: {
      is_merchant: true,
      role: 'Merchant',
      stock: [],
      player_currency: { gp: 12 },
    },
  };
}

function makePanel() {
  const bus = new GameEventBus();
  const panel = new MerchantPanel(null, bus);
  panel.renderMerchantPanel = () => {};
  panel.setMerchantStatus = () => {};
  panel.resetMerchantCatalogSearch();
  panel.activeGameShellTab = 'merchant';
  panel.stateManager = {
    hexmap: {
      resolveCampaignId: () => 246,
      resolveActiveRoomId: () => 'tavern_entrance',
    },
  };
  panel._cachedOccupants = [makeMerchantOccupant()];
  return { panel, bus };
}

console.log('\n=== MerchantPanel inventory refresh loop regression ===');

{
  const { panel, bus } = makePanel();
  let refreshRequests = 0;
  bus.on('character:inventory-refresh-requested', () => {
    refreshRequests += 1;
  });

  panel.currentCharacterInventoryContext = {
    characterId: 904,
    inventory: { items: [], currency: { gp: 12 } },
    currency: { gp: 12 },
  };

  panel.loadMerchantPanel(true);
  assert(refreshRequests === 0, 'force rerender skips inventory refresh when active character inventory is already loaded');
}

{
  const { panel, bus } = makePanel();
  let refreshRequests = 0;
  bus.on('character:inventory-refresh-requested', () => {
    refreshRequests += 1;
  });

  panel.loadMerchantPanel(true);
  assert(refreshRequests === 1, 'merchant panel still requests one inventory refresh when no active inventory context exists');
}

console.log('\nALL TESTS PASSED');
