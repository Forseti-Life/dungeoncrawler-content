/**
 * @file
 * Contract test: server-side action option targeting inference hardening.
 *
 * Run with:
 *   node tests/action_availability_targeting_inference_contract_test.js
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

const source = fs.readFileSync(path.resolve(__dirname, '../src/Service/ActorActionAvailabilityService.php'), 'utf8');

console.log('\n=== Action availability targeting inference contracts ===');

assert(
  source.includes("if ($kind === 'spell') {")
    && source.includes('return $this->inferSpellTargetingFromMetadata($row, $default_targeting);')
    && source.includes("if (in_array($kind, ['skill', 'feat', 'item', 'consumable'], TRUE)) {")
    && source.includes('return $this->inferNonSpellTargetingFromMetadata($row, $default_targeting, $kind);'),
  'resolveOptionTargeting routes spell and non-spell options through explicit metadata inference paths'
);

assert(
  source.includes('protected function inferNonSpellTargetingFromMetadata(array $row, string $fallback, string $kind = \'\'): string {')
    && source.includes("return 'ally_or_self';")
    && source.includes("return 'hostile_entity';")
    && source.includes("return 'entity_or_object';")
    && source.includes("return 'connected_room';")
    && source.includes("return 'self_or_target';"),
  'non-spell targeting inference emits canonical targeting tokens needed by shared map-target workflow'
);

assert(
  source.includes("if (in_array($normalized_kind, ['item', 'consumable'], TRUE)) {")
    && source.includes("return 'self_or_target';")
    && source.includes("return 'entity_or_room';"),
  'target-like hints map item/consumable options to self_or_target and other option families to entity_or_room'
);

console.log('\n=========================================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
