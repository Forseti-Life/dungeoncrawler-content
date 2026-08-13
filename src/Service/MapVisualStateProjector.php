<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Projects the legacy hexmap payload into the canonical visual-only map state.
 *
 * Authority boundary:
 * - input graph data is already normalized by upstream runtime graph assembly
 * - this projector is presentation-only and must not infer missing graph truth
 * - authored exit metadata may be preserved here, but traversal authority does
 *   not originate in this projector
 */
class MapVisualStateProjector {

  protected const SCHEMA_VERSION = '1.0.0';

  protected const LAYER_ORDER = [
    'background',
    'terrain',
    'props',
    'occupants',
    'overlays',
    'hud',
  ];

  protected ?NavigationService $navigationService;

  public function __construct(?NavigationService $navigation_service = NULL) {
    $this->navigationService = $navigation_service;
  }

  /**
   * Build canonical visual map state from the current hexmap payload.
   */
  public function project(array $dungeon_payload, array $launch_context = [], array $launch_character = []): array {
    $active_room_id = trim((string) ($dungeon_payload['active_room_id'] ?? $launch_context['room_id'] ?? ''));
    $rooms = is_array($dungeon_payload['rooms'] ?? NULL) ? $dungeon_payload['rooms'] : [];
    if ($active_room_id === '' && $rooms !== []) {
      $active_room_id = (string) array_key_first($rooms);
    }

    $object_definitions = $this->normalizeObjectDefinitions($dungeon_payload);
    $entity_hex_objects = $this->buildEntityHexObjectsIndex($dungeon_payload, $object_definitions);

    $topology_rooms = [];
    $visibility_room_states = [];
    $discovered_room_ids = [];
    $visible_hex_ids_by_room = [];

    foreach ($rooms as $room_key => $room) {
      if (!is_array($room)) {
        continue;
      }

      $room_id = trim((string) ($room['room_id'] ?? $room_key));
      if ($room_id === '') {
        continue;
      }

      $room_state = $this->normalizeRoomState($room, $room_id, $active_room_id);
      $visible_hex_ids = $room_state['visible_hex_ids'];
      $hex_index = [];
      $min_q = NULL;
      $max_q = NULL;
      $min_r = NULL;
      $max_r = NULL;

      $room_hexes = (is_array($room['hexes'] ?? NULL) ? $room['hexes'] : []);

      // Entry hex rules:
      // 1) If the room authors an entry hex (is_entry/entry), use ONLY that.
      // 2) Else default to (0,0) if it's inside the derived room bounds.
      // 3) Else default to the lexicographically-first authored hex coordinate.
      $authored_entry_q = NULL;
      $authored_entry_r = NULL;
      $min_coord_q = NULL;
      $min_coord_r = NULL;

      foreach ($room_hexes as $hex) {
        if (!is_array($hex)) {
          continue;
        }

        $hex_q = (int) ($hex['q'] ?? 0);
        $hex_r = (int) ($hex['r'] ?? 0);
        $min_q = $min_q === NULL ? $hex_q : min($min_q, $hex_q);
        $max_q = $max_q === NULL ? $hex_q : max($max_q, $hex_q);
        $min_r = $min_r === NULL ? $hex_r : min($min_r, $hex_r);
        $max_r = $max_r === NULL ? $hex_r : max($max_r, $hex_r);

        if ($min_coord_q === NULL || $hex_q < $min_coord_q || ($hex_q === $min_coord_q && $hex_r < (int) $min_coord_r)) {
          $min_coord_q = $hex_q;
          $min_coord_r = $hex_r;
        }

        if (!empty($hex['is_entry']) || !empty($hex['entry'])) {
          if ($authored_entry_q === NULL || $hex_q < $authored_entry_q || ($hex_q === $authored_entry_q && $hex_r < (int) $authored_entry_r)) {
            $authored_entry_q = $hex_q;
            $authored_entry_r = $hex_r;
          }
        }
      }

      $entry_q = NULL;
      $entry_r = NULL;
      if ($authored_entry_q !== NULL && $authored_entry_r !== NULL) {
        $entry_q = (int) $authored_entry_q;
        $entry_r = (int) $authored_entry_r;
      }
      elseif ($min_q !== NULL && $max_q !== NULL && $min_r !== NULL && $max_r !== NULL
        && 0 >= (int) $min_q && 0 <= (int) $max_q && 0 >= (int) $min_r && 0 <= (int) $max_r) {
        $entry_q = 0;
        $entry_r = 0;
      }
      elseif ($min_coord_q !== NULL && $min_coord_r !== NULL) {
        $entry_q = (int) $min_coord_q;
        $entry_r = (int) $min_coord_r;
      }

      $room_lighting = $this->normalizeLightingLevel($room['lighting'] ?? 'normal');
 
      foreach ($room_hexes as $hex) {
        if (!is_array($hex)) {
          continue;
        }
 
        $hex_q = (int) ($hex['q'] ?? 0);
        $hex_r = (int) ($hex['r'] ?? 0);
        $hex_lighting = array_key_exists('lighting', $hex)
          ? $this->normalizeLightingLevel($hex['lighting'])
          : $room_lighting;

        $hex_id = $this->deriveHexId($room_id, $hex_q, $hex_r, $hex);
        $is_visible = $visible_hex_ids === []
          ? $room_id === $active_room_id
          : in_array($hex_id, $visible_hex_ids, TRUE);
        $is_discovered = $room_state['explored'] || $is_visible || $room_id === $active_room_id;

        $authored_objects = $this->normalizeHexObjects($hex, $room_id, $hex_q, $hex_r);
        $derived_objects = $entity_hex_objects[$room_id]["{$hex_q}:{$hex_r}"] ?? [];

        $hex_index["{$hex_q}:{$hex_r}"] = [
          'hex_id' => $hex_id,
          'q' => $hex_q,
          'r' => $hex_r,
          'terrain_type' => $this->normalizeTileType($hex),
          'lighting' => $hex_lighting,
          'elevation_ft' => is_numeric($hex['elevation_ft'] ?? NULL) ? (float) $hex['elevation_ft'] : 0.0,
          'is_entry' => $entry_q !== NULL && $entry_r !== NULL && $hex_q === $entry_q && $hex_r === $entry_r,
          'is_visible' => $is_visible,
          'is_discovered' => $is_discovered,
          'objects' => $this->mergeHexObjectLists($authored_objects, $derived_objects),
        ];
      }

      if ($min_q !== NULL && $max_q !== NULL && $min_r !== NULL && $max_r !== NULL) {
        for ($q = (int) $min_q; $q <= (int) $max_q; $q++) {
          for ($r = (int) $min_r; $r <= (int) $max_r; $r++) {
            $key = "{$q}:{$r}";
            if (isset($hex_index[$key])) {
              continue;
            }

            $hex_id = $this->deriveHexId($room_id, $q, $r);
            $is_visible = $visible_hex_ids === []
              ? $room_id === $active_room_id
              : in_array($hex_id, $visible_hex_ids, TRUE);
            $is_discovered = $room_state['explored'] || $is_visible || $room_id === $active_room_id;

            $derived_objects = $entity_hex_objects[$room_id]["{$q}:{$r}"] ?? [];

            $hex_index[$key] = [
              'hex_id' => $hex_id,
              'q' => $q,
              'r' => $r,
              'terrain_type' => 'floor',
              'lighting' => $room_lighting,
              'elevation_ft' => 0.0,
              'is_entry' => $entry_q !== NULL && $entry_r !== NULL && $q === $entry_q && $r === $entry_r,
              'is_visible' => $is_visible,
              'is_discovered' => $is_discovered,
              'objects' => $this->mergeHexObjectLists([], $derived_objects),
            ];
          }
        }
      }

      $topology_hexes = array_values($hex_index);
      usort($topology_hexes, static function (array $a, array $b): int {
        $qa = (int) ($a['q'] ?? 0);
        $qb = (int) ($b['q'] ?? 0);
        if ($qa !== $qb) {
          return $qa <=> $qb;
        }
        return ((int) ($a['r'] ?? 0)) <=> ((int) ($b['r'] ?? 0));
      });

      $topology_rooms[$room_id] = [
        'room_id' => $room_id,
        'name' => (string) ($room['name'] ?? $room_id),
        'subtitle' => (string) ($room['subtitle'] ?? ''),
        'description' => (string) ($room['description'] ?? ''),
        'room_type' => (string) ($room['room_type'] ?? 'unknown'),
        'size_category' => (string) ($room['size_category'] ?? 'medium'),
        'lighting' => $room_lighting,
        'terrain' => is_array($room['terrain'] ?? NULL) ? $room['terrain'] : [],
        'hexes' => $topology_hexes,
        'hex_bounds' => [
          'has_hexes' => $topology_hexes !== [],
          'hex_count' => count($topology_hexes),
          'min_q' => $min_q === NULL ? 0 : (int) $min_q,
          'max_q' => $max_q === NULL ? 0 : (int) $max_q,
          'min_r' => $min_r === NULL ? 0 : (int) $min_r,
          'max_r' => $max_r === NULL ? 0 : (int) $max_r,
        ],
        'interactables' => $this->normalizeRoomInteractables($room),
        'state' => [
          'explored' => $room_state['explored'],
          'cleared' => $room_state['cleared'],
          'visibility_state' => $room_state['visibility_state'],
        ],
      ];

      $visibility_room_states[$room_id] = [
        'explored' => $room_state['explored'],
        'cleared' => $room_state['cleared'],
        'visibility_state' => $room_state['visibility_state'],
      ];
      $visible_hex_ids_by_room[$room_id] = $visible_hex_ids;

      if ($room_state['explored'] || $room_id === $active_room_id) {
        $discovered_room_ids[] = $room_id;
      }
    }

    $connections = $this->normalizeConnections($dungeon_payload, $topology_rooms);
    $topology_rooms = $this->attachRoomExits($topology_rooms, $connections);
    $topology_rooms = $this->attachQuestDestinationExits($topology_rooms, $dungeon_payload, $active_room_id);
    $occupants = $this->normalizeOccupants(
      $dungeon_payload,
      $active_room_id,
      $visible_hex_ids_by_room,
      $launch_character,
      $object_definitions
    );
    $occupants = $this->dedupeOccupantsByLogicalIdentity($occupants);
    $actor_roster = $this->buildActorRoster(
      $dungeon_payload,
      $occupants,
      $active_room_id,
      $launch_context,
      $launch_character
    );

    return [
      'schema_version' => self::SCHEMA_VERSION,
      'map_meta' => [
        'campaign_id' => (int) ($launch_context['campaign_id'] ?? 0),
        'dungeon_level_id' => (string) ($launch_context['dungeon_level_id'] ?? $dungeon_payload['level_id'] ?? ''),
        'map_id' => (string) ($dungeon_payload['map_id'] ?? $launch_context['map_id'] ?? ''),
        'active_room_id' => $active_room_id,
        'hex_grid' => $this->buildHexGrid($dungeon_payload, $topology_rooms, $active_room_id),
      ],
      'topology' => [
        'rooms' => $topology_rooms,
        'connections' => $connections,
        'regions' => array_values(is_array($dungeon_payload['regions'] ?? NULL) ? $dungeon_payload['regions'] : []),
      ],
      'visibility' => [
        'active_room_id' => $active_room_id,
        'discovered_room_ids' => array_values(array_unique($discovered_room_ids)),
        'visible_hex_ids_by_room' => $visible_hex_ids_by_room,
        'fog_mode' => $this->resolveFogMode($dungeon_payload),
        'room_states' => $visibility_room_states,
      ],
      'occupants' => $occupants,
      'actor_roster' => $actor_roster,
      'presentation' => [
        'object_definitions' => $object_definitions,
        'layer_order' => self::LAYER_ORDER,
        'legend' => $this->buildLegend($topology_rooms, $connections, $occupants),
      ],
    ];
  }

