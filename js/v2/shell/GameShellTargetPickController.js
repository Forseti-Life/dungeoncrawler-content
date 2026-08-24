function getEntityDisplayName(entity = null) {
  if (!entity) {
    return '';
  }
  const identity = entity.getComponent?.('IdentityComponent') || null;
  return String(
    identity?.name
    || entity?.dcEntityName
    || entity?.name
    || entity?.id
    || ''
  ).trim();
}

export class GameShellTargetPickController {
  constructor(shell) {
    this.shell = shell;
  }

  cloneActionButtonForTargetPick(button) {
    const clone = document.createElement('button');
    clone.type = 'button';
    const source = button instanceof HTMLButtonElement ? button : null;
    if (source?.dataset) {
      Object.entries(source.dataset).forEach(([key, value]) => {
        clone.dataset[key] = String(value ?? '');
      });
    }
    clone.dataset.actionRailExecute = String(source?.dataset?.actionRailExecute || '').trim();
    clone.dataset.actionLabel = String(source?.dataset?.actionLabel || source?.textContent || source?.dataset?.actionRailExecute || 'action').trim();
    return clone;
  }

  normalizeTargetPickKindsForAction(actionKey = '', button = null) {
    const key = String(actionKey || '').trim().toLowerCase();
    const targeting = String(button?.dataset?.targeting || '').trim().toLowerCase();
    if (targeting === 'hex' || targeting === 'area_origin' || targeting === 'connected_room' || targeting === 'room_hazard' || targeting === 'room' || targeting === 'self_or_target') {
      return [targeting];
    }
    if (['skill', 'feat', 'consume_item', 'consumable'].includes(key) && targeting) {
      return [targeting];
    }
    if (key === 'attack' || key === 'demoralize' || key === 'feint' || key === 'point_out') {
      return ['hostile_entity'];
    }
    if (key === 'talk') {
      return ['entity_or_room'];
    }
    if (key === 'interact') {
      return ['entity_or_object'];
    }
    if (key === 'command_animal') {
      return ['ally'];
    }
    if (['aid_setup', 'administer_first_aid', 'battle_medicine', 'treat_poison', 'treat_wounds'].includes(key)) {
      return ['ally_or_self'];
    }
    if (key === 'stride' || key === 'step') {
      return ['hex'];
    }
    if (key === 'cast_spell' || key === 'spell') {
      return targeting ? [targeting] : ['contextual'];
    }
    return ['contextual'];
  }

  setTargetPickOverlay(active = false, promptLabel = 'Pick target') {
    const shell = this.shell;
    if (!(shell._targetPickPromptEl instanceof HTMLElement)) {
      shell._targetPickPromptEl = document.getElementById('map-target-pick-prompt');
    }
    const container = shell.container?.closest?.('#hexmap-container')
      || (typeof document !== 'undefined' ? document.getElementById('hexmap-container') : null)
      || shell.container
      || null;
    if (container instanceof HTMLElement) {
      container.classList.toggle('dc-target-pick-active', Boolean(active));
    }
    if (shell._targetPickPromptEl instanceof HTMLElement) {
      shell._targetPickPromptEl.hidden = !active;
      shell._targetPickPromptEl.textContent = active ? String(promptLabel || 'Pick target').trim() : '';
    }
    const instruction = document.getElementById('action-instruction');
    if (instruction instanceof HTMLElement && active) {
      instruction.hidden = false;
      instruction.textContent = String(promptLabel || 'Pick target').trim();
    }
  }

  clearTargetPickSession(reason = 'cleared') {
    const shell = this.shell;
    if (!shell._targetPickSession) {
      this.setTargetPickOverlay(false);
      return;
    }
    console.info('[GameShell] target pick session cleared', { reason, actionKey: shell._targetPickSession.actionKey });
    shell._targetPickSession = null;
    this.setTargetPickOverlay(false);
  }

