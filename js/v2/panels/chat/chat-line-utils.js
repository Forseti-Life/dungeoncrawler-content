import { resolveChatChannelKey as resolveChatChannelKeyUtil } from './chat-view-state-utils.js';

function resolveChannelKey(view, channelKey, options = {}) {
  if (typeof options.resolveChannelKey === 'function') {
    return options.resolveChannelKey(view, channelKey);
  }
  return resolveChatChannelKeyUtil({
    view,
    channelKey,
    activeChannel: options.activeChannel || 'room',
  });
}

export function normalizeChatLineRecord(line = {}, options = {}) {
  const normalizedView = String(line.view || options.activeSessionView || 'room').trim() || 'room';
  const normalizedChannel = resolveChannelKey(normalizedView, line.channel || line.channelKey, options);
  const normalizedSource = String(line.source || '').trim() || 'local-ui';
  const normalizedAuthority = String(line.authority || '').trim()
    || (normalizedSource.startsWith('local') ? 'local' : 'authoritative');
  let normalizedMessageClass = String(line.messageClass || '').trim()
    || (normalizedAuthority === 'authoritative' ? 'authoritative_transcript' : 'local_ui_notice');
  const normalizedMessage = String(line.message || '').trim();
  if (normalizedMessageClass === 'authoritative_transcript') {
    if (/^Objective completed\b/i.test(normalizedMessage)) {
      normalizedMessageClass = 'quest_objective_completion';
    } else if (/^Quest completed\b/i.test(normalizedMessage)) {
      normalizedMessageClass = 'quest_completion';
    }
  }

  const turnPrompt = Boolean(line.turnPrompt || line.turn_prompt);
  const disableTemporaryChatLines = options.disableTemporaryChatLines;
  const transient = (disableTemporaryChatLines !== false)
    ? false
    : (Boolean(line.transient) && !turnPrompt);

  return {
    speaker: String(line.speaker || ''),
    message: String(line.message || ''),
    type: String(line.type || 'npc'),
    transient,
    persistent: typeof line.persistent === 'boolean' ? line.persistent : !transient,
    lineId: String(line.lineId || ''),
    messageId: Number.isFinite(Number(line.messageId)) ? Number(line.messageId) : null,
    sourceMessageId: Number.isFinite(Number(line.sourceMessageId)) ? Number(line.sourceMessageId) : null,
    sequenceIndex: Number.isFinite(Number(line.sequenceIndex ?? line.sequence_index)) ? Number(line.sequenceIndex ?? line.sequence_index) : null,
    created: Number.isFinite(Number(line.created)) ? Number(line.created) : 0,
    source: normalizedSource,
    authority: normalizedAuthority,
    messageClass: normalizedMessageClass,
    channel: normalizedChannel,
    view: normalizedView,
    requestId: String(line.requestId || ''),
    eventId: String(line.eventId || ''),
    internalLog: Boolean(line.internalLog || line.internal_log),
    turnPrompt,
    turnRole: String(line.turnRole || line.turn_role || ''),
    turnName: String(line.turnName || line.turn_name || ''),
    turnIndex: Number.isFinite(Number(line.turnIndex ?? line.turn_index)) ? Number(line.turnIndex ?? line.turn_index) : null,
    initiativeTotal: Number.isFinite(Number(line.initiativeTotal ?? line.initiative_total)) ? Number(line.initiativeTotal ?? line.initiative_total) : null,
    initiativeRoll: Number.isFinite(Number(line.initiativeRoll ?? line.initiative_roll)) ? Number(line.initiativeRoll ?? line.initiative_roll) : null,
    initiativeModifier: Number.isFinite(Number(line.initiativeModifier ?? line.initiative_modifier)) ? Number(line.initiativeModifier ?? line.initiative_modifier) : null,
  };
}

export function buildChatLineContentKey(line = {}, options = {}) {
  const normalized = normalizeChatLineRecord(line, options);
  return [
    normalized.speaker,
    normalized.type,
    normalized.message,
  ].join('|');
}

export function buildChatLineExactKey(line = {}, options = {}) {
  const normalized = normalizeChatLineRecord(line, options);
  if (normalized.messageId) {
    return `message:${normalized.messageId}`;
  }
  if (normalized.sourceMessageId) {
    return `source:${normalized.sourceMessageId}`;
  }
  if (normalized.sequenceIndex) {
    return `sequence:${normalized.sequenceIndex}`;
  }
  if (normalized.lineId) {
    return `line:${normalized.lineId}`;
  }
  return `content:${buildChatLineContentKey(normalized, options)}`;
}

