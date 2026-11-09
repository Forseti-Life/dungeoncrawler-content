/**
 * @file
 * Behavioral tests for the Tab Runtime Consistency corrections.
 *
 * Run with:
 *   node tests/tab_runtime_consistency_behavioral_test.js
 *
 * Unlike the sibling contract test, these exercise the *actual runtime
 * behavior* of the corrected classes (imported as real ES modules) rather than
 * asserting on source strings. Covers:
 *   - event-stream health is isolated from authoritative snapshot health
 *   - idempotent event ingestion dedupe under overlapping action/poll delivery
 *   - bounded bootstrap history hydration populating the event buffer
 *   - interleaved state failure + successful event poll preserving desync
 *   - ChatPanel refreshes system-log from the accepted batch cursor with an
 *     independently-recorded room/system zero-gap assertion
 *   - the shared shell gate blocks representative direct gameplay mutations
 */

// Minimal browser globals used by the imported runtime modules at call time.
globalThis.window = globalThis.window || {
  addEventListener() {},
  removeEventListener() {},
  dispatchEvent() {},
};
globalThis.document = globalThis.document || {
  getElementById() { return null; },
  querySelector() { return null; },
  createElement() {
    return { classList: { toggle() {}, add() {} }, dataset: {}, style: {}, setAttribute() {}, querySelectorAll() { return []; } };
  },
};
globalThis.CustomEvent = globalThis.CustomEvent || class CustomEvent {
  constructor(type, init) { this.type = type; this.detail = init && init.detail; }
};

let passed = 0;
let failed = 0;
function assert(condition, msg) {
  if (condition) { passed++; console.log(`  \u2713 ${msg}`); }
  else { failed++; console.error(`  \u2717 ${msg}`); }
}

function baseResponse(overrides = {}) {
  return Object.assign({
    success: true,
    snapshot_id: 'rtsnap_beh',
    event_cursor: 42,
    state_version: 7,
    game_state: { phase: 'encounter', state_version: 7, event_log_cursor: 42, encounter_id: 99005, active_room_id: 'crypt' },
  }, overrides);
}

