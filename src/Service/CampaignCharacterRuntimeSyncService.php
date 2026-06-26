<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Re-syncs active campaign characters into the canonical dungeon payload.
 */
class CampaignCharacterRuntimeSyncService {

  protected Connection $database;

  protected FollowerSubsystemService $followerSubsystem;

  protected ?NpcSheetGenerationService $npcSheetGenerationService;

  protected ?CharacterPortraitGenerationService $characterPortraitGenerator;

  public function __construct(
    Connection $database,
    FollowerSubsystemService $follower_subsystem,
    ?NpcSheetGenerationService $npc_sheet_generation_service = NULL,
    ?CharacterPortraitGenerationService $character_portrait_generator = NULL,
  ) {
    $this->database = $database;
    $this->followerSubsystem = $follower_subsystem;
    $this->npcSheetGenerationService = $npc_sheet_generation_service;
    $this->characterPortraitGenerator = $character_portrait_generator;
  }

  /**
   * Inject active player-character entities for the current active room.
   *
   * The authoritative campaign-character runtime rows live in
   * dc_campaign_characters. Canonical gameplay services operate on
   * dc_campaign_dungeons.dungeon_data, so this bridge keeps the active room's
   * player-character entities aligned before validation and action processing.
   *
   * @param array $dungeon_payload
   *   The current dungeon payload.
   * @param int $campaign_id
   *   The campaign ID.
   * @param string|null $preferred_actor_id
   *   Optional actor instance ID to prioritize when selecting rows.
   *
   * @return array
   *   The payload with synced player-character entities.
   */
  public function syncActiveRoomPlayerEntities(array $dungeon_payload, int $campaign_id, ?string $preferred_actor_id = NULL): array {
    $active_room_id = trim((string) ($dungeon_payload['active_room_id'] ?? ''));
    if ($campaign_id <= 0 || $active_room_id === '') {
      return $dungeon_payload;
    }

    $records = $this->filterRelevantRecords(
      $this->loadActivePlayerRecords($campaign_id),
      $active_room_id,
      $preferred_actor_id
    );
    $dungeon_payload = $this->syncActiveRoomNpcEntities($dungeon_payload, $campaign_id, $active_room_id);
    if ($records === []) {
      return $dungeon_payload;
    }

    $dungeon_payload['entities'] = array_values(array_filter(
      $dungeon_payload['entities'] ?? [],
      static function (array $entity): bool {
        return strtolower((string) ($entity['entity_type'] ?? '')) !== 'player_character';
      }
    ));
    $occupied = $this->buildOccupiedLookupByRoom($dungeon_payload);

    foreach ($records as $record) {
      $record = $this->ensurePersistentRuntimeRecordIdentity($record, $campaign_id, 'pc');
      $room_id = $this->resolveRecordRoomId($record) ?: $active_room_id;
      $char_data = $this->decodeCharacterData($record);
      $source_character_id = (int) ($record['source_character_id'] ?? 0);
      if ($source_character_id <= 0) {
        throw new \RuntimeException(sprintf(
          'Campaign runtime PC row %d is missing source_character_id.',
          (int) ($record['id'] ?? 0)
        ));
      }
      $backfill_result = $this->followerSubsystem->backfillPersistedActorRecordsOnCharacterData(
        $char_data,
        (string) $source_character_id
      );
      if (!empty($backfill_result['updated'])) {
        $char_data = is_array($backfill_result['character_data'] ?? NULL) ? $backfill_result['character_data'] : $char_data;
        $this->persistRuntimeCharacterData(
          (int) ($record['id'] ?? 0),
          $campaign_id,
          $char_data
        );
        $record['character_data'] = json_encode($char_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      }
      $placement = $this->resolveCharacterPlacement($dungeon_payload, $room_id, $record, $occupied[$room_id] ?? []);
      $occupied[$room_id][$placement['q'] . ',' . $placement['r']] = TRUE;

      $hp_max = (int) ($record['hp_max'] ?: ($char_data['hp']['max'] ?? $char_data['calculated_stats']['max_hp'] ?? 20));
      $hp_current = (int) ($record['hp_current'] ?: ($char_data['hp']['current'] ?? $hp_max));
      $armor_class = (int) ($record['armor_class'] ?: ($char_data['ac'] ?? $char_data['calculated_stats']['ac'] ?? 10));
      $instance_id = (string) ($record['instance_id'] ?? '');
      if ($instance_id === '') {
        $instance_id = sprintf('pc-%d-%d', $campaign_id, (int) ($record['id'] ?? 0));
      }

      $name = trim((string) ($record['name'] ?? ''));
      if ($name === '') {
        $name = (string) ($char_data['name'] ?? sprintf('Character %d', (int) ($record['id'] ?? 0)));
      }

      $dungeon_payload['entities'][] = [
        'entity_type' => 'player_character',
        'instance_id' => $instance_id,
        'entity_instance_id' => $instance_id,
        'entity_ref' => [
          'content_id' => $instance_id,
        ],
        'placement' => [
          'room_id' => $room_id,
          'hex' => $placement,
        ],
        'state' => [
          'metadata' => [
            'display_name' => $name,
            'name' => $name,
            'team' => 'player',
            'character_id' => (int) ($record['id'] ?? 0),
            'source_character_id' => $source_character_id,
            'campaign_character_id' => (int) ($record['id'] ?? 0),
            'runtime_entity_id' => $instance_id,
            'stats' => [
              'maxHp' => $hp_max,
              'currentHp' => $hp_current,
              'ac' => $armor_class,
              'speed' => 25,
            ],
            'movement_speed' => 25,
            'actions_per_turn' => 3,
            'initiative_bonus' => 0,
          ],
        ],
      ];

      $room_occupied = $occupied[$room_id] ?? [];
      $this->injectOwnedRuntimeFollowerEntities($dungeon_payload, $record, $char_data, $room_id, $placement['q'], $placement['r'], $room_occupied);
      $occupied[$room_id] = $room_occupied;
    }

    return $dungeon_payload;
  }

  /**
   * Load active player-character runtime rows for a campaign.
   *
   * @return array<int, array<string, mixed>>
   *   Runtime records from dc_campaign_characters.
   */
  protected function loadActivePlayerRecords(int $campaign_id): array {
    return $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', [
        'id',
        'character_id',
        'source_character_id',
        'instance_id',
        'name',
        'hp_current',
        'hp_max',
        'armor_class',
        'character_data',
        'position_q',
        'position_r',
        'last_room_id',
        'location_ref',
        'updated',
      ])
      ->condition('campaign_id', $campaign_id)
      ->condition('type', 'pc')
      ->condition('is_active', 1)
      ->orderBy('updated', 'DESC')
      ->orderBy('id', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
  }

