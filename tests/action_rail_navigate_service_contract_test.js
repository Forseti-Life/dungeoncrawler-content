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
const navigatePanelServiceSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/services/action-rail-navigate-panel-service.js'), 'utf8');
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
  navigatePanelServiceSource.includes("import { fetchVisitedNavigateLocationGroups } from './navigate-location-service.js';")
    && navigatePanelServiceSource.includes('panel.navigateLocationsInflight = fetchVisitedNavigateLocationGroups(campaignId)')
    && navigatePanelServiceSource.includes('panel.navigateLocationsCampaignId === campaignId && Array.isArray(panel.navigateLocationGroups)')
    && !navigatePanelServiceSource.includes('fetch(`/api/campaign/${campaignId}/visited-locations`'),
  'navigate panel service owns visited-location preload behavior via shared navigate-location API service'
);

assert(
  panelSource.includes("import { buildNavigateActionRailPanel } from '../services/action-rail-navigate-panel-service.js';")
    && panelSource.includes('navigate: () => buildNavigateActionRailPanel(this, context),')
    && !panelSource.includes('this.navigateLocationsInflight = fetchVisitedNavigateLocationGroups(campaignId)'),
  'ActionRailPanel delegates navigate category rendering/preload to the dedicated navigate panel service'
);

assert(
  !navigationSystemSource.includes("import { fetchVisitedNavigateLocationGroups } from '../services/navigate-location-service.js';")
    && !navigationSystemSource.includes('ensureNavigateLocationGroups(')
    && navigationSystemSource.includes("this.bus.on('user:navigate', (d) => this.executeDirectNavigate(d?.button))"),
  'NavigationSystem owns only navigate execution; visited-location preloading stays in ActionRailPanel'
);

assert(
  navigationSystemSource.includes("connectionId.startsWith('quest-synthetic-')")
    && navigationSystemSource.includes('!roomExistsInCurrentDungeon || isQuestSyntheticDestination')
    && navigationSystemSource.includes('await this.requestInSessionDestination(roomId || roomName, {'),
  'Quest-synthetic navigate entries use in-session destination resolution instead of direct transition validation'
);

console.log('\n=============================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
