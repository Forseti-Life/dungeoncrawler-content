/**
 * @file systems/EncounterSystem.js
 *
 * Combat participant resolution, attack/spell/skill/search execution.
 * Methods ported verbatim from hexmap.js UIManager.
 */

export class EncounterSystem {
  constructor(shell, bus) {
    this.shell = shell;
    this.bus = bus;
    this.stateManager = null;
    this.dungeonData = null;
    this._unsubs = [];
    this._lastAnnouncedRound = null;
    this._lastAnnouncedActorKey = '';
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
        if (key === 'attack')   this.executeDirectAttack(d?.button);
        if (key === 'spell')    this.executeDirectSpell(d?.button);
        if (key === 'interact') this.executeDirectInteract(d?.button);
        if (key === 'search')   this.executeDirectSearch(d?.button);
        if (key === 'skill')    this.executeDirectSkill(d?.button);
        if (['treat_wounds', 'refocus', 'repair', 'daily_preparations'].includes(key)) {
          this.executeRestActivity(key, d?.button);
        }
      }),
      this.bus.on('user:combat-start', () => this.startCombat()),
      this.bus.on('user:combat-end',   () => this.endCombat()),
      this.bus.on('user:end-turn',     (d) => this.endCurrentTurn(d)),
      this.bus.on('combat:round-changed', (d) => this.announceRoundChange(d)),
      this.bus.on('combat:turn-changed',  (d) => this.announceTurnChange(d)),
    );
  }

  announceRoundChange(data = {}) {
    const roundNumber = Number(data?.roundNumber || this.shell?.turnManagementSystem?.currentRound || 0);
    if (!Number.isFinite(roundNumber) || roundNumber <= 0 || roundNumber === this._lastAnnouncedRound) {
      return;
    }

    this._lastAnnouncedRound = roundNumber;
    console.info('[EncounterFlow] round_start', { roundNumber });
  }

  announceTurnChange(data = {}) {
    const entity = data?.entity || null;
    const turnIndex = Number(data?.turnIndex);
    const totalTurns = Number(data?.totalTurns);
    const actorName = this._resolveEntityName(entity || data);
    const actorKey = [
      this.shell?.turnManagementSystem?.currentRound || '',
      Number.isFinite(turnIndex) ? turnIndex : '',
      entity?.id || entity?.dcEntityRef || entity?.dcEntityInstanceId || actorName,
    ].join(':');

    if (!actorName || actorKey === this._lastAnnouncedActorKey) {
      return;
    }

    this._lastAnnouncedActorKey = actorKey;
    console.info('[EncounterFlow] turn_start', {
      actorName,
      actorId: entity?.id || entity?.dcEntityRef || entity?.dcEntityInstanceId || null,
      turnIndex: Number.isFinite(turnIndex) ? turnIndex : null,
      totalTurns: Number.isFinite(totalTurns) ? totalTurns : null,
      roundNumber: this.shell?.turnManagementSystem?.currentRound || null,
    });
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

    this.bus.emit('combat:order-changed', { order: orderedTurns });
    return orderedTurns;
  }

  describeCombatantTeam(entity) {
    const combat = entity?.getComponent?.('CombatComponent');
    const rawTeam = String(combat?.team || '').trim();
    if (!rawTeam) {
      return '';
    }
    return rawTeam.charAt(0).toUpperCase() + rawTeam.slice(1);
  }

  async executeDirectSearch(button) {
    if (!this._beginActionRailRequest(button)) {
      return;
    }

    try {
      const context = this._getActionRailContext();
      const hexmap = context.hexmap;
      const runtimeContext = context.runtimeContext || {};
      const phaseSnapshot = context.phaseSnapshot
        || hexmap?.gameCoordinator?.phaseManager?.getSnapshot?.()
        || {};
      const actorRef = String(
        context.actorRef
        || phaseSnapshot?.actionContract?.actor_id
        || phaseSnapshot?.turn?.entity
        || ''
      ).trim() || null;
      const coordinator = hexmap?.gameCoordinator || null;
      if (!hexmap || !coordinator?.api || !actorRef) {
        this._appendChatLine('System', 'Search requires an active campaign room and character.', 'system');
        return;
      }

      const data = await coordinator.api.sendAction('search', actorRef, {
        search_mode: 'explicit',
      }, {
        stateVersion: coordinator.phaseManager?.stateVersion,
      });
      if (!data?.success) {
        this._appendChatLine('System', data?.error || data?.result?.error || 'Unable to search this room.', 'system');
        return;
      }

      coordinator.applyAuthoritativeUpdate?.(data);
      this.announceGameState(data?.game_state);
      if (!Array.isArray(data.events) || data.events.length === 0) {
        if (typeof data.narration === 'string' && data.narration.trim()) {
          this._appendChatLine('Game Master', data.narration.trim(), 'gm');
        }
      }
      hexmap.loadCharacterFromApi?.(context.characterId);
      this._refreshActionRail();
    } finally {
      this._endActionRailRequest(button);
    }
  }

  async executeRestActivity(actionKey, button) {
    if (!this._beginActionRailRequest(button)) {
      return;
    }

    try {
      const context = this._getActionRailContext();
      const coordinator = context.hexmap?.gameCoordinator || null;
      const actorRef = context.actorRef || null;
      if (!coordinator?.api || !actorRef) {
        this._appendChatLine('System', 'Rest actions require an active room character.', 'system');
        return;
      }

      const params = {
        target_id: button?.dataset?.targetId || actorRef,
      };

      const result = await coordinator.api.sendAction(actionKey, actorRef, params, {
        stateVersion: coordinator.phaseManager?.stateVersion,
      });
      if (!result?.success) {
        this._appendChatLine('System', result?.error || result?.result?.error || 'Unable to complete that rest activity.', 'system');
        return;
      }

      coordinator.applyAuthoritativeUpdate?.(result);
      this.announceGameState(result?.game_state);
      if (!Array.isArray(result.events) || result.events.length === 0) {
        if (typeof result.narration === 'string' && result.narration.trim()) {
          this._appendChatLine('Game Master', result.narration.trim(), 'gm');
        }
      }
      context.hexmap?.loadCharacterFromApi?.(context.characterId);
      this._refreshActionRail();
    } finally {
      this._endActionRailRequest(button);
    }
  }

  async endCurrentTurn(data = {}) {
    const button = data?.button || null;
    if (button && !this._beginActionRailRequest(button)) {
      return;
    }

    try {
      const context = this._getActionRailContext();
      const actorRef = context.actorRef || null;
      const coordinator = context.hexmap?.gameCoordinator || null;
      if (!coordinator?.api || !actorRef) {
        this._appendChatLine('System', 'End Turn requires an active encounter character.', 'system');
        return;
      }

      const availableActions = Array.isArray(context.availableActions) ? context.availableActions : [];
      const requestedActionType = String(data?.actionType || '').trim().toLowerCase();
      let actionType = (requestedActionType === 'choose_not_to_act' || requestedActionType === 'end_turn')
        ? requestedActionType
        : '';
      if (actionType && availableActions.length > 0 && !availableActions.includes(actionType)) {
        actionType = '';
      }
      if (!actionType) {
        actionType = availableActions.includes('choose_not_to_act') ? 'choose_not_to_act' : 'end_turn';
      }
      const result = await coordinator.api.sendAction(actionType, actorRef, {
        character_id: context.characterId || null,
        room_id: context.runtimeContext?.roomId || context.hexmap?.resolveActiveRoomId?.() || null,
        reason: actionType === 'choose_not_to_act' ? 'Player chose not to use remaining actions.' : null,
      }, {
        stateVersion: coordinator.phaseManager?.stateVersion,
      });
      if (!result?.success) {
        this._appendChatLine('System', result?.error || result?.result?.error || 'Unable to end the current turn.', 'system');
        return;
      }

      coordinator.applyAuthoritativeUpdate?.(result);
      this.announceGameState(result?.game_state);
      const eventTypes = Array.isArray(result?.events) ? result.events.map((event) => event?.type || 'unknown').filter(Boolean) : [];
      console.info('[EncounterFlow] turn_action_ack', {
        actionType,
        actorRef,
        eventTypes,
        encounterId: result?.game_state?.encounter_id || null,
      });
      if (eventTypes.length === 0) {
        console.warn('[EncounterFlow] missing authoritative turn events', {
          actionType,
          actorRef,
          encounterId: result?.game_state?.encounter_id || null,
        });
      }
      this._refreshActionRail();
    } finally {
      if (button) {
        this._endActionRailRequest(button);
      }
    }
  }

  async executeDirectAttack(button) {
    if (!this._beginActionRailRequest(button)) {
      return;
    }

    try {
      const context = this._getActionRailContext();
      const hexmap = context.hexmap;
      const targetId = Number(button.dataset.targetId || 0);
      const weaponId = String(button.dataset.weaponId || '').trim();
      const weaponName = String(button.dataset.weaponName || 'weapon').trim();

      if (!hexmap || !context.actor || !context.actorRef || !targetId) {
        this._appendChatLine('System', 'Attack options require an active encounter actor and target.', 'system');
        return;
      }

      const coordinator = hexmap?.gameCoordinator || null;
      if (!coordinator?.api) {
        this._appendChatLine('System', 'Attack options require an active coordinator session. Refresh the room.', 'system');
        return;
      }

      if (!context.encounterActive) {
        this._appendChatLine('System', 'Attacks can only be initiated while an encounter is active. Refresh the room to sync encounter state.', 'system');
        return;
      }

      const target = hexmap.entityManager?.getEntity?.(targetId) || null;
      if (!target) {
        this._appendChatLine('System', 'That target is no longer available.', 'system');
        return;
      }

      const targetRef = String(target?.dcEntityRef || target?.dcEntityInstanceId || target?.dcEntityInstanceID || '').trim();
      if (!targetRef) {
        const targetName = this._resolveEntityName(target) || 'that target';
        this._appendChatLine('System', `Unable to resolve a stable target reference for ${targetName}. Refresh the room.`, 'system');
        return;
      }

      const strikeParams = {
        weapon: {
          weapon_id: weaponId || null,
          weapon_name: weaponName || null,
        },
      };

      const result = await coordinator.api.sendAction('strike', context.actorRef, strikeParams, {
        target: targetRef,
        stateVersion: coordinator.phaseManager?.stateVersion,
      });

      if (!result?.success) {
        this._appendChatLine('System', result?.error || result?.result?.error || 'Unable to execute that strike.', 'system');
        return;
      }

      coordinator.applyAuthoritativeUpdate?.(result);
      this.announceGameState(result?.game_state);
      this._refreshActionRail();
    } finally {
      this._endActionRailRequest(button);
    }
  }

  async executeDirectInteract(button) {
    if (!this._beginActionRailRequest(button)) {
      return;
    }

    try {
      const context = this._getActionRailContext();
      const hexmap = context.hexmap;
      const actor = context.actor;
      if (!hexmap || !actor) {
        return;
      }

      const targetQ = Number(button.dataset.targetQ);
      const targetR = Number(button.dataset.targetR);
      const hasTargetHex = Number.isFinite(targetQ) && Number.isFinite(targetR);
      const targetEntityId = button.dataset.targetEntityId || '';
      const targetName = button.dataset.targetName || 'target';
      let targetEntity = null;
      if (targetEntityId && hexmap.entityManager?.getEntitiesWith) {
        const candidates = hexmap.entityManager.getEntitiesWith('PositionComponent', 'IdentityComponent');
        targetEntity = candidates.find((entity) => {
          const instanceId = String(entity?.dcEntityInstanceId || entity?.instanceId || '');
          return String(entity?.id || '') === targetEntityId || instanceId === targetEntityId;
        }) || null;
      }
      if (!targetEntity && hasTargetHex && hexmap.getLiveEntitiesAtHex) {
        targetEntity = hexmap.getLiveEntitiesAtHex(targetQ, targetR)?.[0] || null;
      }

      if (targetEntity) {
        hexmap.selectEntity?.(actor);
        this.showEntityInfo(targetEntity);
      }

      if (!hasTargetHex) {
        this._appendChatLine('System', `Inspect ${targetName} in the room view or on the map for more detail.`, 'system');
        return;
      }

      hexmap.refreshSelectedHexContents?.(targetQ, targetR);

      const actorPos = actor.getComponent?.('PositionComponent');
      const distance = actorPos && hexmap.movementSystem?.hexDistance
        ? hexmap.movementSystem.hexDistance(actorPos.q, actorPos.r, targetQ, targetR)
        : null;

      if (distance !== null && distance > 1) {
        this._appendChatLine('System', `${targetName} is in hex (${targetQ}, ${targetR}). Move adjacent to use ${button.dataset.actionLabel || 'that interaction'}.`, 'system');
        return;
      }

      const interacted = hexmap.performInteractAtHex(actor, targetQ, targetR, targetEntity || undefined);
      if (!interacted) {
        this._appendChatLine('System', `No direct interaction resolved for ${targetName}. Inspect it or move closer if needed.`, 'system');
      }
    } finally {
      this._endActionRailRequest(button);
    }
  }

  async executeDirectSkill(button) {
    if (!this._beginActionRailRequest(button)) {
      return;
    }

    try {
    const context = this._getActionRailContext();
    const skillName = String(button.dataset.skillName || '').replace(/_/g, ' ');
    const skillModifier = Number(button.dataset.skillModifier || 0);
    const label = `${skillName}${Number.isFinite(skillModifier) ? ` (${skillModifier >= 0 ? '+' : ''}${skillModifier})` : ''}`;

    if (context.encounterActive && context.actor && context.hexmap && context.actorRef) {
      const coordinator = context.hexmap?.gameCoordinator || null;
      if (!coordinator?.api) {
        this._appendChatLine('System', 'Skill actions require an active coordinator session. Refresh the room.', 'system');
        return;
      }

      const result = await coordinator.api.sendAction('skill', context.actorRef, {
        action_cost: 1,
        skill_name: skillName,
        skill_bonus: Number.isFinite(skillModifier) ? skillModifier : null,
      }, {
        stateVersion: coordinator.phaseManager?.stateVersion,
      });

      if (!result?.success) {
        this._appendChatLine('System', result?.error || result?.result?.error || `Unable to use ${label}.`, 'system');
        return;
      }

      coordinator.applyAuthoritativeUpdate?.(result);
      this._appendChatLine('System', result?.result?.summary || `${context.actorLabel} uses ${label}.`, 'system');
      this._refreshActionRail();
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
        actionType: 'skill',
        actionName: skillName,
        summary: `${context.actorLabel} uses ${label}.`,
        source: 'action_rail',
        payload: {
          skillName,
          skillModifier,
        },
        campaignId: runtimeContext.campaignId || null,
        instanceId: runtimeContext.instanceId || null,
      }),
    });
    const data = await response.json();
    if (!response.ok || !data.success) {
      this._appendChatLine('System', data.error || `Unable to use ${label}.`, 'system');
      return;
    }

    this._appendChatLine('System', data.action?.summary || `${context.actorLabel} uses ${label}.`, 'system');
    context.hexmap?.loadCharacterFromApi(context.characterId);
    } finally {
      this._endActionRailRequest(button);
    }
  }

  async executeDirectSpell(button) {
    if (!this._beginActionRailRequest(button)) {
      return;
    }

    try {
    const context = this._getActionRailContext();
    const hexmap = context.hexmap;
    if (!hexmap || !context.characterId) {
      return;
    }

    const spellName = button.dataset.spellName || 'spell';
    const payload = {
      spellId: button.dataset.spellId || '',
      spellName,
      spellLevel: Number(button.dataset.spellLevel || 0),
      isFocusSpell: button.dataset.isFocusSpell === '1',
      actionCost: getActionRailCost(button.dataset.actionCost, 2),
    };

    if (context.encounterActive && context.actor && context.actorRef) {
      const coordinator = hexmap?.gameCoordinator || null;
      if (!coordinator?.api) {
        this._appendChatLine('System', 'Spell actions require an active coordinator session. Refresh the room.', 'system');
        return;
      }

      const result = await coordinator.api.sendAction('cast_spell', context.actorRef, {
        action_cost: payload.actionCost,
        spell_id: payload.spellId,
        spell_name: payload.spellName,
        spell_level: payload.spellLevel,
        cast_at_level: payload.spellLevel,
        is_focus_spell: payload.isFocusSpell,
        is_cantrip: payload.spellLevel === 0,
        character_id: context.characterId,
      }, {
        stateVersion: coordinator.phaseManager?.stateVersion,
      });

      if (!result?.success) {
        this._appendChatLine('System', result?.error || result?.result?.error || `Unable to cast ${spellName}.`, 'system');
        return;
      }

      coordinator.applyAuthoritativeUpdate?.(result);
      this._appendChatLine('System', result?.result?.summary || `${context.actorLabel} casts ${spellName}.`, 'system');
      if (typeof result.narration === 'string' && result.narration.trim()) {
        this._appendChatLine('Game Master', result.narration.trim(), 'gm');
      }
      hexmap.loadCharacterFromApi(context.characterId);
      this._refreshActionRail();
      return;
    }

    const runtimeContext = context.runtimeContext || {};
    if (runtimeContext.campaignId && context.actorRef && hexmap) {
      const response = await fetch(`/api/game/${runtimeContext.campaignId}/action`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'include',
        body: JSON.stringify({
          type: 'cast_spell',
          actor: context.actorRef,
          params: {
            spell_id: payload.spellId,
            spell_name: payload.spellName,
            spell_level: payload.spellLevel,
            cast_at_level: payload.spellLevel,
            is_focus_spell: payload.isFocusSpell,
            is_cantrip: payload.spellLevel === 0,
            character_id: context.characterId,
          },
        }),
      });
      const data = await response.json();
      if (!response.ok || !data.success) {
        this._appendChatLine('System', data.error || data.result?.error || `Unable to cast ${spellName}.`, 'system');
        return;
      }

      this._appendChatLine('System', `${context.actorLabel} casts ${spellName}.`, 'system');
      if (typeof data.narration === 'string' && data.narration.trim()) {
        this._appendChatLine('Game Master', data.narration.trim(), 'gm');
      }
      hexmap.loadCharacterFromApi(context.characterId);
      return;
    }

    const response = await fetch(`/api/character/${context.characterId}/cast-spell`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'include',
      body: JSON.stringify({
        spellId: payload.spellId,
        level: payload.spellLevel,
        isFocusSpell: payload.isFocusSpell,
        campaignId: runtimeContext.campaignId || null,
        instanceId: runtimeContext.instanceId || null,
      }),
    });
    const data = await response.json();
    if (!response.ok || !data.success) {
      this._appendChatLine('System', data.error || `Unable to cast ${spellName}.`, 'system');
      return;
    }

    this._appendChatLine('System', `${context.actorLabel} casts ${spellName}.`, 'system');
    hexmap.loadCharacterFromApi(context.characterId);
    } finally {
      this._endActionRailRequest(button);
    }
  }

  getActiveRoomNpcResponderNames(roomId = null) {
    return this.buildActiveRoomNpcTurnOrder(roomId)
      .map((turn) => String(turn?.name || '').trim())
      .filter(Boolean)
      .sort((left, right) => right.length - left.length);
  }

  startCombat() {
    this._appendChatLine('System', 'Encounter start is managed by room entry and server state.', 'system');
  }

  endCombat() {
    this._appendChatLine('System', 'Encounter end is managed by server state.', 'system');
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
      source: 'encounter-system',
      authority: 'authoritative',
      messageClass: 'authoritative_transcript',
    });
  }

  _appendNarratorLine(message) {
    this.bus.emit('chat:system-message', {
      text: message,
      speaker: 'Narrator',
      kind: 'system',
      view: 'room',
      channel: 'room',
      source: 'encounter-system',
      authority: 'authoritative',
      messageClass: 'authoritative_transcript',
    });
  }

  _resolveEntityName(entity) {
    return String(
      entity?.getComponent?.('IdentityComponent')?.name
      || entity?.name
      || entity?.actorName
      || entity?.entity_name
      || entity?.label
      || ''
    ).trim();
  }

  announceGameState(gameState = null) {
    if (!gameState || typeof gameState !== 'object') {
      return;
    }

    const roundNumber = Number(gameState.round || 0);
    if (Number.isFinite(roundNumber) && roundNumber > 0) {
      this.announceRoundChange({ roundNumber });
    }

    const turn = gameState.turn && typeof gameState.turn === 'object' ? gameState.turn : {};
    const initiativeOrder = Array.isArray(gameState.initiative_order) ? gameState.initiative_order : [];
    const turnIndex = Number(turn.index);
    const actorRef = String(turn.entity || '').trim();
    const actor = initiativeOrder.find((entry) => String(entry?.entity_id || '') === actorRef)
      || initiativeOrder[Number.isFinite(turnIndex) ? turnIndex : -1]
      || null;
    const actorName = String(
      actor?.name
      || actor?.display_name
      || actor?.label
      || actor?.entity_id
      || actorRef
      || ''
    ).trim();

    if (actorName) {
      this.announceTurnChange({
        actorName,
        entity: { id: actorRef || actorName, name: actorName },
        turnIndex,
        totalTurns: initiativeOrder.length,
      });
    }
  }

  _resolvePerceptionModifier(state = {}) {
    const skills = state?.data?.skills || state?.skills || {};
    if (Array.isArray(skills)) {
      const perception = skills.find((skill) => String(skill?.name || '').toLowerCase() === 'perception');
      const skillModifier = Number(perception?.bonus ?? perception?.modifier);
      if (Number.isFinite(skillModifier)) {
        return skillModifier;
      }
    }
    const perception = skills?.perception || skills?.Perception || state?.data?.perception || state?.perception || null;
    if (perception && typeof perception === 'object') {
      const perceptionModifier = Number(perception.bonus ?? perception.modifier ?? perception.value);
      if (Number.isFinite(perceptionModifier)) {
        return perceptionModifier;
      }
    }
    const flatPerception = Number(perception);
    if (Number.isFinite(flatPerception)) {
      return flatPerception;
    }
    return 0;
  }

}
