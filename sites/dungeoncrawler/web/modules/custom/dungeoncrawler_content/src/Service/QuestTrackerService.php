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
    TimeInterface $time
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
    $this->time = $time;
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

}
