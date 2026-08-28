/**
 * @file
 * Contract test: reaction packet emission routes through UnifiedReactionEngine.
 *
 * Run with:
 *   node tests/combat_reaction_engine_contract_test.js
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

console.log('\n=== Combat reaction engine contract ===');

const reactionEngineSource = read('../src/Service/UnifiedReactionEngine.php');
assert(
  reactionEngineSource.includes('class UnifiedReactionEngine')
    && reactionEngineSource.includes('buildReactionResolutionPacket(')
    && reactionEngineSource.includes('CombatResolutionContractService'),
  'UnifiedReactionEngine defines the shared reaction packet seam'
);

const phaseHandlerSource = require('./helpers/php-source.js').readEncounterPhaseHandlerSource();
assert(
  phaseHandlerSource.includes('protected UnifiedReactionEngine $unifiedReactionEngine;')
    && phaseHandlerSource.includes('$this->unifiedReactionEngine = $unified_reaction_engine ?? new UnifiedReactionEngine(')
    && phaseHandlerSource.includes('$this->unifiedReactionEngine->buildReactionResolutionPacket('),
  'EncounterPhaseHandler routes reaction packet emission through UnifiedReactionEngine'
);

const servicesSource = read('../dungeoncrawler_content.services.yml');
assert(
  servicesSource.includes('dungeoncrawler_content.unified_reaction_engine:')
    && servicesSource.includes("class: Drupal\\dungeoncrawler_content\\Service\\UnifiedReactionEngine")
    && servicesSource.includes("'@dungeoncrawler_content.unified_reaction_engine'"),
  'Service container wires UnifiedReactionEngine into encounter phase handling'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
