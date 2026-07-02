export function buildChannelLoadRequestToken(counter = 0) {
  return `channels:${Date.now()}:${counter}`;
}

export function buildChannelsCollectionUrl(campaignId, roomId, characterId = null) {
  const campaign = encodeURIComponent(String(campaignId || ''));
  const room = encodeURIComponent(String(roomId || ''));
  const character = String(characterId || '').trim();
  const query = character ? `?character_id=${encodeURIComponent(character)}` : '';
  return `/api/campaign/${campaign}/room/${room}/channels${query}`;
}

export function buildChannelInstanceUrl(campaignId, roomId, channelKey) {
  const campaign = encodeURIComponent(String(campaignId || ''));
  const room = encodeURIComponent(String(roomId || ''));
  const key = encodeURIComponent(String(channelKey || ''));
  return `/api/campaign/${campaign}/room/${room}/channels/${key}`;
}

export function buildOpenChannelKey(targetEntity, sourceAbility = 'whisper') {
  const target = String(targetEntity || '').trim();
  const ability = String(sourceAbility || 'whisper').trim() || 'whisper';
  return ability === 'whisper'
    ? `whisper:${target}`
    : `spell:${ability}:${target}`;
}

export function buildOpenChannelPayload({
  channelKey,
  characterId,
  targetEntity,
  targetName,
  sourceAbility = 'whisper',
} = {}) {
  return {
    channel_key: String(channelKey || '').trim(),
    opened_by: String(characterId || ''),
    target_entity: targetEntity,
    target_name: targetName,
    source_ability: sourceAbility,
  };
}

export function resolveLoadedChannels(channelMap = null, fallbackChannels = {}, activeChannel = 'room') {
  const channels = (channelMap && typeof channelMap === 'object')
    ? channelMap
    : fallbackChannels;
  let nextActiveChannel = activeChannel;
  let resetToRoom = false;
  if (!Object.prototype.hasOwnProperty.call(channels, nextActiveChannel)) {
    nextActiveChannel = 'room';
    resetToRoom = true;
  }
  return {
    channels,
    activeChannel: nextActiveChannel,
    resetToRoom,
  };
}

export function deriveChannelPresentation(channelKey, channel = null) {
  const key = String(channelKey || 'room').trim() || 'room';
  const defaultPresentation = {
    channelType: 'room',
    indicatorIcon: '📢',
    indicatorText: 'Room — Everyone can hear',
    inputPlaceholder: 'Say something to the room...',
  };
  if (key === 'room' || !channel || typeof channel !== 'object') {
    return defaultPresentation;
  }

  const targetName = channel.target_name || channel.label || 'NPC';
  const ability = channel.source_ability || 'whisper';
  const inputAbility = channel.source_ability || 'Whisper';
  if (key.startsWith('spell:')) {
    return {
      channelType: 'spell',
      indicatorIcon: '✨',
      indicatorText: `${channel.label || ability} — Magical link with ${targetName}`,
      inputPlaceholder: `${inputAbility} to ${targetName}...`,
    };
  }

  return {
    channelType: 'whisper',
    indicatorIcon: '🗣',
    indicatorText: `Whisper — Private with ${targetName}`,
    inputPlaceholder: `${inputAbility} to ${targetName}...`,
  };
}
