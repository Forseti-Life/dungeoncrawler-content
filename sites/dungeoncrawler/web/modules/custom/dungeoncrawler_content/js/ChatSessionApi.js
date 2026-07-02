/**
 * @file
 * ChatSessionApi — API client for the hierarchical chat session system.
 *
 * Consumes the REST endpoints provided by ChatSessionController.php:
 *
 *   GET  /api/campaign/{cid}/sessions                  — session tree
 *   GET  /api/campaign/{cid}/sessions/{sid}/messages    — paginated messages
 *   POST /api/campaign/{cid}/sessions/{sid}/messages    — post to session
 *   GET  /api/campaign/{cid}/narrative/{char_id}        — character POV feed
 *   GET  /api/campaign/{cid}/party-chat                 — party chat messages
 *   POST /api/campaign/{cid}/party-chat                 — post to party chat
 *   GET  /api/campaign/{cid}/gm-private/{char_id}       — GM private channel
 *   POST /api/campaign/{cid}/gm-private/{char_id}       — post secret action
 *   GET  /api/campaign/{cid}/system-log                 — dice / mechanical log
 *   POST /api/campaign/{cid}/narration/flush            — force scene beat
 */

const JSON_HEADERS = {
  'Content-Type': 'application/json',
  'Accept': 'application/json',
  'X-Requested-With': 'XMLHttpRequest',
};

/**
 * GET returning parsed JSON. Returns null on non-OK responses.
 * @param {string} url
 * @returns {Promise<object|null>}
 */
async function getJson(url) {
  const res = await fetch(url, {
    method: 'GET',
    headers: { 'Accept': 'application/json' },
    credentials: 'include',
  });
  if (!res.ok) return null;
  const ct = res.headers.get('content-type') || '';
  return ct.includes('application/json') ? res.json() : null;
}

/**
 * POST with JSON body.
 * @param {string} url
 * @param {object} body
 * @returns {Promise<object>}
 */
async function postJson(url, body) {
  const res = await fetch(url, {
    method: 'POST',
    headers: JSON_HEADERS,
    credentials: 'include',
    body: JSON.stringify(body || {}),
  });
  if (!res.ok) {
    const text = await res.text();
    throw new Error(`POST ${url} failed ${res.status}: ${text}`);
  }
  const ct = res.headers.get('content-type') || '';
  return ct.includes('application/json') ? res.json() : {};
}

export class ChatSessionApi {
  /**
   * @param {number|string} campaignId
   */
  constructor(campaignId) {
    this.campaignId = campaignId;
    this.baseUrl = `/api/campaign/${campaignId}`;
  }

  // =========================================================================
  // Session tree
  // =========================================================================

  /**
   * List all sessions (tree structure).
   * @param {object} [opts]
   * @param {string} [opts.type] — filter by session type
   * @param {boolean} [opts.includeArchived]
   * @returns {Promise<{sessions: Array, root_session_id: number|null}|null>}
   */
  async listSessions(opts = {}) {
    let url = `${this.baseUrl}/sessions`;
    const params = new URLSearchParams();
    if (opts.type) params.set('type', opts.type);
    if (opts.includeArchived) params.set('include_archived', '1');
    const qs = params.toString();
    if (qs) url += `?${qs}`;

    const result = await getJson(url);
    return result?.success ? result.data : null;
  }

  // =========================================================================
  // Session messages
  // =========================================================================

  /**
   * Get messages for a specific session.
   * @param {number} sessionId
   * @param {object} [opts]
   * @param {number} [opts.limit=50]
   * @param {number} [opts.beforeId]
   * @param {number} [opts.afterId]
   * @param {string} [opts.type]
   * @param {string} [opts.order='asc']
   * @returns {Promise<{session_id, session_type, messages: Array}|null>}
   */
  async getSessionMessages(sessionId, opts = {}) {
    const params = new URLSearchParams();
    params.set('limit', String(opts.limit ?? 50));
    params.set('order', opts.order ?? 'asc');
    if (opts.beforeId) params.set('before_id', String(opts.beforeId));
    if (opts.afterId) params.set('after_id', String(opts.afterId));
    if (opts.type) params.set('type', opts.type);

    const result = await getJson(`${this.baseUrl}/sessions/${sessionId}/messages?${params}`);
    return result?.success ? result.data : null;
  }

  /**
   * Post a message to a session.
   * @param {number} sessionId
   * @param {object} payload
   * @param {string} payload.speaker
   * @param {string} payload.message
   * @param {string} [payload.speaker_type='player']
   * @param {string} [payload.speaker_ref]
   * @param {string} [payload.message_type='dialogue']
   * @param {string} [payload.visibility='public']
   * @param {object} [payload.metadata]
   * @returns {Promise<{message_id: number, session_id: number}>}
   */
  async postSessionMessage(sessionId, payload) {
    const result = await postJson(
      `${this.baseUrl}/sessions/${sessionId}/messages`,
      payload
    );
    if (!result.success) throw new Error(result.error || 'Post failed');
    return result.data;
  }

