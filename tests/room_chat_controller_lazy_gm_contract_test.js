/**
 * @file
 * Regression coverage for lazy GM subsystem resolution in RoomChatController.
 *
 * Run with:
 *   node tests/room_chat_controller_lazy_gm_contract_test.js
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
  path.resolve(__dirname, '../src/Controller/RoomChatController.php'),
  'utf8'
);

console.log('\n=== RoomChatController lazy GM contract ===');

// The controller no longer carries GM-subsystem or coordinator dependencies at
// all: the work moved behind orchestrators, and the only remaining coordinator
// use is resolved on demand inside the room-chat NPC dialogue trait. That is a
// stronger form of the original "never build them during construction" intent,
// so it is asserted at both ends rather than dropped.
const dialogueTraitSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatServiceNpcDialogueAndQuestLeadTrait.php'),
  'utf8'
);

assert(
  !source.includes('GameMasterSubsystemService') && !source.includes('GameCoordinatorService'),
  'controller carries no GM subsystem or game coordinator dependency'
);
assert(
  !source.includes("dungeoncrawler_content.game_master_subsystem")
    && !source.includes("dungeoncrawler_content.game_coordinator"),
  'controller factory no longer instantiates the GM subsystem or coordinator during creation'
);
assert(
  dialogueTraitSource.includes("\\Drupal::service('dungeoncrawler_content.game_coordinator')"),
  'the game coordinator is resolved only on demand, at its point of use'
);
assert(
  dialogueTraitSource.includes("!\\Drupal::hasService('dungeoncrawler_content.game_coordinator')"),
  'on-demand coordinator resolution guards service availability before resolving'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
