/**
 * @file
 * Contract test: newly created campaigns can auto-enroll in latency canary.
 *
 * Run with:
 *   node tests/latency_canary_new_campaign_enrollment_contract_test.js
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

const initServiceSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/CampaignInitializationService.php'),
  'utf8',
);
const servicesSource = fs.readFileSync(
  path.resolve(__dirname, '../dungeoncrawler_content.services.yml'),
  'utf8',
);

console.log('\n=== New-campaign latency canary enrollment contract ===');

assert(
  initServiceSource.includes('$this->enrollCampaignInLatencyCanaryIfEnabled($campaign_id);')
    && initServiceSource.includes('private function enrollCampaignInLatencyCanaryIfEnabled(int $campaign_id): void')
    && initServiceSource.includes("getEditable('dungeoncrawler_content.settings')")
    && initServiceSource.includes("get('latency_toggle_canary_campaign_ids')")
    && initServiceSource.includes("set('latency_toggle_canary_campaign_ids', implode(',', $ids))->save();"),
  'Campaign initialization appends new campaign IDs into latency canary config when enrollment is enabled'
);

assert(
  initServiceSource.includes('private function shouldAutoEnrollNewCampaignForLatencyCanary(): bool')
    && initServiceSource.includes("getenv('DC_LATENCY_AUTO_ENROLL_NEW_CAMPAIGNS')")
    && initServiceSource.includes("get('latency_toggle_auto_enroll_new_campaigns')"),
  'Auto-enrollment gate is controlled by env override and Drupal config'
);

assert(
  servicesSource.includes('dungeoncrawler_content.campaign_initialization:')
    && servicesSource.includes("- '@config.factory'"),
  'Campaign initialization service wiring includes config factory dependency'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
