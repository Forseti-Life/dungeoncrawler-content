/**
 * @file
 * Contract test: NPC autoplay combat actions should emit visible narration so
 * the action log reflects deterministic hostile turns.
 *
 * Run with:
 *   node tests/npc_autoplay_narration_contract_test.js
 */

const fs = require('fs');
const path = require('path');

const source = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/ActorAutoplayCoordinator.php'),
  'utf8'
);

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

console.log('\n=== NPC autoplay narration contract ===');

assert(
  source.includes("$event_narration = $this->resolveAutoplayActionNarration(")
    && source.includes("GameEventLogger::buildEvent('npc_strike', 'encounter', $entity_id, [")
    && source.includes("'roll' => $strike_result['roll'] ?? NULL,")
    && source.includes("'total' => $strike_result['total'] ?? NULL,")
    && source.includes("'ac' => $strike_result['ac'] ?? NULL,")
    && source.includes("'hit' => is_bool($resolved_hit) ? $resolved_hit : NULL,")
    && source.includes("], $event_narration, $target);"),
  'npc strike events should resolve visible narration and include roll/resolution fields for action-log summaries'
);

assert(
  source.includes("GameEventLogger::buildEvent('npc_stride', 'encounter', $entity_id, [")
    && source.includes("'from' => $decision_basis['stride_from_hex'] ?? NULL,")
    && source.includes("'to' => $decision_basis['stride_to_hex'] ?? NULL,")
    && source.includes("], $event_narration);")
    && source.includes("protected function resolveAutoplayActionNarration(")
    && source.includes("protected function buildAutoplayStrideNarration(string $actor_name, string $target_name, array $action_context = []): string {")
    && source.includes("protected function formatAutoplayHexLabel(?array $hex): string {")
    && source.includes("sprintf('%s moves toward %s%s.', $actor_name, $target_name, $movement_suffix)")
    && source.includes("return sprintf('(%d,%d)', (int) $q, (int) $r);")
    && source.includes("protected function buildAutoplayStrikeNarration(string $actor_name, string $target_name, array $action_context = []): string {")
    && source.includes("sprintf('%s attacks %s%s.%s', $actor_name, $target_name, $with_weapon, $roll_suffix)"),
  'npc stride and strike actions should synthesize default action-log narration, including stride origin/destination hexes when available'
);

assert(
  source.includes("'weapon_name' => $strike_result['weapon_name'] ?? NULL,")
    && source.includes("'damage' => $strike_result['damage'] ?? NULL,")
    && source.includes("'damage_type' => $strike_result['damage_type'] ?? NULL,")
    && source.includes("sprintf(' (attack %d vs AC %d, d20 %d)', (int) $total, (int) $ac, (int) $roll)")
    && source.includes("sprintf(' for %d %s damage', (int) $damage, $damage_type)")
    && source.includes("'success' => sprintf('%s strikes %s%s%s.%s', $actor_name, $subject, $with_weapon, $damage_text, $roll_suffix),")
    && source.includes("'failure' => sprintf('%s swings at %s%s and misses.%s', $actor_name, $subject, $with_weapon, $roll_suffix),"),
  'npc strike narration reports the resolved attack roll, weapon and damage instead of a bare "attacks" line'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
