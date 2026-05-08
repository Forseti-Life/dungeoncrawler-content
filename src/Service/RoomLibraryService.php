<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Psr\Log\LoggerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Room template library for caching and reusing generated room content.
 *
 * Works like the GeneratedImageRepository: every generated room is catalogued
 * here as a reusable blueprint. Future generation checks the library first
 * before creating fresh content, matching by theme, room_type, size_category,
 * and party level range.
 *
 * Campaign rooms link back via dc_campaign_rooms.source_room_id → template_id.
 *
 * @see \Drupal\dungeoncrawler_content\Service\RoomGeneratorService
 * @see \Drupal\dungeoncrawler_content\Service\GeneratedImageRepository
 */
class RoomLibraryService {

  protected const TABLE = 'dungeoncrawler_content_room_templates';

  /**
   * Database connection.
   */
  protected Connection $database;

  /**
   * Logger.
   */
  protected LoggerInterface $logger;

  /**
   * Number generation service for randomness.
   */
  protected NumberGenerationService $numberGeneration;

  /**
   * Constructs the RoomLibraryService.
   */
  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    NumberGenerationService $number_generation
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler');
    $this->numberGeneration = $number_generation;
  }

  /**
   * Catalogue a generated room into the library.
   *
   * Extracts campaign-agnostic layout and content data from a room, generates
   * a unique template_id based on theme/type/size, and stores it for reuse.
   *
   * @param array $room_data
   *   Complete room data (room.schema.json format).
   * @param array $context
   *   Generation context with theme, campaign_id, party_level, etc.
   *
   * @return string|null
   *   The template_id if catalogued, NULL if skipped (table missing, etc.).
   */
  public function catalogueRoom(array $room_data, array $context): ?string {
    if (!$this->tableExists()) {
      return NULL;
    }

    $theme = $context['theme'] ?? 'classic_dungeon';
    $room_type = $room_data['room_type'] ?? $context['room_type'] ?? 'chamber';
    $size_category = $room_data['size_category'] ?? $context['room_size'] ?? 'medium';
    $terrain_type = $room_data['terrain']['type'] ?? $context['terrain_type'] ?? 'stone_floor';

    // Generate a unique template_id.
    $template_id = $this->generateTemplateId($theme, $room_type, $size_category);

    // Check if this exact template_id already exists (extremely unlikely).
    if ($this->loadTemplate($template_id)) {
      // Append random suffix.
      $template_id .= '_' . $this->numberGeneration->rollRange(100, 999);
    }

    // Extract lighting as a string.
    $lighting = 'normal';
    if (isset($room_data['lighting'])) {
      if (is_string($room_data['lighting'])) {
        $lighting = $room_data['lighting'];
      }
      elseif (is_array($room_data['lighting']) && isset($room_data['lighting']['level'])) {
        $lighting = (string) $room_data['lighting']['level'];
      }
    }

    // Build layout data (campaign-agnostic — strip room_id references).
    $layout_data = json_encode([
      'hexes' => $room_data['hexes'] ?? [],
      'hex_manifest' => $room_data['hex_manifest'] ?? [],
      'entry_points' => $room_data['entry_points'] ?? [],
      'exit_points' => $room_data['exit_points'] ?? [],
      'terrain' => $room_data['terrain'] ?? [],
      'lighting' => $room_data['lighting'] ?? [],
    ]);

    // Build contents data (creature definitions, not instances — strip
    // campaign-specific instance_ids so they get re-rolled on instantiation).
    $contents_data = json_encode([
      'creatures' => $this->stripInstanceIds($room_data['creatures'] ?? []),
      'items' => $room_data['items'] ?? [],
      'traps' => $room_data['traps'] ?? [],
      'hazards' => $room_data['hazards'] ?? [],
      'obstacles' => $room_data['obstacles'] ?? [],
      'interactables' => $room_data['interactables'] ?? [],
    ]);

    // Build search tags from theme, type, terrain, and creature types.
    $search_tags = array_unique(array_filter(array_map('strtolower', [
      $theme,
      $room_type,
      $size_category,
      $terrain_type,
      $lighting,
      ...$this->extractCreatureTypes($room_data['creatures'] ?? []),
    ])));

    $party_level = (int) ($context['party_level'] ?? 1);
    $level_min = max(1, $party_level - 2);
    $level_max = min(20, $party_level + 2);

    $hex_count = count($room_data['hexes'] ?? []);
    $now = time();

    try {
      $this->database->insert(static::TABLE)
        ->fields([
          'template_id' => $template_id,
          'name' => $room_data['name'] ?? 'Unknown Room',
          'description' => $room_data['description'] ?? '',
          'room_type' => $room_type,
          'size_category' => $size_category,
          'theme' => $theme,
          'terrain_type' => $terrain_type,
          'lighting' => $lighting,
          'hex_count' => $hex_count,
          'layout_data' => $layout_data,
          'contents_data' => $contents_data,
          'environment_tags' => json_encode($room_data['environmental_effects'] ?? []),
          'search_tags' => json_encode(array_values($search_tags)),
          'level_min' => $level_min,
          'level_max' => $level_max,
          'difficulty' => $context['difficulty'] ?? 'moderate',
          'usage_count' => 0,
          'quality_score' => 0.5,
          'source' => 'generated',
          'source_campaign_id' => $context['campaign_id'] ?? NULL,
          'source_room_id' => $room_data['room_id'] ?? NULL,
          'created' => $now,
          'updated' => $now,
        ])
        ->execute();

      $this->logger->info('Catalogued room template @id from @room_id (theme=@theme, type=@type, size=@size)', [
        '@id' => $template_id,
        '@room_id' => $room_data['room_id'] ?? '?',
        '@theme' => $theme,
        '@type' => $room_type,
        '@size' => $size_category,
      ]);

      return $template_id;
    }
    catch (\Exception $e) {
      $this->logger->warning('Failed to catalogue room template: @error', [
        '@error' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Search the library for a room matching the given criteria.
   *
   * Returns the best match (highest quality, fewest usages) or NULL if none
   * found. This is called by RoomGeneratorService before generating fresh.
   *
   * @param array $criteria
   *   Search criteria:
   *   - theme: string (required) — e.g., 'goblin_warrens'
   *   - room_type: string (optional) — e.g., 'chamber', 'corridor'
   *   - size_category: string (optional) — e.g., 'small', 'medium'
   *   - party_level: int (optional) — filters by level_min/level_max range
   *   - terrain_type: string (optional) — e.g., 'stone_floor'
   *   - difficulty: string (optional) — e.g., 'moderate'
   *   - exclude_template_ids: array (optional) — IDs to skip (already used)
   *
   * @return array|null
   *   Template row with all fields, or NULL if no match.
   */
  public function findTemplate(array $criteria): ?array {
    if (!$this->tableExists()) {
      return NULL;
    }

    $query = $this->database->select(static::TABLE, 't')
      ->fields('t');

    // Required: theme.
    if (!empty($criteria['theme'])) {
      $query->condition('t.theme', $criteria['theme']);
    }

    // Optional filters.
    if (!empty($criteria['room_type'])) {
      $query->condition('t.room_type', $criteria['room_type']);
    }
    if (!empty($criteria['size_category'])) {
      $query->condition('t.size_category', $criteria['size_category']);
    }
    if (!empty($criteria['terrain_type'])) {
      $query->condition('t.terrain_type', $criteria['terrain_type']);
    }
    if (!empty($criteria['difficulty'])) {
      $query->condition('t.difficulty', $criteria['difficulty']);
    }

    // Level range: template must overlap with party level.
    if (!empty($criteria['party_level'])) {
      $level = (int) $criteria['party_level'];
      $query->condition('t.level_min', $level, '<=');
      $query->condition('t.level_max', $level, '>=');
    }

    // Exclude already-used templates within this dungeon.
    if (!empty($criteria['exclude_template_ids']) && is_array($criteria['exclude_template_ids'])) {
      $query->condition('t.template_id', $criteria['exclude_template_ids'], 'NOT IN');
    }

    // Sort: quality DESC, usage_count ASC (prefer high quality, least used).
    $query->orderBy('t.quality_score', 'DESC');
    $query->orderBy('t.usage_count', 'ASC');
    $query->range(0, 5);

    $results = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    if (empty($results)) {
      return NULL;
    }

    // Pick randomly from the top candidates to avoid always reusing the same one.
    $pick = $results[$this->numberGeneration->rollRange(0, count($results) - 1)];

    return $pick;
  }

  /**
   * Instantiate a library template into a campaign room.
   *
   * Decodes the stored layout/contents data, assigns new campaign-specific
   * IDs, and returns room_data ready for the normalizer pipeline.
   *
   * @param array $template
   *   Template row from findTemplate() or loadTemplate().
   * @param array $context
   *   Campaign generation context (campaign_id, room_index, etc.).
   *
   * @return array
   *   Room data structure matching room.schema.json, with new IDs.
   */
  public function instantiateTemplate(array $template, array $context): array {
    // Decode stored JSON.
    $layout = json_decode($template['layout_data'], TRUE) ?: [];
    $contents = json_decode($template['contents_data'], TRUE) ?: [];

    // Generate new campaign-scoped room_id.
    $room_id = sprintf('room_%d_%d_%d',
      $context['dungeon_id'] ?? 0,
      $context['level_id'] ?? 0,
      $context['room_index'] ?? 0
    );

    // Re-generate instance IDs for creatures (they need to be unique per campaign).
    $creatures = $this->reIdCreatures($contents['creatures'] ?? [], $room_id);

    $room_data = [
      'schema_version' => '1.0.0',
      'room_id' => $room_id,
      'name' => $template['name'],
      'description' => $template['description'] ?? '',
      'gm_notes' => '',
      'hexes' => $layout['hexes'] ?? [],
      'room_type' => $template['room_type'],
      'size_category' => $template['size_category'],
      'terrain' => $layout['terrain'] ?? [],
      'lighting' => $layout['lighting'] ?? [],
      'entry_points' => $layout['entry_points'] ?? [],
      'exit_points' => $layout['exit_points'] ?? [],
      'environmental_effects' => json_decode($template['environment_tags'] ?? '[]', TRUE) ?: [],
      'creatures' => $creatures,
      'items' => $contents['items'] ?? [],
      'traps' => $contents['traps'] ?? [],
      'hazards' => $contents['hazards'] ?? [],
      'obstacles' => $contents['obstacles'] ?? [],
      'interactables' => $contents['interactables'] ?? [],
      'state' => [
        'explored' => FALSE,
        'visibility' => 'hidden',
      ],
      'hex_manifest' => $layout['hex_manifest'] ?? [],
      '_library_source' => $template['template_id'],
    ];

    // Increment usage count.
    $this->incrementUsage($template['template_id']);

    $this->logger->info('Instantiated room template @template as @room_id for campaign @campaign', [
      '@template' => $template['template_id'],
      '@room_id' => $room_id,
      '@campaign' => $context['campaign_id'] ?? '?',
    ]);

    return $room_data;
  }

  /**
   * Load a specific template by template_id.
   *
   * @param string $template_id
   *   The template identifier.
   *
   * @return array|null
   *   Template row or NULL.
   */
  public function loadTemplate(string $template_id): ?array {
    if (!$this->tableExists()) {
      return NULL;
    }

    $row = $this->database->select(static::TABLE, 't')
      ->fields('t')
      ->condition('t.template_id', $template_id)
      ->execute()
      ->fetchAssoc();

    return $row ?: NULL;
  }

  /**
   * Count templates matching given criteria (for diagnostics).
   *
   * @param array $criteria
   *   Optional filter criteria (same keys as findTemplate).
   *
   * @return int
   *   Number of matching templates.
   */
  public function countTemplates(array $criteria = []): int {
    if (!$this->tableExists()) {
      return 0;
    }

    $query = $this->database->select(static::TABLE, 't');
    $query->addExpression('COUNT(*)', 'cnt');

    if (!empty($criteria['theme'])) {
      $query->condition('t.theme', $criteria['theme']);
    }
    if (!empty($criteria['room_type'])) {
      $query->condition('t.room_type', $criteria['room_type']);
    }
    if (!empty($criteria['size_category'])) {
      $query->condition('t.size_category', $criteria['size_category']);
    }

    return (int) $query->execute()->fetchField();
  }

  /**
   * List all templates with summary info.
   *
   * @param int $limit
   *   Maximum templates to return.
   * @param int $offset
   *   Offset for pagination.
   *
   * @return array
   *   Array of template summary rows.
   */
  public function listTemplates(int $limit = 50, int $offset = 0): array {
    if (!$this->tableExists()) {
      return [];
    }

    return $this->database->select(static::TABLE, 't')
      ->fields('t', [
        'id', 'template_id', 'name', 'room_type', 'size_category', 'theme',
        'terrain_type', 'lighting', 'hex_count', 'level_min', 'level_max',
        'difficulty', 'usage_count', 'quality_score', 'source', 'created',
      ])
      ->orderBy('t.created', 'DESC')
      ->range($offset, $limit)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Update quality score for a template (e.g., player feedback).
   *
   * @param string $template_id
   *   The template identifier.
   * @param float $score
   *   New quality score (0.0 - 1.0).
   */
  public function updateQualityScore(string $template_id, float $score): void {
    if (!$this->tableExists()) {
      return;
    }

    $this->database->update(static::TABLE)
      ->fields([
        'quality_score' => max(0.0, min(1.0, $score)),
        'updated' => time(),
      ])
      ->condition('template_id', $template_id)
      ->execute();
  }

  /**
   * Delete a template from the library.
   *
   * @param string $template_id
   *   The template identifier.
   *
   * @return bool
   *   TRUE if deleted, FALSE if not found.
   */
  public function deleteTemplate(string $template_id): bool {
    if (!$this->tableExists()) {
      return FALSE;
    }

    $deleted = $this->database->delete(static::TABLE)
      ->condition('template_id', $template_id)
      ->execute();

    return $deleted > 0;
  }

  /**
   * Generate a unique template_id from room attributes.
   */
  protected function generateTemplateId(string $theme, string $room_type, string $size_category): string {
    $suffix = $this->numberGeneration->rollRange(1000, 9999);
    return sprintf('%s_%s_%s_%d', $theme, $room_type, $size_category, $suffix);
  }

  /**
   * Increment usage count for a template.
   */
  protected function incrementUsage(string $template_id): void {
    try {
      $this->database->update(static::TABLE)
        ->expression('usage_count', 'usage_count + 1')
        ->fields(['updated' => time()])
        ->condition('template_id', $template_id)
        ->execute();
    }
    catch (\Exception $e) {
      // Non-critical — log and continue.
      $this->logger->notice('Failed to increment usage for @id: @err', [
        '@id' => $template_id,
        '@err' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Strip campaign-specific instance IDs from creature entities.
   *
   * Keeps the creature definition (type, stats, placement hex) but removes
   * the unique instance_id so it gets regenerated on instantiation.
   */
  protected function stripInstanceIds(array $creatures): array {
    return array_map(function (array $creature) {
      unset($creature['instance_id']);
      return $creature;
    }, $creatures);
  }

  /**
   * Re-generate unique instance IDs for creatures in a new room context.
   */
  protected function reIdCreatures(array $creatures, string $room_id): array {
    $index = 0;
    return array_map(function (array $creature) use ($room_id, &$index) {
      $creature['instance_id'] = sprintf('%s_entity_%d', $room_id, $index++);
      return $creature;
    }, $creatures);
  }

  /**
   * Extract creature type names for search tags.
   */
  protected function extractCreatureTypes(array $creatures): array {
    $types = [];
    foreach ($creatures as $creature) {
      if (!empty($creature['display_name'])) {
        $types[] = $creature['display_name'];
      }
      elseif (!empty($creature['entity_ref']['content_id'])) {
        $types[] = $creature['entity_ref']['content_id'];
      }
    }
    return array_unique($types);
  }

  /**
   * Check if the library table exists.
   */
  protected function tableExists(): bool {
    return $this->database->schema()->tableExists(static::TABLE);
  }

}
