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

console.log('\n=== Encounter Search refresh contract ===');

assert(
  encounterSource.includes('const searchDiscoveries = Array.isArray(data?.result?.discoveries)')
    && encounterSource.includes("this.shell?.panels?.chat?.invalidateChatCaches?.({ sessionViews: ['system-log'] });")
    && encounterSource.includes("this.shell?.panels?.chat?.prefetchSessionViews?.(['system-log']);")
    && encounterSource.includes('if (searchDiscoveries.length > 0) {')
    && encounterSource.includes('await hexmap.loadCharacterFromApi?.(context.characterId);')
    && encounterSource.includes('await hexmap.refreshQuestJournalFromApi?.();'),
  'v2 encounter Search invalidates system-log and only refreshes character/journal when discoveries are returned'
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

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
