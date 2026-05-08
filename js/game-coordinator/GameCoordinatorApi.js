/**
 * @file
 * GameCoordinatorApi — unified API client for the Game Coordinator server endpoints.
 *
 * Replaces direct combat API calls with a single coherent interface that speaks
 * the unified action protocol: { type, actor, target, params, client_state_version }.
 *
 * All methods return Promises resolving to the server response JSON.
 */

const jsonHeaders = {
  'Content-Type': 'application/json',
  'Accept': 'application/json',
  'X-Requested-With': 'XMLHttpRequest',
};

/**
 * GET request returning parsed JSON.
 * @param {string} url
 * @returns {Promise<object>}
 */
async function getJson(url) {
  const res = await fetch(url, {
    method: 'GET',
    headers: { 'Accept': 'application/json' },
    credentials: 'include',
  });
  if (!res.ok) {
    const text = await res.text();
    throw new Error(`GET ${url} failed ${res.status}: ${text}`);
  }
  const ct = res.headers.get('content-type') || '';
  return ct.includes('application/json') ? res.json() : {};
}

/**
 * POST request with JSON body.
 * @param {string} url
 * @param {object} body
 * @returns {Promise<object>}
 */
async function postJson(url, body) {
  const res = await fetch(url, {
    method: 'POST',
    headers: jsonHeaders,
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

export class GameCoordinatorApi {
  /**
   * @param {number} campaignId
   */
  constructor(campaignId) {
    this.campaignId = campaignId;
    this.baseUrl = `/api/game/${campaignId}`;
  }

  // =========================================================================
  // Core Coordinator Endpoints
  // =========================================================================

  /**
   * Send a player action intent to the server.
   *
   * @param {string} type - Action type (move, strike, search, interact, talk, etc.)
   * @param {string} actor - Actor entity_instance_id
   * @param {object} [params={}] - Action-specific parameters
   * @param {object} [options={}] - Additional options
   * @param {string} [options.target] - Target entity_instance_id
   * @param {number} [options.stateVersion] - Client's current state_version for optimistic concurrency
   * @returns {Promise<object>} Server response { success, game_state, events, available_actions, ... }
   */
  async sendAction(type, actor, params = {}, options = {}) {
    const payload = {
      type,
      actor,
      params,
    };
    if (options.target) {
      payload.target = options.target;
    }
    if (options.stateVersion != null) {
      payload.client_state_version = options.stateVersion;
    }
    return postJson(`${this.baseUrl}/action`, payload);
  }

  /**
   * Get the full game state from the server.
   *
   * @returns {Promise<object>} { success, game_state, available_actions, event_log_cursor }
   */
  async getState() {
    return getJson(`${this.baseUrl}/state`);
  }

  /**
   * Manually trigger a phase transition.
   *
   * @param {string} targetPhase - Target phase (exploration, encounter, downtime)
   * @param {object} [context={}] - Transition context (e.g. encounter_context)
   * @returns {Promise<object>} { success, game_state, events }
   */
  async transitionPhase(targetPhase, context = {}) {
    return postJson(`${this.baseUrl}/transition`, {
      target_phase: targetPhase,
      context,
    });
  }

  /**
   * Poll for events since a cursor (for timeline/log updating).
   *
   * @param {number} [since=0] - Event ID cursor
   * @returns {Promise<object>} { events: [...], latest_cursor }
   */
  async getEventsSince(since = 0) {
    return getJson(`${this.baseUrl}/events?since=${since}`);
  }

  // =========================================================================
  // Convenience Methods (typed wrappers around sendAction)
  // =========================================================================

  /**
   * Move an entity to a hex.
   * @param {string} actor - Entity instance ID
   * @param {number} q - Target hex Q
   * @param {number} r - Target hex R
   * @param {number} [stateVersion]
   */
  async move(actor, q, r, stateVersion) {
    return this.sendAction('move', actor, { target_hex: { q, r } }, { stateVersion });
  }

  /**
   * Perform a strike (attack).
   * @param {string} actor - Attacker entity instance ID
   * @param {string} target - Target entity instance ID
   * @param {object} [params={}] - Weapon, etc.
   * @param {number} [stateVersion]
   */
  async strike(actor, target, params = {}, stateVersion) {
    return this.sendAction('strike', actor, params, { target, stateVersion });
  }

  /**
   * Interact with an entity or object.
   * @param {string} actor - Actor entity instance ID
   * @param {string} target - Target entity instance ID
   * @param {string} [interactionType='generic'] - Interaction sub-type
   * @param {number} [stateVersion]
   */
  async interact(actor, target, interactionType = 'generic', stateVersion) {
    return this.sendAction('interact', actor, { interaction_type: interactionType }, { target, stateVersion });
  }

  /**
   * Talk to an NPC or in combat.
   * @param {string} actor
   * @param {string} target
   * @param {string} message
   * @param {number} [stateVersion]
   */
  async talk(actor, target, message, stateVersion) {
    return this.sendAction('talk', actor, { message }, { target, stateVersion });
  }

  /**
   * Search the current area.
   * @param {string} actor
   * @param {number} [stateVersion]
   */
  async search(actor, stateVersion) {
    return this.sendAction('search', actor, {}, { stateVersion });
  }

  /**
   * End the current turn (encounter phase).
   * @param {string} actor
   * @param {number} [stateVersion]
   */
  async endTurn(actor, stateVersion) {
    return this.sendAction('end_turn', actor, {}, { stateVersion });
  }

  /**
   * Request a rest action.
   * @param {string} actor
   * @param {string} [restType='short'] - 'short' or 'long'
   * @param {number} [stateVersion]
   */
  async rest(actor, restType = 'short', stateVersion) {
    return this.sendAction('rest', actor, { rest_type: restType }, { stateVersion });
  }

  /**
   * Stride (movement in encounter).
   * @param {string} actor
   * @param {number} q
   * @param {number} r
   * @param {number} [stateVersion]
   */
  async stride(actor, q, r, stateVersion) {
    return this.sendAction('stride', actor, { target_hex: { q, r } }, { stateVersion });
  }

  /**
   * Cast a spell.
   * @param {string} actor
   * @param {string} [target]
   * @param {object} [params={}]
   * @param {number} [stateVersion]
   */
  async castSpell(actor, target, params = {}, stateVersion) {
    return this.sendAction('cast_spell', actor, params, { target, stateVersion });
  }
}

export default GameCoordinatorApi;
