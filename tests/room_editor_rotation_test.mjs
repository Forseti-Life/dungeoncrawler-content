import assert from 'node:assert/strict';
import test from 'node:test';
import { RoomEditorShell } from '../js/v2/editor/RoomEditorShell.js';

function shell(selection) {
  const editor = Object.create(RoomEditorShell.prototype);
  const commands = [];
  Object.assign(editor, {
    commands,
    selection,
    draft: { room: {
      placements: [{ instance_id: 'p1', facing: 5, definition_ref: { family: 'actor', definition_id: 'assassin_vine' } }],
      entry_ports: [{ port_id: 'in', edge: 0, hex: { q: 0, r: 0 } }],
      exit_ports: [],
    } },
    _sendCommand(type, payload) { commands.push({ type, payload }); },
  });
  return editor;
}

test('turning a placed object wraps around the six facings in both directions', () => {
  const editor = shell({ type: 'placement', instanceId: 'p1' });
  editor._rotateSelection(1);
  editor._rotateSelection(-1);
  assert.deepEqual(editor.commands, [
    { type: 'rotate_object', payload: { instance_id: 'p1', facing: 0 } },
    { type: 'rotate_object', payload: { instance_id: 'p1', facing: 4 } },
  ]);
});

test('turning a port moves it to the neighbouring side of its hex', () => {
  const editor = shell({ type: 'port', family: 'entry', portId: 'in' });
  editor._rotateSelection(-1);
  assert.deepEqual(editor.commands, [{ type: 'update_entry_port', payload: { port_id: 'in', changes: { edge: 5 } } }]);
});

test('nothing selected turns nothing', () => {
  const editor = shell(null);
  editor._rotateSelection(1);
  assert.deepEqual(editor.commands, []);
});

test('viewport buttons pan, zoom and recenter through the shared canvas', () => {
  const editor = shell(null);
  const calls = [];
  editor.hexCanvas = {
    zoomBy(value) { calls.push(['zoom', value]); },
    panBy(x, y) { calls.push(['pan', x, y]); },
  };
  editor._fitRoomToView = () => calls.push(['fit']);

  editor._runViewportAction('zoom-in');
  editor._runViewportAction('pan-left');
  editor._runViewportAction('recenter');

  assert.deepEqual(calls, [
    ['zoom', 1.2],
    ['pan', -72, 0],
    ['fit'],
  ]);
});

test('room canvas allows district-scale fit zoom', () => {
  const source = String(RoomEditorShell.toString());
  assert.match(source, /minZoom:\s*0\.01/);
});

test('room loading state reports loading and ready states', () => {
  const editor = shell(null);
  const state = { dataset: {} };
  const label = { textContent: '' };
  editor.container = {
    querySelector(selector) {
      if (selector.includes('load-label')) return label;
      if (selector.includes('load-state')) return state;
      return null;
    },
  };
  editor._setLoadState('Loading room…', 'loading');
  assert.equal(state.dataset.loadState, 'loading');
  assert.equal(label.textContent, 'Loading room…');
  editor._setLoadState('Room ready.', 'ready');
  assert.equal(state.dataset.loadState, 'ready');
});
