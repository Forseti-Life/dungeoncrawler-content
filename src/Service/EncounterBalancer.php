<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * PF2e encounter balancing service using XP budget system.
 *
 * Implements official Pathfinder 2E encounter building rules
 * with XP budgets by difficulty and creature level differentials.
 *
 * @see /docs/dungeoncrawler/issues/issue-4-procedural-dungeon-generation-design.md
 * Line 1009-1139
 */
class EncounterBalancer {

  /**
   * XP Budget by difficulty (PF2e standard).
   */
  const XP_BUDGETS = [
    'trivial' => 40,
    'low' => 60,
    'moderate' => 80,
    'severe' => 120,
    'extreme' => 160,
  ];

  /**
   * XP cost by creature level relative to party level.
   *
   * Party Level - Creature Level = XP Cost
   * -4 = 10 XP
   * -3 = 15 XP
   * -2 = 20 XP
   * -1 = 30 XP
   *  0 = 40 XP (same level)
   * +1 = 60 XP
   * +2 = 80 XP
   * +3 = 120 XP
   * +4 = 160 XP
   */
  const XP_BY_LEVEL_DIFF = [
    -4 => 10,
    -3 => 15,
    -2 => 20,
    -1 => 30,
    0 => 40,
    1 => 60,
    2 => 80,
    3 => 120,
    4 => 160,
  ];

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The creature database service.
   *
   * @var mixed
   */
  protected $creatureDb;

  /**
   * Constructs an EncounterBalancer object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  /**
   * Number generation service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\NumberGenerationService
   */
  protected NumberGenerationService $numberGeneration;

  /**
   * Constructs an EncounterBalancer object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\dungeoncrawler_content\Service\NumberGenerationService $number_generation
   *   The number generation service.
   */
  public function __construct(Connection $database, NumberGenerationService $number_generation) {
    $this->database = $database;
    $this->numberGeneration = $number_generation;
  }

  /**
   * Create a balanced encounter.
   *
   * See design doc line 1009-1075
   *
   * @param int $party_level
   *   Party level.
   * @param array $party_composition
   *   Party size and roles.
   * @param string $difficulty
   *   'trivial', 'low', 'moderate', 'severe', 'extreme'.
   * @param string $theme
   *   Dungeon theme to select appropriate creatures.
   *
   * @return array
   *   Encounter data array.
   */
  public function createEncounter(
    int $party_level,
    array $party_composition,
    string $difficulty,
    string $theme
  ): array {
    // xpBudget = self::XP_BUDGETS[$difficulty]
    // partySize = count($partyComposition)
    //
    // Adjust budget for party size (4 is baseline)
    // xpBudget = this.adjustBudgetForPartySize(xpBudget, partySize)
    //
    // Select creatures that fit theme
    // availableCreatures = this.creatureDb.getCreaturesByTheme($theme)
    //
    // Filter creatures within reasonable level range
    // levelRange = this.getCreatureLevelRange($partyLevel, $difficulty)
    // availableCreatures = availableCreatures.filter(
    //     c => c.level >= levelRange.min && c.level <= levelRange.max
    // )
    //
    // Build encounter using knapsack-like algorithm
    // creatures = this.selectCreaturesForBudget(
    //     availableCreatures,
    //     xpBudget,
    //     partyLevel,
    //     partyComposition
    // )
    //
    // Create encounter object
    // encounter = new Encounter()
    // encounter.encounter_name = this.generateEncounterName(creatures, theme)
    // encounter.difficulty = difficulty
    // encounter.xp_value = this.calculateTotalXP(creatures, partyLevel)
    // encounter.creatures = json_encode(creatures)
    //
    // return encounter

    $xp_budget = self::XP_BUDGETS[$difficulty] ?? self::XP_BUDGETS['moderate'];
    $party_size = count($party_composition) ?: 4;

    // Adjust budget for party size (4 is baseline).
    $xp_budget = $this->adjustBudgetForPartySize($xp_budget, $party_size);

    // Determine creature level range.
    $level_range = $this->getCreatureLevelRange($party_level, $difficulty);

    // Query available creatures from the content registry.
    $available_creatures = $this->getAvailableCreatures($theme, $level_range);

    // Select creatures to fill the budget.
    $creatures = $this->selectCreaturesForBudget(
      $available_creatures,
      $xp_budget,
      $party_level,
      $party_composition
    );

    // Calculate actual XP value.
    $total_xp = 0;
    foreach ($creatures as $creature) {
      $total_xp += ($creature['xp_cost'] ?? 0) * ($creature['count'] ?? 1);
    }

    return [
      'encounter_name' => $this->generateEncounterName($creatures, $theme),
      'difficulty' => $difficulty,
      'xp_budget' => $xp_budget,
      'xp_value' => $total_xp,
      'party_level' => $party_level,
      'party_size' => $party_size,
      'creatures' => $creatures,
    ];
  }

