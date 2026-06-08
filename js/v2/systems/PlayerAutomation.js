/**
 * @file systems/PlayerAutomation.js
 *
 * Automated player turn sequencing and deferred room messaging.
 * Methods ported verbatim from hexmap.js UIManager.
 */

export class PlayerAutomation {
  constructor(shell, bus) {
    this.shell = shell;
    this.bus = bus;
    this.stateManager = null;
    this.dungeonData = null;
    this._unsubs = [];
  }

  init(dungeonData, stateManager) {
    this.dungeonData = dungeonData || {};
    this.stateManager = stateManager || {};
    this._subscribe();
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
  }

  _subscribe() {
    // Action rail direct executions are owned by EncounterSystem.
  }

  applyInitialSectionState(footer, sections) {
    if (!footer || footer.dataset.initialStateApplied === 'true') {
      return;
    }

    const isMobile = window.matchMedia && window.matchMedia('(max-width: 900px)').matches;
    if (isMobile && sections && sections.length) {
      sections.forEach((section) => {
        section.classList.add('collapsed');
        const chevron = section.querySelector('.action-section__chevron');
        if (chevron) {
          chevron.textContent = '▸';
        }
      });
    }

    footer.dataset.initialStateApplied = 'true';
  }

  async flushDeferredRoomMessages(campaignId, roomId, characterId = null) {
    if (this.roomChatBusy || !this.roomChatDeferredMessages.length) {
      return;
    }
    const nextDeferred = this.roomChatDeferredMessages.shift() || null;
    this._updateQueuedChatStatus(this.roomChatDeferredMessages.length);
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
    const pendingRequest = nextDeferred.pendingRequest || this._buildPendingChatRequest(requestId, nextDeferred.speaker || '', nextDeferred.message || '', targetRoomId, {
      includePlayer: true,
      includePlaceholder: true,
      placeholderText: '...',
      placeholderSpeaker: 'Narrator',
      placeholderType: 'npc',
      target,
    });

    try {
      await this.postChatMessage(targetCampaignId, targetRoomId, nextDeferred.speaker || 'You', nextDeferred.message || '', targetCharacterId, {
        clientRequestId: requestId,
        pendingRequest,
        channelKey: targetChannel,
        context: targetContext,
        target,
      });
    } catch (error) {
      console.error('Failed to send queued room turn:', error);
      this._settlePendingChatRequest(pendingRequest, {
        removePlayer: false,
        removePlaceholder: false,
      });
      this._appendChatLine('System', `Failed to send queued turn: ${error.message}`, 'system');
    } finally {
      this.roomChatBusy = false;
      if (this.roomChatDeferredMessages.length > 0) {
        this._updateQueuedChatStatus(this.roomChatDeferredMessages.length);
        void this.flushDeferredRoomMessages(targetCampaignId, targetRoomId, targetCharacterId);
      }
    }
  }

  // --- Proxy helpers (UIManager methods now live on panels/bus) ---

  _appendChatLine(speaker, message, type = 'system') {
    this.bus.emit('chat:system-message', {
      text: message,
      speaker,
      kind: type,
      view: 'room',
      channel: 'room',
      source: 'player-automation',
      authority: 'authoritative',
      messageClass: 'authoritative_transcript',
    });
  }

  _buildPendingChatRequest(...args) {
    return this.shell.panels.chat?.buildPendingChatRequest?.(...args) ?? null;
  }

  _settlePendingChatRequest(...args) {
    this.shell.panels.chat?.settlePendingChatRequest?.(...args);
  }

  _updateQueuedChatStatus(count) {
    this.shell.panels.chat?.updateQueuedChatStatus?.(count);
  }

}
