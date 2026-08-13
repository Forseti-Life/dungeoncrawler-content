/**
 * @file
 * Contract coverage for internal room-chat callers using explicit actor mode.
 *
 * Run with:
 *   node tests/internal_room_chat_caller_response_mode_contract_test.js
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

const runtimeSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmActorRuntimeService.php'),
  'utf8'
);
const transportSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmActorChatTransportService.php'),
  'utf8'
);
const encounterSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/EncounterActionExecutor.php'),
  'utf8'
);
const explorationSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/ExplorationPhaseHandler.php'),
  'utf8'
);
const questTrackerSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Controller/QuestTrackerController.php'),
  'utf8'
);

console.log('\n=== Internal room chat caller response mode contract ===');

assert(
  runtimeSource.includes("'response_mode' => $response_mode")
    && runtimeSource.includes("'include_legacy_overlay' => $include_legacy_overlay"),
  'GM actor runtime forwards resolved response mode options into room-chat transport'
);
assert(
  transportSource.includes("'response_mode' => (string) ($response_options['response_mode'] ?? 'actor_scoped')")
    && transportSource.includes("'include_legacy_overlay' => !empty($response_options['include_legacy_overlay'])"),
  'GM actor transport applies explicit actor-scoped defaults for validated room chat writes'
);
assert(
  encounterSource.includes("'response_mode' => 'actor_scoped'")
    && encounterSource.includes("'include_legacy_overlay' => FALSE"),
  'Encounter talk automation requests actor-scoped room-chat responses'
);
assert(
  explorationSource.includes("'response_mode' => 'actor_scoped'")
    && explorationSource.includes("'include_legacy_overlay' => FALSE"),
  'Exploration talk automation requests actor-scoped room-chat responses'
);
assert(
  questTrackerSource.includes("'response_mode' => 'actor_scoped'")
    && questTrackerSource.includes("'include_legacy_overlay' => FALSE"),
  'Quest completion room-chat posts request actor-scoped room-chat responses'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}

