/**
 * @file
 * Contract test: read-lane action-availability bypasses membership sync writes.
 *
 * Run with:
 *   node tests/read_lane_membership_sync_bypass_contract_test.js
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
const readSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/CoordinatorRuntimeReadService.php'),
  'utf8',
);

console.log('\n=== Read-lane membership sync bypass contract ===');

assert(
  syncSource.includes('$membership_sync_bypassed = $this->shouldBypassMembershipSyncForReadLane($diagnostic_context);')
    && syncSource.includes('if (!$membership_sync_bypassed) {')
    && syncSource.includes("$this->syncRuntimeActorInstitutionMemberships($campaign_id, 'pc', $instance_id, $char_data);")
    && syncSource.includes("$this->syncRuntimeActorInstitutionMemberships($campaign_id, 'npc', $instance_id, $character_data);")
    && syncSource.includes('protected function shouldBypassMembershipSyncForReadLane(array $diagnostic_context = []): bool'),
  'Runtime sync gate skips membership reconciliation calls when read-lane projection mode is active'
);

assert(
  syncSource.includes('membership_sync_bypassed=@membership_sync_bypassed')
    && syncSource.includes("'@membership_sync_bypassed' => $membership_sync_bypassed ? 'yes' : 'no'"),
  'Runtime sync telemetry emits explicit membership_sync_bypassed marker'
);

assert(
  readSource.includes('membership_projection_mode=@membership_projection_mode')
    && readSource.includes("'@membership_projection_mode' => !empty($diagnostic_context['membership_projection_mode']) ? 'yes' : 'no'"),
  'Action-availability context logs include membership projection mode marker'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
