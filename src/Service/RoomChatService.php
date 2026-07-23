<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\RoomChat\DeterministicNarrativeHelper;
use Drupal\dungeoncrawler_content\Service\RoomChat\EncounterTurnGuard;
use Drupal\dungeoncrawler_content\Service\RoomChat\FallbackAutomationDecisionBuilder;
use Drupal\dungeoncrawler_content\Service\RoomChat\GmPromptArtifactCacheBuilder;
use Drupal\dungeoncrawler_content\Service\RoomChat\EncounterTranscriptPrefixService;
use Drupal\dungeoncrawler_content\Service\RoomChat\NpcPromptAssembler;
use Drupal\dungeoncrawler_content\Service\RoomChat\RoomChatAccessGuard;
use Drupal\dungeoncrawler_content\Service\RoomChat\RoomChatHistoryProjector;
use Drupal\dungeoncrawler_content\Service\RoomChat\RoomLocator;
use Drupal\dungeoncrawler_content\Service\RoomChat\RoomNpcProfileGatherer;
use Drupal\dungeoncrawler_content\Service\RoomChat\SessionContextCompactor;
use Drupal\dungeoncrawler_content\Service\GmSubsystem\CanonicalExecutionPipeline;
use Drupal\dungeoncrawler_content\Service\GmSubsystem\GmGenerationPolicy;
use Drupal\dungeoncrawler_content\Service\GmSubsystem\GmModelInvocationService;
use Drupal\dungeoncrawler_content\Service\GmSubsystem\GmNarrativePostProcessor;
use Drupal\dungeoncrawler_content\Service\GmSubsystem\GmPromptBudgetService;
use Drupal\dungeoncrawler_content\Service\GmSubsystem\GmPromptOrchestrationService;
use Drupal\dungeoncrawler_content\Service\GmSubsystem\GmReplyOrchestrationService;
use Drupal\dungeoncrawler_content\Service\GmSubsystem\GmRealityCheckGenerationService;
use Drupal\dungeoncrawler_content\Service\GmSubsystem\GmRoleBoundaryPolicyService;
use Drupal\dungeoncrawler_content\Service\GmSubsystem\GmResponseRoutingService;
use Drupal\dungeoncrawler_content\Service\GmSubsystem\GmTurnCoordinatorService;
use Drupal\dungeoncrawler_content\Service\GmSubsystem\GmTurnFinalizationService;
use Drupal\dungeoncrawler_content\Service\NavigationApplicationOrchestrator;
use Drupal\dungeoncrawler_content\Service\NavigationRuntimeService;
use Drupal\dungeoncrawler_content\Service\NavigationTransitionPipeline;
use Drupal\dungeoncrawler_content\Service\GmSubsystem\PromptContextAssembler;
use Drupal\dungeoncrawler_content\Service\GmSubsystem\StateMutationPipeline;
use Drupal\dungeoncrawler_content\Service\GmSubsystem\GmTranscriptPersistencePipeline;
use Drupal\dungeoncrawler_content\Service\GmSubsystem\GmTranscriptProjector;
use Drupal\dungeoncrawler_content\Service\GmSubsystem\TurnIntentRouter;
use Drupal\ai_conversation\Service\AIApiService;
use Drupal\ai_conversation\Service\PromptManager;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

// Hierarchical chat session integration.
// These bridge legacy dungeon_data JSON chat into the normalized session tables.

/**
 * Manages room chat messages with proper state management.
 * 
 * Uses DungeonStateService for optimistic locking to prevent race conditions.
 */
class RoomChatService {

