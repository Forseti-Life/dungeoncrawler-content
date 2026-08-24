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
  }

  getSnapshot() {
    return this._snapshot ? { ...this._snapshot } : null;
  }

  getSyncHealth() {
    return this._syncHealth;
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

  commitFromResponse(response = {}, metadata = {}) {
    const normalized = this._normalizeResponse(response, metadata);
    this._assertMonotonicOrdering(normalized);
    this._snapshot = normalized;

    if (normalized.integrityIssues.length > 0) {
      this._consecutiveSyncFailures = 0;
      this.setSyncHealth('degraded', {
        code: 'runtime_snapshot_integrity_issues',
        source: normalized.source,
        issues: normalized.integrityIssues,
      });
    } else {
      this.noteSyncSuccess({
        code: 'runtime_snapshot_committed',
        source: normalized.source,
      });
    }

    for (const listener of this._snapshotListeners) {
      try {
        listener({ snapshot: this.getSnapshot(), metadata: { ...metadata } });
      } catch (error) {
        console.error('[RuntimeStateStore] snapshot listener error', error);
      }
    }

    return {
      snapshot: this.getSnapshot(),
      integrityIssues: [...normalized.integrityIssues],
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
    const gameState = response?.game_state && typeof response.game_state === 'object'
      ? response.game_state
      : null;

    if (!gameState) {
      throw new Error('runtime_snapshot_missing_game_state');
    }

    const stateVersion = normalizeNumber(
      response?.state_version ?? gameState?.state_version,
      NaN,
    );
    if (!Number.isFinite(stateVersion) || stateVersion < 0) {
      throw new Error('runtime_snapshot_missing_state_version');
    }

    const eventCursor = normalizeNumber(
      response?.event_log_cursor ?? gameState?.event_log_cursor,
      NaN,
    );
    if (!Number.isFinite(eventCursor) || eventCursor < 0) {
      throw new Error('runtime_snapshot_missing_event_cursor');
    }

    const integrityIssues = [];
    let snapshotId = toNonEmptyString(response?.snapshot_id) || toNonEmptyString(gameState?.snapshot_id);
    if (!snapshotId) {
      snapshotId = `derived-v${stateVersion}-c${eventCursor}`;
      integrityIssues.push('missing_snapshot_id');
    }

    return {
      snapshotId,
      stateVersion,
      eventCursor,
      phase: String(response?.phase ?? gameState?.phase ?? 'encounter').trim() || 'encounter',
      encounterId: normalizeNumber(response?.encounter_id ?? gameState?.encounter_id, 0) || null,
      activeRoomId: toNonEmptyString(response?.active_room_id ?? gameState?.active_room_id),
      round: normalizeNumber(response?.round ?? gameState?.round, 0) || null,
      turn: response?.turn ?? gameState?.turn ?? null,
      initiativeOrder: Array.isArray(gameState?.initiative_order) ? gameState.initiative_order : [],
      availableActions: Array.isArray(response?.available_actions) ? response.available_actions : [],
      actionContract: (response?.action_contract && typeof response.action_contract === 'object')
        ? response.action_contract
        : null,
      legalIntents: Array.isArray(response?.legal_intents ?? gameState?.legal_intents)
        ? (response?.legal_intents ?? gameState?.legal_intents)
        : [],
      campaignClock: gameState?.campaign_clock ?? null,
      gameTime: gameState?.game_time ?? null,
      timedActivities: Array.isArray(gameState?.timed_activities) ? gameState.timed_activities : [],
      gameState: { ...gameState },
      source,
      committedAt: Date.now(),
      integrityIssues,
    };
  }
}

export default RuntimeStateStore;
