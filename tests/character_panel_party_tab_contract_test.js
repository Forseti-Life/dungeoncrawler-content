/**
 * @file
 * Regression contract for top-level Party tab actor-sheet navigation.
 *
 * Run with:
 *   node tests/character_panel_party_tab_contract_test.js
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

const panelSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/panels/CharacterPanel.js'), 'utf8');
const templateSource = fs.readFileSync(path.resolve(__dirname, '../templates/hexmap-v2.html.twig'), 'utf8');

console.log('\n=== CharacterPanel unified actor selector contract ===');

assert(
  templateSource.includes('id="game-tab-party"')
    && templateSource.includes("{{ 'Actor Roster'|t }}")
    && !templateSource.includes('id="game-tab-character"')
    && templateSource.includes('id="game-panel-party"')
    && templateSource.includes('id="party-actor-select"'),
  'Game shell exposes one Actor Roster tab with a unified actor selector (Character tab removed)'
);

assert(
  panelSource.includes('partyActorSelectWrap:    id(\'party-actor-select-wrap\')')
    && panelSource.includes('partyActorFilters:       id(\'party-actor-filters\')')
    && panelSource.includes('partyActorSort:          id(\'party-actor-sort\')')
    && panelSource.includes('partyActorSelect:        id(\'party-actor-select\')'),
  'CharacterPanel binds Party selector DOM elements'
);

assert(
  templateSource.includes('id="party-actor-filters"')
    && templateSource.includes('id="party-actor-sort"')
    && templateSource.includes('value="initiative"')
    && templateSource.includes('data-actor-filter="party"')
    && templateSource.includes('data-actor-filter="hostile"')
    && templateSource.includes('data-actor-filter="all"'),
  'Party tab exposes side/faction filter controls and sort controls for actor roster'
);

assert(
  panelSource.includes('window.addEventListener(\'dungeoncrawler:game-shell-tab-changed\', this._tabChangedHandler);')
    && panelSource.includes('attachCharacterPanelToActiveSurface() {')
    && panelSource.includes('syncCharacterSurfaceForActiveTab() {'),
  'CharacterPanel keeps actor selection synced through top-level tab changes'
);

assert(
  panelSource.includes('this.focusActorFromSelector(partyActorSelect.value, { activateCharacterTab: false });')
    && panelSource.includes("if (selectedActorKind === 'primary' || normalizedRef === '__primary__') {")
    && panelSource.includes('this.showActorCharacterFromEntity(entity);')
    && panelSource.includes('this.showFollowerCharacterFromEntity(entity);'),
  'Unified selector routes primary option to main sheet, runtime actors to actor rendering, and follower options to follower rendering'
);

assert(
  panelSource.includes('normalizeActorFilter(rawFilter = \'\') {')
    && panelSource.includes('normalizeActorSortMode(rawMode = \'\') {')
    && panelSource.includes('resolveActorSideForEntity(entity = null) {')
    && panelSource.includes('syncActorFilterButtons(optionSet = []) {')
    && panelSource.includes('syncActorSortControl() {')
    && panelSource.includes('sortActorOptions(optionSet = []) {')
    && panelSource.includes("if (sortMode === 'initiative') {"),
  'CharacterPanel classifies active-room actors by side and supports initiative sorting on the roster'
);

assert(
  panelSource.includes('buildFollowerLaunchCharacterPayload(entity) {')
    && panelSource.includes('if (ownerCharacterId > 0 && followerCharacterId === ownerCharacterId) {')
    && panelSource.includes('character_id: followerCharacterId || null,')
    && panelSource.includes('this.showLaunchCharacter(payload, { storeAsPrimary: false });'),
  'Follower selections are transformed into launch-character payloads and rendered through existing Character tab logic'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
