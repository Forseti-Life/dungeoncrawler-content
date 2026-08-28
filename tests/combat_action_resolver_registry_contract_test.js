/**
 * @file
 * Contract test: strike/cast resolve through ActionResolverRegistry seam.
 *
 * Run with:
 *   node tests/combat_action_resolver_registry_contract_test.js
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

function read(relPath) {
  return fs.readFileSync(path.resolve(__dirname, relPath), 'utf8');
}

console.log('\n=== Combat action resolver registry contract ===');

const registrySource = read('../src/Service/ActionResolverRegistry.php');
assert(
  registrySource.includes('class ActionResolverRegistry')
    && registrySource.includes('public function register(')
    && registrySource.includes('public function resolve(string $action_type, mixed &...$args): array'),
  'ActionResolverRegistry defines register/resolve seam with by-reference argument support'
);

const phaseHandlerSource = require('./helpers/php-source.js').readEncounterPhaseHandlerSource();
assert(
  phaseHandlerSource.includes("$this->actionResolverRegistry->register('strike'")
    && phaseHandlerSource.includes("$this->actionResolverRegistry->register('cast_spell'")
    && phaseHandlerSource.includes("$this->actionResolverRegistry->resolve(\n      'strike',")
    && phaseHandlerSource.includes("$this->actionResolverRegistry->resolve(\n      'cast_spell',"),
  'EncounterPhaseHandler registers strike/cast resolvers and routes wrappers through registry'
);

const executorSource = read('../src/Service/EncounterActionExecutor.php');
assert(
  executorSource.includes('public function processStrike(')
    && executorSource.includes('public function processCastSpell('),
  'EncounterActionExecutor remains the execution implementation behind registry dispatch'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
