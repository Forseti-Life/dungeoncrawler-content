<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Geometry\RoomPlacementTransformer;
use Drupal\dungeoncrawler_content\Service\Definition\DefinitionSchemaValidator;

/**
 * Canonical dungeon authoring authority.
 *
 * Owns dungeon drafts end to end: creation, loading, validation, and (from
 * slice 4) mutation and publication. Nothing else writes the dungeon draft or
 * version tables.
 *
 * A dungeon is a level: published room versions placed on one axial hex
 * lattice and joined by port links. It stores placements by reference and
 * never inlines room content; reproducibility comes from the pinned
 * `version_id` on every placement. This service is deliberately independent of
 * RoomEditorService (see 02-target-architecture.md, "Explicitly not reused").
 */
class DungeonEditorService {

  public const SCHEMA_VERSION = 'canonical-dungeon-v1';
  public const DRAFT_SCHEMA_VERSION = 'dungeon-editor-draft-v1';
  public const AGGREGATE_SCHEMA_FILE = 'canonical_dungeon_aggregate.schema.json';

  /**
   * Axial bound for any transformed level hex (12-api-and-error-contracts.md).
   */
  public const AXIAL_BOUND = 1000;
  public const COMMAND_SCHEMA_FILE = 'dungeon_editor_command.schema.json';

  /**
   * Command types the pipeline can execute.
   *
   * Mirrors the command schema's enum exactly (the contract test pins it); a
   * type outside this list is refused with `dungeon_command_type_unsupported`.
   */
  public const SUPPORTED_COMMANDS = [
    'set_dungeon_metadata',
    'place_room',
    'move_room_placement',
    'rotate_room_placement',
    'remove_room_placement',
    'retarget_room_placement',
    'set_placement_metadata',
    'link_ports',
    'update_port_link',
    'unlink_ports',
    'add_region',
    'update_region',
    'remove_region',
    'undo',
    'redo',
  ];
  private const METADATA_KEYS = ['name', 'description', 'depth', 'theme'];
  private const PLACEMENT_METADATA_KEYS = ['label', 'tags', 'is_level_entrance'];
  private const LINK_KEYS = ['kind', 'direction', 'default_state', 'travel_cost', 'requirements', 'description', 'tags'];
  private const REGION_KEYS = ['name', 'placement_ids', 'description', 'environmental_effects', 'ambient_hazard_level'];

