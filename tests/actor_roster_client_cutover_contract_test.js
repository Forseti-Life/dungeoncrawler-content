/**
 * @file
 * Contract test: canonical actor_roster client cutover seams.
 *
 * Run with:
 *   node tests/actor_roster_client_cutover_contract_test.js
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

const shellSource = require('./helpers/js-source.js').readGameShellSource();
const panelSource = fs.readFileSync(
  path.resolve(__dirname, '../js/v2/panels/CharacterPanel.js'),
  'utf8',
);

console.log('\n=== Actor roster client cutover contract ===');

assert(
  shellSource.includes("this.bus.emit('room:actor-roster-changed', {")
    && shellSource.includes('getActiveRoomActorRoster(roomId = null) {')
    && shellSource.includes('function _getVisualActorRoster(mapVisualState = {}) {'),
  'GameShell exposes canonical actor roster and emits room:actor-roster-changed'
);

assert(
  shellSource.includes('getVisualActorRoster: () => shell.getVisualActorRoster()')
    && shellSource.includes('getActiveRoomActorRoster: (roomId = null) => shell.getActiveRoomActorRoster(roomId)'),
  'GameShell state shim exposes roster queries to panel consumers'
);

assert(
  panelSource.includes("this.bus.on('room:actor-roster-changed', (payload) => this.handleRoomContextChanged(payload))")
    && panelSource.includes('buildActorOptionsFromCanonicalRoster() {')
    && panelSource.includes('buildSheetHrefFromSheetRef(sheetRef = null) {')
    && panelSource.includes('getAvailableActorSortModes() {'),
  'CharacterPanel subscribes to roster updates and builds selector options from canonical roster'
);

assert(
  panelSource.includes("if (selectedActorKind === 'actor') {")
    && panelSource.includes('option.sheetHref')
    && panelSource.includes('el.dataset.sheetHref'),
  'CharacterPanel routes actor selections through sheet-ref aware option metadata'
);

assert(
  panelSource.includes('const href = preferredSheetHref')
    && panelSource.includes('|| (selectedEntity'),
  'CharacterPanel prefers canonical sheet_ref href over inferred entity sheet links'
);

assert(
  panelSource.includes('const canonicalRosterOptions = this.buildActorOptionsFromCanonicalRoster();')
    && panelSource.includes('canonicalRosterOptions.forEach((option) => {')
    && panelSource.includes('const isPrimaryRosterDuplicate = (option = {}) => {')
    && panelSource.includes('isPrimaryRosterDuplicate(option)')
    && panelSource.includes('this.syncActorSortControl();')
    && !panelSource.includes('// Fallback path while full roster cutover propagates across environments.'),
  'CharacterPanel consumes canonical roster only for actor selector population'
);

assert(
  panelSource.includes('sortInitiative')
    && panelSource.includes('sortIsParticipant')
    && panelSource.includes("if (sortMode === 'initiative') {"),
  'CharacterPanel carries initiative metadata through canonical roster options and can sort by initiative'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
