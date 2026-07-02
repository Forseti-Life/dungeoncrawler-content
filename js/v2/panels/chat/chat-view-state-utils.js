const KNOWN_SESSION_VIEWS = new Set(['room', 'party', 'gm-private', 'system-log']);

export function isKnownSessionView(view = '') {
  return KNOWN_SESSION_VIEWS.has(String(view).trim());
}

export function buildDefaultRoomChannels() {
  return { room: { key: 'room', label: 'Room', type: 'room', active: true } };
}

export function normalizeRoomChannelKey(channelKey = 'room', channels = {}) {
  const normalized = String(channelKey || 'room').trim() || 'room';
  if (normalized === 'room') {
    return 'room';
  }
  return Object.prototype.hasOwnProperty.call(channels || {}, normalized) ? normalized : '';
}

export function isSameChatContext(left = {}, right = {}) {
  return String(left?.campaignId || '') === String(right?.campaignId || '')
    && String(left?.roomId || '') === String(right?.roomId || '')
    && String(left?.characterId || '') === String(right?.characterId || '');
}

export function isChatCacheFresh(entry, ttlMs = 15000) {
  return Boolean(entry && Number.isFinite(ttlMs) && (Date.now() - entry.storedAt) < ttlMs);
}

export function resolveChatChannelKey({ view = 'room', channelKey = null, activeChannel = 'room' } = {}) {
  const normalizedView = String(view || 'room').trim() || 'room';
  const explicitChannel = String(channelKey || '').trim();
  if (explicitChannel) {
    return explicitChannel;
  }
  if (normalizedView === 'room') {
    return String(activeChannel || 'room').trim() || 'room';
  }
  return normalizedView;
}

export function buildRoomChatCacheKey({ context = null, channelKey = null, activeChannel = 'room' } = {}) {
  if (!context?.campaignId || !context?.roomId) {
    return '';
  }
  return [
    'room',
    context.campaignId,
    context.roomId,
    context.characterId || 0,
    channelKey || activeChannel || 'room',
  ].join(':');
}

export function buildSessionViewCacheKey({ view, context = null } = {}) {
  if (!context?.campaignId || !view || view === 'room') {
    return '';
  }

  switch (view) {
    case 'gm-private':
      if (!context.characterId) {
        return '';
      }
      return ['session', view, context.campaignId, context.characterId].join(':');

    case 'party':
    case 'system-log':
      return ['session', view, context.campaignId].join(':');

    default:
      return '';
  }
}

export function buildChatViewStateKey({ view = 'room', context = null, channelKey = null, activeChannel = 'room' } = {}) {
  if (!context?.campaignId || !view) {
    return '';
  }

  if (view === 'room') {
    if (!context.roomId) {
      return '';
    }
    return [
      'view',
      'room',
      context.campaignId,
      context.roomId,
      context.characterId || 0,
      resolveChatChannelKey({ view, channelKey, activeChannel }),
    ].join(':');
  }

  return buildSessionViewCacheKey({ view, context });
}

export function buildSessionViewRequestToken(view, counter) {
  return `${String(view || '').trim()}:${Date.now()}:${counter}`;
}

export function isCurrentSessionViewRequest({ view, requestToken = '', tokenStore = null } = {}) {
  const token = String(requestToken || '').trim();
  if (!token) {
    return true;
  }
  if (!tokenStore || typeof tokenStore.get !== 'function') {
    return false;
  }
  return tokenStore.get(view) === token;
}

export function buildRoomHistoryRequestState({ context = {}, channelKey = 'room', counter = 0 } = {}) {
  return {
    token: `room-history:${Date.now()}:${counter}`,
    context: {
      campaignId: context?.campaignId || null,
      roomId: context?.roomId || null,
      characterId: context?.characterId || null,
    },
    channelKey: String(channelKey || 'room').trim() || 'room',
  };
}

export function isCurrentRoomHistoryRequest({ requestToken = '', context = null, channelKey = 'room', state = null } = {}) {
  if (!state) {
    return false;
  }
  if (state.token !== requestToken) {
    return false;
  }
  if (!isSameChatContext(state.context, context || {})) {
    return false;
  }
  return String(state.channelKey || 'room') === (String(channelKey || 'room').trim() || 'room');
}