  beginTargetPickSession({ actionKey = '', button = null, promptLabel = '' } = {}) {
    const shell = this.shell;
    const normalizedAction = String(actionKey || '').trim().toLowerCase();
    if (!normalizedAction) {
      return;
    }
    const executionButton = this.cloneActionButtonForTargetPick(button);
    const allowedKinds = this.normalizeTargetPickKindsForAction(normalizedAction, executionButton);
    const targetActorRef = this.resolveTargetPickActorRef(executionButton);
    const minTargets = Number.isFinite(Number(executionButton?.dataset?.minTargets))
      ? Math.max(1, Math.trunc(Number(executionButton.dataset.minTargets)))
      : 1;
    const maxTargets = Number.isFinite(Number(executionButton?.dataset?.maxTargets))
      ? Math.max(minTargets, Math.trunc(Number(executionButton.dataset.maxTargets)))
      : minTargets;
    const selectionMode = String(executionButton?.dataset?.selectionMode || (maxTargets > 1 ? 'multi' : 'single')).trim().toLowerCase();
    const completionPolicy = String(
      executionButton?.dataset?.completionPolicy
      || (selectionMode === 'multi' ? 'max_targets' : 'auto')
    ).trim().toLowerCase();
    const allowDuplicateTargets = executionButton?.dataset?.allowDuplicateTargets === '1';
    const rangeFt = Number(executionButton?.dataset?.rangeFt || 0);
    const maxRangeFt = Number.isFinite(rangeFt) && rangeFt > 0 ? Math.max(0, Math.trunc(rangeFt)) : null;
    const resolvedPrompt = String(promptLabel || '').trim() || 'Pick target';
    shell._targetPickSession = {
      actionKey: normalizedAction,
      button: executionButton,
      promptLabel: resolvedPrompt,
      allowedKinds,
      actorRef: targetActorRef,
      minTargets,
      maxTargets,
      selectionMode,
      completionPolicy,
      allowDuplicateTargets,
      maxRangeFt,
      selectedTargets: [],
      sourceSurface: 'action-rail',
    };
    shell.activateGameShellTab('map');
    const prompt = maxTargets > 1 ? `${resolvedPrompt} (0/${maxTargets})` : resolvedPrompt;
    this.setTargetPickOverlay(true, prompt);
    console.info('[GameShell] target pick session started', {
      actionKey: normalizedAction,
      promptLabel: resolvedPrompt,
      allowedKinds,
      actorRef: targetActorRef,
      minTargets,
      maxTargets,
      selectionMode,
      completionPolicy,
      maxRangeFt,
    });
  }

  resolveTargetPickActorRef(button = null) {
    const shell = this.shell;
    const explicitActorRef = String(button?.dataset?.actorRef || '').trim();
    if (explicitActorRef) {
      return explicitActorRef;
    }
    const snapshot = shell.gameCoordinator?.phaseManager?.getSnapshot?.() || {};
    const encounterActorRef = String(snapshot?.actionContract?.actor_id || snapshot?.turn?.entity || '').trim();
    if (encounterActorRef) {
      return encounterActorRef;
    }
    const selectedEntity = shell._getStateValue('selectedEntity') || null;
    const selectedRef = shell.getEntityInstanceRef(selectedEntity);
    if (selectedRef) {
      return selectedRef;
    }
    return shell.getEntityInstanceRef(shell.findLaunchPlayerEntity?.() || null);
  }

  resolveEntityByInstanceRef(actorRef = '') {
    const shell = this.shell;
    const targetRef = String(actorRef || '').trim();
    if (!targetRef || !shell.entityManager?.getEntitiesWith) {
      return null;
    }
    const entities = shell.entityManager.getEntitiesWith('PositionComponent');
    return entities.find((entity) => shell.getEntityInstanceRef(entity) === targetRef) || null;
  }

  isHostileEntityTarget(entity, actorEntity) {
    if (!entity || !actorEntity || entity.id === actorEntity.id) {
      return false;
    }
    const actorCombat = actorEntity.getComponent?.('CombatComponent') || null;
    const targetCombat = entity.getComponent?.('CombatComponent') || null;
    if (!actorCombat || !targetCombat) {
      return false;
    }
    if (typeof actorCombat.isHostileTo === 'function') {
      return actorCombat.isHostileTo(targetCombat);
    }
    const actorTeam = String(actorCombat.team || '').trim().toLowerCase();
    const targetTeam = String(targetCombat.team || '').trim().toLowerCase();
    return Boolean(actorTeam && targetTeam && actorTeam !== targetTeam);
  }

