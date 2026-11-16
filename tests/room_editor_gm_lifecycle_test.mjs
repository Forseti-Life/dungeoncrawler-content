import assert from 'node:assert/strict';
import test from 'node:test';
import { RoomEditorShell } from '../js/v2/editor/RoomEditorShell.js';

function shell() {
  globalThis.window = {
    location: { href: 'https://example.test/tools/room-editor?keep=1', search: '?keep=1' },
    history: { replaceState(_state, _title, url) { window.location.href = String(url); } },
  };
  const editor = Object.create(RoomEditorShell.prototype);
  Object.assign(editor, {
    draft: null,
    selection: null,
    _dom: {},
    _gm: { context: null, manifest: null, plan: null },
    _history: [],
    _redoStack: [],
    _clipboard: null,
    _catalogEntryCache: new Map(),
    urls: { gm: '/drafts/{draft_id}/gm', gmBootstrap: '/api/room-editor/gm' },
    bus: { emit() {} },
    _renderInspector() {},
    _fitRoomToView() {},
    _refreshGmContext() {},
    _renderGmContext() {},
    _renderGmToolset() {},
    _setStatus() {},
    _setGmState(text) { this.gmState = text; },
    _appendGmMessage(kind, text) { this.message = { kind, text }; },
    async _runExclusive(callback) { return callback(); },
  });
  return editor;
}

test('draft-free requests use bootstrap URL and omit draft_id', async () => {
  const editor = shell();
  editor._postJson = async (url, body) => {
    assert.equal(url, '/api/room-editor/gm');
    assert.equal(Object.hasOwn(body.tool_context, 'draft_id'), false);
    assert.equal(body.options.dry_run, true);
    return { data: { tool_result: {}, context_snapshot: { draft: null } } };
  };
  await editor._sendGmRequest({ type: 'tool_call', tool_name: 'create_room_draft', arguments: { name: 'Copper Hall' } }, true, 'create');
  assert.equal(editor.gmState, 'Ready to create or load a room draft.');
});

test('switching drafts clears stale editing state and persists the draft URL', () => {
  const editor = shell();
  editor.draft = { draft_id: 'old', revision: 3 };
  editor._history = ['command-old'];
  editor._redoStack = ['undo-old'];
  editor._clipboard = { instanceId: 'old-placement' };
  editor._lastHoverAxial = { q: 4, r: 2 };
  editor.selection = { type: 'placement', instanceId: 'old-placement' };
  editor._gm.plan = [{ command_type: 'remove_object' }];
  editor._setDraft({ draft_id: 'new', revision: 0, room: { room_id: 'new-room' } });
  assert.deepEqual(editor._history, []);
  assert.deepEqual(editor._redoStack, []);
  assert.equal(editor._clipboard, null);
  assert.equal(editor._lastHoverAxial, null);
  assert.equal(editor.selection, null);
  assert.equal(editor._gm.plan, null);
  const url = new URL(window.location.href);
  assert.equal(url.searchParams.get('draft_id'), 'new');
  assert.equal(url.searchParams.get('keep'), '1');
});

test('abandonment clears the editor despite returning a retained audit draft', async () => {
  const editor = shell();
  editor.draft = { draft_id: 'old', revision: 3, room: {} };
  editor._postJson = async () => ({ data: {
    tool_result: { active_draft_id: null, draft: { draft_id: 'old', status: 'abandoned', room: {} } },
    context_snapshot: { draft: null },
  } });
  await editor._sendGmRequest({ type: 'tool_call', tool_name: 'abandon_room_draft', arguments: { expected_revision: 3 } }, false, 'abandon');
  assert.equal(editor.draft, null);
  assert.equal(new URL(window.location.href).searchParams.has('draft_id'), false);
});

test('bootstrap context refresh works without an active draft', async () => {
  const editor = shell();
  editor._getJson = async (url) => {
    assert.equal(url, '/api/room-editor/gm?profile=editing');
    return { data: { context_snapshot: { draft: null, tools: ['create_room_draft'] } } };
  };
  await RoomEditorShell.prototype._refreshGmContext.call(editor);
  assert.equal(editor._gm.context.draft, null);
  assert.equal(editor.gmState, 'Ready to create or load a room draft.');
});

test('an old context response cannot overwrite a draft switch', async () => {
  const editor = shell();
  editor.draft = { draft_id: 'old', revision: 0 };
  editor._getJson = async () => {
    editor.draft = { draft_id: 'new', revision: 0 };
    editor._gm.context = { draft: { draft_id: 'new' } };
    return { data: { context_snapshot: { draft: { draft_id: 'old' } } } };
  };
  await RoomEditorShell.prototype._refreshGmContext.call(editor);
  assert.equal(editor._gm.context.draft.draft_id, 'new');
});

test('same-draft updates preserve native undo state', () => {
  const editor = shell();
  editor.draft = { draft_id: 'same', revision: 0 };
  editor._history = ['command'];
  editor._clipboard = { instanceId: 'placement' };
  editor._setDraft({ draft_id: 'same', revision: 1, room: {} });
  assert.deepEqual(editor._history, ['command']);
  assert.deepEqual(editor._clipboard, { instanceId: 'placement' });
});

test('an approval rendered for an old draft cannot act on the new draft', () => {
  const editor = shell();
  const buttons = [];
  globalThis.document = {
    createElement(tag) {
      const node = {
        children: [],
        append(...children) { this.children.push(...children); },
        appendChild(child) { this.children.push(child); },
        addEventListener(event, callback) { this[event] = callback; },
      };
      if (tag === 'button') buttons.push(node);
      return node;
    },
  };
  editor._dom.gmTranscript = { appendChild() {} };
  editor.draft = { draft_id: 'old' };
  let invoked = false;
  editor._invokeGmTool = () => { invoked = true; };
  editor._renderGmProposal({
    intent: 'proposed_execution',
    proposed_execution: { tool_name: 'abandon_room_draft', authority: 'RoomEditorService', arguments: { expected_revision: 1 } },
  });
  editor.draft = { draft_id: 'new' };
  buttons[0].click();
  assert.equal(invoked, false);
  assert.equal(editor.message.kind, 'error');
});
