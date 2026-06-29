/**
 * @file
 * Contract checks for navigation transition pipeline extraction.
 *
 * Run with:
 *   node tests/gm_navigation_transition_pipeline_contract_test.js
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
  path.resolve(__dirname, '../src/Service/GmSubsystem/NavigationTransitionPipeline.php'),
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

console.log('\n=== GM navigation transition pipeline contract ===');

assert(
  pipelineSource.includes('class NavigationTransitionPipeline'),
  'navigation transition pipeline service class exists'
);
assert(
  pipelineSource.includes('public function apply(')
    && pipelineSource.includes("'navigation_result' =>"),
  'pipeline exposes apply() and returns navigation_result payload'
);
assert(
  pipelineSource.includes('$handle_navigation(')
    && pipelineSource.includes('$append_destination_arrival(')
    && pipelineSource.includes('$record_location_transition('),
  'pipeline orchestrates navigation handling and transition side-effects via callables'
);
assert(
  roomChatSource.includes('protected NavigationTransitionPipeline $navigationTransitionPipeline;')
    && roomChatSource.includes('$this->navigationTransitionPipeline = $navigation_transition_pipeline ?? new NavigationTransitionPipeline();'),
  'RoomChatService wires navigation transition pipeline dependency'
);
assert(
  roomChatSource.includes('$navigation_pipeline = $this->navigationTransitionPipeline->apply(')
    && roomChatSource.includes("'navigation_success' => !empty($navigation_pipeline['navigation_success'])"),
  'generateGmReply delegates navigation transition orchestration to subsystem pipeline'
);
assert(
  servicesSource.includes('dungeoncrawler_content.gm_navigation_transition_pipeline:')
    && servicesSource.includes("- '@?dungeoncrawler_content.gm_navigation_transition_pipeline'"),
  'service container registers and injects navigation transition pipeline'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}

