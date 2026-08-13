/**
 * @file
 * Contract test: repository-wide inventory guard for direct packet-builder calls.
 *
 * Run with:
 *   node tests/combat_packet_builder_inventory_contract_test.js
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

function walkPhpFiles(dir, out = []) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      walkPhpFiles(full, out);
      continue;
    }
    if (entry.isFile() && entry.name.endsWith('.php')) {
      out.push(full);
    }
  }
  return out;
}

console.log('\n=== Combat packet-builder inventory guard ===');

const serviceDir = path.resolve(__dirname, '../src/Service');
const files = walkPhpFiles(serviceDir);

const allowedDirectBuilderCallFiles = new Set([
  'CombatResolutionContractService.php',
  'UnifiedDamageEngine.php',
  'UnifiedMovementEngine.php',
  'UnifiedStateEffectEngine.php',
  'UnifiedReactionEngine.php',
]);

const directBuilderPattern = /combatResolutionContractService->build(DamageApplicationPacket|MovementResolutionPacket|StateEffectChangePacket|ReactionResolutionPacket)\(/g;

const violators = [];
for (const file of files) {
  const source = fs.readFileSync(file, 'utf8');
  if (!directBuilderPattern.test(source)) {
    continue;
  }
  const base = path.basename(file);
  if (!allowedDirectBuilderCallFiles.has(base)) {
    violators.push(path.relative(path.resolve(__dirname, '..'), file));
  }
}

assert(
  violators.length === 0,
  violators.length === 0
    ? 'Direct packet-builder calls are restricted to contract/unified-engine files only'
    : `Unexpected direct packet-builder calls found in: ${violators.join(', ')}`
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
