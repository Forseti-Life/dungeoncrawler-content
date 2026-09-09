/**
 * @file
 * RuntimeStateStore — authoritative client runtime snapshot store.
 *
 * This store is the single committed runtime snapshot surface for tab
 * projections. It accepts coordinator payloads, validates ordering semantics,
 * and exposes snapshot + sync-health subscriptions.
 */

const VALID_SYNC_HEALTH = new Set([
  'healthy',
  'resyncing',
  'degraded',
  'read_only_desynced',
]);

function normalizeNumber(value, fallback = 0) {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
}

function toNonEmptyString(value) {
  const normalized = String(value ?? '').trim();
  return normalized !== '' ? normalized : null;
}

export class RuntimeStateStore {
  constructor() {
    /** @type {object|null} */
    this._snapshot = null;
    /** @type {'healthy'|'resyncing'|'degraded'|'read_only_desynced'} */
    this._syncHealth = 'healthy';
    /** @type {Array<Function>} */
    this._snapshotListeners = [];
    /** @type {Array<Function>} */
    this._syncHealthListeners = [];
    /** @type {number} */
    this._consecutiveSyncFailures = 0;

    // Event-stream health is deliberately tracked separately from the
    // authoritative snapshot sync health. The `/events` poll is an observable
    // narrative-stream surface, NOT an authoritative-state read: a successful
    // event poll must never recover authoritative sync health (only a valid
    // canonical snapshot commit may), and an event-stream failure must never
    // silently masquerade as authoritative desync recovery/progression.
    /** @type {'healthy'|'degraded'} */
    this._eventStreamHealth = 'healthy';
    /** @type {number} */
    this._consecutiveEventStreamFailures = 0;
    /** @type {Array<Function>} */
    this._eventStreamHealthListeners = [];
  }

  getSnapshot() {
    return this._snapshot ? { ...this._snapshot } : null;
  }

  getSyncHealth() {
    return this._syncHealth;
  }

  getEventStreamHealth() {
    return this._eventStreamHealth;
  }

  onSnapshotCommitted(listener) {
    if (typeof listener !== 'function') {
      return () => {};
    }
    this._snapshotListeners.push(listener);
    return () => {
      this._snapshotListeners = this._snapshotListeners.filter((entry) => entry !== listener);
    };
  }

  onSyncHealthChanged(listener) {
    if (typeof listener !== 'function') {
      return () => {};
    }
    this._syncHealthListeners.push(listener);
    return () => {
      this._syncHealthListeners = this._syncHealthListeners.filter((entry) => entry !== listener);
    };
  }

  onEventStreamHealthChanged(listener) {
    if (typeof listener !== 'function') {
      return () => {};
    }
    this._eventStreamHealthListeners.push(listener);
    return () => {
      this._eventStreamHealthListeners = this._eventStreamHealthListeners.filter((entry) => entry !== listener);
    };
  }

  setSyncHealth(status, reason = {}) {
    const normalized = VALID_SYNC_HEALTH.has(status) ? status : 'degraded';
    if (normalized === this._syncHealth) {
      return;
    }
    this._syncHealth = normalized;
    for (const listener of this._syncHealthListeners) {
      try {
        listener({ syncHealth: this._syncHealth, reason });
      } catch (error) {
        console.error('[RuntimeStateStore] sync-health listener error', error);
      }
    }
  }

  noteSyncFailure(reason = {}) {
    this._consecutiveSyncFailures += 1;
    const nextHealth = this._consecutiveSyncFailures >= 3 ? 'read_only_desynced' : 'degraded';
    this.setSyncHealth(nextHealth, {
      ...reason,
      consecutiveFailures: this._consecutiveSyncFailures,
    });
  }

  noteSyncSuccess(reason = {}) {
    this._consecutiveSyncFailures = 0;
    this.setSyncHealth('healthy', reason);
  }

  /**
   * Record a successful event-stream (`/events`) poll.
   *
   * This is intentionally isolated from authoritative snapshot health: it must
   * NOT reset `_consecutiveSyncFailures` and must NOT recover `_syncHealth`.
   * Only a valid canonical snapshot commit may recover authoritative sync
   * health. A recovering event stream simply clears the (separate) event-stream
   * degraded signal so event failures remain observable without masking desync.
   *
   * @param {object} [reason]
   */
  noteEventStreamSuccess(reason = {}) {
    this._consecutiveEventStreamFailures = 0;
    this._setEventStreamHealth('healthy', reason);
  }

  /**
   * Record a failed event-stream (`/events`) poll.
   *
   * Event-stream failures are visible/observable through the dedicated
   * event-stream health signal, but they do not drive the authoritative
   * snapshot sync-health state machine (which owns the gameplay-mutation gate).
   *
   * @param {object} [reason]
   */
  noteEventStreamFailure(reason = {}) {
    this._consecutiveEventStreamFailures += 1;
    this._setEventStreamHealth('degraded', {
      ...reason,
      consecutiveFailures: this._consecutiveEventStreamFailures,
    });
  }

  _setEventStreamHealth(status, reason = {}) {
    const normalized = status === 'healthy' ? 'healthy' : 'degraded';
    if (normalized === this._eventStreamHealth) {
      return;
    }
    this._eventStreamHealth = normalized;
    for (const listener of this._eventStreamHealthListeners) {
      try {
        listener({ eventStreamHealth: this._eventStreamHealth, reason });
      } catch (error) {
        console.error('[RuntimeStateStore] event-stream health listener error', error);
      }
    }
  }

