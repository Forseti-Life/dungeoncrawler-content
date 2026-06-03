/*
 * Contract test: hex-object entity blueprints must prefer object_instance_id.
 *
 * Run with:
 *   node tests/hex_object_instance_id_contract_test.js
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

(function run() {
  const srcPath = path.join(__dirname, '..', 'js', 'v2', 'GameShell.js');
  const src = fs.readFileSync(srcPath, 'utf8');

  console.log('\n=== Hex object instance_id contract ===');

  assert(src.includes('object?.object_instance_id'), 'Reads object.object_instance_id');
  assert(src.includes('room-object:'), 'Retains deterministic fallback instance id');

  if (failed > 0) {
    console.error(`\nFAILED: ${failed} failing assertion(s)`);
    process.exit(1);
  }

  console.log(`\nOK: ${passed} passing assertion(s)`);
})();