  /**
   * Keep only rows that belong in the active room.
   *
   * @param array<int, array<string, mixed>> $records
   *   Candidate runtime rows.
   *
   * @return array<int, array<string, mixed>>
   *   Relevant active-room records.
   */
  protected function filterRelevantRecords(array $records, string $active_room_id, ?string $preferred_actor_id = NULL): array {
    $preferred_actor_id = trim((string) $preferred_actor_id);
    $filtered = array_values(array_filter($records, function (array $record) use ($active_room_id, $preferred_actor_id): bool {
      $record_room_id = $this->resolveRecordRoomId($record);
      $instance_id = trim((string) ($record['instance_id'] ?? ''));

      if ($record_room_id === $active_room_id) {
        return TRUE;
      }

      return $preferred_actor_id !== ''
        && $instance_id === $preferred_actor_id
        && $record_room_id === '';
    }));

    if ($preferred_actor_id !== '') {
      usort($filtered, static function (array $left, array $right) use ($preferred_actor_id): int {
        $left_match = trim((string) ($left['instance_id'] ?? '')) === $preferred_actor_id ? 0 : 1;
        $right_match = trim((string) ($right['instance_id'] ?? '')) === $preferred_actor_id ? 0 : 1;
        return $left_match <=> $right_match;
      });
    }

    return $filtered;
  }

