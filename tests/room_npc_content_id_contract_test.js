/**
 * @file
 * Room-management NPC content_id canonicalization contract.
 *
 * Run with:
 *   node tests/room_npc_content_id_contract_test.js
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

const hexMapControllerSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Controller/HexMapController.php'),
  'utf8'
);
const campaignInitializationServiceSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/CampaignInitializationService.php'),
  'utf8'
);

console.log('\n=== Room NPC content_id contract ===');

assert(
  hexMapControllerSource.includes('protected function normalizeRoomNpcContentIdContracts(array $contents_data, string $room_id): array')
    && hexMapControllerSource.includes('$this->normalizeRoomNpcContentIdContracts($result, $contents_room_id);')
    && hexMapControllerSource.includes("->update('dc_campaign_rooms')"),
  'Room contents loader canonicalizes NPC content_id values and persists normalized room-management payloads'
);

assert(
  hexMapControllerSource.includes("if (str_starts_with($normalized, 'npc_'))")
    && hexMapControllerSource.includes("elseif (str_starts_with($normalized, 'npc-'))"),
  'Canonical room NPC content_id policy strips legacy npc_ / npc- prefixes at source'
);

assert(
  hexMapControllerSource.includes("$ecid = $this->canonicalizeRoomNpcContentId((string) ($entity['entity_ref']['content_id'] ?? ''));")
    && hexMapControllerSource.includes("$content_id = $this->canonicalizeRoomNpcContentId((string) ($npc['content_id'] ?? ''));"),
  'Room NPC injection dedupe and authored NPC identity both use canonical room-management content_id policy'
);

assert(
  campaignInitializationServiceSource.includes("$content_id = $this->canonicalizeRoomNpcContentId((string) ($npc['content_id'] ?? ''));")
    && campaignInitializationServiceSource.includes("$instance_id = 'npc_' . $content_id;")
    && campaignInitializationServiceSource.includes('Starter room contains duplicate NPC content_id'),
  'Campaign initialization seeds runtime NPC identities from canonical room-management content IDs only'
);

console.log('\n====================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
