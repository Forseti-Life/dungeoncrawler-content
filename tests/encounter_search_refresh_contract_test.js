/**
 * @file
 * Focused regression for encounter Search refresh behavior.
 *
 * Run with:
 *   node tests/encounter_search_refresh_contract_test.js
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

const encounterSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/systems/EncounterSystem.js'), 'utf8');
const legacySource = fs.readFileSync(path.resolve(__dirname, '../js/hexmap.js'), 'utf8');

console.log('\n=== Encounter action system-log contract ===');

assert(
  encounterSource.includes('_refreshSystemLogView() {')
    && encounterSource.includes("this.shell?.panels?.chat?.invalidateChatCaches?.({ sessionViews: ['system-log'] });")
    && encounterSource.includes("this.shell?.panels?.chat?.prefetchSessionViews?.(['system-log']);")
    && encounterSource.includes('if (result?.success) {')
    && encounterSource.includes('this._refreshSystemLogView();')
    && encounterSource.includes('if (retryResult?.success) {'),
  'v2 shared coordinator helper refreshes system-log for successful encounter actions'
);

assert(
  legacySource.includes('const searchDiscoveries = Array.isArray(data?.result?.discoveries)')
    && legacySource.includes("this.invalidateChatCaches({ sessionViews: ['system-log'] });")
    && legacySource.includes("this.prefetchSessionViews(['system-log']);")
    && legacySource.includes('if (searchDiscoveries.length > 0) {')
    && legacySource.includes('await hexmap.loadCharacterFromApi?.(context.characterId);')
    && legacySource.includes('await hexmap.refreshQuestJournalFromApi?.();'),
  'legacy encounter Search invalidates system-log and only refreshes character/journal when discoveries are returned'
);

assert(
  legacySource.includes("performCombatAction: async function (payload = {}) {")
    && legacySource.includes("this.invalidateChatCaches({ sessionViews: ['system-log'] });")
    && legacySource.includes("this.prefetchSessionViews(['system-log']);"),
  'legacy shared combat action helper refreshes system-log for successful encounter actions'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