  /**
   * Adjust XP budget based on party size.
   *
   * Uses the canonical PF2e Character Adjustment rule:
   * each PC above or below 4 adds or subtracts CHARACTER_ADJUSTMENT_XP (20 XP).
   * Source: PF2e CRB Chapter 10; see CharacterManager::CHARACTER_ADJUSTMENT_XP.
   *
   * @param int $budget
   *   Base XP budget for a 4-PC party.
   * @param int $party_size
   *   Number of party members.
   *
   * @return int
   *   Adjusted XP budget (minimum 0).
   */
  private function adjustBudgetForPartySize(int $budget, int $party_size): int {
    return CharacterManager::adjustBudgetForPartySize($budget, $party_size);
  }

  /**
   * Get appropriate creature level range for encounter.
   *
   * See design doc line 1101-1113
   *
   * @param int $party_level
   *   Party level.
   * @param string $difficulty
   *   Difficulty level.
   *
   * @return array
   *   Array with 'min' and 'max' level bounds.
   */
  private function getCreatureLevelRange(int $party_level, string $difficulty): array {
    // if (difficulty == 'trivial') {
    //     return {min: max(1, partyLevel - 4), max: partyLevel - 2}
    // } else if (difficulty == 'low') {
    //     return {min: max(1, partyLevel - 3), max: partyLevel - 1}
    // } else if (difficulty == 'moderate') {
    //     return {min: max(1, partyLevel - 2), max: partyLevel + 1}
    // } else if (difficulty == 'severe') {
    //     return {min: partyLevel - 1, max: partyLevel + 2}
    // } else { // extreme
    //     return {min: partyLevel, max: partyLevel + 4}
    // }

    switch ($difficulty) {
      case 'trivial':
        return ['min' => max(1, $party_level - 4), 'max' => max(1, $party_level - 2)];

      case 'low':
        return ['min' => max(1, $party_level - 3), 'max' => max(1, $party_level - 1)];

      case 'moderate':
        return ['min' => max(1, $party_level - 2), 'max' => $party_level + 1];

      case 'severe':
        return ['min' => max(1, $party_level - 1), 'max' => $party_level + 2];

      case 'extreme':
        return ['min' => $party_level, 'max' => $party_level + 4];

      default:
        return ['min' => max(1, $party_level - 2), 'max' => $party_level + 1];
    }
  }

