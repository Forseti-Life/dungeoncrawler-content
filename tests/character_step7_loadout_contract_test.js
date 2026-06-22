/**
 * @file
 * Contract coverage for Step 7 class loadout wiring.
 *
 * Run with:
 *   node tests/character_step7_loadout_contract_test.js
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

function read(relativePath) {
  return fs.readFileSync(path.join(__dirname, '..', relativePath), 'utf8');
}

const formSource = read('src/Form/CharacterCreationStepForm.php');
const managerSource = read('src/Service/CharacterManager.php');
const jsSource = read('js/character-step-7.js');
const functionalSource = read('tests/src/Functional/CharacterCreation/CharacterCreationWorkflowTest.php');

console.log('Step 7 loadout contract');

assert(
  formSource.includes("'#type' => 'html_tag'") &&
    formSource.includes("'#tag' => 'button'") &&
    formSource.includes("'type' => 'button'"),
  'Preset apply control is rendered as a non-submit HTML button'
);

assert(
  formSource.includes("'data-step7-loadout-apply' => $class_loadout_preset['id']"),
  'Preset apply button exposes the data-step7-loadout-apply hook'
);

assert(
  formSource.includes("'presets' => $class_loadout_preset !== NULL"),
  'Step 7 form exposes preset ids through drupalSettings'
);

assert(
  managerSource.includes('public static function getDefaultEquipmentLoadoutIdsForClass') &&
    managerSource.includes('public static function getDefaultEquipmentLoadoutPreferencesByClass'),
  'CharacterManager defines shared deterministic class loadout mappings'
);

assert(
  managerSource.includes("'fighter' =>") &&
    managerSource.includes("'wizard' =>") &&
    managerSource.includes("'gunslinger' =>"),
  'Class loadout mapping covers multiple class families including martial, caster, and gunslinger'
);

assert(
  jsSource.includes("var $presetButtons = $form.find('[data-step7-loadout-apply]');") &&
    jsSource.includes('event.preventDefault();') &&
    jsSource.includes("applyPreset(this.getAttribute('data-step7-loadout-apply'));"),
  'Step 7 JavaScript applies presets through the non-submit button hook without submitting the form'
);

assert(
  functionalSource.includes("'weapons[longsword]' => 'longsword'") &&
    functionalSource.includes("'armor[leather]' => 'leather'") &&
    functionalSource.includes("'gear[backpack]' => 'backpack'"),
  'Functional wizard coverage uses the real Step 7 field groups'
);

if (failed > 0) {
  console.error(`\n${failed} assertion(s) failed; ${passed} passed.`);
  process.exit(1);
}

console.log(`\nAll ${passed} assertions passed.`);
