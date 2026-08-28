/**
 * @file
 * Contract test: bootstrap encounter mode must wait for canonical hydration and
 * use a shared hostile-room resolver.
 *
 * Run with:
 *   node tests/room_scene_bootstrap_hydration_contract_test.js
 */

const fs = require('fs');
const path = require('path');

const navigationSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/EncounterNavigationTransitionCoordinatorTrait.php'),
  'utf8'
);
const supportSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/EncounterPhaseHandlerRouteExecutionSupportTrait.php'),
  'utf8'
);
const gameSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GameCoordinatorService.php'),
  'utf8'
);
const graphSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RuntimeGraphAssemblerService.php'),
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

console.log('\n=== Room-scene bootstrap hydration contract ===');

assert(
  navigationSource.includes('resolveBootstrapEncounterInitialization(')
    && navigationSource.includes("$bootstrap_context['combat_context']['should_trigger']")
    && navigationSource.includes('buildCombatEncounterContext($room_id, $dungeon_data, $game_state, $campaign_id, $actor_id)'),
  'bootstrap and room-entry paths share one canonical bootstrap encounter initialization seam'
);

assert(
  navigationSource.includes('rebuildAuthoritativeRuntimeGraph($campaign_id, $dungeon_data, $room_id)')
    && navigationSource.includes('$room = $this->findRoomById($dungeon_data, $room_id);')
    && navigationSource.includes("'combat_context' => $this->buildCombatEncounterContext($room_id, $dungeon_data, $game_state, $campaign_id, $actor_id)"),
  'bootstrap seam attempts authoritative graph rebuild when compatibility room payload is sparse before evaluating combat trigger'
);

assert(
  navigationSource.includes('$enter_result = $this->onEnter($bootstrap_context[\'combat_context\'], $game_state, $dungeon_data, $campaign_id);')
    && navigationSource.includes("is_array($enter_result['events'] ?? NULL) ? $enter_result['events'] : []"),
  'room-entry/bootstrap callers unwrap lifecycle event lists instead of merging entire onEnter envelopes into the event stream'
);

assert(
  gameSource.includes('$room_data = $this->ensureBootstrapRoomAvailable($campaign_id, $room_id, $dungeon_data);')
    && gameSource.includes('protected function ensureBootstrapRoomAvailable(int $campaign_id, string $room_id, array &$dungeon_data): ?array {')
    && gameSource.includes('$this->runtimeGraphAssembler->buildRuntimeGraph('),
  'game coordinator initial room-entry bootstrap rebuilds authoritative room payload before deciding whether startup encounter bootstrap can run'
);

assert(
  gameSource.includes('if ($event_log_cursor > 0 && !$repair_broken_encounter_shell && $this->hasActiveEncounterContextForRoom($game_state, $room_id)) {')
    && gameSource.includes('if ($latest_event_cursor > 0 && !$repair_broken_encounter_shell && $this->hasActiveEncounterContextForRoom($game_state, $room_id)) {'),
  'existing event cursors suppress startup bootstrap only when runtime state already owns a live encounter context'
);

assert(
  supportSource.includes('awaitBootstrapRoomEntityHydration(')
    && supportSource.includes('synchronizeBootstrapRoomEntities(')
    && supportSource.includes('usleep($sleep_micros);'),
  'bootstrap combat resolution waits on bounded canonical room actor hydration before mode selection'
);

assert(
  supportSource.includes('Encounter bootstrap hydration contract violation:')
    && supportSource.includes('resolveCampaignCharacterRuntimeSyncService()')
    && supportSource.includes('resolveActorRuntimeStateStoreService()'),
  'bootstrap hydration gate fails explicitly and relies on canonical actor/runtime APIs'
);

assert(
  graphSource.includes("'runtime_entity_hints' => $this->buildRuntimeEntityHints($contents_data)")
    && graphSource.includes('protected function buildRuntimeEntityHints(array $contents_data): array {'),
  'runtime graph room payloads project compatibility entity hints from canonical contents_data'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
