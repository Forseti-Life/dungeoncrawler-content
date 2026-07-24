<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Psr\Log\LoggerInterface;

/**
 * Campaign H3 projection provisioning and readiness orchestration.
 */
class H3ProjectionQueueService {

  public const QUEUE_ID = 'dungeoncrawler_content.h3_projection_hydration';

  protected LoggerInterface $logger;

  public function __construct(
    protected readonly Connection $database,
    protected readonly QueueFactory $queueFactory,
    protected readonly LockBackendInterface $lock,
    protected readonly GraphVersionService $graphVersionService,
    protected readonly H3ProjectionLedgerService $ledger,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('dungeoncrawler_h3_projection');
  }

  /**
   * Queue-based entrypoint is retired; launch/expansion must provision synchronously.
   *
   * @param array<int, string> $room_ids
   *   Legacy queue scope room ids.
   *
   * @return array<string, mixed>
   *   Never returns; always throws.
   */
  public function enqueueHydrationJob(int $campaign_id, string $dungeon_id, array $room_ids, string $scope = H3ProjectionLedgerService::SCOPE_LAUNCH_SLICE): array {
    throw new \RuntimeException('H3 projection queue path is disabled. Use provisionLaunchSliceNow() at authoritative mutation/creation points.');
  }

  /**
   * Ensure launch-slice hydration readiness without doing request-thread writes.
   *
   * @param array<int, string> $room_ids
   *   Launch-critical room scope.
   *
   * @return array<string, mixed>
   *   Readiness metadata (`ready`, `status`, `reason`, versions, optional job/error).
   */
  public function ensureLaunchSliceReadiness(int $campaign_id, string $dungeon_id, array $room_ids): array {
    $campaign_id = (int) $campaign_id;
    $dungeon_id = trim($dungeon_id);
    $room_ids = $this->normalizeRoomIds($room_ids);
    if ($campaign_id <= 0 || $dungeon_id === '' || $room_ids === []) {
      throw new \InvalidArgumentException('H3 projection launch-slice readiness requires campaign_id, dungeon_id, and non-empty room_ids.');
    }

    $versions = $this->resolveProjectionVersions($campaign_id, $dungeon_id);
    $canonical_graph_version = $versions['canonical_graph_version'];
    $campaign_graph_version = $versions['campaign_graph_version'];
    if ($this->ledger->areRoomsReady($campaign_id, $dungeon_id, $room_ids, $canonical_graph_version, $campaign_graph_version, H3ProjectionLedgerService::SCOPE_LAUNCH_SLICE)) {
      return [
        'ready' => TRUE,
        'status' => H3ProjectionLedgerService::STATUS_COMPLETE,
        'reason' => 'already_ready',
        'job_id' => NULL,
        'canonical_graph_version' => $canonical_graph_version,
        'campaign_graph_version' => $campaign_graph_version,
      ];
    }

    $status = $this->ledger->loadStatus($campaign_id, $dungeon_id, $canonical_graph_version, $campaign_graph_version);
    if (is_array($status) && trim((string) ($status['status'] ?? '')) === H3ProjectionLedgerService::STATUS_FAILED) {
      return [
        'ready' => FALSE,
        'status' => H3ProjectionLedgerService::STATUS_FAILED,
        'reason' => 'failed',
        'job_id' => (string) ($status['active_job_id'] ?? ''),
        'last_error' => (string) ($status['last_error'] ?? ''),
        'canonical_graph_version' => $canonical_graph_version,
        'campaign_graph_version' => $campaign_graph_version,
      ];
    }

    $rooms_ready = $this->ledger->areRoomsReady(
      $campaign_id,
      $dungeon_id,
      $room_ids,
      $canonical_graph_version,
      $campaign_graph_version,
      H3ProjectionLedgerService::SCOPE_LAUNCH_SLICE
    );
    return [
      'ready' => $rooms_ready,
      'status' => $rooms_ready
        ? H3ProjectionLedgerService::STATUS_COMPLETE
        : (is_array($status) ? (string) ($status['status'] ?? H3ProjectionLedgerService::STATUS_PENDING) : H3ProjectionLedgerService::STATUS_PENDING),
      'reason' => $rooms_ready ? 'already_ready' : 'readiness_missing',
      'job_id' => '',
      'canonical_graph_version' => $canonical_graph_version,
      'campaign_graph_version' => $campaign_graph_version,
    ];
  }

