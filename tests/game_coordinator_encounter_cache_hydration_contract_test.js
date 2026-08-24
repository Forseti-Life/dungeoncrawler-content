/**
 * @file
 * Contract test for coordinator encounter-cache hydration wiring.
 *
 * Run with:
 *   node tests/game_coordinator_encounter_cache_hydration_contract_test.js
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

const coordinatorSource = fs.readFileSync(
  path.resolve(__dirname, '../js/game-coordinator/GameCoordinator.js'),
  'utf8'
);

console.log('\n=== GameCoordinator encounter-cache hydration contracts ===');

assert(
  coordinatorSource.includes("this._syncEncounterCacheFromAuthoritativeResult({\n        game_state: bootstrapState,")
    && coordinatorSource.includes("}, 'bootstrap');"),
  'bootstrap runtime commit also primes latest encounter cache'
);

assert(
  coordinatorSource.includes("this._commitRuntimeState(state, 'initial-state');")
    && coordinatorSource.includes('this._syncEncounterCacheFromAuthoritativeResult(state);'),
  'initial authoritative state fetch primes latest encounter cache'
);

assert(
  coordinatorSource.includes('participants: Array.isArray(response?.participants)')
    && coordinatorSource.includes('? response.participants')
    && coordinatorSource.includes('Array.isArray(gameState?.initiative_order)')
    && coordinatorSource.includes('Array.isArray(presentation?.initiative_order) ? presentation.initiative_order : []'),
  'encounter cache participants fall back to initiative order when participants are absent'
);

console.log('\n===========================================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
} else {
  console.log('ALL TESTS PASSED');
}
