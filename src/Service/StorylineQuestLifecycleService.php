<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Central lifecycle authority for storyline-owned campaign quest transitions.
 */
class StorylineQuestLifecycleService {

  protected LoggerInterface $logger;

  public function __construct(
    protected Connection $database,
    protected LockBackendInterface $lock,
    protected QuestTrackerService $questTracker,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->logger = $logger_factory->get('dungeoncrawler_content');
  }

  /**
   * Ensure exactly one open offered/active row exists for a template.
   *
   * @param callable():array<string,mixed> $quest_data_factory
   *   Factory that builds an insert payload for dc_campaign_quests.
   *
   * @return bool
   *   TRUE when a new quest row was inserted; FALSE when one already existed.
   */
  public function ensureOfferedQuestFromTemplate(
    int $campaign_id,
    string $template_id,
    callable $quest_data_factory,
    ?int $character_id = NULL,
    ?int $party_id = NULL,
  ): bool {
    $normalized_template_id = trim($template_id);
    if ($campaign_id <= 0 || $normalized_template_id === '') {
      throw new \RuntimeException('Quest lifecycle ensureOfferedQuestFromTemplate requires campaign_id and template_id.');
    }

    return $this->withTemplateLock($campaign_id, $normalized_template_id, function () use ($campaign_id, $normalized_template_id, $quest_data_factory, $character_id, $party_id): bool {
      if ($this->hasCompletedQuestForTemplate($campaign_id, $normalized_template_id)) {
        $this->logger->info('Quest template {template_id} already completed in campaign {campaign_id}; suppressing re-offer.', [
          'template_id' => $normalized_template_id,
          'campaign_id' => $campaign_id,
        ]);
        return FALSE;
      }

      if ($this->promoteLeadRowsAndDetectTemplatePresence($campaign_id, $normalized_template_id)) {
        $existing = $this->loadQuestByTemplate($campaign_id, $normalized_template_id);
        if (($character_id !== NULL && $character_id > 0) || ($party_id !== NULL && $party_id > 0)) {
          $existing_quest_id = trim((string) ($existing['quest_id'] ?? ''));
          if ($existing_quest_id !== '') {
            if (!$this->startOfferedQuest($campaign_id, $existing_quest_id, $character_id, $party_id)) {
              throw new \RuntimeException(sprintf(
                'Quest lifecycle failed to reconcile active progress for existing quest %s in campaign %d template %s.',
                $existing_quest_id,
                $campaign_id,
                $normalized_template_id
              ));
            }
          }
        }
        $this->ensureObjectiveDestinationRoomsMaterializedForQuestRow($campaign_id, $existing);
        return FALSE;
      }

      $quest_data = $quest_data_factory();
      if (!is_array($quest_data) || $quest_data === []) {
        throw new \RuntimeException(sprintf(
          'Quest lifecycle insert payload missing for campaign %d template %s.',
          $campaign_id,
          $normalized_template_id
        ));
      }

      if (trim((string) ($quest_data['source_template_id'] ?? '')) === '') {
        $quest_data['source_template_id'] = $normalized_template_id;
      }
      if (trim((string) ($quest_data['status'] ?? '')) === '') {
        $quest_data['status'] = 'offered';
      }
      if ($this->hasQuestLogEntryForTemplate($campaign_id, (string) $quest_data['source_template_id'])) {
        $this->logger->info('Quest template {template_id} already exists in campaign {campaign_id} quest log; suppressing duplicate insert.', [
          'template_id' => (string) $quest_data['source_template_id'],
          'campaign_id' => $campaign_id,
        ]);
        $this->ensureObjectiveDestinationRoomsMaterializedForQuestRow(
          $campaign_id,
          $this->loadQuestByTemplate($campaign_id, (string) $quest_data['source_template_id'])
        );
        return FALSE;
      }

      $this->database->insert('dc_campaign_quests')
        ->fields($quest_data)
        ->execute();

      $quest_id = trim((string) ($quest_data['quest_id'] ?? ''));
      if ($quest_id === '') {
        throw new \RuntimeException(sprintf(
          'Quest lifecycle insert payload missing quest_id for campaign %d template %s.',
          $campaign_id,
          $normalized_template_id
        ));
      }

      // Auto-start only when an explicit quest scope is provided.
      // Brokered leads surfaced from dialogue without a character/party must stay
      // in offered state so they appear in the journal as leads with next steps.
      if (($character_id !== NULL && $character_id > 0) || ($party_id !== NULL && $party_id > 0)) {
        if (!$this->startOfferedQuest($campaign_id, $quest_id, $character_id, $party_id)) {
          throw new \RuntimeException(sprintf(
            'Quest lifecycle failed to auto-start offered quest %s for campaign %d template %s.',
            $quest_id,
            $campaign_id,
            $normalized_template_id
          ));
        }
      }
      $this->ensureObjectiveDestinationRoomsMaterializedForQuestRow($campaign_id, $quest_data);

      return TRUE;
    });
  }

