<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\ai_conversation\Service\AIApiService;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Support\H3SpatialHelper;
use Psr\Log\LoggerInterface;

/**
 * Validates state payloads against canonical contracts.
 */
class StateValidationService {

  /**
   * Required canonical room contents buckets.
   *
   * @var array<int, string>
   */
  private const ROOM_CONTENT_REQUIRED_BUCKETS = ['npcs', 'items', 'entities', 'obstacles', 'hazards', 'interactables'];

  /**
   * Contents buckets that must resolve content_id entries in registry.
   *
   * @var array<int, string>
   */
  private const ROOM_CONTENT_REGISTRY_REFERENCE_BUCKETS = ['npcs', 'items', 'entities'];

  /**
   * Allowed room hex object categories.
   *
   * @var array<int, string>
   */
  private const ROOM_HEX_ALLOWED_OBJECT_CATEGORIES = [
    'wall',
    'door',
    'hazard',
    'feature',
    'npc',
    'item',
    'entity',
    'obstacle',
    'interactable',
    'trap',
    'cover',
    'terrain',
    'entry',
    'exit',
  ];

  /**
   * Prompt-derived room ID prefixes that are blocked.
   */
  private const BLOCKED_PROMPT_DERIVED_ROOM_ID_PATTERN = '/^(i-want|hello)-/i';

  /**
   * Axial coordinate neighbor offsets for hex pathing.
   *
   * @var array<int, array{0:int, 1:int}>
   */
  private const ROOM_HEX_NEIGHBOR_OFFSETS = [[1, 0], [-1, 0], [0, 1], [0, -1], [1, -1], [-1, 1]];

  /**
   * H3 resolutions that must retain canonical room ownership.
   *
   * @var array<int, int>
   */
  private const ROOM_OWNERSHIP_REQUIRED_RESOLUTIONS = [14];

  /**
   * Active generation H3 resolution for current validator hard gates.
   */
  private const ACTIVE_GENERATION_RESOLUTION = 14;

  /**
   * Minimum required spacing between room anchors in active res14 H3 hexes.
   */
  private const MIN_RES14_ANCHOR_DISTANCE_HEXES = 200;

  /**
   * Coordinate-frame span threshold (degrees) allowed per dungeon/resolution scope.
   */
  private const MAX_COORDINATE_FRAME_SPAN_DEGREES = 1.0;

  /**
   * Required sparse metadata normalization mode.
   */
  private const REQUIRED_SPARSE_NORMALIZATION = 'global_non_overlapping_axial';

  /**
   * Canonical H3 index regex for active validation.
   */
  private const CANONICAL_H3_INDEX_PATTERN = '/^[0-9a-f]{15,16}$/';

  /**
   * Legacy pseudo H3 index prefixes that are disallowed.
   */
  private const DISALLOWED_PSEUDO_H3_PREFIXES = ['r14a_', 'r14c_'];

  /**
   * Room ownership marker for non-room-mapped resolutions.
   */
  private const ROOM_OWNERSHIP_NOT_APPLICABLE = 'NA';

  /**
   * Required layout field for explicit room-to-room linkage definitions.
   */
  private const ROOM_LAYOUT_EXIT_LINK_FIELD = 'exits';

  /**
   * Toggle for LLM-driven description/metadata alignment checks.
   */
  private const ENABLE_LLM_DESCRIPTION_METADATA_ALIGNMENT_VALIDATION = FALSE;

  private LoggerInterface $logger;
  private ?Connection $database;
  private ?AIApiService $aiApiService;
  private string $schemaBasePath;
  private ?array $contractRegistry = NULL;

  /**
   * Constructor.
   */
  public function __construct(
    LoggerChannelFactoryInterface $logger_factory,
    ?Connection $database = NULL,
    ?AIApiService $ai_api_service = NULL
  ) {
    $this->logger = $logger_factory->get('dungeoncrawler');
    $this->database = $database;
    $this->aiApiService = $ai_api_service;
    $this->schemaBasePath = dirname(__DIR__) . '/../config/schemas';
  }

  /**
   * Validate campaign state against schema.
   */
  public function validateCampaignState(array $state): array {
    return $this->validateAgainstContract($state, 'campaign_state');
  }

  /**
   * Validate dungeon state against schema.
   */
  public function validateDungeonState(array $state): array {
    return $this->validateAgainstContractFragment(
      $this->normalizeDungeonState($state),
      'dungeon_state',
      ['properties', 'level_state']
    );
  }

  /**
   * Validate room state against schema.
   */
  public function validateRoomState(array $state): array {
    return $this->validateAgainstContractFragment(
      $this->normalizeRoomState($state),
      'room_state',
      ['properties', 'state']
    );
  }

  /**
   * Normalize compact dungeon runtime state into the canonical fragment shape.
   */
  public function normalizeDungeonState(array $state): array {
    $normalized = [];

    $bool_mappings = [
      'is_fully_generated' => ['is_fully_generated', 'isFullyGenerated'],
      'boss_defeated' => ['boss_defeated', 'bossDefeated'],
    ];
    foreach ($bool_mappings as $target => $sources) {
      foreach ($sources as $source) {
        if (array_key_exists($source, $state)) {
          $normalized[$target] = (bool) $state[$source];
          break;
        }
      }
    }

    $int_mappings = [
      'rooms_generated' => ['rooms_generated', 'roomsGenerated'],
      'rooms_explored' => ['rooms_explored', 'roomsExplored'],
      'times_visited' => ['times_visited', 'timesVisited'],
    ];
    foreach ($int_mappings as $target => $sources) {
      foreach ($sources as $source) {
        if (array_key_exists($source, $state)) {
          $normalized[$target] = (int) $state[$source];
          break;
        }
      }
    }

    if (array_key_exists('completion_percent', $state) || array_key_exists('completionPercent', $state)) {
      $normalized['completion_percent'] = (float) ($state['completion_percent'] ?? $state['completionPercent']);
    }

    foreach ([
      'first_entered_at' => ['first_entered_at', 'firstEnteredAt'],
      'last_visited_at' => ['last_visited_at', 'lastVisitedAt'],
    ] as $target => $sources) {
      foreach ($sources as $source) {
        if (array_key_exists($source, $state)) {
          $value = $state[$source];
          $normalized[$target] = $value === NULL ? NULL : trim((string) $value);
          break;
        }
      }
    }

    return $normalized;
  }

  /**
   * Normalize compact room runtime state into the canonical fragment shape.
   */
  public function normalizeRoomState(array $state): array {
    $normalized = [];

    if (array_key_exists('explored', $state)) {
      $normalized['explored'] = (bool) $state['explored'];
    }

    foreach ([
      'explored_at' => ['explored_at', 'exploredAt'],
      'explored_by_party' => ['explored_by_party', 'exploredByParty'],
    ] as $target => $sources) {
      foreach ($sources as $source) {
        if (array_key_exists($source, $state)) {
          $value = $state[$source];
          $normalized[$target] = $value === NULL ? NULL : trim((string) $value);
          break;
        }
      }
    }

    foreach ([
      'cleared' => ['cleared', 'isCleared', 'is_cleared'],
      'looted' => ['looted'],
      'traps_disarmed' => ['traps_disarmed', 'trapsDisarmed'],
    ] as $target => $sources) {
      foreach ($sources as $source) {
        if (array_key_exists($source, $state)) {
          $normalized[$target] = (bool) $state[$source];
          break;
        }
      }
    }

    if (array_key_exists('visibility', $state)) {
      $normalized['visibility'] = trim((string) $state['visibility']);
    }

    if (array_key_exists('notes', $state) && is_array($state['notes'])) {
      $normalized['notes'] = $state['notes'];
    }

    return $normalized;
  }

  /**
   * Validate NPC definition against schema.
   */
  public function validateNpcDefinition(array $npc): array {
    $schema_path = dirname(__DIR__) . '/../../../../../docs/dungeoncrawler/schemas/pf2e-npc-definition.schema.json';
    return $this->validateAgainstSchemaFile($npc, $schema_path);
  }

  /**
   * Validate a generated NPC sheet against the canonical contract schema.
   */
  public function validateNpcSheet(array $sheet): array {
    return $this->validateAgainstContract($sheet, 'npc_sheet');
  }

  /**
   * Validate a canonical item definition against the contract schema.
   */
  public function validateItemDefinition(array $item): array {
    $errors = $this->validateCanonicalItemDefinition($item);
    $item_id = trim((string) ($item['item_id'] ?? $item['content_id'] ?? ''));
    $item_type = strtolower(trim((string) ($item['item_type'] ?? $item['type'] ?? '')));

    $errors = array_merge($errors, $this->validateItemDefinitionAgainstDatabase($item, $item_id, $item_type));

    return ['valid' => $errors === [], 'errors' => $errors];
  }

  /**
   * Validate item payload against canonical structural rules only.
   *
   * This excludes DB-authority checks and is intended for pre-persist
   * generation workflows that are still assembling a canonical contract.
   */
  public function validateItemDefinitionStructure(array $item): array {
    $errors = $this->validateCanonicalItemDefinition($item);
    return ['valid' => $errors === [], 'errors' => $errors];
  }

  /**
   * Validate canonical template/library item contracts from the DB registry.
   *
   * Campaign/runtime instances are intentionally excluded from this report.
   *
   * @return array<string, mixed>
   *   Validation report with aggregate summary and per-item diagnostics.
   */
  public function validateCanonicalItemLibraryContracts(): array {
    $report = [
      'valid' => FALSE,
      'errors' => [],
      'summary' => [
        'total_items' => 0,
        'valid_items' => 0,
        'invalid_items' => 0,
      ],
      'items' => [],
    ];

    if ($this->database === NULL) {
      $report['errors'][] = 'Canonical item validation requires database access.';
      return $report;
    }

    $schema = $this->database->schema();
    if (!$schema->tableExists('dungeoncrawler_content_registry')) {
      $report['errors'][] = 'Canonical content registry table dungeoncrawler_content_registry is unavailable.';
      return $report;
    }

    $rows = $this->database->select('dungeoncrawler_content_registry', 'r')
      ->fields('r', ['content_id', 'name', 'level', 'rarity', 'source_file', 'schema_data'])
      ->condition('content_type', 'item')
      ->condition('source_file', 'items/%', 'LIKE')
      ->orderBy('content_id', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    if (!is_array($rows) || $rows === []) {
      $report['errors'][] = 'Canonical content registry contains no item records.';
      return $report;
    }

    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }

      $content_id = trim((string) ($row['content_id'] ?? ''));
      $schema_data_raw = (string) ($row['schema_data'] ?? '');
      $item_errors = [];
      $schema_data = json_decode($schema_data_raw, TRUE);

      if (!is_array($schema_data)) {
        $item_errors[] = 'schema_data must be a valid JSON object.';
        $schema_data = [];
      }

      $schema_item_id = trim((string) ($schema_data['item_id'] ?? $schema_data['content_id'] ?? ''));
      if ($schema_item_id !== '' && $content_id !== '' && $schema_item_id !== $content_id) {
        $item_errors[] = "schema_data item_id/content_id '{$schema_item_id}' must match registry content_id '{$content_id}'.";
      }

      if ($schema_data !== []) {
        $validation = $this->validateItemDefinition($schema_data);
        foreach (array_values(array_filter(array_map('strval', (array) ($validation['errors'] ?? [])))) as $validation_error) {
          $item_errors[] = $validation_error;
        }
      }

      $item_errors = array_values(array_unique($item_errors));
      $item_valid = $item_errors === [];
      $report['items'][] = [
        'content_id' => $content_id,
        'item_id' => $schema_item_id,
        'name' => trim((string) ($schema_data['name'] ?? $row['name'] ?? '')),
        'item_type' => strtolower(trim((string) ($schema_data['item_type'] ?? $schema_data['type'] ?? ''))),
        'level' => $schema_data['level'] ?? $row['level'] ?? NULL,
        'rarity' => strtolower(trim((string) ($schema_data['rarity'] ?? $row['rarity'] ?? ''))),
        'source_file' => trim((string) ($row['source_file'] ?? '')),
        'contract' => $schema_data,
        'valid' => $item_valid,
        'errors' => $item_errors,
      ];
    }

    $report['summary']['total_items'] = count($report['items']);
    $report['summary']['valid_items'] = count(array_filter($report['items'], static fn(array $item): bool => !empty($item['valid'])));
    $report['summary']['invalid_items'] = $report['summary']['total_items'] - $report['summary']['valid_items'];
    $report['valid'] = $report['errors'] === [] && $report['summary']['invalid_items'] === 0;

