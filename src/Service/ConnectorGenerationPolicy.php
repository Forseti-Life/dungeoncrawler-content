<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Maps flat generation metadata to the full canonical connector contract.
 *
 * Generation subsystems (DungeonGeneratorService, StorylineRealizationService)
 * produce minimal or boolean connector descriptors. This policy normalizes
 * those into the full ConnectorDefinitionService contract so that generation
 * and manual authoring both produce the same canonical shape.
 *
 * Authority boundary:
 * - This class is stateless: it does NOT write to the database.
 * - Callers receive a normalized array and pass it to ConnectorDefinitionService.
 *
 * Validation pair: ConnectorDefinitionService canonical connector contract
 * validation after normalization.
 */
class ConnectorGenerationPolicy {

  /**
   * Map from raw dungeon generator connection_type to connector kind.
   */
  private const CONNECTION_TYPE_TO_KIND = [
    'door' => 'door',
    'hallway' => 'hallway',
    'corridor' => 'hallway',
    'passage' => 'hallway',
    'open_passage' => 'archway',
    'archway' => 'archway',
    'hatch' => 'hatch',
    'trapdoor' => 'hatch',
    'portcullis' => 'portcullis',
    'gate' => 'portcullis',
    'secret_door' => 'secret_door',
    'hidden' => 'secret_door',
    'magical_barrier' => 'magical_barrier',
    'magic' => 'magical_barrier',
    'collapsed' => 'collapsed',
    'rubble' => 'collapsed',
    'bridge' => 'bridge',
    'one_way' => 'hallway',
    'storyline_progression' => 'hallway',
  ];

  /**
   * Map from raw connector_type (storyline generation) to direction.
   */
  private const CONNECTOR_TYPE_TO_DIRECTION = [
    'storyline_progression' => 'one_way',
    'one_way' => 'one_way',
    'one_way_drop' => 'one_way',
  ];

  /**
   * Default DC values for generated traps by dungeon theme.
   */
  private const TRAP_DC_BY_THEME = [
    'dungeon' => 15,
    'cave' => 14,
    'crypt' => 16,
    'ruins' => 15,
    'underground' => 14,
    'demonic' => 20,
    'underdark' => 18,
    'sewer' => 13,
    'mine' => 14,
    'default' => 15,
  ];

  /**
   * Normalize a raw JSON blob connection (from dungeon_data.hex_map.connections)
   * into the canonical connector contract.
   *
   * Handles both DungeonGeneratorService and StorylineRealizationService shapes.
   *
   * @param string $dungeon_id
   * @param array $raw
   *   Raw connection from JSON blob.
   * @param string $theme
   *   Optional dungeon theme for trap/lock DC scaling.
   *
   * @return array<string, mixed>
   *   Canonical connector contract ready for ConnectorDefinitionService.
   */
  public static function normalizeFromRawJson(string $dungeon_id, array $raw, string $theme = 'default'): array {
    $from_room_id = trim((string) ($raw['from_room_id'] ?? $raw['from_room'] ?? ''));
    $to_room_id = trim((string) ($raw['to_room_id'] ?? $raw['to_room'] ?? ''));

    $raw_type = strtolower(trim((string) (
      $raw['connection_type'] ?? $raw['connector_type'] ?? $raw['type'] ?? 'hallway'
    )));
    if (
      isset($raw['connection_type']) || isset($raw['connector_type']) || isset($raw['type'])
    ) {
      if ($raw_type === '' || !array_key_exists($raw_type, self::CONNECTION_TYPE_TO_KIND)) {
        throw new \InvalidArgumentException(sprintf(
          'Connector generation contract violation: unsupported connection type "%s" for dungeon "%s".',
          (string) ($raw['connection_type'] ?? $raw['connector_type'] ?? $raw['type'] ?? ''),
          $dungeon_id
        ));
      }
    }

    $kind = self::CONNECTION_TYPE_TO_KIND[$raw_type] ?? 'hallway';
    $direction = self::resolveDirection($raw, $raw_type);
    $default_state = self::resolveDefaultState($raw, $kind);

    $trap_data = self::resolveTrapData($raw, $kind, $theme);
    $lock_data = self::resolveLockData($raw, $kind, $theme);

    $is_discovered_default = !empty($raw['is_discovered']) || !isset($raw['is_hidden'])
      ? (empty($raw['is_hidden']) ? 1 : 0)
      : 1;
    $from_hex = self::extractEndpointHex($raw, 'from');
    $to_hex = self::extractEndpointHex($raw, 'to');

    return [
      'dungeon_id' => $dungeon_id,
      'from_room_id' => $from_room_id,
      'to_room_id' => $to_room_id,
      'from_hex' => $from_hex,
      'to_hex' => $to_hex,
      'kind' => $kind,
      'direction' => $direction,
      'default_state' => $default_state,
      'trap_data' => $trap_data,
      'lock_data' => $lock_data,
      'requirements_data' => self::resolveRequirementsData($raw, $trap_data, $lock_data),
      'description' => isset($raw['description']) ? (string) $raw['description'] : NULL,
      'travel_cost' => max(0, (int) ($raw['travel_cost'] ?? $raw['travel_distance'] ?? 0)),
      'is_discovered_default' => $is_discovered_default,
    ];
  }

