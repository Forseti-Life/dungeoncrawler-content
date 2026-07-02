<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\ai_conversation\Service\AIApiService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Unified AI Game Master narration service.
 *
 * Generates immersive narration text for key game moments across all phases:
 * - Room entry (exploration)
 * - Encounter start / end
 * - Round start (combat)
 * - Entity defeated
 * - Phase transitions
 *
 * Uses the ai_conversation module's AIApiService for LLM calls, with
 * deterministic template fallbacks when AI is unavailable or disabled.
 *
 * Design:
 * - Single service, trigger-based methods (one per narration type).
 * - Context pulled from GameEventLogger for continuity.
 * - Respects config toggles (ai_gm_narration_enabled, per-trigger overrides).
 * - Prompt structure: system prompt + JSON context → plain text narration.
 */
class AiGmService {

  /**
   * AI API service (nullable — module may be absent).
   */
  protected ?AIApiService $aiApiService;

  /**
   * Config factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Logger.
   */
  protected LoggerInterface $logger;

  /**
   * Game event logger for recent events context.
   */
  protected GameEventLogger $eventLogger;

  /**
   * AI session manager for per-campaign/NPC conversation isolation.
   */
  protected AiSessionManager $sessionManager;

  /**
   * NPC psychology service for attitude and profile data.
   */
  protected NpcPsychologyService $npcPsychologyService;

  /**
   * NPC service for canonical campaign NPC catalog (AC-003).
   */
  protected NpcService $npcService;

  /**
   * Encounter balancer for GM-tools encounter generation.
   */
  protected EncounterBalancer $encounterBalancer;

  /**
   * Session service for session summaries and prior-session context.
   */
  protected SessionService $sessionService;

  /**
   * Expirable key-value store for per-campaign rate limiting.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface
   */
  protected $rateLimitStore;

  /**
   * Maximum AI GM API calls allowed per campaign per hour (Security AC).
   */
  const RATE_LIMIT_MAX_CALLS = 60;

  /**
   * Rate-limit window in seconds (1 hour).
   */
  const RATE_LIMIT_WINDOW = 3600;

  /**
   * Constructs the AiGmService.
   */
  public function __construct(
    ?AIApiService $ai_api_service,
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory,
    GameEventLogger $event_logger,
    AiSessionManager $session_manager,
    NpcPsychologyService $npc_psychology_service,
    EncounterBalancer $encounter_balancer,
    SessionService $session_service,
    NpcService $npc_service,
    KeyValueExpirableFactoryInterface $key_value_expirable_factory
  ) {
    $this->aiApiService = $ai_api_service;
    $this->configFactory = $config_factory;
    $this->logger = $logger_factory->get('dungeoncrawler');
    $this->eventLogger = $event_logger;
    $this->sessionManager = $session_manager;
    $this->npcPsychologyService = $npc_psychology_service;
    $this->encounterBalancer = $encounter_balancer;
    $this->sessionService = $session_service;
    $this->npcService = $npc_service;
    $this->rateLimitStore = $key_value_expirable_factory->get('dungeoncrawler_content.ai_gm_rate_limit');
  }

  /**
   * Enforce per-campaign AI GM rate limit (Security AC — TC-GNE-12).
   *
   * Increments the call counter for the campaign within the current window.
   * Throws TooManyRequestsHttpException (HTTP 429) when the limit is exceeded.
   *
   * @param int $campaign_id
   *   Campaign ID (use 0 for non-campaign calls — tracked under key "0").
   *
   * @throws \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException
   */
  protected function enforceRateLimit(int $campaign_id): void {
    $key = 'campaign_' . $campaign_id;
    $count = (int) ($this->rateLimitStore->get($key, 0));
    if ($count >= self::RATE_LIMIT_MAX_CALLS) {
      $this->logger->warning('[AiGmService] Rate limit exceeded for campaign @cid (@count calls this window)', [
        '@cid' => $campaign_id,
        '@count' => $count,
      ]);
      throw new TooManyRequestsHttpException(
        self::RATE_LIMIT_WINDOW,
        'AI GM rate limit exceeded. Please wait before making additional requests.'
      );
    }
    $this->rateLimitStore->setWithExpire($key, $count + 1, self::RATE_LIMIT_WINDOW);
  }

  // =========================================================================
  // AC-001: GM Context Assembly.
  // =========================================================================

