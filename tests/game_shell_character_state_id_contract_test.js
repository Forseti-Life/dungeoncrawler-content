/**
 * @file
 * Focused regression for V2 launch character state-id resolution.
 *
 * Run with:
 *   node tests/game_shell_character_state_id_contract_test.js
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

const source = fs.readFileSync(path.resolve(__dirname, '../js/v2/GameShell.js'), 'utf8');

console.log('\n=== GameShell character state id contract ===');

const stateIdBlock = source.match(/resolveLaunchCharacterStateId\(\)\s*\{[\s\S]*?return Number\(([\s\S]*?)\)\s*\|\|\s*0;[\s\S]*?\}/);
const body = stateIdBlock ? stateIdBlock[1] : '';

assert(body.includes('this.launchCharacter?.id'), 'resolveLaunchCharacterStateId prefers hydrated runtime id');
assert(body.includes('this.launchCharacter?.characterId'), 'resolveLaunchCharacterStateId falls back to hydrated characterId');
assert(body.includes('this.launchContext?.character_id'), 'resolveLaunchCharacterStateId falls back to launchContext character_id');
assert(!body.includes('this.launchCharacter?.character_id'), 'resolveLaunchCharacterStateId does not reuse source character_id as the runtime state id');

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
