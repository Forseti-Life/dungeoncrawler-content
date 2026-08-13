/**
 * @file
 * Contract test: token drag/drop movement uses existing room/combat paths.
 *
 * Run with:
 *   node tests/map_actor_drag_drop_contract_test.js
 */

const fs = require('fs');
const path = require('path');

let passed = 0;
let failed = 0;

function assert(condition, message) {
  if (condition) {
    passed++;
    console.log(`  ✓ ${message}`);
  } else {
    failed++;
    console.error(`  ✗ ${message}`);
  }
}

const canvasSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/canvas/HexCanvas.js'), 'utf8');
const tokenSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/canvas/HexTokenRenderer.js'), 'utf8');
const shellSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/GameShell.js'), 'utf8');

console.log('\n=== Map actor drag/drop contracts ===');

assert(
  canvasSource.includes('setPanEnabled(enabled) {')
    && canvasSource.includes('globalToWorldPoint(globalX, globalY) {')
    && canvasSource.includes('globalToAxial(globalX, globalY) {')
    && canvasSource.includes('renderMovementBandOverlay(bands = {}) {')
    && canvasSource.includes('clearMovementBandOverlay() {'),
  'HexCanvas exposes drag/drop helpers and movement-band overlay rendering'
);

assert(
  tokenSource.includes("container.on('pointerdown', (event) => this._handleTokenPointerDown(container, entity, event));")
    && tokenSource.includes('_handleStagePointerMove(event) {')
    && tokenSource.includes('_handleStagePointerUp(event) {')
    && tokenSource.includes('this.options?.onDropEntity?.({')
    && tokenSource.includes('this.options?.onDragEnd?.(drag.entity, success);'),
  'HexTokenRenderer owns token drag gesture capture and drag lifecycle callbacks'
);

assert(
  shellSource.includes('canDragEntityOnMap(entity) {')
    && shellSource.includes("const isGmMode = String(this.activeCampaignMode || this.campaignAccess?.current_mode || 'player').trim().toLowerCase() === 'gm';")
    && shellSource.includes('isFollowerLikeEntity(entity) {')
    && shellSource.includes('isControlledFollowerEntity(entity) {')
    && shellSource.includes("if (!this.isActorEntity(entity) && !followerLike) {")
    && shellSource.includes('return this.isCombatDragActorTurn(entity);'),
  'GameShell limits drag authority to GM mode, the controlled player character, or controlled followers and respects combat turn ownership'
);

assert(
  shellSource.includes('this.bus?.emit(\'entity:selected\', { entity });')
    && shellSource.includes('if (!suppressCoordinatorResync && this.canResyncCoordinatorForSelectedEntity(entity)) {')
    && shellSource.includes('void this.syncCoordinatorStateFromServer(this.resolveActiveRoomId() || \'\', {')
    && shellSource.includes('actor: this.getEntityInstanceRef(entity),')
    && shellSource.includes('characterId: this.getEntityCharacterId(entity) || null,'),
  'selecting a controllable actor triggers actor-scoped coordinator resync unless explicitly suppressed'
);

assert(
  shellSource.includes('resolveLaunchCharacterRuntimeContext() {')
    && shellSource.includes('const selectedIsControlledFollower = selectedEntity ? this.isControlledFollowerEntity(selectedEntity) : false;')
    && shellSource.includes('characterId: (selectedIsLaunchActor || selectedIsControlledFollower)')
    && shellSource.includes('instanceId: (selectedIsLaunchActor || selectedIsControlledFollower)'),
  'runtime context resolves actor/instance ids from controlled follower selection so authoritative refresh tracks the moved actor'
);

