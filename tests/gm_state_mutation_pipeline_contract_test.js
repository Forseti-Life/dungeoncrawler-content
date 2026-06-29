/**
 * @file
 * Contract checks for state-mutation pipeline extraction.
 *
 * Run with:
 *   node tests/gm_state_mutation_pipeline_contract_test.js
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
  path.resolve(__dirname, '../src/Service/GmSubsystem/StateMutationPipeline.php'),
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

console.log('\n=== GM state mutation pipeline contract ===');

assert(
  pipelineSource.includes('class StateMutationPipeline'),
  'state mutation pipeline service class exists'
);
assert(
  pipelineSource.includes('public function apply(')
    && pipelineSource.includes("'state_diff' =>"),
  'pipeline exposes apply() and returns state_diff payload'
);
assert(
  pipelineSource.includes('applyCharacterStateChanges(')
    && pipelineSource.includes('applyRoomStateChanges(')
    && pipelineSource.includes('buildStateDiffSummary('),
  'pipeline owns character+room mutations and state diff construction'
);
assert(
  roomChatSource.includes('protected StateMutationPipeline $stateMutationPipeline;')
    && roomChatSource.includes('$this->stateMutationPipeline = $state_mutation_pipeline ?? new StateMutationPipeline($this->actionProcessor);'),
  'RoomChatService wires state mutation pipeline dependency'
);
assert(
  roomChatSource.includes('$mutation_result = $this->stateMutationPipeline->apply(')
    && roomChatSource.includes("$state_diff = $mutation_result['state_diff'] ?? NULL;"),
  'generateGmReply delegates mutation/diff work to subsystem pipeline'
);
assert(
  servicesSource.includes('dungeoncrawler_content.gm_state_mutation_pipeline:')
    && servicesSource.includes("- '@?dungeoncrawler_content.gm_state_mutation_pipeline'"),
  'service container registers and injects state mutation pipeline'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}

