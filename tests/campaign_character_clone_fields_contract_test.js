/**
 * Contract test: campaign/library clone actions must preserve portrait-related
 * and default-location fields from source actor rows.
 *
 * Run with:
 *   node tests/campaign_character_clone_fields_contract_test.js
 */

const fs = require('fs');
const path = require('path');
const assert = require('assert');

const runtimeResolverSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/CampaignCharacterRuntimeResolverService.php'),
  'utf8'
);
const wizardHardeningSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/CharacterWizardHardeningService.php'),
  'utf8'
);

assert(
  runtimeResolverSource.includes("'portrait' => $portrait") &&
  runtimeResolverSource.includes("'default_locations' => $default_locations"),
  'Runtime clone payload preserves portrait/default_locations'
);

assert(
  wizardHardeningSource.includes("'portrait' => $portrait") &&
  wizardHardeningSource.includes("'default_locations' => $default_locations"),
  'Campaign-to-library clone payload preserves portrait/default_locations'
);

console.log('OK campaign character clone fields contract');