  isAllyEntityTarget(entity, actorEntity) {
    if (!entity || !actorEntity || entity.id === actorEntity.id) {
      return false;
    }
    const actorCombat = actorEntity.getComponent?.('CombatComponent') || null;
    const targetCombat = entity.getComponent?.('CombatComponent') || null;
    if (!actorCombat || !targetCombat) {
      return false;
    }
    const actorTeam = String(actorCombat.team || '').trim().toLowerCase();
    const targetTeam = String(targetCombat.team || '').trim().toLowerCase();
    return Boolean(actorTeam && targetTeam && actorTeam === targetTeam);
  }

  resolvePrimaryHexEntity(q, r, provided = []) {
    const shell = this.shell;
    const entities = Array.isArray(provided) && provided.length ? provided : shell.getEntitiesAtHex(q, r);
    if (!entities.length) {
      return null;
    }
    const selectedId = shell._getStateValue('selectedEntity')?.id || null;
    if (selectedId) {
      const match = entities.find((entity) => entity?.id === selectedId);
      if (match) {
        return match;
      }
    }
    return entities[0] || null;
  }

  handleTargetPickHexClick(q, r, providedEntities = []) {
    const shell = this.shell;
    const session = shell._targetPickSession;
    if (!session) {
      return false;
    }
    const actor = this.resolveEntityByInstanceRef(session.actorRef) || shell.findLaunchPlayerEntity?.() || null;
    const targetEntity = this.resolvePrimaryHexEntity(q, r, providedEntities);
    const kinds = Array.isArray(session.allowedKinds) ? session.allowedKinds : [];
    const button = session.button;
    console.info('[GameShell] target pick click received', {
      actionKey: session.actionKey,
      actorRef: session.actorRef,
      q: Number(q),
      r: Number(r),
      selectedCount: Array.isArray(session.selectedTargets) ? session.selectedTargets.length : 0,
      minTargets: session.minTargets,
      maxTargets: session.maxTargets,
      completionPolicy: session.completionPolicy,
      selectionMode: session.selectionMode,
      allowDuplicateTargets: session.allowDuplicateTargets,
      targetEntityRef: shell.getEntityInstanceRef(targetEntity),
      targetEntityId: String(targetEntity?.id || ''),
      targetEntityName: getEntityDisplayName(targetEntity),
    });

    const chooseEntityTarget = (entity, kind = 'entity') => {
      if (!entity) {
        return false;
      }
      const targetRef = String(entity?.dcEntityRef || entity?.dcEntityInstanceId || entity?.instanceId || '').trim();
      const targetName = getEntityDisplayName(entity);
      button.dataset.targetId = String(entity.id || '');
      button.dataset.targetEntityId = String(entity.id || '');
      button.dataset.targetName = targetName;
      if (targetRef) {
        button.dataset.targetRef = targetRef;
      }
      shell.selectEntity(entity, { suppressCoordinatorResync: true });
      return {
        target_kind: kind,
        target_ref: targetRef || null,
        target_entity_id: String(entity.id || '').trim() || null,
        target_hex: { q: Number(q), r: Number(r) },
        target_label: targetName || null,
      };
    };

    const chooseHexTarget = (kind = 'hex') => {
      button.dataset.targetQ = String(q);
      button.dataset.targetR = String(r);
      return {
        target_kind: kind,
        target_ref: null,
        target_entity_id: null,
        target_hex: { q: Number(q), r: Number(r) },
        target_label: `Hex (${q}, ${r})`,
      };
    };

    const chooseSelfTarget = () => {
      if (!actor) {
        return false;
      }
      const actorRef = shell.getEntityInstanceRef(actor);
      const actorLabel = getEntityDisplayName(actor);
      if (actorRef) {
        button.dataset.targetRef = actorRef;
      }
      button.dataset.targetEntityId = String(actor?.id || '');
      button.dataset.targetId = String(actor?.id || '');
      button.dataset.targetName = actorLabel;
      shell.selectEntity(actor, { suppressCoordinatorResync: true });
      return {
        target_kind: 'self',
        target_ref: actorRef || null,
        target_entity_id: String(actor?.id || '').trim() || null,
        target_hex: { q: Number(q), r: Number(r) },
        target_label: actorLabel || 'self',
      };
    };

    let selection = null;
    if (kinds.includes('hostile_entity')) {
      selection = this.isHostileEntityTarget(targetEntity, actor) ? chooseEntityTarget(targetEntity, 'hostile_entity') : null;
    } else if (kinds.includes('ally') || kinds.includes('ally_or_self')) {
      selection = this.isAllyEntityTarget(targetEntity, actor) ? chooseEntityTarget(targetEntity, 'ally') : null;
      if (!selection && kinds.includes('ally_or_self')) {
        selection = chooseSelfTarget();
      }
    } else if (kinds.includes('self_or_target')) {
      const actorRef = shell.getEntityInstanceRef(actor);
      const targetRef = shell.getEntityInstanceRef(targetEntity);
      if (targetEntity && actor && actorRef && targetRef && actorRef === targetRef) {
        selection = chooseSelfTarget();
      } else {
        selection = chooseEntityTarget(targetEntity, 'self_or_target');
      }
    } else if (kinds.includes('entity_or_object') || kinds.includes('entity_or_room') || kinds.includes('contextual')) {
      selection = chooseEntityTarget(targetEntity);
      if (!selection) {
        selection = chooseHexTarget();
      }
    } else if (kinds.includes('hex')) {
      selection = chooseHexTarget('hex');
    } else if (kinds.includes('area_origin')) {
      button.dataset.areaOriginQ = String(q);
      button.dataset.areaOriginR = String(r);
      button.dataset.targetQ = String(q);
      button.dataset.targetR = String(r);
      selection = {
        target_kind: 'area_origin',
        target_ref: null,
        target_entity_id: null,
        target_hex: { q: Number(q), r: Number(r) },
        target_label: `Area origin (${q}, ${r})`,
      };
    } else if (kinds.includes('connected_room')) {
      const capability = shell.resolveNavigationCapabilityAtHex?.(q, r) || null;
      if (capability?.available && capability?.target_room_id) {
        button.dataset.targetRoomId = String(capability.target_room_id);
        button.dataset.targetRoomName = String(capability.target_room_name || capability.target_room_id);
        button.dataset.targetRef = String(capability.target_room_id);
        selection = {
          target_kind: 'connected_room',
          target_ref: String(capability.target_room_id),
          target_entity_id: null,
          target_room_id: String(capability.target_room_id),
          target_hex: { q: Number(q), r: Number(r) },
          target_label: String(capability.target_room_name || capability.target_room_id),
        };
      }
    } else if (kinds.includes('room_hazard') || kinds.includes('room')) {
      button.dataset.targetRoomId = String(shell.resolveActiveRoomId() || '');
      selection = chooseEntityTarget(targetEntity, kinds.includes('room_hazard') ? 'room_hazard' : 'room')
        || {
          ...chooseHexTarget(kinds.includes('room_hazard') ? 'room_hazard' : 'room'),
          target_room_id: String(shell.resolveActiveRoomId() || ''),
        };
    } else {
      selection = chooseEntityTarget(targetEntity);
    }

    if (!selection || !this.appendTargetPickSelection(session, selection)) {
      console.warn('[GameShell] target pick selection rejected', {
        actionKey: session.actionKey,
        q: Number(q),
        r: Number(r),
        selection,
        selectedTargets: session.selectedTargets,
      });
      this.setTargetPickOverlay(true, `${session.promptLabel} (invalid target)`);
      return true;
    }

    const selectedCount = Array.isArray(session.selectedTargets) ? session.selectedTargets.length : 0;
    const maxTargets = Number.isFinite(Number(session.maxTargets)) ? Number(session.maxTargets) : 1;
    const minTargets = Number.isFinite(Number(session.minTargets)) ? Number(session.minTargets) : 1;
    const completionPolicy = String(session.completionPolicy || '').trim().toLowerCase();
    const shouldComplete = selectedCount >= minTargets
      && (session.selectionMode !== 'multi' || completionPolicy === 'min_targets' || selectedCount >= maxTargets);
    if (!shouldComplete) {
      this.setTargetPickOverlay(true, `${session.promptLabel} (${selectedCount}/${maxTargets})`);
      return true;
    }

    this.applyLegacySelectionDataset(button, session.selectedTargets[0] || null);
    button.dataset.targetsJson = JSON.stringify(session.selectedTargets || []);
    button.dataset.targetQ = button.dataset.targetQ || String(q);
    button.dataset.targetR = button.dataset.targetR || String(r);
    shell.setSelectedHex(q, r, { emitDetails: false });
    const actionKey = String(session.actionKey || '').trim();
    this.clearTargetPickSession('picked');
    shell.bus.emit('user:action-selected', { actionKey, button });
    return true;
  }