  const MAX_MESSAGE_LENGTH = 2000;
  const MAX_MESSAGES_PER_ROOM = 500;
  const NPC_RESPONSE_CONSUMPTION_MAX = 240;
  protected const PLAYER_AUTOMATION_ROOM_CHAT_LIMIT = 25;
  protected const ROOM_CHAT_MAX_INPUT_CHARS = 6800;
  protected const ROOM_CHAT_MAX_SYSTEM_PROMPT_CHARS = 7600;
  protected const ROOM_CHAT_MAX_USER_PROMPT_CHARS = 4000;
  protected const ROOM_CHAT_GM_MAX_TOKENS = 200;
  protected const NPC_FUZZY_MATCH_MIN_TOKEN_LENGTH = 4;
  protected const CHARACTER_DIALOGUE_SCHEMA_VERSION = 'character-dialogue-v1';
  protected const GM_ROOM_RESPONSE_SCHEMA_VERSION = 'gm-room-response-v1';
  protected const ROOM_TURN_HARNESS_SCHEMA_VERSION = 'room-turn-harness-v1';
  protected const ROOM_CHAT_RESPONSE_SCHEMA_VERSION = 'room-chat-response-v1';
  protected const QUEUED_ROOM_CONTINUATION_SCHEMA_VERSION = 'queued-room-continuation-v1';
  protected const QUEST_UPDATE_SCHEMA_VERSION = 'quest-update-v1';
  protected const NAVIGATION_ACTION_SCHEMA_VERSION = 'navigation-action-v2';
  protected const QUEST_UPDATE_ALLOWED_SOURCES = ['available_quest', 'brokered_storyline'];
  protected const AMBIENT_INTERJECTION_CHARISMA_MULTIPLIER = 4;
  protected const AMBIENT_INTERJECTION_PERCENT_CAP = 100;

  protected Connection $database;
  protected DungeonStateService $dungeonStateService;
  protected LoggerInterface $logger;
  protected AccountProxyInterface $currentUser;
  protected AIApiService $aiApiService;
  protected PromptManager $promptManager;
  protected GameplayActionProcessor $actionProcessor;
  protected AiSessionManager $sessionManager;
  protected ChatChannelManager $channelManager;
  protected NpcPsychologyService $psychologyService;
  protected ActorActionAvailabilityService $actorActionAvailabilityService;
  protected ?NarrationEngine $narrationEngine;
  protected ?ChatSessionManager $chatSessionManager;
  protected ?MapGeneratorService $mapGenerator;
  protected CanonicalActionRegistryService $canonicalActionRegistry;
  protected GmOrchestrationBrokerService $gmOrchestrationBroker;
  protected ?QuestTrackerService $questTracker;
  protected ?RelationshipManagerService $relationshipManager;
  protected ?QuestGeneratorService $questGenerator;
  protected ?StateValidationService $stateValidationService;
  protected ?StorylineManagerService $storylineManager;
  protected ?StorylineGenerationService $storylineGenerationService;
  protected StorylineQuestLifecycleService $storylineQuestLifecycleService;
  protected ?QuestTouchpointService $questTouchpointService;
  protected MerchantBotService $merchantBotService;
  protected ?InventoryManagementService $inventoryManagementService;
  protected ?MerchantTransactionService $merchantTransactionService;
  protected NpcAttentionService $attentionService;
  protected TurnIntentRouter $turnIntentRouter;
  protected PromptContextAssembler $promptContextAssembler;
  protected GmGenerationPolicy $gmGenerationPolicy;
  protected GmModelInvocationService $gmModelInvocation;
  protected GmPromptBudgetService $gmPromptBudget;
  protected GmPromptOrchestrationService $gmPromptOrchestration;
  protected GmReplyOrchestrationService $gmReplyOrchestration;
  protected GmRoleBoundaryPolicyService $gmRoleBoundaryPolicy;
  protected GmNarrativePostProcessor $gmNarrativePostProcessor;
  protected GmRealityCheckGenerationService $gmRealityCheckGeneration;
  protected GmResponseRoutingService $gmResponseRouting;
  protected GmTurnCoordinatorService $gmTurnCoordinator;
  protected GmTurnFinalizationService $gmTurnFinalization;
  protected CanonicalExecutionPipeline $canonicalExecutionPipeline;
  protected StateMutationPipeline $stateMutationPipeline;
  protected NavigationRuntimeService $navigationRuntimeService;
  protected NavigationTransitionPipeline $navigationTransitionPipeline;
  protected NavigationApplicationOrchestrator $navigationApplicationOrchestrator;
  protected GmTranscriptPersistencePipeline $gmTranscriptPersistencePipeline;
  protected GmTranscriptProjector $gmTranscriptProjector;
  protected RoomChatHistoryProjector $roomChatHistoryProjector;
  protected EncounterTranscriptPrefixService $encounterTranscriptPrefixService;
  protected RoomChatAccessGuard $roomChatAccessGuard;
  protected RoomLocator $roomLocator;
  protected EncounterTurnGuard $encounterTurnGuard;
  protected DungeonPayloadStatePersistenceService $dungeonPayloadStatePersistence;
  protected ?array $activeDebugTrace = NULL;
  protected ?bool $roomTurnLogStoreAvailable = NULL;

