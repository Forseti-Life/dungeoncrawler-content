/**
 * @file
 * Unit tests for RoomViewPanel, PartyRailPanel, StatusPanel (Phase 9).
 *
 * Run with:
 *   node tests/room_party_status_test.js
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

// These panels resolve elements through container-scoped `[data-*]` lookups and
// global ids, and build output with createElement()/appendChild(). The shared
// fake DOM models both so the tests observe what the panels actually render.
const { installDom, makeScopedContainer } = require('./helpers/fake-dom.js');
const { loadModuleExport } = require('./helpers/js-module.js');

function loadClass(relPath, className) {
  return loadModuleExport(relPath, className);
}

const RoomViewPanel  = loadClass('../js/v2/panels/RoomViewPanel.js',  'RoomViewPanel');
const PartyRailPanel = loadClass('../js/v2/panels/PartyRailPanel.js', 'PartyRailPanel');
const StatusPanel    = loadClass('../js/v2/panels/StatusPanel.js',    'StatusPanel');
const GameEventBus   = loadClass('../js/v2/GameEventBus.js',          'GameEventBus');

const ROOM_KEYS   = ['name', 'description', 'gallery', 'empty', 'scene-image', 'responders'];
const PARTY_KEYS  = ['rail', 'empty'];
const STATUS_KEYS = ['unavail-banner', 'backend-wait', 'zoom', 'hex-info', 'hex-legend', 'fullscreen'];

const GLOBAL_IDS = [
  'room-view-meta', 'room-view-status', 'room-view-placeholder-text', 'room-view-card-template',
  'initiative-list',
  'npc-portraits-name', 'npc-portraits-meta', 'npc-portraits-status',
  'npc-portraits-grid', 'npc-portraits-placeholder', 'npc-portraits-placeholder-text',
];

function makeContainer(prefix) {
  installDom(GLOBAL_IDS);
  const keys = prefix === 'room' ? ROOM_KEYS : prefix === 'party' ? PARTY_KEYS : STATUS_KEYS;
  return makeScopedContainer(prefix, keys);
}

function makeOccupant({ id, label, type = 'pc', portraitUrl = null, roomId = 'r1' } = {}) {
  return {
    occupant_id: id,
    content_id: id,
    room_id: roomId,
    label,
    occupant_type: type === 'pc' ? 'player_character' : type,
    presentation: { portrait_url: portraitUrl },
  };
}

/**
 * RoomViewPanel and PartyRailPanel read occupants from canonical state via
 * `stateManager.hexmap.getVisualOccupants()`; bus events only carry the room id.
 */
