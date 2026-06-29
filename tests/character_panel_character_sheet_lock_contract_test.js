/**
 * @file
 * Regression contract for locking Character tab full-sheet link to the player character.
 *
 * Run with:
 *   node tests/character_panel_character_sheet_lock_contract_test.js
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
const templateSource = fs.readFileSync(path.resolve(__dirname, '../templates/hexmap-v2.html.twig'), 'utf8');

console.log('\n=== CharacterPanel unified sheet link contract ===');

assert(
  !templateSource.includes('id="char-actor-select"')
    && !templateSource.includes('id="char-actor-select-wrap"'),
  'Legacy character-tab selector controls remain removed'
);

assert(
  source.includes('resolveCurrentCharacterSheetHref() {')
    && source.includes('const sheetCharacterId = Number(this.currentCharacterContext?.sheetCharacterId || 0) || 0;')
    && source.includes('return this.buildCharacterSheetHref(sheetCharacterId);'),
  'Unified sheet link resolver supports primary player-character context'
);

const characterLinkSyncBlock = source.match(/syncCharacterSheetLinkForSelectedEntity\(entity = null\)\s*\{[\s\S]*?\n  \}/);
assert(
  !!characterLinkSyncBlock
    && characterLinkSyncBlock[0].includes('let href = this.resolveCurrentCharacterSheetHref();')
    && characterLinkSyncBlock[0].includes("if (selectedActorKind === 'follower') {")
    && characterLinkSyncBlock[0].includes('href = this.resolveSheetHrefForEntity(selectedEntity);'),
  'Unified sheet link stays on primary context unless a follower actor is selected'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
