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
use Drupal\dungeoncrawler_content\Service\GmSubsystem\GmNarrativePostProcessor;
use Drupal\dungeoncrawler_content\Service\GmSubsystem\NavigationTransitionPipeline;
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
  protected ?QuestTouchpointService $questTouchpointService;
  protected MerchantBotService $merchantBotService;
  protected ?InventoryManagementService $inventoryManagementService;
  protected ?MerchantTransactionService $merchantTransactionService;
  protected NpcAttentionService $attentionService;
  protected TurnIntentRouter $turnIntentRouter;
  protected PromptContextAssembler $promptContextAssembler;
  protected GmGenerationPolicy $gmGenerationPolicy;
  protected GmNarrativePostProcessor $gmNarrativePostProcessor;
  protected CanonicalExecutionPipeline $canonicalExecutionPipeline;
  protected StateMutationPipeline $stateMutationPipeline;
  protected NavigationTransitionPipeline $navigationTransitionPipeline;
  protected GmTranscriptPersistencePipeline $gmTranscriptPersistencePipeline;
  protected GmTranscriptProjector $gmTranscriptProjector;
  protected RoomChatHistoryProjector $roomChatHistoryProjector;
  protected EncounterTranscriptPrefixService $encounterTranscriptPrefixService;
  protected RoomChatAccessGuard $roomChatAccessGuard;
  protected RoomLocator $roomLocator;
  protected EncounterTurnGuard $encounterTurnGuard;
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
    ?GmNarrativePostProcessor $gm_narrative_post_processor = NULL,
    ?CanonicalExecutionPipeline $canonical_execution_pipeline = NULL,
    ?StateMutationPipeline $state_mutation_pipeline = NULL,
    ?NavigationTransitionPipeline $navigation_transition_pipeline = NULL,
    ?GmTranscriptPersistencePipeline $gm_transcript_persistence_pipeline = NULL,
    ?GmTranscriptProjector $gm_transcript_projector = NULL,
    ?RoomChatHistoryProjector $room_chat_history_projector = NULL,
    ?EncounterTranscriptPrefixService $encounter_transcript_prefix_service = NULL,
    ?RoomChatAccessGuard $room_chat_access_guard = NULL,
    ?RoomLocator $room_locator = NULL,
    ?EncounterTurnGuard $encounter_turn_guard = NULL
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
    $this->questTouchpointService = $quest_touchpoint_service;
    $this->attentionService = $attention_service ?? new NpcAttentionService();
    $this->turnIntentRouter = $turn_intent_router ?? new TurnIntentRouter();
    $this->promptContextAssembler = $prompt_context_assembler ?? new PromptContextAssembler();
    $this->gmGenerationPolicy = $gm_generation_policy ?? new GmGenerationPolicy();
    $this->gmNarrativePostProcessor = $gm_narrative_post_processor ?? new GmNarrativePostProcessor($ai_api_service);
    $this->canonicalExecutionPipeline = $canonical_execution_pipeline ?? new CanonicalExecutionPipeline($this->gmOrchestrationBroker);
    $this->stateMutationPipeline = $state_mutation_pipeline ?? new StateMutationPipeline($this->actionProcessor);
    $this->navigationTransitionPipeline = $navigation_transition_pipeline ?? new NavigationTransitionPipeline();
    $this->gmTranscriptPersistencePipeline = $gm_transcript_persistence_pipeline ?? new GmTranscriptPersistencePipeline($database, $session_manager);
    $this->gmTranscriptProjector = $gm_transcript_projector ?? new GmTranscriptProjector();
    $this->roomChatHistoryProjector = $room_chat_history_projector ?? new RoomChatHistoryProjector();
    $this->encounterTranscriptPrefixService = $encounter_transcript_prefix_service ?? new EncounterTranscriptPrefixService();
    $this->roomChatAccessGuard = $room_chat_access_guard ?? new RoomChatAccessGuard($database, $current_user);
    $this->roomLocator = $room_locator ?? new RoomLocator();
    $this->encounterTurnGuard = $encounter_turn_guard ?? new EncounterTurnGuard();
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
