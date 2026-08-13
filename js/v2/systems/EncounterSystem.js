/**
 * @file systems/EncounterSystem.js
 *
 * Combat participant resolution, attack/spell/skill/search/consumable/feat execution.
 * Methods ported verbatim from hexmap.js UIManager.
 */

import { getActionRailCost } from '../utils/action-utils.js';
import { ACTION_SELECTION_HANDLERS, isRestActivityActionKey } from '../contracts/action-rail-contract.js';

export class EncounterSystem {
  constructor(shell, bus) {
    this.shell = shell;
    this.bus = bus;
    this.stateManager = null;
    this.dungeonData = null;
    this._unsubs = [];
    this._lastAnnouncedRound = null;
    this._lastAnnouncedActorKey = '';
    this._actionRailPendingRequests = new Map();
  }

  init(dungeonData, stateManager) {
    this.dungeonData = dungeonData || {};
    this.stateManager = stateManager || {};
    this._subscribe();
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
    this._actionRailPendingRequests.clear();
  }

  _subscribe() {
    this._unsubs.push(
      this.bus.on('user:action-selected', (d) => {
        const key = d?.actionKey;
        const handlerName = ACTION_SELECTION_HANDLERS[key] || '';
        if (handlerName && typeof this[handlerName] === 'function') {
          this[handlerName](d?.button);
          return;
        }

        if (isRestActivityActionKey(key)) {
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
      let actorRef = String(
        context.actorRef
        || phaseSnapshot?.actionContract?.actor_id
        || phaseSnapshot?.turn?.entity
        || ''
      ).trim();
      const coordinator = hexmap?.gameCoordinator || null;
      if (!hexmap || !coordinator?.api) {
        this._appendChatLine('System', 'Search requires an active campaign room.', 'system');
        return;
      }

      const characterId = Number.parseInt(
        String(context.characterId || runtimeContext.characterId || ''),
        10
      );
      const data = await this._sendCoordinatorActionWithResync(coordinator, 'search', actorRef, {
        search_mode: 'explicit',
        ...(Number.isFinite(characterId) && characterId > 0 ? { character_id: characterId } : {}),
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
      const searchDiscoveries = Array.isArray(data?.result?.discoveries)
        ? data.result.discoveries
        : (Array.isArray(data?.discoveries) ? data.discoveries : []);
      if (searchDiscoveries.length > 0) {
        await hexmap.loadCharacterFromApi?.(context.characterId);
        await hexmap.refreshQuestJournalFromApi?.();
      }
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

      const result = await this._sendCoordinatorActionWithResync(coordinator, actionKey, actorRef, params);
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
      const result = await this._sendCoordinatorActionWithResync(coordinator, actionType, actorRef, {
        character_id: context.characterId || null,
        room_id: context.runtimeContext?.roomId || context.hexmap?.resolveActiveRoomId?.() || null,
        reason: actionType === 'choose_not_to_act' ? 'Player chose not to use remaining actions.' : null,
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
      const resolvedTargetRef = this._resolveButtonTargetRef(context, button);

      if (!hexmap || !context.actor || !context.actorRef || (!targetId && !resolvedTargetRef)) {
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

      const target = targetId ? (hexmap.entityManager?.getEntity?.(targetId) || null) : null;
      const targetRef = String(
        resolvedTargetRef
        || target?.dcEntityRef
        || target?.dcEntityInstanceId
        || target?.dcEntityInstanceID
        || ''
      ).trim();
      if (!targetRef) {
        const targetName = this._resolveEntityName(target) || String(button?.dataset?.targetName || '').trim() || 'that target';
        this._appendChatLine('System', `Unable to resolve a stable target reference for ${targetName}. Refresh the room.`, 'system');
        return;
      }

      const strikeParams = {
        weapon: {
          weapon_id: weaponId || null,
          weapon_name: weaponName || null,
        },
      };

      const result = await this._sendCoordinatorActionWithResync(coordinator, 'strike', context.actorRef, strikeParams, {
        target: targetRef,
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

  async executeDirectTalk(button) {
    if (!this._beginActionRailRequest(button)) {
      return;
    }

    try {
      const context = this._getActionRailContext();
      const coordinator = context.hexmap?.gameCoordinator || null;
      const actorRef = context.actorRef || null;
      if (!coordinator?.api || !actorRef) {
        this._appendChatLine('System', 'Talk requires an active encounter actor.', 'system');
        return;
      }

      const targetRef = this._resolveButtonTargetRef(context, button);
      const targets = this._resolveButtonTargets(context, button);
      const targetName = String(
        targets?.[0]?.target_label
        || button?.dataset?.targetName
        || 'target'
      ).trim();
      if (!targetRef) {
        this._appendChatLine('System', 'Talk requires a selected target on the map.', 'system');
        return;
      }

      const message = String(button?.dataset?.talkMessage || `I speak to ${targetName}.`).trim();
      const result = await this._sendCoordinatorActionWithResync(coordinator, 'talk', actorRef, {
        message,
      }, {
        target: targetRef,
      });
      if (!result?.success) {
        this._appendChatLine('System', result?.error || result?.result?.error || `Unable to talk to ${targetName}.`, 'system');
        return;
      }

      coordinator.applyAuthoritativeUpdate?.(result);
      this.announceGameState(result?.game_state);
      this._refreshActionRail();
    } finally {
      this._endActionRailRequest(button);
    }
  }

  async executeDirectStride(button) {
    await this.executeDirectMovementAction('stride', button);
  }

  async executeDirectStep(button) {
    await this.executeDirectMovementAction('step', button);
  }

  async executeDirectMovementAction(actionType, button) {
    if (!this._beginActionRailRequest(button)) {
      return;
    }

    try {
      const context = this._getActionRailContext();
      const coordinator = context.hexmap?.gameCoordinator || null;
      const selectedEntity = context?.selectedEntity || null;
      const selectedActorRef = String(
        selectedEntity?.dcEntityRef
        || selectedEntity?.dcEntityInstanceId
        || selectedEntity?.instanceId
        || selectedEntity?.id
        || ''
      ).trim();
      const selectedIsControllable = Boolean(
        selectedEntity
        && typeof context?.hexmap?.canDragEntityOnMap === 'function'
        && context.hexmap.canDragEntityOnMap(selectedEntity)
      );
      const actorRef = String(context.actorRef || '').trim() || (selectedIsControllable ? selectedActorRef : '') || null;
      if (!coordinator?.api || !actorRef) {
        this._appendChatLine('System', `${actionType} requires an active encounter actor.`, 'system');
        return;
      }

      let targetQ = Number(button?.dataset?.targetQ);
      let targetR = Number(button?.dataset?.targetR);
      if (!Number.isFinite(targetQ) || !Number.isFinite(targetR)) {
        const selectedHex = this.stateManager?.get?.('selectedHex') || null;
        targetQ = Number(selectedHex?.q);
        targetR = Number(selectedHex?.r);
      }
      if (!Number.isFinite(targetQ) || !Number.isFinite(targetR)) {
        this._appendChatLine('System', `${actionType} requires a selected destination hex on the map.`, 'system');
        return;
      }

      const result = await this._sendCoordinatorActionWithResync(coordinator, actionType, actorRef, {
        target_hex: {
          q: Number(targetQ),
          r: Number(targetR),
        },
      });
      if (!result?.success) {
        this._appendChatLine('System', result?.error || result?.result?.error || `Unable to resolve ${actionType}.`, 'system');
        return;
      }

      coordinator.applyAuthoritativeUpdate?.(result);
      this.announceGameState(result?.game_state);
      this._refreshActionRail();
    } finally {
      this._endActionRailRequest(button);
    }
  }

  async executeDirectDemoralize(button) {
    await this.executeDirectTargetedAction('demoralize', button, {
      missingTargetMessage: 'Demoralize requires a selected hostile target on the map.',
      fallbackErrorMessage: 'Unable to demoralize that target.',
      targetRequired: true,
    });
  }

  async executeDirectFeint(button) {
    await this.executeDirectTargetedAction('feint', button, {
      missingTargetMessage: 'Feint requires a selected hostile target on the map.',
      fallbackErrorMessage: 'Unable to feint against that target.',
      targetRequired: true,
    });
  }

  async executeDirectPointOut(button) {
    await this.executeDirectTargetedAction('point_out', button, {
      missingTargetMessage: 'Point Out requires a selected target on the map.',
      fallbackErrorMessage: 'Unable to point out that target.',
      targetRequired: true,
    });
  }

  async executeDirectCommandAnimal(button) {
    await this.executeDirectTargetedAction('command_animal', button, {
      missingTargetMessage: 'Command Animal requires selecting your companion.',
      fallbackErrorMessage: 'Unable to command that companion.',
      targetRequired: true,
    });
  }

  async executeDirectAidSetup(button) {
    await this.executeDirectTargetedAction('aid_setup', button, {
      missingTargetMessage: 'Prepare Aid requires selecting an ally target.',
      fallbackErrorMessage: 'Unable to prepare aid for that target.',
      targetRequired: true,
    });
  }

  async executeDirectAdministerFirstAid(button) {
    await this.executeDirectTargetedAction('administer_first_aid', button, {
      missingTargetMessage: 'Administer First Aid requires selecting an ally target.',
      fallbackErrorMessage: 'Unable to administer first aid to that target.',
      targetRequired: true,
    });
  }

  async executeDirectTreatPoison(button) {
    await this.executeDirectTargetedAction('treat_poison', button, {
      targetRequired: false,
      fallbackErrorMessage: 'Unable to treat poison for that target.',
    });
  }

  async executeDirectBattleMedicine(button) {
    await this.executeDirectTargetedAction('battle_medicine', button, {
      targetRequired: false,
      fallbackErrorMessage: 'Unable to apply battle medicine to that target.',
    });
  }

  _resolveButtonTargetRef(context, button) {
    const targets = this._resolveButtonTargets(context, button);
    const primaryTarget = targets[0] || null;
    const primaryRef = String(primaryTarget?.target_ref || '').trim();
    if (primaryRef) {
      return primaryRef;
    }
    const targetEntityId = String(button?.dataset?.targetEntityId || button?.dataset?.targetId || '').trim();
    let targetRef = String(button?.dataset?.targetRef || '').trim();
    if (!targetRef && targetEntityId && context?.hexmap?.entityManager?.getEntitiesWith) {
      const candidates = context.hexmap.entityManager.getEntitiesWith('PositionComponent', 'IdentityComponent');
      const targetEntity = candidates.find((entity) => {
        const instanceId = String(entity?.dcEntityInstanceId || entity?.instanceId || '').trim();
        return String(entity?.id || '') === targetEntityId || instanceId === targetEntityId;
      }) || null;
      targetRef = String(
        targetEntity?.dcEntityRef
        || targetEntity?.dcEntityInstanceId
        || targetEntity?.instanceId
        || ''
      ).trim();
    }
    if (!targetRef) {
      targetRef = String(
        context?.selectedEntity?.dcEntityRef
        || context?.selectedEntity?.dcEntityInstanceId
        || context?.selectedEntity?.instanceId
        || ''
      ).trim();
    }
    return targetRef;
  }

  _resolveButtonTargetHex(context, button) {
    const targets = this._resolveButtonTargets(context, button);
    const primaryTarget = targets[0] || null;
    if (primaryTarget?.target_hex && Number.isFinite(Number(primaryTarget.target_hex.q)) && Number.isFinite(Number(primaryTarget.target_hex.r))) {
      return {
        q: Number(primaryTarget.target_hex.q),
        r: Number(primaryTarget.target_hex.r),
      };
    }
    let q = Number(button?.dataset?.targetQ);
    let r = Number(button?.dataset?.targetR);
    if (!Number.isFinite(q) || !Number.isFinite(r)) {
      q = Number(button?.dataset?.areaOriginQ);
      r = Number(button?.dataset?.areaOriginR);
    }
    if (!Number.isFinite(q) || !Number.isFinite(r)) {
      const selectedHex = context?.selectedHex || context?.hexmap?.stateManager?.get?.('selectedHex') || null;
      q = Number(selectedHex?.q);
      r = Number(selectedHex?.r);
    }
    if (!Number.isFinite(q) || !Number.isFinite(r)) {
      return null;
    }
    return { q: Number(q), r: Number(r) };
  }

  _hasResolvedActionTarget(context, button) {
    if (this._resolveButtonTargets(context, button).length > 0) {
      return true;
    }
    const targetRef = this._resolveButtonTargetRef(context, button);
    if (targetRef) {
      return true;
    }
    const targetRoomId = String(button?.dataset?.targetRoomId || '').trim();
    if (targetRoomId) {
      return true;
    }
    const hex = this._resolveButtonTargetHex(context, button);
    return Boolean(hex);
  }

  _resolveButtonTargets(context, button) {
    const raw = String(button?.dataset?.targetsJson || '').trim();
    if (raw) {
      try {
        const parsed = JSON.parse(raw);
        if (Array.isArray(parsed)) {
          const normalized = parsed
            .map((entry) => this._normalizeResolvedTarget(entry))
            .filter(Boolean);
          if (normalized.length > 0) {
            return normalized;
          }
        }
      } catch (_error) {
        // Fall through to legacy target bridges.
      }
    }

    const targetRef = String(button?.dataset?.targetRef || '').trim();
    const targetEntityId = String(button?.dataset?.targetEntityId || button?.dataset?.targetId || '').trim();
    const targetRoomId = String(button?.dataset?.targetRoomId || '').trim();
    const targetHex = this._resolveButtonTargetHexLegacy(context, button);
    if (!targetRef && !targetEntityId && !targetRoomId && !targetHex) {
      return [];
    }
    return [this._normalizeResolvedTarget({
      target_kind: targetRoomId ? 'room' : (targetRef || targetEntityId ? 'entity' : 'hex'),
      target_ref: targetRef || targetRoomId || null,
      target_entity_id: targetEntityId || null,
      target_room_id: targetRoomId || null,
      target_hex: targetHex || null,
      target_label: String(button?.dataset?.targetName || button?.dataset?.targetRoomName || '').trim() || null,
    })].filter(Boolean);
  }

  _resolveButtonTargetHexLegacy(context, button) {
    let q = Number(button?.dataset?.targetQ);
    let r = Number(button?.dataset?.targetR);
    if (!Number.isFinite(q) || !Number.isFinite(r)) {
      q = Number(button?.dataset?.areaOriginQ);
      r = Number(button?.dataset?.areaOriginR);
    }
    if (!Number.isFinite(q) || !Number.isFinite(r)) {
      const selectedHex = context?.selectedHex || context?.hexmap?.stateManager?.get?.('selectedHex') || null;
      q = Number(selectedHex?.q);
      r = Number(selectedHex?.r);
    }
    if (!Number.isFinite(q) || !Number.isFinite(r)) {
      return null;
    }
    return { q: Number(q), r: Number(r) };
  }

  _normalizeResolvedTarget(entry) {
    if (!entry || typeof entry !== 'object') {
      return null;
    }
    const targetKind = String(entry.target_kind || entry.targetKind || 'entity').trim().toLowerCase();
    const targetRef = String(entry.target_ref || entry.targetRef || '').trim() || null;
    const targetEntityId = String(entry.target_entity_id || entry.targetEntityId || '').trim() || null;
    const targetRoomId = String(entry.target_room_id || entry.targetRoomId || '').trim() || null;
    const q = Number(entry?.target_hex?.q ?? entry?.targetHex?.q);
    const r = Number(entry?.target_hex?.r ?? entry?.targetHex?.r);
    const targetHex = Number.isFinite(q) && Number.isFinite(r)
      ? { q: Number(q), r: Number(r) }
      : null;
    return {
      target_kind: targetKind || 'entity',
      target_ref: targetRef,
      target_entity_id: targetEntityId,
      target_room_id: targetRoomId,
      target_hex: targetHex,
      target_label: String(entry.target_label || entry.targetLabel || '').trim() || null,
    };
  }

  _resolveTargetSelectionContract(button) {
    const minTargets = Number(button?.dataset?.minTargets);
    const maxTargets = Number(button?.dataset?.maxTargets);
    const rangeFt = Number(button?.dataset?.rangeFt);
    const selectionMode = String(button?.dataset?.selectionMode || '').trim().toLowerCase();
    const completionPolicy = String(button?.dataset?.completionPolicy || '').trim().toLowerCase();
    const allowDuplicateTargets = button?.dataset?.allowDuplicateTargets === '1';
    return {
      min_targets: Number.isFinite(minTargets) && minTargets > 0 ? Math.max(1, Math.trunc(minTargets)) : undefined,
      max_targets: Number.isFinite(maxTargets) && maxTargets > 0 ? Math.max(1, Math.trunc(maxTargets)) : undefined,
      range_ft: Number.isFinite(rangeFt) && rangeFt > 0 ? Math.max(0, Math.trunc(rangeFt)) : undefined,
      selection_mode: selectionMode || undefined,
      completion_policy: completionPolicy || undefined,
      allow_duplicate_targets: allowDuplicateTargets || undefined,
    };
  }

  async executeDirectTargetedAction(actionType, button, options = {}) {
    if (!this._beginActionRailRequest(button)) {
      return;
    }

    try {
      const context = this._getActionRailContext();
      const coordinator = context.hexmap?.gameCoordinator || null;
      const actorRef = context.actorRef || null;
      if (!coordinator?.api || !actorRef) {
        this._appendChatLine('System', `${actionType} requires an active encounter actor.`, 'system');
        return;
      }

      const targetRequired = options?.targetRequired !== false;
      const targetRef = this._resolveButtonTargetRef(context, button);
      const targetHex = this._resolveButtonTargetHex(context, button);
      const targets = this._resolveButtonTargets(context, button);
      const targetSelection = this._resolveTargetSelectionContract(button);
      if (targetRequired && !targetRef && !targetHex) {
        this._appendChatLine('System', options?.missingTargetMessage || `${actionType} requires a selected target on the map.`, 'system');
        return;
      }

      const params = {
        action_cost: getActionRailCost(button?.dataset?.actionCost, 1),
        targeting_mode: String(button?.dataset?.targeting || '').trim() || undefined,
        target_hex: targetHex || undefined,
        targets: targets.length > 0 ? targets : undefined,
        ...targetSelection,
      };
      const result = await this._sendCoordinatorActionWithResync(
        coordinator,
        actionType,
        actorRef,
        params,
        targetRef ? { target: targetRef } : {},
      );
      if (!result?.success) {
        this._appendChatLine('System', result?.error || result?.result?.error || options?.fallbackErrorMessage || `Unable to resolve ${actionType}.`, 'system');
        return;
      }

      coordinator.applyAuthoritativeUpdate?.(result);
      this.announceGameState(result?.game_state);
      this._refreshActionRail();
    } finally {
      this._endActionRailRequest(button);
    }
  }

  async executeDirectRaiseShield(button) {
    await this.executeDirectAtomicAction('raise_shield', button);
  }

  async executeDirectDelay(button) {
    await this.executeDirectAtomicAction('delay', button);
  }

  async executeDirectAtomicAction(actionType, button) {
    if (!this._beginActionRailRequest(button)) {
      return;
    }

    try {
      const context = this._getActionRailContext();
      const coordinator = context.hexmap?.gameCoordinator || null;
      const actorRef = context.actorRef || null;
      if (!coordinator?.api || !actorRef) {
        this._appendChatLine('System', `${actionType} requires an active encounter actor.`, 'system');
        return;
      }

      const result = await this._sendCoordinatorActionWithResync(coordinator, actionType, actorRef, {});
      if (!result?.success) {
        this._appendChatLine('System', result?.error || result?.result?.error || `Unable to resolve ${actionType}.`, 'system');
        return;
      }

      coordinator.applyAuthoritativeUpdate?.(result);
      this.announceGameState(result?.game_state);
      this._refreshActionRail();
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
    const characterId = Number(context.characterId || 0) || 0;
    const skillName = String(button.dataset.skillName || '').replace(/_/g, ' ').trim();
    const skillModifier = Number(button.dataset.skillModifier || 0);
    const actionCost = getActionRailCost(button.dataset.actionCost, 1);
    const targetRequired = button.dataset.targetRequired === '1';
    const targetingMode = String(button.dataset.targeting || 'contextual').trim().toLowerCase();
    const selectedTargetRef = this._resolveButtonTargetRef(context, button);
    const targetHex = this._resolveButtonTargetHex(context, button);
    const targets = this._resolveButtonTargets(context, button);
    const targetSelection = this._resolveTargetSelectionContract(button);
    const targetRoomId = String(button?.dataset?.targetRoomId || '').trim();
    const labelBase = skillName || 'skill action';
    const label = `${labelBase}${Number.isFinite(skillModifier) ? ` (${skillModifier >= 0 ? '+' : ''}${skillModifier})` : ''}`;

    if (!characterId) {
      this._appendChatLine('System', 'Skill actions require an active character.', 'system');
      return;
    }

    if (!skillName) {
      this._appendChatLine('System', 'Skill action is missing a canonical skill name.', 'system');
      return;
    }
    if (targetRequired && !this._hasResolvedActionTarget(context, button)) {
      this._appendChatLine('System', 'That skill action requires a selected target on the map.', 'system');
      return;
    }

    if (context.encounterActive && context.actor && context.hexmap && context.actorRef) {
      const coordinator = context.hexmap?.gameCoordinator || null;
      if (!coordinator?.api) {
        this._appendChatLine('System', 'Skill actions require an active coordinator session. Refresh the room.', 'system');
        return;
      }

      const result = await this._sendCoordinatorActionWithResync(coordinator, 'skill', context.actorRef, {
        action_cost: actionCost,
        skill_name: skillName,
        skill_bonus: Number.isFinite(skillModifier) ? skillModifier : null,
        targeting_mode: targetingMode,
        target_hex: targetHex || undefined,
        target_room_id: targetRoomId || undefined,
        targets: targets.length > 0 ? targets : undefined,
        ...targetSelection,
      }, (targetRequired && selectedTargetRef) ? { target: selectedTargetRef } : {});

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
    const response = await fetch(`/api/character/${characterId}/actions`, {
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
          targetingMode,
          targetRef: selectedTargetRef || null,
          targets,
          targetHex: targetHex || null,
          targetRoomId: targetRoomId || null,
          targetSelection,
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
    context.hexmap?.loadCharacterFromApi(characterId);
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
      targeting: String(button.dataset.targeting || 'contextual').trim().toLowerCase(),
      targetRequired: button.dataset.targetRequired === '1',
    };
    const selectedTargetRef = this._resolveButtonTargetRef(context, button);
    const targetHex = this._resolveButtonTargetHex(context, button);
    const targets = this._resolveButtonTargets(context, button);
    const targetSelection = this._resolveTargetSelectionContract(button);
    const targetRoomId = String(button?.dataset?.targetRoomId || '').trim();
    const spellTargetRef = selectedTargetRef;
    console.info('[EncounterSystem] cast_spell prepared payload', {
      actorRef: context.actorRef || null,
      characterId: context.characterId || null,
      spellId: payload.spellId,
      spellName: payload.spellName,
      spellLevel: payload.spellLevel,
      actionCost: payload.actionCost,
      targeting: payload.targeting,
      targetRequired: payload.targetRequired,
      targetRef: spellTargetRef || null,
      targetHex: targetHex || null,
      targetRoomId: targetRoomId || null,
      targets,
      targetSelection,
    });

    if (context.encounterActive && context.actor && context.actorRef) {
      const coordinator = hexmap?.gameCoordinator || null;
      if (!coordinator?.api) {
        this._appendChatLine('System', 'Spell actions require an active coordinator session. Refresh the room.', 'system');
        return;
      }
      if (payload.targetRequired && !this._hasResolvedActionTarget(context, button)) {
        this._appendChatLine('System', 'That spell requires a selected target on the map.', 'system');
        return;
      }

      const result = await this._sendCoordinatorActionWithResync(coordinator, 'cast_spell', context.actorRef, {
        action_cost: payload.actionCost,
        spell_id: payload.spellId,
        spell_name: payload.spellName,
        spell_level: payload.spellLevel,
        cast_at_level: payload.spellLevel,
        is_focus_spell: payload.isFocusSpell,
        is_cantrip: payload.spellLevel === 0,
        targeting_mode: payload.targeting,
        character_id: context.characterId,
        target_hex: targetHex || undefined,
        target_room_id: targetRoomId || undefined,
        targets: targets.length > 0 ? targets : undefined,
        ...targetSelection,
      }, spellTargetRef ? { target: spellTargetRef } : {});

      if (!result?.success) {
        console.warn('[EncounterSystem] cast_spell rejected', {
          actorRef: context.actorRef,
          spellId: payload.spellId,
          spellName: payload.spellName,
          result,
        });
        this._appendChatLine('System', result?.error || result?.result?.error || `Unable to cast ${spellName}.`, 'system');
        return;
      }

      console.info('[EncounterSystem] cast_spell success', {
        actorRef: context.actorRef,
        spellId: payload.spellId,
        spellName: payload.spellName,
        resultSummary: result?.result?.summary || null,
        narration: result?.narration || null,
      });
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
          ...(spellTargetRef ? { target: spellTargetRef } : {}),
          params: {
            spell_id: payload.spellId,
            spell_name: payload.spellName,
            spell_level: payload.spellLevel,
            cast_at_level: payload.spellLevel,
            is_focus_spell: payload.isFocusSpell,
            is_cantrip: payload.spellLevel === 0,
            targeting_mode: payload.targeting,
            character_id: context.characterId,
            target_hex: targetHex || undefined,
            target_room_id: targetRoomId || undefined,
            targets: targets.length > 0 ? targets : undefined,
            ...targetSelection,
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

  async executeDirectFeat(button) {
    if (!this._beginActionRailRequest(button)) {
      return;
    }

    try {
    const context = this._getActionRailContext();
    const featName = button.dataset.featName || 'feat action';
    const actionCost = getActionRailCost(button.dataset.actionCost, 1);
    const targetRequired = button.dataset.targetRequired === '1';
    const targetingMode = String(button.dataset.targeting || 'contextual').trim().toLowerCase();
    const selectedTargetRef = this._resolveButtonTargetRef(context, button);
    const targetHex = this._resolveButtonTargetHex(context, button);
    const targets = this._resolveButtonTargets(context, button);
    const targetSelection = this._resolveTargetSelectionContract(button);
    const targetRoomId = String(button?.dataset?.targetRoomId || '').trim();
    const characterId = Number(context.characterId || 0) || 0;

    if (!characterId) {
      this._appendChatLine('System', 'Feat actions require an active character.', 'system');
      return;
    }
    if (targetRequired && !this._hasResolvedActionTarget(context, button)) {
      this._appendChatLine('System', 'That feat action requires a selected target on the map.', 'system');
      return;
    }

    if (context.encounterActive && context.actor && context.actorRef) {
      const coordinator = context.hexmap?.gameCoordinator || null;
      if (!coordinator?.api) {
        this._appendChatLine('System', 'Feat actions require an active coordinator session. Refresh the room.', 'system');
        return;
      }

      const result = await this._sendCoordinatorActionWithResync(coordinator, 'feat', context.actorRef, {
        action_cost: actionCost,
        feat_id: button.dataset.featId || '',
        feat_name: featName,
        character_id: characterId,
        targeting_mode: targetingMode,
        target_hex: targetHex || undefined,
        target_room_id: targetRoomId || undefined,
        targets: targets.length > 0 ? targets : undefined,
        ...targetSelection,
      }, (targetRequired && selectedTargetRef) ? { target: selectedTargetRef } : {});
      if (!result?.success) {
        this._appendChatLine('System', result?.error || result?.result?.error || `Unable to use ${featName}.`, 'system');
        return;
      }

      coordinator.applyAuthoritativeUpdate?.(result);
      this._appendChatLine('System', result?.result?.summary || `${context.actorLabel} uses ${featName}.`, 'system');
      this._refreshActionRail();
      return;
    }

    const runtimeContext = context.runtimeContext || {};
    const response = await fetch(`/api/character/${characterId}/actions`, {
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
          targetingMode,
          targetRef: selectedTargetRef || null,
          targets,
          targetHex: targetHex || null,
          targetRoomId: targetRoomId || null,
          targetSelection,
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

  async executeDirectConsumable(button) {
    if (!this._beginActionRailRequest(button)) {
      return;
    }

    try {
    const context = this._getActionRailContext();
    const hexmap = context.hexmap;
    const characterId = Number(context.characterId || 0) || 0;
    const itemPayloadRaw = String(button.dataset.itemPayload || '').trim();
    const targetRequired = button.dataset.targetRequired === '1';
    const targetingMode = String(button.dataset.targeting || 'self_or_target').trim().toLowerCase();
    const selectedTargetRef = this._resolveButtonTargetRef(context, button);
    const targetHex = this._resolveButtonTargetHex(context, button);
    const targets = this._resolveButtonTargets(context, button);
    const targetSelection = this._resolveTargetSelectionContract(button);
    const targetRoomId = String(button?.dataset?.targetRoomId || '').trim();
    let item = null;
    if (itemPayloadRaw !== '') {
      try {
        const parsed = JSON.parse(itemPayloadRaw);
        if (parsed && typeof parsed === 'object') {
          item = parsed;
        }
      } catch (_error) {
        item = null;
      }
    }
    if (!item) {
      item = {
        item_id: String(button.dataset.itemId || '').trim(),
        name: String(button.dataset.itemName || '').trim(),
      };
    }

    if (!hexmap || !characterId || !item || (!item.item_id && !item.id && !item.name)) {
      this._appendChatLine('System', 'Consumable action requires an active character and canonical consumable option data.', 'system');
      return;
    }
    if (targetRequired && !this._hasResolvedActionTarget(context, button)) {
      this._appendChatLine('System', 'That consumable requires a selected target on the map.', 'system');
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

      const result = await this._sendCoordinatorActionWithResync(coordinator, 'consume_item', context.actorRef, {
        action_cost: actionCost,
        character_id: characterId,
        targeting_mode: targetingMode,
        target_hex: targetHex || undefined,
        target_room_id: targetRoomId || undefined,
        targets: targets.length > 0 ? targets : undefined,
        ...targetSelection,
        item,
      }, (targetRequired && selectedTargetRef) ? { target: selectedTargetRef } : {});
      if (!result?.success) {
        this._appendChatLine('System', result?.error || result?.result?.error || `Unable to use ${itemLabel}.`, 'system');
        return;
      }

      coordinator.applyAuthoritativeUpdate?.(result);
      this._appendChatLine('System', result?.result?.summary || `${context.actorLabel} uses ${itemLabel}.`, 'system');
      hexmap.loadCharacterFromApi(characterId);
      this._refreshActionRail();
      return;
    }

    const runtimeContext = context.runtimeContext || {};
    const response = await fetch(`/api/character/${characterId}/inventory`, {
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
        targetRef: selectedTargetRef || null,
        targets,
        targetHex: targetHex || null,
        targetRoomId: targetRoomId || null,
        targetingMode,
        targetSelection,
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
    hexmap.loadCharacterFromApi(characterId);
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

  async _sendCoordinatorActionWithResync(coordinator, type, actorRef, params = {}, options = {}) {
    if (!coordinator?.api) {
      return { success: false, error: 'Coordinator API unavailable.' };
    }

    const sendWithCurrentStateVersion = () => coordinator.api.sendAction(type, actorRef, params, {
      ...options,
      stateVersion: coordinator.phaseManager?.stateVersion,
    });
    if (type === 'cast_spell') {
      console.info('[EncounterSystem] coordinator action dispatch', {
        type,
        actorRef,
        stateVersion: coordinator.phaseManager?.stateVersion ?? null,
        params,
        options,
      });
    }

    const requestStartedAt = Date.now();
    let pendingLogTimer = null;
    if (type === 'cast_spell') {
      pendingLogTimer = setTimeout(() => {
        console.warn('[EncounterSystem] coordinator action still pending', {
          type,
          actorRef,
          pendingMs: Date.now() - requestStartedAt,
          stateVersion: coordinator.phaseManager?.stateVersion ?? null,
        });
      }, 8000);
    }

    try {
      const result = await sendWithCurrentStateVersion();
      if (pendingLogTimer) {
        clearTimeout(pendingLogTimer);
      }
      if (type === 'cast_spell') {
        console.info('[EncounterSystem] coordinator action response', {
          type,
          actorRef,
          elapsedMs: Date.now() - requestStartedAt,
          success: Boolean(result?.success),
          error: result?.error || null,
          result,
        });
      }
      if (result?.success) {
        this._refreshSystemLogView();
      }
      return result;
    } catch (error) {
      if (pendingLogTimer) {
        clearTimeout(pendingLogTimer);
      }
      const fallbackResult = this._toCoordinatorFailureResult(error);
      const status = Number(error?.status || 0);
      const payload = error?.payload && typeof error.payload === 'object' ? error.payload : null;
      const errorMessage = String(payload?.error || error?.message || '').toLowerCase();
      const mismatchError = errorMessage.includes('state version mismatch');
      const isStateVersionMismatch = status === 422 && mismatchError;

      if (type === 'cast_spell') {
        console[isStateVersionMismatch ? 'info' : 'warn']('[EncounterSystem] coordinator action threw', {
          type,
          actorRef,
          elapsedMs: Date.now() - requestStartedAt,
          status: status || null,
          message: String(error?.message || ''),
          payload,
          retrying: isStateVersionMismatch,
        });
      }

      if (!isStateVersionMismatch) {
        return fallbackResult;
      }

      let hasAuthoritativeState = false;
      if (payload?.game_state) {
        coordinator.applyAuthoritativeUpdate?.(payload);
        this.announceGameState(payload.game_state);
        hasAuthoritativeState = true;
      } else {
        try {
          const refreshed = await coordinator.api.getState({ actor: actorRef });
          if (refreshed?.success && refreshed?.game_state) {
            coordinator.applyAuthoritativeUpdate?.(refreshed);
            this.announceGameState(refreshed.game_state);
            hasAuthoritativeState = true;
          }
        } catch (refreshError) {
          if (type === 'cast_spell') {
            console.warn('[EncounterSystem] coordinator mismatch refresh failed', {
              type,
              actorRef,
              status: Number(refreshError?.status || 0) || null,
              message: String(refreshError?.message || ''),
            });
          }
        }
      }

      if (!hasAuthoritativeState) {
        return fallbackResult;
      }

      try {
        const retryResult = await sendWithCurrentStateVersion();
        if (type === 'cast_spell') {
          console.info('[EncounterSystem] coordinator action retry response', {
            type,
            actorRef,
            elapsedMs: Date.now() - requestStartedAt,
            success: Boolean(retryResult?.success),
            error: retryResult?.error || null,
            result: retryResult,
          });
        }
        if (retryResult?.success) {
          this._refreshSystemLogView();
        }
        return retryResult;
      } catch (retryError) {
        if (type === 'cast_spell') {
          console.warn('[EncounterSystem] coordinator action retry threw', {
            type,
            actorRef,
            elapsedMs: Date.now() - requestStartedAt,
            status: Number(retryError?.status || 0) || null,
            message: String(retryError?.message || ''),
            payload: retryError?.payload || null,
          });
        }
        return this._toCoordinatorFailureResult(retryError);
      }
    }
  }

  _toCoordinatorFailureResult(error) {
    const payload = error?.payload && typeof error.payload === 'object' ? error.payload : null;
    if (payload) {
      return {
        ...payload,
        success: false,
        error: String(payload.error || error?.message || 'Request failed.'),
      };
    }
    return {
      success: false,
      error: String(error?.message || 'Request failed.'),
    };
  }

  _beginActionRailRequest(button) {
    const started = this.shell.panels.actionRail?.beginActionRailRequest(button) ?? false;
    if (!started) {
      return false;
    }
    this._beginActionRailPendingChatRequest(button);
    return true;
  }

  _endActionRailRequest(button) {
    this._settleActionRailPendingChatRequest(button);
    this.shell.panels.actionRail?.endActionRailRequest(button);
  }

  _beginActionRailPendingChatRequest(button) {
    const requestId = String(button?.dataset?.backendRequestId || '').trim();
    if (!requestId || this._actionRailPendingRequests.has(requestId)) {
      return;
    }

    const chatPanel = this.shell?.panels?.chat || null;
    if (!chatPanel || typeof chatPanel.buildPendingChatRequest !== 'function') {
      return;
    }

    const context = this._getActionRailContext();
    const runtimeContext = context?.runtimeContext || {};
    const roomId = String(runtimeContext.roomId || context?.hexmap?.resolveActiveRoomId?.() || '').trim();
    if (!roomId) {
      return;
    }
    const campaignId = Number(runtimeContext.campaignId || context?.hexmap?.resolveCampaignId?.() || 0) || null;
    const characterId = Number(context?.characterId || runtimeContext.characterId || 0) || null;
    const actorLabel = String(context?.actorLabel || context?.actor?.name || 'Actor').trim() || 'Actor';
    const pendingMessage = this._buildActionRailPendingMessage(button);
    const target = chatPanel.buildChatRenderTarget?.({
      view: 'room',
      channelKey: 'room',
      context: {
        campaignId,
        roomId,
        characterId,
      },
    }) || {
      view: 'room',
      channelKey: 'room',
      context: { campaignId, roomId, characterId },
    };

    const pending = chatPanel.buildPendingChatRequest(requestId, actorLabel, pendingMessage, roomId, {
      includePlayer: true,
      includePlaceholder: false,
      target,
    });
    if (pending) {
      this._actionRailPendingRequests.set(requestId, pending);
    }
  }

  _settleActionRailPendingChatRequest(button) {
    const requestId = String(button?.dataset?.backendRequestId || '').trim();
    if (!requestId) {
      return;
    }

    const chatPanel = this.shell?.panels?.chat || null;
    const pending = this._actionRailPendingRequests.get(requestId)
      || chatPanel?.pendingChatRequests?.get?.(requestId)
      || null;

    if (!pending) {
      this._actionRailPendingRequests.delete(requestId);
      return;
    }

    chatPanel?.settlePendingChatRequest?.(pending, {
      removePlayer: false,
    });
    this._actionRailPendingRequests.delete(requestId);
  }

  _buildActionRailPendingMessage(button) {
    const actionType = String(
      button?.dataset?.actionRailExecute
      || button?.dataset?.actionRailDirect
      || ''
    ).trim().toLowerCase();
    const actionLabel = String(
      button?.dataset?.actionLabel
      || button?.dataset?.actionName
      || button?.textContent
      || actionType
      || 'action'
    ).replace(/\s+/g, ' ').trim();

    const actionDescriptions = {
      attack: 'Attack action',
      search: 'Search action',
      strike: 'Strike action',
      interact: 'Interact action',
      skill: 'Skill action',
      spell: 'Spell action',
      cast_spell: 'Cast Spell action',
      feat: 'Feat action',
      consumable: 'Consumable action',
      consume_item: 'Use Item action',
      end_turn: 'End Turn action',
      choose_not_to_act: 'Choose Not To Act action',
    };
    const baseLabel = actionDescriptions[actionType]
      || (actionLabel ? `${actionLabel} action` : 'Action');
    return `${baseLabel} in progress. Waiting for server response...`;
  }

  _getActionRailContext() {
    return this.shell.panels.actionRail?.getActionRailContext() ?? {};
  }

  _refreshActionRail() {
    const actionRail = this.shell?.panels?.actionRail || null;
    if (typeof actionRail?.invalidateActionRail === 'function') {
      actionRail.invalidateActionRail(['turn', 'combat', 'header']);
      return;
    }
    if (typeof actionRail?.queueActionRailRefresh === 'function') {
      actionRail.queueActionRailRefresh();
      return;
    }
    actionRail?.refreshActionRail?.();
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

  _refreshSystemLogView() {
    this.shell?.panels?.chat?.invalidateChatCaches?.({ sessionViews: ['system-log'] });
    this.shell?.panels?.chat?.prefetchSessionViews?.(['system-log']);
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