  /**
   * Provision one scope synchronously instead of deferring to queue workers.
   *
   * @param array<int, string> $room_ids
   *   Room scope to provision for campaign runtime.
   *
   * @return array<string, mixed>
   *   Provision metadata.
   */
  public function provisionLaunchSliceNow(int $campaign_id, string $dungeon_id, array $room_ids, string $scope = H3ProjectionLedgerService::SCOPE_LAUNCH_SLICE): array {
    $campaign_id = (int) $campaign_id;
    $dungeon_id = trim($dungeon_id);
    $scope = trim($scope);
    $room_ids = $this->normalizeRoomIds($room_ids);
    if ($campaign_id <= 0 || $dungeon_id === '' || $scope === '' || $room_ids === []) {
      throw new \InvalidArgumentException('H3 projection synchronous provision contract violation: campaign_id, dungeon_id, scope, and room_ids are required.');
    }

    $versions = $this->resolveProjectionVersions($campaign_id, $dungeon_id);
    $canonical_graph_version = $versions['canonical_graph_version'];
    $campaign_graph_version = $versions['campaign_graph_version'];
    if ($this->ledger->areRoomsReady($campaign_id, $dungeon_id, $room_ids, $canonical_graph_version, $campaign_graph_version, $scope)) {
      return [
        'ready' => TRUE,
        'reason' => 'already_ready',
        'job_id' => NULL,
        'canonical_graph_version' => $canonical_graph_version,
        'campaign_graph_version' => $campaign_graph_version,
      ];
    }

    $job_id = sprintf(
      'h3sync:%d:%s:%s',
      $campaign_id,
      substr(sha1($dungeon_id . ':' . $scope), 0, 12),
      substr(sha1($canonical_graph_version . ':' . $campaign_graph_version . ':' . implode(',', $room_ids)), 0, 12)
    );
    $this->processQueuedHydrationItem([
      'job_id' => $job_id,
      'campaign_id' => $campaign_id,
      'dungeon_id' => $dungeon_id,
      'scope' => $scope,
      'room_ids' => $room_ids,
      'canonical_graph_version' => $canonical_graph_version,
      'campaign_graph_version' => $campaign_graph_version,
    ]);

    $ready = $this->ledger->areRoomsReady($campaign_id, $dungeon_id, $room_ids, $canonical_graph_version, $campaign_graph_version, $scope);
    if (!$ready) {
      throw new \RuntimeException(sprintf(
        'H3 projection synchronous provision failed readiness certification for campaign %d dungeon %s scope %s.',
        $campaign_id,
        $dungeon_id,
        $scope
      ));
    }

    return [
      'ready' => TRUE,
      'reason' => 'provisioned_sync',
      'job_id' => $job_id,
      'canonical_graph_version' => $canonical_graph_version,
      'campaign_graph_version' => $campaign_graph_version,
    ];
  }

