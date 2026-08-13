/**
 * @file
 * Contract test: room-chat latency projection toggles are exposed in settings.
 *
 * Run with:
 *   node tests/action_availability_membership_projection_settings_contract_test.js
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
  path.resolve(__dirname, '../src/Form/DungeonCrawlerSettingsForm.php'),
  'utf8',
);
const schemaSource = fs.readFileSync(
  path.resolve(__dirname, '../config/schema/dungeoncrawler_content.schema.yml'),
  'utf8',
);
const installConfigSource = fs.readFileSync(
  path.resolve(__dirname, '../config/install/dungeoncrawler_content.settings.yml'),
  'utf8',
);

console.log('\n=== Action availability projection settings contract ===');

assert(
  formSource.includes("['action_availability_bypass_active_room_sync']")
    && formSource.includes("['action_availability_membership_projection_enabled']")
    && formSource.includes("['action_availability_turn_cache_enabled']")
    && formSource.includes("['room_entry_warmup_split_enabled']")
    && formSource.includes("['latency_toggle_canary_campaign_ids']")
    && formSource.includes("['latency_toggle_auto_enroll_new_campaigns']")
    && formSource.includes("->set('action_availability_bypass_active_room_sync', $form_state->getValue('action_availability_bypass_active_room_sync'))")
    && formSource.includes("->set('action_availability_membership_projection_enabled', $form_state->getValue('action_availability_membership_projection_enabled'))")
    && formSource.includes("->set('action_availability_turn_cache_enabled', $form_state->getValue('action_availability_turn_cache_enabled'))")
    && formSource.includes("->set('room_entry_warmup_split_enabled', $form_state->getValue('room_entry_warmup_split_enabled'))")
    && formSource.includes("->set('latency_toggle_canary_campaign_ids', trim((string) $form_state->getValue('latency_toggle_canary_campaign_ids')))")
    && formSource.includes("->set('latency_toggle_auto_enroll_new_campaigns', $form_state->getValue('latency_toggle_auto_enroll_new_campaigns'))"),
  'Settings form exposes and persists action-availability sync bypass and membership projection toggles'
);

assert(
  schemaSource.includes('action_availability_bypass_active_room_sync:')
    && schemaSource.includes('action_availability_membership_projection_enabled:')
    && schemaSource.includes('action_availability_turn_cache_enabled:')
    && schemaSource.includes('room_entry_warmup_split_enabled:')
    && schemaSource.includes('latency_toggle_canary_campaign_ids:')
    && schemaSource.includes('latency_toggle_auto_enroll_new_campaigns:'),
  'Configuration schema declares both latency cutover toggles'
);

assert(
  installConfigSource.includes('action_availability_bypass_active_room_sync: false')
    && installConfigSource.includes('action_availability_membership_projection_enabled: false')
    && installConfigSource.includes('action_availability_turn_cache_enabled: false')
    && installConfigSource.includes('room_entry_warmup_split_enabled: false')
    && installConfigSource.includes("latency_toggle_canary_campaign_ids: ''")
    && installConfigSource.includes('latency_toggle_auto_enroll_new_campaigns: true'),
  'Install defaults include latency toggle config for canary IDs and new-campaign auto-enrollment'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