assert(
  shellSource.includes('moveEntityWithinRoom(entity, roomId, q, r) {')
    && shellSource.includes('/entity/${encodeURIComponent(instanceId)}/move')
    && shellSource.includes("locationType: 'room'")
    && shellSource.includes('const canResyncAsEntity = this.canResyncCoordinatorForSelectedEntity(entity);')
    && shellSource.includes('await this.syncCoordinatorStateFromServer(roomId, {')
    && shellSource.includes('actor: canResyncAsEntity ? instanceId : fallbackActorRef,')
    && shellSource.includes('characterId: canResyncAsEntity ? (this.getEntityCharacterId(entity) || null) : fallbackCharacterId,')
    && shellSource.includes("actionType: combatPlan.actionType || 'stride'"),
  'drag/drop reuses existing room move endpoint and existing combat movement path'
);

assert(
  shellSource.includes('async syncCoordinatorStateFromServer(expectedRoomId = \'\', runtimeContext = {}) {')
    && shellSource.includes('const fallbackRuntimeContext = this.resolveLaunchCharacterRuntimeContext?.() || {};')
    && shellSource.includes('runtimeContext?.actor')
    && shellSource.includes('fallbackRuntimeContext?.instanceId')
    && shellSource.includes('runtimeContext?.characterId')
    && shellSource.includes('fallbackRuntimeContext?.characterId')
    && shellSource.includes('const responseActorRef = String(')
    && shellSource.includes('state?.action_contract?.actor_id')
    && shellSource.includes("if (actorRef && responseActorRef && responseActorRef !== actorRef) {"),
  'coordinator state resync defaults to launch/selected runtime actor context when explicit actor context is omitted'
);

assert(
  shellSource.includes('this.gameCoordinator?.phaseManager?.encounterId')
    && shellSource.includes("console.error('[GameShell] performCombatAction missing encounterId'")
    && shellSource.includes("if (phase && phase !== 'encounter') {")
    && shellSource.includes("if (presentationStatus && !['active', 'in_progress', 'setup', 'rolling_initiative', 'paused'].includes(presentationStatus)) {"),
  'combat drag/drop resolves encounter authority from the live phase manager before failing'
);

assert(
  shellSource.includes('isCombatDragActorTurn(entity) {')
    && shellSource.includes("const turnActorRef = String(this.gameCoordinator?.phaseManager?.turn?.entity || '').trim();")
    && shellSource.includes("return { valid: false, reason: 'Only the active turn actor can drag-move during combat.' };")
    && shellSource.includes('const actorRef = this.getEntityInstanceRef(actorEntity) || String(options.actorId || \'\').trim();'),
  'combat drag/drop uses canonical actor refs and blocks out-of-turn actor drags before hitting the server'
);

assert(
  shellSource.includes('showMovementHighlightBandsForEntity(entity) {')
    && shellSource.includes('buildMovementHighlightBands(entity) {')
    && shellSource.includes("actionType: 'step'")
    && shellSource.includes('bands.step.push({ q, r });')
    && shellSource.includes('bands.stride1.push({ q, r });')
    && shellSource.includes('bands.stride2.push({ q, r });')
    && shellSource.includes('bands.stride3.push({ q, r });'),
  'drag start paints Step/Stride bands and 1-hex combat drags resolve as Step'
);

assert(
  shellSource.includes('to_hex: options?.targetHex ?? null,')
    && shellSource.includes('const sendWithCurrentStateVersion = () => coordinator.api.sendAction(actionType, actorRef, params, {')
    && shellSource.includes('/State version mismatch/i.test(errorText)')
    && shellSource.includes('coordinator.applyAuthoritativeUpdate?.(payload);')
    && shellSource.includes('const refreshAuthoritativeState = async () => {')
    && shellSource.includes('const state = await coordinator.api.getState({')
    && shellSource.includes('await refreshAuthoritativeState();'),
  'combat drag/drop sends canonical to_hex and refreshes authoritative state from /state on 422 retries/rejections'
);

assert(
  shellSource.includes("['step', 'stride'].includes(actionType)")
    && shellSource.includes('this.applyLocalEntityPlacement(')
    && shellSource.includes('Number(options.targetHex.q)')
    && shellSource.includes('Number(options.targetHex.r)'),
  'successful combat movement updates the actor local position so the next drag bands start from the new hex'
);

console.log('\n====================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
