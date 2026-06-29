/**
 * @file
 * Contract checks for GM generation policy extraction.
 *
 * Run with:
 *   node tests/gm_generation_policy_contract_test.js
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

const policySource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmSubsystem/GmGenerationPolicy.php'),
  'utf8'
);
const roomChatSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatService.php'),
  'utf8'
);
const servicesSource = fs.readFileSync(
  path.resolve(__dirname, '../dungeoncrawler_content.services.yml'),
  'utf8'
);

console.log('\n=== GM generation policy contract ===');

assert(
  policySource.includes('class GmGenerationPolicy'),
  'generation policy service class exists'
);
assert(
  policySource.includes('public function resolve(bool $should_use_cache, ?string $cache_key, callable $generator): array'),
  'generation policy exposes cache-aware resolve() contract'
);
assert(
  policySource.includes("'cache_status' => $cache_status")
    && policySource.includes("'generation_attempted' => $generation_attempted"),
  'generation policy returns explicit cache and generation status fields'
);
assert(
  roomChatSource.includes('protected GmGenerationPolicy $gmGenerationPolicy;')
    && roomChatSource.includes('$this->gmGenerationPolicy = $gm_generation_policy ?? new GmGenerationPolicy();'),
  'RoomChatService wires generation policy dependency with fallback construction'
);
assert(
  roomChatSource.includes('$policy_result = $this->gmGenerationPolicy->resolve(')
    && roomChatSource.includes("'cache' => (string) ($policy_result['cache_status']"),
  'generateGmReply delegates cache/generation policy and records cache metadata from policy result'
);
assert(
  servicesSource.includes('dungeoncrawler_content.gm_generation_policy:')
    && servicesSource.includes("- '@?dungeoncrawler_content.gm_generation_policy'"),
  'service container registers and injects generation policy'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}