  /**
   * Ensure active-room NPC runtime records are reflected in the dungeon payload.
   */
  protected function syncActiveRoomNpcEntities(array $dungeon_payload, int $campaign_id, string $active_room_id): array {
    $room_refs = $this->resolveActiveRoomReferences($dungeon_payload, $campaign_id, $active_room_id);
    if ($room_refs === []) {
      return $dungeon_payload;
    }

    $records = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['id', 'instance_id', 'name', 'state_data', 'character_data', 'uid', 'position_q', 'position_r', 'location_ref'])
      ->condition('campaign_id', $campaign_id)
      ->condition('type', 'npc')
      ->condition('location_type', 'room')
      ->condition('location_ref', $room_refs, 'IN')
      ->orderBy('id', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    if ($records === []) {
      return $dungeon_payload;
    }

    $occupied = $this->buildOccupiedLookupByRoom($dungeon_payload);
    foreach ($records as $record) {
      [$record, $state, $instance_id, $content_id] = $this->ensurePersistentNpcRuntimeIdentity($record, $campaign_id, $dungeon_payload);
      $this->ensureRuntimeNpcGenerationPipeline($campaign_id, $record, $state, $content_id);
      $name = trim((string) ($record['name'] ?? ''));
      $matched = FALSE;

      if (!isset($dungeon_payload['entities']) || !is_array($dungeon_payload['entities'])) {
        $dungeon_payload['entities'] = [];
      }
      foreach ($dungeon_payload['entities'] as &$entity) {
        $entity_runtime_character_id = (int) ($entity['state']['metadata']['campaign_character_id'] ?? 0);
        $entity_instance_id = trim((string) ($entity['instance_id'] ?? $entity['entity_instance_id'] ?? ''));
        $entity_content_id = trim((string) ($entity['entity_ref']['content_id'] ?? ''));
        if (
          $entity_runtime_character_id === (int) ($record['id'] ?? 0)
          || ($instance_id !== '' && $entity_instance_id === $instance_id)
          || ($content_id !== '' && $entity_content_id === $content_id)
        ) {
          $entity['instance_id'] = $instance_id;
          $entity['entity_instance_id'] = $instance_id;
          $entity['state']['metadata']['display_name'] = $name !== '' ? $name : ($entity['state']['metadata']['display_name'] ?? '');
          $entity['state']['metadata']['name'] = $name !== '' ? $name : ($entity['state']['metadata']['name'] ?? '');
          $entity['state']['metadata']['character_id'] = (int) ($record['id'] ?? 0);
          $entity['state']['metadata']['campaign_character_id'] = (int) ($record['id'] ?? 0);
          $entity['state']['metadata']['runtime_entity_id'] = $instance_id;
          if (!empty($state['role'])) {
            $entity['state']['metadata']['role'] = (string) $state['role'];
          }
          if (!empty($state['description'])) {
            $entity['state']['metadata']['description'] = (string) $state['description'];
          }
          $matched = TRUE;
          break;
        }
      }
      unset($entity);

      if ($matched) {
        continue;
      }

      $placement = $this->resolveRoomNpcPlacement($dungeon_payload, $active_room_id, $record, $occupied[$active_room_id] ?? []);
      $occupied[$active_room_id][$placement['q'] . ',' . $placement['r']] = TRUE;

      $dungeon_payload['entities'][] = [
        'entity_type' => 'npc',
        'instance_id' => $instance_id,
        'entity_instance_id' => $instance_id,
        'entity_ref' => [
          'content_type' => 'npc',
          'content_id' => $content_id !== '' ? $content_id : strtolower(str_replace(' ', '_', $name)),
        ],
        'placement' => [
          'room_id' => $active_room_id,
          'hex' => $placement,
          'spawn_type' => 'npc',
        ],
        'state' => [
          'active' => TRUE,
          'metadata' => [
            'display_name' => $name,
            'name' => $name,
            'role' => (string) ($state['role'] ?? 'npc'),
            'description' => (string) ($state['description'] ?? ''),
            'team' => (string) ($state['team'] ?? 'neutral'),
            'character_id' => (int) ($record['id'] ?? 0),
            'campaign_character_id' => (int) ($record['id'] ?? 0),
            'runtime_entity_id' => $instance_id,
            'setting_state' => TRUE,
            'spawn_policy' => 'campaign_runtime',
          ],
        ],
      ];
    }

    return $dungeon_payload;
  }

  /**
   * Seed NPC library generation and portrait generation for runtime-only NPCs.
   */
  protected function ensureRuntimeNpcGenerationPipeline(int $campaign_id, array $record, array $state, string $content_id): void {
    if (!$this->npcSheetGenerationService && !$this->characterPortraitGenerator) {
      return;
    }

    $character_data = $this->decodeCharacterData($record);
    $seed_data = $this->buildRuntimeNpcSeedData($campaign_id, $record, $state, $content_id, $character_data);
    $library_npc_id = $this->ensureCampaignNpcLibraryRecord($campaign_id, $content_id, $seed_data);

    if ($this->npcSheetGenerationService && $content_id !== '') {
      $this->npcSheetGenerationService->enqueueNpcSheetGeneration($campaign_id, $content_id, $seed_data);
    }

    if ($this->characterPortraitGenerator && (int) ($record['id'] ?? 0) > 0) {
      $portrait_payload = $character_data !== [] ? $character_data : $seed_data;
      $portrait_payload['portrait_generate'] = TRUE;
      $this->characterPortraitGenerator->generatePortrait(
        $portrait_payload,
        (int) ($record['id'] ?? 0),
        (int) ($record['uid'] ?? 0),
        $campaign_id,
        [
          'generate' => TRUE,
        ]
      );
    }

    if ($library_npc_id !== NULL) {
      $this->syncCampaignPortraitToLibraryNpc($campaign_id, (int) ($record['id'] ?? 0), $library_npc_id);
    }
  }

