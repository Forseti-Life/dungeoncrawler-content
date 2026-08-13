/**
 * @file
 * Contract test: EncounterActionExecutor DI argument order is strict and aligned.
 *
 * Run with:
 *   node tests/encounter_action_executor_di_contract_test.js
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

console.log('\n=== EncounterActionExecutor DI contract ===');

const executorSource = read('../src/Service/EncounterActionExecutor.php');
const servicesSource = read('../dungeoncrawler_content.services.yml');

assert(
  executorSource.includes('CombatResolutionContractService $combat_resolution_contract_service')
    && executorSource.includes('LoggerChannelFactoryInterface $logger_factory')
    && !executorSource.includes('mixed $combat_resolution_contract_or_logger_factory'),
  'EncounterActionExecutor constructor requires strict contract then logger arguments'
);

const executorServiceBlock = servicesSource.slice(
  servicesSource.indexOf('dungeoncrawler_content.encounter_action_executor:'),
  servicesSource.indexOf('dungeoncrawler_content.encounter_intent_router:')
);

assert(
  executorServiceBlock.includes("- '@dungeoncrawler_content.combat_resolution_contract'")
    && executorServiceBlock.includes("- '@logger.factory'")
    && executorServiceBlock.indexOf("- '@dungeoncrawler_content.combat_resolution_contract'")
      < executorServiceBlock.indexOf("- '@logger.factory'"),
  'Service wiring passes combat resolution contract before logger factory'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
