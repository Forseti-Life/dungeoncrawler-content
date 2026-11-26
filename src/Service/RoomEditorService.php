<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\dungeoncrawler_content\Geometry\PlacementRules;
use Drupal\dungeoncrawler_content\Geometry\RoomPlacementTransformer;
use Drupal\dungeoncrawler_content\Geometry\RoomPortEdgePolicy;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorDraftStatusProjection;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorWarningReview;

/**
 * Canonical room authoring authority for drafts, commands, and publication.
 */
class RoomEditorService {

  use CanonicalPublicationReplayTrait;


  private const TERRAIN_TYPES = [
    'stone_floor', 'rough_stone', 'smooth_stone', 'dirt', 'mud', 'sand',
    'water_shallow', 'water_deep', 'ice', 'lava', 'fungal_growth', 'bone',
    'crystal', 'metal_grate', 'wooden_floor', 'carpet', 'rubble', 'void', 'wall',
  ];

  /**
   * Families an area shape may place; actors keep their own tools.
   */
  private const SHAPE_FAMILIES = ['obstacle', 'item'];

  private const MAX_BULK = 5000;
  public const MAX_STREET_HEXES = 50000;
  private const MAX_ROOM_HEXES = 10000;

  private const MAX_PLACEMENTS = 2500;

  private const LIGHTING_LEVELS = ['bright_light', 'dim_light', 'darkness', 'magical_darkness'];

  private const ROOM_TYPES = [
    'corridor', 'chamber', 'cavern', 'hall', 'shrine', 'vault', 'lair', 'nest',
    'workshop', 'library', 'prison', 'throne_room', 'armory', 'pantry', 'garden',
    'pool', 'mine', 'crypt', 'laboratory', 'barracks', 'marketplace', 'arena',
    'boss_chamber', 'entrance', 'exit', 'stairwell', 'crossroads', 'dead_end',
    'trap_room', 'puzzle_room', 'vault_room', 'safe_room', 'street',
  ];

  private const SIZE_CATEGORIES = ['tiny', 'small', 'medium', 'large', 'huge', 'gargantuan'];
  private const SIZE_HEX_RANGES = [
    'tiny' => [1, 12],
    'small' => [1, 24],
    'medium' => [1, 45],
    'large' => [7, 75],
    'huge' => [20, 120],
    'gargantuan' => [40, 500],
  ];

  /**
   * Placeholder identity of a new room; not yet the author's own.
   */
  private const BLANK_NAME = 'Untitled Room';
  private const BLANK_DESCRIPTION = 'A new canonical room.';

  /**
   * Where each finding is fixed, by code.
   *
   * draft_command: an edit in this editor. definition_editor: a placed
   * object's definition is missing or invalid. A code missing here is a
   * programming error and fails in finding().
   */
  public const FINDING_ROUTES = [
    'required_field_missing' => 'draft_command',
    'unknown_room_field' => 'draft_command',
    'schema_version_invalid' => 'draft_command',
    'room_id_invalid' => 'draft_command',
    'room_name_invalid' => 'draft_command',
    'room_description_invalid' => 'draft_command',
    'room_description_insufficient' => 'draft_command',
    'room_geometry_scale_mismatch' => 'draft_command',
    'room_geometry_shape_mismatch' => 'draft_command',
    'room_type_invalid' => 'draft_command',
    'size_category_invalid' => 'draft_command',
    'room_terrain_invalid' => 'draft_command',
    'room_lighting_invalid' => 'draft_command',
    'room_hex_count_invalid' => 'draft_command',
    'room_placement_count_invalid' => 'draft_command',
    'placement_gap_open' => 'draft_command',
    'room_entry_port_count_invalid' => 'draft_command',
    'room_exit_port_count_invalid' => 'draft_command',
    'hex_coordinate_invalid' => 'draft_command',
    'duplicate_hex' => 'draft_command',
    'terrain_type_invalid' => 'draft_command',
    'hex_elevation_invalid' => 'draft_command',
    'hex_lighting_invalid' => 'draft_command',
    'placement_outside_room' => 'draft_command',
    'placement_family_invalid' => 'draft_command',
    'placement_definition_ref_invalid' => 'draft_command',
    'placement_room_id_mismatch' => 'draft_command',
    'placement_facing_invalid' => 'draft_command',
    'placement_elevation_invalid' => 'draft_command',
    'placement_footprint_invalid' => 'draft_command',
    'placement_collision' => 'draft_command',
    'definition_not_found' => 'definition_editor',
    'environmental_effects_invalid' => 'draft_command',
    'metadata_tags_invalid' => 'draft_command',
    'gameplay_defaults_invalid' => 'draft_command',
    'instance_id_invalid' => 'draft_command',
    'instance_id_duplicate' => 'draft_command',
    'port_outside_room' => 'draft_command',
    'port_edge_not_boundary' => 'draft_command',
    'port_id_duplicate' => 'draft_command',
    'port_invalid' => 'draft_command',
    'default_entry_required' => 'draft_command',
    'room_has_no_placements' => 'draft_command',
    'room_background_invalid' => 'draft_command',
    'street_background_missing' => 'draft_command',
    'street_has_no_doors' => 'draft_command',
    // Raised by the GM tools around a draft, not by aggregate validation.
    'room_draft_required' => 'draft_command',
    'room_id_required' => 'draft_command',
    'draft_not_active' => 'draft_command',
  ];

  public function __construct(
    protected Connection $database,
    protected AccountProxyInterface $currentUser,
    protected UuidInterface $uuid,
    protected CanonicalDefinitionService $definitions,
    protected ?StorylineAuthoringDraftService $storylines = NULL,
    protected ?DungeonEditorService $dungeons = NULL,
    protected ?EditorWarningReview $warningReview = NULL,
  ) {}

  /**
   * Lists canonical rooms available for editing.
   */
  public function listRooms(): array {
    return array_map(static fn(object $row): array => [
      'room_id' => $row->room_id,
      'name' => $row->name,
      'publication_status' => $row->publication_status ?? 'unpublished',
      'published_version_id' => $row->published_version_id ?? NULL,
    ], $this->database->select('dungeoncrawler_content_rooms', 'r')
      ->fields('r', ['room_id', 'name', 'publication_status', 'published_version_id'])
      ->orderBy('name')
      ->execute()
      ->fetchAll());
  }
  /**
   * Active drafts, optionally limited to those the given author created.
   *
   * A read-only listing for the editor suite hub. Each entry carries the
   * decoded room name so the hub can label a resume link without loading the
   * full draft; an undecodable payload throws rather than yielding a blank.
   */
  public function listDrafts(?int $created_by = NULL): array {
    $query = $this->database->select('dungeoncrawler_content_room_editor_drafts', 'd')
      ->fields('d', ['draft_id', 'room_id', 'base_version_id', 'revision', 'status', 'room_payload', 'created_by', 'updated_by', 'updated_at'])
      ->condition('status', 'active')
      ->orderBy('updated_at', 'DESC');
    if ($created_by !== NULL) {
      $query->condition('created_by', $created_by);
    }
    return array_map(static function (object $row): array {
      $room = json_decode((string) $row->room_payload, TRUE, 512, JSON_THROW_ON_ERROR);
      return [
        'draft_id' => $row->draft_id,
        'room_id' => $row->room_id,
        'name' => (string) ($room['name'] ?? ''),
        'base_version_id' => $row->base_version_id,
        'revision' => (int) $row->revision,
        'status' => $row->status,
        'created_by' => (int) $row->created_by,
        'updated_by' => (int) $row->updated_by,
        'updated_at' => (int) $row->updated_at,
      ];
    }, $query->execute()->fetchAll());
  }

