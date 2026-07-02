<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
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

  private LoggerInterface $logger;
  private ?Connection $database;
  private string $schemaBasePath;
  private ?array $contractRegistry = NULL;

  /**
   * Constructor.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory, ?Connection $database = NULL) {
    $this->logger = $logger_factory->get('dungeoncrawler');
    $this->database = $database;
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

      if ($actor_id <= 0) {
        $actor_errors[] = 'actor id must be a positive integer.';
      }
      if ($name === '') {
        $actor_errors[] = 'name is required.';
      }
      if ($level < 1 || $level > 25) {
        $actor_errors[] = 'level must be between 1 and 25.';
      }
      if ($instance_id === '') {
        $actor_errors[] = 'instance_id is required.';
      }
      if ($actor_type === '') {
        $actor_errors[] = 'type is required.';
      }
      if ($lifecycle_state === '') {
        $actor_errors[] = 'lifecycle_state is required.';
      }
      if ($location_type === '') {
        $actor_errors[] = 'location_type is required.';
      }
      $location_ref_optional_types = ['global', 'roster'];
      if ($location_ref === '' && !in_array($location_type, $location_ref_optional_types, TRUE)) {
        $actor_errors[] = 'location_ref is required for location_type values outside global/roster.';
      }
      if ($status === NULL || !in_array($status, [-1, 0, 1, 2], TRUE)) {
        $actor_errors[] = 'status must be one of: -1, 0, 1, 2.';
      }
      if ($campaign_id > 0 && $actor_type === 'pc' && ($source_character_id === NULL || $source_character_id <= 0)) {
        $actor_errors[] = 'pc actor rows must define source_character_id when campaign_id is non-zero.';
      }

      $character_data_raw = trim((string) ($row['character_data'] ?? ''));
      $character_data = [];
      if ($character_data_raw === '') {
        $actor_errors[] = 'character_data contract is required.';
      }
      else {
        $decoded = json_decode($character_data_raw, TRUE);
        if (!is_array($decoded) || $decoded === []) {
          $actor_errors[] = 'character_data must decode to a non-empty JSON object/array.';
        }
        else {
          $character_data = $decoded;
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
        $room_errors = array_merge($room_errors, $this->validateCanonicalRoomLayoutContract($layout_data));
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

    $report['summary']['total_items'] = count($report['items']);
    $report['summary']['valid_items'] = count(array_filter($report['items'], static fn(array $item): bool => !empty($item['valid'])));
    $report['summary']['invalid_items'] = $report['summary']['total_items'] - $report['summary']['valid_items'];
    $report['valid'] = $report['errors'] === [] && $report['summary']['invalid_items'] === 0;

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
    $hexes = $layout_data['hexes'] ?? NULL;
    if (!is_array($hexes) || $hexes === []) {
      $errors[] = 'layout_data.hexes must define at least one hex.';
      return $errors;
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
