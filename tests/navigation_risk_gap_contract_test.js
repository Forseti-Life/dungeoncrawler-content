/**
 * @file
 * Contract checks for navigation risk-gap remediations.
 *
 * Run with:
 *   node tests/navigation_risk_gap_contract_test.js
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

const runtimeSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/NavigationRuntimeService.php'),
  'utf8'
);
const mapGeneratorSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/MapGeneratorService.php'),
  'utf8'
);
const roomChatSource = require('./helpers/php-source.js').readGmPipelineSource();
const gmPipelineTraitSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatServiceGmPipelineTrait.php'),
  'utf8'
);
const servicesSource = fs.readFileSync(
  path.resolve(__dirname, '../dungeoncrawler_content.services.yml'),
  'utf8'
);
const navigationSystemSource = fs.readFileSync(
  path.resolve(__dirname, '../js/v2/systems/NavigationSystem.js'),
  'utf8'
);
const gameShellSource = require('./helpers/js-source.js').readGameShellSource();

console.log('\n=== Navigation risk-gap contract ===');

assert(
  runtimeSource.includes("($latest['speaker'] ?? '') !== 'System'")
    && runtimeSource.includes("'speaker' => 'System'"),
  'arrival dedupe and write path now share System speaker contract'
);

assert(
  mapGeneratorSource.includes('protected function executeNavigationPersistenceTransaction(callable $operation): void')
    && mapGeneratorSource.includes('$transaction = $this->database->startTransaction();')
    && mapGeneratorSource.includes('$transaction->rollBack();'),
  'navigation persistence writes run inside explicit transaction envelope'
);

assert(
  mapGeneratorSource.includes('protected function assertNavigationConnectionParity(')
    && mapGeneratorSource.includes('hasDungeonDataConnectionPair(')
    && mapGeneratorSource.includes('hasCampaignConnectionPair('),
  'cross-store parity invariants exist for dungeon_data and campaign connector rows'
);

assert(
  roomChatSource.includes('protected NavigationApplicationOrchestrator $navigationApplicationOrchestrator;')
    && roomChatSource.includes('RoomChatService contract violation: navigation runtime, transition pipeline, and application orchestrator must be injected.')
    && gmPipelineTraitSource.includes('filterChatBlockedNavigationActions(')
    && !gmPipelineTraitSource.includes('applyNavigationTransition('),
  'RoomChat enforces injected navigation dependencies and blocks chat-originated navigation execution'
);

assert(
  servicesSource.includes('dungeoncrawler_content.navigation_runtime:')
    && servicesSource.includes("- '@dungeoncrawler_content.map_generator'")
    && servicesSource.includes("- '@dungeoncrawler_content.navigation_runtime'")
    && servicesSource.includes("- '@dungeoncrawler_content.navigation_transition_pipeline'")
    && servicesSource.includes('dungeoncrawler_content.navigation_application_orchestrator:'),
  'service wiring uses strict non-optional navigation dependencies'
);

assert(
  navigationSystemSource.includes('/navigation/locations/request')
    && !navigationSystemSource.includes('/gm/locations/request')
    && gameShellSource.includes('/navigation/locations/request')
    && !gameShellSource.includes('/gm/locations/request'),
  'action-rail and runtime pending-room navigation generation use navigation route (not GM-only route)'
);

console.log('\n===================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