function makeStateManager(occupants = [], roomId = 'r1', rooms = {}) {
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

// ---------------------------------------------------------------------------
// RoomViewPanel Tests
// ---------------------------------------------------------------------------

function mountRoomView(occupants = [], rooms = {}) {
  const bus = new GameEventBus();
  const container = makeContainer('room');
  const panel = new RoomViewPanel(container, bus);
  panel.init({}, makeStateManager(occupants, 'r1', rooms));
  return { bus, container, panel };
}

console.log('\n=== RoomViewPanel — empty state on init ===');
{
  const { bus, container } = mountRoomView();
  bus.emit('room:changed', { roomId: 'r1', roomName: 'The Iron Forge' });

  assert(container._elements['empty']?.hidden === false, 'placeholder visible when no gallery entries');
  assert(container._elements['gallery']?.hidden === true, 'gallery hidden when no entries');
}

console.log('\n=== RoomViewPanel — room:changed renders room name ===');
{
  const { bus, container } = mountRoomView();
  bus.emit('room:changed', { roomId: 'r1', roomName: 'The Iron Forge' });

  assert(container._elements['name']?.textContent === 'The Iron Forge', 'room name displayed');
}

console.log('\n=== RoomViewPanel — room name falls back when absent ===');
{
  const { bus, container } = mountRoomView();
  bus.emit('room:changed', { roomId: 'r1' });

  assert(container._elements['name']?.textContent === 'Current room', 'falls back to a neutral room label');
}

console.log('\n=== RoomViewPanel — room description shown and hidden ===');
{
  const { bus, container } = mountRoomView();
  bus.emit('room:changed', { roomId: 'r1', roomName: 'Cave', room: { description: 'A damp cavern.' } });
  assert(container._elements['description']?.textContent === 'A damp cavern.', 'description rendered');
  assert(container._elements['description']?.hidden === false, 'description shown when present');

  bus.emit('room:changed', { roomId: 'r1', roomName: 'Cave' });
  assert(container._elements['description']?.hidden === true, 'description hidden when absent');
}

console.log('\n=== RoomViewPanel — gallery entries render and reveal ===');
{
  const { bus, container } = mountRoomView();
  bus.emit('room:view-loaded', {
    room: { id: 'r1', name: 'Tavern' },
    viewState: { entries: [{ image: { url: '/scenes/tavern.jpg' } }] },
  });

  assert(container._elements['gallery']?.hidden === false, 'gallery visible once entries exist');
  assert(container._elements['empty']?.hidden === true, 'placeholder hidden once entries exist');
}

console.log('\n=== RoomViewPanel — room name is set as text, never parsed as markup ===');
{
  const { bus, container } = mountRoomView();
  bus.emit('room:changed', { roomId: 'r1', roomName: '<script>bad</script>' });

  const name = container._elements['name'];
  assert(name?.textContent === '<script>bad</script>', 'room name kept as literal text');
  assert(
    (name?.children ?? []).every((child) => child.tagName === '#text'),
    'room name produced no element children (not parsed as markup)'
  );
}

console.log('\n=== RoomViewPanel — destroy unsubscribes ===');
{
  const { bus, container, panel } = mountRoomView();
  bus.emit('room:changed', { roomId: 'r1', roomName: 'The Iron Forge' });
  panel.destroy();

  bus.emit('room:changed', { roomId: 'r2', roomName: 'Ghost Room' });
  assert(
    container._elements['name']?.textContent === 'The Iron Forge',
    'destroyed panel stops responding to room changes'
  );
}

// ---------------------------------------------------------------------------
// PartyRailPanel Tests
// ---------------------------------------------------------------------------

function mountPartyRail(occupants = []) {
  const bus = new GameEventBus();
  const container = makeContainer('party');
  const panel = new PartyRailPanel(container, bus);
  panel.init({}, makeStateManager(occupants));
  return { bus, container, panel };
}

console.log('\n=== PartyRailPanel — empty when no PCs ===');
{
  const { bus, container } = mountPartyRail([makeOccupant({ id: 'n1', label: 'Grak', type: 'npc' })]);
  bus.emit('room:occupants-changed', { roomId: 'r1' });

  assert(container._elements['empty']?.hidden === false, 'empty visible when no PCs');
  assert(container._elements['rail']?.hidden === true, 'rail hidden when no PCs');
}

console.log('\n=== PartyRailPanel — renders PC tiles ===');
{
  const { bus, container } = mountPartyRail([
    makeOccupant({ id: 'pc1', label: 'Aria', type: 'pc', portraitUrl: '/p/aria.jpg' }),
    makeOccupant({ id: 'pc2', label: 'Bard', type: 'pc' }),
    makeOccupant({ id: 'n1',  label: 'Grak', type: 'npc' }),
  ]);
  bus.emit('room:occupants-changed', { roomId: 'r1' });

  const rail = container._elements['rail'];
  assert(container._elements['empty']?.hidden === true, 'empty hidden when PCs present');
  assert(rail?.innerHTML.includes('data-entity-id="pc1"'), 'first PC tile rendered');
  assert(rail?.innerHTML.includes('data-entity-id="pc2"'), 'second PC tile rendered');
  assert(!rail?.innerHTML.includes('data-entity-id="n1"'), 'NPC excluded from rail');
  assert(rail?.innerHTML.includes('/p/aria.jpg'), 'portrait url in tile');
  assert(rail?.innerHTML.includes('>B<'), 'Bard initial shown for no-portrait');
}

console.log('\n=== PartyRailPanel — re-render replaces prior cards ===');
{
  const occupants = [makeOccupant({ id: 'pc1', label: 'Aria', type: 'pc' })];
  const { bus, container } = mountPartyRail(occupants);
  bus.emit('room:occupants-changed', { roomId: 'r1' });
  assert(container._elements['rail']?.innerHTML.includes('Aria'), 'initial member rendered');

  occupants.length = 0;
  occupants.push(makeOccupant({ id: 'pc2', label: 'Bard', type: 'pc' }));
  bus.emit('room:occupants-changed', { roomId: 'r1' });

  const html = container._elements['rail']?.innerHTML ?? '';
  assert(html.includes('Bard'),  'new member rendered');
  assert(!html.includes('Aria'), 'previous member cleared');
}

console.log('\n=== PartyRailPanel — destroy unsubscribes ===');
{
  const { bus, container, panel } = mountPartyRail([
    makeOccupant({ id: 'pc1', label: 'Aria', type: 'pc' }),
  ]);
  bus.emit('room:occupants-changed', { roomId: 'r1' });
  panel.destroy();

  const before = container._elements['rail']?.innerHTML ?? '';
  bus.emit('room:occupants-changed', { roomId: 'r1' });
  assert(
    (container._elements['rail']?.innerHTML ?? '') === before,
    'destroyed panel stops re-rendering the rail'
  );
}

// ---------------------------------------------------------------------------
// StatusPanel Tests
// ---------------------------------------------------------------------------

console.log('\n=== StatusPanel — banner hidden on init ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('status');
  const panel = new StatusPanel(container, bus);
  panel.init();

  const banner = container._elements['unavail-banner'];
  assert(banner?.hidden === true, 'banner hidden on init');
}

console.log('\n=== StatusPanel — game:server-unavailable shows banner ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('status');
  const panel = new StatusPanel(container, bus);
  panel.init();

  bus.emit('game:server-unavailable');
  const banner = container._elements['unavail-banner'];
  assert(banner?.hidden === false, 'banner shown on server unavailable');
}

console.log('\n=== StatusPanel — game:server-available hides banner ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('status');
  const panel = new StatusPanel(container, bus);
  panel.init();

  bus.emit('game:server-unavailable');
  bus.emit('game:server-available');
  const banner = container._elements['unavail-banner'];
  assert(banner?.hidden === true, 'banner hidden after server available');
}

console.log('\n=== StatusPanel — hex:hovered shows coordinates ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('status');
  const panel = new StatusPanel(container, bus);
  panel.init();

  bus.emit('hex:hovered', { q: 3, r: -2 });
  const hexInfo = container._elements['hex-info'];
  assert(hexInfo?.hidden === false, 'hex-info visible on hover');
  assert(hexInfo?.textContent.includes('3'), 'q coordinate shown');
  assert(hexInfo?.textContent.includes('-2'), 'r coordinate shown');
}

console.log('\n=== StatusPanel — hex:out clears hex info ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('status');
  const panel = new StatusPanel(container, bus);
  panel.init();

  bus.emit('hex:hovered', { q: 0, r: 0 });
  bus.emit('hex:out');
  const hexInfo = container._elements['hex-info'];
  assert(hexInfo?.hidden === true, 'hex-info hidden on hex:out');
  assert(hexInfo?.textContent === '', 'hex-info cleared');
}

console.log('\n=== StatusPanel — hex:details renders terrain ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('status');
  const panel = new StatusPanel(container, bus);
  panel.init();

  bus.emit('hex:details', { q: 3, r: -2, terrain: 'forest' });
  const hexInfo = container._elements['hex-info'];
  assert(hexInfo?.hidden === false, 'hex-info visible for hex details');
  assert(hexInfo?.textContent.includes('forest'), 'terrain shown from hex:details');
}

console.log('\n=== StatusPanel — canvas:zoom-changed updates zoom display ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('status');
  const panel = new StatusPanel(container, bus);
  panel.init();

  bus.emit('canvas:zoom-changed', { scale: 1.5 });
  assert(container._elements['zoom']?.textContent === '150%', 'zoom percentage shown correctly');
}

console.log('\n=== StatusPanel — fullscreen button fires bus event ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('status');
  const panel = new StatusPanel(container, bus);
  panel.init();

  let fired = false;
  bus.on('user:fullscreen-toggle', () => { fired = true; });

  const fsBtn = container._elements['fullscreen'];
  fsBtn._listeners['click'].forEach((fn) => fn());
  assert(fired, 'user:fullscreen-toggle fired on button click');
}

console.log('\n=== StatusPanel — destroy unsubscribes ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('status');
  const panel = new StatusPanel(container, bus);
  panel.init();
  panel.destroy();

  // Should not throw or update
  bus.emit('game:server-unavailable');
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
