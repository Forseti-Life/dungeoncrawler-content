<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Authoritative runtime bootstrap boundary for campaign play state.
 */
class RuntimeBootstrapService {

  protected const INIT_PHASE_STRUCTURAL_READY = 'structural_ready';
  protected const INIT_PHASE_RUNTIME_READY = 'runtime_ready';

  protected Connection $database;

  protected CampaignCharacterRuntimeSyncService $campaignCharacterRuntimeSync;

  protected LoggerInterface $logger;

  protected DungeonPayloadStatePersistenceService $dungeonPayloadStatePersistence;

  public function __construct(
    Connection $database,
    CampaignCharacterRuntimeSyncService $campaign_character_runtime_sync,
    DungeonPayloadStatePersistenceService $dungeon_payload_state_persistence,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->database = $database;
    $this->campaignCharacterRuntimeSync = $campaign_character_runtime_sync;
    $this->dungeonPayloadStatePersistence = $dungeon_payload_state_persistence;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
  }

  /**
   * Ensure campaign runtime state is materialized for a specific runtime row.
   */
  public function ensureRuntimeReady(int $campaign_id, int $runtime_character_id): void {
    if ($campaign_id <= 0 || $runtime_character_id <= 0) {
      throw new \RuntimeException('Runtime bootstrap contract violation: campaign_id and runtime_character_id are required.');
    }

    $campaign = $this->loadCampaign($campaign_id);
    $campaign_data = $this->decodeCampaignData($campaign_id, (string) ($campaign['campaign_data'] ?? '{}'));
    $init = $this->extractInitStateOrAdoptLegacy($campaign_id, $campaign_data);
    $phase = (string) ($init['phase'] ?? '');
    if ($phase !== self::INIT_PHASE_STRUCTURAL_READY && $phase !== self::INIT_PHASE_RUNTIME_READY) {
      throw new \RuntimeException(sprintf(
        'Runtime bootstrap contract violation: campaign %d init phase must be %s or %s; got "%s".',
        $campaign_id,
        self::INIT_PHASE_STRUCTURAL_READY,
        self::INIT_PHASE_RUNTIME_READY,
        $phase
      ));
    }

    $runtime_row = $this->loadRuntimeCharacterRow($campaign_id, $runtime_character_id);
    $instance_id = trim((string) ($runtime_row['instance_id'] ?? ''));
    if ($instance_id === '') {
      throw new \RuntimeException(sprintf(
        'Runtime bootstrap contract violation: campaign %d runtime character row %d has empty instance_id.',
        $campaign_id,
        $runtime_character_id
      ));
    }

    $dungeon = $this->loadAuthoritativeDungeonRowForBootstrap($campaign_id, $campaign_data, $runtime_row);
    $dungeon_data = json_decode((string) ($dungeon['dungeon_data'] ?? '{}'), TRUE);
    if (!is_array($dungeon_data)) {
      throw new \RuntimeException(sprintf(
        'Runtime bootstrap contract violation: campaign %d latest dungeon payload is invalid JSON.',
        $campaign_id
      ));
    }

    $active_room_id = trim((string) ($dungeon_data['active_room_id'] ?? ''));
    if ($active_room_id === '') {
      $active_room_id = trim((string) ($runtime_row['last_room_id'] ?? ''));
      if ($active_room_id === '') {
        $rooms = is_array($dungeon_data['rooms'] ?? NULL) ? $dungeon_data['rooms'] : [];
        foreach ($rooms as $room) {
          if (!is_array($room)) {
            continue;
          }
          $candidate = trim((string) ($room['room_id'] ?? ''));
          if ($candidate !== '') {
            $active_room_id = $candidate;
            break;
          }
        }
      }
      if ($active_room_id === '') {
        throw new \RuntimeException(sprintf(
          'Runtime bootstrap contract violation: campaign %d has no active room and no usable room entries.',
          $campaign_id
        ));
      }
      $dungeon_data['active_room_id'] = $active_room_id;
    }

    $dungeon_data['campaign_id'] = $campaign_id;
    $dungeon_data = $this->campaignCharacterRuntimeSync->syncActiveRoomPlayerEntities($dungeon_data, $campaign_id, $instance_id);
    $this->assertActivePlayerParticipant($dungeon_data, $active_room_id, $instance_id, $campaign_id, $runtime_character_id);
    $dungeon_data['game_state'] = $this->normalizeGameState($dungeon_data, $active_room_id);

    $now = time();
    $updated = $this->dungeonPayloadStatePersistence->mutateByRowId(
      $campaign_id,
      (int) ($dungeon['id'] ?? 0),
      static fn(array $payload): array => $dungeon_data
    );
    if (!$updated) {
      throw new \RuntimeException(sprintf(
        'Runtime bootstrap contract violation: expected shared state lane to update exactly one dungeon row for campaign %d.',
        $campaign_id
      ));
    }

    $this->persistInitPhase($campaign_id, $campaign_data, self::INIT_PHASE_RUNTIME_READY, [
      'runtime_ready_for_character_id' => $runtime_character_id,
      'runtime_ready_for_instance_id' => $instance_id,
      'runtime_active_room_id' => $active_room_id,
      'runtime_dungeon_id' => (string) ($dungeon['dungeon_id'] ?? ''),
      'runtime_ready_at' => gmdate('c', $now),
    ]);
    $this->logger->info('Runtime bootstrap marked campaign {campaign_id} ready for runtime character {character_id}.', [
      'campaign_id' => $campaign_id,
      'character_id' => $runtime_character_id,
    ]);
  }

