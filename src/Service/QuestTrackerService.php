<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\dungeoncrawler_content\Exception\StorylineSyncContractException;
use Psr\Log\LoggerInterface;

/**
 * Service for tracking quest progress.
 *
 * Handles:
 * - Starting quests for characters/parties
 * - Updating objective progress
 * - Checking completion status
 * - Advancing quest phases
 * - Logging quest events
 */
class QuestTrackerService {

  const ROOM_CHAT_MESSAGE_LIMIT = 500;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * Optional storyline orchestration service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\StorylineManagerService|null
   */
  protected ?StorylineManagerService $storylineManager;

  /**
   * Objective type service.
   */
  protected ?ObjectiveTypeService $objectiveTypeService;

  /**
   * Optional chat-session manager for narrator quest updates.
   */
  protected ?ChatSessionManager $chatSessionManager;

  /**
   * Character-state service for canonical campaign instance persistence.
   */
  protected ?CharacterStateService $characterStateService;

  /**
   * Inventory management service for reward item grants.
   */
  protected ?InventoryManagementService $inventoryManagementService;

  /**
   * Quest prerequisite validator service.
   */
  protected ?QuestValidatorService $questValidatorService;

  /**
   * Constructs a QuestTrackerService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    TimeInterface $time,
    ?StorylineManagerService $storyline_manager = NULL,
    ?ObjectiveTypeService $objective_type_service = NULL,
    ?ChatSessionManager $chat_session_manager = NULL,
    ?CharacterStateService $character_state_service = NULL,
    ?InventoryManagementService $inventory_management_service = NULL,
    ?QuestValidatorService $quest_validator_service = NULL
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
    $this->time = $time;
    $this->storylineManager = $storyline_manager;
    $this->objectiveTypeService = $objective_type_service;
    $this->chatSessionManager = $chat_session_manager;
    $this->characterStateService = $character_state_service;
    $this->inventoryManagementService = $inventory_management_service;
    $this->questValidatorService = $quest_validator_service;
    if (
      $this->chatSessionManager === NULL
      && \Drupal::hasService('dungeoncrawler_content.chat_session_manager')
    ) {
      $candidate = \Drupal::service('dungeoncrawler_content.chat_session_manager');
      $this->chatSessionManager = $candidate instanceof ChatSessionManager ? $candidate : NULL;
    }
    if (
      $this->characterStateService === NULL
      && \Drupal::hasService('dungeoncrawler_content.character_state')
    ) {
      $candidate = \Drupal::service('dungeoncrawler_content.character_state');
      $this->characterStateService = $candidate instanceof CharacterStateService ? $candidate : NULL;
    }
    if (
      $this->inventoryManagementService === NULL
      && \Drupal::hasService('dungeoncrawler_content.inventory_management')
    ) {
      $candidate = \Drupal::service('dungeoncrawler_content.inventory_management');
      $this->inventoryManagementService = $candidate instanceof InventoryManagementService ? $candidate : NULL;
    }
  }

  /**
   * Start a quest for a character or party.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $quest_id
   *   Quest ID.
   * @param int|null $character_id
   *   Character ID (NULL for party quest).
   * @param int|null $party_id
   *   Party ID (NULL for individual quest).
   *
   * @return bool
   *   TRUE if successfully started.
   */
  public function startQuest(
    int $campaign_id,
    string $quest_id,
    ?int $character_id = NULL,
    ?int $party_id = NULL
  ): bool {
    try {
      // Load quest
      $quest = $this->loadCampaignQuest($campaign_id, $quest_id);
      if (empty($quest)) {
        $this->logger->error('Quest not found: @quest in campaign @campaign', [
          '@quest' => $quest_id,
          '@campaign' => $campaign_id,
        ]);
        return FALSE;
      }

      $quest_status = strtolower(trim((string) ($quest['status'] ?? '')));
      $has_active_progress = $this->hasActiveProgress($campaign_id, $quest_id, $character_id, $party_id);
      if (
        $character_id !== NULL
        && $character_id > 0
        && !$this->isPlayerCharacterQuestScope($campaign_id, $character_id)
      ) {
        throw new \InvalidArgumentException(sprintf(
          'Quest start rejected for non-player scope (campaign=%d quest=%s character=%d).',
          $campaign_id,
          $quest_id,
          $character_id
        ));
      }
      if ($quest_status !== 'offered' && $quest_status !== 'active' && $quest_status !== 'ready_for_turn_in') {
        $this->logger->warning('Quest cannot be started from status @status: @quest', [
          '@status' => $quest_status !== '' ? $quest_status : 'unknown',
          '@quest' => $quest_id,
        ]);
        return FALSE;
      }
      if ($has_active_progress) {
        $progress_record = $this->loadProgressByScope($campaign_id, $quest_id, $character_id, $party_id);
        $active_phase = max(1, (int) ($progress_record['current_phase'] ?? 1));
        $this->materializeCollectQuestItemsForActivePhase($campaign_id, $quest, $active_phase);
        return TRUE;
      }

      if ($character_id !== NULL && $character_id > 0) {
        if ($this->questValidatorService === NULL) {
          throw new \RuntimeException('QuestValidatorService is required to enforce quest prerequisite contracts.');
        }
        $prerequisite_validation = $this->questValidatorService->validatePrerequisites($campaign_id, $quest_id, $character_id);
        if (empty($prerequisite_validation['valid'])) {
          $missing = array_values(array_filter(array_map('strval', (array) ($prerequisite_validation['missing'] ?? []))));
          $this->logger->warning('Quest prerequisites not met for quest @quest (character @character): @missing', [
            '@quest' => $quest_id,
            '@character' => $character_id,
            '@missing' => $missing !== [] ? implode('; ', $missing) : 'unknown prerequisite failure',
          ]);
          return FALSE;
        }
      }

      // Initialize objective states.
      $objectives = json_decode($quest['generated_objectives'], TRUE);
      if (!is_array($objectives)) {
        throw new \RuntimeException(sprintf(
          'Quest activation contract violation: quest "%s" has invalid generated_objectives JSON.',
          $quest_id
        ));
      }
      $this->assertQuestActivationDestinationContracts($campaign_id, $quest, $objectives);
      $objective_states = $this->initializeObjectiveStates($objectives);
      $progress_phase = 1;
      $unscoped_progress = NULL;
      $reconciled_from_unscoped_progress = FALSE;
      if ($quest_status !== 'offered') {
        $unscoped_progress = $this->loadProgressByScope($campaign_id, $quest_id, NULL, NULL);
        if (is_array($unscoped_progress)) {
          $reconciled_from_unscoped_progress = TRUE;
          $progress_phase = max(1, (int) ($unscoped_progress['current_phase'] ?? 1));
          $seed_states = json_decode((string) ($unscoped_progress['objective_states'] ?? '[]'), TRUE);
          if (is_array($seed_states) && $seed_states !== []) {
            $objective_states = $seed_states;
          }
        }
      }
      if (
        $character_id !== NULL
        && $character_id > 0
        && $party_id === NULL
        && is_array($unscoped_progress)
      ) {
        $this->promoteUnscopedProgressToCharacterScope($campaign_id, $quest_id, $character_id, $unscoped_progress);
      }

      $this->ensureProgressRecord(
        $campaign_id,
        $quest_id,
        $character_id,
        $party_id,
        $objective_states,
        $progress_phase
      );

      if ($quest_status === 'offered') {
        // Update quest status to active.
        $this->database->update('dc_campaign_quests')
          ->fields(['status' => 'active'])
          ->condition('campaign_id', $campaign_id)
          ->condition('quest_id', $quest_id)
          ->execute();
      }

      $this->materializeCollectQuestItemsForActivePhase($campaign_id, $quest, $progress_phase);

      if (!$reconciled_from_unscoped_progress) {
        // Log a canonical start event only for first activation, not for
        // scope-reconciliation promotions of existing active progress.
        $this->logQuestEvent(
          $campaign_id,
          $quest_id,
          'started',
          ['started_by' => $character_id ?? $party_id],
          'Quest started: ' . $quest['quest_name'],
          $character_id
        );

        $this->logger->info('Started quest @quest for @entity', [
          '@quest' => $quest_id,
          '@entity' => $character_id ? "character $character_id" : "party $party_id",
        ]);
      }

      if ($quest_status === 'offered') {
        $this->notifyStorylineManager($campaign_id, $quest_id, 'quest_started', $character_id, [
          'party_id' => $party_id,
          'status' => 'active',
        ]);
      }

      return TRUE;
    }
    catch (\Exception $e) {
      if ($e instanceof \InvalidArgumentException) {
        throw $e;
      }
      if ($this->isContractCriticalStorylineSyncFailure($e)) {
        throw $e;
      }
      $this->logger->error('Failed to start quest: @error', ['@error' => $e->getMessage()]);
      return FALSE;
    }
  }

  /**
   * Validate that a quest-character scope points at an active player character.
   */
  protected function isPlayerCharacterQuestScope(int $campaign_id, int $character_id): bool {
    if ($campaign_id <= 0 || $character_id <= 0) {
      return FALSE;
    }

    $matches = $this->database->select('dc_campaign_characters', 'cc')
      ->condition('campaign_id', $campaign_id)
      ->condition('id', $character_id)
      ->condition('type', 'pc')
      ->condition('role', 'player')
      ->condition('is_active', 1)
      ->countQuery()
      ->execute()
      ->fetchField();

    return (int) $matches > 0;
  }

