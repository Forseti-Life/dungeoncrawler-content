/**
 * @file
 * Focused regression for CharacterPanel reward refresh wiring.
 *
 * Run with:
 *   node tests/character_panel_refresh_contract_test.js
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
const shellSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/GameShell.js'), 'utf8');

console.log('\n=== CharacterPanel refresh contract ===');

assert(
  source.includes("this.bus.on('character:updated', (d) => {")
    && source.includes('this.stateManager?.hexmap?.launchCharacter')
    && source.includes('this.stateManager?.hexmap?.characterData')
    && source.includes('if (launchCharacter) this.showLaunchCharacter(launchCharacter);'),
  'CharacterPanel re-renders launch character data on character:updated events'
);

assert(
  !source.includes("this.bus.emit('character:updated');"),
  'CharacterPanel does not recursively emit character:updated from showLaunchCharacter'
);

assert(
  shellSource.includes("this.bus.emit('character:updated', { launchCharacter: this.launchCharacter });"),
  'GameShell emits the freshly hydrated launchCharacter payload with character:updated'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
