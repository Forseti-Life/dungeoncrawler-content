<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Generates balanced PF2e encounters for dungeon rooms.
 *
 * Responsible for:
 * - Calculating XP budgets based on party level and difficulty
 * - Selecting creatures from registry matching theme
 * - Scaling creatures to party level
 * - Building encounters within XP budget
 * - Validating threat levels
 *
 * @see /docs/dungeoncrawler/ROOM_DUNGEON_GENERATOR_ARCHITECTURE.md
 */
class EncounterGeneratorService {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The encounter balancer service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\EncounterBalancer
   */
  protected EncounterBalancer $encounterBalancer;

  /**
   * The schema loader service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\SchemaLoader
   */
  protected SchemaLoader $schemaLoader;

  /**
   * Number generation service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\NumberGenerationService
   */
  protected NumberGenerationService $numberGeneration;

  /**
   * Constructs an EncounterGeneratorService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param \Drupal\dungeoncrawler_content\Service\EncounterBalancer $encounter_balancer
   *   The encounter balancer service.
   * @param \Drupal\dungeoncrawler_content\Service\SchemaLoader $schema_loader
   *   The schema loader service.
   */
  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    EncounterBalancer $encounter_balancer,
    SchemaLoader $schema_loader,
    NumberGenerationService $number_generation
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler');
    $this->encounterBalancer = $encounter_balancer;
    $this->schemaLoader = $schema_loader;
    $this->numberGeneration = $number_generation;
  }

  /**
   * PF2e XP budget thresholds per encounter (4-PC baseline).
   *
   * @deprecated Use CharacterManager::ENCOUNTER_THREAT_TIERS for all new code.
   *
   * @var array
   */
  protected const XP_BUDGETS = [
    'trivial' => 40,
    'low' => 60,
    'moderate' => 80,
    'severe' => 120,
    'extreme' => 160,
  ];

  /**
   * Generate encounter for a room.
   *
   * Workflow:
   * 1. Determine XP budget based on party level and difficulty
   * 2. Select creatures from registry matching theme and level
   * 3. Scale creatures to party level
   * 4. Build encounter ensuring within XP budget
   * 5. Validate threat level
   *
   * @param array $context
   *   Encounter context:
   *   - party_level: int - Average party level (1-20)
   *   - party_size: int - Number of party members
   *   - party_composition: array - Class breakdown
   *   - depth: int - Dungeon level (drives difficulty)
   *   - theme: string - Dungeon theme (e.g., 'goblin_warrens')
   *   - difficulty: string - 'low', 'moderate', 'severe', or 'extreme'
   *   - room_type: string - 'corridor' or 'chamber' (affects spacing)
   *
   * @return array
   *   encounter.schema.json structure:
   *   {
   *     "encounter_id": "uuid",
   *     "type": "combat",
   *     "threat_level": "moderate",
   *     "xp_budget": {
   *       "target_xp": 200,
   *       "min_xp": 180,
   *       "max_xp": 220
   *     },
   *     "combatants": [
   *       { "creature_id": "uuid", "level": 5, "xp_value": 100 },
   *       ...
   *     ],
   *     "terrain_effects": [...]
   *   }
   *
   * @see /docs/dungeoncrawler/ROOM_DUNGEON_GENERATOR_ARCHITECTURE.md
   */
  public function generateEncounter(array $context): array {
    $this->logger->info('Generating encounter at party level @level for theme @theme', [
      '@level' => $context['party_level'],
      '@theme' => $context['theme'],
    ]);

    $party_level = $context['party_level'] ?? 3;
    $party_size = $context['party_size'] ?? 4;
    $difficulty = $context['difficulty'] ?? 'moderate';
    $theme = $context['theme'] ?? 'goblin_warrens';

    // Step 1: Calculate XP budget
    $budget = $this->calculateXpBudget($party_level, $party_size, $difficulty);

    // Step 2: Select creatures
    $creatures = $this->selectCreatures($context);

    if (empty($creatures)) {
      $this->logger->warning('No creatures found for theme @theme', [
        '@theme' => $theme,
      ]);
      return [];
    }

    // Step 3-4: Build encounter
    $encounter = $this->buildEncounter($context, $budget, $creatures);

    // Step 5: Validate (Phase 3)
    // $validated = $this->schemaLoader->validateEncounterData($encounter);

    return $encounter;
  }

  /**
   * Calculate XP budget for an encounter.
   *
   * Uses the PF2e canonical encounter budget system:
   * - Base budget for a 4-PC party from ENCOUNTER_THREAT_TIERS.
   * - Each PC above/below 4 adds/subtracts CHARACTER_ADJUSTMENT_XP (20 XP).
   *
   * @param int $party_level
   *   Average party level (1-20)
   * @param int $party_size
   *   Number of party members
   * @param string $difficulty
   *   'trivial', 'low', 'moderate', 'severe', or 'extreme'
   *
   * @return array
   *   Budget object:
   *   {
   *     "target_xp": int,
   *     "min_xp": int,
   *     "max_xp": int,
   *     "difficulty": string,
   *     "threat_level": string
   *   }
   *
   * @see /docs/dungeoncrawler/ROOM_DUNGEON_GENERATOR_ARCHITECTURE.md
   */
  protected function calculateXpBudget(
    int $party_level,
    int $party_size,
    string $difficulty
  ): array {
    $base_xp = CharacterManager::ENCOUNTER_THREAT_TIERS[$difficulty]
      ?? CharacterManager::ENCOUNTER_THREAT_TIERS['moderate'];

    $target_xp = CharacterManager::adjustBudgetForPartySize($base_xp, $party_size);

    // Allow ±15% variance for flexibility.
    $min_xp = (int) floor($target_xp * 0.85);
    $max_xp = (int) ceil($target_xp * 1.15);

    return [
      'target_xp' => $target_xp,
      'min_xp' => $min_xp,
      'max_xp' => $max_xp,
      'difficulty' => $difficulty,
      'threat_level' => $difficulty,
    ];
  }

  /**
   * Select creatures for encounter.
   *
   * Queries creature registry for theme-appropriate creatures
   * at or near party level.
   *
   * @param array $context
   *   Encounter context (theme, party_level, etc.)
   *
   * @return array
   *   Array of creature template objects (unscaled):
   *   [
   *     {
   *       "creature_id": "uuid",
   *       "name": "Goblin Fighter",
   *       "level": 1,
   *       "xp_value": 50,
   *       "theme_tags": ["goblin"]
   *     },
   *     ...
   *   ]
   */
  protected function selectCreatures(array $context): array {
    $theme = $context['theme'] ?? 'goblin_warrens';
    $party_level = $context['party_level'] ?? 3;

    // Phase 2: Return sample creatures for testing
    // Phase 3: Query content registry
    // SELECT * FROM dc_content_registry
    // WHERE content_type = 'creature'
    // AND (theme_tags LIKE '%theme%' OR level BETWEEN party_level-2 AND party_level+2)
    // ORDER BY level

    // Sample creatures for common themes.
    // Note: xp_value is NOT stored here — it is computed dynamically from
    // creature level vs. party level via CharacterManager::computeCreatureXp().
    $theme_creatures = [
      'goblin_warrens' => [
        ['creature_id' => 'goblin_warrior', 'name' => 'Goblin Warrior', 'level' => 1, 'max_hp' => 6],
        ['creature_id' => 'goblin_commando', 'name' => 'Goblin Commando', 'level' => 2, 'max_hp' => 18],
        ['creature_id' => 'hobgoblin_soldier', 'name' => 'Hobgoblin Soldier', 'level' => 3, 'max_hp' => 45],
      ],
      'fungal_caverns' => [
        ['creature_id' => 'violet_fungus', 'name' => 'Violet Fungus', 'level' => 2, 'max_hp' => 30],
        ['creature_id' => 'myceloid', 'name' => 'Myceloid', 'level' => 3, 'max_hp' => 40],
      ],
      'undead_crypts' => [
        ['creature_id' => 'skeleton_guard', 'name' => 'Skeleton Guard', 'level' => 1, 'max_hp' => 4],
        ['creature_id' => 'zombie_shambler', 'name' => 'Zombie Shambler', 'level' => 2, 'max_hp' => 20],
        ['creature_id' => 'wight', 'name' => 'Wight', 'level' => 4, 'max_hp' => 50],
      ],
    ];

    // Get creatures for theme (default to goblin_warrens)
    $creatures = $theme_creatures[$theme] ?? $theme_creatures['goblin_warrens'];

    // Filter by level range (party_level ± 2)
    $filtered = array_filter($creatures, function($creature) use ($party_level) {
      return abs($creature['level'] - $party_level) <= 2;
    });

    return !empty($filtered) ? array_values($filtered) : $creatures;
  }

  /**
   * Build encounter from creatures and budget.
   *
   * Selects and scales creatures to fit within XP budget.
   * XP cost per creature is computed dynamically via
   * CharacterManager::computeCreatureXp() — not stored on the creature stub.
   *
   * Creatures with delta > +4 (computeCreatureXp returns NULL) are skipped
   * as too dangerous (no defined XP value).
   *
   * @param array $context
   *   Encounter context
   * @param array $budget
   *   XP budget from calculateXpBudget()
   * @param array $creatures
   *   Available creatures from selectCreatures()
   *
   * @return array
   *   encounter.schema.json structure
   *
   * @see /docs/dungeoncrawler/ROOM_DUNGEON_GENERATOR_ARCHITECTURE.md
   */
  protected function buildEncounter(array $context, array $budget, array $creatures): array {
    $party_level = $context['party_level'] ?? 3;
    $target_xp = $budget['target_xp'];
    $max_xp = $budget['max_xp'];
    $current_xp = 0;
    $combatants = [];
    $rng = $this->createScopedRng($context, 'encounter_build');

    // Shuffle deterministically for seed-stable variety.
    $creatures = $this->shuffleDeterministic($creatures, $rng);

    // Pre-compute XP for each creature at this party level; drop undefined (delta > +4).
    $eligible = [];
    foreach ($creatures as $c) {
      $xp = CharacterManager::computeCreatureXp($c['level'] ?? 1, $party_level);
      if ($xp === NULL) {
        continue;
      }
      $c['xp_value'] = $xp;
      $eligible[] = $c;
    }

    if (empty($eligible)) {
      return [
        'xp_budget' => $budget,
        'actual_xp' => 0,
        'combatants' => [],
        'combatant_count' => 0,
      ];
    }

    // Add creatures until budget met.
    while ($current_xp < $target_xp && count($combatants) < 10) {
      // Pick a random creature.
      $creature = $rng->pick($eligible);

      // Check if adding would exceed max budget.
      if ($current_xp + $creature['xp_value'] > $max_xp) {
        // Try to find a smaller creature.
        $smaller = array_filter($eligible, function($c) use ($current_xp, $max_xp) {
          return $current_xp + $c['xp_value'] <= $max_xp;
        });

        if (empty($smaller)) {
          break;
        }

        $creature = $rng->pick(array_values($smaller));
      }

      // Add creature to encounter.
      $combatants[] = [
        'entity_type' => 'creature',
        'entity_ref' => $creature['creature_id'],
        'name' => $creature['name'] ?? $creature['creature_id'],
        'level' => $creature['level'] ?? 1,
        'xp_value' => $creature['xp_value'],
        'quantity' => 1,
        'placement_hint' => $this->getPlacementHint(count($combatants)),
        'max_hp' => $creature['max_hp'] ?? 20,
        'spawn_type' => 'permanent',
      ];

      $current_xp += $creature['xp_value'];
    }

    return [
      'xp_budget' => $budget,
      'actual_xp' => $current_xp,
      'threat_tier' => CharacterManager::classifyEncounterTier($current_xp),
      'combatants' => $combatants,
      'combatant_count' => count($combatants),
    ];
  }

  /**
   * Create deterministic RNG for encounter generation scope.
   */
  protected function createScopedRng(array $context, string $scope): SeededRandomSequence {
    $base_seed = isset($context['seed'])
      ? (int) $context['seed']
      : $this->numberGeneration->rollRange(1, 2147483647);

    return new SeededRandomSequence($base_seed ^ abs(crc32($scope)));
  }

  /**
   * Deterministic Fisher-Yates shuffle.
   */
  protected function shuffleDeterministic(array $items, SeededRandomSequence $rng): array {
    $count = count($items);
    for ($index = $count - 1; $index > 0; $index--) {
      $swap_index = $rng->nextInt(0, $index);
      $temp = $items[$index];
      $items[$index] = $items[$swap_index];
      $items[$swap_index] = $temp;
    }

    return $items;
  }

  /**
   * Get placement hint based on combatant index.
   *
   * @param int $index
   *   Combatant index
   *
   * @return string
   *   Placement hint
   */
  protected function getPlacementHint(int $index): string {
    $hints = ['scattered', 'center', 'back_corner', 'near_door'];

    // First creature at back corner (boss position)
    if ($index === 0) {
      return 'back_corner';
    }

    // Second creature near center
    if ($index === 1) {
      return 'center';
    }

    // Others scattered
    return 'scattered';
  }

}