  /**
   * Select creatures to fill XP budget (knapsack algorithm).
   *
   * See design doc line 1120-1170
   *
   * @param array $available_creatures
   *   Available creatures for this theme.
   * @param int $budget
   *   XP budget to fill.
   * @param int $party_level
   *   Party level.
   * @param array $party_composition
   *   Party composition.
   *
   * @return array
   *   Selected creatures array.
   */
  private function selectCreaturesForBudget(
    array $available_creatures,
    int $budget,
    int $party_level,
    array $party_composition
  ): array {
    // selectedCreatures = []
    // remainingBudget = budget
    //
    // Sort creatures by XP cost descending
    // sortedCreatures = this.sortByXPCost(availableCreatures, partyLevel)
    //
    // Try to add creatures until budget is filled
    // maxAttempts = 100
    // attempts = 0
    //
    // while (remainingBudget > 10 && attempts < maxAttempts) {
    //     attempts++
    //
    //     Select a random creature that fits budget
    //     affordableCreatures = sortedCreatures.filter(
    //         c => this.getCreatureXPCost(c, partyLevel) <= remainingBudget
    //     )
    //
    //     if (affordableCreatures.isEmpty()) {
    //         break // No more creatures fit
    //     }
    //
    //     Weighted random selection (prefer appropriate level)
    //     creature = this.weightedRandomCreature(affordableCreatures, partyLevel)
    //
    //     Calculate XP cost
    //     xpCost = this.getCreatureXPCost(creature, partyLevel)
    //
    //     Add creature to encounter
    //     selectedCreatures.push({
    //         creature_id: creature.id,
    //         name: this.generateCreatureName(creature),
    //         level: creature.level,
    //         count: 1, // Can be increased for groups
    //         xp_cost: xpCost
    //     })
    //
    //     remainingBudget -= xpCost
    // }
    //
    // Optimize: if we have similar creatures, group them
    // selectedCreatures = this.groupSimilarCreatures(selectedCreatures)
    //
    // return selectedCreatures

    $selected_creatures = [];
    $remaining_budget = $budget;
    $max_attempts = 100;
    $attempts = 0;

    while ($remaining_budget > 10 && $attempts < $max_attempts) {
      $attempts++;

      // Filter to creatures we can afford.
      $affordable = array_filter($available_creatures, function ($c) use ($party_level, $remaining_budget) {
        return $this->getCreatureXPCost($c, $party_level) <= $remaining_budget;
      });

      if (empty($affordable)) {
        break;
      }

      // Weighted random selection — prefer creatures close to party level.
      $affordable = array_values($affordable);
      $weights = [];
      foreach ($affordable as $i => $c) {
        $diff = abs(($c['level'] ?? $party_level) - $party_level);
        $weights[$i] = max(1, 10 - ($diff * 2));
      }

      $creature = $this->weightedPick($affordable, $weights);
      $xp_cost = $this->getCreatureXPCost($creature, $party_level);

      // Check if we already selected the same creature type — group them.
      $key = $creature['creature_id'] ?? $creature['name'] ?? 'unknown_' . $attempts;
      $found = FALSE;
      foreach ($selected_creatures as &$existing) {
        if (($existing['creature_id'] ?? '') === $key) {
          $existing['count']++;
          $found = TRUE;
          break;
        }
      }
      unset($existing);

      if (!$found) {
        $selected_creatures[] = [
          'creature_id' => $key,
          'name' => $creature['name'] ?? 'Unknown Creature',
          'level' => $creature['level'] ?? $party_level,
          'count' => 1,
          'xp_cost' => $xp_cost,
        ];
      }

      $remaining_budget -= $xp_cost;
    }

    return $selected_creatures;
  }

  /**
   * Get XP cost of creature based on level difference.
   *
   * See design doc line 1177-1185
   *
   * @param array $creature
   *   Creature data.
   * @param int $party_level
   *   Party level.
   *
   * @return int
   *   XP cost.
   */
  private function getCreatureXPCost(array $creature, int $party_level): int {
    // levelDiff = $partyLevel - $creature.level
    //
    // Clamp to -4 to +4 range
    // levelDiff = max(-4, min(4, levelDiff))
    //
    // return self::XP_BY_LEVEL_DIFF[$levelDiff]

    $creature_level = $creature['level'] ?? 0;
    $level_diff = $creature_level - $party_level;

    // Clamp to -4 to +4 range.
    $level_diff = max(-4, min(4, $level_diff));

    return self::XP_BY_LEVEL_DIFF[$level_diff] ?? 40;
  }

