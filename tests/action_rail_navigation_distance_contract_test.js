/**
 * @file
 * Contract checks for navigation distance display semantics in action rail.
 *
 * Run with:
 *   node tests/action_rail_navigation_distance_contract_test.js
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

const source = fs.readFileSync(
  path.resolve(__dirname, '../js/v2/services/action-rail-navigate-panel-service.js'),
  'utf8'
);

console.log('\n=== Action rail navigation distance contract ===');

assert(
  source.includes('function formatDistanceValue(distance, destinationType = \'room\', connectionType = \'\')'),
  'distance formatter accepts destination + connection context'
);

assert(
  source.includes('if (destinationType === \'road\')') && source.includes('return `access ${normalized}`;'),
  'road-destination distances render as access N'
);

assert(
  source.includes('if (connectionType === \'road_network\' && normalized > 0)') && source.includes('return `road ${normalized}`;'),
  'road-network distances render with road label semantics'
);

assert(
  source.includes('`Distance: ${formatDistanceValue(distanceValue, destinationType, connectionType)}`'),
  'room exits route destination + connection metadata into distance formatter'
);

assert(
  source.includes('const navigable = capability?.available !== false;')
    && source.includes('statusLabel: isQuestTarget ? \'🎯 Quest Target\' : (navigable ? \'Exit\' : \'Unavailable\')'),
  'room exits project authoritative availability into action labels'
);

assert(
  source.includes('!navigable && blockedReason ? `Blocked: ${formatBlockedReason(blockedReason)}` : \'\'')
    && source.includes('function formatBlockedReason(reason) {'),
  'room exits surface blocked_reason metadata in action-rail summary'
);

assert(
  !source.includes('Known destinations')
    && !source.includes('collectVisitedNavigateLocationGroups(')
    && source.includes('const groups = collectNavigateExitGroups(panel, context);'),
  'navigate panel renders direct exits only without known-destination merging'
);

assert(
  source.includes('const aUnavailable = a.navigable === false ? 1 : 0;')
    && source.includes('const bUnavailable = b.navigable === false ? 1 : 0;'),
  'navigate exits sort navigable routes ahead of unavailable routes'
);

console.log('\n===============================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
