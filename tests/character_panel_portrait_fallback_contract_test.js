/**
 * @file
 * Regression contract for CharacterPanel portrait source handling.
 *
 * Run with:
 *   node tests/character_panel_portrait_fallback_contract_test.js
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
const controllerSource = fs.readFileSync(path.resolve(__dirname, '../src/Controller/HexMapController.php'), 'utf8');

console.log('\n=== CharacterPanel portrait source contract ===');

assert(
  source.includes('state.portrait?.url')
    && source.includes('basicInfo.portrait?.url')
    && source.includes('launchCharacter.portrait?.url'),
  'CharacterPanel supports nested portrait.url fields in launch payload'
);

const portraitBlockMatch = source.match(/\/\/ Portrait[\s\S]*?const portraitUrl = firstNonEmptyText\([\s\S]*?\);/);
assert(
  !!portraitBlockMatch
    && !portraitBlockMatch[0].includes('launchEntityPortraitUrl')
    && !portraitBlockMatch[0].includes('findLaunchPlayerEntity'),
  'CharacterPanel does not use entity-level portrait fallback for sheet portrait rendering'
);

assert(
  controllerSource.includes("$portrait_url = $this->normalizePortraitUrl((string) ($record['portrait'] ?? ''));")
    && controllerSource.includes("$portrait_url = $this->normalizePortraitUrl((string) ($character_data['portrait_url'] ?? $character_data['portrait'] ?? ''));")
    && controllerSource.includes("'portrait' => $portrait_url,"),
  'HexMap launch character payload sources portrait directly from runtime character data first'
);

assert(
  source.includes('this._el.characterPortrait.removeAttribute(\'src\');')
    && source.includes('this._el.characterPortrait.alt = \'\';')
    && source.includes('this._el.characterPortraitWrap.style.display = \'none\';'),
  'CharacterPanel clears stale portrait state when no portrait source is available'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
