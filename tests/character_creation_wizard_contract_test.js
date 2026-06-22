/**
 * Focused regression for character creation wizard draft synchronization.
 *
 * Run with:
 *   node tests/character_creation_wizard_contract_test.js
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

const formSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Form/CharacterCreationStepForm.php'),
  'utf8'
);
const controllerSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Controller/CharacterCreationStepController.php'),
  'utf8'
);

console.log('\n=== Character creation wizard contract ===');

assert(
  formSource.includes("$has_canonical_payload = is_array($data['basicInfo'] ?? NULL)") &&
  formSource.includes('? $data') &&
  formSource.includes(": (is_array($data['wizard'] ?? NULL) ? $data['wizard'] : $data);"),
  'Character creation form loader prefers canonical top-level payload over stale nested wizard drafts'
);

assert(
  formSource.includes('$character_data = $this->syncWizardDraftFromCharacterData($character_data);') &&
  controllerSource.includes('$character_data = $this->syncWizardDraftFromCharacterData($character_data);'),
  'Both character creation save paths resync the nested wizard draft before persistence'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
