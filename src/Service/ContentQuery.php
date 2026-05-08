<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Service for querying and filtering game content.
 *
 * Provides methods to query creatures, items, traps, and other content
 * with various filters (level, tags, rarity, etc.).
 *
 * @see docs/dungeoncrawler/issues/issue-3-game-content-system-design.md
 *   Section: Service Layer Design > ContentQuery Service
 */
class ContentQuery {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Number generation service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\NumberGenerationService
   */
  protected $numberGeneration;

  /**
   * Constructs a ContentQuery object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database, NumberGenerationService $number_generation) {
    $this->database = $database;
    $this->numberGeneration = $number_generation;
  }

  /**
   * Query creatures by filters.
   *
   * @param array $filters
   *   Filter criteria:
   *   - level_min: int - Minimum level
   *   - level_max: int - Maximum level
   *   - tags_include: array - Tags that must be present
   *   - tags_exclude: array - Tags that must not be present
   *   - rarity: array - Rarity values to match
   *   - size: string - Creature size
   *   - alignment: string - Creature alignment
   * @param int $limit
   *   Maximum number of results (default 10).
   *
   * @return array
   *   Array of creature data.
   *
   * @see docs/dungeoncrawler/issues/issue-3-game-content-system-design.md
   *   Line 172: queryCreatures method specification
   *   Section: Content Query Algorithm (lines 955-1013)
   */
  public function queryCreatures(array $filters, int $limit = 10): array {
    return $this->queryContent('creature', $filters, $limit);
  }

  /**
   * Query items by filters.
   *
   * @param array $filters
   *   Filter criteria:
   *   - item_type: string - 'weapon', 'armor', 'consumable', 'treasure'
   *   - level_min: int - Minimum level
   *   - level_max: int - Maximum level
   *   - tags: array - Tags to match
   *   - rarity: array - Rarity values
   * @param int $limit
   *   Maximum number of results (default 10).
   *
   * @return array
   *   Array of item data.
   *
   * @see docs/dungeoncrawler/issues/issue-3-game-content-system-design.md
   *   Line 189: queryItems method specification
   */
  public function queryItems(array $filters, int $limit = 10): array {
    return $this->queryContent('item', $filters, $limit);
  }

  /**
   * Query traps by filters.
   *
   * @param array $filters
   *   Filter criteria similar to creatures/items.
   * @param int $limit
   *   Maximum number of results.
   *
   * @return array
   *   Array of trap data.
   */
  public function queryTraps(array $filters, int $limit = 10): array {
    return $this->queryContent('trap', $filters, $limit);
  }
  
  /**
   * Generic content query method.
   *
   * @param string $content_type
   *   Content type to query.
   * @param array $filters
   *   Filter criteria.
   * @param int $limit
   *   Maximum results.
   * @param bool $random
   *   Whether to randomize order.
   *
   * @return array
   *   Array of content data.
   */
  protected function queryContent(string $content_type, array $filters, int $limit = 10, bool $random = FALSE): array {
    $query = $this->database->select('dungeoncrawler_content_registry', 'c')
      ->fields('c')
      ->condition('content_type', $content_type);
    
    // Apply level filters
    if (isset($filters['level_min'])) {
      $query->condition('level', $filters['level_min'], '>=');
    }
    if (isset($filters['level_max'])) {
      $query->condition('level', $filters['level_max'], '<=');
    }
    
    // Apply rarity filter
    if (!empty($filters['rarity'])) {
      $rarity_values = is_array($filters['rarity']) ? $filters['rarity'] : [$filters['rarity']];
      $query->condition('rarity', $rarity_values, 'IN');
    }
    
    // Apply tag filters
    if (!empty($filters['tags_include'])) {
      foreach ($filters['tags_include'] as $tag) {
        // Use LIKE to search in JSON array
        $query->condition('tags', '%"' . $tag . '"%', 'LIKE');
      }
    }
    
    if (!empty($filters['tags_exclude'])) {
      foreach ($filters['tags_exclude'] as $tag) {
        $query->condition('tags', '%"' . $tag . '"%', 'NOT LIKE');
      }
    }
    
    // Order
    if ($random) {
      $query->orderRandom();
    } else {
      $query->orderBy('level', 'ASC');
      $query->orderBy('name', 'ASC');
    }
    
    // Limit
    if ($limit > 0) {
      $query->range(0, $limit);
    }
    
    $results = $query->execute()->fetchAll();
    
    // Parse schema_data for each result
    $content = [];
    foreach ($results as $row) {
      $data = json_decode($row->schema_data, TRUE);
      if ($data !== NULL) {
        $content[] = $data;
      }
    }
    
    return $content;
  }