  /**
   * Process one queue item.
   *
   * @param array<string, mixed> $item
   *   Queue payload.
   */
  public function processQueuedHydrationItem(array $item): void {
    $campaign_id = (int) ($item['campaign_id'] ?? 0);
    $dungeon_id = trim((string) ($item['dungeon_id'] ?? ''));
    $scope = trim((string) ($item['scope'] ?? ''));
    $job_id = trim((string) ($item['job_id'] ?? ''));
    $canonical_graph_version = trim((string) ($item['canonical_graph_version'] ?? ''));
    $campaign_graph_version = trim((string) ($item['campaign_graph_version'] ?? ''));
    $room_ids = $this->normalizeRoomIds((array) ($item['room_ids'] ?? []));

    if ($campaign_id <= 0 || $dungeon_id === '' || $scope === '' || $job_id === '' || $canonical_graph_version === '' || $campaign_graph_version === '' || $room_ids === []) {
      throw new \InvalidArgumentException('H3 projection worker contract violation: malformed queue payload.');
    }

    $lock_name = $this->buildSingleFlightLockName($campaign_id, $dungeon_id, $canonical_graph_version, $campaign_graph_version, $scope);
    $lock_acquired = FALSE;
    $lock_wait_started_at = microtime(TRUE);
    $lock_wait_timeout_seconds = 15.0;
    $lock_wait_interval_microseconds = 100000;
    do {
      if ($this->lock->acquire($lock_name, 30.0)) {
        $lock_acquired = TRUE;
        break;
      }
      if ($this->ledger->areRoomsReady($campaign_id, $dungeon_id, $room_ids, $canonical_graph_version, $campaign_graph_version, $scope)) {
        return;
      }
      usleep($lock_wait_interval_microseconds);
    } while ((microtime(TRUE) - $lock_wait_started_at) < $lock_wait_timeout_seconds);

    if (!$lock_acquired) {
      if ($this->ledger->areRoomsReady($campaign_id, $dungeon_id, $room_ids, $canonical_graph_version, $campaign_graph_version, $scope)) {
        return;
      }
      throw new \RuntimeException(sprintf(
        'H3 projection worker lock acquisition failed for campaign %d dungeon %s scope %s after waiting %.1f seconds.',
        $campaign_id,
        $dungeon_id,
        $scope,
        microtime(TRUE) - $lock_wait_started_at
      ));
    }

    $processed_room_count = 0;
    $anchor_writes = 0;
    $cell_writes = 0;
    try {
      $this->ledger->markInProgress($campaign_id, $dungeon_id, $canonical_graph_version, $campaign_graph_version, $job_id);
      foreach ($room_ids as $room_id) {
        $this->ledger->upsertRoomStatus(
          $campaign_id,
          $dungeon_id,
          $room_id,
          $canonical_graph_version,
          $campaign_graph_version,
          $scope,
          H3ProjectionLedgerService::STATUS_IN_PROGRESS,
          FALSE
        );

        $coverage = $this->projectRoomCoverageFromCanonical($dungeon_id, $room_id);
        $processed_room_count++;
        $anchor_writes += (int) ($coverage['anchor_writes'] ?? 0);
        $cell_writes += (int) ($coverage['cell_writes'] ?? 0);

        $this->ledger->upsertRoomStatus(
          $campaign_id,
          $dungeon_id,
          $room_id,
          $canonical_graph_version,
          $campaign_graph_version,
          $scope,
          H3ProjectionLedgerService::STATUS_COMPLETE,
          TRUE
        );
      }

      $critical_scope_complete = $scope === H3ProjectionLedgerService::SCOPE_LAUNCH_SLICE
        ? $this->ledger->areRoomsReady($campaign_id, $dungeon_id, $room_ids, $canonical_graph_version, $campaign_graph_version, $scope)
        : FALSE;
      $this->ledger->markComplete(
        $campaign_id,
        $dungeon_id,
        $canonical_graph_version,
        $campaign_graph_version,
        $critical_scope_complete,
        FALSE
      );

      $this->logger->notice('Completed H3 projection hydration job: campaign_id={campaign_id} dungeon_id={dungeon_id} scope={scope} room_count={room_count} anchor_writes={anchor_writes} cell_writes={cell_writes} job_id={job_id}', [
        'campaign_id' => $campaign_id,
        'dungeon_id' => $dungeon_id,
        'scope' => $scope,
        'room_count' => $processed_room_count,
        'anchor_writes' => $anchor_writes,
        'cell_writes' => $cell_writes,
        'job_id' => $job_id,
      ]);
    }
    catch (\Throwable $e) {
      $failed_room_id = NULL;
      foreach ($room_ids as $room_id) {
        if (!$this->ledger->areRoomsReady($campaign_id, $dungeon_id, [$room_id], $canonical_graph_version, $campaign_graph_version, $scope)) {
          $failed_room_id = $room_id;
          break;
        }
      }
      $this->ledger->markFailed($campaign_id, $dungeon_id, $canonical_graph_version, $campaign_graph_version, $e->getMessage(), $failed_room_id);
      if ($failed_room_id !== NULL) {
        $this->ledger->upsertRoomStatus(
          $campaign_id,
          $dungeon_id,
          $failed_room_id,
          $canonical_graph_version,
          $campaign_graph_version,
          $scope,
          H3ProjectionLedgerService::STATUS_FAILED,
          FALSE,
          $e->getMessage()
        );
      }
      throw $e;
    }
    finally {
      $this->lock->release($lock_name);
    }
  }