(async () => {
  const { RuntimeStateStore } = await import('../js/game-coordinator/RuntimeStateStore.js');
  const { GameCoordinator } = await import('../js/game-coordinator/GameCoordinator.js');
  const { ChatPanel } = await import('../js/v2/panels/ChatPanel.js');
  const { GameShell } = await import('../js/v2/GameShell.js');
  const { EncounterSystem } = await import('../js/v2/systems/EncounterSystem.js');

  console.log('=== Finding 1: event-stream health isolated from authoritative snapshot health ===');
  (() => {
    const store = new RuntimeStateStore();
    store.noteSyncFailure({ code: 'state_read_failed' });
    assert(store.getSyncHealth() === 'degraded', 'authoritative read failure degrades snapshot health');
    store.noteEventStreamSuccess({ code: 'event_poll_ok' });
    assert(store.getSyncHealth() === 'degraded', 'successful event poll does NOT recover authoritative health');
    assert(store.getEventStreamHealth() === 'healthy', 'successful event poll recovers only the event-stream signal');
    store.noteSyncFailure();
    store.noteSyncFailure();
    assert(store.getSyncHealth() === 'read_only_desynced', 'three authoritative failures escalate to read_only_desynced');
    store.noteEventStreamSuccess();
    assert(store.getSyncHealth() === 'read_only_desynced', 'event poll success cannot recover from authoritative desync');
    store.commitFromResponse(baseResponse());
    assert(store.getSyncHealth() === 'healthy', 'only a valid canonical snapshot commit recovers authoritative health');
  })();

  (() => {
    const store = new RuntimeStateStore();
    store.noteEventStreamFailure({ code: 'event_poll_failed' });
    assert(store.getSyncHealth() === 'healthy', 'event-stream failure does not drive the authoritative desync gate');
    assert(store.getEventStreamHealth() === 'degraded', 'event-stream failure remains observable');
  })();

  console.log('=== Finding 1: interleaved state failure + event poll preserves desync progression ===');
  (() => {
    const store = new RuntimeStateStore();
    store.noteSyncFailure();            // 1 -> degraded
    store.noteEventStreamSuccess();
    store.noteSyncFailure();            // 2 -> degraded
    store.noteEventStreamSuccess();
    store.noteSyncFailure();            // 3 -> read_only_desynced
    assert(store.getSyncHealth() === 'read_only_desynced', 'interleaved successful event polls never reset authoritative failure count');
  })();

  console.log('=== Finding 3: idempotent event ingestion dedupe ===');
  (() => {
    const emitted = [];
    const hexmap = { bus: { emit: (n, p) => emitted.push({ n, p }) } };
    const coord = new GameCoordinator(849, hexmap);
    let narrated = 0;
    coord._showNarrations = (events) => { narrated += events.length; };
    coord._logEncounterConsoleEvents = () => {};
    globalThis.window.dispatchEvent = () => {};

    coord._processNewEvents([{ id: 1, type: 'a' }, { id: 2, type: 'b' }]);
    const secondAccepted = coord._processNewEvents([{ id: 2, type: 'b' }, { id: 3, type: 'c' }]);

    assert(coord.eventLog.map((e) => e.id).join(',') === '1,2,3', 'overlapping delivery never double-appends');
    assert(secondAccepted.length === 1 && secondAccepted[0].id === 3, 'ingestion returns only newly accepted events');
    assert(coord.eventCursor === 3, 'cursor advances only from accepted events');
    assert(coord.getRuntimeTelemetry().duplicate_event_suppressed === 1, 'duplicate suppression is counted');
    assert(narrated === 3, 'narration/audio fires once per accepted event (no double-play for id 2)');
    const batches = emitted.filter((e) => e.n === 'runtime:events-batch');
    assert(batches.length === 2, 'one canonical batch per accepted ingestion');
    assert(batches[1].p.events.length === 1 && batches[1].p.events[0].id === 3, 'second batch carries only the new event');
  })();

  console.log('=== Finding 2: bounded bootstrap history hydration ===');
  await (async () => {
    const emitted = [];
    const hexmap = { bus: { emit: (n, p) => emitted.push({ n, p }) } };
    const coord = new GameCoordinator(849, hexmap);
    coord.eventCursor = 50;
    coord.eventLog = [];
    coord._showNarrations = () => {};
    coord._logEncounterConsoleEvents = () => {};
    let sinceUsed = null;
    coord.api = { getEventsSince: async (since) => { sinceUsed = since; return { events: [{ id: 48 }, { id: 49 }, { id: 50 }] }; } };

    await coord._hydrateInitialEventHistory();

    assert(sinceUsed === 0, 'bounded history read starts at max(0, cursor - window)');
    assert(coord.eventLog.length === 3, 'event buffer hydrated before polling advances the cursor');
    assert(coord.getRecentEvents(200).length === 3, 'hydrated transcript is readable by ChatPanel projection');
    const hydrationBatches = emitted.filter((e) => e.n === 'runtime:events-batch' && e.p.hydration);
    assert(hydrationBatches.length === 1, 'hydration emits one canonical batch so the transcript renders after reload');

    // Idempotent: a second hydration attempt is a no-op because the buffer is populated.
    sinceUsed = 'unused';
    await coord._hydrateInitialEventHistory();
    assert(sinceUsed === 'unused', 'hydration is skipped once the buffer is populated');
  })();

  console.log('=== Finding 4: system-log refresh + independent zero-gap assertion ===');
  (() => {
    const chat = Object.create(ChatPanel.prototype);
    chat._roomProjectedEventCursor = 0;
    chat._systemLogProjectedEventCursor = 0;
    chat.activeSessionView = 'system-log';
    const invalidated = [];
    const reloaded = [];
    chat.invalidateChatCaches = (opts) => invalidated.push(opts);
    chat.loadSessionViewMessages = (v, o) => { reloaded.push({ v, o }); return Promise.resolve(); };
    chat.getChatContext = () => ({ campaignId: 849, roomId: 'crypt' });
    // Simulate the room projection recording its own cursor (max rendered id).
    chat.handleGameEvents = (evt) => {
      const ids = (evt.detail.events || []).map((e) => Number(e.id || 0));
      const max = ids.length ? Math.max(...ids) : 0;
      if (max > chat._roomProjectedEventCursor) chat._roomProjectedEventCursor = max;
    };
    const asserted = [];
    chat.stateManager = { hexmap: { gameCoordinator: { assertUnifiedEventCursor: (r, sl) => { asserted.push({ r, sl }); return Math.abs(r - sl); } } } };

    chat.handleCoordinatorEventBatch({ events: [{ id: 10 }, { id: 11 }], cursor: 11 });
    assert(invalidated.length === 1 && invalidated[0].sessionViews.join(',') === 'system-log', 'accepted batch invalidates the system-log projection');
    assert(reloaded.length === 1 && reloaded[0].v === 'system-log', 'system-log refreshes from the accepted cursor');
    assert(chat._systemLogProjectedEventCursor === 11, 'system-log projection cursor recorded independently');
    assert(chat._roomProjectedEventCursor === 11, 'room projection advanced to the accepted cursor');
    const converged = asserted[asserted.length - 1];
    assert(converged.r === 11 && converged.sl === 11, 'zero-gap assertion uses independently-recorded cursors (converged)');

    // Mismatch: room projection lags (renders nothing) but system-log advances.
    chat.handleGameEvents = () => {};
    chat.handleCoordinatorEventBatch({ events: [{ id: 12 }], cursor: 12 });
    const gapCase = asserted[asserted.length - 1];
    assert(gapCase.r === 11 && gapCase.sl === 12, 'a real projection drift produces a non-zero gap (not the same value passed twice)');
  })();

  console.log('=== Finding 5: shared shell gate blocks representative direct mutations ===');
  (() => {
    const shell = Object.create(GameShell.prototype);
    let health = 'read_only_desynced';
    shell._getStateValue = (k) => (k === 'runtimeSyncHealth' ? health : null);
    const busEmits = [];
    shell.bus = { emit: (n, p) => busEmits.push({ n, p }) };

    assert(shell.isGameplayMutationBlockedBySync() === true, 'shell reports mutation blocked when read_only_desynced');
    assert(shell.guardGameplayMutation('this action') === true, 'shared gate blocks the mutation');
    assert(busEmits.some((e) => e.n === 'chat:system-message' && /blocked/i.test(e.p.text)), 'blocked mutation shows a visible explanation');
    health = 'healthy';
    assert(shell.guardGameplayMutation('this action') === false, 'shared gate allows mutation when healthy');
  })();

  await (async () => {
    const enc = Object.create(EncounterSystem.prototype);
    enc.shell = { guardGameplayMutation: () => true };
    let sent = false;
    const coordinator = { api: { sendAction: async () => { sent = true; return { success: true }; } } };
    const result = await enc._sendCoordinatorActionWithResync(coordinator, 'strike', 'pc-1', {});
    assert(result && result.blockedBySync === true, 'EncounterSystem direct mutation is blocked by the shared gate');
    assert(sent === false, 'no authoritative request is issued while blocked');
  })();

  console.log('=== Visible synchronization and retry lifecycle ===');
  (() => {
    const banner = { hidden: true, textContent: '', dataset: {} };
    const root = { querySelector: () => banner };
    const shell = Object.create(GameShell.prototype);
    shell.container = { querySelector: () => null, closest: () => root };
    shell._renderRuntimeSyncBanner('resyncing');
    assert(!banner.hidden && /Waiting on server/.test(banner.textContent),
      'nested shell containers show a visible informational resync banner');
    shell._renderRuntimeSyncBanner('healthy');
    assert(banner.hidden && banner.textContent === '', 'committed healthy state clears the shell notice');
  })();

  (() => {
    const events = [];
    const coord = new GameCoordinator(849, { bus: { emit: (name, data) => events.push({ name, data }) } });
    coord._wirePhaseEvents();
    coord.runtimeStateStore.noteEventStreamFailure({ code: 'event_poll_failed' });
    assert(events.some(e => e.name === 'runtime:event-stream-health-changed' && e.data.eventStreamHealth === 'degraded'),
      'failed actor/event polls publish visible health instead of only logging');
    coord.runtimeStateStore.noteEventStreamSuccess();
    assert(events.some(e => e.name === 'runtime:event-stream-health-changed' && e.data.eventStreamHealth === 'healthy'),
      'event-stream recovery publishes its own completion');
    assert(coord.runtimeStateStore.getSyncHealth() === 'healthy', 'stream feedback does not alter authoritative health');
  })();

  await (async () => {
    const events = [];
    let resolveState;
    const shell = Object.create(GameShell.prototype);
    shell.bus = { emit: (name, data) => events.push({ name, data }) };
    shell.resolveLaunchCharacterRuntimeContext = () => ({ instanceId: 'pc-1', characterId: 1 });
    shell.gameCoordinator = {
      api: { getState: () => new Promise(resolve => { resolveState = resolve; }) },
      applyAuthoritativeUpdate() {},
    };
    const pending = shell.syncCoordinatorStateFromServer('crypt');
    const key = shell._coordinatorStateSyncInFlightKey;
    assert(events.some(e => e.name === 'game:backend-request-start' && /synchronize/.test(e.data.label)),
      'room/actor reconciliation reports a server wait before the request completes');
    assert(await shell.syncCoordinatorStateFromServer('crypt') === false
      && shell._coordinatorStateSyncInFlightKey === key, 'a duplicate sync cannot release the active request lock');
    resolveState({ success: true, active_room_id: 'crypt', action_contract: { actor_id: 'pc-1' } });
    assert(await pending === true, 'the authoritative reconciliation still applies normally');
    assert(events.filter(e => e.name === 'game:backend-request-end').length === 1,
      'only the owning request clears its waiting status');
    const older = shell.syncCoordinatorStateFromServer('lab');
    const finishOlder = resolveState;
    const newer = shell.syncCoordinatorStateFromServer('hall');
    const finishNewer = resolveState;
    const newerKey = shell._coordinatorStateSyncInFlightKey;
    finishOlder({ success: true, active_room_id: 'lab' });
    await older;
    assert(shell._coordinatorStateSyncInFlightKey === newerKey,
      'an older response cannot release the newer reconciliation request lock');
    finishNewer({ success: true, active_room_id: 'crypt' });
    assert(await newer === false && events.some(e => e.name === 'chat:system-message' && /older room state/.test(e.data.text)),
      'a discarded stale response explains the retained view instead of silently appearing unresponsive');
  })();

  await (async () => {
    const events = [];
    let finishRetry;
    let calls = 0;
    const enc = Object.create(EncounterSystem.prototype);
    enc._coordinatorActionLocks = new Map();
    enc.bus = { emit: (name, data) => events.push({ name, data }) };
    enc.shell = { guardGameplayMutation: () => false };
    enc.announceGameState = () => {};
    enc._refreshSystemLogView = () => {};
    const coordinator = {
      campaignId: 849,
      phaseManager: { stateVersion: 7 },
      runtimeStateStore: { setSyncHealth() {} },
      applyAuthoritativeUpdate() {},
      api: { sendAction: async () => {
        calls++;
        if (calls === 1) {
          throw Object.assign(new Error('state version mismatch'), {
            status: 422, payload: { error: 'state version mismatch', game_state: {} },
          });
        }
        return new Promise(resolve => { finishRetry = resolve; });
      } },
    };
    const pending = enc._sendCoordinatorActionWithResync(coordinator, 'strike', 'pc-1', {});
    await Promise.resolve();
    await Promise.resolve();
    assert(events.some(e => e.name === 'game:backend-request-start' && /retry your action/.test(e.data.label)),
      'state-version recovery explains the automatic retry while it is in flight');
    finishRetry({ success: true });
    assert((await pending).success && calls === 2, 'recovery still retries at most once');
    assert(events.some(e => e.name === 'game:backend-request-end' && e.data.source === 'encounter-resync'),
      'the action retry clears its own waiting status');
    calls = 0;
    coordinator.api.sendAction = async () => {
      calls++;
      throw Object.assign(new Error('state version mismatch'), {
        status: 422, payload: { error: 'state version mismatch', game_state: {} },
      });
    };
    const failure = await enc._sendCoordinatorActionWithResync(coordinator, 'strike', 'pc-1', {});
    assert(failure.success === false && /state version mismatch/.test(failure.error) && calls === 2,
      'a rejected retry remains an explicit failure rather than retrying indefinitely');
    assert(events.filter(e => e.name === 'game:backend-request-end').length === 2,
      'a failed retry also releases its own waiting status');
  })();

  console.log('=== Finding 8: shell-owned projection reports each in-scope panel on commit ===');
  (() => {
    const shell = Object.create(GameShell.prototype);
    const reports = [];
    // Stub the shared owner so we can observe per-panel reporting.
    shell._buildStateManagerShim = () => ({
      reportRenderedSnapshot: (panelName, snapshotId) => reports.push({ panelName, snapshotId }),
    });
    // Only some panels are mounted; unmounted panels must not be reported.
    shell.panels = {
      combat: {}, character: {}, quest: {}, roomView: {}, chat: {}, actionRail: {},
    };
    shell._reportPanelRendersForCommittedSnapshot('rtsnap_commit_1');
    const reportedNames = reports.map((r) => r.panelName).sort().join(',');
    assert(reportedNames === 'ActionRail,Character,Chat,Combat,Quest,RoomView', 'each in-scope mounted panel is reported on the committed snapshot');
    assert(reports.every((r) => r.snapshotId === 'rtsnap_commit_1'), 'panels are reported against the committed snapshot id');

    const emptyShell = Object.create(GameShell.prototype);
    const emptyReports = [];
    emptyShell._buildStateManagerShim = () => ({ reportRenderedSnapshot: (n, s) => emptyReports.push({ n, s }) });
    emptyShell.panels = { chat: {} };
    emptyShell._reportPanelRendersForCommittedSnapshot('');
    assert(emptyReports.length === 0, 'no reports emitted without a committed snapshot id');
  })();

  console.log('');
  console.log('===================================');
  console.log(`Passed: ${passed}`);
  console.log(`Failed: ${failed}`);
  if (failed > 0) { console.log('SOME TESTS FAILED'); process.exit(1); }
  console.log('ALL TESTS PASSED');
})().catch((err) => {
  console.error('BEHAVIORAL TEST HARNESS ERROR', err);
  process.exit(1);
});