  /**
   * Normalize a DungeonGeneratorService::connectRoomsInLevel() connection.
   *
   * Input shape:
   * {from_room_id, to_room_id, connection_type:'door', is_locked:bool, is_trapped:bool, is_hidden:bool}
   *
   * @return array<string, mixed>
   *   Canonical connector contract.
   */
  public static function normalizeFromDungeonGenerator(string $dungeon_id, array $raw, string $theme = 'default'): array {
    return self::normalizeFromRawJson($dungeon_id, $raw, $theme);
  }

  /**
   * Normalize a StorylineRealizationService storyline_progression connection.
   *
   * Input shape:
   * {from_room_id, to_room_id, connector_type:'storyline_progression'}
   *
   * @return array<string, mixed>
   *   Canonical connector contract.
   */
  public static function normalizeStorylineProgression(string $dungeon_id, string $from_room_id, string $to_room_id): array {
    return [
      'dungeon_id' => $dungeon_id,
      'from_room_id' => $from_room_id,
      'to_room_id' => $to_room_id,
      'from_hex' => NULL,
      'to_hex' => NULL,
      'kind' => 'hallway',
      'direction' => 'one_way',
      'default_state' => 'open',
      'trap_data' => NULL,
      'lock_data' => NULL,
      'requirements_data' => NULL,
      'description' => NULL,
      'travel_cost' => 0,
      'is_discovered_default' => 1,
    ];
  }

  // ---------------------------------------------------------------------------
  // Internal resolution helpers
  // ---------------------------------------------------------------------------

  private static function resolveDirection(array $raw, string $raw_type): string {
    // Explicit bidirectional flag takes priority.
    if (array_key_exists('bidirectional', $raw)) {
      return empty($raw['bidirectional']) ? 'one_way' : 'bidirectional';
    }

    // Type-based default direction.
    if (isset(self::CONNECTOR_TYPE_TO_DIRECTION[$raw_type])) {
      return self::CONNECTOR_TYPE_TO_DIRECTION[$raw_type];
    }

    // one_way_drop kind is always one-way.
    if ($raw_type === 'one_way_drop') {
      return 'one_way';
    }

    return 'bidirectional';
  }

  private static function resolveDefaultState(array $raw, string $kind): string {
    // Explicit state always wins.
    if (array_key_exists('state', $raw)) {
      $state = strtolower(trim((string) $raw['state']));
      if ($state === '' || !in_array($state, ConnectorDefinitionService::STATES, TRUE)) {
        throw new \InvalidArgumentException(sprintf(
          'Connector generation contract violation: invalid state "%s".',
          (string) $raw['state']
        ));
      }
      return $state;
    }

    // is_locked boolean.
    if (!empty($raw['is_locked'])) {
      return 'locked';
    }

    // is_trapped boolean — state is 'trapped' meaning it's open but a trap fires.
    if (!empty($raw['is_trapped'])) {
      return 'trapped';
    }

    // is_hidden boolean → closed and undiscovered (handled via is_discovered_default).
    if (!empty($raw['is_hidden'])) {
      return 'closed';
    }

    // Collapsed connector defaults to collapsed state.
    if ($kind === 'collapsed') {
      return 'collapsed';
    }

    // Doors start closed by default.
    if (in_array($kind, ['door', 'portcullis', 'hatch', 'secret_door', 'magical_barrier'], TRUE)) {
      return 'closed';
    }

    return 'open';
  }