  /**
   * Get available creatures for a theme and level range.
   *
   * Queries the content registry for creatures, or uses a built-in
   * fallback creature catalog organized by theme.
   *
   * @param string $theme
   *   Dungeon theme.
   * @param array $level_range
   *   Array with 'min' and 'max' keys.
   *
   * @return array
   *   Array of creature data arrays.
   */
  private function getAvailableCreatures(string $theme, array $level_range): array {
    // Try content registry first.
    try {
      $query = $this->database->select('dc_campaign_content_registry', 'r')
        ->fields('r', ['content_id', 'name', 'content_data'])
        ->condition('r.content_type', 'creature')
        ->condition('r.level', $level_range['min'], '>=')
        ->condition('r.level', $level_range['max'], '<=');

      $results = $query->execute()->fetchAll();
      if (!empty($results)) {
        $creatures = [];
        foreach ($results as $row) {
          $data = json_decode($row->content_data, TRUE) ?: [];
          $data['creature_id'] = $row->content_id;
          $data['name'] = $row->name;
          $creatures[] = $data;
        }
        return $creatures;
      }
    }
    catch (\Exception $e) {
      // Content registry table may not have a level column; fall through.
    }

    // Fallback: built-in creature catalog by theme.
    return $this->getFallbackCreatures($theme, $level_range);
  }

  /**
   * Fallback creature catalog organized by theme and level.
   *
   * @param string $theme
   *   Dungeon theme.
   * @param array $level_range
   *   Array with 'min' and 'max' keys.
   *
   * @return array
   *   Creature data arrays.
   */
  private function getFallbackCreatures(string $theme, array $level_range): array {
    $catalog = [
      'dungeon' => [
        ['creature_id' => 'goblin_warrior', 'name' => 'Goblin Warrior', 'level' => 1],
        ['creature_id' => 'goblin_pyro', 'name' => 'Goblin Pyro', 'level' => 1],
        ['creature_id' => 'kobold_scout', 'name' => 'Kobold Scout', 'level' => 0],
        ['creature_id' => 'skeleton_guard', 'name' => 'Skeleton Guard', 'level' => 1],
        ['creature_id' => 'giant_rat', 'name' => 'Giant Rat', 'level' => 0],
        ['creature_id' => 'orc_brute', 'name' => 'Orc Brute', 'level' => 2],
        ['creature_id' => 'hobgoblin_soldier', 'name' => 'Hobgoblin Soldier', 'level' => 3],
        ['creature_id' => 'ogre', 'name' => 'Ogre', 'level' => 3],
        ['creature_id' => 'troll', 'name' => 'Troll', 'level' => 5],
        ['creature_id' => 'stone_golem', 'name' => 'Stone Golem', 'level' => 8],
        ['creature_id' => 'iron_golem', 'name' => 'Iron Golem', 'level' => 13],
      ],
      'cave' => [
        ['creature_id' => 'giant_bat', 'name' => 'Giant Bat', 'level' => 0],
        ['creature_id' => 'giant_spider', 'name' => 'Giant Spider', 'level' => 1],
        ['creature_id' => 'cave_scorpion', 'name' => 'Cave Scorpion', 'level' => 1],
        ['creature_id' => 'darkmantle', 'name' => 'Darkmantle', 'level' => 2],
        ['creature_id' => 'cave_bear', 'name' => 'Cave Bear', 'level' => 3],
        ['creature_id' => 'basilisk', 'name' => 'Basilisk', 'level' => 5],
        ['creature_id' => 'roper', 'name' => 'Roper', 'level' => 8],
        ['creature_id' => 'purple_worm', 'name' => 'Purple Worm', 'level' => 13],
      ],
      'crypt' => [
        ['creature_id' => 'zombie_shambler', 'name' => 'Zombie Shambler', 'level' => 0],
        ['creature_id' => 'skeleton_guard', 'name' => 'Skeleton Guard', 'level' => 1],
        ['creature_id' => 'ghoul', 'name' => 'Ghoul', 'level' => 2],
        ['creature_id' => 'shadow', 'name' => 'Shadow', 'level' => 3],
        ['creature_id' => 'wight', 'name' => 'Wight', 'level' => 4],
        ['creature_id' => 'wraith', 'name' => 'Wraith', 'level' => 6],
        ['creature_id' => 'mummy_guardian', 'name' => 'Mummy Guardian', 'level' => 6],
        ['creature_id' => 'vampire_spawn', 'name' => 'Vampire Spawn', 'level' => 7],
        ['creature_id' => 'lich', 'name' => 'Lich', 'level' => 12],
      ],
      'ruins' => [
        ['creature_id' => 'vine_lasher', 'name' => 'Vine Lasher', 'level' => 0],
        ['creature_id' => 'animated_statue', 'name' => 'Animated Statue', 'level' => 2],
        ['creature_id' => 'guardian_naga', 'name' => 'Guardian Naga', 'level' => 5],
        ['creature_id' => 'stone_golem', 'name' => 'Stone Golem', 'level' => 8],
        ['creature_id' => 'shield_guardian', 'name' => 'Shield Guardian', 'level' => 10],
      ],
      'underground' => [
        ['creature_id' => 'giant_centipede', 'name' => 'Giant Centipede', 'level' => 0],
        ['creature_id' => 'cave_fisher', 'name' => 'Cave Fisher', 'level' => 2],
        ['creature_id' => 'rust_monster', 'name' => 'Rust Monster', 'level' => 3],
        ['creature_id' => 'xorn', 'name' => 'Xorn', 'level' => 6],
        ['creature_id' => 'umber_hulk', 'name' => 'Umber Hulk', 'level' => 8],
      ],
      'demonic' => [
        ['creature_id' => 'dretch', 'name' => 'Dretch', 'level' => 2],
        ['creature_id' => 'quasit', 'name' => 'Quasit', 'level' => 2],
        ['creature_id' => 'babau', 'name' => 'Babau', 'level' => 6],
        ['creature_id' => 'vrock', 'name' => 'Vrock', 'level' => 9],
        ['creature_id' => 'hezrou', 'name' => 'Hezrou', 'level' => 11],
        ['creature_id' => 'glabrezu', 'name' => 'Glabrezu', 'level' => 13],
      ],
      'underdark' => [
        ['creature_id' => 'drow_fighter', 'name' => 'Drow Fighter', 'level' => 2],
        ['creature_id' => 'drider', 'name' => 'Drider', 'level' => 6],
        ['creature_id' => 'drow_priestess', 'name' => 'Drow Priestess', 'level' => 8],
        ['creature_id' => 'mind_flayer', 'name' => 'Mind Flayer', 'level' => 10],
        ['creature_id' => 'beholder', 'name' => 'Beholder', 'level' => 13],
      ],
    ];

    $creatures = $catalog[$theme] ?? $catalog['dungeon'];

    // Filter to level range.
    return array_values(array_filter($creatures, function ($c) use ($level_range) {
      return $c['level'] >= $level_range['min'] && $c['level'] <= $level_range['max'];
    }));
  }

