/**
 * Contract test: character creation feat pickers must filter to creation-legal
 * feats whose prerequisites are currently satisfied.
 *
 * Run with:
 *   node tests/wizard_feat_prerequisite_filter_contract_test.js
 */

const fs = require('fs');
const path = require('path');
const assert = require('assert');

const source = fs.readFileSync(
  path.resolve(__dirname, '../src/Form/CharacterCreationStepForm.php'),
  'utf8'
);

assert(
  source.includes('getCreationEligibleAncestryFeats(') &&
  source.includes('getCreationEligibleClassFeats(') &&
  source.includes('getCreationEligibleGeneralFeats('),
  'Wizard form defines creation-eligible ancestry/class/general feat filters'
);

assert(
  source.includes("if ((int) ($feat['level'] ?? 1) !== 1)") &&
  source.includes("trim((string) ($feat['prerequisites'] ?? ''))"),
  'Creation feat eligibility enforces level-1 scope and prerequisite evaluation'
);

assert(
  source.includes("if ($normalized === 'spellcasting class feature')") &&
  source.includes("preg_match('/^trained in (.+)$/i', $atom, $matches)") &&
  source.includes("str_ends_with($normalized, ' order')") &&
  source.includes("str_ends_with($normalized, ' bloodline')"),
  'Prerequisite evaluation handles spellcasting, trained skills, orders, and bloodlines'
);

assert(
  source.includes("$ancestry_feats = $this->getCreationEligibleAncestryFeats($ancestry_name, $character_data);") &&
  source.includes("$class_feats = $this->getCreationEligibleClassFeats($selected_class, $character_data);") &&
  source.includes("foreach ($this->getCreationEligibleGeneralFeats($character_data) as $feat)"),
  'Wizard build steps use creation-eligible filtered feat lists'
);

assert(
  source.includes("Choose a valid ancestry feat for the current character build.") &&
  source.includes("Choose a valid class feat for the current character build.") &&
  source.includes("Choose a valid general feat for the current character build."),
  'Wizard validation rejects feats that are no longer eligible for the current build'
);

console.log('OK wizard feat prerequisite filter contract');
