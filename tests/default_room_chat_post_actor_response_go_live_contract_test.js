/**
 * @file
 * Contract coverage for actor-scoped go-live default on room chat POST.
 *
 * Run with:
 *   node tests/default_room_chat_post_actor_response_go_live_contract_test.js
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

const chatPanelSource = fs.readFileSync(
  path.resolve(__dirname, '../js/v2/panels/ChatPanel.js'),
  'utf8'
);

console.log('\n=== Room chat actor-response go-live contract ===');

assert(
  chatPanelSource.includes("const responseMode = options.responseMode || 'actor_scoped';"),
  'ChatPanel room-chat POST defaults response_mode to actor_scoped'
);
assert(
  chatPanelSource.includes('const includeLegacyOverlay = typeof options.includeLegacyOverlay === \'boolean\'')
    && chatPanelSource.includes('? options.includeLegacyOverlay')
    && chatPanelSource.includes(': false;'),
  'ChatPanel default requests do not include legacy overlay unless explicitly requested'
);
assert(
  chatPanelSource.includes('response_mode: responseMode')
    && chatPanelSource.includes('include_legacy_overlay: includeLegacyOverlay'),
  'ChatPanel sends explicit response mode and legacy overlay controls on room-chat POST'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
