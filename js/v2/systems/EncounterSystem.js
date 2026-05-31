/**
 * @file systems/EncounterSystem.js
 *
 * Combat participant resolution, attack/spell/skill/interact execution.
 * Methods ported verbatim from hexmap.js UIManager.
 */

export class EncounterSystem {
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
        if (key === 'attack')   this.executeDirectAttack(d?.button);
        if (key === 'spell')    this.executeDirectSpell(d?.button);
        if (key === 'interact') this.executeDirectInteract(d?.button);
        if (key === 'skill')    this.executeDirectSkill(d?.button);
      }),
      this.bus.on('user:combat-start', () => this.startCombat()),
      this.bus.on('user:combat-end',   () => this.endCombat()),
      this.bus.on('user:end-turn',     () => this.endCurrentTurn()),
    );
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

  async executeDirectAttack(button) {
    if (!this._beginActionRailRequest(button)) {
      return;
    }

    try {
      const context = this._getActionRailContext();
      const hexmap = context.hexmap;
      const targetId = Number(button.dataset.targetId || 0);
      const weaponId = String(button.dataset.weaponId || '').trim();
      const weaponName = button.dataset.weaponName || 'weapon';

      if (!hexmap || !context.actor || !targetId) {
        this._appendChatLine('System', 'Attack options require an active character and target.', 'system');
        return;
      }

      let target = hexmap.entityManager?.getEntity?.(targetId) || null;
      if (!target) {
        this._appendChatLine('System', 'That target is no longer available.', 'system');
        return;
      }

      if (!context.encounterActive) {
        const combatState = await hexmap.startCombat?.();
        if (!combatState || !hexmap.stateManager?.get?.('encounterId')) {
          this._appendChatLine('System', 'Unable to start combat for that attack.', 'system');
          return;
        }

        target = hexmap.entityManager?.getEntity?.(targetId) || target;
        const currentTurnEntity = hexmap.turnManagementSystem?.getCurrentTurnEntity?.() || null;
        if (!currentTurnEntity || currentTurnEntity.id !== context.actor.id) {
          const actingName = currentTurnEntity?.getComponent?.('IdentityComponent')?.name || 'another combatant';
          this._appendChatLine('System', `Combat begins and initiative is rolled. It is ${actingName}'s turn.`, 'system');
          this._refreshActionRail();
          return;
        }
      }

      await hexmap.performAttack?.(context.actor, target, {
        weaponId,
        weaponName,
      });
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

    if (context.encounterActive && context.actor && context.hexmap) {
      const response = await context.hexmap.performCombatAction({
        actorId: context.actor.id,
        actionType: 'skill',
        actionCost: 1,
        skillName,
        skillModifier,
      });
      if (response) {
        this._appendChatLine('System', response.action_result?.summary || `${context.actorLabel} uses ${label}.`, 'system');
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

    if (context.encounterActive && context.actor) {
      const response = await hexmap.performCombatAction({
        actorId: context.actor.id,
        actionType: 'cast_spell',
        actionCost: payload.actionCost,
        characterId: context.characterId,
        spellId: payload.spellId,
        spellName: payload.spellName,
        spellLevel: payload.spellLevel,
        isFocusSpell: payload.isFocusSpell,
      });
      if (response) {
        this._appendChatLine('System', response.action_result?.summary || `${context.actorLabel} casts ${spellName}.`, 'system');
        hexmap.loadCharacterFromApi(context.characterId);
      }
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
    const hexmap = this.stateManager?.hexmap;
    hexmap?.startCombat?.();
  }

  endCombat() {
    const hexmap = this.stateManager?.hexmap;
    hexmap?.endCombat?.();
  }

  endCurrentTurn() {
    const hexmap = this.stateManager?.hexmap;
    hexmap?.endTurn?.();
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
    this.bus.emit('chat:system-message', { text: message, speaker, kind: type });
  }

}
