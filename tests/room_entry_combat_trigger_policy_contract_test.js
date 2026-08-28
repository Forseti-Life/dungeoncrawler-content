/**
 * @file
 * Contract test: room-entry combat trigger must be gated by hostile disposition.
 *
 * Run with:
 *   node tests/room_entry_combat_trigger_policy_contract_test.js
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

const source = require('./helpers/php-source.js').readEncounterPhaseHandlerSource();

console.log('\n=== Room entry combat trigger policy contract ===');

assert(
  source.includes('protected function buildCombatEncounterContext(')
    && source.includes('if (!$this->hasHostileDispositionInRoom($room_id, $dungeon_data, $campaign_id, $room_entities)) {'),
  'Room-entry combat trigger is gated by hostile disposition check'
);

assert(
  source.includes('protected function hasHostileDispositionInRoom(')
    && source.includes('$disposition_resolver = $this->resolveDispositionResolverService();')
    && source.includes('$this->hasHostileDispositionBetweenActorRefs($campaign_id, $source_ref, $target_ref, $room_id)')
    && source.includes("$hostile_flag = (bool) ($dto['policy_flags']['hostile'] ?? FALSE);")
    && source.includes('if ($hostile_flag || $this->isHostileDispositionScore($effective_score)) {')
    && source.includes('$relationship_attitude->resolveEdgeDispositionDetails($source_ref, $target_ref, $campaign_id)')
    && source.includes('protected function isHostileDispositionScore(int $score): bool'),
  'Hostility check uses resolver and score-thresholded canonical authorities'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
