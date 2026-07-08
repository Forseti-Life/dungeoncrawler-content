/**
 * @file panels/ChatPanel.js
 *
 * Client-side chat interface (submission + display only).
 *
 * Scope:
 * - Collect player input and submit it to server chat endpoints.
 * - Render only server-provided transcript lines and metadata.
 * - Show local UX status (loading/errors/queue state) without inventing
 *   authoritative room or turn state.
 *
 * Non-scope:
 * - Does not own turn order, round progression, actor legality, or encounter
 *   state transitions. Those are server-authoritative.
 */

import { ChatSessionApi } from '../../ChatSessionApi.js';
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
    this.pendingChatRequests = new Map();
    // Channel / multi-view state
    this.activeChannel = 'room';
    this.channels = { room: { key: 'room', label: 'Room', type: 'room', active: true } };
    // View-state caches
    this.chatViewStateCache = new Map();
    this.roomChatCache = new Map();
    this.sessionViewCache = new Map();
    this.roomChatInflight = new Map();
    this.sessionViewInflight = new Map();
    // Development default: keep all transcript lines and disable temporary/removal flows.
    this.disableTemporaryChatLines = true;
    this.chatCacheTtlMs = 15000;
    this.chatSessionApi = null;
    this.roomChatBusy = false;
    this.roomChatQueueDraining = false;
    this.roomChatDeferredMessages = [];
    this.currentRoomLabel = '';
    this._handleGameEvents = (event) => this.handleGameEvents(event);
  }

  init(dungeonData, stateManager) {
    this.dungeonData = dungeonData || {};
    this.stateManager = stateManager || {};
    const id = (k) => document.getElementById(k);
    this._el = {
      chatShell:                  id('hexmap-chat'),
      chatPanelTitle:             id('chat-panel-title'),
      chatLog:                    id('chat-log'),
      chatSummary:                id('chat-summary'),
      chatInput:                  id('chat-input'),
      chatSend:                   id('chat-send'),
      chatForm:                   id('chat-form'),
      chatChannelTabs:            id('chat-channel-tabs'),
      chatChannelIndicator:       id('chat-channel-indicator'),
      chatChannelLabel:           id('chat-channel-label'),
      chatSessionTabs:            id('chat-session-tabs'),
      chatQuickActions:           id('chat-quick-actions'),
    };
    const nullKeys = Object.entries(this._el).filter(([,v]) => !v).map(([k]) => k);
    console.log('[ChatPanel] init', { container: !!this.container, nullEl: nullKeys.length, nullKeys: nullKeys.join(',') || 'none' });
    this._subscribe();
    if (typeof window !== 'undefined') {
      window.addEventListener('dungeoncrawler:game-events', this._handleGameEvents);
    }
    this.setupChatLog();
    this.setupChannelTabs();
    this.setupSessionViewTabs();
    this.refreshChatPanelTitle();
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
    if (typeof window !== 'undefined') {
      window.removeEventListener('dungeoncrawler:game-events', this._handleGameEvents);
    }
  }

  _subscribe() {
    this._unsubs.push(
      this.bus.on('chat:history-loaded',   (d) => this.renderRoomChatHistory(d)),
      this.bus.on('chat:message-received',  (d) => this.handleBusChatMessageReceived(d)),
      this.bus.on('chat:system-message',    (d) => this.handleBusSystemMessage(d)),
      this.bus.on('room:changed', (d) => this.handleRoomChanged(d)),
      this.bus.on('session:view-data', (d) => {
        if (d?.view && d?.data) this.renderSessionViewData(d.view, d.data);
      }),
    );
  }

  handleRoomChanged(payload = {}) {
    const roomName = String(payload?.roomName || payload?.room?.name || '').trim();
    if (roomName) {
      this.currentRoomLabel = roomName;
    }
    this.refreshChatPanelTitle();
  }

  handleBusChatMessageReceived(payload = {}) {
    const line = payload?.line ?? payload;
    if (!line?.speaker || !line?.message) {
      return;
    }

    const target = this.buildChatRenderTarget({
      view: line?.view || payload?.view || 'room',
      channelKey: line?.channel || line?.channelKey || payload?.channel || payload?.channelKey || 'room',
      context: payload?.context || line?.context,
    });

    this.appendChatLineToTarget(target, line.speaker, line.message, line.type || 'npc', {
      ...line.options,
      ...line,
      suppressRemember: false,
    });
  }

  handleBusSystemMessage(payload = {}) {
    if (!payload?.text) {
      return;
    }

    const hasExplicitTarget = Boolean(
      payload?.view
      || payload?.channel
      || payload?.channelKey
      || payload?.context
    );

    if (hasExplicitTarget) {
      const target = this.buildChatRenderTarget({
        view: payload?.view || 'room',
        channelKey: payload?.channel || payload?.channelKey || 'room',
        context: payload?.context,
      });
      this.appendChatLineToTarget(target, payload.speaker || 'System', payload.text, payload.kind || 'system', {
        source: payload.source || 'system-bus',
        authority: payload.authority || 'authoritative',
        messageClass: payload.messageClass || 'authoritative_transcript',
      });
      return;
    }

    const systemTarget = this.buildChatRenderTarget({
      view: 'system-log',
      channelKey: 'system-log',
      context: payload?.context,
    });
    this.appendChatLineToTarget(systemTarget, payload.speaker || 'System', payload.text, payload.kind || 'system', {
      source: payload.source || 'local-ui',
      authority: payload.authority || 'local',
      messageClass: payload.messageClass || 'local_ui_notice',
    });
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
    
      // Prevent duplicate in-flight submissions across all chat views.
      if (isSubmitting) {
        return;
      }

      const message = input.value.trim();
      if (!message) {
        return;
      }

      // Validate message length (matches backend limit)
      if (message.length > 2000) {
        this.appendChatLine('System', 'Message too long (max 2000 characters)', 'system', {
          source: 'local-ui',
          authority: 'local',
          messageClass: 'local_ui_notice',
        });
        return;
      }

      // Get context from state manager
      const campaignId = this.stateManager?.hexmap?.resolveCampaignId?.() || null;
      const roomId = this.stateManager?.hexmap?.resolveActiveRoomId?.() || null;
      const characterData = this.stateManager?.hexmap?.characterData || {};
      const characterName = characterData.name || 'You';
      const characterId = this.resolveActiveChatCharacterId();

      if (!campaignId) {
        this.appendChatLine('System', 'Unable to send message: No active campaign', 'system', {
          source: 'local-ui',
          authority: 'local',
          messageClass: 'local_ui_notice',
        });
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
          await this.postSessionViewMessage(characterName, message, characterId);
        } catch (error) {
          console.error('Failed to send session message:', error);
          this.appendChatLine('System', `Failed to send: ${error.message}`, 'system', {
            source: 'local-ui',
            authority: 'local',
            messageClass: 'local_ui_notice',
          });
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

      isSubmitting = true;
      const sendButton = this._el.chatSend;
      const originalText = sendButton?.textContent;
      if (sendButton) {
        sendButton.disabled = true;
        sendButton.textContent = 'Sending...';
      }
      try {
        await this.submitRoomChatMessage(message, {
          speaker: characterName,
          characterId,
          campaignId,
          roomId,
          channelKey: this.activeChannel || 'room',
        });
      } catch (error) {
        if (error.message.includes('403')) {
          console.warn('Chat message send denied (permission)');
        } else if (/not your turn/i.test(error.message)) {
          this.appendChatLine('System', error.message, 'system', {
            source: 'local-ui',
            authority: 'local',
            messageClass: 'local_ui_notice',
          });
          input.value = message;
        } else {
          console.error('Failed to send chat message:', error);
          this.appendChatLine('System', `Failed to send message: ${error.message}`, 'system', {
            source: 'local-ui',
            authority: 'local',
            messageClass: 'local_ui_notice',
          });
          input.value = message;
        }
      } finally {
        isSubmitting = false;
        if (sendButton) {
          sendButton.disabled = false;
          sendButton.textContent = originalText || 'Send';
        }
      }
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

  async submitRoomChatMessage(message, options = {}) {
    const trimmedMessage = typeof message === 'string' ? message.trim() : '';
    if (!trimmedMessage) {
      throw new Error('Message is required.');
    }
    if (trimmedMessage.length > 2000) {
      throw new Error('Message too long (max 2000 characters)');
    }

    const campaignId = options.campaignId || this.stateManager?.hexmap?.resolveCampaignId?.() || null;
    const roomId = options.roomId || this.stateManager?.hexmap?.resolveActiveRoomId?.() || null;
    const characterData = this.stateManager?.hexmap?.characterData || {};
    const characterName = options.speaker || characterData.name || 'You';
    const characterId = options.characterId ?? this.resolveActiveChatCharacterId();
    const activeChannelKey = options.channelKey || 'room';

    if (!campaignId) {
      throw new Error('Unable to send message: No active campaign');
    }
    if (!roomId) {
      throw new Error('Unable to send message: No active room');
    }

    const clientRequestId = options.clientRequestId || `chat-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
    const chatTarget = this.buildChatRenderTarget({
      view: 'room',
      channelKey: activeChannelKey,
      context: {
        campaignId,
        roomId,
        characterId,
      },
    });
    const queueOnly = this.roomChatBusy || this.roomChatQueueDraining;
    if (queueOnly) {
      throw new Error('Wait for the current turn response to finish before sending another room message.');
    }
    const pendingResponder = this.resolvePendingResponder(trimmedMessage, {
      channelKey: activeChannelKey,
      roomId,
      target: chatTarget,
    });
    const pendingRequest = this.buildPendingChatRequest(clientRequestId, characterName, trimmedMessage, roomId, {
      includePlayer: true,
      includePlaceholder: false,
      placeholderSpeaker: activeChannelKey === 'room' ? 'Narrator' : pendingResponder.speaker,
      placeholderType: pendingResponder.type,
      target: chatTarget,
    });

    this.prefetchSessionViews();
    this.bus.emit('room:view-reload-requested', { roomId, force: true, preserveExisting: true });

    try {
      if (!queueOnly) {
        this.roomChatBusy = true;
      }
      if (queueOnly) {
        this.roomChatDeferredMessages.push({
          requestId: clientRequestId,
          speaker: characterName,
          message: trimmedMessage,
          roomId,
          campaignId,
          characterId,
          channel: activeChannelKey,
          pendingRequest,
          target: chatTarget,
        });
        this.updateQueuedChatStatus(this.roomChatDeferredMessages.length);
        return {
          success: true,
          data: {
            queued: true,
          },
        };
      }
      return await this.postChatMessage(campaignId, roomId, characterName, trimmedMessage, characterId, {
        clientRequestId,
        pendingRequest,
        channelKey: activeChannelKey,
        context: chatTarget.context,
        target: chatTarget,
      });
    } catch (error) {
      this.settlePendingChatRequest(pendingRequest, {
        removePlayer: false,
      });
      throw error;
    } finally {
      if (!queueOnly) {
        if (this.roomChatDeferredMessages.length > 0) {
          this.roomChatQueueDraining = true;
          this.roomChatBusy = false;
          try {
            await this.flushDeferredRoomMessages(campaignId, roomId, characterId);
          } finally {
            this.roomChatBusy = false;
            this.roomChatQueueDraining = false;
          }
        } else {
          this.roomChatBusy = false;
        }
      }
    }
  }

  async postChatMessage(campaignId, roomId, speaker, message, characterId = null, options = {}) {
    const supportsStreaming = typeof ReadableStream !== 'undefined';
    const chatTarget = this.buildChatRenderTarget(options.target || {
      view: 'room',
      channelKey: options.channelKey,
      context: options.context,
    });
    const roomChannel = (chatTarget.channelKey || 'room') === 'room';
    const shouldStream = supportsStreaming && !options.suppressGm;
    const useStreamTransport = shouldStream && !roomChannel;
    const backendRequestId = options.clientRequestId || `chat-${Date.now()}`;
    this.bus.emit('game:backend-request-start', {
      requestId: backendRequestId,
      label: 'Waiting for narrator response...',
      source: 'chat',
    });

    try {
      const response = await fetch(
        `/api/campaign/${encodeURIComponent(campaignId)}/room/${encodeURIComponent(roomId)}/chat`,
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'same-origin',
          body: JSON.stringify({
            speaker,
            message,
            type: 'player',
            character_id: characterId,
            channel: chatTarget.channelKey,
            stream: useStreamTransport,
            client_request_id: options.clientRequestId || '',
            suppress_gm: Boolean(options.suppressGm),
            continue_gm: Boolean(options.continueGm),
          }),
        }
      );

      if (!response.ok) {
        const result = await response.json().catch(() => ({}));
        const debugId = String(result?.debug?.debug_id || '').trim();
        const debugClass = String(result?.debug?.exception_class || '').trim();
        const debugMessage = String(result?.debug?.message || '').trim();
        if (result?.debug?.exception_class || result?.debug?.message) {
          console.error(`[RoomChat] JSON POST debug ${debugClass || 'Error'}: ${debugMessage || '(no message)'}`, {
            requestId: options.clientRequestId || null,
            roomId,
            campaignId,
            characterId,
            debug: result.debug,
          });
        }
        let errorText = result.error || `HTTP ${response.status}`;
        if (debugClass || debugMessage) {
          const details = [debugClass, debugMessage].filter(Boolean).join(': ');
          if (details && !errorText.includes(details)) {
            errorText += ` [${details}]`;
          }
        }
        if (debugId && !errorText.includes(debugId)) {
          errorText += ` [debug ${debugId}]`;
        }
        throw new Error(errorText);
      }

      const contentType = response.headers.get('content-type') || '';
      if (useStreamTransport && contentType.includes('application/x-ndjson') && response.body?.getReader) {
        return await this.consumeStreamedChatResponse(response, {
          ...options,
          target: chatTarget,
        });
      }

      const result = await response.json();
      if (!result.success) {
        throw new Error(result.error || 'Unknown error');
      }
      this.stateManager?.hexmap?.gameCoordinator?.applyAuthoritativeUpdate?.(result.data || result);
      const pending = options.pendingRequest || null;
      if (pending) {
        this.settlePendingChatRequest(pending, {
          removePlayer: false,
        });
      } else {
        this.appendChatLineToTarget(chatTarget, speaker, message, 'player', {
          ...(result.data?.message || {}),
          source: 'room-response',
          authority: 'authoritative',
          messageClass: 'authoritative_transcript',
        });
      }

      if (result.data?.turn_logs?.length) {
        for (const logMsg of result.data.turn_logs) {
          if (!this.shouldRenderTurnLogLine(logMsg, chatTarget)) {
            continue;
          }
          this.appendChatLineToTarget(chatTarget, logMsg.speaker || 'System', logMsg.message || '', logMsg.type || 'system', {
            ...logMsg,
            source: 'room-response',
            authority: 'authoritative',
            messageClass: 'authoritative_transcript',
          });
        }
      }

      if (result.data?.gm_response) {
        this.renderPendingGmResponse(pending, result.data.gm_response);
      } else if (!options.suppressGm && !options.continueGm) {
        await this.loadChatHistory({ force: true });
      }

      if (result.data?.npc_interjections?.length) {
        for (const npcMsg of result.data.npc_interjections) {
          const sequenceIndex = Number(npcMsg.sequence_index);
          this.appendChatLineToTarget(chatTarget, npcMsg.speaker, npcMsg.message, 'npc', {
            ...npcMsg,
            sourceMessageId: Number.isFinite(sequenceIndex) ? sequenceIndex : undefined,
            source: 'room-response',
            authority: 'authoritative',
            messageClass: 'authoritative_transcript',
          });
        }
      }

      const questHexmap = this.stateManager?.hexmap || null;
      let questJournalRefreshed = false;
      if (result.data?.quest_updates?.length) {
        await questHexmap?.applyQuestUpdates?.(result.data.quest_updates);
        questJournalRefreshed = true;
      }

      if (!questJournalRefreshed && typeof questHexmap?.refreshQuestJournalFromApi === 'function') {
        await questHexmap.refreshQuestJournalFromApi();
      }

      const completedCharacterId = Number(
        questHexmap?.launchContext?.character_id
        || questHexmap?.launchCharacter?.id
        || questHexmap?.characterData?.id
        || 0
      ) || null;
      if (completedCharacterId) {
        const inventoryContext = this.stateManager?.hexmap?.uiManager?.currentCharacterInventoryContext;
        if (inventoryContext && String(inventoryContext.characterId || '') === String(completedCharacterId)) {
          await this.stateManager?.hexmap?.uiManager?.refreshCharacterInventoryFromApi?.({
            ...inventoryContext,
            campaignId: questHexmap?.resolveCampaignId?.() || Number(questHexmap?.launchContext?.campaign_id || 0) || null,
          });
        }
        await questHexmap?.loadCharacterFromApi?.(completedCharacterId);
        await questHexmap?.refreshQuestJournalFromApi?.();
      }

      if (result.data?.navigation?.target_room_id) {
        this.handleNavigationResult(result.data.navigation);
      }

      const pinnedRoomId = this.resolvePinnedChatRoomTarget(chatTarget?.context?.roomId, roomId);
      if (pinnedRoomId) {
        this.bus.emit('room:view-reload-requested', { roomId: pinnedRoomId, force: true, preserveExisting: true });
      }

      this.invalidateChatCaches({
        room: true,
        sessionViews: ['party', 'gm-private', 'system-log'],
      });
      options.onPrimaryResponse?.(result);
      this.logChatTimingSummary(result, pending);
      this.prefetchSessionViews();
      return result;
    } finally {
      this.bus.emit('game:backend-request-end', { requestId: backendRequestId, source: 'chat' });
    }
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
    const activeRoomId = String(this.stateManager?.hexmap?.resolveActiveRoomId?.() || '').trim();
    if (activeRoomId) {
      return activeRoomId;
    }

    const launchRoomId = String(this.stateManager?.hexmap?.launchContext?.room_id || '').trim();
    if (launchRoomId) {
      return launchRoomId;
    }

    if (typeof window !== 'undefined' && window.location?.search) {
      const urlRoomId = String(new URLSearchParams(window.location.search).get('room_id') || '').trim();
      if (urlRoomId) {
        return urlRoomId;
      }
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
        this.resolveChatChannelKey(view, channelKey),
      ].join(':');
    }

    return this.buildSessionViewCacheKey(view, resolved);
  }

  resolveChatChannelKey(view = this.activeSessionView, channelKey = null) {
    const normalizedView = String(view || this.activeSessionView || 'room').trim() || 'room';
    const explicitChannel = String(channelKey || '').trim();
    if (explicitChannel) {
      return explicitChannel;
    }
    if (normalizedView === 'room') {
      return String(this.activeChannel || 'room').trim() || 'room';
    }
    return normalizedView;
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
    if (normalized.sequenceIndex) {
      return `sequence:${normalized.sequenceIndex}`;
    }
    if (normalized.lineId) {
      return `line:${normalized.lineId}`;
    }
    return `content:${this.buildChatLineContentKey(normalized)}`;
  }

  normalizeChatLineRecord(line = {}) {
    const normalizedView = String(line.view || this.activeSessionView || 'room').trim() || 'room';
    const normalizedChannel = this.resolveChatChannelKey(normalizedView, line.channel || line.channelKey);
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
    const transient = (this.disableTemporaryChatLines !== false)
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

  hasCanonicalTranscriptOrder(line = {}) {
    const normalized = this.normalizeChatLineRecord(line);
    return normalized.sequenceIndex !== null
      || normalized.eventId !== ''
      || normalized.messageId !== null
      || normalized.sourceMessageId !== null;
  }

  normalizeChatLineRecords(lines = [], options = {}) {
    return (Array.isArray(lines) ? lines : []).map((line) => this.normalizeChatLineRecord({
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
    }));
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
      console.warn('[ChatPanel] renderRoomChatHistory: bad result', { success: result?.success, hasMessages: !!result?.data?.messages });
      this._roomHistoryHasEncounterTranscript = false;
      return;
    }

    const context = this.getChatContext();
    const incoming = result.data.messages.map((msg, index) => {
      const timestamp = String(msg.timestamp || '').trim();
      const created = timestamp !== '' ? Date.parse(timestamp) || 0 : 0;
      const sequenceIndex = Number(msg.sequence_index);
      if (!Number.isInteger(sequenceIndex) || sequenceIndex <= 0) {
        throw new Error(`room-chat-history-v1 contract violation: missing valid sequence_index at message ${index}`);
      }
      return {
        speaker: msg.speaker,
        message: msg.message,
        type: msg.type,
        lineId: timestamp !== '' ? `${timestamp}:${index}` : `room-history:${index}:${msg.speaker || ''}:${msg.type || ''}`,
        sourceMessageId: sequenceIndex,
        created,
        source: 'room-history',
        authority: 'authoritative',
        messageClass: String(msg.message_class || '').trim() || 'authoritative_transcript',
        channel: this.activeChannel,
        view: 'room',
      };
    });

    const encounterPrefixRegex = /^Round\s+(?:\d+|\?)\s*:\s*Turn\s+(?:\d+|\?)\s*:\s*(?:Actor\s+)?[^:]+:/i;
    this._roomHistoryHasEncounterTranscript = incoming.some((line) => encounterPrefixRegex.test(String(line?.message || '').trim()));

    const merged = this.rememberChatLines('room', incoming, {
      context,
      channelKey: this.activeChannel,
    });
    console.log('[ChatPanel] renderRoomChatHistory:render', { incoming: incoming.length, merged: merged.length });
    this.renderChatLineRecords(merged, 'room', {
      context,
      channelKey: this.activeChannel,
    });

    this.updateChatSummary(merged, {
      emptyText: 'Quick summary: No one has said anything in this room yet.',
    });


    this.scrollChatToBottom({ defer: true });
    const pinnedRoomId = this.resolvePinnedChatRoomTarget(context.roomId);
    if (pinnedRoomId) {
      this.bus.emit('room:view-reload-requested', { roomId: pinnedRoomId, force: true });
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
        this.appendChatLine('System', result.data?.error || result.error || 'Unable to open channel.', 'system', {
          source: 'local-ui',
          authority: 'local',
          messageClass: 'local_ui_notice',
        });
      }
    } catch (err) {
      console.error('Failed to open channel:', err);
      this.appendChatLine('System', 'Failed to open channel.', 'system', {
        source: 'local-ui',
        authority: 'local',
        messageClass: 'local_ui_notice',
      });
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
                  this.appendChatLine(event.data.speaker || 'You', event.data.message || '', event.data.type || 'player', {
                    ...event.data,
                    replaceLine: playerLine,
                    lineId: pending.playerLineId,
                    pending: false,
                    transient: false,
                    source: 'room-stream',
                    authority: 'authoritative',
                    messageClass: 'authoritative_transcript',
                  });
                }
              } else {
                this.appendChatLineToTarget(chatTarget, event.data.speaker || 'You', event.data.message || '', event.data.type || 'player', {
                  ...event.data,
                  source: 'room-stream',
                  authority: 'authoritative',
                  messageClass: 'authoritative_transcript',
                });
              }
            } else if (event.type === 'thinking' && event.data) {
              const thinkingSpeaker = event.data.speaker || '';
              this.updatePendingChatProgress(
                pending,
                event.data.message || 'Game Master is thinking...',
                event.data.phase || '',
                {
                  speaker: thinkingSpeaker,
                  role: thinkingSpeaker === 'Narrator'
                    ? 'Narrator'
                    : (thinkingSpeaker === 'Initiative Order' ? 'Initiative Order' : ''),
                }
              );
            } else if (event.type === 'gm_response' && event.data) {
              this.renderPendingGmResponse(pending, event.data);
              releasePrimary(event.data);
            } else if (event.type === 'system_message' && event.data) {
              if (!this.shouldRenderTurnLogLine(event.data, chatTarget)) {
                newlineIndex = buffer.indexOf('\n');
                continue;
              }
              this.appendChatLineToTarget(chatTarget, event.data.speaker || 'System', event.data.message || '', event.data.type || 'system', {
                ...event.data,
                source: 'room-stream',
                authority: 'authoritative',
                messageClass: 'authoritative_transcript',
              });
              const turnRole = String(event.data.turn_role || '').trim().toLowerCase();
              const turnName = String(event.data.turn_name || '').trim();
              if (pending && turnName && ['narrator', 'gm', 'npc'].includes(turnRole)) {
                pending.progressPhase = 'speaking';
                pending.progressSpeaker = turnName;
                pending.progressRole = turnRole === 'gm'
                  ? 'GM'
                  : (turnRole === 'narrator' ? 'Narrator' : 'NPC');
              }
            } else if (event.type === 'npc_interjection' && event.data) {
              const sequenceIndex = Number(event.data.sequence_index);
              this.appendChatLineToTarget(chatTarget, event.data.speaker, event.data.message, event.data.type || 'npc', {
                ...event.data,
                sourceMessageId: Number.isFinite(sequenceIndex) ? sequenceIndex : undefined,
                source: 'room-stream',
                authority: 'authoritative',
                messageClass: 'authoritative_transcript',
              });
            } else if (event.type === 'complete') {
              const questHexmap = this.stateManager?.hexmap || null;
              const questCampaignId = questHexmap?.resolveCampaignId?.() || Number(questHexmap?.launchContext?.campaign_id || 0) || null;
              const questCharacterId = Number(questHexmap?.launchContext?.character_id || 0);
              console.log('[ChatPanel] Quest journal debug: streamed complete event received', {
                campaignId: questCampaignId,
                characterId: questCharacterId,
                eventKeys: event.data && typeof event.data === 'object' ? Object.keys(event.data) : [],
              });
              completeResult = {
                success: true,
                data: event.data || {},
              };
              this.stateManager?.hexmap?.gameCoordinator?.applyAuthoritativeUpdate?.(completeResult.data || completeResult);
              console.log('[ChatPanel] Quest journal debug: streamed complete payload summary', {
                campaignId: questCampaignId,
                characterId: questCharacterId,
                hasQuestUpdatesArray: Array.isArray(completeResult.data?.quest_updates),
                questUpdateCount: Array.isArray(completeResult.data?.quest_updates) ? completeResult.data.quest_updates.length : null,
                hasRefreshQuestJournal: typeof questHexmap?.refreshQuestJournalFromApi === 'function',
                hasGmResponse: Boolean(completeResult.data?.gm_response),
                hasNpcInterjections: Array.isArray(completeResult.data?.npc_interjections) ? completeResult.data.npc_interjections.length : 0,
              });
              if (completeResult.data?.navigation?.target_room_id) {
                this.handleNavigationResult(completeResult.data.navigation);
              }
              let questJournalRefreshed = false;
              if (Array.isArray(completeResult.data?.quest_updates) && completeResult.data.quest_updates.length > 0) {
                console.log('[ChatPanel] Quest journal debug: streamed room chat received quest updates', {
                  campaignId: questCampaignId,
                  characterId: questCharacterId,
                  questUpdateCount: completeResult.data.quest_updates.length,
                  questIds: completeResult.data.quest_updates.map((update) => update?.quest_id || update?.quest_key || update?.quest_name || 'unknown'),
                });
                await questHexmap?.applyQuestUpdates?.(completeResult.data.quest_updates);
                console.log('[ChatPanel] Quest journal debug: streamed quest update application finished', {
                  campaignId: questCampaignId,
                  characterId: questCharacterId,
                  questUpdateCount: completeResult.data.quest_updates.length,
                });
                questJournalRefreshed = true;
              }
              if (!questJournalRefreshed && typeof questHexmap?.refreshQuestJournalFromApi === 'function') {
                console.log('[ChatPanel] Quest journal debug: streamed room chat had no quest updates, refreshing journal from API', {
                  campaignId: questCampaignId,
                  characterId: questCharacterId,
                });
                await questHexmap.refreshQuestJournalFromApi();
                console.log('[ChatPanel] Quest journal debug: streamed fallback journal refresh finished', {
                  campaignId: questCampaignId,
                  characterId: questCharacterId,
                });
              }
              const completedCharacterId = Number(
                questHexmap?.launchContext?.character_id
                || questHexmap?.launchCharacter?.id
                || questHexmap?.characterData?.id
                || 0
              ) || null;
              if (completedCharacterId) {
                const inventoryContext = this.stateManager?.hexmap?.uiManager?.currentCharacterInventoryContext;
                if (inventoryContext && String(inventoryContext.characterId || '') === String(completedCharacterId)) {
                  await this.stateManager?.hexmap?.uiManager?.refreshCharacterInventoryFromApi?.({
                    ...inventoryContext,
                    campaignId: questCampaignId,
                  });
                }
                await questHexmap?.loadCharacterFromApi?.(completedCharacterId);
                await questHexmap?.refreshQuestJournalFromApi?.();
              }
              this.settlePendingChatRequest(pending, {
                removePlayer: false,
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
    if (pinnedRoomId) {
      this.bus.emit('room:view-reload-requested', { roomId: pinnedRoomId, force: true, preserveExisting: true });
    }

    this.invalidateChatCaches({
      room: true,
      sessionViews: ['party', 'gm-private', 'system-log'],
    });
    this.logChatTimingSummary(completeResult, pending);
    this.prefetchSessionViews();
    return completeResult;
  }

  buildChatRenderTarget(options = {}) {
    const normalizedView = String(options.view || this.activeSessionView || 'room').trim() || 'room';
    const context = options.context || this.getChatContext();
    return {
      view: normalizedView,
      channelKey: this.resolveChatChannelKey(normalizedView, options.channelKey),
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
    const lineRecord = this.normalizeChatLineRecord({
      ...options,
      speaker,
      message,
      type,
      channel: options.channel || options.channelKey || normalizedTarget.channelKey,
      view: options.view || normalizedTarget.view,
    });

    let line = null;
    if (this.isChatTargetVisible(normalizedTarget)) {
      line = this.appendChatLine(lineRecord.speaker, lineRecord.message, lineRecord.type, {
        ...options,
        ...lineRecord,
        suppressRemember: true,
      });
    }

    if (!lineRecord.transient) {
      this.rememberChatLines(normalizedTarget.view, [{
        ...lineRecord,
        channel: normalizedTarget.channelKey,
        view: normalizedTarget.view,
      }], {
        context: normalizedTarget.context,
        channelKey: normalizedTarget.channelKey,
      });
    }

    return line;
  }

  resolveMessageClassCssToken(messageClass = '') {
    return String(messageClass || '')
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9_-]+/g, '-')
      .replace(/-+/g, '-')
      .replace(/^-|-$/g, '');
  }

  appendChatLine(speaker, message, type = 'npc', options = {}) {
    const log = this._el.chatLog;
    if (!log) {
      return null;
    }

    const lineRecord = this.normalizeChatLineRecord({
      ...options,
      speaker,
      message,
      type,
    });
    const displayMessage = this.formatEncounterChatMessage(lineRecord.speaker, lineRecord.message, lineRecord.type, lineRecord);
    const hasAuthoritativeIdentity = Boolean(lineRecord.messageId || lineRecord.sourceMessageId || lineRecord.eventId);
    if (!options.replaceLine && hasAuthoritativeIdentity) {
      const existingByIdentity = Array.from(log.querySelectorAll('.chat-line')).find((candidate) => {
        const candidateMessageId = String(candidate?.dataset?.messageId || '').trim();
        const candidateSourceMessageId = String(candidate?.dataset?.sourceMessageId || '').trim();
        const candidateEventId = String(candidate?.dataset?.eventId || '').trim();
        if (lineRecord.messageId && candidateMessageId === String(lineRecord.messageId)) {
          return true;
        }
        if (lineRecord.sourceMessageId && candidateSourceMessageId === String(lineRecord.sourceMessageId)) {
          return true;
        }
        return Boolean(lineRecord.eventId && candidateEventId !== '' && candidateEventId === lineRecord.eventId);
      });
      if (existingByIdentity) {
        return existingByIdentity;
      }
    }
    const isAuthoritativeTranscript = lineRecord.authority === 'authoritative'
      && lineRecord.messageClass === 'authoritative_transcript';
    if (!options.replaceLine && !hasAuthoritativeIdentity && !lineRecord.lineId && isAuthoritativeTranscript) {
      const existingByTranscriptContent = Array.from(log.querySelectorAll('.chat-line'))
        .reverse()
        .find((candidate) => (
          candidate?.dataset?.transient !== '1'
          && String(candidate?.dataset?.speaker || '') === (lineRecord.speaker || '')
          && String(candidate?.dataset?.message || '') === (displayMessage || '')
          && String(candidate?.dataset?.type || 'npc') === (lineRecord.type || 'npc')
          && String(candidate?.dataset?.authority || '') === 'authoritative'
          && String(candidate?.dataset?.messageClass || '') === 'authoritative_transcript'
        ));
      if (existingByTranscriptContent) {
        return existingByTranscriptContent;
      }
    }
    const existingLine = options.replaceLine || (lineRecord.lineId ? this.findChatLineById(lineRecord.lineId) : null);
    if (!existingLine && !lineRecord.lineId) {
      const lastLine = log.lastElementChild;
      if (
        lastLine
        && lastLine.dataset?.speaker === (lineRecord.speaker || '')
        && lastLine.dataset?.message === (displayMessage || '')
        && lastLine.dataset?.type === (lineRecord.type || 'npc')
        && lastLine.dataset?.transient !== '1'
      ) {
        return lastLine;
      }
    }
    const line = existingLine || document.createElement('div');
    line.innerHTML = '';
    line.className = `chat-line chat-line--${lineRecord.type}`;
    line.classList.toggle('chat-line--pending', Boolean(options.pending));
    line.classList.toggle('chat-line--turn-prompt', Boolean(lineRecord.turnPrompt));
    const messageClassToken = this.resolveMessageClassCssToken(lineRecord.messageClass);
    if (messageClassToken !== '') {
      line.classList.add(`chat-line--message-class-${messageClassToken}`);
    }

    if (lineRecord.speaker) {
      const name = document.createElement('span');
      name.className = 'chat-line__speaker';
      name.textContent = `${lineRecord.speaker}:`;
      line.appendChild(name);
    }

    const text = document.createElement('span');
    text.textContent = displayMessage;
    line.appendChild(text);
    line.dataset.speaker = lineRecord.speaker || '';
    line.dataset.message = displayMessage || '';
    line.dataset.type = lineRecord.type || 'npc';
    if (lineRecord.lineId) {
      line.dataset.lineId = lineRecord.lineId;
    } else {
      delete line.dataset.lineId;
    }
    if (lineRecord.messageId) {
      line.dataset.messageId = String(lineRecord.messageId);
    } else {
      delete line.dataset.messageId;
    }
    if (lineRecord.sourceMessageId) {
      line.dataset.sourceMessageId = String(lineRecord.sourceMessageId);
    } else {
      delete line.dataset.sourceMessageId;
    }
    if (lineRecord.created) {
      line.dataset.created = String(lineRecord.created);
    } else {
      delete line.dataset.created;
    }
    line.dataset.transient = lineRecord.transient ? '1' : '0';
    line.dataset.persistent = lineRecord.persistent ? '1' : '0';
    line.dataset.source = lineRecord.source;
    line.dataset.authority = lineRecord.authority;
    line.dataset.messageClass = lineRecord.messageClass;
    line.dataset.turnPrompt = lineRecord.turnPrompt ? '1' : '0';
    line.dataset.channel = lineRecord.channel;
    line.dataset.view = lineRecord.view;
    if (lineRecord.requestId) {
      line.dataset.requestId = lineRecord.requestId;
    } else {
      delete line.dataset.requestId;
    }
    if (lineRecord.eventId) {
      line.dataset.eventId = lineRecord.eventId;
    } else {
      delete line.dataset.eventId;
    }

    if (!existingLine) {
      log.appendChild(line);
    }
    this.scrollChatToBottom();
    this.updateChatSummary();
    if (!lineRecord.transient && !options.suppressRemember) {
      this.syncCurrentChatViewState();
    }
    return line;
  }

  shouldRenderTurnLogLine(logLine = {}, target = null) {
    if (!logLine || typeof logLine !== 'object') {
      return false;
    }
    const normalizedTarget = this.buildChatRenderTarget(target || {
      view: this.activeSessionView || 'room',
      channelKey: this.activeChannel || 'room',
      context: this.getChatContext(),
    });
    if (normalizedTarget.view !== 'room') {
      return true;
    }
    if (Boolean(logLine.internal_log || logLine.internalLog)) {
      return false;
    }
    if (Boolean(logLine.turn_prompt || logLine.turnPrompt)) {
      return false;
    }
    return true;
  }

  formatEncounterChatMessage(speaker, message, type = 'npc', options = {}) {
    const rawMessage = String(message || '').trim();
    const alreadyPrefixed = /^Round\s+(?:\d+|\?)\s*:\s*Turn\s+(?:\d+|\?)\s*:\s*(?:Actor\s+)?[^:]+:/i.test(rawMessage);
    const messageClass = String(options.messageClass || '').trim().toLowerCase();
    const authority = String(options.authority || '').trim().toLowerCase();
    if (messageClass !== '' && messageClass !== 'authoritative_transcript') {
      return message || '';
    }
    if (authority !== '' && authority !== 'authoritative' && !options.encounterEvent) {
      return message || '';
    }
    if (!rawMessage || options.encounterPrefix === false || alreadyPrefixed) {
      return message || '';
    }
    const context = this.resolveEncounterChatContext(speaker, options);
    if (!context) {
      return message || '';
    }
    return `Round ${context.round}: Turn ${context.turn}: Actor ${context.actorName}: ${rawMessage}`;
  }

  resolveEncounterChatContext(speaker = '', options = {}) {
    const hexmap = this.stateManager?.hexmap || null;
    const snapshot = hexmap?.gameCoordinator?.phaseManager?.getSnapshot?.() || {};
    const gameState = hexmap?.dungeonData?.game_state || this.dungeonData?.game_state || {};
    const phase = String(snapshot.phase || gameState.phase || '').toLowerCase();
    if (phase !== 'encounter' && !options.encounterEvent) {
      return null;
    }
    const data = options.event?.data || {};

    const rawRound = Number(options.round ?? data.round ?? snapshot.round ?? gameState.round);
    const round = Number.isFinite(rawRound) && rawRound > 0 ? Math.max(0, rawRound - 1) : '?';

    const actorId = String(options.actorId || options.event?.actor || data.entity_id || snapshot.turn?.entity || gameState.turn?.entity || '').trim();
    const explicitSpeaker = String(speaker || '').trim();
    const actorName = String(options.actorName || data.actor_name || data.actor || explicitSpeaker || this.resolveEncounterActorName(actorId) || 'Narrator').trim();

    const turnIndex = Number(options.turnIndex ?? data.turn_index ?? snapshot.turn?.index ?? gameState.turn?.index);
    const turn = Number.isFinite(turnIndex) && turnIndex >= 0 ? turnIndex + 1 : '?';
    const totalTurns = Number(
      options.totalTurns
      ?? data.total_turns
      ?? (Array.isArray(gameState.initiative_order) ? gameState.initiative_order.length : NaN)
    );

    return {
      round,
      turn,
      turnIndex: Number.isFinite(turnIndex) ? turnIndex : null,
      totalTurns: Number.isFinite(totalTurns) ? totalTurns : null,
      actorId,
      actorName: actorName || 'Narrator',
    };
  }

  resolveEncounterActorName(actorId = '') {
    const id = String(actorId || '').trim();
    if (!id) {
      return '';
    }
    const hexmap = this.stateManager?.hexmap || null;
    const entities = Array.isArray(hexmap?.dungeonData?.entities)
      ? hexmap.dungeonData.entities
      : (Array.isArray(this.dungeonData?.entities) ? this.dungeonData.entities : []);
    const normalize = (value) => String(value || '').trim();
    for (const entity of entities) {
      const metadata = entity?.state?.metadata || {};
      const keys = [
        entity?.entity_instance_id,
        entity?.instance_id,
        entity?.id,
        entity?.entity_id,
        entity?.entity_ref?.content_id,
        entity?.entity_ref?.id,
        metadata.entity_ref,
        metadata.entity_id,
      ].map(normalize).filter(Boolean);
      if (!keys.includes(id)) {
        continue;
      }
      return normalize(metadata.display_name || metadata.name || entity?.display_name || entity?.name || entity?.entity_ref?.content_id || id);
    }
    const initiativeOrder = Array.isArray(hexmap?.dungeonData?.game_state?.initiative_order)
      ? hexmap.dungeonData.game_state.initiative_order
      : [];
    const participant = initiativeOrder.find((entry) => normalize(entry?.entity_id) === id || normalize(entry?.participant_ref) === id);
    return normalize(participant?.name || '');
  }

  handleGameEvents(event) {
    const events = Array.isArray(event?.detail?.events) ? event.detail.events : [];
    const characterData = this.stateManager?.hexmap?.characterData || {};
    const activeCharacterName = String(characterData?.name || '').trim();
    const normalizeName = (value) => String(value || '').trim().toLowerCase();

    for (const gameEvent of events) {
      const chatLine = this.buildEncounterEventChatLine(gameEvent);
      if (chatLine) {
        this.appendChatLineToTarget({ view: 'room', channelKey: 'room' }, chatLine.speaker, chatLine.message, chatLine.type, {
          lineId: chatLine.lineId,
          created: chatLine.created,
          encounterEvent: true,
          event: gameEvent,
          round: Number.isFinite(chatLine.round) ? chatLine.round : undefined,
          actorId: chatLine.actorId,
          actorName: chatLine.actorName,
          source: chatLine.source,
          authority: chatLine.authority,
          messageClass: chatLine.messageClass,
          eventId: chatLine.eventId,
        });
      }

      if (
        gameEvent
        && String(gameEvent.type || '').trim().toLowerCase() === 'turn_start'
        && activeCharacterName
        && normalizeName(
          chatLine?.actorName
          || gameEvent?.data?.actor_name
          || this.resolveEncounterActorName(String(gameEvent?.actor || gameEvent?.data?.entity_id || '').trim())
        ) === normalizeName(activeCharacterName)
      ) {
        const rawRound = Number(gameEvent?.data?.round);
        const fallbackRound = Number.isFinite(rawRound) ? rawRound : (Number.isFinite(chatLine?.round) ? chatLine.round : NaN);
        const promptLineId = gameEvent.id
          ? `turn-prompt-${gameEvent.id}`
          : `turn-prompt-${Number.isFinite(fallbackRound) ? fallbackRound : 'unknown'}-${normalizeName(activeCharacterName)}`;

        this.appendChatLineToTarget(
          { view: 'room', channelKey: this.activeChannel || 'room', context: this.getChatContext() },
          'System',
          `It's your turn, ${activeCharacterName}.`,
          'system',
          {
            lineId: promptLineId,
            source: 'encounter-event',
            authority: 'authoritative',
            messageClass: 'authoritative_transcript',
            encounterEvent: true,
            event: gameEvent,
            round: Number.isFinite(fallbackRound) ? fallbackRound : undefined,
            actorName: 'System',
            turn_prompt: true,
            turn_role: 'player',
            turn_name: activeCharacterName,
            eventId: String(gameEvent.id || ''),
          }
        );
      }
    }
  }

  buildEncounterEventChatLine(event = {}) {
    const type = String(event.type || '').trim();
    const data = event.data || {};
    const round = Number(data.round);
    const actorId = String(event.actor || data.entity_id || '').trim();
    const actorName = String(data.actor_name || data.actor || '').trim()
      || this.resolveEncounterActorName(actorId)
      || this.extractActorNameFromNarration(event.narration)
      || 'Narrator';
    const lineId = event.id
      ? `encounter-event-${event.id}`
      : `encounter-event-${type}-${Number.isFinite(round) ? round : 'unknown'}-${actorId || 'narrator'}-${String(event.narration || '').slice(0, 32)}`;
    const timestamp = String(event.timestamp || '').trim();
    const created = timestamp !== '' ? Date.parse(timestamp) || 0 : 0;
    if (['round_start', 'turn_start', 'choose_not_to_act', 'npc_choose_not_to_act', 'end_turn'].includes(type)) {
      return null;
    }
    if (type === 'search' && typeof event.narration === 'string' && event.narration.trim()) {
      return {
        speaker: 'Narrator',
        message: event.narration.trim(),
        type: 'gm',
        lineId,
        created,
        round,
        actorId,
        actorName,
        source: 'encounter-event',
        authority: 'authoritative',
        messageClass: 'authoritative_transcript',
        eventId: String(event.id || ''),
      };
    }
    return null;
  }

  extractActorNameFromNarration(narration = '') {
    const text = String(narration || '').trim();
    if (text === '') {
      return '';
    }
    const turnMatch = text.match(/^(.+?)'s turn begins\.$/i);
    if (turnMatch?.[1]) {
      return turnMatch[1].trim();
    }
    const endMatch = text.match(/^(.+?)\s+(?:ends their turn|chooses not to act|takes no action)\.?$/i);
    if (endMatch?.[1]) {
      return endMatch[1].trim();
    }
    return '';
  }

  findChatLineById(lineId) {
    if (!lineId || !this._el.chatLog) {
      return null;
    }
    return Array.from(this._el.chatLog.querySelectorAll('.chat-line'))
      .find((line) => line.dataset.lineId === lineId) || null;
  }

  removeChatLineById(lineId) {
    if (this.disableTemporaryChatLines !== false) {
      return;
    }
    const line = this.findChatLineById(lineId);
    if (!line) {
      return;
    }
    line.remove();
    this.updateChatSummary();
    this.syncCurrentChatViewState();
  }

  removeRememberedChatLineById(target, lineId) {
    if (this.disableTemporaryChatLines !== false) {
      return;
    }
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
      this.appendChatLine('System', 'Queued room messages delivered.', 'system', {
        lineId: 'chat-gm-queue-status',
        pending: false,
        transient: false,
        source: 'local-ui',
        authority: 'local',
        messageClass: 'local_ui_notice',
      });
      return;
    }
    const label = count === 1
      ? '1 message queued for the next response turn.'
      : `${count} messages queued for the next response turn.`;
    this.appendChatLine('System', label, 'system', {
      lineId: 'chat-gm-queue-status',
      pending: true,
      transient: false,
      source: 'local-ui',
      authority: 'local',
      messageClass: 'local_ui_notice',
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
    const encounterPrefixRegex = /^Round\s+(?:\d+|\?)\s*:\s*Turn\s+(?:\d+|\?)\s*:\s*(?:Actor\s+)?[^:]+:\s*/u;
    const trimmedPlayerMessage = String(message || '').trim();
    const pendingPrefix = speaker === 'Game Master'
      ? 'Round ?: Turn ?: Game Master: '
      : `Round ?: Turn ?: Actor ${speaker}: `;
    const pendingPlayerMessage = encounterPrefixRegex.test(trimmedPlayerMessage)
      ? message
      : `${pendingPrefix}${trimmedPlayerMessage}`;

    if (includePlayer) {
      this.appendChatLineToTarget(target, speaker, pendingPlayerMessage, 'player', {
        lineId: playerLineId,
        pending: true,
        source: 'local-ui',
        authority: 'local',
        messageClass: 'local_ui_notice',
        requestId,
      });
    }
    if (includePlaceholder) {
      this.appendChatLineToTarget(target, placeholderSpeaker, placeholderText, placeholderType, {
        lineId: gmProgressLineId,
        pending: true,
        transient: false,
        source: 'room-stream',
        authority: 'authoritative',
        messageClass: 'authoritative_progress',
        requestId,
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
      progressLineIds: includePlaceholder ? [gmProgressLineId] : [],
      progressLineCounter: includePlaceholder ? 1 : 0,
      lastProgressSignature: includePlaceholder ? `${placeholderSpeaker}::${placeholderText}` : '',
    };
    this.pendingChatRequests.set(requestId, pending);
    this.syncPendingChatIndicator?.();
    return pending;
  }

  settlePendingChatRequest(pending, options = {}) {
    if (!pending) {
      return;
    }
    const canRemoveLines = this.disableTemporaryChatLines === false;
    if (options.removePlayer && canRemoveLines) {
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
    if (Array.isArray(pending.progressLineIds) && this.isChatTargetVisible(pending.target)) {
      pending.progressLineIds.forEach((lineId) => {
        const progressLine = this.findChatLineById(lineId);
        if (!progressLine) {
          return;
        }
        progressLine.classList.remove('chat-line--pending');
        progressLine.dataset.transient = '0';
      });
    }

    this.pendingChatRequests.delete(pending.requestId);
    this.syncPendingChatIndicator?.();
  }

  syncPendingChatIndicator() {
    const log = this._el?.chatLog || null;
    if (!log) {
      return;
    }

    const hasVisiblePending = Array.from(this.pendingChatRequests.values()).some((pending) => (
      pending && this.isChatTargetVisible(pending.target)
    ));

    if (hasVisiblePending) {
      log.dataset.chatPending = '1';
    } else {
      delete log.dataset.chatPending;
    }
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
    const progressSignature = `${phase || ''}::${progressSpeaker}::${nextText}`;
    if (pending.lastProgressSignature === progressSignature) {
      pending.progressPhase = phase || '';
      pending.progressText = nextText;
      pending.progressSpeaker = progressSpeaker;
      pending.progressRole = progressRole;
      return;
    }
    const progressBaseLineId = pending.gmProgressLineId || `chat-gm-progress-${pending.requestId}`;
    const progressLineId = pending.progressLineCounter > 0
      ? `${progressBaseLineId}-${pending.progressLineCounter}`
      : progressBaseLineId;
    this.appendChatLineToTarget(
      pending.target,
      progressSpeaker || 'Game Master',
      nextText,
      pending.placeholderType || 'npc',
      {
        lineId: progressLineId,
        pending: true,
        transient: false,
        source: 'room-stream',
        authority: 'authoritative',
        messageClass: 'authoritative_progress',
        requestId: pending.requestId,
      }
    );
    const line = this.isChatTargetVisible(pending.target) ? this.findChatLineById(progressLineId) : null;
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
    pending.progressLineIds = Array.isArray(pending.progressLineIds) ? pending.progressLineIds : [];
    pending.progressLineIds.push(progressLineId);
    pending.progressLineCounter = Number.isFinite(Number(pending.progressLineCounter))
      ? Number(pending.progressLineCounter) + 1
      : 1;
    pending.lastProgressSignature = progressSignature;
  }

  renderPendingGmResponse(pending, response) {
    if (!response) {
      return;
    }
    const visibleMessage = this.resolveVisibleGmResponseMessage(response);
    this.appendChatLineToTarget(pending?.target || null, response.speaker || 'Game Master', visibleMessage, response.type || 'npc', {
      ...response,
      lineId: pending?.gmResponseLineId || '',
      pending: false,
      transient: false,
      source: 'room-stream',
      authority: 'authoritative',
      messageClass: 'authoritative_transcript',
      requestId: pending?.requestId || '',
    });
    if (pending) {
      pending.progressPhase = 'responding';
      pending.progressText = visibleMessage;
      pending.progressSpeaker = response.speaker || 'Game Master';
      pending.progressRole = 'GM';
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

  async flushDeferredRoomMessages(campaignId, roomId, characterId = null) {
    if (this.roomChatBusy || !this.roomChatDeferredMessages.length) {
      return;
    }
    const nextDeferred = this.roomChatDeferredMessages.shift() || null;
    this.updateQueuedChatStatus(this.roomChatDeferredMessages.length);
    if (!nextDeferred) {
      return;
    }

    this.roomChatBusy = true;
    const targetChannel = nextDeferred.channel || 'room';
    const targetRoomId = nextDeferred.roomId || roomId;
    const targetCampaignId = nextDeferred.campaignId || campaignId;
    const targetCharacterId = nextDeferred.characterId ?? characterId;
    const targetContext = {
      campaignId: targetCampaignId,
      roomId: targetRoomId,
      characterId: targetCharacterId,
    };
    const target = nextDeferred.target || this.buildChatRenderTarget({
      view: 'room',
      channelKey: targetChannel,
      context: targetContext,
    });
    const requestId = nextDeferred.requestId || `chat-followup-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
    const pendingRequest = nextDeferred.pendingRequest || this.buildPendingChatRequest(
      requestId,
      nextDeferred.speaker || '',
      nextDeferred.message || '',
      targetRoomId,
      {
        includePlayer: true,
        includePlaceholder: false,
        placeholderSpeaker: 'Narrator',
        placeholderType: 'npc',
        target,
      }
    );

    try {
      await this.postChatMessage(
        targetCampaignId,
        targetRoomId,
        nextDeferred.speaker || 'You',
        nextDeferred.message || '',
        targetCharacterId,
        {
          clientRequestId: requestId,
          pendingRequest,
          channelKey: targetChannel,
          context: targetContext,
          target,
        }
      );
    } catch (error) {
      console.error('Failed to send queued room turn:', error);
      this.settlePendingChatRequest(pendingRequest, {
        removePlayer: false,
      });
      this.appendChatLine('System', `Failed to send queued turn: ${error.message}`, 'system', {
        source: 'local-ui',
        authority: 'local',
        messageClass: 'local_ui_notice',
      });
    } finally {
      this.roomChatBusy = false;
      if (this.roomChatDeferredMessages.length > 0) {
        this.updateQueuedChatStatus(this.roomChatDeferredMessages.length);
        void this.flushDeferredRoomMessages(targetCampaignId, targetRoomId, targetCharacterId);
      }
    }
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
        persistent: line.dataset.persistent !== '0',
        speaker: line.dataset.speaker || '',
        message: line.dataset.message || line.textContent || '',
        type: line.dataset.type || 'npc',
        lineId: line.dataset.lineId || '',
        messageId: line.dataset.messageId || null,
        sourceMessageId: line.dataset.sourceMessageId || null,
        created: line.dataset.created || 0,
        source: line.dataset.source || '',
        authority: line.dataset.authority || '',
        messageClass: line.dataset.messageClass || '',
        channel: line.dataset.channel || '',
        view: line.dataset.view || '',
        requestId: line.dataset.requestId || '',
        eventId: line.dataset.eventId || '',
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

  renderChatLineRecords(lines = [], view = this.activeSessionView, options = {}) {
    const log = this._el.chatLog;
    console.log('[ChatPanel] renderChatLineRecords', { lineCount: lines?.length ?? 0, view, hasLog: !!log });
    if (log) {
      log.innerHTML = '';
    }

    const normalizedLines = this.normalizeChatLineRecords(lines, {
      view,
      channel: options.channelKey || (view === 'room' ? this.activeChannel : view),
    });

    normalizedLines.forEach((line, index) => {
      const requiresCanonicalOrder = line.view === 'room'
        && line.authority === 'authoritative'
        && line.messageClass === 'authoritative_transcript';
      if (requiresCanonicalOrder && !this.hasCanonicalTranscriptOrder(line)) {
        throw new Error(`room-chat-transcript-v1 contract violation: missing canonical order metadata at line ${index}`);
      }
    });

    const sortedLines = normalizedLines
      .map((line, index) => ({ ...line, __sortIndex: index }))
      .sort((a, b) => {
        const numeric = (value) => {
          const n = Number(value);
          return Number.isFinite(n) ? n : null;
        };

        const aSequenceIndex = numeric(a.sequenceIndex);
        const bSequenceIndex = numeric(b.sequenceIndex);
        if (aSequenceIndex !== null || bSequenceIndex !== null) {
          if (aSequenceIndex === null) return 1;
          if (bSequenceIndex === null) return -1;
          if (aSequenceIndex !== bSequenceIndex) return aSequenceIndex - bSequenceIndex;
        }

        const aEventId = numeric(a.eventId);
        const bEventId = numeric(b.eventId);
        if (aEventId !== null || bEventId !== null) {
          if (aEventId === null) return 1;
          if (bEventId === null) return -1;
          if (aEventId !== bEventId) return aEventId - bEventId;
        }

        const aMessageId = numeric(a.messageId);
        const bMessageId = numeric(b.messageId);
        if (aMessageId !== null || bMessageId !== null) {
          if (aMessageId === null) return 1;
          if (bMessageId === null) return -1;
          if (aMessageId !== bMessageId) return aMessageId - bMessageId;
        }

        const aSourceMessageId = numeric(a.sourceMessageId);
        const bSourceMessageId = numeric(b.sourceMessageId);
        if (aSourceMessageId !== null || bSourceMessageId !== null) {
          if (aSourceMessageId === null) return 1;
          if (bSourceMessageId === null) return -1;
          if (aSourceMessageId !== bSourceMessageId) return aSourceMessageId - bSourceMessageId;
        }

        return a.__sortIndex - b.__sortIndex;
      })
      .map(({ __sortIndex, ...line }) => line);

    sortedLines.forEach((line) => {
      this.appendChatLine(line.speaker, line.message, line.type, {
        lineId: line.lineId,
        transient: line.transient,
        persistent: line.persistent,
        messageId: line.messageId,
        sourceMessageId: line.sourceMessageId,
        created: line.created,
        source: line.source,
        authority: line.authority,
        messageClass: line.messageClass,
        channel: line.channel,
        view: line.view,
        requestId: line.requestId,
        eventId: line.eventId,
        suppressRemember: true,
      });
    });

    this.rememberChatLines(view, sortedLines, {
      context: options.context,
      channelKey: options.channelKey,
      replace: true,
    });
  }

  switchChannel(channelKey) {
    this.activeChannel = channelKey;

    const tabContainer = this._el.chatChannelTabs;
    if (tabContainer) {
      tabContainer.querySelectorAll('.chat-channel-tab').forEach((tab) => {
        tab.classList.toggle('chat-channel-tab--active', tab.dataset.channel === channelKey);
      });
    }

    const channel = this.channels[channelKey];
    let channelType = 'room';
    let indicatorIcon = '📢';
    let indicatorText = 'Room — Everyone can hear';

    if (channel && channelKey !== 'room') {
      const targetName = channel.target_name || channel.label || 'NPC';
      const ability = channel.source_ability || 'whisper';
      if (channelKey.startsWith('spell:')) {
        channelType = 'spell';
        indicatorIcon = '✨';
        indicatorText = `${channel.label || ability} — Magical link with ${targetName}`;
      } else {
        channelType = 'whisper';
        indicatorIcon = '🗣';
        indicatorText = `Whisper — Private with ${targetName}`;
      }
    }

    const indicator = this._el.chatChannelIndicator;
    if (indicator) {
      indicator.dataset.channelType = channelType;
      const iconEl = indicator.querySelector('.channel-indicator__icon');
      if (iconEl) iconEl.textContent = indicatorIcon;
    }
    const label = this._el.chatChannelLabel;
    if (label) label.textContent = indicatorText;

    const log = this._el.chatLog;
    if (log) log.dataset.channelType = channelType;

    const input = this._el.chatInput;
    if (input) {
      if (channelKey === 'room') {
        input.placeholder = 'Say something to the room...';
      } else {
        const targetName = channel?.target_name || channel?.label || 'NPC';
        input.placeholder = `${channel?.source_ability || 'Whisper'} to ${targetName}...`;
      }
    }

    if (this.activeSessionView === 'room') {
      if (typeof this.loadChatHistory === 'function') {
        this.loadChatHistory();
      } else if (typeof this.loadSessionViewMessages === 'function') {
        this.loadSessionViewMessages('room', { channelKey });
      }
    }
    this.syncPendingChatIndicator?.();
  }

  handleNavigationResult(nav) {
    if (!nav) {
      console.error('[Navigation] navigation payload not available');
      return;
    }
    this.bus.emit('navigation:apply-result', { navigation: nav, source: 'chat-panel' });
  }

  buildActiveRoomNpcTurnOrder(roomId = null) {
    const hexmap = this.stateManager?.hexmap;
    const activeRoomId = roomId || hexmap?.resolveActiveRoomId?.() || null;
    const entities = Array.isArray(hexmap?.dungeonData?.entities) ? hexmap.dungeonData.entities : [];
    const initiativeOrder = Array.isArray(hexmap?.dungeonData?.game_state?.initiative_order)
      ? hexmap.dungeonData.game_state.initiative_order
      : [];
    const roomNpcs = entities.filter((entity) => (
      (entity?.placement?.room_id || null) === activeRoomId
      && String(entity?.entity_type || '').toLowerCase() === 'npc'
    ));
    const candidateMaps = new Map();
    const normalizeName = (value) => String(value || '').trim().toLowerCase();
    roomNpcs.forEach((entity) => {
      const metadata = entity?.state?.metadata || {};
      const displayName = String(metadata.display_name || metadata.name || entity?.display_name || entity?.name || '').trim();
      const keys = [
        entity?.instance_id,
        entity?.entity_instance_id,
        entity?.id,
        entity?.entity_id,
        entity?.entity_ref?.content_id,
        entity?.entity_ref?.id,
        metadata.entity_ref,
        metadata.entity_id,
        displayName,
      ];
      keys.forEach((key) => {
        const normalizedKey = normalizeName(key);
        if (normalizedKey && !candidateMaps.has(normalizedKey)) {
          candidateMaps.set(normalizedKey, entity);
        }
      });
    });

    const orderedTurns = [];
    const seenNames = new Set();
    initiativeOrder.forEach((participant) => {
      if (!participant || typeof participant !== 'object') {
        return;
      }
      const participantRoomId = String(participant.room_id || participant?.placement?.room_id || '').trim();
      if (activeRoomId && participantRoomId && participantRoomId !== activeRoomId) {
        return;
      }
      const matchedEntity = [
        participant.entity_ref,
        participant.entity_id,
        participant.participant_ref,
        participant.name,
      ].map(normalizeName).filter(Boolean).map((key) => candidateMaps.get(key)).find(Boolean) || null;
      if (!matchedEntity) {
        return;
      }
      const metadata = matchedEntity?.state?.metadata || {};
      const displayName = String(metadata.display_name || metadata.name || matchedEntity?.display_name || matchedEntity?.name || '').trim();
      const normalizedDisplayName = normalizeName(displayName);
      if (!displayName || seenNames.has(normalizedDisplayName)) {
        return;
      }
      seenNames.add(normalizedDisplayName);
      orderedTurns.push({
        role: 'npc',
        name: displayName,
        initiative: Number.isFinite(Number(participant?.initiative_total))
          ? Number(participant.initiative_total)
          : null,
      });
    });

    roomNpcs.forEach((entity) => {
      const metadata = entity?.state?.metadata || {};
      const displayName = String(metadata.display_name || metadata.name || entity?.display_name || entity?.name || '').trim();
      const normalizedDisplayName = normalizeName(displayName);
      if (!displayName || seenNames.has(normalizedDisplayName)) {
        return;
      }
      seenNames.add(normalizedDisplayName);
      orderedTurns.push({
        role: 'npc',
        name: displayName,
        initiative: null,
      });
    });

    return orderedTurns;
  }

  getActiveRoomNpcResponderNames(roomId = null) {
    return this.buildActiveRoomNpcTurnOrder(roomId)
      .map((turn) => String(turn?.name || '').trim())
      .filter(Boolean)
      .sort((left, right) => right.length - left.length);
  }

  prefetchSessionViews(views = ['party', 'gm-private', 'system-log']) {
    views.forEach((view) => {
      if (!view || view === this.activeSessionView || view === 'room') {
        return;
      }
      if (typeof this.fetchSessionViewData !== 'function') {
        return;
      }
      void this.fetchSessionViewData(view).catch((error) => {
        console.debug(`Skipped prefetch for ${view}:`, error?.message || error);
      });
    });
  }

  switchSessionView(view) {
    this.activeSessionView = view;

    const container = this._el.chatSessionTabs || this._el.chatViewTabs;
    if (container) {
      container.querySelectorAll('.session-view-tab').forEach((tab) => {
        tab.classList.toggle('session-view-tab--active', tab.dataset.view === view);
      });
    }

    const channelTabs = this._el.chatChannelTabs;
    const channelIndicator = this._el.chatChannelIndicator;
    const quickActions = this._el.chatQuickActions;
    if (channelTabs) channelTabs.style.display = view === 'room' ? '' : 'none';
    if (channelIndicator) channelIndicator.style.display = view === 'room' ? '' : 'none';
    if (quickActions) quickActions.style.display = view === 'room' ? '' : 'none';

    const titles = {
      room: this.resolveRoomDialogueTitle(),
      party: 'Party Chat',
      'gm-private': 'GM Secret',
      'system-log': 'System',
    };
    if (this._el.chatPanelTitle) {
      this._el.chatPanelTitle.textContent = titles[view] || 'Chat';
    }

    const log = this._el.chatLog;
    if (log) {
      if (view === 'room') {
        const channelType = this.activeChannel === 'room' ? 'room'
          : this.activeChannel.startsWith('spell:') ? 'spell' : 'whisper';
        log.dataset.channelType = channelType;
        delete log.dataset.viewType;
      } else {
        delete log.dataset.channelType;
        log.dataset.viewType = view;
      }
    }

    const input = this._el.chatInput;
    const sendBtn = this._el.chatSend || this._el.chatSubmit;
    const isReadOnly = view === 'system-log';

    if (input) {
      input.disabled = isReadOnly;
      const placeholders = {
        room: 'Say something to the room...',
        party: 'Whisper to your party...',
        'gm-private': 'Message the GM directly. The GM should answer here and use /location, /room, /quests, /dungeon to resolve issues.',
        'system-log': 'System messages, dice rolls, checks, and mechanical output appear here automatically...',
      };
      input.placeholder = placeholders[view] || '';
    }
    if (sendBtn) sendBtn.disabled = isReadOnly;

    this.loadSessionViewMessages(view);
    this.syncPendingChatIndicator?.();
  }

  resolveRoomDialogueTitle() {
    const roomName = String(this.currentRoomLabel || this.resolveActiveRoomNameFromState()).trim();
    return roomName || 'Room Dialogue';
  }

  resolveActiveRoomNameFromState() {
    const hexmap = this.stateManager?.hexmap;
    const activeRoomId = String(hexmap?.resolveActiveRoomId?.() || '').trim();
    if (!activeRoomId) {
      return '';
    }
    const visualRooms = typeof hexmap?.getVisualRooms === 'function' ? hexmap.getVisualRooms() : {};
    const activeRoom = visualRooms && typeof visualRooms === 'object' ? visualRooms[activeRoomId] : null;
    return String(activeRoom?.name || activeRoom?.title || activeRoomId).trim();
  }

  refreshChatPanelTitle() {
    if (!this._el?.chatPanelTitle) {
      return;
    }
    if (this.activeSessionView !== 'room') {
      return;
    }
    this._el.chatPanelTitle.textContent = this.resolveRoomDialogueTitle();
  }

  renderSessionViewData(view, data) {
    const context = this.getChatContext();
    const incomingMessages = Array.isArray(data?.messages) ? data.messages : [];
    const isSystemLogMessage = (msg = {}) => {
      const messageType = String(msg.message_type || msg.type || '').trim().toLowerCase();
      const speakerType = String(msg.speaker_type || '').trim().toLowerCase();
      const metadata = msg?.metadata && typeof msg.metadata === 'object' ? msg.metadata : {};
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
    };
    const scopedMessages = view === 'system-log'
      ? incomingMessages.filter((msg) => isSystemLogMessage(msg))
      : incomingMessages;

    if (scopedMessages.length > 0) {
      const incoming = scopedMessages.map((msg) => ({
        speaker: msg.speaker,
        message: msg.message,
        type: this.resolveSessionLineType(msg, view),
        messageId: msg.id || null,
        sourceMessageId: msg.source_message_id || null,
        created: msg.created || 0,
        source: `session-view:${view}`,
        authority: 'authoritative',
        messageClass: String(msg?.metadata?.message_class || '').trim() || 'authoritative_transcript',
        channel: view,
        view,
      }));
      const merged = this.rememberChatLines(view, incoming, {
        context,
        replace: view === 'system-log',
      });
      this.renderChatLineRecords(merged, view, { context });
      this.updateChatSummary(merged, {
        emptyText: 'Quick summary: No messages in this view yet.',
      });
    } else {
      const emptyMessages = {
        'party': 'No party chatter yet. Say something!',
        'gm-private': 'No GM messages yet. Messages here go straight to the GM, and the GM should answer here while using tools to resolve issues.',
        'system-log': 'No system messages yet.',
      };
      if (view === 'system-log') {
        this.rememberChatLines(view, [], { context, replace: true });
      }
      const remembered = this.getRememberedChatLines(view, { context });
      if (remembered.length > 0) {
        this.renderChatLineRecords(remembered, view, { context });
        this.updateChatSummary(remembered, {
          emptyText: 'Quick summary: No messages in this view yet.',
        });
      } else {
        this.renderChatLineRecords([], view, { context });
        this.appendChatLine('System', emptyMessages[view] || 'No messages.', 'system', {
          source: 'local-ui',
          authority: 'local',
          messageClass: 'local_ui_notice',
          channel: view,
          view,
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
    if (msg.speaker_type === 'narrator') return 'narrator';
    if (msg.speaker_type === 'gm') return 'gm';
    if (msg.message_type === 'mechanical' || msg.message_type === 'dice_roll') return 'mechanical';
    if (view === 'gm-private') return msg.speaker_type === 'player' ? 'secret' : 'gm';
    if (msg.speaker_type === 'player') return 'player';
    return 'npc';
  }

  async loadSessionViewMessages(view, options = {}) {
    if (view === 'room') {
      await this.loadChatHistory(options);
      return;
    }

    const context = this.getChatContext();
    if (!context.campaignId) return;

    if (view === 'gm-private' && !context.characterId) {
      const log = this._el.chatLog;
      if (log) log.innerHTML = '';
      this.appendChatLine('System', 'No character selected.', 'system', {
        source: 'local-ui',
        authority: 'local',
        messageClass: 'local_ui_notice',
        channel: view,
        view,
      });
      return;
    }

    // Emit request; GameShell handles the fetch and emits session:view-data back
    this.bus.emit('user:session-view-requested', { view, options });
  }

  async loadChatHistory(options = {}) {
    const context = this.getChatContext();

    if (!context.campaignId || !context.roomId) {
      return;
    }

    try {
      const result = await this.fetchRoomChatHistory(options);
      if (result?.success && result.data?.messages) {
        this.renderRoomChatHistory(result);
        if ((this.activeChannel || 'room') === 'room') {
          await this.renderPersistedEncounterEventHistory();
        }
        this.prefetchSessionViews();
        this.prefetchConnectedRoomContext();
      }
    } catch (error) {
      console.error('Failed to load chat history:', error);
    }
  }

  async fetchRoomChatHistory(options = {}) {
    return this.fetchRoomChatHistoryForContext(this.getChatContext(), options);
  }

  async fetchRoomChatHistoryForContext(context, options = {}) {
    const { force = false } = options;
    const channelKey = options.channelKey || this.activeChannel || 'room';

    if (!this.roomChatCache) {
      this.roomChatCache = new Map();
    }

    if (!this.roomChatInflight) {
      this.roomChatInflight = new Map();
    }
    if (!Number.isFinite(this.chatCacheTtlMs)) {
      this.chatCacheTtlMs = 15000;
    }

    if (!context.campaignId || !context.roomId) {
      return null;
    }

    const cacheKey = this.buildRoomChatCacheKey(context, channelKey);
    if (!force) {
      const cached = this.getCachedChatPayload(this.roomChatCache, cacheKey);
      if (cached) {
        return cached;
      }
      if (this.roomChatInflight.has(cacheKey)) {
        return this.roomChatInflight.get(cacheKey);
      }
    }

    const request = (async () => {
      let url = `/api/campaign/${context.campaignId}/room/${context.roomId}/chat?channel=${encodeURIComponent(channelKey)}`;
      if (context.characterId) {
        url += `&character_id=${context.characterId}`;
      }
      const response = await fetch(url, {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
      });

      if (response.status === 403) {
        console.warn('Chat access denied for campaign:', context.campaignId);
        return null;
      }

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const result = await response.json();
      if (result?.success && result.data?.messages) {
        this.setCachedChatPayload(this.roomChatCache, cacheKey, result);
      }
      return result;
    })();

    this.roomChatInflight.set(cacheKey, request);

    try {
      return await request;
    } finally {
      if (this.roomChatInflight.get(cacheKey) === request) {
        this.roomChatInflight.delete(cacheKey);
      }
    }
  }

  async renderPersistedEncounterEventHistory() {
    const context = this.getChatContext();
    if (!context.campaignId) {
      return;
    }
    try {
      const response = await fetch(`/api/game/${encodeURIComponent(context.campaignId)}/events?since=0`, {
        method: 'GET',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      const result = await response.json();
      const events = Array.isArray(result?.events) ? result.events : [];
      if (events.length === 0) {
        return;
      }

      // If the server-provided room history already contains encounter-prefixed transcript
      // lines, avoid appending the encounter-event transcript again (double-render risk).
      if (this._roomHistoryHasEncounterTranscript) {
        const characterData = this.stateManager?.hexmap?.characterData || {};
        const activeCharacterName = String(characterData?.name || '').trim();
        const normalizeName = (value) => String(value || '').trim().toLowerCase();

        if (!activeCharacterName) {
          return;
        }

        for (const gameEvent of events) {
          if (String(gameEvent?.type || '').trim().toLowerCase() !== 'turn_start') {
            continue;
          }

          const chatLine = this.buildEncounterEventChatLine(gameEvent);
          if (!chatLine || normalizeName(chatLine.actorName) !== normalizeName(activeCharacterName)) {
            continue;
          }

          const rawRound = Number(gameEvent?.data?.round);
          const fallbackRound = Number.isFinite(rawRound) ? rawRound : (Number.isFinite(chatLine.round) ? chatLine.round : NaN);
          const promptLineId = gameEvent.id
            ? `turn-prompt-${gameEvent.id}`
            : `turn-prompt-${Number.isFinite(fallbackRound) ? fallbackRound : 'unknown'}-${normalizeName(activeCharacterName)}`;

          this.appendChatLineToTarget(
            { view: 'room', channelKey: this.activeChannel || 'room', context: this.getChatContext() },
            'System',
            `It's your turn, ${activeCharacterName}.`,
            'system',
            {
              lineId: promptLineId,
              source: 'encounter-event',
              authority: 'authoritative',
              messageClass: 'authoritative_transcript',
              encounterEvent: true,
              event: gameEvent,
              round: Number.isFinite(fallbackRound) ? fallbackRound : undefined,
              actorName: 'System',
              turn_prompt: true,
              turn_role: 'player',
              turn_name: activeCharacterName,
              eventId: String(gameEvent.id || ''),
            }
          );
        }

        return;
      }

      this.handleGameEvents({
        detail: { events },
      });
    } catch (error) {
      console.warn('[ChatPanel] Failed to render persisted encounter events:', error?.message || error);
    }
  }

  async postSessionViewMessage(speaker, message, characterId) {
    const api = this.ensureChatSessionApi();
    if (!api) return;

    try {
      switch (this.activeSessionView) {
        case 'party': {
          const partyLine = this.appendChatLine(speaker, message, 'player', {
            source: 'session-local:party',
            authority: 'local',
            messageClass: 'local_ui_notice',
            channel: 'party',
            view: 'party',
          });
          const partyResult = await api.postPartyChat(speaker, message, String(characterId || ''));
          if (partyLine && partyResult?.message_id) {
            partyLine.dataset.messageId = String(partyResult.message_id);
            this.syncCurrentChatViewState('party');
          }
          this.invalidateChatCaches({ sessionViews: ['party'] });
          break;
        }

        case 'gm-private': {
          if (!characterId) {
            this.appendChatLine('System', 'No character selected.', 'system', {
              source: 'local-ui',
              authority: 'local',
              messageClass: 'local_ui_notice',
              channel: 'gm-private',
              view: 'gm-private',
            });
            return;
          }
          const requestedRoom = parseGmRoomRequest(message);
          if (requestedRoom) {
            const originRoomId = this.stateManager?.hexmap?.resolveActiveRoomId?.() || null;
            const dungeonData = this.stateManager?.hexmap?.dungeonData || {};
            if (!originRoomId || !dungeonData?.level_id || !dungeonData?.map_id) {
              this.appendChatLine('System', 'Missing dungeon context for procedural room generation.', 'system', {
                source: 'local-ui',
                authority: 'local',
                messageClass: 'local_ui_notice',
                channel: 'gm-private',
                view: 'gm-private',
              });
              return;
            }
            this.appendChatLine(speaker, message, 'secret', {
              source: 'session-local:gm-private',
              authority: 'local',
              messageClass: 'local_ui_notice',
              channel: 'gm-private',
              view: 'gm-private',
            });
            const roomResult = await api.requestRoomGeneration({
              origin_room_id: originRoomId,
              level_id: dungeonData.level_id,
              room_type: requestedRoom.roomType,
              terrain_type: requestedRoom.terrainType,
              room_size: requestedRoom.roomSize,
              character_id: characterId,
              speaker,
              gm_private_message: message,
            });
            if (roomResult?.message) {
              this.appendChatLine('Game Master', roomResult.message, 'gm', {
                source: 'session-action',
                authority: 'authoritative',
                messageClass: 'authoritative_transcript',
                channel: 'gm-private',
                view: 'gm-private',
              });
            }
            if (roomResult?.navigation?.target_room_id) {
              this.handleNavigationResult(roomResult.navigation);
            }
            this.invalidateChatCaches({ sessionViews: ['gm-private', 'system-log'] });
            break;
          }

          const requestedQuests = parseGmQuestRequest(message);
          if (requestedQuests) {
            const roomId = this.stateManager?.hexmap?.resolveActiveRoomId?.() || null;
            if (!roomId) {
              this.appendChatLine('System', 'No active room available for quest generation.', 'system', {
                source: 'local-ui',
                authority: 'local',
                messageClass: 'local_ui_notice',
                channel: 'gm-private',
                view: 'gm-private',
              });
              return;
            }
            this.appendChatLine(speaker, message, 'secret', {
              source: 'session-local:gm-private',
              authority: 'local',
              messageClass: 'local_ui_notice',
              channel: 'gm-private',
              view: 'gm-private',
            });
            const questResult = await api.requestLocationQuests({
              room_id: roomId,
              count: requestedQuests.count,
              character_id: characterId,
              speaker,
              gm_private_message: message,
            });
            if (questResult?.message) {
              this.appendChatLine('Game Master', questResult.message, 'gm', {
                source: 'session-action',
                authority: 'authoritative',
                messageClass: 'authoritative_transcript',
                channel: 'gm-private',
                view: 'gm-private',
              });
            }
            await this.stateManager?.hexmap?.refreshQuestJournalFromApi?.();
            this.invalidateChatCaches({ sessionViews: ['gm-private', 'system-log'] });
            break;
          }

          const requestedDungeon = parseGmDungeonRequest(message);
          if (requestedDungeon) {
            this.appendChatLine(speaker, message, 'secret', {
              source: 'session-local:gm-private',
              authority: 'local',
              messageClass: 'local_ui_notice',
              channel: 'gm-private',
              view: 'gm-private',
            });
            const dungeonResult = await api.generateDungeon({
              location_x: requestedDungeon.locationX,
              location_y: requestedDungeon.locationY,
              party_level: requestedDungeon.partyLevel || 1,
              theme: requestedDungeon.theme || undefined,
              character_id: characterId,
              speaker,
              gm_private_message: message,
            });
            const dungeonName = dungeonResult?.name || dungeonResult?.data?.name || dungeonResult?.dungeon_id || 'new dungeon';
            this.appendChatLine('Game Master', `Generated dungeon site: ${dungeonName}.`, 'gm', {
              source: 'session-action',
              authority: 'authoritative',
              messageClass: 'authoritative_transcript',
              channel: 'gm-private',
              view: 'gm-private',
            });
            this.invalidateChatCaches({ sessionViews: ['gm-private', 'system-log'] });
            break;
          }

          const requestedDestination = parseGmLocationRequest(message);
          if (requestedDestination) {
            const originRoomId = this.stateManager?.hexmap?.resolveActiveRoomId?.() || null;
            if (!originRoomId) {
              this.appendChatLine('System', 'No active room available for location generation.', 'system', {
                source: 'local-ui',
                authority: 'local',
                messageClass: 'local_ui_notice',
                channel: 'gm-private',
                view: 'gm-private',
              });
              return;
            }
            this.appendChatLine(speaker, message, 'secret', {
              source: 'session-local:gm-private',
              authority: 'local',
              messageClass: 'local_ui_notice',
              channel: 'gm-private',
              view: 'gm-private',
            });
            const locationResult = await api.requestLocationGeneration({
              destination: requestedDestination,
              origin_room_id: originRoomId,
              character_id: characterId,
              speaker,
              gm_private_message: message,
            });
            if (locationResult?.message) {
              this.appendChatLine('Game Master', locationResult.message, 'gm', {
                source: 'session-action',
                authority: 'authoritative',
                messageClass: 'authoritative_transcript',
                channel: 'gm-private',
                view: 'gm-private',
              });
            }
            if (locationResult?.navigation?.target_room_id) {
              this.handleNavigationResult(locationResult.navigation);
            }
            this.invalidateChatCaches({ sessionViews: ['gm-private', 'system-log'] });
            break;
          }

          const gmPrivateLine = this.appendChatLine(speaker, message, 'secret', {
            source: 'session-local:gm-private',
            authority: 'local',
            messageClass: 'local_ui_notice',
            channel: 'gm-private',
            view: 'gm-private',
          });
          const gmPrivateResult = await api.postGmPrivate(characterId, speaker, message);
          if (gmPrivateLine && gmPrivateResult?.message_id) {
            gmPrivateLine.dataset.messageId = String(gmPrivateResult.message_id);
            this.syncCurrentChatViewState('gm-private');
          }
          this.invalidateChatCaches({ sessionViews: ['gm-private'] });
          break;
        }

        case 'system-log':
          return;
      }
    } catch (err) {
      console.error(`Failed to post to ${this.activeSessionView}:`, err);
      this.appendChatLine('System', `Failed to send: ${err.message}`, 'system', {
        source: 'local-ui',
        authority: 'local',
        messageClass: 'local_ui_notice',
        channel: this.activeSessionView,
        view: this.activeSessionView,
      });
    }

    this.prefetchSessionViews();
  }

  navigateToDungeonContext(dungeonSwitch) {
    // Chat panel delegates navigation intent to the unified runtime path.
    // It does not own routing/rule logic and must not mutate URL context.
    if (!this.bus) {
      console.error('[Navigation] event bus unavailable for dungeon switch');
      return;
    }
    this.bus.emit('user:navigate-dungeon', { dungeonSwitch });
  }

  ensureChatSessionApi() {
    const campaignId = this.stateManager?.hexmap?.resolveCampaignId?.() || null;
    if (!campaignId) return null;

    if (!this.chatSessionApi || this.chatSessionApi.campaignId !== campaignId) {
      this.chatSessionApi = new ChatSessionApi(campaignId);
    }
    return this.chatSessionApi;
  }

  async fetchSessionViewData(view, options = {}) {
    const { force = false } = options;
    const context = this.getChatContext();
    const cacheKey = this.buildSessionViewCacheKey(view, context);
    const shouldCache = view !== 'system-log';
    if (!cacheKey) {
      return null;
    }

    if (!this.sessionViewCache) {
      this.sessionViewCache = new Map();
    }
    if (!this.sessionViewInflight) {
      this.sessionViewInflight = new Map();
    }
    if (!Number.isFinite(this.chatCacheTtlMs)) {
      this.chatCacheTtlMs = 15000;
    }

    if (!force && shouldCache) {
      const cached = this.getCachedChatPayload(this.sessionViewCache, cacheKey);
      if (cached) {
        return cached;
      }
      if (this.sessionViewInflight.has(cacheKey)) {
        return this.sessionViewInflight.get(cacheKey);
      }
    }

    const api = this.ensureChatSessionApi();
    if (!api) {
      return null;
    }

    const request = (async () => {
      let data = null;

      switch (view) {
        case 'party':
          data = await api.getPartyChat({ limit: 50 });
          break;

        case 'gm-private':
          data = await api.getGmPrivate(context.characterId, { limit: 50 });
          break;

        case 'system-log':
          data = await api.getSystemLog({ limit: 100 });
          break;
      }

      if (shouldCache) {
        this.setCachedChatPayload(this.sessionViewCache, cacheKey, data || { messages: [] });
      }
      return data;
    })();

    if (shouldCache) {
      this.sessionViewInflight.set(cacheKey, request);
    }

    try {
      return await request;
    } finally {
      if (shouldCache && this.sessionViewInflight.get(cacheKey) === request) {
        this.sessionViewInflight.delete(cacheKey);
      }
    }
  }

  prefetchConnectedRoomContext(limit = 2) {
    const hexmap = this.stateManager?.hexmap;
    const campaignId = hexmap?.resolveCampaignId?.() || null;
    const currentRoomId = hexmap?.resolveActiveRoomId?.() || null;
    const characterId = Number(hexmap?.characterData?.id || 0) || null;
    const connections = typeof hexmap?.getVisualConnections === 'function'
      ? hexmap.getVisualConnections()
      : (Array.isArray(hexmap?.dungeonData?.connections) ? hexmap.dungeonData.connections : []);
    if (!campaignId || !currentRoomId || !connections.length) {
      return;
    }

    const nextRoomIds = [];
    connections.forEach((connection) => {
      if (connection?.is_passable === false) {
        return;
      }
      const fromRoomId = typeof hexmap?.getConnectionRoomId === 'function'
        ? hexmap.getConnectionRoomId(connection, 'from')
        : connection?.from_room;
      const toRoomId = typeof hexmap?.getConnectionRoomId === 'function'
        ? hexmap.getConnectionRoomId(connection, 'to')
        : connection?.to_room;
      if (fromRoomId === currentRoomId && toRoomId) {
        nextRoomIds.push(String(toRoomId));
      } else if (toRoomId === currentRoomId && fromRoomId) {
        nextRoomIds.push(String(fromRoomId));
      }
    });

    Array.from(new Set(nextRoomIds)).filter(Boolean).slice(0, limit).forEach((roomId) => {
      const context = { campaignId, roomId, characterId };
      void this.fetchRoomChatHistoryForContext(context, { channelKey: 'room' }).catch((error) => {
        console.debug(`Skipped connected-room chat warm for ${roomId}:`, error?.message || error);
      });
      this.bus.emit('room:view-prefetch-requested', { campaignId, roomId });
    });
  }

}