  private const ID_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,99}$/';

  private ?array $aggregateSchema = NULL;
  private ?array $commandSchema = NULL;

  /**
   * Per-request cache of frozen room payloads by version_id.
   *
   * @var array<string, array|null>
   */
  private array $roomVersionCache = [];

  public function __construct(
    protected Connection $database,
    protected AccountProxyInterface $currentUser,
    protected UuidInterface $uuid,
    protected DefinitionSchemaValidator $validator,
    protected CanonicalDefinitionService $definitions,
    protected ?string $schemaDirectory = NULL,
  ) {
    $this->schemaDirectory ??= dirname(__DIR__, 2) . '/config/schemas';
  }

  /**
   * Lists dungeons available to the editor.
   */
  public function listDungeons(): array {
    return array_map(static fn(object $row): array => [
      'dungeon_id' => $row->dungeon_id,
      'name' => $row->name,
      'publication_status' => $row->publication_status ?? 'unpublished',
      'published_version_id' => $row->published_version_id ?? NULL,
    ], $this->database->select('dungeoncrawler_content_dungeons', 'd')
      ->fields('d', ['dungeon_id', 'name', 'publication_status', 'published_version_id'])
      ->orderBy('name')
      ->execute()
      ->fetchAll());
  }

  /**
   * Active drafts, optionally limited to those the given author created.
   *
   * Read-only listing for the editor suite hub. Each entry carries the room
   * version every placement pins so the hub can report superseded pins
   * without loading full drafts.
   */
  public function listDrafts(?int $created_by = NULL): array {
    $query = $this->database->select('dungeoncrawler_content_dungeon_editor_drafts', 'd')
      ->fields('d', ['draft_id', 'dungeon_id', 'base_version_id', 'revision', 'status', 'dungeon_payload', 'created_by', 'updated_by', 'updated_at'])
      ->condition('status', 'active')
      ->orderBy('updated_at', 'DESC');
    if ($created_by !== NULL) {
      $query->condition('created_by', $created_by);
    }
    return array_map(static function (object $row): array {
      $dungeon = json_decode((string) $row->dungeon_payload, TRUE, 512, JSON_THROW_ON_ERROR);
      $pins = [];
      foreach ($dungeon['placements'] ?? [] as $placement) {
        $pins[] = [
          'placement_id' => (string) $placement['placement_id'],
          'room_id' => (string) $placement['room_id'],
          'version_id' => (string) $placement['version_id'],
        ];
      }
      return [
        'draft_id' => $row->draft_id,
        'dungeon_id' => $row->dungeon_id,
        'name' => (string) ($dungeon['name'] ?? ''),
        'base_version_id' => $row->base_version_id,
        'revision' => (int) $row->revision,
        'status' => $row->status,
        'placement_pins' => $pins,
        'created_by' => (int) $row->created_by,
        'updated_by' => (int) $row->updated_by,
        'updated_at' => (int) $row->updated_at,
      ];
    }, $query->execute()->fetchAll());
  }

  /**
   * Published room versions available for placement.
   *
   * Only rooms with a published version appear: a placement pins a version,
   * and there is no such thing as placing an unpublished room. Each entry
   * carries the room-local footprint and ports so the client can render
   * thumbnails and drag ghosts with the shared transform.
   */
  public function roomLibrary(): array {
    $rows = $this->database->query(
      'SELECT r.room_id, r.name, r.published_version_id, v.version, v.room_payload
       FROM {dungeoncrawler_content_rooms} r
       INNER JOIN {dungeoncrawler_content_room_versions} v ON v.version_id = r.published_version_id
       WHERE r.publication_status = :status
       ORDER BY r.name',
      [':status' => 'published']
    )->fetchAll();

    $library = [];
    foreach ($rows as $row) {
      $room = json_decode((string) $row->room_payload, TRUE, 512, JSON_THROW_ON_ERROR);
      $this->roomVersionCache[$row->published_version_id] = $room;
      $library[] = [
        'room_id' => $row->room_id,
        'name' => $row->name,
        'version_id' => $row->published_version_id,
        'version' => $row->version,
        'room_type' => $room['room_type'] ?? NULL,
        'size_category' => $room['size_category'] ?? NULL,
        'hex_count' => count($room['hexes'] ?? []),
        'entry_port_count' => count($room['entry_ports'] ?? []),
        'exit_port_count' => count($room['exit_ports'] ?? []),
        'footprint' => $this->roomFootprint($room),
        'ports' => $this->roomPorts($room),
      ];
    }
    return $library;
  }

  /**
   * Creates an active draft from a published dungeon or a blank level.
   *
   * A dungeon that exists but has never been published through this editor
   * cannot seed a draft: its legacy `dungeon_data` is generator output, not
   * editor authority (09-data-model-and-versioning.md), and is never
   * normalised into an aggregate.
   */
  public function createDraft(?string $dungeon_id = NULL): array {
    $uid = (int) $this->currentUser->id();
    $dungeon_id = $dungeon_id !== NULL ? trim($dungeon_id) : NULL;
    if ($dungeon_id !== NULL && !preg_match(self::ID_PATTERN, $dungeon_id)) {
      throw new \InvalidArgumentException('dungeon_id_invalid');
    }

    if ($dungeon_id !== NULL) {
      $existing = $this->database->select('dungeoncrawler_content_dungeon_editor_drafts', 'd')
        ->fields('d')
        ->condition('dungeon_id', $dungeon_id)
        ->condition('created_by', $uid)
        ->condition('status', 'active')
        ->orderBy('updated_at', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if ($existing) {
        return $this->getDraft($existing['draft_id']);
      }
    }

    $base_version_id = NULL;
    if ($dungeon_id === NULL) {
      $dungeon = $this->blankDungeon();
    }
    else {
      $row = $this->database->select('dungeoncrawler_content_dungeons', 'd')
        ->fields('d', ['dungeon_id', 'published_version_id'])
        ->condition('dungeon_id', $dungeon_id)
        ->execute()
        ->fetchAssoc();
      if (!$row) {
        throw new \OutOfBoundsException('dungeon_not_found');
      }
      $base_version_id = $row['published_version_id'] ?: NULL;
      if ($base_version_id === NULL) {
        throw new \DomainException('dungeon_not_published');
      }
      $payload = $this->database->select('dungeoncrawler_content_dungeon_versions', 'v')
        ->fields('v', ['dungeon_payload'])
        ->condition('version_id', $base_version_id)
        ->execute()
        ->fetchField();
      if (!$payload) {
        throw new \RuntimeException('dungeon_version_missing:' . $base_version_id);
      }
      $dungeon = json_decode((string) $payload, TRUE, 512, JSON_THROW_ON_ERROR);
    }

    $draft_id = $this->uuid->generate();
    $encoded = $this->encodeDungeon($dungeon);
    $now = time();
    $this->database->insert('dungeoncrawler_content_dungeon_editor_drafts')
      ->fields([
        'draft_id' => $draft_id,
        'dungeon_id' => $dungeon_id,
        'base_version_id' => $base_version_id,
        'revision' => 0,
        'status' => 'active',
        'dungeon_payload' => $encoded,
        'payload_hash' => hash('sha256', $encoded),
        'created_by' => $uid,
        'updated_by' => $uid,
        'created_at' => $now,
        'updated_at' => $now,
      ])
      ->execute();

    return $this->getDraft($draft_id);
  }

  /**
   * Loads one draft with its current validation result.
   */
  public function getDraft(string $draft_id): array {
    $row = $this->loadDraftRow($draft_id);
    $draft = $this->decodeDraft($row);
    $draft['validation'] = $this->validateAggregate($draft);
    return $draft;
  }

  /**
   * Read model of a draft for the shell and the assistant.
   *
   * Resolves every placement's pinned room version and returns the room-local
   * geometry the client needs to render footprints with the shared transform.
   * Level-space occupancy is computed here, by the PHP transformer, so the
   * server remains the authority on what overlaps what.
   */
  public function describe(string $draft_id): array {
    $row = $this->loadDraftRow($draft_id);
    $draft = $this->decodeDraft($row);
    $dungeon = $draft['dungeon'];

    $placements = [];
    $occupancy = [];
    foreach ($dungeon['room_placements'] ?? [] as $placement) {
      $room = $this->roomVersion((string) $placement['version_id']);
      $entry = [
        'placement_id' => $placement['placement_id'],
        'room_id' => $placement['room_id'],
        'version_id' => $placement['version_id'],
        'origin' => $placement['origin'],
        'rotation_steps' => (int) $placement['rotation_steps'],
        'label' => $placement['label'],
        'is_level_entrance' => (bool) $placement['is_level_entrance'],
        'tags' => $placement['tags'],
        'resolved' => $room !== NULL,
        'room' => NULL,
        'level_hexes' => [],
      ];
      if ($room !== NULL) {
        $entry['room'] = [
          'name' => $room['name'] ?? $placement['room_id'],
          'room_type' => $room['room_type'] ?? NULL,
          'hex_count' => count($room['hexes'] ?? []),
          'hexes' => $this->roomHexesForRender($room),
          'ports' => $this->roomPorts($room),
        ];
        foreach ($this->roomFootprint($room) as $hex) {
          $level = RoomPlacementTransformer::toLevel($hex, $placement);
          $entry['level_hexes'][] = $level;
          $occupancy[RoomPlacementTransformer::hexKey($level)][] = $placement['placement_id'];
        }
      }
      $placements[] = $entry;
    }

    return [
      'schema_version' => 'dungeon-editor-read-model-v1',
      'draft_id' => $draft['draft_id'],
      'dungeon_id' => $draft['dungeon_id'],
      'revision' => $draft['revision'],
      'status' => $draft['status'],
      'name' => $dungeon['name'],
      'description' => $dungeon['description'],
      'depth' => $dungeon['depth'],
      'theme' => $dungeon['theme'],
      'hex_grid' => $dungeon['hex_grid'],
      'placements' => $placements,
      'port_links' => $dungeon['port_links'] ?? [],
      'regions' => $dungeon['regions'] ?? [],
      'occupancy' => array_map(static fn(array $ids): array => array_values(array_unique($ids)), $occupancy),
      'validation' => $this->validateAggregate($draft),
    ];
  }

  // ---------------------------------------------------------------------------
  // Command pipeline
  // ---------------------------------------------------------------------------

  /**
   * Applies one revision-checked, idempotent authoring command.
   *
   * Order: envelope contract -> idempotent replay -> (in one transaction)
   * lock draft, revision check, mutate, schema conformance, validation,
   * append command log, advance draft. Any failure rolls back everything;
   * no partial result is ever stored or returned.
   *
   * @return array{command_id: string, idempotent: bool, result_revision: int, placement_id: ?string, draft: array, model: array}
   */
  public function applyCommand(string $draft_id, array $command): array {
    if (!$this->isUuid($draft_id)) {
      throw new \InvalidArgumentException('draft_id_invalid');
    }
    $this->assertCommandEnvelope($command);
    $command_id = $command['command_id'];
    $encoded_command = $this->encode($command);

    $prior = $this->database->select('dungeoncrawler_content_dungeon_editor_commands', 'c')
      ->fields('c', ['draft_id', 'result_revision', 'command_payload'])
      ->condition('command_id', $command_id)
      ->execute()
      ->fetchAssoc();
    if ($prior) {
      if ($prior['draft_id'] !== $draft_id || hash('sha256', $prior['command_payload']) !== hash('sha256', $encoded_command)) {
        throw new \RuntimeException('idempotency_conflict');
      }
      return [
        'command_id' => $command_id,
        'idempotent' => TRUE,
        'result_revision' => (int) $prior['result_revision'],
        'placement_id' => $this->placementIdFromLog($prior['command_payload']),
        'draft' => $this->getDraft($draft_id),
        'model' => $this->describe($draft_id),
      ];
    }

    $transaction = $this->database->startTransaction();
    try {
      $row = $this->database->select('dungeoncrawler_content_dungeon_editor_drafts', 'd')
        ->fields('d')
        ->condition('draft_id', $draft_id)
        ->forUpdate()
        ->execute()
        ->fetchAssoc();
      if (!$row) {
        throw new \OutOfBoundsException('dungeon_draft_not_found');
      }
      $this->assertDraftAccess($row);
      if ($row['status'] !== 'active') {
        throw new \DomainException('dungeon_draft_not_active');
      }
      if ((int) $row['revision'] !== $command['expected_revision']) {
        throw new \RuntimeException('revision_conflict');
      }

      $before = json_decode($row['dungeon_payload'], TRUE, 512, JSON_THROW_ON_ERROR);
      $outcome = $this->mutate($before, $command['type'], $command['payload'], $draft_id);
      $after = $outcome['dungeon'];
      $revision = ((int) $row['revision']) + 1;
      $this->assertCommandResult($after, $draft_id, $revision);

      $encoded = $this->encodeDungeon($after);
      $hash = hash('sha256', $encoded);
      $now = time();
      $uid = (int) $this->currentUser->id();
      $this->database->insert('dungeoncrawler_content_dungeon_editor_commands')
        ->fields([
          'command_id' => $command_id,
          'draft_id' => $draft_id,
          'base_revision' => (int) $row['revision'],
          'result_revision' => $revision,
          'command_type' => $command['type'],
          'command_payload' => $encoded_command,
          'inverse_payload' => $this->encode(['dungeon' => $before]),
          'issued_by' => $uid,
          'issued_at' => $now,
          'result_hash' => $hash,
        ])
        ->execute();
      $affected = $this->database->update('dungeoncrawler_content_dungeon_editor_drafts')
        ->fields([
          'revision' => $revision,
          'dungeon_payload' => $encoded,
          'payload_hash' => $hash,
          'updated_by' => $uid,
          'updated_at' => $now,
        ])
        ->condition('draft_id', $draft_id)
        ->condition('revision', (int) $row['revision'])
        ->execute();
      if ($affected !== 1) {
        throw new \RuntimeException('revision_conflict');
      }
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
    unset($transaction);

    return [
      'command_id' => $command_id,
      'idempotent' => FALSE,
      'result_revision' => $revision,
      'placement_id' => $outcome['placement_id'],
      'draft' => $this->getDraft($draft_id),
      'model' => $this->describe($draft_id),
    ];
  }

  /**
   * Projects a command list onto a draft without persisting anything.
   *
   * Commands must chain from the draft's current revision exactly as they
   * would if applied, so a previewed plan is the plan that gets applied. The
   * first rejected command stops the projection; the returned aggregate is
   * the state just before it. The draft row is re-read afterwards and its
   * hash asserted unchanged.
   */
  public function simulateCommands(string $draft_id, array $commands, string $profile = 'editing'): array {
    $row = $this->loadDraftRow($draft_id);
    $draft = $this->decodeDraft($row);
    if ($commands === [] || !array_is_list($commands)) {
      throw new \InvalidArgumentException('dungeon_command_list_invalid');
    }

    $dungeon = $draft['dungeon'];
    $revision = $draft['revision'];
    $applied = [];
    $rejected = NULL;
    foreach ($commands as $index => $command) {
      if (!is_array($command)) {
        throw new \InvalidArgumentException('dungeon_command_list_invalid');
      }
      $this->assertCommandEnvelope($command);
      if ($command['expected_revision'] !== $revision) {
        throw new \RuntimeException('revision_conflict');
      }
      $exists = $this->database->select('dungeoncrawler_content_dungeon_editor_commands', 'c')
        ->fields('c', ['command_id'])
        ->condition('command_id', $command['command_id'])
        ->execute()
        ->fetchField();
      if ($exists) {
        throw new \RuntimeException('idempotency_conflict');
      }
      try {
        $outcome = $this->mutate($dungeon, $command['type'], $command['payload'], $draft_id);
        $this->assertCommandResult($outcome['dungeon'], $draft_id, $revision + 1);
      }
      catch (DungeonEditorFindingsInterface $exception) {
        $rejected = [
          'index' => $index,
          'command_id' => $command['command_id'],
          'type' => $command['type'],
          'code' => $exception->getMessage(),
          'findings' => $exception->getFindings(),
        ];
        break;
      }
      catch (\DomainException $exception) {
        $rejected = [
          'index' => $index,
          'command_id' => $command['command_id'],
          'type' => $command['type'],
          'code' => $exception->getMessage(),
          'findings' => [],
        ];
        break;
      }
      $dungeon = $outcome['dungeon'];
      $revision++;
      $applied[] = ['command_id' => $command['command_id'], 'type' => $command['type'], 'result_revision' => $revision, 'placement_id' => $outcome['placement_id']];
    }

    $after = $this->loadDraftRow($draft_id);
    if ($after['payload_hash'] !== $row['payload_hash'] || (int) $after['revision'] !== $draft['revision']) {
      throw new \RuntimeException('simulation_mutated_draft');
    }

    return [
      'schema_version' => 'dungeon-editor-simulation-v1',
      'draft_id' => $draft_id,
      'base_revision' => $draft['revision'],
      'projected_revision' => $revision,
      'applied' => $applied,
      'rejected' => $rejected,
      'dungeon' => $dungeon,
      'validation' => $this->validateAggregate(['draft_id' => $draft_id, 'revision' => $revision, 'dungeon' => $dungeon], $profile),
    ];
  }

  /**
   * Validates a stored draft at a profile without loading the read model.
   */
  public function validateDraft(string $draft_id, string $profile = 'editing'): array {
    $draft = $this->decodeDraft($this->loadDraftRow($draft_id));
    return $this->validateAggregate($draft, $profile);
  }

  /**
   * Checks the envelope against dungeon_editor_command.schema.json.
   *
   * A type outside the closed enum, or inside it but not yet executable, is
   * `dungeon_command_type_unsupported`; any other contract failure is
   * `dungeon_command_payload_invalid` with the schema findings attached.
   */
  private function assertCommandEnvelope(array $command): void {
    $type = $command['type'] ?? NULL;
    $schema = $this->commandSchema();
    if (!is_string($type) || !in_array($type, $schema['properties']['type']['enum'], TRUE) || !in_array($type, self::SUPPORTED_COMMANDS, TRUE)) {
      throw new DungeonCommandPayloadException('dungeon_command_type_unsupported');
    }
    $findings = $this->validator->validate($schema, $command);
    if ($findings !== []) {
      throw new DungeonCommandPayloadException('dungeon_command_payload_invalid', $findings);
    }
    if (!is_array($command['payload']) || array_is_list($command['payload']) && $command['payload'] !== []) {
      throw new DungeonCommandPayloadException('dungeon_command_payload_invalid', [[
        'code' => 'type_mismatch',
        'pointer' => '/payload',
        'schema_pointer' => '#/properties/payload',
        'message' => 'Payload must be a JSON object.',
      ]]);
    }
  }

  /**
   * A mutated aggregate must conform to its schema and carry no error finding.
   *
   * Schema nonconformance after a well-formed command means the payload asked
   * for an illegal value (empty name, depth 500): `dungeon_command_payload_invalid`.
   * Validation errors mean a legal value produced an illegal level
   * (`placement_overlap`, ...): the first code is the rejection code.
   */
  private function assertCommandResult(array $after, string $draft_id, int $revision): void {
    try {
      $this->assertAggregateConforms($after, 'editing');
    }
    catch (DungeonAggregateException $exception) {
      throw new DungeonCommandPayloadException('dungeon_command_payload_invalid', $exception->findings, $exception);
    }
    $result = $this->validateAggregate(['draft_id' => $draft_id, 'revision' => $revision, 'dungeon' => $after], 'editing');
    $errors = array_values(array_filter($result['findings'], static fn(array $f): bool => $f['severity'] === 'error'));
    if ($errors !== []) {
      // Errors first: the rejecting finding leads, advisories follow.
      $rest = array_values(array_filter($result['findings'], static fn(array $f): bool => $f['severity'] !== 'error'));
      throw new DungeonCommandRejectedException($errors[0]['code'], array_merge($errors, $rest));
    }
  }

  /**
   * Pure aggregate transition. No I/O except reading room versions and, for
   * undo/redo, the append-only command log.
   *
   * @return array{dungeon: array, placement_id: ?string}
   */
  private function mutate(array $dungeon, string $type, array $payload, string $draft_id): array {
    $placement_id = NULL;
    switch ($type) {
      case 'set_dungeon_metadata':
        $changes = $this->requireChanges($payload, self::METADATA_KEYS);
        foreach ($changes as $key => $value) {
          $dungeon[$key] = $value;
        }
        break;

      case 'place_room':
        $room_id = $payload['room_id'];
        $version_id = $payload['version_id'];
        if (!is_string($room_id) || !preg_match(self::ID_PATTERN, $room_id) || !is_string($version_id)) {
          throw new DungeonCommandPayloadException('dungeon_command_payload_invalid', [$this->payloadFinding('/room_id', 'room_id and version_id must be strings.')]);
        }
        $room = $this->resolvePlacementVersion($version_id, $room_id);
        $placement_id = $this->uuid->generate();
        $label = $payload['label'] ?? $room['name'] ?? $room_id;
        $placement = [
          'placement_id' => $placement_id,
          'room_id' => $room_id,
          'version_id' => $version_id,
          'origin' => $this->requireOrigin($payload),
          'rotation_steps' => $this->requireRotation($payload),
          'label' => $label,
          'is_level_entrance' => FALSE,
          'tags' => [],
        ];
        $this->assertPlacementInBounds($placement, $room);
        $dungeon['room_placements'][] = $placement;
        break;

      case 'move_room_placement':
        $index = $this->requirePlacementIndex($dungeon, $payload);
        $placement_id = $dungeon['room_placements'][$index]['placement_id'];
        $dungeon['room_placements'][$index]['origin'] = $this->requireOrigin($payload);
        $this->assertPlacementInBounds($dungeon['room_placements'][$index], $this->resolvePlacementVersion($dungeon['room_placements'][$index]['version_id']));
        break;

      case 'rotate_room_placement':
        $index = $this->requirePlacementIndex($dungeon, $payload);
        $placement_id = $dungeon['room_placements'][$index]['placement_id'];
        $dungeon['room_placements'][$index]['rotation_steps'] = $this->requireRotation($payload);
        $this->assertPlacementInBounds($dungeon['room_placements'][$index], $this->resolvePlacementVersion($dungeon['room_placements'][$index]['version_id']));
        break;

      case 'remove_room_placement':
        $index = $this->requirePlacementIndex($dungeon, $payload);
        $placement_id = $dungeon['room_placements'][$index]['placement_id'];
        array_splice($dungeon['room_placements'], $index, 1);
        $dungeon['port_links'] = array_values(array_filter($dungeon['port_links'], static fn(array $link): bool =>
          $link['from']['placement_id'] !== $placement_id && $link['to']['placement_id'] !== $placement_id
        ));
        foreach ($dungeon['regions'] as &$region) {
          $region['placement_ids'] = array_values(array_filter($region['placement_ids'], static fn(string $id): bool => $id !== $placement_id));
        }
        unset($region);
        break;

      case 'retarget_room_placement':
        $index = $this->requirePlacementIndex($dungeon, $payload);
        $placement_id = $dungeon['room_placements'][$index]['placement_id'];
        $version_id = $payload['version_id'];
        if (!is_string($version_id)) {
          throw new DungeonCommandPayloadException('dungeon_command_payload_invalid', [$this->payloadFinding('/version_id', 'version_id must be a string.')]);
        }
        $room = $this->resolvePlacementVersion($version_id, $dungeon['room_placements'][$index]['room_id']);
        $dungeon['room_placements'][$index]['version_id'] = $version_id;
        $this->assertPlacementInBounds($dungeon['room_placements'][$index], $room);
        $this->assertLinksResolve($dungeon, $placement_id, $room);
        break;

      case 'set_placement_metadata':
        $index = $this->requirePlacementIndex($dungeon, $payload);
        $placement_id = $dungeon['room_placements'][$index]['placement_id'];
        $changes = $this->requireChanges($payload, self::PLACEMENT_METADATA_KEYS);
        foreach ($changes as $key => $value) {
          $dungeon['room_placements'][$index][$key] = $value;
        }
        break;

      case 'link_ports':
        foreach (['from', 'to'] as $end) {
          $endpoint = $payload[$end];
          if (!is_array($endpoint) || !is_string($endpoint['placement_id'] ?? NULL) || !is_string($endpoint['port_id'] ?? NULL) || count($endpoint) !== 2) {
            throw new DungeonCommandPayloadException('dungeon_command_payload_invalid', [$this->payloadFinding('/' . $end, 'Endpoint must be {placement_id, port_id}.')]);
          }
        }
        $extras = $this->optionalFields($payload, ['from', 'to', 'kind', 'direction', 'default_state'], self::LINK_KEYS);
        $link_id = $this->uuid->generate();
        $dungeon['port_links'][] = [
          'link_id' => $link_id,
          'from' => ['placement_id' => $payload['from']['placement_id'], 'port_id' => $payload['from']['port_id']],
          'to' => ['placement_id' => $payload['to']['placement_id'], 'port_id' => $payload['to']['port_id']],
          'kind' => $payload['kind'],
          'direction' => $payload['direction'],
          'default_state' => $payload['default_state'],
          'travel_cost' => $extras['travel_cost'] ?? 0,
          'requirements' => $extras['requirements'] ?? [],
          'description' => $extras['description'] ?? '',
          'tags' => $extras['tags'] ?? [],
        ];
        break;

      case 'update_port_link':
        $index = $this->requireLinkIndex($dungeon, $payload);
        $changes = $this->requireChanges($payload, self::LINK_KEYS);
        foreach ($changes as $key => $value) {
          $dungeon['port_links'][$index][$key] = $value;
        }
        break;

      case 'unlink_ports':
        $index = $this->requireLinkIndex($dungeon, $payload);
        array_splice($dungeon['port_links'], $index, 1);
        break;

      case 'add_region':
        $region_id = $payload['region_id'];
        if (!is_string($region_id) || !preg_match(self::ID_PATTERN, $region_id)) {
          throw new DungeonCommandPayloadException('dungeon_command_payload_invalid', [$this->payloadFinding('/region_id', 'region_id must match ' . self::ID_PATTERN . '.')]);
        }
        foreach ($dungeon['regions'] as $region) {
          if ($region['region_id'] === $region_id) {
            throw new \DomainException('region_id_duplicate');
          }
        }
        $extras = $this->optionalFields($payload, ['region_id', 'name', 'placement_ids'], self::REGION_KEYS);
        $dungeon['regions'][] = [
          'region_id' => $region_id,
          'name' => $payload['name'],
          'placement_ids' => $payload['placement_ids'],
          'description' => $extras['description'] ?? '',
          'environmental_effects' => $extras['environmental_effects'] ?? [],
          'ambient_hazard_level' => $extras['ambient_hazard_level'] ?? 0,
        ];
        break;

      case 'update_region':
        $index = $this->requireRegionIndex($dungeon, $payload);
        $changes = $this->requireChanges($payload, self::REGION_KEYS);
        foreach ($changes as $key => $value) {
          $dungeon['regions'][$index][$key] = $value;
        }
        break;

      case 'remove_region':
        $index = $this->requireRegionIndex($dungeon, $payload);
        array_splice($dungeon['regions'], $index, 1);
        break;

      case 'undo':
      case 'redo':
        $target = $payload['target_command_id'];
        if (!is_string($target) || !$this->isUuid($target)) {
          throw new DungeonCommandPayloadException('dungeon_command_payload_invalid', [$this->payloadFinding('/target_command_id', 'target_command_id must be a uuid.')]);
        }
        $entry = $this->database->select('dungeoncrawler_content_dungeon_editor_commands', 'c')
          ->fields('c', ['command_type', 'inverse_payload'])
          ->condition('command_id', $target)
          ->condition('draft_id', $draft_id)
          ->execute()
          ->fetchAssoc();
        if (!$entry) {
          throw new \DomainException('history_target_not_found');
        }
        // undo reverts a forward command; redo reverts an undo. Each restores
        // the target's recorded "before" snapshot, so the pair is symmetric.
        $forward = !in_array($entry['command_type'], ['undo', 'redo'], TRUE);
        if (($type === 'undo' && !$forward) || ($type === 'redo' && $entry['command_type'] !== 'undo')) {
          throw new \DomainException('history_target_invalid');
        }
        $snapshot = json_decode((string) $entry['inverse_payload'], TRUE, 512, JSON_THROW_ON_ERROR);
        if (!is_array($snapshot['dungeon'] ?? NULL)) {
          throw new \RuntimeException('history_snapshot_invalid:' . $target);
        }
        $dungeon = $snapshot['dungeon'];
        break;

      default:
        throw new DungeonCommandPayloadException('dungeon_command_type_unsupported');
    }
    return ['dungeon' => $dungeon, 'placement_id' => $placement_id];
  }

  /**
   * The published room payload a placement may pin, or a hard failure.
   *
   * @throws \Drupal\dungeoncrawler_content\Service\DungeonCommandRejectedException
   *   `placement_version_unresolved` when the version is not published;
   *   `dungeon_command_payload_invalid` when it belongs to another room.
   */
  private function resolvePlacementVersion(string $version_id, ?string $expected_room_id = NULL): array {
    $room = $this->roomVersion($version_id);
    if ($room === NULL) {
      throw new DungeonCommandRejectedException('placement_version_unresolved', [
        $this->finding('error', 'placement_version_unresolved', sprintf('%s is not a published room version.', $version_id), []),
      ]);
    }
    if ($expected_room_id !== NULL && ($room['room_id'] ?? NULL) !== $expected_room_id) {
      throw new DungeonCommandPayloadException('dungeon_command_payload_invalid', [
        $this->payloadFinding('/version_id', sprintf('Version %s belongs to room "%s", not "%s".', $version_id, $room['room_id'] ?? '?', $expected_room_id)),
      ]);
    }
    return $room;
  }

  /**
   * Every transformed hex must stay inside the axial bound. Nothing is clamped.
   */
  private function assertPlacementInBounds(array $placement, array $room): void {
    foreach ($this->roomFootprint($room) as $hex) {
      $level = RoomPlacementTransformer::toLevel($hex, $placement);
      if (abs($level['q']) > self::AXIAL_BOUND || abs($level['r']) > self::AXIAL_BOUND) {
        throw new DungeonCommandRejectedException('placement_origin_out_of_bounds', [
          $this->finding('error', 'placement_origin_out_of_bounds', sprintf(
            'Placement "%s" would reach (%d, %d), beyond the ±%d axial bound.',
            $placement['label'],
            $level['q'],
            $level['r'],
            self::AXIAL_BOUND
          ), [['placement_id' => $placement['placement_id']]], $level),
        ]);
      }
    }
  }

  /**
   * After a retarget every link touching the placement must still name a
   * port the new version has. Nothing is re-routed.
   */
  private function assertLinksResolve(array $dungeon, string $placement_id, array $room): void {
    $ports = array_column($this->roomPorts($room), 'port_id');
    foreach ($dungeon['port_links'] as $link) {
      foreach (['from', 'to'] as $end) {
        if ($link[$end]['placement_id'] === $placement_id && !in_array($link[$end]['port_id'], $ports, TRUE)) {
          throw new DungeonCommandRejectedException('port_link_endpoint_missing', [
            $this->finding('error', 'port_link_endpoint_missing', sprintf(
              'Link %s names port "%s", which the retargeted version does not have.',
              $link['link_id'],
              $link[$end]['port_id']
            ), [['link_id' => $link['link_id']], ['placement_id' => $placement_id]]),
          ]);
        }
      }
    }
  }

  /**
   * Optional payload keys: anything beyond the required set must be in the
   * allowed set. Values are checked by the aggregate schema afterwards.
   */
  private function optionalFields(array $payload, array $required, array $allowed): array {
    $unknown = array_diff(array_keys($payload), $required, $allowed);
    if ($unknown !== []) {
      throw new DungeonCommandPayloadException('dungeon_command_payload_invalid', [
        $this->payloadFinding('/' . reset($unknown), sprintf('Unknown key; allowed: %s.', implode(', ', array_merge($required, $allowed)))),
      ]);
    }
    return array_intersect_key($payload, array_flip($allowed));
  }

  private function requireLinkIndex(array $dungeon, array $payload): int {
    $link_id = $payload['link_id'] ?? NULL;
    if (!is_string($link_id) || !$this->isUuid($link_id)) {
      throw new DungeonCommandPayloadException('dungeon_command_payload_invalid', [$this->payloadFinding('/link_id', 'link_id must be a uuid.')]);
    }
    foreach ($dungeon['port_links'] as $index => $link) {
      if ($link['link_id'] === $link_id) {
        return $index;
      }
    }
    throw new \DomainException('port_link_not_found');
  }

  private function requireRegionIndex(array $dungeon, array $payload): int {
    $region_id = $payload['region_id'] ?? NULL;
    if (!is_string($region_id)) {
      throw new DungeonCommandPayloadException('dungeon_command_payload_invalid', [$this->payloadFinding('/region_id', 'region_id must be a string.')]);
    }
    foreach ($dungeon['regions'] as $index => $region) {
      if ($region['region_id'] === $region_id) {
        return $index;
      }
    }
    throw new \DomainException('region_not_found');
  }

  private function requirePlacementIndex(array $dungeon, array $payload): int {
    $placement_id = $payload['placement_id'] ?? NULL;
    if (!is_string($placement_id) || !$this->isUuid($placement_id)) {
      throw new DungeonCommandPayloadException('dungeon_command_payload_invalid', [$this->payloadFinding('/placement_id', 'placement_id must be a uuid.')]);
    }
    foreach ($dungeon['room_placements'] as $index => $placement) {
      if ($placement['placement_id'] === $placement_id) {
        return $index;
      }
    }
    throw new \DomainException('placement_not_found');
  }

  private function requireOrigin(array $payload): array {
    $origin = $payload['origin'] ?? NULL;
    if (!is_array($origin) || !is_int($origin['q'] ?? NULL) || !is_int($origin['r'] ?? NULL) || count($origin) !== 2) {
      throw new DungeonCommandPayloadException('dungeon_command_payload_invalid', [$this->payloadFinding('/origin', 'origin must be {q: int, r: int}.')]);
    }
    if (abs($origin['q']) > self::AXIAL_BOUND || abs($origin['r']) > self::AXIAL_BOUND) {
      throw new DungeonCommandRejectedException('placement_origin_out_of_bounds', [
        $this->finding('error', 'placement_origin_out_of_bounds', sprintf('Origin (%d, %d) is beyond the ±%d axial bound.', $origin['q'], $origin['r'], self::AXIAL_BOUND), [], $origin),
      ]);
    }
    return ['q' => $origin['q'], 'r' => $origin['r']];
  }

  private function requireRotation(array $payload): int {
    $steps = $payload['rotation_steps'] ?? NULL;
    if (!is_int($steps) || $steps < 0 || $steps > 5) {
      throw new DungeonCommandRejectedException('rotation_steps_invalid', [
        $this->finding('error', 'rotation_steps_invalid', 'rotation_steps must be an integer from 0 to 5; rotation is absolute, never a delta.', []),
      ]);
    }
    return $steps;
  }

  /**
   * `changes` must be a non-empty object whose keys are all in the allowed set.
   */
  private function requireChanges(array $payload, array $allowed): array {
    $changes = $payload['changes'] ?? NULL;
    if (!is_array($changes) || $changes === [] || array_is_list($changes)) {
      throw new DungeonCommandPayloadException('dungeon_command_payload_invalid', [$this->payloadFinding('/changes', 'changes must be a non-empty object.')]);
    }
    $unknown = array_diff(array_keys($changes), $allowed);
    if ($unknown !== []) {
      throw new DungeonCommandPayloadException('dungeon_command_payload_invalid', [
        $this->payloadFinding('/changes/' . reset($unknown), sprintf('Unknown key; allowed: %s.', implode(', ', $allowed))),
      ]);
    }
    return $changes;
  }

  private function payloadFinding(string $pointer, string $message): array {
    return ['code' => 'payload_invalid', 'pointer' => '/payload' . $pointer, 'schema_pointer' => '#/properties/payload', 'message' => $message];
  }

  private function placementIdFromLog(string $encoded_command): ?string {
    $command = json_decode($encoded_command, TRUE, 512, JSON_THROW_ON_ERROR);
    $id = $command['payload']['placement_id'] ?? NULL;
    return is_string($id) ? $id : NULL;
  }

  private function commandSchema(): array {
    if ($this->commandSchema === NULL) {
      $path = rtrim($this->schemaDirectory, '/') . '/' . self::COMMAND_SCHEMA_FILE;
      if (!is_file($path)) {
        throw new \RuntimeException('dungeon_schema_missing:' . self::COMMAND_SCHEMA_FILE);
      }
      $this->commandSchema = json_decode((string) file_get_contents($path), TRUE, 512, JSON_THROW_ON_ERROR);
    }
    return $this->commandSchema;
  }

  private function encode(array $value): string {
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
  }

  /**
   * Asserts the aggregate conforms to canonical_dungeon_aggregate.schema.json.
   *
   * Only this service writes aggregates, so nonconformance is never a user
   * finding: it is either a rejected command (slice 4) or stored corruption.
   * Either way it hard-fails with the schema findings attached.
   */
  public function assertAggregateConforms(array $dungeon, string $profile = 'editing'): void {
    $findings = $this->validator->validate($this->aggregateSchema(), $dungeon);
    if ($profile === 'editing') {
      // The published contract requires at least one placement. An empty
      // level is incompleteness, which editing tolerates; it is the only
      // schema rule relaxed for drafts, and publication enforces it.
      $findings = array_values(array_filter($findings, static fn(array $f): bool => !(
        $f['code'] === 'min_items' && $f['pointer'] === '/room_placements'
      )));
    }
    if ($findings !== []) {
      throw new DungeonAggregateException($findings);
    }
  }

  /**
   * Validates a draft's aggregate per dungeon_editor_validation_result.schema.json.
   *
   * Implements the full rule table of 04-contracts.md "Validation rules by
   * profile": editing is permissive about incompleteness (entrance,
   * reachability, dangling exits) and strict about incorrectness (overlap,
   * unresolved versions, illegal links, unresolved region references). The
   * code list is closed by the contract, so nothing outside it is emitted.
   */
  public function validateAggregate(array $draft, string $profile = 'editing'): array {
    if (!in_array($profile, ['editing', 'publication'], TRUE)) {
      throw new \InvalidArgumentException('validation_profile_invalid');
    }
    $dungeon = $draft['dungeon'];
    $this->assertAggregateConforms($dungeon, $profile);

    $findings = [];
    $occupancy = [];
    $entrances = [];
    // Level-space ports by "placement_id:port_id" for resolved placements.
    $level_ports = [];
    $resolved = [];
    foreach ($dungeon['room_placements'] as $placement) {
      $placement_id = $placement['placement_id'];
      $resolved[$placement_id] = FALSE;
      if ($placement['is_level_entrance']) {
        $entrances[] = $placement_id;
      }
      $room = $this->roomVersion((string) $placement['version_id']);
      if ($room === NULL) {
        $findings[] = $this->finding('error', 'placement_version_unresolved', sprintf(
          'Placement "%s" pins version %s, which is not a published room version.',
          $placement['label'],
          $placement['version_id']
        ), [['placement_id' => $placement_id]]);
        continue;
      }
      $resolved[$placement_id] = TRUE;
      foreach ($this->roomFootprint($room) as $hex) {
        $level = RoomPlacementTransformer::toLevel($hex, $placement);
        if (abs($level['q']) > self::AXIAL_BOUND || abs($level['r']) > self::AXIAL_BOUND) {
          // Commands reject this before storage; a stored instance is corruption.
          throw new \RuntimeException('placement_origin_out_of_bounds:' . $placement_id);
        }
        $occupancy[RoomPlacementTransformer::hexKey($level)][] = $placement_id;
      }
      foreach ($this->roomPorts($room) as $port) {
        $level_ports[$placement_id . ':' . $port['port_id']] = [
          'kind' => $port['kind'],
          'label' => $placement['label'],
        ] + RoomPlacementTransformer::toLevelPort(['q' => $port['q'], 'r' => $port['r']], $port['edge'], $placement);
      }
    }

    $reported = [];
    foreach ($occupancy as $key => $ids) {
      $ids = array_values(array_unique($ids));
      if (count($ids) < 2) {
        continue;
      }
      sort($ids);
      $pair = implode('|', $ids);
      if (isset($reported[$pair])) {
        continue;
      }
      [$q, $r] = array_map('intval', explode(':', $key));
      $reported[$pair] = TRUE;
      $findings[] = $this->finding('error', 'placement_overlap', sprintf(
        'Placements %s claim the same level hex, first at (%d, %d).',
        implode(' and ', $ids),
        $q,
        $r
      ), array_map(static fn(string $id): array => ['placement_id' => $id], $ids), ['q' => $q, 'r' => $r]);
    }

    // Links: endpoints, direction, self reference, arity, sealed adjacency.
    $exit_use = [];
    $edges = [];
    foreach ($dungeon['port_links'] as $link) {
      $link_id = $link['link_id'];
      $subject = [['link_id' => $link_id]];
      $from_id = $link['from']['placement_id'];
      $to_id = $link['to']['placement_id'];
      if ($from_id === $to_id) {
        $findings[] = $this->finding('error', 'port_link_self_reference', sprintf('Link %s joins placement %s to itself.', $link_id, $from_id), $subject);
        continue;
      }
      $missing = FALSE;
      foreach (['from' => $from_id, 'to' => $to_id] as $end => $pid) {
        if (!array_key_exists($pid, $resolved)) {
          $findings[] = $this->finding('error', 'port_link_endpoint_missing', sprintf('Link %s: %s placement %s does not exist.', $link_id, $end, $pid), $subject);
          $missing = TRUE;
        }
        elseif ($resolved[$pid] && !isset($level_ports[$pid . ':' . $link[$end]['port_id']])) {
          $findings[] = $this->finding('error', 'port_link_endpoint_missing', sprintf('Link %s: %s port "%s" does not exist on placement %s.', $link_id, $end, $link[$end]['port_id'], $pid), [['link_id' => $link_id], ['port_id' => $link[$end]['port_id']]]);
          $missing = TRUE;
        }
      }
      if ($missing || !$resolved[$from_id] || !$resolved[$to_id]) {
        continue;
      }
      $a = $level_ports[$from_id . ':' . $link['from']['port_id']];
      $b = $level_ports[$to_id . ':' . $link['to']['port_id']];
      if ($a['kind'] !== 'exit' || $b['kind'] !== 'entry') {
        $findings[] = $this->finding('error', 'port_link_direction_invalid', sprintf('Link %s must run from an exit port to an entry port (from is %s, to is %s).', $link_id, $a['kind'], $b['kind']), $subject);
        continue;
      }
      $exit_key = $from_id . ':' . $link['from']['port_id'];
      $exit_use[$exit_key][] = $link_id;
      $expected_hex = RoomPlacementTransformer::neighbor(['q' => $a['q'], 'r' => $a['r']], $a['edge']);
      $expected_edge = RoomPlacementTransformer::opposite($a['edge']);
      if ($expected_hex['q'] !== $b['q'] || $expected_hex['r'] !== $b['r'] || $expected_edge !== $b['edge']) {
        $findings[] = $this->finding('error', 'port_link_not_adjacent', sprintf(
          'Link %s is not sealed: exit port at (%d, %d) faces edge %d, so the entry port must be at (%d, %d) facing edge %d; it is at (%d, %d) facing edge %d.',
          $link_id, $a['q'], $a['r'], $a['edge'], $expected_hex['q'], $expected_hex['r'], $expected_edge, $b['q'], $b['r'], $b['edge']
        ), $subject, ['q' => $a['q'], 'r' => $a['r']]);
        continue;
      }
      $edges[$from_id][] = $to_id;
      if ($link['direction'] === 'bidirectional') {
        $edges[$to_id][] = $from_id;
      }
    }
    foreach ($exit_use as $exit_key => $link_ids) {
      if (count($link_ids) > 1) {
        [$pid, $port_id] = explode(':', $exit_key, 2);
        $findings[] = $this->finding('error', 'port_already_linked', sprintf('Exit port "%s" on placement %s is used by %d links; at most one is allowed.', $port_id, $pid, count($link_ids)),
          array_merge([['placement_id' => $pid], ['port_id' => $port_id]], array_map(static fn(string $id): array => ['link_id' => $id], $link_ids)));
      }
    }
    foreach ($level_ports as $key => $port) {
      if ($port['kind'] === 'exit' && !isset($exit_use[$key])) {
        [$pid, $port_id] = explode(':', $key, 2);
        $findings[] = $this->finding($profile === 'publication' ? 'warning' : 'info', 'exit_port_dangling', sprintf('Exit port "%s" on "%s" is not linked.', $port_id, $port['label']), [['placement_id' => $pid], ['port_id' => $port_id]], ['q' => $port['q'], 'r' => $port['r']]);
      }
    }

    // Entrance cardinality: incompleteness while editing, incorrectness at publication.
    $structural = $profile === 'publication' ? 'error' : 'warning';
    if ($entrances === [] && $dungeon['room_placements'] !== []) {
      $findings[] = $this->finding($structural, 'dungeon_entrance_missing', 'No placement is flagged as the level entrance.', []);
    }
    if (count($entrances) > 1) {
      $findings[] = $this->finding($structural, 'dungeon_entrance_ambiguous', sprintf(
        '%d placements are flagged as the level entrance; exactly one is allowed.',
        count($entrances)
      ), array_map(static fn(string $id): array => ['placement_id' => $id], $entrances));
    }

    // Reachability from the single entrance over sealed links.
    if (count($entrances) === 1) {
      $seen = [$entrances[0] => TRUE];
      $queue = [$entrances[0]];
      while ($queue) {
        $current = array_shift($queue);
        foreach ($edges[$current] ?? [] as $next) {
          if (!isset($seen[$next])) {
            $seen[$next] = TRUE;
            $queue[] = $next;
          }
        }
      }
      foreach ($dungeon['room_placements'] as $placement) {
        if (!isset($seen[$placement['placement_id']])) {
          $findings[] = $this->finding($structural, 'placement_unreachable', sprintf('"%s" cannot be reached from the level entrance.', $placement['label']), [['placement_id' => $placement['placement_id']]]);
        }
      }
    }

    // Regions reference placements that exist.
    foreach ($dungeon['regions'] as $region) {
      foreach ($region['placement_ids'] as $pid) {
        if (!array_key_exists($pid, $resolved)) {
          $findings[] = $this->finding('error', 'region_placement_unresolved', sprintf('Region "%s" references placement %s, which does not exist.', $region['region_id'], $pid), [['region_id' => $region['region_id']], ['placement_id' => $pid]]);
        }
      }
    }

    $counts = ['error' => 0, 'warning' => 0, 'info' => 0];
    foreach ($findings as $finding) {
      $counts[$finding['severity']]++;
    }
    return [
      'schema_version' => 'dungeon-editor-validation-result-v1',
      'profile' => $profile,
      'draft_id' => $draft['draft_id'],
      'revision' => (int) $draft['revision'],
      'is_valid' => $counts['error'] === 0,
      'findings' => $findings,
      'counts' => $counts,
      'validated_at' => gmdate(DATE_RFC3339),
    ];
  }

  /**
   * Returns the frozen room payload for a published version, or NULL.
   */
  public function roomVersion(string $version_id): ?array {
    if (!$this->isUuid($version_id)) {
      return NULL;
    }
    if (!array_key_exists($version_id, $this->roomVersionCache)) {
      $payload = $this->database->select('dungeoncrawler_content_room_versions', 'v')
        ->fields('v', ['room_payload'])
        ->condition('version_id', $version_id)
        ->execute()
        ->fetchField();
      $this->roomVersionCache[$version_id] = $payload
        ? json_decode((string) $payload, TRUE, 512, JSON_THROW_ON_ERROR)
        : NULL;
    }
    return $this->roomVersionCache[$version_id];
  }

  /**
   * The aggregate schema, loaded once.
   */
  public function aggregateSchema(): array {
    if ($this->aggregateSchema === NULL) {
      $path = rtrim($this->schemaDirectory, '/') . '/' . self::AGGREGATE_SCHEMA_FILE;
      if (!is_file($path)) {
        throw new \RuntimeException('dungeon_schema_missing:' . self::AGGREGATE_SCHEMA_FILE);
      }
      $this->aggregateSchema = json_decode((string) file_get_contents($path), TRUE, 512, JSON_THROW_ON_ERROR);
    }
    return $this->aggregateSchema;
  }

  private function blankDungeon(): array {
    return [
      'schema_version' => self::SCHEMA_VERSION,
      'dungeon_id' => 'new-dungeon-' . substr($this->uuid->generate(), 0, 8),
      'name' => 'Untitled Dungeon',
      'description' => 'A new canonical dungeon level.',
      'depth' => 1,
      'theme' => 'unthemed',
      'hex_grid' => [
        'orientation' => 'flat-top',
        'hex_size_ft' => 5,
        'origin' => ['q' => 0, 'r' => 0],
        'coordinate_system' => 'axial',
      ],
      'room_placements' => [],
      'port_links' => [],
      'regions' => [],
      'metadata' => [
        'tags' => [],
        'provenance' => ['source' => 'dungeon_editor'],
        'catalog_version' => $this->definitions->catalogVersion(),
      ],
    ];
  }

  private function loadDraftRow(string $draft_id): array {
    if (!$this->isUuid($draft_id)) {
      throw new \InvalidArgumentException('draft_id_invalid');
    }
    $row = $this->database->select('dungeoncrawler_content_dungeon_editor_drafts', 'd')
      ->fields('d')
      ->condition('draft_id', $draft_id)
      ->execute()
      ->fetchAssoc();
    if (!$row) {
      throw new \OutOfBoundsException('dungeon_draft_not_found');
    }
    $this->assertDraftAccess($row);
    return $row;
  }

  private function decodeDraft(array $row): array {
    return [
      'schema_version' => self::DRAFT_SCHEMA_VERSION,
      'draft_id' => $row['draft_id'],
      'dungeon_id' => $row['dungeon_id'],
      'base_version_id' => $row['base_version_id'],
      'revision' => (int) $row['revision'],
      'status' => $row['status'],
      'dungeon' => json_decode($row['dungeon_payload'], TRUE, 512, JSON_THROW_ON_ERROR),
      'payload_hash' => $row['payload_hash'],
      'created_by' => (int) $row['created_by'],
      'updated_by' => (int) $row['updated_by'],
      'created_at' => gmdate(DATE_RFC3339, (int) $row['created_at']),
      'updated_at' => gmdate(DATE_RFC3339, (int) $row['updated_at']),
      'published_version_id' => $row['published_version_id'],
    ];
  }

  private function assertDraftAccess(array $row): void {
    if ((int) $row['created_by'] !== (int) $this->currentUser->id()
      && !$this->currentUser->hasPermission('administer dungeoncrawler content')
      && !$this->currentUser->hasPermission('publish canonical dungeoncrawler dungeons')) {
      throw new \UnexpectedValueException('draft_access_denied');
    }
  }

  /**
   * Room-local footprint as bare {q, r} hexes.
   */
  private function roomFootprint(array $room): array {
    $hexes = [];
    foreach ($room['hexes'] ?? [] as $hex) {
      $hexes[] = ['q' => (int) $hex['q'], 'r' => (int) $hex['r']];
    }
    return $hexes;
  }

  /**
   * Room-local hexes with only the fields the map renderer styles by.
   */
  private function roomHexesForRender(array $room): array {
    $hexes = [];
    foreach ($room['hexes'] ?? [] as $hex) {
      $hexes[] = [
        'q' => (int) $hex['q'],
        'r' => (int) $hex['r'],
        'terrain_type' => $hex['terrain_type'] ?? NULL,
        'lighting' => $hex['lighting'] ?? NULL,
        'elevation_ft' => $hex['elevation_ft'] ?? 0,
      ];
    }
    return $hexes;
  }

  /**
   * Room-local ports, entry and exit, in one list.
   */
  private function roomPorts(array $room): array {
    $ports = [];
    foreach (['entry' => 'entry_ports', 'exit' => 'exit_ports'] as $kind => $key) {
      foreach ($room[$key] ?? [] as $port) {
        $ports[] = [
          'port_id' => $port['port_id'],
          'kind' => $kind,
          'q' => (int) $port['hex']['q'],
          'r' => (int) $port['hex']['r'],
          'edge' => (int) $port['edge'],
          'label' => $port['label'] ?? $port['port_id'],
          'link_kind' => $kind === 'exit' ? ($port['kind'] ?? NULL) : NULL,
          'direction' => $kind === 'exit' ? ($port['direction'] ?? NULL) : NULL,
        ];
      }
    }
    return $ports;
  }

  private function finding(string $severity, string $code, string $message, array $subjects, ?array $hex = NULL): array {
    $finding = [
      'severity' => $severity,
      'code' => $code,
      'message' => $message,
      'subjects' => $subjects,
    ];
    if ($hex !== NULL) {
      $finding['hex'] = $hex;
    }
    return $finding;
  }

  private function isUuid(string $value): bool {
    return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value);
  }

  private function encodeDungeon(array $dungeon): string {
    if (isset($dungeon['metadata']['provenance']) && is_array($dungeon['metadata']['provenance'])) {
      $dungeon['metadata']['provenance'] = (object) $dungeon['metadata']['provenance'];
    }
    return json_encode($dungeon, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
  }

}
