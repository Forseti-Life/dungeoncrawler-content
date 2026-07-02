export function mapRoomHistoryIncomingLines(messages = [], channelKey = 'room') {
  const source = Array.isArray(messages) ? messages : [];
  return source.map((message, index) => {
    const timestamp = String(message.timestamp || '').trim();
    const created = timestamp !== '' ? Date.parse(timestamp) || 0 : 0;
    const sequenceIndex = Number(message.sequence_index);
    if (!Number.isInteger(sequenceIndex) || sequenceIndex <= 0) {
      throw new Error(`room-chat-history-v1 contract violation: missing valid sequence_index at message ${index}`);
    }

    return {
      speaker: message.speaker,
      message: message.message,
      type: message.type,
      lineId: timestamp !== '' ? `${timestamp}:${index}` : `room-history:${index}:${message.speaker || ''}:${message.type || ''}`,
      sourceMessageId: sequenceIndex,
      created,
      source: 'room-history',
      authority: 'authoritative',
      messageClass: String(message.message_class || '').trim() || 'authoritative_transcript',
      channel: channelKey,
      view: 'room',
    };
  });
}

export function hasEncounterTranscriptPrefix(lines = []) {
  const encounterPrefixRegex = /^Round\s+(?:\d+|\?)\s*:\s*Turn\s+(?:\d+|\?)\s*:\s*(?:Actor\s+)?[^:]+:/i;
  return (Array.isArray(lines) ? lines : []).some((line) => encounterPrefixRegex.test(String(line?.message || '').trim()));
}
