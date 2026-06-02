/**
 * @file systems/PlayerAutomation.js
 *
 * Automated player turn sequencing, consumable/feat execution.
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
    this._unsubs.push(
      this.bus.on('user:action-selected', (d) => {
        const key = d?.actionKey;
        if (key === 'consumable') this.executeDirectConsumable(d?.button);
        if (key === 'feat')       this.executeDirectFeat(d?.button);
      }),
    );
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

  async executeDirectConsumable(button) {
    if (!this._beginActionRailRequest(button)) {
      return;
    }

    try {
    const context = this._getActionRailContext();
    const hexmap = context.hexmap;
    const items = extractConsumableItems(context.state?.inventory || {}, context.state?.equipment || []);
    const item = items.find((entry) => String(entry.id || entry.item_id || entry.name || '') === String(button.dataset.itemId || ''));

    if (!hexmap || !context.characterId || !item) {
      return;
    }

    const actionCost = getActionRailCost(button.dataset.actionCost, 1);
    const itemLabel = item.name || 'consumable';

    if (context.encounterActive && context.actor && context.actorRef) {
      const coordinator = hexmap?.gameCoordinator || null;
      if (!coordinator?.api) {
        this._appendChatLine('System', 'Consumable actions require an active coordinator session. Refresh the room.', 'system');
        return;
      }

      const result = await coordinator.api.sendAction('consume_item', context.actorRef, {
        action_cost: actionCost,
        character_id: context.characterId,
        item,
      }, {
        stateVersion: coordinator.phaseManager?.stateVersion,
      });

      if (!result?.success) {
        this._appendChatLine('System', result?.error || result?.result?.error || `Unable to use ${itemLabel}.`, 'system');
        return;
      }

      coordinator.applyAuthoritativeUpdate?.(result);
      this._appendChatLine('System', result?.result?.summary || `${context.actorLabel} uses ${itemLabel}.`, 'system');
      hexmap.loadCharacterFromApi(context.characterId);
      this._refreshActionRail?.();
      return;
    }

    const runtimeContext = context.runtimeContext || {};
    const response = await fetch(`/api/character/${context.characterId}/inventory`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'include',
      body: JSON.stringify({
        action: 'consume',
        item,
        campaignId: runtimeContext.campaignId || null,
        instanceId: runtimeContext.instanceId || null,
      }),
    });
    const data = await response.json();
    if (!response.ok || !data.success) {
      this._appendChatLine('System', data.error || `Unable to use ${itemLabel}.`, 'system');
      return;
    }

    this._appendChatLine('System', data.actionSummary || `${context.actorLabel} uses ${itemLabel}.`, 'system');
    hexmap.loadCharacterFromApi(context.characterId);
    } finally {
      this._endActionRailRequest(button);
    }
  }

  async executeDirectFeat(button) {
    if (!this._beginActionRailRequest(button)) {
      return;
    }

    try {
    const context = this._getActionRailContext();
    const featName = button.dataset.featName || 'feat action';
    const actionCost = getActionRailCost(button.dataset.actionCost, 1);

    if (context.encounterActive && context.actor && context.hexmap && context.actorRef) {
      const coordinator = context.hexmap?.gameCoordinator || null;
      if (!coordinator?.api) {
        this._appendChatLine('System', 'Feat actions require an active coordinator session. Refresh the room.', 'system');
        return;
      }

      const result = await coordinator.api.sendAction('feat', context.actorRef, {
        action_cost: actionCost,
        feat_id: button.dataset.featId || '',
        feat_name: featName,
      }, {
        stateVersion: coordinator.phaseManager?.stateVersion,
      });

      if (!result?.success) {
        this._appendChatLine('System', result?.error || result?.result?.error || `Unable to use ${featName}.`, 'system');
        return;
      }

      coordinator.applyAuthoritativeUpdate?.(result);
      this._appendChatLine('System', result?.result?.summary || `${context.actorLabel} uses ${featName}.`, 'system');
      this._refreshActionRail?.();
      return;
    }

    const runtimeContext = context.runtimeContext || {};
    const response = await fetch(`/api/character/${context.characterId}/actions`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'include',
      body: JSON.stringify({
        actionType: 'feat',
        actionName: featName,
        summary: `${context.actorLabel} uses ${featName}.`,
        source: 'action_rail',
        payload: {
          featId: button.dataset.featId || '',
          featName,
          actionCost,
        },
        campaignId: runtimeContext.campaignId || null,
        instanceId: runtimeContext.instanceId || null,
      }),
    });
    const data = await response.json();
    if (!response.ok || !data.success) {
      this._appendChatLine('System', data.error || `Unable to use ${featName}.`, 'system');
      return;
    }

    this._appendChatLine('System', data.action?.summary || `${context.actorLabel} uses ${featName}.`, 'system');
    context.hexmap?.loadCharacterFromApi(context.characterId);
    } finally {
      this._endActionRailRequest(button);
    }
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
        removePlayer: true,
        removePlaceholder: true,
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

  _beginActionRailRequest(button) {
    return this.shell.panels.actionRail?.beginActionRailRequest(button) ?? false;
  }

  _endActionRailRequest(button) {
    this.shell.panels.actionRail?.endActionRailRequest(button);
  }

  _getActionRailContext() {
    return this.shell.panels.actionRail?.getActionRailContext() ?? {};
  }

  _refreshActionRail() {
    this.shell.panels.actionRail?.refreshActionRail?.();
  }

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
