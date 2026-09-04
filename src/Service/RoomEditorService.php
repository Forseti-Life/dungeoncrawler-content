<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Component\Uuid\UuidInterface;

/**
 * Canonical room authoring authority for drafts, commands, and publication.
 */
class RoomEditorService {

  private const FAMILIES = ['creature', 'actor', 'item', 'obstacle', 'trap', 'hazard'];

  private const TERRAIN_TYPES = [
    'stone_floor', 'rough_stone', 'smooth_stone', 'dirt', 'mud', 'sand',
    'water_shallow', 'water_deep', 'ice', 'lava', 'fungal_growth', 'bone',
    'crystal', 'metal_grate', 'wooden_floor', 'carpet', 'rubble', 'void',
  ];

  private const LIGHTING_LEVELS = ['bright_light', 'dim_light', 'darkness', 'magical_darkness'];

  private const ROOM_TYPES = [
    'corridor', 'chamber', 'cavern', 'hall', 'shrine', 'vault', 'lair', 'nest',
    'workshop', 'library', 'prison', 'throne_room', 'armory', 'pantry', 'garden',
    'pool', 'mine', 'crypt', 'laboratory', 'barracks', 'marketplace', 'arena',
    'boss_chamber', 'entrance', 'exit', 'stairwell', 'crossroads', 'dead_end',
    'trap_room', 'puzzle_room', 'vault_room', 'safe_room',
  ];

  private const SIZE_CATEGORIES = ['tiny', 'small', 'medium', 'large', 'huge', 'gargantuan'];

