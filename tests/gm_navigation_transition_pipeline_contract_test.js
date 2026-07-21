/**
 * @file
 * Contract checks for navigation-owned transition pipeline extraction.
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
  path.resolve(__dirname, '../src/Service/NavigationTransitionPipeline.php'),
  'utf8'
);
const runtimeSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/NavigationRuntimeService.php'),
  'utf8'
);
const appOrchestratorSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/NavigationApplicationOrchestrator.php'),
  'utf8'
);
const roomChatSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatService.php'),
  'utf8'
);
const gmPipelineTraitSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatServiceGmPipelineTrait.php'),
  'utf8'
);
const servicesSource = fs.readFileSync(
  path.resolve(__dirname, '../dungeoncrawler_content.services.yml'),
  'utf8'
);

console.log('\n=== Navigation transition pipeline contract ===');

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
  pipelineSource.includes('protected NavigationRuntimeService $navigationRuntime;')
    && pipelineSource.includes('$this->navigationRuntime->handleNavigationActions(')
    && pipelineSource.includes('$this->navigationRuntime->appendDestinationArrivalNarration(')
    && pipelineSource.includes('$this->navigationRuntime->recordLocationTransition('),
  'pipeline delegates navigation execution and side effects to NavigationRuntimeService'
);
assert(
  runtimeSource.includes('class NavigationRuntimeService')
    && runtimeSource.includes('public function handleNavigationActions('),
  'navigation runtime service exists with dedicated navigate_to_location execution entrypoint'
);
assert(
  appOrchestratorSource.includes('class NavigationApplicationOrchestrator')
    && appOrchestratorSource.includes('public function applyNavigationTransition(')
    && appOrchestratorSource.includes('$this->navigationTransitionPipeline->apply('),
  'navigation application orchestrator owns transition coordination wrapper'
);
assert(
  roomChatSource.includes('protected NavigationTransitionPipeline $navigationTransitionPipeline;')
    && roomChatSource.includes('protected NavigationRuntimeService $navigationRuntimeService;')
    && roomChatSource.includes('protected NavigationApplicationOrchestrator $navigationApplicationOrchestrator;')
    && roomChatSource.includes('RoomChatService contract violation: navigation runtime, transition pipeline, and application orchestrator must be injected.')
    && roomChatSource.includes('$this->navigationApplicationOrchestrator = $navigation_application_orchestrator;'),
  'RoomChatService requires injected navigation runtime, transition pipeline, and application orchestrator'
);
assert(
  gmPipelineTraitSource.includes('filterChatBlockedNavigationActions(')
    && !gmPipelineTraitSource.includes('navigation_action_bar_required')
    && !gmPipelineTraitSource.includes('travel must originate from action rail'),
  'GM pipeline no longer blocks chat-originated navigate_to_location actions'
);
assert(
  !gmPipelineTraitSource.includes('protected function handleNavigationActions('),
  'RoomChat GM pipeline trait no longer owns navigate_to_location execution'
);
assert(
  servicesSource.includes('dungeoncrawler_content.navigation_runtime:')
    && servicesSource.includes("- '@dungeoncrawler_content.navigation_runtime'")
    && servicesSource.includes('dungeoncrawler_content.navigation_transition_pipeline:')
    && servicesSource.includes("- '@dungeoncrawler_content.navigation_transition_pipeline'")
    && servicesSource.includes('dungeoncrawler_content.navigation_application_orchestrator:'),
  'service container registers and injects strict navigation runtime/pipeline/orchestrator dependencies'
);
assert(
  !runtimeSource.includes('Failed to generate the new location. Try again.'),
  'navigation runtime has no soft-fallback error payload text'
);
assert(
  servicesSource.includes('dungeoncrawler_content.navigation_transition_pipeline:')
    && servicesSource.includes('arguments:'),
  'navigation transition pipeline service receives explicit constructor dependencies'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
