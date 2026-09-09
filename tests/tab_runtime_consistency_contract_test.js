/**
 * @file
 * Contract + behavioral tests for the Tab Runtime Consistency initiative.
 *
 * Run with:
 *   node tests/tab_runtime_consistency_contract_test.js
 *
 * Covers:
 *   - Strict snapshot contract in RuntimeStateStore (reject missing
 *     snapshot_id, prefer event_cursor, prefer runtime_snapshot wrapper,
 *     monotonic ordering, repeated failures -> read_only_desynced).
 *   - ChatPanel no longer owns a private event cursor or fetches /events.
 *   - Residual GameShell encounter-state cache surface is gone.
 *   - Shell-level desync gate/banner blocks authoritative gameplay mutation.
 *   - One-retry 422 resync behavior.
 *   - Coordinator observability counters for read/desync/mismatch drift.
 *   - Server authoritative payloads carry snapshot_id + event_cursor.
 */

const fs = require('fs');
const path = require('path');

let passed = 0;
let failed = 0;

function assert(condition, msg) {
  if (condition) {
    passed++;
    console.log(`  ✓ ${msg}`);
  } else {
    failed++;
    console.error(`  ✗ ${msg}`);
  }
}

function read(rel) {
  return fs.readFileSync(path.resolve(__dirname, '..', rel), 'utf8');
}

// ---------------------------------------------------------------------------
// Load RuntimeStateStore as a plain class (no relative imports to resolve).
// ---------------------------------------------------------------------------
function loadRuntimeStateStore() {
  const src = read('js/game-coordinator/RuntimeStateStore.js')
    .replace(/^\s*import\s+[\s\S]*?;\s*$/gm, '')
    .replace(/^\s*export\s+default\s+.*$/gm, '')
    .replace(/^\s*export\s+/gm, '');
  // eslint-disable-next-line no-new-func
  return new Function(`${src}\nreturn RuntimeStateStore;`)();
}

const RuntimeStateStore = loadRuntimeStateStore();

function baseResponse(overrides = {}) {
  return Object.assign({
    success: true,
    snapshot_id: 'rtsnap_abc123',
    event_cursor: 42,
    event_log_cursor: 42,
    state_version: 7,
    phase: 'encounter',
    encounter_id: 99005,
    active_room_id: 'undead_crypt_entry_hall',
    turn: { entity: 'pc-1', index: 0 },
    available_actions: [],
    action_contract: { actor_id: 'pc-1' },
    game_state: {
      phase: 'encounter',
      state_version: 7,
      event_log_cursor: 42,
      encounter_id: 99005,
      active_room_id: 'undead_crypt_entry_hall',
    },
  }, overrides);
}

console.log('=== Strict snapshot contract (RuntimeStateStore) ===');

(() => {
  const store = new RuntimeStateStore();
  const { snapshot } = store.commitFromResponse(baseResponse(), { source: 'test' });
  assert(snapshot?.snapshotId === 'rtsnap_abc123', 'commits real server snapshot_id verbatim');
  assert(snapshot?.eventCursor === 42, 'commits canonical event cursor');
  assert(store.getSyncHealth() === 'healthy', 'healthy after a valid commit');
})();

(() => {
  const store = new RuntimeStateStore();
  let threw = null;
  try {
    const r = baseResponse();
    delete r.snapshot_id;
    delete r.game_state.snapshot_id;
    store.commitFromResponse(r, { source: 'test' });
  } catch (e) {
    threw = e;
  }
  assert(
    threw && /runtime_snapshot_missing_snapshot_id/.test(threw.message),
    'rejects missing snapshot_id loudly with producer/consumer context',
  );
  assert(/source=test/.test(threw?.message || ''), 'missing snapshot_id error names the source');
})();

(() => {
  const store = new RuntimeStateStore();
  // event_cursor is preferred over the legacy event_log_cursor projection.
  const r = baseResponse({ event_cursor: 50, event_log_cursor: 10 });
  r.game_state.event_log_cursor = 10;
  const { snapshot } = store.commitFromResponse(r, { source: 'test' });
  assert(snapshot?.eventCursor === 50, 'prefers event_cursor over event_log_cursor');
})();

