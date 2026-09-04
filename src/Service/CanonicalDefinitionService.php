<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\Definition\DefinitionSchemaValidator;
use Drupal\dungeoncrawler_content\Service\Definition\DefinitionValidationException;

/**
 * The single authority for canonical placeable object definitions.
 *
 * Specification:
 * copilot-hq 20260904-dc-canonical-dungeon-editor-architecture/
 *   19-definition-editor-spec.md
 *
 * Extracted from RoomEditorService so that the Room Editor, the Dungeon
 * Editor, the definition editors and the editor GM harness all read and write
 * definitions through one service rather than reaching into two storage
 * tables from four places.
 *
 * The old RoomEditorService methods were removed, not wrapped: a delegating
 * shim would leave two entry points and the drift it is meant to prevent.
 *
 * Storage is deliberately not uniform, and this service is the only place that
 * knows it:
 *  - creature, item, trap, hazard -> dungeoncrawler_content_registry
 *  - obstacle                     -> the same table, but content_type is
 *                                    'obstacle_object', not 'obstacle'
 *  - actor                        -> dc_canonical_actors
 */
class CanonicalDefinitionService {

  /**
   * The canonical definition families.
   */
  public const FAMILIES = ['creature', 'actor', 'item', 'obstacle', 'trap', 'hazard'];

  /**
   * Families stored in dc_canonical_actors rather than the content registry.
   */
  private const ACTOR_FAMILY = 'actor';

  /**
   * Family to schema file, relative to config/schemas.
   *
   * Every family has a schema. A family without one cannot be edited or
   * written by anything, and that is enforced, not defaulted.
   */
  public const SCHEMA_FILES = [
    'creature' => 'creature.schema.json',
    'actor' => 'canonical_actor.schema.json',
    'item' => 'item.schema.json',
    'obstacle' => 'obstacle.schema.json',
    'trap' => 'trap.schema.json',
    'hazard' => 'hazard.schema.json',
  ];

  /**
   * Payload property that carries the definition identity per family.
   */
  public const ID_PROPERTIES = [
    'creature' => 'creature_id',
    'actor' => 'actor_id',
    'item' => 'item_id',
    'obstacle' => 'obstacle_id',
    'trap' => 'trap_id',
    'hazard' => 'hazard_id',
  ];

  /**
   * Payload property that carries the display name per family.
   */
  public const NAME_PROPERTIES = [
    'creature' => 'name',
    'actor' => 'display_name',
    'item' => 'name',
    'obstacle' => 'name',
    'trap' => 'name',
    'hazard' => 'name',
  ];

  /**
   * Loaded schemas, keyed by family.
   *
   * @var array<string, array>
   */
  private array $schemas = [];

  public function __construct(
    protected Connection $database,
    protected DefinitionSchemaValidator $validator,
    protected ?string $schemaDirectory = NULL,
  ) {
    $this->schemaDirectory ??= dirname(__DIR__, 2) . '/config/schemas';
  }

  /**
   * Returns the family JSON Schema.
   *
   * @throws \RuntimeException
   *   `definition_schema_missing:<family>` when the file is absent or invalid.
   */
  public function schemaForFamily(string $family): array {
    $this->assertFamily($family);
    if (!isset($this->schemas[$family])) {
      $path = $this->schemaDirectory . '/' . self::SCHEMA_FILES[$family];
      $decoded = is_file($path) ? json_decode((string) file_get_contents($path), TRUE) : NULL;
      if (!is_array($decoded)) {
        throw new \RuntimeException('definition_schema_missing:' . $family);
      }
      $this->schemas[$family] = $decoded;
    }

    return $this->schemas[$family];
  }

  /**
   * Payload property holding the identity for a family.
   */
  public function idProperty(string $family): string {
    $this->assertFamily($family);

    return self::ID_PROPERTIES[$family];
  }

  /**
   * Payload property holding the display name for a family.
   */
  public function nameProperty(string $family): string {
    $this->assertFamily($family);

    return self::NAME_PROPERTIES[$family];
  }

