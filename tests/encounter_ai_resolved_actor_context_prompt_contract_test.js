/**
 * @file
 * Contract test: encounter AI recommendation prompt must include canonical
 * resolved actor context and instruct model to prefer it.
 *
 * Run with:
 *   node tests/encounter_ai_resolved_actor_context_prompt_contract_test.js
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
  path.resolve(__dirname, '../src/Service/AiConversationEncounterAiProvider.php'),
  'utf8',
);

console.log('\n=== Encounter AI resolved actor context prompt contract ===');

assert(
  source.includes("$resolved_actor_context = is_array($context['resolved_actor_context'] ?? NULL)")
    && source.includes('$current_actor_profile = $this->normalizeProfileFromResolvedContext($current_actor_profile, $resolved_actor_context);')
    && source.includes("'resolved_actor_context' => $resolved_actor_context,")
    && source.includes('private function normalizeProfileFromResolvedContext(array $current_actor_profile, array $resolved_actor_context): array')
    && source.includes("$current_actor_profile['attitude_source'] = 'resolved_actor_context';"),
  'Recommendation prompt includes canonical resolved_actor_context payload and normalizes profile attitude from it'
);

assert(
  source.includes('Use resolved_actor_context as the canonical disposition/aggression/stance/process_flow/relationship source'),
  'Recommendation system prompt directs model to prefer resolved_actor_context'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