    return $report;
  }

  /**
   * Validate canonical actor contracts from dc_campaign_characters.
   *
   * @return array<string, mixed>
   *   Validation report with aggregate summary and per-actor diagnostics.
   */
  public function validateCanonicalActorLibraryContracts(): array {
    $report = [
      'valid' => FALSE,
      'errors' => [],
      'validation_checks' => [
        ['id' => 'actor_id_positive', 'label' => 'actor id must be a positive integer.'],
        ['id' => 'name_required', 'label' => 'name is required.'],
        ['id' => 'level_range', 'label' => 'level must be between 1 and 25.'],
        ['id' => 'instance_id_required', 'label' => 'instance_id is required.'],
        ['id' => 'type_required', 'label' => 'type is required.'],
        ['id' => 'lifecycle_state_required', 'label' => 'lifecycle_state is required.'],
        ['id' => 'location_type_required', 'label' => 'location_type is required.'],
        ['id' => 'location_ref_required_by_type', 'label' => 'location_ref is required for location_type values outside global/roster.'],
        ['id' => 'status_allowed', 'label' => 'status must be one of: -1, 0, 1, 2.'],
        ['id' => 'pc_source_character_required', 'label' => 'pc actor rows must define source_character_id when campaign_id is non-zero.'],
        ['id' => 'character_data_present', 'label' => 'character_data contract is required.'],
        ['id' => 'character_data_json_contract', 'label' => 'character_data must decode to a non-empty JSON object/array.'],
      ],
      'summary' => [
        'total_items' => 0,
        'valid_items' => 0,
        'invalid_items' => 0,
      ],
      'items' => [],
    ];

    if ($this->database === NULL) {
      $report['errors'][] = 'Canonical actor validation requires database access.';
      return $report;
    }

    $schema = $this->database->schema();
    if (!$schema->tableExists('dc_campaign_characters')) {
      $report['errors'][] = 'Canonical actor table dc_campaign_characters is unavailable.';
      return $report;
    }

    $rows = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', [
        'id',
        'campaign_id',
        'character_id',
        'source_character_id',
        'name',
        'level',
        'instance_id',
        'type',
        'lifecycle_state',
        'location_type',
        'location_ref',
        'status',
        'character_data',
      ])
      ->orderBy('id', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    if (!is_array($rows) || $rows === []) {
      $report['errors'][] = 'Canonical actor store contains no records.';
      return $report;
    }

    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }

      $actor_id = (int) ($row['id'] ?? 0);
      $campaign_id = (int) ($row['campaign_id'] ?? 0);
      $source_character_id = isset($row['source_character_id']) && is_numeric($row['source_character_id'])
        ? (int) $row['source_character_id']
        : NULL;
      $name = trim((string) ($row['name'] ?? ''));
      $level = (int) ($row['level'] ?? 0);
      $instance_id = trim((string) ($row['instance_id'] ?? ''));
      $actor_type = strtolower(trim((string) ($row['type'] ?? '')));
      $lifecycle_state = strtolower(trim((string) ($row['lifecycle_state'] ?? '')));
      $location_type = strtolower(trim((string) ($row['location_type'] ?? '')));
      $location_ref = trim((string) ($row['location_ref'] ?? ''));
      $status = isset($row['status']) && is_numeric($row['status']) ? (int) $row['status'] : NULL;
      $actor_errors = [];
      $location_ref_optional_types = ['global', 'roster'];

      $character_data_raw = trim((string) ($row['character_data'] ?? ''));
      $character_data = [];
      $character_data_decoded_valid = FALSE;
      if ($character_data_raw !== '') {
        $decoded = json_decode($character_data_raw, TRUE);
        if (is_array($decoded) && $decoded !== []) {
          $character_data = $decoded;
          $character_data_decoded_valid = TRUE;
        }
      }

      $actor_checks = [
        [
          'id' => 'actor_id_positive',
          'label' => 'actor id must be a positive integer.',
          'passed' => $actor_id > 0,
          'error' => 'actor id must be a positive integer.',
        ],
        [
          'id' => 'name_required',
          'label' => 'name is required.',
          'passed' => $name !== '',
          'error' => 'name is required.',
        ],
        [
          'id' => 'level_range',
          'label' => 'level must be between 1 and 25.',
          'passed' => $level >= 1 && $level <= 25,
          'error' => 'level must be between 1 and 25.',
        ],
        [
          'id' => 'instance_id_required',
          'label' => 'instance_id is required.',
          'passed' => $instance_id !== '',
          'error' => 'instance_id is required.',
        ],
        [
          'id' => 'type_required',
          'label' => 'type is required.',
          'passed' => $actor_type !== '',
          'error' => 'type is required.',
        ],
        [
          'id' => 'lifecycle_state_required',
          'label' => 'lifecycle_state is required.',
          'passed' => $lifecycle_state !== '',
          'error' => 'lifecycle_state is required.',
        ],
        [
          'id' => 'location_type_required',
          'label' => 'location_type is required.',
          'passed' => $location_type !== '',
          'error' => 'location_type is required.',
        ],
        [
          'id' => 'location_ref_required_by_type',
          'label' => 'location_ref is required for location_type values outside global/roster.',
          'passed' => $location_ref !== '' || in_array($location_type, $location_ref_optional_types, TRUE),
          'error' => 'location_ref is required for location_type values outside global/roster.',
        ],
        [
          'id' => 'status_allowed',
          'label' => 'status must be one of: -1, 0, 1, 2.',
          'passed' => $status !== NULL && in_array($status, [-1, 0, 1, 2], TRUE),
          'error' => 'status must be one of: -1, 0, 1, 2.',
        ],
        [
          'id' => 'pc_source_character_required',
          'label' => 'pc actor rows must define source_character_id when campaign_id is non-zero.',
          'passed' => !($campaign_id > 0 && $actor_type === 'pc' && ($source_character_id === NULL || $source_character_id <= 0)),
          'error' => 'pc actor rows must define source_character_id when campaign_id is non-zero.',
        ],
        [
          'id' => 'character_data_present',
          'label' => 'character_data contract is required.',
          'passed' => $character_data_raw !== '',
          'error' => 'character_data contract is required.',
        ],
        [
          'id' => 'character_data_json_contract',
          'label' => 'character_data must decode to a non-empty JSON object/array.',
          'passed' => $character_data_raw !== '' ? $character_data_decoded_valid : TRUE,
          'error' => 'character_data must decode to a non-empty JSON object/array.',
        ],
      ];
      foreach ($actor_checks as $check) {
        if (empty($check['passed'])) {
          $actor_errors[] = (string) ($check['error'] ?? $check['label'] ?? 'Actor contract check failed.');
        }
      }
      $actor_errors = array_values(array_unique($actor_errors));
      $actor_valid = $actor_errors === [];

      $report['items'][] = [
        'content_id' => (string) $actor_id,
        'item_id' => $instance_id,
        'name' => $name,
        'item_type' => $actor_type,
        'level' => $level,
        'rarity' => $lifecycle_state,
        'source_file' => 'campaign:' . $campaign_id,
        'contract' => [
          'actor' => [
            'id' => $actor_id,
            'campaign_id' => $campaign_id,
            'character_id' => (int) ($row['character_id'] ?? 0),
            'source_character_id' => $source_character_id,
            'instance_id' => $instance_id,
            'name' => $name,
            'type' => $actor_type,
            'level' => $level,
            'status' => $status,
            'lifecycle_state' => $lifecycle_state,
            'location_type' => $location_type,
            'location_ref' => $location_ref,
          ],
          'character_data' => $character_data,
        ],
        'checks' => array_map(static function (array $check): array {
          return [
            'id' => (string) ($check['id'] ?? ''),
            'label' => (string) ($check['label'] ?? ''),
            'passed' => !empty($check['passed']),
          ];
        }, $actor_checks),
        'valid' => $actor_valid,
        'errors' => $actor_errors,
      ];
    }

    $report['summary']['total_items'] = count($report['items']);
    $report['summary']['valid_items'] = count(array_filter($report['items'], static fn(array $item): bool => !empty($item['valid'])));
    $report['summary']['invalid_items'] = $report['summary']['total_items'] - $report['summary']['valid_items'];
    $report['valid'] = $report['errors'] === [] && $report['summary']['invalid_items'] === 0;

    return $report;
  }

  /**
   * Validate canonical room contracts from dungeoncrawler_content_rooms.
   *
   * @return array<string, mixed>
   *   Validation report with aggregate summary and per-room diagnostics.
   */
  public function validateCanonicalRoomLibraryContracts(): array {
    $report = [
      'valid' => FALSE,
      'errors' => [],
      'summary' => [
        'total_items' => 0,
        'valid_items' => 0,
        'invalid_items' => 0,
      ],
      'items' => [],
    ];

    if ($this->database === NULL) {
      $report['errors'][] = 'Canonical room validation requires database access.';
      return $report;
    }

    $schema = $this->database->schema();
    if (!$schema->tableExists('dungeoncrawler_content_rooms')) {
      $report['errors'][] = 'Canonical room table dungeoncrawler_content_rooms is unavailable.';
      return $report;
    }

    $rows = $this->database->select('dungeoncrawler_content_rooms', 'r')
      ->fields('r', [
        'room_id',
        'name',
        'description',
        'environment_tags',
        'layout_data',
        'contents_data',
        'source_room_id',
      ])
      ->orderBy('room_id', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    if (!is_array($rows) || $rows === []) {
      $report['errors'][] = 'Canonical room table contains no records.';
      return $report;
    }

    $canonical_room_ids = [];
    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }
      $candidate_room_id = trim((string) ($row['room_id'] ?? ''));
      if ($candidate_room_id !== '') {
        $canonical_room_ids[$candidate_room_id] = TRUE;
      }
    }

    if (!$schema->tableExists('dungeoncrawler_content_registry')) {
      $report['errors'][] = 'Canonical content registry table dungeoncrawler_content_registry is unavailable.';
      return $report;
    }

    $registry_rows = $this->database->select('dungeoncrawler_content_registry', 'r')
      ->fields('r', ['content_id'])
      ->orderBy('content_id', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $registry_content_id_map = [];
    if (is_array($registry_rows)) {
      foreach ($registry_rows as $registry_row) {
        if (!is_array($registry_row)) {
          continue;
        }
        $registry_content_id = trim((string) ($registry_row['content_id'] ?? ''));
        if ($registry_content_id !== '') {
          $registry_content_id_map[$registry_content_id] = TRUE;
        }
      }
    }

    $room_exit_targets_by_room = [];
    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }

      $room_id = trim((string) ($row['room_id'] ?? ''));
      $name = trim((string) ($row['name'] ?? ''));
      $description = trim((string) ($row['description'] ?? ''));
      $source_room_id = trim((string) ($row['source_room_id'] ?? ''));
      $room_errors = [];

      if ($room_id === '') {
        $room_errors[] = 'room_id is required.';
      }
      elseif (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $room_id)) {
        $room_errors[] = "room_id '{$room_id}' does not match canonical id pattern.";
      }
      elseif ($this->isBlockedPromptDerivedRoomId($room_id)) {
        $room_errors[] = "room_id '{$room_id}' uses a blocked prompt-derived prefix.";
      }

      if ($name === '') {
        $room_errors[] = 'name is required.';
      }
      if ($description === '') {
        $room_errors[] = 'description is required.';
      }

      if ($source_room_id !== '') {
        if ($this->isBlockedPromptDerivedRoomId($source_room_id)) {
          $room_errors[] = "source_room_id '{$source_room_id}' uses a blocked prompt-derived prefix.";
        }
        if (!isset($canonical_room_ids[$source_room_id])) {
          $room_errors[] = "source_room_id '{$source_room_id}' does not resolve to a canonical room_id.";
        }
      }

      $layout_data = json_decode((string) ($row['layout_data'] ?? ''), TRUE);
      if (!is_array($layout_data) || $layout_data === []) {
        $room_errors[] = 'layout_data contract is required.';
        $layout_data = [];
      }
      else {
        $room_exit_targets = [];
        $layout_exit_links = $layout_data[self::ROOM_LAYOUT_EXIT_LINK_FIELD] ?? NULL;
        if (is_array($layout_exit_links)) {
          foreach ($layout_exit_links as $layout_exit_link) {
            if (!is_array($layout_exit_link)) {
              continue;
            }
            $target_room_id = trim((string) ($layout_exit_link['target_room_id'] ?? ''));
            if ($target_room_id !== '') {
              $room_exit_targets[$target_room_id] = TRUE;
            }
          }
        }
        if ($room_id !== '') {
          $room_exit_targets_by_room[$room_id] = array_keys($room_exit_targets);
        }

        $room_errors = array_merge($room_errors, $this->validateCanonicalRoomLayoutContract($layout_data));
        $room_errors = array_merge($room_errors, $this->validateCanonicalRoomExitLinkContract($layout_data, $room_id, $canonical_room_ids));
      }

      $contents_data = json_decode((string) ($row['contents_data'] ?? ''), TRUE);
      if (!is_array($contents_data) || $contents_data === []) {
        $room_errors[] = 'contents_data contract is required.';
        $contents_data = [];
      }
      else {
        $room_errors = array_merge($room_errors, $this->validateCanonicalRoomContentsContract($contents_data, $registry_content_id_map));
      }

      $environment_tags = json_decode((string) ($row['environment_tags'] ?? ''), TRUE);
      if (!is_array($environment_tags) || $environment_tags === []) {
        $room_errors[] = 'environment_tags must be a non-empty array.';
        $environment_tags = [];
      }
      else {
        $normalized_tags = [];
        $seen_tags = [];
        foreach ($environment_tags as $index => $tag) {
          if (!is_string($tag) || trim($tag) === '') {
            $room_errors[] = "environment_tags[{$index}] must be a non-empty string.";
            continue;
          }
          $normalized_tag = trim($tag);
          $dedupe_key = strtolower($normalized_tag);
          if (isset($seen_tags[$dedupe_key])) {
            $room_errors[] = "environment_tags contains duplicate value '{$normalized_tag}'.";
            continue;
          }
          $seen_tags[$dedupe_key] = TRUE;
          $normalized_tags[] = $normalized_tag;
        }
        $environment_tags = $normalized_tags;
        if ($environment_tags === []) {
          $room_errors[] = 'environment_tags must include at least one valid tag.';
        }
      }

      if (
        self::ENABLE_LLM_DESCRIPTION_METADATA_ALIGNMENT_VALIDATION
        && $room_id !== ''
        && $description !== ''
        && $layout_data !== []
        && $contents_data !== []
        && $environment_tags !== []
      ) {
        $room_errors = array_merge(
          $room_errors,
          $this->validateCanonicalRoomDescriptionMetadataAlignmentWithLlm(
            $room_id,
            $name,
            $description,
            $layout_data,
            $contents_data,
            $environment_tags
          )
        );
      }

      $room_errors = array_values(array_unique($room_errors));
      $room_valid = $room_errors === [];

      $report['items'][] = [
        'content_id' => $room_id,
        'item_id' => $source_room_id !== '' ? $source_room_id : $room_id,
        'name' => $name,
        'item_type' => 'room_template',
        'level' => NULL,
        'rarity' => '',
        'source_file' => 'dungeoncrawler_content_rooms',
        'contract' => [
          'room' => [
            'room_id' => $room_id,
            'name' => $name,
            'description' => $description,
            'source_room_id' => $source_room_id,
            'environment_tags' => $environment_tags,
          ],
          'layout_data' => $layout_data,
          'contents_data' => $contents_data,
        ],
        'valid' => $room_valid,
        'errors' => $room_errors,
      ];
    }

    $report['errors'] = array_merge(
      $report['errors'],
      $this->validateCanonicalDungeonCrossDungeonLinkage($canonical_room_ids, $room_exit_targets_by_room)
    );
    $report['hex_validation'] = $this->validateCanonicalHexReferentialContracts($canonical_room_ids);
    $report['errors'] = array_merge($report['errors'], (array) ($report['hex_validation']['errors'] ?? []));

    $report['summary']['total_items'] = count($report['items']);
    $report['summary']['valid_items'] = count(array_filter($report['items'], static fn(array $item): bool => !empty($item['valid'])));
    $report['summary']['invalid_items'] = $report['summary']['total_items'] - $report['summary']['valid_items'];
    $report['valid'] = $report['errors'] === [] && $report['summary']['invalid_items'] === 0;

    return $report;
  }

  /**
   * Validate room description alignment with canonical metadata via local LLM.
   *
   * @param string $room_id
   *   Canonical room id.
   * @param string $name
   *   Canonical room name.
   * @param string $description
   *   Canonical room description.
   * @param array<string, mixed> $layout_data
   *   Canonical layout payload.
   * @param array<string, mixed> $contents_data
   *   Canonical contents payload.
   * @param array<int, string> $environment_tags
   *   Canonical environment tags.
   *
   * @return array<int, string>
   *   Validation errors.
   */
  private function validateCanonicalRoomDescriptionMetadataAlignmentWithLlm(
    string $room_id,
    string $name,
    string $description,
    array $layout_data,
    array $contents_data,
    array $environment_tags
  ): array {
    if ($this->aiApiService === NULL) {
      return ['description-metadata alignment validation requires ai_conversation.ai_api_service.'];
    }

    $layout_hexes = is_array($layout_data['hexes'] ?? NULL) ? $layout_data['hexes'] : [];
    $entry_points = is_array($layout_data['entry_points'] ?? NULL) ? $layout_data['entry_points'] : [];
    $exit_points = is_array($layout_data['exit_points'] ?? NULL) ? $layout_data['exit_points'] : [];
    $exit_links = is_array($layout_data[self::ROOM_LAYOUT_EXIT_LINK_FIELD] ?? NULL)
      ? $layout_data[self::ROOM_LAYOUT_EXIT_LINK_FIELD]
      : [];

    $content_buckets = [];
    foreach ($contents_data as $bucket => $entries) {
      if (!is_array($entries)) {
        continue;
      }
      $sample_references = [];
      foreach ($entries as $entry) {
        if (!is_array($entry)) {
          continue;
        }
        $content_id = trim((string) ($entry['content_id'] ?? ''));
        if ($content_id !== '') {
          $sample_references[] = $content_id;
        }
        if (count($sample_references) >= 8) {
          break;
        }
      }
      $content_buckets[(string) $bucket] = [
        'count' => count($entries),
        'sample_content_ids' => $sample_references,
      ];
    }

    $alignment_payload = [
      'room_id' => $room_id,
      'name' => $name,
      'description' => $description,
      'environment_tags' => array_values($environment_tags),
      'layout' => [
        'shape' => trim((string) ($layout_data['shape'] ?? '')),
        'hex_count' => count($layout_hexes),
        'entry_point_count' => count($entry_points),
        'exit_point_count' => count($exit_points),
        'exit_link_count' => count($exit_links),
      ],
      'contents' => $content_buckets,
    ];

    $payload_json = json_encode($alignment_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!is_string($payload_json) || $payload_json === '') {
      return ["Room '{$room_id}' failed to encode metadata alignment payload for LLM validation."];
    }

    $prompt = "Validate whether this canonical room description is aligned with its metadata.\n";
    $prompt .= "Return ONLY JSON with this exact shape:\n";
    $prompt .= "{\"aligned\": boolean, \"violations\": [string, ...], \"evidence\": [string, ...]}\n";
    $prompt .= "Validation rules:\n";
    $prompt .= "- Compare description against shape, hex_count, entry/exit counts, environment_tags, and content buckets.\n";
    $prompt .= "- Flag mismatches in scope/scale (e.g., tiny interior vs city-scale description).\n";
    $prompt .= "- Flag thematic mismatches between described scene and tags/contents.\n";
    $prompt .= "- If uncertain, mark aligned=false and explain ambiguity in violations.\n";
    $prompt .= "Room metadata:\n";
    $prompt .= $payload_json;

    $cache_fingerprint = hash('sha256', json_encode($alignment_payload, JSON_UNESCAPED_SLASHES) ?: $room_id);
    $result = $this->aiApiService->invokeModelDirect(
      $prompt,
      'dungeoncrawler_content',
      'room_description_metadata_alignment',
      [
        'room_id' => $room_id,
        'fingerprint' => $cache_fingerprint,
      ],
      [
        'provider' => 'deepseek',
        'system_prompt' => 'You are a strict canonical data validator. Return only JSON. Do not include markdown.',
        'max_tokens' => 450,
        'skip_cache' => TRUE,
      ]
    );

    if (empty($result['success'])) {
      $error_message = trim((string) ($result['error'] ?? 'unknown LLM error'));
      return ["Room '{$room_id}' description-metadata alignment validation failed: {$error_message}."];
    }

    $response = trim((string) ($result['response'] ?? ''));
    if ($response === '') {
      return ["Room '{$room_id}' description-metadata alignment returned an empty response."];
    }
    $response = preg_replace('/^```json\s*|\s*```$/', '', $response) ?? $response;

    $parsed = json_decode($response, TRUE);
    if (!is_array($parsed)) {
      return ["Room '{$room_id}' description-metadata alignment returned invalid JSON."];
    }

    if (!array_key_exists('aligned', $parsed) || !is_bool($parsed['aligned'])) {
      return ["Room '{$room_id}' description-metadata alignment JSON is missing boolean field 'aligned'."];
    }

    $violations = [];
    $raw_violations = $parsed['violations'] ?? [];
    if (!is_array($raw_violations)) {
      return ["Room '{$room_id}' description-metadata alignment JSON field 'violations' must be an array."];
    }
    foreach ($raw_violations as $violation) {
      $text = trim((string) $violation);
      if ($text !== '') {
        $violations[] = $text;
      }
    }

    if ($parsed['aligned'] === TRUE && $violations !== []) {
      return ["Room '{$room_id}' description-metadata alignment response is inconsistent: aligned=true with non-empty violations."];
    }

    if ($parsed['aligned'] === FALSE && $violations === []) {
      return ["Room '{$room_id}' description-metadata alignment failed without explicit violations."];
    }

    $errors = [];
    if ($parsed['aligned'] === FALSE) {
      foreach ($violations as $violation) {
        $errors[] = "LLM description-metadata misalignment for room '{$room_id}': {$violation}";
      }
    }

    return $errors;
  }

  /**
   * Validate sparse canonical hex referential contracts.
   *
   * @return array<string, mixed>
   *   Validation report with aggregate summary and per-collision diagnostics.
   */
  public function validateCanonicalHexLibraryContracts(): array {
    $report = [
      'valid' => FALSE,
      'errors' => [],
      'summary' => [
        'total_anchors' => 0,
        'total_cells' => 0,
        'cells_with_h3_index' => 0,
        'cells_without_h3_index' => 0,
        'cells_with_vertical_metrics' => 0,
        'cells_missing_vertical_metrics' => 0,
        'collision_count' => 0,
        'hex_graph_nodes' => 0,
        'hex_graph_edges' => 0,
        'res14_rooms_total' => 0,
        'res14_rooms_passing' => 0,
        'res14_rooms_failing' => 0,
        'res14_nodes' => 0,
        'res14_edges' => 0,
      ],
      'items' => [],
    ];

    if ($this->database === NULL) {
      $report['errors'][] = 'Canonical hex validation requires database access.';
      return $report;
    }

    $schema = $this->database->schema();
    if (!$schema->tableExists('dungeoncrawler_content_rooms')) {
      $report['errors'][] = 'Canonical room table dungeoncrawler_content_rooms is unavailable.';
      return $report;
    }

    $room_rows = $this->database->select('dungeoncrawler_content_rooms', 'r')
      ->fields('r', ['room_id'])
      ->orderBy('room_id', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    $canonical_room_ids = [];
    foreach ((array) $room_rows as $room_row) {
      if (!is_array($room_row)) {
        continue;
      }
      $room_id = trim((string) ($room_row['room_id'] ?? ''));
      if ($room_id !== '') {
        $canonical_room_ids[$room_id] = TRUE;
      }
    }

    if ($canonical_room_ids === []) {
      $report['errors'][] = 'Canonical room table contains no room_id records for hex validation.';
      return $report;
    }

    $hex_validation = $this->validateCanonicalHexReferentialContracts($canonical_room_ids);
    $report['errors'] = (array) ($hex_validation['errors'] ?? []);
    $report['summary'] = is_array($hex_validation['summary'] ?? NULL) ? $hex_validation['summary'] : $report['summary'];
    $report['items'] = array_values(array_filter((array) ($hex_validation['items'] ?? []), 'is_array'));
    $report['valid'] = $report['errors'] === [];

    return $report;
  }

  /**
   * Validate canonical room layout contract shape and playability.
   *
   * @param array<string, mixed> $layout_data
   *   Room layout payload.
   *
   * @return array<int, string>
   *   Validation errors.
   */
  private function validateCanonicalRoomLayoutContract(array $layout_data): array {
    $errors = [];
    $shape = trim((string) ($layout_data['shape'] ?? ''));
    if ($shape === '') {
      $errors[] = 'layout_data.shape must be a non-empty string.';
    }

    $hexes = $layout_data['hexes'] ?? NULL;
    if (!is_array($hexes) || $hexes === []) {
      $errors[] = 'layout_data.hexes must define at least one hex.';
      return $errors;
    }
    if (count($hexes) < 4) {
      $errors[] = 'layout_data.hexes must define at least four hexes per room.';
    }

    if ($this->isPlaceholderRoomHexLayout($hexes)) {
      $errors[] = 'layout_data.hexes matches the placeholder two-hex template (0,0 + 1,0); define a real room footprint.';
    }

    $entry_points = $layout_data['entry_points'] ?? NULL;
    if (!is_array($entry_points) || $entry_points === []) {
      $errors[] = 'layout_data.entry_points must define at least one entry point.';
      $entry_points = [];
    }

    $exit_points = $layout_data['exit_points'] ?? NULL;
    if (!is_array($exit_points) || $exit_points === []) {
      $errors[] = 'layout_data.exit_points must define at least one exit point.';
      $exit_points = [];
    }

    $hex_coordinate_map = [];
    $traversable_hexes = [];
    $entry_hex_flags = [];
    foreach ($hexes as $index => $hex) {
      if (!is_array($hex)) {
        $errors[] = "layout_data.hexes[{$index}] must be an object.";
        continue;
      }

      $q = $hex['q'] ?? NULL;
      $r = $hex['r'] ?? NULL;
      if (!is_int($q) || !is_int($r)) {
        $errors[] = "layout_data.hexes[{$index}] must include integer q/r coordinates.";
        continue;
      }
      $coord_key = $q . ':' . $r;
      if (isset($hex_coordinate_map[$coord_key])) {
        $errors[] = "layout_data.hexes contains duplicate coordinate '{$coord_key}'.";
      }
      $hex_coordinate_map[$coord_key] = ['q' => $q, 'r' => $r];

      if (!array_key_exists('terrain_type', $hex) || !is_string($hex['terrain_type']) || trim($hex['terrain_type']) === '') {
        $errors[] = "layout_data.hexes[{$index}].terrain_type must be a non-empty string.";
      }
      if (!array_key_exists('lighting', $hex) || !is_string($hex['lighting']) || trim($hex['lighting']) === '') {
        $errors[] = "layout_data.hexes[{$index}].lighting must be a non-empty string.";
      }
      if (!array_key_exists('is_discovered', $hex) || !is_bool($hex['is_discovered'])) {
        $errors[] = "layout_data.hexes[{$index}].is_discovered must be a boolean.";
      }
      if (!array_key_exists('is_visible', $hex) || !is_bool($hex['is_visible'])) {
        $errors[] = "layout_data.hexes[{$index}].is_visible must be a boolean.";
      }
      if (!array_key_exists('is_entry', $hex) || !is_bool($hex['is_entry'])) {
        $errors[] = "layout_data.hexes[{$index}].is_entry must be a boolean.";
      }
      if (!array_key_exists('elevation_ft', $hex) || (!is_int($hex['elevation_ft']) && !is_float($hex['elevation_ft']))) {
        $errors[] = "layout_data.hexes[{$index}].elevation_ft must be numeric.";
      }

      $objects = $hex['objects'] ?? NULL;
      if (!is_array($objects)) {
        $errors[] = "layout_data.hexes[{$index}].objects must be an array.";
        $objects = [];
      }

      $has_explicit_blocker = FALSE;
      $has_explicit_passable = FALSE;
      foreach ($objects as $object_index => $object) {
        if (!is_array($object)) {
          $errors[] = "layout_data.hexes[{$index}].objects[{$object_index}] must be an object.";
          continue;
        }
        $errors = array_merge($errors, $this->validateCanonicalRoomHexObjectContract($object, $index, $object_index));
        $blocks_movement = isset($object['blocks_movement']) && is_bool($object['blocks_movement']) ? $object['blocks_movement'] : FALSE;
        $passable = isset($object['passable']) && is_bool($object['passable']) ? $object['passable'] : TRUE;
        if ($blocks_movement || !$passable) {
          $has_explicit_blocker = TRUE;
        }
        if ($passable && !$blocks_movement) {
          $has_explicit_passable = TRUE;
        }
      }
      $hex_is_blocked = $has_explicit_blocker && !$has_explicit_passable;

      if (!$hex_is_blocked) {
        $traversable_hexes[$coord_key] = TRUE;
      }
      if (!empty($hex['is_entry'])) {
        $entry_hex_flags[$coord_key] = TRUE;
      }
    }

    if ($entry_hex_flags === []) {
      $errors[] = 'layout_data.hexes must flag at least one hex with is_entry=true.';
    }

    $entry_points_valid = $this->validateCanonicalRoomLayoutPoints($entry_points, 'entry_points', $hex_coordinate_map, $errors);
    $exit_points_valid = $this->validateCanonicalRoomLayoutPoints($exit_points, 'exit_points', $hex_coordinate_map, $errors);

    if ($entry_points_valid !== [] && $exit_points_valid !== [] && $traversable_hexes !== []) {
      $entry_keys = [];
      foreach ($entry_points_valid as $point) {
        $point_key = $point['q'] . ':' . $point['r'];
        if (!isset($traversable_hexes[$point_key])) {
          $errors[] = "layout_data.entry_points coordinate '{$point_key}' is blocked and not traversable.";
        }
        else {
          $entry_keys[] = $point_key;
        }
      }

      $exit_keys = [];
      foreach ($exit_points_valid as $point) {
        $point_key = $point['q'] . ':' . $point['r'];
        if (!isset($traversable_hexes[$point_key])) {
          $errors[] = "layout_data.exit_points coordinate '{$point_key}' is blocked and not traversable.";
        }
        else {
          $exit_keys[] = $point_key;
        }
      }

      if ($entry_keys !== [] && $exit_keys !== [] && !$this->hasTraversableRoomPath($entry_keys, $exit_keys, $traversable_hexes)) {
        $errors[] = 'layout_data must provide at least one traversable path from an entry point to an exit point.';
      }
    }

    return $errors;
  }

  /**
   * Detect the canonical two-hex placeholder layout pattern.
   *
   * @param array<int, mixed> $hexes
   *   Layout hex definitions.
   */
  private function isPlaceholderRoomHexLayout(array $hexes): bool {
    if (count($hexes) !== 2) {
      return FALSE;
    }

    $coords = [];
    foreach ($hexes as $hex) {
      if (!is_array($hex)) {
        return FALSE;
      }
      if (!array_key_exists('q', $hex) || !array_key_exists('r', $hex)) {
        return FALSE;
      }
      if (!is_int($hex['q']) || !is_int($hex['r'])) {
        return FALSE;
      }
      $objects = $hex['objects'] ?? NULL;
      if (!is_array($objects) || $objects !== []) {
        return FALSE;
      }
      $coords[] = $hex['q'] . ':' . $hex['r'];
    }

    sort($coords, SORT_STRING);
    return $coords === ['0:0', '1:0'];
  }

  /**
   * Validate explicit room-to-room exit linkage definitions.
   *
   * @param array<string, mixed> $layout_data
   *   Room layout payload.
   * @param string $room_id
   *   Room ID under validation.
   * @param array<string, bool> $canonical_room_ids
   *   Canonical room IDs available in the dataset.
   *
   * @return array<int, string>
   *   Validation errors.
   */
  private function validateCanonicalRoomExitLinkContract(array $layout_data, string $room_id, array $canonical_room_ids): array {
    $errors = [];
    $layout_hex_coordinate_map = $this->buildLayoutCoordinateMap($layout_data['hexes'] ?? NULL);
    $exit_point_coordinate_map = $this->buildLayoutCoordinateMap($layout_data['exit_points'] ?? NULL);
    $exit_links = $layout_data[self::ROOM_LAYOUT_EXIT_LINK_FIELD] ?? NULL;
    if (!is_array($exit_links) || $exit_links === []) {
      $errors[] = 'layout_data.exits must define at least one linked target_room_id.';
      return $errors;
    }

    $valid_links = 0;
    $seen_targets = [];
    foreach ($exit_links as $index => $link) {
      if (!is_array($link)) {
        $errors[] = "layout_data.exits[{$index}] must be an object with target_room_id.";
        continue;
      }

      $target_room_id = trim((string) ($link['target_room_id'] ?? ''));
      if ($target_room_id === '') {
        $errors[] = "layout_data.exits[{$index}].target_room_id is required.";
        continue;
      }
      if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $target_room_id)) {
        $errors[] = "layout_data.exits[{$index}].target_room_id '{$target_room_id}' does not match canonical id pattern.";
        continue;
      }
      if ($this->isBlockedPromptDerivedRoomId($target_room_id)) {
        $errors[] = "layout_data.exits[{$index}].target_room_id '{$target_room_id}' uses a blocked prompt-derived prefix.";
        continue;
      }
      if ($target_room_id === $room_id) {
        $errors[] = "layout_data.exits[{$index}].target_room_id must reference another room (self-link is not allowed).";
        continue;
      }
      if (!isset($canonical_room_ids[$target_room_id])) {
        $errors[] = "layout_data.exits[{$index}].target_room_id '{$target_room_id}' does not resolve to a canonical room_id.";
        continue;
      }

      if (isset($seen_targets[$target_room_id])) {
        $errors[] = "layout_data.exits contains duplicate target_room_id '{$target_room_id}'.";
        continue;
      }
      $seen_targets[$target_room_id] = TRUE;

      $exit_hex = $this->extractExitLinkHexCoordinate($link);
      if ($exit_hex === NULL) {
        $errors[] = "layout_data.exits[{$index}] must include integer q/r coordinates (or hex.q/hex.r).";
        continue;
      }

      $exit_key = $exit_hex['q'] . ':' . $exit_hex['r'];
      if (!isset($layout_hex_coordinate_map[$exit_key])) {
        $errors[] = "layout_data.exits[{$index}] coordinate '{$exit_key}' does not map to any layout_data.hexes coordinate.";
        continue;
      }
      if ($exit_point_coordinate_map !== [] && !isset($exit_point_coordinate_map[$exit_key])) {
        $errors[] = "layout_data.exits[{$index}] coordinate '{$exit_key}' must match a layout_data.exit_points coordinate.";
        continue;
      }

      $valid_links++;
    }

    if ($valid_links === 0) {
      $errors[] = 'layout_data.exits must include at least one valid link to another canonical room.';
    }

    return $errors;
  }

  /**
   * Build coordinate map from q/r point arrays.
   *
   * @param mixed $points
   *   Candidate point list.
   *
   * @return array<string, bool>
   *   Keyed as "q:r".
   */
  private function buildLayoutCoordinateMap($points): array {
    $coordinate_map = [];
    if (!is_array($points)) {
      return $coordinate_map;
    }

    foreach ($points as $point) {
      if (!is_array($point) || !isset($point['q'], $point['r'])) {
        continue;
      }
      if (!is_int($point['q']) || !is_int($point['r'])) {
        continue;
      }
      $coordinate_map[$point['q'] . ':' . $point['r']] = TRUE;
    }

    return $coordinate_map;
  }

  /**
   * Extract one exit-link coordinate from top-level q/r or nested hex.q/hex.r.
   *
   * @param array<string, mixed> $link
   *   Exit link payload.
   *
   * @return array{q:int,r:int}|null
   *   Resolved coordinate or NULL when unavailable/invalid.
   */
  private function extractExitLinkHexCoordinate(array $link): ?array {
    $candidate = NULL;
    if (array_key_exists('q', $link) || array_key_exists('r', $link)) {
      $candidate = [
        'q' => $link['q'] ?? NULL,
        'r' => $link['r'] ?? NULL,
      ];
    }
    elseif (is_array($link['hex'] ?? NULL)) {
      $candidate = [
        'q' => $link['hex']['q'] ?? NULL,
        'r' => $link['hex']['r'] ?? NULL,
      ];
    }

    if (!is_array($candidate) || !is_int($candidate['q'] ?? NULL) || !is_int($candidate['r'] ?? NULL)) {
      return NULL;
    }

    return [
      'q' => (int) $candidate['q'],
      'r' => (int) $candidate['r'],
    ];
  }

  /**
   * Check whether one layout contains a specific q/r coordinate in hexes.
   *
   * @param array<string, mixed> $layout_data
   *   Room layout payload.
   */
  private function layoutContainsHexCoordinate(array $layout_data, int $q, int $r): bool {
    foreach ((array) ($layout_data['hexes'] ?? []) as $hex) {
      if (!is_array($hex) || !isset($hex['q'], $hex['r'])) {
        continue;
      }
      if ((int) $hex['q'] === $q && (int) $hex['r'] === $r) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Extract explicit exit-link endpoint hex for one target room.
   *
   * @param array<string, mixed> $layout_data
   *   Room layout payload.
   * @param string $target_room_id
   *   Target room id.
   *
   * @return array{q:int,r:int}|null
   *   Endpoint coordinate or NULL when no explicit coordinate exists.
   */
  private function extractRoomExitHexCoordinateForTarget(array $layout_data, string $target_room_id): ?array {
    foreach ((array) ($layout_data[self::ROOM_LAYOUT_EXIT_LINK_FIELD] ?? []) as $exit_link) {
      if (!is_array($exit_link)) {
        continue;
      }
      if (trim((string) ($exit_link['target_room_id'] ?? '')) !== $target_room_id) {
        continue;
      }
      $hex = $this->extractExitLinkHexCoordinate($exit_link);
      if ($hex !== NULL) {
        return $hex;
      }
    }

    return NULL;
  }

  /**
   * Validate dungeon-level requirement for cross-dungeon room links.
   *
   * @param array<string, bool> $canonical_room_ids
   *   Canonical room ID map.
   * @param array<string, array<int, string>> $room_exit_targets_by_room
   *   Exit target room IDs keyed by room ID.
   *
   * @return array<int, string>
   *   Validation errors.
   */
  private function validateCanonicalDungeonCrossDungeonLinkage(array $canonical_room_ids, array $room_exit_targets_by_room): array {
    if ($this->database === NULL) {
      return ['Canonical dungeon cross-link validation requires database access.'];
    }

    $schema = $this->database->schema();
    if (!$schema->tableExists('dungeoncrawler_content_dungeons')) {
      return ['Canonical dungeon table dungeoncrawler_content_dungeons is unavailable.'];
    }

    $rows = $this->database->select('dungeoncrawler_content_dungeons', 'd')
      ->fields('d', ['dungeon_id', 'name', 'dungeon_data'])
      ->orderBy('dungeon_id', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    if (!is_array($rows) || $rows === []) {
      return ['Canonical dungeon table contains no records.'];
    }

    $errors = [];
    $room_layouts_by_id = [];
    $room_layout_rows = $this->database->select('dungeoncrawler_content_rooms', 'r')
      ->fields('r', ['room_id', 'layout_data'])
      ->condition('room_id', array_keys($canonical_room_ids), 'IN')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    foreach ((array) $room_layout_rows as $room_layout_row) {
      if (!is_array($room_layout_row)) {
        continue;
      }
      $room_id = trim((string) ($room_layout_row['room_id'] ?? ''));
      if ($room_id === '') {
        continue;
      }
      $layout_data = json_decode((string) ($room_layout_row['layout_data'] ?? ''), TRUE);
      if (is_array($layout_data)) {
        $room_layouts_by_id[$room_id] = $layout_data;
      }
    }

    $dungeon_rooms = [];
    $room_to_dungeons = [];
    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }

      $dungeon_id = trim((string) ($row['dungeon_id'] ?? ''));
      if ($dungeon_id === '') {
        $errors[] = 'Canonical dungeon row is missing dungeon_id.';
        continue;
      }

      $dungeon_data = json_decode((string) ($row['dungeon_data'] ?? ''), TRUE);
      if (!is_array($dungeon_data)) {
        $errors[] = "Dungeon '{$dungeon_id}' has invalid dungeon_data JSON.";
        continue;
      }

      $rooms = $dungeon_data['rooms'] ?? NULL;
      if (!is_array($rooms) || $rooms === []) {
        $errors[] = "Dungeon '{$dungeon_id}' must define at least one room in dungeon_data.rooms.";
        continue;
      }

      $resolved_room_ids = [];
      foreach ($rooms as $index => $room) {
        $room_id = '';
        if (is_string($room)) {
          $room_id = trim($room);
        }
        elseif (is_array($room)) {
          $room_id = trim((string) ($room['room_id'] ?? ''));
        }
        if ($room_id === '') {
          $errors[] = "Dungeon '{$dungeon_id}' has an invalid room entry at index {$index}.";
          continue;
        }
        if (!isset($canonical_room_ids[$room_id])) {
          $errors[] = "Dungeon '{$dungeon_id}' references unknown room_id '{$room_id}'.";
          continue;
        }
        if (isset($resolved_room_ids[$room_id])) {
          continue;
        }
        $resolved_room_ids[$room_id] = TRUE;
        $room_to_dungeons[$room_id][$dungeon_id] = TRUE;
      }

      if ($resolved_room_ids === []) {
        $errors[] = "Dungeon '{$dungeon_id}' has no valid canonical rooms after normalization.";
        continue;
      }
      $dungeon_rooms[$dungeon_id] = array_keys($resolved_room_ids);
    }

    $connector_rows_by_dungeon = [];
    if ($schema->tableExists('dungeoncrawler_content_connections')) {
      $required_connector_fields = ['from_hex_q', 'from_hex_r', 'to_hex_q', 'to_hex_r'];
      $missing_connector_fields = [];
      foreach ($required_connector_fields as $required_connector_field) {
        if (!$schema->fieldExists('dungeoncrawler_content_connections', $required_connector_field)) {
          $missing_connector_fields[] = $required_connector_field;
        }
      }
      if ($missing_connector_fields !== []) {
        $errors[] = 'Canonical connector schema missing endpoint hex fields: '
          . implode(', ', $missing_connector_fields)
          . '. Run module update hook 10159.';
      }
      else {
      $connector_rows = $this->database->select('dungeoncrawler_content_connections', 'c')
        ->fields('c', ['dungeon_id', 'from_room_id', 'to_room_id', 'direction', 'default_state', 'from_hex_q', 'from_hex_r', 'to_hex_q', 'to_hex_r'])
        ->orderBy('dungeon_id', 'ASC')
        ->orderBy('id', 'ASC')
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC);
      foreach ((array) $connector_rows as $connector_row) {
        if (!is_array($connector_row)) {
          continue;
        }
        $connector_dungeon_id = trim((string) ($connector_row['dungeon_id'] ?? ''));
        if ($connector_dungeon_id === '') {
          $errors[] = 'Canonical connector row is missing dungeon_id.';
          continue;
        }
        if (!isset($connector_rows_by_dungeon[$connector_dungeon_id])) {
          $connector_rows_by_dungeon[$connector_dungeon_id] = [];
        }
        $connector_rows_by_dungeon[$connector_dungeon_id][] = $connector_row;
      }
      }
    }

    foreach ($dungeon_rooms as $dungeon_id => $room_ids) {
      $has_cross_dungeon_link = FALSE;
      foreach ($room_ids as $room_id) {
        $exit_targets = $room_exit_targets_by_room[$room_id] ?? [];
        foreach ($exit_targets as $target_room_id) {
          $target_dungeons = array_keys($room_to_dungeons[$target_room_id] ?? []);
          foreach ($target_dungeons as $target_dungeon_id) {
            if ($target_dungeon_id !== $dungeon_id) {
              $has_cross_dungeon_link = TRUE;
              break 3;
            }
          }
        }
      }
      if (!$has_cross_dungeon_link) {
        $errors[] = "Dungeon '{$dungeon_id}' must have at least one room link to a room in another dungeon.";
      }

      $room_set = array_fill_keys($room_ids, TRUE);
      $in_dungeon_graph = [];
      foreach ($room_ids as $room_id) {
        $in_dungeon_graph[$room_id] = [];
      }

      $in_dungeon_edge_count = 0;
      foreach ($room_ids as $room_id) {
        $exit_targets = (array) ($room_exit_targets_by_room[$room_id] ?? []);
        foreach ($exit_targets as $target_room_id) {
          if (!isset($room_set[$target_room_id])) {
            continue;
          }
          $in_dungeon_graph[$room_id][$target_room_id] = TRUE;
          $in_dungeon_graph[$target_room_id][$room_id] = TRUE;
          $in_dungeon_edge_count++;
        }
      }

      if (count($room_ids) > 1 && $in_dungeon_edge_count === 0) {
        $errors[] = "Dungeon '{$dungeon_id}' has multiple rooms but no in-dungeon room-to-room links.";
      }

      if ($room_ids !== []) {
        $start_room_id = (string) reset($room_ids);
        $visited = [];
        $queue = [$start_room_id];
        while ($queue !== []) {
          $current_room_id = array_shift($queue);
          if (!is_string($current_room_id) || isset($visited[$current_room_id])) {
            continue;
          }
          if (!isset($in_dungeon_graph[$current_room_id])) {
            continue;
          }
          $visited[$current_room_id] = TRUE;
          foreach (array_keys($in_dungeon_graph[$current_room_id]) as $neighbor_room_id) {
            if (!isset($visited[$neighbor_room_id])) {
              $queue[] = $neighbor_room_id;
            }
          }
        }

        $unreachable_room_ids = [];
        foreach ($room_ids as $room_id) {
          if (!isset($visited[$room_id])) {
            $unreachable_room_ids[] = $room_id;
          }
        }
        if ($unreachable_room_ids !== []) {
          $errors[] = "Dungeon '{$dungeon_id}' has unreachable rooms in in-dungeon topology: " . implode(', ', $unreachable_room_ids) . '.';
        }
      }

      $connector_rows = (array) ($connector_rows_by_dungeon[$dungeon_id] ?? []);
      if (count($room_ids) > 1 && $connector_rows === []) {
        $errors[] = "Dungeon '{$dungeon_id}' has no connector rows in dungeoncrawler_content_connections.";
      }

      $room_total_connector_degree = array_fill_keys($room_ids, 0);
      $room_passable_connector_degree = array_fill_keys($room_ids, 0);
      $room_outgoing_edge_count = array_fill_keys($room_ids, 0);
      $room_incoming_edge_count = array_fill_keys($room_ids, 0);
      $connector_index = [];
      foreach ($connector_rows as $connector_row) {
        if (!is_array($connector_row)) {
          continue;
        }
        $from_room_id = trim((string) ($connector_row['from_room_id'] ?? ''));
        $to_room_id = trim((string) ($connector_row['to_room_id'] ?? ''));
        $direction = strtolower(trim((string) ($connector_row['direction'] ?? '')));
        $default_state = strtolower(trim((string) ($connector_row['default_state'] ?? 'open')));
        $from_hex_q = $connector_row['from_hex_q'] ?? NULL;
        $from_hex_r = $connector_row['from_hex_r'] ?? NULL;
        $to_hex_q = $connector_row['to_hex_q'] ?? NULL;
        $to_hex_r = $connector_row['to_hex_r'] ?? NULL;

        if ($from_room_id === '' || $to_room_id === '') {
          $errors[] = "Dungeon '{$dungeon_id}' has connector rows missing from_room_id/to_room_id.";
          continue;
        }
        if (!isset($room_set[$from_room_id])) {
          $errors[] = "Dungeon '{$dungeon_id}' connector '{$from_room_id}' -> '{$to_room_id}' has from_room_id outside dungeon_data.rooms.";
          continue;
        }
        if (!in_array($direction, ['one_way', 'bidirectional'], TRUE)) {
          $errors[] = "Dungeon '{$dungeon_id}' connector '{$from_room_id}' -> '{$to_room_id}' has unsupported direction '{$direction}'.";
          continue;
        }
        if (!is_numeric($from_hex_q) || !is_numeric($from_hex_r) || !is_numeric($to_hex_q) || !is_numeric($to_hex_r)) {
          $errors[] = "Dungeon '{$dungeon_id}' connector '{$from_room_id}' -> '{$to_room_id}' is missing endpoint hex coordinates.";
          continue;
        }
        $from_hex_q = (int) $from_hex_q;
        $from_hex_r = (int) $from_hex_r;
        $to_hex_q = (int) $to_hex_q;
        $to_hex_r = (int) $to_hex_r;

        $from_layout = (array) ($room_layouts_by_id[$from_room_id] ?? []);
        $to_layout = (array) ($room_layouts_by_id[$to_room_id] ?? []);
        if (!$this->layoutContainsHexCoordinate($from_layout, $from_hex_q, $from_hex_r)) {
          $errors[] = "Dungeon '{$dungeon_id}' connector '{$from_room_id}' -> '{$to_room_id}' from_hex '{$from_hex_q}:{$from_hex_r}' does not map to from_room layout_data.hexes.";
        }
        if (!$this->layoutContainsHexCoordinate($to_layout, $to_hex_q, $to_hex_r)) {
          $errors[] = "Dungeon '{$dungeon_id}' connector '{$from_room_id}' -> '{$to_room_id}' to_hex '{$to_hex_q}:{$to_hex_r}' does not map to to_room layout_data.hexes.";
        }
        $from_exit_hex = $this->extractRoomExitHexCoordinateForTarget($from_layout, $to_room_id);
        if (
          is_array($from_exit_hex)
          && ((int) ($from_exit_hex['q'] ?? 0) !== $from_hex_q || (int) ($from_exit_hex['r'] ?? 0) !== $from_hex_r)
        ) {
          $errors[] = "Dungeon '{$dungeon_id}' connector '{$from_room_id}' -> '{$to_room_id}' from_hex '{$from_hex_q}:{$from_hex_r}' does not match layout_data.exits endpoint '{$from_exit_hex['q']}:{$from_exit_hex['r']}'.";
        }
        $to_exit_hex = $this->extractRoomExitHexCoordinateForTarget($to_layout, $from_room_id);
        if (
          is_array($to_exit_hex)
          && ((int) ($to_exit_hex['q'] ?? 0) !== $to_hex_q || (int) ($to_exit_hex['r'] ?? 0) !== $to_hex_r)
        ) {
          $errors[] = "Dungeon '{$dungeon_id}' connector '{$from_room_id}' -> '{$to_room_id}' to_hex '{$to_hex_q}:{$to_hex_r}' does not match reverse layout_data.exits endpoint '{$to_exit_hex['q']}:{$to_exit_hex['r']}'.";
        }

        $from_targets = (array) ($room_exit_targets_by_room[$from_room_id] ?? []);
        if (!in_array($to_room_id, $from_targets, TRUE)) {
          $errors[] = "Dungeon '{$dungeon_id}' connector '{$from_room_id}' -> '{$to_room_id}' is unresolved in layout_data.exits.";
        }
        if ($direction === 'bidirectional') {
          $to_targets = (array) ($room_exit_targets_by_room[$to_room_id] ?? []);
          if (!in_array($from_room_id, $to_targets, TRUE)) {
            $errors[] = "Dungeon '{$dungeon_id}' bidirectional connector '{$from_room_id}' <-> '{$to_room_id}' is missing reverse layout_data.exits link.";
          }
        }

        $connector_index[$from_room_id . '::' . $to_room_id] = $direction;
        if ($direction === 'bidirectional') {
          $connector_index[$to_room_id . '::' . $from_room_id] = $direction;
        }

        if (isset($room_set[$to_room_id])) {
          $room_outgoing_edge_count[$from_room_id] = (int) ($room_outgoing_edge_count[$from_room_id] ?? 0) + 1;
          $room_incoming_edge_count[$to_room_id] = (int) ($room_incoming_edge_count[$to_room_id] ?? 0) + 1;
          if ($direction === 'bidirectional') {
            $room_outgoing_edge_count[$to_room_id] = (int) ($room_outgoing_edge_count[$to_room_id] ?? 0) + 1;
            $room_incoming_edge_count[$from_room_id] = (int) ($room_incoming_edge_count[$from_room_id] ?? 0) + 1;
          }
        }

        $room_total_connector_degree[$from_room_id] = (int) ($room_total_connector_degree[$from_room_id] ?? 0) + 1;
        if (isset($room_set[$to_room_id])) {
          $room_total_connector_degree[$to_room_id] = (int) ($room_total_connector_degree[$to_room_id] ?? 0) + 1;
        }

        $is_passable = !in_array($default_state, ['destroyed', 'collapsed'], TRUE);
        if ($is_passable) {
          $room_passable_connector_degree[$from_room_id] = (int) ($room_passable_connector_degree[$from_room_id] ?? 0) + 1;
          if (isset($room_set[$to_room_id])) {
            $room_passable_connector_degree[$to_room_id] = (int) ($room_passable_connector_degree[$to_room_id] ?? 0) + 1;
          }
        }
      }

      foreach ($room_ids as $room_id) {
        $exit_targets = (array) ($room_exit_targets_by_room[$room_id] ?? []);
        foreach ($exit_targets as $target_room_id) {
          if (!isset($canonical_room_ids[$target_room_id])) {
            continue;
          }
          if (!isset($connector_index[$room_id . '::' . $target_room_id])) {
            $errors[] = "Dungeon '{$dungeon_id}' layout_data.exits link '{$room_id}' -> '{$target_room_id}' is missing a matching connector row.";
          }
        }
      }

      if (count($room_ids) > 1) {
        foreach ($room_ids as $room_id) {
          $total_degree = (int) ($room_total_connector_degree[$room_id] ?? 0);
          $passable_degree = (int) ($room_passable_connector_degree[$room_id] ?? 0);
          if ($total_degree > 0 && $passable_degree === 0) {
            $errors[] = "Dungeon '{$dungeon_id}' room '{$room_id}' is only connected through blocked/non-passable connectors.";
          }
          $outgoing_edges = (int) ($room_outgoing_edge_count[$room_id] ?? 0);
          $incoming_edges = (int) ($room_incoming_edge_count[$room_id] ?? 0);
          if ($outgoing_edges === 0) {
            $errors[] = "Dungeon '{$dungeon_id}' room '{$room_id}' has no outgoing connector edge.";
          }
          if ($incoming_edges === 0) {
            $errors[] = "Dungeon '{$dungeon_id}' room '{$room_id}' has no incoming connector edge.";
          }
        }
      }
    }

    return array_values(array_unique($errors));
  }

  /**
   * Validate sparse H3 room anchors/cells and detect overlapping hex designations.
   *
   * @param array<string, bool> $canonical_room_ids
   *   Canonical room ID map.
   *
   * @return array<string, mixed>
   *   Validation payload with errors, summary metrics, and collision diagnostics.
   */
  private function validateCanonicalHexReferentialContracts(array $canonical_room_ids): array {
    $result = [
      'errors' => [],
      'summary' => [
        'total_anchors' => 0,
        'total_cells' => 0,
        'cells_with_h3_index' => 0,
        'cells_without_h3_index' => 0,
        'cells_with_vertical_metrics' => 0,
        'cells_missing_vertical_metrics' => 0,
        'collision_count' => 0,
        'hex_graph_nodes' => 0,
        'hex_graph_edges' => 0,
        'res14_rooms_total' => 0,
        'res14_rooms_passing' => 0,
        'res14_rooms_failing' => 0,
        'res14_nodes' => 0,
        'res14_edges' => 0,
      ],
      'items' => [],
    ];

    if ($this->database === NULL) {
      $result['errors'][] = 'Canonical hex validation requires database access.';
      return $result;
    }

    $schema = $this->database->schema();
    if (
      !$schema->tableExists('dungeoncrawler_content_h3_room_anchors')
      || !$schema->tableExists('dungeoncrawler_content_h3_room_cells')
    ) {
      $result['errors'][] = 'Sparse hex referential tables are unavailable (dungeoncrawler_content_h3_room_anchors/cells).';
      return $result;
    }

    $anchor_rows = $this->database->select('dungeoncrawler_content_h3_room_anchors', 'a')
      ->fields('a', ['room_id', 'dungeon_id', 'h3_resolution', 'h3_index', 'center_latitude', 'center_longitude', 'reference_q', 'reference_r', 'metadata'])
      ->condition('a.dungeon_id', 'tpl\\_%', 'LIKE')
      ->orderBy('room_id', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    $cell_rows = $this->database->select('dungeoncrawler_content_h3_room_cells', 'c')
      ->fields('c', ['id', 'room_id', 'dungeon_id', 'cell_role', 'h3_resolution', 'h3_index', 'source_q', 'source_r', 'center_latitude', 'center_longitude', 'metadata'])
      ->condition('c.dungeon_id', 'tpl\\_%', 'LIKE')
      ->orderBy('id', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $anchor_by_room = [];
    $res14_anchor_h3_by_dungeon = [];
    foreach ((array) $anchor_rows as $anchor_row) {
      if (!is_array($anchor_row)) {
        continue;
      }
      $room_id = trim((string) ($anchor_row['room_id'] ?? ''));
      $dungeon_id = trim((string) ($anchor_row['dungeon_id'] ?? ''));
      $resolution = isset($anchor_row['h3_resolution']) ? (int) $anchor_row['h3_resolution'] : 0;
      if ($room_id === '') {
        $result['errors'][] = 'Sparse hex anchor row is missing room_id.';
        continue;
      }
      if ($dungeon_id === '') {
        $result['errors'][] = "Sparse hex anchor '{$room_id}' is missing dungeon_id.";
      }
      if (!isset($canonical_room_ids[$room_id])) {
        $result['errors'][] = "Sparse hex anchor '{$room_id}' does not resolve to a canonical room_id.";
      }
      if ($resolution < 5 || $resolution > 15) {
        $result['errors'][] = "Sparse hex anchor '{$room_id}' has out-of-range h3_resolution '{$resolution}' (expected 5-15).";
      }
      $h3_index = trim((string) ($anchor_row['h3_index'] ?? ''));
      $anchor_latitude = isset($anchor_row['center_latitude']) && is_numeric($anchor_row['center_latitude'])
        ? (float) $anchor_row['center_latitude']
        : NULL;
      $anchor_longitude = isset($anchor_row['center_longitude']) && is_numeric($anchor_row['center_longitude'])
        ? (float) $anchor_row['center_longitude']
        : NULL;
      $anchor_metadata_raw = trim((string) ($anchor_row['metadata'] ?? ''));
      $anchor_metadata = [];
      if ($anchor_metadata_raw !== '') {
        $anchor_metadata = json_decode($anchor_metadata_raw, TRUE);
      }

      if ($resolution === self::ACTIVE_GENERATION_RESOLUTION) {
        if ($h3_index === '') {
          $result['errors'][] = "Sparse hex anchor '{$room_id}' at resolution {$resolution} must define h3_index.";
        }
        else {
          foreach (self::DISALLOWED_PSEUDO_H3_PREFIXES as $pseudo_prefix) {
            if (str_starts_with(strtolower($h3_index), $pseudo_prefix)) {
              $result['errors'][] = "Sparse hex anchor '{$room_id}' at resolution {$resolution} uses pseudo h3_index '{$h3_index}' (prefix {$pseudo_prefix}); canonical H3 index required.";
              break;
            }
          }
          if (!preg_match(self::CANONICAL_H3_INDEX_PATTERN, strtolower($h3_index))) {
            $result['errors'][] = "Sparse hex anchor '{$room_id}' at resolution {$resolution} has invalid h3_index format '{$h3_index}' (expected canonical hex H3 index).";
          }
        }
        if ($anchor_latitude === NULL || $anchor_longitude === NULL) {
          $result['errors'][] = "Sparse hex anchor '{$room_id}' at resolution {$resolution} must define center_latitude/center_longitude.";
        }
        else {
          if ($anchor_latitude < -90.0 || $anchor_latitude > 90.0) {
            $result['errors'][] = "Sparse hex anchor '{$room_id}' at resolution {$resolution} has out-of-range center_latitude '{$anchor_latitude}'.";
          }
          if ($anchor_longitude < -180.0 || $anchor_longitude > 180.0) {
            $result['errors'][] = "Sparse hex anchor '{$room_id}' at resolution {$resolution} has out-of-range center_longitude '{$anchor_longitude}'.";
          }
        }
        if (!is_array($anchor_metadata)) {
          $result['errors'][] = "Sparse hex anchor '{$room_id}' at resolution {$resolution} has invalid metadata JSON.";
        }
        else {
          $anchor_placement_model = trim((string) ($anchor_metadata['placement_model'] ?? ''));
          if ($anchor_placement_model === '') {
            $result['errors'][] = "Sparse hex anchor '{$room_id}' at resolution {$resolution} must define metadata.placement_model.";
          }
          $anchor_gap = $anchor_metadata['placement_min_gap_hexes'] ?? NULL;
          if (!is_numeric($anchor_gap) || (int) $anchor_gap < 1) {
            $result['errors'][] = "Sparse hex anchor '{$room_id}' at resolution {$resolution} must define positive metadata.placement_min_gap_hexes.";
          }
          $anchor_min_distance = $anchor_metadata['placement_min_anchor_distance_hexes'] ?? NULL;
          if (!is_numeric($anchor_min_distance) || (int) $anchor_min_distance < self::MIN_RES14_ANCHOR_DISTANCE_HEXES) {
            $result['errors'][] = "Sparse hex anchor '{$room_id}' at resolution {$resolution} must define metadata.placement_min_anchor_distance_hexes >= " . self::MIN_RES14_ANCHOR_DISTANCE_HEXES . '.';
          }
          if (!array_key_exists('global_offset_q', $anchor_metadata) || !is_numeric($anchor_metadata['global_offset_q'])) {
            $result['errors'][] = "Sparse hex anchor '{$room_id}' at resolution {$resolution} must define numeric metadata.global_offset_q.";
          }
          if (!array_key_exists('global_offset_r', $anchor_metadata) || !is_numeric($anchor_metadata['global_offset_r'])) {
            $result['errors'][] = "Sparse hex anchor '{$room_id}' at resolution {$resolution} must define numeric metadata.global_offset_r.";
          }
          $normalization = trim((string) ($anchor_metadata['normalization'] ?? ''));
          if ($normalization !== self::REQUIRED_SPARSE_NORMALIZATION) {
            $result['errors'][] = "Sparse hex anchor '{$room_id}' at resolution {$resolution} must define metadata.normalization='" . self::REQUIRED_SPARSE_NORMALIZATION . "'.";
          }
        }
      }
      if (
        $resolution === self::ACTIVE_GENERATION_RESOLUTION
        && $dungeon_id !== ''
        && $h3_index !== ''
        && preg_match(self::CANONICAL_H3_INDEX_PATTERN, strtolower($h3_index))
      ) {
        if (!isset($res14_anchor_h3_by_dungeon[$dungeon_id])) {
          $res14_anchor_h3_by_dungeon[$dungeon_id] = [];
        }
        $res14_anchor_h3_by_dungeon[$dungeon_id][$room_id] = strtolower($h3_index);
      }
      if ($resolution === 15) {
        $result['errors'][] = "Sparse hex anchor '{$room_id}' uses resolution 15, which is out of scope for active generation validation.";
      }
      $anchor_by_room[$room_id] = [
        'room_id' => $room_id,
        'dungeon_id' => $dungeon_id,
        'h3_resolution' => $resolution,
        'h3_index' => $h3_index,
        'center_latitude' => $anchor_latitude,
        'center_longitude' => $anchor_longitude,
        'metadata' => is_array($anchor_metadata) ? $anchor_metadata : [],
      ];
      $result['summary']['total_anchors']++;
    }
    foreach ($res14_anchor_h3_by_dungeon as $dungeon_id => $anchor_h3_by_room) {
      if (!is_array($anchor_h3_by_room) || count($anchor_h3_by_room) < 2) {
        continue;
      }
      $dungeon_room_ids = array_keys($anchor_h3_by_room);
      sort($dungeon_room_ids, SORT_STRING);
      for ($i = 0; $i < count($dungeon_room_ids); $i++) {
        $left_room_id = $dungeon_room_ids[$i];
        $left_h3 = (string) ($anchor_h3_by_room[$left_room_id] ?? '');
        if ($left_h3 === '') {
          continue;
        }
        for ($j = $i + 1; $j < count($dungeon_room_ids); $j++) {
          $right_room_id = $dungeon_room_ids[$j];
          $right_h3 = (string) ($anchor_h3_by_room[$right_room_id] ?? '');
          if ($right_h3 === '') {
            continue;
          }
          try {
            $anchor_distance = H3SpatialHelper::h3GridDistance($left_h3, $right_h3);
          }
          catch (\Throwable $e) {
            $result['errors'][] = "Sparse hex anchor spacing check failed for dungeon '{$dungeon_id}' rooms '{$left_room_id}'/'{$right_room_id}': " . $e->getMessage();
            continue;
          }
          if ($anchor_distance < self::MIN_RES14_ANCHOR_DISTANCE_HEXES) {
            $result['errors'][] = "Sparse hex anchor spacing contract violation in dungeon '{$dungeon_id}' between rooms '{$left_room_id}' and '{$right_room_id}': {$anchor_distance} < " . self::MIN_RES14_ANCHOR_DISTANCE_HEXES . ' res14 hexes.';
          }
        }
      }
    }

    $cell_designation_map = [];
    $hex_nodes_by_scope = [];
    $room_hex_nodes_by_scope = [];
    $room_entrance_coordinates_by_scope = [];
    $coordinate_ranges_by_scope = [];
    $res14_cells_missing_h3_index = 0;
    $res14_cells_missing_lat_lng = 0;
    $res15_cells_out_of_scope = 0;
    $anchor_resolution_mismatch_by_room = [];
    $room_mapped_resolution_map = array_fill_keys(self::ROOM_OWNERSHIP_REQUIRED_RESOLUTIONS, TRUE);
    foreach ((array) $cell_rows as $cell_row) {
      if (!is_array($cell_row)) {
        continue;
      }

      $cell_id = isset($cell_row['id']) ? (int) $cell_row['id'] : 0;
      $room_id = trim((string) ($cell_row['room_id'] ?? ''));
      $dungeon_id = trim((string) ($cell_row['dungeon_id'] ?? ''));
      $cell_role = trim((string) ($cell_row['cell_role'] ?? ''));
      $resolution = isset($cell_row['h3_resolution']) ? (int) $cell_row['h3_resolution'] : 0;
      $source_q = isset($cell_row['source_q']) ? (int) $cell_row['source_q'] : 0;
      $source_r = isset($cell_row['source_r']) ? (int) $cell_row['source_r'] : 0;
      $center_latitude = isset($cell_row['center_latitude']) && is_numeric($cell_row['center_latitude'])
        ? (float) $cell_row['center_latitude']
        : NULL;
      $center_longitude = isset($cell_row['center_longitude']) && is_numeric($cell_row['center_longitude'])
        ? (float) $cell_row['center_longitude']
        : NULL;
      $h3_index = trim((string) ($cell_row['h3_index'] ?? ''));
      $cell_metadata_raw = trim((string) ($cell_row['metadata'] ?? ''));
      $room_required = isset($room_mapped_resolution_map[$resolution]);

      if ($room_id === '') {
        $result['errors'][] = "Sparse hex cell #{$cell_id} is missing room_id.";
        continue;
      }
      if ($room_required) {
        if (strtoupper($room_id) === self::ROOM_OWNERSHIP_NOT_APPLICABLE) {
          $result['errors'][] = "Sparse hex cell #{$cell_id} at resolution {$resolution} must resolve to a canonical room_id (room_id cannot be 'NA').";
        }
        elseif (!isset($canonical_room_ids[$room_id])) {
          $result['errors'][] = "Sparse hex cell #{$cell_id} references unknown canonical room_id '{$room_id}'.";
        }
      }
      elseif ($room_id !== self::ROOM_OWNERSHIP_NOT_APPLICABLE) {
        $result['errors'][] = "Sparse hex cell #{$cell_id} at resolution {$resolution} must set room_id to 'NA'; found '{$room_id}'.";
      }
      if ($dungeon_id === '') {
        $result['errors'][] = "Sparse hex cell #{$cell_id} ({$room_id}) is missing dungeon_id.";
      }
      if ($cell_role === '') {
        $result['errors'][] = "Sparse hex cell #{$cell_id} ({$room_id}) is missing cell_role.";
      }
      if ($resolution < 5 || $resolution > 15) {
        $result['errors'][] = "Sparse hex cell #{$cell_id} ({$room_id}) has out-of-range h3_resolution '{$resolution}' (expected 5-15).";
      }
      if ($resolution === 15) {
        $res15_cells_out_of_scope++;
      }
      if ($resolution === self::ACTIVE_GENERATION_RESOLUTION) {
        if ($h3_index === '') {
          $res14_cells_missing_h3_index++;
        }
        else {
          foreach (self::DISALLOWED_PSEUDO_H3_PREFIXES as $pseudo_prefix) {
            if (str_starts_with(strtolower($h3_index), $pseudo_prefix)) {
              $result['errors'][] = "Sparse hex cell #{$cell_id} ({$room_id}) uses pseudo h3_index '{$h3_index}' (prefix {$pseudo_prefix}); canonical H3 index required.";
              break;
            }
          }
          if (!preg_match(self::CANONICAL_H3_INDEX_PATTERN, strtolower($h3_index))) {
            $result['errors'][] = "Sparse hex cell #{$cell_id} ({$room_id}) has invalid h3_index format '{$h3_index}' (expected canonical hex H3 index).";
          }
        }
        if ($center_latitude === NULL || $center_longitude === NULL) {
          $res14_cells_missing_lat_lng++;
        }
        else {
          if ($center_latitude < -90.0 || $center_latitude > 90.0) {
            $result['errors'][] = "Sparse hex cell #{$cell_id} ({$room_id}) has out-of-range center_latitude '{$center_latitude}'.";
          }
          if ($center_longitude < -180.0 || $center_longitude > 180.0) {
            $result['errors'][] = "Sparse hex cell #{$cell_id} ({$room_id}) has out-of-range center_longitude '{$center_longitude}'.";
          }
          $coord_scope_key = ($dungeon_id !== '' ? $dungeon_id : '<missing-dungeon>') . '|' . (string) $resolution;
          if (!isset($coordinate_ranges_by_scope[$coord_scope_key])) {
            $coordinate_ranges_by_scope[$coord_scope_key] = [
              'min_lat' => $center_latitude,
              'max_lat' => $center_latitude,
              'min_lng' => $center_longitude,
              'max_lng' => $center_longitude,
            ];
          }
          else {
            $coordinate_ranges_by_scope[$coord_scope_key]['min_lat'] = min((float) $coordinate_ranges_by_scope[$coord_scope_key]['min_lat'], $center_latitude);
            $coordinate_ranges_by_scope[$coord_scope_key]['max_lat'] = max((float) $coordinate_ranges_by_scope[$coord_scope_key]['max_lat'], $center_latitude);
            $coordinate_ranges_by_scope[$coord_scope_key]['min_lng'] = min((float) $coordinate_ranges_by_scope[$coord_scope_key]['min_lng'], $center_longitude);
            $coordinate_ranges_by_scope[$coord_scope_key]['max_lng'] = max((float) $coordinate_ranges_by_scope[$coord_scope_key]['max_lng'], $center_longitude);
          }
        }
      }

      $cell_has_vertical_metrics = TRUE;
      $cell_metadata = [];
      if ($cell_metadata_raw === '') {
        $cell_has_vertical_metrics = FALSE;
        $result['errors'][] = "Sparse hex cell #{$cell_id} ({$room_id}) is missing metadata; expected elevation_ft and ceiling_height_ft for each hex.";
      }
      else {
        $cell_metadata = json_decode($cell_metadata_raw, TRUE);
        if (!is_array($cell_metadata)) {
          $cell_has_vertical_metrics = FALSE;
          $result['errors'][] = "Sparse hex cell #{$cell_id} ({$room_id}) metadata is not valid JSON; expected elevation_ft and ceiling_height_ft.";
        }
        else {
          if (!array_key_exists('elevation_ft', $cell_metadata) || !is_numeric($cell_metadata['elevation_ft'])) {
            $cell_has_vertical_metrics = FALSE;
            $result['errors'][] = "Sparse hex cell #{$cell_id} ({$room_id}) must define numeric metadata.elevation_ft.";
          }
          if (!array_key_exists('ceiling_height_ft', $cell_metadata) || !is_numeric($cell_metadata['ceiling_height_ft'])) {
            $cell_has_vertical_metrics = FALSE;
            $result['errors'][] = "Sparse hex cell #{$cell_id} ({$room_id}) must define numeric metadata.ceiling_height_ft.";
          }
          elseif ((float) $cell_metadata['ceiling_height_ft'] <= 0.0) {
            $cell_has_vertical_metrics = FALSE;
            $result['errors'][] = "Sparse hex cell #{$cell_id} ({$room_id}) metadata.ceiling_height_ft must be greater than 0.";
          }

          if ($resolution === self::ACTIVE_GENERATION_RESOLUTION) {
            $cell_placement_model = trim((string) ($cell_metadata['placement_model'] ?? ''));
            if ($cell_placement_model === '') {
              $result['errors'][] = "Sparse hex cell #{$cell_id} ({$room_id}) at resolution {$resolution} must define metadata.placement_model.";
            }

            $cell_gap = $cell_metadata['placement_min_gap_hexes'] ?? NULL;
            if (!is_numeric($cell_gap) || (int) $cell_gap < 1) {
              $result['errors'][] = "Sparse hex cell #{$cell_id} ({$room_id}) at resolution {$resolution} must define positive metadata.placement_min_gap_hexes.";
            }
            $cell_anchor_gap = $cell_metadata['placement_min_anchor_distance_hexes'] ?? NULL;
            if (!is_numeric($cell_anchor_gap) || (int) $cell_anchor_gap < self::MIN_RES14_ANCHOR_DISTANCE_HEXES) {
              $result['errors'][] = "Sparse hex cell #{$cell_id} ({$room_id}) at resolution {$resolution} must define metadata.placement_min_anchor_distance_hexes >= " . self::MIN_RES14_ANCHOR_DISTANCE_HEXES . '.';
            }
            if (!array_key_exists('global_offset_q', $cell_metadata) || !is_numeric($cell_metadata['global_offset_q'])) {
              $result['errors'][] = "Sparse hex cell #{$cell_id} ({$room_id}) at resolution {$resolution} must define numeric metadata.global_offset_q.";
            }
            if (!array_key_exists('global_offset_r', $cell_metadata) || !is_numeric($cell_metadata['global_offset_r'])) {
              $result['errors'][] = "Sparse hex cell #{$cell_id} ({$room_id}) at resolution {$resolution} must define numeric metadata.global_offset_r.";
            }
            $normalization = trim((string) ($cell_metadata['normalization'] ?? ''));
            if ($normalization !== self::REQUIRED_SPARSE_NORMALIZATION) {
              $result['errors'][] = "Sparse hex cell #{$cell_id} ({$room_id}) at resolution {$resolution} must define metadata.normalization='" . self::REQUIRED_SPARSE_NORMALIZATION . "'.";
            }
            if (
              $cell_role === 'room_hex'
              && (
                !array_key_exists('room_entrance_global_q', $cell_metadata)
                || !array_key_exists('room_entrance_global_r', $cell_metadata)
                || !is_numeric($cell_metadata['room_entrance_global_q'])
                || !is_numeric($cell_metadata['room_entrance_global_r'])
              )
            ) {
              $result['errors'][] = "Sparse hex cell #{$cell_id} ({$room_id}) at resolution {$resolution} must define numeric metadata.room_entrance_global_q/room_entrance_global_r.";
            }
          }
        }
      }
      if ($cell_has_vertical_metrics) {
        $result['summary']['cells_with_vertical_metrics']++;
      }
      else {
        $result['summary']['cells_missing_vertical_metrics']++;
      }

      if ($room_required) {
        $anchor = is_array($anchor_by_room[$room_id] ?? NULL) ? $anchor_by_room[$room_id] : NULL;
        if ($anchor === NULL) {
          $result['errors'][] = "Sparse hex cell #{$cell_id} ({$room_id}) has no matching room anchor.";
        }
        else {
          $anchor_dungeon_id = trim((string) ($anchor['dungeon_id'] ?? ''));
          if ($anchor_dungeon_id !== '' && $dungeon_id !== '' && $anchor_dungeon_id !== $dungeon_id) {
            $result['errors'][] = "Sparse hex cell #{$cell_id} ({$room_id}) dungeon_id '{$dungeon_id}' does not match anchor dungeon_id '{$anchor_dungeon_id}'.";
          }
          $anchor_resolution = isset($anchor['h3_resolution']) ? (int) $anchor['h3_resolution'] : 0;
          if ($anchor_resolution !== $resolution) {
            $mismatch_key = $room_id . '|' . (string) $dungeon_id;
            $anchor_resolution_mismatch_by_room[$mismatch_key] = [
              'room_id' => $room_id,
              'dungeon_id' => $dungeon_id,
              'anchor_resolution' => $anchor_resolution,
              'cell_resolution' => $resolution,
            ];
          }
        }
      }

      $designation_suffix = $h3_index !== ''
        ? "h3:{$h3_index}"
        : "axial:{$source_q}:{$source_r}";
      $designation_key = implode('|', [
        (string) $resolution,
        $designation_suffix,
      ]);

      if (isset($cell_designation_map[$designation_key])) {
        $existing = $cell_designation_map[$designation_key];
        $collision = [
          'designation' => $designation_key,
          'left' => $existing,
          'right' => [
            'id' => $cell_id,
            'room_id' => $room_id,
            'dungeon_id' => $dungeon_id,
            'cell_role' => $cell_role,
          ],
        ];
        $result['items'][] = $collision;
        $result['summary']['collision_count']++;
        $result['errors'][] = "Sparse hex designation collision '{$designation_key}' between dungeon/room '{$existing['dungeon_id']}/{$existing['room_id']}' and '{$dungeon_id}/{$room_id}'.";
      }
      else {
        $cell_designation_map[$designation_key] = [
          'id' => $cell_id,
          'room_id' => $room_id,
          'dungeon_id' => $dungeon_id,
          'cell_role' => $cell_role,
        ];
        $scope_key = implode('|', [
          $dungeon_id !== '' ? $dungeon_id : '<missing-dungeon>',
          (string) $resolution,
        ]);
        if (!isset($hex_nodes_by_scope[$scope_key])) {
          $hex_nodes_by_scope[$scope_key] = [];
        }
        $axial_key = $source_q . ':' . $source_r;
        $existing_axial_node = is_array($hex_nodes_by_scope[$scope_key][$axial_key] ?? NULL)
          ? $hex_nodes_by_scope[$scope_key][$axial_key]
          : NULL;
        if ($existing_axial_node !== NULL) {
          $existing_designation = trim((string) ($existing_axial_node['designation'] ?? ''));
          if ($existing_designation !== '' && $existing_designation !== $designation_key) {
            $result['summary']['collision_count']++;
            $result['errors'][] = "Sparse hex axial collision '{$scope_key}|{$axial_key}' between designations '{$existing_designation}' and '{$designation_key}'.";
          }
        }
        $hex_nodes_by_scope[$scope_key][$axial_key] = [
          'designation' => $designation_key,
          'q' => $source_q,
          'r' => $source_r,
          'room_id' => $room_id,
        ];

        if ($room_required && strtoupper($room_id) !== self::ROOM_OWNERSHIP_NOT_APPLICABLE) {
          $room_scope_key = implode('|', [
            $dungeon_id !== '' ? $dungeon_id : '<missing-dungeon>',
            (string) $resolution,
            $room_id,
          ]);
          if (!isset($room_hex_nodes_by_scope[$room_scope_key])) {
            $room_hex_nodes_by_scope[$room_scope_key] = [];
          }
          $room_hex_nodes_by_scope[$room_scope_key][$axial_key] = ['q' => $source_q, 'r' => $source_r];

          if (
            is_array($cell_metadata)
            && $cell_role === 'room_hex'
            && array_key_exists('room_entrance_global_q', $cell_metadata)
            && array_key_exists('room_entrance_global_r', $cell_metadata)
            && is_numeric($cell_metadata['room_entrance_global_q'])
            && is_numeric($cell_metadata['room_entrance_global_r'])
          ) {
            if (!isset($room_entrance_coordinates_by_scope[$room_scope_key])) {
              $room_entrance_coordinates_by_scope[$room_scope_key] = [];
            }
            $room_entrance_key = (int) $cell_metadata['room_entrance_global_q'] . ':' . (int) $cell_metadata['room_entrance_global_r'];
            $room_entrance_coordinates_by_scope[$room_scope_key][$room_entrance_key] = TRUE;
          }
        }
      }

      if ($h3_index !== '') {
        $result['summary']['cells_with_h3_index']++;
      }
      else {
        $result['summary']['cells_without_h3_index']++;
      }
      $result['summary']['total_cells']++;
    }

    if ($res14_cells_missing_h3_index > 0) {
      $result['errors'][] = "Sparse hex res14 validation found {$res14_cells_missing_h3_index} cell row(s) without h3_index.";
    }
    if ($res14_cells_missing_lat_lng > 0) {
      $result['errors'][] = "Sparse hex res14 validation found {$res14_cells_missing_lat_lng} cell row(s) without center_latitude/center_longitude.";
    }
    if ($res15_cells_out_of_scope > 0) {
      $result['errors'][] = "Sparse hex validation found {$res15_cells_out_of_scope} resolution-15 cell row(s), but resolution 15 is out of scope for active generation validation.";
    }
    foreach ($anchor_resolution_mismatch_by_room as $mismatch) {
      if (!is_array($mismatch)) {
        continue;
      }
      $room_id = trim((string) ($mismatch['room_id'] ?? ''));
      $dungeon_id = trim((string) ($mismatch['dungeon_id'] ?? ''));
      $anchor_resolution = (int) ($mismatch['anchor_resolution'] ?? 0);
      $cell_resolution = (int) ($mismatch['cell_resolution'] ?? 0);
      $result['errors'][] = "Room '{$room_id}' in dungeon '{$dungeon_id}' has anchor resolution {$anchor_resolution} but mapped cell resolution {$cell_resolution}.";
    }

    foreach ($coordinate_ranges_by_scope as $scope_key => $range) {
      if (!is_array($range)) {
        continue;
      }
      $lat_span = (float) ($range['max_lat'] ?? 0.0) - (float) ($range['min_lat'] ?? 0.0);
      $lng_span = (float) ($range['max_lng'] ?? 0.0) - (float) ($range['min_lng'] ?? 0.0);
      if ($lat_span > self::MAX_COORDINATE_FRAME_SPAN_DEGREES || $lng_span > self::MAX_COORDINATE_FRAME_SPAN_DEGREES) {
        $result['errors'][] = "Sparse hex scope '{$scope_key}' exceeds coordinate-frame span contract (lat_span={$lat_span}, lng_span={$lng_span}).";
      }
    }

    foreach ($room_hex_nodes_by_scope as $room_scope_key => $nodes_by_axial) {
      if (!is_array($nodes_by_axial) || $nodes_by_axial === []) {
        continue;
      }
      $entrance_coordinates = is_array($room_entrance_coordinates_by_scope[$room_scope_key] ?? NULL)
        ? array_keys($room_entrance_coordinates_by_scope[$room_scope_key])
        : [];
      if ($entrance_coordinates === []) {
        $result['errors'][] = "Room scope '{$room_scope_key}' is missing metadata.room_entrance_global_q/room_entrance_global_r ingress coordinates.";
        continue;
      }
      if (count($entrance_coordinates) !== 1) {
        $result['errors'][] = "Room scope '{$room_scope_key}' has multiple ingress coordinates (" . implode(', ', $entrance_coordinates) . "); expected exactly one.";
        continue;
      }
      $ingress_key = (string) $entrance_coordinates[0];
      if (!isset($nodes_by_axial[$ingress_key])) {
        $result['errors'][] = "Room scope '{$room_scope_key}' ingress coordinate '{$ingress_key}' is not mapped to a room_hex cell.";
      }
    }

    $edge_keys = [];
    foreach ($hex_nodes_by_scope as $scope_key => $nodes_by_axial) {
      if (!is_array($nodes_by_axial) || $nodes_by_axial === []) {
        continue;
      }
      foreach ($nodes_by_axial as $axial_key => $node) {
        if (!is_array($node)) {
          continue;
        }
        $q = isset($node['q']) ? (int) $node['q'] : 0;
        $r = isset($node['r']) ? (int) $node['r'] : 0;
        $designation = trim((string) ($node['designation'] ?? ''));
        if ($designation === '') {
          continue;
        }
        foreach (self::ROOM_HEX_NEIGHBOR_OFFSETS as $offset) {
          $neighbor_key = ($q + $offset[0]) . ':' . ($r + $offset[1]);
          $neighbor = is_array($nodes_by_axial[$neighbor_key] ?? NULL) ? $nodes_by_axial[$neighbor_key] : NULL;
          if ($neighbor === NULL) {
            continue;
          }
          $neighbor_designation = trim((string) ($neighbor['designation'] ?? ''));
          if ($neighbor_designation === '' || $neighbor_designation === $designation) {
            continue;
          }
          $pair = [$designation, $neighbor_designation];
          sort($pair, SORT_STRING);
          $edge_keys[$scope_key . '|' . $pair[0] . '|' . $pair[1]] = TRUE;
        }
      }
    }

    $result['summary']['hex_graph_nodes'] = count($cell_designation_map);
    $result['summary']['hex_graph_edges'] = count($edge_keys);

    foreach ($anchor_by_room as $room_id => $anchor) {
      if (!is_array($anchor)) {
        continue;
      }
      $dungeon_id = trim((string) ($anchor['dungeon_id'] ?? ''));
      if ($dungeon_id === '') {
        continue;
      }

      $room_scope_key = $dungeon_id . '|' . (string) self::ACTIVE_GENERATION_RESOLUTION . '|' . $room_id;
      $nodes_by_axial = is_array($room_hex_nodes_by_scope[$room_scope_key] ?? NULL)
        ? $room_hex_nodes_by_scope[$room_scope_key]
        : [];
      $node_count = count($nodes_by_axial);
      $result['summary']['res14_rooms_total']++;
      $result['summary']['res14_nodes'] += $node_count;

      $room_edge_keys = [];
      $node_degree = [];
      foreach ($nodes_by_axial as $axial_key => $node) {
        $q = isset($node['q']) ? (int) $node['q'] : 0;
        $r = isset($node['r']) ? (int) $node['r'] : 0;
        $node_degree[$axial_key] = $node_degree[$axial_key] ?? 0;
        foreach (self::ROOM_HEX_NEIGHBOR_OFFSETS as $offset) {
          $neighbor_key = ($q + $offset[0]) . ':' . ($r + $offset[1]);
          if (!isset($nodes_by_axial[$neighbor_key])) {
            continue;
          }
          $pair = [$axial_key, $neighbor_key];
          sort($pair, SORT_STRING);
          $edge_key = $pair[0] . '|' . $pair[1];
          if (!isset($room_edge_keys[$edge_key])) {
            $room_edge_keys[$edge_key] = TRUE;
            $node_degree[$pair[0]] = ($node_degree[$pair[0]] ?? 0) + 1;
            $node_degree[$pair[1]] = ($node_degree[$pair[1]] ?? 0) + 1;
          }
        }
      }
      $edge_count = count($room_edge_keys);
      $result['summary']['res14_edges'] += $edge_count;

      $room_errors = [];
      if ($node_count < 4) {
        $room_errors[] = "Room '{$room_id}' in dungeon '{$dungeon_id}' must define at least four res14 hex nodes.";
      }
      if ($node_count > 1 && $edge_count === 0) {
        $room_errors[] = "Room '{$room_id}' in dungeon '{$dungeon_id}' has res14 nodes but no connector edges.";
      }

      if ($node_count > 0) {
        $node_keys = array_keys($nodes_by_axial);
        $visited = [];
        $queue = [reset($node_keys)];
        while ($queue !== []) {
          $current = array_shift($queue);
          if (!is_string($current) || isset($visited[$current]) || !isset($nodes_by_axial[$current])) {
            continue;
          }
          $visited[$current] = TRUE;
          $node = $nodes_by_axial[$current];
          $q = isset($node['q']) ? (int) $node['q'] : 0;
          $r = isset($node['r']) ? (int) $node['r'] : 0;
          foreach (self::ROOM_HEX_NEIGHBOR_OFFSETS as $offset) {
            $neighbor_key = ($q + $offset[0]) . ':' . ($r + $offset[1]);
            if (isset($nodes_by_axial[$neighbor_key]) && !isset($visited[$neighbor_key])) {
              $queue[] = $neighbor_key;
            }
          }
        }
        if (count($visited) !== $node_count) {
          $room_errors[] = "Room '{$room_id}' in dungeon '{$dungeon_id}' has a disconnected res14 hex graph.";
        }
      }

      $isolated_nodes = 0;
      foreach ($node_degree as $degree) {
        if ((int) $degree === 0 && $node_count > 1) {
          $isolated_nodes++;
        }
      }
      if ($isolated_nodes > 0) {
        $room_errors[] = "Room '{$room_id}' in dungeon '{$dungeon_id}' has {$isolated_nodes} isolated res14 hex node(s) without connectors.";
      }

      $result['items'][] = [
        'type' => 'room_res14_graph',
        'dungeon_id' => $dungeon_id,
        'room_id' => $room_id,
        'node_count' => $node_count,
        'edge_count' => $edge_count,
        'valid' => $room_errors === [],
      ];

      if ($room_errors === []) {
        $result['summary']['res14_rooms_passing']++;
      }
      else {
        $result['summary']['res14_rooms_failing']++;
        foreach ($room_errors as $room_error) {
          $result['errors'][] = $room_error;
        }
      }
    }

    $result['errors'] = array_values(array_unique($result['errors']));
    return $result;
  }

  /**
   * Validate canonical room contents contract shape and references.
   *
   * @param array<string, mixed> $contents_data
   *   Room contents payload.
   * @param array<string, bool> $registry_content_id_map
   *   Canonical registry content IDs keyed by ID.
   *
   * @return array<int, string>
   *   Validation errors.
   */
  private function validateCanonicalRoomContentsContract(array $contents_data, array $registry_content_id_map): array {
    $errors = [];
    foreach (self::ROOM_CONTENT_REQUIRED_BUCKETS as $bucket) {
      if (!array_key_exists($bucket, $contents_data)) {
        $errors[] = "contents_data.{$bucket} is required and must be an array.";
        continue;
      }
      if (!is_array($contents_data[$bucket])) {
        $errors[] = "contents_data.{$bucket} must be an array.";
      }
    }

    foreach ($contents_data as $bucket => $entries) {
      if (!is_array($entries)) {
        continue;
      }
      foreach ($entries as $index => $entry) {
        $path = "contents_data.{$bucket}[{$index}]";
        if (in_array((string) $bucket, self::ROOM_CONTENT_REGISTRY_REFERENCE_BUCKETS, TRUE)) {
          if (!is_array($entry)) {
            $errors[] = "{$path} must be an object with content_id.";
            continue;
          }
          $content_id = trim((string) ($entry['content_id'] ?? ''));
          if ($content_id === '') {
            $errors[] = "{$path}.content_id is required.";
            continue;
          }
          if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $content_id)) {
            $errors[] = "{$path}.content_id '{$content_id}' does not match canonical id pattern.";
            continue;
          }
          if (!isset($registry_content_id_map[$content_id])) {
            $errors[] = "{$path}.content_id '{$content_id}' does not resolve in canonical content registry.";
          }
          continue;
        }

        if (is_string($entry)) {
          if (trim($entry) === '') {
            $errors[] = "{$path} must not be empty.";
          }
          continue;
        }

        if (is_array($entry)) {
          if (array_key_exists('content_id', $entry)) {
            $content_id = trim((string) $entry['content_id']);
            if ($content_id === '') {
              $errors[] = "{$path}.content_id must not be empty.";
              continue;
            }
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $content_id)) {
              $errors[] = "{$path}.content_id '{$content_id}' does not match canonical id pattern.";
              continue;
            }
            if (
              in_array((string) $bucket, self::ROOM_CONTENT_REGISTRY_REFERENCE_BUCKETS, TRUE) &&
              !isset($registry_content_id_map[$content_id])
            ) {
              $errors[] = "{$path}.content_id '{$content_id}' does not resolve in canonical content registry.";
            }
            continue;
          }
          $label = trim((string) ($entry['label'] ?? $entry['name'] ?? ''));
          if ($label === '') {
            $errors[] = "{$path} must provide a non-empty string, name, label, or content_id.";
          }
          continue;
        }

        $errors[] = "{$path} must be a string or object.";
      }
    }

    return $errors;
  }

  /**
   * Validate room layout point arrays against known coordinates.
   *
   * @param array<int, mixed> $points
   *   Layout points.
   * @param string $path
   *   Point path name.
   * @param array<string, array{q:int,r:int}> $hex_coordinate_map
   *   Coordinate map from layout hexes.
   * @param array<int, string> $errors
   *   Error collection (mutated by reference).
   *
   * @return array<int, array{q:int,r:int}>
   *   Validated points.
   */
  private function validateCanonicalRoomLayoutPoints(array $points, string $path, array $hex_coordinate_map, array &$errors): array {
    $validated = [];
    foreach ($points as $index => $point) {
      if (!is_array($point)) {
        $errors[] = "layout_data.{$path}[{$index}] must be an object with q/r coordinates.";
        continue;
      }
      $q = $point['q'] ?? NULL;
      $r = $point['r'] ?? NULL;
      if (!is_int($q) || !is_int($r)) {
        $errors[] = "layout_data.{$path}[{$index}] must include integer q/r coordinates.";
        continue;
      }
      $key = $q . ':' . $r;
      if (!isset($hex_coordinate_map[$key])) {
        $errors[] = "layout_data.{$path}[{$index}] coordinate '{$key}' does not map to any layout hex.";
        continue;
      }
      $validated[] = ['q' => $q, 'r' => $r];
    }

    return $validated;
  }

  /**
   * Validate canonical hex object contract.
   *
   * @param array<string, mixed> $object
   *   Hex object payload.
   * @param int $hex_index
   *   Parent hex index.
   * @param int $object_index
   *   Object index within hex.
   *
   * @return array<int, string>
   *   Validation errors.
   */
  private function validateCanonicalRoomHexObjectContract(array $object, int $hex_index, int $object_index): array {
    $errors = [];
    $path = "layout_data.hexes[{$hex_index}].objects[{$object_index}]";
    $category = trim((string) ($object['category'] ?? ''));
    if ($category === '') {
      $errors[] = "{$path}.category is required.";
    }
    elseif (!in_array($category, self::ROOM_HEX_ALLOWED_OBJECT_CATEGORIES, TRUE)) {
      $errors[] = "{$path}.category '{$category}' is not allowed.";
    }
    if (!array_key_exists('passable', $object) || !is_bool($object['passable'])) {
      $errors[] = "{$path}.passable must be a boolean.";
    }
    if (!array_key_exists('blocks_movement', $object) || !is_bool($object['blocks_movement'])) {
      $errors[] = "{$path}.blocks_movement must be a boolean.";
    }
    if (!array_key_exists('label', $object) || !is_string($object['label']) || trim($object['label']) === '') {
      $errors[] = "{$path}.label must be a non-empty string.";
    }
    if (!array_key_exists('object_id', $object) || !is_string($object['object_id']) || trim($object['object_id']) === '') {
      $errors[] = "{$path}.object_id must be a non-empty string.";
    }

    return $errors;
  }

  /**
   * Determine whether any entry point can reach any exit point.
   *
   * @param array<int, string> $entry_keys
   *   Entry coordinate keys.
   * @param array<int, string> $exit_keys
   *   Exit coordinate keys.
   * @param array<string, bool> $traversable_hexes
   *   Traversable coordinate map.
   */
  private function hasTraversableRoomPath(array $entry_keys, array $exit_keys, array $traversable_hexes): bool {
    $exit_set = array_fill_keys($exit_keys, TRUE);
    $queue = new \SplQueue();
    $visited = [];
    foreach ($entry_keys as $entry_key) {
      if (!isset($traversable_hexes[$entry_key])) {
        continue;
      }
      $queue->enqueue($entry_key);
      $visited[$entry_key] = TRUE;
    }
    if ($queue->isEmpty()) {
      return FALSE;
    }

    while (!$queue->isEmpty()) {
      $current = $queue->dequeue();
      if (isset($exit_set[$current])) {
        return TRUE;
      }
      [$q, $r] = array_map('intval', explode(':', $current, 2));
      foreach (self::ROOM_HEX_NEIGHBOR_OFFSETS as $offset) {
        $neighbor_key = ($q + $offset[0]) . ':' . ($r + $offset[1]);
        if (!isset($traversable_hexes[$neighbor_key]) || isset($visited[$neighbor_key])) {
          continue;
        }
        $visited[$neighbor_key] = TRUE;
        $queue->enqueue($neighbor_key);
      }
    }

    return FALSE;
  }

  /**
   * Determine whether a room ID is from blocked prompt-derived prefixes.
   */
  private function isBlockedPromptDerivedRoomId(string $room_id): bool {
    return preg_match(self::BLOCKED_PROMPT_DERIVED_ROOM_ID_PATTERN, $room_id) === 1;
  }

  /**
   * Validate item payload against the canonical runtime contract.
   *
   * This path intentionally avoids file-backed schema references so item
   * validation can run from database-authoritative runtime rules.
   *
   * @param array<string, mixed> $item
   *   Item payload.
   *
   * @return array<int, string>
   *   Validation errors.
   */
  private function validateCanonicalItemDefinition(array $item): array {
    $errors = [];
    $allowed_fields = [
      'schema_version',
      'item_id',
      'content_id',
      'name',
      'item_type',
      'type',
      'level',
      'rarity',
      'traits',
      'description',
      'price',
      'bulk',
      'hands',
      'weapon_stats',
      'armor_stats',
      'shield_stats',
      'consumable_stats',
      'container_stats',
      'inventory_metadata',
      'magic_properties',
      'ai_generation',
      'created_at',
      'updated_at',
      'item_category',
      'ancestry_granted',
      'sell_taboo',
      'sell_taboo_message',
    ];

    foreach (array_keys($item) as $key) {
      if (!in_array((string) $key, $allowed_fields, TRUE)) {
        $errors[] = 'Unknown property: ' . $key;
      }
    }

    $schema_version = trim((string) ($item['schema_version'] ?? ''));
    if ($schema_version === '') {
      $errors[] = 'Missing required field: schema_version';
    }
    elseif (!preg_match('/^\d+\.\d+\.\d+$/', $schema_version)) {
      $errors[] = "Field 'schema_version' does not match required pattern";
    }

    $item_id = trim((string) ($item['item_id'] ?? $item['content_id'] ?? ''));
    if ($item_id === '') {
      $errors[] = 'Missing required field: item_id';
    }
    elseif (!preg_match('/^[a-z0-9]+(?:[_-][a-z0-9]+)*$/', $item_id)) {
      $errors[] = "Field 'item_id' does not match required pattern";
    }

    $name = $item['name'] ?? NULL;
    if (!is_string($name) || trim($name) === '') {
      $errors[] = 'Missing required field: name';
    }
    elseif (strlen($name) > 200) {
      $errors[] = "Field 'name' is too long (maximum 200 characters)";
    }

    $item_type = strtolower(trim((string) ($item['item_type'] ?? $item['type'] ?? '')));
    $allowed_item_types = [
      'weapon',
      'armor',
      'shield',
      'consumable',
      'alchemical',
      'potion',
      'scroll',
      'wand',
      'talisman',
      'worn_item',
      'held_item',
      'material',
      'adventuring_gear',
      'relic',
      'artifact',
    ];
    if ($item_type === '') {
      $errors[] = 'Missing required field: item_type';
    }
    elseif (!in_array($item_type, $allowed_item_types, TRUE)) {
      $errors[] = "Field 'item_type' must be one of: " . implode(', ', $allowed_item_types);
    }

    if (!array_key_exists('level', $item)) {
      $errors[] = 'Missing required field: level';
    }
    elseif (!is_int($item['level'])) {
      $errors[] = "Field 'level' has invalid type. Expected integer, got " . $this->resolveJsonType($item['level']);
    }
    elseif ($item['level'] < 0) {
      $errors[] = "Field 'level' is below minimum value 0";
    }
    elseif ($item['level'] > 25) {
      $errors[] = "Field 'level' is above maximum value 25";
    }

    $rarity = strtolower(trim((string) ($item['rarity'] ?? '')));
    $allowed_rarities = ['common', 'uncommon', 'rare', 'epic', 'legendary'];
    if ($rarity === '') {
      $errors[] = 'Missing required field: rarity';
    }
    elseif (!in_array($rarity, $allowed_rarities, TRUE)) {
      $errors[] = "Field 'rarity' must be one of: " . implode(', ', $allowed_rarities);
    }

    if (array_key_exists('traits', $item)) {
      if (!is_array($item['traits'])) {
        $errors[] = "Field 'traits' has invalid type. Expected array, got " . $this->resolveJsonType($item['traits']);
      }
      else {
        $seen_traits = [];
        foreach ($item['traits'] as $index => $trait) {
          if (!is_string($trait) || trim($trait) === '') {
            $errors[] = "Field 'traits[{$index}]' has invalid type. Expected string, got " . $this->resolveJsonType($trait);
            continue;
          }
          $canonical_trait = trim($trait);
          if (isset($seen_traits[$canonical_trait])) {
            $errors[] = "Field 'traits' must contain unique items";
            break;
          }
          $seen_traits[$canonical_trait] = TRUE;
        }
        if (count($item['traits']) > 20) {
          $errors[] = "Field 'traits' has too many items (maximum 20)";
        }
      }
    }

    if (array_key_exists('description', $item)) {
      if (!is_string($item['description'])) {
        $errors[] = "Field 'description' has invalid type. Expected string, got " . $this->resolveJsonType($item['description']);
      }
      elseif (strlen($item['description']) > 2000) {
        $errors[] = "Field 'description' is too long (maximum 2000 characters)";
      }
    }

    if (array_key_exists('price', $item)) {
      if (!is_array($item['price'])) {
        $errors[] = "Field 'price' has invalid type. Expected object, got " . $this->resolveJsonType($item['price']);
      }
      else {
        $errors = array_merge($errors, $this->validateItemPrice($item['price']));
      }
    }

    if (array_key_exists('bulk', $item)) {
      if (!is_string($item['bulk'])) {
        $errors[] = "Field 'bulk' has invalid type. Expected string, got " . $this->resolveJsonType($item['bulk']);
      }
      elseif (!preg_match('/^(\d+(\.\d+)?|L|-)$/', $item['bulk'])) {
        $errors[] = "Field 'bulk' does not match required pattern";
      }
    }

    if (array_key_exists('hands', $item)) {
      if ($item['hands'] === NULL) {
        // Canonical template/library records may use null to represent 0 hands.
      }
      elseif (is_int($item['hands'])) {
        if (!in_array($item['hands'], [0, 1, 2], TRUE)) {
          $errors[] = "Field 'hands' must be one of: 0, 1, 1+, 2";
        }
      }
      elseif (!is_string($item['hands'])) {
        $errors[] = "Field 'hands' has invalid type. Expected string|integer|null, got " . $this->resolveJsonType($item['hands']);
      }
      elseif (!in_array($item['hands'], ['0', '1', '1+', '2'], TRUE)) {
        $errors[] = "Field 'hands' must be one of: 0, 1, 1+, 2";
      }
    }

    foreach (['weapon_stats', 'armor_stats', 'shield_stats', 'consumable_stats', 'container_stats', 'inventory_metadata', 'magic_properties'] as $stats_field) {
      if (array_key_exists($stats_field, $item) && $item[$stats_field] !== NULL && !is_array($item[$stats_field])) {
        $errors[] = "Field '{$stats_field}' has invalid type. Expected object|null, got " . $this->resolveJsonType($item[$stats_field]);
      }
    }

    if (array_key_exists('ai_generation', $item) && !is_array($item['ai_generation'])) {
      $errors[] = "Field 'ai_generation' has invalid type. Expected object, got " . $this->resolveJsonType($item['ai_generation']);
    }

    foreach (['created_at', 'updated_at'] as $datetime_field) {
      if (!array_key_exists($datetime_field, $item)) {
        continue;
      }
      if (!is_string($item[$datetime_field])) {
        $errors[] = "Field '{$datetime_field}' has invalid type. Expected string, got " . $this->resolveJsonType($item[$datetime_field]);
        continue;
      }
      if (!$this->validateDateTimeString($item[$datetime_field])) {
        $errors[] = "Field '{$datetime_field}' must be a valid date-time string";
      }
    }

    if ($item_type !== '') {
      $errors = array_merge($errors, $this->validateItemSpecificContractFields($item, $item_type));
    }

    return $errors;
  }

  /**
   * Validate item-type-specific required structures.
   *
   * @param array<string, mixed> $item
   *   Item payload.
   * @param string $item_type
   *   Canonical item type.
   *
   * @return array<int, string>
   *   Validation errors.
   */
  private function validateItemSpecificContractFields(array $item, string $item_type): array {
    $errors = [];

    if ($item_type === 'weapon' && (!isset($item['weapon_stats']) || !is_array($item['weapon_stats']))) {
      $errors[] = "Missing required field: weapon_stats when item_type is weapon";
    }

    if ($item_type === 'armor' && (!isset($item['armor_stats']) || !is_array($item['armor_stats']))) {
      $errors[] = "Missing required field: armor_stats when item_type is armor";
    }

    if ($item_type === 'shield') {
      if (!isset($item['shield_stats']) || !is_array($item['shield_stats'])) {
        $errors[] = "Missing required field: shield_stats when item_type is shield";
      }
      else {
        foreach (['ac_bonus', 'hardness', 'hp', 'bt'] as $required_field) {
          if (!array_key_exists($required_field, $item['shield_stats'])) {
            $errors[] = "Missing required field: shield_stats.{$required_field}";
            continue;
          }
          if (!is_int($item['shield_stats'][$required_field])) {
            $errors[] = "Field 'shield_stats.{$required_field}' has invalid type. Expected integer, got " . $this->resolveJsonType($item['shield_stats'][$required_field]);
          }
        }
      }
    }

    if (in_array($item_type, ['potion', 'scroll', 'talisman'], TRUE)) {
      if (!isset($item['consumable_stats']) || !is_array($item['consumable_stats'])) {
        $errors[] = "Missing required field: consumable_stats when item_type is {$item_type}";
      }
    }

    return $errors;
  }

  /**
   * Validate canonical price object.
   *
   * @param array<string, mixed> $price
   *   Price payload.
   *
   * @return array<int, string>
   *   Validation errors.
   */
  private function validateItemPrice(array $price): array {
    $errors = [];
    $allowed_fields = ['cp', 'sp', 'gp', 'pp'];

    foreach (array_keys($price) as $field) {
      if (!in_array((string) $field, $allowed_fields, TRUE)) {
        $errors[] = 'Unknown property: price.' . $field;
      }
    }

    foreach ($allowed_fields as $field) {
      if (!array_key_exists($field, $price)) {
        continue;
      }
      if (!is_int($price[$field])) {
        $errors[] = "Field 'price.{$field}' has invalid type. Expected integer, got " . $this->resolveJsonType($price[$field]);
        continue;
      }
      if ($price[$field] < 0) {
        $errors[] = "Field 'price.{$field}' is below minimum value 0";
      }
    }

    return $errors;
  }

  /**
   * Validate RFC3339 timestamp strings.
   */
  private function validateDateTimeString(string $value): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $value)) {
      return FALSE;
    }

    return strtotime($value) !== FALSE;
  }

  /**
   * Validate canonical item fields against DB registry content when present.
   *
   * @param array<string, mixed> $item
   *   Item payload.
   * @param string $item_id
   *   Canonical item identifier.
   * @param string $item_type
   *   Canonical item type.
   *
   * @return array<int, string>
   *   Validation errors.
   */
  private function validateItemDefinitionAgainstDatabase(array $item, string $item_id, string $item_type): array {
    if ($this->database === NULL) {
      return ['Item validation requires database authority but database service is unavailable.'];
    }

    if ($item_id === '') {
      return ['Item validation requires canonical item_id/content_id for DB authority lookup.'];
    }

    $schema = $this->database->schema();
    if (!$schema->tableExists('dungeoncrawler_content_registry')) {
      return ['Item validation requires canonical registry table dungeoncrawler_content_registry.'];
    }

    try {
      $row = $this->database->select('dungeoncrawler_content_registry', 'r')
        ->fields('r', ['content_id', 'name', 'level', 'rarity', 'schema_data'])
        ->condition('content_type', 'item')
        ->condition('content_id', $item_id)
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();

      if (!is_array($row)) {
        return ["Canonical DB contract not found for item '{$item_id}'."];
      }

      $schema_data = json_decode((string) ($row['schema_data'] ?? ''), TRUE);
      if (!is_array($schema_data)) {
        return ["Canonical DB item '{$item_id}' has invalid schema_data JSON"];
      }

      $errors = [];
      $db_item_type = strtolower(trim((string) ($schema_data['item_type'] ?? $schema_data['type'] ?? '')));
      if ($db_item_type !== '' && $db_item_type !== $item_type) {
        $errors[] = "Field 'item_type' does not match canonical DB contract for item '{$item_id}'";
      }

      $db_level = $schema_data['level'] ?? $row['level'] ?? NULL;
      if (is_numeric($db_level) && (int) $db_level !== (int) ($item['level'] ?? 0)) {
        $errors[] = "Field 'level' does not match canonical DB contract for item '{$item_id}'";
      }

      $db_rarity = strtolower(trim((string) ($schema_data['rarity'] ?? $row['rarity'] ?? '')));
      $item_rarity = strtolower(trim((string) ($item['rarity'] ?? '')));
      if ($db_rarity !== '' && $db_rarity !== $item_rarity) {
        $errors[] = "Field 'rarity' does not match canonical DB contract for item '{$item_id}'";
      }

      $db_name = trim((string) ($schema_data['name'] ?? $row['name'] ?? ''));
      $item_name = trim((string) ($item['name'] ?? ''));
      if ($db_name !== '' && $db_name !== $item_name) {
        $errors[] = "Field 'name' does not match canonical DB contract for item '{$item_id}'";
      }

      return $errors;
    }
    catch (\Throwable $exception) {
      $this->logger->error('Item DB contract lookup failed for {item_id}: {message}', [
        'item_id' => $item_id,
        'message' => $exception->getMessage(),
      ]);
      return ["Item DB contract lookup failed for '{$item_id}': " . $exception->getMessage()];
    }
  }

  /**
   * Validate a normalized storyline definition against the canonical contract.
   */
  public function validateStorylineDefinition(array $definition): array {
    return $this->validateAgainstContract($definition, 'storyline_definition');
  }

  /**
   * Validate a normalized storyline bootstrap request payload.
   */
  public function validateStorylineBootstrapRequest(array $request): array {
    return $this->validateAgainstContract($request, 'storyline_bootstrap_request');
  }

  /**
   * Validate a queued storyline expansion job payload.
   */
  public function validateStorylineExpansionJob(array $payload): array {
    return $this->validateAgainstContract($payload, 'storyline_expansion_job');
  }

  /**
   * Validate stored storyline runtime state against the canonical questline contract.
   */
  public function validateStorylineRuntime(array $runtime): array {
    return $this->validateAgainstContract($runtime, 'storyline_runtime');
  }

  /**
   * Validate a hexmap quest summary payload against the canonical contract.
   */
  public function validateQuestSummary(array $summary): array {
    return $this->validateAgainstContract($summary, 'quest_summary');
  }

  /**
   * Validate a room-chat quest update payload against the canonical contract.
   */
  public function validateQuestUpdate(array $update): array {
    return $this->validateAgainstContract($update, 'quest_update');
  }

  /**
   * Validate the canonical objective type options registry.
   */
  public function validateObjectiveTypeOptions(array $payload): array {
    return $this->validateAgainstContract($payload, 'objective_type_options');
  }

  /**
   * Validate the canonical NPC quest-giver policy registry.
   */
  public function validateNpcQuestGiverPolicies(array $payload): array {
    return $this->validateAgainstContract($payload, 'npc_quest_giver_policies');
  }

  /**
   * Validate a standardized character dialogue payload.
   */
  public function validateCharacterDialogue(array $dialogue): array {
    return $this->validateAgainstContract($dialogue, 'character_dialogue');
  }

  /**
   * Validate a standardized GM room response payload.
   */
  public function validateGmRoomResponse(array $response): array {
    return $this->validateAgainstContract($response, 'gm_room_response');
  }

  /**
   * Validate a standardized room turn harness result payload.
   */
  public function validateRoomTurnHarness(array $payload): array {
    return $this->validateAgainstContract($payload, 'room_turn_harness');
  }

  /**
   * Validate a standardized room-chat response envelope.
   */
  public function validateRoomChatResponse(array $payload): array {
    return $this->validateAgainstContract($payload, 'room_chat_response');
  }

  /**
   * Validate a standardized queued room continuation envelope.
   */
  public function validateQueuedRoomContinuation(array $payload): array {
    return $this->validateAgainstContract($payload, 'queued_room_continuation');
  }

  /**
   * Validate a canonical navigation action payload.
   */
  public function validateNavigationAction(array $payload): array {
    return $this->validateAgainstContract($payload, 'navigation');
  }

  /**
   * Validate a canonical navigation receipt payload.
   */
  public function validateNavigationReceipt(array $payload): array {
    return $this->validateAgainstContract($payload, 'navigation_receipt');
  }

  /**
   * Return the canonical data-contract registry.
   */
  public function getContractRegistry(): array {
    if ($this->contractRegistry !== NULL) {
      return $this->contractRegistry;
    }

    $registry_path = $this->schemaBasePath . '/contract_registry.json';
    if (!file_exists($registry_path)) {
      $this->logger->error('Contract registry file not found: {path}', ['path' => $registry_path]);
      $this->contractRegistry = [];
      return $this->contractRegistry;
    }

    $registry_content = file_get_contents($registry_path);
    $registry = json_decode((string) $registry_content, TRUE);
    if (!is_array($registry) || !is_array($registry['contracts'] ?? NULL)) {
      $this->logger->error('Invalid contract registry file: {path}', ['path' => $registry_path]);
      $this->contractRegistry = [];
      return $this->contractRegistry;
    }

    $this->contractRegistry = $registry['contracts'];
    return $this->contractRegistry;
  }

  /**
   * Resolve the schema path for a registered contract id.
   */
  public function getContractSchemaPath(string $contract_id): ?string {
    $registry = $this->getContractRegistry();
    $entry = is_array($registry[$contract_id] ?? NULL) ? $registry[$contract_id] : NULL;
    if (is_array($entry) && trim((string) ($entry['validator'] ?? '')) !== '') {
      return NULL;
    }
    $schema_filename = trim((string) ($entry['schema'] ?? ''));
    if ($schema_filename === '') {
      return NULL;
    }

    return $this->schemaBasePath . '/' . $schema_filename;
  }

  /**
   * Resolve a registered validator method for a contract id.
   */
  public function getContractValidator(string $contract_id): ?string {
    $registry = $this->getContractRegistry();
    $entry = is_array($registry[$contract_id] ?? NULL) ? $registry[$contract_id] : NULL;
    $validator = trim((string) ($entry['validator'] ?? ''));
    if ($validator === '') {
      return NULL;
    }

    return $validator;
  }

  /**
   * Validate data against a registered contract id.
   */
  private function validateAgainstContract(array $data, string $contract_id): array {
    $validator = $this->getContractValidator($contract_id);
    if ($validator !== NULL) {
      if (!method_exists($this, $validator)) {
        $this->logger->error('Unknown contract validator for {contract_id}: {validator}', [
          'contract_id' => $contract_id,
          'validator' => $validator,
        ]);
        return ['valid' => FALSE, 'errors' => ["Unknown contract validator: {$contract_id}.{$validator}"]];
      }

      $result = $this->{$validator}($data);
      if (!is_array($result) || !array_key_exists('valid', $result) || !array_key_exists('errors', $result)) {
        $this->logger->error('Invalid contract validator result for {contract_id}: {validator}', [
          'contract_id' => $contract_id,
          'validator' => $validator,
        ]);
        return ['valid' => FALSE, 'errors' => ["Invalid contract validator result: {$contract_id}.{$validator}"]];
      }

      return $result;
    }

    $schema_path = $this->getContractSchemaPath($contract_id);
    if ($schema_path === NULL) {
      $this->logger->error('Unknown contract id: {contract_id}', ['contract_id' => $contract_id]);
      return ['valid' => FALSE, 'errors' => ["Unknown contract id: {$contract_id}"]];
    }

    return $this->validateAgainstSchemaFile($data, $schema_path);
  }

  /**
   * Validate data against a fragment inside a registered contract schema.
   *
   * @param array<int, string> $fragment_path
   *   Nested key path to the schema fragment.
   */
  private function validateAgainstContractFragment(array $data, string $contract_id, array $fragment_path): array {
    $schema_path = $this->getContractSchemaPath($contract_id);
    if ($schema_path === NULL) {
      $this->logger->error('Unknown contract id: {contract_id}', ['contract_id' => $contract_id]);
      return ['valid' => FALSE, 'errors' => ["Unknown contract id: {$contract_id}"]];
    }

    if (!file_exists($schema_path)) {
      $this->logger->error('Schema file not found: {path}', ['path' => $schema_path]);
      return ['valid' => FALSE, 'errors' => ["Schema file not found: {$schema_path}"]];
    }

    $schema_content = file_get_contents($schema_path);
    $schema = json_decode((string) $schema_content, TRUE);
    if (!is_array($schema)) {
      $this->logger->error('Invalid schema file: {path}', ['path' => $schema_path]);
      return ['valid' => FALSE, 'errors' => ["Invalid schema file: {$schema_path}"]];
    }

    $fragment = $this->resolveSchemaFragment($schema, $fragment_path);
    if (!is_array($fragment)) {
      $joined_path = implode('.', $fragment_path);
      $this->logger->error('Schema fragment not found: {contract_id}.{path}', [
        'contract_id' => $contract_id,
        'path' => $joined_path,
      ]);
      return ['valid' => FALSE, 'errors' => ["Schema fragment not found: {$contract_id}.{$joined_path}"]];
    }

    $errors = $this->validateValueAgainstSchema($data, $fragment, '', $schema);
    return ['valid' => empty($errors), 'errors' => $errors];
  }

  /**
   * Validate data against a schema file path.
   */
  private function validateAgainstSchemaFile(array $data, string $schema_path): array {
    if (!file_exists($schema_path)) {
      $this->logger->error('Schema file not found: {path}', ['path' => $schema_path]);
      return ['valid' => FALSE, 'errors' => ["Schema file not found: {$schema_path}"]];
    }

    $schema_content = file_get_contents($schema_path);
    $schema = json_decode((string) $schema_content, TRUE);

    if (!is_array($schema)) {
      $this->logger->error('Invalid schema file: {path}', ['path' => $schema_path]);
      return ['valid' => FALSE, 'errors' => ["Invalid schema file: {$schema_path}"]];
    }

    $errors = $this->validateValueAgainstSchema($data, $schema, '', $schema);
    return ['valid' => empty($errors), 'errors' => $errors];
  }

  /**
   * Resolve a nested schema fragment by path.
   *
   * @param array<int, string> $fragment_path
   *   Nested key path to the schema fragment.
   */
  private function resolveSchemaFragment(array $schema, array $fragment_path): ?array {
    $fragment = $schema;
    foreach ($fragment_path as $segment) {
      if (!is_array($fragment) || !array_key_exists($segment, $fragment)) {
        return NULL;
      }
      $fragment = $fragment[$segment];
    }

    return is_array($fragment) ? $fragment : NULL;
  }

  /**
   * Recursively validate a value against a schema fragment.
   *
   * @param mixed $value
   *   Value to validate.
   * @param array $schema
   *   Schema fragment.
   * @param string $field_path
   *   Dot-notated field path.
   *
   * @return array<int, string>
   *   Validation errors.
   */
  private function validateValueAgainstSchema($value, array $schema, string $field_path, array $root_schema): array {
    $errors = [];
    $field_name = $field_path === '' ? 'root' : $field_path;
    $schema = $this->resolveSchemaReference($schema, $root_schema);

    $type_errors = $this->validateType($value, $schema, $field_name);
    if ($type_errors !== []) {
      return $type_errors;
    }

    if (isset($schema['enum']) && is_array($schema['enum']) && !in_array($value, $schema['enum'], TRUE)) {
      $errors[] = "Field '{$field_name}' must be one of: " . implode(', ', array_map('strval', $schema['enum']));
    }

    if (is_string($value) && isset($schema['pattern']) && is_string($schema['pattern'])) {
      $pattern = '/' . str_replace('/', '\/', $schema['pattern']) . '/';
      if (@preg_match($pattern, '') !== FALSE && !preg_match($pattern, $value)) {
        $errors[] = "Field '{$field_name}' does not match required pattern";
      }
    }

    $json_type = $this->resolveJsonTypeForSchema($value, $schema);

    if ($json_type === 'object') {
      if (isset($schema['required']) && is_array($schema['required'])) {
        foreach ($schema['required'] as $required_field) {
          if (!array_key_exists($required_field, $value)) {
            $required_path = $field_path === '' ? (string) $required_field : $field_path . '.' . $required_field;
            $errors[] = "Missing required field: {$required_path}";
          }
        }
      }

      $properties = is_array($schema['properties'] ?? NULL) ? $schema['properties'] : [];
      foreach ($value as $key => $property_value) {
        $property_path = $field_path === '' ? (string) $key : $field_path . '.' . $key;
        if (!isset($properties[$key])) {
          if (($schema['additionalProperties'] ?? TRUE) === FALSE) {
            $errors[] = "Unknown property: {$property_path}";
          }
          continue;
        }
        $errors = array_merge($errors, $this->validateValueAgainstSchema($property_value, $properties[$key], $property_path, $root_schema));
      }
    }
    elseif ($json_type === 'array' && isset($schema['items']) && is_array($schema['items'])) {
      foreach ($value as $index => $item) {
        $item_path = $field_name . '[' . $index . ']';
        $errors = array_merge($errors, $this->validateValueAgainstSchema($item, $schema['items'], $item_path, $root_schema));
      }
    }

    return $errors;
  }

  /**
   * Resolve an internal JSON-schema $ref against the root schema.
   */
  private function resolveSchemaReference(array $schema, array $root_schema): array {
    $reference = trim((string) ($schema['$ref'] ?? ''));
    if ($reference === '' || !str_starts_with($reference, '#/')) {
      return $schema;
    }

    $segments = array_values(array_filter(explode('/', substr($reference, 2)), static fn(string $segment): bool => $segment !== ''));
    $resolved = $root_schema;
    foreach ($segments as $segment) {
      $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);
      if (!is_array($resolved) || !array_key_exists($segment, $resolved)) {
        return $schema;
      }
      $resolved = $resolved[$segment];
    }

    if (!is_array($resolved)) {
      return $schema;
    }

    $overlay = $schema;
    unset($overlay['$ref']);
    return array_replace_recursive($resolved, $overlay);
  }

  /**
   * Resolve a PHP value to a JSON schema type label.
   */
  private function resolveJsonType($value): string {
    $actual_type = gettype($value);
    $is_sequential_array = is_array($value) && (empty($value) || array_keys($value) === range(0, count($value) - 1));

    return [
      'boolean' => 'boolean',
      'integer' => 'integer',
      'double' => 'number',
      'string' => 'string',
      'array' => $is_sequential_array ? 'array' : 'object',
      'NULL' => 'null',
    ][$actual_type] ?? 'unknown';
  }

  /**
   * Validate a value against a type schema.
   *
   * @param mixed $value
   *   Value to validate.
   * @param array $schema
   *   Property schema.
   * @param string $field_name
   *   Field name for error messages.
   *
   * @return array<int, string>
   *   Type validation errors.
   */
  private function validateType($value, array $schema, string $field_name): array {
    $errors = [];

    if (!isset($schema['type'])) {
      return $errors;
    }

    $json_type = $this->resolveJsonTypeForSchema($value, $schema);
    $allowed_types = is_array($schema['type']) ? $schema['type'] : [$schema['type']];
    if (!in_array($json_type, $allowed_types, TRUE)) {
      $errors[] = "Field '{$field_name}' has invalid type. Expected " . implode('|', $allowed_types) . ", got {$json_type}";
      return $errors;
    }

    if (($json_type === 'integer' || $json_type === 'number') && is_numeric($value)) {
      if (isset($schema['minimum']) && $value < $schema['minimum']) {
        $errors[] = "Field '{$field_name}' is below minimum value {$schema['minimum']}";
      }
      if (isset($schema['maximum']) && $value > $schema['maximum']) {
        $errors[] = "Field '{$field_name}' is above maximum value {$schema['maximum']}";
      }
    }

    if ($json_type === 'string') {
      if (isset($schema['minLength']) && strlen($value) < $schema['minLength']) {
        $errors[] = "Field '{$field_name}' is too short (minimum {$schema['minLength']} characters)";
      }
      if (isset($schema['maxLength']) && strlen($value) > $schema['maxLength']) {
        $errors[] = "Field '{$field_name}' is too long (maximum {$schema['maxLength']} characters)";
      }
      if (isset($schema['format']) && is_string($schema['format']) && !$this->validateStringFormat($value, $schema['format'])) {
        $errors[] = "Field '{$field_name}' must be a valid {$schema['format']} value";
      }
    }

    if ($json_type === 'array') {
      if (isset($schema['minItems']) && count($value) < $schema['minItems']) {
        $errors[] = "Field '{$field_name}' has too few items (minimum {$schema['minItems']})";
      }

      if (isset($schema['maxItems']) && count($value) > $schema['maxItems']) {
        $errors[] = "Field '{$field_name}' has too many items (maximum {$schema['maxItems']})";
      }
      if (!empty($schema['uniqueItems'])) {
        $encoded = array_map(static fn($item) => json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $value);
        if (count($encoded) !== count(array_unique($encoded))) {
          $errors[] = "Field '{$field_name}' must contain unique items";
        }
      }
    }

    return $errors;
  }

  /**
   * Validate JSON-schema string format constraints used by canonical contracts.
   */
  private function validateStringFormat(string $value, string $format): bool {
    return match ($format) {
      'date-time' => $this->validateDateTimeString($value),
      'uuid' => (bool) preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/', $value),
      default => TRUE,
    };
  }

  /**
   * Resolve the effective JSON type for a schema-aware validation pass.
   *
   * Empty JSON objects decode to [] when using json_decode(..., TRUE), so treat
   * an empty PHP array as an object when the schema expects an object and does
   * not also allow arrays.
   */
  private function resolveJsonTypeForSchema($value, array $schema): string {
    $json_type = $this->resolveJsonType($value);
    if (
      $json_type === 'array'
      && is_array($value)
      && $value === []
      && $this->schemaAllowsType($schema, 'object')
      && !$this->schemaAllowsType($schema, 'array')
    ) {
      return 'object';
    }

    return $json_type;
  }

  /**
   * Determine whether a schema allows a given JSON type.
   */
  private function schemaAllowsType(array $schema, string $type): bool {
    if (!isset($schema['type'])) {
      return FALSE;
    }

    $allowed_types = is_array($schema['type']) ? $schema['type'] : [$schema['type']];
    return in_array($type, $allowed_types, TRUE);
  }

}
