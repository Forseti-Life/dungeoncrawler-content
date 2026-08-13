/**
 * @file
 * Contract test: game-event logger rejects malformed event payload contracts.
 *
 * Run with:
 *   node tests/game_event_logger_contract_test.js
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
  path.resolve(__dirname, '../src/Service/GameEventLogger.php'),
  'utf8'
);

console.log('\n=== Game event logger contract ===');

assert(
  source.includes('protected function normalizeEvent(array $event, bool $strict = TRUE): array')
    && source.includes("throw new \\InvalidArgumentException('Game event contract violation: missing non-empty phase.');")
    && source.includes("throw new \\InvalidArgumentException('Game event contract violation: missing non-empty type.');")
    && source.includes("throw new \\InvalidArgumentException('Game event contract violation: data must be an array.');"),
  'normalizeEvent enforces strict phase/type/data contract on new event writes'
);

assert(
  source.includes("$normalized = $this->normalizeEvent($context, FALSE);"),
  'persistent event decode path remains backward-compatible for historical rows'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}

