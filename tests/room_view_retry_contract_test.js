/**
 * @file
 * Contract test: unavailable room-view responses must not loop forever as pending generation.
 *
 * Run with:
 *   node tests/room_view_retry_contract_test.js
 */

const fs = require('fs');
const path = require('path');

const roomViewServiceSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomViewImageService.php'),
  'utf8'
);
const fetchBridgeSource = fs.readFileSync(
  path.resolve(__dirname, '../js/v2/shell/GameShellFetchBridge.js'),
  'utf8'
);
const legacyHexmapSource = fs.readFileSync(
  path.resolve(__dirname, '../js/hexmap.js'),
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

console.log('\n=== Room view retry contract ===');

assert(
  roomViewServiceSource.includes("'available' => FALSE,")
    && roomViewServiceSource.includes("'status' => 'unavailable',"),
  'room view service should mark missing/unready room payloads as unavailable instead of pending'
);

assert(
  fetchBridgeSource.includes('this.getRoomViewRetryDeadline(viewKey)')
    && fetchBridgeSource.includes('this.getRoomViewRetryRemainingSeconds(viewKey)')
    && fetchBridgeSource.includes("Room scene is not ready yet — retrying automatically for up to")
    && fetchBridgeSource.includes("} else if (Date.now() < retryDeadline && (generationAvailable || dataStatus === 'pending' || dataStatus === 'unavailable')) {"),
  'v2 shell should keep retrying empty room-view payloads inside the bounded retry window'
);

assert(
  legacyHexmapSource.includes('getRoomViewRetryDeadline(viewKey)')
    && legacyHexmapSource.includes('getRoomViewRetryRemainingSeconds(viewKey)')
    && legacyHexmapSource.includes("Room scene is not ready yet — retrying automatically for up to")
    && legacyHexmapSource.includes("} else if (Date.now() < retryDeadline && (generationAvailable || String(payload?.status || '').toLowerCase() === 'pending' || String(payload?.status || '').toLowerCase() === 'unavailable')) {"),
  'legacy hexmap room-view loop should mirror the bounded retry window behavior'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
