<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;

/**
 * Stores and resolves ambiguous quest touchpoint confirmations.
 */
class QuestConfirmationService {

  /**
   * Quest confirmation table name.
   */
  protected const TABLE = 'dc_campaign_quest_confirmations';

  /**
   * Database connection.
   */
  protected Connection $database;

  /**
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected UuidInterface $uuid;

  /**
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * Constructs the confirmation service.
   */
  public function __construct(
    Connection $database,
    UuidInterface $uuid,
    TimeInterface $time
  ) {
    $this->database = $database;
    $this->uuid = $uuid;
    $this->time = $time;
  }

  /**
   * Create a pending confirmation entry.
   */
  public function createPending(
    int $campaign_id,
    int $character_id,
    array $touchpoint_event,
    array $candidates,
    string $prompt = '',
    int $ttl_seconds = 3600
  ): array {
    $now = $this->time->getRequestTime();
    $id = 'qcf_' . str_replace('-', '', $this->uuid->generate());

    $entry = [
      'confirmation_id' => $id,
      'campaign_id' => $campaign_id,
      'character_id' => $character_id,
      'status' => 'pending',
      'created_at' => $now,
      'expires_at' => $now + max(60, $ttl_seconds),
      'touchpoint_event' => $touchpoint_event,
      'candidates' => array_values($candidates),
      'prompt' => $prompt,
    ];

    $this->database->insert(static::TABLE)
      ->fields($this->toStorageRow($entry))
      ->execute();

    return $entry;
  }

  /**
   * Get pending confirmations by campaign and optional character.
   */
  public function listPending(int $campaign_id, ?int $character_id = NULL): array {
    $this->expireStaleConfirmations($campaign_id, $character_id);

    $query = $this->database->select(static::TABLE, 'q')
      ->fields('q')
      ->condition('campaign_id', $campaign_id)
      ->condition('status', 'pending')
      ->orderBy('created_at', 'DESC');

    if ($character_id !== NULL) {
      $query->condition('character_id', $character_id);
    }

    $rows = $query->execute()->fetchAllAssoc('confirmation_id');
    return array_values(array_map([$this, 'fromStorageRow'], $rows ?: []));
  }

  /**
   * Resolve a confirmation.
   */
  public function resolve(
    string $confirmation_id,
    string $resolution,
    ?string $selected_objective_id,
    string $resolved_by = 'player'
  ): ?array {
    $entry = $this->get($confirmation_id);
    if ($entry === NULL) {
      return NULL;
    }

    if (($entry['status'] ?? '') !== 'pending') {
      return $entry;
    }

    $status = strtolower($resolution) === 'approved' ? 'approved' : 'rejected';
    $entry['status'] = $status;
    $entry['selected_objective_id'] = $selected_objective_id;
    $entry['resolved_by'] = $resolved_by;
    $entry['resolved_at'] = $this->time->getRequestTime();

    $this->database->update(static::TABLE)
      ->fields([
        'status' => $entry['status'],
        'selected_objective_id' => $entry['selected_objective_id'],
        'resolved_by' => $entry['resolved_by'],
        'resolved_at' => $entry['resolved_at'],
      ])
      ->condition('confirmation_id', $confirmation_id)
      ->execute();

    return $entry;
  }

  /**
   * Lookup a confirmation by id.
   */
  public function get(string $confirmation_id): ?array {
    $row = $this->database->select(static::TABLE, 'q')
      ->fields('q')
      ->condition('confirmation_id', $confirmation_id)
      ->execute()
      ->fetchAssoc();

    if (!is_array($row)) {
      return NULL;
    }

    $entry = $this->fromStorageRow($row);
    $expires_at = (int) ($entry['expires_at'] ?? 0);
    $now = $this->time->getRequestTime();
    if (($entry['status'] ?? '') === 'pending' && $expires_at > 0 && $expires_at < $now) {
      $this->database->update(static::TABLE)
        ->fields([
          'status' => 'expired',
          'resolved_by' => 'system',
          'resolved_at' => $now,
        ])
        ->condition('confirmation_id', $confirmation_id)
        ->execute();
      $entry['status'] = 'expired';
      $entry['resolved_by'] = 'system';
      $entry['resolved_at'] = $now;
    }

    return $entry;
  }

