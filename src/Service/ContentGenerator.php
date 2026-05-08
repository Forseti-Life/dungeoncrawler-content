<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Service for generating game content procedurally.
 *
 * Generates encounters, treasure hoards, and creature personalities
 * based on dungeon level, theme, and other parameters.
 *
 * @see docs/dungeoncrawler/issues/issue-3-game-content-system-design.md
 *   Section: Service Layer Design > ContentGenerator Service
 */
class ContentGenerator {

  /**
   * The content query service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\ContentQuery
   */
  protected $contentQuery;

  /**
   * Number generation service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\NumberGenerationService
   */
  protected $numberGeneration;

  /**
   * Constructs a ContentGenerator object.
   *
   * @param \Drupal\dungeoncrawler_content\Service\ContentQuery $content_query
   *   The content query service.
   */
  public function __construct(ContentQuery $content_query, NumberGenerationService $number_generation) {
    $this->contentQuery = $content_query;
    $this->numberGeneration = $number_generation;
  }

  /**
   * Generate appropriate content for dungeon level.
   *
   * @param int $dungeon_level
   *   The dungeon level (difficulty tier).
   * @param string $theme
   *   Theme identifier (e.g., 'goblin_warrens', 'undead_crypt').
   * @param string $room_type
   *   Room type: 'combat', 'treasure', 'trap', 'empty'.
   *
   * @return array
   *   Generated content with keys:
   *   - creatures: Array of creatures for combat rooms
   *   - items: Array of items for treasure rooms
   *   - traps: Array of traps
   *   - hazards: Array of hazards
   *
   * @see docs/dungeoncrawler/issues/issue-3-game-content-system-design.md
   *   Line 416: generateRoomContent method specification
   */
  public function generateRoomContent(int $dungeon_level, string $theme, string $room_type): array {
    $content = [
      'creatures' => [],
      'items' => [],
      'traps' => [],
      'hazards' => [],
    ];
    
    switch ($room_type) {
      case 'combat':
        // Generate encounter (assume 4-person party for dungeon generation)
        $encounter = $this->generateEncounter($dungeon_level, 4, 'moderate', $theme);
        $content['creatures'] = $encounter['creatures'];
        break;
        
      case 'treasure':
        $hoard = $this->generateTreasureHoard($dungeon_level, 'moderate');
        $content['items'] = $hoard['items'];
        $content['currency'] = $hoard['currency'];
        break;
        
      case 'trap':
        $traps = $this->contentQuery->queryTraps([
          'level_min' => max(1, $dungeon_level - 1),
          'level_max' => $dungeon_level + 1,
        ], 1);
        
        if (!empty($traps)) {
          $content['traps'] = [$traps[0]];
        }
        break;
        
      case 'empty':
        // Empty room - maybe add a minor detail
        break;
    }
    
    return $content;
  }