  /**
   * Assert that campaign runtime phase is materialized.
   */
  public function assertCampaignRuntimeReady(int $campaign_id): void {
    if ($campaign_id <= 0) {
      throw new \RuntimeException('Runtime bootstrap contract violation: campaign_id is required for runtime readiness assertion.');
    }
    $campaign = $this->loadCampaign($campaign_id);
    $campaign_data = $this->decodeCampaignData($campaign_id, (string) ($campaign['campaign_data'] ?? '{}'));
    $init = $this->extractInitState($campaign_id, $campaign_data);
    $phase = (string) ($init['phase'] ?? '');
    if ($phase !== self::INIT_PHASE_RUNTIME_READY && $phase !== self::INIT_PHASE_STRUCTURAL_READY) {
      throw new \RuntimeException(sprintf(
        'Runtime bootstrap contract violation: campaign %d requires init phase "%s" or "%s" before runtime state access (current="%s").',
        $campaign_id,
        self::INIT_PHASE_STRUCTURAL_READY,
        self::INIT_PHASE_RUNTIME_READY,
        $phase
      ));
    }
    $runtime_dungeon_id = trim((string) (
      $init['runtime_dungeon_id']
      ?? $init['context']['runtime_dungeon_id']
      ?? $init['context']['dungeon_id']
      ?? ''
    ));
    if ($runtime_dungeon_id === '') {
      $runtime_dungeon_id = trim((string) ($this->loadLatestDungeonRow($campaign_id)['dungeon_id'] ?? ''));
    }
    if ($runtime_dungeon_id === '') {
      throw new \RuntimeException(sprintf(
        'Runtime bootstrap contract violation: campaign %d runtime state access could not resolve runtime_dungeon_id.',
        $campaign_id
      ));
    }
    $row = $this->loadLatestDungeonRowByDungeonId($campaign_id, $runtime_dungeon_id);
    $dungeon_data = json_decode((string) ($row['dungeon_data'] ?? '{}'), TRUE);
    if (!is_array($dungeon_data)) {
      throw new \RuntimeException(sprintf(
        'Runtime bootstrap contract violation: campaign %d authoritative runtime dungeon payload is invalid JSON.',
        $campaign_id
      ));
    }
    $runtime_active_room_id = trim((string) (
      $dungeon_data['active_room_id']
      ?? $dungeon_data['current_room_id']
      ?? $init['runtime_active_room_id']
      ?? $init['context']['runtime_active_room_id']
      ?? $init['context']['starter_room_id']
      ?? ''
    ));
    if ($runtime_active_room_id === '') {
      $rooms = is_array($dungeon_data['rooms'] ?? NULL) ? $dungeon_data['rooms'] : [];
      foreach ($rooms as $room) {
        if (!is_array($room)) {
          continue;
        }
        $candidate_room_id = trim((string) ($room['room_id'] ?? ''));
        if ($candidate_room_id !== '') {
          $runtime_active_room_id = $candidate_room_id;
          break;
        }
      }
    }
    if ($runtime_active_room_id === '') {
      throw new \RuntimeException(sprintf(
        'Runtime bootstrap contract violation: campaign %d runtime state access could not resolve runtime_active_room_id.',
        $campaign_id
      ));
    }
    if (!$this->payloadContainsRoomId($dungeon_data, $runtime_active_room_id)) {
      throw new \RuntimeException(sprintf(
        'Runtime bootstrap contract violation: campaign %d runtime_dungeon_id %s does not contain runtime_active_room_id %s.',
        $campaign_id,
        $runtime_dungeon_id,
        $runtime_active_room_id
      ));
    }
  }