  commitFromResponse(response = {}, metadata = {}) {
    const normalized = this._normalizeResponse(response, metadata);
    this._assertMonotonicOrdering(normalized);
    this._snapshot = normalized;

    this.noteSyncSuccess({
      code: 'runtime_snapshot_committed',
      source: normalized.source,
    });

    for (const listener of this._snapshotListeners) {
      try {
        listener({ snapshot: this.getSnapshot(), metadata: { ...metadata } });
      } catch (error) {
        console.error('[RuntimeStateStore] snapshot listener error', error);
      }
    }

    return {
      snapshot: this.getSnapshot(),
      integrityIssues: [],
    };
  }

  _assertMonotonicOrdering(nextSnapshot) {
    if (!this._snapshot) {
      return;
    }
    const prevVersion = normalizeNumber(this._snapshot.stateVersion, 0);
    const nextVersion = normalizeNumber(nextSnapshot.stateVersion, 0);
    const prevCursor = normalizeNumber(this._snapshot.eventCursor, 0);
    const nextCursor = normalizeNumber(nextSnapshot.eventCursor, 0);

    if (nextVersion < prevVersion) {
      throw new Error(`runtime_state_version_regressed:${nextVersion}<${prevVersion}`);
    }
    if (nextVersion === prevVersion && nextCursor < prevCursor) {
      throw new Error(`runtime_event_cursor_regressed:${nextCursor}<${prevCursor}`);
    }
  }

  _normalizeResponse(response = {}, metadata = {}) {
    const source = String(metadata?.source || 'unknown').trim() || 'unknown';

    // Canonical rule: prefer the wrapped runtime_snapshot projection when the
    // response carries one and no top-level game_state. This keeps a single
    // authoritative shape regardless of which coordinator lane produced it.
    let carrier = response;
    if (
      (!response?.game_state || typeof response.game_state !== 'object')
      && response?.runtime_snapshot
      && typeof response.runtime_snapshot === 'object'
      && response.runtime_snapshot.game_state
      && typeof response.runtime_snapshot.game_state === 'object'
    ) {
      carrier = { ...response.runtime_snapshot };
    }

    const gameState = carrier?.game_state && typeof carrier.game_state === 'object'
      ? carrier.game_state
      : null;

    if (!gameState) {
      throw new Error(`runtime_snapshot_missing_game_state:source=${source}`);
    }

    const stateVersion = normalizeNumber(
      carrier?.state_version ?? gameState?.state_version,
      NaN,
    );
    if (!Number.isFinite(stateVersion) || stateVersion < 0) {
      throw new Error(`runtime_snapshot_missing_state_version:source=${source}`);
    }

    // event_cursor is the canonical field name; event_log_cursor is retained as
    // an external-contract projection only.
    const eventCursor = normalizeNumber(
      carrier?.event_cursor
      ?? gameState?.event_cursor
      ?? carrier?.event_log_cursor
      ?? gameState?.event_log_cursor,
      NaN,
    );
    if (!Number.isFinite(eventCursor) || eventCursor < 0) {
      throw new Error(`runtime_snapshot_missing_event_cursor:source=${source}`);
    }

    // Strict contract: a real, server-committed snapshot_id is mandatory. The
    // store never synthesizes/derives one and never degrades-and-continues on a
    // missing id — the producer/consumer context is surfaced for the failure.
    const snapshotId = toNonEmptyString(carrier?.snapshot_id) || toNonEmptyString(gameState?.snapshot_id);
    if (!snapshotId) {
      throw new Error(`runtime_snapshot_missing_snapshot_id:source=${source}:state_version=${stateVersion}:event_cursor=${eventCursor}`);
    }

    return {
      snapshotId,
      stateVersion,
      eventCursor,
      phase: String(carrier?.phase ?? gameState?.phase ?? 'encounter').trim() || 'encounter',
      encounterId: normalizeNumber(carrier?.encounter_id ?? gameState?.encounter_id, 0) || null,
      activeRoomId: toNonEmptyString(carrier?.active_room_id ?? gameState?.active_room_id),
      round: normalizeNumber(carrier?.round ?? gameState?.round, 0) || null,
      turn: carrier?.turn ?? gameState?.turn ?? null,
      initiativeOrder: Array.isArray(gameState?.initiative_order) ? gameState.initiative_order : [],
      availableActions: Array.isArray(carrier?.available_actions) ? carrier.available_actions : [],
      actionContract: (carrier?.action_contract && typeof carrier.action_contract === 'object')
        ? carrier.action_contract
        : null,
      legalIntents: Array.isArray(carrier?.legal_intents ?? gameState?.legal_intents)
        ? (carrier?.legal_intents ?? gameState?.legal_intents)
        : [],
      campaignClock: gameState?.campaign_clock ?? null,
      gameTime: gameState?.game_time ?? null,
      timedActivities: Array.isArray(gameState?.timed_activities) ? gameState.timed_activities : [],
      gameState: { ...gameState },
      source,
      committedAt: Date.now(),
    };
  }
}

export default RuntimeStateStore;