  /**
   * Get random content matching criteria.
   *
   * @param string $content_type
   *   Content type ('creature', 'item', 'trap', 'hazard').
   * @param array $filters
   *   Filter criteria.
   * @param int $count
   *   Number of items to return (default 1).
   *
   * @return array
   *   Random selection of content.
   *
   * @see docs/dungeoncrawler/issues/issue-3-game-content-system-design.md
   *   Line 202: getRandomContent method specification
   */
  public function getRandomContent(string $content_type, array $filters, int $count = 1): array {
    return $this->queryContent($content_type, $filters, $count, TRUE);
  }

  /**
   * Get loot table and roll for items.
   *
   * @param string $table_id
   *   Loot table identifier.
   *
   * @return array
   *   Array of rolled items with quantities.
   *   Format: [['item_id' => 'gold_piece', 'quantity' => 15], ...]
   *
   * @see docs/dungeoncrawler/issues/issue-3-game-content-system-design.md
   *   Line 211: rollLootTable method specification
   *   Section: Loot Table Roll Algorithm (lines 903-954)
   */
  public function rollLootTable(string $table_id): array {
    // TODO: Implement loot table rolling
    // 1. Load loot table from dungeoncrawler_content_loot_tables
    // 2. Determine number of rolls (roll_count.min to roll_count.max)
    // 3. For each roll:
    //    a. Calculate total weight of all entries
    //    b. Roll random number (1 to total_weight)
    //    c. Select entry based on weight
    //    d. If entry.item_id: roll quantity dice
    //    e. If entry.table_ref: recursive rollLootTable()
    // 4. Consolidate duplicate items
    // 5. Return items array
    
    return [];
  }

  /**
   * Build encounter from template.
   *
   * @param string $template_id
   *   Encounter template identifier.
   * @param int $party_level
   *   Average party level.
   *
   * @return array
   *   Complete encounter data:
   *   - creatures: Array of creature data with counts
   *   - xp_total: Total XP value
   *   - threat_level: Threat level string
   *
   * @see docs/dungeoncrawler/issues/issue-3-game-content-system-design.md
   *   Line 226: buildEncounterFromTemplate method specification
   *   Section: Encounter Generation Algorithm (lines 834-902)
   */
  public function buildEncounterFromTemplate(string $template_id, int $party_level): array {
    // TODO: Implement encounter building
    // 1. Load encounter template from dungeoncrawler_content_encounter_templates
    // 2. For each creature_slot in template:
    //    a. Calculate target_level = party_level + slot.level_offset
    //    b. Query creatures with filters (level ± 1, tags_required, tags_excluded)
    //    c. Select random creature from results
    //    d. Add to encounter with quantity
    // 3. Calculate total XP
    // 4. Verify XP is within budget
    // 5. Return encounter data
    
    return [
      'creatures' => [],
      'xp_total' => 0,
      'threat_level' => 'moderate',
    ];
  }

  /**
   * Get encounter templates by filters.
   *
   * @param array $filters
   *   Filter criteria:
   *   - level: int - Party level
   *   - threat_level: string - Desired threat level
   *   - tags_include: array - Environment/theme tags
   * @param int $limit
   *   Maximum number of results.
   *
   * @return array
   *   Array of encounter template data.
   */
  public function queryEncounterTemplates(array $filters, int $limit = 10): array {
    // TODO: Implement encounter template query
    // Query dungeoncrawler_content_encounter_templates with filters
    
    return [];
  }

  /**
   * Roll dice notation.
   *
   * @param string $dice_notation
   *   Dice notation (e.g., '2d6', '1d20+5', '3d10-2').
   *
   * @return int
   *   Rolled result.
   */
  protected function rollDice(string $dice_notation): int {
    if (is_numeric($dice_notation)) {
      return (int) $dice_notation;
    }

    try {
      $result = $this->numberGeneration->rollNotation($dice_notation);
      return (int) ($result['total'] ?? 0);
    }
    catch (\InvalidArgumentException $exception) {
      return 0;
    }
  }

}