  public function __construct(
    protected Connection $database,
    protected AccountProxyInterface $currentUser,
    protected UuidInterface $uuid,
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
   * Creates an active draft from a canonical room or a blank room.
   */
  public function createDraft(?string $room_id = NULL): array {
    $uid = (int) $this->currentUser->id();
    $room_id = $room_id !== NULL ? trim($room_id) : NULL;
    if ($room_id !== NULL && !preg_match('/^[a-z0-9][a-z0-9_-]{0,99}$/', $room_id)) {
      throw new \InvalidArgumentException('room_id_invalid');
    }

    if ($room_id !== NULL) {
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
    if ($room_id === NULL) {
      $room = $this->blankRoom();
    }
    else {
      $row = $this->database->select('dungeoncrawler_content_rooms', 'r')
        ->fields('r')
        ->condition('room_id', $room_id)
        ->execute()
        ->fetchAssoc();
      if (!$row) {
        throw new \OutOfBoundsException('room_not_found');
      }
      $base_version_id = $row['published_version_id'] ?? NULL;
      $room = $this->loadPublishedPayload($base_version_id) ?? $this->normalizeLegacyRoom($row);
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
      $findings = $this->validateAggregate($after);
      if ($findings['errors']) {
        throw new \DomainException($findings['errors'][0]['code']);
      }
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
    $result = $this->validateAggregate($draft['room'], $profile);
    return [
      'valid' => $result['errors'] === [],
      'profile' => $profile,
      'draft_id' => $draft_id,
      'revision' => $draft['revision'],
      'errors' => $result['errors'],
      'warnings' => $result['warnings'],
      'validated_at' => gmdate(DATE_RFC3339),
    ];
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
    if ($draft['status'] !== 'active') {
      throw new \DomainException('draft_not_active');
    }
    $expected_base = $request['expected_base_version_id'] ?? NULL;
    if ($expected_base !== $draft['base_version_id']) {
      throw new \RuntimeException('base_version_conflict');
    }
    $validation = $this->validateDraft($draft_id, 'publication');
    if (!$validation['valid']) {
      throw new \DomainException('publication_validation_failed');
    }

    $room = $draft['room'];
    $this->assertStarterFixedDataContract($room);
    $room_id = (string) $room['room_id'];
    if ($room_id === '') {
      throw new \DomainException('room_id_required');
    }
    $encoded = $this->encodeRoom($room);
    $hash = hash('sha256', $encoded);
    $identical_version = $this->database->select('dungeoncrawler_content_room_versions', 'v')
      ->fields('v', ['version_id'])
      ->condition('room_id', $room_id)
      ->condition('payload_hash', $hash)
      ->execute()
      ->fetchField();
    if ($identical_version) {
      throw new \DomainException('payload_already_published');
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
          'catalog_version' => $this->catalogVersion(),
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
   * Returns a paginated, normalized placeable catalog.
   */
  public function catalog(?string $family, string $search, int $limit, int $offset): array {
    if ($family !== NULL && !in_array($family, self::FAMILIES, TRUE)) {
      throw new \InvalidArgumentException('catalog_family_invalid');
    }
    $limit = max(1, min(250, $limit));
    $offset = max(0, $offset);
    $families = $family ? [$family] : self::FAMILIES;
    $definitions = [];

    foreach ($families as $current_family) {
      if ($current_family === 'actor') {
        $query = $this->database->select('dc_canonical_actors', 'a')
          ->fields('a', ['actor_id', 'version', 'display_name', 'actor_type', 'state_data']);
        if ($search !== '') {
          $or = $query->orConditionGroup()
            ->condition('display_name', '%' . $this->database->escapeLike($search) . '%', 'LIKE')
            ->condition('actor_id', '%' . $this->database->escapeLike($search) . '%', 'LIKE');
          $query->condition($or);
        }
        foreach ($query->execute()->fetchAll() as $row) {
          $data = json_decode((string) $row->state_data, TRUE) ?: [];
          $definitions[] = $this->normalizeDefinition(
            'actor',
            $row->actor_id,
            $row->version,
            $row->display_name ?: ($data['name'] ?? $row->actor_id),
            $row->actor_type ?: 'npc',
            $data,
            'dc_canonical_actors'
          );
        }
        continue;
      }

      $registry_type = $current_family === 'obstacle' ? 'obstacle_object' : $current_family;
      $query = $this->database->select('dungeoncrawler_content_registry', 'r')
        ->fields('r', ['content_id', 'name', 'version', 'schema_data']);
      $query->condition('content_type', $registry_type);
      if ($search !== '') {
        $or = $query->orConditionGroup()
          ->condition('name', '%' . $this->database->escapeLike($search) . '%', 'LIKE')
          ->condition('content_id', '%' . $this->database->escapeLike($search) . '%', 'LIKE');
        $query->condition($or);
      }
      foreach ($query->execute()->fetchAll() as $row) {
        $data = json_decode((string) $row->schema_data, TRUE) ?: [];
        $category = $data['category'] ?? $data[$current_family . '_type'] ?? $data['type'] ?? $current_family;
        $definitions[] = $this->normalizeDefinition(
          $current_family,
          $row->content_id,
          $row->version ?: ($data['schema_version'] ?? '1.0.0'),
          $row->name,
          (string) $category,
          $data,
          'dungeoncrawler_content_registry'
        );
      }
    }

    usort($definitions, static fn(array $a, array $b): int =>
      [$a['family'], strtolower($a['label']), $a['definition_id']]
      <=> [$b['family'], strtolower($b['label']), $b['definition_id']]
    );
    $total = count($definitions);
    return [
      'definitions' => array_slice($definitions, $offset, $limit),
      'total' => $total,
      'limit' => $limit,
      'offset' => $offset,
      'catalog_version' => $this->catalogVersion(),
      'families' => self::FAMILIES,
    ];
  }

  /**
   * Returns one fully-normalized catalog definition (for inspector lookups).
   *
   * Unlike catalog(), which pages through the whole family list, this fetches
   * a single row directly by id so the Room Editor inspector can enrich a
   * placement with its canonical name/description/tags even when that
   * definition isn't part of the currently-loaded catalog page.
   */
  public function catalogEntry(string $family, string $definition_id): ?array {
    if (!in_array($family, self::FAMILIES, TRUE)) {
      throw new \InvalidArgumentException('catalog_family_invalid');
    }
    $definition_id = trim($definition_id);
    if ($definition_id === '') {
      return NULL;
    }

    if ($family === 'actor') {
      $row = $this->database->select('dc_canonical_actors', 'a')
        ->fields('a', ['actor_id', 'version', 'display_name', 'actor_type', 'state_data'])
        ->condition('actor_id', $definition_id)
        ->execute()
        ->fetchAssoc();
      if (!$row) {
        return NULL;
      }
      $data = json_decode((string) $row['state_data'], TRUE) ?: [];
      return $this->normalizeDefinition(
        'actor',
        $row['actor_id'],
        $row['version'],
        $row['display_name'] ?: ($data['name'] ?? $row['actor_id']),
        $row['actor_type'] ?: 'npc',
        $data,
        'dc_canonical_actors'
      );
    }

    $registry_type = $family === 'obstacle' ? 'obstacle_object' : $family;
    $row = $this->database->select('dungeoncrawler_content_registry', 'r')
      ->fields('r', ['content_id', 'name', 'version', 'schema_data'])
      ->condition('content_type', $registry_type)
      ->condition('content_id', $definition_id)
      ->execute()
      ->fetchAssoc();
    if (!$row) {
      return NULL;
    }
    $data = json_decode((string) $row['schema_data'], TRUE) ?: [];
    $category = $data['category'] ?? $data[$family . '_type'] ?? $data['type'] ?? $family;
    return $this->normalizeDefinition(
      $family,
      $row['content_id'],
      $row['version'] ?: ($data['schema_version'] ?? '1.0.0'),
      $row['name'],
      (string) $category,
      $data,
      'dungeoncrawler_content_registry'
    );
  }

  /**
   * Loads the raw editable record backing one catalog definition.
   *
   * Used by the canonical library edit form - returns the full schema_data
   * payload (not the trimmed placeable-object-v1 projection normalizeDefinition
   * produces) since editors need access to every attribute, not just the
   * subset relevant to room placement.
   */
  public function loadCanonicalEntry(string $family, string $definition_id): array {
    if (!in_array($family, self::FAMILIES, TRUE)) {
      throw new \InvalidArgumentException('catalog_family_invalid');
    }
    $definition_id = trim($definition_id);
    if ($definition_id === '') {
      throw new \InvalidArgumentException('definition_id_required');
    }

    if ($family === 'actor') {
      $row = $this->database->select('dc_canonical_actors', 'a')
        ->fields('a', ['actor_id', 'version', 'display_name', 'actor_type', 'state_data'])
        ->condition('actor_id', $definition_id)
        ->execute()
        ->fetchAssoc();
      if (!$row) {
        throw new \OutOfBoundsException('canonical_entry_not_found');
      }
      return [
        'family' => 'actor',
        'definition_id' => $row['actor_id'],
        'name' => (string) $row['display_name'],
        'category' => (string) $row['actor_type'],
        'version' => (string) $row['version'],
        'schema_data' => json_decode((string) $row['state_data'], TRUE) ?: [],
        'source_table' => 'dc_canonical_actors',
      ];
    }

    $registry_type = $family === 'obstacle' ? 'obstacle_object' : $family;
    $row = $this->database->select('dungeoncrawler_content_registry', 'r')
      ->fields('r', ['content_id', 'name', 'content_type', 'version', 'schema_data'])
      ->condition('content_type', $registry_type)
      ->condition('content_id', $definition_id)
      ->execute()
      ->fetchAssoc();
    if (!$row) {
      throw new \OutOfBoundsException('canonical_entry_not_found');
    }
    $data = json_decode((string) $row['schema_data'], TRUE) ?: [];
    return [
      'family' => $family,
      'definition_id' => $row['content_id'],
      'name' => (string) $row['name'],
      'category' => (string) ($data['category'] ?? $data[$family . '_type'] ?? $data['type'] ?? $family),
      'version' => (string) $row['version'],
      'schema_data' => $data,
      'source_table' => 'dungeoncrawler_content_registry',
    ];
  }

  /**
   * Persists edited name/attributes back to the canonical source row.
   */
  public function saveCanonicalEntry(string $family, string $definition_id, string $name, array $schema_data): void {
    if (!in_array($family, self::FAMILIES, TRUE)) {
      throw new \InvalidArgumentException('catalog_family_invalid');
    }
    $definition_id = trim($definition_id);
    $name = trim($name);
    if ($definition_id === '') {
      throw new \InvalidArgumentException('definition_id_required');
    }
    if ($name === '') {
      throw new \InvalidArgumentException('name_required');
    }
    $now = time();

    if ($family === 'actor') {
      $updated = $this->database->update('dc_canonical_actors')
        ->fields([
          'display_name' => $name,
          'state_data' => $this->encode($schema_data),
          'updated_at' => $now,
        ])
        ->condition('actor_id', $definition_id)
        ->execute();
      if (!$updated) {
        throw new \OutOfBoundsException('canonical_entry_not_found');
      }
      return;
    }

    $registry_type = $family === 'obstacle' ? 'obstacle_object' : $family;
    $updated = $this->database->update('dungeoncrawler_content_registry')
      ->fields([
        'name' => $name,
        'schema_data' => $this->encode($schema_data),
        'updated' => $now,
      ])
      ->condition('content_type', $registry_type)
      ->condition('content_id', $definition_id)
      ->execute();
    if (!$updated) {
      throw new \OutOfBoundsException('canonical_entry_not_found');
    }
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

      case 'set_hex_terrain':
      case 'set_hex_elevation':
        $q = (int) ($payload['hex']['q'] ?? PHP_INT_MAX);
        $r = (int) ($payload['hex']['r'] ?? PHP_INT_MAX);
        $index = $this->findHexIndex($room, $q, $r);
        if ($index === NULL) {
          throw new \DomainException('hex_not_found');
        }
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
        if (!is_array($definition) || !in_array($definition['family'] ?? NULL, self::FAMILIES, TRUE)) {
          throw new \InvalidArgumentException('definition_ref_invalid');
        }
        if (!$this->definitionExists(
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
    if (count($hexes) < 1 || count($hexes) > 10000) {
      $errors[] = $this->finding('room_hex_count_invalid', '/hexes', 'Rooms require 1 through 10000 hexes.');
    }
    if (count((array) ($room['placements'] ?? [])) > 250) {
      $errors[] = $this->finding('room_placement_count_invalid', '/placements', 'Rooms support at most 250 placements.');
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
      if (!in_array($placement['definition_ref']['family'] ?? NULL, self::FAMILIES, TRUE)) {
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
      if (in_array($family, ['actor', 'creature', 'obstacle'], TRUE)) {
        if (isset($solid_occupancy[$key])) {
          $errors[] = $this->finding('placement_collision', "/placements/{$index}/anchor_hex", 'Solid placements cannot share a room hex.');
        }
        $solid_occupancy[$key] = TRUE;
      }
      if ($profile === 'publication' && !$this->definitionExists(
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
    $defaults = array_filter((array) ($room['entry_ports'] ?? []), static fn(array $port): bool => !empty($port['is_default']));
    if ($profile === 'publication' && count($defaults) !== 1) {
      $errors[] = $this->finding('default_entry_required', '/entry_ports', 'Published rooms require exactly one default entry.');
    }
    if ($profile === 'publication' && ($room['placements'] ?? []) === []) {
      $warnings[] = $this->finding('room_has_no_placements', '/placements', 'Room has no placeable objects.', 'warning');
    }
    return ['errors' => $errors, 'warnings' => $warnings];
  }

  private function finding(string $code, string $path, string $message, string $severity = 'error'): array {
    return [
      'code' => $code,
      'message' => $message,
      'path' => $path,
      'severity' => $severity,
      'context' => [],
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
      'name' => 'Untitled Room',
      'description' => 'A new canonical room.',
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
        $room = $this->mutate($room, $type, $payload, $draft_id);
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
    $findings = $blocked === [] ? $this->validateAggregate($room, $profile) : ['errors' => [], 'warnings' => []];

    return [
      'draft_id' => $draft_id,
      'base_revision' => (int) $draft['revision'],
      'projected_revision' => (int) $draft['revision'] + count(array_filter($steps, static fn(array $s): bool => $s['applies'])),
      'applies_cleanly' => $blocked === [],
      'steps' => $steps,
      'projected_room' => $room,
      'validation' => [
        'valid' => $blocked === [] && $findings['errors'] === [],
        'profile' => $profile,
        'errors' => $findings['errors'],
        'warnings' => $findings['warnings'],
      ],
    ];
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
    if (!in_array($family, ['actor', 'creature', 'obstacle'], TRUE)) {
      return;
    }
    foreach ((array) ($room['placements'] ?? []) as $placement) {
      if ($ignored_instance_id !== NULL && ($placement['instance_id'] ?? NULL) === $ignored_instance_id) {
        continue;
      }
      $placed_family = $placement['definition_ref']['family'] ?? '';
      if (($placement['anchor_hex']['q'] ?? NULL) === $q
        && ($placement['anchor_hex']['r'] ?? NULL) === $r
        && in_array($placed_family, ['actor', 'creature', 'obstacle'], TRUE)) {
        throw new \DomainException('placement_collision');
      }
    }
  }

  private function normalizeDefinition(string $family, string $id, string $version, string $label, string $category, array $data, string $source_table): array {
    $visual = is_array($data['visual'] ?? NULL) ? $data['visual'] : [];
    return [
      'schema_version' => 'placeable-object-v1',
      'definition_id' => $id,
      'definition_version' => $this->normalizeSemanticVersion($version),
      'family' => $family,
      'label' => $label,
      'category' => $category,
      'description' => (string) ($data['description'] ?? ''),
      'tags' => array_values(array_filter((array) ($data['tags'] ?? $data['traits'] ?? []), 'is_string')),
      'footprint' => [['q' => 0, 'r' => 0]],
      'visual' => [
        'sprite_id' => (string) ($visual['sprite_id'] ?? 'generic_' . $family),
        'color' => (string) ($visual['color'] ?? ''),
      ],
      'placement_constraints' => [
        'occupancy' => in_array($family, ['item', 'trap', 'hazard'], TRUE) ? 'overlay' : 'solid',
        'stackable' => (bool) ($data['stackable'] ?? $family === 'item'),
      ],
      'allowed_instance_overrides' => ['facing', 'elevation_ft', 'hidden'],
      'source_authority' => [
        'provider' => $family === 'actor' ? 'canonical_actor' : 'content_registry',
        'source_table' => $source_table,
        'source_id' => $id,
        'source_hash' => hash('sha256', $this->encode($data)),
      ],
    ];
  }

  private function definitionExists(string $family, string $definition_id, string $version): bool {
    if ($definition_id === '' || $version === '') {
      return FALSE;
    }
    if ($family === 'actor') {
      $versions = $this->database->select('dc_canonical_actors', 'a')
        ->fields('a', ['version'])
        ->condition('actor_id', $definition_id)
        ->execute()
        ->fetchCol();
      return in_array($this->normalizeSemanticVersion($version), array_map([$this, 'normalizeSemanticVersion'], $versions), TRUE);
    }
    $registry_type = $family === 'obstacle' ? 'obstacle_object' : $family;
    $versions = $this->database->select('dungeoncrawler_content_registry', 'r')
      ->fields('r', ['version'])
      ->condition('content_type', $registry_type)
      ->condition('content_id', $definition_id)
      ->execute()
      ->fetchCol();
    return in_array($this->normalizeSemanticVersion($version), array_map([$this, 'normalizeSemanticVersion'], $versions), TRUE);
  }

  private function normalizeSemanticVersion(mixed $version): string {
    $version = trim((string) $version);
    if (preg_match('/^\d+\.\d+\.\d+$/', $version)) {
      return $version;
    }
    if (preg_match('/^(\d+)\.(\d+)$/', $version, $matches)) {
      return $matches[1] . '.' . $matches[2] . '.0';
    }
    return '1.0.0';
  }

  private function isUuid(string $value): bool {
    return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value);
  }

  private function catalogVersion(): string {
    $registry_query = $this->database->select('dungeoncrawler_content_registry', 'r');
    $registry_query->addExpression('MAX(updated)', 'max_updated');
    $registry_max = (string) ($registry_query->execute()->fetchField() ?: '0');
    $actor_query = $this->database->select('dc_canonical_actors', 'a');
    $actor_query->addExpression('MAX(updated_at)', 'max_updated');
    $actor_max = (string) ($actor_query->execute()->fetchField() ?: '0');
    return hash('sha256', $registry_max . '|' . $actor_max);
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
    $layout = [
      'hexes' => $room['hexes'],
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
      $bucket = match ($family) {
        'actor' => 'npcs',
        'creature' => 'creatures',
        'item' => 'items',
        'obstacle' => 'obstacles',
        'trap' => 'traps',
        'hazard' => 'hazards',
      };
      $contents[$bucket][] = [
        'instance_id' => $placement['instance_id'],
        'content_id' => $placement['definition_ref']['definition_id'],
        'version' => $placement['definition_ref']['version'],
        'position' => $placement['anchor_hex'],
        'orientation' => $placement['facing'],
        'elevation_ft' => $placement['elevation_ft'],
        'state_defaults' => $placement['state_defaults'],
        'overrides' => $placement['overrides'],
      ];
    }
    return [$layout, $contents];
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
