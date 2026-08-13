/**
 * @file
 * Contract test: state/effect packet emission routes through UnifiedStateEffectEngine.
 *
 * Run with:
 *   node tests/combat_state_effect_engine_contract_test.js
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

console.log('\n=== Combat state/effect engine contract ===');

const stateEngineSource = read('../src/Service/UnifiedStateEffectEngine.php');
assert(
  stateEngineSource.includes('class UnifiedStateEffectEngine')
    && stateEngineSource.includes('buildStateEffectChangePacket(')
    && stateEngineSource.includes('CombatResolutionContractService'),
  'UnifiedStateEffectEngine defines the shared state/effect packet seam'
);

const phaseHandlerSource = read('../src/Service/EncounterPhaseHandler.php');
assert(
  phaseHandlerSource.includes('protected UnifiedStateEffectEngine $unifiedStateEffectEngine;')
    && phaseHandlerSource.includes('$this->unifiedStateEffectEngine = $unified_state_effect_engine ?? new UnifiedStateEffectEngine(')
    && phaseHandlerSource.includes('$this->unifiedStateEffectEngine->buildStateEffectChangePacket('),
  'EncounterPhaseHandler routes state/effect packet emission through UnifiedStateEffectEngine'
);

const servicesSource = read('../dungeoncrawler_content.services.yml');
assert(
  servicesSource.includes('dungeoncrawler_content.unified_state_effect_engine:')
    && servicesSource.includes("class: Drupal\\dungeoncrawler_content\\Service\\UnifiedStateEffectEngine")
    && servicesSource.includes("'@dungeoncrawler_content.unified_state_effect_engine'"),
  'Service container wires UnifiedStateEffectEngine into encounter phase handling'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