  /**
   * Generate an encounter for party.
   *
   * @param int $party_level
   *   Average party level.
   * @param int $party_size
   *   Number of party members.
   * @param string $threat_level
   *   Desired threat: 'trivial', 'low', 'moderate', 'severe', 'extreme'.
   * @param string $theme
   *   Theme/environment for creature selection.
   *
   * @return array
   *   Encounter data with creatures, XP, and threat level.
   *
   * @see docs/dungeoncrawler/issues/issue-3-game-content-system-design.md
   *   Section: Encounter Generation Algorithm (lines 834-902)
   */
  public function generateEncounter(int $party_level, int $party_size, string $threat_level, string $theme): array {
    // Calculate XP budget
    $xp_budget = $this->calculateXPBudget($party_level, $party_size, $threat_level);
    
    $creatures = [];
    $total_xp = 0;
    
    // Try to fill encounter with creatures
    $remaining_xp = $xp_budget;
    $attempts = 0;
    $max_attempts = 10;
    
    while ($remaining_xp > 0 && $attempts < $max_attempts) {
      $attempts++;
      
      // Determine creature level based on remaining XP
      $target_level = $party_level;
      if ($remaining_xp < $xp_budget * 0.3) {
        $target_level = max(1, $party_level - 2); // Use weaker creatures for remaining XP
      }
      
      // Query for creatures
      $filters = [
        'level_min' => max(1, $target_level - 2),
        'level_max' => $target_level + 2,
      ];
      
      if (!empty($theme)) {
        $filters['tags_include'] = [$theme];
      }
      
      $candidates = $this->contentQuery->queryCreatures($filters, 20);
      
      if (empty($candidates)) {
        break;
      }
      
      // Pick a random creature
      $creature = $candidates[$this->randomArrayIndex($candidates)];
      
      // Calculate creature XP (simplified - should match PF2e XP chart)
      $creature_xp = $this->getCreatureXP($creature['level'] ?? 1);
      
      // Add creature if it fits budget
      if ($creature_xp <= $remaining_xp * 1.2) { // Allow 20% overage
        $creatures[] = [
          'creature_id' => $creature['content_id'],
          'name' => $creature['name'],
          'level' => $creature['level'] ?? 1,
          'xp' => $creature_xp,
          'data' => $creature,
        ];
        
        $total_xp += $creature_xp;
        $remaining_xp -= $creature_xp;
      } else {
        // Try with weaker creatures
        if ($target_level > 1) {
          $target_level--;
        } else {
          break;
        }
      }
    }
    
    return [
      'creatures' => $creatures,
      'total_xp' => $total_xp,
      'threat_level' => $threat_level,
      'xp_budget' => $xp_budget,
    ];
  }
  
  /**
   * Get XP value for creature level.
   *
   * Simplified PF2e XP chart.
   *
   * @param int $level
   *   Creature level.
   *
   * @return int
   *   XP value.
   */
  protected function getCreatureXP(int $level): int {
    $xp_chart = [
      -1 => 2,
      0 => 5,
      1 => 10,
      2 => 15,
      3 => 20,
      4 => 30,
      5 => 40,
      6 => 60,
      7 => 80,
      8 => 120,
      9 => 160,
      10 => 240,
    ];
    
    return $xp_chart[$level] ?? max(240, $level * 24);
  }

  /**
   * Populate creature with AI personality.
   *
   * @param array $creature_data
   *   Base creature data from schema.
   *
   * @return array
   *   Creature with generated personality traits.
   *
   * @see docs/dungeoncrawler/issues/issue-3-game-content-system-design.md
   *   Line 432: generateCreaturePersonality method specification
   */
  public function generateCreaturePersonality(array $creature_data): array {
    // TODO: Implement AI personality generation
    // 1. Use creature's ai_behavior as base
    // 2. Generate random variations on:
    //    - aggression level (0.0 - 1.0)
    //    - tactics preference
    //    - target priorities
    //    - retreat threshold
    // 3. Add flavor text/description
    // 4. Return enhanced creature data
    
    return $creature_data;
  }

  /**
   * Generate treasure hoard for level.
   *
   * @param int $level
   *   Dungeon/challenge level.
   * @param string $hoard_type
   *   Hoard size: 'minor', 'moderate', 'major'.
   *
   * @return array
   *   Array of items with quantities.
   *
   * @see docs/dungeoncrawler/issues/issue-3-game-content-system-design.md
   *   Line 440: generateTreasureHoard method specification
   *   Section: Treasure Hoard Generation Algorithm (lines 1014-1079)
   */
  public function generateTreasureHoard(int $level, string $hoard_type): array {
    $currency = $this->generateCurrency($hoard_type);
    $items = $this->generateHoardItems($level, $hoard_type);
    
    // Calculate total value in gold pieces
    $total_value_gp = $currency['gp'] +
                      ($currency['sp'] / 10) +
                      ($currency['cp'] / 100) +
                      ($currency['pp'] * 10);
    
    return [
      'currency' => $currency,
      'items' => $items,
      'total_value_gp' => $total_value_gp,
    ];
  }
  