  /**
   * The schema-shaped payload for one stored definition.
   *
   * For registry families this is `schema_data`. For actors the schema
   * governs the whole row, so the row is assembled into the payload.
   */
  public function definitionPayload(string $family, string $definition_id): array {
    $entry = $this->loadCanonicalEntry($family, $definition_id);
    if ($family !== self::ACTOR_FAMILY) {
      return $entry['schema_data'];
    }

    return [
      'actor_id' => $entry['definition_id'],
      'version' => $entry['version'],
      'actor_type' => $entry['category'],
      'display_name' => $entry['name'],
      'state_data' => $entry['schema_data'],
    ];
  }

  /**
   * Validates a payload against its family schema.
   *
   * @return array<int, array{code: string, pointer: string, schema_pointer: string, message: string}>
   *   Per-pointer findings. Empty means valid.
   */
  public function validateDefinition(string $family, array $payload): array {
    return $this->validator->validate($this->schemaForFamily($family), $payload);
  }

  /**
   * Published room versions whose placements pin this definition.
   *
   * @return array<int, array{room_id: string, version_id: string, version: string, pinned_definition_version: string, placement_count: int}>
   */
  public function publishedRoomsReferencing(string $family, string $definition_id): array {
    $this->assertFamily($family);
    $definition_id = trim($definition_id);
    if ($definition_id === '') {
      throw new \InvalidArgumentException('definition_id_required');
    }
    $rows = $this->database->select('dungeoncrawler_content_room_versions', 'v')
      ->fields('v', ['room_id', 'version_id', 'version', 'room_payload'])
      ->condition('room_payload', '%' . $this->database->escapeLike('"definition_id":"' . $definition_id . '"') . '%', 'LIKE')
      ->orderBy('room_id')
      ->orderBy('version')
      ->execute();

    $affected = [];
    foreach ($rows as $row) {
      $payload = json_decode((string) $row->room_payload, TRUE);
      $pinned = [];
      foreach ((array) ($payload['placements'] ?? []) as $placement) {
        $ref = $placement['definition_ref'] ?? [];
        if (($ref['family'] ?? NULL) === $family && ($ref['definition_id'] ?? NULL) === $definition_id) {
          $pinned[] = (string) ($ref['version'] ?? '');
        }
      }
      if ($pinned === []) {
        continue;
      }
      $affected[] = [
        'room_id' => (string) $row->room_id,
        'version_id' => (string) $row->version_id,
        'version' => (string) $row->version,
        'pinned_definition_version' => implode(',', array_unique($pinned)),
        'placement_count' => count($pinned),
      ];
    }

    return $affected;
  }