  /**
   * Load the authoritative runtime dungeon row for coordinator/runtime reads.
   */
  public function loadAuthoritativeDungeonRowForRuntimeRead(int $campaign_id, ?int $runtime_character_id = NULL): array {
    if ($campaign_id <= 0) {
      throw new \RuntimeException('Runtime bootstrap contract violation: campaign_id is required for runtime dungeon row resolution.');
    }
    $campaign = $this->loadCampaign($campaign_id);
    $campaign_data = $this->decodeCampaignData($campaign_id, (string) ($campaign['campaign_data'] ?? '{}'));
    $init = $this->extractInitState($campaign_id, $campaign_data);
    $phase = trim((string) ($init['phase'] ?? ''));
    if ($phase !== self::INIT_PHASE_RUNTIME_READY && $phase !== self::INIT_PHASE_STRUCTURAL_READY) {
      throw new \RuntimeException(sprintf(
        'Runtime bootstrap contract violation: campaign %d must be structural_ready or runtime_ready for runtime dungeon reads (current=%s).',
        $campaign_id,
        $phase
      ));
    }

    $runtime_dungeon_id = trim((string) (
      $init['runtime_dungeon_id']
      ?? $init['context']['runtime_dungeon_id']
      ?? $init['context']['dungeon_id']
      ?? ''
    ));
    if ($runtime_dungeon_id === '') {
      $latest_row = $this->loadLatestDungeonRow($campaign_id);
      $runtime_dungeon_id = trim((string) ($latest_row['dungeon_id'] ?? ''));
      if ($runtime_dungeon_id === '') {
        throw new \RuntimeException(sprintf(
          'Runtime bootstrap contract violation: campaign %d runtime dungeon read could not resolve dungeon_id.',
          $campaign_id
        ));
      }
    }

    $row = $this->loadLatestDungeonRowByDungeonId($campaign_id, $runtime_dungeon_id);
    $dungeon_data = json_decode((string) ($row['dungeon_data'] ?? '{}'), TRUE);
    if (!is_array($dungeon_data)) {
      throw new \RuntimeException(sprintf(
        'Runtime bootstrap contract violation: campaign %d authoritative dungeon %s payload is invalid JSON.',
        $campaign_id,
        $runtime_dungeon_id
      ));
    }

    $expected_room_id = trim((string) (
      $dungeon_data['active_room_id']
      ?? $dungeon_data['current_room_id']
      ?? ''
    ));
    if ($expected_room_id === '' && $runtime_character_id !== NULL && $runtime_character_id > 0) {
      $runtime_row = $this->loadRuntimeCharacterRow($campaign_id, $runtime_character_id);
      $runtime_row_room_id = trim((string) ($runtime_row['last_room_id'] ?? ''));
      if ($runtime_row_room_id !== '') {
        $expected_room_id = $runtime_row_room_id;
      }
    }
    if ($expected_room_id === '') {
      $expected_room_id = trim((string) (
        $init['runtime_active_room_id']
        ?? $init['context']['runtime_active_room_id']
        ?? $init['context']['starter_room_id']
        ?? ''
      ));
    }
    if ($expected_room_id !== '' && !$this->payloadContainsRoomId($dungeon_data, $expected_room_id)) {
      throw new \RuntimeException(sprintf(
        'Runtime bootstrap contract violation: campaign %d authoritative dungeon %s does not contain expected room %s.',
        $campaign_id,
        $runtime_dungeon_id,
        $expected_room_id
      ));
    }

    return $row;
  }

  /**
   * Resolve a runtime character row ID from a runtime actor instance ID.
   */
  public function resolveRuntimeCharacterIdForActor(int $campaign_id, string $actor_id): ?int {
    $actor_id = trim($actor_id);
    if ($campaign_id <= 0 || $actor_id === '') {
      return NULL;
    }
    $value = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('instance_id', $actor_id)
      ->condition('type', 'pc')
      ->range(0, 1)
      ->execute()
      ->fetchField();
    if (!is_numeric($value)) {
      return NULL;
    }
    $id = (int) $value;
    return $id > 0 ? $id : NULL;
  }

