<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Component\Datetime\TimeInterface;
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
    ?StorylineManagerService $storyline_manager = NULL
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
    $this->time = $time;
    $this->storylineManager = $storyline_manager;
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

      // Check if already started
      if ($this->hasActiveProgress($campaign_id, $quest_id, $character_id, $party_id)) {
        $this->logger->warning('Quest already active: @quest', ['@quest' => $quest_id]);
        return FALSE;
      }

      // Initialize objective states.
      $objectives = json_decode($quest['generated_objectives'], TRUE);
      $objective_states = $this->initializeObjectiveStates($objectives);

      // Always ensure campaign-level tracking exists.
      $this->ensureProgressRecord(
        $campaign_id,
        $quest_id,
        NULL,
        NULL,
        $objective_states,
        1
      );

      // Ensure entity-specific tracking exists.
      $this->ensureProgressRecord(
        $campaign_id,
        $quest_id,
        $character_id,
        $party_id,
        $objective_states,
        1
      );

      // Update quest status to active
      $this->database->update('dc_campaign_quests')
        ->fields(['status' => 'active'])
        ->condition('campaign_id', $campaign_id)
        ->condition('quest_id', $quest_id)
        ->execute();

      // Log event
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

      $this->notifyStorylineManager($campaign_id, $quest_id, 'quest_started', $character_id, [
        'party_id' => $party_id,
        'status' => 'active',
      ]);

      return TRUE;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to start quest: @error', ['@error' => $e->getMessage()]);
      return FALSE;
    }
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

      // Save updated progress for the caller scope.
      $this->saveProgressRecord(
        $campaign_id,
        $quest_id,
        $character_id,
        NULL,
        $objective_states,
        $current_phase
      );

      // Log if objective completed
      if ($objective_completed) {
        $this->logQuestEvent(
          $campaign_id,
          $quest_id,
          'objective_completed',
          ['objective_id' => $objective_id],
          "Objective completed: $objective_id",
          $character_id
        );
      }

      // Advance phase if complete.
      if ($phase_complete) {
        $this->advancePhase($campaign_id, $quest_id, $character_id);
      }

      // Mirror updates into campaign-level tracking when this was character-scoped.
      if ($character_id !== NULL) {
        $campaign_progress = $this->loadProgressByScope($campaign_id, $quest_id, NULL, NULL);
        if (!empty($campaign_progress)) {
          $campaign_objective_states = json_decode((string) $campaign_progress['objective_states'], TRUE) ?? [];
          $campaign_phase = (int) ($campaign_progress['current_phase'] ?? 1);

          ['updated' => $campaign_updated] = $this->applyObjectiveUpdate(
            $campaign_objective_states,
            $campaign_phase,
            $objective_id,
            $progress
          );

          if ($campaign_updated) {
            $this->saveProgressRecord(
              $campaign_id,
              $quest_id,
              NULL,
              NULL,
              $campaign_objective_states,
              $campaign_phase
            );

            if ($this->isPhaseComplete($campaign_objective_states, $campaign_phase)) {
              $this->advancePhase($campaign_id, $quest_id, NULL, FALSE);
            }
          }
        }
      }

      // Check overall completion
      $quest_complete = $this->isQuestCompleted($objective_states);

      return [
        'success' => TRUE,
        'objective_states' => $objective_states,
        'quest_completed' => $quest_complete,
        'phase_completed' => $phase_complete,
        'objective_completed' => $objective_completed,
      ];
    }
    catch (\Exception $e) {
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
    try {
      $now = $this->time->getRequestTime();

      // Update requested scope progress record.
      $this->database->update('dc_campaign_quest_progress')
        ->fields([
          'completed_at' => $now,
          'outcome' => $outcome,
        ])
        ->condition('campaign_id', $campaign_id)
        ->condition('quest_id', $quest_id)
        ->condition('character_id', $character_id, is_null($character_id) ? 'IS NULL' : '=')
        ->execute();

      // Keep campaign-scope tracking in sync when a character completes a quest.
      if ($character_id !== NULL) {
        $this->database->update('dc_campaign_quest_progress')
          ->fields([
            'completed_at' => $now,
            'outcome' => $outcome,
          ])
          ->condition('campaign_id', $campaign_id)
          ->condition('quest_id', $quest_id)
          ->condition('character_id', NULL, 'IS NULL')
          ->condition('party_id', NULL, 'IS NULL')
          ->execute();
      }

      // Update quest status
      $this->database->update('dc_campaign_quests')
        ->fields(['status' => 'completed'])
        ->condition('campaign_id', $campaign_id)
        ->condition('quest_id', $quest_id)
        ->execute();

      // Load quest for rewards
      $quest = $this->loadCampaignQuest($campaign_id, $quest_id);
      $rewards = json_decode($quest['generated_rewards'] ?? '{}', TRUE);

      // Log completion
      $this->logQuestEvent(
        $campaign_id,
        $quest_id,
        'completed',
        ['outcome' => $outcome, 'rewards' => $rewards],
        "Quest completed with outcome: $outcome",
        $character_id
      );

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
        'completed_at' => $now,
      ];
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to complete quest: @error', ['@error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => $e->getMessage()];
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
    $query = $this->database->select('dc_campaign_quest_progress', 'qp');
    $query->join('dc_campaign_quests', 'q', 'qp.campaign_id = q.campaign_id AND qp.quest_id = q.quest_id');
    $query->fields('q')
      ->fields('qp', ['objective_states', 'current_phase', 'started_at', 'last_updated'])
      ->condition('qp.campaign_id', $campaign_id)
      ->condition('qp.character_id', $character_id)
      ->condition('qp.completed_at', NULL, 'IS NULL');

    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
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
    $query = $this->database->select('dc_campaign_quests', 'q');
    $query->leftJoin('dc_campaign_quest_progress', 'qp', 'qp.campaign_id = q.campaign_id AND qp.quest_id = q.quest_id AND qp.character_id IS NULL AND qp.party_id IS NULL');
    $query->fields('q')
      ->fields('qp', ['objective_states', 'current_phase', 'started_at', 'last_updated', 'completed_at', 'outcome'])
      ->condition('q.campaign_id', $campaign_id)
      ->orderBy('q.created_at', 'DESC');

    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
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
    $query = $this->database->select('dc_campaign_quest_progress', 'qp');
    $query->join('dc_campaign_quests', 'q', 'qp.campaign_id = q.campaign_id AND qp.quest_id = q.quest_id');
    $query->fields('q')
      ->fields('qp', ['objective_states', 'current_phase', 'started_at', 'last_updated', 'completed_at', 'outcome'])
      ->condition('qp.campaign_id', $campaign_id)
      ->condition('qp.character_id', $character_id)
      ->orderBy('qp.last_updated', 'DESC');

    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Build a concise quest-context block for GM prompts when quests are referenced.
   */
  public function buildRelevantQuestPromptContext(int $campaign_id, ?int $character_id, string $player_text, int $max_quests = 3): string {
    $normalized_text = $this->normalizeQuestSearchText($player_text);
    if ($campaign_id <= 0 || $normalized_text === '') {
      return '';
    }

    $quests = $character_id !== NULL && $character_id > 0
      ? $this->getActiveQuests($campaign_id, $character_id)
      : array_values(array_filter($this->getCampaignQuestTracking($campaign_id), function (array $quest): bool {
        return empty($quest['completed_at']) && (($quest['status'] ?? '') === 'active');
      }));

    if ($quests === []) {
      return '';
    }

    $quest_rows = [];
    foreach ($quests as $quest) {
      if (!is_array($quest)) {
        continue;
      }

      $quest = $this->normalizeQuestPromptRow($quest);
      $current_objectives = $this->getObjectivesForPhase($quest, (int) ($quest['current_phase'] ?? 1), TRUE);
      if ($current_objectives === []) {
        continue;
      }

      $quest['current_objectives'] = $current_objectives;
      $quest['next_objectives'] = $this->getObjectivesForPhase($quest, ((int) ($quest['current_phase'] ?? 1)) + 1, FALSE);
      $quest['match_score'] = $this->scoreQuestAgainstPrompt($normalized_text, $quest);
      $quest_rows[] = $quest;
    }

    if ($quest_rows === []) {
      return '';
    }

    $quest_reference_detected = $this->hasQuestReferenceCue($normalized_text);
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
      $lines[] = "- {$quest_name} {quest_id: {$quest_id}} [status: {$status}, current_phase: {$current_phase}]";

      foreach (array_slice($quest['current_objectives'] ?? [], 0, 3) as $objective) {
        $lines[] = '  Current objective: ' . $this->formatObjectiveForPrompt($objective);
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
      ->condition('character_id', NULL, 'IS NULL')
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
    return $this->database->select('dc_campaign_quest_log', 'ql')
      ->fields('ql')
      ->condition('campaign_id', $campaign_id)
      ->condition('character_id', $character_id)
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
      ->condition('status', 'available')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
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

    $result = $query->execute()->fetchAssoc();
    return $result ?: NULL;
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

      foreach ($phase['objectives'] as &$obj) {
        $type = (string) ($obj['type'] ?? '');
        $candidate_id = (string) ($obj['objective_id'] ?? '');
        $matches = $candidate_id === $objective_id
          || ($objective_id === 'explore' && $type === 'explore')
          || ($objective_id === 'kill_enemies' && $type === 'kill');

        if (!$matches) {
          continue;
        }

        switch ($type) {
          case 'kill':
          case 'collect':
            $target_count = (int) ($obj['target_count'] ?? 0);
            $current_count = (int) ($obj['current'] ?? 0);
            $next_count = $target_count > 0 ? min($current_count + $progress, $target_count) : ($current_count + $progress);
            $obj['current'] = $next_count;
            if ($target_count > 0 && $next_count >= $target_count && empty($obj['completed'])) {
              $obj['completed'] = TRUE;
              $objective_completed = TRUE;
            }
            break;

          case 'explore':
            $obj['discovered'] = TRUE;
            if (empty($obj['completed'])) {
              $obj['completed'] = TRUE;
              $objective_completed = TRUE;
            }
            break;

          case 'escort':
            $obj['arrived'] = TRUE;
            if (empty($obj['completed'])) {
              $obj['completed'] = TRUE;
              $objective_completed = TRUE;
            }
            break;

          case 'interact':
            if (empty($obj['completed'])) {
              $obj['completed'] = TRUE;
              $objective_completed = TRUE;
            }
            break;
        }

        $updated = TRUE;
        break 2;
      }
    }

    return [
      'updated' => $updated,
      'objective_completed' => $objective_completed,
    ];
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
   * Notifies storyline orchestration without breaking quest flows on failures.
   */
  protected function notifyStorylineManager(
    int $campaign_id,
    string $quest_id,
    string $event_type,
    ?int $character_id,
    array $event_data = []
  ): void {
    if ($this->storylineManager === NULL) {
      return;
    }

    try {
      $this->storylineManager->recordQuestStateChange(
        $campaign_id,
        $quest_id,
        $event_type,
        $character_id,
        $event_data
      );
    }
    catch (\Throwable $throwable) {
      $this->logger->warning('Storyline sync skipped for quest @quest: @message', [
        '@quest' => $quest_id,
        '@message' => $throwable->getMessage(),
      ]);
    }
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
    // Objectives are already in the correct format from generator
    return $objectives;
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
        foreach ($phase_data['objectives'] as $obj) {
          if (empty($obj['completed'])) {
            return FALSE;
          }
        }
        return TRUE;
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
      foreach ($phase['objectives'] as $obj) {
        if (empty($obj['completed'])) {
          return FALSE;
        }
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

      $this->database->update('dc_campaign_quest_progress')
        ->fields(['current_phase' => $new_phase])
        ->condition('campaign_id', $campaign_id)
        ->condition('quest_id', $quest_id)
        ->condition('character_id', $character_id, is_null($character_id) ? 'IS NULL' : '=')
        ->execute();

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

    // Campaign-level log entry.
    $this->database->insert('dc_campaign_quest_log')
      ->fields([
        'campaign_id' => $campaign_id,
        'quest_id' => $quest_id,
        'character_id' => NULL,
        'event_type' => $event_type,
        'event_data' => json_encode($event_data),
        'narrative_text' => $narrative_text,
        'timestamp' => $timestamp,
      ])
      ->execute();

    // Character-level log entry (when applicable).
    if ($character_id !== NULL) {
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
      if (!$exclude_completed) {
        return array_values($objectives);
      }

      return array_values(array_filter($objectives, static function (array $objective): bool {
        if (!empty($objective['completed'])) {
          return FALSE;
        }

        $target_count = (int) ($objective['target_count'] ?? 0);
        $current = (int) ($objective['current'] ?? 0);
        return $target_count <= 0 || $current < $target_count;
      }));
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
      $parts[] = 'item: ' . $objective['item'];
    }
    if (!empty($objective['target'])) {
      $parts[] = 'target: ' . $objective['target'];
    }

    return implode(' | ', $parts);
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
