/**
 * @file
 * Contract checks for ChatPanel cross-view cache invalidation consistency.
 *
 * Run with:
 *   node tests/chat_panel_cross_view_cache_invalidation_contract_test.js
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

const source = fs.readFileSync(path.resolve(__dirname, '../js/v2/panels/ChatPanel.js'), 'utf8');

console.log('\n=== ChatPanel cross-view invalidation contract ===');

assert(
  source.includes('invalidateCrossViewChatCaches(options = {}) {')
    && source.includes('this.invalidateChatCaches({')
    && source.includes("sessionViews: ['party', 'gm-private', 'system-log'],"),
  'ChatPanel defines a single cross-view cache invalidation helper'
);

assert(
  source.includes('this.invalidateCrossViewChatCaches();')
    && !source.includes("this.invalidateChatCaches({ sessionViews: ['party'] });")
    && !source.includes("this.invalidateChatCaches({ sessionViews: ['gm-private'] });"),
  'Session view posting paths use the unified cross-view invalidation helper instead of per-view one-offs'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
