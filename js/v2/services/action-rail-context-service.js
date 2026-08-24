/**
 * @file
 * Shared adapter for constructing the canonical Action Rail panel context.
 */

import { buildActionRailEntrySummary } from '../utils/inventory-utils.js';

export function selectRailHexmap(stateManager) {
  return stateManager?.hexmap || null;
}

export function selectRailSelectedEntity(stateManager) {
  return stateManager?.get?.('selectedEntity') || null;
}

export function selectRailPhaseSnapshot(hexmap) {
  return hexmap?.gameCoordinator?.getAuthoritativePhaseSnapshot?.()
    || hexmap?.gameCoordinator?.phaseManager?.getSnapshot?.()
    || {};
}

export function selectRailEncounterId(phaseSnapshot) {
  const value = Number(phaseSnapshot?.encounterId || 0);
  return Number.isFinite(value) && value > 0 ? value : 0;
}

export function selectRailRuntimeGameState(hexmap) {
  return hexmap?.dungeonData?.game_state || {};
}

export function selectRailTurnEnvelope(hexmap, phaseSnapshot, selectedEntity) {
  const current = hexmap?.turnManagementSystem?.getCurrentTurnEntity?.() || null;
  const encounterActive = phaseSnapshot?.phase === 'encounter';
  const serverTurnEntity = String(phaseSnapshot?.turn?.entity || '').trim();
  const hasServerTurn = serverTurnEntity !== '';
  const serverTurnActor = hasServerTurn ? selectRailCanonicalActorByRef(hexmap, serverTurnEntity) : null;
  const launchPlayer = hexmap?.findLaunchPlayerEntity?.() || null;
  const selectedControllableActor = selectedEntity
    && typeof hexmap?.canDragEntityOnMap === 'function'
    && hexmap.canDragEntityOnMap(selectedEntity)
    ? selectedEntity
    : null;
  const actor = serverTurnActor
    || launchPlayer
    || selectedControllableActor
    || (!hasServerTurn ? (selectedEntity || current || null) : null);

  return {
    current,
    encounterActive,
    serverTurnEntity,
    hasServerTurn,
    serverTurnActor,
    launchPlayer,
    selectedControllableActor,
    actor,
  };
}

export function selectRailCharacterState(hexmap) {
  return hexmap?.launchCharacter || hexmap?.characterData || {};
}

export function selectRailActorIdentity(actor, state) {
  const basicInfo = state?.basicInfo || {};
  const actorMetadata = actor?.dcStatePayload?.metadata || {};
  const actorName = basicInfo.name || state?.name || actor?.getComponent?.('IdentityComponent')?.name || 'No actor selected';
  const actorPortraitUrl = String(
    actorMetadata?.portrait_url
    || actorMetadata?.portrait
    || state?.portrait_url
    || state?.portrait
    || basicInfo?.portrait_url
    || basicInfo?.portrait
    || ''
  ).trim();

  return {
    actorName,
    actorPortraitUrl,
  };
}

export function selectRailEntityByRef(hexmap, entityRef) {
  const ref = String(entityRef || '').trim();
  if (!ref || !hexmap?.entityManager?.getEntitiesWith) {
    return null;
  }
  const entities = hexmap.entityManager.getEntitiesWith('PositionComponent') || [];
  return entities.find((entity) => {
    const refs = [
      entity?.dcEntityRef,
      entity?.dcEntityInstanceId,
      entity?.instanceId,
      entity?.id,
    ].map((value) => String(value || '').trim()).filter(Boolean);
    return refs.includes(ref);
  }) || null;
}

export function selectRailCanonicalActorByRef(hexmap, actorRef) {
  const ref = String(actorRef || '').trim();
  if (!ref) {
    return null;
  }
  const directEntity = hexmap?.entityManager?.getEntity?.(ref) || null;
  if (directEntity) {
    return directEntity;
  }
  return selectRailEntityByRef(hexmap, ref);
}

export function selectRailEntityRef(entity) {
  return String(
    entity?.dcEntityRef
    || entity?.dcEntityInstanceId
    || entity?.instanceId
    || entity?.id
    || ''
  ).trim();
}

export function selectRailRuntimeContext(hexmap) {
  return hexmap?.resolveLaunchCharacterRuntimeContext?.() || {};
}

export function selectRailAutomationState(hexmap) {
  return {
    automationProfile: hexmap?.buildPlayerAutomationProfile?.() || {},
    automationState: hexmap?.getPlayerAutomationState?.() || {},
  };
}

