<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Re-syncs active campaign characters into the canonical dungeon payload.
 */
class CampaignCharacterRuntimeSyncService {

  protected Connection $database;

  protected AnimalCompanionService $animalCompanionService;

  public function __construct(Connection $database, AnimalCompanionService $animal_companion_service) {
    $this->database = $database;
    $this->animalCompanionService = $animal_companion_service;
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
      $room_id = $this->resolveRecordRoomId($record) ?: $active_room_id;
      $char_data = $this->decodeCharacterData($record);
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
      $this->injectOwnedAnimalCompanionEntity($dungeon_payload, $record, $char_data, $room_id, $placement['q'], $placement['r'], $room_occupied);
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
   * Resolve the persisted room ID for a campaign-character runtime row.
   */
  protected function resolveRecordRoomId(array $record): string {
    return trim((string) ($record['location_ref'] ?? $record['last_room_id'] ?? ''));
  }

  /**
   * Decode runtime character_data safely.
   */
  protected function decodeCharacterData(array $record): array {
    $decoded = json_decode((string) ($record['character_data'] ?? '{}'), TRUE);
    return is_array($decoded) ? $decoded : [];
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
   * Inject the owner's active animal companion as an ally NPC entity.
   */
  protected function injectOwnedAnimalCompanionEntity(array &$dungeon_payload, array $record, array $char_data, string $room_id, int $owner_q, int $owner_r, array &$occupied): void {
    $character_id = (string) ($record['id'] ?? '');
    if ($character_id === '') {
      return;
    }

    $companion = $this->animalCompanionService->resolveCompanionFromCharacterData($char_data, $character_id);
    if ($companion === NULL) {
      return;
    }

    $instance_id = 'animal-companion-' . $character_id;
    foreach (($dungeon_payload['entities'] ?? []) as $entity) {
      $existing_instance_id = (string) ($entity['instance_id'] ?? $entity['entity_instance_id'] ?? '');
      if ($existing_instance_id === $instance_id) {
        return;
      }
    }

    $placement = $this->findAdjacentCompanionHex($dungeon_payload, $room_id, $owner_q, $owner_r, $occupied);
    $occupied[$placement['q'] . ',' . $placement['r']] = TRUE;

    $dungeon_payload['entities'][] = [
      'entity_type' => 'npc',
      'instance_id' => $instance_id,
      'entity_instance_id' => $instance_id,
      'entity_ref' => [
        'content_type' => 'npc',
        'content_id' => 'animal_companion_' . ($companion['species_id'] ?? 'unknown'),
      ],
      'placement' => [
        'room_id' => $room_id,
        'hex' => $placement,
        'spawn_type' => 'npc',
      ],
      'state' => [
        'active' => TRUE,
        'metadata' => [
          'display_name' => (string) ($companion['name'] ?? 'Animal Companion'),
          'name' => (string) ($companion['name'] ?? 'Animal Companion'),
          'role' => 'animal_companion',
          'description' => (string) ($companion['support_benefit'] ?? ''),
          'team' => 'ally',
          'owner_character_id' => (int) $character_id,
          'companion_species_id' => (string) ($companion['species_id'] ?? ''),
          'companion_stage' => (string) ($companion['stage'] ?? 'young'),
          'companion_specialization' => $companion['specialization'] ?? NULL,
          'stats' => is_array($companion['stats'] ?? NULL) ? $companion['stats'] : [],
          'movement_speed' => (int) ($companion['movement_speed'] ?? ($companion['stats']['speed'] ?? 25)),
          'actions_per_turn' => (int) ($companion['actions_per_turn'] ?? 2),
          'initiative_bonus' => (int) ($companion['stats']['initiative_bonus'] ?? $companion['stats']['perception'] ?? 0),
          'traits' => is_array($companion['traits'] ?? NULL) ? $companion['traits'] : [],
          'attacks' => is_array($companion['attacks'] ?? NULL) ? $companion['attacks'] : [],
          'setting_state' => FALSE,
          'spawn_policy' => 'owner_companion',
        ],
      ],
    ];
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