  protected function normalizeGameState(array $dungeon_data, string $active_room_id): array {
    $state = is_array($dungeon_data['game_state'] ?? NULL) ? $dungeon_data['game_state'] : [];
    if (!is_string($state['phase'] ?? NULL) || trim((string) $state['phase']) === '') {
      $state['phase'] = 'encounter';
    }
    if (!is_string($state['session_id'] ?? NULL) || trim((string) $state['session_id']) === '') {
      $state['session_id'] = 'runtime_bootstrap_' . gmdate('Ymd_His');
    }
    if (!is_string($state['started_at'] ?? NULL) || trim((string) $state['started_at']) === '') {
      $state['started_at'] = gmdate('c');
    }
    if (!is_numeric($state['state_version'] ?? NULL) || (int) $state['state_version'] <= 0) {
      $state['state_version'] = 1;
    }
    if (!is_numeric($state['event_log_cursor'] ?? NULL) || (int) $state['event_log_cursor'] < 0) {
      $state['event_log_cursor'] = 0;
    }
    if (!isset($state['encounter_context']) || !is_array($state['encounter_context'])) {
      $state['encounter_context'] = [];
    }
    if (trim((string) ($state['encounter_context']['room_id'] ?? '')) === '' && $active_room_id !== '') {
      $state['encounter_context']['room_id'] = $active_room_id;
    }
    return $state;
  }

  protected function assertActivePlayerParticipant(array $dungeon_data, string $active_room_id, string $instance_id, int $campaign_id, int $runtime_character_id): void {
    $entities = is_array($dungeon_data['entities'] ?? NULL) ? $dungeon_data['entities'] : [];
    foreach ($entities as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      $entity_instance = trim((string) ($entity['entity_instance_id'] ?? $entity['instance_id'] ?? $entity['id'] ?? ''));
      if ($entity_instance !== $instance_id) {
        continue;
      }
      $entity_type = strtolower(trim((string) ($entity['entity_type'] ?? '')));
      $room_id = trim((string) ($entity['placement']['room_id'] ?? ''));
      if ($entity_type !== 'player_character') {
        throw new \RuntimeException(sprintf(
          'Runtime bootstrap contract violation: campaign %d actor %s is not a player_character entity.',
          $campaign_id,
          $instance_id
        ));
      }
      if ($room_id !== $active_room_id) {
        throw new \RuntimeException(sprintf(
          'Runtime bootstrap contract violation: campaign %d runtime row %d actor %s is in room %s, expected active room %s.',
          $campaign_id,
          $runtime_character_id,
          $instance_id,
          $room_id,
          $active_room_id
        ));
      }
      return;
    }

    throw new \RuntimeException(sprintf(
      'Runtime bootstrap contract violation: campaign %d runtime row %d actor %s was not materialized into active room %s.',
      $campaign_id,
      $runtime_character_id,
      $instance_id,
      $active_room_id
    ));
  }

  protected function persistInitPhase(int $campaign_id, array $campaign_data, string $phase, array $context): void {
    $now = time();
    $campaign_data['init'] = is_array($campaign_data['init'] ?? NULL) ? $campaign_data['init'] : [];
    $campaign_data['init']['phase'] = $phase;
    $campaign_data['init']['context'] = array_replace(
      is_array($campaign_data['init']['context'] ?? NULL) ? $campaign_data['init']['context'] : [],
      $context
    );
    if (array_key_exists('runtime_ready_for_character_id', $context)) {
      $campaign_data['init']['runtime_ready_for_character_id'] = (int) $context['runtime_ready_for_character_id'];
    }
    if (array_key_exists('runtime_ready_for_instance_id', $context)) {
      $campaign_data['init']['runtime_ready_for_instance_id'] = (string) $context['runtime_ready_for_instance_id'];
    }
    if (array_key_exists('runtime_dungeon_id', $context)) {
      $campaign_data['init']['runtime_dungeon_id'] = (string) $context['runtime_dungeon_id'];
    }
    if (array_key_exists('runtime_active_room_id', $context)) {
      $campaign_data['init']['runtime_active_room_id'] = (string) $context['runtime_active_room_id'];
    }
    $campaign_data['init']['updated_at'] = gmdate('c', $now);
    $campaign_data['init']['version'] = (int) ($campaign_data['init']['version'] ?? 0) + 1;

    $encoded = json_encode($campaign_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
      throw new \RuntimeException(sprintf(
        'Runtime bootstrap contract violation: failed to encode campaign_data for campaign %d while persisting init phase.',
        $campaign_id
      ));
    }
    $updated = (int) $this->database->update('dc_campaigns')
      ->fields([
        'campaign_data' => $encoded,
        'changed' => $now,
      ])
      ->condition('id', $campaign_id)
      ->execute();
    if ($updated !== 1) {
      throw new \RuntimeException(sprintf(
        'Runtime bootstrap contract violation: expected to update one dc_campaigns row for campaign %d, updated=%d.',
        $campaign_id,
        $updated
      ));
    }
  }

