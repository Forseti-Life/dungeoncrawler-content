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

  private const ID_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,99}$/';

  private ?array $aggregateSchema = NULL;

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
   * Slice 3 emits the findings that can be judged from placements alone:
   * resolvable versions, overlap, and entrance cardinality. Link, reachability,
   * and region findings arrive with their authoring commands. The code list is
   * closed by the contract, so nothing outside it is ever emitted here.
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
    foreach ($dungeon['room_placements'] as $placement) {
      $placement_id = $placement['placement_id'];
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
      foreach ($this->roomFootprint($room) as $hex) {
        $level = RoomPlacementTransformer::toLevel($hex, $placement);
        if (abs($level['q']) > self::AXIAL_BOUND || abs($level['r']) > self::AXIAL_BOUND) {
          // Commands reject this before storage; a stored instance is corruption.
          throw new \RuntimeException('placement_origin_out_of_bounds:' . $placement_id);
        }
        $occupancy[RoomPlacementTransformer::hexKey($level)][] = $placement_id;
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

    if ($profile === 'publication' && $entrances === []) {
      $findings[] = $this->finding('error', 'dungeon_entrance_missing', 'No placement is flagged as the level entrance.', []);
    }
    if (count($entrances) > 1) {
      $findings[] = $this->finding('error', 'dungeon_entrance_ambiguous', sprintf(
        '%d placements are flagged as the level entrance; exactly one is allowed.',
        count($entrances)
      ), array_map(static fn(string $id): array => ['placement_id' => $id], $entrances));
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
