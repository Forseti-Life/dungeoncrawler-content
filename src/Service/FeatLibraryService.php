<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Registry-backed feat library service.
 */
class FeatLibraryService {

  public const FEAT_TYPES = ['ancestry', 'class', 'general', 'skill'];

  /**
   * Cached normalized feat records keyed by canonical ID.
   *
   * @var array<string, array>|null
   */
  protected ?array $registryCatalog = NULL;

  /**
   * Optional live content registry database connection.
   */
  protected ?Connection $database = NULL;

  public function __construct(?Connection $database = NULL) {
    $this->database = $database;
  }

  /**
   * Get feats matching the provided filters.
   *
   * @param array<string, mixed> $filters
   *   Supported filters:
   *   - source_book: crb|apg|all
   *   - type: ancestry|class|general|skill
   *
   * @return array<int, array<string, mixed>>
   *   Normalized feat records.
   */
  public function getFeats(array $filters = []): array {
    $catalog = array_values($this->getRegistryCatalog());
    $source_book = (string) ($filters['source_book'] ?? 'all');
    $type = (string) ($filters['type'] ?? '');

    if ($source_book !== '' && $source_book !== 'all') {
      $catalog = array_values(array_filter($catalog, static function (array $feat) use ($source_book): bool {
        return ($feat['source_book'] ?? 'crb') === $source_book;
      }));
    }

    if ($type !== '') {
      $catalog = array_values(array_filter($catalog, static function (array $feat) use ($type): bool {
        return ($feat['type'] ?? '') === $type;
      }));
    }

    usort($catalog, static function (array $left, array $right): int {
      $level_compare = ((int) ($left['level'] ?? 0)) <=> ((int) ($right['level'] ?? 0));
      if ($level_compare !== 0) {
        return $level_compare;
      }
      return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
    });

    return $catalog;
  }

  /**
   * Get the feat catalog keyed by canonical feat ID.
   *
   * @return array<string, array<string, mixed>>
   *   Normalized feat records keyed by feat ID.
   */
  public function getFeatLookup(): array {
    return $this->getRegistryCatalog();
  }

  /**
   * Get class feats, optionally filtered by class identifier.
   *
   * @return array<int, array<string, mixed>>
   *   Matching class feats.
   */
  public function getClassFeats(?string $class_id = NULL): array {
    $feats = $this->getFeats(['type' => 'class']);
    if ($class_id === NULL || trim($class_id) === '') {
      return $feats;
    }

    $normalized_class_id = $this->normalizeFeatId($class_id);
    return array_values(array_filter($feats, function (array $feat) use ($normalized_class_id): bool {
      $candidate = $this->normalizeFeatId((string) ($feat['class_id'] ?? $feat['class'] ?? ''));
      return $candidate === $normalized_class_id;
    }));
  }

  /**
   * Get general feats.
   *
   * @return array<int, array<string, mixed>>
   *   General feat records.
   */
  public function getGeneralFeats(): array {
    return $this->getFeats(['type' => 'general']);
  }

  /**
   * Get skill feats.
   *
   * @return array<int, array<string, mixed>>
   *   Skill feat records.
   */
  public function getSkillFeats(): array {
    return $this->getFeats(['type' => 'skill']);
  }

  /**
   * Get ancestry feats, optionally filtered by ancestry name or ID.
   *
   * @return array<int, array<string, mixed>>
   *   Matching ancestry feats.
   */
  public function getAncestryFeats(?string $ancestry = NULL): array {
    $feats = $this->getFeats(['type' => 'ancestry']);
    if ($ancestry === NULL || trim($ancestry) === '') {
      return $feats;
    }

    $normalized_ancestry = $this->normalizeFeatId($ancestry);
    return array_values(array_filter($feats, function (array $feat) use ($normalized_ancestry): bool {
      $candidate = $this->normalizeFeatId((string) ($feat['ancestry_id'] ?? $feat['ancestry'] ?? ''));
      return $candidate === $normalized_ancestry;
    }));
  }

