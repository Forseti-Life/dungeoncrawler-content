/**
 * @file
 * Contract test: deterministic room combat initiation should include
 * canonical source_entity_ref grounding.
 *
 * Run with:
 *   node tests/room_chat_combat_source_entity_contract_test.js
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
  path.resolve(__dirname, '../src/Service/RoomChatServiceNpcInterjectionTrait.php'),
  'utf8',
);

console.log('\n=== Room chat combat source-entity grounding contract ===');

assert(
  source.includes("if ($intent === 'combat_engagement') {")
    && source.includes('$source_entity_ref = $this->resolvePlayerEntityRefForRoomAction($room_id, $dungeon_data, $character_id);')
    && source.includes("'source_entity_ref' => $source_entity_ref,"),
  'Deterministic room combat initiation includes canonical source_entity_ref in combat payload'
);

assert(
  source.includes('protected function resolvePlayerEntityRefForRoomAction(string $room_id, array $dungeon_data, ?int $character_id): string')
    && source.includes("if ((int) ($entity['character_id'] ?? 0) !== $character_id) {")
    && source.includes("$entity_ref = trim((string) ($entity['entity_instance_id'] ?? $entity['instance_id'] ?? $entity['id'] ?? ''));"),
  'Source entity-ref resolver grounds actor source by room + character identity before fallback'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
