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

assert(
  source.includes('protected ?GameMasterSubsystemService $gmSubsystem;'),
  'controller stores the GM subsystem as a nullable dependency'
);
assert(
  source.includes('protected function getGmSubsystem(): GameMasterSubsystemService {'),
  'controller exposes a lazy GM subsystem accessor'
);
assert(
  source.includes("\\Drupal::service('dungeoncrawler_content.game_master_subsystem')"),
  'lazy accessor resolves the GM subsystem service only on demand'
);
assert(
  source.includes('NULL,\n      $container->get(\'logger.factory\')->get(\'dungeoncrawler_chat\')'),
  'controller factory no longer instantiates the GM subsystem during creation'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
