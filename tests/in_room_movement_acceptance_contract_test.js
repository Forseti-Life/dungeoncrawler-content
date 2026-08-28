/**
 * @file
 * Contract test: in-room movement closeout acceptance guards.
 *
 * Run with:
 *   node tests/in_room_movement_acceptance_contract_test.js
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
const mapArtifactsSource = fs.readFileSync(path.resolve(__dirname, './hexmap_v2_map_artifacts_test.js'), 'utf8');
const bootstrapSource = fs.readFileSync(path.resolve(__dirname, './game_coordinator_bootstrap_state_contract_test.js'), 'utf8');

console.log('\n=== In-room movement acceptance closeout contracts ===');

assert(
  shellSource.includes('resolveLaunchCharacterRuntimeContext() {')
    && shellSource.includes('const selectedIsLaunchActor = launchCharacterId > 0 && selectedCharacterId === launchCharacterId;')
    && shellSource.includes('characterId: selectedIsLaunchActor')
    && shellSource.includes('instanceId: selectedIsLaunchActor')
    && shellSource.includes('return matchesLaunchCharacter;'),
  'PC character-state refresh remains isolated from controlled-follower action routing'
);

assert(
  shellSource.includes("['step', 'stride'].includes(actionType)")
    && shellSource.includes('this.applyLocalEntityPlacement(')
    && shellSource.includes("window.dispatchEvent(new window.CustomEvent('dungeoncrawler:game-events'")
    && shellSource.includes('movement_packet: movementPacket')
    && shellSource.includes('const hazardEvents = Array.isArray(payload?.data?.hazardEvents) ? payload.data.hazardEvents : [];')
    && shellSource.includes("type: 'hazard_triggered'")
    && shellSource.includes("hazardName} triggers as ${actorLabel} moves to")
    && shellSource.includes("movement_mode: 'room_move'")
    && shellSource.includes('showMovementHighlightBandsForEntity(entity) {')
    && shellSource.includes('buildMovementHighlightBands(entity) {'),
  'post-move path emits canonical movement and hazard event metadata and re-anchors movement rendering'
);

assert(
  mapArtifactsSource.includes("display_name: 'Burasco'")
    && mapArtifactsSource.includes("display_name: 'Mimi'")
    && mapArtifactsSource.includes("name: 'Burasco'")
    && mapArtifactsSource.includes('canonical party occupants'),
  'Burasco and Mimi canonical map-occupant projection coverage is present'
);

assert(
  bootstrapSource.includes('const shouldFetchInitialState = !bootstrapState')
    && bootstrapSource.includes('|| bootstrapAvailableActions.length === 0')
    && bootstrapSource.includes('|| !bootstrapActionContract;'),
  'bootstrap parity guard remains enforced when action contract data is missing'
);

console.log('\n======================================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
