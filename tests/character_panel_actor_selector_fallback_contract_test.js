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

// The party selector was re-sourced from the launch-character `followers`
// array onto the canonical server-side active-room actor roster
// (hexmap.getActiveRoomActorRoster()). The launch-character roster is retained
// only for resolving a selected ref back to its follower entry.
assert(
  source.includes('resolvePrimaryFollowerRoster() {')
    && source.includes('resolveFollowerRosterEntryByRef(actorRef = \'\') {')
    && source.includes('buildActorOptionsFromCanonicalRoster() {')
    && source.includes('const canonicalRosterOptions = this.buildActorOptionsFromCanonicalRoster();'),
  'Party selector is sourced from the canonical active-room actor roster'
);

// The old contract threw on roster entries missing runtime_entity_id /
// follower_kind / owner_character_id / follower_character_id. Those four
// concerns are now carried by the canonical roster option builder: the runtime
// ref is mandatory (entries without one are dropped, never emitted with a
// fabricated value), and the three follower identity fields are derived from
// the canonical sheet_ref rather than guessed.
assert(
  source.includes("const rosterEntries = Array.isArray(hexmap?.getActiveRoomActorRoster?.())")
    && source.includes('      if (!runtimeRef) {\n        return null;\n      }')
    && source.includes("entry?.sheet_ref?.sheet_type === 'follower'")
    && source.includes("? (entry?.sheet_ref?.route_params?.follower_kind || '')")
    && source.includes('const ownerCharacterId = Number(entry?.sheet_ref?.route_params?.character_id || 0) || 0;')
    && source.includes("entry?.sheet_ref?.sheet_type === 'character'"),
  'Canonical roster options require a runtime ref and derive follower identity from sheet_ref'
);

assert(
  source.includes('const canonicalRosterOptions = this.buildActorOptionsFromCanonicalRoster();')
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
