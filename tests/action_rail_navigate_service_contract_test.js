/**
 * @file
 * Focused regressions for shared Action Rail navigate-location service usage.
 *
 * Run with:
 *   node tests/action_rail_navigate_service_contract_test.js
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

const serviceSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/services/navigate-location-service.js'), 'utf8');
const panelSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/panels/ActionRailPanel.js'), 'utf8');
const navigationSystemSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/systems/NavigationSystem.js'), 'utf8');

console.log('\n=== Action rail navigate service contract ===');

assert(
  serviceSource.includes('export async function fetchVisitedNavigateLocationGroups(campaignId) {')
    && serviceSource.includes("fetch(`/api/campaign/${numericCampaignId}/visited-locations`")
    && serviceSource.includes("throw new Error(data.error || 'Unable to load visited locations.');"),
  'navigate-location service owns the visited-locations API fetch + canonical error contract'
);

assert(
  panelSource.includes("import { fetchVisitedNavigateLocationGroups } from '../services/navigate-location-service.js';")
    && panelSource.includes('this.navigateLocationsInflight = fetchVisitedNavigateLocationGroups(campaignId)')
    && !panelSource.includes('fetch(`/api/campaign/${campaignId}/visited-locations`'),
  'ActionRailPanel uses shared navigate-location service instead of duplicating API fetch logic'
);

assert(
  navigationSystemSource.includes("import { fetchVisitedNavigateLocationGroups } from '../services/navigate-location-service.js';")
    && navigationSystemSource.includes('this.navigateLocationsInflight = fetchVisitedNavigateLocationGroups(campaignId)')
    && !navigationSystemSource.includes('fetch(`/api/campaign/${campaignId}/visited-locations`'),
  'NavigationSystem uses shared navigate-location service instead of duplicating API fetch logic'
);

console.log('\n=============================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
