/**
 * @file
 * Contract test: campaign entity move endpoint enforces actor move authority.
 *
 * Run with:
 *   node tests/campaign_entity_move_authority_contract_test.js
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

const source = fs.readFileSync(path.resolve(__dirname, '../src/Controller/CampaignEntityController.php'), 'utf8');

console.log('\n=== Campaign entity move authority contracts ===');

assert(
  source.includes('use Drupal\\dungeoncrawler_content\\Service\\CampaignAuthorizationService;')
    && source.includes('private CampaignAuthorizationService $campaignAuthorization;')
    && source.includes("$container->get('dungeoncrawler_content.campaign_authorization')"),
  'CampaignEntityController receives campaign authorization service'
);

assert(
  source.includes('if (!$this->canMoveEntity($campaign_id, $entity)) {')
    && source.includes("'error' => 'You are not allowed to move this actor.'"),
  'move endpoint rejects unauthorized actor movement before persistence'
);

assert(
  source.includes("$current_mode === 'gm' && !empty($campaign_access['can_use_gm_mode'])")
    && source.includes("strtolower(trim((string) ($entity['type'] ?? ''))) !== 'pc'")
    && source.includes("$campaign_access['playable_principals'] ?? []")
    && source.includes("$metadata['follower_kind']")
    && source.includes("$owner_source_character_id"),
  'move endpoint allows GM any-actor moves and player-mode playable PC or controlled follower moves'
);

assert(
  source.includes('use Drupal\\dungeoncrawler_content\\Service\\CampaignRuntimeStateStore;')
    && source.includes('private CampaignRuntimeStateStore $runtimeStateStore;')
    && source.includes("$container->get('dungeoncrawler_content.campaign_runtime_state_store')"),
  'CampaignEntityController receives campaign runtime state store service'
);

assert(
  source.includes("if ($location_type === 'room' && $this->isEntityInActiveEncounter($campaign_id, $instance_id)) {")
    && source.includes("'error' => 'This actor is an active combatant in an encounter and must move via the combat stride action, not a free room move.'"),
  'move endpoint rejects free room moves for entities that are live combatants in an active encounter'
);

assert(
  source.includes('private function isEntityInActiveEncounter(int $campaign_id, string $instance_id): bool {')
    && source.includes("$phase = strtolower(trim((string) ($game_state['phase'] ?? '')));")
    && source.includes("$phase !== 'encounter' || $encounter_id <= 0")
    && source.includes("$entity_id === $instance_id && !$is_defeated"),
  'active-encounter check inspects game_state phase, encounter id, and live (non-defeated) initiative order membership'
);

console.log('\n===============================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
