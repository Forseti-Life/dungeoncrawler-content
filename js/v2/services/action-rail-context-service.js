/**
 * @file
 * Shared adapter for constructing the canonical Action Rail panel context.
 */

import { buildActionRailEntrySummary } from '../utils/inventory-utils.js';

export function buildActionRailContext(stateManager) {
  const hexmap = stateManager?.hexmap || null;
  const selected = stateManager?.get?.('selectedEntity') || null;
  const phaseSnapshot = hexmap?.gameCoordinator?.phaseManager?.getSnapshot?.() || {};
  const current = hexmap?.turnManagementSystem?.getCurrentTurnEntity?.() || null;
  const encounterActive = phaseSnapshot?.phase === 'encounter';
  const serverTurnEntity = String(phaseSnapshot?.turn?.entity || '').trim();
  const hasServerTurn = serverTurnEntity !== '';
  const launchPlayer = hexmap?.findLaunchPlayerEntity?.() || null;
  const actor = launchPlayer || (!hasServerTurn ? (selected || current || null) : null);
  const state = hexmap?.launchCharacter || hexmap?.characterData || {};
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
  const runtimeContext = hexmap?.resolveLaunchCharacterRuntimeContext?.() || {};
  const automationProfile = hexmap?.buildPlayerAutomationProfile?.() || {};
  const automationState = hexmap?.getPlayerAutomationState?.() || {};
  const actions = actor?.getComponent?.('ActionsComponent') || null;
  const movement = actor?.getComponent?.('MovementComponent') || null;
  const serverActionsRemaining = Number(phaseSnapshot?.turn?.actions_remaining);
  const actionText = Number.isFinite(serverActionsRemaining)
    ? `${serverActionsRemaining}/3 actions`
    : (actions ? `${actions.actionsRemaining}/${actions.maxActions ?? actions.actionsRemaining} actions` : null);
  const movementText = movement && Number.isFinite(movement.movementRemaining)
    ? `${movement.movementRemaining} ft move`
    : null;
  const serverTurnActor = hasServerTurn && hexmap?.entityManager?.getEntitiesWith
    ? (hexmap.entityManager.getEntitiesWith('PositionComponent') || []).find((entity) => {
        const refs = [
          entity?.dcEntityRef,
          entity?.dcEntityInstanceId,
          entity?.instanceId,
          entity?.id,
        ].map((value) => String(value || '').trim()).filter(Boolean);
        return refs.includes(serverTurnEntity);
      }) || null
    : null;
  const currentTurnLabel = hasServerTurn
    ? (serverTurnActor?.getComponent?.('IdentityComponent')?.name || serverTurnEntity)
    : (current?.getComponent?.('IdentityComponent')?.name || actorName);
  const contractActorRef = String(phaseSnapshot?.actionContract?.actor_id || '').trim();
  const directActorRef = String(
    actor?.dcEntityRef
    || actor?.dcEntityInstanceId
    || runtimeContext?.instanceId
    || '',
  ).trim();
  const availableActions = Array.isArray(phaseSnapshot?.availableActions) ? phaseSnapshot.availableActions : [];
  const hasTurnScopedAction = availableActions.some((entry) => [
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
  const actorRef = directActorRef
    || contractActorRef
    || ((hasServerTurn && hasTurnScopedAction && serverTurnEntity) ? serverTurnEntity : '')
    || null;
  const isActorTurn = !hasServerTurn
    || !serverTurnEntity
    || (Boolean(actorRef) && serverTurnEntity === actorRef);
  const characterId = Number(
    state?.characterId
    || state?.id
    || runtimeContext?.characterId
    || hexmap?.launchContext?.character_id
    || 0
  ) || 0;
  const baseStatus = buildActionRailEntrySummary([
    encounterActive ? 'Encounter active' : 'Encounter unavailable',
    hasServerTurn ? (isActorTurn ? 'Active turn' : `${currentTurnLabel}'s turn`) : '',
    actionText,
    movementText,
  ]) || 'Select your character to unlock direct actions.';

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
    campaignClock: phaseSnapshot?.campaignClock || null,
    timedActivities: Array.isArray(phaseSnapshot?.timedActivities) ? phaseSnapshot.timedActivities : [],
    encounterActive,
    hasServerTurn,
    isActorTurn,
    selectedEntity: selected,
    availableActions: Array.isArray(phaseSnapshot?.availableActions) ? phaseSnapshot.availableActions : [],
    actionContract: phaseSnapshot?.actionContract || null,
    automationState,
    canAutomate: Boolean(
      runtimeContext?.campaignId
      && Number(automationProfile?.character_id || 0) > 0
      && String(runtimeContext?.roomId || hexmap?.resolveActiveRoomId?.() || '').trim() !== ''
    ),
    actions,
    movement,
    statusLabel: buildActionRailEntrySummary([
      baseStatus,
      automationState?.inflight ? 'Running next autonomous step' : '',
      automationState?.lastError ? 'Automation failed' : '',
    ]) || baseStatus,
  };
}