  /**
   * Normalize room-level visual state from current room payload fields.
   */
  protected function normalizeRoomState(array $room, string $room_id, string $active_room_id): array {
    $state = is_array($room['state'] ?? NULL)
      ? $room['state']
      : (is_array($room['gameplay_state'] ?? NULL) ? $room['gameplay_state'] : []);

    $visible_hex_ids = [];
    if (isset($state['visible_hex_ids']) && is_array($state['visible_hex_ids'])) {
      $visible_hex_ids = array_values(array_filter(array_map('strval', $state['visible_hex_ids'])));
    }
    elseif (isset($state['visibleHexIds']) && is_array($state['visibleHexIds'])) {
      $visible_hex_ids = array_values(array_filter(array_map('strval', $state['visibleHexIds'])));
    }

    $explored = !empty($state['explored']) || $room_id === $active_room_id;
    $cleared = !empty($state['cleared']) || !empty($state['is_cleared']) || !empty($state['isCleared']);
    $visibility_state = (string) ($state['visibility_state'] ?? $state['visibility'] ?? ($room_id === $active_room_id ? 'visible' : ($explored ? 'discovered' : 'hidden')));

    return [
      'explored' => $explored,
      'cleared' => $cleared,
      'visibility_state' => $visibility_state !== '' ? $visibility_state : 'hidden',
      'visible_hex_ids' => $visible_hex_ids,
    ];
  }

  /**
   * Normalize room lighting from legacy scalar or canonical object payloads.
   */
  protected function normalizeLightingLevel(mixed $lighting): string {
    if (is_array($lighting)) {
      $level = trim((string) ($lighting['level'] ?? $lighting['lighting_level'] ?? ''));
      return $level !== '' ? $level : 'normal';
    }

    $level = trim((string) $lighting);
    return $level !== '' ? $level : 'normal';
  }

  /**
   * Normalize room-authored interactables for canonical visual consumers.
   */
  protected function normalizeRoomInteractables(array $room): array {
    $interactables = [];
    foreach ((is_array($room['interactables'] ?? NULL) ? $room['interactables'] : []) as $interactable) {
      if (is_string($interactable)) {
        $name = trim($interactable);
        if ($name !== '') {
          $interactables[] = $name;
        }
        continue;
      }
      if (!is_array($interactable)) {
        continue;
      }

      $id = trim((string) ($interactable['interactable_id'] ?? $interactable['id'] ?? ''));
      $name = trim((string) ($interactable['name'] ?? $interactable['label'] ?? $id));
      if ($name === '' && $id === '') {
        continue;
      }

      $hex = is_array($interactable['hex'] ?? NULL) ? $interactable['hex'] : [];
      $options = is_array($interactable['options'] ?? NULL) ? $interactable['options'] : [];
      $interactables[] = [
        'id' => $id !== '' ? $id : $name,
        'label' => $name !== '' ? $name : $id,
        'description' => (string) ($interactable['description'] ?? ''),
        'position' => [
          'q' => (int) ($hex['q'] ?? 0),
          'r' => (int) ($hex['r'] ?? 0),
        ],
        'options' => array_values(array_map(static function ($option): string {
          if (is_array($option)) {
            return (string) ($option['label'] ?? $option['id'] ?? '');
          }
          return (string) $option;
        }, $options)),
      ];
    }

    return $interactables;
  }

  /**
   * Normalize object definitions from the current payload.
   */
  protected function normalizeObjectDefinitions(array $dungeon_payload): array {
    $definitions = [];
    foreach ((is_array($dungeon_payload['object_definitions'] ?? NULL) ? $dungeon_payload['object_definitions'] : []) as $definition_key => $definition) {
      if (!is_array($definition)) {
        continue;
      }
      $object_id = trim((string) ($definition['object_id'] ?? $definition_key));
      if ($object_id === '') {
        continue;
      }

      $visual = is_array($definition['visual'] ?? NULL) ? $definition['visual'] : [];
      $movement = is_array($definition['movement'] ?? NULL) ? $definition['movement'] : [];
      $definitions[$object_id] = [
        'object_id' => $object_id,
        'label' => (string) ($definition['label'] ?? $object_id),
        'category' => (string) ($definition['category'] ?? 'decor'),
        'description' => (string) ($definition['description'] ?? ''),
        'visual' => [
          'sprite_id' => (string) ($visual['sprite_id'] ?? $object_id),
          'size' => (string) ($visual['size'] ?? 'medium'),
          'color' => isset($visual['color']) ? (string) $visual['color'] : NULL,
          'orientation' => (string) ($visual['orientation'] ?? $definition['orientation'] ?? 'n'),
          'layer' => $this->resolveVisualLayer((string) ($definition['category'] ?? 'decor')),
        ],
        'movement' => [
          'passable' => array_key_exists('passable', $movement) ? (bool) $movement['passable'] : TRUE,
          'blocks_movement' => array_key_exists('blocks_movement', $movement) ? (bool) $movement['blocks_movement'] : FALSE,
        ],
      ];
    }

    return $definitions;
  }

  /**
   * Normalize connection payloads for the visual contract.
   */
  protected function normalizeConnections(array $dungeon_payload, array $rooms = []): array {
    $connections = [];
    foreach ($this->extractRawConnections($dungeon_payload) as $connection) {
      if (!is_array($connection)) {
        continue;
      }

      $from_endpoint = is_array($connection['from'] ?? NULL) ? $connection['from'] : [];
      $to_endpoint = is_array($connection['to'] ?? NULL) ? $connection['to'] : [];
      $from_hex = is_array($connection['from_hex'] ?? NULL)
        ? $connection['from_hex']
        : $from_endpoint;
      $to_hex = is_array($connection['to_hex'] ?? NULL)
        ? $connection['to_hex']
        : $to_endpoint;
      $from_room_id = $this->resolveConnectionRoomId($connection, 'from', $rooms, $from_hex);
      $to_room_id = $this->resolveConnectionRoomId($connection, 'to', $rooms, $to_hex);
      $has_from_hex = array_key_exists('q', $from_hex) && array_key_exists('r', $from_hex);
      $has_to_hex = array_key_exists('q', $to_hex) && array_key_exists('r', $to_hex);
      $from_q = (int) ($from_hex['q'] ?? 0);
      $from_r = (int) ($from_hex['r'] ?? 0);
      $to_q = (int) ($to_hex['q'] ?? 0);
      $to_r = (int) ($to_hex['r'] ?? 0);

      $type = (string) ($connection['type'] ?? 'open_passage');
      $destination_type = NavigationService::normalizeDestinationType($connection);
      $destination_id = NavigationService::normalizeDestinationId($connection, $to_room_id, $destination_type);
      $distance = NavigationService::normalizeDistance($connection, $destination_type);

      $is_discovered = array_key_exists('is_discovered', $connection)
        ? (bool) $connection['is_discovered']
        : (array_key_exists('is_known', $connection) ? (bool) $connection['is_known'] : TRUE);
      $is_passable = (bool) ($connection['is_passable'] ?? TRUE);
      $bidirectional = array_key_exists('bidirectional', $connection)
        ? (bool) $connection['bidirectional']
        : $type !== 'one_way';
      $requires_interaction = NavigationService::resolveRequiresInteraction($connection);
      $blocked_reason = NavigationService::resolveBlockedReason(
        $to_room_id,
        $destination_type,
        $destination_id,
        $distance,
        $is_discovered,
        $is_passable,
        NavigationService::hasRoomRoadAnchorInPayload($dungeon_payload, $from_room_id)
      );

      $connection_id = trim((string) ($connection['connection_id'] ?? $connection['id'] ?? ''));
      if ($connection_id === '') {
        $connection_id = $this->deriveCanonicalConnectionId($connection, $from_room_id, $to_room_id);
      }

      $normalized_from_hex_id = ($from_room_id !== '' && $has_from_hex)
        ? $this->deriveHexId($from_room_id, $from_q, $from_r, $from_hex)
        : '';
      $normalized_to_hex_id = ($to_room_id !== '' && $has_to_hex)
        ? $this->deriveHexId($to_room_id, $to_q, $to_r, $to_hex)
        : '';

      $connections[] = [
        'connection_id' => $connection_id,
        'from_room_id' => $from_room_id,
        'to_room_id' => $to_room_id,
        'from_hex_id' => $normalized_from_hex_id,
        'to_hex_id' => $normalized_to_hex_id,
        'from' => [
          'room_id' => $from_room_id,
          'hex_id' => $normalized_from_hex_id,
          'q' => $from_q,
          'r' => $from_r,
        ],
        'to' => [
          'room_id' => $to_room_id,
          'hex_id' => $normalized_to_hex_id,
          'q' => $to_q,
          'r' => $to_r,
        ],
        'type' => $type,
        'destination_type' => $destination_type,
        'destination_id' => $destination_id,
        'distance' => $distance,
        'available' => $blocked_reason === NULL,
        'blocked_reason' => $blocked_reason,
        'bidirectional' => $bidirectional,
        'requires_interaction' => $requires_interaction,
        'is_discovered' => $is_discovered,
        'is_passable' => $is_passable,
        'visibility_state' => $is_discovered ? 'visible' : 'hidden',
        'travel_time_seconds' => is_numeric($connection['travel_time_seconds'] ?? NULL) ? max(0, (int) $connection['travel_time_seconds']) : NULL,
      ];
    }

    return $connections;
  }

