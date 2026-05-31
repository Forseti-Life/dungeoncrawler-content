/**
 * @file panels/ChatPanel.js
 *
 * Multi-channel chat log, GM message rendering, pending turn status.
 * Methods ported verbatim from hexmap.js UIManager.
 * Largest panel: 65 functions.
 */

import { escapeQuestHtml } from '../utils/quest-utils.js';

// --- parseGm* helpers (module-level in original hexmap.js, used internally) ---

function parseGmLocationRequest(message) {
  const trimmed = String(message ?? '').trim();
  const match = trimmed.match(/^\/(?:generate-location|location)\s+(.+)$/i);
  if (!match) {
    return '';
  }
  return String(match[1] ?? '').trim();
}

function parseGmRoomRequest(message) {
  const trimmed = String(message ?? '').trim();
  const match = trimmed.match(/^\/(?:generate-room|room)\s+([a-z_][a-z0-9_-]*)(?:\s+([a-z_][a-z0-9_-]*))?(?:\s+([a-z_][a-z0-9_-]*))?$/i);
  if (!match) {
    return null;
  }
  return {
    roomType: String(match[1] || 'chamber').toLowerCase(),
    terrainType: String(match[2] || 'stone_floor').toLowerCase(),
    roomSize: String(match[3] || 'medium').toLowerCase(),
  };
}

function parseGmQuestRequest(message) {
  const trimmed = String(message ?? '').trim();
  const match = trimmed.match(/^\/(?:generate-quests|quests)(?:\s+(\d+))?$/i);
  if (!match) {
    return null;
  }
  return {
    count: Math.max(1, Math.min(5, Number(match[1] || 3))),
  };
}

function parseGmDungeonRequest(message) {
  const trimmed = String(message ?? '').trim();
  const match = trimmed.match(/^\/(?:generate-dungeon|dungeon)\s+(-?\d+)\s+(-?\d+)(?:\s+(.+))?$/i);
  if (!match) {
    return null;
  }

  const extra = String(match[3] || '').trim();
  let partyLevel = null;
  let theme = '';
  if (extra) {
    const parts = extra.split(/\s+/);
    if (/^\d+$/.test(parts[0] || '')) {
      partyLevel = Math.max(1, Math.min(20, Number(parts.shift())));
    }
    theme = parts.join(' ').trim();
  }

  return {
    locationX: Number(match[1]),
    locationY: Number(match[2]),
    partyLevel,
    theme,
  };
}

// ---------------------------------------------------------------------------

export class ChatPanel {
  constructor(container, bus) {
    this.container = container;
    this.bus = bus;
    this._unsubs = [];
    this._el = {};
    this.stateManager = null;
    this.dungeonData = null;
    this.activeSessionView = 'room';
    this._chatCache = new Map();
    this._pendingChatRequests = new Map();
    // Channel / multi-view state
    this.activeChannel = 'room';
    this.channels = { room: { key: 'room', label: 'Room', type: 'room', active: true } };
    this.lastChatTurnStatusKey = null;
    // View-state caches
    this.chatViewStateCache = new Map();
    this.roomChatCache = new Map();
    this.roomTurnSequenceCache = new Map();
    this.sessionViewCache = new Map();
  }

  init(dungeonData, stateManager) {
    this.dungeonData = dungeonData || {};
    this.stateManager = stateManager || {};
    const id = (k) => document.getElementById(k);
    this._el = {
      chatLog:          id('chat-log'),
      chatSummary:      id('chat-summary'),
      chatInput:        id('chat-input'),
      chatSubmit:       id('chat-submit'),
      chatChannelTabs:  id('chat-channel-tabs'),
      chatPendingWrap:  id('chat-pending-wrap'),
      chatSceneImg:     id('chat-scene-bg'),
      chatViewTabs:     id('chat-view-tabs'),
    };
    this._subscribe();
    this.setupChatLog();
    this.setupChannelTabs();
    this.setupSessionViewTabs();
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
  }

  _subscribe() {
    this._unsubs.push(
      this.bus.on('chat:history-loaded',   (d) => this.renderRoomChatHistory(d)),
      this.bus.on('chat:message-received',  (d) => {
        if (d?.speaker && d?.message) this.appendChatLine(d.speaker, d.message, d.type || 'npc', d.options);
      }),
      this.bus.on('chat:system-message',    (d) => {
        if (d?.text) this.appendChatLine('System', d.text, 'system');
      }),
      this.bus.on('chat:turn-status-changed', () => this.syncChatTurnStatus()),
      this.bus.on('room:changed',            (d) => {
        if (d?.room) this.setChatPanelSceneBackground(d.room.imageUrl, d.room);
      }),
      this.bus.on('session:view-data', (d) => {
        if (d?.view && d?.data) this.renderSessionViewData(d.view, d.data);
      }),
    );
  }

  setChatPanelSceneBackground(imageSrc = '', room = null) {
    const chatShell = this._el.chatShell;
    if (!chatShell) {
      return;
    }

    const normalizedImageSrc = typeof imageSrc === 'string' ? imageSrc.trim() : '';
    if (!normalizedImageSrc) {
      chatShell.style.removeProperty('--chat-scene-image');
      chatShell.style.removeProperty('background-image');
      chatShell.style.removeProperty('background-position');
      chatShell.style.removeProperty('background-size');
      chatShell.style.removeProperty('background-repeat');
      chatShell.dataset.sceneReady = 'false';
      chatShell.removeAttribute('data-scene-room');
      return;
    }

    chatShell.style.setProperty('--chat-scene-image', `url(${JSON.stringify(normalizedImageSrc)})`);
    chatShell.style.backgroundImage = `linear-gradient(180deg, rgba(6, 10, 18, 0.22) 0%, rgba(6, 10, 18, 0.54) 55%, rgba(6, 10, 18, 0.72) 100%), url(${JSON.stringify(normalizedImageSrc)})`;
    chatShell.style.backgroundPosition = 'center';
    chatShell.style.backgroundSize = 'cover';
    chatShell.style.backgroundRepeat = 'no-repeat';
    chatShell.dataset.sceneReady = 'true';
    if (room?.name) {
      chatShell.dataset.sceneRoom = String(room.name);
    } else {
      chatShell.removeAttribute('data-scene-room');
    }
  }