export function mergeChatLineRecord(existing = {}, incoming = {}, options = {}) {
  const base = normalizeChatLineRecord(existing, options);
  const next = normalizeChatLineRecord(incoming, options);
  return {
    ...base,
    ...next,
    speaker: next.speaker || base.speaker,
    message: next.message || base.message,
    type: next.type || base.type,
    transient: base.transient && next.transient,
    lineId: next.lineId || base.lineId,
    messageId: next.messageId || base.messageId,
    sourceMessageId: next.sourceMessageId || base.sourceMessageId,
    sequenceIndex: next.sequenceIndex || base.sequenceIndex,
    created: next.created || base.created || 0,
    persistent: next.persistent || base.persistent,
    source: next.source || base.source,
    authority: next.authority || base.authority,
    messageClass: next.messageClass || base.messageClass,
    channel: next.channel || base.channel,
    view: next.view || base.view,
    requestId: next.requestId || base.requestId,
    eventId: next.eventId || base.eventId,
  };
}

export function hasCanonicalTranscriptOrder(line = {}, options = {}) {
  const normalized = normalizeChatLineRecord(line, options);
  return normalized.sequenceIndex !== null
    || normalized.eventId !== ''
    || normalized.messageId !== null
    || normalized.sourceMessageId !== null;
}

export function normalizeChatLineRecords(lines = [], options = {}) {
  return (Array.isArray(lines) ? lines : []).map((line) => normalizeChatLineRecord({
    ...line,
    ...options,
    speaker: line?.speaker,
    message: line?.message,
    type: line?.type,
    transient: typeof line?.transient === 'boolean' ? line.transient : options.transient,
    persistent: typeof line?.persistent === 'boolean' ? line.persistent : options.persistent,
    lineId: line?.lineId,
    messageId: line?.messageId,
    sourceMessageId: line?.sourceMessageId,
    sequenceIndex: line?.sequenceIndex ?? line?.sequence_index,
    created: line?.created,
    source: line?.source || options.source,
    authority: line?.authority || options.authority,
    messageClass: line?.messageClass || options.messageClass,
    channel: line?.channel || line?.channelKey || options.channel || options.channelKey,
    view: line?.view || options.view,
    requestId: line?.requestId || options.requestId,
    eventId: line?.eventId || options.eventId,
  }, options));
}

export function mergeRememberedChatLines(existingLines = [], incomingLines = [], options = {}) {
  const merged = (Array.isArray(existingLines) ? existingLines : [])
    .map((line) => normalizeChatLineRecord(line, options))
    .filter((line) => line.message !== '');

  (Array.isArray(incomingLines) ? incomingLines : []).forEach((line) => {
    const normalized = normalizeChatLineRecord(line, options);
    if (!normalized.message) {
      return;
    }

    const exactKey = buildChatLineExactKey(normalized, options);
    const exactIndex = merged.findIndex((candidate) => buildChatLineExactKey(candidate, options) === exactKey);
    if (exactIndex !== -1) {
      merged[exactIndex] = mergeChatLineRecord(merged[exactIndex], normalized, options);
      return;
    }

    const contentKey = buildChatLineContentKey(normalized, options);
    const contentIndex = merged.findIndex((candidate) => {
      if (candidate.transient || normalized.transient) {
        return false;
      }
      return buildChatLineContentKey(candidate, options) === contentKey;
    });
    if (contentIndex !== -1) {
      merged[contentIndex] = mergeChatLineRecord(merged[contentIndex], normalized, options);
      return;
    }

    merged.push(normalized);
  });

  return merged.filter((line) => !line.transient && line.message !== '');
}

function toSortableNumber(value) {
  const n = Number(value);
  return Number.isFinite(n) ? n : null;
}

export function sortChatLineRecords(lines = []) {
  return (Array.isArray(lines) ? lines : [])
    .map((line, index) => ({ ...line, __sortIndex: index }))
    .sort((a, b) => {
      const aSequenceIndex = toSortableNumber(a.sequenceIndex);
      const bSequenceIndex = toSortableNumber(b.sequenceIndex);
      if (aSequenceIndex !== null || bSequenceIndex !== null) {
        if (aSequenceIndex === null) return 1;
        if (bSequenceIndex === null) return -1;
        if (aSequenceIndex !== bSequenceIndex) return aSequenceIndex - bSequenceIndex;
      }

      const aEventId = toSortableNumber(a.eventId);
      const bEventId = toSortableNumber(b.eventId);
      if (aEventId !== null || bEventId !== null) {
        if (aEventId === null) return 1;
        if (bEventId === null) return -1;
        if (aEventId !== bEventId) return aEventId - bEventId;
      }

      const aMessageId = toSortableNumber(a.messageId);
      const bMessageId = toSortableNumber(b.messageId);
      if (aMessageId !== null || bMessageId !== null) {
        if (aMessageId === null) return 1;
        if (bMessageId === null) return -1;
        if (aMessageId !== bMessageId) return aMessageId - bMessageId;
      }

      const aSourceMessageId = toSortableNumber(a.sourceMessageId);
      const bSourceMessageId = toSortableNumber(b.sourceMessageId);
      if (aSourceMessageId !== null || bSourceMessageId !== null) {
        if (aSourceMessageId === null) return 1;
        if (bSourceMessageId === null) return -1;
        if (aSourceMessageId !== bSourceMessageId) return aSourceMessageId - bSourceMessageId;
      }

      return a.__sortIndex - b.__sortIndex;
    })
    .map(({ __sortIndex, ...line }) => line);
}
