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
  const hasServerTurn = Boolean(phaseSnapshot?.turn?.entity);
  const launchPlayer = hexmap?.findLaunchPlayerEntity?.() || null;
  const actor = launchPlayer || (!hasServerTurn ? (selected || current || null) : null);
  const state = hexmap?.launchCharacter || hexmap?.characterData || {};
  const basicInfo = state?.basicInfo || {};
  const actorName = basicInfo.name || state?.name || actor?.getComponent?.('IdentityComponent')?.name || 'No actor selected';
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
  const currentTurnLabel = current?.getComponent?.('IdentityComponent')?.name || actorName;
  const actorRef = actor?.dcEntityRef || actor?.dcEntityInstanceId || runtimeContext?.instanceId || null;
  const serverTurnEntity = String(phaseSnapshot?.turn?.entity || '').trim();
  const isActorTurn = !hasServerTurn
    || !serverTurnEntity
    || !actorRef
    || serverTurnEntity === actorRef
    || (!current || !actor || current.id === actor.id);
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