  /**
   * Constructor.
   */
  public function __construct(
    Connection $database,
    DungeonStateService $dungeon_state_service,
    LoggerChannelFactoryInterface $logger_factory,
    AccountProxyInterface $current_user,
    AIApiService $ai_api_service,
    PromptManager $prompt_manager,
    GameplayActionProcessor $action_processor,
    AiSessionManager $session_manager,
    ChatChannelManager $channel_manager,
    NpcPsychologyService $psychology_service,
    StorylineQuestLifecycleService $storyline_quest_lifecycle_service,
    DungeonPayloadStatePersistenceService $dungeon_payload_state_persistence,
    ?NarrationEngine $narration_engine = NULL,
    ?ChatSessionManager $chat_session_manager = NULL,
    ?MapGeneratorService $map_generator = NULL,
    ?CanonicalActionRegistryService $canonical_action_registry = NULL,
    ?GmOrchestrationBrokerService $gm_orchestration_broker = NULL,
    ?QuestTrackerService $quest_tracker = NULL,
    ?RelationshipManagerService $relationship_manager = NULL,
    ?MerchantBotService $merchant_bot_service = NULL,
    ?ClientInterface $http_client = NULL,
    ?InventoryManagementService $inventory_management_service = NULL,
    ?MerchantTransactionService $merchant_transaction_service = NULL,
    ?QuestGeneratorService $quest_generator = NULL,
    ?StateValidationService $state_validation_service = NULL,
    ?StorylineManagerService $storyline_manager = NULL,
    ?StorylineGenerationService $storyline_generation_service = NULL,
    ?QuestTouchpointService $quest_touchpoint_service = NULL,
    ?NpcAttentionService $attention_service = NULL,
    ?TurnIntentRouter $turn_intent_router = NULL,
    ?PromptContextAssembler $prompt_context_assembler = NULL,
    ?GmGenerationPolicy $gm_generation_policy = NULL,
    ?GmModelInvocationService $gm_model_invocation = NULL,
    ?GmPromptBudgetService $gm_prompt_budget = NULL,
    ?GmReplyOrchestrationService $gm_reply_orchestration = NULL,
    ?GmRoleBoundaryPolicyService $gm_role_boundary_policy = NULL,
    ?GmNarrativePostProcessor $gm_narrative_post_processor = NULL,
    ?GmRealityCheckGenerationService $gm_reality_check_generation = NULL,
    ?GmResponseRoutingService $gm_response_routing = NULL,
    ?GmTurnCoordinatorService $gm_turn_coordinator = NULL,
    ?GmTurnFinalizationService $gm_turn_finalization = NULL,
    ?CanonicalExecutionPipeline $canonical_execution_pipeline = NULL,
    ?StateMutationPipeline $state_mutation_pipeline = NULL,
    ?NavigationRuntimeService $navigation_runtime_service = NULL,
    ?NavigationTransitionPipeline $navigation_transition_pipeline = NULL,
    ?NavigationApplicationOrchestrator $navigation_application_orchestrator = NULL,
    ?GmTranscriptPersistencePipeline $gm_transcript_persistence_pipeline = NULL,
    ?GmTranscriptProjector $gm_transcript_projector = NULL,
    ?RoomChatHistoryProjector $room_chat_history_projector = NULL,
    ?EncounterTranscriptPrefixService $encounter_transcript_prefix_service = NULL,
    ?RoomChatAccessGuard $room_chat_access_guard = NULL,
    ?RoomLocator $room_locator = NULL,
    ?EncounterTurnGuard $encounter_turn_guard = NULL,
    ?GmPromptOrchestrationService $gm_prompt_orchestration = NULL,
    ?ActorActionAvailabilityService $actor_action_availability_service = NULL
  ) {
    $this->database = $database;
    $this->dungeonStateService = $dungeon_state_service;
    $this->logger = $logger_factory->get('dungeoncrawler_chat');
    $this->currentUser = $current_user;
    $this->aiApiService = $ai_api_service;
    $this->promptManager = $prompt_manager;
    $this->actionProcessor = $action_processor;
    $this->sessionManager = $session_manager;
    $this->channelManager = $channel_manager;
    $this->psychologyService = $psychology_service;
    $this->dungeonPayloadStatePersistence = $dungeon_payload_state_persistence;
    $this->actorActionAvailabilityService = $actor_action_availability_service ?? new ActorActionAvailabilityService();
    $this->narrationEngine = $narration_engine;
    $this->chatSessionManager = $chat_session_manager;
    $this->mapGenerator = $map_generator;
    $this->canonicalActionRegistry = $canonical_action_registry ?? new CanonicalActionRegistryService($database, $current_user);
    $this->gmOrchestrationBroker = $gm_orchestration_broker ?? new GmOrchestrationBrokerService($database, $this->canonicalActionRegistry);
    $this->questTracker = $quest_tracker;
    $this->relationshipManager = $relationship_manager;
    $this->merchantBotService = $merchant_bot_service ?? new MerchantBotService($database, $logger_factory, $http_client);
    $this->inventoryManagementService = $inventory_management_service;
    $this->merchantTransactionService = $merchant_transaction_service;
    $this->questGenerator = $quest_generator;
    $this->stateValidationService = $state_validation_service;
    $this->storylineManager = $storyline_manager;
    $this->storylineGenerationService = $storyline_generation_service;
    $this->storylineQuestLifecycleService = $storyline_quest_lifecycle_service;
    $this->questTouchpointService = $quest_touchpoint_service;
    $this->attentionService = $attention_service ?? new NpcAttentionService();
    $this->turnIntentRouter = $turn_intent_router ?? new TurnIntentRouter();
    $this->promptContextAssembler = $prompt_context_assembler ?? new PromptContextAssembler();
    $this->gmGenerationPolicy = $gm_generation_policy ?? new GmGenerationPolicy();
    $this->gmModelInvocation = $gm_model_invocation ?? new GmModelInvocationService();
    $this->gmPromptBudget = $gm_prompt_budget ?? new GmPromptBudgetService();
    $this->gmReplyOrchestration = $gm_reply_orchestration ?? new GmReplyOrchestrationService();
    $this->gmRoleBoundaryPolicy = $gm_role_boundary_policy ?? new GmRoleBoundaryPolicyService();
    $this->gmNarrativePostProcessor = $gm_narrative_post_processor ?? new GmNarrativePostProcessor($ai_api_service);
    $this->gmRealityCheckGeneration = $gm_reality_check_generation ?? new GmRealityCheckGenerationService();
    $this->gmResponseRouting = $gm_response_routing ?? new GmResponseRoutingService();
    $this->gmTurnCoordinator = $gm_turn_coordinator ?? new GmTurnCoordinatorService($this->turnIntentRouter, $this->gmResponseRouting, $this->gmGenerationPolicy);
    $this->canonicalExecutionPipeline = $canonical_execution_pipeline ?? new CanonicalExecutionPipeline($this->gmOrchestrationBroker);
    $this->stateMutationPipeline = $state_mutation_pipeline ?? new StateMutationPipeline($this->actionProcessor);
    $this->roomLocator = $room_locator ?? new RoomLocator();
    if (!$navigation_runtime_service || !$navigation_transition_pipeline || !$navigation_application_orchestrator) {
      throw new \RuntimeException('RoomChatService contract violation: navigation runtime, transition pipeline, and application orchestrator must be injected.');
    }
    $this->navigationRuntimeService = $navigation_runtime_service;
    $this->navigationTransitionPipeline = $navigation_transition_pipeline;
    $this->navigationApplicationOrchestrator = $navigation_application_orchestrator;
    $this->gmTranscriptPersistencePipeline = $gm_transcript_persistence_pipeline ?? new GmTranscriptPersistencePipeline($database, $session_manager);
    $this->gmTranscriptProjector = $gm_transcript_projector ?? new GmTranscriptProjector();
    $this->roomChatHistoryProjector = $room_chat_history_projector ?? new RoomChatHistoryProjector();
    $this->encounterTranscriptPrefixService = $encounter_transcript_prefix_service ?? new EncounterTranscriptPrefixService();
    $this->roomChatAccessGuard = $room_chat_access_guard ?? new RoomChatAccessGuard($database, $current_user);
    $this->encounterTurnGuard = $encounter_turn_guard ?? new EncounterTurnGuard();
    $this->gmPromptOrchestration = $gm_prompt_orchestration ?? new GmPromptOrchestrationService($this->promptContextAssembler);
    $this->gmTurnFinalization = $gm_turn_finalization ?? new GmTurnFinalizationService(
      $this->gmNarrativePostProcessor,
      $this->canonicalExecutionPipeline,
      $this->stateMutationPipeline,
      $this->gmTranscriptProjector,
      $this->gmTranscriptPersistencePipeline,
      $this->encounterTranscriptPrefixService,
      $this->roomLocator,
      $this->navigationApplicationOrchestrator,
      $this->logger
    );
    $this->enforceTraitContracts();
  }

