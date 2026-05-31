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

function loadClass(relPath, className) {
  let src = fs.readFileSync(path.resolve(__dirname, relPath), 'utf8');
  src = src.replace(/^export\s+/gm, '');
  return new Function(src + `\nreturn { ${className} };`)()[className];
}

const RoomViewPanel  = loadClass('../js/v2/panels/RoomViewPanel.js',  'RoomViewPanel');
const PartyRailPanel = loadClass('../js/v2/panels/PartyRailPanel.js', 'PartyRailPanel');
const StatusPanel    = loadClass('../js/v2/panels/StatusPanel.js',    'StatusPanel');
const GameEventBus   = loadClass('../js/v2/GameEventBus.js',          'GameEventBus');

// ---------------------------------------------------------------------------
// DOM helpers
// ---------------------------------------------------------------------------

function makeEl(key) {
  return {
    _key: key, textContent: '', innerHTML: '', src: '', alt: '', href: '',
    hidden: false, dataset: {}, _listeners: {},
    addEventListener(e, f) { (this._listeners[e] = this._listeners[e] || []).push(f); },
    querySelectorAll(sel) {
      if (sel.includes('data-entity-id')) return this._tiles || [];
      return [];
    },
    querySelector()    { return null; },
    closest()          { return null; },
    classList: {
      _classes: new Set(),
      toggle(cls, force) { force ? this._classes.add(cls) : this._classes.delete(cls); },
      add(cls)    { this._classes.add(cls); },
      remove(cls) { this._classes.delete(cls); },
      contains(cls) { return this._classes.has(cls); },
    },
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

function makeOccupant({ id, label, type = 'pc', portraitUrl = null } = {}) {
  return { occupant_id: id, label, occupant_type: type, presentation: { portrait_url: portraitUrl } };
}

// ---------------------------------------------------------------------------
// RoomViewPanel Tests
// ---------------------------------------------------------------------------

console.log('\n=== RoomViewPanel — empty state on init ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('room');
  const panel = new RoomViewPanel(container, bus);
  panel.init();

  const empty = container._elements['empty'];
  assert(empty?.hidden === false, 'empty visible on init');
}

console.log('\n=== RoomViewPanel — room:changed renders room name ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('room');
  const panel = new RoomViewPanel(container, bus);
  panel.init();

  bus.emit('room:changed', { roomId: 'r1', roomName: 'The Iron Forge', sceneImageUrl: null, responders: [] });

  const name  = container._elements['name'];
  const empty = container._elements['empty'];
  assert(name?.textContent === 'The Iron Forge', 'room name displayed');
  assert(empty?.hidden === true, 'empty hidden after room loaded');
}

console.log('\n=== RoomViewPanel — room:changed sets scene image ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('room');
  const panel = new RoomViewPanel(container, bus);
  panel.init();

  bus.emit('room:changed', {
    roomId: 'r1', roomName: 'Cave', sceneImageUrl: '/scenes/cave.jpg', responders: [],
  });

  const img = container._elements['scene-image'];
  assert(img?.src === '/scenes/cave.jpg', 'scene image src set');
  assert(img?.hidden === false, 'scene image visible');
}

console.log('\n=== RoomViewPanel — no scene image hides img element ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('room');
  const panel = new RoomViewPanel(container, bus);
  panel.init();

  bus.emit('room:changed', { roomId: 'r1', roomName: 'Cave', responders: [] });

  const img = container._elements['scene-image'];
  assert(img?.hidden === true, 'scene image hidden when no URL');
}

console.log('\n=== RoomViewPanel — responders rendered ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('room');
  const panel = new RoomViewPanel(container, bus);
  panel.init();

  bus.emit('room:changed', {
    roomId: 'r1', roomName: 'Tavern', responders: [
      { npc_id: 'n1', name: 'Grak', portrait_url: '/p/grak.jpg' },
      { npc_id: 'n2', name: 'Mira' },
    ],
  });

  const respEl = container._elements['responders'];
  assert(respEl?.innerHTML.includes('Grak'), 'first responder rendered');
  assert(respEl?.innerHTML.includes('Mira'), 'second responder rendered');
  assert(respEl?.innerHTML.includes('/p/grak.jpg'), 'portrait url in img');
  // Mira has no portrait → show initial
  assert(respEl?.innerHTML.includes('>M<'), 'initial shown for no-portrait responder');
}

console.log('\n=== RoomViewPanel — HTML escaped ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('room');
  const panel = new RoomViewPanel(container, bus);
  panel.init();

  bus.emit('room:changed', {
    roomId: 'r1', roomName: '<script>bad</script>', responders: [],
  });

  const name = container._elements['name'];
  assert(name?.textContent === '<script>bad</script>', 'room name raw in textContent (safe)');
}

console.log('\n=== RoomViewPanel — destroy unsubscribes ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('room');
  const panel = new RoomViewPanel(container, bus);
  panel.init();
  panel.destroy();

  bus.emit('room:changed', { roomId: 'r2', roomName: 'Ghost Room', responders: [] });
  assert(panel._currentRoomId === null, 'room id cleared on destroy');
}

// ---------------------------------------------------------------------------
// PartyRailPanel Tests
// ---------------------------------------------------------------------------

console.log('\n=== PartyRailPanel — empty when no PCs ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('party');
  const panel = new PartyRailPanel(container, bus);
  panel.init();

  bus.emit('room:occupants-changed', {
    occupants: [makeOccupant({ id: 'n1', label: 'Grak', type: 'npc' })],
  });

  const empty = container._elements['empty'];
  assert(empty?.hidden === false, 'empty visible when no PCs');
}

console.log('\n=== PartyRailPanel — renders PC tiles ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('party');
  const panel = new PartyRailPanel(container, bus);
  panel.init();

  bus.emit('room:occupants-changed', {
    occupants: [
      makeOccupant({ id: 'pc1', label: 'Aria', type: 'pc', portraitUrl: '/p/aria.jpg' }),
      makeOccupant({ id: 'pc2', label: 'Bard', type: 'pc' }),
      makeOccupant({ id: 'n1',  label: 'Grak', type: 'npc' }),
    ],
  });

  const rail  = container._elements['rail'];
  const empty = container._elements['empty'];
  assert(empty?.hidden === true, 'empty hidden when PCs present');
  assert(rail?.innerHTML.includes('data-entity-id="pc1"'), 'first PC tile rendered');
  assert(rail?.innerHTML.includes('data-entity-id="pc2"'), 'second PC tile rendered');
  assert(!rail?.innerHTML.includes('data-entity-id="n1"'), 'NPC excluded from rail');
  assert(rail?.innerHTML.includes('/p/aria.jpg'), 'portrait url in tile');
  assert(rail?.innerHTML.includes('>B<'), 'Bard initial shown for no-portrait');
}

console.log('\n=== PartyRailPanel — entity:selected highlights tile ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('party');
  const panel = new PartyRailPanel(container, bus);
  panel.init();

  bus.emit('room:occupants-changed', {
    occupants: [makeOccupant({ id: 'pc1', label: 'Aria', type: 'pc' })],
  });

  bus.emit('entity:selected', { entityId: 'pc1' });

  assert(panel._selectedId === 'pc1', 'selected id tracked');
}

console.log('\n=== PartyRailPanel — destroy unsubscribes ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('party');
  const panel = new PartyRailPanel(container, bus);
  panel.init();
  panel.destroy();

  bus.emit('room:occupants-changed', { occupants: [makeOccupant({ id: 'pc1', label: 'Ghost', type: 'pc' })] });
  assert(panel._members.length === 0, 'members cleared on destroy');
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

  bus.emit('hex:hovered', { q: 3, r: -2, terrain: 'forest' });
  const hexInfo = container._elements['hex-info'];
  assert(hexInfo?.hidden === false, 'hex-info visible on hover');
  assert(hexInfo?.textContent.includes('3'), 'q coordinate shown');
  assert(hexInfo?.textContent.includes('-2'), 'r coordinate shown');
  assert(hexInfo?.textContent.includes('forest'), 'terrain shown');
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

console.log('\n=== StatusPanel — canvas:zoom-changed updates zoom display ===');
{
  const bus = new GameEventBus();
  const container = makeContainer('status');
  const panel = new StatusPanel(container, bus);
  panel.init();

  bus.emit('canvas:zoom-changed', { scale: 1.5 });
  const zoom = container._elements['zoom'];
  assert(zoom?.textContent === '150%', 'zoom percentage shown correctly');
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