  appendTargetPickSelection(session, selection) {
    const shell = this.shell;
    if (!session || !selection || typeof selection !== 'object') {
      return false;
    }
    if (!Array.isArray(session.selectedTargets)) {
      session.selectedTargets = [];
    }
    const key = [
      String(selection.target_kind || '').trim(),
      String(selection.target_ref || '').trim(),
      String(selection.target_entity_id || '').trim(),
      Number.isFinite(Number(selection?.target_hex?.q)) ? Number(selection.target_hex.q) : '',
      Number.isFinite(Number(selection?.target_hex?.r)) ? Number(selection.target_hex.r) : '',
      String(selection.target_room_id || '').trim(),
    ].join(':');
    const existingKeys = new Set((session.selectedTargets || []).map((entry) => [
      String(entry?.target_kind || '').trim(),
      String(entry?.target_ref || '').trim(),
      String(entry?.target_entity_id || '').trim(),
      Number.isFinite(Number(entry?.target_hex?.q)) ? Number(entry.target_hex.q) : '',
      Number.isFinite(Number(entry?.target_hex?.r)) ? Number(entry.target_hex.r) : '',
      String(entry?.target_room_id || '').trim(),
    ].join(':')));
    if (!session.allowDuplicateTargets && existingKeys.has(key)) {
      return false;
    }
    const maxTargets = Number.isFinite(Number(session.maxTargets)) ? Number(session.maxTargets) : 1;
    if (session.selectedTargets.length >= maxTargets) {
      return false;
    }
    if (Number.isFinite(Number(session.maxRangeFt)) && Number(session.maxRangeFt) > 0) {
      const actor = this.resolveEntityByInstanceRef(session.actorRef) || shell.findLaunchPlayerEntity?.() || null;
      const actorPos = actor?.getComponent?.('PositionComponent') || null;
      const targetQ = Number(selection?.target_hex?.q);
      const targetR = Number(selection?.target_hex?.r);
      const distanceHexes = actorPos && Number.isFinite(targetQ) && Number.isFinite(targetR) && shell.movementSystem?.hexDistance
        ? shell.movementSystem.hexDistance(Number(actorPos.q), Number(actorPos.r), targetQ, targetR)
        : null;
      const hexCost = Number(actor?.getComponent?.('MovementComponent')?.hexMovementCost || 5);
      const distanceFt = Number.isFinite(Number(distanceHexes))
        ? Number(distanceHexes) * (Number.isFinite(hexCost) && hexCost > 0 ? hexCost : 5)
        : null;
      if (Number.isFinite(Number(distanceFt)) && Number(distanceFt) > Number(session.maxRangeFt)) {
        return false;
      }
    }
    session.selectedTargets.push(selection);
    return true;
  }

  applyLegacySelectionDataset(button, selection) {
    if (!button?.dataset || !selection || typeof selection !== 'object') {
      return;
    }
    if (selection.target_ref) {
      button.dataset.targetRef = String(selection.target_ref);
    }
    if (selection.target_entity_id) {
      button.dataset.targetEntityId = String(selection.target_entity_id);
      button.dataset.targetId = String(selection.target_entity_id);
    }
    if (selection.target_label) {
      button.dataset.targetName = String(selection.target_label);
    }
    if (selection.target_room_id) {
      button.dataset.targetRoomId = String(selection.target_room_id);
    }
    if (selection?.target_hex && Number.isFinite(Number(selection.target_hex.q)) && Number.isFinite(Number(selection.target_hex.r))) {
      button.dataset.targetQ = String(selection.target_hex.q);
      button.dataset.targetR = String(selection.target_hex.r);
    }
  }
}