  // =========================================================================
  // Character narrative
  // =========================================================================

  /**
   * Get a character's narrative feed (perception-filtered).
   * @param {number|string} characterId
   * @param {object} [opts]
   * @param {string} [opts.dungeonId]
   * @param {string} [opts.roomId]
   * @param {number} [opts.limit=50]
   * @returns {Promise<{character_id, messages: Array, session_id?}|null>}
   */
  async getCharacterNarrative(characterId, opts = {}) {
    const params = new URLSearchParams();
    if (opts.dungeonId) params.set('dungeon_id', opts.dungeonId);
    if (opts.roomId) params.set('room_id', opts.roomId);
    if (opts.limit) params.set('limit', String(opts.limit));

    const qs = params.toString();
    const url = `${this.baseUrl}/narrative/${characterId}${qs ? '?' + qs : ''}`;
    const result = await getJson(url);
    return result?.success ? result.data : null;
  }

  // =========================================================================
  // Party chat
  // =========================================================================

  /**
   * Get party chat messages.
   * @param {object} [opts]
   * @param {number} [opts.limit=50]
   * @param {number} [opts.beforeId]
   * @returns {Promise<{session_id, messages: Array}|null>}
   */
  async getPartyChat(opts = {}) {
    const params = new URLSearchParams();
    if (opts.limit) params.set('limit', String(opts.limit));
    if (opts.beforeId) params.set('before_id', String(opts.beforeId));

    const qs = params.toString();
    const url = `${this.baseUrl}/party-chat${qs ? '?' + qs : ''}`;
    const result = await getJson(url);
    return result?.success ? result.data : null;
  }

  /**
   * Post to party chat.
   * @param {string} speaker
   * @param {string} message
   * @param {string} [speakerRef]
   * @returns {Promise<{message_id, session_id}>}
   */
  async postPartyChat(speaker, message, speakerRef = '') {
    const result = await postJson(`${this.baseUrl}/party-chat`, {
      speaker,
      message,
      speaker_ref: speakerRef,
    });
    if (!result.success) throw new Error(result.error || 'Post failed');
    return result.data;
  }

  // =========================================================================
  // GM private
  // =========================================================================

  /**
   * Get GM private channel for a character.
   * @param {number|string} characterId
   * @param {object} [opts]
   * @param {number} [opts.limit=50]
   * @returns {Promise<{session_id, messages: Array}|null>}
   */
  async getGmPrivate(characterId, opts = {}) {
    const params = new URLSearchParams();
    if (opts.limit) params.set('limit', String(opts.limit));

    const qs = params.toString();
    const url = `${this.baseUrl}/gm-private/${characterId}${qs ? '?' + qs : ''}`;
    const result = await getJson(url);
    return result?.success ? result.data : null;
  }

  /**
   * Post a secret action to GM private.
   * @param {number|string} characterId
   * @param {string} speaker
   * @param {string} message
   * @param {object} [metadata]
   * @returns {Promise<{message_id, character_id}>}
   */
  async postGmPrivate(characterId, speaker, message, metadata = {}) {
    const result = await postJson(`${this.baseUrl}/gm-private/${characterId}`, {
      speaker,
      message,
      metadata,
    });
    if (!result.success) throw new Error(result.error || 'Post failed');
    return result.data;
  }

  // =========================================================================
  // System log
  // =========================================================================

  /**
   * Get the system log (dice rolls, checks, mechanical events).
   * @param {object} [opts]
   * @param {number} [opts.limit=100]
   * @param {number} [opts.beforeId]
   * @returns {Promise<{session_id, messages: Array}|null>}
   */
  async getSystemLog(opts = {}) {
    const params = new URLSearchParams();
    if (opts.limit) params.set('limit', String(opts.limit));
    if (opts.beforeId) params.set('before_id', String(opts.beforeId));

    const qs = params.toString();
    const url = `${this.baseUrl}/system-log${qs ? '?' + qs : ''}`;
    const result = await getJson(url);
    return result?.success ? result.data : null;
  }

  // =========================================================================
  // Narration control
  // =========================================================================

  /**
   * Force-flush the narration buffer, generating scene beats.
   * @param {string|number} dungeonId
   * @param {string} roomId
   * @param {Array} presentCharacters — [{character_id, name, ...}]
   * @returns {Promise<{flushed, scene_beats, characters_narrated}>}
   */
  async flushNarration(dungeonId, roomId, presentCharacters = []) {
    const result = await postJson(`${this.baseUrl}/narration/flush`, {
      dungeon_id: dungeonId,
      room_id: roomId,
      present_characters: presentCharacters,
    });
    if (!result.success) throw new Error(result.error || 'Flush failed');
    return result.data;
  }
}

export default ChatSessionApi;