  protected function loadCampaign(int $campaign_id): array {
    $campaign = $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['id', 'campaign_data'])
      ->condition('id', $campaign_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($campaign)) {
      throw new \RuntimeException(sprintf(
        'Runtime bootstrap contract violation: campaign %d not found.',
        $campaign_id
      ));
    }
    return $campaign;
  }

  protected function loadRuntimeCharacterRow(int $campaign_id, int $runtime_character_id): array {
    $row = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['id', 'campaign_id', 'instance_id', 'last_room_id', 'type', 'status'])
      ->condition('id', $runtime_character_id)
      ->condition('campaign_id', $campaign_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($row)) {
      throw new \RuntimeException(sprintf(
        'Runtime bootstrap contract violation: runtime character row %d not found in campaign %d.',
        $runtime_character_id,
        $campaign_id
      ));
    }
    if (strtolower(trim((string) ($row['type'] ?? ''))) !== 'pc') {
      throw new \RuntimeException(sprintf(
        'Runtime bootstrap contract violation: runtime character row %d in campaign %d is not type=pc.',
        $runtime_character_id,
        $campaign_id
      ));
    }
    if ((int) ($row['status'] ?? 0) !== 1) {
      throw new \RuntimeException(sprintf(
        'Runtime bootstrap contract violation: runtime character row %d in campaign %d is not active.',
        $runtime_character_id,
        $campaign_id
      ));
    }
    return $row;
  }

  protected function loadLatestDungeonRow(int $campaign_id): array {
    $row = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['id', 'campaign_id', 'dungeon_id', 'dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->orderBy('updated', 'DESC')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($row)) {
      throw new \RuntimeException(sprintf(
        'Runtime bootstrap contract violation: campaign %d has no dungeon rows.',
        $campaign_id
      ));
    }
    return $row;
  }

  protected function loadLatestDungeonRowByDungeonId(int $campaign_id, string $dungeon_id): array {
    $dungeon_id = trim($dungeon_id);
    if ($dungeon_id === '') {
      throw new \RuntimeException(sprintf(
        'Runtime bootstrap contract violation: campaign %d requires non-empty dungeon_id for authoritative row lookup.',
        $campaign_id
      ));
    }
    $row = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['id', 'campaign_id', 'dungeon_id', 'dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->condition('dungeon_id', $dungeon_id)
      ->orderBy('updated', 'DESC')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($row)) {
      throw new \RuntimeException(sprintf(
        'Runtime bootstrap contract violation: campaign %d has no dungeon row for authoritative dungeon_id %s.',
        $campaign_id,
        $dungeon_id
      ));
    }
    return $row;
  }

  protected function loadAuthoritativeDungeonRowForBootstrap(int $campaign_id, array $campaign_data, array $runtime_row): array {
    $init = is_array($campaign_data['init'] ?? NULL) ? $campaign_data['init'] : [];
    $context = is_array($init['context'] ?? NULL) ? $init['context'] : [];
    $runtime_dungeon_id = trim((string) ($init['runtime_dungeon_id'] ?? $context['runtime_dungeon_id'] ?? ''));
    if ($runtime_dungeon_id !== '') {
      return $this->loadLatestDungeonRowByDungeonId($campaign_id, $runtime_dungeon_id);
    }

    $expected_room_id = trim((string) ($runtime_row['last_room_id'] ?? ''));
    if ($expected_room_id === '') {
      $expected_room_id = trim((string) ($init['runtime_active_room_id'] ?? $context['runtime_active_room_id'] ?? ''));
    }
    if ($expected_room_id !== '') {
      $rows = $this->database->select('dc_campaign_dungeons', 'd')
        ->fields('d', ['id', 'campaign_id', 'dungeon_id', 'dungeon_data'])
        ->condition('campaign_id', $campaign_id)
        ->orderBy('updated', 'DESC')
        ->orderBy('id', 'DESC')
        ->execute()
        ->fetchAllAssoc('id');
      foreach ($rows as $row) {
        if (!is_object($row)) {
          continue;
        }
        $dungeon_data = json_decode((string) ($row->dungeon_data ?? '{}'), TRUE);
        if (!is_array($dungeon_data)) {
          continue;
        }
        if ($this->payloadContainsRoomId($dungeon_data, $expected_room_id)) {
          return [
            'id' => (int) ($row->id ?? 0),
            'campaign_id' => (int) ($row->campaign_id ?? $campaign_id),
            'dungeon_id' => (string) ($row->dungeon_id ?? ''),
            'dungeon_data' => (string) ($row->dungeon_data ?? '{}'),
          ];
        }
      }
      throw new \RuntimeException(sprintf(
        'Runtime bootstrap contract violation: campaign %d has no dungeon row containing expected room %s.',
        $campaign_id,
        $expected_room_id
      ));
    }

    return $this->loadLatestDungeonRow($campaign_id);
  }

  protected function decodeCampaignData(int $campaign_id, string $campaign_data_raw): array {
    $decoded = json_decode($campaign_data_raw, TRUE);
    if (!is_array($decoded)) {
      throw new \RuntimeException(sprintf(
        'Runtime bootstrap contract violation: campaign %d has invalid campaign_data JSON.',
        $campaign_id
      ));
    }
    return $decoded;
  }

  protected function extractInitState(int $campaign_id, array $campaign_data): array {
    if (!isset($campaign_data['init']) || !is_array($campaign_data['init'])) {
      throw new \RuntimeException(sprintf(
        'Runtime bootstrap contract violation: campaign %d campaign_data.init is missing.',
        $campaign_id
      ));
    }
    return $campaign_data['init'];
  }

  /**
   * Ensure an initialization envelope exists for runtime bootstrap.
   */
  protected function extractInitStateOrAdoptLegacy(int $campaign_id, array &$campaign_data): array {
    if (isset($campaign_data['init']) && is_array($campaign_data['init'])) {
      return $campaign_data['init'];
    }

    $legacy_dungeon = $this->loadLatestDungeonRow($campaign_id);
    $legacy_dungeon_data = json_decode((string) ($legacy_dungeon['dungeon_data'] ?? '{}'), TRUE);
    if (!is_array($legacy_dungeon_data)) {
      throw new \RuntimeException(sprintf(
        'Runtime bootstrap contract violation: campaign %d cannot adopt legacy init metadata from invalid dungeon_data.',
        $campaign_id
      ));
    }
    if (trim((string) ($legacy_dungeon_data['dungeon_id'] ?? $legacy_dungeon['dungeon_id'] ?? '')) === '') {
      throw new \RuntimeException(sprintf(
        'Runtime bootstrap contract violation: campaign %d cannot adopt legacy init metadata without a dungeon_id.',
        $campaign_id
      ));
    }

    $now = time();
    $campaign_data['init'] = [
      'phase' => self::INIT_PHASE_STRUCTURAL_READY,
      'owner' => 'RuntimeBootstrapService',
      'version' => 1,
      'updated_at' => gmdate('c', $now),
      'context' => [
        'adopted_legacy_contract' => TRUE,
      ],
    ];
    $this->persistInitPhase($campaign_id, $campaign_data, self::INIT_PHASE_STRUCTURAL_READY, [
      'adopted_legacy_contract' => TRUE,
      'owner' => 'RuntimeBootstrapService',
    ]);
    return $campaign_data['init'];
  }

  protected function payloadContainsRoomId(array $dungeon_data, string $room_id): bool {
    $room_id = trim($room_id);
    if ($room_id === '') {
      return FALSE;
    }
    $rooms = is_array($dungeon_data['rooms'] ?? NULL) ? $dungeon_data['rooms'] : [];
    foreach ($rooms as $room) {
      if (!is_array($room)) {
        continue;
      }
      if (trim((string) ($room['room_id'] ?? '')) === $room_id) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