  /**
   * Copy authoritative canonical room anchor/cells into one campaign dungeon.
   *
   * @return array{anchor_writes:int,cell_writes:int}
   *   Write counts.
   */
  protected function projectRoomCoverageFromCanonical(string $dungeon_id, string $room_id): array {
    $now = time();

    $source_anchor_query = $this->database->select('dungeoncrawler_content_h3_room_anchors', 'a')
      ->fields('a', ['dungeon_id', 'room_id', 'h3_resolution', 'h3_index', 'center_latitude', 'center_longitude', 'reference_q', 'reference_r', 'hex_size_meters', 'metadata'])
      ->condition('a.room_id', $room_id)
      ->orderBy('a.h3_resolution', 'DESC')
      ->orderBy('a.id', 'ASC')
      ->range(0, 1);
    $source_anchor = $source_anchor_query
      ->condition('a.dungeon_id', $dungeon_id, '<>')
      ->execute()
      ->fetchAssoc();
    if (!is_array($source_anchor)) {
      $source_anchor = $this->database->select('dungeoncrawler_content_h3_room_anchors', 'a')
        ->fields('a', ['dungeon_id', 'room_id', 'h3_resolution', 'h3_index', 'center_latitude', 'center_longitude', 'reference_q', 'reference_r', 'hex_size_meters', 'metadata'])
        ->condition('a.room_id', $room_id)
        ->orderBy('a.h3_resolution', 'DESC')
        ->orderBy('a.id', 'ASC')
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
    }
    if (!is_array($source_anchor)) {
      throw new \RuntimeException(sprintf(
        'H3 projection hydration contract violation: no canonical sparse room-anchor rows found for room %s.',
        $room_id
      ));
    }
    $source_dungeon_id = trim((string) ($source_anchor['dungeon_id'] ?? ''));
    if ($source_dungeon_id === '') {
      throw new \RuntimeException(sprintf(
        'H3 projection hydration contract violation: no source dungeon_id available for room %s.',
        $room_id
      ));
    }

    $source_cells = $this->database->select('dungeoncrawler_content_h3_room_cells', 'c')
      ->fields('c', ['cell_role', 'h3_resolution', 'h3_index', 'source_q', 'source_r', 'center_latitude', 'center_longitude', 'metadata'])
      ->condition('c.dungeon_id', $source_dungeon_id)
      ->condition('c.room_id', $room_id)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    if ($source_cells === []) {
      throw new \RuntimeException(sprintf(
        'H3 projection hydration contract violation: no canonical sparse room-cell rows found for room %s.',
        $room_id
      ));
    }

    $this->database->merge('dungeoncrawler_content_h3_room_anchors')
      ->keys([
        'dungeon_id' => $dungeon_id,
        'room_id' => $room_id,
      ])
      ->fields([
        'h3_resolution' => (int) ($source_anchor['h3_resolution'] ?? 14),
        'h3_index' => (string) ($source_anchor['h3_index'] ?? ''),
        'center_latitude' => isset($source_anchor['center_latitude']) && is_numeric($source_anchor['center_latitude']) ? (float) $source_anchor['center_latitude'] : NULL,
        'center_longitude' => isset($source_anchor['center_longitude']) && is_numeric($source_anchor['center_longitude']) ? (float) $source_anchor['center_longitude'] : NULL,
        'reference_q' => (int) ($source_anchor['reference_q'] ?? 0),
        'reference_r' => (int) ($source_anchor['reference_r'] ?? 0),
        'hex_size_meters' => isset($source_anchor['hex_size_meters']) && is_numeric($source_anchor['hex_size_meters']) ? (float) $source_anchor['hex_size_meters'] : 1.524,
        'metadata' => (string) ($source_anchor['metadata'] ?? ''),
        'created' => $now,
        'updated' => $now,
      ])
      ->execute();

    $cell_writes = 0;
    foreach ($source_cells as $source_cell) {
      if (!is_array($source_cell)) {
        continue;
      }
      if (!is_numeric($source_cell['source_q'] ?? NULL) || !is_numeric($source_cell['source_r'] ?? NULL)) {
        continue;
      }
      $this->database->merge('dungeoncrawler_content_h3_room_cells')
        ->keys([
          'dungeon_id' => $dungeon_id,
          'room_id' => $room_id,
          'cell_role' => (string) ($source_cell['cell_role'] ?? 'room_hex'),
          'h3_resolution' => (int) ($source_cell['h3_resolution'] ?? 14),
          'h3_index' => (string) ($source_cell['h3_index'] ?? ''),
          'source_q' => (int) $source_cell['source_q'],
          'source_r' => (int) $source_cell['source_r'],
        ])
        ->fields([
          'center_latitude' => isset($source_cell['center_latitude']) && is_numeric($source_cell['center_latitude']) ? (float) $source_cell['center_latitude'] : NULL,
          'center_longitude' => isset($source_cell['center_longitude']) && is_numeric($source_cell['center_longitude']) ? (float) $source_cell['center_longitude'] : NULL,
          'metadata' => (string) ($source_cell['metadata'] ?? ''),
          'created' => $now,
          'updated' => $now,
        ])
        ->execute();
      $cell_writes++;
    }

    return [
      'anchor_writes' => 1,
      'cell_writes' => $cell_writes,
    ];
  }