  private static function resolveTrapData(array $raw, string $kind, string $theme): ?array {
    // Explicit trap_data provided.
    if (!empty($raw['trap_data']) && is_array($raw['trap_data'])) {
      return $raw['trap_data'];
    }

    // is_trapped boolean → synthesize a standard trap.
    if (!empty($raw['is_trapped'])) {
      $dc = self::TRAP_DC_BY_THEME[$theme] ?? self::TRAP_DC_BY_THEME['default'];
      return [
        'trap_id' => 'generated_trap',
        'type' => $kind === 'door' ? 'door_trap' : 'floor_trap',
        'dc' => $dc,
        'damage_dice' => '2d6',
        'triggered' => FALSE,
      ];
    }

    return NULL;
  }

  private static function resolveLockData(array $raw, string $kind, string $theme): ?array {
    // Explicit lock_data provided.
    if (!empty($raw['lock_data']) && is_array($raw['lock_data'])) {
      return $raw['lock_data'];
    }

    // is_locked boolean → synthesize a standard lock.
    if (!empty($raw['is_locked'])) {
      $dc = (self::TRAP_DC_BY_THEME[$theme] ?? self::TRAP_DC_BY_THEME['default']) + 3;
      return [
        'lock_id' => 'generated_lock',
        'type' => 'standard',
        'dc' => $dc,
        'key_item_id' => NULL,
      ];
    }

    return NULL;
  }

  private static function resolveRequirementsData(array $raw, ?array $trap_data, ?array $lock_data): ?array {
    // Explicit requirements override.
    if (!empty($raw['requirements_data']) && is_array($raw['requirements_data'])) {
      return $raw['requirements_data'];
    }

    $requirements = [];

    // Locked doors require either a key or a Thievery check.
    if ($lock_data !== NULL) {
      if (!empty($lock_data['key_item_id'])) {
        $requirements[] = [
          'type' => 'item',
          'item_id' => (string) $lock_data['key_item_id'],
          'consume' => FALSE,
        ];
      }
      else {
        $requirements[] = [
          'type' => 'skill_check',
          'ability' => 'thievery',
          'dc' => (int) ($lock_data['dc'] ?? 15),
        ];
      }
    }

    return empty($requirements) ? NULL : $requirements;
  }

  /**
   * Extract one endpoint hex from raw connector payload.
   *
   * Accepts:
   * - from_hex/to_hex: {q,r}
   * - from/to: {q,r}
   * - from/to: {hex:{q,r}}
   *
   * @return array{q:int,r:int}|null
   *   Parsed endpoint hex or NULL when unavailable.
   */
  private static function extractEndpointHex(array $raw, string $endpoint): ?array {
    $direct = $raw[$endpoint . '_hex'] ?? NULL;
    if (is_array($direct) && isset($direct['q'], $direct['r']) && is_numeric($direct['q']) && is_numeric($direct['r'])) {
      return ['q' => (int) $direct['q'], 'r' => (int) $direct['r']];
    }

    $legacy = $raw[$endpoint] ?? NULL;
    if (is_array($legacy) && isset($legacy['q'], $legacy['r']) && is_numeric($legacy['q']) && is_numeric($legacy['r'])) {
      return ['q' => (int) $legacy['q'], 'r' => (int) $legacy['r']];
    }
    if (
      is_array($legacy)
      && is_array($legacy['hex'] ?? NULL)
      && isset($legacy['hex']['q'], $legacy['hex']['r'])
      && is_numeric($legacy['hex']['q'])
      && is_numeric($legacy['hex']['r'])
    ) {
      return ['q' => (int) $legacy['hex']['q'], 'r' => (int) $legacy['hex']['r']];
    }

    return NULL;
  }

}
