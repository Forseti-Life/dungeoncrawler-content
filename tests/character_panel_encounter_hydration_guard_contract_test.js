/**
 * @file
 * Contract checks for encounter-authoritative actor hydration in CharacterPanel.
 *
 * Run with:
 *   node tests/character_panel_encounter_hydration_guard_contract_test.js
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

console.log('\n=== CharacterPanel selected-actor rehydrate contract ===');

assert(
  source.includes('isEncounterPhaseActive() {')
    && source.includes("const phase = String(snapshot?.phase || '').trim().toLowerCase();")
    && source.includes("return phase === 'encounter'"),
  'CharacterPanel exposes an encounter-phase guard helper'
);

assert(
  source.includes('showActorCharacterFromEntity(entity, options = {}) {')
    && source.includes("this.refreshEncounterStateAndRerenderEntity(entity, 'actor', options);")
    && source.includes('showFollowerCharacterFromEntity(entity) {')
    && source.includes("this.refreshEncounterStateAndRerenderEntity(entity, 'follower');"),
  'CharacterPanel routes actor/follower sheet hydration through one refresh path'
);

assert(
  source.includes('const requestRoomId = String(')
    && source.includes('const activeRoomId = String(this.stateManager?.hexmap?.resolveActiveRoomId?.() || \'\').trim();')
    && source.includes('if (requestRoomId && activeRoomId && requestRoomId !== activeRoomId) {'),
  'CharacterPanel blocks stale rerender commits when room context changes mid-refresh'
);

assert(
  source.includes("this.bus.on('game:state-refreshed', () => this.rehydrateSelectedEntityFromEncounterCache()),")
    && source.includes('rehydrateSelectedEntityFromEncounterCache() {')
    && source.includes('this.refreshEncounterStateAndRerenderEntity('),
  'CharacterPanel rehydrates selected actor sheet by replaying the canonical refresh path on state updates'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
