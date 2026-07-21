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

const navigatePanelServiceSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/services/action-rail-navigate-panel-service.js'), 'utf8');
const legacyHexmapSource = fs.readFileSync(path.resolve(__dirname, '../js/hexmap.js'), 'utf8');
const panelSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/panels/ActionRailPanel.js'), 'utf8');
const navigationSystemSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/systems/NavigationSystem.js'), 'utf8');

console.log('\n=== Action rail navigate service contract ===');

assert(
  !navigatePanelServiceSource.includes("import { fetchVisitedNavigateLocationGroups } from './navigate-location-service.js';")
    && navigatePanelServiceSource.includes('const groups = collectNavigateExitGroups(panel, context);')
    && navigatePanelServiceSource.includes("title: 'Room exits'")
    && !navigatePanelServiceSource.includes('collectVisitedNavigateLocationGroups(')
    && !navigatePanelServiceSource.includes('Known destinations'),
  'navigate panel service renders direct room exits only and does not merge known destinations'
);

assert(
  !legacyHexmapSource.includes("import { fetchVisitedNavigateLocationGroups } from './v2/services/navigate-location-service.js';")
    && legacyHexmapSource.includes('const groups = this.collectNavigateLocationGroups(context);')
    && !legacyHexmapSource.includes('const visitedGroups = this.collectVisitedNavigateLocationGroups(context, campaignId);')
    && !legacyHexmapSource.includes('const questTargetGroups = this.collectQuestTargetNavigateLocationGroups(context, routeGroups, visitedGroups);')
    && !legacyHexmapSource.includes('Known destinations'),
  'legacy hexmap navigation also renders direct room exits only'
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
    && navigationSystemSource.includes("this.bus.on('user:navigate', (d) => this.executeDirectNavigate(d?.button))")
    && navigationSystemSource.includes('const authoritativeState = await this._getAuthoritativeCoordinatorState(coordinator, hexmap);')
    && navigationSystemSource.includes('await this.shell.loadRuntimeStateBundle({')
    && navigationSystemSource.includes('room_id: nextRoomId,'),
  'NavigationSystem owns only navigate execution; visited-location preloading stays in ActionRailPanel'
);

assert(
  navigationSystemSource.includes("connectionId.startsWith('quest-synthetic-')")
    && navigationSystemSource.includes('const hasCanonicalTransition = Boolean(connectionId) || Boolean(matchedCapability);')
    && navigationSystemSource.includes('(!roomExistsInCurrentDungeon && !hasCanonicalTransition) || isQuestSyntheticDestination')
    && navigationSystemSource.includes('await this.requestInSessionDestination(roomId || roomName, {'),
  'Only unresolved non-canonical destinations (or quest-synthetic) use in-session destination resolution'
);

console.log('\n=============================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
