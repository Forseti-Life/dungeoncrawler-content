// Thin client for Hexmap -> backend combat APIs.
// Currently calls planned endpoints; falls back to client-only behavior if unavailable.

const jsonHeaders = {
  'Content-Type': 'application/json',
  'Accept': 'application/json',
  'X-Requested-With': 'XMLHttpRequest',
};

async function getJson(url) {
  const res = await fetch(url, {
    method: 'GET',
    headers: {
      'Accept': 'application/json',
    },
    credentials: 'include',
  });

  if (!res.ok) {
    const text = await res.text();
    throw new Error(`Request failed ${res.status}: ${text}`);
  }

  const contentType = res.headers.get('content-type') || '';
  if (contentType.includes('application/json')) {
    return await res.json();
  }

  return {};
}

async function postJson(url, body) {
  const res = await fetch(url, {
    method: 'POST',
    headers: jsonHeaders,
    credentials: 'include',
    body: JSON.stringify(body || {}),
  });
  if (!res.ok) {
    const text = await res.text();
    throw new Error(`Request failed ${res.status}: ${text}`);
  }
  const contentType = res.headers.get('content-type') || '';
  if (contentType.includes('application/json')) {
    return await res.json();
  }
  return {};
}

export default {
  async getCurrentState(payload) {
    const params = new URLSearchParams();
    Object.entries(payload || {}).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        params.set(key, String(value));
      }
    });

    const query = params.toString();
    const url = query ? `/api/combat/state?${query}` : '/api/combat/state';
    return getJson(url);
  },

  async startCombat(payload) {
    // Expected payload: { campaignId, roomId, entities }
    return postJson('/api/combat/start', payload);
  },

  async endTurn(payload) {
    // Expected payload: { encounterId, participantId }
    return postJson('/api/combat/end-turn', payload);
  },

  async endCombat(payload) {
    // Expected payload: { encounterId }
    return postJson('/api/combat/end', payload);
  },

  async performAttack(payload) {
    // Expected payload: { encounterId, attackerId, targetId, action }
    return postJson('/api/combat/attack', payload);
  },

  async performAction(payload) {
    // Expected payload: { encounterId, actorId, actionType, actionCost?, targetId?, interactionType?, targetHex?, message? }
    return postJson('/api/combat/action', payload);
  },
};
