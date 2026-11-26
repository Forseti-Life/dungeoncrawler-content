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

(async () => {
  const { EncounterSystem } = await import('../js/v2/systems/EncounterSystem.js');
  const system = Object.create(EncounterSystem.prototype);
  const calls = [];
  const coordinator = { api: {}, applyAuthoritativeUpdate() {} };
  const hexmap = {
    gameCoordinator: coordinator,
    launchContext: { character_id: 8172 },
    loadCharacterFromApi: async (id) => calls.push(['refresh', id]),
    refreshQuestJournalFromApi: async () => calls.push(['journal']),
  };
  const context = {
    hexmap,
    characterId: 5689,
    runtimeContext: { campaignId: 1159, characterId: 5689 },
    phaseSnapshot: { actionContract: { actor_id: 'pc-1159-1033' } },
  };
  system._getActionRailContext = () => context;
  system._beginActionRailRequest = () => true;
  system._endActionRailRequest = () => calls.push(['release']);
  system._appendChatLine = (...args) => calls.push(args);
  system._refreshActionRail = () => {};
  system.announceGameState = () => {};
  system._sendCoordinatorActionWithResync = async (_coordinator, type, actor, params) => {
    calls.push([type, actor, params]);
    return { success: true, result: { discoveries: [{ name: 'Clue' }] } };
  };

  await system.executeDirectSearch({});
  assert(
    JSON.stringify(calls) === JSON.stringify([
      ['search', 'pc-1159-1033', { search_mode: 'explicit' }],
      ['refresh', 8172], ['journal'], ['release'],
    ]),
    'Search ignores a foreign sheet row, submits the turn actor, and refreshes the current campaign character',
  );
  calls.length = 0;
  context.phaseSnapshot = {};
  await system.executeDirectSearch({});
  assert(
    calls.length === 2 && calls[0][0] === 'System' && calls[1][0] === 'release',
    'Search without a current actor explains the problem and sends no action',
  );

  console.log(`\nPassed: ${passed}`);
  console.log(`Failed: ${failed}`);
  process.exitCode = failed > 0 ? 1 : 0;
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
