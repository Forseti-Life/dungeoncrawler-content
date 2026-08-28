/**
 * @file
 * Contract test: campaign GM/player mode access cutover seams.
 *
 * Run with:
 *   node tests/campaign_gm_player_mode_access_contract_test.js
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

const authzSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/CampaignAuthorizationService.php'),
  'utf8',
);
const hexMapControllerSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Controller/HexMapController.php'),
  'utf8',
);
const settingsControllerSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Controller/CampaignSettingsController.php'),
  'utf8',
);
const gameShellSource = require('./helpers/js-source.js').readGameShellSource();
const playSessionControllerSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Controller/PlaySessionController.php'),
  'utf8',
);

console.log('\n=== Campaign GM/player mode access contract ===');

assert(
  authzSource.includes('function canUseGmMode(')
    && authzSource.includes('function canUsePlayerMode(')
    && authzSource.includes('function canPlayAsCharacter(')
    && authzSource.includes('function listPlayablePrincipals(')
    && authzSource.includes('function buildCampaignAccessContext('),
  'CampaignAuthorizationService exposes mode/principal capability methods'
);

assert(
  hexMapControllerSource.includes("'campaignAccess' => $campaign_access")
    && hexMapControllerSource.includes("'campaign_access' => $this->buildCampaignAccessBootstrap($launch_context)")
    && hexMapControllerSource.includes('protected function buildCampaignAccessBootstrap(array $launch_context): array'),
  'HexMapController ships campaign-access payload through bootstrap and visual-state API'
);

assert(
  settingsControllerSource.includes("'can_use_player_mode' =>")
    && settingsControllerSource.includes("'can_use_gm_mode' =>")
    && settingsControllerSource.includes("canUseGmMode($campaign_id, $uid)")
    && settingsControllerSource.includes("canUsePlayerMode($campaign_id, $uid)"),
  'CampaignSettingsController returns and validates explicit mode capabilities'
);

assert(
  gameShellSource.includes('this.campaignAccess = this._normalizeCampaignAccess(rawSettings.campaignAccess || {});')
    && gameShellSource.includes('this._applyCampaignModeGates();')
    && gameShellSource.includes('_normalizeCampaignAccess(input = {}) {')
    && gameShellSource.includes('_applyCampaignModeGates() {')
    && gameShellSource.includes(".session-view-tab[data-view=\"gm-private\"]"),
  'GameShell consumes campaign access and gates GM-private chat by effective mode/capability'
);

assert(
  playSessionControllerSource.includes("tableExists('dc_campaign_members')")
    && playSessionControllerSource.includes("insert('dc_campaign_members')")
    && playSessionControllerSource.includes("else {")
    && playSessionControllerSource.includes("insert('dc_campaign_characters')"),
  'PlaySessionController treats membership as primary invite authority with legacy fallback'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