  /**
   * Generate a thematic encounter name.
   *
   * @param array $creatures
   *   Selected creatures.
   * @param string $theme
   *   Dungeon theme.
   *
   * @return string
   *   Encounter name.
   */
  private function generateEncounterName(array $creatures, string $theme): string {
    if (empty($creatures)) {
      return 'Empty Chamber';
    }

    // Find the highest-level creature as the "star".
    $star = $creatures[0];
    foreach ($creatures as $c) {
      if (($c['level'] ?? 0) > ($star['level'] ?? 0)) {
        $star = $c;
      }
    }

    $total_count = 0;
    foreach ($creatures as $c) {
      $total_count += $c['count'] ?? 1;
    }

    $star_name = $star['name'] ?? 'Unknown';

    if ($total_count === 1) {
      return sprintf('Lone %s', $star_name);
    }
    if (count($creatures) === 1 && $total_count > 1) {
      return sprintf('%s Pack (%d)', $star_name, $total_count);
    }

    return sprintf('%s and Allies', $star_name);
  }

  /**
   * Weighted random selection from an array.
   *
   * @param array $items
   *   Items to select from.
   * @param array $weights
   *   Weight for each item (same indices).
   *
   * @return mixed
   *   Selected item.
   */
  private function weightedPick(array $items, array $weights): mixed {
    $total_weight = array_sum($weights);
    if ($total_weight <= 0) {
      return $items[array_rand($items)];
    }

    $roll = $this->numberGeneration->rollRange(1, $total_weight);
    $cumulative = 0;
    foreach ($weights as $i => $w) {
      $cumulative += $w;
      if ($roll <= $cumulative) {
        return $items[$i];
      }
    }

    return end($items);
  }

}
