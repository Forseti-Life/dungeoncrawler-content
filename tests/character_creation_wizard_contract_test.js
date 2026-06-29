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
const hardeningServiceSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/CharacterWizardHardeningService.php'),
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

assert(
  hardeningServiceSource.includes('$wizard = [];') &&
  hardeningServiceSource.includes("if ($key === 'wizard')") &&
  formSource.includes('return $this->wizardHardening->syncWizardDraftFromCharacterData($character_data);') &&
  controllerSource.includes('return $this->wizardHardening->syncWizardDraftFromCharacterData($character_data);'),
  'Wizard mirrors are rebuilt in shared hardening service and delegated consistently by form/controller save paths'
);

assert(
  formSource.includes('$character_data = $this->storeCalculatedAbilityScores($character_data, $calculation);') &&
  formSource.includes("$character_data['abilities'] = $abilities;") &&
  formSource.includes("$character_data['ability_scores'] = $ability_scores;") &&
  formSource.includes("$character_data['hit_points'] = [") &&
  formSource.includes("$resources['hitPoints'] = [") &&
  formSource.includes("$defenses['armorClass'] = 10 + $dex_mod;"),
  'Form save path persists recalculated abilities and derived combat stats into canonical and mirror payloads'
);

assert(
  controllerSource.includes('new AbilityScoreTracker($this->characterManager);') &&
  controllerSource.includes('$character_data = $this->storeCalculatedAbilityScores(') &&
  controllerSource.includes("$character_data['abilities'] = $abilities;") &&
  controllerSource.includes("$character_data['hit_points'] = [") &&
  controllerSource.includes("$resources['hitPoints'] = [") &&
  controllerSource.includes("$defenses['armorClass'] = 10 + $dex_mod;"),
  'Controller save path also refreshes canonical ability and derived combat mirrors after steps 2-5'
);

assert(
  formSource.includes("if ((int) ($character_data['step'] ?? 0) >= 8) {") &&
  formSource.includes("$character_data['wizard_complete'] = $pending_completion_grants === [];") &&
  controllerSource.includes("if ((int) ($character_data['step'] ?? 0) >= 8) {") &&
  controllerSource.includes("$character_data['wizard_complete'] = $pending_completion_grants === [];"),
  'Both save paths compute step-8 wizard_complete from pending completion grants before persistence'
);

assert(
  formSource.includes('buildFamiliarSelectionSection($form[\'class_dynamic\']') &&
  formSource.includes('resolveFamiliarSelectionSource(') &&
  formSource.includes('buildCreationFamiliarPayload('),
  'Step 4 now includes a familiar workflow branch and persists the familiar payload'
);

assert(
  formSource.includes("if (!isset($form['class_dynamic']['feat_selections']) || !is_array($form['class_dynamic']['feat_selections'])) {") &&
  formSource.includes("$form['class_dynamic']['feat_selections']['adapted-cantrip']"),
  'Adapted Cantrip preserves the shared feat selection container instead of overwriting other Step 4 workflows'
);

assert(
  formSource.includes("$item !== FALSE") &&
  formSource.includes("$item !== 0") &&
  formSource.includes("$item !== '0'"),
  'Wizard list normalization strips unchecked checkbox zero values before validation and persistence'
);

assert(
  formSource.includes("buildStaffNexusSelectionSection") &&
  formSource.includes("Staff Nexus choices will unlock as soon as those spell selections are present.") &&
  formSource.includes("The makeshift staff contains one cantrip from your selected arcane cantrips.") &&
  formSource.includes("The makeshift staff contains one selected 1st-rank spell from your spellbook."),
  'Staff Nexus surfaces a dedicated dependent selector and a pending-state prompt tied to selected cantrips and spells'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
