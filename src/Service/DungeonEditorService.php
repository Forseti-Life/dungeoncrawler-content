<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Geometry\RoomPlacementTransformer;
use Drupal\dungeoncrawler_content\Geometry\RoomPlacementContainment;
use Drupal\dungeoncrawler_content\Service\Definition\DefinitionSchemaValidator;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorDraftStatusProjection;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorWarningReview;

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
 * `version_id` on every placement. Room validation remains owned by
 * RoomEditorService and is consumed rather than reimplemented here.
 */
class DungeonEditorService {

  use CanonicalPublicationReplayTrait;

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
  // Clear street hexes a building keeps to the street's edge.
  public const STREET_EDGE_MARGIN = 3;

  private const METADATA_KEYS = ['name', 'description', 'depth', 'theme', 'kind', 'metadata'];
  private const PLACEMENT_METADATA_KEYS = ['label', 'tags', 'is_level_entrance'];
  private const LINK_KEYS = ['kind', 'direction', 'default_state', 'travel_cost', 'requirements', 'description', 'tags'];
  private const REGION_KEYS = ['name', 'placement_ids', 'description', 'environmental_effects', 'ambient_hazard_level'];

  private const ID_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,99}$/';

  /**
   * Placeholder identity of a new level; not yet the author's own.
   */
  private const BLANK_NAME = 'Untitled Dungeon';
  private const BLANK_DESCRIPTION = 'A new canonical dungeon level.';
  private const BLANK_THEME = 'unthemed';

  /**
   * Where each finding is fixed, by code.
   *
   * draft_command: an edit in this editor. room_editor: a room must be built,
   * fixed or republished. outside_editor: no editor command changes it. A
   * code missing here is a programming error and fails in routeFor().
   */
  public const FINDING_ROUTES = [
    'placement_overlap' => 'draft_command',
    'district_street_missing' => 'draft_command',
    'district_street_ambiguous' => 'draft_command',
    'district_street_background_missing' => 'room_editor',
    'district_building_near_street_edge' => 'draft_command',
    'district_building_no_street_access' => 'draft_command',
    'placement_version_unresolved' => 'room_editor',
    'room_validation_failed' => 'room_editor',
    'port_link_endpoint_missing' => 'draft_command',
    'port_link_direction_invalid' => 'draft_command',
    'port_already_linked' => 'draft_command',
    'port_link_self_reference' => 'draft_command',
    'dungeon_entrance_missing' => 'draft_command',
    'dungeon_entrance_ambiguous' => 'draft_command',
    'placement_unreachable' => 'draft_command',
    'exit_port_dangling' => 'draft_command',
    'region_placement_unresolved' => 'draft_command',
    // Command rejections.
    'placement_id_duplicate' => 'draft_command',
    'placement_origin_out_of_bounds' => 'draft_command',
    'rotation_steps_invalid' => 'draft_command',
    // Publication schema: the only rule editing relaxes is an empty level.
    'min_items' => 'draft_command',
    // Connector projection at publication.
    'connection_id_duplicate_in_dungeon' => 'draft_command',
    'connector_h3_endpoint_missing' => 'room_editor',
    'dungeon_id_invalid' => 'outside_editor',
    'dungeon_id_reserved' => 'outside_editor',
    'dungeon_id_scope_mismatch' => 'outside_editor',
    'publication_blocked_by_active_campaign' => 'outside_editor',
    // The build differs from what a storyline says about this site.
    'story_room_not_placed' => 'draft_command',
    'story_entrance_differs' => 'draft_command',
  ];

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
    protected ?ConnectorProjectionService $connectorProjection = NULL,
    protected ?StorylineAuthoringDraftService $storylines = NULL,
    protected ?EditorWarningReview $warningReview = NULL,
    protected ?ObjectImageValidationService $imageValidation = NULL,
  ) {
    $this->schemaDirectory ??= dirname(__DIR__, 2) . '/config/schemas';
    $this->connectorProjection ??= new ConnectorProjectionService($database);
  }

  /**
   * A read-only room-validation authority without a reciprocal Dungeon link.
   */
  private function roomEditor(): RoomEditorService {
    return new RoomEditorService(
      $this->database,
      $this->currentUser,
      $this->uuid,
      $this->definitions,
    );
  }

  /**
   * Lists dungeons available to the editor.
   */
  public function listDungeons(): array {
    $query = $this->database->select('dungeoncrawler_content_dungeons', 'd');
    $query->leftJoin('dungeoncrawler_content_dungeon_versions', 'v', 'v.version_id = d.published_version_id');
    $rows = $query
      ->fields('d', ['dungeon_id', 'name', 'publication_status', 'published_version_id'])
      ->fields('v', ['version', 'dungeon_payload', 'published_at'])
      ->orderBy('name')
      ->execute()
      ->fetchAll();
    $active_drafts = [];
    if ($rows !== []) {
      $draft_query = $this->database->select('dungeoncrawler_content_dungeon_editor_drafts', 'drafts')
        ->fields('drafts', ['dungeon_id'])
        ->condition('status', 'active')
        ->condition('dungeon_id', array_values(array_map(static fn(object $row): string => (string) $row->dungeon_id, $rows)), 'IN');
      foreach ($draft_query->execute()->fetchCol() as $dungeon_id) {
        $active_drafts[(string) $dungeon_id] = TRUE;
      }
    }
    $listed = array_map(static function (object $row) use ($active_drafts): array {
      $published_version_id = trim((string) ($row->published_version_id ?? ''));
      $metadata = [];
      $room_count = 0;
      if ($published_version_id !== '') {
        $payload = json_decode((string) ($row->dungeon_payload ?? ''), TRUE, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
          throw new \RuntimeException(sprintf('dungeon_aggregate_invalid: published dungeon %s payload is not an object.', (string) $row->dungeon_id));
        }
        $metadata = is_array($payload['metadata'] ?? NULL) ? $payload['metadata'] : [];
        $room_count = count((array) ($payload['room_placements'] ?? []));
      }
      return [
        'dungeon_id' => $row->dungeon_id,
        'name' => $row->name,
        'publication_status' => $row->publication_status ?? 'unpublished',
        'published_version_id' => $row->published_version_id ?? NULL,
        'published_version' => $row->version ?? NULL,
        'published_at' => (int) ($row->published_at ?? 0),
        'published_room_count' => $room_count,
        'metadata' => $metadata,
        'has_active_draft' => isset($active_drafts[(string) $row->dungeon_id]),
      ];
    }, $rows);

    // A dungeon being built for the first time exists only as an editor
    // draft until it is published. It is still a dungeon an author can open.
    $known = array_fill_keys(array_column($listed, 'dungeon_id'), TRUE);
    foreach ($this->listDrafts() as $draft) {
      $dungeon_id = (string) $draft['dungeon_id'];
      // A blank draft nobody named or placed a room in is not one yet.
      if (isset($known[$dungeon_id]) || (($draft['name'] === '' || $draft['name'] === self::BLANK_NAME) && $draft['placement_pins'] === [])) {
        continue;
      }
      $known[$dungeon_id] = TRUE;
      $listed[] = [
        'dungeon_id' => $dungeon_id,
        'name' => $draft['name'] !== '' ? $draft['name'] : $dungeon_id,
        'publication_status' => 'unpublished',
        'published_version_id' => NULL,
        'published_version' => NULL,
        'published_at' => 0,
        'published_room_count' => 0,
        'metadata' => [],
        'has_active_draft' => TRUE,
      ];
    }
    usort($listed, static fn(array $a, array $b): int => strcasecmp((string) $a['name'], (string) $b['name']));
    return $listed;
  }

  /**
   * Latest published copy per displayed dungeon title for campaign selectors.
   *
   * Distinct editor identities can share a title (notably conversion validation
   * runs). The selector shows only the newest published copy of each title.
   *
   * @return array<int,array<string,mixed>>
   */
  public function listLatestPublishedDungeons(): array {
    $latest = [];
    foreach ($this->listDungeons() as $dungeon) {
      if (($dungeon['publication_status'] ?? '') !== 'published'
        || trim((string) ($dungeon['published_version_id'] ?? '')) === ''
        || ($dungeon['metadata']['module_conversion']['execution_mode'] ?? NULL) === 'original_scene_generation') {
        continue;
      }
      $key = mb_strtolower(trim((string) $dungeon['name']));
      if ($key === '') {
        $key = (string) $dungeon['dungeon_id'];
      }
      $previous = $latest[$key] ?? NULL;
      if ($previous === NULL
        || (int) $dungeon['published_at'] > (int) $previous['published_at']
        || ((int) $dungeon['published_at'] === (int) $previous['published_at']
          && strcmp((string) $dungeon['dungeon_id'], (string) $previous['dungeon_id']) > 0)) {
        $latest[$key] = $dungeon;
      }
    }
    return array_values($latest);
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
      foreach ($dungeon['room_placements'] ?? [] as $placement) {
        $pins[] = [
          'placement_id' => (string) $placement['placement_id'],
          'room_id' => (string) $placement['room_id'],
          'version_id' => (string) $placement['version_id'],
          'label' => (string) ($placement['label'] ?? ''),
          'origin' => $placement['origin'] ?? NULL,
          'rotation_steps' => (int) ($placement['rotation_steps'] ?? 0),
          'is_level_entrance' => (bool) ($placement['is_level_entrance'] ?? FALSE),
          'tags' => array_values((array) ($placement['tags'] ?? [])),
        ];
      }
      return [
        'draft_id' => $row->draft_id,
        'dungeon_id' => $row->dungeon_id,
        'kind' => (string) ($dungeon['kind'] ?? 'dungeon'),
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
   * Active district drafts the current author may edit from a street room.
   */
  public function districtDraftsForRoom(string $room_id): array {
    $all = $this->currentUser->hasPermission('administer dungeoncrawler content')
      || $this->currentUser->hasPermission('publish canonical dungeoncrawler dungeons');
    $drafts = $this->listDrafts($all ? NULL : (int) $this->currentUser->id());
    return array_values(array_map(
      static fn(array $draft): array => array_intersect_key(
        $draft,
        array_flip(['draft_id', 'dungeon_id', 'kind', 'name', 'revision']),
      ),
      array_filter(
        $drafts,
        static fn(array $draft): bool => ($draft['kind'] ?? NULL) === 'district'
          && array_filter(
            $draft['placement_pins'],
            static fn(array $placement): bool => ($placement['room_id'] ?? NULL) === $room_id,
          ) !== [],
      ),
    ));
  }

  /**
   * Preserve an abandoned draft and its command log for audit.
   *
   * Same operation as RoomEditorService::abandonDraft(). The draft leaves
   * every list of work in progress; nothing is deleted.
   */
  public function abandonDraft(string $draft_id, int $expected_revision): array {
    $draft = $this->getDraft($draft_id);
    if ($draft['status'] !== 'active') {
      throw new \DomainException('draft_not_active');
    }
    $changed = $this->database->update('dungeoncrawler_content_dungeon_editor_drafts')
      ->fields([
        'status' => 'abandoned',
        'revision' => $expected_revision + 1,
        'updated_by' => (int) $this->currentUser->id(),
        'updated_at' => time(),
      ])
      ->condition('draft_id', $draft_id)
      ->condition('revision', $expected_revision)
      ->condition('status', 'active')
      ->execute();
    if ($changed !== 1) {
      throw new \RuntimeException('revision_conflict');
    }
    return $this->getDraft($draft_id);
  }

  /**
   * Finds current published and active-draft placements of one room.
   */
  public function roomUsage(string $room_id): array {
    if (!preg_match(self::ID_PATTERN, $room_id)) {
      throw new \InvalidArgumentException('room_id_invalid');
    }

    $published = [];
    $query = $this->database->select('dungeoncrawler_content_dungeons', 'd');
    $query->innerJoin('dungeoncrawler_content_dungeon_versions', 'v', 'v.version_id = d.published_version_id');
    $rows = $query
      ->fields('d', ['dungeon_id', 'name', 'publication_status', 'published_version_id'])
      ->fields('v', ['version', 'dungeon_payload'])
      ->execute()
      ->fetchAll();
    foreach ($rows as $row) {
      $dungeon = json_decode((string) $row->dungeon_payload, TRUE, 512, JSON_THROW_ON_ERROR);
      $placements = $this->matchingRoomPlacements($dungeon, $room_id);
      if ($placements === []) {
        continue;
      }
      $published[] = [
        'dungeon_id' => (string) $row->dungeon_id,
        'name' => (string) $row->name,
        'publication_status' => (string) $row->publication_status,
        'published_version_id' => (string) $row->published_version_id,
        'published_version' => (string) $row->version,
        'placements' => $placements,
      ];
    }

    $drafts = [];
    foreach ($this->listDrafts() as $draft) {
      $placements = array_values(array_filter(
        $draft['placement_pins'],
        static fn(array $placement): bool => $placement['room_id'] === $room_id,
      ));
      if ($placements === []) {
        continue;
      }
      $drafts[] = [
        'draft_id' => $draft['draft_id'],
        'dungeon_id' => $draft['dungeon_id'],
        'name' => $draft['name'],
        'revision' => $draft['revision'],
        'base_version_id' => $draft['base_version_id'],
        'placements' => $placements,
      ];
    }

    return [
      'published_dungeons' => $published,
      'active_dungeon_drafts' => $drafts,
    ];
  }

  /**
   * Returns placements of one canonical room from a dungeon aggregate.
   */
  private function matchingRoomPlacements(array $dungeon, string $room_id): array {
    $matches = [];
    foreach ($dungeon['room_placements'] ?? [] as $placement) {
      if (($placement['room_id'] ?? NULL) !== $room_id) {
        continue;
      }
      $matches[] = [
        'placement_id' => (string) $placement['placement_id'],
        'room_id' => (string) $placement['room_id'],
        'version_id' => (string) $placement['version_id'],
        'label' => (string) ($placement['label'] ?? ''),
        'origin' => $placement['origin'] ?? NULL,
        'rotation_steps' => (int) ($placement['rotation_steps'] ?? 0),
        'is_level_entrance' => (bool) ($placement['is_level_entrance'] ?? FALSE),
        'tags' => array_values((array) ($placement['tags'] ?? [])),
      ];
    }
    return $matches;
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
    );

    $library = [];
    foreach ($rows as $row) {
      $room = json_decode((string) $row->room_payload, TRUE, 512, JSON_THROW_ON_ERROR);
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
        'footprint' => iterator_to_array($this->roomFootprint($room), FALSE),
        'ports' => $this->roomPorts($room),
      ];
    }
    return $library;
  }

  /**
   * Creates an active draft from a published dungeon or a blank level.
   *
   * A dungeon that exists but has never been published through this editor
   * starts as a blank aggregate under the existing identity. Its legacy
   * `dungeon_data` remains non-authoritative and is never imported.
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
      $dungeon_id = $dungeon['dungeon_id'];
    }
    else {
      $row = $this->database->select('dungeoncrawler_content_dungeons', 'd')
        ->fields('d', ['dungeon_id', 'name', 'description', 'theme', 'published_version_id'])
        ->condition('dungeon_id', $dungeon_id)
        ->execute()
        ->fetchAssoc();
      if (!$row) {
        $dungeon = $this->blankDungeon();
        $dungeon['dungeon_id'] = $dungeon_id;
        $dungeon['metadata']['provenance'] = [
          'source' => 'dungeon_editor',
          'new_canonical_identity' => TRUE,
        ];
      }
      else {
        $base_version_id = $row['published_version_id'] ?: NULL;
      }
      if ($row && $base_version_id === NULL) {
        $dungeon = $this->blankDungeon();
        $dungeon['dungeon_id'] = $dungeon_id;
        if (trim((string) ($row['name'] ?? '')) !== '') {
          $dungeon['name'] = trim((string) $row['name']);
        }
        if (trim((string) ($row['description'] ?? '')) !== '') {
          $dungeon['description'] = trim((string) $row['description']);
        }
        if (trim((string) ($row['theme'] ?? '')) !== '') {
          $dungeon['theme'] = trim((string) $row['theme']);
        }
        $dungeon['metadata']['provenance'] = [
          'source' => 'dungeon_editor',
          'legacy_identity_reauthored' => TRUE,
        ];
      }
      elseif ($row) {
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
    $validation = $this->validateAggregate($draft);

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
        'level_bounds' => NULL,
      ];
      if ($room !== NULL) {
        $claim = [$placement['placement_id']];
        $entry['room'] = [
          'name' => $room['name'] ?? $placement['room_id'],
          'room_type' => $room['room_type'] ?? NULL,
          'hex_count' => count($room['hexes'] ?? []),
          'hexes' => $room['hexes'],
          'ports' => $this->roomPorts($room),
        ];
        $min_q = $min_r = PHP_INT_MAX;
        $max_q = $max_r = PHP_INT_MIN;
        foreach ($this->roomFootprint($room) as $hex) {
          $level = RoomPlacementTransformer::toLevel($hex, $placement);
          $min_q = min($min_q, $level['q']);
          $max_q = max($max_q, $level['q']);
          $min_r = min($min_r, $level['r']);
          $max_r = max($max_r, $level['r']);
          $key = RoomPlacementTransformer::hexKey($level);
          if (!isset($occupancy[$key])) {
            $occupancy[$key] = [];
          }
          if (!in_array($placement['placement_id'], $occupancy[$key], TRUE)) {
            $occupancy[$key][] = $placement['placement_id'];
          }
        }
        if ($min_q !== PHP_INT_MAX) {
          $entry['level_bounds'] = ['min_q' => $min_q, 'max_q' => $max_q, 'min_r' => $min_r, 'max_r' => $max_r];
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
      'kind' => $dungeon['kind'] ?? 'dungeon',
      'hex_grid' => $dungeon['hex_grid'],
      'metadata' => $dungeon['metadata'] ?? [],
      'placements' => $placements,
      'port_links' => $dungeon['port_links'] ?? [],
      'regions' => $dungeon['regions'] ?? [],
      'occupancy' => $occupancy,
      'validation' => $validation,
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
   * Builds one status projection shared by the GM and the Dungeon Editor UI.
   *
   * Every value comes from the saved draft, its pinned room versions and the
   * given validation result.
   */
  public function summarizeDraftState(array $draft, array $validation): array {
    $review = $this->warningReview?->summary('dungeon', $draft, $validation) ?? [];
    $dungeon = $draft['dungeon'];
    $placements = $dungeon['room_placements'];
    $resolved = 0;
    $with_contents = 0;
    $with_encounter = 0;
    $room_statuses = [];
    foreach ($placements as $placement) {
      $room = $this->roomVersion((string) $placement['version_id']);
      if ($room === NULL) {
        continue;
      }
      $resolved++;
      $families = array_map(
        static fn(array $object): string => (string) $object['definition_ref']['family'],
        $room['placements'] ?? [],
      );
      $with_contents += $families !== [] ? 1 : 0;
      $with_encounter += in_array('actor', $families, TRUE) ? 1 : 0;
      $status = $this->roomPublicationStatus((string) $placement['version_id']);
      if ($status !== NULL) {
        $room_statuses[(string) $placement['placement_id']] = $status;
      }
    }
    $entrances = count(array_filter($placements, static fn(array $placement): bool => $placement['is_level_entrance']));
    $finding_codes = array_count_values(array_column($validation['findings'], 'code'));
    $unreachable = $finding_codes['placement_unreachable'] ?? 0;
    $open_exits = $finding_codes['exit_port_dangling'] ?? 0;
    $counts = [
      'placements' => count($placements),
      'placements_resolved' => $resolved,
      'level_entrances' => $entrances,
      'port_links' => count($dungeon['port_links']),
      'regions' => count($dungeon['regions']),
      'unreachable_placements' => $unreachable,
      'open_exits' => $open_exits,
      'rooms_with_contents' => $with_contents,
      'rooms_with_encounter' => $with_encounter,
    ];
    $placed = $placements !== [];
    $is = [
      'name' => trim($dungeon['name']) !== '' && $dungeon['name'] !== self::BLANK_NAME,
      'description' => trim($dungeon['description']) !== '' && $dungeon['description'] !== self::BLANK_DESCRIPTION,
      'theme' => trim($dungeon['theme']) !== '' && $dungeon['theme'] !== self::BLANK_THEME,
      'placed' => $placed,
      'resolved' => $placed && $resolved === count($placements),
      'entrance' => $entrances === 1,
      'reachable' => $entrances === 1 && $unreachable === 0,
      'exits' => $placed && $open_exits === 0,
      'contents' => $with_contents > 0,
      'valid' => $validation['is_valid'],
    ];
    $item = static fn(string $id, string $label, bool $complete, string $detail = '', string $group = 'minimum_content'): array
      => compact('id', 'label', 'complete', 'group', 'detail');
    $checklist = [
      $item('identity_name', 'Dungeon name', $is['name']),
      $item('identity_description', 'Dungeon description', $is['description']),
      $item('identity_theme', 'Dungeon theme', $is['theme']),
      $item('rooms_placed', 'At least one room placed', $is['placed'], (string) count($placements)),
      $item('rooms_resolved', 'Every placed room uses a published room version', $is['resolved'], sprintf('%d/%d', $resolved, count($placements))),
      $item('entrance_single', 'Exactly one level entrance', $is['entrance'], (string) $entrances),
      $item('rooms_reachable', 'Every room can be reached from the entrance', $is['reachable'], sprintf('%d unreachable', $unreachable)),
      $item('exits_linked', 'Every exit is linked', $is['exits'], sprintf('%d open', $open_exits)),
      $item('contents_present', 'At least one placed room has contents', $is['contents'], sprintf('%d/%d', $with_contents, count($placements))),
      // A level without creatures is legitimate, so this never blocks.
      $item('encounter_present', 'At least one placed room has an actor', $with_encounter > 0, sprintf('%d/%d', $with_encounter, count($placements)), 'optional'),
      $item('canonical_validation', 'Validation passes', $is['valid'], sprintf('%d errors, %d warnings', $validation['counts']['error'], $validation['counts']['warning']), 'validation'),
    ];
    $result = EditorDraftStatusProjection::project('dungeon-draft-status-v1', $draft, $validation, $checklist, [
      1 => [
        'label' => 'Identity and purpose',
        'checks' => [$is['name'], $is['description'], $is['theme']],
        'metrics' => ['depth' => $dungeon['depth'], 'theme' => $dungeon['theme']],
      ],
      2 => [
        'label' => 'Rooms placed',
        'checks' => [$is['placed'], $is['resolved']],
        'metrics' => ['placements' => count($placements), 'published_versions' => sprintf('%d/%d', $resolved, count($placements))],
      ],
      3 => [
        'label' => 'Links and entrances',
        'checks' => [$is['entrance'], $is['reachable'], $is['exits']],
        'metrics' => ['level_entrances' => $entrances, 'port_links' => $counts['port_links'], 'unreachable' => $unreachable, 'open_exits' => $open_exits],
      ],
      4 => [
        'label' => 'Contents and encounters',
        'checks' => [$is['contents']],
        'metrics' => ['rooms_with_contents' => $with_contents, 'rooms_with_encounter' => $with_encounter],
      ],
      5 => [
        'label' => 'Validation',
        'checks' => EditorDraftStatusProjection::validationChecks($is['valid'], $review),
        'metrics' => ['errors' => $validation['counts']['error'], 'warnings' => $validation['counts']['warning']],
      ],
      6 => [
        'label' => 'Published',
        'checks' => [$is['valid'], $draft['status'] === 'published'],
        'metrics' => ['published_version_id' => $draft['published_version_id']],
      ],
    ], $counts) + $review;
    $result['storylines'] = array_map(
      static fn(array $story): array => array_intersect_key($story, array_flip(['draft_id', 'title', 'status'])),
      $this->storiesUsing((string) $dungeon['dungeon_id']),
    );
    // What the editor page names and links to.
    $result['name'] = (string) $dungeon['name'];
    $rooms = [];
    foreach ($placements as $placement) {
      $room_id = (string) $placement['room_id'];
      $room_status = $room_statuses[(string) $placement['placement_id']] ?? NULL;
      $rooms[$room_id] ??= [
        'room_id' => $room_id,
        'name' => (string) ($this->roomVersion((string) $placement['version_id'])['name'] ?? $room_id),
        'completion_percent' => (int) ($room_status['completion_percent'] ?? 0),
        'valid' => ($room_status['canonical_valid'] ?? FALSE) === TRUE,
      ];
    }
    $result['rooms'] = array_values($rooms);
    $room_completion = $room_statuses === []
      ? 0
      : (int) round(array_sum(array_map(
        static fn(array $status): int => (int) ($status['completion_percent'] ?? 0),
        $room_statuses,
      )) / count($room_statuses));
    $result['room_completion_percent'] = $room_completion;
    $result['completion_percent'] = EditorDraftStatusProjection::cappedByChildCompletion(
      (int) $result['completion_percent'],
      $room_completion,
    );
    $result['next_steps'] = self::recommendNextAuthoringAction($result);
    return $result;
  }

  /**
   * Storylines that name this dungeon, with the rooms each puts in it.
   */
  private function storiesUsing(string $dungeon_id): array {
    if ($this->storylines === NULL || $dungeon_id === '') {
      return [];
    }
    return $this->storylines->readStoryBriefs('dungeon', $dungeon_id)['storylines'];
  }

  /**
   * Storyline drafts that reference this dungeon, for parent navigation.
   *
   * @return array<int, array{draft_id: string, title: string, status: string, revision: int}>
   */
  public function storylineParents(string $dungeon_id): array {
    return array_map(
      static fn(array $story): array => array_intersect_key($story, array_flip([
        'draft_id',
        'title',
        'status',
        'revision',
      ])),
      $this->storiesUsing($dungeon_id),
    );
  }

  /**
   * Published dungeons no storyline names.
   *
   * @return array<int, array{dungeon_id: string, name: string, campaigns: int}>
   */
  public function unattachedDungeons(): array {
    if ($this->storylines === NULL) {
      throw new \LogicException('dungeon_editor_storylines_unavailable');
    }
    $rows = $this->database->query(
      "SELECT d.dungeon_id, d.name, (SELECT COUNT(DISTINCT c.campaign_id) FROM {dc_campaign_dungeons} c WHERE c.source_dungeon_id = d.dungeon_id OR c.dungeon_id = d.dungeon_id) AS campaigns FROM {dungeoncrawler_content_dungeons} d WHERE d.published_version_id IS NOT NULL ORDER BY d.name, d.dungeon_id"
    )->fetchAll(\PDO::FETCH_ASSOC);
    $attached = $this->storylines->placeUsageIndex()['dungeons'];
    $unattached = [];
    foreach ($rows as $row) {
      if (!isset($attached[(string) $row['dungeon_id']])) {
        $unattached[] = ['dungeon_id' => (string) $row['dungeon_id'], 'name' => (string) $row['name'], 'campaigns' => (int) $row['campaigns']];
      }
    }
    return $unattached;
  }

  /**
   * Which published dungeons place each room.
   *
   * @return array<string, array<int, array{dungeon_id: string, name: string}>>
   */
  public function roomPlacementIndex(): array {
    $query = $this->database->select('dungeoncrawler_content_dungeons', 'd');
    $query->innerJoin('dungeoncrawler_content_dungeon_versions', 'v', 'v.version_id = d.published_version_id');
    $rows = $query->fields('d', ['dungeon_id', 'name'])->fields('v', ['dungeon_payload'])->orderBy('d.name')->execute()->fetchAll();
    $index = [];
    foreach ($rows as $row) {
      $dungeon = json_decode((string) $row->dungeon_payload, TRUE, 512, JSON_THROW_ON_ERROR);
      foreach (array_unique(array_column((array) ($dungeon['room_placements'] ?? []), 'room_id')) as $room_id) {
        $index[(string) $room_id][] = ['dungeon_id' => (string) $row->dungeon_id, 'name' => (string) $row->name];
      }
    }
    $draft_query = $this->database->select('dungeoncrawler_content_dungeon_editor_drafts', 'd');
    $draft_query->leftJoin('dungeoncrawler_content_dungeons', 'canonical', 'canonical.dungeon_id = d.dungeon_id');
    $draft_rows = $draft_query
      ->fields('d', ['dungeon_id', 'dungeon_payload'])
      ->fields('canonical', ['name'])
      ->condition('d.status', 'active')
      ->condition('d.created_by', (int) $this->currentUser->id())
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($draft_rows as $row) {
      $dungeon = json_decode((string) $row['dungeon_payload'], TRUE, 512, JSON_THROW_ON_ERROR);
      foreach (array_unique(array_column((array) ($dungeon['room_placements'] ?? []), 'room_id')) as $room_id) {
        $room_id = (string) $room_id;
        $candidate = [
          'dungeon_id' => (string) $row['dungeon_id'],
          'name' => trim((string) ($row['name'] ?? $dungeon['name'] ?? $row['dungeon_id'])),
        ];
        foreach ($index[$room_id] ?? [] as $existing) {
          if ($existing['dungeon_id'] === $candidate['dungeon_id']) {
            continue 2;
          }
        }
        $index[$room_id][] = $candidate;
      }
    }
    return $index;
  }

  /**
   * Published dungeons that place a room, for parent navigation.
   *
   * @return array<int, array{dungeon_id: string, name: string}>
   */
  public function parentDungeonsForRoom(string $room_id): array {
    return $this->roomPlacementIndex()[$room_id] ?? [];
  }

  /**
   * Warnings where the build differs from what a storyline says.
   */
  private function storyFindings(array $dungeon): array {
    $placed = array_column($dungeon['room_placements'], 'room_id');
    if ($placed === []) {
      // Nothing is built yet; the status already says rooms are needed.
      return [];
    }
    $entrance_rooms = array_column(array_filter(
      $dungeon['room_placements'],
      static fn(array $placement): bool => $placement['is_level_entrance'],
    ), 'room_id');
    $findings = [];
    foreach ($this->storiesUsing((string) $dungeon['dungeon_id']) as $story) {
      $missing = array_values(array_diff(array_column($story['rooms'], 'room_id'), $placed));
      if ($missing !== []) {
        $findings[] = $this->finding('warning', 'story_room_not_placed', sprintf(
          "The storyline '%s' puts %d room(s) in this dungeon that are not placed: %s.",
          $story['title'],
          count($missing),
          implode(', ', $missing),
        ), [['storyline_draft_id' => $story['draft_id']]]);
      }
      if ($story['entrance_room'] !== '' && $entrance_rooms !== [] && !in_array($story['entrance_room'], $entrance_rooms, TRUE)) {
        $findings[] = $this->finding('warning', 'story_entrance_differs', sprintf(
          "The storyline '%s' enters this dungeon at '%s'; the level entrance here is '%s'.",
          $story['title'],
          $story['entrance_room'],
          implode(', ', $entrance_rooms),
        ), [['storyline_draft_id' => $story['draft_id']]]);
      }
    }
    return $findings;
  }

  /**
   * Owns the single next author action for the Dungeon Editor.
   *
   * @return string[]
   */
  public static function recommendNextAuthoringAction(array $state): array {
    if ($state['status'] === 'published') {
      return ['Open the published dungeon.'];
    }
    if ($state['missing_content'] !== []) {
      $label = $state['missing_content'][0];
      return [match ($label) {
        'Dungeon name' => 'Choose a name for this dungeon.',
        'Dungeon description' => 'Describe this dungeon level in one short paragraph.',
        'Dungeon theme' => 'Choose a theme for this dungeon.',
        'At least one room placed' => 'Have the GM place the first room.',
        'Every placed room uses a published room version' => 'Have the GM address the next validation finding.',
        'Exactly one level entrance' => 'Choose which room is the level entrance.',
        'Every room can be reached from the entrance' => 'Have the GM link the next unreachable room.',
        'Every exit is linked' => 'Have the GM link the next open exit.',
        'At least one placed room has contents' => 'Add contents to a placed room in the Room Editor, or place a room that has contents.',
        default => throw new \LogicException('dungeon_status_label_unmapped:' . $label),
      }];
    }
    if ($state['error_count'] > 0) {
      return ['Have the GM address the next validation finding.'];
    }
    if (($state['open_warning_count'] ?? $state['warning_count']) > 0) {
      return ['Have the GM address the next validation warning.'];
    }
    return ['Review the dungeon and check its publication readiness.'];
  }

  /**
   * A placed room's display name: its pinned version's, else its library row's.
   */
  public function roomDisplayName(string $room_id, string $version_id): ?string {
    $room = $this->roomVersion($version_id);
    if ($room !== NULL) {
      return (string) $room['name'];
    }
    $name = $this->database->select('dungeoncrawler_content_rooms', 'r')
      ->fields('r', ['name'])
      ->condition('room_id', $room_id)
      ->execute()
      ->fetchField();
    return $name === FALSE ? NULL : (string) $name;
  }

  /**
   * Publication-profile validation plus non-ended campaign guard state.
   */
  public function publicationReadiness(string $draft_id): array {
    $draft = $this->decodeDraft($this->loadDraftRow($draft_id));
    $dungeon_id = (string) ($draft['dungeon_id'] ?: ($draft['dungeon']['dungeon_id'] ?? ''));
    try {
      $validation = $this->validateAggregate($draft, 'publication');
    }
    catch (DungeonAggregateException $exception) {
      $findings = array_map(fn(array $finding): array => $this->schemaFinding($finding), $exception->getFindings());
      $validation = [
        'schema_version' => 'dungeon-editor-validation-result-v1',
        'profile' => 'publication',
        'draft_id' => $draft['draft_id'],
        'revision' => (int) $draft['revision'],
        'is_valid' => FALSE,
        'findings' => $findings,
        'counts' => ['error' => count($findings), 'warning' => 0, 'info' => 0],
        'validated_at' => gmdate(DATE_RFC3339),
      ];
    }
    if (!empty($validation['is_valid']) && $this->connectorProjection instanceof ConnectorProjectionService) {
      try {
        $this->connectorProjection->project($dungeon_id, $draft['dungeon']);
      }
      catch (DungeonEditorFindingsInterface $exception) {
        $projection_findings = array_map(
          static fn(array $finding): array => $finding + ['route' => self::routeFor((string) $finding['code'])],
          $exception->getFindings(),
        );
        $validation['findings'] = array_values(array_merge($validation['findings'], $projection_findings));
        $validation['counts']['error'] += count($projection_findings);
        $validation['is_valid'] = FALSE;
      }
    }
    $campaign_blockers = $dungeon_id !== '' ? $this->activeCampaignBlockers($dungeon_id) : [];
    $blockers = array_values(array_merge(
      array_filter($validation['findings'], static fn(array $finding): bool => ($finding['severity'] ?? 'error') === 'error'),
      $campaign_blockers
    ));

    $latest_version = '';
    if ($dungeon_id !== '') {
      $latest = $this->database->select('dungeoncrawler_content_dungeon_versions', 'v')
        ->fields('v', ['version'])
        ->condition('dungeon_id', $dungeon_id)
        ->orderBy('published_at', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchField();
      $latest_version = $latest === FALSE ? '' : (string) $latest;
    }
    $suggested_version = '1.0.0';
    if ($latest_version !== '' && preg_match('/^(\\d+)\\.(\\d+)\\.(\\d+)$/', $latest_version, $match) === 1) {
      $suggested_version = sprintf('%d.%d.%d', (int) $match[1], (int) $match[2], (int) $match[3] + 1);
    }

    return [
      'schema_version' => 'dungeon-editor-publication-readiness-v1',
      'draft_id' => $draft['draft_id'],
      'dungeon_id' => $dungeon_id,
      'revision' => (int) $draft['revision'],
      'published_version' => $latest_version,
      'suggested_version' => $suggested_version,
      'ready' => $blockers === [],
      'blockers' => $blockers,
      'validation' => $validation,
      'campaign_guard' => [
        'blocked' => $campaign_blockers !== [],
        'blockers' => $campaign_blockers,
      ],
    ];
  }

  /**
   * Publishes one revision-checked draft and projects canonical connectors.
   */
  public function publish(string $draft_id, int $expected_revision, int $uid, array $request = []): array {
    if (!$this->isUuid($draft_id)) {
      throw new \InvalidArgumentException('draft_id_invalid');
    }
    $version = trim((string) ($request['version'] ?? ''));
    if ($version === '' || strlen($version) > 32 || !preg_match('/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/', $version)) {
      throw new \DomainException('publication_version_invalid');
    }
    $publication_note = isset($request['publication_note']) ? (string) $request['publication_note'] : NULL;
    $expected_base = array_key_exists('expected_base_version_id', $request) && $request['expected_base_version_id'] !== NULL
      ? (string) $request['expected_base_version_id']
      : NULL;
    $version_id = $this->uuid->generate();

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
      if ((int) $row['revision'] !== $expected_revision) {
        throw new \RuntimeException('revision_conflict');
      }
      if ($expected_base !== NULL && (string) ($row['base_version_id'] ?? '') !== $expected_base) {
        throw new \RuntimeException('base_version_conflict');
      }

      $draft = $this->decodeDraft($row);
      $dungeon = $draft['dungeon'];
      $dungeon_id = (string) ($row['dungeon_id'] ?? '');
      if ($dungeon_id === '' || (string) ($dungeon['dungeon_id'] ?? '') !== $dungeon_id) {
        throw new DungeonCommandRejectedException('connector_projection_failed', [
          $this->finding('error', 'dungeon_id_scope_mismatch', 'Draft dungeon_id must match the aggregate dungeon_id before publication.', [['dungeon_id' => (string) ($dungeon['dungeon_id'] ?? '')]]),
        ]);
      }

      $identity = $this->database->select('dungeoncrawler_content_dungeons', 'd')
        ->fields('d', ['published_version_id'])
        ->condition('dungeon_id', $dungeon_id)
        ->forUpdate()
        ->execute()
        ->fetchAssoc();
      $now = time();
      if (!$identity) {
        $this->database->insert('dungeoncrawler_content_dungeons')
          ->fields([
            'dungeon_id' => $dungeon_id,
            'name' => (string) $dungeon['name'],
            'description' => (string) $dungeon['description'],
            'theme' => (string) ($dungeon['theme'] ?? ''),
            'dungeon_data' => $this->encodeDungeon($dungeon),
            'source_dungeon_id' => NULL,
            'created' => $now,
            'updated' => $now,
            'published_version_id' => NULL,
            'publication_status' => 'unpublished',
            'created_by' => $uid,
            'updated_by' => $uid,
          ])
          ->execute();
        $identity = ['published_version_id' => NULL];
      }
      if ((string) ($identity['published_version_id'] ?? '') !== (string) ($row['base_version_id'] ?? '')) {
        throw new \RuntimeException('base_version_conflict');
      }

      $encoded = $this->encodeDungeon($dungeon);
      $hash = hash('sha256', $encoded);
      $replay = $this->completeExistingPublicationReplay(
        'dungeoncrawler_content_dungeon_versions',
        'dungeoncrawler_content_dungeons',
        'dungeon_id',
        $dungeon_id,
        $hash,
        'dungeoncrawler_content_dungeon_editor_drafts',
        $draft_id,
        (int) $row['revision'],
      );
      if ($replay !== NULL) {
        return [
          'schema_version' => 'dungeon-editor-publication-result-v1',
          'draft_id' => $draft_id,
          'dungeon_id' => $dungeon_id,
          'published_version_id' => (string) $replay['version_id'],
          'version' => (string) $replay['version'],
          'projected_connector_count' => 0,
          ...$replay,
          'model' => $this->describe($draft_id),
        ];
      }
      if ($row['status'] !== 'active') {
        throw new \DomainException('dungeon_draft_not_active');
      }
      try {
        $validation = $this->validateAggregate($draft, 'publication');
      }
      catch (DungeonAggregateException $exception) {
        throw new DungeonCommandRejectedException('dungeon_validation_failed', array_map(fn(array $finding): array => $this->schemaFinding($finding), $exception->getFindings()), $exception);
      }
      $errors = array_values(array_filter($validation['findings'], static fn(array $finding): bool => $finding['severity'] === 'error'));
      if ($errors !== []) {
        throw new DungeonCommandRejectedException('dungeon_validation_failed', $validation['findings']);
      }
      $campaign_blockers = $this->activeCampaignBlockers($dungeon_id);
      if ($campaign_blockers !== []) {
        throw new DungeonCommandRejectedException('publication_blocked_by_active_campaign', $campaign_blockers);
      }
      $projected_rows = $this->connectorProjection->project($dungeon_id, $dungeon);

      $latest_version = $this->database->select('dungeoncrawler_content_dungeon_versions', 'v')
        ->fields('v', ['version'])
        ->condition('dungeon_id', $dungeon_id)
        ->orderBy('published_at', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchField();
      if ($latest_version && version_compare($version, (string) $latest_version, '<=')) {
        throw new \DomainException('version_must_increase');
      }

      $duplicate = $this->database->select('dungeoncrawler_content_dungeon_versions', 'v')
        ->fields('v', ['version_id'])
        ->condition('dungeon_id', $dungeon_id)
        ->condition('version', $version)
        ->execute()
        ->fetchField();
      if ($duplicate) {
        throw new \DomainException('version_already_exists');
      }

      $this->database->insert('dungeoncrawler_content_dungeon_versions')
        ->fields([
          'version_id' => $version_id,
          'dungeon_id' => $dungeon_id,
          'version' => $version,
          'schema_version' => self::SCHEMA_VERSION,
          'dungeon_payload' => $encoded,
          'payload_hash' => $hash,
          'catalog_version' => (string) ($dungeon['metadata']['catalog_version'] ?? ''),
          'publication_note' => $publication_note,
          'source' => 'dungeon_editor',
          'published_by' => $uid,
          'published_at' => $now,
        ])
        ->execute();

      $this->database->delete('dungeoncrawler_content_connections')
        ->condition('dungeon_id', $dungeon_id)
        ->execute();
      foreach ($projected_rows as $projected_row) {
        $this->database->insert('dungeoncrawler_content_connections')
          ->fields($projected_row + ['created' => $now, 'updated' => $now])
          ->execute();
      }

      $this->database->update('dungeoncrawler_content_dungeons')
        ->fields([
          'name' => (string) $dungeon['name'],
          'description' => (string) $dungeon['description'],
          'theme' => (string) ($dungeon['theme'] ?? ''),
          'dungeon_data' => $encoded,
          'published_version_id' => $version_id,
          'publication_status' => 'published',
          'updated_by' => $uid,
          'updated' => $now,
        ])
        ->condition('dungeon_id', $dungeon_id)
        ->execute();

      $this->database->update('dungeoncrawler_content_dungeon_editor_drafts')
        ->fields([
          'status' => 'published',
          'published_version_id' => $version_id,
          'updated_by' => $uid,
          'updated_at' => $now,
        ])
        ->condition('draft_id', $draft_id)
        ->condition('revision', (int) $row['revision'])
        ->execute();

      $this->database->insert('dungeoncrawler_content_dungeon_editor_commands')
        ->fields([
          'command_id' => $this->uuid->generate(),
          'draft_id' => $draft_id,
          'base_revision' => (int) $row['revision'],
          'result_revision' => (int) $row['revision'],
          'command_type' => 'publish_dungeon_version',
          'command_payload' => $this->encode(['expected_revision' => $expected_revision, 'version' => $version, 'publication_note' => $publication_note]),
          'inverse_payload' => NULL,
          'issued_by' => $uid,
          'issued_at' => $now,
          'result_hash' => $hash,
        ])
        ->execute();
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
    unset($transaction);

    return [
      'schema_version' => 'dungeon-editor-publication-result-v1',
      'draft_id' => $draft_id,
      'dungeon_id' => $dungeon_id,
      'published_version_id' => $version_id,
      'version' => $version,
      'projected_connector_count' => count($projected_rows),
      'model' => $this->describe($draft_id),
    ];
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
          if ($key === 'metadata') {
            if (!is_array($value) || array_is_list($value)) {
              throw new DungeonCommandPayloadException('dungeon_command_payload_invalid', [$this->payloadFinding('/changes/metadata', 'metadata must be an object.')]);
            }
            $dungeon['metadata'] = array_replace_recursive(
              is_array($dungeon['metadata'] ?? NULL) ? $dungeon['metadata'] : [],
              $value
            );
          }
          else {
            $dungeon[$key] = $value;
          }
        }
        break;

      case 'place_room':
        $room_id = $payload['room_id'];
        $version_id = $payload['version_id'];
        if (!is_string($room_id) || !preg_match(self::ID_PATTERN, $room_id) || !is_string($version_id)) {
          throw new DungeonCommandPayloadException('dungeon_command_payload_invalid', [$this->payloadFinding('/room_id', 'room_id and version_id must be strings.')]);
        }
        $room = $this->resolvePlacementVersion($version_id, $room_id);
        if (($dungeon['kind'] ?? 'dungeon') === 'district' && ($room['room_type'] ?? '') !== 'street') {
          $has_street = FALSE;
          foreach ($dungeon['room_placements'] ?? [] as $existing_placement) {
            $existing_room = $this->resolvePlacementVersion(
              (string) ($existing_placement['version_id'] ?? ''),
              (string) ($existing_placement['room_id'] ?? ''),
            );
            if (($existing_room['room_type'] ?? '') === 'street') {
              $has_street = TRUE;
              break;
            }
          }
          if (!$has_street) {
            throw new DungeonCommandRejectedException('district_ground_required', [
              $this->finding(
                'error',
                'district_ground_required',
                'Create and place the district street ground before placing any building room.',
                [],
              ),
            ]);
          }
        }
        // G1 generation plans may supply placement_id so later same-plan
        // link_ports steps can reference previewed placements before apply.
        $placement_id = isset($payload['placement_id']) ? (string) $payload['placement_id'] : $this->uuid->generate();
        if (!$this->isUuid($placement_id)) {
          throw new DungeonCommandPayloadException('dungeon_command_payload_invalid', [$this->payloadFinding('/placement_id', 'placement_id must be a uuid when supplied.')]);
        }
        foreach ($dungeon['room_placements'] as $existing) {
          if (($existing['placement_id'] ?? NULL) === $placement_id) {
            throw new DungeonCommandRejectedException('placement_id_duplicate', [
              $this->finding('error', 'placement_id_duplicate', sprintf('Placement id %s already exists in this draft.', $placement_id), [['placement_id' => $placement_id]]),
            ]);
          }
        }
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
        $dungeon['port_links'][] = $this->newLink($payload['from'], $payload['to'], $payload);
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

  /**
   * Placements that genuinely compete for one hex. In a district a building
   * overlays the street and overrides its hex, so the street never competes.
   */
  private function competingClaims(array $ids, bool $district, array $placement_rooms, array $placement_hexes = []): array {
    $ids = array_values(array_unique($ids));
    if ($district) {
      $ids = array_values(array_filter($ids, static fn(string $id): bool => ($placement_rooms[$id]['room_type'] ?? NULL) !== 'street'));
    }
    if ($placement_hexes === []) {
      return $ids;
    }
    $competing = [];
    foreach (RoomPlacementContainment::invalidPairs($ids, $placement_hexes) as $pair) {
      $competing[$pair[0]] = TRUE;
      $competing[$pair[1]] = TRUE;
    }
    return array_values(array_keys($competing));
  }

  /**
   * Buildings whose entry port stands on a street hex.
   *
   * The door belongs to the building room: an entry port on a street hex is
   * the way in. No street door or stored link is involved, so moving a
   * building needs nothing relinked.
   *
   * @return array<string, true>
   *   Placement ids.
   */
  private function streetContacts(array $level_ports, array $occupancy, array $streets): array {
    $contacts = [];
    foreach ($level_ports as $key => $port) {
      if ($port['kind'] !== 'entry') {
        continue;
      }
      [$placement_id] = explode(':', $key, 2);
      if (in_array($placement_id, $streets, TRUE)) {
        continue;
      }
      if (array_intersect($streets, $occupancy[RoomPlacementTransformer::hexKey($port)] ?? []) !== []) {
        $contacts[$placement_id] = TRUE;
      }
    }
    return $contacts;
  }

  private function newLink(array $from, array $to, array $fields): array {
    $extras = $this->optionalFields($fields, ['from', 'to', 'kind', 'direction', 'default_state', 'entry_port_id'], self::LINK_KEYS);
    foreach (['kind', 'direction', 'default_state'] as $key) {
      if (!is_string($fields[$key] ?? NULL)) {
        throw new DungeonCommandPayloadException('dungeon_command_payload_invalid', [$this->payloadFinding('/' . $key, $key . ' is required.')]);
      }
    }
    return [
      'link_id' => $this->uuid->generate(),
      'from' => ['placement_id' => $from['placement_id'], 'port_id' => $from['port_id']],
      'to' => ['placement_id' => $to['placement_id'], 'port_id' => $to['port_id']],
      'kind' => $fields['kind'],
      'direction' => $fields['direction'],
      'default_state' => $fields['default_state'],
      'travel_cost' => $extras['travel_cost'] ?? 0,
      'requirements' => $extras['requirements'] ?? [],
      'description' => $extras['description'] ?? '',
      'tags' => $extras['tags'] ?? [],
    ];
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
   * Level-space footprint and ports of every placement in a dungeon draft.
   *
   * The one place placements are resolved to level hexes; validation and
   * district inspection both read it.
   *
   * @return array{findings: array, occupancy: array, entrances: array, level_ports: array, placement_rooms: array, resolved: array}
   */
  private function levelGeometry(array $dungeon): array {
    $findings = [];
    $occupancy = [];
    $entrances = [];
    // Level-space ports by "placement_id:port_id" for resolved placements.
    $level_ports = [];
    $placement_rooms = [];
    $resolved = [];
    $placement_hexes = [];
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
      $placement_rooms[$placement_id] = $room;
      $placement_hexes[$placement_id] = RoomPlacementContainment::hexes($room, $placement);
      $claim = [$placement_id];
      foreach ($this->roomFootprint($room) as $hex) {
        $level = RoomPlacementTransformer::toLevel($hex, $placement);
        if (abs($level['q']) > self::AXIAL_BOUND || abs($level['r']) > self::AXIAL_BOUND) {
          // Commands reject this before storage; a stored instance is corruption.
          throw new \RuntimeException('placement_origin_out_of_bounds:' . $placement_id);
        }
        $key = RoomPlacementTransformer::hexKey($level);
        if (isset($occupancy[$key])) {
          $occupancy[$key][] = $placement_id;
        }
        else {
          $occupancy[$key] = $claim;
        }
      }
      foreach ($this->roomPorts($room) as $port) {
        $level_ports[$placement_id . ':' . $port['port_id']] = [
          'kind' => $port['kind'],
          'label' => $placement['label'],
        ] + RoomPlacementTransformer::toLevelPort(['q' => $port['q'], 'r' => $port['r']], $port['edge'], $placement);
      }
    }

    return [
      'findings' => $findings,
      'occupancy' => $occupancy,
      'entrances' => $entrances,
      'level_ports' => $level_ports,
      'placement_rooms' => $placement_rooms,
      'resolved' => $resolved,
      'placement_hexes' => $placement_hexes,
    ];
  }

  /**
   * Lists the exact data points that break a district layout.
   *
   * Read-only and built on the same level geometry as validation, so it can
   * name every overlapping hex, link gap and unused door where validation
   * reports the first of each.
   *
   * @return array<string, mixed>
   */
  public function inspectDistrict(array $draft): array {
    $dungeon = $draft['dungeon'];
    $geometry = $this->levelGeometry($dungeon);
    $labels = [];
    foreach ($dungeon['room_placements'] as $placement) {
      $labels[$placement['placement_id']] = $placement['label'];
    }
    $streets = array_keys(array_filter($geometry['placement_rooms'], static fn(array $room): bool => ($room['room_type'] ?? NULL) === 'street'));

    $overlaps = [];
    foreach ($geometry['occupancy'] as $key => $ids) {
      $ids = $this->competingClaims($ids, ($dungeon['kind'] ?? 'dungeon') === 'district', $geometry['placement_rooms'], $geometry['placement_hexes']);
      sort($ids);
      for ($i = 0; $i < count($ids); $i++) {
        for ($j = $i + 1; $j < count($ids); $j++) {
          $pair = $ids[$i] . '|' . $ids[$j];
          $overlaps[$pair]['placements'] = [$labels[$ids[$i]], $labels[$ids[$j]]];
          [$q, $r] = array_map('intval', explode(':', $key));
          $overlaps[$pair]['hexes'][] = ['q' => $q, 'r' => $r];
        }
      }
    }
    $overlap_rows = [];
    foreach ($overlaps as $row) {
      if (!empty($row['hexes'])) {
        $row['overlap_count'] = count($row['hexes']);
        $row['hexes'] = array_slice($row['hexes'], 0, 40);
        $overlap_rows[] = $row;
      }
    }

    $contacts = $this->streetContacts($geometry['level_ports'], $geometry['occupancy'], $streets);
    $buildings = [];
    foreach ($dungeon['room_placements'] as $placement) {
      $id = $placement['placement_id'];
      if (!in_array($id, $streets, TRUE) && !empty($geometry['resolved'][$id]) && !isset($contacts[$id])) {
        $buildings[] = ['placement' => $placement['label'], 'problem' => 'no_entry_port_on_the_street'];
      }
    }

    $crowding = $this->buildingsCrowdingStreetEdge($geometry['occupancy'], $streets, $labels);

    return [
      'streets' => array_map(static fn(string $id): string => $labels[$id], $streets),
      'level_entrances' => array_map(static fn(string $id): string => $labels[$id], $geometry['entrances']),
      'overlapping_hexes' => $overlap_rows,
      'buildings_without_street_access' => $buildings,
      'buildings_near_street_edge' => $crowding,
    ];
  }

  /**
   * Buildings with a hex outside the street or within the margin of its edge.
   *
   * A district street should hold every building well inside its borders, so
   * the party can walk all the way around each one.
   *
   * @param array<string, list<string>> $occupancy
   *   Placement ids by hex key.
   * @param list<string> $streets
   * @param array<string, string> $labels
   *
   * @return list<array<string, mixed>>
   */
  private function buildingsCrowdingStreetEdge(array $occupancy, array $streets, array $labels): array {
    if ($streets === []) {
      return [];
    }
    $street_hexes = [];
    $building_hexes = [];
    foreach ($occupancy as $key => $ids) {
      foreach ($ids as $id) {
        if (in_array($id, $streets, TRUE)) {
          $street_hexes[$key] = TRUE;
        }
        else {
          $building_hexes[$id][] = $key;
        }
      }
    }
    $margin = self::STREET_EDGE_MARGIN;
    $rows = [];
    foreach ($building_hexes as $id => $keys) {
      $worst = NULL;
      foreach ($keys as $key) {
        [$q, $r] = array_map('intval', explode(':', $key));
        for ($dq = -$margin; $dq <= $margin; $dq++) {
          for ($dr = max(-$margin, -$dq - $margin); $dr <= min($margin, -$dq + $margin); $dr++) {
            if (!isset($street_hexes[($q + $dq) . ':' . ($r + $dr)])) {
              $worst = ['q' => $q, 'r' => $r];
              break 3;
            }
          }
        }
      }
      if ($worst !== NULL) {
        $rows[] = ['placement' => $labels[$id] ?? $id, 'hex' => $worst, 'margin' => $margin, 'problem' => 'within_margin_of_street_edge'];
      }
    }
    return $rows;
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

    $geometry = $this->levelGeometry($dungeon);
    $findings = $geometry['findings'];
    $occupancy = $geometry['occupancy'];
    $entrances = $geometry['entrances'];
    $level_ports = $geometry['level_ports'];
    $placement_rooms = $geometry['placement_rooms'];
    $resolved = $geometry['resolved'];
    foreach ($placement_rooms as $placement_id => $room) {
      $placement = array_values(array_filter(
        (array) $dungeon['room_placements'],
        static fn(array $candidate): bool => ($candidate['placement_id'] ?? '') === $placement_id,
      ))[0] ?? NULL;
      $version_id = (string) ($placement['version_id'] ?? '');
      $room_status = $version_id !== '' ? $this->roomPublicationStatus($version_id) : NULL;
      if ($room_status !== NULL && ($room_status['canonical_valid'] ?? FALSE) !== TRUE) {
        $findings[] = $this->finding(
          $profile === 'publication' ? 'error' : 'warning',
          'room_validation_failed',
          sprintf(
            'Placed room "%s" fails publication validation: %d error(s). Fix it in the Room Editor before publishing the dungeon.',
            (string) ($room['name'] ?? $placement_id),
            (int) ($room_status['error_count'] ?? 0),
          ),
          [[
            'placement_id' => $placement_id,
            'room_id' => (string) ($placement['room_id'] ?? ''),
            'version_id' => $version_id,
            'completion_percent' => (int) ($room_status['completion_percent'] ?? 0),
          ]],
        );
      }
    }

    $reported = [];
    // In a district a building overlays the street and its entry port on a
    // street hex is the way in; rooms themselves never share a hex.
    $district = ($dungeon['kind'] ?? 'dungeon') === 'district';
    foreach ($occupancy as $key => $ids) {
      $pairs = RoomPlacementContainment::invalidPairs(
        $district
          ? array_values(array_filter($ids, static fn(string $id): bool => ($placement_rooms[$id]['room_type'] ?? NULL) !== 'street'))
          : $ids,
        $geometry['placement_hexes'],
      );
      if ($pairs === []) {
        continue;
      }
      sort($ids);
      $pair = implode('|', $pairs[0]);
      if (isset($reported[$pair])) {
        continue;
      }
      [$q, $r] = array_map('intval', explode(':', $key));
      $reported[$pair] = TRUE;
      $findings[] = $this->finding('error', 'placement_overlap', sprintf(
        'Placements %s claim the same level hex, first at (%d, %d).',
        implode(' and ', $pairs[0]),
        $q,
        $r
      ), array_map(static fn(string $id): array => ['placement_id' => $id], $pairs[0]), ['q' => $q, 'r' => $r]);
    }

    if ($district) {
      $streets = array_keys(array_filter($placement_rooms, static fn(array $room): bool => ($room['room_type'] ?? NULL) === 'street'));
      if ($streets === []) {
        $findings[] = $this->finding('error', 'district_street_missing', 'A district needs exactly one street room; none is placed.', []);
      }
      elseif (count($streets) > 1) {
        $findings[] = $this->finding('error', 'district_street_ambiguous', 'A district needs exactly one street room; several are placed.', array_map(static fn(string $id): array => ['placement_id' => $id], $streets));
      }
      foreach ($streets as $street_id) {
        if (empty($placement_rooms[$street_id]['metadata']['background']['url'])) {
          $findings[] = $this->finding('warning', 'district_street_background_missing', 'The street room has no background art, so the district has nothing to draw.', [['placement_id' => $street_id]]);
        }
      }
      $labels = [];
      foreach ($dungeon['room_placements'] as $placement) {
        $labels[$placement['placement_id']] = $placement['label'];
      }
      foreach ($this->buildingsCrowdingStreetEdge($occupancy, $streets, $labels) as $crowded) {
        $findings[] = $this->finding('warning', 'district_building_near_street_edge', sprintf(
          '%s is within %d hexes of the street edge or outside it; move it (remove the placement and plan it again, or place the building over a door nearer the centre of the street) so the party can walk around it.',
          $crowded['placement'], $crowded['margin']
        ), [], $crowded['hex']);
      }
      $street_contacts = $this->streetContacts($level_ports, $occupancy, $streets);
      foreach ($dungeon['room_placements'] as $placement) {
        $id = $placement['placement_id'];
        if (!empty($resolved[$id]) && !in_array($id, $streets, TRUE) && !isset($street_contacts[$id])) {
          $findings[] = $this->finding('warning', 'district_building_no_street_access', sprintf('"%s" has no entry port on a street hex, so the party cannot walk into it from the street; move it so an entry port sits on the street.', $placement['label']), [['placement_id' => $id]]);
        }
      }
    }

    // Links: endpoints, direction, self reference, arity. A link joins an exit
    // port to an entry port wherever the two rooms sit on the level.
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
      $edges[$from_id][] = $to_id;
      if ($link['direction'] === 'bidirectional') {
        $edges[$to_id][] = $from_id;
      }
    }
    if (!$district) {
      foreach ($geometry['placement_hexes'] as $child_id => $child_hexes) {
        $parent_id = '';
        $parent_size = PHP_INT_MAX;
        foreach ($geometry['placement_hexes'] as $candidate_id => $candidate_hexes) {
          if ($candidate_id === $child_id || count($candidate_hexes) >= $parent_size) {
            continue;
          }
          if (RoomPlacementContainment::strictlyContains($candidate_hexes, $child_hexes)) {
            $parent_id = $candidate_id;
            $parent_size = count($candidate_hexes);
          }
        }
        if ($parent_id !== '') {
          $edges[$parent_id][] = $child_id;
          $edges[$child_id][] = $parent_id;
        }
      }
    }
    foreach ($street_contacts ?? [] as $building_id => $_) {
      foreach ($streets as $street_id) {
        $edges[$street_id][] = $building_id;
        $edges[$building_id][] = $street_id;
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
      // A district building is entered from the street and ends there.
      if ($port['kind'] === 'exit' && !isset($exit_use[$key]) && !$district) {
        [$pid, $port_id] = explode(':', $key, 2);
        $findings[] = $this->finding($profile === 'publication' ? 'error' : 'info', 'exit_port_dangling', sprintf('Exit port "%s" on "%s" is not linked.', $port_id, $port['label']), [['placement_id' => $pid], ['port_id' => $port_id]], ['q' => $port['q'], 'r' => $port['r']]);
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

    // Reachability from the single entrance over links.
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

    $findings = [...$findings, ...$this->storyFindings($dungeon)];
    if ($this->imageValidation !== NULL) {
      $findings = [
        ...$findings,
        ...$this->imageValidation->warnings('dungeon', (string) ($dungeon['dungeon_id'] ?? '')),
      ];
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

  protected function roomPublicationStatus(string $version_id): ?array {
    $room = $this->roomVersion($version_id);
    if ($room === NULL) {
      return NULL;
    }
    $validation = $this->roomEditor()->validateAggregate($room, 'publication');
    $validation['profile'] = 'publication';
    return $this->roomEditor()->summarizeDraftState([
      'draft_id' => $version_id,
      'revision' => 0,
      'status' => 'published',
      'published_version_id' => $version_id,
      'room' => $room,
    ], $validation);
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
      'name' => self::BLANK_NAME,
      'description' => self::BLANK_DESCRIPTION,
      'depth' => 1,
      'theme' => self::BLANK_THEME,
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
  private function roomFootprint(array $room): \Generator {
    foreach ($room['hexes'] ?? [] as $hex) {
      yield ['q' => (int) $hex['q'], 'r' => (int) $hex['r']];
    }
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
      'route' => self::routeFor($code),
      'subjects' => $subjects,
    ];
    if ($hex !== NULL) {
      $finding['hex'] = $hex;
    }
    return $finding;
  }

  /**
   * The declared fix route for a finding code.
   */
  public static function routeFor(string $code): string {
    return self::FINDING_ROUTES[$code]
      ?? throw new \LogicException('dungeon_finding_route_undeclared:' . $code);
  }

  private function schemaFinding(array $finding): array {
    $normalized = [
      'severity' => 'error',
      'code' => (string) $finding['code'],
      'message' => (string) $finding['message'],
      'route' => self::routeFor((string) $finding['code']),
      'subjects' => isset($finding['pointer']) ? [['pointer' => (string) $finding['pointer']]] : [],
    ];
    foreach (['pointer', 'schema_pointer'] as $key) {
      if (isset($finding[$key])) {
        $normalized[$key] = $finding[$key];
      }
    }
    return $normalized;
  }

  /**
   * Reads only campaign identity tables to block republishing live dungeons.
   */
  private function activeCampaignBlockers(string $dungeon_id): array {
    $rows = $this->database->query(
      'SELECT c.id AS campaign_id, c.name AS campaign_name, c.status, cd.dungeon_id, cd.source_dungeon_id
       FROM {dc_campaign_dungeons} cd
       INNER JOIN {dc_campaigns} c ON c.id = cd.campaign_id
       WHERE (cd.dungeon_id = :dungeon_id OR cd.source_dungeon_id = :dungeon_id)
       AND c.status NOT IN (:ended[])',
      [
        ':dungeon_id' => $dungeon_id,
        ':ended[]' => ['completed', 'archived', 'ended', 'cancelled'],
      ]
    )->fetchAll(\PDO::FETCH_ASSOC);
    return array_map(function (array $row) use ($dungeon_id): array {
      return $this->finding(
        'error',
        'publication_blocked_by_active_campaign',
        sprintf('Dungeon %s is referenced by non-ended campaign %s (%s).', $dungeon_id, $row['campaign_name'] ?? $row['campaign_id'], $row['status'] ?? 'unknown'),
        [['campaign_id' => (int) $row['campaign_id']], ['dungeon_id' => $dungeon_id]]
      );
    }, $rows ?: []);
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
