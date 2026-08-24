/**
 * @file
 * Contract test: Action Rail actor identity stays API-owned.
 *
 * Run with:
 *   node tests/action_rail_api_authority_contract_test.js
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

const shellSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/GameShell.js'), 'utf8');
const actionRailContextSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/services/action-rail-context-service.js'), 'utf8');

console.log('\n=== Action Rail API authority contracts ===');

assert(
  shellSource.includes('const launchCharacterInstanceId = this.launchCharacter?.instanceId || this.launchCharacter?.instance_id || null;')
    && shellSource.includes('const authoritativeInstanceId = launchCharacterInstanceId || (selectedIsLaunchActor ? selectedInstanceId : null);')
    && shellSource.includes('instanceId: selectedIsLaunchActor')
    && shellSource.includes('? (selectedInstanceId || authoritativeInstanceId)')
    && shellSource.includes(': authoritativeInstanceId,'),
  'runtime context never nulls an API-provided launchCharacter.instanceId when selectedEntity lacks a ref'
);

assert(
  actionRailContextSource.includes('const actor = serverTurnActor')
    && actionRailContextSource.includes('|| launchPlayer')
    && actionRailContextSource.includes('|| selectedControllableActor'),
  'Action Rail prefers the launch/player actor over local selected controllable actors'
);

assert(
  actionRailContextSource.includes('const authoritativeActorRef = String(')
    && actionRailContextSource.includes('runtimeContext?.instanceId')
    && actionRailContextSource.includes('|| runtimeContext?.instance_id')
    && actionRailContextSource.includes('|| launchPlayerRef')
    && actionRailContextSource.includes('const directActorRef = String(')
    && actionRailContextSource.includes('authoritativeActorRef')
    && actionRailContextSource.includes('|| selectRailEntityRef(actor)'),
  'Action Rail prefers API-backed actor refs before local entity refs'
);

console.log('\n==========================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
