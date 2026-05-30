<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Projects the legacy hexmap payload into the canonical visual-only map state.
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

  /**
   * Build canonical visual map state from the current hexmap payload.
   */
  public function project(array $dungeon_payload, array $launch_context = [], array $launch_character = []): array {
    $active_room_id = trim((string) ($dungeon_payload['active_room_id'] ?? $launch_context['room_id'] ?? ''));
    $rooms = is_array($dungeon_payload['rooms'] ?? NULL) ? $dungeon_payload['rooms'] : [];
    if ($active_room_id === '' && $rooms !== []) {
      $active_room_id = (string) array_key_first($rooms);
    }

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
      $topology_hexes = [];

      foreach ((is_array($room['hexes'] ?? NULL) ? $room['hexes'] : []) as $hex) {
        if (!is_array($hex)) {
          continue;
        }

        $hex_q = (int) ($hex['q'] ?? 0);
        $hex_r = (int) ($hex['r'] ?? 0);
        $hex_id = $this->deriveHexId($room_id, $hex_q, $hex_r, $hex);
        $is_visible = $visible_hex_ids === []
          ? $room_id === $active_room_id
          : in_array($hex_id, $visible_hex_ids, TRUE);
        $is_discovered = $room_state['explored'] || $is_visible || $room_id === $active_room_id;

        $topology_hexes[] = [
          'hex_id' => $hex_id,
          'q' => $hex_q,
          'r' => $hex_r,
          'tile_type' => $this->normalizeTileType($hex),
          'is_entry' => !empty($hex['is_entry']) || !empty($hex['entry']) || ($hex_q === 0 && $hex_r === 0),
          'is_visible' => $is_visible,
          'is_discovered' => $is_discovered,
          'objects' => $this->normalizeHexObjects($hex),
        ];
      }

      $topology_rooms[$room_id] = [
        'room_id' => $room_id,
        'name' => (string) ($room['name'] ?? $room_id),
        'description' => (string) ($room['description'] ?? ''),
        'room_type' => (string) ($room['room_type'] ?? 'unknown'),
        'size_category' => (string) ($room['size_category'] ?? 'medium'),
        'lighting' => $this->normalizeLightingLevel($room['lighting'] ?? 'normal'),
        'terrain' => is_array($room['terrain'] ?? NULL) ? $room['terrain'] : [],
        'hexes' => $topology_hexes,
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

    $object_definitions = $this->normalizeObjectDefinitions($dungeon_payload);
    $connections = $this->normalizeConnections($dungeon_payload, $topology_rooms);
    $occupants = $this->normalizeOccupants(
      $dungeon_payload,
      $active_room_id,
      $visible_hex_ids_by_room,
      $launch_character,
      $object_definitions
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
    foreach ((is_array($dungeon_payload['connections'] ?? NULL) ? $dungeon_payload['connections'] : []) as $connection) {
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

      $is_discovered = array_key_exists('is_discovered', $connection)
        ? (bool) $connection['is_discovered']
        : (array_key_exists('is_known', $connection) ? (bool) $connection['is_known'] : TRUE);

      $connections[] = [
        'connection_id' => (string) ($connection['connection_id'] ?? ''),
        'from_room_id' => $from_room_id,
        'to_room_id' => $to_room_id,
        'from_hex_id' => ($from_room_id !== '' && $has_from_hex) ? $this->deriveHexId($from_room_id, $from_q, $from_r, $from_hex) : '',
        'to_hex_id' => ($to_room_id !== '' && $has_to_hex) ? $this->deriveHexId($to_room_id, $to_q, $to_r, $to_hex) : '',
        'type' => (string) ($connection['type'] ?? 'open_passage'),
        'is_discovered' => $is_discovered,
        'is_passable' => (bool) ($connection['is_passable'] ?? TRUE),
        'visibility_state' => $is_discovered ? 'visible' : 'hidden',
      ];
    }

    return $connections;
  }

  /**
   * Normalize room-hex objects for visual use.
   */
  protected function normalizeHexObjects(array $hex): array {
    $objects = [];
    foreach ((is_array($hex['objects'] ?? NULL) ? $hex['objects'] : []) as $object) {
      if (!is_array($object)) {
        continue;
      }

      $object_id = trim((string) ($object['object_id'] ?? $object['id'] ?? $object['content_id'] ?? ''));
      if ($object_id === '') {
        continue;
      }

      $visual = is_array($object['visual'] ?? NULL) ? $object['visual'] : [];
      $objects[] = [
        'object_id' => $object_id,
        'label' => (string) ($object['label'] ?? $object['name'] ?? $object_id),
        'category' => (string) ($object['category'] ?? $object['type'] ?? 'decor'),
        'description' => (string) ($object['description'] ?? ''),
        'orientation' => (string) ($object['orientation'] ?? $visual['orientation'] ?? 'n'),
        'passable' => array_key_exists('passable', $object) ? (bool) $object['passable'] : NULL,
        'blocks_movement' => array_key_exists('blocks_movement', $object) ? (bool) $object['blocks_movement'] : NULL,
        'movable' => array_key_exists('movable', $object) ? (bool) $object['movable'] : NULL,
        'collectible' => array_key_exists('collectible', $object) ? (bool) $object['collectible'] : NULL,
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
      $occupant = [
        'occupant_id' => $occupant_id,
        'occupant_type' => (string) ($entity['entity_type'] ?? 'unknown'),
        'content_id' => $content_id,
        'room_id' => $room_id,
        'hex_id' => $hex_id,
        'placement' => [
          'q' => $q,
          'r' => $r,
        ],
        'label' => (string) ($metadata['display_name'] ?? $metadata['name'] ?? $content_id ?: $occupant_id),
        'visible' => $visible,
        'presentation' => [
          'sprite_id' => (string) ($metadata['sprite_id'] ?? $definition['visual']['sprite_id'] ?? $content_id),
          'portrait_url' => isset($metadata['portrait_url']) ? (string) $metadata['portrait_url'] : NULL,
          'layer' => (string) ($definition['visual']['layer'] ?? $this->resolveVisualLayer((string) ($entity['entity_type'] ?? 'unknown'))),
          'badge' => isset($metadata['team']) ? (string) $metadata['team'] : NULL,
        ],
        'state' => [
          'active' => (bool) ($entity['state']['active'] ?? TRUE),
          'hidden' => (bool) ($entity['state']['hidden'] ?? FALSE),
          'disabled' => (bool) ($entity['state']['disabled'] ?? FALSE),
        ],
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
   * Build map-global grid metadata.
   */
  protected function buildHexGrid(array $dungeon_payload, array $rooms, string $active_room_id): array {
    $origin = ['q' => 0, 'r' => 0];
    $candidate_room = $rooms[$active_room_id] ?? reset($rooms) ?: NULL;
    if (is_array($candidate_room) && !empty($candidate_room['hexes'][0]) && is_array($candidate_room['hexes'][0])) {
      $origin['q'] = (int) ($candidate_room['hexes'][0]['q'] ?? 0);
      $origin['r'] = (int) ($candidate_room['hexes'][0]['r'] ?? 0);
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
   * Normalize tile type from current room hex fields.
   */
  protected function normalizeTileType(array $hex): string {
    $value = (string) ($hex['tile_type'] ?? $hex['terrain_type'] ?? $hex['type'] ?? 'floor');
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
        $tile_type = trim((string) ($hex['tile_type'] ?? ''));
        if ($tile_type === '' || isset($terrain_types[$tile_type])) {
          continue;
        }
        $terrain_types[$tile_type] = [
          'label' => $this->formatLegendLabel($tile_type),
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
