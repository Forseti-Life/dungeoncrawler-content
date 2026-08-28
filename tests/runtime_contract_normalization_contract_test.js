/**
 * Contract test: campaign character selection and hexmap launch should route
 * through the shared campaign runtime resolver service.
 *
 * Run with:
 *   node tests/runtime_contract_normalization_contract_test.js
 */

const fs = require('fs');
const path = require('path');
const assert = require('assert');

const serviceSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/CampaignCharacterRuntimeResolverService.php'),
  'utf8'
);
const campaignControllerSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Controller/CampaignController.php'),
  'utf8'
);
const hexMapControllerSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Controller/HexMapController.php'),
  'utf8'
);
const creationFormSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Form/CharacterCreationStepForm.php'),
  'utf8'
);
const creationControllerSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Controller/CharacterCreationStepController.php'),
  'utf8'
);
const wizardHardeningSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/CharacterWizardHardeningService.php'),
  'utf8'
);

assert(
  serviceSource.includes('class CampaignCharacterRuntimeResolverService') &&
  serviceSource.includes('public function resolveStarterRoomIdForCampaign') &&
  serviceSource.includes('public function loadRuntimeRecord') &&
  serviceSource.includes('public function upsertRuntimeRecord'),
  'Runtime resolver service defines shared starter-room, lookup, and upsert contract methods'
);

assert(
  campaignControllerSource.includes('dungeoncrawler_content.campaign_character_runtime_resolver') ||
  campaignControllerSource.includes('CampaignCharacterRuntimeResolverService'),
  'CampaignController depends on the shared runtime resolver service'
);

assert(
  campaignControllerSource.includes('$this->runtimeResolver->upsertRuntimeRecord('),
  'CampaignController selects campaign characters through the shared runtime resolver'
);

assert(
  hexMapControllerSource.includes('$this->campaignCharacterRuntimeResolver->loadRuntimeRecord(') &&
  hexMapControllerSource.includes('$this->campaignCharacterRuntimeResolver->upsertRuntimeRecord('),
  'HexMapController launch flow uses the shared runtime resolver for record lookup and materialization'
);

// Creation finalization no longer calls the resolver inline; both paths now
// delegate to CharacterWizardHardeningService, which owns the resolver call.
// Assert the whole chain so the contract cannot be broken at either link.
assert(
  creationFormSource.includes('$this->wizardHardening->ensureCampaignCharacterHasCanonicalSource($character_id, $campaign_id)') &&
  creationControllerSource.includes('$this->wizardHardening->ensureCampaignCharacterHasCanonicalSource($character_id, $campaign_id)'),
  'Character creation finalization paths delegate canonical-source setup to the wizard hardening service'
);

assert(
  wizardHardeningSource.includes('public function ensureCampaignCharacterHasCanonicalSource') &&
  wizardHardeningSource.includes('$this->runtimeResolver->resolveStarterRoomIdForCampaign($campaign_id)'),
  'Wizard hardening service uses the shared starter-room resolver when establishing the canonical source'
);

console.log('OK runtime contract normalization contract');
