import assert from 'node:assert/strict';
import test from 'node:test';
import { DungeonEditorShell } from '../js/v2/editor/DungeonEditorShell.js';

function placement(id, name, origin, ports) {
  return {
    placement_id: id,
    room_id: id,
    label: 'connector',
    origin,
    rotation_steps: 0,
    is_level_entrance: false,
    resolved: true,
    room: { name, hexes: [{ q: 0, r: 0 }, { q: 1, r: 0 }], ports },
  };
}

function shell() {
  const editor = Object.create(DungeonEditorShell.prototype);
  const commands = [];
  Object.assign(editor, {
    commands,
    model: {
      placements: [
        placement('a', 'Mill', { q: 0, r: 0 }, [{ port_id: 'out', kind: 'exit', q: 0, r: 0, edge: 0 }]),
        placement('b', 'Forge', { q: 20, r: -7 }, [
          { port_id: 'west', kind: 'entry', q: 0, r: 0, edge: 3 },
          { port_id: 'east', kind: 'entry', q: 1, r: 0, edge: 0 },
        ]),
      ],
      port_links: [],
      occupancy: { '0:0': ['a'], '1:0': ['a'], '20:-7': ['b'], '21:-7': ['b'] },
    },
    _linking: null,
    _lastLinkChoice: {},
    _dom: { inspectorBody: { scrollIntoView() {}, querySelector: (selector) => ({ value: { kind: 'door', direction: 'bidirectional', default_state: 'closed' }[selector.match(/"(\w+)"/)[1]] }) } },
    container: { setAttribute() {}, removeAttribute() {} },
    bus: { emit() {} },
    _emitMap() {},
    _renderSelection() {},
    _setAuthorDrawerOpen() {},
    _setStatus(text, level) { this.status = { text, level }; },
    _selectLink() {},
    applyCommand(type, payload) {
      commands.push({ type, payload });
      return Promise.resolve({ model: { port_links: [] } });
    },
  });
  return editor;
}

test('new links default to open bidirectional travel', () => {
  const editor = new DungeonEditorShell({}, {});
  assert.deepEqual(editor._lastLinkChoice, {
    direction: 'bidirectional',
    default_state: 'open',
  });
});

test('room editor links carry their dungeon parent', () => {
  const editor = Object.create(DungeonEditorShell.prototype);
  editor.urls = { roomEditor: '/room-editor/{room_id}' };
  editor.model = { dungeon_id: 'sunken-cistern' };
  editor.settings = {};
  assert.equal(
    editor._roomEditorHref('flooded-room'),
    '/room-editor/flooded-room?dungeon_id=sunken-cistern',
  );
  editor.settings.selectedStorylineDraftId = 'storyline-123';
  assert.equal(
    editor._roomEditorHref('flooded-room'),
    '/room-editor/flooded-room?dungeon_id=sunken-cistern&storyline_draft_id=storyline-123',
  );
});

test('smaller room may be placed fully inside a larger room', () => {
  const editor = Object.create(DungeonEditorShell.prototype);
  editor.model = {
    placements: [{
      placement_id: 'outer',
      resolved: true,
      origin: { q: 0, r: 0 },
      rotation_steps: 0,
      room: { hexes: [{ q: 0, r: 0 }, { q: 1, r: 0 }, { q: 0, r: 1 }] },
    }],
    occupancy: {
      '0:0': ['outer'],
      '1:0': ['outer'],
      '0:1': ['outer'],
    },
  };
  const nested = editor._ghostFor({
    kind: 'place',
    footprint: [{ q: 0, r: 0 }, { q: 1, r: 0 }],
    rotation: 0,
  }, { q: 0, r: 0 });
  const partial = editor._ghostFor({
    kind: 'place',
    footprint: [{ q: 1, r: 0 }, { q: 2, r: 0 }],
    rotation: 0,
  }, { q: 0, r: 0 });
  assert.equal(nested.valid, true);
  assert.equal(partial.valid, false);
});

test('viewport buttons use shared canvas zoom, pan and fit actions', () => {
  const editor = Object.create(DungeonEditorShell.prototype);
  const calls = [];
  editor.hexCanvas = {
    zoomBy(value) { calls.push(['zoom', value]); },
    panBy(x, y) { calls.push(['pan', x, y]); },
  };
  editor._fitMapToView = () => calls.push(['fit']);

  editor._runViewportAction('zoom-out');
  editor._runViewportAction('pan-down');
  editor._runViewportAction('recenter');

  assert.deepEqual(calls, [
    ['zoom', 1 / 1.2],
    ['pan', 0, 72],
    ['fit'],
  ]);
});

test('dungeon loading state reports failures clearly', () => {
  const editor = Object.create(DungeonEditorShell.prototype);
  const state = { dataset: {} };
  const label = { textContent: '' };
  editor.container = {
    querySelector(selector) {
      if (selector.includes('load-label')) return label;
      if (selector.includes('load-state')) return state;
      return null;
    },
  };
  editor._setLoadState('Dungeon load failed.', 'error');
  assert.equal(state.dataset.loadState, 'error');
  assert.equal(label.textContent, 'Dungeon load failed.');
});

test('rooms that are far apart can be connected from either end', () => {
  const editor = shell();
  editor._handlePortClick({ placementId: 'b', portId: 'west', button: 0 });
  assert.deepEqual([...editor._linking.legal], ['a:out']);
  editor._handlePortClick({ placementId: 'a', portId: 'out', button: 0 });
  editor._runLinkAction('link-create');
  assert.deepEqual(editor.commands, [{
    type: 'link_ports',
    payload: {
      from: { placement_id: 'a', port_id: 'out' },
      to: { placement_id: 'b', port_id: 'west' },
      kind: 'door',
      direction: 'bidirectional',
      default_state: 'closed',
    },
  }]);
});

test('dropping on a room picks its nearest free port; dropping elsewhere does not', () => {
  const editor = shell();
  editor._handlePortClick({ placementId: 'a', portId: 'out', button: 0 });
  assert.equal(editor._linkToHex(1, 0), false, 'own room is not a target');
  assert.equal(editor._linkToHex(50, 50), false, 'empty space is not a target');
  assert.equal(editor._linking.to, null);
  assert.equal(editor._linkToHex(21, -7), true);
  assert.equal(editor._linking.to.key, 'b:east');
});

test('an exit that already has a link is not offered again', () => {
  const editor = shell();
  editor.model.port_links = [{ link_id: 'l1', from: { placement_id: 'a', port_id: 'out' }, to: { placement_id: 'b', port_id: 'west' } }];
  editor._handlePortClick({ placementId: 'b', portId: 'east', button: 0 });
  assert.equal(editor._linking.legal.size, 0);
  assert.equal(editor.status.level, 'warning');
});

test('a click selects a room and never moves it, even if the map shifts under the pointer', () => {
  globalThis.window = { removeEventListener() {} };
  const editor = shell();
  Object.assign(editor, {
    hexCanvas: null,
    _selectPlacement(id) { this.selected = id; },
    _pointerToAxial: () => ({ q: 5, r: 5 }),
    _drag: { kind: 'move', placementId: 'a', origin: { q: 0, r: 0 }, rotation: 0, anchor: { q: 0, r: 0 }, startClient: { x: 100, y: 100 } },
  });
  editor._onDragEnd({ type: 'pointerup', clientX: 101, clientY: 102 });
  assert.equal(editor.selected, 'a');
  assert.deepEqual(editor.commands, []);
});