  /**
   * Normalize room id list.
   *
   * @param array<int, string> $room_ids
   *   Candidate room ids.
   *
   * @return array<int, string>
   *   Unique, non-empty room ids.
   */
  protected function normalizeRoomIds(array $room_ids): array {
    return array_values(array_unique(array_filter(array_map('trim', array_map('strval', $room_ids)), static fn(string $room_id): bool => $room_id !== '')));
  }

  /**
   * Resolve campaign-dungeon projection versions independent of room subset.
   *
   * Readiness/provisioning calls may pass different room subsets for the same
   * campaign transition path. Version tokens must stay stable across those
   * subsets so room-level readiness rows can be reused deterministically.
   *
   * @return array{canonical_graph_version:string,campaign_graph_version:string}
   *   Stable campaign-dungeon graph version tokens.
   */
  protected function resolveProjectionVersions(int $campaign_id, string $dungeon_id): array {
    $version_metadata = $this->graphVersionService->buildVersionMetadata($campaign_id, $dungeon_id);
    $canonical_graph_version = trim((string) ($version_metadata['canonical_graph_version'] ?? ''));
    $campaign_graph_version = trim((string) ($version_metadata['campaign_graph_version'] ?? ''));
    if ($canonical_graph_version === '' || $campaign_graph_version === '') {
      throw new \RuntimeException(sprintf(
        'H3 projection version contract violation for campaign %d dungeon %s.',
        $campaign_id,
        $dungeon_id
      ));
    }
    return [
      'canonical_graph_version' => $canonical_graph_version,
      'campaign_graph_version' => $campaign_graph_version,
    ];
  }

  /**
   * Build single-flight lock key for one scope+version tuple.
   */
  protected function buildSingleFlightLockName(int $campaign_id, string $dungeon_id, string $canonical_graph_version, string $campaign_graph_version, string $scope): string {
    return sprintf(
      'dungeoncrawler_content:h3_projection:%d:%s:%s:%s:%s',
      $campaign_id,
      $dungeon_id,
      $canonical_graph_version,
      $campaign_graph_version,
      $scope
    );
  }

}
