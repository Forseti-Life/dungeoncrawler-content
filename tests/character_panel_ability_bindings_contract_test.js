/**
 * @file
 * Contract test for V2 CharacterPanel ability DOM bindings.
 *
 * Run with:
 *   node tests/character_panel_ability_bindings_contract_test.js
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

const sourcePath = path.resolve(__dirname, '../js/v2/panels/CharacterPanel.js');
const source = fs.readFileSync(sourcePath, 'utf8');

console.log('\n=== CharacterPanel ability binding contract ===');

const requiredAbilityBindings = {
  characterStr: 'char-str',
  characterStrMod: 'char-str-mod',
  characterDex: 'char-dex',
  characterDexMod: 'char-dex-mod',
  characterCon: 'char-con',
  characterConMod: 'char-con-mod',
  characterInt: 'char-int',
  characterIntMod: 'char-int-mod',
  characterWis: 'char-wis',
  characterWisMod: 'char-wis-mod',
  characterCha: 'char-cha',
  characterChaMod: 'char-cha-mod',
};

for (const [key, domId] of Object.entries(requiredAbilityBindings)) {
  const bindingPattern = new RegExp(`${key}:\\s*['"]${domId}['"]`);
  assert(bindingPattern.test(source), `${key} maps to ${domId} in abilityBindingIds`);
}

assert(/\.\.\.abilityElements,/.test(source), 'abilityElements are spread into the _el map');

const abilityPairPattern = /\['Str',[\s\S]*\['Dex',[\s\S]*\['Con',[\s\S]*\['Int',[\s\S]*\['Wis',[\s\S]*\['Cha'/m;
assert(abilityPairPattern.test(source), 'showLaunchCharacter includes all six ability pairs');

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
