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

  protected ?InstitutionMembershipService $institutionMembershipService;

  protected ?InstitutionDispositionMatrixService $institutionDispositionMatrixService;

  /**
   * Tracks campaigns seeded for institutional matrix defaults in this request.
   *
   * @var array<int,bool>
   */
  protected array $institutionMatrixSeededCampaigns = [];

  public function __construct(
    Connection $database,
    FollowerSubsystemService $follower_subsystem,
    ?NpcSheetGenerationService $npc_sheet_generation_service = NULL,
    ?CharacterPortraitGenerationService $character_portrait_generator = NULL,
    ?InstitutionMembershipService $institution_membership_service = NULL,
    ?InstitutionDispositionMatrixService $institution_disposition_matrix_service = NULL,
  ) {
    $this->database = $database;
    $this->followerSubsystem = $follower_subsystem;
    $this->npcSheetGenerationService = $npc_sheet_generation_service;
    $this->characterPortraitGenerator = $character_portrait_generator;
    $this->institutionMembershipService = $institution_membership_service;
    $this->institutionDispositionMatrixService = $institution_disposition_matrix_service;
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
  public function syncActiveRoomPlayerEntities(array $dungeon_payload, int $campaign_id, ?string $preferred_actor_id = NULL, array $diagnostic_context = []): array {
    $overall_started_at = hrtime(true);
    $active_room_id = trim((string) ($dungeon_payload['active_room_id'] ?? ''));
    if ($campaign_id <= 0 || $active_room_id === '') {
      return $dungeon_payload;
    }

    $preferred_actor_id = trim((string) $preferred_actor_id);
    $stage_started_at = hrtime(true);
    $all_records = $this->loadActivePlayerRecords($campaign_id);
    $load_active_player_records_ms = round((hrtime(true) - $stage_started_at) / 1000000, 2);
    $stage_started_at = hrtime(true);
    $records = $this->filterRelevantRecords($all_records, $active_room_id, $preferred_actor_id);
    $filter_relevant_records_ms = round((hrtime(true) - $stage_started_at) / 1000000, 2);
    $stage_started_at = hrtime(true);
    $dungeon_payload = $this->syncActiveRoomNpcEntities($dungeon_payload, $campaign_id, $active_room_id, $diagnostic_context);
    $sync_active_room_npcs_ms = round((hrtime(true) - $stage_started_at) / 1000000, 2);
    $force_active_room_placement = FALSE;
    if ($records === []) {
      if ($this->hasPlayerEntityInRoom($dungeon_payload, $active_room_id)) {
        if (!empty($diagnostic_context)) {
          \Drupal::logger('dungeoncrawler')->debug(
            'Active-room player sync timing: campaign=@campaign_id actor=@actor_id trace=@trace_id total_ms=@total load_records_ms=@load_records_ms filter_records_ms=@filter_records_ms sync_npcs_ms=@sync_npcs_ms status=@status all_records=@all_records filtered_records=@filtered_records active_room_id=@active_room_id',
            [
              '@campaign_id' => $campaign_id,
              '@actor_id' => $preferred_actor_id,
              '@trace_id' => trim((string) ($diagnostic_context['trace_id'] ?? '')),
              '@total' => round((hrtime(true) - $overall_started_at) / 1000000, 2),
              '@load_records_ms' => $load_active_player_records_ms,
              '@filter_records_ms' => $filter_relevant_records_ms,
              '@sync_npcs_ms' => $sync_active_room_npcs_ms,
              '@status' => 'early_return_player_already_present',
              '@all_records' => count($all_records),
              '@filtered_records' => 0,
              '@active_room_id' => $active_room_id,
            ]
          );
        }
        return $dungeon_payload;
      }
      if ($all_records === []) {
        if (!empty($diagnostic_context)) {
          \Drupal::logger('dungeoncrawler')->debug(
            'Active-room player sync timing: campaign=@campaign_id actor=@actor_id trace=@trace_id total_ms=@total load_records_ms=@load_records_ms filter_records_ms=@filter_records_ms sync_npcs_ms=@sync_npcs_ms status=@status all_records=@all_records filtered_records=@filtered_records active_room_id=@active_room_id',
            [
              '@campaign_id' => $campaign_id,
              '@actor_id' => $preferred_actor_id,
              '@trace_id' => trim((string) ($diagnostic_context['trace_id'] ?? '')),
              '@total' => round((hrtime(true) - $overall_started_at) / 1000000, 2),
              '@load_records_ms' => $load_active_player_records_ms,
              '@filter_records_ms' => $filter_relevant_records_ms,
              '@sync_npcs_ms' => $sync_active_room_npcs_ms,
              '@status' => 'early_return_no_player_records',
              '@all_records' => 0,
              '@filtered_records' => 0,
              '@active_room_id' => $active_room_id,
            ]
          );
        }
        return $dungeon_payload;
      }
      $primary = $all_records[0];
      if ($preferred_actor_id !== '') {
        foreach ($all_records as $candidate) {
          if (trim((string) ($candidate['instance_id'] ?? '')) === $preferred_actor_id) {
            $primary = $candidate;
            break;
          }
        }
      }
      $records = [$primary];
      $force_active_room_placement = TRUE;
    }
    if ($records === []) {
      if (!empty($diagnostic_context)) {
        \Drupal::logger('dungeoncrawler')->debug(
          'Active-room player sync timing: campaign=@campaign_id actor=@actor_id trace=@trace_id total_ms=@total load_records_ms=@load_records_ms filter_records_ms=@filter_records_ms sync_npcs_ms=@sync_npcs_ms status=@status all_records=@all_records filtered_records=@filtered_records active_room_id=@active_room_id',
          [
            '@campaign_id' => $campaign_id,
            '@actor_id' => $preferred_actor_id,
            '@trace_id' => trim((string) ($diagnostic_context['trace_id'] ?? '')),
            '@total' => round((hrtime(true) - $overall_started_at) / 1000000, 2),
            '@load_records_ms' => $load_active_player_records_ms,
            '@filter_records_ms' => $filter_relevant_records_ms,
            '@sync_npcs_ms' => $sync_active_room_npcs_ms,
            '@status' => 'early_return_unresolved_records',
            '@all_records' => count($all_records),
            '@filtered_records' => 0,
            '@active_room_id' => $active_room_id,
          ]
        );
      }
      return $dungeon_payload;
    }

    $stage_started_at = hrtime(true);
    $dungeon_payload['entities'] = array_values(array_filter(
      $dungeon_payload['entities'] ?? [],
      static function (array $entity): bool {
        return strtolower((string) ($entity['entity_type'] ?? '')) !== 'player_character';
      }
    ));
    $remove_existing_pc_entities_ms = round((hrtime(true) - $stage_started_at) / 1000000, 2);
    $occupied = $this->buildOccupiedLookupByRoom($dungeon_payload);
    $record_count = count($records);
    $record_identity_ms = 0.0;
    $record_backfill_ms = 0.0;
    $record_backfill_persist_ms = 0.0;
    $record_placement_ms = 0.0;
    $record_placement_persist_ms = 0.0;
    $record_membership_sync_ms = 0.0;
    $membership_sync_bypassed = $this->shouldBypassMembershipSyncForReadLane($diagnostic_context);
    $record_inject_followers_ms = 0.0;
    $backfilled_record_count = 0;
    $placement_persist_count = 0;

    foreach ($records as $record) {
      $stage_started_at = hrtime(true);
      $record = $this->ensurePersistentRuntimeRecordIdentity($record, $campaign_id, 'pc');
      $record_identity_ms += round((hrtime(true) - $stage_started_at) / 1000000, 2);
      $instance_id = trim((string) ($record['instance_id'] ?? ''));
      $is_preferred_actor = $preferred_actor_id !== '' && $instance_id === $preferred_actor_id;
      $room_id = $is_preferred_actor
        ? $active_room_id
        : ($this->resolveRecordRoomId($record) ?: $active_room_id);
      if ($force_active_room_placement) {
        $room_id = $active_room_id;
      }
      $char_data = $this->decodeCharacterData($record);
      $source_character_id = (int) ($record['source_character_id'] ?? 0);
      if ($source_character_id <= 0) {
        throw new \RuntimeException(sprintf(
          'Campaign runtime PC row %d is missing source_character_id.',
          (int) ($record['id'] ?? 0)
        ));
      }
      $stage_started_at = hrtime(true);
      $backfill_result = $this->followerSubsystem->backfillPersistedActorRecordsOnCharacterData(
        $char_data,
        (string) $source_character_id
      );
      $record_backfill_ms += round((hrtime(true) - $stage_started_at) / 1000000, 2);
      if (!empty($backfill_result['updated'])) {
        $backfilled_record_count++;
        $char_data = is_array($backfill_result['character_data'] ?? NULL) ? $backfill_result['character_data'] : $char_data;
        $stage_started_at = hrtime(true);
        $this->persistRuntimeCharacterData(
          (int) ($record['id'] ?? 0),
          $campaign_id,
          $char_data
        );
        $record_backfill_persist_ms += round((hrtime(true) - $stage_started_at) / 1000000, 2);
        $record['character_data'] = json_encode($char_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      }
      $canonical_char_data = $this->loadCanonicalPlayerCharacterState($record, $campaign_id);
      if ($canonical_char_data !== $char_data) {
        $stage_started_at = hrtime(true);
        $this->persistRuntimeCharacterData(
          (int) ($record['id'] ?? 0),
          $campaign_id,
          $canonical_char_data
        );
        $record_backfill_persist_ms += round((hrtime(true) - $stage_started_at) / 1000000, 2);
        $record['character_data'] = json_encode($canonical_char_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      }
      $char_data = $canonical_char_data;
      $stage_started_at = hrtime(true);
      $placement = $this->resolveCharacterPlacement($dungeon_payload, $room_id, $record, $occupied[$room_id] ?? []);
      $placement_contract = $this->resolvePlacementRoomAndH3(
        $dungeon_payload,
        $room_id,
        $placement,
        sprintf('pc-runtime-row-%d', (int) ($record['id'] ?? 0))
      );
      $record_placement_ms += round((hrtime(true) - $stage_started_at) / 1000000, 2);
      $room_id = $placement_contract['room_id'];
      $placement['h3_index_res14'] = $placement_contract['h3_index_res14'];
      $occupied[$room_id][$placement['q'] . ',' . $placement['r']] = TRUE;
      $stage_started_at = hrtime(true);
      $this->persistRuntimePlacement((int) ($record['id'] ?? 0), $campaign_id, $room_id, $placement);
      $record_placement_persist_ms += round((hrtime(true) - $stage_started_at) / 1000000, 2);
      $placement_persist_count++;

      $hp_max = (int) ($record['hp_max'] ?: ($char_data['hp']['max'] ?? $char_data['calculated_stats']['max_hp'] ?? 20));
      $hp_current = (int) ($record['hp_current'] ?: ($char_data['hp']['current'] ?? $hp_max));
      $armor_class = (int) ($record['armor_class'] ?: ($char_data['ac'] ?? $char_data['calculated_stats']['ac'] ?? 10));
      $resolved_class = is_array($char_data['class'] ?? NULL)
        ? (string) ($char_data['class']['name'] ?? '')
        : (string) ($char_data['class'] ?? '');
      $resolved_ancestry = is_array($char_data['ancestry'] ?? NULL)
        ? (string) ($char_data['ancestry']['name'] ?? '')
        : (string) ($char_data['ancestry'] ?? '');
      $resolved_level = (int) ($record['level'] ?? $char_data['level'] ?? 1);
      $resolved_abilities = is_array($char_data['abilities'] ?? NULL) ? $char_data['abilities'] : [];
      $resolved_skills = is_array($char_data['skills'] ?? NULL) ? $char_data['skills'] : [];
      $resolved_saves = is_array($char_data['saves'] ?? NULL) ? $char_data['saves'] : [];
      $resolved_spellcasting = is_array($char_data['spellcasting'] ?? NULL)
        ? $char_data['spellcasting']
        : (is_array($char_data['spells'] ?? NULL) ? $char_data['spells'] : []);
      $resolved_features = is_array($char_data['features'] ?? NULL) ? $char_data['features'] : [];
      $resolved_actions = is_array($char_data['actions'] ?? NULL) ? $char_data['actions'] : [];
      $resolved_resources = is_array($char_data['resources'] ?? NULL) ? $char_data['resources'] : [];
      $resolved_inventory = is_array($char_data['inventory'] ?? NULL) ? $char_data['inventory'] : [];
      $resolved_equipment = is_array($char_data['equipment'] ?? NULL) ? $char_data['equipment'] : [];
      $resolved_feats = is_array($char_data['feats'] ?? NULL)
        ? $char_data['feats']
        : (is_array($resolved_features['feats'] ?? NULL) ? $resolved_features['feats'] : []);
      $resolved_class_features = is_array($char_data['class_features'] ?? NULL)
        ? $char_data['class_features']
        : (is_array($resolved_features['classFeatures'] ?? NULL) ? $resolved_features['classFeatures'] : []);
      $resolved_conditions = is_array($char_data['conditions'] ?? NULL) ? array_values($char_data['conditions']) : [];
      $instance_id = (string) ($record['instance_id'] ?? '');
      if ($instance_id === '') {
        $instance_id = sprintf('pc-%d-%d', $campaign_id, (int) ($record['id'] ?? 0));
      }
      if (!$membership_sync_bypassed) {
        $stage_started_at = hrtime(true);
        $this->syncRuntimeActorInstitutionMemberships($campaign_id, 'pc', $instance_id, $char_data);
        $record_membership_sync_ms += round((hrtime(true) - $stage_started_at) / 1000000, 2);
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
          'facing' => 0,
          'h3_index_res14' => $placement['h3_index_res14'],
        ],
        'state' => [
          'character_data' => $char_data,
          'abilities' => $resolved_abilities,
          'skills' => $resolved_skills,
          'saves' => $resolved_saves,
          'spells' => $resolved_spellcasting,
          'spellcasting' => $resolved_spellcasting,
          'spellbook' => is_array($char_data['spellbook'] ?? NULL) ? $char_data['spellbook'] : [],
          'known_spells' => is_array($char_data['known_spells'] ?? NULL) ? $char_data['known_spells'] : [],
          'features' => $resolved_features,
          'feats' => $resolved_feats,
          'class_features' => $resolved_class_features,
          'actions' => $resolved_actions,
          'resources' => $resolved_resources,
          'inventory' => $resolved_inventory,
          'equipment' => $resolved_equipment,
          'conditions' => $resolved_conditions,
          'metadata' => [
            'display_name' => $name,
            'name' => $name,
            'team' => 'player',
            'character_id' => (int) ($record['id'] ?? 0),
            'source_character_id' => $source_character_id,
            'campaign_character_id' => (int) ($record['id'] ?? 0),
            'runtime_entity_id' => $instance_id,
            'class' => $resolved_class,
            'ancestry' => $resolved_ancestry,
            'level' => $resolved_level,
            'abilities' => $resolved_abilities,
            'skills' => $resolved_skills,
            'saves' => $resolved_saves,
            'spellcasting' => $resolved_spellcasting,
            'conditions' => $resolved_conditions,
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
      $stage_started_at = hrtime(true);
      $this->injectOwnedRuntimeFollowerEntities($dungeon_payload, $campaign_id, $record, $char_data, $room_id, $placement['q'], $placement['r'], $room_occupied);
      $record_inject_followers_ms += round((hrtime(true) - $stage_started_at) / 1000000, 2);
      $occupied[$room_id] = $room_occupied;
    }

    if (!empty($diagnostic_context)) {
      \Drupal::logger('dungeoncrawler')->debug(
        'Active-room player sync timing: campaign=@campaign_id actor=@actor_id trace=@trace_id total_ms=@total load_records_ms=@load_records_ms filter_records_ms=@filter_records_ms sync_npcs_ms=@sync_npcs_ms remove_existing_pc_ms=@remove_existing_pc_ms record_count=@record_count record_identity_ms=@record_identity_ms backfill_ms=@backfill_ms backfill_persist_ms=@backfill_persist_ms backfilled_records=@backfilled_records placement_ms=@placement_ms placement_persist_ms=@placement_persist_ms placement_persist_count=@placement_persist_count membership_sync_ms=@membership_sync_ms membership_sync_bypassed=@membership_sync_bypassed inject_followers_ms=@inject_followers_ms all_records=@all_records filtered_records=@filtered_records force_active_room_placement=@force_active_room_placement active_room_id=@active_room_id',
        [
          '@campaign_id' => $campaign_id,
          '@actor_id' => $preferred_actor_id,
          '@trace_id' => trim((string) ($diagnostic_context['trace_id'] ?? '')),
          '@total' => round((hrtime(true) - $overall_started_at) / 1000000, 2),
          '@load_records_ms' => $load_active_player_records_ms,
          '@filter_records_ms' => $filter_relevant_records_ms,
          '@sync_npcs_ms' => $sync_active_room_npcs_ms,
          '@remove_existing_pc_ms' => $remove_existing_pc_entities_ms,
          '@record_count' => $record_count,
          '@record_identity_ms' => $record_identity_ms,
          '@backfill_ms' => $record_backfill_ms,
          '@backfill_persist_ms' => $record_backfill_persist_ms,
          '@backfilled_records' => $backfilled_record_count,
          '@placement_ms' => $record_placement_ms,
          '@placement_persist_ms' => $record_placement_persist_ms,
          '@placement_persist_count' => $placement_persist_count,
          '@membership_sync_ms' => $record_membership_sync_ms,
          '@membership_sync_bypassed' => $membership_sync_bypassed ? 'yes' : 'no',
          '@inject_followers_ms' => $record_inject_followers_ms,
          '@all_records' => count($all_records),
          '@filtered_records' => count($records),
          '@force_active_room_placement' => $force_active_room_placement ? 'yes' : 'no',
          '@active_room_id' => $active_room_id,
        ]
      );
    }

    return $dungeon_payload;
  }

  /**
   * Determine whether the payload already has a player in the active room.
   */
  protected function hasPlayerEntityInRoom(array $dungeon_payload, string $active_room_id): bool {
    foreach ((array) ($dungeon_payload['entities'] ?? []) as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      $entity_room_id = trim((string) ($entity['placement']['room_id'] ?? ''));
      if ($entity_room_id !== $active_room_id) {
        continue;
      }
      $entity_type = strtolower(trim((string) ($entity['entity_type'] ?? ($entity['entity_ref']['content_type'] ?? ''))));
      $team = strtolower(trim((string) ($entity['state']['metadata']['team'] ?? ($entity['state']['team'] ?? ''))));
      if ($entity_type === 'player_character' || in_array($team, ['player', 'player_character', 'pc'], TRUE)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Resolve authoritative follower runtime rows for one player character.
   *
   * @return array<int, array<string, mixed>>
   *   Follower roster keyed by runtime entity identity.
   */
  public function getFollowers(int $campaign_id, int $owner_character_identifier): array {
    if ($campaign_id <= 0 || $owner_character_identifier <= 0) {
      return [];
    }

    $query = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['id', 'source_character_id', 'character_data'])
      ->condition('campaign_id', $campaign_id)
      ->condition('type', 'pc');
    $query->condition(
      $query->orConditionGroup()
        ->condition('id', $owner_character_identifier)
        ->condition('source_character_id', $owner_character_identifier)
    );
    $owner_record = $query
      ->orderBy('updated', 'DESC')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($owner_record)) {
      return [];
    }

    $owner_runtime_character_id = (int) ($owner_record['id'] ?? 0);
    $owner_source_character_id = (int) ($owner_record['source_character_id'] ?? 0);
    if ($owner_runtime_character_id <= 0) {
      throw new \RuntimeException('Follower roster resolution requires a valid runtime owner character ID.');
    }
    if ($owner_source_character_id <= 0) {
      $owner_source_character_id = $owner_runtime_character_id;
    }

    $char_data = $this->decodeCharacterData($owner_record);
    $canonical_char_data = isset($char_data['character']) && is_array($char_data['character'])
      ? $char_data['character']
      : $char_data;

    $profiles = $this->followerSubsystem->resolveRuntimeFollowerProfiles($canonical_char_data, (string) $owner_source_character_id);
    if ($profiles === []) {
      return [];
    }

    $followers = [];
    foreach ($profiles as $profile) {
      $instance_id = trim((string) ($profile['instance_id'] ?? ''));
      if ($instance_id === '') {
        throw new \RuntimeException('Follower roster entry is missing runtime entity identity.');
      }

      $metadata = is_array($profile['metadata'] ?? NULL) ? $profile['metadata'] : [];
      $follower_kind = strtolower(trim((string) ($profile['follower_kind'] ?? $metadata['follower_kind'] ?? $profile['role'] ?? '')));
      if ($follower_kind === '') {
        throw new \RuntimeException(sprintf('Follower roster entry "%s" is missing follower kind.', $instance_id));
      }

      $follower_role = strtolower(trim((string) ($profile['role'] ?? $metadata['role'] ?? $follower_kind)));
      $follower_display_name = trim((string) ($profile['display_name'] ?? $metadata['display_name'] ?? $metadata['name'] ?? ''));
      if ($follower_display_name === '') {
        throw new \RuntimeException(sprintf(
          'Follower roster entry "%s" is missing display_name.',
          $instance_id
        ));
      }
      $role_candidates = $this->buildFollowerRoleCandidates($follower_role);
      if ($role_candidates === []) {
        $role_candidates = [$follower_kind];
      }
      $follower_runtime_record = $this->queryFollowerRuntimeRecord($campaign_id, $role_candidates, [
        'instance_id' => $instance_id,
        'exclude_id' => $owner_runtime_character_id,
      ]);
      if ($follower_runtime_record === []) {
        $follower_runtime_record = $this->queryFollowerRuntimeRecord($campaign_id, $role_candidates, [
          'name' => $follower_display_name,
          'exclude_id' => $owner_runtime_character_id,
        ]);
      }
      if ($follower_runtime_record === []) {
        $follower_runtime_record = $this->materializeMissingFollowerRuntimeRecord(
          $campaign_id,
          $instance_id,
          $role_candidates,
          $follower_display_name,
          $owner_source_character_id
        );
      }
      if ($follower_runtime_record === []) {
        throw new \RuntimeException(sprintf(
          'Follower roster entry "%s" has no matching runtime row by identity.',
          $instance_id
        ));
      }
      $follower_runtime_record = $this->enforceFollowerRuntimeRecordIdentity(
        $campaign_id,
        $follower_runtime_record,
        $instance_id,
        $owner_source_character_id,
        $follower_display_name
      );
      $follower_runtime_character_id = (int) ($follower_runtime_record['id'] ?? 0);
      if ($follower_runtime_character_id <= 0 || $follower_runtime_character_id === $owner_runtime_character_id) {
        throw new \RuntimeException(sprintf(
          'Follower roster entry "%s" failed runtime character_id resolution.',
          $instance_id
        ));
      }

      $follower_source_character_id = (int) ($follower_runtime_record['source_character_id'] ?? 0);
      if ($follower_source_character_id <= 0) {
        $follower_source_character_id = (int) ($metadata['source_character_id'] ?? 0);
      }
      if ($follower_source_character_id <= 0) {
        throw new \RuntimeException(sprintf(
          'Follower roster entry "%s" is missing source character identity.',
          $instance_id
        ));
      }

      $resolved_name = trim((string) ($profile['display_name'] ?? $metadata['display_name'] ?? $follower_runtime_record['name'] ?? ''));
      $followers[$instance_id] = [
        'runtime_entity_id' => $instance_id,
        'display_name' => $resolved_name !== '' ? $resolved_name : 'Follower',
        'follower_kind' => $follower_kind,
        'role' => $follower_role !== '' ? $follower_role : $follower_kind,
        'owner_character_id' => $owner_runtime_character_id,
        'owner_source_character_id' => $owner_source_character_id,
        'follower_character_id' => $follower_runtime_character_id,
        'follower_source_character_id' => $follower_source_character_id,
        'content_id' => (string) ($profile['content_id'] ?? ''),
        'portrait_url' => $this->resolveFollowerRuntimePortrait($follower_runtime_record),
      ];
    }

    return array_values($followers);
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
        'position_h3',
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
        && $instance_id === $preferred_actor_id;
    }));

    if ($preferred_actor_id !== '') {
      usort($filtered, static function (array $left, array $right) use ($preferred_actor_id): int {
        $left_match = trim((string) ($left['instance_id'] ?? '')) === $preferred_actor_id ? 0 : 1;
        $right_match = trim((string) ($right['instance_id'] ?? '')) === $preferred_actor_id ? 0 : 1;
        return $left_match <=> $right_match;
      });
    }

    $deduped = [];
    $seen_keys = [];
    foreach ($filtered as $record) {
      $identity_key = $this->buildPlayerRecordIdentityKey($record);
      if (isset($seen_keys[$identity_key])) {
        continue;
      }
      $seen_keys[$identity_key] = TRUE;
      $deduped[] = $record;
    }

    return $deduped;
  }

  /**
   * Build one canonical identity key for a runtime player record.
   */
  protected function buildPlayerRecordIdentityKey(array $record): string {
    $source_character_id = (int) ($record['source_character_id'] ?? 0);
    if ($source_character_id > 0) {
      return 'source:' . $source_character_id;
    }

    $character_id = (int) ($record['character_id'] ?? 0);
    if ($character_id > 0) {
      return 'character:' . $character_id;
    }

    $instance_id = trim((string) ($record['instance_id'] ?? ''));
    if ($instance_id !== '') {
      return 'instance:' . $instance_id;
    }

    return 'record:' . (int) ($record['id'] ?? 0);
  }

  /**
   * Ensure active-room NPC runtime records are reflected in the dungeon payload.
   */
  protected function syncActiveRoomNpcEntities(array $dungeon_payload, int $campaign_id, string $active_room_id, array $diagnostic_context = []): array {
    $overall_started_at = hrtime(true);
    $stage_started_at = hrtime(true);
    $room_refs = $this->resolveActiveRoomReferences($dungeon_payload, $campaign_id, $active_room_id);
    $resolve_room_refs_ms = round((hrtime(true) - $stage_started_at) / 1000000, 2);
    if ($room_refs === []) {
      return $dungeon_payload;
    }

    $stage_started_at = hrtime(true);
    $records = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['id', 'instance_id', 'name', 'portrait', 'state_data', 'character_data', 'uid', 'source_character_id', 'position_q', 'position_r', 'position_h3', 'location_ref'])
      ->condition('campaign_id', $campaign_id)
      ->condition('type', 'npc')
      ->condition('location_type', 'room')
      ->condition('location_ref', $room_refs, 'IN')
      ->orderBy('id', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    $load_npc_records_ms = round((hrtime(true) - $stage_started_at) / 1000000, 2);
    if ($records === []) {
      return $dungeon_payload;
    }

    $occupied = $this->buildOccupiedLookupByRoom($dungeon_payload);
    $identity_ms = 0.0;
    $membership_sync_ms = 0.0;
    $membership_sync_bypassed = $this->shouldBypassMembershipSyncForReadLane($diagnostic_context);
    $generation_pipeline_ms = 0.0;
    $placement_persist_ms = 0.0;
    $matched_entity_count = 0;
    $created_entity_count = 0;
    foreach ($records as $record) {
      $stage_started_at = hrtime(true);
      [$record, $state, $instance_id, $content_id] = $this->ensurePersistentNpcRuntimeIdentity($record, $campaign_id, $dungeon_payload);
      $identity_ms += round((hrtime(true) - $stage_started_at) / 1000000, 2);
      $character_data = $this->decodeCharacterData($record);
      $runtime_equipment = array_values(is_array($state['equipment'] ?? NULL) ? $state['equipment'] : []);
      $runtime_inventory = is_array($state['inventory'] ?? NULL) ? $state['inventory'] : [];
      if ($runtime_inventory === [] && $runtime_equipment !== []) {
        $runtime_inventory = ['carried' => $runtime_equipment];
      }
      $runtime_currency = is_array($state['currency'] ?? NULL) ? $state['currency'] : [];
      $resolved_class = is_array($character_data['class'] ?? NULL)
        ? (string) ($character_data['class']['name'] ?? '')
        : (string) ($character_data['class'] ?? ($state['class'] ?? ''));
      $resolved_ancestry = is_array($character_data['ancestry'] ?? NULL)
        ? (string) ($character_data['ancestry']['name'] ?? '')
        : (string) ($character_data['ancestry'] ?? ($state['ancestry'] ?? ''));
      $resolved_level = (int) ($character_data['level'] ?? $state['level'] ?? 1);
      $resolved_abilities = is_array($character_data['abilities'] ?? NULL) ? $character_data['abilities'] : [];
      $resolved_skills = is_array($character_data['skills'] ?? NULL) ? $character_data['skills'] : [];
      $resolved_saves = is_array($character_data['saves'] ?? NULL) ? $character_data['saves'] : [];
      $resolved_spellcasting = is_array($character_data['spellcasting'] ?? NULL)
        ? $character_data['spellcasting']
        : (is_array($character_data['spells'] ?? NULL) ? $character_data['spells'] : []);
      $resolved_conditions = is_array($character_data['conditions'] ?? NULL) ? array_values($character_data['conditions']) : [];
      if (!$membership_sync_bypassed) {
        $stage_started_at = hrtime(true);
        $this->syncRuntimeActorInstitutionMemberships($campaign_id, 'npc', $instance_id, $character_data);
        $membership_sync_ms += round((hrtime(true) - $stage_started_at) / 1000000, 2);
      }
      $record_room_id = $this->resolveRecordRoomId($record);
      $canonical_anchor = $this->resolveCanonicalUndeadCryptNpcAnchor($content_id, $record_room_id, $dungeon_payload);
      if (is_array($canonical_anchor)) {
        $record['position_q'] = (int) $canonical_anchor['q'];
        $record['position_r'] = (int) $canonical_anchor['r'];
        $record['position_h3'] = '';
      }
      $stage_started_at = hrtime(true);
      $this->ensureRuntimeNpcGenerationPipeline($campaign_id, $record, $state, $content_id);
      $generation_pipeline_ms += round((hrtime(true) - $stage_started_at) / 1000000, 2);
      $name = trim((string) ($record['name'] ?? ''));
      $matched = FALSE;

      if (!isset($dungeon_payload['entities']) || !is_array($dungeon_payload['entities'])) {
        $dungeon_payload['entities'] = [];
      }
      foreach ($dungeon_payload['entities'] as &$entity) {
        $entity_runtime_character_id = (int) ($entity['state']['metadata']['campaign_character_id'] ?? 0);
        $entity_instance_id = trim((string) ($entity['instance_id'] ?? $entity['entity_instance_id'] ?? ''));
        $entity_content_id = $this->canonicalizeNpcContentId((string) ($entity['entity_ref']['content_id'] ?? ''));
        $entity_room_id = trim((string) ($entity['placement']['room_id'] ?? ''));
        $entity_display_name = strtolower(trim((string) (
          $entity['state']['metadata']['display_name']
          ?? $entity['state']['metadata']['name']
          ?? $entity['name']
          ?? ''
        )));
        $record_display_name = strtolower($name);
        $same_room_scope = $entity_room_id !== '' && in_array($entity_room_id, $room_refs, TRUE);
        if (
          $entity_runtime_character_id === (int) ($record['id'] ?? 0)
          || ($instance_id !== '' && $entity_instance_id === $instance_id)
          || ($same_room_scope && $content_id !== '' && $entity_content_id === $content_id)
          || ($same_room_scope && $record_display_name !== '' && $entity_display_name === $record_display_name)
        ) {
          $resolved_room_id = $entity_room_id !== '' ? $entity_room_id : $active_room_id;
          if ($resolved_room_id === '') {
            throw new \RuntimeException(sprintf(
              'Campaign runtime sync contract violation: NPC %d cannot resolve placement room_id.',
              (int) ($record['id'] ?? 0)
            ));
          }
          $room_occupied = $occupied[$resolved_room_id] ?? [];
          if (isset($entity['placement']['hex']) && is_array($entity['placement']['hex'])) {
            $existing_q = (int) ($entity['placement']['hex']['q'] ?? 0);
            $existing_r = (int) ($entity['placement']['hex']['r'] ?? 0);
            unset($room_occupied[$existing_q . ',' . $existing_r]);
          }
          $placement = $this->resolveRoomNpcPlacement(
            $dungeon_payload,
            $resolved_room_id,
            $record,
            $room_occupied
          );
          $placement_contract = $this->resolvePlacementRoomAndH3(
            $dungeon_payload,
            $resolved_room_id,
            $placement,
            sprintf('npc-runtime-row-%d', (int) ($record['id'] ?? 0))
          );
          $resolved_room_id = $placement_contract['room_id'];
          $placement['h3_index_res14'] = $placement_contract['h3_index_res14'];
          $entity['instance_id'] = $instance_id;
          $entity['entity_instance_id'] = $instance_id;
          if (!isset($entity['entity_ref']) || !is_array($entity['entity_ref'])) {
            $entity['entity_ref'] = [];
          }
          $entity['entity_ref']['content_id'] = $content_id;
          if (!isset($entity['placement']) || !is_array($entity['placement'])) {
            $entity['placement'] = [];
          }
          $entity['placement']['room_id'] = $resolved_room_id;
          $entity['placement']['hex'] = $placement;
          $entity['placement']['facing'] = isset($entity['placement']['facing'])
            ? ((int) $entity['placement']['facing'] % 6 + 6) % 6
            : 0;
          $entity['placement']['h3_index_res14'] = $placement['h3_index_res14'];
          $stage_started_at = hrtime(true);
          $this->persistRuntimePlacement((int) ($record['id'] ?? 0), $campaign_id, $resolved_room_id, $placement);
          $placement_persist_ms += round((hrtime(true) - $stage_started_at) / 1000000, 2);
          $occupied[$resolved_room_id][$placement['q'] . ',' . $placement['r']] = TRUE;
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
          $entity['state']['character_data'] = $character_data;
          $entity['state']['metadata']['class'] = $resolved_class;
          $entity['state']['metadata']['ancestry'] = $resolved_ancestry;
          $entity['state']['metadata']['level'] = $resolved_level;
          $entity['state']['metadata']['abilities'] = $resolved_abilities;
          $entity['state']['metadata']['skills'] = $resolved_skills;
          $entity['state']['metadata']['saves'] = $resolved_saves;
          $entity['state']['metadata']['spellcasting'] = $resolved_spellcasting;
          $entity['state']['metadata']['conditions'] = $resolved_conditions;
          $entity['state']['equipment'] = $runtime_equipment;
          $entity['state']['inventory'] = $runtime_inventory;
          if ($runtime_currency !== []) {
            $entity['state']['currency'] = $runtime_currency;
          }
          $entity['state']['metadata']['equipment'] = $runtime_equipment;
          if ($runtime_inventory !== []) {
            $entity['state']['metadata']['inventory'] = $runtime_inventory;
          }
          if ($runtime_currency !== []) {
            $entity['state']['metadata']['currency'] = $runtime_currency;
          }
          if (!isset($entity['state']['resources']) || !is_array($entity['state']['resources'])) {
            $entity['state']['resources'] = [];
          }
          if ($runtime_inventory !== []) {
            $entity['state']['resources']['inventory'] = $runtime_inventory;
          }
          if ($runtime_currency !== []) {
            $entity['state']['resources']['currency'] = $runtime_currency;
          }
          $matched_entity_count++;
          $matched = TRUE;
          break;
        }
      }
      unset($entity);

      if ($matched) {
        continue;
      }

      $placement = $this->resolveRoomNpcPlacement($dungeon_payload, $active_room_id, $record, $occupied[$active_room_id] ?? []);
      $placement_contract = $this->resolvePlacementRoomAndH3(
        $dungeon_payload,
        $active_room_id,
        $placement,
        sprintf('npc-runtime-row-%d', (int) ($record['id'] ?? 0))
      );
      $active_runtime_room_id = $placement_contract['room_id'];
      $placement['h3_index_res14'] = $placement_contract['h3_index_res14'];
      $occupied[$active_runtime_room_id][$placement['q'] . ',' . $placement['r']] = TRUE;
      $stage_started_at = hrtime(true);
      $this->persistRuntimePlacement((int) ($record['id'] ?? 0), $campaign_id, $active_runtime_room_id, $placement);
      $placement_persist_ms += round((hrtime(true) - $stage_started_at) / 1000000, 2);

      $dungeon_payload['entities'][] = [
        'entity_type' => 'npc',
        'instance_id' => $instance_id,
        'entity_instance_id' => $instance_id,
        'entity_ref' => [
          'content_type' => 'npc',
          'content_id' => $content_id !== '' ? $content_id : strtolower(str_replace(' ', '_', $name)),
        ],
        'placement' => [
          'room_id' => $active_runtime_room_id,
          'hex' => $placement,
          'facing' => 0,
          'h3_index_res14' => $placement['h3_index_res14'],
          'spawn_type' => 'npc',
        ],
        'state' => [
          'active' => TRUE,
          'character_data' => $character_data,
          'equipment' => $runtime_equipment,
          'inventory' => $runtime_inventory,
          'currency' => $runtime_currency,
          'resources' => [
            'inventory' => $runtime_inventory,
            'currency' => $runtime_currency,
          ],
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
            'class' => $resolved_class,
            'ancestry' => $resolved_ancestry,
            'level' => $resolved_level,
            'abilities' => $resolved_abilities,
            'skills' => $resolved_skills,
            'saves' => $resolved_saves,
            'spellcasting' => $resolved_spellcasting,
            'conditions' => $resolved_conditions,
            'equipment' => $runtime_equipment,
            'inventory' => $runtime_inventory,
            'currency' => $runtime_currency,
          ],
        ],
      ];
      $created_entity_count++;
    }

    if (!empty($diagnostic_context)) {
      \Drupal::logger('dungeoncrawler')->debug(
        'Active-room NPC sync timing: campaign=@campaign_id trace=@trace_id total_ms=@total resolve_room_refs_ms=@resolve_room_refs_ms load_npc_records_ms=@load_npc_records_ms npc_record_count=@npc_record_count identity_ms=@identity_ms membership_sync_ms=@membership_sync_ms membership_sync_bypassed=@membership_sync_bypassed generation_pipeline_ms=@generation_pipeline_ms placement_persist_ms=@placement_persist_ms matched_entity_count=@matched_entity_count created_entity_count=@created_entity_count room_ref_count=@room_ref_count active_room_id=@active_room_id',
        [
          '@campaign_id' => $campaign_id,
          '@trace_id' => trim((string) ($diagnostic_context['trace_id'] ?? '')),
          '@total' => round((hrtime(true) - $overall_started_at) / 1000000, 2),
          '@resolve_room_refs_ms' => $resolve_room_refs_ms,
          '@load_npc_records_ms' => $load_npc_records_ms,
          '@npc_record_count' => count($records),
          '@identity_ms' => $identity_ms,
          '@membership_sync_ms' => $membership_sync_ms,
          '@membership_sync_bypassed' => $membership_sync_bypassed ? 'yes' : 'no',
          '@generation_pipeline_ms' => $generation_pipeline_ms,
          '@placement_persist_ms' => $placement_persist_ms,
          '@matched_entity_count' => $matched_entity_count,
          '@created_entity_count' => $created_entity_count,
          '@room_ref_count' => count($room_refs),
          '@active_room_id' => $active_room_id,
        ]
      );
    }

    return $dungeon_payload;
  }

  /**
   * Determine whether read-lane sync should bypass membership reconciliation.
   */
  protected function shouldBypassMembershipSyncForReadLane(array $diagnostic_context = []): bool {
    if (!empty($diagnostic_context['membership_projection_mode'])) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Resolve canonical undead-crypt starter anchors for required skeleton NPCs.
   *
   * @return array{q:int,r:int}|null
   *   Canonical anchor coordinate when the NPC belongs to undead-crypt starter.
   */
  protected function resolveCanonicalUndeadCryptNpcAnchor(string $content_id, string $room_id, array $dungeon_payload): ?array {
    $normalized_content_id = strtolower(trim($content_id));
    if ($normalized_content_id === '') {
      return NULL;
    }

    $anchors = [
      'skeleton_guard_alpha' => ['q' => 3, 'r' => 2],
      'skeleton_guard_beta' => ['q' => 2, 'r' => 3],
    ];
    if (!isset($anchors[$normalized_content_id])) {
      return NULL;
    }

    if (!$this->isUndeadCryptStarterRoom($room_id, $dungeon_payload)) {
      return NULL;
    }

    return $anchors[$normalized_content_id];
  }

  /**
   * Determine whether a runtime room is the undead-crypt starter room.
   */
  protected function isUndeadCryptStarterRoom(string $room_id, array $dungeon_payload): bool {
    $room_id = strtolower(trim($room_id));
    if ($room_id === '') {
      return FALSE;
    }

    if (in_array($room_id, ['undead_crypt_entry_hall', 'tpl_room_crypt_anteroom'], TRUE)) {
      return TRUE;
    }

    $room_payload = $this->resolveRoomPayloadById($dungeon_payload, $room_id);
    if (!is_array($room_payload)) {
      return FALSE;
    }

    $source_room_id = strtolower(trim((string) ($room_payload['source_room_id'] ?? '')));
    if ($source_room_id === 'tpl_room_crypt_anteroom') {
      return TRUE;
    }

    $room_type = strtolower(trim((string) ($room_payload['room_type'] ?? '')));
    return $room_type === 'starter_undead_crypt';
  }

  /**
   * Resolve room payload by ID from keyed or list-shaped rooms payload.
   */
  protected function resolveRoomPayloadById(array $dungeon_payload, string $room_id): ?array {
    $room_id = trim($room_id);
    if ($room_id === '') {
      return NULL;
    }

    if (isset($dungeon_payload['rooms'][$room_id]) && is_array($dungeon_payload['rooms'][$room_id])) {
      return $dungeon_payload['rooms'][$room_id];
    }

    foreach (($dungeon_payload['rooms'] ?? []) as $candidate) {
      if (!is_array($candidate)) {
        continue;
      }
      if (trim((string) ($candidate['room_id'] ?? '')) === $room_id) {
        return $candidate;
      }
    }

    return NULL;
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

    if (
      $this->characterPortraitGenerator
      && (int) ($record['id'] ?? 0) > 0
      && $this->shouldGenerateRuntimeNpcPortrait($campaign_id, $record, $content_id, $state)
    ) {
      $portrait_payload = array_replace($seed_data, $character_data);
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
   * Determine whether runtime NPC portrait generation is still necessary.
   */
  protected function shouldGenerateRuntimeNpcPortrait(int $campaign_id, array $record, string $content_id, array $state = []): bool {
    $record_id = (int) ($record['id'] ?? 0);
    if ($campaign_id <= 0 || $record_id <= 0) {
      return FALSE;
    }

    $canonical_follower_portrait = $this->resolveCanonicalFollowerOwnerPortrait($campaign_id, $record, $state);
    if ($canonical_follower_portrait !== '') {
      $this->synchronizeFollowerRuntimePortrait($campaign_id, $record_id, $canonical_follower_portrait);
      return FALSE;
    }

    $canonical_library_portrait = $this->resolveCanonicalLibraryNpcPortrait($record, $content_id, $state);
    if (is_array($canonical_library_portrait)) {
      $this->synchronizeRuntimeNpcPortraitFromCanonicalLibrary($campaign_id, $record_id, $canonical_library_portrait);
      return FALSE;
    }

    if (trim((string) ($record['portrait'] ?? '')) !== '') {
      return FALSE;
    }

    if ($this->hasPortraitLinkForCampaignCharacter($campaign_id, $record_id)) {
      return FALSE;
    }

    $instance_id = trim((string) ($record['instance_id'] ?? ''));
    if (
      $instance_id !== ''
      && $this->hasPortraitCarrierRuntimeNpc($campaign_id, 'instance_id', $instance_id, $record_id)
    ) {
      return FALSE;
    }

    $name = trim((string) ($record['name'] ?? ''));
    if ($name !== '' && $this->hasPortraitCarrierRuntimeNpc($campaign_id, 'name', $name, $record_id)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Resolve canonical follower portrait from owner actor record when available.
   */
  protected function resolveCanonicalFollowerOwnerPortrait(int $campaign_id, array $record, array $state = []): string {
    $instance_id = strtolower(trim((string) ($record['instance_id'] ?? '')));
    $metadata = is_array($state['metadata'] ?? NULL) ? $state['metadata'] : [];
    $role = strtolower(trim((string) ($state['role'] ?? $metadata['role'] ?? '')));
    $follower_kind = strtolower(trim((string) ($metadata['follower_kind'] ?? '')));
    $is_follower_candidate = $follower_kind !== ''
      || in_array($role, ['familiar', 'animal_companion', 'construct_companion', 'eidolon'], TRUE)
      || preg_match('/^(?:familiar|animal-companion|construct-companion|eidolon)-\d+$/', $instance_id) === 1;
    if (!$is_follower_candidate) {
      return '';
    }

    $owner_source_character_id = (int) ($record['source_character_id'] ?? 0);
    if ($owner_source_character_id <= 0) {
      $owner_source_character_id = (int) ($metadata['owner_character_id'] ?? 0);
    }
    if ($owner_source_character_id <= 0 && preg_match('/^(?:familiar|animal-companion|construct-companion|eidolon)-(\d+)$/', $instance_id, $matches) === 1) {
      $owner_source_character_id = (int) ($matches[1] ?? 0);
    }
    if ($campaign_id <= 0 || $owner_source_character_id <= 0) {
      return '';
    }

    if ($follower_kind === '') {
      if (str_starts_with($instance_id, 'familiar-') || $role === 'familiar') {
        $follower_kind = FollowerSubsystemService::FOLLOWER_KIND_FAMILIAR;
      }
      elseif (str_starts_with($instance_id, 'animal-companion-') || $role === 'animal_companion') {
        $follower_kind = FollowerSubsystemService::FOLLOWER_KIND_ANIMAL_COMPANION;
      }
      elseif (str_starts_with($instance_id, 'construct-companion-') || $role === 'construct_companion') {
        $follower_kind = FollowerSubsystemService::FOLLOWER_KIND_CONSTRUCT_COMPANION;
      }
      elseif (str_starts_with($instance_id, 'eidolon-') || $role === 'eidolon') {
        $follower_kind = FollowerSubsystemService::FOLLOWER_KIND_EIDOLON;
      }
    }

    $owner_query = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['id', 'character_data'])
      ->condition('campaign_id', $campaign_id)
      ->condition('type', 'pc');
    $owner_query->condition(
      $owner_query->orConditionGroup()
        ->condition('id', $owner_source_character_id)
        ->condition('source_character_id', $owner_source_character_id)
    );
    $owner_record = $owner_query
      ->orderBy('updated', 'DESC')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($owner_record)) {
      return '';
    }

    $decoded = $this->decodeCharacterData($owner_record);
    $owner_char_data = isset($decoded['character']) && is_array($decoded['character']) ? $decoded['character'] : $decoded;
    $candidates = [];
    if ($follower_kind !== '' && is_array($owner_char_data['follower_actor_records'][$follower_kind] ?? NULL)) {
      $candidates[] = $owner_char_data['follower_actor_records'][$follower_kind];
    }
    if ($follower_kind === FollowerSubsystemService::FOLLOWER_KIND_FAMILIAR && is_array($owner_char_data['familiar']['actor_record'] ?? NULL)) {
      $candidates[] = $owner_char_data['familiar']['actor_record'];
    }
    if ($follower_kind === FollowerSubsystemService::FOLLOWER_KIND_ANIMAL_COMPANION && is_array($owner_char_data['animal_companion']['actor_record'] ?? NULL)) {
      $candidates[] = $owner_char_data['animal_companion']['actor_record'];
    }
    if ($follower_kind === FollowerSubsystemService::FOLLOWER_KIND_CONSTRUCT_COMPANION && is_array($owner_char_data['construct_companion']['actor_record'] ?? NULL)) {
      $candidates[] = $owner_char_data['construct_companion']['actor_record'];
    }
    if ($follower_kind === FollowerSubsystemService::FOLLOWER_KIND_EIDOLON && is_array($owner_char_data['som_state']['eidolon']['actor_record'] ?? NULL)) {
      $candidates[] = $owner_char_data['som_state']['eidolon']['actor_record'];
    }

    foreach ($candidates as $actor_record) {
      $candidate_portrait = trim((string) (
        $actor_record['state']['metadata']['portrait_url']
        ?? $actor_record['state']['metadata']['portrait']
        ?? ''
      ));
      if ($candidate_portrait !== '') {
        return $candidate_portrait;
      }
    }

    return '';
  }

  /**
   * Keep follower runtime portrait aligned with canonical owner actor record.
   */
  protected function synchronizeFollowerRuntimePortrait(int $campaign_id, int $record_id, string $portrait_url): void {
    $portrait_url = trim($portrait_url);
    if ($campaign_id <= 0 || $record_id <= 0 || $portrait_url === '') {
      return;
    }

    $existing = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['portrait'])
      ->condition('campaign_id', $campaign_id)
      ->condition('id', $record_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();
    if (trim((string) $existing) === $portrait_url) {
      return;
    }

    $this->database->update('dc_campaign_characters')
      ->fields(['portrait' => $portrait_url])
      ->condition('campaign_id', $campaign_id)
      ->condition('id', $record_id)
      ->execute();
  }

  /**
   * Check whether canonical NPC library already has a portrait for this identity.
   */
  protected function resolveCanonicalLibraryNpcPortrait(array $record, string $content_id, array $state): ?array {
    $instance_candidates = [];
    $content_id = trim($content_id);
    if ($content_id !== '') {
      $instance_candidates[] = $content_id;
      if (!str_starts_with($content_id, 'npc_')) {
        $instance_candidates[] = 'npc_' . $content_id;
      }
    }

    $record_instance = trim((string) ($record['instance_id'] ?? ''));
    if ($record_instance !== '') {
      $instance_candidates[] = $record_instance;
      if (str_starts_with($record_instance, 'npc_')) {
        $instance_candidates[] = substr($record_instance, strlen('npc_'));
      }
    }
    $instance_candidates = array_values(array_unique(array_filter($instance_candidates, static fn(string $candidate): bool => $candidate !== '')));

    $library_row_id = FALSE;
    if ($instance_candidates !== []) {
      $library_row_id = $this->database->select('dungeoncrawler_content_characters', 'c')
        ->fields('c', ['id'])
        ->condition('c.type', 'npc')
        ->condition('c.instance_id', $instance_candidates, 'IN')
        ->orderBy('c.updated', 'DESC')
        ->orderBy('c.id', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchField();
    }

    if ($library_row_id === FALSE) {
      $name = trim((string) (
        $record['name']
        ?? $state['metadata']['display_name']
        ?? $state['metadata']['name']
        ?? ''
      ));
      if ($name !== '') {
        $candidates = $this->database->select('dungeoncrawler_content_characters', 'c')
          ->fields('c', ['id', 'state_data'])
          ->condition('c.type', 'npc')
          ->condition('c.state_data', '%' . $this->database->escapeLike($name) . '%', 'LIKE')
          ->orderBy('c.id', 'DESC')
          ->execute()
          ->fetchAllAssoc('id');
        if (is_array($candidates)) {
          foreach ($candidates as $candidate) {
            $state_data = json_decode((string) ($candidate->state_data ?? '{}'), TRUE);
            if (!is_array($state_data) || trim((string) ($state_data['name'] ?? '')) !== $name) {
              continue;
            }
            $library_row_id = (int) ($candidate->id ?? 0);
            break;
          }
        }
      }
    }

    $library_row_id = (int) $library_row_id;
    if ($library_row_id <= 0) {
      return NULL;
    }

    $link_row = $this->database->select('dc_generated_image_links', 'l')
      ->fields('l', ['image_id'])
      ->condition('l.table_name', 'dungeoncrawler_content_characters')
      ->condition('l.object_id', (string) $library_row_id)
      ->condition('l.slot', 'portrait')
      ->condition('l.variant', 'original')
      ->isNull('l.campaign_id')
      ->orderBy('l.is_primary', 'DESC')
      ->orderBy('l.created', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($link_row)) {
      return NULL;
    }

    $image_id = (int) ($link_row['image_id'] ?? 0);
    if ($image_id <= 0) {
      return NULL;
    }

    $image_row = $this->database->select('dc_generated_images', 'i')
      ->fields('i', ['public_url', 'file_uri', 'status', 'deleted'])
      ->condition('i.id', $image_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($image_row) || (int) ($image_row['deleted'] ?? 1) !== 0 || (string) ($image_row['status'] ?? '') !== 'ready') {
      return NULL;
    }

    $portrait_url = trim((string) ($image_row['public_url'] ?? ''));
    if ($portrait_url === '') {
      $file_uri = trim((string) ($image_row['file_uri'] ?? ''));
      if (!str_starts_with($file_uri, 'public://')) {
        return NULL;
      }
      $portrait_url = '/sites/default/files/' . ltrim(substr($file_uri, strlen('public://')), '/');
    }

    return [
      'image_id' => $image_id,
      'portrait_url' => $portrait_url,
    ];
  }

  /**
   * Persist canonical library portrait onto runtime NPC row and campaign link.
   */
  protected function synchronizeRuntimeNpcPortraitFromCanonicalLibrary(int $campaign_id, int $record_id, array $canonical_portrait): void {
    if ($campaign_id <= 0 || $record_id <= 0) {
      return;
    }

    $image_id = (int) ($canonical_portrait['image_id'] ?? 0);
    $portrait_url = trim((string) ($canonical_portrait['portrait_url'] ?? ''));
    if ($image_id <= 0 || $portrait_url === '') {
      return;
    }

    $existing_portrait = trim((string) ($this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['portrait'])
      ->condition('cc.campaign_id', $campaign_id)
      ->condition('cc.id', $record_id)
      ->range(0, 1)
      ->execute()
      ->fetchField() ?? ''));

    if ($existing_portrait !== $portrait_url) {
      $this->database->update('dc_campaign_characters')
        ->fields([
          'portrait' => $portrait_url,
          'updated' => time(),
        ])
        ->condition('campaign_id', $campaign_id)
        ->condition('id', $record_id)
        ->execute();
    }

    $link_exists = (bool) $this->database->select('dc_generated_image_links', 'l')
      ->fields('l', ['id'])
      ->condition('l.table_name', 'dc_campaign_characters')
      ->condition('l.object_id', (string) $record_id)
      ->condition('l.slot', 'portrait')
      ->condition('l.variant', 'original')
      ->condition('l.campaign_id', $campaign_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();
    if ($link_exists) {
      return;
    }

    $now = time();
    $this->database->insert('dc_generated_image_links')
      ->fields([
        'image_id' => $image_id,
        'scope_type' => 'campaign',
        'campaign_id' => $campaign_id,
        'table_name' => 'dc_campaign_characters',
        'object_id' => (string) $record_id,
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
   * Check whether this campaign character row already has a portrait image link.
   */
  protected function hasPortraitLinkForCampaignCharacter(int $campaign_id, int $record_id): bool {
    if ($campaign_id <= 0 || $record_id <= 0) {
      return FALSE;
    }

    $query = $this->database->select('dc_generated_image_links', 'l')
      ->fields('l', ['id'])
      ->condition('l.table_name', 'dc_campaign_characters')
      ->condition('l.object_id', (string) $record_id)
      ->condition('l.slot', 'portrait');
    $campaign_scope = $query->orConditionGroup()
      ->condition('l.campaign_id', $campaign_id)
      ->isNull('l.campaign_id');
    $query->condition($campaign_scope)
      ->range(0, 1);

    return (bool) $query->execute()->fetchField();
  }

  /**
   * Check whether a sibling runtime NPC already carries portrait data for same identity.
   */
  protected function hasPortraitCarrierRuntimeNpc(
    int $campaign_id,
    string $identity_field,
    string $identity_value,
    int $exclude_record_id
  ): bool {
    if ($campaign_id <= 0 || $identity_value === '') {
      return FALSE;
    }

    if (!in_array($identity_field, ['instance_id', 'name'], TRUE)) {
      return FALSE;
    }

    $rows = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['id', 'portrait'])
      ->condition('cc.campaign_id', $campaign_id)
      ->condition('cc.type', 'npc')
      ->condition('cc.' . $identity_field, $identity_value)
      ->condition('cc.id', $exclude_record_id, '<>')
      ->orderBy('cc.updated', 'DESC')
      ->orderBy('cc.id', 'DESC')
      ->range(0, 10)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as $row) {
      $row_id = (int) ($row['id'] ?? 0);
      if ($row_id <= 0) {
        continue;
      }

      if (trim((string) ($row['portrait'] ?? '')) !== '') {
        return TRUE;
      }

      if ($this->hasPortraitLinkForCampaignCharacter($campaign_id, $row_id)) {
        return TRUE;
      }
    }

    return FALSE;
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
    $role = strtolower(trim((string) ($state['role'] ?? $metadata['role'] ?? 'neutral')));
    $follower_kind = strtolower(trim((string) ($metadata['follower_kind'] ?? '')));
    $familiar_type = strtolower(trim((string) (
      $metadata['familiar_type']
      ?? $character_data['familiar_type']
      ?? ''
    )));
    if ($familiar_type === '' && str_starts_with($content_id, 'familiar_')) {
      $familiar_type = substr($content_id, strlen('familiar_'));
    }
    $is_familiar = $role === 'familiar'
      || $follower_kind === FollowerSubsystemService::FOLLOWER_KIND_FAMILIAR
      || str_starts_with($content_id, 'familiar_');
    if ($is_familiar && $familiar_type === '') {
      $familiar_type = 'standard';
    }
    $familiar_species_name = '';
    if ($familiar_type !== '') {
      if ($familiar_type !== 'standard' && isset(FamiliarService::FAMILIAR_TYPES[$familiar_type]['name'])) {
        $familiar_species_name = (string) FamiliarService::FAMILIAR_TYPES[$familiar_type]['name'];
      }
      else {
        $familiar_species_name = ucwords(str_replace(['_', '-'], ' ', $familiar_type));
      }
    }
    if ($familiar_species_name === '' && $is_familiar) {
      $familiar_species_name = 'Familiar';
    }
    $description = (string) ($state['description'] ?? $metadata['description'] ?? $profile['appearance'] ?? '');
    if ($is_familiar && trim($description) === '') {
      $description = $familiar_species_name !== ''
        ? sprintf('Bound %s familiar ally.', strtolower($familiar_species_name))
        : 'Bound familiar ally.';
    }
    $resolved_ancestry = $is_familiar
      ? ($familiar_species_name !== '' ? $familiar_species_name : 'Familiar')
      : (string) ($character_data['ancestry'] ?? $basic_info['ancestry'] ?? 'Humanoid');
    $resolved_class = $is_familiar
      ? 'familiar'
      : (string) ($character_data['class'] ?? $basic_info['class'] ?? 'Commoner');

    return [
      'campaign_id' => $campaign_id,
      'content_id' => $content_id,
      'entity_ref' => $content_id,
      'name' => (string) ($record['name'] ?? $metadata['display_name'] ?? $metadata['name'] ?? $content_id),
      'role' => $role !== '' ? $role : 'neutral',
      'team' => (string) ($state['team'] ?? $metadata['team'] ?? ''),
      'level' => (int) ($character_data['level'] ?? $basic_info['level'] ?? $stats['level'] ?? 1),
      'ancestry' => $resolved_ancestry,
      'class' => $resolved_class,
      'occupation' => (string) ($metadata['occupation'] ?? $profile['role'] ?? ''),
      'description' => $description,
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
      'follower_kind' => $follower_kind,
      'familiar_type' => $familiar_type,
      'familiar_species_name' => $familiar_species_name,
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
      'follower_kind' => (string) ($seed_data['follower_kind'] ?? ''),
      'familiar_type' => (string) ($seed_data['familiar_type'] ?? ''),
      'familiar_species_name' => (string) ($seed_data['familiar_species_name'] ?? ''),
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
      'follower_kind' => (string) ($seed_data['follower_kind'] ?? ''),
      'familiar_type' => (string) ($seed_data['familiar_type'] ?? ''),
      'familiar_species_name' => (string) ($seed_data['familiar_species_name'] ?? ''),
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
        'position_h3' => '',
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
    $candidates = array_values(array_filter(array_map(
      fn(string $value): string => $this->canonicalizeNpcContentId($value),
      [
        (string) ($state['content_id'] ?? ''),
        (string) ($state['entity_ref']['content_id'] ?? ''),
        $this->deriveNpcContentIdFromInstanceId((string) ($record['instance_id'] ?? '')),
        $this->findNpcContentIdInPayload($record, $dungeon_payload),
      ]
    ), static fn(string $value): bool => $value !== ''));

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
        return $this->canonicalizeNpcContentId((string) ($entity['entity_ref']['content_id'] ?? ''));
      }
    }

    return '';
  }

  /**
   * Canonicalize NPC content IDs to the unprefixed room-template contract.
   */
  protected function canonicalizeNpcContentId(string $content_id): string {
    $normalized = trim($content_id);
    if ($normalized === '') {
      return '';
    }

    if (str_starts_with($normalized, 'npc_') && strlen($normalized) > 4) {
      return substr($normalized, 4);
    }
    if (str_starts_with($normalized, 'npc-') && strlen($normalized) > 4) {
      return substr($normalized, 4);
    }

    return $normalized;
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
   * Load canonical player state for runtime sync.
   */
  protected function loadCanonicalPlayerCharacterState(array $record, int $campaign_id): array {
    $record_id = (int) ($record['id'] ?? 0);
    if ($record_id <= 0 || $campaign_id <= 0) {
      throw new \RuntimeException('Campaign runtime sync contract violation: canonical player state requires positive record id and campaign id.');
    }

    $instance_id = trim((string) ($record['instance_id'] ?? ''));
    $state = $this->getCharacterStateService()->getState((string) $record_id, $campaign_id, $instance_id !== '' ? $instance_id : NULL);
    if (!is_array($state) || $state === []) {
      throw new \RuntimeException(sprintf(
        'Campaign runtime sync contract violation: canonical player state unavailable for campaign %d character %d.',
        $campaign_id,
        $record_id
      ));
    }

    return $state;
  }

  /**
   * Resolve canonical character state service lazily to preserve constructor compatibility.
   */
  protected function getCharacterStateService(): CharacterStateService {
    if (!\Drupal::hasService('dungeoncrawler_content.character_state')) {
      throw new \RuntimeException('Campaign runtime sync contract violation: character state service is unavailable.');
    }
    $service = \Drupal::service('dungeoncrawler_content.character_state');
    if (!$service instanceof CharacterStateService) {
      throw new \RuntimeException('Campaign runtime sync contract violation: character state service has unexpected type.');
    }
    return $service;
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
   * Persist canonical runtime room/hex/h3 placement for one campaign actor row.
   */
  protected function persistRuntimePlacement(int $record_id, int $campaign_id, string $room_id, array $placement): void {
    if ($record_id <= 0 || $campaign_id <= 0) {
      return;
    }
    $room_id = trim($room_id);
    if ($room_id === '') {
      throw new \RuntimeException(sprintf(
        'Cannot persist runtime placement for row %d without room_id.',
        $record_id
      ));
    }
    $position_h3 = strtolower(trim((string) ($placement['h3_index_res14'] ?? '')));
    if ($position_h3 === '') {
      throw new \RuntimeException(sprintf(
        'Cannot persist runtime placement for row %d without canonical h3_index_res14.',
        $record_id
      ));
    }
    $this->database->update('dc_campaign_characters')
      ->fields([
        'position_q' => (int) ($placement['q'] ?? 0),
        'position_r' => (int) ($placement['r'] ?? 0),
        'position_h3' => $position_h3,
        'last_room_id' => $room_id,
        'location_type' => 'room',
        'location_ref' => $room_id,
        'updated' => time(),
      ])
      ->condition('id', $record_id)
      ->condition('campaign_id', $campaign_id)
      ->execute();
  }

  /**
   * Resolve authoritative placement room and canonical H3 index.
   *
   * If preferred room cannot project the placement to H3, a deterministic
   * active-room fallback is attempted.
   *
   * @return array{room_id:string,h3_index_res14:string}
   *   Canonical room + H3 placement.
   */
  protected function resolvePlacementRoomAndH3(array $dungeon_payload, string $preferred_room_id, array $placement, string $context): array {
    $fallback_room_id = $this->resolveRuntimeFallbackRoomId($dungeon_payload, $preferred_room_id);
    $preferred_room_id = trim($preferred_room_id);
    if ($preferred_room_id === '') {
      $preferred_room_id = $fallback_room_id;
    }

    $h3_index_res14 = $this->resolvePlacementH3IndexRes14($dungeon_payload, $preferred_room_id, $placement);
    if ($h3_index_res14 !== '') {
      return [
        'room_id' => $preferred_room_id,
        'h3_index_res14' => $h3_index_res14,
      ];
    }

    if ($fallback_room_id !== '' && $preferred_room_id !== $fallback_room_id) {
      $fallback_h3 = $this->resolvePlacementH3IndexRes14($dungeon_payload, $fallback_room_id, $placement);
      if ($fallback_h3 !== '') {
        return [
          'room_id' => $fallback_room_id,
          'h3_index_res14' => $fallback_h3,
        ];
      }
    }

    throw new \RuntimeException(sprintf(
      'Unable to resolve canonical H3 placement for %s at room %s hex %d:%d.',
      $context,
      $preferred_room_id,
      (int) ($placement['q'] ?? 0),
      (int) ($placement['r'] ?? 0)
    ));
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
      'h3_index_res14' => (string) ($record['position_h3'] ?? ''),
    ];
    $record_room_id = $this->resolveRecordRoomId($record);
    $has_canonical_room_placement = $record_room_id === $room_id
      && trim((string) ($record['position_h3'] ?? '')) !== ''
      && ($room_hexes === [] || $this->roomContainsHex($room_hexes, $preferred['q'], $preferred['r']));
    if (!$has_canonical_room_placement) {
      $entry_hex = $this->resolveRoomEntryHexCoordinate($dungeon_payload, $room_id);
      if (is_array($entry_hex)) {
        $entry_key = ((int) $entry_hex['q']) . ',' . ((int) $entry_hex['r']);
        if (
          !isset($occupied[$entry_key])
          && $this->canResolvePlacementH3FromPayloadOrSparse($dungeon_payload, $room_id, (int) $entry_hex['q'], (int) $entry_hex['r'])
        ) {
          return [
            'q' => (int) $entry_hex['q'],
            'r' => (int) $entry_hex['r'],
          ];
        }
        return $this->findAdjacentCompanionHex(
          $dungeon_payload,
          $room_id,
          (int) $entry_hex['q'],
          (int) $entry_hex['r'],
          $occupied,
          FALSE
        );
      }
    }
    if ($room_hexes === []) {
      $sparse_fallback = $this->resolveRoomHexPlacementFromSparseStorage($dungeon_payload, $room_id, $preferred, $occupied);
      if (is_array($sparse_fallback)) {
        return $sparse_fallback;
      }
    }
    $preferred_key = $preferred['q'] . ',' . $preferred['r'];

    if (($room_hexes === [] || $this->roomContainsHex($room_hexes, $preferred['q'], $preferred['r']))
      && $this->canResolvePlacementH3FromPayloadOrSparse($dungeon_payload, $room_id, $preferred['q'], $preferred['r'])
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
      if (
        !isset($occupied[$candidate_key])
        && $this->canResolvePlacementH3FromPayloadOrSparse($dungeon_payload, $room_id, $candidate['q'], $candidate['r'])
      ) {
        return $candidate;
      }
    }

    foreach ($room_hexes as $hex) {
      if (!isset($hex['q'], $hex['r'])) {
        continue;
      }
      if (!$this->canResolvePlacementH3FromPayloadOrSparse($dungeon_payload, $room_id, (int) $hex['q'], (int) $hex['r'])) {
        continue;
      }
      return [
        'q' => (int) $hex['q'],
        'r' => (int) $hex['r'],
      ];
    }

    $fallback_room_id = $this->resolveRuntimeFallbackRoomId($dungeon_payload);
    if ($fallback_room_id !== '' && $fallback_room_id !== $room_id) {
      $fallback_occupied = $this->resolveOccupiedHexesForRoom($dungeon_payload, $fallback_room_id, $occupied);
      $fallback_placement = $this->resolveRoomHexPlacementFromSparseStorage($dungeon_payload, $fallback_room_id, $preferred, $fallback_occupied);
      if (is_array($fallback_placement)) {
        return $fallback_placement;
      }
    }

    return $preferred;
  }

  /**
   * Resolve the canonical entry hex for a room when available.
   */
  protected function resolveRoomEntryHexCoordinate(array $dungeon_payload, string $room_id): ?array {
    $room_hexes = $this->getRoomHexes($dungeon_payload, $room_id);
    foreach ($room_hexes as $hex) {
      if (!is_array($hex)) {
        continue;
      }
      $is_entry = !empty($hex['is_entry']) || !empty($hex['entry']);
      if (!$is_entry) {
        continue;
      }
      if (!isset($hex['q'], $hex['r'])) {
        continue;
      }
      return [
        'q' => (int) $hex['q'],
        'r' => (int) $hex['r'],
      ];
    }

    $dungeon_id = trim((string) ($dungeon_payload['dungeon_id'] ?? ''));
    if ($dungeon_id === '' || $room_id === '') {
      return NULL;
    }

    $entry_row = $this->database->select('dungeoncrawler_content_h3_room_cells', 'c')
      ->fields('c', ['source_q', 'source_r'])
      ->condition('c.dungeon_id', $dungeon_id)
      ->condition('c.room_id', $room_id)
      ->condition('c.h3_resolution', 14)
      ->condition('c.cell_role', 'entry_gateway')
      ->orderBy('c.id', 'ASC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (is_array($entry_row)) {
      return [
        'q' => (int) ($entry_row['source_q'] ?? 0),
        'r' => (int) ($entry_row['source_r'] ?? 0),
      ];
    }

    return NULL;
  }

  /**
   * Resolve a stable placement for an injected room NPC.
   */
  protected function resolveRoomNpcPlacement(array $dungeon_payload, string $room_id, array $record, array $occupied): array {
    $preferred = [
      'q' => (int) ($record['position_q'] ?? 0),
      'r' => (int) ($record['position_r'] ?? 0),
      'h3_index_res14' => (string) ($record['position_h3'] ?? ''),
    ];
    $room_hexes = $this->getRoomHexes($dungeon_payload, $room_id);
    if ($room_hexes === []) {
      $sparse_fallback = $this->resolveRoomHexPlacementFromSparseStorage($dungeon_payload, $room_id, $preferred, $occupied);
      if (is_array($sparse_fallback)) {
        return $sparse_fallback;
      }
    }
    $preferred_key = $preferred['q'] . ',' . $preferred['r'];
    if (($room_hexes === [] || $this->roomContainsHex($room_hexes, $preferred['q'], $preferred['r']))
      && $this->canResolvePlacementH3FromPayloadOrSparse($dungeon_payload, $room_id, $preferred['q'], $preferred['r'])
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
      if (
        !isset($occupied[$candidate_key])
        && $this->canResolvePlacementH3FromPayloadOrSparse($dungeon_payload, $room_id, $candidate['q'], $candidate['r'])
      ) {
        return $candidate;
      }
    }

    foreach ($room_hexes as $hex) {
      if (!isset($hex['q'], $hex['r'])) {
        continue;
      }
      if (!$this->canResolvePlacementH3FromPayloadOrSparse($dungeon_payload, $room_id, (int) $hex['q'], (int) $hex['r'])) {
        continue;
      }
      return [
        'q' => (int) $hex['q'],
        'r' => (int) $hex['r'],
      ];
    }

    $fallback_room_id = $this->resolveRuntimeFallbackRoomId($dungeon_payload);
    if ($fallback_room_id !== '' && $fallback_room_id !== $room_id) {
      $fallback_occupied = $this->resolveOccupiedHexesForRoom($dungeon_payload, $fallback_room_id, $occupied);
      $fallback_placement = $this->resolveRoomHexPlacementFromSparseStorage($dungeon_payload, $fallback_room_id, $preferred, $fallback_occupied);
      if (is_array($fallback_placement)) {
        return $fallback_placement;
      }
    }

    return $preferred;
  }

  /**
   * Resolve one valid room hex placement from canonical sparse storage.
   */
  protected function resolveRoomHexPlacementFromSparseStorage(
    array $dungeon_payload,
    string $room_id,
    array $preferred,
    array $occupied
  ): ?array {
    $dungeon_id = trim((string) ($dungeon_payload['dungeon_id'] ?? ''));
    if ($dungeon_id === '' || $room_id === '') {
      return NULL;
    }

    $rows = $this->database->select('dungeoncrawler_content_h3_room_cells', 'c')
      ->fields('c', ['source_q', 'source_r', 'h3_index'])
      ->condition('c.dungeon_id', $dungeon_id)
      ->condition('c.room_id', $room_id)
      ->condition('c.h3_resolution', 14)
      ->condition('c.cell_role', 'room_hex')
      ->orderBy('c.id', 'ASC')
      ->range(0, 256)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    if ($rows === []) {
      return NULL;
    }

    $preferred_h3 = strtolower(trim((string) ($preferred['h3_index_res14'] ?? $preferred['h3_index'] ?? '')));
    if ($preferred_h3 !== '') {
      foreach ($rows as $row) {
        if (!is_array($row)) {
          continue;
        }
        $row_h3 = strtolower(trim((string) ($row['h3_index'] ?? '')));
        if ($row_h3 === '' || $row_h3 !== $preferred_h3) {
          continue;
        }
        $q = (int) ($row['source_q'] ?? 0);
        $r = (int) ($row['source_r'] ?? 0);
        if (!isset($occupied[$q . ',' . $r])) {
          return ['q' => $q, 'r' => $r];
        }
      }
    }

    $preferred_q = (int) ($preferred['q'] ?? 0);
    $preferred_r = (int) ($preferred['r'] ?? 0);
    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }
      $q = (int) ($row['source_q'] ?? 0);
      $r = (int) ($row['source_r'] ?? 0);
      if ($q === $preferred_q && $r === $preferred_r && !isset($occupied[$q . ',' . $r])) {
        return ['q' => $q, 'r' => $r];
      }
    }

    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }
      $q = (int) ($row['source_q'] ?? 0);
      $r = (int) ($row['source_r'] ?? 0);
      if (!isset($occupied[$q . ',' . $r])) {
        return ['q' => $q, 'r' => $r];
      }
    }

    $first = reset($rows);
    return is_array($first)
      ? ['q' => (int) ($first['source_q'] ?? 0), 'r' => (int) ($first['source_r'] ?? 0)]
      : NULL;
  }

  /**
   * Determine whether a room placement can resolve a canonical Res14 H3 index.
   */
  protected function canResolvePlacementH3FromPayloadOrSparse(array $dungeon_payload, string $room_id, int $q, int $r): bool {
    $room_hexes = $this->getRoomHexes($dungeon_payload, $room_id);
    foreach ($room_hexes as $hex) {
      if (!is_array($hex)) {
        continue;
      }
      if ((int) ($hex['q'] ?? 0) !== $q || (int) ($hex['r'] ?? 0) !== $r) {
        continue;
      }
      $h3_index = trim((string) ($hex['h3_index_res14'] ?? $hex['h3_index'] ?? ''));
      if ($h3_index !== '') {
        return TRUE;
      }
      break;
    }

    return $this->resolvePlacementH3IndexRes14FromSparseStorage($dungeon_payload, $room_id, $q, $r) !== '';
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
   * Resolve the canonical Res14 H3 index for a placement coordinate.
   */
  protected function resolvePlacementH3IndexRes14(array $dungeon_payload, string $room_id, array $placement): string {
    $room_hexes = $this->getRoomHexes($dungeon_payload, $room_id);
    $q = (int) ($placement['q'] ?? 0);
    $r = (int) ($placement['r'] ?? 0);
    $matched_hex = FALSE;
    foreach ($room_hexes as $hex) {
      if (!is_array($hex)) {
        continue;
      }
      if ((int) ($hex['q'] ?? 0) !== $q || (int) ($hex['r'] ?? 0) !== $r) {
        continue;
      }
      $matched_hex = TRUE;
      $h3_index = trim((string) ($hex['h3_index_res14'] ?? $hex['h3_index'] ?? ''));
      if ($h3_index !== '') {
        return strtolower($h3_index);
      }
    }

    $sparse_h3_index = $this->resolvePlacementH3IndexRes14FromSparseStorage($dungeon_payload, $room_id, $q, $r);
    if ($sparse_h3_index !== '') {
      return $sparse_h3_index;
    }

    if ($matched_hex) {
      throw new \RuntimeException(sprintf(
        'Campaign runtime sync contract violation: room %s hex (%d,%d) missing h3_index_res14 in payload and sparse storage.',
        $room_id,
        $q,
        $r
      ));
    }

    throw new \RuntimeException(sprintf(
      'Campaign runtime sync contract violation: room %s missing placement hex (%d,%d) in payload and sparse storage.',
      $room_id,
      $q,
      $r
    ));
  }

  /**
   * Resolve Res14 H3 index from canonical sparse storage by room/source hex.
   */
  protected function resolvePlacementH3IndexRes14FromSparseStorage(array $dungeon_payload, string $room_id, int $q, int $r): string {
    $dungeon_id = trim((string) ($dungeon_payload['dungeon_id'] ?? ''));
    if ($dungeon_id === '' || $room_id === '') {
      return '';
    }

    $h3_index = $this->database->select('dungeoncrawler_content_h3_room_cells', 'c')
      ->fields('c', ['h3_index'])
      ->condition('c.dungeon_id', $dungeon_id)
      ->condition('c.room_id', $room_id)
      ->condition('c.h3_resolution', 14)
      ->condition('c.source_q', $q)
      ->condition('c.source_r', $r)
      ->condition('c.cell_role', 'room_hex')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    $normalized = strtolower(trim((string) $h3_index));
    return $normalized !== '' ? $normalized : '';
  }

  /**
   * Inject owner-linked runtime followers as ally NPC entities.
   */
  protected function injectOwnedRuntimeFollowerEntities(array &$dungeon_payload, int $campaign_id, array $record, array $char_data, string $room_id, int $owner_q, int $owner_r, array &$occupied): void {
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

      $extra_metadata = is_array($profile['metadata'] ?? NULL) ? $profile['metadata'] : [];
      $owner_runtime_character_id = (int) ($record['id'] ?? 0);
      $owner_source_character_id = (int) ($record['source_character_id'] ?? 0);
      $follower_role = strtolower(trim((string) ($profile['role'] ?? $extra_metadata['role'] ?? $profile['follower_kind'] ?? 'follower')));
      $follower_display_name = trim((string) ($profile['display_name'] ?? $extra_metadata['display_name'] ?? $extra_metadata['name'] ?? ''));
      $follower_runtime_record = $this->resolveFollowerRuntimeRecord(
        $campaign_id,
        $instance_id,
        $follower_role,
        $follower_display_name,
        $owner_runtime_character_id,
        $owner_source_character_id
      );
      $follower_runtime_record = $this->enforceFollowerRuntimeRecordIdentity(
        $campaign_id,
        $follower_runtime_record,
        $instance_id,
        $owner_source_character_id,
        $follower_display_name
      );
      $follower_runtime_character_id = (int) ($follower_runtime_record['id'] ?? 0);
      if ($follower_runtime_character_id === $owner_runtime_character_id) {
        $follower_runtime_character_id = 0;
      }
      $follower_source_character_id = (int) ($follower_runtime_record['source_character_id'] ?? 0);
      if ($follower_source_character_id <= 0) {
        $follower_source_character_id = (int) ($extra_metadata['source_character_id'] ?? 0);
      }
      $follower_portrait = $this->resolveFollowerRuntimePortrait($follower_runtime_record);
      $follower_metadata = array_merge($extra_metadata, [
        'display_name' => (string) ($profile['display_name'] ?? 'Follower'),
        'name' => (string) ($profile['display_name'] ?? 'Follower'),
        'role' => (string) ($profile['role'] ?? 'follower'),
        'description' => (string) ($profile['description'] ?? ''),
        'team' => (string) ($profile['team'] ?? 'ally'),
        'stats' => is_array($profile['stats'] ?? NULL) ? $profile['stats'] : [],
        'movement_speed' => (int) ($profile['movement_speed'] ?? 25),
        'actions_per_turn' => (int) ($profile['actions_per_turn'] ?? 2),
        'initiative_bonus' => (int) ($profile['initiative_bonus'] ?? 0),
        // Runtime entities must bind to campaign-scoped owner identity.
        'owner_character_id' => $owner_runtime_character_id > 0 ? $owner_runtime_character_id : $owner_source_character_id,
        'owner_source_character_id' => $owner_source_character_id,
        'character_id' => $follower_runtime_character_id,
        'campaign_character_id' => $follower_runtime_character_id,
        'follower_character_id' => $follower_runtime_character_id,
        'source_character_id' => $follower_source_character_id,
        'follower_source_character_id' => $follower_source_character_id,
        'runtime_entity_id' => $instance_id,
        'traits' => is_array($profile['traits'] ?? NULL) ? $profile['traits'] : [],
        'attacks' => is_array($profile['attacks'] ?? NULL) ? $profile['attacks'] : [],
        'setting_state' => FALSE,
        'spawn_policy' => (string) ($profile['spawn_policy'] ?? 'owner_follower'),
      ]);
      $this->syncRuntimeActorInstitutionMemberships(
        $campaign_id,
        'npc',
        $instance_id,
        $this->resolveFollowerInstitutionActorData($profile, $follower_metadata, $follower_runtime_record)
      );
      if ($follower_portrait !== '') {
        $follower_metadata['portrait_url'] = $follower_portrait;
        $follower_metadata['portrait'] = $follower_portrait;
      }

      $existing_entity_index = NULL;
      foreach (($dungeon_payload['entities'] ?? []) as $index => &$entity) {
        $existing_instance_id = (string) ($entity['instance_id'] ?? $entity['entity_instance_id'] ?? '');
        if ($existing_instance_id === $instance_id) {
          $existing_entity_index = (int) $index;
          break;
        }
      }
      unset($entity);

      if ($existing_entity_index !== NULL) {
        $existing_entity = is_array($dungeon_payload['entities'][$existing_entity_index] ?? NULL)
          ? $dungeon_payload['entities'][$existing_entity_index]
          : [];
        $room_occupied = $occupied[$room_id] ?? [];
        if (isset($existing_entity['placement']['hex']) && is_array($existing_entity['placement']['hex'])) {
          $existing_q = (int) ($existing_entity['placement']['hex']['q'] ?? 0);
          $existing_r = (int) ($existing_entity['placement']['hex']['r'] ?? 0);
          unset($room_occupied[$existing_q . ',' . $existing_r]);
        }
        $existing_room_id = $room_id;
        $resolved_placement = NULL;
        $runtime_placement = $this->resolveFollowerRuntimePlacement(
          $dungeon_payload,
          $follower_runtime_record,
          $room_id,
          $room_occupied
        );
        if (is_array($runtime_placement)) {
          $existing_room_id = (string) ($runtime_placement['room_id'] ?? $room_id);
          $resolved_placement = is_array($runtime_placement['placement'] ?? NULL)
            ? $runtime_placement['placement']
            : NULL;
        }
        if (!is_array($resolved_placement)) {
          $resolved_placement = $this->findAdjacentCompanionHex(
            $dungeon_payload,
            $room_id,
            $owner_q,
            $owner_r,
            $room_occupied
          );
        }
        $existing_entity['entity_type'] = 'npc';
        $existing_entity['instance_id'] = $instance_id;
        $existing_entity['entity_instance_id'] = $instance_id;
        $existing_entity['entity_ref'] = is_array($existing_entity['entity_ref'] ?? NULL) ? $existing_entity['entity_ref'] : [];
        $existing_entity['entity_ref']['content_type'] = 'npc';
        $existing_entity['entity_ref']['content_id'] = (string) ($profile['content_id'] ?? ($existing_entity['entity_ref']['content_id'] ?? ''));
        $existing_entity['placement'] = is_array($existing_entity['placement'] ?? NULL) ? $existing_entity['placement'] : [];
        $existing_entity['placement']['hex'] = $resolved_placement;
        $existing_entity['placement']['facing'] = isset($existing_entity['placement']['facing'])
          ? ((int) $existing_entity['placement']['facing'] % 6 + 6) % 6
          : 0;
        $placement_contract = $this->resolvePlacementRoomAndH3(
          $dungeon_payload,
          $existing_room_id,
          $existing_entity['placement']['hex'],
          sprintf('follower-runtime-row-%d', $follower_runtime_character_id)
        );
        $existing_room_id = $placement_contract['room_id'];
        $existing_entity['placement']['room_id'] = $existing_room_id;
        $existing_entity['placement']['h3_index_res14'] = $placement_contract['h3_index_res14'];
        $occupied[$existing_room_id][$resolved_placement['q'] . ',' . $resolved_placement['r']] = TRUE;
        $this->persistRuntimePlacement($follower_runtime_character_id, $campaign_id, $existing_room_id, [
          'q' => (int) ($existing_entity['placement']['hex']['q'] ?? 0),
          'r' => (int) ($existing_entity['placement']['hex']['r'] ?? 0),
          'h3_index_res14' => $placement_contract['h3_index_res14'],
        ]);
        $existing_entity['state'] = is_array($existing_entity['state'] ?? NULL) ? $existing_entity['state'] : [];
        $existing_entity['state']['active'] = TRUE;
        $existing_entity['state']['metadata'] = $follower_metadata;
        $dungeon_payload['entities'][$existing_entity_index] = $existing_entity;
        continue;
      }

      $runtime_placement = $this->resolveFollowerRuntimePlacement(
        $dungeon_payload,
        $follower_runtime_record,
        $room_id,
        $occupied
      );
      $placement_room_id = $room_id;
      $placement = NULL;
      if (is_array($runtime_placement)) {
        $placement_room_id = (string) ($runtime_placement['room_id'] ?? $room_id);
        $placement = is_array($runtime_placement['placement'] ?? NULL)
          ? $runtime_placement['placement']
          : NULL;
      }
      if (!is_array($placement)) {
        $placement = $this->findAdjacentCompanionHex($dungeon_payload, $room_id, $owner_q, $owner_r, $occupied);
      }
      $placement_contract = $this->resolvePlacementRoomAndH3(
        $dungeon_payload,
        $placement_room_id,
        $placement,
        sprintf('follower-runtime-row-%d', $follower_runtime_character_id)
      );
      $placement_room_id = (string) ($placement_contract['room_id'] ?? $placement_room_id);
      $placement['h3_index_res14'] = $placement_contract['h3_index_res14'];
      $occupied[$placement['q'] . ',' . $placement['r']] = TRUE;
      $this->persistRuntimePlacement($follower_runtime_character_id, $campaign_id, $placement_room_id, $placement);
      $dungeon_payload['entities'][] = [
        'entity_type' => 'npc',
        'instance_id' => $instance_id,
        'entity_instance_id' => $instance_id,
        'entity_ref' => [
          'content_type' => 'npc',
          'content_id' => (string) ($profile['content_id'] ?? ''),
        ],
        'placement' => [
          'room_id' => $placement_room_id,
          'hex' => $placement,
          'facing' => 0,
          'h3_index_res14' => $placement['h3_index_res14'],
          'spawn_type' => 'npc',
        ],
        'state' => [
          'active' => TRUE,
          'metadata' => $follower_metadata,
        ],
      ];
    }
  }

  /**
   * Resolve campaign runtime follower row for entity metadata identity/portrait.
   *
   * Follower runtime rows do not always carry a stable instance_id, so this uses
   * strict criteria first and deterministic fallbacks second.
   */
  protected function resolveFollowerRuntimeRecord(
    int $campaign_id,
    string $instance_id,
    string $role,
    string $display_name,
    int $owner_runtime_character_id,
    int $owner_source_character_id
  ): array {
    if ($campaign_id <= 0) {
      return [];
    }

    $role = strtolower(trim($role));
    $role_candidates = $this->buildFollowerRoleCandidates($role);
    if ($role_candidates === []) {
      $role_candidates = ['follower'];
    }

    if ($instance_id !== '') {
      $record = $this->queryFollowerRuntimeRecord($campaign_id, $role_candidates, ['instance_id' => $instance_id]);
      if ($record !== []) {
        return $record;
      }
    }

    if ($display_name !== '') {
      $record = $this->queryFollowerRuntimeRecord($campaign_id, $role_candidates, ['name' => $display_name]);
      if ($record !== []) {
        return $record;
      }
    }

    if ($owner_source_character_id > 0) {
      $record = $this->queryFollowerRuntimeRecord($campaign_id, $role_candidates, ['source_character_id' => $owner_source_character_id]);
      if ($record !== []) {
        return $record;
      }
    }

    return $this->queryFollowerRuntimeRecord($campaign_id, $role_candidates, ['exclude_id' => $owner_runtime_character_id]);
  }

  /**
   * Build role aliases for follower-runtime row lookup.
   *
   * @return array<int,string>
   *   Lowercase role candidates.
   */
  protected function buildFollowerRoleCandidates(string $role): array {
    $role = strtolower(trim($role));
    $candidates = [];
    if ($role !== '') {
      $candidates[] = $role;
      $candidates[] = str_replace('-', '_', $role);
      $candidates[] = str_replace('_', '-', $role);
    }
    if ($role === 'animal_companion') {
      $candidates[] = 'companion';
      $candidates[] = 'animal-companion';
    }
    if ($role === 'construct_companion') {
      $candidates[] = 'construct';
      $candidates[] = 'construct-companion';
      $candidates[] = 'companion';
    }
    if ($role === 'eidolon') {
      $candidates[] = 'summoner_eidolon';
    }

    return array_values(array_unique(array_filter(array_map(
      static fn(string $candidate): string => strtolower(trim($candidate)),
      $candidates
    ), static fn(string $candidate): bool => $candidate !== '')));
  }

  /**
   * Query a candidate follower runtime row by deterministic criteria.
   *
   * @param array<string,mixed> $criteria
   *   Supported keys: instance_id, name, source_character_id, exclude_id.
   */
  protected function queryFollowerRuntimeRecord(int $campaign_id, array $role_candidates, array $criteria = []): array {
    $query = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['id', 'source_character_id', 'instance_id', 'name', 'role', 'portrait', 'character_data', 'position_q', 'position_r', 'last_room_id', 'state_data'])
      ->condition('campaign_id', $campaign_id)
      ->condition('type', 'npc')
      ->condition('role', $role_candidates, 'IN');

    if (!empty($criteria['instance_id'])) {
      $query->condition('instance_id', (string) $criteria['instance_id']);
    }
    if (!empty($criteria['name'])) {
      $query->condition('name', (string) $criteria['name']);
    }
    if (!empty($criteria['source_character_id'])) {
      $query->condition('source_character_id', (int) $criteria['source_character_id']);
    }
    if (!empty($criteria['exclude_id'])) {
      $query->condition('id', (int) $criteria['exclude_id'], '<>');
    }

    $query->orderBy('id', 'DESC')->range(0, 1);
    $record = $query->execute()->fetchAssoc();
    return is_array($record) ? $record : [];
  }

  /**
   * Resolve follower placement from canonical runtime row when available.
   *
   * @return array<string,mixed>|null
   *   ['room_id' => string, 'placement' => ['q' => int, 'r' => int]] or NULL.
   */
  protected function resolveFollowerRuntimePlacement(
    array $dungeon_payload,
    array $follower_runtime_record,
    string $default_room_id,
    array $occupied = []
  ): ?array {
    $room_id = trim((string) ($follower_runtime_record['last_room_id'] ?? ''));
    if ($room_id === '') {
      $room_id = $default_room_id;
    }
    if ($room_id === '') {
      return NULL;
    }

    $q = isset($follower_runtime_record['position_q']) ? (int) $follower_runtime_record['position_q'] : 0;
    $r = isset($follower_runtime_record['position_r']) ? (int) $follower_runtime_record['position_r'] : 0;
    $position_h3 = strtolower(trim((string) ($follower_runtime_record['position_h3'] ?? '')));
    $has_runtime_room = trim((string) ($follower_runtime_record['last_room_id'] ?? '')) !== '';
    $has_runtime_h3 = $position_h3 !== '';
    $has_runtime_hex = !($q === 0 && $r === 0);
    $has_canonical_runtime_placement = $has_runtime_room && ($has_runtime_h3 || $has_runtime_hex);
    $state_placement_present = FALSE;
    if (($q === 0 && $r === 0) && isset($follower_runtime_record['state_data'])) {
      $state_data = json_decode((string) $follower_runtime_record['state_data'], TRUE);
      if (is_array($state_data)) {
        $placement = is_array($state_data['placement'] ?? NULL) ? $state_data['placement'] : [];
        $hex = is_array($placement['hex'] ?? NULL) ? $placement['hex'] : [];
        if (array_key_exists('q', $hex) || array_key_exists('r', $hex)) {
          $q = (int) ($hex['q'] ?? 0);
          $r = (int) ($hex['r'] ?? 0);
          $state_placement_present = TRUE;
        }
        $state_room_id = trim((string) ($placement['room_id'] ?? ''));
        if ($state_room_id !== '') {
          $room_id = $state_room_id;
          $state_placement_present = TRUE;
        }
      }
    }

    if (!$has_canonical_runtime_placement && !$state_placement_present) {
      return NULL;
    }

    $room_occupied = $this->resolveOccupiedHexesForRoom($dungeon_payload, $room_id, $occupied);
    unset($room_occupied[$q . ',' . $r]);
    if (!$this->canResolvePlacementH3FromPayloadOrSparse($dungeon_payload, $room_id, $q, $r)) {
      return NULL;
    }
    if (isset($room_occupied[$q . ',' . $r])) {
      return NULL;
    }

    return [
      'room_id' => $room_id,
      'placement' => ['q' => $q, 'r' => $r],
    ];
  }

  /**
   * Materialize a missing follower runtime row when follower contracts exist.
   *
   * This enforces one standard runtime contract: configured followers must have
   * a campaign-scoped dc_campaign_characters row with stable identity fields.
   */
  protected function materializeMissingFollowerRuntimeRecord(
    int $campaign_id,
    string $instance_id,
    array $role_candidates,
    string $display_name,
    int $owner_source_character_id
  ): array {
    if ($campaign_id <= 0 || $instance_id === '' || $display_name === '' || $owner_source_character_id <= 0) {
      return [];
    }

    $role = strtolower(trim((string) ($role_candidates[0] ?? 'follower')));
    if ($role === '') {
      $role = 'follower';
    }
    $now = time();
    $minimal_payload = [
      'name' => $display_name,
      'role' => $role,
      'display_name' => $display_name,
      'team' => 'ally',
      'runtime_entity_id' => $instance_id,
      'source_character_id' => $owner_source_character_id,
      'stats' => [
        'maxHp' => 1,
        'currentHp' => 1,
        'ac' => 10,
      ],
    ];

    $insert_id = (int) $this->database->insert('dc_campaign_characters')
      ->fields([
        'campaign_id' => $campaign_id,
        'character_id' => 0,
        'uid' => 0,
        'role' => $role,
        'is_active' => 1,
        'joined' => $now,
        'instance_id' => $instance_id,
        'type' => 'npc',
        'state_data' => json_encode($minimal_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'location_type' => 'global',
        'location_ref' => '',
        'updated' => $now,
        'name' => $display_name,
        'level' => 1,
        'ancestry' => '',
        'class' => $role,
        'character_data' => json_encode($minimal_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'status' => 1,
        'created' => $now,
        'changed' => $now,
        'hp_current' => 1,
        'hp_max' => 1,
        'armor_class' => 10,
        'experience_points' => 0,
        'position_q' => 0,
        'position_r' => 0,
        'position_h3' => '',
        'last_room_id' => '',
        'version' => 0,
        'source_character_id' => $owner_source_character_id,
        'lifecycle_state' => 'campaign_npc',
      ])
      ->execute();

    if ($insert_id <= 0) {
      return [];
    }

    $row = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['id', 'source_character_id', 'instance_id', 'name', 'role', 'portrait', 'character_data'])
      ->condition('campaign_id', $campaign_id)
      ->condition('id', $insert_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    return is_array($row) ? $row : [];
  }

  /**
   * Resolve follower portrait URL from runtime row fields.
   */
  protected function resolveFollowerRuntimePortrait(array $follower_record): string {
    $portrait = trim((string) ($follower_record['portrait'] ?? ''));
    if ($portrait !== '') {
      return $portrait;
    }

    $decoded = json_decode((string) ($follower_record['character_data'] ?? '{}'), TRUE);
    if (!is_array($decoded)) {
      return '';
    }
    $payload = isset($decoded['character']) && is_array($decoded['character']) ? $decoded['character'] : $decoded;
    return trim((string) ($payload['portrait_url'] ?? $payload['portrait'] ?? ''));
  }

  /**
   * Ensure runtime actor institution memberships are seeded from class/ancestry.
   */
  protected function syncRuntimeActorInstitutionMemberships(int $campaign_id, string $actor_type, string $instance_id, array $actor_data): void {
    if ($campaign_id <= 0 || $this->institutionMembershipService === NULL) {
      return;
    }
    $instance_id = trim($instance_id);
    if ($instance_id === '') {
      return;
    }

    $canonical_actor_data = isset($actor_data['character']) && is_array($actor_data['character'])
      ? $actor_data['character']
      : $actor_data;
    if (!is_array($canonical_actor_data)) {
      $canonical_actor_data = [];
    }
    if ($canonical_actor_data === []) {
      return;
    }

    if ($actor_type === 'pc') {
      $this->institutionMembershipService->syncCampaignCharacterMemberships($campaign_id, $instance_id, $canonical_actor_data);
    }
    else {
      $this->institutionMembershipService->syncCampaignNpcMemberships($campaign_id, $instance_id, $canonical_actor_data);
    }

    $this->seedInstitutionMatrixDefaultsForCampaign($campaign_id);
  }

  /**
   * Seed neutral matrix defaults after institutional memberships are available.
   */
  protected function seedInstitutionMatrixDefaultsForCampaign(int $campaign_id): void {
    if ($campaign_id <= 0 || $this->institutionDispositionMatrixService === NULL) {
      return;
    }
    if (isset($this->institutionMatrixSeededCampaigns[$campaign_id])) {
      return;
    }

    $this->institutionDispositionMatrixService->seedNeutralDefaultsForCampaign($campaign_id);
    $this->institutionMatrixSeededCampaigns[$campaign_id] = TRUE;
  }

  /**
   * Build follower actor-data payload for institution membership seeding.
   *
   * @return array<string, mixed>
   */
  protected function resolveFollowerInstitutionActorData(array $profile, array $metadata, array $runtime_record): array {
    $actor_data = [];
    $runtime_character_data = $this->decodeCharacterData($runtime_record);
    $runtime_character = isset($runtime_character_data['character']) && is_array($runtime_character_data['character'])
      ? $runtime_character_data['character']
      : $runtime_character_data;

    $class_value = trim((string) (
      $runtime_character['class']['name']
      ?? $runtime_character['class']
      ?? $profile['class']
      ?? $metadata['class']
      ?? ''
    ));
    if ($class_value !== '') {
      $actor_data['class'] = $class_value;
    }

    $ancestry_value = trim((string) (
      $runtime_character['ancestry']['name']
      ?? $runtime_character['ancestry']
      ?? $profile['ancestry']
      ?? $profile['species']
      ?? $metadata['ancestry']
      ?? $metadata['species']
      ?? ''
    ));
    if ($ancestry_value !== '') {
      $actor_data['ancestry'] = $ancestry_value;
    }

    return $actor_data;
  }

  /**
   * Enforce runtime follower row identity contract for lookup stability.
   *
   * - instance_id must match the follower actor contract instance_id
   * - source_character_id must be the owner source character id
   */
  protected function enforceFollowerRuntimeRecordIdentity(
    int $campaign_id,
    array $follower_record,
    string $expected_instance_id,
    int $owner_source_character_id,
    string $expected_display_name = ''
  ): array {
    $record_id = (int) ($follower_record['id'] ?? 0);
    if ($campaign_id <= 0 || $record_id <= 0) {
      return $follower_record;
    }

    $fields = [];
    $current_instance_id = trim((string) ($follower_record['instance_id'] ?? ''));
    if ($expected_instance_id !== '' && $current_instance_id !== $expected_instance_id) {
      $fields['instance_id'] = $expected_instance_id;
    }

    $current_source_character_id = (int) ($follower_record['source_character_id'] ?? 0);
    if ($owner_source_character_id > 0 && $current_source_character_id !== $owner_source_character_id) {
      $fields['source_character_id'] = $owner_source_character_id;
    }

    $current_name = trim((string) ($follower_record['name'] ?? ''));
    if ($current_name === '' && $expected_display_name !== '') {
      $fields['name'] = $expected_display_name;
    }

    if ($fields === []) {
      return $follower_record;
    }

    $this->database->update('dc_campaign_characters')
      ->fields($fields)
      ->condition('campaign_id', $campaign_id)
      ->condition('id', $record_id)
      ->execute();

    foreach ($fields as $key => $value) {
      $follower_record[$key] = $value;
    }
    return $follower_record;
  }

  /**
   * Find a free adjacent hex for the companion.
   */
  protected function findAdjacentCompanionHex(array $dungeon_payload, string $room_id, int $owner_q, int $owner_r, array $occupied, bool $allow_owner_fallback = TRUE): array {
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
      if (!$this->canResolvePlacementH3FromPayloadOrSparse($dungeon_payload, $room_id, $candidate['q'], $candidate['r'])) {
        continue;
      }
      return $candidate;
    }

    if ($allow_owner_fallback && $this->canResolvePlacementH3FromPayloadOrSparse($dungeon_payload, $room_id, $owner_q, $owner_r)) {
      if ($allow_owner_fallback) {
        return ['q' => $owner_q, 'r' => $owner_r];
      }

      $room_hexes = $this->getRoomHexes($dungeon_payload, $room_id);
      foreach ($room_hexes as $hex) {
        if (!isset($hex['q'], $hex['r'])) {
          continue;
        }
        $q = (int) $hex['q'];
        $r = (int) $hex['r'];
        if (isset($occupied[$q . ',' . $r])) {
          continue;
        }
        if ($this->canResolvePlacementH3FromPayloadOrSparse($dungeon_payload, $room_id, $q, $r)) {
          return ['q' => $q, 'r' => $r];
        }
      }

      return ['q' => $owner_q, 'r' => $owner_r];
    }

    $fallback = $this->resolveRoomHexPlacementFromSparseStorage(
      $dungeon_payload,
      $room_id,
      ['q' => $owner_q, 'r' => $owner_r],
      $occupied
    );
    if (is_array($fallback)) {
      return $fallback;
    }

    $fallback_room_id = $this->resolveRuntimeFallbackRoomId($dungeon_payload);
    if ($fallback_room_id !== '' && $fallback_room_id !== $room_id) {
      $fallback_occupied = $this->resolveOccupiedHexesForRoom($dungeon_payload, $fallback_room_id, $occupied);
      $fallback_placement = $this->resolveRoomHexPlacementFromSparseStorage(
        $dungeon_payload,
        $fallback_room_id,
        ['q' => $owner_q, 'r' => $owner_r],
        $fallback_occupied
      );
      if (is_array($fallback_placement)) {
        return $fallback_placement;
      }
    }

    return ['q' => $owner_q, 'r' => $owner_r];
  }

  /**
   * Resolve a campaign-scoped fallback room id from runtime payload.
   */
  protected function resolveRuntimeFallbackRoomId(array $dungeon_payload, string $preferred_room_id = ''): string {
    $preferred_room_id = trim($preferred_room_id);
    if ($preferred_room_id !== '') {
      return $preferred_room_id;
    }

    $active_room_id = trim((string) ($dungeon_payload['active_room_id'] ?? ''));
    if ($active_room_id !== '') {
      return $active_room_id;
    }

    $game_state_room_id = trim((string) ($dungeon_payload['game_state']['active_room_id'] ?? ''));
    if ($game_state_room_id !== '') {
      return $game_state_room_id;
    }

    $rooms = is_array($dungeon_payload['rooms'] ?? NULL) ? $dungeon_payload['rooms'] : [];
    foreach ($rooms as $room_id => $room_payload) {
      if (is_string($room_id) && trim($room_id) !== '') {
        return trim($room_id);
      }
      if (is_array($room_payload)) {
        $candidate = trim((string) ($room_payload['room_id'] ?? ''));
        if ($candidate !== '') {
          return $candidate;
        }
      }
    }

    return '';
  }

  /**
   * Resolve occupied hexes for one room by merging payload entities + caller cache.
   *
   * @return array<string, bool>
   *   Room occupancy keyed as "q,r".
   */
  protected function resolveOccupiedHexesForRoom(array $dungeon_payload, string $room_id, array $occupied = []): array {
    $lookup = $occupied;
    if ($room_id === '') {
      return $lookup;
    }

    foreach (($dungeon_payload['entities'] ?? []) as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      $entity_room_id = trim((string) ($entity['placement']['room_id'] ?? ''));
      if ($entity_room_id !== $room_id) {
        continue;
      }
      $hex = is_array($entity['placement']['hex'] ?? NULL) ? $entity['placement']['hex'] : [];
      if ($hex === []) {
        continue;
      }
      $lookup[(int) ($hex['q'] ?? 0) . ',' . (int) ($hex['r'] ?? 0)] = TRUE;
    }

    return $lookup;
  }

}
