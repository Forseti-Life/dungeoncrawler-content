/**
 * @file
 * Contract coverage for GM reality-check delegation boundary.
 *
 * Run with:
 *   node tests/gm_realitycheck_delegation_contract_test.js
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

const roomChatServiceSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatService.php'),
  'utf8'
);
const gmPipelineSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatServiceGmPipelineTrait.php'),
  'utf8'
);
const roomChatCoreFlowSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatServiceCoreFlowTrait.php'),
  'utf8'
);
const roomChatDiagnosticsSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatServiceQuestUpdateAndDiagnosticsTrait.php'),
  'utf8'
);
const gmReplyOrchestrationSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmSubsystem/GmReplyOrchestrationService.php'),
  'utf8'
);
const realityServiceSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmSubsystem/GmRealityCheckGenerationService.php'),
  'utf8'
);
const realityCallbacksSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmSubsystem/GmRealityCheckCallbacks.php'),
  'utf8'
);
const realityPolicySource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmSubsystem/GmRealityCheckPolicyAdapter.php'),
  'utf8'
);
const modelInvocationSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmSubsystem/GmModelInvocationService.php'),
  'utf8'
);
const modelInvocationCallbacksSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmSubsystem/GmModelInvocationCallbacks.php'),
  'utf8'
);
const promptBudgetSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmSubsystem/GmPromptBudgetService.php'),
  'utf8'
);
const promptOrchestrationSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmSubsystem/GmPromptOrchestrationService.php'),
  'utf8'
);
const roleBoundarySource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmSubsystem/GmRoleBoundaryPolicyService.php'),
  'utf8'
);
const turnCoordinatorSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmSubsystem/GmTurnCoordinatorService.php'),
  'utf8'
);
const turnCoordinatorCallbacksSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmSubsystem/GmTurnCoordinatorCallbacks.php'),
  'utf8'
);
const turnFinalizationSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmSubsystem/GmTurnFinalizationService.php'),
  'utf8'
);
const turnFinalizationCallbacksSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmSubsystem/GmTurnFinalizationCallbacks.php'),
  'utf8'
);

console.log('\n=== GM reality-check delegation contract ===');

const canonicalExecutionIdx = gmPipelineSource.indexOf('$this->canonicalExecutionPipeline->execute(');
const stateMutationIdx = gmPipelineSource.indexOf('$this->stateMutationPipeline->apply(');
const transcriptProjectorIdx = gmPipelineSource.indexOf('$this->gmTranscriptProjector->project(');
const transcriptPersistIdx = gmPipelineSource.indexOf('$this->gmTranscriptPersistencePipeline->persistVisibleReply(');
const stripActionBlocksIdx = gmPipelineSource.indexOf('$this->stripPlayerVisibleActionBlocks(');

assert(
  roomChatServiceSource.includes('protected GmRealityCheckGenerationService $gmRealityCheckGeneration;'),
  'RoomChatService declares dedicated GM reality-check generation dependency'
);
assert(
  roomChatServiceSource.includes('protected GmResponseRoutingService $gmResponseRouting;'),
  'RoomChatService declares dedicated GM response routing dependency'
);
assert(
  roomChatServiceSource.includes('protected GmTurnCoordinatorService $gmTurnCoordinator;'),
  'RoomChatService declares dedicated GM turn-coordinator dependency'
);
assert(
  roomChatServiceSource.includes('protected GmTurnFinalizationService $gmTurnFinalization;'),
  'RoomChatService declares dedicated GM turn-finalization dependency'
);
assert(
  roomChatServiceSource.includes('protected GmModelInvocationService $gmModelInvocation;'),
  'RoomChatService declares dedicated GM model-invocation dependency'
);
assert(
  roomChatServiceSource.includes('protected GmPromptBudgetService $gmPromptBudget;'),
  'RoomChatService declares dedicated GM prompt-budget dependency'
);
assert(
  roomChatServiceSource.includes('protected GmPromptOrchestrationService $gmPromptOrchestration;'),
  'RoomChatService declares dedicated GM prompt orchestration dependency'
);
assert(
  roomChatServiceSource.includes('protected GmReplyOrchestrationService $gmReplyOrchestration;'),
  'RoomChatService declares GM reply-orchestration entry dependency'
);
assert(
  roomChatServiceSource.includes('protected GmRoleBoundaryPolicyService $gmRoleBoundaryPolicy;'),
  'RoomChatService declares dedicated GM role-boundary dependency'
);
assert(
  roomChatServiceSource.includes('$this->gmRealityCheckGeneration = $gm_reality_check_generation ?? new GmRealityCheckGenerationService();'),
  'RoomChatService wires GM reality-check generation dependency in constructor'
);
assert(
  roomChatServiceSource.includes('$this->gmModelInvocation = $gm_model_invocation ?? new GmModelInvocationService();'),
  'RoomChatService wires GM model-invocation dependency in constructor'
);
assert(
  roomChatServiceSource.includes('$this->gmPromptBudget = $gm_prompt_budget ?? new GmPromptBudgetService();'),
  'RoomChatService wires GM prompt-budget dependency in constructor'
);
assert(
  roomChatServiceSource.includes('$this->gmPromptOrchestration = $gm_prompt_orchestration ?? new GmPromptOrchestrationService($this->promptContextAssembler);'),
  'RoomChatService wires GM prompt orchestration dependency in constructor'
);
assert(
  roomChatServiceSource.includes('$this->gmReplyOrchestration = $gm_reply_orchestration ?? new GmReplyOrchestrationService();'),
  'RoomChatService wires GM reply-orchestration dependency in constructor'
);
assert(
  roomChatServiceSource.includes('$this->gmRoleBoundaryPolicy = $gm_role_boundary_policy ?? new GmRoleBoundaryPolicyService();'),
  'RoomChatService wires GM role-boundary dependency in constructor'
);
assert(
  roomChatServiceSource.includes('$this->gmResponseRouting = $gm_response_routing ?? new GmResponseRoutingService();'),
  'RoomChatService wires GM response routing dependency in constructor'
);
assert(
  roomChatServiceSource.includes('$this->gmTurnCoordinator = $gm_turn_coordinator ?? new GmTurnCoordinatorService($this->turnIntentRouter, $this->gmResponseRouting, $this->gmGenerationPolicy);'),
  'RoomChatService wires GM turn-coordinator dependency in constructor'
);
assert(
  roomChatServiceSource.includes('$this->gmTurnFinalization = $gm_turn_finalization ?? new GmTurnFinalizationService('),
  'RoomChatService wires GM turn-finalization dependency in constructor'
);
assert(
  gmPipelineSource.includes('return $this->gmRealityCheckGeneration->generate('),
  'RoomChat GM pipeline delegates reality-check generation through dedicated service boundary'
);
assert(
  gmPipelineSource.includes('return $this->gmModelInvocation->invoke('),
  'RoomChat GM pipeline delegates model invocation through dedicated service boundary'
);
assert(
  gmPipelineSource.includes('new GmModelInvocationCallbacks(')
    && modelInvocationSource.includes('GmModelInvocationCallbacks $callbacks')
    && modelInvocationCallbacksSource.includes('class GmModelInvocationCallbacks')
    && modelInvocationCallbacksSource.includes('public function fitContextBudget(')
    && modelInvocationCallbacksSource.includes('public function invokeTimedModelCall('),
  'GM model invocation path uses typed callback contract instead of inline callable fan-out'
);
assert(
  gmPipelineSource.includes('return $this->gmPromptBudget->fit('),
  'RoomChat GM pipeline delegates prompt budget fitting through dedicated service boundary'
);
assert(
  gmPipelineSource.includes('$this->gmPromptOrchestration->buildPromptArtifacts(')
    && gmPipelineSource.includes('$this->gmPromptOrchestration->buildPromptDebugMeta(')
    && promptOrchestrationSource.includes('class GmPromptOrchestrationService')
    && promptOrchestrationSource.includes('public function buildPromptArtifacts(')
    && promptOrchestrationSource.includes('public function buildPromptDebugMeta('),
  'RoomChat GM pipeline delegates prompt assembly and debug-metadata composition through dedicated orchestration service'
);
assert(
  gmPipelineSource.includes('$this->gmResponseRouting->resolveDeterministicBranch(')
    && turnCoordinatorSource.includes('$this->gmResponseRouting->shouldUseResponseCache(')
    && turnCoordinatorSource.includes('$this->gmResponseRouting->buildResponseCacheKey('),
  'RoomChat GM pipeline delegates deterministic-vs-LLM routing and cache-key preparation through dedicated response routing service'
);
assert(
  gmPipelineSource.includes('$this->gmTurnCoordinator->prepareTurnContext(')
    && gmPipelineSource.includes('new GmTurnCoordinatorCallbacks(')
    && gmPipelineSource.includes('$this->gmTurnCoordinator->handoffLlmGeneration(')
    && turnCoordinatorSource.includes('class GmTurnCoordinatorService')
    && turnCoordinatorSource.includes('public function prepareTurnContext(')
    && turnCoordinatorSource.includes('public function handoffLlmGeneration(')
    && turnCoordinatorCallbacksSource.includes('class GmTurnCoordinatorCallbacks'),
  'RoomChat GM pipeline delegates turn preparation and LLM handoff sequencing through dedicated turn-coordinator boundary'
);
assert(
  gmPipelineSource.includes('$this->gmTurnFinalization->finalizeTurn(')
   && turnFinalizationSource.includes('class GmTurnFinalizationService')
   && turnFinalizationSource.includes('public function finalizeTurn(')
   && turnFinalizationCallbacksSource.includes('class GmTurnFinalizationCallbacks')
   && turnFinalizationSource.includes('$this->gmNarrativePostProcessor->process(')
   && turnFinalizationSource.includes('$this->canonicalExecutionPipeline->execute(')
   && turnFinalizationSource.includes('$this->stateMutationPipeline->apply(')
   && turnFinalizationSource.includes('$this->gmTranscriptProjector->project(')
   && turnFinalizationSource.includes('$this->gmTranscriptPersistencePipeline->persistVisibleReply('),
  'RoomChat GM pipeline delegates post-generation execution, projection, and persistence through dedicated turn-finalization boundary'
);
assert(
  turnFinalizationSource.includes('$this->canonicalExecutionPipeline->execute(')
   && turnFinalizationSource.includes('$this->stateMutationPipeline->apply(')
   && turnFinalizationSource.includes('$this->gmTranscriptProjector->project(')
   && turnFinalizationSource.includes('$this->gmTranscriptPersistencePipeline->persistVisibleReply('),
  'GM turn-finalization service executes canonical/state mutations before projecting and persisting player-visible narrative'
);
assert(
  turnFinalizationSource.includes('$this->gmNarrativePostProcessor->process(')
   && turnFinalizationSource.includes('stripPlayerVisibleActionBlocks')
   && turnFinalizationSource.includes('trimIncompleteNarrative')
   && turnFinalizationSource.includes('sanitizePlayerVisibleNarrative'),
  'GM turn-finalization service strips player-visible action blocks before persisting final narrative output'
);
assert(
  gmPipelineSource.includes('return $this->gmRoleBoundaryPolicy->validateNarrative(')
    && gmPipelineSource.includes('return $this->gmRoleBoundaryPolicy->buildRetryPrompt(')
    && gmPipelineSource.includes('return $this->gmRoleBoundaryPolicy->buildSafeFallbackNarrative('),
  'RoomChat GM pipeline delegates role-boundary validation and fallback text through dedicated service boundary'
);
assert(
  roomChatCoreFlowSource.includes('$this->gmReplyOrchestration->generateRoomReply(')
    && roomChatCoreFlowSource.includes('$this->gmReplyOrchestration->generateQueuedReply(')
    && roomChatCoreFlowSource.includes("'speaker_type' => 'player'")
    && roomChatCoreFlowSource.includes("'is_gm_direct_channel' => $is_gm_direct_channel"),
  'RoomChat core flow delegates GM reply entry orchestration through dedicated GM subsystem service'
);
assert(
  gmReplyOrchestrationSource.includes('assertGmEntryContext')
    && gmReplyOrchestrationSource.includes('requires gm_direct_channel=true')
    && gmReplyOrchestrationSource.includes('only accepts player-originated turns')
    && gmReplyOrchestrationSource.includes('public function recordDebugStage(')
    && gmReplyOrchestrationSource.includes('public function recordCanonicalActionBatch('),
  'GM reply orchestration enforces strict entry contract and owns GM debug/action observability adapters'
);
assert(
  gmPipelineSource.includes('$this->gmReplyOrchestration->recordCanonicalActionBatch(')
    && roomChatDiagnosticsSource.includes('$this->gmReplyOrchestration->recordDebugStage(')
    && roomChatServiceSource.includes('protected GmReplyOrchestrationService $gmReplyOrchestration;'),
  'RoomChat delegates debug-stage and canonical action observability through GM orchestration adapter'
);
assert(
  realityServiceSource.includes('GmRealityCheckCallbacks $callbacks')
    && realityServiceSource.includes('GmRealityCheckPolicyAdapter $policy')
    && realityCallbacksSource.includes('class GmRealityCheckCallbacks')
    && realityCallbacksSource.includes('public function invokeModel(')
    && realityCallbacksSource.includes('public function recordDebugStage(')
    && realityPolicySource.includes('class GmRealityCheckPolicyAdapter')
    && realityPolicySource.includes('public function parseResponse(')
    && realityPolicySource.includes('public function validateRoleBoundary(')
    && realityPolicySource.includes('public function buildRealityRetryPrompt('),
  'GM reality-check service uses typed transport + typed response-policy adapters instead of raw callable fan-out'
);
assert(
  realityServiceSource.includes('class GmRealityCheckGenerationService')
    && realityServiceSource.includes('public function generate('),
  'Dedicated GM reality-check generation service exposes generation entrypoint'
);
assert(
  modelInvocationSource.includes('class GmModelInvocationService')
    && modelInvocationSource.includes('public function invoke('),
  'Dedicated GM model invocation service exposes invocation entrypoint'
);
assert(
  promptBudgetSource.includes('class GmPromptBudgetService')
    && promptBudgetSource.includes('public function fit('),
  'Dedicated GM prompt-budget service exposes fit entrypoint'
);
assert(
  roleBoundarySource.includes('class GmRoleBoundaryPolicyService')
    && roleBoundarySource.includes('public function validateNarrative(')
    && roleBoundarySource.includes('public function buildRetryPrompt(')
    && roleBoundarySource.includes('public function buildSafeFallbackNarrative('),
  'Dedicated GM role-boundary policy service exposes validation and fallback entrypoints'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