  /**
   * Merge connection sources into one canonical list for projection.
   */
  protected function extractRawConnections(array $dungeon_payload): array {
    $sources = [];
    if (is_array($dungeon_payload['hex_map']['connections'] ?? NULL)) {
      $sources[] = $dungeon_payload['hex_map']['connections'];
    }
    if (is_array($dungeon_payload['connections'] ?? NULL)) {
      $sources[] = $dungeon_payload['connections'];
    }
    if ($sources === []) {
      return [];
    }

    $connections = [];
    $seen = [];
    foreach ($sources as $bucket) {
      foreach ($bucket as $connection) {
        if (!is_array($connection)) {
          continue;
        }
        $identity = $this->buildConnectionIdentityKey($connection);
        if (isset($seen[$identity])) {
          continue;
        }
        $seen[$identity] = TRUE;
        $connections[] = $connection;
      }
    }

    return $connections;
  }

  /**
   * Build a stable identity key for connection deduplication.
   */
  protected function buildConnectionIdentityKey(array $connection): string {
    $explicit = trim((string) ($connection['connection_id'] ?? $connection['id'] ?? ''));
    if ($explicit !== '') {
      return 'id:' . $explicit;
    }

    $from_room = trim((string) (
      $connection['from_room']
      ?? $connection['from_room_id']
      ?? ($connection['from']['room_id'] ?? $connection['from']['room'] ?? '')
    ));
    $to_room = trim((string) (
      $connection['to_room']
      ?? $connection['to_room_id']
      ?? ($connection['to']['room_id'] ?? $connection['to']['room'] ?? '')
    ));
    $type = trim((string) ($connection['type'] ?? 'passage')) ?: 'passage';
    $from_hex = $this->normalizeHex($connection['from_hex'] ?? ($connection['from'] ?? NULL));
    $to_hex = $this->normalizeHex($connection['to_hex'] ?? ($connection['to'] ?? NULL));

    return 'sig:' . sha1(json_encode([
      'from_room' => $from_room,
      'to_room' => $to_room,
      'type' => $type,
      'from_q' => $from_hex['q'] ?? NULL,
      'from_r' => $from_hex['r'] ?? NULL,
      'to_q' => $to_hex['q'] ?? NULL,
      'to_r' => $to_hex['r'] ?? NULL,
      'destination_type' => trim((string) ($connection['destination_type'] ?? $connection['to_type'] ?? '')),
      'destination_id' => trim((string) ($connection['destination_id'] ?? $connection['road_node_id'] ?? $connection['road_id'] ?? '')),
      'distance' => $connection['distance'] ?? $connection['travel_distance'] ?? $connection['distance_units'] ?? NULL,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
  }

  /**
   * Canonical implicit connection id aligned with NavigationService.
   */
  protected function deriveCanonicalConnectionId(array $connection, string $from_room_id, string $to_room_id): string {
    $type = trim((string) ($connection['type'] ?? 'passage')) ?: 'passage';
    $from_hex = $this->normalizeHex($connection['from_hex'] ?? ($connection['from'] ?? NULL));
    $to_hex = $this->normalizeHex($connection['to_hex'] ?? ($connection['to'] ?? NULL));
    $scope_suffix = 'unscoped';
    if ($from_hex !== NULL && $to_hex !== NULL) {
      $scope_suffix = $from_hex['q'] . ':' . $from_hex['r'] . '__' . $to_hex['q'] . ':' . $to_hex['r'];
    }
    return ($from_room_id ?: 'unknown') . '__' . ($to_room_id ?: 'unknown') . '__' . $type . '__' . $scope_suffix;
  }

  /**
   * Normalize one hex payload into canonical integer coordinates.
   *
   * @return array<string, int>|null
   *   Canonical hex coordinate payload or NULL when unavailable.
   */
  protected function normalizeHex(mixed $hex): ?array {
    if (!is_array($hex) || !array_key_exists('q', $hex) || !array_key_exists('r', $hex)) {
      return NULL;
    }

    return [
      'q' => (int) $hex['q'],
      'r' => (int) $hex['r'],
    ];
  }

  /**
   * Attach per-room exits derived from normalized connections.
   */
  protected function attachRoomExits(array $rooms, array $connections): array {
    $authored_exit_index = $this->indexAuthoredRoomExits($rooms);
    foreach ($rooms as $room_id => &$room) {
      if (!is_array($room)) {
        $room = [];
      }
      $room['exits'] = [];
    }
    unset($room);

    foreach ($connections as $connection) {
      if (!is_array($connection)) {
        continue;
      }

      $connection_id = trim((string) ($connection['connection_id'] ?? ''));
      $from_room_id = trim((string) ($connection['from_room_id'] ?? ''));
      $to_room_id = trim((string) ($connection['to_room_id'] ?? ''));
      if ($connection_id === '' || $from_room_id === '' || $to_room_id === '') {
        continue;
      }

      $type = (string) ($connection['type'] ?? 'open_passage');
      $is_passable = (bool) ($connection['is_passable'] ?? TRUE);
      $is_discovered = (bool) ($connection['is_discovered'] ?? TRUE);
      $visibility_state = (string) ($connection['visibility_state'] ?? ($is_discovered ? 'visible' : 'hidden'));
      $distance = is_numeric($connection['distance'] ?? NULL) ? max(0, (int) $connection['distance']) : 0;
      $blocked_reason = trim((string) ($connection['blocked_reason'] ?? '')) ?: NULL;
      $available = array_key_exists('available', $connection)
        ? (bool) $connection['available']
        : $blocked_reason === NULL;
      $bidirectional = array_key_exists('bidirectional', $connection)
        ? (bool) $connection['bidirectional']
        : $type !== 'one_way';
      $requires_interaction = array_key_exists('requires_interaction', $connection)
        ? (bool) $connection['requires_interaction']
        : NavigationService::resolveRequiresInteraction($connection);
      $destination_type = NavigationService::normalizeDestinationType($connection);
      $destination_id = NavigationService::normalizeDestinationId($connection, $to_room_id, $destination_type);
      $travel_time_seconds = is_numeric($connection['travel_time_seconds'] ?? NULL) ? max(0, (int) $connection['travel_time_seconds']) : NULL;

      $from = is_array($connection['from'] ?? NULL) ? $connection['from'] : [];
      $to = is_array($connection['to'] ?? NULL) ? $connection['to'] : [];
      $from_room_name = trim((string) ($connection['from_room_name'] ?? $connection['from_name'] ?? ''));
      $to_room_name = trim((string) ($connection['to_room_name'] ?? $connection['to_name'] ?? ''));

      $exit_from = $this->mergeAuthoredExitMetadata(
        $from_room_id,
        $this->buildRoomExitPayload(
          $connection_id,
          $type,
          $to_room_id,
          $to_room_name,
          $from,
          $to,
          (string) ($connection['from_hex_id'] ?? ''),
          (string) ($connection['to_hex_id'] ?? ''),
          $is_passable,
          $is_discovered,
          $visibility_state,
          $destination_type,
          $destination_id,
          $distance,
          $available,
          $blocked_reason,
          $bidirectional,
          $requires_interaction,
          $travel_time_seconds
        ),
        $authored_exit_index
      );

      $reverse_destination_type = 'room';
      $reverse_destination_id = $from_room_id;
      $reverse_blocked_reason = NavigationService::resolveBlockedReason(
        $from_room_id,
        $reverse_destination_type,
        $reverse_destination_id,
        $distance,
        $is_discovered,
        $is_passable,
        TRUE
      );

      $exit_to = $this->mergeAuthoredExitMetadata(
        $to_room_id,
        $this->buildRoomExitPayload(
          $connection_id,
          $type,
          $from_room_id,
          $from_room_name,
          $to,
          $from,
          (string) ($connection['to_hex_id'] ?? ''),
          (string) ($connection['from_hex_id'] ?? ''),
          $is_passable,
          $is_discovered,
          $visibility_state,
          $reverse_destination_type,
          $reverse_destination_id,
          $distance,
          $reverse_blocked_reason === NULL,
          $reverse_blocked_reason,
          $bidirectional,
          $requires_interaction,
          $travel_time_seconds
        ),
        $authored_exit_index
      );

      if (isset($rooms[$from_room_id]) && is_array($rooms[$from_room_id])) {
        $rooms[$from_room_id]['exits'][] = $exit_from;
      }
      // Avoid duplicate exits for self-loop connections.
      if ($to_room_id !== $from_room_id && isset($rooms[$to_room_id]) && is_array($rooms[$to_room_id])) {
        $rooms[$to_room_id]['exits'][] = $exit_to;
      }
    }

    foreach ($rooms as &$room) {
      if (!is_array($room)) {
        continue;
      }
      $room['exits'] = is_array($room['exits'] ?? NULL) ? $room['exits'] : [];
      usort($room['exits'], static function (array $a, array $b): int {
        $key_a = (string) ($a['connection_id'] ?? '') . ':' . (string) ($a['target_room_id'] ?? '');
        $key_b = (string) ($b['connection_id'] ?? '') . ':' . (string) ($b['target_room_id'] ?? '');
        return $key_a <=> $key_b;
      });
    }
    unset($room);

    return $rooms;
  }

  /**
   * Index room-authored exits so projection can preserve authored metadata.
   *
   * @param array<string, array<string, mixed>> $rooms
   *   Normalized room payloads keyed by room id.
   *
   * @return array<string, array<string, mixed>>
   *   Flat lookup keyed by room-exit identity.
   */
  protected function indexAuthoredRoomExits(array $rooms): array {
    $index = [];
    foreach ($rooms as $room_id => $room) {
      if (!is_array($room)) {
        continue;
      }
      foreach ((array) ($room['exits'] ?? []) as $exit) {
        if (!is_array($exit)) {
          continue;
        }
        foreach ($this->buildRoomExitIdentityKeys((string) $room_id, $exit) as $key) {
          $index[$key] = $exit;
        }
      }
    }

    return $index;
  }

  /**
   * Merge authored room-exit metadata onto a projected exit payload.
   *
   * @param string $source_room_id
   *   Room that owns the exit.
   * @param array<string, mixed> $projected_exit
   *   Projected exit payload.
   * @param array<string, array<string, mixed>> $authored_exit_index
   *   Flat authored exit lookup.
   *
   * @return array<string, mixed>
   *   Exit payload with authored metadata preserved.
   */
  protected function mergeAuthoredExitMetadata(string $source_room_id, array $projected_exit, array $authored_exit_index): array {
    foreach ($this->buildRoomExitIdentityKeys($source_room_id, $projected_exit) as $key) {
      $authored_exit = $authored_exit_index[$key] ?? NULL;
      if (!is_array($authored_exit)) {
        continue;
      }

      foreach (['label', 'link_type'] as $field) {
        if (
          (!array_key_exists($field, $projected_exit) || trim((string) $projected_exit[$field]) === '')
          && array_key_exists($field, $authored_exit)
          && trim((string) $authored_exit[$field]) !== ''
        ) {
          $projected_exit[$field] = (string) $authored_exit[$field];
        }
      }

      if (empty($projected_exit['target_room_name']) && !empty($authored_exit['target_room_name'])) {
        $projected_exit['target_room_name'] = (string) $authored_exit['target_room_name'];
      }

      return $projected_exit;
    }

    return $projected_exit;
  }

  /**
   * Build stable identity keys for matching authored and projected exits.
   *
   * @param string $source_room_id
   *   Room that owns the exit.
   * @param array<string, mixed> $exit
   *   Exit payload.
   *
   * @return array<int, string>
   *   Identity keys in match-priority order.
   */
  protected function buildRoomExitIdentityKeys(string $source_room_id, array $exit): array {
    $source_room_id = trim($source_room_id);
    $target_room_id = trim((string) ($exit['target_room_id'] ?? ''));
    if ($source_room_id === '' || $target_room_id === '') {
      return [];
    }

    $keys = [];
    $connection_id = trim((string) ($exit['connection_id'] ?? ''));
    if ($connection_id !== '') {
      $keys[] = 'id:' . $source_room_id . ':' . $connection_id;
    }

    $type = trim((string) ($exit['type'] ?? 'open_passage')) ?: 'open_passage';
    $origin_hex = is_array($exit['origin_hex'] ?? NULL)
      ? $exit['origin_hex']
      : (is_array($exit['hex'] ?? NULL) ? $exit['hex'] : $exit);
    $q = is_numeric($origin_hex['q'] ?? NULL) ? (int) $origin_hex['q'] : NULL;
    $r = is_numeric($origin_hex['r'] ?? NULL) ? (int) $origin_hex['r'] : NULL;
    $keys[] = implode('|', [
      $source_room_id,
      $target_room_id,
      $type,
      $q !== NULL ? (string) $q : '',
      $r !== NULL ? (string) $r : '',
    ]);
    $keys[] = implode('|', [
      $source_room_id,
      $target_room_id,
      $q !== NULL ? (string) $q : '',
      $r !== NULL ? (string) $r : '',
    ]);

    return $keys;
  }

  /**
   * Build one canonical room-exit payload.
   */
  protected function buildRoomExitPayload(
    string $connection_id,
    string $type,
    string $target_room_id,
    string $target_room_name,
    array $origin,
    array $target,
    string $origin_hex_fallback,
    string $target_hex_fallback,
    bool $is_passable,
    bool $is_discovered,
    string $visibility_state,
    string $destination_type,
    string $destination_id,
    int $distance,
    bool $available,
    ?string $blocked_reason,
    bool $bidirectional,
    bool $requires_interaction,
    ?int $travel_time_seconds
  ): array {
    return [
      'connection_id' => $connection_id,
      'type' => $type,
      'target_room_id' => $target_room_id,
      'target_room_name' => $target_room_name,
      'origin_hex' => $this->buildExitHexReference($origin, $origin_hex_fallback),
      'target_hex' => $this->buildExitHexReference($target, $target_hex_fallback),
      'is_passable' => $is_passable,
      'is_discovered' => $is_discovered,
      'visibility_state' => $visibility_state,
      'destination_type' => $destination_type,
      'destination_id' => $destination_id,
      'distance' => $distance,
      'available' => $available,
      'blocked_reason' => $blocked_reason,
      'bidirectional' => $bidirectional,
      'requires_interaction' => $requires_interaction,
      'travel_time_seconds' => $travel_time_seconds,
    ];
  }

  /**
   * Build canonical hex endpoint payload for a room exit.
   */
  protected function buildExitHexReference(array $endpoint, string $hex_id_fallback): array {
    return [
      'hex_id' => (string) ($endpoint['hex_id'] ?? $hex_id_fallback),
      'q' => (int) ($endpoint['q'] ?? 0),
      'r' => (int) ($endpoint['r'] ?? 0),
    ];
  }

  /**
   * Attach synthetic quest destination exits for the active room.
   */
  protected function attachQuestDestinationExits(array $rooms, array $dungeon_payload, string $active_room_id): array {
    $active_room_id = trim($active_room_id);
    if ($active_room_id === '' || !isset($rooms[$active_room_id]) || !is_array($rooms[$active_room_id])) {
      return $rooms;
    }

    $quest_entries = $this->extractQuestEntriesFromPayload($dungeon_payload);
    if ($quest_entries === []) {
      return $rooms;
    }

    if ($this->navigationService === NULL) {
      throw new \RuntimeException('Map visual projection contract violation: NavigationService is required; fallback navigation construction is not supported.');
    }
    $navigation_service = $this->navigationService;
    $capabilities = $navigation_service->buildNavigationCapabilitiesWithQuestTargets($dungeon_payload, $active_room_id, $quest_entries);
    $quest_capabilities = array_values(array_filter($capabilities, static fn(array $capability): bool => !empty($capability['quest_reference'])));
    if ($quest_capabilities === []) {
      return $rooms;
    }

    $rooms[$active_room_id]['exits'] = is_array($rooms[$active_room_id]['exits'] ?? NULL) ? $rooms[$active_room_id]['exits'] : [];
    $existing_exits = &$rooms[$active_room_id]['exits'];

    $target_indexes = [];
    foreach ($existing_exits as $index => $exit) {
      $target_room_id = trim((string) ($exit['target_room_id'] ?? ''));
      if ($target_room_id === '') {
        continue;
      }
      $target_indexes[$target_room_id][] = $index;
    }

    foreach ($quest_capabilities as $capability) {
      $target_room_id = trim((string) ($capability['target_room_id'] ?? ''));
      if ($target_room_id === '' || $target_room_id === $active_room_id) {
        continue;
      }

      $quest_ids = array_values(array_unique(array_map(
        'strval',
        is_array($capability['quest_ids'] ?? NULL) ? $capability['quest_ids'] : []
      )));

      if (isset($target_indexes[$target_room_id])) {
        foreach ($target_indexes[$target_room_id] as $index) {
          $existing_quest_ids = array_values(array_unique(array_map(
            'strval',
            is_array($existing_exits[$index]['quest_ids'] ?? NULL) ? $existing_exits[$index]['quest_ids'] : []
          )));
          $existing_exits[$index]['quest_reference'] = TRUE;
          $existing_exits[$index]['quest_ids'] = array_values(array_unique(array_merge($existing_quest_ids, $quest_ids)));
        }
        continue;
      }

      $existing_exits[] = $this->buildQuestExitFromCapability($capability);
      $target_indexes[$target_room_id] = [count($existing_exits) - 1];
    }

    usort($existing_exits, static function (array $a, array $b): int {
      $key_a = (string) ($a['connection_id'] ?? '') . ':' . (string) ($a['target_room_id'] ?? '');
      $key_b = (string) ($b['connection_id'] ?? '') . ':' . (string) ($b['target_room_id'] ?? '');
      return $key_a <=> $key_b;
    });

    return $rooms;
  }

  /**
   * Build one projected quest destination exit from a navigation capability.
   */
  protected function buildQuestExitFromCapability(array $capability): array {
    $target_room_id = trim((string) ($capability['target_room_id'] ?? ''));
    $origin_hex = is_array($capability['origin_hex'] ?? NULL) ? $capability['origin_hex'] : [];
    $target_hex = is_array($capability['target_hex'] ?? NULL) ? $capability['target_hex'] : [];
    $is_discovered = array_key_exists('is_discovered', $capability) ? (bool) $capability['is_discovered'] : TRUE;
    $is_passable = array_key_exists('is_passable', $capability) ? (bool) $capability['is_passable'] : TRUE;

    return [
      'connection_id' => trim((string) ($capability['connection_id'] ?? '')) !== ''
        ? (string) $capability['connection_id']
        : "quest-synthetic-{$target_room_id}",
      'type' => (string) ($capability['type'] ?? 'synthetic'),
      'target_room_id' => $target_room_id,
      'origin_hex' => [
        'hex_id' => (string) ($origin_hex['hex_id'] ?? ''),
        'q' => (int) ($origin_hex['q'] ?? 0),
        'r' => (int) ($origin_hex['r'] ?? 0),
      ],
      'target_hex' => [
        'hex_id' => (string) ($target_hex['hex_id'] ?? ''),
        'q' => (int) ($target_hex['q'] ?? 0),
        'r' => (int) ($target_hex['r'] ?? 0),
      ],
      'is_passable' => $is_passable,
      'is_discovered' => $is_discovered,
      'visibility_state' => $is_discovered ? 'visible' : 'hidden',
      'destination_type' => (string) ($capability['destination_type'] ?? 'room'),
      'destination_id' => (string) ($capability['destination_id'] ?? $target_room_id),
      'distance' => is_numeric($capability['distance'] ?? NULL) ? max(0, (int) $capability['distance']) : 0,
      'available' => array_key_exists('available', $capability) ? (bool) $capability['available'] : TRUE,
      'blocked_reason' => trim((string) ($capability['blocked_reason'] ?? '')) ?: NULL,
      'bidirectional' => array_key_exists('bidirectional', $capability) ? (bool) $capability['bidirectional'] : FALSE,
      'requires_interaction' => array_key_exists('requires_interaction', $capability) ? (bool) $capability['requires_interaction'] : FALSE,
      'travel_time_seconds' => is_numeric($capability['travel_time_seconds'] ?? NULL) ? max(0, (int) $capability['travel_time_seconds']) : NULL,
      'quest_reference' => TRUE,
      'quest_ids' => array_values(array_unique(array_map(
        'strval',
        is_array($capability['quest_ids'] ?? NULL) ? $capability['quest_ids'] : []
      ))),
    ];
  }

  /**
   * Extract quest entries from launch payload.
   *
   * @return array<int, array<string, mixed>>
   *   Quest entries from active/offered/lead buckets.
   */
  protected function extractQuestEntriesFromPayload(array $dungeon_payload): array {
    $entries = [];
    $quest_summary = is_array($dungeon_payload['quest_summary'] ?? NULL) ? $dungeon_payload['quest_summary'] : [];
    foreach (['active', 'offers', 'leads'] as $bucket) {
      if (!is_array($quest_summary[$bucket] ?? NULL)) {
        continue;
      }
      foreach ((array) $quest_summary[$bucket] as $quest_entry) {
        if (is_array($quest_entry)) {
          $entries[] = $quest_entry;
        }
      }
    }

    if ($entries === [] && is_array($dungeon_payload['active_quests'] ?? NULL)) {
      foreach ((array) $dungeon_payload['active_quests'] as $quest_entry) {
        if (is_array($quest_entry)) {
          $entries[] = $quest_entry;
        }
      }
    }

    return $entries;
  }

  /**
   * Normalize room-hex objects for visual use.
   */
  protected function normalizeHexObjects(array $hex, string $room_id, int $hex_q, int $hex_r): array {
    $objects = [];
    $hex_id = $this->deriveHexId($room_id, $hex_q, $hex_r, $hex);

    foreach ((is_array($hex['objects'] ?? NULL) ? $hex['objects'] : []) as $object_index => $object) {
      if (!is_array($object)) {
        continue;
      }

      $object_id = trim((string) ($object['object_id'] ?? $object['id'] ?? $object['content_id'] ?? ''));
      if ($object_id === '') {
        continue;
      }

      $object_instance_id = trim((string) ($object['object_instance_id'] ?? $object['instance_id'] ?? ''));
      if ($object_instance_id === '') {
        $object_instance_id = sprintf('%s:%d:%d:%s:%d', $room_id, $hex_q, $hex_r, $object_id, (int) $object_index);
      }

      $visual = is_array($object['visual'] ?? NULL) ? $object['visual'] : [];

      $passable = array_key_exists('passable', $object) ? (bool) $object['passable'] : TRUE;
      $blocks_movement = array_key_exists('blocks_movement', $object)
        ? (bool) $object['blocks_movement']
        : (!$passable);

      $objects[] = [
        'object_id' => $object_id,
        'object_instance_id' => $object_instance_id,
        'label' => (string) ($object['label'] ?? $object['name'] ?? $object_id),
        'category' => (string) ($object['category'] ?? $object['type'] ?? 'decor'),
        'description' => (string) ($object['description'] ?? ''),
        'placement' => [
          'room_id' => $room_id,
          'hex_id' => $hex_id,
          'q' => $hex_q,
          'r' => $hex_r,
        ],
        'orientation' => (string) ($object['orientation'] ?? $visual['orientation'] ?? 'n'),
        'passable' => $passable,
        'blocks_movement' => $blocks_movement,
        'movable' => array_key_exists('movable', $object) ? (bool) $object['movable'] : FALSE,
        'collectible' => array_key_exists('collectible', $object) ? (bool) $object['collectible'] : FALSE,
        'visual' => [
          'sprite_id' => (string) ($visual['sprite_id'] ?? $object_id),
          'size' => (string) ($visual['size'] ?? 'medium'),
          'color' => isset($visual['color']) ? (string) $visual['color'] : NULL,
        ],
      ];
    }

    return $objects;
  }

  /**
   * Derive hex "objects" from entities (items/props) when room-authored objects are absent.
   */
  protected function buildEntityHexObjectsIndex(array $dungeon_payload, array $object_definitions): array {
    $index = [];

    foreach ((is_array($dungeon_payload['entities'] ?? NULL) ? $dungeon_payload['entities'] : []) as $entity) {
      if (!is_array($entity)) {
        continue;
      }

      $placement = is_array($entity['placement'] ?? NULL) ? $entity['placement'] : [];
      $hex = is_array($placement['hex'] ?? NULL) ? $placement['hex'] : [];
      $room_id = trim((string) ($placement['room_id'] ?? ''));
      if ($room_id === '') {
        continue;
      }

      $q = (int) ($hex['q'] ?? 0);
      $r = (int) ($hex['r'] ?? 0);

      $entity_type = strtolower(trim((string) ($entity['entity_type'] ?? 'unknown')));
      if ($this->resolveVisualLayer($entity_type) !== 'props') {
        continue;
      }

      $object_instance_id = trim((string) ($entity['entity_instance_id'] ?? $entity['instance_id'] ?? $entity['id'] ?? ''));
      if ($object_instance_id === '') {
        continue;
      }

      $content_id = trim((string) ($entity['entity_ref']['content_id'] ?? ''));
      $definition = $content_id !== '' ? ($object_definitions[$content_id] ?? []) : [];
      $metadata = is_array($entity['state']['metadata'] ?? NULL) ? $entity['state']['metadata'] : [];

      $object_id = $content_id !== '' ? $content_id : $object_instance_id;
      $hex_id = $this->deriveHexId($room_id, $q, $r);

      $orientation = trim((string) ($placement['orientation'] ?? $metadata['orientation'] ?? $definition['visual']['orientation'] ?? 'n'));
      if ($orientation === '') {
        $orientation = 'n';
      }
      $orientation = strtolower($orientation);

      $movement = is_array($definition['movement'] ?? NULL) ? $definition['movement'] : [];
      $passable = array_key_exists('passable', $movement) ? (bool) $movement['passable'] : TRUE;
      $blocks_movement = array_key_exists('blocks_movement', $movement) ? (bool) $movement['blocks_movement'] : (!$passable);

      $visual = is_array($definition['visual'] ?? NULL) ? $definition['visual'] : [];
      $sprite_id = (string) ($metadata['sprite_id'] ?? $visual['sprite_id'] ?? $object_id);

      $index[$room_id]["{$q}:{$r}"][] = [
        'object_id' => $object_id,
        'object_instance_id' => $object_instance_id,
        'label' => (string) ($metadata['display_name'] ?? $metadata['name'] ?? $definition['label'] ?? $object_id),
        'category' => (string) ($definition['category'] ?? $entity_type ?: 'item'),
        'description' => (string) ($metadata['description'] ?? ''),
        'placement' => [
          'room_id' => $room_id,
          'hex_id' => $hex_id,
          'q' => $q,
          'r' => $r,
        ],
        'orientation' => $orientation,
        'passable' => $passable,
        'blocks_movement' => $blocks_movement,
        'movable' => FALSE,
        'collectible' => FALSE,
        'visual' => [
          'sprite_id' => $sprite_id,
          'size' => (string) ($visual['size'] ?? 'medium'),
          'color' => isset($visual['color']) ? (string) $visual['color'] : NULL,
        ],
      ];
    }

    return $index;
  }

  /**
   * Merge object lists with stable de-duplication (by object_instance_id).
   */
  protected function mergeHexObjectLists(array $primary, array $secondary): array {
    $out = [];
    $seen = [];

    foreach ([$primary, $secondary] as $list) {
      foreach ($list as $object) {
        if (!is_array($object)) {
          continue;
        }
        $instance_id = trim((string) ($object['object_instance_id'] ?? ''));
        $key = $instance_id !== '' ? $instance_id : json_encode($object);
        if ($key === '' || isset($seen[$key])) {
          continue;
        }
        $seen[$key] = TRUE;
        $out[] = $object;
      }
    }

    return $out;
  }

  /**
   * Resolve canonical room id for a connection endpoint.
   */
  protected function resolveConnectionRoomId(array $connection, string $side, array $rooms, array $hex): string {
    $direct = trim((string) (
      $connection["{$side}_room_id"]
      ?? $connection["{$side}_room"]
      ?? $connection[$side]['room_id']
      ?? $connection[$side]['room']
      ?? ''
    ));
    if ($direct !== '') {
      return $direct;
    }

    if (!array_key_exists('q', $hex) || !array_key_exists('r', $hex)) {
      return '';
    }

    $q = (int) ($hex['q'] ?? 0);
    $r = (int) ($hex['r'] ?? 0);
    foreach ($rooms as $room_id => $room) {
      foreach ((is_array($room['hexes'] ?? NULL) ? $room['hexes'] : []) as $room_hex) {
        if ((int) ($room_hex['q'] ?? 0) === $q && (int) ($room_hex['r'] ?? 0) === $r) {
          return (string) ($room['room_id'] ?? $room_id);
        }
      }
    }

    return '';
  }

  /**
   * Normalize current entities into visual occupants.
   */
  protected function normalizeOccupants(array $dungeon_payload, string $active_room_id, array $visible_hex_ids_by_room, array $launch_character, array $object_definitions): array {
    $party = [];
    $entities = [];
    $launch_instance_id = trim((string) ($launch_character['instance_id'] ?? $launch_character['instanceId'] ?? ''));
    $launch_character_id = trim((string) ($launch_character['id'] ?? $launch_character['character_id'] ?? ''));

    foreach ((is_array($dungeon_payload['entities'] ?? NULL) ? $dungeon_payload['entities'] : []) as $entity) {
      if (!is_array($entity)) {
        continue;
      }

      $placement = is_array($entity['placement'] ?? NULL) ? $entity['placement'] : [];
      $hex = is_array($placement['hex'] ?? NULL) ? $placement['hex'] : [];
      $room_id = trim((string) ($placement['room_id'] ?? ''));
      $q = (int) ($hex['q'] ?? 0);
      $r = (int) ($hex['r'] ?? 0);
      $occupant_id = trim((string) ($entity['entity_instance_id'] ?? $entity['instance_id'] ?? $entity['id'] ?? ''));
      $content_id = trim((string) ($entity['entity_ref']['content_id'] ?? ''));
      if ($occupant_id === '' || $room_id === '') {
        continue;
      }

      $hex_id = $this->deriveHexId($room_id, $q, $r);
      $visible_hex_ids = $visible_hex_ids_by_room[$room_id] ?? [];
      $visible = $visible_hex_ids === []
        ? $room_id === $active_room_id
        : in_array($hex_id, $visible_hex_ids, TRUE);

      $metadata = is_array($entity['state']['metadata'] ?? NULL) ? $entity['state']['metadata'] : [];
      $definition = $content_id !== '' ? ($object_definitions[$content_id] ?? []) : [];
      $entity_state = is_array($entity['state'] ?? NULL) ? $entity['state'] : [];
      $merchant_state = is_array($entity_state['merchant'] ?? NULL) ? $entity_state['merchant'] : [];
      $is_merchant = (bool) ($entity_state['merchant_enabled'] ?? $merchant_state['enabled'] ?? !empty($entity_state['merchant_stock']));
      if (!$is_merchant && strtolower(trim((string) ($entity['entity_type'] ?? ''))) === 'npc') {
        $descriptor = strtolower(implode(' ', array_filter(array_map('strval', [
          $metadata['display_name'] ?? '',
          $metadata['name'] ?? '',
          $metadata['role'] ?? '',
          $metadata['occupation'] ?? '',
          $metadata['description'] ?? '',
          $content_id,
          $occupant_id,
        ]))));
        $merchant_keywords = [
          'merchant', 'vendor', 'shop', 'shopkeeper', 'barkeep', 'bartender',
          'keeper', 'innkeeper', 'tavern', 'bar', 'blacksmith', 'smith',
          'armorer', 'apothecary', 'alchemist', 'herbalist', 'trader',
        ];
        foreach ($merchant_keywords as $keyword) {
          if (str_contains($descriptor, $keyword)) {
            $is_merchant = TRUE;
            break;
          }
        }
      }
      $orientation = trim((string) ($placement['orientation'] ?? $metadata['orientation'] ?? $definition['visual']['orientation'] ?? 'n'));
      if ($orientation === '') {
        $orientation = 'n';
      }
      $orientation = strtolower($orientation);

      $character_id = (int) ($metadata['character_id'] ?? $entity['character_id'] ?? 0);
      if ($character_id <= 0) {
        $character_id = NULL;
      }

      $occupant = [
        'occupant_id' => $occupant_id,
        'occupant_type' => (string) ($entity['entity_type'] ?? 'unknown'),
        'content_id' => $content_id,
        'character_id' => $character_id,
        'room_id' => $room_id,
        'hex_id' => $hex_id,
        'placement' => [
          'q' => $q,
          'r' => $r,
          'orientation' => $orientation,
        ],
        'label' => (string) ($metadata['display_name'] ?? $metadata['name'] ?? $content_id ?: $occupant_id),
        'visible' => $visible,
        'presentation' => [
          'sprite_id' => (string) ($metadata['sprite_id'] ?? $definition['visual']['sprite_id'] ?? $content_id),
          'portrait_url' => isset($metadata['portrait_url']) ? (string) $metadata['portrait_url'] : NULL,
          'role' => (string) ($metadata['role'] ?? $metadata['occupation'] ?? ''),
          'is_merchant' => $is_merchant,
          'layer' => (string) ($definition['visual']['layer'] ?? $this->resolveVisualLayer((string) ($entity['entity_type'] ?? 'unknown'))),
          'badge' => isset($metadata['team']) ? (string) $metadata['team'] : NULL,
        ],
        'state' => $this->buildVisualOccupantStateProjection($entity_state, $metadata),
      ];

      $team = strtolower(trim((string) ($metadata['team'] ?? '')));
      $is_party = $launch_instance_id !== '' && $occupant_id === $launch_instance_id;
      $is_party = $is_party || ($launch_character_id !== '' && (string) ($metadata['character_id'] ?? '') === $launch_character_id);
      $is_party = $is_party || in_array($team, ['player', 'ally', 'party'], TRUE);

      if ($is_party) {
        $party[] = $occupant;
      }
      else {
        $entities[] = $occupant;
      }
    }

    return [
      'party' => $party,
      'entities' => $entities,
    ];
  }

  /**
   * Project occupant state payload for map/roster consumers.
   *
   * Preserves canonical actor data slices (abilities, class/ancestry, inventory,
   * spellcasting, etc.) so roster-selected actor sheets don't drop to defaults.
   */
  protected function buildVisualOccupantStateProjection(array $entity_state, array $metadata): array {
    $projected = $entity_state;
    $projected['active'] = (bool) ($entity_state['active'] ?? TRUE);
    $projected['hidden'] = (bool) ($entity_state['hidden'] ?? FALSE);
    $projected['disabled'] = (bool) ($entity_state['disabled'] ?? FALSE);
    if (!is_array($projected['metadata'] ?? NULL)) {
      $projected['metadata'] = $metadata;
    }
    return $projected;
  }

  /**
   * Remove duplicate logical actors from projected occupants.
   */
  protected function dedupeOccupantsByLogicalIdentity(array $occupants): array {
    $deduped = [
      'party' => [],
      'entities' => [],
    ];
    $seen = [];

    foreach (['party', 'entities'] as $bucket) {
      foreach ((is_array($occupants[$bucket] ?? NULL) ? $occupants[$bucket] : []) as $occupant) {
        if (!is_array($occupant)) {
          continue;
        }

        $logical_identity = $this->buildLogicalActorIdentity(
          (string) ($occupant['occupant_type'] ?? ''),
          is_array($occupant['state']['metadata'] ?? NULL) ? $occupant['state']['metadata'] : [],
          (string) ($occupant['occupant_id'] ?? ''),
          (string) ($occupant['content_id'] ?? ''),
          (string) ($occupant['room_id'] ?? ''),
          !empty($occupant['is_party'])
        );
        if ($logical_identity !== '' && isset($seen[$logical_identity])) {
          continue;
        }
        if ($logical_identity !== '') {
          $seen[$logical_identity] = TRUE;
        }
        $deduped[$bucket][] = $occupant;
      }
    }

    return $deduped;
  }

  /**
   * Build canonical actor-roster projection for active-room review surfaces.
   */
  protected function buildActorRoster(array $dungeon_payload, array $occupants, string $active_room_id, array $launch_context, array $launch_character): array {
    $entries = [];
    $party_ids = [];
    $party = is_array($occupants['party'] ?? NULL) ? $occupants['party'] : [];
    foreach ($party as $occupant) {
      if (!is_array($occupant)) {
        continue;
      }
      $occupant_id = trim((string) ($occupant['occupant_id'] ?? ''));
      if ($occupant_id !== '') {
        $party_ids[$occupant_id] = TRUE;
      }
    }

    $launch_instance_id = trim((string) ($launch_character['instance_id'] ?? $launch_character['instanceId'] ?? ''));
    $launch_character_id = (int) ($launch_character['id'] ?? $launch_character['character_id'] ?? 0);
    $campaign_id = (int) ($launch_context['campaign_id'] ?? 0);
    $entities = is_array($dungeon_payload['entities'] ?? NULL) ? $dungeon_payload['entities'] : [];

    foreach ($entities as $entity) {
      if (!is_array($entity)) {
        continue;
      }

      $placement = is_array($entity['placement'] ?? NULL) ? $entity['placement'] : [];
      $room_id = trim((string) ($placement['room_id'] ?? ''));
      $runtime_instance_id = trim((string) ($entity['entity_instance_id'] ?? $entity['instance_id'] ?? $entity['id'] ?? ''));
      if ($room_id === '' || $runtime_instance_id === '') {
        continue;
      }

      $state = is_array($entity['state'] ?? NULL) ? $entity['state'] : [];
      if (!empty($state['hidden'])) {
        continue;
      }
      $metadata = is_array($state['metadata'] ?? NULL) ? $state['metadata'] : [];
      $entity_type = strtolower(trim((string) ($entity['entity_type'] ?? 'unknown')));
      $team = $this->normalizeActorTeam((string) ($metadata['team'] ?? ''));
      $character_id = (int) ($metadata['character_id'] ?? $entity['character_id'] ?? 0);
      $campaign_character_id = (int) ($metadata['campaign_character_id'] ?? 0);
      $side = $this->resolveActorSide(
        $entity_type,
        $team,
        $metadata,
        isset($party_ids[$runtime_instance_id]),
        $runtime_instance_id,
        $launch_instance_id,
        $character_id,
        $launch_character_id
      );
      $actor_kind = $this->resolveActorKind($entity_type, $metadata, $side);
      $display_name = trim((string) ($metadata['display_name'] ?? $metadata['name'] ?? $runtime_instance_id));
      $content_id = trim((string) ($entity['entity_ref']['content_id'] ?? ''));
      $actor_id = $this->resolveActorId($campaign_character_id, $character_id, $runtime_instance_id, $content_id);
      $faction_id = trim((string) ($metadata['faction_id'] ?? $metadata['faction'] ?? ''));
      $initiative = isset($metadata['initiative']) && is_numeric($metadata['initiative'])
        ? (int) $metadata['initiative']
        : NULL;

      $entries[] = [
        'actor_id' => $actor_id,
        'entity_ref' => $runtime_instance_id,
        'runtime_instance_id' => $runtime_instance_id,
        'room_id' => $room_id,
        'actor_kind' => $actor_kind,
        'side' => $side,
        'team' => $team,
        'faction_id' => $faction_id !== '' ? $faction_id : NULL,
        'controllability' => $this->resolveActorControllability($side),
        'display_name' => $display_name !== '' ? $display_name : $actor_id,
        'sheet_ref' => $this->buildActorSheetRef($actor_id, $character_id, $metadata, $campaign_id),
        'presentation' => [
          'portrait_url' => isset($metadata['portrait_url']) ? (string) $metadata['portrait_url'] : NULL,
          'badge' => $team !== 'neutral' ? $team : NULL,
          'sprite_id' => $content_id !== '' ? $content_id : NULL,
        ],
        'combat' => [
          'initiative' => $initiative,
          'is_current' => !empty($metadata['is_current_turn']),
          'is_defeated' => !empty($metadata['is_defeated']) || !empty($state['disabled']),
          'is_participant' => $initiative !== NULL,
        ],
      ];
    }

    $entries = $this->dedupeActorRosterEntries($entries, $entities, $party_ids);

    usort($entries, static function (array $a, array $b): int {
      $room_a = (string) ($a['room_id'] ?? '');
      $room_b = (string) ($b['room_id'] ?? '');
      if ($room_a !== $room_b) {
        return $room_a <=> $room_b;
      }
      return strcasecmp((string) ($a['display_name'] ?? ''), (string) ($b['display_name'] ?? ''));
    });

    return [
      'schema_version' => 'actor-roster-v1',
      'room_id' => $active_room_id,
      'default_filter' => 'party',
      'available_filters' => ['all', 'party', 'allied', 'hostile', 'neutral', 'hazard'],
      'sort_modes' => ['alpha', 'initiative'],
      'entries' => $entries,
    ];
  }

  /**
   * Remove duplicate logical actors from the canonical room roster.
   *
   * @param array<int, array<string, mixed>> $entries
   * @param array<int, array<string, mixed>> $entities
   * @param array<string, bool> $party_ids
   *
   * @return array<int, array<string, mixed>>
   */
  protected function dedupeActorRosterEntries(array $entries, array $entities, array $party_ids): array {
    $deduped = [];
    $seen = [];

    foreach ($entries as $index => $entry) {
      if (!is_array($entry)) {
        continue;
      }

      $entity = is_array($entities[$index] ?? NULL) ? $entities[$index] : [];
      $metadata = is_array($entity['state']['metadata'] ?? NULL) ? $entity['state']['metadata'] : [];
      $runtime_instance_id = trim((string) ($entry['runtime_instance_id'] ?? ''));
      $logical_identity = $this->buildLogicalActorIdentity(
        (string) ($entity['entity_type'] ?? ''),
        $metadata,
        $runtime_instance_id,
        (string) ($entity['entity_ref']['content_id'] ?? ''),
        (string) ($entry['room_id'] ?? ''),
        $runtime_instance_id !== '' && isset($party_ids[$runtime_instance_id])
      );
      if ($logical_identity !== '' && isset($seen[$logical_identity])) {
        continue;
      }
      if ($logical_identity !== '') {
        $seen[$logical_identity] = TRUE;
      }
      $deduped[] = $entry;
    }

    return $deduped;
  }

  /**
   * Build one stable room-scoped logical identity for actors that must be unique.
   */
  protected function buildLogicalActorIdentity(string $entity_type, array $metadata, string $runtime_instance_id, string $content_id, string $room_id, bool $is_party_member = FALSE): string {
    $normalized_room_id = trim($room_id);
    if ($normalized_room_id === '') {
      return '';
    }

    $entity_type = strtolower(trim($entity_type));
    $team = $this->normalizeActorTeam((string) ($metadata['team'] ?? ''));
    $source_character_id = (int) ($metadata['source_character_id'] ?? 0);
    $campaign_character_id = (int) ($metadata['campaign_character_id'] ?? 0);
    $character_id = (int) ($metadata['character_id'] ?? 0);
    $owner_source_character_id = (int) ($metadata['owner_source_character_id'] ?? $metadata['bond_contract']['owner_source_character_id'] ?? 0);
    $owner_character_id = (int) ($metadata['owner_character_id'] ?? $metadata['bond_contract']['owner_character_id'] ?? 0);
    $follower_source_character_id = (int) ($metadata['follower_source_character_id'] ?? 0);
    $follower_kind = $this->resolveFollowerKindFromMetadata($metadata);
    $runtime_entity_id = trim((string) ($metadata['runtime_entity_id'] ?? $runtime_instance_id));
    $is_player_like = $is_party_member
      || in_array($entity_type, ['player_character', 'pc', 'player'], TRUE)
      || $team === 'player';

    if ($follower_kind !== '' && $follower_source_character_id > 0) {
      return sprintf('%s:follower-source:%d:%s', $normalized_room_id, $follower_source_character_id, $follower_kind);
    }
    if ($follower_kind !== '' && $owner_source_character_id > 0) {
      return sprintf('%s:follower-owner-source:%d:%s', $normalized_room_id, $owner_source_character_id, $follower_kind);
    }
    if ($follower_kind !== '' && $owner_character_id > 0) {
      return sprintf('%s:follower-owner:%d:%s', $normalized_room_id, $owner_character_id, $follower_kind);
    }
    if ($is_player_like && $source_character_id > 0) {
      return sprintf('%s:player-source:%d', $normalized_room_id, $source_character_id);
    }
    if ($campaign_character_id > 0) {
      return sprintf('%s:campaign-character:%d', $normalized_room_id, $campaign_character_id);
    }
    if ($is_player_like && $character_id > 0) {
      return sprintf('%s:player-character:%d', $normalized_room_id, $character_id);
    }
    if ($runtime_entity_id !== '') {
      return sprintf('%s:runtime:%s', $normalized_room_id, $runtime_entity_id);
    }
    if ($content_id !== '' && $follower_kind !== '') {
      return sprintf('%s:follower-content:%s:%s', $normalized_room_id, trim($content_id), $follower_kind);
    }

    return '';
  }

  protected function normalizeActorTeam(string $team): string {
    $normalized = strtolower(trim($team));
    if ($normalized === 'party' || $normalized === 'player') {
      return 'player';
    }
    if ($normalized === 'ally' || $normalized === 'allied') {
      return 'ally';
    }
    if ($normalized === 'enemy' || $normalized === 'hostile') {
      return 'enemy';
    }
    return 'neutral';
  }

  protected function resolveActorSide(string $entity_type, string $team, array $metadata, bool $is_party_member, string $runtime_instance_id, string $launch_instance_id, int $character_id, int $launch_character_id): string {
    if (in_array($entity_type, ['hazard', 'trap', 'obstacle'], TRUE)) {
      return 'hazard';
    }

    $owner_character_id = (int) ($metadata['owner_character_id'] ?? $metadata['bond_contract']['owner_character_id'] ?? 0);
    $is_launch_actor = $launch_instance_id !== '' && $runtime_instance_id === $launch_instance_id;
    $is_launch_actor = $is_launch_actor || ($launch_character_id > 0 && $character_id > 0 && $character_id === $launch_character_id);
    if ($is_launch_actor) {
      return 'party';
    }
    if ($owner_character_id > 0 && $launch_character_id > 0 && $owner_character_id === $launch_character_id) {
      return 'party';
    }
    if ($team === 'player') {
      return 'party';
    }
    if ($team === 'ally') {
      return 'allied';
    }
    if ($team === 'enemy') {
      return 'hostile';
    }
    if ($is_party_member) {
      return 'party';
    }
    return 'neutral';
  }

  protected function resolveActorKind(string $entity_type, array $metadata, string $side): string {
    $follower_kind = $this->resolveFollowerKindFromMetadata($metadata);
    if ($follower_kind !== '') {
      return 'follower';
    }
    if ($side === 'hazard') {
      return 'hazard';
    }
    if ($entity_type === 'player_character' || $entity_type === 'pc' || $entity_type === 'player') {
      return 'pc';
    }
    if ($entity_type === 'npc' && !empty($metadata['occupation']) && str_contains(strtolower((string) $metadata['occupation']), 'merchant')) {
      return 'merchant';
    }
    if ($entity_type === 'npc' && $side === 'hostile') {
      return 'creature';
    }
    if ($entity_type === 'npc') {
      return 'npc';
    }
    if ($entity_type !== '') {
      return $entity_type;
    }
    return 'npc';
  }

  protected function resolveFollowerKindFromMetadata(array $metadata): string {
    $kind = strtolower(trim((string) ($metadata['follower_kind'] ?? $metadata['bond_contract']['follower_kind'] ?? '')));
    if ($kind !== '') {
      return $kind;
    }
    $role = strtolower(trim((string) ($metadata['role'] ?? '')));
    if (str_contains($role, 'familiar')) {
      return 'familiar';
    }
    if (str_contains($role, 'companion')) {
      return 'animal_companion';
    }
    if (str_contains($role, 'follower')) {
      return 'follower';
    }
    return '';
  }

  protected function resolveActorId(int $campaign_character_id, int $character_id, string $runtime_instance_id, string $content_id): string {
    if ($campaign_character_id > 0) {
      return 'campaign-character:' . $campaign_character_id;
    }
    if ($character_id > 0) {
      return 'character:' . $character_id;
    }
    if ($runtime_instance_id !== '') {
      return 'runtime:' . $runtime_instance_id;
    }
    if ($content_id !== '') {
      return 'content:' . $content_id;
    }
    return 'actor:unknown';
  }

  protected function resolveActorControllability(string $side): string {
    if ($side === 'party') {
      return 'player';
    }
    if ($side === 'allied' || $side === 'neutral') {
      return 'gm';
    }
    return 'system';
  }

  /**
   * Build a normalized sheet_ref contract for roster entries.
   *
   * @return array<string, mixed>
   */
  protected function buildActorSheetRef(string $actor_id, int $character_id, array $metadata, int $campaign_id): array {
    $query = [];
    if ($campaign_id > 0) {
      $query['campaign_id'] = $campaign_id;
    }

    $owner_character_id = (int) ($metadata['owner_character_id'] ?? $metadata['bond_contract']['owner_character_id'] ?? 0);
    $follower_kind = $this->resolveFollowerKindFromMetadata($metadata);
    if ($owner_character_id > 0 && $follower_kind !== '') {
      return [
        'sheet_type' => 'follower',
        'sheet_id' => 'follower:' . $owner_character_id . ':' . $follower_kind,
        'route_name' => 'dungeoncrawler_content.character_follower_view',
        'route_params' => [
          'character_id' => $owner_character_id,
          'follower_kind' => $follower_kind,
        ],
        'query' => $query,
      ];
    }

    $resolvedCharacterId = $character_id > 0
      ? $character_id
      : (int) ($metadata['campaign_character_id'] ?? 0);
    if ($resolvedCharacterId > 0) {
      return [
        'sheet_type' => 'character',
        'sheet_id' => 'character:' . $resolvedCharacterId,
        'route_name' => 'dungeoncrawler_content.character_view',
        'route_params' => ['character_id' => $resolvedCharacterId],
        'query' => $query,
      ];
    }

    return [
      'sheet_type' => 'actor',
      'sheet_id' => $actor_id,
      'route_name' => 'dungeoncrawler_content.actor_view',
      'route_params' => ['actor_id' => $actor_id],
      'query' => $query,
    ];
  }

  /**
   * Build map-global grid metadata.
   */
  protected function buildHexGrid(array $dungeon_payload, array $rooms, string $active_room_id): array {
    $origin = ['q' => 0, 'r' => 0];
    $candidate_room = $rooms[$active_room_id] ?? reset($rooms) ?: NULL;

    if (is_array($candidate_room) && !empty($candidate_room['hexes']) && is_array($candidate_room['hexes'])) {
      $origin_hex = NULL;
      foreach ($candidate_room['hexes'] as $hex) {
        if (!is_array($hex)) {
          continue;
        }
        if (!empty($hex['is_entry'])) {
          $origin_hex = $hex;
          break;
        }
      }

      if ($origin_hex === NULL && !empty($candidate_room['hexes'][0]) && is_array($candidate_room['hexes'][0])) {
        $origin_hex = $candidate_room['hexes'][0];
      }

      if (is_array($origin_hex)) {
        $origin['q'] = (int) ($origin_hex['q'] ?? 0);
        $origin['r'] = (int) ($origin_hex['r'] ?? 0);
      }
    }

    return [
      'orientation' => 'flat-top',
      'hex_size_ft' => 5,
      'coordinate_system' => 'axial',
      'origin' => $origin,
    ];
  }

  /**
   * Derive a stable hex id.
   */
  protected function deriveHexId(string $room_id, int $q, int $r, array $hex = []): string {
    $existing = trim((string) ($hex['hex_id'] ?? $hex['hexId'] ?? $hex['id'] ?? ''));
    return $existing !== '' ? $existing : sprintf('%s:%d:%d', $room_id, $q, $r);
  }

  /**
   * Normalize terrain type from current room hex fields.
   */
  protected function normalizeTileType(array $hex): string {
    $value = (string) ($hex['terrain_type'] ?? 'floor');
    return $value !== '' ? $value : 'floor';
  }

  /**
   * Resolve a visual layer hint.
   */
  protected function resolveVisualLayer(string $category): string {
    $normalized = strtolower(trim($category));
    if (in_array($normalized, ['creature', 'npc', 'character', 'player', 'party_member'], TRUE)) {
      return 'occupants';
    }
    if (in_array($normalized, ['decor', 'furniture', 'obstacle', 'prop', 'item'], TRUE)) {
      return 'props';
    }
    return 'props';
  }

  /**
   * Resolve canonical fog mode.
   */
  protected function resolveFogMode(array $dungeon_payload): string {
    $hex_map = is_array($dungeon_payload['hex_map'] ?? NULL) ? $dungeon_payload['hex_map'] : [];
    $fog_mode = trim((string) ($dungeon_payload['fog_mode'] ?? $hex_map['fog_mode'] ?? 'room'));
    return $fog_mode !== '' ? $fog_mode : 'room';
  }

  /**
   * Build legend hints from projected visual content.
   */
  protected function buildLegend(array $rooms, array $connections, array $occupants): array {
    $occupant_types = [];
    foreach (['party', 'entities'] as $bucket) {
      foreach ((is_array($occupants[$bucket] ?? NULL) ? $occupants[$bucket] : []) as $occupant) {
        if (!is_array($occupant)) {
          continue;
        }
        $type = trim((string) ($occupant['occupant_type'] ?? ''));
        if ($type === '' || isset($occupant_types[$type])) {
          continue;
        }
        $occupant_types[$type] = [
          'label' => $this->formatLegendLabel($type),
        ];
      }
    }

    $connection_types = [];
    foreach ($connections as $connection) {
      if (!is_array($connection)) {
        continue;
      }
      $type = trim((string) ($connection['type'] ?? ''));
      if ($type === '' || isset($connection_types[$type])) {
        continue;
      }
      $connection_types[$type] = [
        'label' => $this->formatLegendLabel($type),
      ];
    }

    $terrain_types = [];
    foreach ($rooms as $room) {
      if (!is_array($room)) {
        continue;
      }

      $room_terrain = is_array($room['terrain'] ?? NULL) ? $room['terrain'] : [];
      $terrain_key = '';
      if (isset($room_terrain['type']) && is_scalar($room_terrain['type'])) {
        $terrain_key = trim((string) $room_terrain['type']);
      }
      elseif (array_is_list($room_terrain)) {
        foreach ($room_terrain as $terrain) {
          $terrain_key = trim((string) $terrain);
          if ($terrain_key === '' || isset($terrain_types[$terrain_key])) {
            continue;
          }
          $terrain_types[$terrain_key] = [
            'label' => $this->formatLegendLabel($terrain_key),
          ];
        }
        $terrain_key = '';
      }

      if ($terrain_key !== '' && !isset($terrain_types[$terrain_key])) {
        $terrain_types[$terrain_key] = [
          'label' => $this->formatLegendLabel($terrain_key),
        ];
      }

      foreach ((is_array($room['hexes'] ?? NULL) ? $room['hexes'] : []) as $hex) {
        if (!is_array($hex)) {
          continue;
        }
        $terrain_type = trim((string) ($hex['terrain_type'] ?? ''));
        if ($terrain_type === '' || isset($terrain_types[$terrain_type])) {
          continue;
        }
        $terrain_types[$terrain_type] = [
          'label' => $this->formatLegendLabel($terrain_type),
        ];
      }
    }

    return [
      'occupant_types' => $occupant_types,
      'connection_types' => $connection_types,
      'terrain_types' => $terrain_types,
    ];
  }

  /**
   * Build a display label for legend entries.
   */
  protected function formatLegendLabel(string $value): string {
    $normalized = trim(str_replace(['_', '-'], ' ', $value));
    return $normalized !== '' ? ucwords($normalized) : '';
  }

}
