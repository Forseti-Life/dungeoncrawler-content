<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalDefinitionGenerationService;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalGenerationException;
use Drupal\dungeoncrawler_content\Service\Generation\EncounterGenerationRules;
use Drupal\dungeoncrawler_content\Service\Generation\RuntimeGenerationException;
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
 * Validation pair: no dedicated validator service; threat/budget checks are
 * enforced in-generator.
 *
 * Runtime generator scheduled for reconciliation into the canonical generation path (Board decision 2026-09-08, item 20260908-dc-editor-generation-tools). No new callers; use CanonicalGenerationService.
 * Runtime generation failures hard-fail with runtime_generation_failed; no generic
 * pool, cached fallback, or legacy generator on failure.
 *
 * Legacy generation entrypoint frozen by ADR-GEN-06: no new callers; use
 * CanonicalGenerationService.
 * Runtime generation failures hard-fail with runtime_generation_failed; no generic
 * pool, cached fallback, or legacy generator on failure.
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
   * Canonical published definition authority.
   *
   * @var \Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService
   */
  protected CanonicalDefinitionService $definitions;

  protected ?CanonicalDefinitionGenerationService $definitionGeneration;

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
    NumberGenerationService $number_generation,
    CanonicalDefinitionService $definitions,
    ?CanonicalDefinitionGenerationService $definition_generation = NULL
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler');
    $this->encounterBalancer = $encounter_balancer;
    $this->schemaLoader = $schema_loader;
    $this->numberGeneration = $number_generation;
    $this->definitions = $definitions;
    $this->definitionGeneration = $definition_generation;
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
   * Legacy generation entrypoint frozen by ADR-GEN-06: no new callers; use
   * CanonicalGenerationService.
   * Runtime generation failures hard-fail with runtime_generation_failed; no generic
   * pool, cached fallback, or legacy generator on failure.
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

    // Step 2: Select published canonical creature definitions.
    $creatures = $this->selectCreatures($context);

    // Step 3-4: Build encounter
    $encounter = $this->buildEncounter($context, $budget, $creatures);
    if (!empty($context['include_treasure']) || !empty($context['include_items'])) {
      $encounter['item_plan'] = $this->buildItemPlan($context);
      $encounter['population_plan'] = array_values(array_merge($encounter['population_plan'], $encounter['item_plan']));
    }

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
    return EncounterGenerationRules::xpBudget($party_level, $party_size, $difficulty);
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
    $difficulty = (string) ($context['difficulty'] ?? 'moderate');
    $level_band = EncounterGenerationRules::creatureLevelRange((int) $party_level, $difficulty);
    $tags_tried = $this->tagsTried($theme, $context);
    $creatures = $this->definitions->publishedDefinitions('creature', [
      'level_min' => $level_band['min'],
      'level_max' => $level_band['max'],
      'tags_any' => $tags_tried,
      'limit' => 100,
    ]);
    if ($creatures === [] && !empty($context['canonical_generation_wait'])) {
      $this->generateRuntimeCreatureDefinition($context, $tags_tried, (int) $party_level);
      $creatures = $this->definitions->publishedDefinitions('creature', [
        'level_min' => $level_band['min'],
        'level_max' => $level_band['max'],
        'tags_any' => $tags_tried,
        'limit' => 100,
      ]);
    }
    if ($creatures === []) {
      throw $this->selectionFailed($context, $this->calculateXpBudget((int) $party_level, (int) ($context['party_size'] ?? 4), $difficulty), $level_band, $tags_tried, 'No schema-valid published canonical creature definitions matched the encounter criteria.');
    }

    return array_map(static function (array $definition): array {
      $payload = is_array($definition['payload'] ?? NULL) ? $definition['payload'] : [];
      return [
        'creature_id' => (string) $definition['definition_id'],
        'definition_id' => (string) $definition['definition_id'],
        'definition_version' => (string) $definition['version'],
        'name' => (string) ($definition['label'] ?? $definition['definition_id']),
        'level' => (int) ($definition['level'] ?? 0),
        'max_hp' => (int) ($payload['pf2e_stats']['hp']['max'] ?? 20),
        'tags' => (array) ($definition['tags'] ?? []),
      ];
    }, $creatures);
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
      throw $this->selectionFailed($context, $budget, $this->levelBand($context), $this->tagsTried((string) ($context['theme'] ?? ''), $context), 'No published canonical creature definitions have a PF2e XP value for this party level.');
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
        'definition_id' => $creature['definition_id'],
        'definition_version' => $creature['definition_version'],
        'name' => $creature['name'] ?? $creature['creature_id'],
        'level' => $creature['level'] ?? 1,
        'xp_value' => $creature['xp_value'],
        'quantity' => 1,
        'placement_hint' => $this->getPlacementHint(count($combatants)),
        'placement_plan' => [
          'room_id' => (string) ($context['room_id'] ?? ''),
          'hex' => $this->planHex($context, count($combatants)),
          'disposition' => (string) ($context['disposition'] ?? 'hostile'),
        ],
        'max_hp' => $creature['max_hp'] ?? 20,
        'spawn_type' => 'permanent',
      ];

      $current_xp += $creature['xp_value'];
    }

    if ($current_xp < (int) $budget['min_xp']) {
      throw $this->selectionFailed($context, $budget, $this->levelBand($context), $this->tagsTried((string) ($context['theme'] ?? ''), $context), sprintf('Published canonical creature definitions could only fill %d XP of the requested minimum %d XP.', $current_xp, (int) $budget['min_xp']));
    }

    $population_plan = array_map(static fn(array $combatant): array => [
      'entity_type' => 'creature',
      'definition_id' => (string) $combatant['definition_id'],
      'definition_version' => (string) $combatant['definition_version'],
      'quantity' => (int) ($combatant['quantity'] ?? 1),
      'room_id' => (string) ($combatant['placement_plan']['room_id'] ?? ''),
      'hex' => $combatant['placement_plan']['hex'],
      'disposition' => (string) ($combatant['placement_plan']['disposition'] ?? 'hostile'),
    ], $combatants);

    return [
      'schema_version' => 'encounter_population_plan-v1',
      'xp_budget' => $budget,
      'actual_xp' => $current_xp,
      'threat_tier' => CharacterManager::classifyEncounterTier($current_xp),
      'combatants' => $combatants,
      'combatant_count' => count($combatants),
      'population_plan' => $population_plan,
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
    return EncounterGenerationRules::placementHint($index);
  }

  protected function planHex(array $context, int $index): array {
    $hexes = array_values(array_filter((array) ($context['hexes'] ?? []), 'is_array'));
    if ($hexes === []) {
      throw new RuntimeGenerationException('runtime_selection_failed', [[
        'code' => 'runtime_selection_failed',
        'pointer' => '/hexes',
        'message' => 'Room hexes are required to emit an encounter population plan.',
        'severity' => 'error',
      ]], 422);
    }
    $hex = $hexes[$index % count($hexes)];
    return ['q' => (int) ($hex['q'] ?? 0), 'r' => (int) ($hex['r'] ?? 0)];
  }

  protected function buildItemPlan(array $context): array {
    $party_level = (int) ($context['party_level'] ?? 1);
    $tags_tried = $this->tagsTried((string) ($context['theme'] ?? ''), $context);
    $items = $this->definitions->publishedDefinitions('item', [
      'level_min' => max(0, $party_level - 1),
      'level_max' => $party_level + 1,
      'tags_any' => $tags_tried,
      'limit' => 25,
    ]);
    if ($items === [] && !empty($context['canonical_generation_wait'])) {
      $this->generateRuntimeItemDefinition($context, $tags_tried, $party_level);
      $items = $this->definitions->publishedDefinitions('item', [
        'level_min' => max(0, $party_level - 1),
        'level_max' => $party_level + 1,
        'tags_any' => $tags_tried,
        'limit' => 25,
      ]);
    }
    if ($items === []) {
      throw $this->selectionFailed($context, $this->calculateXpBudget($party_level, (int) ($context['party_size'] ?? 4), (string) ($context['difficulty'] ?? 'moderate')), $this->levelBand($context), $tags_tried, 'No schema-valid published canonical item definitions matched the requested encounter item criteria.');
    }
    $rng = $this->createScopedRng($context, 'item_plan');
    $item = $rng->pick($items);
    return [[
      'entity_type' => 'item',
      'definition_id' => (string) $item['definition_id'],
      'definition_version' => (string) $item['version'],
      'quantity' => 1,
      'room_id' => (string) ($context['room_id'] ?? ''),
      'hex' => $this->planHex($context, 0),
      'disposition' => 'loot',
    ]];
  }

  protected function levelBand(array $context): array {
    return EncounterGenerationRules::creatureLevelRange((int) ($context['party_level'] ?? 3), (string) ($context['difficulty'] ?? 'moderate'));
  }

  protected function tagsTried(string $theme, array $context): array {
    $tags = array_values(array_filter(array_map(
      static fn($tag): string => strtolower(trim((string) $tag)),
      array_merge([$theme], (array) ($context['tags'] ?? []), (array) ($context['environment_tags'] ?? []))
    ), static fn(string $tag): bool => $tag !== ''));
    return array_values(array_unique($tags));
  }

  protected function selectionFailed(array $context, array $budget, array $level_band, array $tags_tried, string $message): RuntimeGenerationException {
    return new RuntimeGenerationException('runtime_selection_failed', [[
      'code' => 'runtime_selection_failed',
      'pointer' => '/encounter',
      'message' => $message,
      'severity' => 'error',
      'budget' => [
        'target_xp' => (int) ($budget['target_xp'] ?? 0),
        'min_xp' => (int) ($budget['min_xp'] ?? 0),
        'max_xp' => (int) ($budget['max_xp'] ?? 0),
        'difficulty' => (string) ($budget['difficulty'] ?? ($context['difficulty'] ?? 'moderate')),
      ],
      'level_band' => $level_band,
      'tags_tried' => $tags_tried,
    ]], 422);
  }

  protected function generateRuntimeCreatureDefinition(array $context, array $tags_tried, int $party_level): void {
    if (!$this->definitionGeneration) {
      throw new RuntimeGenerationException('runtime_generation_failed', [[
        'code' => 'runtime_generation_failed',
        'pointer' => '/canonical_definition_generation',
        'message' => 'Canonical definition generation service is required for explicit encounter definition generation.',
        'severity' => 'error',
      ]], 503);
    }
    try {
      $prompt = trim((string) ($context['definition_prompt'] ?? $context['prompt'] ?? ''));
      if ($prompt === '') {
        $prompt = sprintf('Create a level %d creature suitable for tags: %s.', $party_level, implode(', ', $tags_tried));
      }
      $this->definitionGeneration->generateRuntimeDefinitionAndSave('creature', [
        'prompt' => $prompt,
        'level' => $party_level,
        'role' => (string) ($context['role'] ?? 'encounter'),
        'seed' => isset($context['seed']) && is_int($context['seed']) ? $context['seed'] : NULL,
      ], (int) ($context['requested_by_uid'] ?? 0));
    }
    catch (CanonicalGenerationException $e) {
      throw new RuntimeGenerationException('runtime_generation_failed', $e->getFindings(), $e->httpStatus(), $e);
    }
    catch (\Throwable $e) {
      throw new RuntimeGenerationException('runtime_generation_failed', [[
        'code' => 'runtime_generation_failed',
        'pointer' => '/canonical_definition_generation/creature',
        'message' => $e->getMessage(),
        'severity' => 'error',
      ]], 500, $e);
    }
  }

  protected function generateRuntimeItemDefinition(array $context, array $tags_tried, int $party_level): void {
    if (!$this->definitionGeneration) {
      throw new RuntimeGenerationException('runtime_generation_failed', [[
        'code' => 'runtime_generation_failed',
        'pointer' => '/canonical_definition_generation',
        'message' => 'Canonical definition generation service is required for explicit encounter item generation.',
        'severity' => 'error',
      ]], 503);
    }
    try {
      $prompt = trim((string) ($context['item_prompt'] ?? $context['prompt'] ?? ''));
      if ($prompt === '') {
        $prompt = sprintf('Create a level %d encounter item suitable for tags: %s.', $party_level, implode(', ', $tags_tried));
      }
      $this->definitionGeneration->generateRuntimeDefinitionAndSave('item', [
        'prompt' => $prompt,
        'level' => max(0, $party_level),
        'rarity' => (string) ($context['rarity'] ?? 'common'),
        'seed' => isset($context['seed']) && is_int($context['seed']) ? $context['seed'] : NULL,
      ], (int) ($context['requested_by_uid'] ?? 0));
    }
    catch (CanonicalGenerationException $e) {
      throw new RuntimeGenerationException('runtime_generation_failed', $e->getFindings(), $e->httpStatus(), $e);
    }
    catch (\Throwable $e) {
      throw new RuntimeGenerationException('runtime_generation_failed', [[
        'code' => 'runtime_generation_failed',
        'pointer' => '/canonical_definition_generation/item',
        'message' => $e->getMessage(),
        'severity' => 'error',
      ]], 500, $e);
    }
  }

}