(() => {
  const store = new RuntimeStateStore();
  // Response wraps a canonical runtime_snapshot and has no top-level game_state.
  const wrapped = {
    success: true,
    runtime_snapshot: baseResponse({ snapshot_id: 'rtsnap_wrapped' }),
  };
  const { snapshot } = store.commitFromResponse(wrapped, { source: 'wrapped' });
  assert(snapshot?.snapshotId === 'rtsnap_wrapped', 'prefers canonical runtime_snapshot wrapper');
})();

(() => {
  const store = new RuntimeStateStore();
  let threw = null;
  try {
    const r = baseResponse();
    delete r.state_version;
    delete r.game_state.state_version;
    store.commitFromResponse(r, { source: 'test' });
  } catch (e) { threw = e; }
  assert(threw && /missing_state_version/.test(threw.message), 'rejects missing state_version');
})();

(() => {
  const store = new RuntimeStateStore();
  let threw = null;
  try {
    const r = baseResponse();
    delete r.event_cursor;
    delete r.event_log_cursor;
    delete r.game_state.event_log_cursor;
    store.commitFromResponse(r, { source: 'test' });
  } catch (e) { threw = e; }
  assert(threw && /missing_event_cursor/.test(threw.message), 'rejects missing event cursor');
})();

(() => {
  const store = new RuntimeStateStore();
  assert(!/derived-v/.test(read('js/game-coordinator/RuntimeStateStore.js')), 'never synthesizes a derived snapshot id');
})();

console.log('=== Monotonic ordering ===');
(() => {
  const store = new RuntimeStateStore();
  store.commitFromResponse(baseResponse({ state_version: 7, event_cursor: 42 }), { source: 'a' });
  let threw = null;
  try {
    const r = baseResponse({ state_version: 6, event_cursor: 42, snapshot_id: 'rtsnap_old' });
    r.game_state.state_version = 6;
    store.commitFromResponse(r, { source: 'b' });
  } catch (e) { threw = e; }
  assert(threw && /state_version_regressed/.test(threw.message), 'rejects regressed state_version');
})();

(() => {
  const store = new RuntimeStateStore();
  store.commitFromResponse(baseResponse({ state_version: 7, event_cursor: 42 }), { source: 'a' });
  let threw = null;
  try {
    const r = baseResponse({ state_version: 7, event_cursor: 10, snapshot_id: 'rtsnap_old' });
    r.game_state.event_log_cursor = 10;
    store.commitFromResponse(r, { source: 'b' });
  } catch (e) { threw = e; }
  assert(threw && /event_cursor_regressed/.test(threw.message), 'rejects regressed cursor at same version');
})();

console.log('=== Repeated failures escalate to read_only_desynced ===');
(() => {
  const store = new RuntimeStateStore();
  store.noteSyncFailure({ code: 'state_read_failed' });
  assert(store.getSyncHealth() === 'degraded', 'single read failure -> degraded');
  store.noteSyncFailure({ code: 'state_read_failed' });
  store.noteSyncFailure({ code: 'state_read_failed' });
  assert(store.getSyncHealth() === 'read_only_desynced', 'three read failures -> read_only_desynced');
  store.noteSyncSuccess();
  assert(store.getSyncHealth() === 'healthy', 'success recovers to healthy');
})();