  /**
   * Build seed data for runtime NPCs that bypassed the NPC library service.
   */
  protected function buildRuntimeNpcSeedData(int $campaign_id, array $record, array $state, string $content_id, array $character_data): array {
    $metadata = is_array($state['metadata'] ?? NULL) ? $state['metadata'] : [];
    $profile = is_array($character_data['profile'] ?? NULL) ? $character_data['profile'] : [];
    $basic_info = is_array($character_data['basicInfo'] ?? NULL) ? $character_data['basicInfo'] : [];
    $merchant = is_array($character_data['merchant'] ?? NULL) ? $character_data['merchant'] : [];
    $stats = is_array($character_data['stats'] ?? NULL) ? $character_data['stats'] : [];

    return [
      'campaign_id' => $campaign_id,
      'content_id' => $content_id,
      'entity_ref' => $content_id,
      'name' => (string) ($record['name'] ?? $metadata['display_name'] ?? $metadata['name'] ?? $content_id),
      'role' => (string) ($state['role'] ?? $metadata['role'] ?? 'neutral'),
      'team' => (string) ($state['team'] ?? $metadata['team'] ?? ''),
      'level' => (int) ($character_data['level'] ?? $basic_info['level'] ?? $stats['level'] ?? 1),
      'ancestry' => (string) ($character_data['ancestry'] ?? $basic_info['ancestry'] ?? 'Humanoid'),
      'class' => (string) ($character_data['class'] ?? $basic_info['class'] ?? 'Commoner'),
      'occupation' => (string) ($metadata['occupation'] ?? $profile['role'] ?? ''),
      'description' => (string) ($state['description'] ?? $metadata['description'] ?? $profile['appearance'] ?? ''),
      'backstory' => (string) ($character_data['backstory'] ?? ''),
      'alignment' => (string) ($character_data['alignment'] ?? 'N'),
      'attitude' => (string) ($character_data['attitude'] ?? 'indifferent'),
      'motivations' => (string) ($character_data['motivations'] ?? ''),
      'fears' => (string) ($character_data['fears'] ?? ''),
      'bonds' => (string) ($character_data['bonds'] ?? ''),
      'languages' => array_values(is_array($character_data['languages'] ?? NULL) ? $character_data['languages'] : ['Common']),
      'senses' => array_values(is_array($character_data['senses'] ?? NULL) ? $character_data['senses'] : []),
      'equipment' => array_values(is_array($character_data['equipment'] ?? NULL) ? $character_data['equipment'] : []),
      'stats' => $stats,
      'merchant' => $merchant,
      'appearance' => (string) ($profile['appearance'] ?? $character_data['appearance'] ?? ''),
      'personality' => (string) ($character_data['personality'] ?? ''),
    ];
  }