  /**
   * Fail fast if decomposed trait dependencies drift out of contract.
   */
  protected function enforceTraitContracts(): void {
    $required_methods = [
      'normalizeNpcNameForMatch',
      'textContainsAny',
      'extractNavigationDestination',
      'hasVisitedDestinationName',
    ];
    foreach ($required_methods as $method_name) {
      if (!method_exists($this, $method_name)) {
        throw new \RuntimeException('RoomChatService contract violation: missing required method ' . $method_name . '().');
      }
    }

    if (!method_exists($this->actionProcessor, 'getResolvedRoomExits')) {
      throw new \RuntimeException('RoomChatService contract violation: GameplayActionProcessor::getResolvedRoomExits() is required.');
    }
    if (!method_exists($this->navigationRuntimeService, 'buildCanonicalNavigationActionPayload')) {
      throw new \RuntimeException('RoomChatService contract violation: NavigationRuntimeService::buildCanonicalNavigationActionPayload() is required.');
    }
  }


  // Decomposed method partitions (each file kept below 2k lines).
  use RoomChatServiceCoreFlowTrait;
  use RoomChatServiceGmPipelineTrait;
  use RoomChatServiceChannelAndSessionTrait;
  use RoomChatServiceNpcInterjectionTrait;
  use RoomChatServiceIntentAndDeterminismTrait;
  use RoomChatServiceNpcDialogueAndQuestLeadTrait;
  use RoomChatServiceConversationStateAndQuestActivationTrait;
  use RoomChatServiceQuestUpdateAndDiagnosticsTrait;

}