export function selectRailActionState(hexmap, actor, phaseSnapshot, turnEnvelope, actorIdentity) {
  const actions = actor?.getComponent?.('ActionsComponent') || null;
  const movement = actor?.getComponent?.('MovementComponent') || null;
  const serverActionsRemaining = Number(phaseSnapshot?.turn?.actions_remaining);
  const actionText = Number.isFinite(serverActionsRemaining)
    ? `${serverActionsRemaining}/3 actions`
    : (actions ? `${actions.actionsRemaining}/${actions.maxActions ?? actions.actionsRemaining} actions` : null);
  const movementText = movement && Number.isFinite(movement.movementRemaining)
    ? `${movement.movementRemaining} ft move`
    : null;
  const serverTurnActor = turnEnvelope.serverTurnActor || null;
  const currentTurnLabel = turnEnvelope.hasServerTurn
    ? (serverTurnActor?.getComponent?.('IdentityComponent')?.name || turnEnvelope.serverTurnEntity)
    : (turnEnvelope.current?.getComponent?.('IdentityComponent')?.name || actorIdentity.actorName);
  const availableActions = Array.isArray(phaseSnapshot?.availableActions) ? phaseSnapshot.availableActions : [];
  const hasTurnScopedAction = availableActions.some((entry) => [
    'attack',
    'end_turn',
    'choose_not_to_act',
    'strike',
    'stride',
    'cast_spell',
    'interact',
    'search',
    'skill',
    'feat',
    'consume_item',
    'talk',
  ].includes(String(entry || '').trim()));
  const contractActorRef = String(phaseSnapshot?.actionContract?.actor_id || '').trim();

  return {
    actions,
    movement,
    actionText,
    movementText,
    currentTurnLabel,
    availableActions,
    hasTurnScopedAction,
    contractActorRef,
  };
}

export function selectRailActorRef(actor, runtimeContext, actionState, turnEnvelope) {
  const encounterScopedActorRef = (turnEnvelope.hasServerTurn && actionState.hasTurnScopedAction)
    ? String(turnEnvelope.serverTurnEntity || actionState.contractActorRef || '').trim()
    : '';
  const launchPlayerRef = selectRailEntityRef(turnEnvelope?.launchPlayer || null);
  const authoritativeActorRef = String(
    runtimeContext?.instanceId
    || runtimeContext?.instance_id
    || launchPlayerRef
    || '',
  ).trim();
  const directActorRef = String(
    authoritativeActorRef
    || selectRailEntityRef(actor)
    || '',
  ).trim();
  return encounterScopedActorRef
    || directActorRef
    || actionState.contractActorRef
    || ((turnEnvelope.hasServerTurn && actionState.hasTurnScopedAction && turnEnvelope.serverTurnEntity) ? turnEnvelope.serverTurnEntity : '')
    || null;
}

export function selectRailIsActorTurn(actorRef, turnEnvelope) {
  return !turnEnvelope.hasServerTurn
    || !turnEnvelope.serverTurnEntity
    || (Boolean(actorRef) && turnEnvelope.serverTurnEntity === actorRef);
}

export function selectRailCharacterId(runtimeContext, hexmap, state) {
  return Number(
    runtimeContext?.characterId
    || hexmap?.launchContext?.character_id
    || state?.id
    || state?.characterId
    || 0
  ) || 0;
}

export function selectRailCanAutomate(runtimeContext, automationProfile, hexmap) {
  return Boolean(
    runtimeContext?.campaignId
    && Number(automationProfile?.character_id || 0) > 0
    && String(runtimeContext?.roomId || hexmap?.resolveActiveRoomId?.() || '').trim() !== ''
  );
}

export function selectRailActionHydrationPending(turnEnvelope, phaseSnapshot, actionContract, availableActions) {
  if (!turnEnvelope?.encounterActive) {
    return false;
  }
  if (actionContract && typeof actionContract === 'object') {
    return false;
  }
  const stateVersion = Number(phaseSnapshot?.stateVersion || 0);
  const hasTurnEntity = String(phaseSnapshot?.turn?.entity || '').trim() !== '';
  if (stateVersion <= 0) {
    return true;
  }
  return hasTurnEntity && (!Array.isArray(availableActions) || availableActions.length === 0);
}

export function selectRailRuntimeSyncState(stateManager, phaseSnapshot = {}) {
  const syncHealth = String(
    stateManager?.get?.('runtimeSyncHealth')
    || phaseSnapshot?.syncHealth
    || 'healthy'
  ).trim().toLowerCase() || 'healthy';
  const snapshotId = String(
    stateManager?.get?.('runtimeSnapshotId')
    || phaseSnapshot?.snapshotId
    || ''
  ).trim() || null;

  return {
    syncHealth,
    snapshotId,
    degraded: syncHealth === 'degraded' || syncHealth === 'read_only_desynced',
    readOnlyDesynced: syncHealth === 'read_only_desynced',
  };
}

export function selectRailStatusLabel(turnEnvelope, isActorTurn, actionState, automationState, actionHydrationPending = false) {
  const runtimeSync = (arguments.length > 5 && arguments[5] && typeof arguments[5] === 'object')
    ? arguments[5]
    : {};
  const baseStatus = buildActionRailEntrySummary([
    turnEnvelope.encounterActive ? 'Encounter active' : 'Encounter unavailable',
    actionHydrationPending ? 'Syncing encounter actions' : '',
    runtimeSync?.syncHealth === 'degraded' ? 'Runtime sync degraded' : '',
    runtimeSync?.syncHealth === 'read_only_desynced' ? 'Runtime desynced (read-only)' : '',
    turnEnvelope.hasServerTurn ? (isActorTurn ? 'Active turn' : `${actionState.currentTurnLabel}'s turn`) : '',
    actionState.actionText,
    actionState.movementText,
  ]) || 'Select your character to unlock direct actions.';

  return buildActionRailEntrySummary([
    baseStatus,
    automationState?.inflight ? 'Running next autonomous step' : '',
    automationState?.lastError ? 'Automation failed' : '',
  ]) || baseStatus;
}