  /**
   * Get a single feat by canonical ID or name.
   */
  public function getFeat(string $feat_id): ?array {
    $catalog = $this->getRegistryCatalog();
    $requested_id = $this->normalizeFeatId($feat_id);
    $candidate_ids = array_values(array_unique(array_filter([
      trim($feat_id),
      $requested_id,
      str_replace('-', '_', $requested_id),
    ])));

    foreach ($candidate_ids as $candidate_id) {
      if (isset($catalog[$candidate_id])) {
        return $catalog[$candidate_id];
      }
    }

    foreach ($catalog as $feat) {
      if ($this->normalizeFeatId((string) ($feat['name'] ?? '')) === $requested_id) {
        return $feat;
      }
    }

    return NULL;
  }

  /**
   * Load the registry-backed feat catalog.
   *
   * @return array<string, array<string, mixed>>
   *   Feat records keyed by canonical feat ID.
   */
  protected function getRegistryCatalog(): array {
    if ($this->registryCatalog !== NULL) {
      return $this->registryCatalog;
    }
    if ($this->database === NULL) {
      throw new \RuntimeException('Feat registry database connection is unavailable.');
    }

    $catalog = [];
    foreach ($this->fetchRegistryFeatRows() as $row) {
      $record = $this->buildRegistryFeatRecord($row);
      if ($record === NULL) {
        continue;
      }
      $catalog[$record['id']] = $record;
    }

    $this->registryCatalog = $catalog;
    return $this->registryCatalog;
  }

  /**
   * Fetch raw feat rows from the content registry.
   *
   * @return array<int, object>
   *   Raw registry rows.
   */
  protected function fetchRegistryFeatRows(): array {
    return $this->database->select('dungeoncrawler_content_registry', 'r')
      ->fields('r', [
        'content_id',
        'name',
        'content_type',
        'level',
        'rarity',
        'tags',
        'schema_data',
      ])
      ->condition('content_type', 'feat')
      ->execute()
      ->fetchAll();
  }

  /**
   * Build a normalized feat record from a registry row.
   *
   * @param object $row
   *   Raw registry row.
   *
   * @return array<string, mixed>|null
   *   Normalized feat record or NULL when the row cannot be decoded.
   */
  protected function buildRegistryFeatRecord(object $row): ?array {
    $schema_data = json_decode((string) ($row->schema_data ?? ''), TRUE);
    if (!is_array($schema_data)) {
      return NULL;
    }

    $record = $schema_data;
    $record['id'] = $this->normalizeFeatId((string) ($record['id'] ?? $row->content_id ?? $record['name'] ?? $row->name ?? ''));
    $record['name'] = (string) ($record['name'] ?? $row->name ?? '');
    $record['type'] = strtolower(trim((string) ($record['type'] ?? ''))) ?: 'general';
    $record['level'] = (int) ($record['level'] ?? $row->level ?? 1);
    $record['rarity'] = strtolower(trim((string) ($record['rarity'] ?? $row->rarity ?? ''))) ?: 'common';
    $row_source_book = property_exists($row, 'source_book') ? $row->source_book : '';
    $record['source_book'] = strtolower(trim((string) ($record['source_book'] ?? $row_source_book ?? ''))) ?: 'crb';
    $record['prerequisites'] = trim((string) ($record['prerequisites'] ?? '')) ?: 'none';
    $record['traits'] = array_values(array_unique(array_filter(array_map(static function ($trait): string {
      return strtolower(trim((string) $trait));
    }, is_array($record['traits'] ?? NULL) ? $record['traits'] : []))));
    $record['class_id'] = $this->normalizeOptionalContextId((string) ($record['class_id'] ?? $record['class'] ?? ''));
    $record['ancestry_id'] = $this->normalizeOptionalContextId((string) ($record['ancestry_id'] ?? $record['ancestry'] ?? ''));

    if (!empty($record['description_snippet']) && empty($record['description'])) {
      $record['description'] = $record['description_snippet'];
      $record['description_source'] = 'description_snippet';
    }

    return $record;
  }

  /**
   * Normalize feat IDs between display text and slug forms.
   */
  protected function normalizeFeatId(string $value): string {
    $value = strtolower(trim($value));
    $value = str_replace(['_', '\''], ['-', ''], $value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
  }

  /**
   * Normalize optional context identifiers such as class or ancestry.
   */
  protected function normalizeOptionalContextId(string $value): string {
    $value = trim($value);
    return $value === '' ? '' : $this->normalizeFeatId($value);
  }

}
