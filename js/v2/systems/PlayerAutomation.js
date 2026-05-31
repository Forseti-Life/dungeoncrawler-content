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
    if (!this.beginActionRailRequest(button)) {
      return;
    }

    try {
    const context = this.getActionRailContext();
    const hexmap = context.hexmap;
    const items = extractConsumableItems(context.state?.inventory || {}, context.state?.equipment || []);
    const item = items.find((entry) => String(entry.id || entry.item_id || entry.name || '') === String(button.dataset.itemId || ''));

    if (!hexmap || !context.characterId || !item) {
      return;
    }

    const actionCost = getActionRailCost(button.dataset.actionCost, 1);
    const itemLabel = item.name || 'consumable';

    if (context.encounterActive && context.actor) {
      const response = await hexmap.performCombatAction({
        actorId: context.actor.id,
        actionType: 'consume_item',
        actionCost,
        characterId: context.characterId,
        item,
      });
      if (response) {
        this.appendChatLine('System', response.action_result?.summary || `${context.actorLabel} uses ${itemLabel}.`, 'system');
        hexmap.loadCharacterFromApi(context.characterId);
      }
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
      this.appendChatLine('System', data.error || `Unable to use ${itemLabel}.`, 'system');
      return;
    }

    this.appendChatLine('System', data.actionSummary || `${context.actorLabel} uses ${itemLabel}.`, 'system');
    hexmap.loadCharacterFromApi(context.characterId);
    } finally {
      this.endActionRailRequest(button);
    }
  }

  async executeDirectFeat(button) {
    if (!this.beginActionRailRequest(button)) {
      return;
    }

    try {
    const context = this.getActionRailContext();
    const featName = button.dataset.featName || 'feat action';
    const actionCost = getActionRailCost(button.dataset.actionCost, 1);

    if (context.encounterActive && context.actor && context.hexmap) {
      const response = await context.hexmap.performCombatAction({
        actorId: context.actor.id,
        actionType: 'feat',
        actionCost,
        featId: button.dataset.featId || '',
        featName,
      });
      if (response) {
        this.appendChatLine('System', response.action_result?.summary || `${context.actorLabel} uses ${featName}.`, 'system');
      }
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
      this.appendChatLine('System', data.error || `Unable to use ${featName}.`, 'system');
      return;
    }

    this.appendChatLine('System', data.action?.summary || `${context.actorLabel} uses ${featName}.`, 'system');
    context.hexmap?.loadCharacterFromApi(context.characterId);
    } finally {
      this.endActionRailRequest(button);
    }
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
    const pendingRequest = nextDeferred.pendingRequest || this.buildPendingChatRequest(requestId, nextDeferred.speaker || '', nextDeferred.message || '', targetRoomId, {
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
      this.settlePendingChatRequest(pendingRequest, {
        removePlayer: true,
        removePlaceholder: true,
      });
      this.appendChatLine('System', `Failed to send queued turn: ${error.message}`, 'system');
    } finally {
      this.roomChatBusy = false;
      if (this.roomChatDeferredMessages.length > 0) {
        this.updateQueuedChatStatus(this.roomChatDeferredMessages.length);
        void this.flushDeferredRoomMessages(targetCampaignId, targetRoomId, targetCharacterId);
      }
    }
  }

}