export function buildActionRailContext(stateManager) {
  const hexmap = selectRailHexmap(stateManager);
  const selected = selectRailSelectedEntity(stateManager);
  const phaseSnapshot = selectRailPhaseSnapshot(hexmap);
  const encounterId = selectRailEncounterId(phaseSnapshot);
  const runtimeGameState = selectRailRuntimeGameState(hexmap);
  const turnEnvelope = selectRailTurnEnvelope(hexmap, phaseSnapshot, selected);
  const encounterActive = turnEnvelope.encounterActive;
  const hasServerTurn = turnEnvelope.hasServerTurn;
  const actor = turnEnvelope.actor;
  const state = selectRailCharacterState(hexmap);
  const actorIdentity = selectRailActorIdentity(actor, state);
  let actorName = actorIdentity.actorName;
  let actorPortraitUrl = actorIdentity.actorPortraitUrl;
  const runtimeContext = selectRailRuntimeContext(hexmap);
  const { automationProfile, automationState } = selectRailAutomationState(hexmap);
  const actionState = selectRailActionState(hexmap, actor, phaseSnapshot, turnEnvelope, actorIdentity);
  const actions = actionState.actions;
  const movement = actionState.movement;
  const availableActions = actionState.availableActions;
  const actionContract = phaseSnapshot?.actionContract || null;
  const actionHydrationPending = selectRailActionHydrationPending(
    turnEnvelope,
    phaseSnapshot,
    actionContract,
    availableActions,
  );
  const actorRef = selectRailActorRef(actor, runtimeContext, actionState, turnEnvelope);
  const launchActorRef = String(runtimeContext?.instanceId || state?.instance_id || state?.instanceId || '').trim();
  const actorMatchesRef = Boolean(actor && actorRef) && [
    actor?.dcEntityRef,
    actor?.dcEntityInstanceId,
    actor?.instanceId,
    actor?.id,
  ].map((value) => String(value || '').trim()).filter(Boolean).includes(String(actorRef).trim());
  if (actorRef && !actorMatchesRef) {
    const actorFromRef = selectRailCanonicalActorByRef(hexmap, actorRef);
    if (actorFromRef) {
      const identity = actorFromRef.getComponent?.('IdentityComponent');
      const metadata = actorFromRef?.dcStatePayload?.metadata || actorFromRef?.dcStatePayload?.state?.metadata || {};
      actorName = String(identity?.name || actorName || actorRef).trim() || String(actorRef).trim();
      actorPortraitUrl = String(
        metadata?.portrait_url
        || metadata?.portrait
        || actorPortraitUrl
        || ''
      ).trim();
    } else if (turnEnvelope.hasServerTurn && String(turnEnvelope.serverTurnEntity || '').trim() === String(actorRef).trim()) {
      actorName = String(actionState.currentTurnLabel || actorRef).trim() || String(actorRef).trim();
    } else if (launchActorRef !== '' && String(actorRef).trim() !== launchActorRef) {
      actorName = String(actorRef).trim();
    }
  }
  const isActorTurn = selectRailIsActorTurn(actorRef, turnEnvelope);
  const characterId = selectRailCharacterId(runtimeContext, hexmap, state);
  const runtimeSync = selectRailRuntimeSyncState(stateManager, phaseSnapshot);
  const statusLabel = selectRailStatusLabel(
    turnEnvelope,
    isActorTurn,
    actionState,
    automationState,
    actionHydrationPending,
    runtimeSync,
  );
  const canAutomate = selectRailCanAutomate(runtimeContext, automationProfile, hexmap);
  const campaignClock = phaseSnapshot?.campaignClock
    || phaseSnapshot?.gameTime
    || runtimeGameState?.campaign_clock
    || runtimeGameState?.game_time
    || null;
  const timedActivities = Array.isArray(phaseSnapshot?.timedActivities)
    ? phaseSnapshot.timedActivities
    : (Array.isArray(runtimeGameState?.timed_activities) ? runtimeGameState.timed_activities : []);

  return {
    hexmap,
    state,
    actor,
    actorRef,
    actorLabel: actorName,
    actorPortraitUrl,
    characterId,
    runtimeContext,
    phaseSnapshot,
    encounterId,
    campaignClock,
    timedActivities,
    encounterActive,
    hasServerTurn,
    awaitingHydration: actionHydrationPending,
    isActorTurn,
    selectedEntity: selected,
    availableActions: Array.isArray(phaseSnapshot?.availableActions) ? phaseSnapshot.availableActions : [],
    actionContract,
    automationState,
    canAutomate,
    runtimeSync,
    actions,
    movement,
    statusLabel,
  };
}
