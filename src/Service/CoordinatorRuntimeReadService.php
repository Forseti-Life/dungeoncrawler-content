<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Side-effect-free runtime read context loader for coordinator read lanes.
 */
class CoordinatorRuntimeReadService {

  protected const DEFAULT_ACTIVE_PHASE = 'encounter';

  protected const DEFAULT_GAME_STATE = [
    'phase' => self::DEFAULT_ACTIVE_PHASE,
    'session_id' => NULL,
    'started_at' => NULL,
    'round' => NULL,
    'turn' => NULL,
    'encounter_id' => NULL,
    'initiative_order' => NULL,
    'exploration' => [
      'time_elapsed_minutes' => 0,
      'character_activities' => [],
      'previous_room' => NULL,
    ],
    'timed_activities' => [],
    'state_version' => 1,
    'active_room_id' => NULL,
    'event_log_cursor' => 0,
    'initial_room_entry_room_id' => NULL,
    'initial_room_entry_completed_at' => NULL,
    'last_encounter' => NULL,
  ];

  protected LoggerInterface $logger;

  public function __construct(
    protected readonly RuntimeBootstrapService $runtimeBootstrap,
    protected readonly RuntimeGraphAssemblerService $runtimeGraphAssembler,
    protected readonly CampaignRuntimeStateStore $campaignRuntimeStateStore,
    protected readonly ActorRuntimeStateStore $actorRuntimeStateStore,
    protected readonly CampaignCharacterRuntimeSyncService $campaignCharacterRuntimeSync,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->logger = $logger_factory->get('dungeoncrawler');
  }

  /**
   * Build side-effect-free coordinator read context for action availability.
   *
   * @return array{dungeon_data: array<string,mixed>, game_state: array<string,mixed>}|null
   *   Runtime read context, or NULL when no authoritative dungeon payload exists.
   */
  public function resolveActionAvailabilityContext(int $campaign_id, ?string $actor_id = NULL): ?array {
    $requested_room_id = $this->resolveActorRoomIdFromRuntimeStore($campaign_id, $actor_id);
    $dungeon_data = $this->loadDungeonData(
      $campaign_id,
      $actor_id,
      TRUE,
      1,
      $requested_room_id
    );
    if (!is_array($dungeon_data)) {
      return NULL;
    }

    $game_state = $this->ensureGameState($dungeon_data);
    return [
      'dungeon_data' => $dungeon_data,
      'game_state' => $game_state,
    ];
  }

  /**
   * Build side-effect-free full-state read context for coordinator callers.
   *
   * @return array{dungeon_data: array<string,mixed>, game_state: array<string,mixed>}|null
   *   Runtime read context, or NULL when no authoritative dungeon payload exists.
   */
  public function resolveFullStateReadContext(int $campaign_id): ?array {
    $dungeon_data = $this->loadDungeonData(
      $campaign_id,
      NULL,
      TRUE,
      1,
      NULL
    );
    if (!is_array($dungeon_data)) {
      return NULL;
    }

    $game_state = $this->ensureGameState($dungeon_data);
    return [
      'dungeon_data' => $dungeon_data,
      'game_state' => $game_state,
    ];
  }

  /**
   * Build specialized mutation-lane execution context for coordinator writes.
   *
   * This lane intentionally hydrates only the scoped runtime projection needed
   * by compatibility handlers while keeping persistence responsibilities in the
   * coordinator mutation pipeline.
   *
   * @return array{dungeon_data: array<string,mixed>, game_state: array<string,mixed>}|null
   *   Runtime mutation execution context, or NULL when unavailable.
   */
  public function resolveMutationExecutionContext(
    int $campaign_id,
    ?string $preferred_actor_id = NULL,
    ?string $requested_room_id = NULL
  ): ?array {
    $preferred_actor_id = trim((string) $preferred_actor_id);
    $requested_room_id = trim((string) $requested_room_id);
    if ($requested_room_id === '') {
      $requested_room_id = $this->resolveActorRoomIdFromRuntimeStore(
        $campaign_id,
        $preferred_actor_id !== '' ? $preferred_actor_id : NULL
      ) ?? '';
    }

    $dungeon_data = $this->loadDungeonData(
      $campaign_id,
      $preferred_actor_id !== '' ? $preferred_actor_id : NULL,
      TRUE,
      1,
      $requested_room_id !== '' ? $requested_room_id : NULL
    );
    if (!is_array($dungeon_data)) {
      return NULL;
    }

    $game_state = $this->ensureGameState($dungeon_data);
    return [
      'dungeon_data' => $dungeon_data,
      'game_state' => $game_state,
    ];
  }

