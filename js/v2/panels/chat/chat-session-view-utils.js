export function resolveSessionLineType(message = {}, view = '') {
  if (message.speaker_type === 'system') return 'system';
  if (message.speaker_type === 'narrator') return 'narrator';
  if (message.speaker_type === 'gm') return 'gm';
  if (message.message_type === 'mechanical' || message.message_type === 'dice_roll') return 'mechanical';
  if (view === 'gm-private') return message.speaker_type === 'player' ? 'secret' : 'gm';
  if (message.speaker_type === 'player') return 'player';
  return 'npc';
}

export function isSystemLogMessage(message = {}) {
  const messageType = String(message.message_type || message.type || '').trim().toLowerCase();
  const speakerType = String(message.speaker_type || '').trim().toLowerCase();
  const metadata = message?.metadata && typeof message.metadata === 'object' ? message.metadata : {};
  const diceRolls = Array.isArray(metadata.dice_rolls) ? metadata.dice_rolls : [];

  if (['system', 'mechanical', 'dice_roll', 'dice', 'check', 'roll'].includes(messageType)) {
    return true;
  }
  if (speakerType === 'system' && messageType !== 'dialogue' && messageType !== 'narrative') {
    return true;
  }
  if (diceRolls.length > 0) {
    return true;
  }

  return Number.isFinite(Number(metadata.roll))
    || Number.isFinite(Number(metadata.total))
    || Number.isFinite(Number(metadata.dc))
    || typeof metadata.check === 'string';
}

export function scopeSessionViewMessages(messages = [], view = '') {
  const source = Array.isArray(messages) ? messages : [];
  if (view !== 'system-log') {
    return source;
  }
  return source.filter((message) => isSystemLogMessage(message));
}

export function buildSessionViewIncomingLines(messages = [], view = '') {
  return scopeSessionViewMessages(messages, view).map((msg) => ({
    speaker: msg.speaker,
    message: msg.message,
    type: resolveSessionLineType(msg, view),
    messageId: msg.id || null,
    sourceMessageId: msg.source_message_id || null,
    created: msg.created || 0,
    source: `session-view:${view}`,
    authority: 'authoritative',
    messageClass: String(msg?.metadata?.message_class || '').trim() || 'authoritative_transcript',
    channel: view,
    view,
  }));
}

export function getSessionViewEmptyMessage(view = '') {
  const messages = {
    party: 'No party chatter yet. Say something!',
    'gm-private': 'No GM messages yet. Messages here go straight to the GM, and the GM should answer here while using tools to resolve issues.',
    'system-log': 'No system messages yet.',
  };
  return messages[view] || 'No messages.';
}
