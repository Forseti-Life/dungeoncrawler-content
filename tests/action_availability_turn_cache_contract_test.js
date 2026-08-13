/**
 * @file
 * Contract test: action-availability exposes turn-scoped read cache hooks.
 *
 * Run with:
 *   node tests/action_availability_turn_cache_contract_test.js
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

const coordinatorSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GameCoordinatorService.php'),
  'utf8',
);

console.log('\n=== Action availability turn-cache contract ===');

assert(
  coordinatorSource.includes('protected array $actionAvailabilityTurnCache = [];')
    && coordinatorSource.includes('$turn_cache_enabled = $this->shouldEnableActionAvailabilityTurnCache($campaign_id);')
    && coordinatorSource.includes('$turn_signature = $this->buildActionAvailabilityTurnSignature($campaign_id, $actor_id, $context);')
    && coordinatorSource.includes('$turn_cache_key = $this->buildActionAvailabilityTurnCacheKey($campaign_id, $actor_id, $turn_signature);')
    && coordinatorSource.includes('if ($turn_cache_enabled && isset($this->actionAvailabilityTurnCache[$turn_cache_key])) {')
    && coordinatorSource.includes('$this->actionAvailabilityTurnCache[$turn_cache_key] = $payload;'),
  'Coordinator caches actor availability payloads by campaign+actor turn signature'
);

assert(
  coordinatorSource.includes('protected function shouldEnableActionAvailabilityTurnCache(int $campaign_id): bool')
    && coordinatorSource.includes("getenv('DC_ACTION_AVAILABILITY_TURN_CACHE_ENABLED')")
    && coordinatorSource.includes("get('action_availability_turn_cache_enabled')")
    && coordinatorSource.includes("get('latency_toggle_canary_campaign_ids')"),
  'Turn cache toggle is environment/config controlled for safe rollout'
);

assert(
  coordinatorSource.includes("'cache_mode' => $turn_cache_enabled ? 'turn' : 'disabled'")
    && coordinatorSource.includes("'cache_hit' => FALSE")
    && coordinatorSource.includes("'turn_signature' => $turn_signature")
    && coordinatorSource.includes("$cached_payload['diagnostics']['cache_hit'] = TRUE;"),
  'Availability diagnostics expose cache mode, hit status, and turn signature'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