  /**
   * Validates and writes a definition.
   *
   * Rules (19-definition-editor-spec.md "Versioning"):
   *  - the payload must validate against the family schema;
   *  - identity is immutable: the payload id property must equal
   *    $definition_id for an existing row; a NULL $definition_id is an
   *    explicit create that takes its id from the payload and fails if the
   *    id already exists;
   *  - $expected_version, when given, must match the stored version or the
   *    save is rejected as a concurrent edit;
   *  - a definition referenced by any published room version has its patch
   *    version incremented; the rooms keep their pinned version and are
   *    reported back so the author sees the blast radius.
   *
   * @return array{family: string, definition_id: string, created: bool, previous_version: ?string, version: string, affected_rooms: array}
   *
   * @throws DefinitionValidationException
   * @throws \InvalidArgumentException
   *   `definition_id_required`, `definition_id_mismatch`, `definition_exists`
   * @throws \OutOfBoundsException
   *   `definition_not_found`
   * @throws \RuntimeException
   *   `definition_version_conflict`
   */
  public function saveDefinition(string $family, ?string $definition_id, array $payload, ?string $expected_version = NULL): array {
    $this->assertFamily($family);
    $findings = $this->validateDefinition($family, $payload);
    if ($findings !== []) {
      throw new DefinitionValidationException($findings);
    }

    $id_property = $this->idProperty($family);
    $payload_id = trim((string) ($payload[$id_property] ?? ''));
    if ($payload_id === '') {
      throw new \InvalidArgumentException('definition_id_required');
    }
    $create_request = $definition_id === NULL;
    $definition_id = $create_request ? $payload_id : trim($definition_id);
    if ($definition_id !== $payload_id) {
      throw new \InvalidArgumentException('definition_id_mismatch');
    }
    $name = trim((string) ($payload[$this->nameProperty($family)] ?? ''));
    if ($name === '') {
      throw new \InvalidArgumentException('name_required');
    }

    $current = $this->currentVersion($family, $definition_id);
    $creating = $current === NULL;
    // A NULL $definition_id is an explicit create; it never silently updates.
    if ($create_request && !$creating) {
      throw new \InvalidArgumentException('definition_exists');
    }
    if (!$create_request && $creating) {
      throw new \OutOfBoundsException('definition_not_found');
    }
    if ($expected_version !== NULL) {
      if ($this->normalizeSemanticVersion($expected_version) !== $this->normalizeSemanticVersion($current)) {
        throw new \RuntimeException('definition_version_conflict');
      }
    }

    $affected_rooms = $creating ? [] : $this->publishedRoomsReferencing($family, $definition_id);
    if ($creating) {
      $version = $family === self::ACTOR_FAMILY
        ? $this->normalizeSemanticVersion($payload['version'] ?? '')
        : '1.0.0';
    }
    elseif ($affected_rooms !== []) {
      $version = $this->incrementPatch($this->normalizeSemanticVersion($current));
    }
    else {
      $version = $this->normalizeSemanticVersion($current);
    }

    $now = time();
    if ($family === self::ACTOR_FAMILY) {
      // The bumped version is written back into the payload so the stored
      // row and the schema-shaped payload cannot disagree.
      $payload['version'] = $version;
      $fields = [
        'version' => $version,
        'actor_type' => (string) $payload['actor_type'],
        'display_name' => $name,
        'state_data' => $this->encode($payload['state_data']),
        'updated_at' => $now,
      ];
      if ($creating) {
        $this->database->insert('dc_canonical_actors')
          ->fields($fields + ['actor_id' => $definition_id, 'created_at' => $now])
          ->execute();
      }
      else {
        $this->database->update('dc_canonical_actors')
          ->fields($fields)
          ->condition('actor_id', $definition_id)
          ->execute();
      }
    }
    else {
      // The registry keeps denormalized level/rarity/tags columns for the
      // catalog controllers; they are derived from the payload, never edited.
      $tags = $payload['tags'] ?? $payload['traits'] ?? NULL;
      $fields = [
        'name' => $name,
        'version' => $version,
        'level' => isset($payload['level']) && is_int($payload['level']) ? $payload['level'] : NULL,
        'rarity' => isset($payload['rarity']) && is_string($payload['rarity']) ? $payload['rarity'] : NULL,
        'tags' => is_array($tags) ? $this->encode(array_values($tags)) : NULL,
        'schema_data' => $this->encode($payload),
        'updated' => $now,
      ];
      if ($creating) {
        $this->database->insert('dungeoncrawler_content_registry')
          ->fields($fields + [
            'content_type' => $this->registryContentType($family),
            'content_id' => $definition_id,
            'source_file' => 'definition_editor',
            'created' => $now,
          ])
          ->execute();
      }
      else {
        $this->database->update('dungeoncrawler_content_registry')
          ->fields($fields)
          ->condition('content_type', $this->registryContentType($family))
          ->condition('content_id', $definition_id)
          ->execute();
      }
    }

    return [
      'family' => $family,
      'definition_id' => $definition_id,
      'created' => $creating,
      'previous_version' => $current,
      'version' => $version,
      'affected_rooms' => $affected_rooms,
    ];
  }

