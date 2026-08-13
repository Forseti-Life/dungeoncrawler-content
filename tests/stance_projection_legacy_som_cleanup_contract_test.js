/**
 * @file
 * Contract test: stance summary projection should no longer rely on direct
 * legacy som_state Arcane Cascade flags in projection authority paths.
 *
 * Run with:
 *   node tests/stance_projection_legacy_som_cleanup_contract_test.js
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
  path.resolve(__dirname, '../src/Service/ActorContextProjectionService.php'),
  'utf8',
);

console.log('\n=== Stance projection legacy som-state cleanup contract ===');

assert(
  source.includes('public function buildStanceSummary(array $character_data = [], int $campaign_id = 0, string $entity_ref = \'\'): array')
    && source.includes("$arcane_cascade_active = !empty($stored_summary['arcane_cascade_active']);")
    && source.includes("if (($entry['stance_id'] ?? '') === 'arcane_cascade') {")
    && !source.includes("|| !empty($character_data['som_state']['arcane_cascade_active'])")
    && !source.includes("'source_type' => 'legacy_som_state'"),
  'Stance summary projection resolves Arcane Cascade activity from canonical stance summary/active stances without direct som_state fallback'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
