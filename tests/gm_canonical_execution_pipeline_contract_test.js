/**
 * @file
 * Contract checks for canonical execution pipeline extraction.
 *
 * Run with:
 *   node tests/gm_canonical_execution_pipeline_contract_test.js
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

const pipelineSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmSubsystem/CanonicalExecutionPipeline.php'),
  'utf8'
);
const roomChatSource = require('./helpers/php-source.js').readGmPipelineSource();
const servicesSource = fs.readFileSync(
  path.resolve(__dirname, '../dungeoncrawler_content.services.yml'),
  'utf8'
);

console.log('\n=== GM canonical execution pipeline contract ===');

assert(
  pipelineSource.includes('class CanonicalExecutionPipeline'),
  'canonical execution pipeline service class exists'
);
assert(
  pipelineSource.includes('public function execute(')
    && pipelineSource.includes("'canonical_results' =>"),
  'pipeline exposes execute() and returns canonical_results payload'
);
assert(
  pipelineSource.includes('executeCanonicalAuthoritativeActions(')
    && pipelineSource.includes("'error_count' => count($errors)"),
  'pipeline delegates to broker and returns explicit error_count'
);
assert(
  roomChatSource.includes('protected CanonicalExecutionPipeline $canonicalExecutionPipeline;')
    && roomChatSource.includes('$this->canonicalExecutionPipeline = $canonical_execution_pipeline ?? new CanonicalExecutionPipeline($this->gmOrchestrationBroker);'),
  'RoomChatService wires canonical execution pipeline dependency'
);
assert(
  roomChatSource.includes('$canonical_execution = $this->canonicalExecutionPipeline->execute(')
    && roomChatSource.includes("'error_count' => (int) ($canonical_execution['error_count'] ?? 0)"),
  'generateGmReply delegates canonical execution to subsystem pipeline'
);
assert(
  servicesSource.includes('dungeoncrawler_content.gm_canonical_execution_pipeline:')
    && servicesSource.includes("- '@?dungeoncrawler_content.gm_canonical_execution_pipeline'"),
  'service container registers and injects canonical execution pipeline'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}