  /**
   * Creates an active draft from a canonical room or a blank room.
   *
   * A dedicated draft from the published version is for callers that will
   * publish it immediately without including any pending author edits.
   */
  public function createDraft(?string $room_id = NULL, ?string $name = NULL, bool $from_published_version = FALSE): array {
    $uid = (int) $this->currentUser->id();
    $room_id = $room_id !== NULL ? trim($room_id) : NULL;
    if ($name !== NULL && (trim($name) === '' || mb_strlen($name) > 200)) {
      throw new \InvalidArgumentException('new_room_name_invalid');
    }
    if ($room_id !== NULL && !preg_match('/^[a-z0-9][a-z0-9_-]{0,99}$/', $room_id)) {
      throw new \InvalidArgumentException('room_id_invalid');
    }

    if ($room_id !== NULL && !$from_published_version) {
      $existing = $this->database->select('dungeoncrawler_content_room_editor_drafts', 'd')
        ->fields('d')
        ->condition('room_id', $room_id)
        ->condition('created_by', $uid)
        ->condition('status', 'active')
        ->orderBy('updated_at', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if ($existing) {
        return $this->decodeDraft($existing);
      }
    }

    $base_version_id = NULL;
    $row = NULL;
    if ($room_id !== NULL) {
      $row = $this->database->select('dungeoncrawler_content_rooms', 'r')
        ->fields('r')
        ->condition('room_id', $room_id)
        ->execute()
        ->fetchAssoc();
    }
    if ($room_id === NULL || $row === FALSE || $row === NULL) {
      if ($room_id !== NULL && $name === NULL) {
        throw new \OutOfBoundsException('room_not_found');
      }
      $room = $this->blankRoom();
      if ($room_id !== NULL) {
        $room['room_id'] = $room_id;
      }
      if ($name !== NULL) {
        $room['name'] = trim($name);
      }
    }
    else {
      if ($name !== NULL) {
        throw new \InvalidArgumentException('existing_room_name_invalid');
      }
      $base_version_id = $row['published_version_id'] ?? NULL;
      $published_room = $this->loadPublishedPayload($base_version_id);
      if ($from_published_version && $published_room === NULL) {
        throw new \DomainException('canonical_room_not_published:' . $room_id);
      }
      $room = $published_room ?? $this->normalizeLegacyRoom($row);
    }

    $draft_id = $this->uuid->generate();
    $encoded = $this->encodeRoom($room);
    $now = time();
    $this->database->insert('dungeoncrawler_content_room_editor_drafts')
      ->fields([
        'draft_id' => $draft_id,
        'room_id' => $room_id,
        'base_version_id' => $base_version_id,
        'revision' => 0,
        'status' => 'active',
        'room_payload' => $encoded,
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
   * Loads one draft visible to the current author or an administrator.
   */
  public function getDraft(string $draft_id): array {
    if (!$this->isUuid($draft_id)) {
      throw new \InvalidArgumentException('draft_id_invalid');
    }
    $row = $this->database->select('dungeoncrawler_content_room_editor_drafts', 'd')
      ->fields('d')
      ->condition('draft_id', $draft_id)
      ->execute()
      ->fetchAssoc();
    if (!$row) {
      throw new \OutOfBoundsException('draft_not_found');
    }
    $this->assertDraftAccess($row);
    return $this->decodeDraft($row);
  }

  public function listAccessibleDrafts(): array {
    $all = $this->currentUser->hasPermission('administer dungeoncrawler content')
      || $this->currentUser->hasPermission('publish canonical dungeoncrawler rooms');
    return $this->listDrafts($all ? NULL : (int) $this->currentUser->id());
  }

  /**
   * Preserve an abandoned draft and its command log for audit.
   */
  public function abandonDraft(string $draft_id, int $expected_revision): array {
    $draft = $this->getDraft($draft_id);
    if ($draft['status'] !== 'active') {
      throw new \DomainException('draft_not_active');
    }
    $changed = $this->database->update('dungeoncrawler_content_room_editor_drafts')
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
   * Return bounded command history without exposing inverse snapshots.
   */
  public function commandHistory(string $draft_id, int $limit = 50, ?int $before_revision = NULL): array {
    $draft = $this->getDraft($draft_id);
    if ($limit < 1 || $limit > 100 || ($before_revision !== NULL && $before_revision < 1)) {
      throw new \InvalidArgumentException('history_bounds_invalid');
    }
    $query = $this->database->select('dungeoncrawler_content_room_editor_commands', 'c')
      ->fields('c', ['command_id', 'base_revision', 'result_revision', 'command_type', 'command_payload', 'issued_by', 'issued_at'])
      ->condition('draft_id', $draft_id)
      ->orderBy('result_revision', 'DESC')
      ->range(0, $limit);
    if ($before_revision !== NULL) {
      $query->condition('result_revision', $before_revision, '<');
    }
    $commands = [];
    foreach ($query->execute()->fetchAll(\PDO::FETCH_ASSOC) as $row) {
      $row['command'] = json_decode($row['command_payload'], TRUE, 512, JSON_THROW_ON_ERROR);
      unset($row['command_payload']);
      foreach (['base_revision', 'result_revision', 'issued_by', 'issued_at'] as $key) {
        $row[$key] = (int) $row[$key];
      }
      $commands[] = $row;
    }
    return [
      'draft_id' => $draft_id,
      'revision' => $draft['revision'],
      'commands' => $commands,
      'next_before_revision' => count($commands) === $limit ? end($commands)['result_revision'] : NULL,
    ];
  }

  /**
   * Applies a revision-checked, idempotent authoring command.
   */
  public function applyCommand(string $draft_id, array $command): array {
    $command_id = (string) ($command['command_id'] ?? '');
    if (!$this->isUuid($command_id)) {
      throw new \InvalidArgumentException('command_id_invalid');
    }
    if (!isset($command['expected_revision']) || !is_int($command['expected_revision'])) {
      throw new \InvalidArgumentException('expected_revision_required');
    }
    $type = (string) ($command['type'] ?? '');
    $payload = $command['payload'] ?? NULL;
    if (!is_array($payload)) {
      throw new \InvalidArgumentException('command_payload_invalid');
    }

    $prior = $this->database->select('dungeoncrawler_content_room_editor_commands', 'c')
      ->fields('c', ['draft_id', 'result_revision', 'result_hash'])
      ->condition('command_id', $command_id)
      ->execute()
      ->fetchAssoc();
    if ($prior) {
      if ($prior['draft_id'] !== $draft_id) {
        throw new \RuntimeException('idempotency_conflict');
      }
      $draft = $this->getDraft($draft_id);
      if ((int) $draft['revision'] !== (int) $prior['result_revision']
        || $draft['payload_hash'] !== $prior['result_hash']) {
        throw new \RuntimeException('idempotency_conflict');
      }
      return ['command_id' => $command_id, 'draft' => $draft, 'idempotent' => TRUE];
    }

    $transaction = $this->database->startTransaction();
    try {
      $row = $this->database->select('dungeoncrawler_content_room_editor_drafts', 'd')
        ->fields('d')
        ->condition('draft_id', $draft_id)
        ->execute()
        ->fetchAssoc();
      if (!$row) {
        throw new \OutOfBoundsException('draft_not_found');
      }
      $this->assertDraftAccess($row);
      if ($row['status'] !== 'active') {
        throw new \DomainException('draft_not_active');
      }
      if ((int) $row['revision'] !== $command['expected_revision']) {
        throw new \RuntimeException('revision_conflict');
      }

      $before = json_decode($row['room_payload'], TRUE, 512, JSON_THROW_ON_ERROR);
      $after = $this->mutate($before, $type, $payload, $draft_id);
      $this->assertNoIntroducedValidationErrors($before, $after);
      $encoded = $this->encodeRoom($after);
      $hash = hash('sha256', $encoded);
      $revision = ((int) $row['revision']) + 1;

      $this->database->insert('dungeoncrawler_content_room_editor_commands')
        ->fields([
          'command_id' => $command_id,
          'draft_id' => $draft_id,
          'base_revision' => (int) $row['revision'],
          'result_revision' => $revision,
          'command_type' => $type,
          'command_payload' => $this->encode($command),
          'inverse_payload' => $this->encode(['room' => $this->canonicalizeRoomJsonObjects($before)]),
          'issued_by' => (int) $this->currentUser->id(),
          'issued_at' => time(),
          'result_hash' => $hash,
        ])
        ->execute();
      $affected = $this->database->update('dungeoncrawler_content_room_editor_drafts')
        ->fields([
          'room_id' => $after['room_id'] ?: NULL,
          'revision' => $revision,
          'room_payload' => $encoded,
          'payload_hash' => $hash,
          'updated_by' => (int) $this->currentUser->id(),
          'updated_at' => time(),
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

    return [
      'command_id' => $command_id,
      'draft' => $this->getDraft($draft_id),
      'idempotent' => FALSE,
    ];
  }

  /**
   * Validates a draft for editing or publication.
   */
  public function validateDraft(string $draft_id, string $profile = 'editing'): array {
    $draft = $this->getDraft($draft_id);
    return [
      'schema_version' => 'room-editor-validation-result-v1',
      'profile' => $profile,
      'draft_id' => $draft_id,
      'revision' => $draft['revision'],
    ] + $this->validateAggregate($draft['room'], $profile) + [
      'validated_at' => gmdate(DATE_RFC3339),
    ];
  }

  /**
   * Builds one status projection shared by the GM and the Room Editor UI.
   *
   * Every value comes from the saved draft and the given validation result.
   */
  public function summarizeDraftState(array $draft, array $validation): array {
    $review = $this->warningReview?->summary('room', $draft, $validation) ?? [];
    $room = $draft['room'];
    // Room drafts may predate the aggregate contract. A field validation
    // reports as missing or malformed holds nothing to count.
    $list = static fn(string $key): array => is_array($room[$key] ?? NULL) ? $room[$key] : [];
    $text = static fn(string $key): string => is_string($room[$key] ?? NULL) ? trim($room[$key]) : '';
    $default_entries = count(array_filter($list('entry_ports'), static fn($port): bool => is_array($port) && !empty($port['is_default'])));
    $counts = [
      'hexes' => count($list('hexes')),
      'entry_ports' => count($list('entry_ports')),
      'default_entry_ports' => $default_entries,
      'exit_ports' => count($list('exit_ports')),
      'placements' => count($list('placements')),
    ];
    $is = [
      'name' => $text('name') !== '' && $text('name') !== self::BLANK_NAME,
      'description' => $text('description') !== '' && $text('description') !== self::BLANK_DESCRIPTION,
      'hexes' => $counts['hexes'] > 0,
      'entry' => $counts['entry_ports'] > 0,
      'default_entry' => $default_entries === 1,
      'contents' => $counts['placements'] > 0,
      'valid' => $validation['is_valid'],
    ];
    // Whatever is placed must exist in the library. Missing furnishing is a
    // recommendation phase, not an automatic room-content write.
    $contents_resolve = array_filter(
      $validation['findings'],
      static fn(array $finding): bool => $finding['severity'] === 'error' && $finding['route'] === 'definition_editor',
    ) === [];
    $item = static fn(string $id, string $label, bool $complete, string $detail = '', string $group = 'minimum_content'): array
      => compact('id', 'label', 'complete', 'group', 'detail');
    $checklist = [
      $item('identity_name', 'Room name', $is['name']),
      $item('identity_description', 'Room description', $is['description']),
      $item('layout_hexes', 'At least one floor hex', $is['hexes'], (string) $counts['hexes']),
      $item('ports_entry', 'At least one entrance', $is['entry'], (string) $counts['entry_ports']),
      $item('ports_default_entry', 'Exactly one default entrance', $is['default_entry'], (string) $default_entries),
      // An empty room is a finished room: a road, a clearing, a corridor.
      $item('contents_placed', 'At least one placed object', $is['contents'], (string) $counts['placements'], 'optional'),
      $item('canonical_validation', 'Validation passes', $is['valid'], sprintf('%d errors, %d warnings', $validation['counts']['error'], $validation['counts']['warning']), 'validation'),
    ];
    $result = EditorDraftStatusProjection::project('room-draft-status-v1', $draft, $validation, $checklist, [
      1 => [
        'label' => 'Identity',
        'checks' => [$is['name'], $is['description']],
        'metrics' => ['room_type' => $text('room_type'), 'size_category' => $text('size_category')],
      ],
      2 => [
        'label' => 'Layout',
        'checks' => [$is['hexes']],
        'metrics' => ['hexes' => $counts['hexes']],
      ],
      3 => [
        'label' => 'Ports',
        'checks' => [$is['entry'], $is['default_entry']],
        'metrics' => ['entry_ports' => $counts['entry_ports'], 'default_entry_ports' => $default_entries, 'exit_ports' => $counts['exit_ports']],
      ],
      4 => [
        'label' => 'Contents',
        'checks' => [$contents_resolve],
        'metrics' => ['placements' => $counts['placements']],
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
    $room_id = $text('room_id');
    $result['storylines'] = $this->storylines === NULL || $room_id === '' ? [] : array_map(
      static fn(array $story): array => array_intersect_key($story, array_flip(['draft_id', 'title', 'status'])),
      $this->storylines->readStoryBriefs('room', $room_id)['storylines'],
    );
    // What the editor page names and links to.
    $result['name'] = $text('name');
    $result['dungeons'] = [];
    if ($this->dungeons !== NULL && $room_id !== '') {
      $usage = $this->dungeons->roomUsage($room_id);
      foreach (array_merge($usage['published_dungeons'], $usage['active_dungeon_drafts']) as $dungeon) {
        $result['dungeons'][(string) $dungeon['dungeon_id']] = ['dungeon_id' => (string) $dungeon['dungeon_id'], 'name' => (string) $dungeon['name']];
      }
      $result['dungeons'] = array_values($result['dungeons']);
    }
    // Each definition placed in the room, once, with how many times it appears.
    $contents = [];
    foreach ($list('placements') as $placement) {
      $family = is_array($placement) ? ($placement['definition_ref']['family'] ?? NULL) : NULL;
      $definition_id = is_array($placement) ? ($placement['definition_ref']['definition_id'] ?? NULL) : NULL;
      if (!is_string($family) || !is_string($definition_id) || !in_array($family, CanonicalDefinitionService::FAMILIES, TRUE)) {
        continue;
      }
      $key = $family . ':' . $definition_id;
      if (!isset($contents[$key])) {
        $entry = $this->definitions->catalogEntry($family, $definition_id);
        $contents[$key] = [
          'family' => $family,
          'definition_id' => $definition_id,
          // An id the library does not hold is listed by id; validation reports it.
          'name' => (string) ($entry['label'] ?? $definition_id),
          'category' => (string) ($entry['category'] ?? ''),
          'description' => (string) ($entry['description'] ?? ''),
          'count' => 0,
        ];
      }
      $contents[$key]['count']++;
    }
    usort($contents, static fn(array $a, array $b): int => [$a['family'], $a['name']] <=> [$b['family'], $b['name']]);
    $result['contents'] = $contents;
    $result['furnishing_recommendations'] = $this->furnishingRecommendations($room);
    $result['next_steps'] = self::recommendNextAuthoringAction($result);
    return $result;
  }

  /**
   * Owns the single next author action for the Room Editor.
   *
   * @return string[]
   */
  public static function recommendNextAuthoringAction(array $state): array {
    if ($state['status'] === 'published') {
      return ['Open the published room.'];
    }
    if ($state['missing_content'] !== []) {
      $label = $state['missing_content'][0];
      return [match ($label) {
        'Room name' => 'Choose a name for this room.',
        'Room description' => 'Describe this room in one short paragraph.',
        'At least one floor hex' => 'Have the GM lay out the room floor.',
        'At least one entrance' => 'Have the GM add an entrance.',
        'Exactly one default entrance' => 'Choose which entrance is the default.',
        default => throw new \LogicException('room_status_label_unmapped:' . $label),
      }];
    }
    if ($state['error_count'] > 0) {
      return ['Have the GM address the next validation finding.'];
    }
    if (($state['open_warning_count'] ?? $state['warning_count']) > 0) {
      return ['Have the GM address the next validation warning.'];
    }
    if ($state['furnishing_recommendations'] !== []) {
      $labels = array_column(array_slice($state['furnishing_recommendations'], 0, 3), 'label');
      return [sprintf(
        'Before publication, have the GM review the room-fleshing recommendations and place or dismiss: %s.',
        implode(', ', array_filter($labels)),
      )];
    }
    return ['Review the room and check its publication readiness.'];
  }

  public function furnishingRecommendations(array $room): array {
    if ($this->definitions === NULL) {
      return [];
    }
    $placements = array_values(array_filter((array) ($room['placements'] ?? []), 'is_array'));
    $placed = [];
    foreach ($placements as $placement) {
      $family = (string) ($placement['definition_ref']['family'] ?? '');
      $id = (string) ($placement['definition_ref']['definition_id'] ?? '');
      if ($family !== '' && $id !== '') {
        $placed[$family . ':' . $id] = TRUE;
      }
    }
    $text = mb_strtolower(trim(
      (string) ($room['name'] ?? '')
      . ' '
      . (string) ($room['description'] ?? '')
      . ' '
      . (string) ($room['room_type'] ?? ''),
    ));
    $tokens = array_values(array_unique(array_filter(
      preg_split('/[^a-z0-9]+/u', $text) ?: [],
      static fn(string $token): bool => strlen($token) >= 4
        && !in_array($token, [
          'room', 'this', 'that', 'with', 'from', 'their', 'there', 'where', 'into',
          'chamber', 'hall', 'floor', 'entrance', 'exit', 'large', 'small', 'medium',
          'bright', 'light', 'stone', 'space', 'inside', 'standing', 'through',
          'rows', 'wait', 'like', 'ordered', 'future', 'hangs', 'pale',
        ], TRUE),
    )));
    $semantic_groups = [
      'wheelwright' => ['wheel', 'wagon', 'cart', 'axle', 'rim', 'timber', 'tool', 'workbench'],
      'cartwright' => ['wheel', 'wagon', 'cart', 'axle', 'rim', 'timber', 'tool', 'workbench'],
      'smith' => ['forge', 'anvil', 'hammer', 'metal', 'tool', 'iron'],
      'tavern' => ['table', 'stool', 'bar', 'bottle', 'barrel', 'tankard', 'hearth'],
      'kitchen' => ['table', 'stove', 'barrel', 'pot', 'pan', 'shelf', 'crate'],
      'library' => ['book', 'shelf', 'desk', 'scroll', 'lectern'],
      'workshop' => ['bench', 'tool', 'rack', 'work', 'timber', 'parts'],
    ];
    foreach ($semantic_groups as $trigger => $related) {
      if (str_contains($text, $trigger)) {
        $tokens = array_values(array_unique(array_merge($tokens, $related)));
      }
    }
    $catalog = [];
    foreach (CanonicalDefinitionService::FAMILIES as $family) {
      $catalog = array_merge(
        $catalog,
        (array) ($this->definitions->catalog($family, '', 250, 0)['definitions'] ?? []),
      );
    }
    $families = CanonicalDefinitionService::FAMILIES;
    $selected = [];
    foreach ($families as $family) {
      $candidates = [];
      foreach ($catalog as $entry) {
        if ((string) ($entry['family'] ?? '') !== $family) {
          continue;
        }
        $key = $family . ':' . (string) ($entry['definition_id'] ?? '');
        if (isset($placed[$key]) || trim((string) ($entry['definition_id'] ?? '')) === '') {
          continue;
        }
        $haystack = mb_strtolower(implode(' ', array_filter([
          (string) ($entry['label'] ?? ''),
          (string) ($entry['category'] ?? ''),
          (string) ($entry['description'] ?? ''),
          (string) ($entry['definition_id'] ?? ''),
        ])));
        $haystack_tokens = array_values(array_filter(
          preg_split('/[^a-z0-9]+/u', $haystack) ?: [],
          static fn(string $token): bool => strlen($token) >= 4,
        ));
        $score = count(array_filter(
          $tokens,
          static function (string $token) use ($haystack_tokens): bool {
            $stem = rtrim($token, 's');
            foreach ($haystack_tokens as $candidate) {
              if ($candidate === $token || rtrim($candidate, 's') === $stem) {
                return TRUE;
              }
            }
            return FALSE;
          },
        ));
        $minimum_score = $family === 'actor' ? 3 : 1;
        if ($score >= $minimum_score) {
          if ($family === 'actor'
            && preg_match('/\b(guard|worker|merchant|craftsperson|smith|keeper|owner|patron)\b/u', $text) !== 1) {
            continue;
          }
          $candidates[] = $entry + ['recommendation_score' => $score];
        }
      }
      usort($candidates, static fn(array $a, array $b): int =>
        [$b['recommendation_score'], $a['label']] <=> [$a['recommendation_score'], $b['label']]);
      if ($candidates !== []) {
        $selected[] = $candidates[0];
      }
    }
    if ($selected === []) {
      return [];
    }
    $anchors = $this->recommendedPlacementHexes($room, count($selected));
    return array_values(array_map(
      static fn(array $entry, int $index): array => [
        'family' => (string) $entry['family'],
        'definition_id' => (string) $entry['definition_id'],
        'version' => (string) ($entry['version'] ?? ''),
        'label' => (string) ($entry['label'] ?? $entry['definition_id']),
        'category' => (string) ($entry['category'] ?? ''),
        'why' => sprintf(
          'Matches the room name, description, or type: %s.',
          (string) ($entry['description'] ?: $entry['category'] ?: $entry['family']),
        ),
        'anchor_hex' => $anchors[$index] ?? NULL,
      ],
      $selected,
      array_keys($selected),
    ));
  }

  private function recommendedPlacementHexes(array $room, int $count): array {
    $hexes = array_values(array_filter((array) ($room['hexes'] ?? []), 'is_array'));
    if ($hexes === [] || $count <= 0) {
      return [];
    }
    $occupied = [];
    foreach ((array) ($room['placements'] ?? []) as $placement) {
      if (!is_array($placement)) {
        continue;
      }
      $hex = $placement['anchor_hex'] ?? NULL;
      if (is_array($hex)) {
        $occupied[(int) ($hex['q'] ?? 0) . ':' . (int) ($hex['r'] ?? 0)] = TRUE;
      }
    }
    $available = array_values(array_filter(
      $hexes,
      static fn(array $hex): bool => !isset($occupied[(int) ($hex['q'] ?? 0) . ':' . (int) ($hex['r'] ?? 0)]),
    ));
    if ($available === []) {
      $available = $hexes;
    }
    $center_q = array_sum(array_column($available, 'q')) / count($available);
    $center_r = array_sum(array_column($available, 'r')) / count($available);
    usort($available, static fn(array $a, array $b): int =>
      (abs((int) $a['q'] - $center_q) + abs((int) $a['r'] - $center_r))
      <=> (abs((int) $b['q'] - $center_q) + abs((int) $b['r'] - $center_r)));
    $step = max(1, intdiv(count($available), $count));
    $anchors = [];
    for ($index = 0; $index < $count; $index++) {
      $hex = $available[min(($index * $step), count($available) - 1)];
      $anchors[] = ['q' => (int) $hex['q'], 'r' => (int) $hex['r']];
    }
    return $anchors;
  }

  /**
   * Publishes an immutable version and updates canonical materialized columns.
   */
  public function publish(string $draft_id, array $request): array {
    if (!isset($request['expected_revision']) || !is_int($request['expected_revision'])) {
      throw new \InvalidArgumentException('expected_revision_required');
    }
    $version = (string) ($request['version'] ?? '');
    if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
      throw new \InvalidArgumentException('version_invalid');
    }
    $draft = $this->getDraft($draft_id);
    if ((int) $draft['revision'] !== $request['expected_revision']) {
      throw new \RuntimeException('revision_conflict');
    }
    $expected_base = $request['expected_base_version_id'] ?? NULL;
    if ($expected_base !== $draft['base_version_id']) {
      throw new \RuntimeException('base_version_conflict');
    }
    $room = $draft['room'];
    // Gaps are working notes of the draft; a published room carries none.
    unset($room['metadata']['placement_gaps']);
    $this->assertStarterFixedDataContract($room);
    $room_id = (string) $room['room_id'];
    if ($room_id === '') {
      throw new \DomainException('room_id_required');
    }
    $encoded = $this->encodeRoom($room);
    $hash = hash('sha256', $encoded);
    $replay = $this->completeExistingPublicationReplay(
      'dungeoncrawler_content_room_versions',
      'dungeoncrawler_content_rooms',
      'room_id',
      $room_id,
      $hash,
      'dungeoncrawler_content_room_editor_drafts',
      $draft_id,
      (int) $draft['revision'],
    );
    if ($replay !== NULL) {
      return [
        'room_id' => $room_id,
        'payload_hash' => $hash,
        ...$replay,
      ];
    }
    if ($draft['status'] !== 'active') {
      throw new \DomainException('draft_not_active');
    }
    $validation = $this->validateDraft($draft_id, 'publication');
    if (!$validation['is_valid']) {
      throw new \DomainException('publication_validation_failed');
    }
    $version_id = $this->uuid->generate();
    $transaction = $this->database->startTransaction();
    try {
      $current = $this->database->select('dungeoncrawler_content_rooms', 'r')
        ->fields('r', ['published_version_id'])
        ->condition('room_id', $room_id)
        ->execute()
        ->fetchField();
      if (($current ?: NULL) !== $draft['base_version_id']) {
        throw new \RuntimeException('base_version_conflict');
      }
      $latest_version = $this->database->select('dungeoncrawler_content_room_versions', 'v')
        ->fields('v', ['version'])
        ->condition('room_id', $room_id)
        ->orderBy('published_at', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchField();
      if ($latest_version && version_compare($version, (string) $latest_version, '<=')) {
        throw new \DomainException('version_must_increase');
      }
      $duplicate = $this->database->select('dungeoncrawler_content_room_versions', 'v')
        ->fields('v', ['version_id'])
        ->condition('room_id', $room_id)
        ->condition('version', $version)
        ->execute()
        ->fetchField();
      if ($duplicate) {
        throw new \DomainException('version_already_exists');
      }

      $this->database->insert('dungeoncrawler_content_room_versions')
        ->fields([
          'version_id' => $version_id,
          'room_id' => $room_id,
          'version' => $version,
          'schema_version' => 'canonical-room-v1',
          'room_payload' => $encoded,
          'payload_hash' => $hash,
          'catalog_version' => $this->definitions->catalogVersion(),
          'publication_note' => trim((string) ($request['publication_note'] ?? '')),
          'source' => 'room_editor',
          'published_by' => (int) $this->currentUser->id(),
          'published_at' => time(),
        ])
        ->execute();

      [$layout, $contents] = $this->legacyProjection($room);
      $canonical_fields = [
        'name' => $room['name'],
        'description' => $room['description'],
        'environment_tags' => $this->encode($room['metadata']['tags'] ?? []),
        'layout_data' => $this->encode($layout),
        'contents_data' => $this->encode($contents),
        'published_version_id' => $version_id,
        'publication_status' => 'published',
        'updated_by' => (int) $this->currentUser->id(),
        'updated' => time(),
      ];
      $this->database->merge('dungeoncrawler_content_rooms')
        ->key('room_id', $room_id)
        ->fields($canonical_fields)
        ->insertFields($canonical_fields + [
          'room_id' => $room_id,
          'source_room_id' => NULL,
          'created_by' => (int) $this->currentUser->id(),
          'created' => time(),
        ])
        ->execute();
      $this->database->update('dungeoncrawler_content_room_editor_drafts')
        ->fields([
          'status' => 'published',
          'published_version_id' => $version_id,
          'updated_at' => time(),
        ])
        ->condition('draft_id', $draft_id)
        ->execute();
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }

    return [
      'room_id' => $room_id,
      'version_id' => $version_id,
      'version' => $version,
      'payload_hash' => $hash,
      'published_at' => gmdate(DATE_RFC3339),
      'published_by' => (int) $this->currentUser->id(),
    ];
  }


  /**
   * Mutates the aggregate for one command.
   */
  private function mutate(array $room, string $type, array $payload, string $draft_id): array {
    switch ($type) {
      case 'set_room_metadata':
        $changes = $payload['changes'] ?? [];
        if (!is_array($changes)) {
          throw new \InvalidArgumentException('metadata_changes_invalid');
        }
        foreach (['room_id', 'name', 'description', 'room_type', 'size_category'] as $field) {
          if (array_key_exists($field, $changes)) {
            $room[$field] = $changes[$field];
          }
        }
        if (array_key_exists('metadata', $changes)) {
          if (!is_array($changes['metadata']) || array_is_list($changes['metadata'])) {
            throw new \InvalidArgumentException('metadata_invalid');
          }
          $room['metadata'] = array_replace_recursive(
            is_array($room['metadata'] ?? NULL) ? $room['metadata'] : [],
            $changes['metadata']
          );
        }
        if (array_key_exists('room_id', $changes)) {
          foreach ($room['placements'] as &$placement) {
            $placement['room_id'] = (string) $room['room_id'];
          }
          unset($placement);
        }
        break;

      case 'add_hex':
        $hex = $this->normalizeHex($payload['hex'] ?? []);
        if ($this->findHexIndex($room, $hex['q'], $hex['r']) !== NULL) {
          throw new \DomainException('hex_already_exists');
        }
        $room['hexes'][] = $hex;
        break;

      case 'set_street_footprint':
        $hexes = $payload['hexes'] ?? NULL;
        $entry = $payload['entry_hex'] ?? NULL;
        if ($room['room_type'] !== 'street' || !is_array($hexes) || !array_is_list($hexes)
          || $hexes === [] || count($hexes) > self::MAX_STREET_HEXES
          || !is_array($entry) || !is_int($entry['q'] ?? NULL) || !is_int($entry['r'] ?? NULL)
          || $room['placements'] !== [] || $room['exit_ports'] !== []
          || !in_array(array_column($room['entry_ports'], 'port_id'), [['entry-1'], ['street-entry']], TRUE)) {
          throw new \InvalidArgumentException('street_footprint_requires_unplaced_street_with_seed_entry');
        }
        $room['hexes'] = array_map(fn(mixed $hex): array => $this->normalizeHex($hex), $hexes);
        $edges = RoomPortEdgePolicy::openEdges($room, $entry);
        if ($this->findHexIndex($room, $entry['q'], $entry['r']) === NULL || $edges === []) {
          throw new \DomainException('street_footprint_entry_not_on_boundary');
        }
        $edge = RoomPortEdgePolicy::farthestOpenEdgeFromCentroid($room, $entry, $edges);
        $room['entry_ports'] = [[
          'port_id' => 'street-entry',
          'hex' => ['q' => $entry['q'], 'r' => $entry['r']],
          'edge' => $edge,
          'label' => 'Street entrance',
          'arrival_facing' => $edge,
          'is_default' => TRUE,
          'tags' => [],
        ]];
        break;

      case 'remove_hex':
        $q = (int) ($payload['hex']['q'] ?? PHP_INT_MAX);
        $r = (int) ($payload['hex']['r'] ?? PHP_INT_MAX);
        $index = $this->findHexIndex($room, $q, $r);
        if ($index === NULL) {
          throw new \DomainException('hex_not_found');
        }
        foreach ($room['placements'] as $placement) {
          if (($placement['anchor_hex']['q'] ?? NULL) === $q && ($placement['anchor_hex']['r'] ?? NULL) === $r) {
            throw new \DomainException('hex_has_placements');
          }
        }
        foreach (['entry_ports', 'exit_ports'] as $bucket) {
          foreach ($room[$bucket] as $port) {
            if ((int) ($port['hex']['q'] ?? PHP_INT_MAX) === $q
              && (int) ($port['hex']['r'] ?? PHP_INT_MAX) === $r) {
              throw new \DomainException('hex_has_ports');
            }
          }
        }
        array_splice($room['hexes'], $index, 1);
        break;

      case 'set_hexes_terrain':
        $hexes = $payload['hexes'] ?? NULL;
        if (!is_array($hexes) || $hexes === [] || !array_is_list($hexes) || count($hexes) > 5000) {
          throw new \InvalidArgumentException('hexes_list_invalid');
        }
        $terrain = (string) ($payload['terrain_type'] ?? '');
        if (!in_array($terrain, self::TERRAIN_TYPES, TRUE)) {
          throw new \InvalidArgumentException('terrain_type_invalid');
        }
        foreach ($hexes as $hex) {
          $index = $this->findHexIndex($room, (int) ($hex['q'] ?? PHP_INT_MAX), (int) ($hex['r'] ?? PHP_INT_MAX));
          if ($index === NULL) {
            throw new \DomainException('hex_not_found');
          }
          $room['hexes'][$index]['terrain_type'] = $terrain;
        }
        break;

      case 'set_hex_terrain':
      case 'set_hex_elevation':
        $q = (int) ($payload['hex']['q'] ?? PHP_INT_MAX);
        $r = (int) ($payload['hex']['r'] ?? PHP_INT_MAX);
        $index = $this->findHexIndex($room, $q, $r);
        if ($index === NULL) {
          throw new \DomainException('hex_not_found');
        }
        $room['hexes'][$index] = $this->mergeHexFixedData($room['hexes'][$index], $payload['hex']);
        if ($type === 'set_hex_terrain') {
          $room['hexes'][$index]['terrain_type'] = (string) ($payload['terrain_type'] ?? '');
        }
        else {
          if (!isset($payload['elevation_ft'])
            || !is_int($payload['elevation_ft'])
            || $payload['elevation_ft'] < -50
            || $payload['elevation_ft'] > 200) {
            throw new \InvalidArgumentException('hex_elevation_invalid');
          }
          $room['hexes'][$index]['elevation_ft'] = $payload['elevation_ft'];
        }
        break;

      case 'place_object':
        $definition = $payload['definition_ref'] ?? [];
        if (!is_array($definition) || !in_array($definition['family'] ?? NULL, CanonicalDefinitionService::FAMILIES, TRUE)) {
          throw new \InvalidArgumentException('definition_ref_invalid');
        }
        $this->definitions->assertDefinitionSelectable((string) $definition['family'], (string) ($definition['definition_id'] ?? ''));
        if (!$this->definitions->definitionExists(
          (string) $definition['family'],
          (string) ($definition['definition_id'] ?? ''),
          (string) ($definition['version'] ?? '1.0.0')
        )) {
          throw new \DomainException('definition_not_found');
        }
        $anchor = $payload['anchor_hex'] ?? [];
        $q = (int) ($anchor['q'] ?? PHP_INT_MAX);
        $r = (int) ($anchor['r'] ?? PHP_INT_MAX);
        if ($this->findHexIndex($room, $q, $r) === NULL) {
          throw new \DomainException('placement_outside_room');
        }
        $family = (string) $definition['family'];
        $this->assertNoSolidPlacementCollision($room, $family, $q, $r);
        $instance_id = (string) ($payload['instance_id'] ?? $this->uuid->generate());
        if (!$this->isUuid($instance_id)) {
          throw new \InvalidArgumentException('instance_id_invalid');
        }
        if ($this->findPlacementIndex($room, $instance_id) !== NULL) {
          throw new \DomainException('instance_id_already_exists');
        }
        $room['placements'][] = [
          'schema_version' => 'room-placement-v1',
          'instance_id' => $instance_id,
          'definition_ref' => [
            'family' => $family,
            'definition_id' => (string) ($definition['definition_id'] ?? ''),
            'version' => (string) ($definition['version'] ?? '1.0.0'),
          ],
          'room_id' => (string) $room['room_id'],
          'anchor_hex' => ['q' => $q, 'r' => $r],
          'footprint' => [['q' => 0, 'r' => 0]],
          'facing' => max(0, min(5, (int) ($payload['facing'] ?? 0))),
          'elevation_ft' => max(-50, min(200, (int) ($payload['elevation_ft'] ?? 0))),
          'state_defaults' => [],
          'overrides' => is_array($payload['overrides'] ?? NULL) ? $payload['overrides'] : [],
        ];
        break;

      case 'move_object':
      case 'rotate_object':
      case 'update_object_overrides':
        $index = $this->findPlacementIndex($room, (string) ($payload['instance_id'] ?? ''));
        if ($index === NULL) {
          throw new \DomainException('placement_not_found');
        }
        if ($type === 'move_object') {
          $anchor = $payload['anchor_hex'] ?? [];
          $q = (int) ($anchor['q'] ?? PHP_INT_MAX);
          $r = (int) ($anchor['r'] ?? PHP_INT_MAX);
          if ($this->findHexIndex($room, $q, $r) === NULL) {
            throw new \DomainException('placement_outside_room');
          }
          $this->assertNoSolidPlacementCollision(
            $room,
            (string) ($room['placements'][$index]['definition_ref']['family'] ?? ''),
            $q,
            $r,
            (string) ($room['placements'][$index]['instance_id'] ?? '')
          );
          $room['placements'][$index]['anchor_hex'] = ['q' => $q, 'r' => $r];
        }
        elseif ($type === 'rotate_object') {
          $room['placements'][$index]['facing'] = max(0, min(5, (int) ($payload['facing'] ?? 0)));
        }
        else {
          if (!is_array($payload['overrides'] ?? NULL)) {
            throw new \InvalidArgumentException('overrides_invalid');
          }
          $room['placements'][$index]['overrides'] = $payload['overrides'];
        }
        break;

      case 'duplicate_object':
        $index = $this->findPlacementIndex($room, (string) ($payload['instance_id'] ?? ''));
        if ($index === NULL) {
          throw new \DomainException('placement_not_found');
        }
        $copy = $room['placements'][$index];
        $this->definitions->assertDefinitionSelectable($copy['definition_ref']['family'], $copy['definition_ref']['definition_id']);
        $new_instance_id = (string) ($payload['new_instance_id'] ?? $this->uuid->generate());
        if (!$this->isUuid($new_instance_id)) {
          throw new \InvalidArgumentException('instance_id_invalid');
        }
        if ($this->findPlacementIndex($room, $new_instance_id) !== NULL) {
          throw new \DomainException('instance_id_already_exists');
        }
        $copy['instance_id'] = $new_instance_id;
        $copy['anchor_hex'] = [
          'q' => (int) ($payload['anchor_hex']['q'] ?? $copy['anchor_hex']['q']),
          'r' => (int) ($payload['anchor_hex']['r'] ?? $copy['anchor_hex']['r']),
        ];
        if ($this->findHexIndex($room, $copy['anchor_hex']['q'], $copy['anchor_hex']['r']) === NULL) {
          throw new \DomainException('placement_outside_room');
        }
        $this->assertNoSolidPlacementCollision(
          $room,
          (string) ($copy['definition_ref']['family'] ?? ''),
          (int) $copy['anchor_hex']['q'],
          (int) $copy['anchor_hex']['r']
        );
        $room['placements'][] = $copy;
        break;

      case 'remove_object':
        $index = $this->findPlacementIndex($room, (string) ($payload['instance_id'] ?? ''));
        if ($index === NULL) {
          throw new \DomainException('placement_not_found');
        }
        array_splice($room['placements'], $index, 1);
        break;

      case 'place_objects_bulk':
        $this->placeObjectsBulk($room, $payload);
        break;

      case 'move_objects_bulk':
        $this->moveObjectsBulk($room, $payload);
        break;

      case 'remove_objects_bulk':
        $ids = $this->bulkIds($payload['instance_ids'] ?? NULL, 'instance_ids_invalid');
        foreach ($ids as $instance_id) {
          if ($this->findPlacementIndex($room, $instance_id) === NULL) {
            throw new \DomainException('placement_not_found');
          }
        }
        $room['placements'] = array_values(array_filter(
          $room['placements'],
          static fn(array $placement): bool => !in_array($placement['instance_id'] ?? NULL, $ids, TRUE)
        ));
        break;

      case 'replace_objects_bulk':
        $ids = $this->bulkIds($payload['instance_ids'] ?? NULL, 'instance_ids_invalid');
        $definition = $this->bulkDefinition($payload['definition_ref'] ?? NULL);
        foreach ($ids as $instance_id) {
          $index = $this->findPlacementIndex($room, $instance_id);
          if ($index === NULL) {
            throw new \DomainException('placement_not_found');
          }
          if (!in_array($room['placements'][$index]['definition_ref']['family'] ?? NULL, self::SHAPE_FAMILIES, TRUE)) {
            throw new \DomainException('bulk_family_invalid');
          }
          $room['placements'][$index]['definition_ref'] = $definition;
        }
        break;

      case 'dismiss_placement_gaps':
        $ids = $this->bulkIds($payload['gap_ids'] ?? NULL, 'gap_ids_invalid');
        $reason = trim((string) ($payload['reason'] ?? ''));
        if ($reason === '') {
          throw new \InvalidArgumentException('gap_dismiss_reason_required');
        }
        $known = array_column((array) ($room['metadata']['placement_gaps'] ?? []), 'gap_id');
        foreach ($ids as $gap_id) {
          if (!in_array($gap_id, $known, TRUE)) {
            throw new \DomainException('placement_gap_not_found');
          }
        }
        foreach ($room['metadata']['placement_gaps'] as &$gap) {
          if (in_array($gap['gap_id'], $ids, TRUE)) {
            $gap['status'] = 'dismissed';
            $gap['dismissed_reason'] = $reason;
          }
        }
        unset($gap);
        break;

      case 'add_entry_port':
      case 'add_exit_port':
        $bucket = $type === 'add_entry_port' ? 'entry_ports' : 'exit_ports';
        $port = $payload['port'] ?? NULL;
        if (!is_array($port)) {
          throw new \InvalidArgumentException('port_invalid');
        }
        $port = $this->normalizePort($port, $type === 'add_entry_port');
        foreach ($room[$bucket] as $existing) {
          if (($existing['port_id'] ?? NULL) === $port['port_id']) {
            throw new \DomainException('port_already_exists');
          }
        }
        if ($this->findHexIndex($room, (int) ($port['hex']['q'] ?? PHP_INT_MAX), (int) ($port['hex']['r'] ?? PHP_INT_MAX)) === NULL) {
          throw new \DomainException('port_outside_room');
        }
        $room[$bucket][] = $port;
        break;

      case 'update_entry_port':
      case 'update_exit_port':
      case 'remove_entry_port':
      case 'remove_exit_port':
        $is_entry = str_contains($type, 'entry');
        $bucket = $is_entry ? 'entry_ports' : 'exit_ports';
        $port_id = (string) ($payload['port_id'] ?? '');
        $port_index = NULL;
        foreach ($room[$bucket] as $index => $port) {
          if (($port['port_id'] ?? NULL) === $port_id) {
            $port_index = $index;
            break;
          }
        }
        if ($port_index === NULL) {
          throw new \DomainException('port_not_found');
        }
        if (str_starts_with($type, 'remove_')) {
          if ($is_entry && !empty($room[$bucket][$port_index]['is_default'])) {
            throw new \DomainException('default_entry_cannot_be_removed');
          }
          array_splice($room[$bucket], $port_index, 1);
        }
        else {
          $changes = $payload['changes'] ?? NULL;
          if (!is_array($changes)) {
            throw new \InvalidArgumentException('port_changes_invalid');
          }
          $allowed = $is_entry
            ? ['hex', 'edge', 'label', 'arrival_facing', 'is_default', 'tags']
            : ['hex', 'edge', 'label', 'kind', 'direction', 'default_state', 'destination_hint', 'linked_placement_id', 'requirements', 'tags'];
          foreach ($allowed as $field) {
            if (array_key_exists($field, $changes)) {
              $room[$bucket][$port_index][$field] = $changes[$field];
            }
          }
          $room[$bucket][$port_index] = $this->normalizePort($room[$bucket][$port_index], $is_entry);
          $port_hex = $room[$bucket][$port_index]['hex'];
          if ($this->findHexIndex($room, $port_hex['q'], $port_hex['r']) === NULL) {
            throw new \DomainException('port_outside_room');
          }
          if ($is_entry && !empty($changes['is_default'])) {
            foreach ($room[$bucket] as $index => &$entry) {
              $entry['is_default'] = $index === $port_index;
            }
            unset($entry);
          }
        }
        break;

      case 'undo':
      case 'redo':
        $target = (string) ($payload['target_command_id'] ?? '');
        $snapshot = $this->database->select('dungeoncrawler_content_room_editor_commands', 'c')
          ->fields('c', ['inverse_payload'])
          ->condition('command_id', $target)
          ->condition('draft_id', $draft_id)
          ->execute()
          ->fetchField();
        if (!$snapshot) {
          throw new \DomainException('history_target_not_found');
        }
        $decoded = json_decode($snapshot, TRUE, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded['room'] ?? NULL)) {
          throw new \DomainException('history_snapshot_invalid');
        }
        $room = $decoded['room'];
        break;

      default:
        throw new \InvalidArgumentException('command_type_unsupported');
    }

    return $room;
  }

  /**
   * Performs deterministic aggregate checks.
   */
  /**
   * Validate an aggregate independently of draft persistence.
   */
  public function validateAggregate(array $room, string $profile = 'editing'): array {
    $errors = [];
    $warnings = [];
    $required_fields = [
      'schema_version', 'room_id', 'name', 'description', 'room_type', 'size_category',
      'hexes', 'terrain', 'lighting', 'entry_ports', 'exit_ports', 'placements',
      'environmental_effects', 'gameplay_defaults', 'metadata',
    ];
    foreach ($required_fields as $field) {
      if (!array_key_exists($field, $room)) {
        $errors[] = $this->finding('required_field_missing', '/' . $field, "Missing required field: {$field}.");
      }
    }
    $unknown_fields = array_diff(array_keys($room), $required_fields);
    foreach ($unknown_fields as $field) {
      $errors[] = $this->finding('unknown_room_field', '/' . $field, "Unknown room field: {$field}.");
    }
    if (($room['schema_version'] ?? NULL) !== 'canonical-room-v1') {
      $errors[] = $this->finding('schema_version_invalid', '/schema_version', 'Room schema version must be canonical-room-v1.');
    }
    if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,99}$/', (string) ($room['room_id'] ?? ''))) {
      $errors[] = $this->finding('room_id_invalid', '/room_id', 'Room ID must be a canonical lowercase slug.');
    }
    if (!is_string($room['name'] ?? NULL) || mb_strlen($room['name']) < 1 || mb_strlen($room['name']) > 200) {
      $errors[] = $this->finding('room_name_invalid', '/name', 'Room name must contain 1 through 200 characters.');
    }
    if (!is_string($room['description'] ?? NULL) || mb_strlen($room['description']) < 1 || mb_strlen($room['description']) > 2000) {
      $errors[] = $this->finding('room_description_invalid', '/description', 'Room description must contain 1 through 2000 characters.');
    }
    if (!in_array($room['room_type'] ?? NULL, self::ROOM_TYPES, TRUE)) {
      $errors[] = $this->finding('room_type_invalid', '/room_type', 'Room type is not canonical.');
    }
    if (!in_array($room['size_category'] ?? NULL, self::SIZE_CATEGORIES, TRUE)) {
      $errors[] = $this->finding('size_category_invalid', '/size_category', 'Room size category is not canonical.');
    }
    if (!is_array($room['terrain'] ?? NULL) || !in_array($room['terrain']['type'] ?? NULL, self::TERRAIN_TYPES, TRUE)) {
      $errors[] = $this->finding('room_terrain_invalid', '/terrain/type', 'Room terrain default is not canonical.');
    }
    if (!is_array($room['lighting'] ?? NULL)
      || !in_array($room['lighting']['level'] ?? NULL, self::LIGHTING_LEVELS, TRUE)
      || !is_array($room['lighting']['light_sources'] ?? NULL)
      || count($room['lighting']['light_sources'] ?? []) > 20) {
      $errors[] = $this->finding('room_lighting_invalid', '/lighting', 'Room lighting must use a canonical level and at most 20 sources.');
    }
    $hexes = is_array($room['hexes'] ?? NULL) ? $room['hexes'] : [];
    $max_hexes = ($room['room_type'] ?? NULL) === 'street' ? self::MAX_STREET_HEXES : self::MAX_ROOM_HEXES;
    if (count($hexes) < 1 || count($hexes) > $max_hexes) {
      $errors[] = $this->finding('room_hex_count_invalid', '/hexes', sprintf('This room type requires 1 through %d hexes.', $max_hexes));
    }
    $description = is_string($room['description'] ?? NULL) ? trim((string) $room['description']) : '';
    $description_words = $description === '' ? [] : (preg_split('/\s+/u', $description) ?: []);
    if (
      $description === self::BLANK_DESCRIPTION
      || mb_strlen($description) < 40
      || count($description_words) < 8
    ) {
      $errors[] = $this->finding(
        'room_description_insufficient',
        '/description',
        'Room description is too vague for layout review. Describe purpose, atmosphere, key features, occupants, and how the space is used in at least 8 words.',
      );
    }
    $size_category = (string) ($room['size_category'] ?? '');
    $size_range = self::SIZE_HEX_RANGES[$size_category] ?? NULL;
    if (
      $size_range !== NULL
      && (count($hexes) < $size_range[0] || count($hexes) > $size_range[1])
    ) {
      $errors[] = $this->finding(
        'room_geometry_scale_mismatch',
        '/hexes',
        sprintf(
          'Room geometry has %d hexes, which is inconsistent with size category "%s". Expected approximately %d through %d hexes, or choose a more accurate category.',
          count($hexes),
          $size_category,
          $size_range[0],
          $size_range[1],
        ),
      );
    }
    $room_text = mb_strtolower(trim(
      (string) ($room['name'] ?? '') . ' ' . $description . ' ' . (string) ($room['room_type'] ?? ''),
    ));
    $wants_linear = preg_match('/\b(corridor|hallway|passage|tunnel|street|alley|bridge)\b/u', $room_text) === 1;
    if ($wants_linear && count($hexes) >= 7) {
      $qs = array_values(array_map(static fn(array $hex): int => (int) ($hex['q'] ?? 0), array_filter($hexes, 'is_array')));
      $rs = array_values(array_map(static fn(array $hex): int => (int) ($hex['r'] ?? 0), array_filter($hexes, 'is_array')));
      if ($qs !== [] && $rs !== []) {
        $span_q = max($qs) - min($qs);
        $span_r = max($rs) - min($rs);
        $longest = max($span_q, $span_r, 1);
        $shortest = max(min($span_q, $span_r), 1);
        if (($longest / $shortest) < 1.6) {
          $errors[] = $this->finding(
            'room_geometry_shape_mismatch',
            '/hexes',
            'Room name, description, or type implies a corridor/street-like space, but the footprint is not meaningfully longer than it is wide.',
          );
        }
      }
    }
    if (count((array) ($room['placements'] ?? [])) > self::MAX_PLACEMENTS) {
      $errors[] = $this->finding('room_placement_count_invalid', '/placements', 'Rooms support at most ' . self::MAX_PLACEMENTS . ' placements.');
    }
    if (count((array) ($room['entry_ports'] ?? [])) > 20) {
      $errors[] = $this->finding('room_entry_port_count_invalid', '/entry_ports', 'Rooms support at most 20 entry ports.');
    }
    if (count((array) ($room['exit_ports'] ?? [])) > 50) {
      $errors[] = $this->finding('room_exit_port_count_invalid', '/exit_ports', 'Rooms support at most 50 exit ports.');
    }
    $coordinates = [];
    foreach ($hexes as $index => $hex) {
      if (!is_array($hex)
        || !is_int($hex['q'] ?? NULL)
        || !is_int($hex['r'] ?? NULL)
        || $hex['q'] < -1000
        || $hex['q'] > 1000
        || $hex['r'] < -1000
        || $hex['r'] > 1000) {
        $errors[] = $this->finding('hex_coordinate_invalid', "/hexes/{$index}", 'Hex coordinates must be integers from -1000 through 1000.');
        continue;
      }
      $key = ($hex['q'] ?? '') . ':' . ($hex['r'] ?? '');
      if (isset($coordinates[$key])) {
        $errors[] = $this->finding('duplicate_hex', "/hexes/{$index}", "Duplicate room hex {$key}.");
      }
      $coordinates[$key] = TRUE;
      if (!in_array($hex['terrain_type'] ?? NULL, self::TERRAIN_TYPES, TRUE)) {
        $errors[] = $this->finding('terrain_type_invalid', "/hexes/{$index}/terrain_type", 'Hex terrain type is not canonical.');
      }
      if (!is_int($hex['elevation_ft'] ?? NULL) || $hex['elevation_ft'] < -50 || $hex['elevation_ft'] > 200) {
        $errors[] = $this->finding('hex_elevation_invalid', "/hexes/{$index}/elevation_ft", 'Hex elevation must be an integer from -50 through 200 feet.');
      }
      if (isset($hex['lighting']) && !in_array($hex['lighting'], self::LIGHTING_LEVELS, TRUE)) {
        $errors[] = $this->finding('hex_lighting_invalid', "/hexes/{$index}/lighting", 'Hex lighting is not canonical.');
      }
    }
    $solid_occupancy = [];
    foreach ((array) ($room['placements'] ?? []) as $index => $placement) {
      $key = ($placement['anchor_hex']['q'] ?? '') . ':' . ($placement['anchor_hex']['r'] ?? '');
      if (!isset($coordinates[$key])) {
        $errors[] = $this->finding('placement_outside_room', "/placements/{$index}/anchor_hex", 'Placement anchor is outside the room.');
      }
      if (!in_array($placement['definition_ref']['family'] ?? NULL, CanonicalDefinitionService::FAMILIES, TRUE)) {
        $errors[] = $this->finding('placement_family_invalid', "/placements/{$index}/definition_ref/family", 'Placement family is not canonical.');
      }
      if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,99}$/', (string) ($placement['definition_ref']['definition_id'] ?? ''))
        || !preg_match('/^\d+\.\d+\.\d+$/', (string) ($placement['definition_ref']['version'] ?? ''))) {
        $errors[] = $this->finding('placement_definition_ref_invalid', "/placements/{$index}/definition_ref", 'Placement definition reference must use a canonical ID and semantic version.');
      }
      if (($placement['room_id'] ?? NULL) !== ($room['room_id'] ?? NULL)) {
        $errors[] = $this->finding('placement_room_id_mismatch', "/placements/{$index}/room_id", 'Placement room ID must match its aggregate.');
      }
      if (!is_int($placement['facing'] ?? NULL) || $placement['facing'] < 0 || $placement['facing'] > 5) {
        $errors[] = $this->finding('placement_facing_invalid', "/placements/{$index}/facing", 'Placement facing must be an integer from 0 through 5.');
      }
      if (!is_int($placement['elevation_ft'] ?? NULL) || $placement['elevation_ft'] < -50 || $placement['elevation_ft'] > 200) {
        $errors[] = $this->finding('placement_elevation_invalid', "/placements/{$index}/elevation_ft", 'Placement elevation must be an integer from -50 through 200 feet.');
      }
      if (!is_array($placement['footprint'] ?? NULL)
        || count($placement['footprint'] ?? []) < 1
        || count($placement['footprint'] ?? []) > 25) {
        $errors[] = $this->finding('placement_footprint_invalid', "/placements/{$index}/footprint", 'Placement footprint must contain 1 through 25 coordinates.');
      }
      $family = (string) ($placement['definition_ref']['family'] ?? '');
      if (in_array($family, ['actor', 'obstacle'], TRUE)) {
        if (isset($solid_occupancy[$key])) {
          $errors[] = $this->finding('placement_collision', "/placements/{$index}/anchor_hex", 'Solid placements cannot share a room hex.');
        }
        $solid_occupancy[$key] = TRUE;
      }
      if ($profile === 'publication' && !$this->definitions->definitionExists(
        $family,
        (string) ($placement['definition_ref']['definition_id'] ?? ''),
        (string) ($placement['definition_ref']['version'] ?? '')
      )) {
        $errors[] = $this->finding('definition_not_found', "/placements/{$index}/definition_ref", 'Pinned placement definition is not available from canonical authority.');
      }
    }
    if (!is_array($room['environmental_effects'] ?? NULL) || count($room['environmental_effects'] ?? []) > 20) {
      $errors[] = $this->finding('environmental_effects_invalid', '/environmental_effects', 'Rooms support at most 20 environmental effects.');
    }
    $tags = $room['metadata']['tags'] ?? NULL;
    if (!is_array($tags)
      || array_filter($tags, static fn($tag): bool => !is_string($tag)) !== []
      || count($tags) > 50
      || count(array_unique($tags)) !== count($tags)) {
      $errors[] = $this->finding('metadata_tags_invalid', '/metadata/tags', 'Room tags must be unique and contain at most 50 values.');
    }
    if (!is_bool($room['gameplay_defaults']['safe_for_rest'] ?? NULL)
      || !in_array($room['gameplay_defaults']['visibility'] ?? NULL, ['visible', 'hidden'], TRUE)) {
      $errors[] = $this->finding('gameplay_defaults_invalid', '/gameplay_defaults', 'Gameplay defaults must define rest safety and canonical visibility.');
    }
    $instance_ids = [];
    foreach ((array) ($room['placements'] ?? []) as $index => $placement) {
      $instance_id = (string) ($placement['instance_id'] ?? '');
      if (!$this->isUuid($instance_id)) {
        $errors[] = $this->finding('instance_id_invalid', "/placements/{$index}/instance_id", 'Placement instance ID must be a UUID.');
      }
      if (isset($instance_ids[$instance_id])) {
        $errors[] = $this->finding('instance_id_duplicate', "/placements/{$index}/instance_id", 'Placement instance IDs must be unique.');
      }
      $instance_ids[$instance_id] = TRUE;
    }
    foreach (['entry_ports' => TRUE, 'exit_ports' => FALSE] as $bucket => $is_entry) {
      $port_ids = [];
      foreach ((array) ($room[$bucket] ?? []) as $index => $port) {
        try {
          $normalized_port = $this->normalizePort($port, $is_entry);
          $key = $normalized_port['hex']['q'] . ':' . $normalized_port['hex']['r'];
          if (!isset($coordinates[$key])) {
            $errors[] = $this->finding('port_outside_room', "/{$bucket}/{$index}/hex", 'Port anchor is outside the room.');
          }
          else {
            // A port is a doorway through the room boundary. The hex on the
            // far side of its declared edge must therefore lie outside the
            // footprint; otherwise the edge points into the room and the
            // dungeon-level port transform would seal it against a wall.
            $outside = RoomPlacementTransformer::neighbor($normalized_port['hex'], $normalized_port['edge']);
            // A street's doors may stand anywhere on it: a building placed
            // over the street overrides the hexes it covers.
            $street_door = $bucket === 'exit_ports' && ($room['room_type'] ?? NULL) === 'street';
            if (!$street_door && isset($coordinates[$outside['q'] . ':' . $outside['r']])) {
              $errors[] = $this->finding(
                'port_edge_not_boundary',
                "/{$bucket}/{$index}/edge",
                'Port edge must face out of the room: the neighbouring hex across this edge is inside the footprint.',
                'error',
                ['hex' => $normalized_port['hex'], 'edge' => $normalized_port['edge'], 'blocked_by' => $outside]
              );
            }
          }
          if (isset($port_ids[$normalized_port['port_id']])) {
            $errors[] = $this->finding('port_id_duplicate', "/{$bucket}/{$index}/port_id", 'Port IDs must be unique within their family.');
          }
          $port_ids[$normalized_port['port_id']] = TRUE;
        }
        catch (\InvalidArgumentException) {
          $errors[] = $this->finding('port_invalid', "/{$bucket}/{$index}", 'Port is missing required or valid fields.');
        }
      }
    }
    $open_gaps = [];
    foreach ((array) ($room['metadata']['placement_gaps'] ?? []) as $gap) {
      if (($gap['status'] ?? 'open') === 'open') {
        $open_gaps[(string) $gap['operation_id']][] = $gap;
      }
    }
    foreach ($open_gaps as $operation_id => $gaps) {
      $finding = $this->finding(
        'placement_gap_open',
        '/metadata/placement_gaps',
        sprintf('%d placement%s skipped by "%s" and still open.', count($gaps), count($gaps) === 1 ? '' : 's', $gaps[0]['operation']),
        $profile === 'publication' ? 'error' : 'warning',
        ['operation_id' => $operation_id, 'operation' => $gaps[0]['operation'], 'gap_count' => count($gaps), 'reasons' => array_values(array_unique(array_column($gaps, 'reason')))]
      );
      if ($profile === 'publication') {
        $errors[] = $finding;
      }
      else {
        $warnings[] = $finding;
      }
    }
    $defaults = array_filter((array) ($room['entry_ports'] ?? []), static fn(array $port): bool => !empty($port['is_default']));
    $background = $room['metadata']['background'] ?? NULL;
    if ($background !== NULL && (!is_array($background)
      || !is_string($background['url'] ?? NULL) || trim($background['url']) === ''
      || (isset($background['width']) && (!is_int($background['width']) || $background['width'] < 1))
      || (isset($background['height']) && (!is_int($background['height']) || $background['height'] < 1)))) {
      $errors[] = $this->finding('room_background_invalid', '/metadata/background', 'Room background must have a url and, when given, positive integer width and height.');
    }
    if (($room['room_type'] ?? NULL) === 'street') {
      if ($background === NULL) {
        $errors[] = $this->finding('street_background_missing', '/metadata/background', 'A street room needs background art in metadata.background.', $profile === 'publication' ? 'error' : 'warning');
      }
      if ((array) ($room['exit_ports'] ?? []) === []) {
        $warnings[] = $this->finding('street_has_no_doors', '/exit_ports', 'A street room has no door ports, so no building can open onto it.', 'warning');
      }
    }
    if ($profile === 'publication' && count($defaults) !== 1) {
      $errors[] = $this->finding('default_entry_required', '/entry_ports', 'Published rooms require exactly one default entry.');
    }
    if ($profile === 'publication' && ($room['placements'] ?? []) === []) {
      $warnings[] = $this->finding('room_has_no_placements', '/placements', 'Room has no placeable objects.', 'warning');
    }
    return self::validationResult(array_merge($errors, $warnings));
  }

  /**
   * The validation result shape shared with the Dungeon Editor.
   *
   * @return array{is_valid: bool, findings: array, counts: array{error: int, warning: int, info: int}}
   */
  public static function validationResult(array $findings): array {
    $counts = ['error' => 0, 'warning' => 0, 'info' => 0];
    foreach ($findings as $finding) {
      $counts[$finding['severity']]++;
    }
    return [
      'is_valid' => $counts['error'] === 0,
      'findings' => array_values($findings),
      'counts' => $counts,
    ];
  }

  /**
   * The error findings of a validation result.
   */
  public static function errorFindings(array $validation): array {
    return array_values(array_filter(
      $validation['findings'],
      static fn(array $finding): bool => $finding['severity'] === 'error',
    ));
  }

  /**
   * Builds one finding; its route comes from FINDING_ROUTES.
   */
  public static function finding(string $code, string $path, string $message, string $severity = 'error', array $context = []): array {
    return [
      'severity' => $severity,
      'code' => $code,
      'message' => $message,
      'route' => self::FINDING_ROUTES[$code]
        ?? throw new \LogicException('room_finding_route_undeclared:' . $code),
      'path' => $path,
      'context' => $context,
    ];
  }

  private function blankRoom(): array {
    $hexes = [];
    for ($q = -2; $q <= 2; $q++) {
      for ($r = max(-2, -$q - 2); $r <= min(2, -$q + 2); $r++) {
        $hexes[] = ['q' => $q, 'r' => $r, 'terrain_type' => 'stone_floor', 'elevation_ft' => 0, 'lighting' => 'bright_light'];
      }
    }
    return [
      'schema_version' => 'canonical-room-v1',
      'room_id' => 'new-room-' . substr($this->uuid->generate(), 0, 8),
      'name' => self::BLANK_NAME,
      'description' => self::BLANK_DESCRIPTION,
      'room_type' => 'chamber',
      'size_category' => 'gargantuan',
      'hexes' => $hexes,
      'terrain' => ['type' => 'stone_floor'],
      'lighting' => ['level' => 'bright_light', 'light_sources' => []],
      'entry_ports' => [[
        'port_id' => 'entry-1',
        'hex' => ['q' => -2, 'r' => 0],
        'edge' => 3,
        'label' => 'Default entry',
        'arrival_facing' => 0,
        'is_default' => TRUE,
        'tags' => [],
      ]],
      'exit_ports' => [],
      'placements' => [],
      'environmental_effects' => [],
      'gameplay_defaults' => ['safe_for_rest' => FALSE, 'visibility' => 'visible'],
      'metadata' => ['tags' => [], 'provenance' => ['source' => 'room_editor']],
    ];
  }

  private function normalizeLegacyRoom(array $row): array {
    if (!function_exists('_dungeoncrawler_content_room_editor_legacy_aggregate')) {
      require_once DRUPAL_ROOT . '/' . \Drupal::service('extension.list.module')->getPath('dungeoncrawler_content') . '/dungeoncrawler_content.install';
    }
    return _dungeoncrawler_content_room_editor_legacy_aggregate($row);
  }

  /**
   * Projects an ordered command list against a draft without persisting it.
   *
   * Simulation reuses the same mutation and validation code as applyCommand(),
   * so a previewed plan cannot diverge from what execution would actually do.
   * Nothing is written: no revision bump, no command log entry, no draft row
   * update.
   */
  public function simulateCommands(string $draft_id, array $commands, string $profile = 'editing'): array {
    if ($commands === []) {
      throw new \InvalidArgumentException('command_plan_empty');
    }
    $draft = $this->getDraft($draft_id);
    if ($draft['status'] !== 'active') {
      throw new \DomainException('draft_not_active');
    }

    $room = $draft['room'];
    $steps = [];
    foreach (array_values($commands) as $index => $command) {
      $step = $index + 1;
      if (!is_array($command)) {
        throw new \InvalidArgumentException(sprintf('command_step_invalid:%d', $step));
      }
      $type = (string) ($command['type'] ?? '');
      if ($type === '') {
        throw new \InvalidArgumentException(sprintf('command_step_type_required:%d', $step));
      }
      $payload = $command['payload'] ?? NULL;
      if (!is_array($payload)) {
        throw new \InvalidArgumentException(sprintf('command_step_payload_invalid:%d', $step));
      }
      try {
        $candidate = $this->mutate($room, $type, $payload, $draft_id);
        $this->assertNoIntroducedValidationErrors($room, $candidate);
        $room = $candidate;
        $steps[] = ['step' => $step, 'command_type' => $type, 'applies' => TRUE, 'error' => NULL];
      }
      catch (\Throwable $exception) {
        $steps[] = [
          'step' => $step,
          'command_type' => $type,
          'applies' => FALSE,
          'error' => $exception->getMessage(),
        ];
        break;
      }
    }

    $blocked = array_values(array_filter($steps, static fn(array $s): bool => !$s['applies']));
    $validation = $blocked === [] ? $this->validateAggregate($room, $profile) : self::validationResult([]);

    return [
      'draft_id' => $draft_id,
      'base_revision' => (int) $draft['revision'],
      'projected_revision' => (int) $draft['revision'] + count(array_filter($steps, static fn(array $s): bool => $s['applies'])),
      'applies_cleanly' => $blocked === [],
      'steps' => $steps,
      'projected_room' => $room,
      'validation' => ['profile' => $profile, 'is_valid' => $blocked === [] && $validation['is_valid']] + $validation,
    ];
  }

  /**
   * Rejects a command only when its result introduces new validation errors.
   */
  private function assertNoIntroducedValidationErrors(array $before, array $after): void {
    $introduced = $this->introducedValidationErrors($before, $after);
    if ($introduced !== []) {
      throw new \DomainException((string) $introduced[0]['code']);
    }
  }

  /**
   * Validation errors in $after not already present in $before.
   *
   * Error identity is the public editor contract tuple: code plus path. The
   * complete validation result still reports every finding to readers; this
   * method only controls whether one mutation is allowed to proceed.
   */
  private function introducedValidationErrors(array $before, array $after): array {
    $existing = [];
    foreach (self::errorFindings($this->validateAggregate($before)) as $error) {
      $existing[$this->validationErrorKey($error)] = TRUE;
    }

    $introduced = [];
    foreach (self::errorFindings($this->validateAggregate($after)) as $error) {
      if (!isset($existing[$this->validationErrorKey($error)])) {
        $introduced[] = $error;
      }
    }
    return $introduced;
  }

  private function validationErrorKey(array $error): string {
    return (string) ($error['code'] ?? '') . "\n" . (string) ($error['path'] ?? '');
  }

  /**
   * Returns the currently published canonical aggregate for one room.
   */
  public function publishedRoom(string $room_id): ?array {
    if ($room_id === '') {
      throw new \InvalidArgumentException('room_id_required');
    }
    $version_id = $this->database->select('dungeoncrawler_content_rooms', 'r')
      ->fields('r', ['published_version_id'])
      ->condition('room_id', $room_id)
      ->execute()
      ->fetchField();
    return $this->loadPublishedPayload($version_id ?: NULL);
  }

  /**
   * Returns the published version string of a room, or NULL when unpublished.
   */
  public function publishedVersion(string $room_id): ?string {
    $query = $this->database->select('dungeoncrawler_content_rooms', 'r');
    $query->innerJoin('dungeoncrawler_content_room_versions', 'v', 'v.version_id = r.published_version_id');
    $query->fields('v', ['version']);
    $query->condition('r.room_id', $room_id);
    $version = $query->execute()->fetchField();
    return $version ? (string) $version : NULL;
  }

  private function loadPublishedPayload(?string $version_id): ?array {
    if (!$version_id) {
      return NULL;
    }
    $payload = $this->database->select('dungeoncrawler_content_room_versions', 'v')
      ->fields('v', ['room_payload'])
      ->condition('version_id', $version_id)
      ->execute()
      ->fetchField();
    return $payload ? json_decode($payload, TRUE, 512, JSON_THROW_ON_ERROR) : NULL;
  }

  private function decodeDraft(array $row): array {
    return [
      'schema_version' => 'room-editor-draft-v1',
      'draft_id' => $row['draft_id'],
      'room_id' => $row['room_id'],
      'base_version_id' => $row['base_version_id'],
      'revision' => (int) $row['revision'],
      'status' => $row['status'],
      'room' => json_decode($row['room_payload'], TRUE, 512, JSON_THROW_ON_ERROR),
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
      && !$this->currentUser->hasPermission('publish canonical dungeoncrawler rooms')) {
      throw new \UnexpectedValueException('draft_access_denied');
    }
  }

  private function normalizeHex(mixed $hex): array {
    if (!is_array($hex) || !isset($hex['q'], $hex['r'])) {
      throw new \InvalidArgumentException('hex_invalid');
    }
    $normalized = [
      'q' => max(-1000, min(1000, (int) $hex['q'])),
      'r' => max(-1000, min(1000, (int) $hex['r'])),
      'terrain_type' => (string) ($hex['terrain_type'] ?? 'stone_floor'),
      'elevation_ft' => max(-50, min(200, (int) ($hex['elevation_ft'] ?? 0))),
      'lighting' => (string) ($hex['lighting'] ?? 'bright_light'),
    ];
    foreach ([
      'h3_index_res14',
      'h3_index',
      'lat',
      'lng',
      'is_entry',
      'is_discovered',
      'is_visible',
      'movement_cost',
      'elevation',
      'objects',
      'metadata',
    ] as $field) {
      if (array_key_exists($field, $hex)) {
        $normalized[$field] = $hex[$field];
      }
    }
    return $normalized;
  }

  private function mergeHexFixedData(array $hex, mixed $source): array {
    if (!is_array($source)) {
      throw new \InvalidArgumentException('hex_invalid');
    }
    foreach ([
      'h3_index_res14',
      'h3_index',
      'lat',
      'lng',
      'is_entry',
      'is_discovered',
      'is_visible',
      'movement_cost',
      'elevation',
      'objects',
      'metadata',
    ] as $field) {
      if (array_key_exists($field, $source)) {
        $hex[$field] = $source[$field];
      }
    }
    return $hex;
  }

  /**
   * Enforce hard starter-room fixed-data invariants at publication.
   */
  private function assertStarterFixedDataContract(array $room): void {
    $room_id = trim((string) ($room['room_id'] ?? ''));
    if ($room_id !== 'tavern_entrance' && $room_id !== 'tpl_room_absalom_streets') {
      return;
    }

    $hexes = is_array($room['hexes'] ?? NULL) ? $room['hexes'] : [];
    foreach ($hexes as $index => $hex) {
      if (!is_array($hex)) {
        throw new \DomainException('starter_room_hex_invalid');
      }
      $h3 = trim((string) ($hex['h3_index_res14'] ?? $hex['h3_index'] ?? ''));
      if ($h3 === '') {
        throw new \DomainException('starter_room_h3_required');
      }
    }

    if ($room_id === 'tavern_entrance') {
      $exit_ports = is_array($room['exit_ports'] ?? NULL) ? $room['exit_ports'] : [];
      $has_absalom_streets_exit = FALSE;
      foreach ($exit_ports as $port) {
        if (!is_array($port)) {
          continue;
        }
        if (trim((string) ($port['destination_hint'] ?? '')) === 'tpl_room_absalom_streets') {
          $has_absalom_streets_exit = TRUE;
          break;
        }
      }
      if (!$has_absalom_streets_exit) {
        throw new \DomainException('starter_room_exit_target_required');
      }
    }
  }

  private function normalizePort(array $port, bool $is_entry): array {
    $required = $is_entry
      ? ['port_id', 'hex', 'edge', 'label', 'arrival_facing', 'is_default', 'tags']
      : ['port_id', 'hex', 'edge', 'label', 'kind', 'direction', 'default_state', 'destination_hint', 'linked_placement_id', 'requirements', 'tags'];
    foreach ($required as $field) {
      if (!array_key_exists($field, $port)) {
        throw new \InvalidArgumentException('port_invalid');
      }
    }
    if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,99}$/', (string) $port['port_id'])
      || !is_array($port['hex'])
      || !isset($port['hex']['q'], $port['hex']['r'])
      || !is_int($port['hex']['q'])
      || !is_int($port['hex']['r'])
      || $port['hex']['q'] < -1000
      || $port['hex']['q'] > 1000
      || $port['hex']['r'] < -1000
      || $port['hex']['r'] > 1000
      || !is_string($port['label'])
      || trim($port['label']) === ''
      || mb_strlen(trim($port['label'])) > 200
      || !is_array($port['tags'])
      || array_filter($port['tags'], static fn($tag): bool => !is_string($tag)) !== []
      || count(array_unique($port['tags'])) !== count($port['tags'])
      || !is_int($port['edge'])
      || $port['edge'] < 0
      || $port['edge'] > 5) {
      throw new \InvalidArgumentException('port_invalid');
    }
    $port['hex'] = ['q' => $port['hex']['q'], 'r' => $port['hex']['r']];
    $port['label'] = trim($port['label']);
    $port['tags'] = array_values($port['tags']);
    if ($is_entry) {
      if (!is_int($port['arrival_facing'])
        || $port['arrival_facing'] < 0
        || $port['arrival_facing'] > 5
        || !is_bool($port['is_default'])) {
        throw new \InvalidArgumentException('port_invalid');
      }
    }
    elseif (!in_array($port['kind'], ['hallway', 'archway', 'door', 'hatch', 'portcullis', 'secret_door', 'magical_barrier', 'collapsed', 'bridge', 'one_way_drop'], TRUE)
      || !in_array($port['direction'], ['bidirectional', 'one_way'], TRUE)
      || !in_array($port['default_state'], ['open', 'closed', 'locked', 'barred', 'trapped', 'triggered', 'destroyed'], TRUE)
      || !is_array($port['requirements'])
      || (!is_null($port['destination_hint']) && !is_string($port['destination_hint']))
      || (is_string($port['destination_hint']) && mb_strlen($port['destination_hint']) > 200)
      || (!is_null($port['linked_placement_id']) && (!is_string($port['linked_placement_id']) || !$this->isUuid($port['linked_placement_id'])))) {
      throw new \InvalidArgumentException('port_invalid');
    }
    return $port;
  }

  /**
   * @return list<string>
   */
  private function bulkIds(mixed $ids, string $error): array {
    if (!is_array($ids) || $ids === [] || !array_is_list($ids) || count($ids) > self::MAX_BULK) {
      throw new \InvalidArgumentException($error);
    }
    foreach ($ids as $id) {
      if (!is_string($id) || $id === '') {
        throw new \InvalidArgumentException($error);
      }
    }
    return array_values(array_unique($ids));
  }

  /**
   * Validates a definition a shape may place and returns its normal form.
   */
  private function bulkDefinition(mixed $definition): array {
    if (!is_array($definition) || !in_array($definition['family'] ?? NULL, self::SHAPE_FAMILIES, TRUE)) {
      throw new \InvalidArgumentException('bulk_family_invalid');
    }
    $family = (string) $definition['family'];
    $id = (string) ($definition['definition_id'] ?? '');
    $version = (string) ($definition['version'] ?? '1.0.0');
    $this->definitions->assertDefinitionSelectable($family, $id);
    if (!$this->definitions->definitionExists($family, $id, $version)) {
      throw new \DomainException('definition_not_found');
    }
    return ['family' => $family, 'definition_id' => $id, 'version' => $version];
  }

  /**
   * Records, updates or clears one gap on the draft.
   *
   * A gap an item already carries is updated in place on failure and removed
   * on success, so retrying never duplicates it.
   */
  private function settleGap(array &$room, array $item, string $operation, string $operation_id, ?array $refusal, array $facts): void {
    $gaps = array_values((array) ($room['metadata']['placement_gaps'] ?? []));
    $gap_id = isset($item['gap_id']) ? (string) $item['gap_id'] : NULL;
    $position = NULL;
    if ($gap_id !== NULL) {
      foreach ($gaps as $i => $gap) {
        if (($gap['gap_id'] ?? NULL) === $gap_id && ($gap['status'] ?? 'open') === 'open') {
          $position = $i;
        }
      }
      if ($position === NULL) {
        throw new \DomainException('placement_gap_not_found');
      }
    }
    if ($refusal === NULL) {
      if ($position !== NULL) {
        array_splice($gaps, $position, 1);
      }
    }
    else {
      $row = [
        'gap_id' => $gap_id ?? $this->uuid->generate(),
        'operation' => $operation,
        'operation_id' => $operation_id,
        'status' => 'open',
        'reason' => $refusal['reason'],
        'blocker' => $refusal['blocker'],
      ] + $facts;
      if ($position !== NULL) {
        $row['operation'] = $gaps[$position]['operation'];
        $row['operation_id'] = $gaps[$position]['operation_id'];
        $gaps[$position] = $row;
      }
      else {
        $gaps[] = $row;
      }
    }
    if ($gaps === [] && !isset($room['metadata']['placement_gaps'])) {
      return;
    }
    $room['metadata']['placement_gaps'] = $gaps;
  }

  private function bulkOperation(array $payload): array {
    $operation = (string) ($payload['operation'] ?? 'place_objects');
    if (!preg_match('/^[a-z][a-z0-9_]{0,39}$/', $operation)) {
      throw new \InvalidArgumentException('operation_invalid');
    }
    $operation_id = (string) ($payload['operation_id'] ?? $this->uuid->generate());
    if (!$this->isUuid($operation_id)) {
      throw new \InvalidArgumentException('operation_id_invalid');
    }
    return [$operation, $operation_id];
  }

  private function bulkItems(array $payload): array {
    $items = $payload['items'] ?? NULL;
    if (!is_array($items) || $items === [] || !array_is_list($items) || count($items) > self::MAX_BULK) {
      throw new \InvalidArgumentException('bulk_items_invalid');
    }
    return $items;
  }

  /**
   * Places many obstacles or items; a hex that cannot take one is skipped and
   * recorded as a gap instead of stopping the command.
   */
  private function placeObjectsBulk(array &$room, array $payload): void {
    [$operation, $operation_id] = $this->bulkOperation($payload);
    $items = $this->bulkItems($payload);
    $terrain = PlacementRules::terrainByHex($room);
    $clear = PlacementRules::keptClear($room, $payload);
    $occupied = PlacementRules::solidOccupancy($room);
    $definitions = [];
    foreach ($items as $item) {
      if (!is_array($item) || !isset($item['anchor_hex']['q'], $item['anchor_hex']['r']) || !is_int($item['anchor_hex']['q']) || !is_int($item['anchor_hex']['r'])) {
        throw new \InvalidArgumentException('bulk_item_invalid');
      }
      $ref = $item['definition_ref'] ?? NULL;
      $cache_key = is_array($ref) ? json_encode($ref) : '';
      $definitions[$cache_key] ??= $this->bulkDefinition($ref);
      $family = $definitions[$cache_key]['family'];
      $q = $item['anchor_hex']['q'];
      $r = $item['anchor_hex']['r'];
      $facing = max(0, min(5, (int) ($item['facing'] ?? 0)));
      $refusal = PlacementRules::refusal($terrain, $clear, $occupied, $family, $q . ':' . $r);
      if ($refusal === NULL) {
        $instance_id = (string) ($item['instance_id'] ?? $this->uuid->generate());
        if (!$this->isUuid($instance_id)) {
          throw new \InvalidArgumentException('instance_id_invalid');
        }
        if ($this->findPlacementIndex($room, $instance_id) !== NULL) {
          throw new \DomainException('instance_id_already_exists');
        }
        $room['placements'][] = [
          'schema_version' => 'room-placement-v1',
          'instance_id' => $instance_id,
          'definition_ref' => $definitions[$cache_key],
          'room_id' => (string) $room['room_id'],
          'anchor_hex' => ['q' => $q, 'r' => $r],
          'footprint' => [['q' => 0, 'r' => 0]],
          'facing' => $facing,
          'elevation_ft' => max(-50, min(200, (int) ($item['elevation_ft'] ?? 0))),
          'state_defaults' => [],
          'overrides' => is_array($item['overrides'] ?? NULL) ? $item['overrides'] : [],
        ];
        if ($family === 'obstacle') {
          $occupied[$q . ':' . $r] = $instance_id;
        }
      }
      $this->settleGap($room, $item, $operation, $operation_id, $refusal, [
        'hex' => ['q' => $q, 'r' => $r],
        'definition_ref' => $definitions[$cache_key],
        'facing' => $facing,
      ]);
    }
  }

  /**
   * Moves many placements; one whose target is refused stays where it is and
   * the refusal is recorded as a gap.
   */
  private function moveObjectsBulk(array &$room, array $payload): void {
    [$operation, $operation_id] = $this->bulkOperation($payload);
    $items = $this->bulkItems($payload);
    $moving = [];
    foreach ($items as $item) {
      $id = (string) ($item['instance_id'] ?? '');
      if ($this->findPlacementIndex($room, $id) === NULL) {
        throw new \DomainException('placement_not_found');
      }
      $moving[] = $id;
    }
    $terrain = PlacementRules::terrainByHex($room);
    $clear = PlacementRules::keptClear($room, $payload);
    // The moving set leaves its hexes first, so shifting a line by one hex
    // does not collide with itself.
    $occupied = PlacementRules::solidOccupancy($room, $moving);
    foreach ($items as $item) {
      if (!isset($item['anchor_hex']['q'], $item['anchor_hex']['r']) || !is_int($item['anchor_hex']['q']) || !is_int($item['anchor_hex']['r'])) {
        throw new \InvalidArgumentException('bulk_item_invalid');
      }
      $index = $this->findPlacementIndex($room, (string) $item['instance_id']);
      $family = (string) ($room['placements'][$index]['definition_ref']['family'] ?? '');
      $q = $item['anchor_hex']['q'];
      $r = $item['anchor_hex']['r'];
      $refusal = PlacementRules::refusal($terrain, $clear, $occupied, $family, $q . ':' . $r);
      if ($refusal === NULL) {
        $room['placements'][$index]['anchor_hex'] = ['q' => $q, 'r' => $r];
        if (isset($item['facing'])) {
          if (!is_int($item['facing']) || $item['facing'] < 0 || $item['facing'] > 5) {
            throw new \InvalidArgumentException('bulk_item_invalid');
          }
          $room['placements'][$index]['facing'] = $item['facing'];
        }
        if (in_array($family, ['actor', 'obstacle'], TRUE)) {
          $occupied[$q . ':' . $r] = (string) $item['instance_id'];
        }
      }
      else {
        // It stays put, so it still occupies its own hex.
        $own = $room['placements'][$index]['anchor_hex'];
        if (in_array($family, ['actor', 'obstacle'], TRUE)) {
          $occupied[$own['q'] . ':' . $own['r']] = (string) $item['instance_id'];
        }
      }
      $this->settleGap($room, $item, $operation, $operation_id, $refusal, [
        'hex' => ['q' => $q, 'r' => $r],
        'instance_id' => (string) $item['instance_id'],
        'definition_ref' => $room['placements'][$index]['definition_ref'],
        'facing' => (int) ($room['placements'][$index]['facing'] ?? 0),
      ]);
    }
  }

  private function findHexIndex(array $room, int $q, int $r): ?int {
    foreach ((array) ($room['hexes'] ?? []) as $index => $hex) {
      if ((int) ($hex['q'] ?? PHP_INT_MAX) === $q && (int) ($hex['r'] ?? PHP_INT_MAX) === $r) {
        return $index;
      }
    }
    return NULL;
  }

  private function findPlacementIndex(array $room, string $instance_id): ?int {
    foreach ((array) ($room['placements'] ?? []) as $index => $placement) {
      if (($placement['instance_id'] ?? NULL) === $instance_id) {
        return $index;
      }
    }
    return NULL;
  }

  private function assertNoSolidPlacementCollision(array $room, string $family, int $q, int $r, ?string $ignored_instance_id = NULL): void {
    if (!in_array($family, ['actor', 'obstacle'], TRUE)) {
      return;
    }
    foreach ((array) ($room['placements'] ?? []) as $placement) {
      if ($ignored_instance_id !== NULL && ($placement['instance_id'] ?? NULL) === $ignored_instance_id) {
        continue;
      }
      $placed_family = $placement['definition_ref']['family'] ?? '';
      if (($placement['anchor_hex']['q'] ?? NULL) === $q
        && ($placement['anchor_hex']['r'] ?? NULL) === $r
        && in_array($placed_family, ['actor', 'obstacle'], TRUE)) {
        throw new \DomainException('placement_collision');
      }
    }
  }




  private function isUuid(string $value): bool {
    return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value);
  }


  private function legacyProjection(array $room): array {
    $entry_points = array_map(static fn(array $port): array => [
      'q' => (int) $port['hex']['q'],
      'r' => (int) $port['hex']['r'],
      'edge' => (int) $port['edge'],
      'label' => (string) $port['label'],
      'arrival_facing' => (int) $port['arrival_facing'],
      'is_default' => (bool) $port['is_default'],
    ], $room['entry_ports']);
    $exit_points = array_map(static fn(array $port): array => [
      'q' => (int) $port['hex']['q'],
      'r' => (int) $port['hex']['r'],
      'edge' => (int) $port['edge'],
      'label' => (string) $port['label'],
      'kind' => (string) $port['kind'],
      'direction' => (string) $port['direction'],
      'default_state' => (string) $port['default_state'],
      'target_room_id' => $port['destination_hint'],
    ], $room['exit_ports']);
    $room_id = (string) ($room['room_id'] ?? 'room');
    $hexes = [];
    foreach ($room['hexes'] as $hex) {
      if (is_array($hex) && is_numeric($hex['q'] ?? NULL) && is_numeric($hex['r'] ?? NULL)) {
        $existing_h3 = trim((string) ($hex['h3_index_res14'] ?? $hex['h3_index'] ?? ''));
        if ($existing_h3 === '') {
          $latlng = \Drupal\dungeoncrawler_content\Support\H3SpatialHelper::projectAxialHexToLatLng($room_id, (int) $hex['q'], (int) $hex['r']);
          $existing_h3 = strtolower(\Drupal\dungeoncrawler_content\Support\H3SpatialHelper::latLngToH3Index(
            (float) $latlng['latitude'],
            (float) $latlng['longitude'],
            14
          ));
        }
        $hex['h3_index'] = strtolower($existing_h3);
        $hex['h3_index_res14'] = strtolower($existing_h3);
      }
      $hexes[] = $hex;
    }
    $layout = [
      'hexes' => $hexes,
      'entry_points' => $entry_points,
      'exit_points' => $exit_points,
      'exits' => $exit_points,
      'shape' => $room['metadata']['shape'] ?? 'custom',
      'room_type' => $room['room_type'],
      'size_category' => $room['size_category'],
    ];
    $contents = [
      'entities' => $room['placements'],
      'npcs' => [],
      'creatures' => [],
      'items' => [],
      'obstacles' => [],
      'traps' => [],
      'hazards' => [],
      'interactables' => [],
    ];
    foreach ($room['placements'] as $placement) {
      $family = $placement['definition_ref']['family'];
      $definition_id = (string) ($placement['definition_ref']['definition_id'] ?? '');
      $bucket = match ($family) {
        'actor' => $this->definitions->isCreature($definition_id) ? 'creatures' : 'npcs',
        'item' => 'items',
        'obstacle' => 'obstacles',
        'trap' => 'traps',
        'hazard' => 'hazards',
      };
      $entry = [
        'instance_id' => $placement['instance_id'],
        'content_id' => $definition_id,
        'version' => $placement['definition_ref']['version'],
        'position' => $placement['anchor_hex'],
        'orientation' => $placement['facing'],
        'elevation_ft' => $placement['elevation_ft'],
        'state_defaults' => $placement['state_defaults'],
        'overrides' => $placement['overrides'],
      ];
      if ($family === 'item' && $definition_id !== '') {
        $entry = $this->hydrateProjectedItemPlacement($entry, $definition_id);
      }
      elseif ($family === 'actor' && $definition_id !== '') {
        $entry = $this->hydrateProjectedActorPlacement($entry, $definition_id);
      }
      $contents[$bucket][] = $entry;
    }
    return [$layout, $contents];
  }

  /**
   * Hydrate projected item placements from canonical registry metadata.
   */
  private function hydrateProjectedItemPlacement(array $entry, string $definition_id): array {
    $row = $this->database->select('dungeoncrawler_content_registry', 'r')
      ->fields('r', ['name', 'tags', 'schema_data'])
      ->condition('content_type', 'item')
      ->condition('content_id', $definition_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($row)) {
      return $entry;
    }
    $schema_data = json_decode((string) ($row['schema_data'] ?? '{}'), TRUE);
    if (!is_array($schema_data)) {
      $schema_data = [];
    }
    $tags = json_decode((string) ($row['tags'] ?? '[]'), TRUE);
    if (!is_array($tags)) {
      $tags = is_array($schema_data['tags'] ?? NULL) ? $schema_data['tags'] : [];
    }
    $quest_association = trim((string) ($schema_data['quest_association'] ?? ''));
    if ($quest_association !== '' && !in_array($quest_association, $tags, TRUE)) {
      $tags[] = $quest_association;
    }
    $item_type = trim((string) ($schema_data['item_type'] ?? $schema_data['type'] ?? ''));
    if ($item_type === 'collectible_item' && !in_array('collectible', $tags, TRUE)) {
      $tags[] = 'collectible';
    }
    $entry['name'] = trim((string) ($row['name'] ?? $schema_data['name'] ?? $definition_id));
    if ($item_type !== '') {
      $entry['type'] = $item_type;
    }
    if (trim((string) ($schema_data['description'] ?? '')) !== '') {
      $entry['description'] = trim((string) $schema_data['description']);
    }
    if ($quest_association !== '') {
      $entry['quest_association'] = $quest_association;
    }
    if ($tags !== []) {
      $entry['tags'] = array_values(array_unique(array_filter(array_map('strval', $tags))));
    }
    return $entry;
  }

  /**
   * Hydrate projected actor placements with canonical room quests.
   */
  private function hydrateProjectedActorPlacement(array $entry, string $definition_id): array {
    if (!empty($entry['quests']) && is_array($entry['quests'])) {
      return $entry;
    }
    $default_quests = match ($definition_id) {
      'tavern_keeper' => [
        ['quest_id' => 'gather_wine', 'title' => 'Collect Wine Bottles', 'description' => 'Eldric needs wine bottles for the evening service'],
        ['quest_id' => 'gather_torch_components', 'title' => 'Gather Torch Materials', 'description' => 'Help brighten the tavern by collecting torch components'],
        ['quest_id' => 'tavern_storyline_leads', 'title' => 'Ask About Storyline Leads', 'description' => 'Get Eldric\'s latest actionable leads and choose which thread to pursue.'],
      ],
      'scholar_npc' => [
        ['quest_id' => 'collect_spellbooks', 'title' => 'Recover Lost Spellbook', 'description' => 'Marta seeks a missing spellbook in the tavern'],
        ['quest_id' => 'stabilize_arcane_resonance', 'title' => 'Stabilize Arcane Resonance', 'description' => 'Help Marta collect resonance samples and stabilize the codex\'s ambient magical feedback.'],
      ],
      'gribbles_rindsworth' => [
        ['quest_id' => 'follow_the_whispers', 'title' => 'Follow the Whispers', 'description' => 'Track suspicious tavern rumors to identify who is moving contraband arcana through town.'],
        ['quest_id' => 'recover_blackmail_ledger', 'title' => 'Recover Blackmail Ledger', 'description' => 'Retrieve Gribbles\' stolen ledger before rival brokers weaponize its secrets.'],
      ],
      default => [],
    };
    if ($default_quests !== []) {
      $entry['quests'] = $default_quests;
    }
    return $entry;
  }

  private function encode(mixed $value): string {
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
  }

  /**
   * Preserve object-shaped contract fields when their PHP arrays are empty.
   */
  private function encodeRoom(array $room): string {
    return $this->encode($this->canonicalizeRoomJsonObjects($room));
  }

  private function canonicalizeRoomJsonObjects(array $room): array {
    if (isset($room['metadata']['provenance']) && is_array($room['metadata']['provenance'])) {
      $room['metadata']['provenance'] = (object) $room['metadata']['provenance'];
    }
    foreach ((array) ($room['lighting']['light_sources'] ?? []) as $index => $item) {
      if (is_array($item)) {
        $room['lighting']['light_sources'][$index] = (object) $item;
      }
    }
    foreach ((array) ($room['environmental_effects'] ?? []) as $index => $item) {
      if (is_array($item)) {
        $room['environmental_effects'][$index] = (object) $item;
      }
    }
    foreach ((array) ($room['hexes'] ?? []) as $index => $hex) {
      if (isset($hex['metadata']) && is_array($hex['metadata'])) {
        $room['hexes'][$index]['metadata'] = (object) $hex['metadata'];
      }
    }
    foreach ((array) ($room['placements'] ?? []) as $index => $placement) {
      foreach (['state_defaults', 'overrides'] as $field) {
        if (isset($placement[$field]) && is_array($placement[$field])) {
          $room['placements'][$index][$field] = (object) $placement[$field];
        }
      }
    }
    foreach ((array) ($room['exit_ports'] ?? []) as $port_index => $port) {
      foreach ((array) ($port['requirements'] ?? []) as $requirement_index => $requirement) {
        if (is_array($requirement)) {
          $room['exit_ports'][$port_index]['requirements'][$requirement_index] = (object) $requirement;
        }
      }
    }
    return $room;
  }

}