  /**
   * Ensure active-phase collect objectives have room item instances to collect.
   */
  protected function materializeCollectQuestItemsForActivePhase(int $campaign_id, array $quest, int $active_phase = 1): void {
    if (
      $campaign_id <= 0
      || !$this->database->schema()->tableExists('dc_campaign_item_instances')
    ) {
      return;
    }

    $objective_states = json_decode((string) ($quest['generated_objectives'] ?? '[]'), TRUE);
    if (!is_array($objective_states) || $objective_states === []) {
      return;
    }

    $quest_id = trim((string) ($quest['quest_id'] ?? ''));
    if ($quest_id === '') {
      return;
    }
    $quest_source = trim((string) ($quest['source_template_id'] ?? $quest_id));
    if ($quest_source === '') {
      $quest_source = $quest_id;
    }
    $fallback_room_id = trim((string) ($quest['location_id'] ?? ''));
    $now = $this->time->getRequestTime();
    $collect_objectives = $this->collectQuestCollectObjectivesForPhase($objective_states, max(1, $active_phase));

    foreach ($collect_objectives as $objective) {
      if (!is_array($objective)) {
        continue;
      }
      $objective_id = trim((string) ($objective['objective_id'] ?? ''));
      if ($objective_id === '') {
        continue;
      }
      $room_id = trim((string) ($objective['location_id'] ?? $objective['location'] ?? $fallback_room_id));
      if ($room_id === '') {
        continue;
      }

      $target_count = max(1, (int) ($objective['target_count'] ?? $objective['completion_criteria']['target_count'] ?? 1));
      $existing = $this->countRoomQuestCollectiblesForObjective($campaign_id, $room_id, $quest_source, $objective_id);
      if ($existing >= $target_count) {
        continue;
      }

      $item_name = trim((string) ($objective['item'] ?? 'Quest Item'));
      if ($item_name === '') {
        $item_name = 'Quest Item';
      }
      $item_id = $this->buildQuestCollectibleItemId($quest_source, $objective_id, $item_name);

      for ($slot = $existing + 1; $slot <= $target_count; $slot++) {
        $item_instance_id = sprintf(
          'quest_item_%d_%s_%02d',
          $campaign_id,
          substr(hash('sha256', $quest_source . '|' . $objective_id), 0, 16),
          $slot
        );
        $item_state = [
          'id' => $item_id,
          'content_id' => $item_id,
          'name' => $item_name,
          'type' => 'quest_collectible_item',
          'description' => sprintf('Collectible for %s.', (string) ($quest['quest_name'] ?? $quest_id)),
          'quest_association' => $quest_source,
          'objective_id' => $objective_id,
          'tags' => ['collectible', 'quest_item'],
          '_spawn' => [
            'source' => 'quest_start',
            'quest_id' => $quest_id,
            'quest_source' => $quest_source,
            'objective_id' => $objective_id,
            'room_id' => $room_id,
          ],
        ];

        $this->database->merge('dc_campaign_item_instances')
          ->keys([
            'campaign_id' => $campaign_id,
            'item_instance_id' => $item_instance_id,
          ])
          ->fields([
            'item_id' => $item_id,
            'location_type' => 'room',
            'location_ref' => $room_id,
            'quantity' => 1,
            'state_data' => json_encode($item_state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated' => $now,
          ])
          ->expression('created', 'COALESCE(created, :created)', [':created' => $now])
          ->execute();
      }
    }
  }

  /**
   * Return collect objectives for one quest phase, including nested nodes.
   */
  protected function collectQuestCollectObjectivesForPhase(array $objective_states, int $phase_number): array {
    foreach ($objective_states as $phase) {
      if (!is_array($phase) || (int) ($phase['phase'] ?? 1) !== $phase_number) {
        continue;
      }
      return $this->collectQuestCollectObjectiveNodes((array) ($phase['objectives'] ?? []));
    }
    return [];
  }

  /**
   * Recursively collect "collect" objective nodes from a tree.
   */
  protected function collectQuestCollectObjectiveNodes(array $objectives): array {
    $result = [];
    foreach ($objectives as $objective) {
      if (!is_array($objective)) {
        continue;
      }
      if (strtolower((string) ($objective['type'] ?? '')) === 'collect') {
        $result[] = $objective;
      }
      foreach (['objectives', 'children', 'sub_objectives'] as $children_key) {
        if (!is_array($objective[$children_key] ?? NULL)) {
          continue;
        }
        $result = array_merge($result, $this->collectQuestCollectObjectiveNodes($objective[$children_key]));
      }
    }
    return $result;
  }

  /**
   * Count room collectibles already present for one quest objective.
   */
  protected function countRoomQuestCollectiblesForObjective(int $campaign_id, string $room_id, string $quest_source, string $objective_id): int {
    $rows = $this->database->select('dc_campaign_item_instances', 'i')
      ->fields('i', ['state_data', 'quantity'])
      ->condition('campaign_id', $campaign_id)
      ->condition('location_type', 'room')
      ->condition('location_ref', $room_id)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    $count = 0;
    foreach ($rows as $row) {
      $state = json_decode((string) ($row['state_data'] ?? '{}'), TRUE);
      $state = is_array($state) ? $state : [];
      $association = trim((string) ($state['quest_association'] ?? $state['_spawn']['quest_source'] ?? ''));
      $state_objective_id = trim((string) ($state['objective_id'] ?? $state['_spawn']['objective_id'] ?? ''));
      if ($association === $quest_source && $state_objective_id === $objective_id) {
        $count += max(1, (int) ($row['quantity'] ?? 1));
      }
    }
    return $count;
  }

  /**
   * Build a stable item id for generated collect quest items.
   */
  protected function buildQuestCollectibleItemId(string $quest_source, string $objective_id, string $item_name): string {
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $item_name) ?? ''));
    $slug = trim($slug, '_');
    if ($slug === '') {
      $slug = 'quest_item';
    }
    return sprintf('quest_collect_%s_%s_%s', substr(hash('sha256', $quest_source), 0, 8), substr(hash('sha256', $objective_id), 0, 8), $slug);
  }

  /**
   * Update objective progress.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $quest_id
   *   Quest ID.
   * @param string $objective_id
   *   Objective identifier.
   * @param int $progress
   *   New progress value (increment for counters).
   * @param int|null $character_id
   *   Character ID.
   *
   * @return array
   *   Updated quest state including completion status.
   */
  public function updateObjectiveProgress(
    int $campaign_id,
    string $quest_id,
    string $objective_id,
    int $progress,
    ?int $character_id = NULL
  ): array {
    try {
      // Load current progress
      $progress_record = $this->loadProgress($campaign_id, $quest_id, $character_id);
      if (empty($progress_record)) {
        return ['success' => FALSE, 'error' => 'Quest progress not found'];
      }

      $objective_states = json_decode($progress_record['objective_states'], TRUE);
      $current_phase = (int) $progress_record['current_phase'];
      $progress_character_id = !empty($progress_record['character_id']) ? (int) $progress_record['character_id'] : $character_id;
      $progress_party_id = !empty($progress_record['party_id']) ? (int) $progress_record['party_id'] : NULL;
      $quest = $this->loadCampaignQuest($campaign_id, $quest_id);

      ['updated' => $updated, 'objective_completed' => $objective_completed] = $this->applyObjectiveUpdate(
        $objective_states,
        $current_phase,
        $objective_id,
        $progress
      );

      if (!$updated) {
        return ['success' => FALSE, 'error' => 'Objective not found'];
      }

      // Check if phase is complete
      $phase_complete = $this->isPhaseComplete($objective_states, $current_phase);
      $quest_complete = $this->isQuestCompleted($objective_states);
      $narrator_notes = [];

      // Log if objective completed
      if ($objective_completed) {
        if (!empty($quest)) {
          $this->transferTurnInCollectiblesForCompletedObjective(
            $campaign_id,
            $quest,
            $objective_states,
            $objective_id,
            $progress_character_id
          );
        }
        $this->logQuestEvent(
          $campaign_id,
          $quest_id,
          'objective_completed',
          ['objective_id' => $objective_id],
          "Objective completed: $objective_id",
          $character_id
        );
        if (!empty($quest)) {
          $next_step = $quest_complete
            ? ''
            : $this->resolveNextObjectiveNarrationLabel($objective_states, $current_phase);
          $narrator_notes[] = $this->postQuestObjectiveCompletionNarratorNote($campaign_id, $quest, $objective_id, $progress_character_id, $next_step);
        }
      }

      // Save updated progress for the caller scope.
      $this->saveProgressRecord(
        $campaign_id,
        $quest_id,
        $progress_character_id,
        $progress_party_id,
        $objective_states,
        $current_phase
      );

      if ($phase_complete) {
        if ($quest_complete) {
          $completion_result = $this->completeQuest($campaign_id, $quest_id, $progress_character_id);
          $narrator_notes = array_values(array_merge(
            $narrator_notes,
            array_values(array_filter((array) ($completion_result['narrator_notes'] ?? []), 'is_string'))
          ));
        }
        else {
          $this->advancePhase($campaign_id, $quest_id, $progress_character_id);
        }
      }

      return [
        'success' => TRUE,
        'objective_states' => $objective_states,
        'quest_completed' => $quest_complete,
        'phase_completed' => $phase_complete,
        'objective_completed' => $objective_completed,
        'quest_status' => $quest_complete ? 'completed' : 'active',
        'narrator_notes' => array_values(array_filter($narrator_notes, static fn($note): bool => is_string($note) && trim($note) !== '')),
      ];
    }
    catch (\Exception $e) {
      if ($this->isContractCriticalStorylineSyncFailure($e)) {
        throw $e;
      }
      $this->logger->error('Failed to update objective: @error', ['@error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => $e->getMessage()];
    }
  }

  /**
   * Complete a quest.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $quest_id
   *   Quest ID.
   * @param int|null $character_id
   *   Character ID.
   * @param string $outcome
   *   Outcome: success, failure, partial, abandoned.
   *
   * @return array
   *   Quest completion data including rewards.
   */
  public function completeQuest(
    int $campaign_id,
    string $quest_id,
    ?int $character_id = NULL,
    string $outcome = 'success'
  ): array {
    if ($character_id === NULL || $character_id <= 0) {
      throw new \InvalidArgumentException('Quest completion requires a positive character_id.');
    }

    $now = $this->time->getRequestTime();
    $progress_record = $this->loadProgress($campaign_id, $quest_id, $character_id);
    $quest = $this->loadCampaignQuest($campaign_id, $quest_id);
    if (!is_array($quest)) {
      throw new \RuntimeException(sprintf(
        'Quest not found for completion (campaign_id=%d, quest_id=%s).',
        $campaign_id,
        $quest_id
      ));
    }

    $already_completed = !empty($progress_record['completed_at'])
      || strtolower((string) ($quest['status'] ?? '')) === 'completed';
    $progress_character_id = !empty($progress_record['character_id'])
      ? (int) $progress_record['character_id']
      : $character_id;
    // Validate reward contract and canonical reward target before mutating quest state.
    $rewards = $this->assertQuestRewardContract(
      json_decode((string) ($quest['generated_rewards'] ?? ''), TRUE),
      $quest_id
    );
    $reward_target = $this->resolveQuestRewardCharacterTarget($campaign_id, $character_id);
    if ($reward_target === NULL) {
      throw new \RuntimeException(sprintf(
        'Unable to resolve campaign character reward target (campaign_id=%d, character_id=%d).',
        $campaign_id,
        $character_id
      ));
    }
    $reward_claim_character_id = (int) ($reward_target['row_id'] ?? 0);
    if ($reward_claim_character_id <= 0) {
      throw new \RuntimeException(sprintf(
        'Resolved reward target is missing a valid row id (campaign_id=%d, character_id=%d).',
        $campaign_id,
        $character_id
      ));
    }
    $completion_objective_states = json_decode((string) ($progress_record['objective_states'] ?? '[]'), TRUE);
    if (
      strtolower(trim($outcome)) === 'success'
      && is_array($completion_objective_states)
    ) {
      $this->transferQuestCompletionTurnInCollectibles(
        $campaign_id,
        $quest,
        $completion_objective_states,
        $progress_character_id
      );
    }

    // Update requested scope progress record.
    $this->database->update('dc_campaign_quest_progress')
      ->fields([
        'completed_at' => $now,
        'outcome' => $outcome,
      ])
      ->condition('campaign_id', $campaign_id)
      ->condition('quest_id', $quest_id)
      ->condition('character_id', $progress_character_id)
      ->execute();

    // Update quest status
    $this->database->update('dc_campaign_quests')
      ->fields(['status' => 'completed'])
      ->condition('campaign_id', $campaign_id)
      ->condition('quest_id', $quest_id)
      ->execute();

    $rewards_applied = [];
    $narrator_notes = [];
    $reward_transaction = $this->database->startTransaction();
    $reward_claim_started = $this->beginQuestRewardGrant($campaign_id, $quest_id, $reward_claim_character_id);
    if ($reward_claim_started) {
      $rewards_applied = $this->applyQuestRewardsToTarget($reward_target, $rewards);
      $this->finalizeQuestRewardGrant($campaign_id, $quest_id, $reward_claim_character_id, $rewards_applied);
    }
    unset($reward_transaction);

    // Log completion
    $this->logQuestEvent(
      $campaign_id,
      $quest_id,
      'completed',
      ['outcome' => $outcome, 'rewards' => $rewards, 'rewards_applied' => $rewards_applied],
      "Quest completed with outcome: $outcome",
      $character_id
    );
    if (!$already_completed) {
      $narrator_notes[] = $this->postQuestCompletionNarratorNote($campaign_id, $quest, $character_id);
    }

    $this->logger->info('Completed quest @quest with outcome @outcome', [
      '@quest' => $quest_id,
      '@outcome' => $outcome,
    ]);

    $this->notifyStorylineManager($campaign_id, $quest_id, 'quest_completed', $character_id, [
      'outcome' => $outcome,
      'status' => 'completed',
    ]);

    return [
      'success' => TRUE,
      'quest_id' => $quest_id,
      'outcome' => $outcome,
      'rewards' => $rewards,
      'rewards_applied' => $rewards_applied,
      'completed_at' => $now,
      'narrator_notes' => array_values(array_filter($narrator_notes, static fn($note): bool => is_string($note) && trim($note) !== '')),
    ];
  }

  /**
   * Post a narrator note when an objective is completed.
   */
  protected function postQuestObjectiveCompletionNarratorNote(
    int $campaign_id,
    array $quest,
    string $objective_id,
    ?int $character_id,
    string $next_step = ''
  ): string {
    $quest_name = trim((string) ($quest['quest_name'] ?? $quest['name'] ?? $quest['quest_id'] ?? 'Quest'));
    $objective_label = $this->resolveQuestObjectiveNarrationLabel($quest, $objective_id);
    $objective_label = $objective_label !== '' ? $this->normalizeQuestNarratorSentence($objective_label) : '';
    $message = $objective_label !== ''
      ? sprintf('Objective completed for %s: %s', $quest_name, $objective_label)
      : sprintf('Objective completed for %s.', $quest_name);
    $next_step = trim($next_step);
    if ($next_step !== '') {
      $message .= "\nNext step: " . $this->normalizeQuestNarratorSentence($next_step);
    }

    $this->postQuestNarratorNote($campaign_id, $quest, $message, [
      'event' => 'quest_objective_completed',
      'message_class' => 'quest_objective_completion',
      'quest_id' => (string) ($quest['quest_id'] ?? ''),
      'objective_id' => $objective_id,
      'character_id' => $character_id,
      'next_step' => $next_step,
    ]);
    return $message;
  }

  /**
   * Post a narrator note when a quest is completed.
   */
  protected function postQuestCompletionNarratorNote(int $campaign_id, array $quest, ?int $character_id): string {
    $quest_name = trim((string) ($quest['quest_name'] ?? $quest['name'] ?? $quest['quest_id'] ?? 'Quest'));
    $message = sprintf('Quest completed: %s. All goals accomplished.', $quest_name);
    $this->postQuestNarratorNote($campaign_id, $quest, $message, [
      'event' => 'quest_completed',
      'message_class' => 'quest_completion',
      'quest_id' => (string) ($quest['quest_id'] ?? ''),
      'character_id' => $character_id,
    ]);
    return $message;
  }

  /**
   * Post a narrator quest note to the deterministic room chat path.
   */
  protected function postQuestNarratorNote(int $campaign_id, array $quest, string $message, array $metadata = []): void {
    if ($campaign_id <= 0) {
      throw new \InvalidArgumentException('Quest narrator note requires a valid campaign_id.');
    }
    if (trim($message) === '') {
      throw new \InvalidArgumentException('Quest narrator note requires a non-empty message.');
    }
    if (!$this->chatSessionManager) {
      throw new \RuntimeException('ChatSessionManager is required to post quest narrator notes.');
    }

    [$dungeon_id, $room_id, $room_name] = $this->resolveQuestNarrationContext($campaign_id, $quest);
    if ($dungeon_id === '' || $room_id === '') {
      throw new \RuntimeException('Quest narrator note requires resolved dungeon_id and room_id context.');
    }

    $legacy_appended = $this->appendQuestNarratorNoteToLegacyRoomChat($campaign_id, $dungeon_id, $room_id, $message, $metadata);
    if (!$legacy_appended) {
      throw new \RuntimeException('Failed to append quest narrator note to legacy room chat transcript.');
    }

    $room_session = $this->chatSessionManager->ensureRoomSession($campaign_id, $dungeon_id, $room_id, $room_name);
    $session_id = (int) ($room_session['id'] ?? 0);
    if ($session_id <= 0) {
      throw new \RuntimeException('Failed to resolve room chat session for quest narrator note.');
    }
    $this->chatSessionManager->postMessage(
      $session_id,
      $campaign_id,
      'Narrator',
      'narrator',
      '',
      $message,
      'narrative',
      'public',
      $metadata
    );
  }

  /**
   * Append narrator quest notes to the legacy room-chat transcript used by hexmap.
   */
  protected function appendQuestNarratorNoteToLegacyRoomChat(
    int $campaign_id,
    string $dungeon_id,
    string $room_id,
    string $message,
    array $metadata = []
  ): bool {
    $dungeon_row = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['id', 'dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->condition('dungeon_id', $dungeon_id)
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!is_array($dungeon_row)) {
      $dungeon_row = $this->database->select('dc_campaign_dungeons', 'd')
        ->fields('d', ['id', 'dungeon_data'])
        ->condition('campaign_id', $campaign_id)
        ->orderBy('id', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
    }

    if (!is_array($dungeon_row) || empty($dungeon_row['id'])) {
      return FALSE;
    }

    $dungeon_data = json_decode((string) ($dungeon_row['dungeon_data'] ?? '{}'), TRUE);
    if (!is_array($dungeon_data)) {
      return FALSE;
    }

    $rooms = is_array($dungeon_data['rooms'] ?? NULL) ? $dungeon_data['rooms'] : [];
    $room_index = $this->findQuestNarrationRoomIndex($rooms, $room_id);
    if ($room_index === NULL) {
      $resolved_row = $this->loadLatestQuestNarrationDungeonRow($campaign_id, $room_id);
      if (is_array($resolved_row) && !empty($resolved_row['id'])) {
        $resolved_data = json_decode((string) ($resolved_row['dungeon_data'] ?? '{}'), TRUE);
        if (is_array($resolved_data)) {
          $resolved_rooms = is_array($resolved_data['rooms'] ?? NULL) ? $resolved_data['rooms'] : [];
          $resolved_room_index = $this->findQuestNarrationRoomIndex($resolved_rooms, $room_id);
          if ($resolved_room_index !== NULL) {
            $dungeon_row = $resolved_row;
            $dungeon_data = $resolved_data;
            $rooms = $resolved_rooms;
            $room_index = $resolved_room_index;
          }
        }
      }
    }
    if ($room_index === NULL) {
      $active_room_id = trim((string) ($dungeon_data['active_room_id'] ?? ''));
      if ($active_room_id !== '') {
        $room_index = $this->findQuestNarrationRoomIndex($rooms, $active_room_id);
      }
    }

    if ($room_index === NULL || !is_array($rooms[$room_index] ?? NULL)) {
      return FALSE;
    }

    if (!isset($rooms[$room_index]['chat']) || !is_array($rooms[$room_index]['chat'])) {
      $rooms[$room_index]['chat'] = [];
    }

    $entry = [
      'speaker' => 'Narrator',
      'message' => trim($message),
      'type' => 'narrator',
      'channel' => 'room',
      'timestamp' => date('c'),
      'character_id' => NULL,
      'user_id' => 0,
      'internal_log' => FALSE,
    ];
    $message_class = trim((string) ($metadata['message_class'] ?? ''));
    if ($message_class !== '') {
      $entry['message_class'] = $message_class;
    }
    if ($metadata !== []) {
      $entry['quest_event'] = $metadata;
    }

    $rooms[$room_index]['chat'][] = $entry;
    $chat_count = count($rooms[$room_index]['chat']);
    if ($chat_count > self::ROOM_CHAT_MESSAGE_LIMIT) {
      $rooms[$room_index]['chat'] = array_slice(
        $rooms[$room_index]['chat'],
        $chat_count - self::ROOM_CHAT_MESSAGE_LIMIT
      );
    }

    $dungeon_data['rooms'] = $rooms;
    $updated = (int) $this->database->update('dc_campaign_dungeons')
      ->fields([
        'dungeon_data' => json_encode($dungeon_data),
        'updated' => $this->time->getRequestTime(),
      ])
      ->condition('id', (int) $dungeon_row['id'])
      ->condition('campaign_id', $campaign_id)
      ->execute();

    return $updated > 0;
  }

  /**
   * Find room key by runtime room id or canonical source room id.
   */
  protected function findQuestNarrationRoomIndex(array $rooms, string $room_id): int|string|null {
    if ($room_id === '') {
      return NULL;
    }

    if (isset($rooms[$room_id]) && is_array($rooms[$room_id])) {
      return $room_id;
    }

    foreach ($rooms as $key => $room) {
      if (!is_array($room)) {
        continue;
      }
      $candidate_room_id = trim((string) ($room['room_id'] ?? $room['id'] ?? ''));
      $candidate_source_room_id = trim((string) ($room['source_room_id'] ?? ''));
      if ($candidate_room_id === $room_id || ($candidate_source_room_id !== '' && $candidate_source_room_id === $room_id)) {
        return $key;
      }
    }

    return NULL;
  }

  /**
   * Resolve dungeon+room context for quest narrator notes.
   *
   * @return array{0:string,1:string,2:string}
   */
  protected function resolveQuestNarrationContext(int $campaign_id, array $quest): array {
    $room_id = trim((string) ($quest['location_id'] ?? ''));
    $room_name = '';
    if ($room_id !== '') {
      $room_row = $this->database->select('dc_campaign_rooms', 'r')
        ->fields('r', ['name'])
        ->condition('campaign_id', $campaign_id)
        ->condition('room_id', $room_id)
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if (is_array($room_row)) {
        $room_name = trim((string) ($room_row['name'] ?? ''));
      }
    }

    $dungeon_row = $this->loadLatestQuestNarrationDungeonRow($campaign_id, $room_id);
    $dungeon_id = is_array($dungeon_row) ? trim((string) ($dungeon_row['dungeon_id'] ?? '')) : '';

    if ($room_id === '' && is_array($dungeon_row)) {
      $dungeon_data = json_decode((string) ($dungeon_row['dungeon_data'] ?? '{}'), TRUE);
      if (is_array($dungeon_data)) {
        $room_id = trim((string) ($dungeon_data['active_room_id'] ?? ''));
      }
    }

    if ($room_name === '' && $room_id !== '') {
      $room_row = $this->database->select('dc_campaign_rooms', 'r')
        ->fields('r', ['name'])
        ->condition('campaign_id', $campaign_id)
        ->condition('room_id', $room_id)
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if (is_array($room_row)) {
        $room_name = trim((string) ($room_row['name'] ?? ''));
      }
    }

    return [$dungeon_id, $room_id, $room_name];
  }

  /**
   * Load the latest dungeon snapshot row for narrator quest notes.
   *
   * When a room id is provided, prefer the newest snapshot that actually
   * contains that room instead of blindly using the newest dungeon row.
   *
   * @return array<string, mixed>|null
   *   The resolved dungeon row, or NULL when none exist.
   */
  protected function loadLatestQuestNarrationDungeonRow(int $campaign_id, string $room_id = ''): ?array {
    $rows = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['id', 'dungeon_id', 'dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->orderBy('updated', 'DESC')
      ->orderBy('id', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    if ($rows === []) {
      return NULL;
    }

    $room_id = trim($room_id);
    if ($room_id === '') {
      return $rows[0];
    }

    foreach ($rows as $row) {
      $dungeon_data = json_decode((string) ($row['dungeon_data'] ?? '{}'), TRUE);
      if (!is_array($dungeon_data)) {
        continue;
      }
      $rooms = is_array($dungeon_data['rooms'] ?? NULL) ? $dungeon_data['rooms'] : [];
      if ($this->findQuestNarrationRoomIndex($rooms, $room_id) !== NULL) {
        return $row;
      }
    }

    return $rows[0];
  }

  /**
   * Resolve a display label for a quest objective.
   */
  protected function resolveQuestObjectiveNarrationLabel(array $quest, string $objective_id): string {
    $objective_id = trim($objective_id);
    if ($objective_id === '') {
      return '';
    }

    $phases = json_decode((string) ($quest['objective_states'] ?? $quest['generated_objectives'] ?? '[]'), TRUE);
    if (!is_array($phases)) {
      return $objective_id;
    }

    foreach ($phases as $phase) {
      if (!is_array($phase)) {
        continue;
      }
      $label = $this->findObjectiveNarrationLabelInNodes((array) ($phase['objectives'] ?? []), $objective_id);
      if ($label !== '') {
        return $label;
      }
    }

    return $objective_id;
  }

  /**
   * Resolve the next objective narration label for a quest progress snapshot.
   */
  protected function resolveNextObjectiveNarrationLabel(array $objective_states, int $current_phase): string {
    if (!is_array($objective_states) || $objective_states === []) {
      return '';
    }

    $phase_rows = array_values(array_filter($objective_states, static fn($row): bool => is_array($row)));
    usort($phase_rows, static function (array $left, array $right): int {
      return ((int) ($left['phase'] ?? 0)) <=> ((int) ($right['phase'] ?? 0));
    });

    $min_phase = max(1, $current_phase);
    foreach ($phase_rows as $phase_row) {
      $phase = (int) ($phase_row['phase'] ?? 0);
      if ($phase < $min_phase) {
        continue;
      }

      $objectives = $this->collectObjectivesForDisplay((array) ($phase_row['objectives'] ?? []), TRUE);
      foreach ($objectives as $objective) {
        if (!is_array($objective)) {
          continue;
        }
        $label = $this->resolveObjectiveNarrationLabelFromState($objective);
        if ($label !== '') {
          return $label;
        }
      }
    }

    return '';
  }

  /**
   * Resolve a readable narration label for an objective state node.
   */
  protected function resolveObjectiveNarrationLabelFromState(array $objective): string {
    $description = trim((string) ($objective['description'] ?? ''));
    if ($description === '') {
      return trim((string) ($objective['objective_id'] ?? ''));
    }

    foreach ([
      'item' => 'item_label',
      'target' => 'target_label',
      'location' => 'location_label',
      'destination' => 'destination_label',
    ] as $field => $label_field) {
      $value = trim((string) ($objective[$field] ?? ''));
      if ($value === '') {
        continue;
      }
      $label = trim((string) ($objective[$label_field] ?? $this->humanizeQuestReference($value)));
      if ($label !== '' && $label !== $value) {
        $description = str_replace($value, $label, $description);
      }
    }

    return trim($description);
  }

  /**
   * Ensure narrator note fragments end with terminal punctuation.
   */
  protected function normalizeQuestNarratorSentence(string $text): string {
    $trimmed = trim($text);
    if ($trimmed === '') {
      return '';
    }
    if (preg_match('/[.!?]$/', $trimmed)) {
      return $trimmed;
    }
    return $trimmed . '.';
  }

  /**
   * Recursively find the best narration label for an objective.
   */
  protected function findObjectiveNarrationLabelInNodes(array $nodes, string $objective_id): string {
    foreach ($nodes as $node) {
      if (!is_array($node)) {
        continue;
      }
      if ((string) ($node['objective_id'] ?? '') === $objective_id) {
        return trim((string) ($node['description'] ?? $objective_id));
      }
      foreach (['objectives', 'children', 'sub_objectives'] as $children_key) {
        $label = $this->findObjectiveNarrationLabelInNodes((array) ($node[$children_key] ?? []), $objective_id);
        if ($label !== '') {
          return $label;
        }
      }
    }
    return '';
  }

  /**
   * Apply quest rewards to the owning character exactly once on completion.
   *
   * @param array<string, mixed> $rewards
   *   Normalized generated reward payload.
   *
   * @return array<string, mixed>
   *   Reward amounts that were persisted.
   */
  protected function applyQuestRewards(int $campaign_id, ?int $character_id, array $rewards): array {
    if ($campaign_id <= 0 || $character_id === NULL || $character_id <= 0) {
      throw new \InvalidArgumentException('Quest reward application requires a valid campaign_id and character_id.');
    }

    $reward_target = $this->resolveQuestRewardCharacterTarget($campaign_id, $character_id);
    if ($reward_target === NULL) {
      throw new \RuntimeException(sprintf(
        'Unable to resolve campaign character reward target (campaign_id=%d, character_id=%d).',
        $campaign_id,
        $character_id
      ));
    }

    return $this->applyQuestRewardsToTarget($reward_target, $rewards);
  }

  /**
   * Apply canonical quest rewards to a resolved campaign-runtime target row.
   *
   * @param array<string, mixed> $reward_target
   *   Canonical runtime target metadata.
   * @param array<string, mixed> $rewards
   *   Canonical quest rewards payload.
   *
   * @return array<string, mixed>
   *   Reward amounts that were persisted.
   */
  protected function applyQuestRewardsToTarget(array $reward_target, array $rewards): array {
    $row_id = (int) ($reward_target['row_id'] ?? 0);
    if ($row_id <= 0) {
      throw new \RuntimeException('Quest reward target is missing canonical row_id.');
    }

    $applied = [];

    $xp = (int) $rewards['xp'];
    if ($xp > 0) {
      $this->applyQuestXpReward($reward_target, $xp);
      $applied['xp'] = $xp;
    }

    $gold = (int) $rewards['gold'];
    if ($gold > 0) {
      $this->applyQuestGoldReward($reward_target, $gold);
      $applied['gold'] = $gold;
    }

    $normalized_items = $this->normalizeQuestRewardItems($rewards['items'] ?? []);
    if ($normalized_items !== []) {
      $applied_items = $this->applyQuestItemRewards($reward_target, $normalized_items);
      if ($applied_items !== []) {
        $applied['items'] = $applied_items;
      }
    }

    return $applied;
  }

  /**
   * Resolve the authoritative campaign character row that receives quest rewards.
   *
   * @return array<string, mixed>|null
   *   Reward target metadata (row id + campaign scope), or NULL when missing.
   */
  protected function resolveQuestRewardCharacterTarget(int $campaign_id, int $character_id): ?array {
    $runtime_row = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id', 'campaign_id', 'instance_id', 'type'])
      ->condition('campaign_id', $campaign_id)
      ->condition('type', 'pc')
      ->condition('id', $character_id);

    $row = $runtime_row
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if ($row) {
      return [
        'row_id' => (int) ($row['id'] ?? 0),
        'campaign_id' => (int) ($row['campaign_id'] ?? $campaign_id),
        'instance_id' => (string) ($row['instance_id'] ?? ''),
      ];
    }

    $source_rows = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id', 'campaign_id', 'instance_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('type', 'pc')
      ->condition('character_id', $character_id)
      ->range(0, 2)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    if (!is_array($source_rows) || $source_rows === []) {
      return NULL;
    }
    if (count($source_rows) !== 1) {
      throw new \RuntimeException(sprintf(
        'Ambiguous campaign character reward target (campaign_id=%d, character_id=%d, matches=%d).',
        $campaign_id,
        $character_id,
        count($source_rows)
      ));
    }

    $source = $source_rows[0];
    return [
      'row_id' => (int) ($source['id'] ?? 0),
      'campaign_id' => (int) ($source['campaign_id'] ?? $campaign_id),
      'instance_id' => (string) ($source['instance_id'] ?? ''),
    ];
  }

  /**
   * Persist quest XP rewards into canonical campaign character state.
   *
   * @param array<string, mixed> $target
   *   Reward target metadata.
   */
  protected function applyQuestXpReward(array $target, int $xp): void {
    if ($xp <= 0) {
      return;
    }
    if (!$this->characterStateService) {
      throw new \RuntimeException('CharacterStateService is required to apply XP quest rewards.');
    }

    $this->characterStateService->gainExperience(
      (string) ($target['row_id'] ?? 0),
      $xp,
      (int) ($target['campaign_id'] ?? 0),
      (string) ($target['instance_id'] ?? '')
    );
  }

  /**
   * Persist quest gold rewards into canonical campaign character state.
   *
   * @param array<string, mixed> $target
   *   Reward target metadata.
   */
  protected function applyQuestGoldReward(array $target, int $gold): void {
    if ($gold <= 0) {
      return;
    }
    if (!$this->characterStateService) {
      throw new \RuntimeException('CharacterStateService is required to apply gold quest rewards.');
    }

    $character_id = (string) ($target['row_id'] ?? 0);
    $campaign_id = (int) ($target['campaign_id'] ?? 0);
    $instance_id = (string) ($target['instance_id'] ?? '');

    $state = $this->characterStateService->getState($character_id, $campaign_id, $instance_id);
    $inventory = is_array($state['inventory'] ?? NULL) ? $state['inventory'] : [];
    $raw_currency = is_array($inventory['currency'] ?? NULL)
      ? $inventory['currency']
      : (is_array($state['currency'] ?? NULL) ? $state['currency'] : []);

    $currency = CharacterManager::normalizeCurrencyDenominations(
      $raw_currency,
      isset($state['gold']) ? (float) $state['gold'] : NULL
    );
    $currency['gp'] = (int) ($currency['gp'] ?? 0) + $gold;

    $state['inventory'] = $inventory;
    $state['inventory']['currency'] = $currency;
    $state['currency'] = $currency;
    $state['gold'] = (int) ($currency['gp'] ?? 0);
    if (!isset($state['basicInfo']) || !is_array($state['basicInfo'])) {
      $state['basicInfo'] = [];
    }
    $state['basicInfo']['gold'] = (int) ($currency['gp'] ?? 0);

    $this->characterStateService->setState($character_id, $state, NULL, $campaign_id, $instance_id);
  }

  /**
   * Normalize generated item rewards into inventory-grantable tuples.
   *
   * @param mixed $raw_items
   *   Reward item contract payload.
   *
   * @return array<int, array<string, mixed>>
   *   Normalized entries with item_id + quantity + source payload.
   */
  protected function normalizeQuestRewardItems(mixed $raw_items): array {
    if (!is_array($raw_items) || !array_is_list($raw_items)) {
      throw new \RuntimeException('Quest reward contract violation: items must be a list.');
    }

    $items = $raw_items;
    $normalized = [];
    foreach ($items as $item) {
      if (!is_array($item)) {
        throw new \RuntimeException('Quest reward contract violation: every reward item must be an object.');
      }

      $item_id = trim((string) ($item['item_id'] ?? ''));
      if ($item_id === '') {
        throw new \RuntimeException('Quest reward contract violation: reward item is missing item_id.');
      }

      $quantity = $item['quantity'] ?? 1;
      if (!is_int($quantity) || $quantity < 1) {
        throw new \RuntimeException(sprintf(
          'Quest reward contract violation: item %s has invalid quantity.',
          $item_id
        ));
      }

      $normalized[] = [
        'item_id' => $item_id,
        'quantity' => $quantity,
        'source' => $item,
      ];
    }

    return $normalized;
  }

  /**
   * Validate and normalize the canonical quest reward contract shape.
   *
   * @return array{xp:int,gold:int,items:array<int,array<string,mixed>>}
   */
  protected function assertQuestRewardContract(mixed $decoded_rewards, string $quest_id): array {
    if (!is_array($decoded_rewards)) {
      throw new \RuntimeException(sprintf(
        'Quest reward contract violation for %s: generated_rewards must decode to an object.',
        $quest_id
      ));
    }

    foreach (['xp', 'gold', 'items'] as $required_key) {
      if (!array_key_exists($required_key, $decoded_rewards)) {
        throw new \RuntimeException(sprintf(
          'Quest reward contract violation for %s: missing required key "%s".',
          $quest_id,
          $required_key
        ));
      }
    }

    $xp = $decoded_rewards['xp'];
    $gold = $decoded_rewards['gold'];
    if (!is_int($xp) || $xp < 0) {
      throw new \RuntimeException(sprintf(
        'Quest reward contract violation for %s: xp must be a non-negative integer.',
        $quest_id
      ));
    }
    if (!is_int($gold) || $gold < 0) {
      throw new \RuntimeException(sprintf(
        'Quest reward contract violation for %s: gold must be a non-negative integer.',
        $quest_id
      ));
    }

    $items = $this->normalizeQuestRewardItems($decoded_rewards['items']);

    return [
      'xp' => $xp,
      'gold' => $gold,
      'items' => $items,
    ];
  }

  /**
   * Persist normalized quest item rewards into campaign inventory state.
   *
   * @param array<string, mixed> $target
   *   Reward target metadata.
   * @param array<int, array<string, mixed>> $items
   *   Normalized reward items.
   *
   * @return array<int, array<string, mixed>>
   *   Applied item grants.
   */
  protected function applyQuestItemRewards(array $target, array $items): array {
    if ($items === []) {
      return [];
    }
    if (!$this->inventoryManagementService) {
      throw new \RuntimeException('InventoryManagementService is required to apply item quest rewards.');
    }

    $granted = [];
    $owner_id = (string) ($target['row_id'] ?? 0);
    $campaign_id = (int) ($target['campaign_id'] ?? 0);

    foreach ($items as $item_reward) {
      if (!is_array($item_reward)) {
        continue;
      }

      $item_id = trim((string) ($item_reward['item_id'] ?? ''));
      if ($item_id === '') {
        continue;
      }
      $quantity = max(1, (int) ($item_reward['quantity'] ?? 1));
      $source = is_array($item_reward['source'] ?? NULL) ? $item_reward['source'] : [];

      $item_name = trim((string) ($source['name'] ?? $source['item_name'] ?? ''));
      if ($item_name === '') {
        $item_name = $this->humanizeQuestReference($item_id);
      }
      $item_type = trim((string) ($source['item_type'] ?? $source['type'] ?? 'treasure'));
      if ($item_type === '') {
        $item_type = 'treasure';
      }

      $inventory_item = [
        'id' => $item_id,
        'name' => $item_name !== '' ? $item_name : 'Quest Reward',
        'item_type' => $item_type,
        'type' => $item_type,
      ];
      if (!empty($source['description'])) {
        $inventory_item['description'] = (string) $source['description'];
      }

      $this->inventoryManagementService->addItemToInventory(
        $owner_id,
        'character',
        $inventory_item,
        'carried',
        $quantity,
        $campaign_id
      );

      $granted[] = [
        'item_id' => $item_id,
        'quantity' => $quantity,
      ];
    }

    return $granted;
  }

  /**
   * Start an atomic quest reward claim for this quest+character scope.
   *
   * @return bool
   *   TRUE when this request owns reward application, FALSE when already claimed.
   */
  protected function beginQuestRewardGrant(int $campaign_id, string $quest_id, ?int $character_id): bool {
    if ($campaign_id <= 0 || trim($quest_id) === '' || $character_id === NULL || $character_id <= 0) {
      throw new \InvalidArgumentException('Reward claim start requires campaign_id, quest_id, and character_id.');
    }

    try {
      $this->database->insert('dc_campaign_quest_rewards_claimed')
        ->fields([
          'campaign_id' => $campaign_id,
          'quest_id' => $quest_id,
          'character_id' => $character_id,
          'reward_data' => json_encode(['status' => 'pending'], JSON_UNESCAPED_UNICODE),
          'claimed_at' => $this->time->getRequestTime(),
        ])
        ->execute();
      return TRUE;
    }
    catch (IntegrityConstraintViolationException) {
      return FALSE;
    }
  }

  /**
   * Finalize an active quest reward claim record.
   *
   * @param array<string, mixed> $reward_data
   *   Applied reward payload.
   */
  protected function finalizeQuestRewardGrant(int $campaign_id, string $quest_id, ?int $character_id, array $reward_data): void {
    if ($campaign_id <= 0 || trim($quest_id) === '' || $character_id === NULL || $character_id <= 0) {
      throw new \InvalidArgumentException('Reward claim finalize requires campaign_id, quest_id, and character_id.');
    }

    $updated = (int) $this->database->update('dc_campaign_quest_rewards_claimed')
      ->fields([
        'reward_data' => json_encode($reward_data, JSON_UNESCAPED_UNICODE),
        'claimed_at' => $this->time->getRequestTime(),
      ])
      ->condition('campaign_id', $campaign_id)
      ->condition('quest_id', $quest_id)
      ->condition('character_id', $character_id)
      ->execute();
    if ($updated !== 1) {
      throw new \RuntimeException(sprintf(
        'Failed to finalize reward claim row (campaign_id=%d, quest_id=%s, character_id=%d).',
        $campaign_id,
        $quest_id,
        $character_id
      ));
    }
  }

  /**
   * Get active quests for a character.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param int $character_id
   *   Character ID.
   *
   * @return array
   *   Array of active quests with progress.
   */
  public function getActiveQuests(int $campaign_id, int $character_id): array {
    $active = array_values(array_filter(
      $this->loadCharacterQuestRows($campaign_id, $character_id),
      static function (array $quest): bool {
        return in_array(strtolower((string) ($quest['status'] ?? '')), ['active', 'ready_for_turn_in'], TRUE)
          && empty($quest['completed_at']);
      }
    ));

    $reconciled = FALSE;
    foreach ($active as $quest) {
      $quest_id = trim((string) ($quest['quest_id'] ?? ''));
      if ($quest_id === '') {
        continue;
      }
      $progress_character_id = isset($quest['character_id']) ? (int) $quest['character_id'] : 0;
      if ($progress_character_id > 0) {
        continue;
      }
      if ($this->startQuest($campaign_id, $quest_id, $character_id)) {
        $reconciled = TRUE;
      }
    }

    if ($reconciled) {
      $active = array_values(array_filter(
        $this->loadCharacterQuestRows($campaign_id, $character_id),
        static function (array $quest): bool {
          return in_array(strtolower((string) ($quest['status'] ?? '')), ['active', 'ready_for_turn_in'], TRUE)
            && empty($quest['completed_at']);
        }
      ));
    }

    $hydrated = [];
    foreach ($active as $quest) {
      if (!is_array($quest)) {
        continue;
      }

      $quest = $this->normalizeQuestPromptRow($quest);
      $current_phase = max(1, (int) ($quest['current_phase'] ?? 1));
      $quest['current_objectives'] = $this->getObjectivesForPhase($quest, $current_phase, TRUE);
      $quest['next_objectives'] = $this->getObjectivesForPhase($quest, $current_phase + 1, FALSE);
      $hydrated[] = $quest;
    }

    return $hydrated;
  }

  /**
   * Get quest offers at a location.
   */
  public function getOfferQuests(int $campaign_id, string $location_id, int $character_id): array {
    return array_values(array_filter(
      $this->getAvailableQuests($campaign_id, $location_id, $character_id),
      static function (array $quest): bool {
        return strtolower((string) ($quest['status'] ?? '')) === 'offered';
      }
    ));
  }

  /**
   * Get quest leads at a location.
   */
  public function getLeadQuests(int $campaign_id, string $location_id, int $character_id): array {
    return array_values(array_filter(
      $this->getAvailableQuests($campaign_id, $location_id, $character_id),
      static function (array $quest): bool {
        return strtolower((string) ($quest['status'] ?? '')) === 'lead';
      }
    ));
  }

  /**
   * Get campaign-scoped quest tracking records.
   *
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return array
   *   Campaign-level quest tracking rows.
   */
  public function getCampaignQuestTracking(int $campaign_id): array {
    return $this->loadCampaignQuestRows($campaign_id);
  }

  /**
   * Get character-scoped quest tracking records.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param int $character_id
   *   Character ID.
   *
   * @return array
   *   Character-level quest tracking rows.
   */
  public function getCharacterQuestTracking(int $campaign_id, int $character_id): array {
    return $this->loadCharacterQuestRows($campaign_id, $character_id);
  }

  /**
   * Load quest rows visible to a character, overlaying the best progress scope.
   *
   * Character journals need more than rows that already have direct
   * character-owned progress: they must also surface campaign-scoped active
   * quests and newly available leads that were introduced through dialogue.
   *
   * @return array<int, array<string, mixed>>
   *   Quest rows with progress fields merged in when available.
   */
  protected function loadCharacterQuestRows(int $campaign_id, int $character_id): array {
    $tracking_ids = $this->resolveQuestTrackingCharacterIds($campaign_id, $character_id);
    if ($campaign_id <= 0 || $tracking_ids === []) {
      return [];
    }

    $quests = $this->database->select('dc_campaign_quests', 'q')
      ->fields('q')
      ->condition('q.campaign_id', $campaign_id)
      ->orderBy('q.created_at', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    if ($quests === []) {
      return [];
    }

    $progress_rows = $this->database->select('dc_campaign_quest_progress', 'qp')
      ->fields('qp')
      ->condition('qp.campaign_id', $campaign_id)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    $progress_by_quest = [];
    foreach ($progress_rows as $row) {
      $quest_id = trim((string) ($row['quest_id'] ?? ''));
      if ($quest_id === '') {
        continue;
      }

      $scope_rank = $this->rankQuestProgressScope($row, $tracking_ids);
      if ($scope_rank === NULL) {
        continue;
      }

      if (!isset($progress_by_quest[$quest_id])) {
        $progress_by_quest[$quest_id] = ['rank' => $scope_rank, 'row' => $row];
        continue;
      }

      $existing = $progress_by_quest[$quest_id];
      $existing_rank = (int) ($existing['rank'] ?? PHP_INT_MAX);
      $existing_updated = (int) (($existing['row']['last_updated'] ?? 0));
      $candidate_updated = (int) ($row['last_updated'] ?? 0);
      if ($scope_rank < $existing_rank || ($scope_rank === $existing_rank && $candidate_updated > $existing_updated)) {
        $progress_by_quest[$quest_id] = ['rank' => $scope_rank, 'row' => $row];
      }
    }

    $merged_rows = [];
    foreach ($quests as $quest) {
      $quest_id = trim((string) ($quest['quest_id'] ?? ''));
      if ($quest_id === '') {
        continue;
      }

      $merged = $quest;
      $progress = $progress_by_quest[$quest_id]['row'] ?? NULL;
      if (is_array($progress)) {
        foreach (['character_id', 'party_id', 'objective_states', 'current_phase', 'started_at', 'last_updated', 'completed_at', 'outcome'] as $field) {
          $merged[$field] = $progress[$field] ?? NULL;
        }
      }
      $merged_rows[] = $merged;
    }

    return $this->dedupeOpenQuestRowsByTemplate($merged_rows);
  }

  /**
   * Load quest rows for the campaign journal, overlaying the best progress row.
   *
   * @return array<int, array<string, mixed>>
   *   Quest rows with progress fields merged in when available.
   */
  protected function loadCampaignQuestRows(int $campaign_id): array {
    if ($campaign_id <= 0) {
      return [];
    }

    $quests = $this->database->select('dc_campaign_quests', 'q')
      ->fields('q')
      ->condition('q.campaign_id', $campaign_id)
      ->orderBy('q.created_at', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    if ($quests === []) {
      return [];
    }

    $progress_rows = $this->database->select('dc_campaign_quest_progress', 'qp')
      ->fields('qp')
      ->condition('qp.campaign_id', $campaign_id)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    $progress_by_quest = [];
    foreach ($progress_rows as $row) {
      $quest_id = trim((string) ($row['quest_id'] ?? ''));
      if ($quest_id === '') {
        continue;
      }

      $scope_rank = $this->rankCampaignProgressScope($row);
      if (!isset($progress_by_quest[$quest_id])) {
        $progress_by_quest[$quest_id] = ['rank' => $scope_rank, 'row' => $row];
        continue;
      }

      $existing = $progress_by_quest[$quest_id];
      $existing_rank = (int) ($existing['rank'] ?? PHP_INT_MAX);
      $existing_updated = (int) ($existing['row']['last_updated'] ?? 0);
      $candidate_updated = (int) ($row['last_updated'] ?? 0);
      if ($scope_rank < $existing_rank || ($scope_rank === $existing_rank && $candidate_updated > $existing_updated)) {
        $progress_by_quest[$quest_id] = ['rank' => $scope_rank, 'row' => $row];
      }
    }

    $merged_rows = [];
    foreach ($quests as $quest) {
      $quest_id = trim((string) ($quest['quest_id'] ?? ''));
      if ($quest_id === '') {
        continue;
      }

      $merged = $quest;
      $progress = $progress_by_quest[$quest_id]['row'] ?? NULL;
      if (is_array($progress)) {
        foreach (['character_id', 'party_id', 'objective_states', 'current_phase', 'started_at', 'last_updated', 'completed_at', 'outcome'] as $field) {
          $merged[$field] = $progress[$field] ?? NULL;
        }
      }
      $merged_rows[] = $merged;
    }

    return $this->dedupeOpenQuestRowsByTemplate($merged_rows);
  }

  /**
   * Deduplicate open quest rows by source template to prevent split progression.
   *
   * @param array<int, array<string, mixed>> $rows
   *   Quest rows with merged progress.
   *
   * @return array<int, array<string, mixed>>
   *   Rows with at most one open row per source_template_id.
   */
  protected function dedupeOpenQuestRowsByTemplate(array $rows): array {
    if ($rows === []) {
      return [];
    }

    $open_status_rank = [
      'active' => 0,
      'ready_for_turn_in' => 1,
      'offered' => 2,
      'lead' => 3,
      'available' => 4,
    ];

    $deduped = [];
    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }

      $status = strtolower(trim((string) ($row['status'] ?? '')));
      $template_id = trim((string) ($row['source_template_id'] ?? ''));
      $quest_id = trim((string) ($row['quest_id'] ?? ''));

      if ($quest_id === '') {
        continue;
      }

      $is_open = isset($open_status_rank[$status]);
      if ($template_id === '' || !$is_open) {
        $deduped[] = $row;
        continue;
      }

      $key = 'template:' . $template_id;
      if (!isset($deduped[$key])) {
        $deduped[$key] = $row;
        continue;
      }

      $existing = $deduped[$key];
      $existing_status = strtolower(trim((string) ($existing['status'] ?? '')));
      $existing_rank = $open_status_rank[$existing_status] ?? 99;
      $candidate_rank = $open_status_rank[$status] ?? 99;
      $existing_phase = max(1, (int) ($existing['current_phase'] ?? 1));
      $candidate_phase = max(1, (int) ($row['current_phase'] ?? 1));
      $existing_updated = (int) ($existing['last_updated'] ?? $existing['updated_at'] ?? 0);
      $candidate_updated = (int) ($row['last_updated'] ?? $row['updated_at'] ?? 0);
      $existing_created = (int) ($existing['created_at'] ?? 0);
      $candidate_created = (int) ($row['created_at'] ?? 0);
      $existing_id = (int) ($existing['id'] ?? 0);
      $candidate_id = (int) ($row['id'] ?? 0);

      $replace = FALSE;
      if ($candidate_rank < $existing_rank) {
        $replace = TRUE;
      }
      elseif ($candidate_rank === $existing_rank && $candidate_phase > $existing_phase) {
        $replace = TRUE;
      }
      elseif ($candidate_rank === $existing_rank && $candidate_phase === $existing_phase && $candidate_updated > $existing_updated) {
        $replace = TRUE;
      }
      elseif ($candidate_rank === $existing_rank && $candidate_phase === $existing_phase && $candidate_updated === $existing_updated && $candidate_created > $existing_created) {
        $replace = TRUE;
      }
      elseif ($candidate_rank === $existing_rank && $candidate_phase === $existing_phase && $candidate_updated === $existing_updated && $candidate_created === $existing_created && $candidate_id > $existing_id) {
        $replace = TRUE;
      }

      if ($replace) {
        $deduped[$key] = $row;
      }
    }

    $normalized = array_values(array_filter($deduped, 'is_array'));
    usort($normalized, static function (array $a, array $b): int {
      $a_created = (int) ($a['created_at'] ?? 0);
      $b_created = (int) ($b['created_at'] ?? 0);
      if ($a_created !== $b_created) {
        return $b_created <=> $a_created;
      }
      return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
    });

    return $normalized;
  }

  /**
   * Rank whether a progress row applies to the current character journal.
   *
   * Lower numbers are better. NULL means the row should not be considered.
   */
  protected function rankQuestProgressScope(array $row, array $tracking_ids): ?int {
    $character_id = isset($row['character_id']) ? (int) $row['character_id'] : 0;
    $party_id = isset($row['party_id']) ? (int) $row['party_id'] : 0;

    if ($character_id > 0) {
      $position = array_search($character_id, $tracking_ids, TRUE);
      return $position === FALSE ? NULL : (int) $position;
    }

    if ($party_id > 0) {
      return NULL;
    }

    return 1000;
  }

  /**
   * Rank quest progress rows for campaign-level journal views.
   */
  protected function rankCampaignProgressScope(array $row): int {
    $character_id = isset($row['character_id']) ? (int) $row['character_id'] : 0;
    $party_id = isset($row['party_id']) ? (int) $row['party_id'] : 0;
    if ($party_id > 0) {
      return 0;
    }
    if ($character_id > 0) {
      return 1;
    }
    return 2;
  }

  /**
   * Build a concise quest-context block for GM prompts when quests are referenced.
   */
  public function buildRelevantQuestPromptContext(int $campaign_id, ?int $character_id, string $player_text, int $max_quests = 3): string {
    $normalized_text = $this->normalizeQuestSearchText($player_text);
    if ($campaign_id <= 0 || $normalized_text === '') {
      return '';
    }

    $quest_reference_detected = $this->hasQuestReferenceCue($normalized_text);
    $status_review_detected = $this->hasQuestStatusReviewCue($normalized_text);
    $include_completed_context = $quest_reference_detected && $status_review_detected;

    if ($character_id !== NULL && $character_id > 0) {
      $quests = $include_completed_context
        ? $this->getCharacterQuestTracking($campaign_id, $character_id)
        : $this->getActiveQuests($campaign_id, $character_id);
    }
    else {
      $quests = array_values(array_filter($this->getCampaignQuestTracking($campaign_id), function (array $quest) use ($include_completed_context): bool {
        if ($include_completed_context) {
          return TRUE;
        }
        return empty($quest['completed_at']) && (($quest['status'] ?? '') === 'active');
      }));
    }

    if ($quests === []) {
      return '';
    }

    $quest_rows = [];
    foreach ($quests as $quest) {
      if (!is_array($quest)) {
        continue;
      }

      $quest = $this->normalizeQuestPromptRow($quest);
      $current_objectives = $this->getObjectivesForPhase($quest, (int) ($quest['current_phase'] ?? 1), !$include_completed_context);
      if ($current_objectives === []) {
        continue;
      }

      $quest['current_objectives'] = $current_objectives;
      $quest['next_objectives'] = empty($quest['completed_at'])
        ? $this->getObjectivesForPhase($quest, ((int) ($quest['current_phase'] ?? 1)) + 1, FALSE)
        : [];
      $quest['match_score'] = $this->scoreQuestAgainstPrompt($normalized_text, $quest);
      $quest_rows[] = $quest;
    }

    if ($quest_rows === []) {
      return '';
    }

    $matched = array_values(array_filter($quest_rows, static fn(array $quest): bool => (int) ($quest['match_score'] ?? 0) >= 4));

    if ($matched === [] && !$quest_reference_detected) {
      return '';
    }

    usort($quest_rows, static function (array $a, array $b): int {
      $score_compare = ((int) ($b['match_score'] ?? 0)) <=> ((int) ($a['match_score'] ?? 0));
      if ($score_compare !== 0) {
        return $score_compare;
      }

      return ((int) ($b['last_updated'] ?? 0)) <=> ((int) ($a['last_updated'] ?? 0));
    });

    $selected = array_slice($matched !== [] ? $quest_rows : $quest_rows, 0, max(1, $max_quests));
    if ($selected === []) {
      return '';
    }

    $lines = [
      '=== RELEVANT QUEST CONTEXT ===',
      'The player referenced quest progress, a quest item, or a quest target. Use the quest ids and objective ids below if you need to discuss or resolve quest work.',
    ];

    foreach ($selected as $quest) {
      $quest_id = (string) ($quest['quest_id'] ?? 'unknown_quest');
      $quest_name = (string) ($quest['quest_name'] ?? $quest_id);
      $status = (string) ($quest['status'] ?? 'active');
      $current_phase = max(1, (int) ($quest['current_phase'] ?? 1));
      $completion_note = !empty($quest['completed_at']) ? ', completed: yes' : '';
      $lines[] = "- {$quest_name} {quest_id: {$quest_id}} [status: {$status}, current_phase: {$current_phase}{$completion_note}]";

      foreach (array_slice($quest['current_objectives'] ?? [], 0, 3) as $objective) {
        $objective_label = !empty($objective['completed']) ? 'Completed objective' : 'Current objective';
        $lines[] = '  ' . $objective_label . ': ' . $this->formatObjectiveForPrompt($objective);
      }

      foreach (array_slice($quest['next_objectives'] ?? [], 0, 2) as $objective) {
        $lines[] = '  Upcoming objective: ' . $this->formatObjectiveForPrompt($objective);
      }
    }

    return implode("\n", $lines);
  }

  /**
   * Get campaign-level quest log entries.
   *
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return array
   *   Campaign log entries.
   */
  public function getCampaignQuestLog(int $campaign_id): array {
    return $this->database->select('dc_campaign_quest_log', 'ql')
      ->fields('ql')
      ->condition('campaign_id', $campaign_id)
      ->orderBy('timestamp', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Get character-level quest log entries.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param int $character_id
   *   Character ID.
   *
   * @return array
   *   Character log entries.
   */
  public function getCharacterQuestLog(int $campaign_id, int $character_id): array {
    $tracking_ids = $this->resolveQuestTrackingCharacterIds($campaign_id, $character_id);
    if ($tracking_ids === []) {
      return [];
    }

    return $this->database->select('dc_campaign_quest_log', 'ql')
      ->fields('ql')
      ->condition('campaign_id', $campaign_id)
      ->condition('character_id', $tracking_ids, 'IN')
      ->orderBy('timestamp', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Get available quests at a location.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $location_id
   *   Location identifier.
   * @param int $character_id
   *   Character ID (to check prerequisites).
   *
   * @return array
   *   Array of available quests.
   */
  public function getAvailableQuests(
    int $campaign_id,
    string $location_id,
    int $character_id
  ): array {
    // TODO: Add prerequisite checking
    return $this->database->select('dc_campaign_quests', 'q')
      ->fields('q')
      ->condition('campaign_id', $campaign_id)
      ->condition('location_id', $location_id)
      ->condition('status', ['offered', 'lead'], 'IN')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Find location quests clearly referenced by text for a character.
   */
  public function findMentionedAvailableQuests(
    int $campaign_id,
    string $location_id,
    int $character_id,
    string $text,
    int $max_matches = 3,
    int $minimum_score = 4
  ): array {
    $normalized_text = $this->normalizeQuestSearchText($text);
    if ($campaign_id <= 0 || $location_id === '' || $character_id <= 0 || $normalized_text === '') {
      return [];
    }

    $active_quests = $this->getActiveQuests($campaign_id, $character_id);
    $active_ids = array_fill_keys(array_map(static fn(array $quest): string => (string) ($quest['quest_id'] ?? ''), $active_quests), TRUE);
    $matches = [];
    $candidate_rows = [];

    foreach ($this->getAvailableQuests($campaign_id, $location_id, $character_id) as $quest) {
      if (is_array($quest)) {
        $candidate_rows[(string) ($quest['quest_id'] ?? '')] = $quest;
      }
    }
    foreach ($this->getCampaignQuestTracking($campaign_id) as $quest) {
      if (!is_array($quest)) {
        continue;
      }
      if ((string) ($quest['location_id'] ?? '') !== $location_id) {
        continue;
      }
      if (!empty($quest['completed_at']) || strtolower((string) ($quest['status'] ?? '')) !== 'active') {
        continue;
      }
      $candidate_rows[(string) ($quest['quest_id'] ?? '')] = $quest;
    }

    foreach ($candidate_rows as $quest) {
      if (!is_array($quest)) {
        continue;
      }

      $quest = $this->normalizeQuestPromptRow($quest);
      $quest_id = (string) ($quest['quest_id'] ?? '');
      if ($quest_id === '' || isset($active_ids[$quest_id])) {
        continue;
      }

      $current_phase = max(1, (int) ($quest['current_phase'] ?? 1));
      $quest['current_objectives'] = $this->getObjectivesForPhase($quest, $current_phase, TRUE);
      $quest['next_objectives'] = $this->getObjectivesForPhase($quest, $current_phase + 1, FALSE);
      $quest['match_score'] = $this->scoreQuestAgainstPrompt($normalized_text, $quest);
      if ((int) $quest['match_score'] < $minimum_score) {
        continue;
      }

      $matches[] = $quest;
    }

    if ($matches === []) {
      return [];
    }

    usort($matches, static function (array $a, array $b): int {
      $score_compare = ((int) ($b['match_score'] ?? 0)) <=> ((int) ($a['match_score'] ?? 0));
      if ($score_compare !== 0) {
        return $score_compare;
      }
      return strcmp((string) ($a['quest_id'] ?? ''), (string) ($b['quest_id'] ?? ''));
    });

    return array_slice($matches, 0, max(1, $max_matches));
  }

  /**
   * Load campaign quest.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $quest_id
   *   Quest ID.
   *
   * @return array|null
   *   Quest data or NULL.
   */
  protected function loadCampaignQuest(int $campaign_id, string $quest_id): ?array {
    $result = $this->database->select('dc_campaign_quests', 'q')
      ->fields('q')
      ->condition('campaign_id', $campaign_id)
      ->condition('quest_id', $quest_id)
      ->execute()
      ->fetchAssoc();

    return $result ?: NULL;
  }

  /**
   * Enforce quest destination contracts before activating a quest.
   *
   * Active quests may only reference destination identifiers that resolve to
   * campaign room records. This prevents runtime navigation contract violations
   * that would otherwise fail later during map rendering.
   *
   * @param int $campaign_id
   *   Campaign identifier.
   * @param array<string, mixed> $quest
   *   Quest row payload.
   * @param array<int, mixed> $objective_phases
   *   Generated objective phases payload.
   */
  protected function assertQuestActivationDestinationContracts(
    int $campaign_id,
    array $quest,
    array $objective_phases
  ): void {
    $destinations = [];
    $objective_actor_refs = [];
    foreach ($objective_phases as $phase) {
      if (!is_array($phase)) {
        continue;
      }
      foreach ((array) ($phase['objectives'] ?? []) as $objective) {
        if (is_array($objective)) {
          $this->collectObjectiveDestinationReferences($objective, $destinations);
          $this->collectObjectiveActorReferences($objective, $objective_actor_refs);
        }
      }
    }
    if ($destinations !== []) {
      $room_rows = $this->database->select('dc_campaign_rooms', 'r')
        ->fields('r', ['room_id', 'name', 'source_room_id'])
        ->condition('campaign_id', $campaign_id)
        ->execute()
        ->fetchAllAssoc('room_id');
      if (!is_array($room_rows) || $room_rows === []) {
        throw new \RuntimeException(sprintf(
          'Quest activation contract violation: campaign %d has no room records for destination validation.',
          $campaign_id
        ));
      }

      $valid_identifiers = [];
      foreach ($room_rows as $row) {
        $room = is_array($row) ? $row : (array) $row;
        foreach (['room_id', 'name', 'source_room_id'] as $field) {
          $value = trim((string) ($room[$field] ?? ''));
          if ($value !== '') {
            $valid_identifiers[$value] = TRUE;
          }
        }
      }

      foreach (array_keys($destinations) as $destination) {
        if (isset($valid_identifiers[$destination])) {
          continue;
        }
        throw new \InvalidArgumentException(sprintf(
          'Quest activation contract violation: quest "%s" references destination "%s", but it is not present in campaign %d room registry.',
          (string) ($quest['quest_id'] ?? 'unknown'),
          $destination,
          $campaign_id
        ));
      }
    }

    foreach (array_keys($objective_actor_refs) as $actor_ref) {
      $resolved_row_id = $this->lookupNpcCharacterRowIdByReferences($campaign_id, [$actor_ref]);
      if ($resolved_row_id !== NULL) {
        continue;
      }
      throw new \InvalidArgumentException(sprintf(
        'Quest activation contract violation: quest "%s" references actor target "%s", but it is not present in campaign %d NPC registry.',
        (string) ($quest['quest_id'] ?? 'unknown'),
        $actor_ref,
        $campaign_id
      ));
    }
  }

  /**
   * Recursively collect destination references from one objective tree node.
   *
   * @param array<string, mixed> $objective
   *   Objective node.
   * @param array<string, bool> $destinations
   *   Destination set (mutated).
   */
  protected function collectObjectiveDestinationReferences(array $objective, array &$destinations): void {
    foreach (['destination_id', 'destination', 'location_id', 'location'] as $field) {
      $value = trim((string) ($objective[$field] ?? ''));
      if ($value !== '') {
        $destinations[$value] = TRUE;
      }
    }

    foreach ((array) ($objective['children'] ?? []) as $child) {
      if (is_array($child)) {
        $this->collectObjectiveDestinationReferences($child, $destinations);
      }
    }
  }

  /**
   * Recursively collect actor reference contracts from interact objective nodes.
   *
   * @param array<string, mixed> $objective
   *   Objective node.
   * @param array<string, bool> $actor_refs
   *   Actor reference set (mutated).
   */
  protected function collectObjectiveActorReferences(array $objective, array &$actor_refs): void {
    $objective_type = strtolower(trim((string) ($objective['type'] ?? '')));
    if ($objective_type === 'interact') {
      foreach (['target', 'npc_ref', 'entity_ref', 'completion_criteria.target'] as $field) {
        $value = '';
        if ($field === 'completion_criteria.target') {
          $value = trim((string) ($objective['completion_criteria']['target'] ?? ''));
        }
        else {
          $value = trim((string) ($objective[$field] ?? ''));
        }
        if ($value !== '') {
          $actor_refs[$value] = TRUE;
        }
      }
    }

    foreach ((array) ($objective['children'] ?? []) as $child) {
      if (is_array($child)) {
        $this->collectObjectiveActorReferences($child, $actor_refs);
      }
    }
  }

  /**
   * Load quest progress.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $quest_id
   *   Quest ID.
   * @param int|null $character_id
   *   Character ID.
   *
   * @return array|null
   *   Progress record or NULL.
   */
  protected function loadProgress(int $campaign_id, string $quest_id, ?int $character_id): ?array {
    return $this->loadProgressByScope($campaign_id, $quest_id, $character_id, NULL);
  }

  /**
   * Load quest progress for a specific tracking scope.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $quest_id
   *   Quest ID.
   * @param int|null $character_id
   *   Character ID (NULL for non-character scopes).
   * @param int|null $party_id
   *   Party ID (NULL for non-party scopes).
   *
   * @return array|null
   *   Progress record or NULL.
   */
  protected function loadProgressByScope(
    int $campaign_id,
    string $quest_id,
    ?int $character_id,
    ?int $party_id
  ): ?array {
    $query = $this->database->select('dc_campaign_quest_progress', 'qp')
      ->fields('qp')
      ->condition('campaign_id', $campaign_id)
      ->condition('quest_id', $quest_id);

    if ($character_id !== NULL) {
      $tracking_ids = $this->resolveQuestTrackingCharacterIds($campaign_id, $character_id);
      if ($tracking_ids === []) {
        return NULL;
      }
      $query->condition('character_id', $tracking_ids, 'IN');
      $query->condition('party_id', NULL, 'IS NULL');
    }
    elseif ($party_id !== NULL) {
      $query->condition('party_id', $party_id);
      $query->condition('character_id', NULL, 'IS NULL');
    }
    else {
      $query->condition('character_id', NULL, 'IS NULL');
      $query->condition('party_id', NULL, 'IS NULL');
    }

    $result = $query->execute()->fetchAssoc();
    return $result ?: NULL;
  }

  /**
   * Promote one unscoped progress row to a character scope.
   *
   * @param array<string,mixed> $unscoped_progress
   *   Progress row loaded with NULL character_id/party_id scope.
   */
  protected function promoteUnscopedProgressToCharacterScope(
    int $campaign_id,
    string $quest_id,
    int $character_id,
    array $unscoped_progress
  ): void {
    if ($campaign_id <= 0 || trim($quest_id) === '' || $character_id <= 0) {
      throw new \InvalidArgumentException('Progress promotion requires campaign_id, quest_id, and character_id.');
    }

    $progress_row_id = isset($unscoped_progress['id']) && is_numeric($unscoped_progress['id'])
      ? (int) $unscoped_progress['id']
      : 0;
    if ($progress_row_id <= 0) {
      return;
    }

    $this->database->update('dc_campaign_quest_progress')
      ->fields([
        'character_id' => $character_id,
        'party_id' => NULL,
        'last_updated' => $this->time->getRequestTime(),
      ])
      ->condition('id', $progress_row_id)
      ->condition('campaign_id', $campaign_id)
      ->condition('quest_id', $quest_id)
      ->condition('character_id', NULL, 'IS NULL')
      ->condition('party_id', NULL, 'IS NULL')
      ->execute();
  }

  /**
   * Resolve runtime/source character ids that may own quest state.
   *
   * @return array<int>
   *   Positive ids used for quest lookups.
   */
  protected function resolveQuestTrackingCharacterIds(int $campaign_id, int $character_id): array {
    if ($campaign_id <= 0 || $character_id <= 0) {
      return [];
    }

    $ids = [$character_id];

    $runtime_row = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id', 'character_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('id', $character_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (is_array($runtime_row) && !empty($runtime_row['character_id'])) {
      $ids[] = (int) $runtime_row['character_id'];
    }

    $runtime_ids = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('character_id', $character_id)
      ->execute()
      ->fetchCol();
    foreach ($runtime_ids as $runtime_id) {
      if (is_numeric($runtime_id)) {
        $ids[] = (int) $runtime_id;
      }
    }

    return array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
  }

  /**
   * Ensure a progress record exists for a specific scope.
   */
  protected function ensureProgressRecord(
    int $campaign_id,
    string $quest_id,
    ?int $character_id,
    ?int $party_id,
    array $objective_states,
    int $current_phase
  ): void {
    $existing = $this->loadProgressByScope($campaign_id, $quest_id, $character_id, $party_id);
    if (!empty($existing)) {
      return;
    }

    $now = $this->time->getRequestTime();
    $this->database->insert('dc_campaign_quest_progress')
      ->fields([
        'campaign_id' => $campaign_id,
        'quest_id' => $quest_id,
        'character_id' => $character_id,
        'party_id' => $party_id,
        'objective_states' => json_encode($objective_states),
        'current_phase' => $current_phase,
        'started_at' => $now,
        'last_updated' => $now,
      ])
      ->execute();
  }

  /**
   * Save quest progress for a specific scope.
   */
  protected function saveProgressRecord(
    int $campaign_id,
    string $quest_id,
    ?int $character_id,
    ?int $party_id,
    array $objective_states,
    int $current_phase
  ): void {
    $query = $this->database->update('dc_campaign_quest_progress')
      ->fields([
        'objective_states' => json_encode($objective_states),
        'current_phase' => $current_phase,
        'last_updated' => $this->time->getRequestTime(),
      ])
      ->condition('campaign_id', $campaign_id)
      ->condition('quest_id', $quest_id);

    if ($character_id !== NULL) {
      $query->condition('character_id', $character_id);
      $query->condition('party_id', NULL, 'IS NULL');
    }
    elseif ($party_id !== NULL) {
      $query->condition('party_id', $party_id);
      $query->condition('character_id', NULL, 'IS NULL');
    }
    else {
      $query->condition('character_id', NULL, 'IS NULL');
      $query->condition('party_id', NULL, 'IS NULL');
    }

    $query->execute();
  }

  /**
   * Apply a quest objective update for a phase and objective.
   *
   * @param array $objective_states
   *   Objective states (updated by reference).
   * @param int $current_phase
   *   Current phase to update.
   * @param string $objective_id
   *   Objective ID.
   * @param int $progress
   *   Progress amount.
   *
   * @return array
   *   Flags: updated, objective_completed.
   */
  protected function applyObjectiveUpdate(
    array &$objective_states,
    int $current_phase,
    string $objective_id,
    int $progress
  ): array {
    $updated = FALSE;
    $objective_completed = FALSE;

    foreach ($objective_states as &$phase) {
      if (($phase['phase'] ?? NULL) != $current_phase) {
        continue;
      }

      $phase_objectives = is_array($phase['objectives'] ?? NULL) ? $phase['objectives'] : [];
      $completed_before = $this->collectCompletedObjectiveIds($phase_objectives);
      $updated = $this->applyObjectiveUpdateToCollection($phase_objectives, $objective_id, $progress);
      if ($updated) {
        $this->refreshObjectiveCollection($phase_objectives);
        $this->normalizeObjectiveCollectionVisibility($phase_objectives, TRUE);
        $phase['objectives'] = $phase_objectives;
        $completed_after = $this->collectCompletedObjectiveIds($phase_objectives);
        $objective_completed = array_diff($completed_after, $completed_before) !== [];
        break;
      }
    }

    return [
      'updated' => $updated,
      'objective_completed' => $objective_completed,
    ];
  }

  /**
   * Transfer collected quest items to the turn-in target when handoff completes.
   */
  protected function transferTurnInCollectiblesForCompletedObjective(
    int $campaign_id,
    array $quest,
    array $objective_states,
    string $completed_objective_id,
    ?int $character_id
  ): void {
    if (
      $campaign_id <= 0
      || !$character_id
      || $character_id <= 0
      || !$this->inventoryManagementService
      || !$this->database->schema()->tableExists('dc_campaign_item_instances')
    ) {
      return;
    }

    $completed_objective = $this->findObjectiveNodeById($objective_states, $completed_objective_id);
    if (!is_array($completed_objective) || strtolower((string) ($completed_objective['type'] ?? '')) !== 'interact') {
      return;
    }

    $target_character_id = $this->resolveTurnInTargetCharacterId($campaign_id, $quest, $completed_objective);
    if ($target_character_id === NULL) {
      throw new \RuntimeException(sprintf(
        'Quest turn-in target not found for objective "%s" (campaign_id=%d, quest_id=%s).',
        $completed_objective_id,
        $campaign_id,
        (string) ($quest['quest_id'] ?? '')
      ));
    }

    $depends_on = array_values(array_filter(
      array_map(
        static fn($value): string => trim((string) $value),
        is_array($completed_objective['depends_on'] ?? NULL) ? $completed_objective['depends_on'] : []
      ),
      static fn(string $value): bool => $value !== ''
    ));
    if ($depends_on === []) {
      return;
    }

    $quest_source = trim((string) ($quest['source_template_id'] ?? $quest['quest_id'] ?? ''));
    if ($quest_source === '') {
      $quest_source = trim((string) ($quest['quest_id'] ?? ''));
    }
    $quest_id = trim((string) ($quest['quest_id'] ?? ''));

    foreach ($depends_on as $dependency_objective_id) {
      $dependency_objective = $this->findObjectiveNodeById($objective_states, $dependency_objective_id);
      if (!is_array($dependency_objective)) {
        continue;
      }
      if (strtolower((string) ($dependency_objective['type'] ?? '')) !== 'collect') {
        continue;
      }

      $target_quantity = max(
        1,
        (int) ($dependency_objective['target_count'] ?? $dependency_objective['completion_criteria']['target_count'] ?? 1)
      );
      $item_label = trim((string) ($dependency_objective['item'] ?? ''));
      $canonical_item_id = $item_label !== ''
        ? $this->buildQuestCollectibleItemId($quest_source, $dependency_objective_id, $item_label)
        : '';

      $this->transferQuestCollectibleInventoryByObjective(
        $campaign_id,
        (string) $character_id,
        (string) $target_character_id,
        $quest_source,
        $quest_id,
        $dependency_objective_id,
        $canonical_item_id,
        $target_quantity
      );
    }
  }

  /**
   * Transfer collectible inventory rows that belong to one collect objective.
   */
  protected function transferQuestCollectibleInventoryByObjective(
    int $campaign_id,
    string $character_id,
    string $target_character_id,
    string $quest_source,
    string $quest_id,
    string $objective_id,
    string $canonical_item_id,
    int $required_quantity
  ): void {
    if ($required_quantity <= 0) {
      return;
    }

    $rows = $this->database->select('dc_campaign_item_instances', 'i')
      ->fields('i', ['item_instance_id', 'item_id', 'quantity', 'state_data'])
      ->condition('campaign_id', $campaign_id)
      ->condition('location_ref', $character_id)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    $candidates = [];
    foreach ($rows as $row) {
      $state = json_decode((string) ($row['state_data'] ?? '{}'), TRUE);
      $state = is_array($state) ? $state : [];
      $spawn = is_array($state['_spawn'] ?? NULL) ? $state['_spawn'] : [];
      $state_objective_id = trim((string) ($state['objective_id'] ?? $spawn['objective_id'] ?? ''));
      $state_quest_association = trim((string) ($state['quest_association'] ?? $spawn['quest_source'] ?? ''));
      $state_spawn_quest_id = trim((string) ($spawn['quest_id'] ?? ''));
      $item_type = strtolower(trim((string) ($state['type'] ?? '')));
      $tags = array_map(
        static fn($value): string => strtolower(trim((string) $value)),
        is_array($state['tags'] ?? NULL) ? $state['tags'] : []
      );

      $is_collectible = str_contains($item_type, 'collectible')
        || in_array('collectible', $tags, TRUE)
        || in_array('quest_item', $tags, TRUE);
      if (!$is_collectible) {
        continue;
      }

      $item_id = trim((string) ($row['item_id'] ?? ''));
      $matches_objective = $state_objective_id !== '' && $state_objective_id === $objective_id;
      $matches_association = $state_quest_association !== '' && (
        $state_quest_association === $quest_source
        || $state_quest_association === $quest_id
        || ($quest_id !== '' && str_starts_with($quest_id, $state_quest_association . '_'))
      );
      $matches_quest_id = $state_spawn_quest_id !== '' && $state_spawn_quest_id === $quest_id;
      $matches_canonical_item_id = $canonical_item_id !== '' && $item_id === $canonical_item_id;

      if (!$matches_objective && !$matches_association && !$matches_quest_id && !$matches_canonical_item_id) {
        continue;
      }

      $priority = 0;
      if ($matches_objective) {
        $priority += 4;
      }
      if ($matches_quest_id) {
        $priority += 2;
      }
      if ($matches_canonical_item_id) {
        $priority += 1;
      }
      if ($matches_association) {
        $priority += 1;
      }

      $candidates[] = [
        'item_instance_id' => (string) ($row['item_instance_id'] ?? ''),
        'item_id' => $item_id,
        'quantity' => max(0, (int) ($row['quantity'] ?? 0)),
        'priority' => $priority,
        'state_data' => $state,
      ];
    }

    if ($candidates === []) {
      return;
    }

    usort($candidates, static function (array $left, array $right): int {
      if ($left['priority'] !== $right['priority']) {
        return $right['priority'] <=> $left['priority'];
      }
      return strcmp($left['item_instance_id'], $right['item_instance_id']);
    });

    $available_quantity = array_reduce($candidates, static function (int $carry, array $candidate): int {
      return $carry + max(0, (int) ($candidate['quantity'] ?? 0));
    }, 0);
    $remaining = max($required_quantity, $available_quantity);
    foreach ($candidates as $candidate) {
      if ($remaining <= 0) {
        break;
      }
      if (($candidate['item_instance_id'] ?? '') === '' || (int) ($candidate['quantity'] ?? 0) <= 0) {
        continue;
      }

      $transfer_quantity = min($remaining, (int) $candidate['quantity']);
      $state_data = is_array($candidate['state_data'] ?? NULL) ? $candidate['state_data'] : [];
      $item_id = trim((string) ($candidate['item_id'] ?? ''));
      if ($item_id === '') {
        $item_id = trim((string) ($state_data['id'] ?? $state_data['content_id'] ?? 'quest_item'));
      }
      $item_name = trim((string) ($state_data['name'] ?? $this->humanizeQuestReference($item_id) ?? 'Quest Item'));
      if ($item_name === '') {
        $item_name = 'Quest Item';
      }
      $item_type = trim((string) ($state_data['type'] ?? $state_data['item_type'] ?? 'quest_collectible_item'));
      if ($item_type === '') {
        $item_type = 'quest_collectible_item';
      }
      $transfer_payload = $state_data;
      $transfer_payload['id'] = $item_id;
      $transfer_payload['name'] = $item_name;
      $transfer_payload['type'] = $item_type;

      $this->inventoryManagementService->removeItemFromInventory(
        $character_id,
        'character',
        $candidate['item_instance_id'],
        $transfer_quantity,
        $campaign_id
      );
      $this->inventoryManagementService->addItemToInventory(
        $target_character_id,
        'character',
        $transfer_payload,
        'carried',
        $transfer_quantity,
        $campaign_id
      );
      $remaining -= $transfer_quantity;
    }

    if ($remaining > 0) {
      throw new \RuntimeException(sprintf(
        'Quest hand-in transfer incomplete for objective "%s": missing %d collectible item(s).',
        $objective_id,
        $remaining
      ));
    }
  }

  /**
   * Transfer any completed turn-in collectible handoffs before quest finalization.
   */
  protected function transferQuestCompletionTurnInCollectibles(
    int $campaign_id,
    array $quest,
    array $objective_states,
    ?int $character_id
  ): void {
    if (
      $campaign_id <= 0
      || !$character_id
      || $character_id <= 0
    ) {
      return;
    }

    $interact_objective_ids = $this->collectCompletedInteractObjectiveIds($objective_states);
    foreach ($interact_objective_ids as $objective_id) {
      $this->transferTurnInCollectiblesForCompletedObjective(
        $campaign_id,
        $quest,
        $objective_states,
        $objective_id,
        $character_id
      );
    }
  }

  /**
   * Collect completed interact objective ids from phase/objective trees.
   *
   * @return array<int, string>
   *   Completed interact objective ids.
   */
  protected function collectCompletedInteractObjectiveIds(array $objective_states): array {
    $ids = [];
    foreach ($objective_states as $phase) {
      if (!is_array($phase)) {
        continue;
      }
      $phase_objectives = is_array($phase['objectives'] ?? NULL) ? $phase['objectives'] : [];
      foreach ($phase_objectives as $objective) {
        $this->collectCompletedInteractObjectiveIdsFromNode($objective, $ids);
      }
    }

    return array_values(array_unique($ids));
  }

  /**
   * Collect completed interact objective ids from one objective node subtree.
   *
   * @param array<string, mixed> $objective
   *   Objective definition.
   * @param array<int, string> $ids
   *   Interact ids accumulator.
   */
  protected function collectCompletedInteractObjectiveIdsFromNode(array $objective, array &$ids): void {
    $objective_id = trim((string) ($objective['objective_id'] ?? ''));
    if (
      $objective_id !== ''
      && !empty($objective['completed'])
      && strtolower(trim((string) ($objective['type'] ?? ''))) === 'interact'
    ) {
      $ids[] = $objective_id;
    }

    foreach ($this->getObjectiveChildren($objective) as $child) {
      if (!is_array($child)) {
        continue;
      }
      $this->collectCompletedInteractObjectiveIdsFromNode($child, $ids);
    }
  }

  /**
   * Find one objective node by objective_id from the nested phase/objective tree.
   */
  protected function findObjectiveNodeById(array $objective_states, string $objective_id): ?array {
    $objective_id = trim($objective_id);
    if ($objective_id === '') {
      return NULL;
    }

    foreach ($objective_states as $phase) {
      if (!is_array($phase)) {
        continue;
      }
      $found = $this->findObjectiveNodeByIdInCollection((array) ($phase['objectives'] ?? []), $objective_id);
      if (is_array($found)) {
        return $found;
      }
    }

    return NULL;
  }

  /**
   * Resolve the runtime NPC character row that should receive hand-in items.
   */
  protected function resolveTurnInTargetCharacterId(int $campaign_id, array $quest, array $completed_objective): ?int {
    $target_refs = [];
    foreach ([
      (string) ($completed_objective['target'] ?? ''),
      (string) ($completed_objective['npc_ref'] ?? ''),
      (string) ($completed_objective['entity_ref'] ?? ''),
      (string) ($completed_objective['completion_criteria']['target'] ?? ''),
    ] as $raw_ref) {
      foreach ($this->expandTurnInNpcReferenceCandidates($raw_ref) as $candidate) {
        if (!in_array($candidate, $target_refs, TRUE)) {
          $target_refs[] = $candidate;
        }
      }
    }

    $target_match = $this->lookupNpcCharacterRowIdByReferences($campaign_id, $target_refs);
    if ($target_match !== NULL) {
      return $target_match;
    }

    return NULL;
  }

  /**
   * Expand one NPC target token into normalized lookup candidates.
   *
   * @return array<int, string>
   *   Candidate instance/character ids to match in dc_campaign_characters.
   */
  protected function expandTurnInNpcReferenceCandidates(string $raw_ref): array {
    $raw_ref = trim($raw_ref);
    if ($raw_ref === '') {
      return [];
    }

    $normalized = strtolower($raw_ref);
    $normalized = preg_replace('/\s+/', '_', $normalized) ?? $normalized;
    $normalized = preg_replace('/[^a-z0-9_:-]/', '', $normalized) ?? $normalized;
    $normalized = trim($normalized, '_');
    if ($normalized === '') {
      return [];
    }

    $candidates = [$normalized];
    if (str_starts_with($normalized, 'npc_')) {
      $without_prefix = substr($normalized, 4);
      if ($without_prefix !== '') {
        $candidates[] = $without_prefix;
      }
    }
    else {
      $candidates[] = 'npc_' . $normalized;
    }

    return array_values(array_unique(array_filter($candidates, static fn(string $value): bool => $value !== '')));
  }

  /**
   * Resolve one NPC runtime row id from reference candidates.
   *
   * @param array<int, string> $raw_refs
   *   Candidate reference values.
   */
  protected function lookupNpcCharacterRowIdByReferences(int $campaign_id, array $raw_refs): ?int {
    $instance_refs = [];
    $numeric_ids = [];

    foreach ($raw_refs as $raw_ref) {
      $raw_ref = trim((string) $raw_ref);
      if ($raw_ref === '') {
        continue;
      }
      foreach ($this->expandTurnInNpcReferenceCandidates($raw_ref) as $candidate) {
        if ($candidate === '') {
          continue;
        }
        $instance_refs[] = $candidate;
        if (ctype_digit($candidate)) {
          $numeric_value = (int) $candidate;
          if ($numeric_value > 0) {
            $numeric_ids[] = $numeric_value;
          }
        }
      }
    }

    $instance_refs = array_values(array_unique($instance_refs));
    $numeric_ids = array_values(array_unique($numeric_ids));
    if ($instance_refs === [] && $numeric_ids === []) {
      return NULL;
    }

    $query = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('type', 'npc');
    $or = $query->orConditionGroup();
    if ($instance_refs !== []) {
      $or->condition('instance_id', $instance_refs, 'IN');
    }
    if ($numeric_ids !== []) {
      $or->condition('id', $numeric_ids, 'IN');
      $or->condition('character_id', $numeric_ids, 'IN');
    }
    $query->condition($or)
      ->orderBy('id', 'DESC')
      ->range(0, 1);

    $row = $query->execute()->fetchAssoc();
    if (!is_array($row) || empty($row['id'])) {
      return NULL;
    }

    return (int) $row['id'];
  }

  /**
   * Recursively find one objective node by objective_id.
   */
  protected function findObjectiveNodeByIdInCollection(array $objectives, string $objective_id): ?array {
    foreach ($objectives as $objective) {
      if (!is_array($objective)) {
        continue;
      }
      if (trim((string) ($objective['objective_id'] ?? '')) === $objective_id) {
        return $objective;
      }
      foreach (['children', 'objectives', 'sub_objectives'] as $children_key) {
        if (!is_array($objective[$children_key] ?? NULL)) {
          continue;
        }
        $found = $this->findObjectiveNodeByIdInCollection($objective[$children_key], $objective_id);
        if (is_array($found)) {
          return $found;
        }
      }
    }

    return NULL;
  }

  /**
   * Apply a quest objective update across a nested objective collection.
   */
  protected function applyObjectiveUpdateToCollection(array &$objectives, string $objective_id, int $progress): bool {
    foreach ($objectives as &$objective) {
      if (!is_array($objective)) {
        continue;
      }
      if ($this->applyObjectiveUpdateToNode($objective, $objective_id, $progress)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Apply a quest objective update to one node or a nested child node.
   */
  protected function applyObjectiveUpdateToNode(array &$objective, string $objective_id, int $progress): bool {
    $type = (string) ($objective['type'] ?? '');
    $candidate_id = (string) ($objective['objective_id'] ?? '');
    $matches = $candidate_id === $objective_id
      || ($objective_id === 'explore' && $type === 'explore')
      || ($objective_id === 'kill_enemies' && $type === 'kill');

    if ($matches) {
      $this->applyObjectiveNodeProgress($objective, $progress);
      return TRUE;
    }

    foreach ($this->getObjectiveChildren($objective) as &$child_objective) {
      if (!is_array($child_objective)) {
        continue;
      }
      if ($this->applyObjectiveUpdateToNode($child_objective, $objective_id, $progress)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Apply progress to a single objective node.
   */
  protected function applyObjectiveNodeProgress(array &$objective, int $progress): void {
    $this->getObjectiveTypeService()->applyProgress($objective, $progress);
  }

  /**
   * Refresh completion state for an objective collection.
   */
  protected function refreshObjectiveCollection(array &$objectives): void {
    foreach ($objectives as &$objective) {
      if (!is_array($objective)) {
        continue;
      }
      $this->refreshObjectiveCompletionState($objective);
      $this->syncEscortRuntimeMetadata($objective);
    }
  }

  /**
   * Reveal hidden sibling objectives in sequence for active collections.
   */
  protected function normalizeObjectiveCollectionVisibility(array &$objectives, bool $allow_hidden_reveal): void {
    $all_previous_completed = TRUE;

    foreach ($objectives as &$objective) {
      if (!is_array($objective)) {
        continue;
      }

      if (!empty($objective['hidden'])) {
        $objective['revealed'] = !empty($objective['completed']) || ($allow_hidden_reveal && $all_previous_completed);
      }

      $children = &$this->getObjectiveChildren($objective);
      if ($children !== []) {
        $child_reveal_allowed = $allow_hidden_reveal
          && (!array_key_exists('revealed', $objective) || !empty($objective['revealed']) || !empty($objective['completed']));
        $this->normalizeObjectiveCollectionVisibility($children, $child_reveal_allowed);
      }

      if (empty($objective['completed'])) {
        $all_previous_completed = FALSE;
      }
    }
  }

  /**
   * Keep escort runtime metadata aligned with child-objective completion state.
   */
  protected function syncEscortRuntimeMetadata(array &$objective): void {
    $children = $this->getObjectiveChildren($objective);
    if ($children === []) {
      return;
    }

    $encounter_completion = [];
    $arrival_completed = FALSE;
    foreach ($children as $child) {
      if (!is_array($child)) {
        continue;
      }

      $encounter_id = trim((string) ($child['encounter_id'] ?? ''));
      if ($encounter_id !== '') {
        $encounter_completion[$encounter_id] = !empty($child['completed']);
      }

      if (!empty($child['escort_arrival']) && !empty($child['completed'])) {
        $arrival_completed = TRUE;
      }
    }

    if (is_array($objective['path_encounters'] ?? NULL)) {
      foreach ($objective['path_encounters'] as &$encounter) {
        if (!is_array($encounter)) {
          continue;
        }

        $encounter_id = trim((string) ($encounter['encounter_id'] ?? ''));
        if ($encounter_id !== '' && array_key_exists($encounter_id, $encounter_completion)) {
          $encounter['resolved'] = $encounter_completion[$encounter_id];
        }
      }
      unset($encounter);
    }

    if (($objective['type'] ?? NULL) === 'escort') {
      $objective['arrived'] = $arrival_completed || !empty($objective['completed']);
    }
  }

  /**
   * Mark an objective collection as revealed or hidden for the quest journal.
   */
  protected function setObjectiveCollectionRevealed(array &$objectives, bool $revealed): void {
    foreach ($objectives as &$objective) {
      if (!is_array($objective)) {
        continue;
      }
      $objective['revealed'] = !empty($objective['hidden']) ? !empty($objective['completed']) : $revealed;
      $children = &$this->getObjectiveChildren($objective);
      if ($children !== []) {
        $this->setObjectiveCollectionRevealed($children, $revealed);
      }
    }
  }

  /**
   * Refresh the computed completion state for one objective node.
   */
  protected function refreshObjectiveCompletionState(array &$objective): bool {
    return $this->getObjectiveTypeService()->refreshCompletion($objective);
  }

  /**
   * Determine whether every objective in a collection is complete.
   */
  protected function areObjectiveCollectionCompleted(array $objectives): bool {
    return $this->getObjectiveTypeService()->areObjectiveCollectionCompleted($objectives);
  }

  /**
   * Collect completed objective ids from a nested objective tree.
   *
   * @return array<int, string>
   *   Completed objective ids.
   */
  protected function collectCompletedObjectiveIds(array $objectives): array {
    $ids = [];
    foreach ($objectives as $objective) {
      if (!is_array($objective)) {
        continue;
      }
      if (!empty($objective['completed']) && !empty($objective['objective_id'])) {
        $ids[] = (string) $objective['objective_id'];
      }
      $ids = array_merge($ids, $this->collectCompletedObjectiveIds($this->getObjectiveChildren($objective)));
    }

    return $ids;
  }

  /**
   * Flatten a nested objective tree into actionable display rows.
   */
  protected function collectObjectivesForDisplay(array $objectives, bool $exclude_completed): array {
    $flattened = [];
    foreach ($objectives as $objective) {
      if (!is_array($objective)) {
        continue;
      }
      if (!empty($objective['hidden']) && empty($objective['completed'])) {
        continue;
      }

      $children = $this->getObjectiveChildren($objective);
      if ($children !== []) {
        $flattened = array_merge($flattened, $this->collectObjectivesForDisplay($children, $exclude_completed));
        continue;
      }

      if ($exclude_completed && !empty($objective['completed'])) {
        continue;
      }

      $target_count = (int) ($objective['target_count'] ?? 0);
      $current = (int) ($objective['current'] ?? 0);
      if ($exclude_completed && $target_count > 0 && $current >= $target_count) {
        continue;
      }

      $flattened[] = $objective;
    }

    return array_values($flattened);
  }

  /**
   * Return nested objective children by reference.
   */
  protected function &getObjectiveChildren(array &$objective): array {
    if (!isset($objective['children']) || !is_array($objective['children'])) {
      $objective['children'] = [];
    }

    return $objective['children'];
  }

  /**
   * Resolve completion criteria for an objective, defaulting when omitted.
   */
  protected function resolveObjectiveCompletionCriteria(array $objective): array {
    return $this->getObjectiveTypeService()->normalizeCompletionCriteria($objective['completion_criteria'] ?? [], $objective);
  }

  /**
   * Resolve required objective type service for quest objective state logic.
   */
  protected function getObjectiveTypeService(): ObjectiveTypeService {
    if (!($this->objectiveTypeService instanceof ObjectiveTypeService)) {
      throw new \RuntimeException('ObjectiveTypeService is required for quest objective state validation and progression.');
    }

    return $this->objectiveTypeService;
  }

  /**
   * Check if quest has active progress.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $quest_id
   *   Quest ID.
   * @param int|null $character_id
   *   Character ID.
   * @param int|null $party_id
   *   Party ID.
   *
   * @return bool
   *   TRUE if active progress exists.
   */
  protected function hasActiveProgress(
    int $campaign_id,
    string $quest_id,
    ?int $character_id,
    ?int $party_id
  ): bool {
    $query = $this->database->select('dc_campaign_quest_progress', 'qp')
      ->condition('campaign_id', $campaign_id)
      ->condition('quest_id', $quest_id)
      ->condition('completed_at', NULL, 'IS NULL');

    if ($character_id) {
      $query->condition('character_id', $character_id);
    }
    elseif ($party_id) {
      $query->condition('party_id', $party_id);
    }

    return $query->countQuery()->execute()->fetchField() > 0;
  }

  /**
   * Notifies storyline orchestration for contract-critical quest state changes.
   */
  protected function notifyStorylineManager(
    int $campaign_id,
    string $quest_id,
    string $event_type,
    ?int $character_id,
    array $event_data = []
  ): void {
    $storyline_manager = $this->resolveStorylineManagerService();
    if ($storyline_manager === NULL) {
      throw new StorylineSyncContractException(sprintf(
        'Storyline synchronization failed for quest "%s": StorylineManagerService is required for event "%s".',
        $quest_id,
        $event_type
      ));
    }

    try {
      $storyline_manager->recordQuestStateChange(
        $campaign_id,
        $quest_id,
        $event_type,
        $character_id,
        $event_data
      );
    }
    catch (\Throwable $throwable) {
      throw new StorylineSyncContractException(sprintf(
        'Storyline synchronization failed for quest "%s" on event "%s": %s',
        $quest_id,
        $event_type,
        $throwable->getMessage()
      ), 0, $throwable);
    }
  }

  /**
   * Identify contract-critical sync failures that must not degrade to soft-fail.
   */
  protected function isContractCriticalStorylineSyncFailure(\Throwable $throwable): bool {
    return $throwable instanceof StorylineSyncContractException;
  }

  /**
   * Resolve storyline manager lazily to avoid constructor-time DI ordering drift.
   */
  protected function resolveStorylineManagerService(): ?StorylineManagerService {
    if ($this->storylineManager instanceof StorylineManagerService) {
      return $this->storylineManager;
    }
    if (!\Drupal::hasService('dungeoncrawler_content.storyline_manager')) {
      return NULL;
    }

    $candidate = \Drupal::service('dungeoncrawler_content.storyline_manager');
    if ($candidate instanceof StorylineManagerService) {
      $this->storylineManager = $candidate;
      return $this->storylineManager;
    }

    return NULL;
  }

  /**
   * Initialize objective states from objectives.
   *
   * @param array $objectives
   *   Objectives array.
   *
   * @return array
   *   Initial objective states.
   */
  protected function initializeObjectiveStates(array $objectives): array {
    foreach ($objectives as &$phase) {
      if (!is_array($phase)) {
        continue;
      }
      $phase_number = max(1, (int) ($phase['phase'] ?? 1));
      $this->preparePhaseObjectiveCollection($phase, $phase_number === 1);
    }
    return $objectives;
  }

  /**
   * Normalize and refresh one phase objective collection for journal visibility.
   */
  protected function preparePhaseObjectiveCollection(array &$phase_row, bool $allow_hidden_reveal): void {
    $phase_row['objectives'] = is_array($phase_row['objectives'] ?? NULL) ? $phase_row['objectives'] : [];
    $this->setObjectiveCollectionRevealed($phase_row['objectives'], $allow_hidden_reveal);
    $this->refreshObjectiveCollection($phase_row['objectives']);
    $this->normalizeObjectiveCollectionVisibility($phase_row['objectives'], $allow_hidden_reveal);
  }

  /**
   * Check if a phase is complete.
   *
   * @param array $objective_states
   *   Objective states.
   * @param int $phase
   *   Phase number.
   *
   * @return bool
   *   TRUE if all objectives in phase are complete.
   */
  protected function isPhaseComplete(array $objective_states, int $phase): bool {
    foreach ($objective_states as $phase_data) {
      if ($phase_data['phase'] == $phase) {
        return $this->areObjectiveCollectionCompleted(is_array($phase_data['objectives'] ?? NULL) ? $phase_data['objectives'] : []);
      }
    }
    return FALSE;
  }

  /**
   * Check if quest is completed (all phases done).
   *
   * @param array $objective_states
   *   Current objective states.
   *
   * @return bool
   *   TRUE if all objectives complete.
   */
  protected function isQuestCompleted(array $objective_states): bool {
    foreach ($objective_states as $phase) {
      if (!$this->areObjectiveCollectionCompleted(is_array($phase['objectives'] ?? NULL) ? $phase['objectives'] : [])) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Advance to next quest phase.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $quest_id
   *   Quest ID.
   * @param int|null $character_id
   *   Character ID.
   */
  protected function advancePhase(int $campaign_id, string $quest_id, ?int $character_id, bool $log_event = TRUE): void {
    $progress = $this->loadProgress($campaign_id, $quest_id, $character_id);
    if ($progress) {
      $new_phase = $progress['current_phase'] + 1;
      $objective_states = json_decode((string) ($progress['objective_states'] ?? '[]'), TRUE) ?? [];
      foreach ($objective_states as &$phase_row) {
        if (!is_array($phase_row)) {
          continue;
        }
        if ((int) ($phase_row['phase'] ?? 0) !== $new_phase) {
          continue;
        }
        $this->preparePhaseObjectiveCollection($phase_row, TRUE);
      }
      unset($phase_row);

      $this->database->update('dc_campaign_quest_progress')
        ->fields([
          'current_phase' => $new_phase,
          'objective_states' => json_encode($objective_states),
        ])
        ->condition('campaign_id', $campaign_id)
        ->condition('quest_id', $quest_id)
        ->condition('character_id', $character_id, is_null($character_id) ? 'IS NULL' : '=')
        ->execute();

      $quest = $this->loadCampaignQuest($campaign_id, $quest_id);
      if (is_array($quest) && $quest !== []) {
        $this->materializeCollectQuestItemsForActivePhase($campaign_id, $quest, (int) $new_phase);
      }

      if ($log_event) {
        $this->logQuestEvent(
          $campaign_id,
          $quest_id,
          'phase_advanced',
          ['old_phase' => $progress['current_phase'], 'new_phase' => $new_phase],
          "Advanced to phase $new_phase",
          $character_id
        );
      }
    }
  }

  /**
   * Log a quest event.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $quest_id
   *   Quest ID.
   * @param string $event_type
   *   Event type.
   * @param array $event_data
   *   Event data.
   * @param string|null $narrative_text
   *   Human-readable narrative.
   * @param int|null $character_id
   *   Character ID.
   */
  protected function logQuestEvent(
    int $campaign_id,
    string $quest_id,
    string $event_type,
    array $event_data,
    ?string $narrative_text = NULL,
    ?int $character_id = NULL
  ): void {
    $timestamp = $this->time->getRequestTime();

    $this->database->insert('dc_campaign_quest_log')
      ->fields([
        'campaign_id' => $campaign_id,
        'quest_id' => $quest_id,
        'character_id' => $character_id,
        'event_type' => $event_type,
        'event_data' => json_encode($event_data),
        'narrative_text' => $narrative_text,
        'timestamp' => $timestamp,
      ])
      ->execute();
  }

  /**
   * Normalize quest tracking rows for prompt use.
   */
  protected function normalizeQuestPromptRow(array $quest): array {
    $quest['generated_objectives'] = json_decode((string) ($quest['generated_objectives'] ?? '[]'), TRUE) ?? [];
    $quest['objective_states'] = json_decode((string) ($quest['objective_states'] ?? '[]'), TRUE) ?? [];
    $quest['quest_data'] = json_decode((string) ($quest['quest_data'] ?? '{}'), TRUE) ?? [];
    return $quest;
  }

  /**
   * Return objectives for a given phase, optionally excluding completed rows.
   */
  protected function getObjectivesForPhase(array $quest, int $phase, bool $exclude_completed): array {
    if ($phase <= 0) {
      $phase = 1;
    }

    $phase_rows = is_array($quest['objective_states'] ?? NULL) && $quest['objective_states'] !== []
      ? $quest['objective_states']
      : (is_array($quest['generated_objectives'] ?? NULL) ? $quest['generated_objectives'] : []);

    foreach ($phase_rows as $phase_row) {
      if ((int) ($phase_row['phase'] ?? 0) !== $phase) {
        continue;
      }

      $objectives = is_array($phase_row['objectives'] ?? NULL) ? $phase_row['objectives'] : [];
      return $this->collectObjectivesForDisplay($objectives, $exclude_completed);
    }

    return [];
  }

  /**
   * Score whether a quest is relevant to the player's current request.
   */
  protected function scoreQuestAgainstPrompt(string $normalized_text, array $quest): int {
    $score = 0;

    foreach ($this->buildQuestReferencePhrases($quest) as $phrase) {
      if ($phrase === '') {
        continue;
      }

      if (strlen($phrase) >= 4 && str_contains($normalized_text, $phrase)) {
        $score += strlen($phrase) >= 12 ? 6 : 4;
      }

      foreach (explode(' ', $phrase) as $token) {
        if (strlen($token) < 4 || $this->isQuestStopWord($token)) {
          continue;
        }

        if (preg_match('/\b' . preg_quote($token, '/') . '\b/', $normalized_text)) {
          $score++;
        }
      }
    }

    return $score;
  }

  /**
   * Build searchable phrases from quest metadata and objectives.
   */
  protected function buildQuestReferencePhrases(array $quest): array {
    $phrases = [
      $quest['quest_id'] ?? '',
      $quest['quest_name'] ?? '',
      $quest['quest_description'] ?? '',
      $quest['giver_npc_id'] ?? '',
    ];

    foreach (['current_objectives', 'next_objectives'] as $objective_list_key) {
      foreach (($quest[$objective_list_key] ?? []) as $objective) {
        $phrases[] = $objective['objective_id'] ?? '';
        $phrases[] = $objective['description'] ?? '';
        $phrases[] = $objective['item'] ?? '';
        $phrases[] = $objective['target'] ?? '';
        $phrases[] = $objective['npc_ref'] ?? '';
      }
    }

    $normalized = [];
    foreach ($phrases as $phrase) {
      $normalized_phrase = $this->normalizeQuestSearchText((string) $phrase);
      if ($normalized_phrase !== '') {
        $normalized[$normalized_phrase] = TRUE;
      }
    }

    return array_keys($normalized);
  }

  /**
   * Format a current or upcoming objective for prompt context.
   */
  protected function formatObjectiveForPrompt(array $objective): string {
    $objective_id = (string) ($objective['objective_id'] ?? 'objective');
    $description = (string) ($objective['description'] ?? $objective_id);
    foreach ([
      'item' => 'item_label',
      'target' => 'target_label',
      'location' => 'location_label',
      'destination' => 'destination_label',
    ] as $field => $label_field) {
      $value = trim((string) ($objective[$field] ?? ''));
      if ($value === '') {
        continue;
      }
      $label = (string) ($objective[$label_field] ?? $this->humanizeQuestReference($value));
      if ($label !== '' && $label !== $value) {
        $description = str_replace($value, $label, $description);
      }
    }
    $parts = ["{$description} {objective_id: {$objective_id}}"];

    $type = (string) ($objective['type'] ?? '');
    if ($type !== '') {
      $parts[] = "type: {$type}";
    }

    $target_count = (int) ($objective['target_count'] ?? 0);
    if ($target_count > 0) {
      $parts[] = 'progress: ' . (int) ($objective['current'] ?? 0) . '/' . $target_count;
    }

    if (!empty($objective['item'])) {
      $parts[] = 'item: ' . ($objective['item_label'] ?? $this->humanizeQuestReference((string) $objective['item']));
    }
    if (!empty($objective['target'])) {
      $parts[] = 'target: ' . ($objective['target_label'] ?? $this->humanizeQuestReference((string) $objective['target']));
    }

    return implode(' | ', $parts);
  }

  /**
   * Humanize internal quest/objective references for prompt readability.
   */
  protected function humanizeQuestReference(string $value): string {
    $value = trim($value);
    if ($value === '') {
      return '';
    }
    return ucwords(str_replace(['_', '-'], ' ', $value));
  }

  /**
   * Detect whether the player message explicitly asks about quests/tasks.
   */
  protected function hasQuestReferenceCue(string $normalized_text): bool {
    foreach ([
      'quest',
      'quests',
      'objective',
      'objectives',
      'task',
      'tasks',
      'mission',
      'missions',
      'job',
      'jobs',
      'assignment',
      'assignments',
    ] as $cue) {
      if (preg_match('/\b' . preg_quote($cue, '/') . '\b/', $normalized_text)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Detect whether the player asks for status/progress/completion details.
   */
  protected function hasQuestStatusReviewCue(string $normalized_text): bool {
    foreach ([
      'journal',
      'updated',
      'update',
      'complete',
      'completed',
      'completion',
      'done',
      'finished',
      'progress',
      'status',
      'why',
      'did',
      'didnt',
      'didn t',
    ] as $cue) {
      if (preg_match('/\b' . preg_quote($cue, '/') . '\b/', $normalized_text)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Normalize text for loose quest matching.
   */
  protected function normalizeQuestSearchText(string $value): string {
    $value = strtolower(trim($value));
    $value = str_replace(['_', '-'], ' ', $value);
    $value = preg_replace('/[^a-z0-9\s]+/', ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value);
    return trim((string) $value);
  }

  /**
   * Ignore common non-discriminating search tokens.
   */
  protected function isQuestStopWord(string $token): bool {
    return in_array($token, [
      'that',
      'with',
      'from',
      'this',
      'have',
      'need',
      'your',
      'their',
      'them',
      'into',
      'then',
      'tavern',
      'room',
      'return',
      'gather',
      'collect',
      'talk',
      'give',
      'bring',
      'find',
    ], TRUE);
  }

}