console.log('=== ChatPanel unified cursor / no direct events fetch ===');
(() => {
  const chat = read('js/v2/panels/ChatPanel.js');
  assert(!/_roomEncounterEventCursorByRoom/.test(chat), 'ChatPanel private per-room cursor map removed');
  assert(!/\/api\/game\/\$\{encodeURIComponent\(context\.campaignId\)\}\/events\?since/.test(chat), 'ChatPanel no longer directly fetches /events');
  assert(!/setEncounterEventCursor|advanceEncounterEventCursor/.test(chat), 'ChatPanel private cursor mutators removed');
  assert(/coordinator\.getRecentEvents\(200\)/.test(chat), 'ChatPanel projects the coordinator event buffer');
  assert(/getCoordinatorEventCursor/.test(chat), 'ChatPanel reads the canonical coordinator cursor');
  assert(/noteSyncFailure/.test(chat), 'ChatPanel escalates unavailable event projection to sync failure');
  assert(/assertUnifiedEventCursor/.test(chat), 'ChatPanel asserts room-chat/system-log zero cursor gap');
  assert(/\.sort\(\s*\n?\s*\(a, b\) => Number\(a\?\.id \|\| 0\) - Number\(b\?\.id \|\| 0\)/.test(chat), 'ChatPanel orders by canonical event id');
})();

console.log('=== GameShell residual encounter-state cache removed ===');
(() => {
  const shell = read('js/v2/GameShell.js');
  assert(!/cacheEncounterServerState\s*\(/.test(shell), 'GameShell.cacheEncounterServerState removed');
  assert(!/getEncounterServerState\s*\(/.test(shell), 'GameShell.getEncounterServerState removed');
  assert(!/latestEncounterState/.test(shell), 'GameShell latestEncounterState state key removed');
})();

console.log('=== Shell-level sync-health gate + banner ===');
(() => {
  const shell = read('js/v2/GameShell.js');
  assert(/_applyRuntimeSyncHealth\s*\(/.test(shell), 'shell has a single sync-health gate method');
  assert(/isGameplayMutationBlockedBySync\s*\(/.test(shell), 'shell exposes a shared gameplay-mutation gate');
  assert(/game-shell__sync-banner/.test(shell), 'shell renders a sync-health banner');
  assert(/shell:sync-health/.test(shell), 'shell broadcasts one shared sync-health projection');
  assert(/read_only_desynced[\s\S]{0,400}?blocked/i.test(shell), 'read_only_desynced blocks with explicit text');
  // performCombatAction consults the shared gate before mutating.
  const combatFn = shell.slice(shell.indexOf('async performCombatAction'));
  assert(/guardGameplayMutation\(/.test(combatFn.slice(0, 1200)), 'performCombatAction blocks on the shared sync gate');
  const css = read('css/hexmap.css');
  assert(/game-shell--read-only-desynced/.test(css), 'CSS gates gameplay controls on read_only_desynced');
  assert(/game-shell__sync-banner/.test(css), 'CSS styles the shell sync banner');
})();

console.log('=== One-retry 422 resync behavior ===');
(() => {
  const shell = read('js/v2/GameShell.js');
  const region = shell.slice(shell.indexOf('async performCombatAction'), shell.indexOf('async performCombatAction') + 7000);
  assert(/State version mismatch/i.test(region), '422 path detects State version mismatch');
  assert(/applyAuthoritativeUpdate\?\.\(payload\)/.test(region), 'resyncs once from the mismatch payload');
  // Exactly one initial send + one retry send in the mismatch branch; the retry
  // catch does not send again (it refreshes authoritative state and returns).
  const sends = (region.match(/await sendWithCurrentStateVersion\(\)/g) || []).length;
  assert(sends === 2, 'action sends exactly initial + one retry (no hidden retry loop)');
  assert(/rejected after resync/.test(region), 'a rejected retry stops instead of looping');
})();

console.log('=== Coordinator observability counters ===');
(() => {
  const coord = read('js/game-coordinator/GameCoordinator.js');
  ['snapshot_commit', 'snapshot_render_mismatch', 'room_chat_vs_system_log_cursor_gap', 'action_contract_age_ms_max', 'desynced_mode_entry', 'authoritative_read_failure', 'event_stream_read_failure', 'duplicate_event_suppressed'].forEach((metric) => {
    assert(coord.includes(`${metric}`), `coordinator tracks ${metric}`);
  });
  assert(/runtime:telemetry/.test(coord), 'coordinator emits runtime:telemetry on the event bus');
  assert(/_emitRuntimeTelemetry\('event_stream_read_failure'[\s\S]{0,120}event_poll_failed/.test(coord), 'event-stream read failure emits its own (non-authoritative) telemetry');
  assert(/_emitRuntimeTelemetry\('authoritative_read_failure'[\s\S]{0,160}initial_state_failed/.test(coord), 'state read failure emits telemetry');
  assert(/notePanelRender\s*\(/.test(coord), 'coordinator supports panel snapshot-mismatch reporting');
  assert(/noteEventStreamSuccess\(/.test(coord), 'successful event poll uses event-stream health, not authoritative recovery');
  assert(!/noteSyncSuccess\(\{\s*\n?\s*code: 'event_poll_ok'/.test(coord), 'event poll no longer recovers authoritative sync health');
  assert(/runtime:events-batch/.test(coord), 'coordinator emits one canonical event-batch signal');
})();

console.log('=== Server authoritative payload contract ===');
(() => {
  const svc = read('src/Service/GameCoordinatorService.php');
  const commits = (svc.match(/'snapshot_id' => \$this->buildRuntimeSnapshotId\(/g) || []).length;
  assert(commits >= 4, 'action/full-state/transition responses all carry a server snapshot_id');
  assert(/'event_cursor' => \(int\) \(\$game_state\['event_log_cursor'\]/.test(svc), 'responses project a top-level event_cursor');
  const assembler = read('src/Service/RuntimeStateReadModelAssembler.php');
  assert(/public static function computeSnapshotId/.test(assembler), 'single deterministic snapshot-id authority exists');
  assert(/hash\('sha256'/.test(assembler), 'snapshot_id is an opaque server-side hash');
  const ctrl = read('src/Controller/HexMapController.php');
  assert(/RuntimeStateReadModelAssembler::computeSnapshotId/.test(ctrl), 'bootstrap payload stamps the canonical snapshot_id');
})();

console.log('=== Unified batch + bootstrap hydration + campaign identity wiring ===');
(() => {
  const coord = read('js/game-coordinator/GameCoordinator.js');
  assert(/_hydrateInitialEventHistory\s*\(/.test(coord), 'coordinator owns bounded initial history hydration');
  assert(/_ingestedEventIds/.test(coord), 'coordinator dedupes canonical events by id');
  const chat = read('js/v2/panels/ChatPanel.js');
  assert(/runtime:events-batch/.test(chat) && /handleCoordinatorEventBatch\s*\(/.test(chat), 'ChatPanel consumes the one canonical event-batch signal');
  assert(/_refreshSystemLogProjection\s*\(/.test(chat) && /_assertUnifiedProjectionCursors\s*\(/.test(chat), 'ChatPanel refreshes system-log and asserts independent cursor convergence');
  assert(/_roomProjectedEventCursor/.test(chat) && /_systemLogProjectedEventCursor/.test(chat), 'ChatPanel records room + system projection cursors independently');
  const shell = read('js/v2/GameShell.js');
  assert(/_reportPanelRendersForCommittedSnapshot\s*\(/.test(shell), 'shell owns a per-panel render-telemetry projection');
  assert(/guardGameplayMutation\s*\(/.test(shell), 'shell exposes the shared gameplay-mutation guard');
  const enc = read('js/v2/systems/EncounterSystem.js');
  assert((enc.match(/guardGameplayMutation\?\.\(/g) || []).length >= 3, 'EncounterSystem gates coordinator + character-endpoint mutation paths');
  const nav = read('js/v2/systems/NavigationSystem.js');
  assert(/guardGameplayMutation\?\.\(/.test(nav), 'NavigationSystem gates authoritative transitions');
  const merchant = read('js/v2/panels/MerchantPanel.js');
  assert(/guardGameplayMutation\?\.\(/.test(merchant), 'MerchantPanel gates trades');
  const inventory = read('js/v2/panels/InventoryPanel.js');
  assert(/guardGameplayMutation\?\.\(/.test(inventory), 'InventoryPanel gates equip/unequip mutations');
  const assembler = read('src/Service/RuntimeStateReadModelAssembler.php');
  assert(/public static function resolveSnapshotCampaignId/.test(assembler), 'assembler exposes a single campaign-identity resolver');
  assert(/resolveSnapshotCampaignId\(\$game_state, \$dungeon_data/.test(assembler), 'nested runtime_snapshot derives campaign id from dungeon_data');
  const css = read('css/hexmap.css');
  assert(/data-merchant-action/.test(css) && /data-inventory-action/.test(css) && /data-navigate/.test(css), 'CSS gate covers merchant/inventory/navigation mutation controls');
})();

console.log('');
console.log('===================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.log('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
