/**
 * @file
 * Contract test: hexmap payload must stamp campaign_id before building
 * navigation capabilities so NavigationService can load campaign connector rows.
 *
 * Run with:
 *   node tests/hexmap_navigation_campaign_connector_contract_test.js
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

const controllerSource = fs.readFileSync(path.resolve(__dirname, '../src/Controller/HexMapController.php'), 'utf8');

console.log('\n=== Hexmap navigation campaign connector contract ===');

const campaignIdAssignIndex = controllerSource.indexOf("$dungeon_payload['campaign_id'] = (int) ($launch_context['campaign_id'] ?? 0);");
const navBuildIndex = controllerSource.indexOf("$dungeon_payload['navigation_capabilities'] = $active_room_id !== ''");

assert(
  campaignIdAssignIndex !== -1
    && navBuildIndex !== -1
    && campaignIdAssignIndex < navBuildIndex,
  'HexMapController stamps campaign_id onto dungeon payload before building navigation capabilities'
);

assert(
  !controllerSource.includes('room_scope[self::STARTER_TAVERN_ROOM_ID] = TRUE;')
    && !controllerSource.includes('room_scope[self::STARTER_STREETS_ROOM_ID] = TRUE;'),
  'HexMapController launch-slice scope no longer forces tavern and streets to be provisioned as an inseparable pair'
);

console.log('\n====================================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