  /**
   * Resolve one actor's current room id from actor runtime slice rows.
   */
  protected function resolveActorRoomIdFromRuntimeStore(int $campaign_id, ?string $actor_id = NULL): ?string {
    $actor_id = trim((string) $actor_id);
    if ($actor_id === '') {
      return NULL;
    }
    foreach ($this->actorRuntimeStateStore->loadActorEntities($campaign_id) as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      $entity_instance_id = trim((string) (
        $entity['entity_instance_id']
        ?? $entity['instance_id']
        ?? $entity['id']
        ?? ''
      ));
      if ($entity_instance_id !== $actor_id) {
        continue;
      }
      $room_id = trim((string) ($entity['placement']['room_id'] ?? ''));
      return $room_id !== '' ? $room_id : NULL;
    }
    return NULL;
  }

  /**
   * Load a composed runtime read payload from authoritative persistence lanes.
   *
   * @return array<string,mixed>|null
   *   Runtime read payload, or NULL if no authoritative row is available.
   */
  protected function loadDungeonData(
    int $campaign_id,
    ?string $preferred_actor_id = NULL,
    bool $rebuild_runtime_graph = TRUE,
    int $room_scope_depth = -1,
    ?string $requested_room_id = NULL
  ): ?array {
    try {
      $runtime_character_id = NULL;
      if (is_string($preferred_actor_id) && trim($preferred_actor_id) !== '') {
        $runtime_character_id = $this->runtimeBootstrap->resolveRuntimeCharacterIdForActor($campaign_id, trim($preferred_actor_id));
      }
      $row = $this->runtimeBootstrap->loadAuthoritativeDungeonRowForRuntimeRead($campaign_id, $runtime_character_id);

      if (empty($row['dungeon_data'])) {
        return NULL;
      }
      $decoded = json_decode((string) $row['dungeon_data'], TRUE) ?: NULL;
      if (!is_array($decoded)) {
        return NULL;
      }

      $decoded['campaign_id'] = $campaign_id;
      if (trim((string) ($decoded['dungeon_id'] ?? '')) === '') {
        $decoded['dungeon_id'] = trim((string) ($row['dungeon_id'] ?? ''));
      }

      $runtime_game_state = $this->campaignRuntimeStateStore->loadGameState($campaign_id);
      if (is_array($runtime_game_state)) {
        $decoded['game_state'] = $runtime_game_state;
        $authoritative_active_room_id = trim((string) (
          $runtime_game_state['active_room_id']
          ?? $runtime_game_state['encounter_context']['room_id']
          ?? ''
        ));
        if ($authoritative_active_room_id !== '') {
          $decoded['active_room_id'] = $authoritative_active_room_id;
          $decoded['current_room_id'] = $authoritative_active_room_id;
        }
      }

      $resolved_dungeon_id = trim((string) ($decoded['dungeon_id'] ?? $row['dungeon_id'] ?? ''));
      if ($rebuild_runtime_graph && $resolved_dungeon_id !== '') {
        $resolved_requested_room_id = trim((string) ($requested_room_id ?? ''));
        $decoded = $this->runtimeGraphAssembler->buildRuntimeGraph(
          $campaign_id,
          $resolved_dungeon_id,
          $decoded,
          [
            'active_room_id' => trim((string) ($decoded['active_room_id'] ?? '')),
            'room_batch_size' => 8,
            'room_scope_depth' => $room_scope_depth,
            'requested_room_id' => $resolved_requested_room_id,
          ]
        );
      }

      if (empty($decoded['active_room_id'])) {
        $this->resolveStartupRoomId($decoded);
      }

      $runtime_entities = $this->actorRuntimeStateStore->loadActorEntities($campaign_id);
      if ($room_scope_depth >= 0 && $runtime_entities !== []) {
        $scoped_room_ids = [];
        foreach ($decoded['rooms'] ?? [] as $room) {
          if (!is_array($room)) {
            continue;
          }
          $room_id = trim((string) ($room['room_id'] ?? ''));
          if ($room_id !== '') {
            $scoped_room_ids[$room_id] = TRUE;
          }
        }
        $preferred_actor_id = trim((string) ($preferred_actor_id ?? ''));
        $runtime_entities = array_values(array_filter($runtime_entities, static function ($entity) use ($scoped_room_ids, $preferred_actor_id): bool {
          if (!is_array($entity)) {
            return FALSE;
          }
          $entity_id = trim((string) (
            $entity['entity_instance_id']
            ?? $entity['instance_id']
            ?? $entity['id']
            ?? ''
          ));
          if ($preferred_actor_id !== '' && $entity_id === $preferred_actor_id) {
            return TRUE;
          }
          $entity_room_id = trim((string) ($entity['placement']['room_id'] ?? ''));
          return $entity_room_id !== '' && isset($scoped_room_ids[$entity_room_id]);
        }));
      }
      if ($runtime_entities !== []) {
        $decoded['entities'] = $runtime_entities;
      }
      $decoded['__campaign_dungeon_row_id'] = (int) ($row['id'] ?? 0);
      return $this->campaignCharacterRuntimeSync->syncActiveRoomPlayerEntities($decoded, $campaign_id, $preferred_actor_id);
    }
    catch (\Throwable $e) {
      $this->logger->error('Failed to load coordinator runtime read context for campaign @id: @error', [
        '@id' => $campaign_id,
        '@error' => $e->getMessage(),
      ]);
    }

    return NULL;
  }