  /**
   * Assemble the full AI GM context for a scene request.
   *
   * Includes: current session rolling summary, active NPC roster with
   * attitudes, quest hooks, current location, recent events, and prior-session
   * summaries (recent sessions prioritized, truncated to fit context window).
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param array $current_scene
   *   Optional override data: location (string), quest_hooks (array),
   *   recent_events (array), tone (string).
   *
   * @return array
   *   Structured GM context array.
   */
  public function assembleGmContext(int $campaign_id, array $current_scene = []): array {
    // Prior-session summary from the last ended session.
    $prior = $this->sessionService->buildAiGmContext($campaign_id);
    $prior_summary = $prior['prior_session_summary'] ?? '';

    // Active NPC roster with attitudes (from psychology profiles).
    $npc_profiles = $this->npcPsychologyService->getCampaignProfiles($campaign_id);
    $npc_roster = array_map(function (array $profile): array {
      return [
        'entity_ref' => $profile['entity_ref'] ?? '',
        'name' => $profile['name'] ?? $profile['entity_ref'] ?? 'Unknown',
        'role' => $profile['role'] ?? 'unknown',
        'attitude' => $profile['attitude'] ?? 'indifferent',
      ];
    }, $npc_profiles);

    // AC-003: canonical named NPC catalog with lore/dialogue context.
    $named_npcs = $this->npcService->buildAiPromptData($campaign_id);

    // Rolling GM session context (recent messages + compressed summary).
    $session_key = $this->sessionManager->gmSessionKey($campaign_id);
    $session_context = $this->sessionManager->buildSessionContext($session_key, $campaign_id, 10);

    return [
      'campaign_id' => $campaign_id,
      'prior_session_summary' => $prior_summary,
      'session_context' => $session_context,
      'npc_roster' => array_values($npc_roster),
      'named_npcs' => $named_npcs,
      'location' => $current_scene['location'] ?? '',
      'quest_hooks' => $current_scene['quest_hooks'] ?? [],
      'recent_events' => $current_scene['recent_events'] ?? [],
      'tone' => $current_scene['tone'] ?? 'dark fantasy',
    ];
  }

  // =========================================================================
  // AC-003: NPC Dialogue — attitude shift narration.
  // =========================================================================

