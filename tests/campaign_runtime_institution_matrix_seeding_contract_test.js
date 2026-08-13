/**
 * @file
 * Contract test: runtime institution matrix seeding hook.
 *
 * Run with:
 *   node tests/campaign_runtime_institution_matrix_seeding_contract_test.js
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

const syncSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/CampaignCharacterRuntimeSyncService.php'),
  'utf8',
);
const servicesSource = fs.readFileSync(
  path.resolve(__dirname, '../dungeoncrawler_content.services.yml'),
  'utf8',
);

console.log('\n=== Campaign runtime institution matrix seeding contract ===');

assert(
  syncSource.includes('protected ?InstitutionDispositionMatrixService $institutionDispositionMatrixService;')
    && syncSource.includes('protected array $institutionMatrixSeededCampaigns = [];')
    && syncSource.includes('seedInstitutionMatrixDefaultsForCampaign(int $campaign_id): void'),
  'Runtime sync service tracks per-request campaign matrix seeding state and exposes a dedicated seeding hook'
);

assert(
  syncSource.includes('syncRuntimeActorInstitutionMemberships(')
    && syncSource.includes('$this->seedInstitutionMatrixDefaultsForCampaign($campaign_id);')
    && syncSource.includes('$this->institutionDispositionMatrixService->seedNeutralDefaultsForCampaign($campaign_id);'),
  'Runtime actor membership sync triggers neutral institution matrix seeding after membership writes'
);

assert(
  servicesSource.includes('dungeoncrawler_content.campaign_character_runtime_sync:')
    && servicesSource.includes("- '@?dungeoncrawler_content.institution_disposition_matrix'"),
  'Service wiring injects institution disposition matrix service into runtime sync'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
