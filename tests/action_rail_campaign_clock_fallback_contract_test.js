/**
 * @file
 * Guards campaign-clock fallback wiring for both Action Rail implementations.
 *
 * Run with:
 *   node tests/action_rail_campaign_clock_fallback_contract_test.js
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

const contextServiceSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/services/action-rail-context-service.js'), 'utf8');
const legacyHexmapSource = fs.readFileSync(path.resolve(__dirname, '../js/hexmap.js'), 'utf8');

console.log('\n=== Action rail campaign clock fallback contract ===');

assert(
  contextServiceSource.includes('const campaignClock = phaseSnapshot?.campaignClock')
    && contextServiceSource.includes('|| phaseSnapshot?.gameTime')
    && contextServiceSource.includes('|| runtimeGameState?.campaign_clock')
    && contextServiceSource.includes('|| runtimeGameState?.game_time')
    && contextServiceSource.includes('const timedActivities = Array.isArray(phaseSnapshot?.timedActivities)')
    && contextServiceSource.includes('Array.isArray(runtimeGameState?.timed_activities) ? runtimeGameState.timed_activities : []'),
  'v2 Action Rail context falls back from phase snapshot to canonical runtime game-state clock data'
);

assert(
  legacyHexmapSource.includes('const runtimeGameState = hexmap?.dungeonData?.game_state || {};')
    && legacyHexmapSource.includes('const campaignClock = phaseSnapshot?.campaignClock')
    && legacyHexmapSource.includes('|| phaseSnapshot?.gameTime')
    && legacyHexmapSource.includes('|| runtimeGameState?.campaign_clock')
    && legacyHexmapSource.includes('|| runtimeGameState?.game_time')
    && legacyHexmapSource.includes('const timedActivities = Array.isArray(phaseSnapshot?.timedActivities)')
    && legacyHexmapSource.includes('Array.isArray(runtimeGameState?.timed_activities) ? runtimeGameState.timed_activities : []'),
  'legacy Action Rail header also falls back to canonical runtime game-state clock data'
);

console.log('\n===================================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
