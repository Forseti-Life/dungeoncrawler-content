/**
 * @file
 * Contract checks for chat panel polling and prefetch behavior.
 *
 * Run with:
 *   node tests/chat_panel_polling_contract_test.js
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

console.log('\n=== Chat panel polling contract ===');

assert(
  source.includes("prefetchSessionViews(views = ['party', 'gm-private'])"),
  'default prefetch avoids eager system-log requests'
);

assert(
  source.includes('const shouldCache = true;')
    && source.includes("const cacheTtlMs = view === 'system-log' ? 3000 : this.chatCacheTtlMs;")
    && source.includes('this.getCachedChatPayload(this.sessionViewCache, cacheKey, cacheTtlMs);'),
  'system-log requests are short-TTL cached and coalesced through session view cache'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
