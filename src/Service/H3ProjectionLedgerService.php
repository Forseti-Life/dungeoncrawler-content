<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Durable status/readiness ledger for campaign H3 projection workflows.
 */
class H3ProjectionLedgerService {

  public const STATUS_PENDING = 'pending';
  public const STATUS_IN_PROGRESS = 'in_progress';
  public const STATUS_COMPLETE = 'complete';
  public const STATUS_FAILED = 'failed';
  public const SCOPE_LAUNCH_SLICE = 'launch_slice';

  public function __construct(
    protected readonly Connection $database,
  ) {}

  /**
   * Load projection status for one campaign dungeon graph-version tuple.
   *
   * @return array<string, mixed>|null
   *   Ledger row or NULL when absent.
   */
  public function loadStatus(int $campaign_id, string $dungeon_id, string $canonical_graph_version, string $campaign_graph_version): ?array {
    $row = $this->database->select('dc_campaign_h3_projection_status', 's')
      ->fields('s')
      ->condition('campaign_id', $campaign_id)
      ->condition('dungeon_id', $dungeon_id)
      ->condition('canonical_graph_version', $canonical_graph_version)
      ->condition('campaign_graph_version', $campaign_graph_version)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    return is_array($row) ? $row : NULL;
  }

  /**
   * Upsert projection status row.
   *
   * @param array<string, mixed> $fields
   *   Optional additional fields to write.
   */
  public function upsertStatus(int $campaign_id, string $dungeon_id, string $canonical_graph_version, string $campaign_graph_version, string $status, array $fields = []): void {
    $now = time();
    $status = $this->normalizeStatus($status);
    $base_fields = [
      'status' => $status,
      'updated' => $now,
    ] + $fields;
    if (!array_key_exists('created', $base_fields)) {
      $base_fields['created'] = $now;
    }
    $this->database->merge('dc_campaign_h3_projection_status')
      ->keys([
        'campaign_id' => $campaign_id,
        'dungeon_id' => $dungeon_id,
        'canonical_graph_version' => $canonical_graph_version,
        'campaign_graph_version' => $campaign_graph_version,
      ])
      ->fields($base_fields)
      ->execute();
  }

  /**
   * Mark a projection job as running.
   */
  public function markInProgress(int $campaign_id, string $dungeon_id, string $canonical_graph_version, string $campaign_graph_version, string $job_id): void {
    $this->upsertStatus(
      $campaign_id,
      $dungeon_id,
      $canonical_graph_version,
      $campaign_graph_version,
      self::STATUS_IN_PROGRESS,
      [
        'active_job_id' => trim($job_id) !== '' ? $job_id : NULL,
        'last_started' => time(),
        'last_error' => NULL,
        'failed_room_id' => NULL,
      ]
    );
  }

  /**
   * Mark projection completion.
   */
  public function markComplete(int $campaign_id, string $dungeon_id, string $canonical_graph_version, string $campaign_graph_version, bool $critical_scope_complete, bool $full_scope_complete): void {
    $this->upsertStatus(
      $campaign_id,
      $dungeon_id,
      $canonical_graph_version,
      $campaign_graph_version,
      self::STATUS_COMPLETE,
      [
        'critical_scope_complete' => $critical_scope_complete ? 1 : 0,
        'full_scope_complete' => $full_scope_complete ? 1 : 0,
        'active_job_id' => NULL,
        'last_completed' => time(),
        'last_error' => NULL,
        'failed_room_id' => NULL,
      ]
    );
  }

  /**
   * Mark projection failure with diagnostic metadata.
   */
  public function markFailed(int $campaign_id, string $dungeon_id, string $canonical_graph_version, string $campaign_graph_version, string $error_message, ?string $failed_room_id = NULL): void {
    $this->upsertStatus(
      $campaign_id,
      $dungeon_id,
      $canonical_graph_version,
      $campaign_graph_version,
      self::STATUS_FAILED,
      [
        'active_job_id' => NULL,
        'last_error' => $error_message,
        'failed_room_id' => $failed_room_id !== NULL && trim($failed_room_id) !== '' ? trim($failed_room_id) : NULL,
      ]
    );
  }

  /**
   * Upsert room-level readiness status.
   */
  public function upsertRoomStatus(
    int $campaign_id,
    string $dungeon_id,
    string $room_id,
    string $canonical_graph_version,
    string $campaign_graph_version,
    string $scope,
    string $status,
    bool $ready,
    ?string $last_error = NULL,
  ): void {
    $now = time();
    $scope = trim($scope);
    if ($scope === '') {
      throw new \InvalidArgumentException('H3 projection room status requires non-empty scope.');
    }

    $this->database->merge('dc_campaign_h3_projection_room_status')
      ->keys([
        'campaign_id' => $campaign_id,
        'dungeon_id' => $dungeon_id,
        'room_id' => $room_id,
        'scope' => $scope,
        'canonical_graph_version' => $canonical_graph_version,
        'campaign_graph_version' => $campaign_graph_version,
      ])
      ->fields([
        'status' => $this->normalizeStatus($status),
        'ready' => $ready ? 1 : 0,
        'ready_at' => $ready ? $now : NULL,
        'last_error' => $last_error,
        'created' => $now,
        'updated' => $now,
      ])
      ->execute();
  }

  /**
   * Return true when all requested rooms are ready for one scope+version tuple.
   *
   * @param array<int, string> $room_ids
   *   Required room identifiers.
   */
  public function areRoomsReady(
    int $campaign_id,
    string $dungeon_id,
    array $room_ids,
    string $canonical_graph_version,
    string $campaign_graph_version,
    string $scope = self::SCOPE_LAUNCH_SLICE,
  ): bool {
    $room_ids = array_values(array_unique(array_filter(array_map('trim', $room_ids), static fn(string $room_id): bool => $room_id !== '')));
    if ($room_ids === []) {
      return FALSE;
    }

    $ready_rows = $this->database->select('dc_campaign_h3_projection_room_status', 'r')
      ->fields('r', ['room_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('dungeon_id', $dungeon_id)
      ->condition('canonical_graph_version', $canonical_graph_version)
      ->condition('campaign_graph_version', $campaign_graph_version)
      ->condition('scope', $scope)
      ->condition('room_id', $room_ids, 'IN')
      ->condition('ready', 1)
      ->execute()
      ->fetchCol() ?: [];

    $ready_set = array_fill_keys(array_map('strval', $ready_rows), TRUE);
    foreach ($room_ids as $room_id) {
      if (!isset($ready_set[$room_id])) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Normalize and validate allowed status values.
   */
  protected function normalizeStatus(string $status): string {
    $status = trim($status);
    $allowed = [
      self::STATUS_PENDING => TRUE,
      self::STATUS_IN_PROGRESS => TRUE,
      self::STATUS_COMPLETE => TRUE,
      self::STATUS_FAILED => TRUE,
    ];
    if (!isset($allowed[$status])) {
      throw new \InvalidArgumentException(sprintf('Unsupported H3 projection status "%s".', $status));
    }
    return $status;
  }

}
