/**
 * @file
 * Contract coverage for ChatPanel actor_response transition consumption.
 *
 * Run with:
 *   node tests/chat_panel_actor_response_transition_contract_test.js
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
  path.resolve(__dirname, '../js/v2/panels/ChatPanel.js'),
  'utf8'
);

console.log('\n=== ChatPanel actor response transition contract ===');

assert(
  source.includes('resolveAuthoritativeRoomChatData(resultData = {})')
    && source.includes('const actorResponse = data.actor_response')
    && source.includes('...actorResponse'),
  'ChatPanel resolves actor_response projection as preferred room-chat completion data'
);
assert(
  source.includes('const responseMode = options.responseMode || \'actor_scoped\';')
    && source.includes('const includeLegacyOverlay = typeof options.includeLegacyOverlay === \'boolean\'')
    && source.includes('response_mode: responseMode')
    && source.includes('include_legacy_overlay: includeLegacyOverlay'),
  'ChatPanel defaults to actor_scoped mode and supports explicit compatibility overlay control'
);
assert(
  source.includes('this.stateManager?.hexmap?.gameCoordinator?.applyAuthoritativeUpdate?.(responseData);')
    && source.includes('this.logChatTimingSummary({ ...result, data: responseData }, pending);'),
  'ChatPanel applies normalized transition-mode response data to authoritative updates and timing telemetry'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
