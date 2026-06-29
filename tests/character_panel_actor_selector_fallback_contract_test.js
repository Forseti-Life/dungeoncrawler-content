/**
 * @file
 * Regression contract for CharacterPanel party selector roster authority.
 *
 * Run with:
 *   node tests/character_panel_actor_selector_fallback_contract_test.js
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

const source = fs.readFileSync(path.resolve(__dirname, '../js/v2/panels/CharacterPanel.js'), 'utf8');

console.log('\n=== CharacterPanel party follower selector contract ===');

assert(
  source.includes('resolvePrimaryFollowerRoster() {')
    && source.includes('resolveFollowerRosterEntryByRef(actorRef = \'\') {')
    && source.includes('const followerRoster = this.resolvePrimaryFollowerRoster();'),
  'Party selector is sourced from the launch-character follower roster'
);

assert(
  source.includes("throw new Error('Follower roster entry is missing runtime_entity_id.')")
    && source.includes('is missing follower_kind.')
    && source.includes('is missing owner_character_id.')
    && source.includes('is missing follower_character_id.'),
  'Party selector enforces hard follower roster contract requirements'
);

assert(
  source.includes('const followerRoster = this.resolvePrimaryFollowerRoster();')
    && !source.includes('visibleOccupantsByRef')
    && !source.includes('const occupants = typeof hexmap.getVisualOccupants'),
  'Party selector is roster-only and does not depend on projected occupant filtering'
);

assert(
  source.includes('const selectedValue = String(this._el.partyActorSelect?.value || \'\').trim();')
    && source.includes("value: '__primary__'")
    && source.includes('const preferredValue = [selectedValue, resolvedOptions[0]?.value]'),
  'Unified selector includes the main PC option and preserves current selection before defaulting'
);

assert(
  source.includes("placeholder.textContent = 'No characters available';")
    && source.includes("select.disabled = true;")
    && source.includes("wrap.style.display = '';"),
  'Unified selector remains visible (disabled) with explicit empty-state copy'
);

assert(
  source.includes('const followerRosterEntry = this.resolveFollowerRosterEntryByRef(selectedRef);')
    && source.includes('if (!followerRosterEntry) {')
    && !source.includes('this.isFollowerEntityForCurrentCharacter(selectedEntity, currentCharacterId)'),
  'Party selection gating uses roster membership, not entity ownership fallback checks'
);

assert(
  source.includes('const fallbackPayload = this.buildFollowerLaunchCharacterPayloadFromRosterEntry(followerRosterEntry);')
    && source.includes('this.showLaunchCharacter(fallbackPayload, { storeAsPrimary: false });')
    && source.includes('buildFollowerLaunchCharacterPayloadFromRosterEntry(followerEntry = null) {'),
  'Party sheet hydrates from roster payload when runtime follower entity is not yet projected'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
