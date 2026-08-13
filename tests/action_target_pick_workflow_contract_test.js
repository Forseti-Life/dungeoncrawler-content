/**
 * @file
 * Contract regressions for shared map-tab target-picking workflow.
 *
 * Run with:
 *   node tests/action_target_pick_workflow_contract_test.js
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

const actionRailPanel = fs.readFileSync(path.resolve(__dirname, '../js/v2/panels/ActionRailPanel.js'), 'utf8');
const gameShell = fs.readFileSync(path.resolve(__dirname, '../js/v2/GameShell.js'), 'utf8');
const template = fs.readFileSync(path.resolve(__dirname, '../templates/hexmap-v2.html.twig'), 'utf8');
const css = fs.readFileSync(path.resolve(__dirname, '../css/hexmap.css'), 'utf8');

console.log('\n=== Action target-pick workflow contracts ===');

assert(
  actionRailPanel.includes("this.bus.emit('user:target-pick-requested', {")
    && actionRailPanel.includes('isActionRailTargetPickRequired(actionType, button) {')
    && actionRailPanel.includes("if (['skill', 'feat', 'consume_item', 'consumable'].includes(key)) {")
    && actionRailPanel.includes("return targeting !== '' && !['none', 'self'].includes(targeting);")
    && actionRailPanel.includes("if (targeting === 'self_or_target') {")
    && actionRailPanel.includes('shouldTreatContextualActionAsTargetable(metadata = {}, option = {}) {')
    && actionRailPanel.includes('resolveContextualTargetingMode(metadata = {}, option = {}, fallback = \'contextual\') {')
    && actionRailPanel.includes("const rawTargeting = String(option?.targeting || metadata?.targeting || 'contextual').trim().toLowerCase();")
    && actionRailPanel.includes("? this.resolveContextualTargetingMode(metadata, option, rawTargeting)")
    && actionRailPanel.includes('resolveActionRailTargetPrompt(actionType, button) {')
    && actionRailPanel.includes("if (key === 'consume_item' || key === 'consumable') {")
    && actionRailPanel.includes("if (key === 'skill') {")
    && actionRailPanel.includes("if (key === 'feat') {")
    && actionRailPanel.includes("'treat_wounds'")
    && actionRailPanel.includes('resolveSpellTargetingMode(option = {}, metadata = {}) {')
    && actionRailPanel.includes("if (rawTargeting === 'contextual') {")
    && actionRailPanel.includes("if (key === 'cast_spell' || key === 'spell') {"),
  'ActionRailPanel routes targetable use actions through a dedicated target-pick request event with contextual normalization and self-or-target gating'
);

assert(
  gameShell.includes("this.bus.on('user:target-pick-requested', (data) => this._beginTargetPickSession(data || {}));")
    && gameShell.includes('_beginTargetPickSession({ actionKey = \'\', button = null, promptLabel = \'\' } = {}) {')
    && gameShell.includes('activateGameShellTab(\'map\');')
    && gameShell.includes("const targetActorRef = this._resolveTargetPickActorRef(executionButton);")
    && gameShell.includes('canResyncCoordinatorForSelectedEntity(entity)')
    && gameShell.includes('this._setTargetPickOverlay(true, prompt);'),
  'GameShell starts a shared target-pick session and forces map-tab handoff'
);

assert(
  gameShell.includes("if (key === 'feint' || key === 'point_out') {")
    && gameShell.includes("if (key === 'cast_spell' || key === 'spell') {")
    && gameShell.includes("if (key === 'command_animal') {")
    && gameShell.includes("'aid_setup'")
    && gameShell.includes("'administer_first_aid'")
    && gameShell.includes("'battle_medicine'")
    && gameShell.includes("'treat_poison'")
    && gameShell.includes("'treat_wounds'")
    && gameShell.includes("targeting === 'hex' || targeting === 'area_origin' || targeting === 'connected_room' || targeting === 'room_hazard' || targeting === 'room' || targeting === 'self_or_target'")
    && gameShell.includes("return ['ally_or_self'];"),
  'GameShell normalizes support, social, room/area, and self-or-target actions into explicit allowed target-kind classes'
);

assert(
  gameShell.includes('if (this._targetPickSession && Number(button) !== 2) {')
    && gameShell.includes('const consumed = this._handleTargetPickHexClick(Number(q), Number(r), entities);')
    && gameShell.includes('onTokenSelected: (entity) => {')
    && gameShell.includes("const consumed = this._handleTargetPickHexClick(Number(q), Number(r), [entity]);")
    && gameShell.includes('suppressCoordinatorResync: Boolean(this._targetPickSession),')
    && gameShell.includes('button.dataset.targetsJson = JSON.stringify(session.selectedTargets || []);')
    && gameShell.includes('this._appendTargetPickSelection(session, selection)')
    && gameShell.includes('this.selectEntity(entity, { suppressCoordinatorResync: true });')
    && gameShell.includes("} else if (kinds.includes('area_origin')) {")
    && gameShell.includes("} else if (kinds.includes('connected_room')) {")
    && gameShell.includes("} else if (kinds.includes('self_or_target')) {")
    && gameShell.includes("if (targetEntity && actor && actorRef && targetRef && actorRef === targetRef) {")
    && gameShell.includes('selection = chooseSelfTarget();')
    && gameShell.includes("} else if (kinds.includes('room_hazard') || kinds.includes('room')) {")
    && gameShell.includes("this.bus.emit('user:action-selected', { actionKey, button });"),
  'Hex click handling resolves entity/room/area/self-or-target picks, records canonical targets, and replays canonical action execution'
);

assert(
  gameShell.includes("this.bus.on('combat:turn-changed', ({ entity } = {}) => {")
    && gameShell.includes('const turnActorRef = this.getEntityInstanceRef(entity);')
    && gameShell.includes("this._clearTargetPickSession('turn-changed');")
    && gameShell.includes("this.bus.on('game:state-refreshed', ({ phaseSnapshot } = {}) => {")
    && gameShell.includes('const snapshotActorRef = String(')
    && gameShell.includes('phaseSnapshot?.actionContract?.actor_id')
    && gameShell.includes('phaseSnapshot?.turn?.entity')
    && gameShell.includes('if (!snapshotActorRef || (sessionActorRef && sessionActorRef === snapshotActorRef)) {')
    && gameShell.includes("this._clearTargetPickSession('state-refreshed');"),
  'Target-pick workflow clears pending sessions when turn ownership or authoritative state changes'
);

assert(
  template.includes('id="map-target-pick-prompt"')
    && css.includes('.hexmap-container.dc-target-pick-active .hexmap-canvas-wrapper')
    && css.includes('.hexmap-hud__target-pick'),
  'Map UI exposes visible target-pick prompt and crosshair cursor styling while targeting is active'
);

console.log('\n=============================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