  /**
   * Stored version string for a definition, or NULL when absent.
   */
  public function currentVersion(string $family, string $definition_id): ?string {
    $this->assertFamily($family);
    if ($family === self::ACTOR_FAMILY) {
      $version = $this->database->select('dc_canonical_actors', 'a')
        ->fields('a', ['version'])
        ->condition('actor_id', $definition_id)
        ->execute()
        ->fetchField();
    }
    else {
      $version = $this->database->select('dungeoncrawler_content_registry', 'r')
        ->fields('r', ['version'])
        ->condition('content_type', $this->registryContentType($family))
        ->condition('content_id', $definition_id)
        ->execute()
        ->fetchField();
    }

    return $version === FALSE ? NULL : (string) $version;
  }

  /**
   * Increments the patch component of a semantic version.
   */
  public function incrementPatch(string $version): string {
    if (!preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $version, $m)) {
      throw new \InvalidArgumentException('definition_version_invalid');
    }

    return $m[1] . '.' . $m[2] . '.' . ((int) $m[3] + 1);
  }

  /**
   * Returns the supported family list.
   */
  public function families(): array {
    return self::FAMILIES;
  }

  /**
   * Maps a family to its registry content_type.
   *
   * 'obstacle' is stored as 'obstacle_object'. This mapping is load bearing
   * and is the reason this lives in one place.
   */
  public function registryContentType(string $family): string {
    $this->assertFamily($family);

    return $family === 'obstacle' ? 'obstacle_object' : $family;
  }

  /**
   * Returns the storage table backing a family.
   */
  public function sourceTable(string $family): string {
    $this->assertFamily($family);

    return $family === self::ACTOR_FAMILY
      ? 'dc_canonical_actors'
      : 'dungeoncrawler_content_registry';
  }

  /**
   * Returns a paginated, normalized placeable catalog.
   */
  public function catalog(?string $family, string $search, int $limit, int $offset): array {
    if ($family !== NULL) {
      $this->assertFamily($family);
    }
    $limit = max(1, min(250, $limit));
    $offset = max(0, $offset);
    $families = $family ? [$family] : self::FAMILIES;
    $definitions = [];

    foreach ($families as $current_family) {
      if ($current_family === self::ACTOR_FAMILY) {
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
            self::ACTOR_FAMILY,
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

      $query = $this->database->select('dungeoncrawler_content_registry', 'r')
        ->fields('r', ['content_id', 'name', 'version', 'schema_data']);
      $query->condition('content_type', $this->registryContentType($current_family));
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

    return [
      'definitions' => array_slice($definitions, $offset, $limit),
      'total' => count($definitions),
      'limit' => $limit,
      'offset' => $offset,
      'catalog_version' => $this->catalogVersion(),
      'families' => self::FAMILIES,
    ];
  }

  /**
   * Returns one fully-normalized catalog definition, or NULL if absent.
   */
  public function catalogEntry(string $family, string $definition_id): ?array {
    $this->assertFamily($family);
    $definition_id = trim($definition_id);
    if ($definition_id === '') {
      return NULL;
    }

    if ($family === self::ACTOR_FAMILY) {
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
        self::ACTOR_FAMILY,
        $row['actor_id'],
        $row['version'],
        $row['display_name'] ?: ($data['name'] ?? $row['actor_id']),
        $row['actor_type'] ?: 'npc',
        $data,
        'dc_canonical_actors'
      );
    }

    $row = $this->database->select('dungeoncrawler_content_registry', 'r')
      ->fields('r', ['content_id', 'name', 'version', 'schema_data'])
      ->condition('content_type', $this->registryContentType($family))
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
   * Returns the full payload rather than the trimmed placeable-object-v1
   * projection, because editors need every attribute.
   */
  public function loadCanonicalEntry(string $family, string $definition_id): array {
    $this->assertFamily($family);
    $definition_id = trim($definition_id);
    if ($definition_id === '') {
      throw new \InvalidArgumentException('definition_id_required');
    }

    if ($family === self::ACTOR_FAMILY) {
      $row = $this->database->select('dc_canonical_actors', 'a')
        ->fields('a', ['actor_id', 'version', 'display_name', 'actor_type', 'state_data'])
        ->condition('actor_id', $definition_id)
        ->execute()
        ->fetchAssoc();
      if (!$row) {
        throw new \OutOfBoundsException('definition_not_found');
      }

      return [
        'family' => self::ACTOR_FAMILY,
        'definition_id' => $row['actor_id'],
        'name' => (string) $row['display_name'],
        'category' => (string) $row['actor_type'],
        'version' => (string) $row['version'],
        'schema_data' => json_decode((string) $row['state_data'], TRUE) ?: [],
        'source_table' => 'dc_canonical_actors',
      ];
    }

    $row = $this->database->select('dungeoncrawler_content_registry', 'r')
      ->fields('r', ['content_id', 'name', 'content_type', 'version', 'schema_data'])
      ->condition('content_type', $this->registryContentType($family))
      ->condition('content_id', $definition_id)
      ->execute()
      ->fetchAssoc();
    if (!$row) {
      throw new \OutOfBoundsException('definition_not_found');
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
   * Whether a specific definition version exists.
   */
  public function definitionExists(string $family, string $definition_id, string $version): bool {
    if ($definition_id === '' || $version === '') {
      return FALSE;
    }
    $this->assertFamily($family);

    if ($family === self::ACTOR_FAMILY) {
      $versions = $this->database->select('dc_canonical_actors', 'a')
        ->fields('a', ['version'])
        ->condition('actor_id', $definition_id)
        ->execute()
        ->fetchCol();
    }
    else {
      $versions = $this->database->select('dungeoncrawler_content_registry', 'r')
        ->fields('r', ['version'])
        ->condition('content_type', $this->registryContentType($family))
        ->condition('content_id', $definition_id)
        ->execute()
        ->fetchCol();
    }

    return in_array(
      $this->normalizeSemanticVersion($version),
      array_map([$this, 'normalizeSemanticVersion'], $versions),
      TRUE
    );
  }

  /**
   * A hash that changes whenever any definition changes.
   */
  public function catalogVersion(): string {
    $registry_query = $this->database->select('dungeoncrawler_content_registry', 'r');
    $registry_query->addExpression('MAX(updated)', 'max_updated');
    $registry_max = (string) ($registry_query->execute()->fetchField() ?: '0');

    $actor_query = $this->database->select('dc_canonical_actors', 'a');
    $actor_query->addExpression('MAX(updated_at)', 'max_updated');
    $actor_max = (string) ($actor_query->execute()->fetchField() ?: '0');

    return hash('sha256', $registry_max . '|' . $actor_max);
  }

  /**
   * Normalizes a stored record into the placeable-object-v1 projection.
   */
  public function normalizeDefinition(string $family, string $id, string $version, string $label, string $category, array $data, string $source_table): array {
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
        'provider' => $family === self::ACTOR_FAMILY ? 'canonical_actor' : 'content_registry',
        'source_table' => $source_table,
        'source_id' => $id,
        'source_hash' => hash('sha256', $this->encode($data)),
      ],
    ];
  }

  /**
   * Coerces a version string to semantic form.
   */
  public function normalizeSemanticVersion(mixed $version): string {
    $version = trim((string) $version);
    if (preg_match('/^\d+\.\d+\.\d+$/', $version)) {
      return $version;
    }
    if (preg_match('/^(\d+)\.(\d+)$/', $version, $matches)) {
      return $matches[1] . '.' . $matches[2] . '.0';
    }

    return '1.0.0';
  }

  /**
   * Hard-fails on an unknown family. No default, no coercion.
   */
  private function assertFamily(string $family): void {
    if (!in_array($family, self::FAMILIES, TRUE)) {
      throw new \InvalidArgumentException('definition_family_unsupported');
    }
  }

  /**
   * Encodes a payload for storage.
   */
  private function encode(mixed $value): string {
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
  }

}
