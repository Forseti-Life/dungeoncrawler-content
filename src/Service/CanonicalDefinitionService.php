<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

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

  public function __construct(
    protected Connection $database,
  ) {}

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
        throw new \OutOfBoundsException('canonical_entry_not_found');
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
   * Persists edited name and attributes back to the canonical source row.
   */
  public function saveCanonicalEntry(string $family, string $definition_id, string $name, array $schema_data): void {
    $this->assertFamily($family);
    $definition_id = trim($definition_id);
    $name = trim($name);
    if ($definition_id === '') {
      throw new \InvalidArgumentException('definition_id_required');
    }
    if ($name === '') {
      throw new \InvalidArgumentException('name_required');
    }
    $now = time();

    if ($family === self::ACTOR_FAMILY) {
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

    $updated = $this->database->update('dungeoncrawler_content_registry')
      ->fields([
        'name' => $name,
        'schema_data' => $this->encode($schema_data),
        'updated' => $now,
      ])
      ->condition('content_type', $this->registryContentType($family))
      ->condition('content_id', $definition_id)
      ->execute();
    if (!$updated) {
      throw new \OutOfBoundsException('canonical_entry_not_found');
    }
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
      throw new \InvalidArgumentException('catalog_family_invalid');
    }
  }

  /**
   * Encodes a payload for storage.
   */
  private function encode(mixed $value): string {
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
  }

}
