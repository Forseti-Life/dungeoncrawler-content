/**
 * @file
 * Contract coverage for GM private channel routing.
 *
 * Run with:
 *   node tests/gm_channel_routing_contract_test.js
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

const coreFlowSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatServiceCoreFlowTrait.php'),
  'utf8'
);
const writeOrchestratorSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChat/RoomChatWriteEndpointOrchestrator.php'),
  'utf8'
);

console.log('\n=== GM channel routing contract ===');

assert(
  coreFlowSource.includes("str_starts_with($channel, 'gm_private:')")
    && coreFlowSource.includes('$is_gm_direct_channel')
    && coreFlowSource.includes('$this->generateGmReply('),
  'GM private channel messages are routed through GM reply generation path'
);
assert(
  coreFlowSource.includes("!empty($channel_def['gm_responds'])"),
  'Channel definitions with gm_responds are treated as GM-directed channels'
);
assert(
  writeOrchestratorSource.includes("if (str_starts_with($channel, 'gm_private:') && !$is_player_turn)"),
  'Write orchestrator rejects non-player posts to GM private channels'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}