  setupChatLog() {
    const form = this._el.chatForm;
    const input = this._el.chatInput;
    const log = this._el.chatLog;

    if (!form || !input || !log || form.dataset.bound === 'true') {
      return;
    }

    form.dataset.bound = 'true';
    let isSubmitting = false;

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
    
      // Prevent double submission for non-room views only.
      if (this.activeSessionView !== 'room' && isSubmitting) {
        return;
      }

      const message = input.value.trim();
      if (!message) {
        return;
      }

      // Validate message length (matches backend limit)
      if (message.length > 2000) {
        this.appendChatLine('System', 'Message too long (max 2000 characters)', 'system');
        return;
      }

      // Get context from state manager
      const campaignId = this.stateManager?.hexmap?.resolveCampaignId?.() || null;
      const roomId = this.stateManager?.hexmap?.resolveActiveRoomId?.() || null;
      const characterData = this.stateManager?.hexmap?.characterData || {};
      const characterName = characterData.name || 'You';
      const characterId = this.resolveActiveChatCharacterId();

      if (!campaignId) {
        this.appendChatLine('System', 'Unable to send message: No active campaign', 'system');
        return;
      }

      // Clear input immediately for better UX
      input.value = '';

      // Route to the correct handler based on active session view.
      if (this.activeSessionView !== 'room') {
        isSubmitting = true;
        const sendButton = this._el.chatSend;
        const originalText = sendButton?.textContent;
        if (sendButton) {
          sendButton.disabled = true;
          sendButton.textContent = 'Sending...';
        }
        try {
          this.bus.emit('user:session-message-submitted', { characterName, message, characterId });
        } catch (error) {
          console.error('Failed to send session message:', error);
          this.appendChatLine('System', `Failed to send: ${error.message}`, 'system');
          input.value = message;
        } finally {
          isSubmitting = false;
          if (sendButton) {
            sendButton.disabled = false;
            sendButton.textContent = originalText || 'Send';
          }
        }
        return;
      }

      // Emit to GameShell which handles the API call and emits chat:message-received
      this.bus.emit('user:chat-submitted', {
        message,
        speaker: characterName,
        characterId,
        campaignId,
        roomId,
        channel: this.activeChannel || 'room',
      });
    });

    // Chat history will be loaded when room becomes active
    // (via state subscription or explicit call from room change handler)
  }

  resolveActiveChatCharacterId() {
    const hexmap = this.stateManager?.hexmap || null;
    const runtimeContext = hexmap?.resolveLaunchCharacterRuntimeContext?.() || null;
    const runtimeCharacterId = Number(runtimeContext?.characterId || 0);
    if (runtimeCharacterId > 0) {
      return runtimeCharacterId;
    }

    const characterData = hexmap?.characterData || {};
    return Number(
      characterData.id
      || characterData.characterId
      || characterData.character_id
      || hexmap?.launchCharacter?.id
      || hexmap?.launchCharacter?.characterId
      || hexmap?.launchCharacter?.character_id
      || hexmap?.launchContext?.character_id
      || 0
    ) || null;
  }

  setupChannelTabs() {
    const tabContainer = this._el.chatChannelTabs;
    if (!tabContainer) return;

    tabContainer.addEventListener('click', (e) => {
      const tab = e.target.closest('.chat-channel-tab');
      if (!tab) return;

      const channelKey = tab.dataset.channel;
      if (!channelKey || channelKey === this.activeChannel) return;

      this.switchChannel(channelKey);
    });
  }

  renderChannelTabs() {
    const container = this._el.chatChannelTabs;
    if (!container) return;

    container.innerHTML = '';

    for (const [key, ch] of Object.entries(this.channels)) {
      if (!(ch.active ?? true)) continue;

      const tab = document.createElement('button');
      tab.className = 'chat-channel-tab';
      if (key === this.activeChannel) {
        tab.classList.add('chat-channel-tab--active');
      }
      tab.dataset.channel = key;
      tab.title = ch.description || ch.label || key;
      tab.textContent = ch.label || key;

      // Add close button for non-room channels.
      if (key !== 'room') {
        const close = document.createElement('span');
        close.className = 'chat-channel-tab__close';
        close.textContent = '\u00D7';
        close.title = 'Close channel';
        close.addEventListener('click', (e) => {
          e.stopPropagation();
          this.closeChannel(key);
        });
        tab.appendChild(close);
      }

      container.appendChild(tab);
    }
  }

  resolvePinnedChatRoomId() {
    if (typeof window !== 'undefined' && window.location?.search) {
      const urlRoomId = String(new URLSearchParams(window.location.search).get('room_id') || '').trim();
      if (urlRoomId) {
        return urlRoomId;
      }
    }

    const launchRoomId = String(this.stateManager?.hexmap?.launchContext?.room_id || '').trim();
    if (launchRoomId) {
      return launchRoomId;
    }

    return this.stateManager?.hexmap?.resolveActiveRoomId?.() || null;
  }

  resolvePinnedChatRoomTarget(preferredRoomId = null, fallbackRoomId = null) {
    const preferred = String(preferredRoomId || '').trim();
    if (preferred) {
      return preferred;
    }

    const pinned = this.resolvePinnedChatRoomId();
    if (pinned) {
      return pinned;
    }

    const fallback = String(fallbackRoomId || '').trim();
    return fallback || null;
  }

  getChatContext() {
    const campaignId = this.stateManager?.hexmap?.resolveCampaignId?.() || null;
    const roomId = this.resolvePinnedChatRoomId();
    const characterId = this.resolveActiveChatCharacterId();

    return {
      campaignId,
      roomId,
      characterId,
    };
  }

  isChatCacheFresh(entry) {
    return Boolean(entry && (Date.now() - entry.storedAt) < this.chatCacheTtlMs);
  }

  setCachedChatPayload(store, key, payload) {
    if (!key) {
      return payload;
    }
    store.set(key, {
      storedAt: Date.now(),
      payload,
    });
    return payload;
  }

  getCachedChatPayload(store, key) {
    if (!key) {
      return null;
    }
    const entry = store.get(key);
    return this.isChatCacheFresh(entry) ? entry.payload : null;
  }

  buildRoomChatCacheKey(context = null, channelKey = null) {
    const resolved = context || this.getChatContext();
    if (!resolved.campaignId || !resolved.roomId) {
      return '';
    }
    return [
      'room',
      resolved.campaignId,
      resolved.roomId,
      resolved.characterId || 0,
      channelKey || this.activeChannel || 'room',
    ].join(':');
  }

  buildSessionViewCacheKey(view, context = null) {
    const resolved = context || this.getChatContext();
    if (!resolved.campaignId || !view || view === 'room') {
      return '';
    }

    switch (view) {
      case 'narrative':
        if (!resolved.characterId || !resolved.roomId) {
          return '';
        }
        return ['session', view, resolved.campaignId, resolved.characterId, resolved.roomId].join(':');

      case 'gm-private':
        if (!resolved.characterId) {
          return '';
        }
        return ['session', view, resolved.campaignId, resolved.characterId].join(':');

      case 'party':
      case 'system-log':
        return ['session', view, resolved.campaignId].join(':');

      default:
        return '';
    }
  }

  buildChatViewStateKey(view = this.activeSessionView, context = null, channelKey = null) {
    const resolved = context || this.getChatContext();
    if (!resolved.campaignId || !view) {
      return '';
    }

    if (view === 'room') {
      if (!resolved.roomId) {
        return '';
      }
      return [
        'view',
        'room',
        resolved.campaignId,
        resolved.roomId,
        resolved.characterId || 0,
        channelKey || this.activeChannel || 'room',
      ].join(':');
    }

    return this.buildSessionViewCacheKey(view, resolved);
  }

  buildChatLineContentKey(line = {}) {
    const normalized = this.normalizeChatLineRecord(line);
    return [
      normalized.speaker,
      normalized.type,
      normalized.message,
    ].join('|');
  }

  buildChatLineExactKey(line = {}) {
    const normalized = this.normalizeChatLineRecord(line);
    if (normalized.messageId) {
      return `message:${normalized.messageId}`;
    }
    if (normalized.sourceMessageId) {
      return `source:${normalized.sourceMessageId}`;
    }
    if (normalized.lineId) {
      return `line:${normalized.lineId}`;
    }
    return `content:${this.buildChatLineContentKey(normalized)}`;
  }

  normalizeChatLineRecord(line = {}) {
    return {
      speaker: String(line.speaker || ''),
      message: String(line.message || ''),
      type: String(line.type || 'npc'),
      transient: Boolean(line.transient),
      lineId: String(line.lineId || ''),
      messageId: Number.isFinite(Number(line.messageId)) ? Number(line.messageId) : null,
      sourceMessageId: Number.isFinite(Number(line.sourceMessageId)) ? Number(line.sourceMessageId) : null,
      created: Number.isFinite(Number(line.created)) ? Number(line.created) : 0,
    };
  }

  mergeChatLineRecord(existing = {}, incoming = {}) {
    const base = this.normalizeChatLineRecord(existing);
    const next = this.normalizeChatLineRecord(incoming);
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
      created: next.created || base.created || 0,
    };
  }

  mergeRememberedChatLines(existingLines = [], incomingLines = []) {
    const merged = (Array.isArray(existingLines) ? existingLines : [])
      .map((line) => this.normalizeChatLineRecord(line))
      .filter((line) => line.message !== '');

    (Array.isArray(incomingLines) ? incomingLines : []).forEach((line) => {
      const normalized = this.normalizeChatLineRecord(line);
      if (!normalized.message) {
        return;
      }

      const exactKey = this.buildChatLineExactKey(normalized);
      const exactIndex = merged.findIndex((candidate) => this.buildChatLineExactKey(candidate) === exactKey);
      if (exactIndex !== -1) {
        merged[exactIndex] = this.mergeChatLineRecord(merged[exactIndex], normalized);
        return;
      }

      const contentKey = this.buildChatLineContentKey(normalized);
      const contentIndex = merged.findIndex((candidate) => {
        if (candidate.transient || normalized.transient) {
          return false;
        }
        return this.buildChatLineContentKey(candidate) === contentKey;
      });
      if (contentIndex !== -1) {
        merged[contentIndex] = this.mergeChatLineRecord(merged[contentIndex], normalized);
        return;
      }

      merged.push(normalized);
    });

    return merged.filter((line) => !line.transient && line.message !== '');
  }

  getRememberedChatLines(view = this.activeSessionView, options = {}) {
    const key = this.buildChatViewStateKey(view, options.context, options.channelKey);
    if (!key) {
      return [];
    }
    return this.chatViewStateCache.get(key) || [];
  }

  rememberChatLines(view = this.activeSessionView, lines = [], options = {}) {
    const key = this.buildChatViewStateKey(view, options.context, options.channelKey);
    if (!key) {
      return [];
    }
    const existing = options.replace ? [] : (this.chatViewStateCache.get(key) || []);
    const merged = this.mergeRememberedChatLines(existing, lines);
    this.chatViewStateCache.set(key, merged);
    return merged;
  }

  syncCurrentChatViewState(view = this.activeSessionView, options = {}) {
    const lines = this.collectRenderedChatMessages();
    this.rememberChatLines(view, lines, {
      ...options,
      replace: true,
    });
  }

  invalidateChatCaches({ room = false, sessionViews = [] } = {}) {
    if (room) {
      const context = this.getChatContext();
      const roomPrefix = ['room', context.campaignId || '', context.roomId || ''].join(':');
      for (const key of this.roomChatCache.keys()) {
        if (key.startsWith(roomPrefix)) {
          this.roomChatCache.delete(key);
        }
      }
    }

    if (Array.isArray(sessionViews) && sessionViews.length > 0) {
      const context = this.getChatContext();
      for (const view of sessionViews) {
        const key = this.buildSessionViewCacheKey(view, context);
        if (key) {
          this.sessionViewCache.delete(key);
        }
      }
    }
  }

  renderRoomChatHistory(result) {
    if (!result?.success || !result.data?.messages) {
      return;
    }

    const context = this.getChatContext();
    this.rememberRoomTurnSequence(result.data?.turn_sequence || [], context, this.activeChannel);
    const incoming = result.data.messages.map((msg, index) => {
      const timestamp = String(msg.timestamp || '').trim();
      const created = timestamp !== '' ? Date.parse(timestamp) || 0 : 0;
      return {
        speaker: msg.speaker,
        message: msg.message,
        type: msg.type,
        lineId: timestamp !== '' ? `${timestamp}:${index}` : `room-history:${index}:${msg.speaker || ''}:${msg.type || ''}`,
        created,
      };
    });
    const merged = this.rememberChatLines('room', incoming, {
      context,
      channelKey: this.activeChannel,
    });
    this.renderChatLineRecords(merged, 'room', {
      context,
      channelKey: this.activeChannel,
    });

    this.updateChatSummary(merged, {
      emptyText: 'Quick summary: No one has said anything in this room yet.',
    });

    if (merged.length === 0 && result.data.messages.length === 0) {
      const roomData = this.stateManager?.hexmap?.getActiveRoomData?.() || null;
      if (roomData?.name) {
        const terrain = roomData.terrain?.type ? roomData.terrain.type.replace(/_/g, ' ') : '';
        const lighting = roomData.lighting && roomData.lighting !== 'normal' ? ` | Lighting: ${roomData.lighting}` : '';
        const size = roomData.size_category && roomData.size_category !== 'medium' ? ` | ${roomData.size_category}` : '';
        const subtitle = [terrain, lighting, size].filter(Boolean).join('').replace(/^\s*\|\s*/, '');
        const meta = subtitle ? ` (${subtitle})` : '';
        this.appendChatLine('System', `📍 ${roomData.name}${meta}`, 'system');
      }
      if (roomData?.description) {
        this.appendChatLine('System', roomData.description, 'system');
      } else {
        this.appendChatLine('System', 'Welcome to the room. Start a conversation!', 'system');
      }
      const occupantSummary = this.stateManager?.hexmap?.buildActiveRoomOccupantSummary?.() || '';
      if (occupantSummary) {
        this.appendChatLine('System', occupantSummary, 'system');
      }
    }

    this.scrollChatToBottom({ defer: true });
    this.syncChatTurnStatus();
    if (this.loadActiveRoomView) {
      const pinnedRoomId = this.resolvePinnedChatRoomTarget(context.roomId);
      if (pinnedRoomId) {
        this.loadActiveRoomView(pinnedRoomId, { force: true });
      }
    }
  }

  async loadChannels() {
    const campaignId = this.stateManager?.hexmap?.resolveCampaignId?.() || null;
    const roomId = this.stateManager?.hexmap?.resolveActiveRoomId?.() || null;
    const characterId = this.resolveActiveChatCharacterId();

    if (!campaignId || !roomId) return;

    try {
      const url = `/api/campaign/${campaignId}/room/${roomId}/channels${characterId ? '?character_id=' + characterId : ''}`;
      const response = await fetch(url);
      if (!response.ok) return;

      const result = await response.json();
      if (!result.success || !result.data) return;

      this.channels = result.data.channels || { room: { key: 'room', label: 'Room', type: 'room', active: true } };
      this.renderChannelTabs();
    } catch (err) {
      console.error('Failed to load channels:', err);
    }
  }

  async openChannel(targetEntity, targetName, sourceAbility = 'whisper') {
    const campaignId = this.stateManager?.hexmap?.resolveCampaignId?.() || null;
    const roomId = this.stateManager?.hexmap?.resolveActiveRoomId?.() || null;
    const characterId = this.resolveActiveChatCharacterId();

    if (!campaignId || !roomId) return;

    const channelKey = sourceAbility === 'whisper'
      ? `whisper:${targetEntity}`
      : `spell:${sourceAbility}:${targetEntity}`;

    try {
      const response = await fetch(`/api/campaign/${campaignId}/room/${roomId}/channels`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({
          channel_key: channelKey,
          opened_by: String(characterId),
          target_entity: targetEntity,
          target_name: targetName,
          source_ability: sourceAbility,
        }),
      });

      const result = await response.json();
      if (result.success && result.data?.channel) {
        // Add to local channels and render.
        this.channels[channelKey] = result.data.channel;
        this.renderChannelTabs();
        // Switch to the new channel.
        this.switchChannel(channelKey);
      } else {
        this.appendChatLine('System', result.data?.error || result.error || 'Unable to open channel.', 'system');
      }
    } catch (err) {
      console.error('Failed to open channel:', err);
      this.appendChatLine('System', 'Failed to open channel.', 'system');
    }
  }

  async closeChannel(channelKey) {
    if (channelKey === 'room') return;

    const campaignId = this.stateManager?.hexmap?.resolveCampaignId?.() || null;
    const roomId = this.stateManager?.hexmap?.resolveActiveRoomId?.() || null;
    if (!campaignId || !roomId) return;

    try {
      await fetch(`/api/campaign/${campaignId}/room/${roomId}/channels/${encodeURIComponent(channelKey)}`, {
        method: 'DELETE',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });

      // Remove from local state and switch to room if we were on it.
      delete this.channels[channelKey];
      this.renderChannelTabs();
      if (this.activeChannel === channelKey) {
        this.switchChannel('room');
      }
    } catch (err) {
      console.error('Failed to close channel:', err);
    }
  }

  async consumeStreamedChatResponse(response, options = {}) {
    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';
    let completeResult = null;
    let primaryReleased = false;
    const pending = options.pendingRequest || null;
    const chatTarget = pending?.target || this.buildChatRenderTarget(options.target || {
      view: 'room',
      channelKey: options.channelKey,
      context: options.context,
    });
    const releasePrimary = (payload = null) => {
      if (primaryReleased) return;
      primaryReleased = true;
      options.onPrimaryResponse?.(payload);
    };

    while (true) {
      const { value, done } = await reader.read();
      buffer += decoder.decode(value || new Uint8Array(), { stream: !done });

      let newlineIndex = buffer.indexOf('\n');
      while (newlineIndex !== -1) {
        const line = buffer.slice(0, newlineIndex).trim();
        buffer = buffer.slice(newlineIndex + 1);

        if (line) {
          let event;
          try {
            event = JSON.parse(line);
          } catch (error) {
            console.warn('Skipping invalid streamed chat event', error);
            event = null;
          }

          if (event) {
            if (event.type === 'player_ack' && event.data) {
              if (pending) {
                const playerLine = this.isChatTargetVisible(pending.target) ? this.findChatLineById(pending.playerLineId) : null;
                if (playerLine) {
                  playerLine.classList.remove('chat-line--pending');
                  playerLine.dataset.transient = '0';
                }
              } else {
                this.appendChatLineToTarget(chatTarget, event.data.speaker || 'You', event.data.message || '', event.data.type || 'player');
              }
            } else if (event.type === 'thinking' && event.data) {
              const thinkingSpeaker = event.data.phase === 'npc-reactions'
                ? 'Narrator'
                : (event.data.speaker || '');
              this.updatePendingChatProgress(
                pending,
                event.data.message || 'Game Master is thinking...',
                event.data.phase || '',
                {
                  speaker: thinkingSpeaker,
                  role: thinkingSpeaker === 'Narrator' ? 'Narrator' : '',
                }
              );
            } else if (event.type === 'gm_response' && event.data) {
              this.renderPendingGmResponse(pending, event.data);
              releasePrimary(event.data);
            } else if (event.type === 'system_message' && event.data) {
              this.appendChatLineToTarget(chatTarget, event.data.speaker || 'System', event.data.message || '', event.data.type || 'system');
              const turnRole = String(event.data.turn_role || '').trim().toLowerCase();
              const turnName = String(event.data.turn_name || '').trim();
              if (pending && turnName && ['narrator', 'gm', 'npc'].includes(turnRole)) {
                pending.progressPhase = 'speaking';
                pending.progressSpeaker = turnName;
                pending.progressRole = turnRole === 'gm'
                  ? 'GM'
                  : (turnRole === 'narrator' ? 'Narrator' : 'NPC');
                this.syncChatTurnStatus();
              }
            } else if (event.type === 'npc_interjection' && event.data) {
              this.appendChatLineToTarget(chatTarget, event.data.speaker, event.data.message, event.data.type || 'npc');
            } else if (event.type === 'complete') {
              const questHexmap = this.stateManager?.hexmap || null;
              const questCampaignId = questHexmap?.resolveCampaignId?.() || Number(questHexmap?.launchContext?.campaign_id || 0) || null;
              const questCharacterId = Number(questHexmap?.launchContext?.character_id || 0);
              console.error('Quest journal debug: streamed complete event received', {
                campaignId: questCampaignId,
                characterId: questCharacterId,
                eventKeys: event.data && typeof event.data === 'object' ? Object.keys(event.data) : [],
              });
              completeResult = {
                success: true,
                data: event.data || {},
              };
              console.error('Quest journal debug: streamed complete payload summary', {
                campaignId: questCampaignId,
                characterId: questCharacterId,
                hasQuestUpdatesArray: Array.isArray(completeResult.data?.quest_updates),
                questUpdateCount: Array.isArray(completeResult.data?.quest_updates) ? completeResult.data.quest_updates.length : null,
                hasRefreshQuestJournal: typeof questHexmap?.refreshQuestJournalFromApi === 'function',
                hasGmResponse: Boolean(completeResult.data?.gm_response),
                hasNpcInterjections: Array.isArray(completeResult.data?.npc_interjections) ? completeResult.data.npc_interjections.length : 0,
              });
              if (Array.isArray(completeResult.data?.turn_sequence)) {
                this.rememberRoomTurnSequence(completeResult.data.turn_sequence, chatTarget.context, chatTarget.channelKey);
              }
              if (completeResult.data?.navigation?.target_room_id) {
                this.handleNavigationResult(completeResult.data.navigation);
              }
              let questJournalRefreshed = false;
              if (Array.isArray(completeResult.data?.quest_updates) && completeResult.data.quest_updates.length > 0) {
                console.warn('Quest journal debug: streamed room chat received quest updates', {
                  campaignId: questCampaignId,
                  characterId: questCharacterId,
                  questUpdateCount: completeResult.data.quest_updates.length,
                  questIds: completeResult.data.quest_updates.map((update) => update?.quest_id || update?.quest_key || update?.quest_name || 'unknown'),
                });
                await questHexmap?.applyQuestUpdates?.(completeResult.data.quest_updates);
                console.error('Quest journal debug: streamed quest update application finished', {
                  campaignId: questCampaignId,
                  characterId: questCharacterId,
                  questUpdateCount: completeResult.data.quest_updates.length,
                });
                questJournalRefreshed = true;
              }
              if (!questJournalRefreshed && typeof questHexmap?.refreshQuestJournalFromApi === 'function') {
                console.warn('Quest journal debug: streamed room chat had no quest updates, refreshing journal from API', {
                  campaignId: questCampaignId,
                  characterId: questCharacterId,
                });
                await questHexmap.refreshQuestJournalFromApi();
                console.error('Quest journal debug: streamed fallback journal refresh finished', {
                  campaignId: questCampaignId,
                  characterId: questCharacterId,
                });
              }
              this.settlePendingChatRequest(pending, {
                removePlayer: false,
                removePlaceholder: !Boolean(completeResult.data?.gm_response),
              });
              releasePrimary(completeResult);
            } else if (event.type === 'error') {
              console.error('[RoomChat] streamed error event', {
                requestId: pending?.requestId || null,
                roomId: pending?.roomId || chatTarget?.context?.roomId || null,
                campaignId: chatTarget?.context?.campaignId || null,
                status: Number(event.status || event?.debug?.status || 0) || null,
                error: event.error || event?.data?.error || null,
                debug: event.debug || null,
                event,
              });
              this.settlePendingChatRequest(pending, {
                removePlayer: false,
                removePlaceholder: true,
              });
              releasePrimary();
              const errorMessage = event.error || event?.data?.error || 'An error occurred';
              const debugId = String(event?.debug?.debug_id || '').trim();
              const error = new Error(debugId ? `${errorMessage} [debug ${debugId}]` : errorMessage);
              error.status = Number(event.status || event?.debug?.status || 0) || null;
              error.streamEvent = event;
              throw error;
            }
          }
        }

        newlineIndex = buffer.indexOf('\n');
      }

      if (done) {
        break;
      }
    }

    releasePrimary(completeResult);
    if (!completeResult) {
      throw new Error('Incomplete streamed chat response');
    }

    const pinnedRoomId = this.resolvePinnedChatRoomTarget(chatTarget?.context?.roomId);
    if (pinnedRoomId && this.loadActiveRoomView) {
      this.loadActiveRoomView(pinnedRoomId, { force: true, preserveExisting: true });
    }

    this.invalidateChatCaches({
      room: true,
      sessionViews: ['narrative', 'party', 'gm-private', 'system-log'],
    });
    this.logChatTimingSummary(completeResult, pending);
    this.prefetchSessionViews();
    return completeResult;
  }

  buildChatRenderTarget(options = {}) {
    const context = options.context || this.getChatContext();
    return {
      view: options.view || this.activeSessionView || 'room',
      channelKey: options.channelKey || this.activeChannel || 'room',
      context: {
        campaignId: context?.campaignId || null,
        roomId: context?.roomId || null,
        characterId: context?.characterId || null,
      },
    };
  }

  isSameChatContext(left = {}, right = {}) {
    return String(left?.campaignId || '') === String(right?.campaignId || '')
      && String(left?.roomId || '') === String(right?.roomId || '')
      && String(left?.characterId || '') === String(right?.characterId || '');
  }

  isChatTargetVisible(target = {}) {
    const normalizedTarget = this.buildChatRenderTarget(target);
    if ((normalizedTarget.view || 'room') !== this.activeSessionView) {
      return false;
    }
    if (!this.isSameChatContext(normalizedTarget.context, this.getChatContext())) {
      return false;
    }
    if ((normalizedTarget.view || 'room') !== 'room') {
      return true;
    }
    return (normalizedTarget.channelKey || 'room') === (this.activeChannel || 'room');
  }

  appendChatLineToTarget(target, speaker, message, type = 'npc', options = {}) {
    const normalizedTarget = this.buildChatRenderTarget(target);
    const lineRecord = {
      speaker: speaker || '',
      message: message || '',
      type: type || 'npc',
      transient: Boolean(options.transient),
      lineId: options.lineId || '',
      messageId: Number.isFinite(Number(options.messageId)) ? Number(options.messageId) : null,
      sourceMessageId: Number.isFinite(Number(options.sourceMessageId)) ? Number(options.sourceMessageId) : null,
      created: Number.isFinite(Number(options.created)) ? Number(options.created) : 0,
    };

    let line = null;
    if (this.isChatTargetVisible(normalizedTarget)) {
      line = this.appendChatLine(speaker, message, type, {
        ...options,
        suppressRemember: true,
      });
    }

    if (!lineRecord.transient) {
      this.rememberChatLines(normalizedTarget.view, [lineRecord], {
        context: normalizedTarget.context,
        channelKey: normalizedTarget.channelKey,
      });
    }

    return line;
  }

  appendChatLine(speaker, message, type = 'npc', options = {}) {
    const log = this._el.chatLog;
    if (!log) {
      return null;
    }

    const existingLine = options.replaceLine || (options.lineId ? this.findChatLineById(options.lineId) : null);
    if (!existingLine && !options.lineId) {
      const lastLine = log.lastElementChild;
      if (
        lastLine
        && lastLine.dataset?.speaker === (speaker || '')
        && lastLine.dataset?.message === (message || '')
        && lastLine.dataset?.type === (type || 'npc')
        && lastLine.dataset?.transient !== '1'
      ) {
        return lastLine;
      }
    }
    const line = existingLine || document.createElement('div');
    line.innerHTML = '';
    line.className = `chat-line chat-line--${type}`;
    line.classList.toggle('chat-line--pending', Boolean(options.pending));

    if (speaker) {
      const name = document.createElement('span');
      name.className = 'chat-line__speaker';
      name.textContent = `${speaker}:`;
      line.appendChild(name);
    }

    const text = document.createElement('span');
    text.textContent = message;
    line.appendChild(text);
    line.dataset.speaker = speaker || '';
    line.dataset.message = message || '';
    line.dataset.type = type || 'npc';
    if (options.lineId) {
      line.dataset.lineId = options.lineId;
    } else {
      delete line.dataset.lineId;
    }
    if (options.messageId) {
      line.dataset.messageId = String(options.messageId);
    } else {
      delete line.dataset.messageId;
    }
    if (options.sourceMessageId) {
      line.dataset.sourceMessageId = String(options.sourceMessageId);
    } else {
      delete line.dataset.sourceMessageId;
    }
    if (options.created) {
      line.dataset.created = String(options.created);
    } else {
      delete line.dataset.created;
    }
    line.dataset.transient = options.transient ? '1' : '0';

    if (!existingLine) {
      log.appendChild(line);
    }
    this.scrollChatToBottom();
    this.updateChatSummary();
    if (!options.transient && !options.suppressRemember) {
      this.syncCurrentChatViewState();
    }
    return line;
  }

  findChatLineById(lineId) {
    if (!lineId || !this._el.chatLog) {
      return null;
    }
    return Array.from(this._el.chatLog.querySelectorAll('.chat-line'))
      .find((line) => line.dataset.lineId === lineId) || null;
  }

  removeChatLineById(lineId) {
    const line = this.findChatLineById(lineId);
    if (!line) {
      return;
    }
    line.remove();
    this.updateChatSummary();
    this.syncCurrentChatViewState();
  }

  removeRememberedChatLineById(target, lineId) {
    if (!lineId) {
      return;
    }
    const normalizedTarget = this.buildChatRenderTarget(target);
    const key = this.buildChatViewStateKey(
      normalizedTarget.view,
      normalizedTarget.context,
      normalizedTarget.channelKey
    );
    if (!key) {
      return;
    }
    const existing = this.chatViewStateCache.get(key) || [];
    const filtered = existing.filter((line) => line?.lineId !== lineId);
    if (filtered.length === existing.length) {
      return;
    }
    this.chatViewStateCache.set(key, filtered);
  }

  updateQueuedChatStatus(count = 0) {
    if (count <= 0) {
      this.removeChatLineById('chat-gm-queue-status');
      return;
    }
    const label = count === 1
      ? '1 message queued for the next response turn.'
      : `${count} messages queued for the next response turn.`;
    this.appendChatLine('System', label, 'system', {
      lineId: 'chat-gm-queue-status',
      pending: true,
      transient: true,
    });
  }

  buildPendingChatRequest(requestId, speaker, message, roomId, options = {}) {
    const includePlayer = options.includePlayer !== false;
    const includePlaceholder = options.includePlaceholder !== false;
    const placeholderText = options.placeholderText || '...';
    const placeholderSpeaker = options.placeholderSpeaker || 'Game Master';
    const placeholderType = options.placeholderType || 'npc';
    const target = this.buildChatRenderTarget(options.target || {
      context: options.context,
      channelKey: options.channelKey,
      view: options.view,
    });
    const startedAt = (typeof performance !== 'undefined' && typeof performance.now === 'function')
      ? performance.now()
      : Date.now();
    const playerLineId = `chat-player-${requestId}`;
    const gmProgressLineId = `chat-gm-progress-${requestId}`;
    const gmResponseLineId = `chat-gm-${requestId}`;
    if (includePlayer) {
      this.appendChatLineToTarget(target, speaker, message, 'player', {
        lineId: playerLineId,
        pending: true,
      });
    }
    if (includePlaceholder) {
      this.appendChatLineToTarget(target, placeholderSpeaker, placeholderText, placeholderType, {
        lineId: gmProgressLineId,
        pending: true,
        transient: true,
      });
    }
    const pending = {
      requestId,
      roomId,
      startedAt,
      playerLineId: includePlayer ? playerLineId : '',
      gmProgressLineId: includePlaceholder ? gmProgressLineId : '',
      gmResponseLineId,
      target,
      placeholderSpeaker,
      placeholderType,
      progressPhase: '',
      progressText: '',
      progressSpeaker: '',
      progressRole: '',
    };
    this.pendingChatRequests.set(requestId, pending);
    this.syncChatTurnStatus();
    return pending;
  }

  settlePendingChatRequest(pending, options = {}) {
    if (!pending) {
      return;
    }
    if (options.removePlayer) {
      if (this.isChatTargetVisible(pending.target)) {
        this.removeChatLineById(pending.playerLineId);
      } else {
        this.removeRememberedChatLineById(pending.target, pending.playerLineId);
      }
    } else if (pending.playerLineId) {
      const playerLine = this.isChatTargetVisible(pending.target) ? this.findChatLineById(pending.playerLineId) : null;
      if (playerLine) {
        playerLine.classList.remove('chat-line--pending');
        playerLine.dataset.transient = '0';
      }
    }

    this.removeChatLineById(pending.gmProgressLineId);
    this.pendingChatRequests.delete(pending.requestId);
    this.syncChatTurnStatus();
  }

  updatePendingChatProgress(pending, text, phase = '', actor = {}) {
    if (!pending) {
      return;
    }
    let nextText = text || '...';
    const progressSpeaker = String(actor.speaker || pending.progressSpeaker || pending.placeholderSpeaker || 'Game Master').trim();
    const progressRole = String(actor.role || pending.progressRole || '').trim();
    if (progressSpeaker && progressSpeaker !== 'Game Master') {
      nextText = String(nextText).replace(/\bGame Master\b/g, progressSpeaker);
    }
    this.appendChatLineToTarget(
      pending.target,
      progressSpeaker || 'Game Master',
      nextText,
      pending.placeholderType || 'npc',
      {
        lineId: pending.gmProgressLineId,
        pending: true,
        transient: true,
      }
    );
    const line = this.isChatTargetVisible(pending.target) ? this.findChatLineById(pending.gmProgressLineId) : null;
    if (line) {
      if (phase) {
        line.dataset.phase = phase;
      } else {
        delete line.dataset.phase;
      }
    }
    pending.progressPhase = phase || '';
    pending.progressText = nextText;
    pending.progressSpeaker = progressSpeaker;
    pending.progressRole = progressRole;
    this.syncChatTurnStatus();
  }

  renderPendingGmResponse(pending, response) {
    if (!response) {
      return;
    }
    const visibleMessage = this.resolveVisibleGmResponseMessage(response);
    if (this.isChatTargetVisible(pending?.target || {})) {
      this.removeChatLineById(pending?.gmProgressLineId || '');
    }
    this.appendChatLineToTarget(pending?.target || null, response.speaker || 'Game Master', visibleMessage, response.type || 'npc', {
      lineId: pending?.gmResponseLineId || '',
      pending: false,
      transient: false,
    });
    if (pending) {
      pending.progressPhase = 'responding';
      pending.progressText = visibleMessage;
      pending.progressSpeaker = response.speaker || 'Game Master';
      pending.progressRole = 'GM';
      this.syncChatTurnStatus();
    }
  }

  resolveVisibleGmResponseMessage(response = {}) {
    const directMessage = String(response.message || response.text || response.narrative || response.gm_payload?.narrative || '').trim();
    if (directMessage) {
      return directMessage;
    }

    const actionNames = Array.isArray(response.mechanical_actions)
      ? response.mechanical_actions
        .map((action) => String(action?.name || action?.type || '').trim())
        .filter(Boolean)
      : [];
    if (actionNames.length) {
      return `Game Master update: resolved ${actionNames.join(', ')}.`;
    }

    return 'Game Master update: the situation shifts.';
  }

  getVisiblePendingChatRequest() {
    const pendingEntries = Array.from(this.pendingChatRequests.values());
    for (let index = pendingEntries.length - 1; index >= 0; index -= 1) {
      const pending = pendingEntries[index];
      if (pending && this.isChatTargetVisible(pending.target)) {
        return pending;
      }
    }
    return pendingEntries.length ? pendingEntries[pendingEntries.length - 1] : null;
  }

  getPendingTurnDescriptor(pending) {
    const target = pending?.target || {};
    const rawSpeaker = String(pending?.progressSpeaker || pending?.placeholderSpeaker || '').trim();
    const rawRole = String(pending?.progressRole || '').trim().toLowerCase();
    const normalizedSpeaker = rawSpeaker.toLowerCase();
    const placeholderType = String(pending?.placeholderType || '').trim().toLowerCase();

    if (rawRole === 'narrator') {
      return { role: 'Narrator', name: rawSpeaker || 'Narrator' };
    }
    if (rawRole === 'gm') {
      return { role: 'GM', name: rawSpeaker || 'Game Master' };
    }
    if (rawRole === 'npc') {
      return { role: 'NPC', name: rawSpeaker || 'NPC' };
    }
    if (target.view === 'narrative' || placeholderType === 'narrative') {
      return { role: 'Narrator', name: rawSpeaker || 'Narrator' };
    }
    if (placeholderType === 'player') {
      return { role: 'Character', name: rawSpeaker || 'Character' };
    }
    if (normalizedSpeaker === 'game master' || normalizedSpeaker === 'gm') {
      return { role: 'GM', name: rawSpeaker || 'Game Master' };
    }
    if (normalizedSpeaker === 'narrator') {
      return { role: 'Narrator', name: rawSpeaker || 'Narrator' };
    }
    if (rawSpeaker !== '') {
      return { role: 'NPC', name: rawSpeaker };
    }
    return { role: 'Narrator', name: 'Narrator' };
  }

  buildPendingTurnMeta(pending, descriptor) {
    const phase = String(pending?.progressPhase || '').trim().toLowerCase();
    if (phase === 'reviewing-room') {
      return `${descriptor.role} turn: reviewing the room and your latest input...`;
    }
    if (phase === 'updating-conversation') {
      return `${descriptor.role} turn: updating the conversation state...`;
    }
    if (phase === 'syncing-context') {
      return `${descriptor.role} turn: syncing the scene context...`;
    }
    if (phase === 'checking-reactions') {
      return `${descriptor.role} turn: checking who is active in the scene...`;
    }
    if (phase === 'drafting-response' || phase === 'thinking') {
      return `${descriptor.role} turn: preparing the scene...`;
    }
    if (phase === 'npc-reactions') {
      return `${descriptor.role} turn: acting in initiative order...`;
    }
    if (phase === 'responding' || phase === 'speaking') {
      return `${descriptor.role} turn: responding now...`;
    }
    if (phase === 'queued') {
      return `${descriptor.role} turn: queued for the next response cycle...`;
    }
    return `${descriptor.role} turn in progress.`;
  }

  rememberRoomTurnSequence(turnSequence = [], context = null, channelKey = null) {
    const targetContext = context || this.getChatContext();
    if (!targetContext?.campaignId || !targetContext?.roomId || !this.roomTurnSequenceCache) {
      return;
    }
    const cacheKey = this.buildRoomChatCacheKey(targetContext, channelKey || this.activeChannel || 'room');
    if (!Array.isArray(turnSequence) || turnSequence.length === 0) {
      this.roomTurnSequenceCache.delete(cacheKey);
      return;
    }
    this.roomTurnSequenceCache.set(cacheKey, turnSequence.map((turn) => ({ ...turn })));
  }

  getRememberedRoomTurnSequence(context = null, channelKey = null) {
    const targetContext = context || this.getChatContext();
    if (!targetContext?.campaignId || !targetContext?.roomId || !this.roomTurnSequenceCache) {
      return [];
    }
    const cacheKey = this.buildRoomChatCacheKey(targetContext, channelKey || this.activeChannel || 'room');
    const sequence = this.roomTurnSequenceCache.get(cacheKey);
    return Array.isArray(sequence) ? sequence.map((turn) => ({ ...turn })) : [];
  }

  buildChatRoundOrderLines(pending = null) {
    const target = pending?.target || null;
    const targetContext = target?.context || this.getChatContext();
    const targetChannel = target?.channelKey || this.activeChannel || 'room';
    const rememberedSequence = this.getRememberedRoomTurnSequence(targetContext, targetChannel);
    const npcTurns = rememberedSequence
      .filter((turn) => String(turn?.role || '').toLowerCase() === 'npc')
      .map((turn) => ({
        role: 'npc',
        name: String(turn?.display_name || turn?.turn_name || turn?.actor_ref || 'NPC').trim() || 'NPC',
        initiative: Number.isFinite(Number(turn?.initiative_total)) ? Number(turn.initiative_total) : null,
      }));
    const fallbackNpcTurns = npcTurns.length > 0
      ? npcTurns
      : this.buildActiveRoomNpcTurnOrder(targetContext?.roomId || null);
    const orderedTurns = [
      { role: 'narrator', name: 'Narrator', initiative: null },
      { role: 'gm', name: 'Game Master', initiative: null },
      ...(fallbackNpcTurns.length > 0 ? fallbackNpcTurns : [{ role: 'npc', name: 'NPC initiative order', initiative: null }]),
    ];
    const descriptor = pending ? this.getPendingTurnDescriptor(pending) : null;
    const activeRole = String(descriptor?.role || '').trim().toLowerCase();
    const activeName = String(descriptor?.name || '').trim().toLowerCase();

    const formatLines = (activeMatcher = null) => orderedTurns.map((turn, index) => {
      const initiativeSuffix = turn.role === 'npc' && Number.isFinite(turn.initiative)
        ? ` (initiative ${turn.initiative})`
        : '';
      const isActive = typeof activeMatcher === 'function' && activeMatcher(turn, index);
      return `Turn ${index + 1}: ${turn.name}${initiativeSuffix}${isActive ? ' - current' : ''}`;
    }).join('\n');

    return {
      currentRoundLabel: 'Current round order',
      currentRoundOrder: formatLines((turn) => {
        if (activeRole === 'gm') {
          return turn.role === 'gm';
        }
        if (activeRole === 'narrator') {
          return turn.role === 'narrator';
        }
        if (activeRole === 'npc') {
          return turn.role === 'npc' && String(turn.name || '').trim().toLowerCase() === activeName;
        }
        return false;
      }),
      nextRoundLabel: 'Next round order',
      nextRoundOrder: formatLines(),
    };
  }

  buildIdleChatTurnStatus() {
    if (this.activeSessionView !== 'room') {
      return null;
    }
    const roundOrder = this.buildChatRoundOrderLines();
    return {
      role: 'Player',
      name: 'You',
      meta: 'Player turn: write the next line or choose your next action.',
      currentRoundLabel: roundOrder.currentRoundLabel,
      currentRoundOrder: roundOrder.currentRoundOrder,
      nextRoundLabel: roundOrder.nextRoundLabel,
      nextRoundOrder: roundOrder.nextRoundOrder,
    };
  }

  setChatTurnStatus(status = null) {
    const container = this._el.chatTurnStatus;
    const roleEl = this._el.chatTurnRole;
    const nameEl = this._el.chatTurnName;
    const metaEl = this._el.chatTurnMeta;
    const currentRoundLabelEl = this._el.chatTurnCurrentRoundLabel;
    const currentRoundOrderEl = this._el.chatTurnCurrentRoundOrder;
    const nextRoundLabelEl = this._el.chatTurnNextRoundLabel;
    const nextRoundOrderEl = this._el.chatTurnNextRoundOrder;
    if (!container || !roleEl || !nameEl || !metaEl) {
      return;
    }

    if (!status) {
      container.hidden = true;
      if (this.lastChatTurnStatusKey !== 'hidden') {
        this.lastChatTurnStatusKey = 'hidden';
        console.info('[RoomChat] current turn', {
          hidden: true,
          view: this.activeSessionView,
          channel: this.activeChannel,
        });
      }
      return;
    }

    roleEl.textContent = status.role || 'System';
    nameEl.textContent = status.name || 'Unknown';
    metaEl.textContent = status.meta || 'Turn in progress.';
    if (currentRoundLabelEl) {
      currentRoundLabelEl.textContent = status.currentRoundLabel || 'Current round order';
    }
    if (currentRoundOrderEl) {
      currentRoundOrderEl.textContent = status.currentRoundOrder || '';
    }
    if (nextRoundLabelEl) {
      nextRoundLabelEl.textContent = status.nextRoundLabel || 'Next round order';
    }
    if (nextRoundOrderEl) {
      nextRoundOrderEl.textContent = status.nextRoundOrder || '';
    }
    container.hidden = false;

    const statusKey = [
      status.role || '',
      status.name || '',
      status.meta || '',
      status.currentRoundLabel || '',
      status.currentRoundOrder || '',
      status.nextRoundLabel || '',
      status.nextRoundOrder || '',
      this.activeSessionView || '',
      this.activeChannel || '',
    ].join('|');
    if (statusKey !== this.lastChatTurnStatusKey) {
      this.lastChatTurnStatusKey = statusKey;
      console.info('[RoomChat] current turn', {
        role: status.role || 'System',
        name: status.name || 'Unknown',
        meta: status.meta || 'Turn in progress.',
        view: this.activeSessionView,
        channel: this.activeChannel,
        pendingRequestId: status.pendingRequestId || null,
      });
    }
  }

  syncChatTurnStatus() {
    const pending = this.getVisiblePendingChatRequest();
    if (!pending) {
      this.setChatTurnStatus(this.buildIdleChatTurnStatus());
      return;
    }

    const descriptor = this.getPendingTurnDescriptor(pending);
    const roundOrder = this.buildChatRoundOrderLines(pending);
    this.setChatTurnStatus({
      role: descriptor.role,
      name: descriptor.name,
      meta: this.buildPendingTurnMeta(pending, descriptor),
      currentRoundLabel: roundOrder.currentRoundLabel,
      currentRoundOrder: roundOrder.currentRoundOrder,
      nextRoundLabel: roundOrder.nextRoundLabel,
      nextRoundOrder: roundOrder.nextRoundOrder,
      pendingRequestId: pending.requestId || '',
    });
  }

  normalizeResponderLookupText(value) {
    return String(value || '')
      .toLowerCase()
      .replace(/[^a-z0-9\s]/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  resolvePendingResponder(message, options = {}) {
    const channelKey = options.channelKey || this.activeChannel || 'room';
    if (channelKey !== 'room') {
      return { speaker: 'Game Master', type: 'npc' };
    }

    const normalizedMessage = this.normalizeResponderLookupText(message);
    if (normalizedMessage) {
      for (const npcName of this.getActiveRoomNpcResponderNames(options.roomId)) {
        const normalizedNpcName = this.normalizeResponderLookupText(npcName);
        if (!normalizedNpcName) {
          continue;
        }
        const matcher = new RegExp(`(^|\\s)${normalizedNpcName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}(\\s|$)`, 'i');
        if (matcher.test(normalizedMessage)) {
          return { speaker: npcName, type: 'npc' };
        }
      }
    }

    return { speaker: 'Game Master', type: 'npc' };
  }

  logChatTimingSummary(result, pending = null) {
    const timing = result?.data?.timing || null;
    const debugTrace = result?.data?.debug_trace || null;
    const elapsedMs = pending
      ? Math.round(((typeof performance !== 'undefined' && typeof performance.now === 'function')
        ? performance.now()
        : Date.now()) - pending.startedAt)
      : null;
    const totalMs = Number(timing?.total_ms ?? debugTrace?.total_ms ?? elapsedMs ?? 0);
    const gmStage = Array.isArray(debugTrace?.stages)
      ? debugTrace.stages.find((stage) => stage?.stage === 'gm.total' || stage?.stage === 'generate_gm_reply')
      : null;
    const cacheStage = Array.isArray(debugTrace?.stages)
      ? debugTrace.stages.find((stage) => stage?.stage === 'gm.response_cache')
      : null;
    const cacheHit = timing?.cache_hit ?? cacheStage?.meta?.cache_hit ?? cacheStage?.meta?.hit ?? null;
    console.info('[RoomChat] response telemetry', {
      requestId: pending?.requestId || result?.data?.client_request_id || null,
      roomId: pending?.roomId || this.stateManager?.hexmap?.resolveActiveRoomId?.() || null,
      turnLogKey: result?.data?.turn_log_key || result?.data?.turn_harness?.turn_log_key || null,
      totalMs,
      gmMs: timing?.gm_ms ?? gmStage?.duration_ms ?? null,
      cacheHit,
      stageCount: timing?.stage_count ?? (Array.isArray(debugTrace?.stages) ? debugTrace.stages.length : 0),
    });
  }

  scrollChatToBottom(options = {}) {
    const log = this._el.chatLog;
    if (!log) {
      return;
    }

    const apply = () => {
      log.scrollTop = log.scrollHeight;
    };

    apply();
    if (options.defer) {
      requestAnimationFrame(() => requestAnimationFrame(apply));
    }
  }

  collectRenderedChatMessages() {
    const log = this._el.chatLog;
    if (!log) {
      return [];
    }

    return Array.from(log.querySelectorAll('.chat-line'))
      .map((line) => this.normalizeChatLineRecord({
        transient: line.dataset.transient === '1',
        speaker: line.dataset.speaker || '',
        message: line.dataset.message || line.textContent || '',
        type: line.dataset.type || 'npc',
        lineId: line.dataset.lineId || '',
        messageId: line.dataset.messageId || null,
        sourceMessageId: line.dataset.sourceMessageId || null,
        created: line.dataset.created || 0,
      }))
      .filter((line) => !line.transient);
  }

  updateChatSummary(messages = null, options = {}) {
    const summary = this._el.chatSummary;
    if (!summary) {
      return;
    }

    const source = Array.isArray(messages) ? messages : this.collectRenderedChatMessages();
    const normalized = source
      .map((msg) => ({
        speaker: String(msg?.speaker || '').trim(),
        message: String(msg?.message || '').trim(),
        type: String(msg?.type || '').trim().toLowerCase(),
      }))
      .filter((msg) => msg.message !== '');

    if (!normalized.length) {
      summary.textContent = options.emptyText || 'Quick summary: No conversation yet.';
      return;
    }

    const conversational = normalized.filter((msg) => msg.type !== 'system');
    const focus = (conversational.length ? conversational : normalized).slice(-3);
    const snippets = focus.map((msg) => {
      const speakerLabel = msg.speaker || (msg.type === 'player' ? 'You' : 'System');
      return `${speakerLabel}: ${this.truncateChatSummaryText(msg.message, 70)}`;
    });

    summary.textContent = `Quick summary: ${snippets.join(' | ')}`;
  }

  truncateChatSummaryText(message, maxLength = 70) {
    const text = String(message || '').replace(/\s+/g, ' ').trim();
    if (text.length <= maxLength) {
      return text;
    }
    return `${text.slice(0, Math.max(0, maxLength - 1)).trimEnd()}…`;
  }

  renderSessionViewData(view, data) {
    const context = this.getChatContext();

    if (data && data.messages && data.messages.length > 0) {
      const incoming = data.messages.map((msg) => ({
        speaker: msg.speaker,
        message: msg.message,
        type: this.resolveSessionLineType(msg, view),
        messageId: msg.id || null,
        sourceMessageId: msg.source_message_id || null,
        created: msg.created || 0,
      }));
      const merged = this.rememberChatLines(view, incoming, { context });
      this.renderChatLineRecords(merged, view, { context });
      this.updateChatSummary(merged, {
        emptyText: 'Quick summary: No messages in this view yet.',
      });
    } else {
      const emptyMessages = {
        'narrative': 'Your story in this room has not yet begun...',
        'party': 'No party chatter yet. Say something!',
        'gm-private': 'No secret notes yet. Messages here go straight to the GM, and slash commands reply here too.',
        'system-log': 'No dice rolls yet.',
      };
      const remembered = this.getRememberedChatLines(view, { context });
      if (remembered.length > 0) {
        this.renderChatLineRecords(remembered, view, { context });
        this.updateChatSummary(remembered, {
          emptyText: 'Quick summary: No messages in this view yet.',
        });
      } else {
        this.renderChatLineRecords([], view, { context });
        this.appendChatLine('System', emptyMessages[view] || 'No messages.', 'system', {
          suppressRemember: true,
        });
        this.updateChatSummary([], {
          emptyText: 'Quick summary: No messages in this view yet.',
        });
      }
    }
    this.scrollChatToBottom({ defer: true });
  }

  setupSessionViewTabs() {
    const container = this._el.chatSessionTabs;
    if (!container) return;

    container.addEventListener('click', (e) => {
      const tab = e.target.closest('.session-view-tab');
      if (!tab) return;

      const view = tab.dataset.view;
      if (!view || view === this.activeSessionView) return;

      this.switchSessionView(view);
    });
  }

  resolveSessionLineType(msg, view) {
    if (msg.speaker_type === 'system') return 'system';
    if (msg.speaker_type === 'gm') return 'gm';
    if (msg.message_type === 'mechanical' || msg.message_type === 'dice_roll') return 'mechanical';
    if (view === 'gm-private') return msg.speaker_type === 'player' ? 'secret' : 'gm';
    if (view === 'narrative') return msg.speaker_type === 'player' ? 'player' : 'narrative';
    if (msg.speaker_type === 'player') return 'player';
    return 'npc';
  }

  async loadSessionViewMessages(view, options = {}) {
    // Room view uses bus to request history from GameShell
    if (view === 'room') {
      this.bus.emit('user:chat-history-requested', options);
      return;
    }

    const context = this.getChatContext();
    if (!context.campaignId) return;

    if ((view === 'narrative' || view === 'gm-private') && !context.characterId) {
      const log = this._el.chatLog;
      if (log) log.innerHTML = '';
      this.appendChatLine('System', 'No character selected.', 'system');
      return;
    }

    // Emit request; GameShell handles the fetch and emits session:view-data back
    this.bus.emit('user:session-view-requested', { view, options });
  }

}
