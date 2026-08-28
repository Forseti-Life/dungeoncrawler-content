/**
 * @file
 * Contract test: NPC autoplay stride must execute authoritative movement
 * mutations so map state can reflect position changes.
 *
 * Run with:
 *   node tests/npc_stride_position_contract_test.js
 */

const fs = require('fs');
const path = require('path');

const autoplaySource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/ActorAutoplayCoordinator.php'),
  'utf8'
);
const coordinatorSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/EncounterNpcTurnCoordinatorTrait.php'),
  'utf8'
);
const endTurnSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/EncounterPhaseHandlerRouteExecutionCorePartCTrait.php'),
  'utf8'
);
const encounterHandlerSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/EncounterPhaseHandler.php'),
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

console.log('\n=== NPC stride position contract ===');

assert(
  autoplaySource.includes('$movement_target = $target ?? $find_nearest_alive_player($entity_id, $game_state);')
    && autoplaySource.includes('$stride_to_hex = $this->resolveStrideDestinationHex($entity_id, $movement_target, $game_state, $dungeon_data, $intent_contract);')
    && autoplaySource.includes('$stride_result = $process_stride($encounter_id, $entity_id, $stride_to_hex, $game_state, $dungeon_data, $campaign_id);')
    && autoplaySource.includes("'to_hex' => $decision_basis['stride_to_hex'] ?? NULL,"),
  'autoplay stride should resolve a goal-aware destination hex, execute stride mutation callback, and emit destination metadata'
);

assert(
  autoplaySource.includes('protected function resolveStrideDestinationHex(')
    && autoplaySource.includes('collectReachableStrideHexes(')
    && autoplaySource.includes('selectBestStrideGoalHex(')
    && autoplaySource.includes('protected function hexDistance(int $q1, int $r1, int $q2, int $r2): int {'),
  'autoplay stride should include full-budget reachable-hex selection and distance helpers'
);

assert(
  coordinatorSource.includes('return $this->processStride($eid, $actor_id, [')
    && coordinatorSource.includes("'to_hex' => [")
    && coordinatorSource.includes("'action_cost' => 1,")
    && coordinatorSource.includes('return $this->processStrike($eid, $actor_id, $target_id, $this->buildNpcAutoplayStrikeParams($actor_id, $state), $state, $dungeon, $cid);')
    && coordinatorSource.includes('protected function buildNpcAutoplayStrikeParams(string $actor_id, array $game_state): array {'),
  'encounter NPC turn coordinator should route autoplay stride authoritatively and provide deterministic strike params for NPC attack resolution'
);

assert(
  encounterHandlerSource.includes("$resolved_entity_target = $this->normalizeMutationTargetId($mutation['entity_id'] ?? NULL);")
    && encounterHandlerSource.includes("$resolved_entity_target = $this->normalizeMutationTargetId($mutation['entity'] ?? NULL);")
    && encounterHandlerSource.includes("$mutation['entity_id'] = $resolved_entity_target;"),
  'encounter mutation normalization should preserve stride actor IDs from entity-tagged NPC mutations'
);

assert(
  endTurnSource.includes("$npc_mutations = [];")
    && endTurnSource.includes("$npc_mutations = array_merge($npc_mutations, $npc_result['mutations']);")
    && endTurnSource.includes("'mutations' => $npc_mutations,"),
  'end-turn recursion should propagate NPC autoplay mutations through the encounter response lane'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
