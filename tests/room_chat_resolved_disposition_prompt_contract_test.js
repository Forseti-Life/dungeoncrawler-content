/**
 * @file
 * Contract test: room chat prompt consumes canonical resolved disposition context.
 *
 * Run with:
 *   node tests/room_chat_resolved_disposition_prompt_contract_test.js
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
  path.resolve(__dirname, '../src/Service/RoomChatServiceIntentAndDeterminismTrait.php'),
  'utf8',
);

console.log('\n=== Room chat resolved disposition prompt contract ===');

assert(
  source.includes('protected function buildResolvedDispositionPromptContext(')
    && source.includes("dungeoncrawler_content.disposition_resolver_service")
    && source.includes('resolveActorTargetDisposition($campaign_id, $entity_ref, $target_ref')
    && source.includes('Resolved disposition authority (use as canonical social state):')
    && source.includes('effective_score:')
    && source.includes('confidence:'),
  'Room chat trait builds canonical resolved disposition prompt context from resolver DTO'
);

assert(
  source.includes('$resolved_disposition_context = $this->buildResolvedDispositionPromptContext(')
    && source.includes('if ($resolved_disposition_context !== \'\') {')
    && source.includes('$npc_context = trim($npc_context . "\\n\\n" . $resolved_disposition_context);'),
  'Room chat prompt assembly injects resolved disposition context into NPC prompt input'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