  /**
   * Expire stale pending confirmations before reading the queue.
   */
  protected function expireStaleConfirmations(int $campaign_id, ?int $character_id = NULL): void {
    $now = $this->time->getRequestTime();
    $query = $this->database->update(static::TABLE)
      ->fields([
        'status' => 'expired',
        'resolved_by' => 'system',
        'resolved_at' => $now,
      ])
      ->condition('campaign_id', $campaign_id)
      ->condition('status', 'pending')
      ->condition('expires_at', 0, '>')
      ->condition('expires_at', $now, '<');

    if ($character_id !== NULL) {
      $query->condition('character_id', $character_id);
    }

    $query->execute();
  }

  /**
   * Normalize a database row to the public confirmation payload shape.
   */
  protected function fromStorageRow($row): array {
    if (is_object($row)) {
      $row = (array) $row;
    }

    return [
      'confirmation_id' => (string) ($row['confirmation_id'] ?? ''),
      'campaign_id' => (int) ($row['campaign_id'] ?? 0),
      'character_id' => isset($row['character_id']) ? (int) $row['character_id'] : 0,
      'status' => (string) ($row['status'] ?? 'pending'),
      'created_at' => (int) ($row['created_at'] ?? 0),
      'expires_at' => isset($row['expires_at']) ? (int) $row['expires_at'] : NULL,
      'resolved_at' => isset($row['resolved_at']) ? (int) $row['resolved_at'] : NULL,
      'resolved_by' => isset($row['resolved_by']) ? (string) $row['resolved_by'] : NULL,
      'selected_objective_id' => isset($row['selected_objective_id']) ? (string) $row['selected_objective_id'] : NULL,
      'touchpoint_event' => $this->decodeJsonField($row['touchpoint_event'] ?? '[]', []),
      'candidates' => $this->decodeJsonField($row['candidates'] ?? '[]', []),
      'prompt' => isset($row['prompt']) ? (string) $row['prompt'] : '',
    ];
  }

  /**
   * Normalize a public confirmation payload into storage fields.
   */
  protected function toStorageRow(array $entry): array {
    return [
      'confirmation_id' => (string) ($entry['confirmation_id'] ?? ''),
      'campaign_id' => (int) ($entry['campaign_id'] ?? 0),
      'character_id' => isset($entry['character_id']) ? (int) $entry['character_id'] : NULL,
      'status' => (string) ($entry['status'] ?? 'pending'),
      'prompt' => isset($entry['prompt']) ? (string) $entry['prompt'] : NULL,
      'touchpoint_event' => json_encode($entry['touchpoint_event'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'candidates' => json_encode($entry['candidates'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'selected_objective_id' => isset($entry['selected_objective_id']) ? (string) $entry['selected_objective_id'] : NULL,
      'resolved_by' => isset($entry['resolved_by']) ? (string) $entry['resolved_by'] : NULL,
      'created_at' => (int) ($entry['created_at'] ?? 0),
      'expires_at' => isset($entry['expires_at']) ? (int) $entry['expires_at'] : NULL,
      'resolved_at' => isset($entry['resolved_at']) ? (int) $entry['resolved_at'] : NULL,
    ];
  }

  /**
   * Decode stored JSON fields without producing scalar/null payloads.
   *
   * @param mixed $value
   *   Raw database value.
   * @param array $default
   *   Default array value.
   */
  protected function decodeJsonField($value, array $default): array {
    if (is_array($value)) {
      return $value;
    }

    $decoded = json_decode((string) $value, TRUE);
    return is_array($decoded) ? $decoded : $default;
  }

}