  /**
   * Narrate the moment an NPC softens after a successful Diplomacy check.
   *
   * Updates the stored attitude field and generates immersive narration
   * reflecting the NPC's change in disposition.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $entity_ref
   *   NPC entity reference key.
   * @param string $player_name
   *   Name of the player character who made the check.
   * @param string $old_attitude
   *   Attitude before the check (e.g. 'hostile', 'unfriendly').
   * @param string $new_attitude
   *   Attitude after the shift (e.g. 'indifferent', 'friendly').
   * @param array $npc_data
   *   Optional live entity data for the NPC (name, description, etc.).
   *
   * @return string|null
   *   Narration text, or NULL if AI unavailable.
   */
  public function narrateNpcAttitudeShift(
    int $campaign_id,
    string $entity_ref,
    string $player_name,
    string $old_attitude,
    string $new_attitude,
    array $npc_data = []
  ): ?string {
    // Persist the attitude update.
    $this->npcPsychologyService->updateProfile($campaign_id, $entity_ref, ['attitude' => $new_attitude]);

    $npc_context = $this->npcPsychologyService->buildNpcContextForPrompt($campaign_id, $entity_ref, $npc_data);

    $context = [
      'trigger' => 'npc_attitude_shift',
      'npc_ref' => $entity_ref,
      'npc_name' => $npc_data['name'] ?? $entity_ref,
      'player_name' => $player_name,
      'attitude_before' => $old_attitude,
      'attitude_after' => $new_attitude,
      'npc_context' => $npc_context,
    ];

    $system = $this->buildSystemPrompt('npc_attitude_shift');
    $session_key = $this->sessionManager->npcSessionKey($campaign_id, $entity_ref);
    $session_ctx = $this->sessionManager->buildSessionContext($session_key, $campaign_id, 6);
    $prompt = ($session_ctx !== '' ? $session_ctx . "\n\n---\nCURRENT REQUEST:\n" : '') . $this->buildPrompt($context);

    if ($this->aiApiService === NULL) {
      return $this->fallbackNpcAttitudeShift($npc_data['name'] ?? $entity_ref, $old_attitude, $new_attitude);
    }

    $this->enforceRateLimit($campaign_id);

    try {
      $response = $this->aiApiService->invokeModelDirect(
        $prompt,
        'dungeoncrawler_content',
        'ai_gm_npc_attitude_shift',
        ['trigger' => 'npc_attitude_shift', 'campaign_id' => $campaign_id],
        ['max_tokens' => $this->getMaxTokens(), 'skip_cache' => TRUE, 'system_prompt' => $system]
      );
      if (!empty($response['success'])) {
        $text = $this->stripMarkdownFences(trim((string) ($response['response'] ?? '')));
        if ($text !== '') {
          $this->sessionManager->appendMessage($session_key, $campaign_id, 'assistant', $text, ['trigger' => 'npc_attitude_shift']);
          return $text;
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->warning('[AiGmService] npc_attitude_shift exception: @err', ['@err' => $e->getMessage()]);
    }

    return $this->fallbackNpcAttitudeShift($npc_data['name'] ?? $entity_ref, $old_attitude, $new_attitude);
  }

  // =========================================================================
  // AC-005: Session Summary Generation.
  // =========================================================================

  /**
   * AI-generate a narrative summary at session end and save it.
   *
   * Generates a brief narrative covering key events, XP earned, and NPCs met.
   * Calls SessionService::endSession to persist the summary and character
   * state snapshots.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param array $session_data
   *   Must include: session_id (int), character_states (array),
   *   session_xp (int), key_events (array), npcs_met (array),
   *   npcs (array for narrative_state).
   *
   * @return string|null
   *   The generated (or fallback) narrative summary.
   */
  public function generateSessionSummary(int $campaign_id, array $session_data): ?string {
    $session_id = (int) ($session_data['session_id'] ?? 0);
    if ($session_id <= 0) {
      return NULL;
    }

    $context = [
      'trigger' => 'session_summary',
      'campaign_id' => $campaign_id,
      'session_xp' => $session_data['session_xp'] ?? 0,
      'key_events' => $session_data['key_events'] ?? [],
      'npcs_met' => $session_data['npcs_met'] ?? [],
    ];

    $system = $this->buildSystemPrompt('session_summary');
    $prompt = $this->buildPrompt($context);

    $summary = NULL;

    if ($this->aiApiService !== NULL) {
      $this->enforceRateLimit($campaign_id);
      try {
        $response = $this->aiApiService->invokeModelDirect(
          $prompt,
          'dungeoncrawler_content',
          'ai_gm_session_summary',
          ['trigger' => 'session_summary', 'campaign_id' => $campaign_id],
          ['max_tokens' => 300, 'skip_cache' => TRUE, 'system_prompt' => $system]
        );
        if (!empty($response['success'])) {
          $summary = $this->stripMarkdownFences(trim((string) ($response['response'] ?? '')));
          if ($summary === '') {
            $summary = NULL;
          }
        }
      }
      catch (\Exception $e) {
        $this->logger->warning('[AiGmService] session_summary exception: @err', ['@err' => $e->getMessage()]);
      }
    }

    // Fall back to a simple template summary.
    if ($summary === NULL) {
      $xp = (int) ($session_data['session_xp'] ?? 0);
      $event_count = count($session_data['key_events'] ?? []);
      $npc_count = count($session_data['npcs_met'] ?? []);
      $summary = "The session concluded. The party earned {$xp} XP across {$event_count} key events and encountered {$npc_count} NPCs.";
    }

    // Persist via SessionService.
    try {
      $this->sessionService->endSession($session_id, [
        'character_states' => $session_data['character_states'] ?? [],
        'session_xp' => $session_data['session_xp'] ?? 0,
        'narrative_summary' => $summary,
        'npcs' => $session_data['npcs'] ?? [],
      ]);
    }
    catch (\Exception $e) {
      $this->logger->warning('[AiGmService] endSession failed: @err', ['@err' => $e->getMessage()]);
    }

    return $summary;
  }

  // =========================================================================
  // AC-006: GM Tools Integration.
  // =========================================================================

  /**
   * Resolve a balanced encounter for a narrative trigger.
   *
   * Pulls from EncounterBalancer to ensure threat level is appropriate to
   * party level. Called when the AI GM determines an encounter should begin.
   *
   * @param int $party_level
   *   Average party level.
   * @param int $party_size
   *   Number of party members.
   * @param string $difficulty
   *   One of: trivial, low, moderate, severe, extreme.
   * @param array $context
   *   Optional context: theme (string).
   *
   * @return array
   *   Encounter data from EncounterBalancer::createEncounter().
   */
  public function resolveEncounterForNarrative(
    int $party_level,
    int $party_size,
    string $difficulty = 'moderate',
    array $context = []
  ): array {
    $party_composition = array_fill(0, max(1, $party_size), ['role' => 'adventurer']);
    $theme = $context['theme'] ?? 'dungeon';
    return $this->encounterBalancer->createEncounter($party_level, $party_composition, $difficulty, $theme);
  }

  /**
   * Resolve an NPC by role for a scene from the campaign NPC gallery.
   *
   * Queries active NPC profiles for the campaign and returns the first one
   * matching the requested role. Used when the GM needs a random NPC to
   * populate a scene.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $required_role
   *   NPC role to match (e.g. 'merchant', 'guard', 'villain').
   *
   * @return array|null
   *   NPC profile array, or NULL if none found.
   */
  public function resolveNpcForScene(int $campaign_id, string $required_role): ?array {
    $profiles = $this->npcPsychologyService->getCampaignProfiles($campaign_id);
    foreach ($profiles as $profile) {
      if (($profile['role'] ?? '') === $required_role) {
        return $profile;
      }
    }
    return NULL;
  }

  // =========================================================================
  // Public narration triggers.
  // =========================================================================

  /**
   * Narrate a room entry during exploration.
   *
   * @param array $room
   *   Room data: id, name, description, lighting, terrain, entities.
   * @param array $dungeon_data
   *   Full dungeon_data payload for recent event context.
   * @param bool $first_visit
   *   Whether this is the player's first time entering the room.
   *
   * @return string|null
   *   Narration text, or NULL if disabled / AI unavailable.
   */
  public function narrateRoomEntry(array $room, array $dungeon_data, bool $first_visit = TRUE, int $campaign_id = 0): ?string {
    if (!$this->isEnabled('room_entry')) {
      return NULL;
    }

    $context = [
      'trigger' => 'room_entry',
      'room' => [
        'name' => $room['name'] ?? 'Unknown Room',
        'description' => $room['description'] ?? '',
        'lighting' => $room['lighting'] ?? 'dim',
        'terrain' => $room['terrain'] ?? 'stone',
        'entity_count' => count($room['entities'] ?? []),
        'entity_names' => $this->extractEntityNames($room['entities'] ?? []),
        'entity_details' => $this->extractEntityDetails($room['entities'] ?? []),
        'environment_tags' => $room['environment_tags'] ?? [],
        'active_effects' => $room['gameplay_state']['active_effects'] ?? [],
      ],
      'first_visit' => $first_visit,
      'recent_events' => $this->getRecentEventSummary($dungeon_data, 5),
    ];

    $system = $this->buildSystemPrompt('room_entry');
    $prompt = $this->buildPrompt($context);

    return $this->invokeNarration($system, $prompt, 'room_entry', $context, $campaign_id);
  }

  /**
   * Narrate the start of an encounter.
   *
   * @param array $encounter_context
   *   Encounter details: participants, room, trigger reason.
   * @param array $dungeon_data
   *   Full dungeon_data for context.
   *
   * @return string|null
   *   Dramatic narration for encounter start.
   */
  public function narrateEncounterStart(array $encounter_context, array $dungeon_data, int $campaign_id = 0): ?string {
    if (!$this->isEnabled('encounter_start')) {
      return NULL;
    }

    $context = [
      'trigger' => 'encounter_start',
      'participants' => $this->summarizeParticipants($encounter_context['participants'] ?? []),
      'room_name' => $encounter_context['room_name'] ?? 'Unknown',
      'trigger_reason' => $encounter_context['reason'] ?? 'Hostile creatures detected',
      'recent_events' => $this->getRecentEventSummary($dungeon_data, 5),
    ];

    return $this->invokeNarration(
      $this->buildSystemPrompt('encounter_start'),
      $this->buildPrompt($context),
      'encounter_start',
      $context,
      $campaign_id
    );
  }

  /**
   * Narrate the end of an encounter.
   *
   * @param array $encounter_result
   *   Result details: encounter_id, final_round, victory, defeated_enemies.
   * @param array $dungeon_data
   *   Full dungeon_data for context.
   *
   * @return string|null
   *   Narration wrapping up the encounter.
   */
  public function narrateEncounterEnd(array $encounter_result, array $dungeon_data, int $campaign_id = 0): ?string {
    if (!$this->isEnabled('encounter_end')) {
      return NULL;
    }

    $context = [
      'trigger' => 'encounter_end',
      'encounter_id' => $encounter_result['encounter_id'] ?? NULL,
      'final_round' => $encounter_result['final_round'] ?? 1,
      'victory' => $encounter_result['victory'] ?? TRUE,
      'recent_events' => $this->getRecentEventSummary($dungeon_data, 10),
    ];

    return $this->invokeNarration(
      $this->buildSystemPrompt('encounter_end'),
      $this->buildPrompt($context),
      'encounter_end',
      $context,
      $campaign_id
    );
  }

  /**
   * Narrate the start of a new combat round.
   *
   * @param int $round_number
   *   The round number starting.
   * @param array $game_state
   *   Current game state with initiative order.
   * @param array $dungeon_data
   *   Full dungeon_data for context.
   *
   * @return string|null
   *   Short tactical narration for the round.
   */
  public function narrateRoundStart(int $round_number, array $game_state, array $dungeon_data, int $campaign_id = 0): ?string {
    if (!$this->isEnabled('round_start')) {
      return NULL;
    }

    $context = [
      'trigger' => 'round_start',
      'round' => $round_number,
      'combatants_alive' => $this->countAliveCombatants($game_state),
      'recent_events' => $this->getRecentEventSummary($dungeon_data, 5),
    ];

    return $this->invokeNarration(
      $this->buildSystemPrompt('round_start'),
      $this->buildPrompt($context),
      'round_start',
      $context,
      $campaign_id
    );
  }

  /**
   * Narrate an entity being defeated.
   *
   * @param string $entity_name
   *   Name of the defeated entity.
   * @param string $killer_name
   *   Name of the entity that dealt the final blow.
   * @param array $dungeon_data
   *   Full dungeon_data for context.
   *
   * @return string|null
   *   Dramatic defeat narration.
   */
  public function narrateEntityDefeated(string $entity_name, string $killer_name, array $dungeon_data, int $campaign_id = 0): ?string {
    if (!$this->isEnabled('entity_defeated')) {
      return NULL;
    }

    $context = [
      'trigger' => 'entity_defeated',
      'defeated_name' => $entity_name,
      'killer_name' => $killer_name,
      'recent_events' => $this->getRecentEventSummary($dungeon_data, 5),
    ];

    return $this->invokeNarration(
      $this->buildSystemPrompt('entity_defeated'),
      $this->buildPrompt($context),
      'entity_defeated',
      $context,
      $campaign_id
    );
  }

  /**
   * Narrate a phase transition.
   *
   * @param string $from_phase
   *   Phase being exited.
   * @param string $to_phase
   *   Phase being entered.
   * @param string $reason
   *   Reason for transition.
   * @param array $dungeon_data
   *   Full dungeon_data for context.
   *
   * @return string|null
   *   Narration bridging the phase change.
   */
  public function narratePhaseTransition(string $from_phase, string $to_phase, string $reason, array $dungeon_data, int $campaign_id = 0): ?string {
    if (!$this->isEnabled('phase_transition')) {
      return NULL;
    }

    $context = [
      'trigger' => 'phase_transition',
      'from_phase' => $from_phase,
      'to_phase' => $to_phase,
      'reason' => $reason,
      'recent_events' => $this->getRecentEventSummary($dungeon_data, 5),
    ];

    return $this->invokeNarration(
      $this->buildSystemPrompt('phase_transition'),
      $this->buildPrompt($context),
      'phase_transition',
      $context,
      $campaign_id
    );
  }

  // =========================================================================
  // AI Invocation.
  // =========================================================================

  /**
   * Invoke the LLM for narration and return plain text.
   *
   * Threads session context (prior conversation history + rolling summary)
   * into the prompt when a campaign_id is provided, ensuring continuity
   * within a campaign and isolation across campaigns.
   *
   * @param string $system_prompt
   *   System prompt establishing GM persona.
   * @param string $prompt
   *   JSON context prompt.
   * @param string $operation
   *   Operation name for usage tracking.
   * @param array $context
   *   Context metadata for tracking.
   * @param int $campaign_id
   *   Campaign ID for session scoping (0 = no session).
   *
   * @return string|null
   *   Narration text, or NULL on failure.
   */
  protected function invokeNarration(string $system_prompt, string $prompt, string $operation, array $context, int $campaign_id = 0): ?string {
    if ($this->aiApiService === NULL) {
      $this->logger->info('[AiGmService] AI API service not available, using fallback for @op', [
        '@op' => $operation,
      ]);
      return $this->fallbackForTrigger($context['trigger'] ?? $operation, $context);
    }

    $this->enforceRateLimit($campaign_id);

    // Thread session context into the prompt for campaign continuity.
    if ($campaign_id > 0) {
      $session_key = $this->sessionManager->gmSessionKey($campaign_id);
      $session_context = $this->sessionManager->buildSessionContext($session_key, $campaign_id, 8);
      if ($session_context !== '') {
        $prompt = $session_context . "\n\n---\nCURRENT REQUEST:\n" . $prompt;
      }
    }

    $max_tokens = $this->getMaxTokens();

    try {
      $response = $this->aiApiService->invokeModelDirect(
        $prompt,
        'dungeoncrawler_content',
        'ai_gm_' . $operation,
        [
          'trigger' => $operation,
          'campaign_context' => 'ai_gm_narration',
          'campaign_id' => $campaign_id,
        ],
        [
          'max_tokens' => $max_tokens,
          'skip_cache' => TRUE,
          'system_prompt' => $system_prompt,
        ]
      );

      if (!empty($response['success'])) {
        $text = trim((string) ($response['response'] ?? ''));
        // Strip markdown fences if the model wraps output.
        $text = $this->stripMarkdownFences($text);
        if ($text !== '') {
          // Record this exchange in the session for future context.
          if ($campaign_id > 0) {
            $session_key = $this->sessionManager->gmSessionKey($campaign_id);
            $this->sessionManager->appendMessage($session_key, $campaign_id, 'user', $prompt, ['trigger' => $operation]);
            $this->sessionManager->appendMessage($session_key, $campaign_id, 'assistant', $text, ['trigger' => $operation]);
          }
          return $text;
        }
      }

      $this->logger->warning('[AiGmService] AI narration failed for @op: @error', [
        '@op' => $operation,
        '@error' => (string) ($response['error'] ?? 'Empty response'),
      ]);
    }
    catch (\Exception $e) {
      $this->logger->warning('[AiGmService] AI exception for @op: @error', [
        '@op' => $operation,
        '@error' => $e->getMessage(),
      ]);
    }

    // Fall back to template narration.
    return $this->fallbackForTrigger($context['trigger'] ?? $operation, $context);
  }

  // =========================================================================
  // Prompt building.
  // =========================================================================

  /**
   * Build the system prompt for the GM persona.
   *
   * @param string $trigger
   *   Narration trigger type.
   *
   * @return string
   *   System prompt text.
   */
  protected function buildSystemPrompt(string $trigger): string {
    $base = implode("\n", [
      'You are the Game Master for a Pathfinder 2e dungeon crawl.',
      'Write immersive, concise narration in second person ("You see…").',
      'Keep narration to 1-3 sentences. Be vivid but brief.',
      'Do not include dice rolls, mechanical numbers, or JSON.',
      'Do not break the fourth wall.',
      'Maintain dark fantasy tone with moments of wonder.',
      '',
      'ENTITY GROUNDING: ONLY reference NPCs, creatures, items, and objects provided in the context data. Do NOT invent new characters or objects. Use exact names from the data. If an entity is not listed, it does not exist in the scene.',
    ]);

    // Trigger-specific guidance.
    $extras = [
      'room_entry' => 'Describe the room atmosphere, notable features, and any immediate impressions. Set the scene for exploration.',
      'encounter_start' => 'Build dramatic tension. Describe the enemies appearing and the moment combat begins. Make it feel urgent.',
      'encounter_end' => 'Describe the aftermath. Convey relief, exhaustion, or triumph. Mention the state of the battlefield.',
      'round_start' => 'Write one short tactical sentence about the ebb and flow of the battle. Keep under 20 words.',
      'entity_defeated' => 'Describe the final blow dramatically. Keep it impactful and respectful.',
      'phase_transition' => 'Bridge the narrative between phases. Describe the shift in pace and mood.',
      'npc_attitude_shift' => 'Describe the NPC\'s subtle change in body language and tone as their hostility or wariness softens. Stay in character — show, don\'t tell.',
      'session_summary' => 'Write a brief narrative summary of the session in 3-5 sentences. Cover key events, enemies defeated, and notable NPCs encountered. Write in past tense from the GM\'s perspective.',
    ];

    if (isset($extras[$trigger])) {
      $base .= "\n\nFor this narration: " . $extras[$trigger];
    }

    return $base;
  }

  /**
   * Build the JSON context prompt.
   *
   * @param array $context
   *   Context data for the narration trigger.
   *
   * @return string
   *   JSON-encoded prompt.
   */
  protected function buildPrompt(array $context): string {
    return json_encode([
      'task' => 'Write narration for the described game moment.',
      'context' => $context,
      'output_format' => 'plain_text_narration_only',
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
  }

  // =========================================================================
  // Fallback narration (deterministic templates).
  // =========================================================================

  /**
   * Route to the appropriate fallback based on trigger type.
   */
  protected function fallbackForTrigger(string $trigger, array $context): ?string {
    switch ($trigger) {
      case 'room_entry':
        return $this->fallbackRoomEntry(
          $context['room'] ?? [],
          $context['first_visit'] ?? TRUE
        );

      case 'encounter_start':
        return $this->fallbackEncounterStart($context);

      case 'encounter_end':
        return $this->fallbackEncounterEnd($context);

      case 'round_start':
        return $this->fallbackRoundStart(
          $context['round'] ?? 1,
          []
        );

      case 'entity_defeated':
        return $this->fallbackEntityDefeated(
          $context['defeated_name'] ?? 'the creature',
          $context['killer_name'] ?? 'an unknown force'
        );

      case 'phase_transition':
        return $this->fallbackPhaseTransition(
          $context['from_phase'] ?? 'exploration',
          $context['to_phase'] ?? 'exploration',
          $context['reason'] ?? ''
        );

      default:
        return NULL;
    }
  }

  /**
   * Fallback narration for room entry.
   */
  protected function fallbackRoomEntry(array $room, bool $first_visit): ?string {
    $name = $room['name'] ?? 'the room';
    if ($first_visit) {
      return "You step into $name for the first time. The air shifts around you as shadows dance in the dim light.";
    }
    return "You re-enter $name. Familiar shadows greet your return.";
  }

  /**
   * Fallback narration for encounter start.
   */
  protected function fallbackEncounterStart(array $context): ?string {
    $reason = $context['trigger_reason'] ?? $context['reason'] ?? 'Hostile creatures detected';
    return "Steel rings in the darkness — $reason! Roll for initiative!";
  }

  /**
   * Fallback narration for encounter end.
   */
  protected function fallbackEncounterEnd(array $context): ?string {
    $victory = $context['victory'] ?? TRUE;
    $rounds = $context['final_round'] ?? 1;
    if ($victory) {
      return "After $rounds rounds of fierce combat, silence reclaims the chamber. Victory is yours.";
    }
    return "The battle concludes after $rounds rounds. The outcome hangs heavy in the air.";
  }

  /**
   * Fallback narration for round start.
   */
  protected function fallbackRoundStart(int $round, array $game_state): ?string {
    $templates = [
      'The clash of steel echoes as round %d begins.',
      'Round %d — the combatants reposition for another exchange.',
      'The battle rages on into round %d.',
      'Weapons ready — round %d commences.',
    ];
    $template = $templates[($round - 1) % count($templates)];
    return sprintf($template, $round);
  }

  /**
   * Fallback narration for entity defeated.
   */
  protected function fallbackEntityDefeated(string $entity_name, string $killer_name): ?string {
    return "$entity_name falls before $killer_name's decisive blow.";
  }

  /**
   * Fallback narration for phase transition.
   */
  protected function fallbackPhaseTransition(string $from, string $to, string $reason): ?string {
    $transitions = [
      'exploration_encounter' => 'The calm shatters — danger emerges!',
      'encounter_exploration' => 'The dust settles. You may explore freely once more.',
      'exploration_downtime' => 'You find a safe haven and settle in for rest.',
      'downtime_exploration' => 'Refreshed and restored, you venture forth once more.',
    ];

    $key = $from . '_' . $to;
    return $transitions[$key] ?? "The journey shifts from $from to $to. $reason";
  }

  // =========================================================================
  // Helpers.
  // =========================================================================

  /**
   * Check if AI GM narration is enabled for a given trigger.
   */
  protected function isEnabled(string $trigger = ''): bool {
    $config = $this->configFactory->get('dungeoncrawler_content.settings');

    // Master toggle.
    if (!$config->get('ai_gm_narration_enabled')) {
      return FALSE;
    }

    // Per-trigger override (default TRUE if master is on).
    if ($trigger !== '') {
      $key = 'ai_gm_trigger_' . $trigger;
      $value = $config->get($key);
      // If the per-trigger key isn't set, it defaults to enabled.
      if ($value !== NULL && !$value) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Get max tokens for narration requests.
   */
  protected function getMaxTokens(): int {
    return (int) ($this->configFactory->get('dungeoncrawler_content.settings')
      ->get('ai_gm_narration_max_tokens') ?? 300);
  }

  /**
   * Build a summary of recent events for context.
   */
  protected function getRecentEventSummary(array $dungeon_data, int $count): array {
    $events = $this->eventLogger->getRecentEvents($dungeon_data, $count);
    $summary = [];

    foreach ($events as $event) {
      $entry = [
        'type' => $event['type'] ?? 'unknown',
        'actor' => $event['actor'] ?? NULL,
      ];

      // Include narration if present (for conversation continuity).
      if (!empty($event['narration'])) {
        $entry['narration'] = $event['narration'];
      }

      // Include key data fields.
      $data = $event['data'] ?? [];
      if (isset($data['degree'])) {
        $entry['degree'] = $data['degree'];
      }
      if (isset($data['damage'])) {
        $entry['damage'] = $data['damage'];
      }
      if (isset($data['target'])) {
        $entry['target'] = $data['target'];
      }

      $summary[] = $entry;
    }

    return $summary;
  }

  /**
   * Extract entity names from a room entity list.
   */
  protected function extractEntityNames(array $entities): array {
    $names = [];
    foreach ($entities as $entity) {
      $name = $entity['name'] ?? $entity['label'] ?? $entity['entity_ref'] ?? NULL;
      if ($name) {
        $names[] = $name;
      }
    }
    return array_slice($names, 0, 10); // Cap at 10 for prompt size.
  }

  /**
   * Extract detailed entity information for richer narration context.
   *
   * Returns type, role, description snippet, and condition for each visible
   * entity so the GM can weave them into atmospheric descriptions.
   */
  protected function extractEntityDetails(array $entities): array {
    $details = [];
    foreach (array_slice($entities, 0, 15) as $entity) {
      $name = $entity['state']['metadata']['display_name']
        ?? $entity['name']
        ?? $entity['label']
        ?? 'Unknown';
      $type = $entity['type'] ?? ($entity['entity_ref']['type'] ?? 'npc');
      $description = $entity['description']
        ?? $entity['state']['metadata']['description']
        ?? '';
      $role = $entity['role'] ?? ($entity['state']['metadata']['role'] ?? '');

      // Skip hidden/undetected.
      $is_hidden = !empty($entity['hidden']) || !empty($entity['state']['hidden']);
      $is_detected = !empty($entity['detected']) || !empty($entity['state']['detected']);
      if ($is_hidden && !$is_detected) {
        continue;
      }
      // Traps only shown if detected.
      if ($type === 'trap' && !$is_detected) {
        continue;
      }

      $detail = [
        'name' => $name,
        'type' => $type,
      ];
      if ($role) {
        $detail['role'] = $role;
      }
      if ($description) {
        $detail['description'] = substr($description, 0, 120);
      }

      // HP hint for creatures.
      $stats = $entity['state']['metadata']['stats'] ?? $entity['stats'] ?? [];
      if (!empty($stats['hp_current']) && !empty($stats['hp_max'])) {
        $pct = round(($stats['hp_current'] / $stats['hp_max']) * 100);
        if ($pct >= 75) {
          $detail['condition'] = 'healthy';
        }
        elseif ($pct >= 50) {
          $detail['condition'] = 'hurt';
        }
        elseif ($pct >= 25) {
          $detail['condition'] = 'bloodied';
        }
        else {
          $detail['condition'] = 'near death';
        }
      }

      $details[] = $detail;
    }
    return $details;
  }

  /**
   * Summarize participants for prompt context.
   */
  protected function summarizeParticipants(array $participants): array {
    $summary = [];
    foreach ($participants as $p) {
      $summary[] = [
        'name' => $p['name'] ?? $p['entity_id'] ?? 'Unknown',
        'team' => $p['team'] ?? 'unknown',
        'level' => $p['level'] ?? NULL,
      ];
    }
    return array_slice($summary, 0, 12);
  }

  /**
   * Count alive combatants by team.
   */
  protected function countAliveCombatants(array $game_state): array {
    $counts = ['player' => 0, 'enemy' => 0];
    foreach ($game_state['initiative_order'] ?? [] as $c) {
      if (empty($c['is_defeated'])) {
        $team = $c['team'] ?? 'enemy';
        $counts[$team] = ($counts[$team] ?? 0) + 1;
      }
    }
    return $counts;
  }

  /**
   * Fallback narration for NPC attitude shift (AI unavailable).
   */
  protected function fallbackNpcAttitudeShift(string $npc_name, string $old_attitude, string $new_attitude): string {
    $transitions = [
      'hostile_unfriendly' => "{$npc_name} lowers their weapon slightly, suspicion still clear in their eyes.",
      'hostile_indifferent' => "{$npc_name}'s hostility fades to a cold neutrality. They seem willing to listen.",
      'hostile_friendly' => "{$npc_name} is visibly surprised — something in your words struck a chord they didn't expect.",
      'unfriendly_indifferent' => "{$npc_name} relaxes their guarded stance, no longer treating you as an immediate threat.",
      'unfriendly_friendly' => "{$npc_name} blinks, and then a small, cautious smile crosses their face.",
      'indifferent_friendly' => "{$npc_name} seems genuinely pleased by your words. They meet your gaze with warmth.",
      'indifferent_helpful' => "{$npc_name} nods, leaning forward. \"You've earned my trust. I'll do what I can.\"",
      'friendly_helpful' => "{$npc_name}'s expression brightens. \"Alright — I'll go out of my way for you.\"",
    ];
    $key = "{$old_attitude}_{$new_attitude}";
    return $transitions[$key] ?? "{$npc_name}'s attitude shifts from {$old_attitude} to {$new_attitude} in response to your words.";
  }

  /**
   * Strip markdown code fences from model output.
   */
  protected function stripMarkdownFences(string $text): string {
    // Remove ```text ... ``` or ```json ... ``` wrappers.
    if (preg_match('/^```\w*\s*\n?(.*?)\n?```$/s', $text, $m)) {
      return trim($m[1]);
    }
    return $text;
  }

}