  /**
   * Check whether a template has already been completed in this campaign.
   */
  public function hasCompletedQuestForTemplate(int $campaign_id, string $template_id): bool {
    $normalized_template_id = trim($template_id);
    if ($campaign_id <= 0 || $normalized_template_id === '') {
      return FALSE;
    }

    $completed = $this->database->select('dc_campaign_quests', 'q')
      ->fields('q', ['quest_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('source_template_id', $normalized_template_id)
      ->condition('status', 'completed')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    if (is_string($completed) && trim($completed) !== '') {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Promote lead rows for a template and report whether any row exists.
   */
  public function promoteLeadRowsAndDetectTemplatePresence(int $campaign_id, string $template_id): bool {
    $normalized_template_id = trim($template_id);
    if ($campaign_id <= 0 || $normalized_template_id === '') {
      return FALSE;
    }

    $existing_rows = $this->database->select('dc_campaign_quests', 'q')
      ->fields('q', ['id', 'quest_id', 'status', 'created_at'])
      ->condition('campaign_id', $campaign_id)
      ->condition('source_template_id', $normalized_template_id)
      ->orderBy('created_at', 'DESC')
      ->orderBy('id', 'DESC')
      ->execute()
      ->fetchAllAssoc('quest_id');

    if (!is_array($existing_rows) || $existing_rows === []) {
      return FALSE;
    }

    $has_existing_template_row = FALSE;
    $open_statuses = ['active', 'ready_for_turn_in', 'offered', 'lead', 'available'];
    $open_rows = [];
    foreach ($existing_rows as $existing_row) {
      if (!is_array($existing_row) || empty($existing_row['quest_id'])) {
        continue;
      }
      $has_existing_template_row = TRUE;
      $status = strtolower(trim((string) ($existing_row['status'] ?? ''))) ?: 'offered';
      if (in_array($status, $open_statuses, TRUE)) {
        $open_rows[] = [
          'id' => (int) ($existing_row['id'] ?? 0),
          'quest_id' => (string) $existing_row['quest_id'],
          'status' => $status,
          'created_at' => (int) ($existing_row['created_at'] ?? 0),
        ];
      }
    }

    if (count($open_rows) > 1) {
      $this->collapseDuplicateOpenTemplateRows($campaign_id, $normalized_template_id, $open_rows);
    }

    if ($open_rows !== []) {
      $rank = ['active' => 0, 'ready_for_turn_in' => 1, 'offered' => 2, 'lead' => 3, 'available' => 4];
      usort($open_rows, static function (array $a, array $b) use ($rank): int {
        $rank_cmp = ($rank[$a['status']] ?? 99) <=> ($rank[$b['status']] ?? 99);
        if ($rank_cmp !== 0) {
          return $rank_cmp;
        }
        $created_cmp = ((int) ($b['created_at'] ?? 0)) <=> ((int) ($a['created_at'] ?? 0));
        if ($created_cmp !== 0) {
          return $created_cmp;
        }
        return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
      });

      $keeper = $open_rows[0];
      if (($keeper['status'] ?? '') === 'lead') {
        $this->database->update('dc_campaign_quests')
          ->fields(['status' => 'offered'])
          ->condition('campaign_id', $campaign_id)
          ->condition('quest_id', (string) $keeper['quest_id'])
          ->execute();
      }
    }

    return $has_existing_template_row;
  }

  /**
   * Promote one quest row from lead to offered.
   */
  public function promoteLeadToOfferedByQuestId(int $campaign_id, string $quest_id): bool {
    $normalized_quest_id = trim($quest_id);
    if ($campaign_id <= 0 || $normalized_quest_id === '') {
      return FALSE;
    }

    $affected = $this->database->update('dc_campaign_quests')
      ->fields(['status' => 'offered'])
      ->condition('campaign_id', $campaign_id)
      ->condition('quest_id', $normalized_quest_id)
      ->condition('status', 'lead')
      ->execute();

    return $affected > 0;
  }

  /**
   * Set quest status with optional expected-current-state guard.
   *
   * @param array<int, string> $expected_current_statuses
   *   Optional allowed current statuses for the transition.
   */
  public function setQuestStatusByQuestId(
    int $campaign_id,
    string $quest_id,
    string $new_status,
    array $expected_current_statuses = [],
  ): bool {
    $normalized_quest_id = trim($quest_id);
    $normalized_new_status = strtolower(trim($new_status));
    if ($campaign_id <= 0 || $normalized_quest_id === '' || $normalized_new_status === '') {
      return FALSE;
    }

    $update = $this->database->update('dc_campaign_quests')
      ->fields(['status' => $normalized_new_status])
      ->condition('campaign_id', $campaign_id)
      ->condition('quest_id', $normalized_quest_id);

    $expected = array_values(array_filter(array_map(static fn(string $status): string => strtolower(trim($status)), $expected_current_statuses)));
    if ($expected !== []) {
      $update->condition('status', $expected, 'IN');
    }

    return $update->execute() > 0;
  }

  /**
   * Start an offered quest through the canonical tracker service.
   */
  public function startOfferedQuest(
    int $campaign_id,
    string $quest_id,
    ?int $character_id = NULL,
    ?int $party_id = NULL,
  ): bool {
    $normalized_quest_id = trim($quest_id);
    if ($campaign_id <= 0 || $normalized_quest_id === '') {
      throw new \RuntimeException('Quest lifecycle startOfferedQuest requires campaign_id and quest_id.');
    }
    return $this->questTracker->startQuest($campaign_id, $normalized_quest_id, $character_id, $party_id);
  }

  /**
   * Load one campaign quest by source template id.
   */
  public function loadQuestByTemplate(int $campaign_id, string $template_id): ?array {
    $normalized_template_id = trim($template_id);
    if ($campaign_id <= 0 || $normalized_template_id === '') {
      return NULL;
    }

    $rows = $this->database->select('dc_campaign_quests', 'q')
      ->fields('q')
      ->condition('campaign_id', $campaign_id)
      ->condition('source_template_id', $normalized_template_id)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    if (!is_array($rows) || $rows === []) {
      return NULL;
    }

    $rank = [
      'active' => 0,
      'ready_for_turn_in' => 1,
      'offered' => 2,
      'lead' => 3,
      'available' => 4,
      'completed' => 5,
      'superseded' => 6,
    ];
    $normalized_rows = array_values(array_filter($rows, static fn($row): bool => is_array($row) && !empty($row['quest_id'])));
    usort($normalized_rows, static function (array $a, array $b) use ($rank): int {
      $status_cmp = ($rank[strtolower(trim((string) ($a['status'] ?? '')))] ?? 99) <=> ($rank[strtolower(trim((string) ($b['status'] ?? '')))] ?? 99);
      if ($status_cmp !== 0) {
        return $status_cmp;
      }
      $created_cmp = ((int) ($b['created_at'] ?? 0)) <=> ((int) ($a['created_at'] ?? 0));
      if ($created_cmp !== 0) {
        return $created_cmp;
      }
      return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
    });
    $row = $normalized_rows[0] ?? NULL;

    return is_array($row) ? $row : NULL;
  }

  /**
   * Determine whether a template already has any quest-log row in this campaign.
   */
  protected function hasQuestLogEntryForTemplate(int $campaign_id, string $template_id): bool {
    $normalized_template_id = trim($template_id);
    if ($campaign_id <= 0 || $normalized_template_id === '') {
      return FALSE;
    }

    $existing = $this->database->select('dc_campaign_quests', 'q')
      ->fields('q', ['quest_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('source_template_id', $normalized_template_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();

    return is_string($existing) && trim($existing) !== '';
  }

  /**
   * Load one campaign quest by quest id.
   */
  public function loadQuestById(int $campaign_id, string $quest_id): ?array {
    $normalized_quest_id = trim($quest_id);
    if ($campaign_id <= 0 || $normalized_quest_id === '') {
      return NULL;
    }

    $row = $this->database->select('dc_campaign_quests', 'q')
      ->fields('q')
      ->condition('campaign_id', $campaign_id)
      ->condition('quest_id', $normalized_quest_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    return is_array($row) ? $row : NULL;
  }

  /**
   * Ensure offered quest exists and return the canonical row.
   *
   * @param callable():array<string,mixed> $quest_data_factory
   *   Factory that builds an insert payload for dc_campaign_quests.
   */
  public function ensureOfferedQuestFromTemplateAndLoad(
    int $campaign_id,
    string $template_id,
    callable $quest_data_factory,
  ): ?array {
    $this->ensureOfferedQuestFromTemplate($campaign_id, $template_id, $quest_data_factory);
    return $this->loadQuestByTemplate($campaign_id, $template_id);
  }

  /**
   * Attach storyline linkage fields to one quest row.
   */
  public function attachStorylineReferenceToQuestRow(
    int $campaign_id,
    string $quest_id,
    string $storyline_id,
    ?string $storyline_chapter_id = NULL,
    ?string $storyline_scene_id = NULL,
  ): void {
    $normalized_quest_id = trim($quest_id);
    $normalized_storyline_id = trim($storyline_id);
    if ($campaign_id <= 0 || $normalized_quest_id === '' || $normalized_storyline_id === '') {
      return;
    }

    $this->database->update('dc_campaign_quests')
      ->fields([
        'storyline_id' => $normalized_storyline_id,
        'storyline_chapter_id' => $storyline_chapter_id !== NULL && trim($storyline_chapter_id) !== '' ? trim($storyline_chapter_id) : NULL,
        'storyline_scene_id' => $storyline_scene_id !== NULL && trim($storyline_scene_id) !== '' ? trim($storyline_scene_id) : NULL,
      ])
      ->condition('campaign_id', $campaign_id)
      ->condition('quest_id', $normalized_quest_id)
      ->execute();
  }

  /**
   * Execute operation while holding template-scoped lifecycle lock.
   *
   * @template T
   * @param callable():T $operation
   *
   * @return T
   */
  protected function withTemplateLock(int $campaign_id, string $template_id, callable $operation) {
    $lock_name = sprintf('dungeoncrawler_content:quest_template:%d:%s', $campaign_id, $template_id);
    if (!$this->lock->acquire($lock_name, 10.0)) {
      throw new \RuntimeException(sprintf(
        'Quest lifecycle lock acquisition failed for campaign %d template %s.',
        $campaign_id,
        $template_id
      ));
    }

    try {
      return $operation();
    }
    finally {
      $this->lock->release($lock_name);
    }
  }

  /**
   * Collapse duplicate open rows for one template to a single canonical row.
   *
   * @param array<int, array{id:int,quest_id:string,status:string,created_at:int}> $open_rows
   */
  protected function collapseDuplicateOpenTemplateRows(int $campaign_id, string $template_id, array $open_rows): void {
    if (count($open_rows) <= 1) {
      return;
    }

    $rank = ['active' => 0, 'ready_for_turn_in' => 1, 'offered' => 2, 'lead' => 3, 'available' => 4];
    usort($open_rows, static function (array $a, array $b) use ($rank): int {
      $rank_cmp = ($rank[$a['status']] ?? 99) <=> ($rank[$b['status']] ?? 99);
      if ($rank_cmp !== 0) {
        return $rank_cmp;
      }
      $created_cmp = ((int) ($b['created_at'] ?? 0)) <=> ((int) ($a['created_at'] ?? 0));
      if ($created_cmp !== 0) {
        return $created_cmp;
      }
      return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
    });

    $keeper_quest_id = (string) ($open_rows[0]['quest_id'] ?? '');
    if ($keeper_quest_id === '') {
      throw new \RuntimeException(sprintf(
        'Quest lifecycle contract violation: unable to resolve canonical quest row for campaign %d template %s.',
        $campaign_id,
        $template_id
      ));
    }

    foreach (array_slice($open_rows, 1) as $duplicate_row) {
      $duplicate_quest_id = (string) ($duplicate_row['quest_id'] ?? '');
      if ($duplicate_quest_id === '' || $duplicate_quest_id === $keeper_quest_id) {
        continue;
      }
      $this->database->update('dc_campaign_quests')
        ->fields(['status' => 'superseded'])
        ->condition('campaign_id', $campaign_id)
        ->condition('quest_id', $duplicate_quest_id)
        ->execute();
    }
  }

  /**
   * Enforce quest-destination room materialization from persisted objectives.
   *
   * @param array<string, mixed>|null $quest_row
   *   Existing quest row payload.
   */
  protected function ensureObjectiveDestinationRoomsMaterializedForQuestRow(int $campaign_id, ?array $quest_row): void {
    if ($campaign_id <= 0 || !is_array($quest_row) || $quest_row === []) {
      return;
    }
    $this->resolveQuestGeneratorService()
      ->ensureQuestObjectiveDestinationRoomsMaterialized($campaign_id, $quest_row);
  }

  /**
   * Resolve quest generator service from lifecycle context.
   */
  protected function resolveQuestGeneratorService(): QuestGeneratorService {
    if (!\Drupal::hasService('dungeoncrawler_content.quest_generator')) {
      throw new \RuntimeException('QuestGeneratorService is required for quest destination room materialization.');
    }
    $service = \Drupal::service('dungeoncrawler_content.quest_generator');
    if (!($service instanceof QuestGeneratorService)) {
      throw new \RuntimeException('Quest generator service does not implement QuestGeneratorService.');
    }
    return $service;
  }

}
