/**
 * Focused regression for campaign delete form cleanup coverage.
 *
 * Run with:
 *   node tests/campaign_delete_form_contract_test.js
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

const source = fs.readFileSync(
  path.resolve(__dirname, '../src/Form/CampaignDeleteForm.php'),
  'utf8'
);

console.log('\n=== Campaign delete form contract ===');

assert(
  source.includes("'dc_campaign_quest_progress'") &&
  source.includes("'dc_campaign_item_instances'"),
  'Campaign delete form removes quest progress rows and campaign item rows'
);

assert(
  source.includes("'dc_campaign_quest_rewards_claimed'") &&
  source.includes("'dc_campaign_quest_confirmations'"),
  'Campaign delete form removes supporting quest reward/confirmation rows'
);

assert(
  source.includes('deleteCampaignScopedRows('),
  'Campaign delete form uses a shared campaign-scoped delete helper'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