  /**
   * Generate currency for hoard.
   *
   * @param string $hoard_type
   *   Hoard size.
   *
   * @return array
   *   Currency amounts.
   */
  protected function generateCurrency(string $hoard_type): array {
    $currency_tables = [
      'minor' => [
        'gp' => '1d10',
        'sp' => '2d20',
        'cp' => '5d20',
        'pp' => 0,
      ],
      'moderate' => [
        'gp' => '2d20',
        'sp' => '5d20',
        'cp' => '10d20',
        'pp' => '1d4',
      ],
      'major' => [
        'gp' => '5d20',
        'sp' => '10d20',
        'cp' => '5d10',
        'pp' => '1d10',
      ],
    ];
    
    $table = $currency_tables[$hoard_type] ?? $currency_tables['minor'];
    
    return [
      'gp' => is_numeric($table['gp']) ? (int) $table['gp'] : $this->rollDice($table['gp']),
      'sp' => is_numeric($table['sp']) ? (int) $table['sp'] : $this->rollDice($table['sp']),
      'cp' => is_numeric($table['cp']) ? (int) $table['cp'] : $this->rollDice($table['cp']),
      'pp' => is_numeric($table['pp']) ? (int) $table['pp'] : $this->rollDice($table['pp']),
    ];
  }
  
  /**
   * Generate items for hoard.
   *
   * @param int $level
   *   Challenge level.
   * @param string $hoard_type
   *   Hoard size.
   *
   * @return array
   *   Array of items.
   */
  protected function generateHoardItems(int $level, string $hoard_type): array {
    $item_counts = [
      'minor' => 1,
      'moderate' => $this->numberGeneration->rollRange(2, 3),
      'major' => $this->numberGeneration->rollRange(3, 5),
    ];
    
    $count = $item_counts[$hoard_type] ?? 1;
    $items = [];
    
    for ($i = 0; $i < $count; $i++) {
      // Query for level-appropriate items
      $item_level = $level + $this->numberGeneration->rollRange(-1, 1);
      $rarity = $hoard_type === 'major' && $i === 0 ? 'rare' : 'common';
      
      $candidates = $this->contentQuery->queryItems([
        'level_min' => max(0, $item_level - 1),
        'level_max' => $item_level + 1,
        'rarity' => $rarity,
      ], 10);
      
      if (!empty($candidates)) {
        $item = $candidates[$this->randomArrayIndex($candidates)];
        $items[] = [
          'item_id' => $item['content_id'],
          'name' => $item['name'],
          'level' => $item['level'] ?? 0,
          'rarity' => $item['rarity'] ?? 'common',
          'quantity' => 1,
        ];
      }
    }
    
    return $items;
  }
  
  /**
   * Roll dice notation.
   *
   * @param string $notation
   *   Dice notation.
   *
   * @return int
   *   Result.
   */
  protected function rollDice(string $notation): int {
    if (is_numeric($notation)) {
      return (int) $notation;
    }

    try {
      $result = $this->numberGeneration->rollNotation($notation);
      return (int) ($result['total'] ?? 0);
    }
    catch (\InvalidArgumentException $exception) {
      return 0;
    }
  }

  /**
   * Pick a random valid index from a non-empty array.
   */
  protected function randomArrayIndex(array $items): int {
    return $this->numberGeneration->rollRange(0, count($items) - 1);
  }

  /**
   * Calculate XP budget for encounter.
   *
   * @param int $party_level
   *   Average party level.
   * @param int $party_size
   *   Number of party members.
   * @param string $threat_level
   *   Threat level.
   *
   * @return int
   *   XP budget for encounter.
   */
  protected function calculateXPBudget(int $party_level, int $party_size, string $threat_level): int {
    // TODO: Implement XP budget calculation
    $multipliers = [
      'trivial' => 10,
      'low' => 15,
      'moderate' => 20,
      'severe' => 30,
      'extreme' => 40,
    ];
    
    $multiplier = $multipliers[$threat_level] ?? 20;
    return $party_size * $multiplier;
  }

}
