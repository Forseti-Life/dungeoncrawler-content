/**
 * @file
 * Contract checks for CharacterPanel authoritative refresh scoping.
 *
 * Run with:
 *   node tests/character_panel_authoritative_refresh_scope_contract_test.js
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

const source = fs.readFileSync(path.resolve(__dirname, '../js/v2/panels/CharacterPanel.js'), 'utf8');

console.log('\n=== CharacterPanel character-state primary refresh contract ===');

assert(
  source.includes('const characterId = Number(this.resolveEntityCharacterId(entity) || 0) || 0;')
    && source.includes('const campaignId = Number(')
    && source.includes('query.set(\'campaignId\', String(campaignId));')
    && source.includes('query.set(\'instanceId\', targetRef);')
    && source.includes('fetch(`/api/character/${encodeURIComponent(characterId)}/state?${query.toString()}`'),
  'authoritative refresh uses /api/character/{id}/state with campaignId+instanceId scope'
);

assert(
  source.includes('if (characterId <= 0 || campaignId <= 0 || !targetRef) {')
    && source.includes('message: String(error?.message || error || \'\'),')
    && source.includes('characterId,'),
  'refresh enforces required scope and reports character-scoped failures'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