  /**
   * Ensure game_state exists with defaults for read-lane consumers.
   *
   * @param array<string,mixed> $dungeon_data
   *   Runtime payload.
   *
   * @return array<string,mixed>
   *   Normalized game_state payload.
   */
  protected function ensureGameState(array &$dungeon_data): array {
    if (!isset($dungeon_data['game_state']) || !is_array($dungeon_data['game_state'])) {
      $dungeon_data['game_state'] = self::DEFAULT_GAME_STATE;
      $dungeon_data['game_state']['started_at'] = date('c');
      $dungeon_data['game_state']['session_id'] = 'sess_' . date('Ymd_His');
    }

    foreach (self::DEFAULT_GAME_STATE as $key => $default) {
      if (!array_key_exists($key, $dungeon_data['game_state'])) {
        $dungeon_data['game_state'][$key] = $default;
      }
    }

    $phase = (string) ($dungeon_data['game_state']['phase'] ?? self::DEFAULT_ACTIVE_PHASE);
    if ($phase === '') {
      $dungeon_data['game_state']['phase'] = self::DEFAULT_ACTIVE_PHASE;
    }
    $this->synchronizeActiveRoomAuthority($dungeon_data['game_state'], $dungeon_data);
    return $dungeon_data['game_state'];
  }

  /**
   * Mirror authoritative active-room id into runtime game_state.
   */
  protected function synchronizeActiveRoomAuthority(array &$game_state, array $dungeon_data): void {
    $active_room_id = trim((string) ($dungeon_data['active_room_id'] ?? ''));
    if ($active_room_id === '') {
      $active_room_id = trim((string) ($game_state['encounter_context']['room_id'] ?? ''));
    }
    if ($active_room_id === '') {
      return;
    }

    $game_state['active_room_id'] = $active_room_id;
    if (
      isset($game_state['encounter_context'])
      && is_array($game_state['encounter_context'])
      && trim((string) ($game_state['encounter_context']['room_id'] ?? '')) === ''
    ) {
      $game_state['encounter_context']['room_id'] = $active_room_id;
    }
  }

  /**
   * Resolve startup active-room id when missing from payload.
   */
  protected function resolveStartupRoomId(array &$dungeon_data): ?string {
    $active_room_id = trim((string) ($dungeon_data['active_room_id'] ?? ''));
    if ($active_room_id !== '') {
      return $active_room_id;
    }

    foreach ((array) ($dungeon_data['rooms'] ?? []) as $room) {
      if (!is_array($room)) {
        continue;
      }
      $candidate = trim((string) ($room['room_id'] ?? ''));
      if ($candidate !== '') {
        $dungeon_data['active_room_id'] = $candidate;
        $dungeon_data['current_room_id'] = $candidate;
        return $candidate;
      }
    }

    foreach ((array) ($dungeon_data['room_ids'] ?? []) as $room_id) {
      $candidate = trim((string) $room_id);
      if ($candidate !== '') {
        $dungeon_data['active_room_id'] = $candidate;
        $dungeon_data['current_room_id'] = $candidate;
        return $candidate;
      }
    }

    return NULL;
  }

}
