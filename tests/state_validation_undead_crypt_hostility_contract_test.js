/**
 * @file
 * Contract test: undead-crypt hostility validation uses centralized
 * disposition authority score gate in validation/bootstrap services.
 *
 * Run with:
 *   node tests/state_validation_undead_crypt_hostility_contract_test.js
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

const stateValidationSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/StateValidationService.php'),
  'utf8',
);
const initSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/CampaignInitializationService.php'),
  'utf8',
);

console.log('\n=== Undead crypt hostility validation contract ===');

assert(
  stateValidationSource.includes('if (!$this->isStarterNpcHostileAttitude((string) ($matched[\'attitude\'] ?? \'\'))) {')
    && stateValidationSource.includes('private function isStarterNpcHostileAttitude(string $attitude): bool')
    && stateValidationSource.includes('DispositionAuthorityContract::isHostileScore($score);'),
  'StateValidationService routes undead-crypt hostility checks through disposition authority contract'
);

assert(
  initSource.includes('if (!$this->isStarterNpcHostileAttitude((string) ($matched[\'attitude\'] ?? \'\'))) {')
    && initSource.includes('private function isStarterNpcHostileAttitude(string $attitude): bool')
    && initSource.includes('DispositionAuthorityContract::isHostileScore($score);'),
  'CampaignInitializationService routes undead-crypt hostility checks through disposition authority contract'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}

