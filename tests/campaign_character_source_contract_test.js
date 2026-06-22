/**
 * Focused regression for campaign-context character creation source linkage.
 *
 * Run with:
 *   node tests/campaign_character_source_contract_test.js
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

console.log('\n=== Campaign character source contract ===');

assert(
  formSource.includes('ensureCampaignCharacterHasCanonicalSource((int) $character_id, (int) $campaign_id);') &&
  controllerSource.includes('ensureCampaignCharacterHasCanonicalSource((int) $character_id, (int) $campaign_id);'),
  'Both final character creation paths ensure campaign-created characters gain a canonical library source row'
);

assert(
  formSource.includes("'campaign_id' => 0") &&
  formSource.includes("'character_id' => 0") &&
  formSource.includes("'location_type' => 'roster'"),
  'Campaign-created characters are cloned into canonical library rows before the campaign row links back to them'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