  /**
   * Ensure a runtime NPC also has a campaign NPC library row.
   */
  protected function ensureCampaignNpcLibraryRecord(int $campaign_id, string $content_id, array $seed_data): ?int {
    $content_id = trim($content_id);
    if ($campaign_id <= 0 || $content_id === '') {
      return NULL;
    }

    $instance_id = str_starts_with($content_id, 'npc_') ? $content_id : 'npc_' . $content_id;

    $existing_id = $this->database->select('dc_campaign_characters', 'lib')
      ->fields('lib', ['id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('type', 'npc')
      ->condition('instance_id', $instance_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();
    if ($existing_id !== FALSE) {
      return (int) $existing_id;
    }

    $now = time();
    $role = (string) ($seed_data['role'] ?? 'npc');
    $state_data = [
      'content_id' => $content_id,
      'role' => $role,
      'description' => (string) ($seed_data['description'] ?? ''),
      'backstory' => (string) ($seed_data['backstory'] ?? ''),
      'stats' => [
        'perception' => (int) ($seed_data['stats']['perception'] ?? 0),
        'ac' => (int) ($seed_data['stats']['ac'] ?? 10),
        'currentHp' => (int) ($seed_data['stats']['maxHp'] ?? 0),
        'maxHp' => (int) ($seed_data['stats']['maxHp'] ?? 0),
        'fortitude' => (int) ($seed_data['stats']['fortitude'] ?? 0),
        'reflex' => (int) ($seed_data['stats']['reflex'] ?? 0),
        'will' => (int) ($seed_data['stats']['will'] ?? 0),
      ],
      'npc_profile' => [
        'attitude' => (string) ($seed_data['attitude'] ?? 'indifferent'),
        'alignment' => (string) ($seed_data['alignment'] ?? 'N'),
        'lore_notes' => (string) ($seed_data['backstory'] ?? ''),
        'dialogue_notes' => (string) ($seed_data['description'] ?? ''),
      ],
    ];
    $character_data = [
      'name' => (string) ($seed_data['name'] ?? $content_id),
      'type' => 'npc',
      'role' => $role,
      'level' => (int) ($seed_data['level'] ?? 1),
      'attitude' => (string) ($seed_data['attitude'] ?? 'indifferent'),
      'alignment' => (string) ($seed_data['alignment'] ?? 'N'),
      'description' => (string) ($seed_data['description'] ?? ''),
      'backstory' => (string) ($seed_data['backstory'] ?? ''),
      'stats' => is_array($seed_data['stats'] ?? NULL) ? $seed_data['stats'] : [],
      'languages' => is_array($seed_data['languages'] ?? NULL) ? $seed_data['languages'] : ['Common'],
      'senses' => is_array($seed_data['senses'] ?? NULL) ? $seed_data['senses'] : [],
      'equipment' => is_array($seed_data['equipment'] ?? NULL) ? $seed_data['equipment'] : [],
      'motivations' => (string) ($seed_data['motivations'] ?? ''),
      'fears' => (string) ($seed_data['fears'] ?? ''),
      'bonds' => (string) ($seed_data['bonds'] ?? ''),
    ];

    return (int) $this->database->insert('dc_campaign_characters')
      ->fields([
        'campaign_id' => $campaign_id,
        'character_id' => 0,
        'source_character_id' => NULL,
        'name' => (string) ($seed_data['name'] ?? $content_id),
        'role' => $role,
        'uid' => 0,
        'is_active' => 1,
        'joined' => $now,
        'instance_id' => $instance_id,
        'type' => 'npc',
        'state_data' => json_encode($state_data, JSON_UNESCAPED_UNICODE),
        'location_type' => 'global',
        'location_ref' => '',
        'updated' => $now,
        'level' => (int) ($seed_data['level'] ?? 1),
        'armor_class' => (int) ($seed_data['stats']['ac'] ?? 10),
        'hp_current' => (int) ($seed_data['stats']['maxHp'] ?? 0),
        'hp_max' => (int) ($seed_data['stats']['maxHp'] ?? 0),
        'experience_points' => 0,
        'position_q' => 0,
        'position_r' => 0,
        'last_room_id' => '',
        'ancestry' => (string) ($seed_data['ancestry'] ?? 'Humanoid'),
        'class' => (string) ($seed_data['class'] ?? 'Commoner'),
        'character_data' => json_encode($character_data, JSON_UNESCAPED_UNICODE),
        'default_character_data' => NULL,
        'default_locations' => NULL,
        'portrait' => NULL,
        'status' => 1,
        'created' => $now,
        'changed' => $now,
        'version' => 1,
        'lifecycle_state' => $role === 'merchant' ? 'campaign_merchant' : 'campaign_npc',
      ])
      ->execute();
  }

  /**
   * Mirror the runtime portrait link onto the campaign NPC library row.
   */
  protected function syncCampaignPortraitToLibraryNpc(int $campaign_id, int $campaign_character_id, int $library_npc_id): void {
    if ($campaign_id <= 0 || $campaign_character_id <= 0 || $library_npc_id <= 0) {
      return;
    }

    $image_id = $this->database->select('dc_generated_image_links', 'l')
      ->fields('l', ['image_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('table_name', 'dc_campaign_characters')
      ->condition('object_id', (string) $campaign_character_id)
      ->condition('slot', 'portrait')
      ->condition('variant', 'original')
      ->orderBy('is_primary', 'DESC')
      ->orderBy('created', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();
    if ($image_id === FALSE) {
      return;
    }

    $existing_link = $this->database->select('dc_generated_image_links', 'l')
      ->fields('l', ['image_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('table_name', 'dc_campaign_characters')
      ->condition('object_id', (string) $library_npc_id)
      ->condition('slot', 'portrait')
      ->condition('variant', 'original')
      ->range(0, 1)
      ->execute()
      ->fetchField();
    if ($existing_link !== FALSE) {
      return;
    }

    $now = time();
    $this->database->insert('dc_generated_image_links')
      ->fields([
        'image_id' => (int) $image_id,
        'scope_type' => 'campaign',
        'campaign_id' => $campaign_id,
        'table_name' => 'dc_campaign_characters',
        'object_id' => (string) $library_npc_id,
        'slot' => 'portrait',
        'variant' => 'original',
        'is_primary' => 1,
        'sort_weight' => 0,
        'visibility' => 'owner',
        'created' => $now,
        'updated' => $now,
      ])
      ->execute();
  }

  /**
   * Resolve the persisted room ID for a campaign-character runtime row.
   */
  protected function resolveRecordRoomId(array $record): string {
    return trim((string) ($record['location_ref'] ?? $record['last_room_id'] ?? ''));
  }

  /**
   * Ensure a runtime record has a durable persisted instance id.
   *
   * @return array<string, mixed>
   *   Updated runtime record.
   */
  protected function ensurePersistentRuntimeRecordIdentity(array $record, int $campaign_id, string $type): array {
    $instance_id = trim((string) ($record['instance_id'] ?? ''));
    $record_id = (int) ($record['id'] ?? 0);
    if ($instance_id !== '' || $record_id <= 0) {
      return $record;
    }

    $prefix = $type === 'npc' ? 'npc' : 'pc';
    $instance_id = sprintf('%s-%d-%d', $prefix, $campaign_id, $record_id);
    $this->database->update('dc_campaign_characters')
      ->fields(['instance_id' => $instance_id])
      ->condition('id', $record_id)
      ->condition('campaign_id', $campaign_id)
      ->execute();
    $record['instance_id'] = $instance_id;

    return $record;
  }

  /**
   * Ensure a room NPC has durable persisted runtime identity fields.
   *
   * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: string, 3: string}
   *   Updated record, decoded state, stable instance id, stable content id.
   */
  protected function ensurePersistentNpcRuntimeIdentity(array $record, int $campaign_id, array $dungeon_payload): array {
    $record = $this->ensurePersistentRuntimeRecordIdentity($record, $campaign_id, 'npc');
    $state = json_decode((string) ($record['state_data'] ?? '{}'), TRUE);
    $state = is_array($state) ? $state : [];

    $instance_id = trim((string) ($record['instance_id'] ?? ''));
    $content_id = $this->resolvePersistedNpcContentId($record, $state, $dungeon_payload, $campaign_id);
    $state_changed = FALSE;

    if (($state['content_id'] ?? NULL) !== $content_id) {
      $state['content_id'] = $content_id;
      $state_changed = TRUE;
    }
    if (($state['runtime_entity_id'] ?? NULL) !== $instance_id) {
      $state['runtime_entity_id'] = $instance_id;
      $state_changed = TRUE;
    }
    if (($state['campaign_character_id'] ?? NULL) !== (int) ($record['id'] ?? 0)) {
      $state['campaign_character_id'] = (int) ($record['id'] ?? 0);
      $state_changed = TRUE;
    }

    if ($state_changed) {
      $encoded_state = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      $this->database->update('dc_campaign_characters')
        ->fields(['state_data' => $encoded_state])
        ->condition('id', (int) ($record['id'] ?? 0))
        ->condition('campaign_id', $campaign_id)
        ->execute();
      $record['state_data'] = $encoded_state;
    }

    return [$record, $state, $instance_id, $content_id];
  }

  /**
   * Resolve a stable NPC content id without relying on display-name matching.
   */
  protected function resolvePersistedNpcContentId(array $record, array $state, array $dungeon_payload, int $campaign_id): string {
    $candidates = array_values(array_filter([
      trim((string) ($state['content_id'] ?? '')),
      trim((string) ($state['entity_ref']['content_id'] ?? '')),
      $this->deriveNpcContentIdFromInstanceId((string) ($record['instance_id'] ?? '')),
      $this->findNpcContentIdInPayload($record, $dungeon_payload),
    ], static fn(string $value): bool => $value !== ''));

    if ($candidates !== []) {
      return (string) reset($candidates);
    }

    return sprintf('campaign_npc_%d_%d', $campaign_id, (int) ($record['id'] ?? 0));
  }

  /**
   * Derive a source content id from a persisted NPC instance id when possible.
   */
  protected function deriveNpcContentIdFromInstanceId(string $instance_id): string {
    $instance_id = trim($instance_id);
    if ($instance_id === '') {
      return '';
    }
    if (str_starts_with($instance_id, 'npc_')) {
      return substr($instance_id, 4);
    }
    if (str_starts_with($instance_id, 'npc-') && preg_match('/^npc-\d+-\d+$/', $instance_id) !== 1) {
      return substr($instance_id, 4);
    }
    return '';
  }

  /**
   * Recover an existing payload content id using immutable runtime identifiers.
   */
  protected function findNpcContentIdInPayload(array $record, array $dungeon_payload): string {
    $record_id = (int) ($record['id'] ?? 0);
    $instance_id = trim((string) ($record['instance_id'] ?? ''));
    foreach (($dungeon_payload['entities'] ?? []) as $entity) {
      $entity_record_id = (int) ($entity['state']['metadata']['campaign_character_id'] ?? 0);
      $entity_instance_id = trim((string) ($entity['instance_id'] ?? $entity['entity_instance_id'] ?? ''));
      if (($record_id > 0 && $entity_record_id === $record_id) || ($instance_id !== '' && $entity_instance_id === $instance_id)) {
        return trim((string) ($entity['entity_ref']['content_id'] ?? ''));
      }
    }

    return '';
  }

  /**
   * Resolve the active room's runtime and authored references.
   *
   * @return array<int, string>
   *   Room references that may appear in runtime rows.
   */
  protected function resolveActiveRoomReferences(array $dungeon_payload, int $campaign_id, string $active_room_id): array {
    $refs = [$active_room_id];
    $room = NULL;
    foreach (($dungeon_payload['rooms'] ?? []) as $candidate) {
      if (($candidate['room_id'] ?? '') === $active_room_id) {
        $room = is_array($candidate) ? $candidate : NULL;
        break;
      }
    }

    $room_name = trim((string) ($room['name'] ?? ''));
    $exact_room_id = $this->database->select('dc_campaign_rooms', 'r')
      ->fields('r', ['room_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('room_id', $active_room_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();
    if (is_string($exact_room_id) && $exact_room_id !== '') {
      $refs[] = $exact_room_id;
    }

    if ($room_name !== '') {
      $room_ids_by_name = $this->database->select('dc_campaign_rooms', 'r')
        ->fields('r', ['room_id'])
        ->condition('campaign_id', $campaign_id)
        ->condition('name', $room_name)
        ->execute()
        ->fetchCol();
      foreach ($room_ids_by_name as $room_id) {
        if (is_string($room_id) && $room_id !== '') {
          $refs[] = $room_id;
        }
      }
    }

    return array_values(array_unique(array_filter(array_map('strval', $refs))));
  }

  /**
   * Decode runtime character_data safely.
   */
  protected function decodeCharacterData(array $record): array {
    $decoded = json_decode((string) ($record['character_data'] ?? '{}'), TRUE);
    return is_array($decoded) ? $decoded : [];
  }

  /**
   * Persist updated runtime character_data for a campaign-character row.
   */
  protected function persistRuntimeCharacterData(int $record_id, int $campaign_id, array $character_data): void {
    if ($record_id <= 0 || $campaign_id <= 0) {
      return;
    }
    $this->database->update('dc_campaign_characters')
      ->fields([
        'character_data' => json_encode($character_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      ])
      ->condition('id', $record_id)
      ->condition('campaign_id', $campaign_id)
      ->execute();
  }

  /**
   * Build occupied hex lookup grouped by room.
   *
   * @return array<string, array<string, bool>>
   *   Occupied hexes keyed by room ID, then "q,r".
   */
  protected function buildOccupiedLookupByRoom(array $dungeon_payload): array {
    $occupied = [];
    foreach (($dungeon_payload['entities'] ?? []) as $entity) {
      $room_id = (string) ($entity['placement']['room_id'] ?? '');
      if ($room_id === '' || !isset($entity['placement']['hex'])) {
        continue;
      }

      $hex = $entity['placement']['hex'];
      $occupied[$room_id][(int) ($hex['q'] ?? 0) . ',' . (int) ($hex['r'] ?? 0)] = TRUE;
    }

    return $occupied;
  }

  /**
   * Resolve a stable placement for an injected player character.
   */
  protected function resolveCharacterPlacement(array $dungeon_payload, string $room_id, array $record, array $occupied): array {
    $room_hexes = $this->getRoomHexes($dungeon_payload, $room_id);
    $preferred = [
      'q' => (int) ($record['position_q'] ?? 0),
      'r' => (int) ($record['position_r'] ?? 0),
    ];
    $preferred_key = $preferred['q'] . ',' . $preferred['r'];

    if (($room_hexes === [] || $this->roomContainsHex($room_hexes, $preferred['q'], $preferred['r']))
      && !isset($occupied[$preferred_key])) {
      return $preferred;
    }

    foreach ($room_hexes as $hex) {
      if (!isset($hex['q'], $hex['r'])) {
        continue;
      }
      $candidate = [
        'q' => (int) $hex['q'],
        'r' => (int) $hex['r'],
      ];
      $candidate_key = $candidate['q'] . ',' . $candidate['r'];
      if (!isset($occupied[$candidate_key])) {
        return $candidate;
      }
    }

    return $preferred;
  }

  /**
   * Resolve a stable placement for an injected room NPC.
   */
  protected function resolveRoomNpcPlacement(array $dungeon_payload, string $room_id, array $record, array $occupied): array {
    $preferred = [
      'q' => (int) ($record['position_q'] ?? 0),
      'r' => (int) ($record['position_r'] ?? 0),
    ];
    $room_hexes = $this->getRoomHexes($dungeon_payload, $room_id);
    $preferred_key = $preferred['q'] . ',' . $preferred['r'];
    if (($room_hexes === [] || $this->roomContainsHex($room_hexes, $preferred['q'], $preferred['r']))
      && !isset($occupied[$preferred_key])) {
      return $preferred;
    }

    foreach ($room_hexes as $hex) {
      if (!isset($hex['q'], $hex['r'])) {
        continue;
      }
      $candidate = [
        'q' => (int) $hex['q'],
        'r' => (int) $hex['r'],
      ];
      $candidate_key = $candidate['q'] . ',' . $candidate['r'];
      if (!isset($occupied[$candidate_key])) {
        return $candidate;
      }
    }

    return $preferred;
  }

  /**
   * Resolve room hex definitions from keyed or list-shaped payloads.
   *
   * @return array<int, array<string, mixed>>
   *   Room hex payloads.
   */
  protected function getRoomHexes(array $dungeon_payload, string $room_id): array {
    if ($room_id === '') {
      return [];
    }

    if (is_array($dungeon_payload['rooms'][$room_id]['hexes'] ?? NULL)) {
      return $dungeon_payload['rooms'][$room_id]['hexes'];
    }

    foreach (($dungeon_payload['rooms'] ?? []) as $room) {
      if (($room['room_id'] ?? '') === $room_id && is_array($room['hexes'] ?? NULL)) {
        return $room['hexes'];
      }
    }

    return [];
  }

  /**
   * Determine whether a room contains a specific hex.
   */
  protected function roomContainsHex(array $room_hexes, int $q, int $r): bool {
    foreach ($room_hexes as $hex) {
      if ((int) ($hex['q'] ?? 0) === $q && (int) ($hex['r'] ?? 0) === $r) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Inject owner-linked runtime followers as ally NPC entities.
   */
  protected function injectOwnedRuntimeFollowerEntities(array &$dungeon_payload, array $record, array $char_data, string $room_id, int $owner_q, int $owner_r, array &$occupied): void {
    $character_id = (string) ((int) ($record['source_character_id'] ?? 0));
    if ($character_id === '' || $character_id === '0') {
      $character_id = (string) ($record['id'] ?? '');
    }
    if ($character_id === '') {
      return;
    }

    $canonical_char_data = isset($char_data['character']) && is_array($char_data['character'])
      ? $char_data['character']
      : $char_data;
    $profiles = $this->followerSubsystem->resolveRuntimeFollowerProfiles($canonical_char_data, $character_id);
    if ($profiles === []) {
      return;
    }

    foreach ($profiles as $profile) {
      $instance_id = trim((string) ($profile['instance_id'] ?? ''));
      if ($instance_id === '') {
        continue;
      }

      $already_present = FALSE;
      foreach (($dungeon_payload['entities'] ?? []) as $entity) {
        $existing_instance_id = (string) ($entity['instance_id'] ?? $entity['entity_instance_id'] ?? '');
        if ($existing_instance_id === $instance_id) {
          $already_present = TRUE;
          break;
        }
      }
      if ($already_present) {
        continue;
      }

      $placement = $this->findAdjacentCompanionHex($dungeon_payload, $room_id, $owner_q, $owner_r, $occupied);
      $occupied[$placement['q'] . ',' . $placement['r']] = TRUE;

      $extra_metadata = is_array($profile['metadata'] ?? NULL) ? $profile['metadata'] : [];
      $dungeon_payload['entities'][] = [
        'entity_type' => 'npc',
        'instance_id' => $instance_id,
        'entity_instance_id' => $instance_id,
        'entity_ref' => [
          'content_type' => 'npc',
          'content_id' => (string) ($profile['content_id'] ?? ''),
        ],
        'placement' => [
          'room_id' => $room_id,
          'hex' => $placement,
          'spawn_type' => 'npc',
        ],
        'state' => [
          'active' => TRUE,
          'metadata' => array_merge($extra_metadata, [
            'display_name' => (string) ($profile['display_name'] ?? 'Follower'),
            'name' => (string) ($profile['display_name'] ?? 'Follower'),
            'role' => (string) ($profile['role'] ?? 'follower'),
            'description' => (string) ($profile['description'] ?? ''),
            'team' => (string) ($profile['team'] ?? 'ally'),
            'stats' => is_array($profile['stats'] ?? NULL) ? $profile['stats'] : [],
            'movement_speed' => (int) ($profile['movement_speed'] ?? 25),
            'actions_per_turn' => (int) ($profile['actions_per_turn'] ?? 2),
            'initiative_bonus' => (int) ($profile['initiative_bonus'] ?? 0),
            'traits' => is_array($profile['traits'] ?? NULL) ? $profile['traits'] : [],
            'attacks' => is_array($profile['attacks'] ?? NULL) ? $profile['attacks'] : [],
            'setting_state' => FALSE,
            'spawn_policy' => (string) ($profile['spawn_policy'] ?? 'owner_follower'),
          ]),
        ],
      ];
    }
  }

  /**
   * Find a free adjacent hex for the companion.
   */
  protected function findAdjacentCompanionHex(array $dungeon_payload, string $room_id, int $owner_q, int $owner_r, array $occupied): array {
    $offsets = [
      ['q' => 1, 'r' => 0],
      ['q' => -1, 'r' => 0],
      ['q' => 0, 'r' => 1],
      ['q' => 0, 'r' => -1],
      ['q' => 1, 'r' => -1],
      ['q' => -1, 'r' => 1],
    ];
    $room_hexes = $this->getRoomHexes($dungeon_payload, $room_id);
    $room_lookup = [];
    foreach ($room_hexes as $hex) {
      if (!isset($hex['q'], $hex['r'])) {
        continue;
      }
      $room_lookup[(int) $hex['q'] . ',' . (int) $hex['r']] = TRUE;
    }

    foreach ($offsets as $offset) {
      $candidate = [
        'q' => $owner_q + $offset['q'],
        'r' => $owner_r + $offset['r'],
      ];
      $key = $candidate['q'] . ',' . $candidate['r'];
      if (($room_lookup !== [] && !isset($room_lookup[$key])) || isset($occupied[$key])) {
        continue;
      }
      return $candidate;
    }

    return ['q' => $owner_q, 'r' => $owner_r];
  }

}
