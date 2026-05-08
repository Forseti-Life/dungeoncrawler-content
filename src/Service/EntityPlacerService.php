<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Places entities (creatures, items, traps, hazards) on hex grid.
 *
 * Responsible for:
 * - Finding valid hex placements avoiding collisions
 * - Checking line-of-sight from room entrance
 * - Creating entity_instance objects with placement
 * - Validating placement against room boundaries
 *
 * @see /docs/dungeoncrawler/ROOM_DUNGEON_GENERATOR_ARCHITECTURE.md
 */
class EntityPlacerService {

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
   * The schema loader service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\SchemaLoader
   */
  protected SchemaLoader $schemaLoader;

  /**
   * The hex utility service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\HexUtilityService
   */
  protected HexUtilityService $hexUtility;

  /**
   * The terrain generator service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\TerrainGeneratorService
   */
  protected TerrainGeneratorService $terrainGenerator;

  /**
   * Number generation service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\NumberGenerationService
   */
  protected NumberGenerationService $numberGeneration;

  /**
   * The character tracking service (optional).
   *
   * @var \Drupal\dungeoncrawler_content\Service\CharacterTrackingService|null
   */
  protected ?CharacterTrackingService $characterTracking;

  /**
   * Constructs an EntityPlacerService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param \Drupal\dungeoncrawler_content\Service\SchemaLoader $schema_loader
   *   The schema loader service.
   * @param \Drupal\dungeoncrawler_content\Service\HexUtilityService $hex_utility
   *   The hex utility service.
   * @param \Drupal\dungeoncrawler_content\Service\TerrainGeneratorService $terrain_generator
   *   The terrain generator service.
   * @param \Drupal\dungeoncrawler_content\Service\CharacterTrackingService $character_tracking
   *   The character tracking service (optional).
   */
  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    SchemaLoader $schema_loader,
    HexUtilityService $hex_utility,
    TerrainGeneratorService $terrain_generator,
    NumberGenerationService $number_generation,
    CharacterTrackingService $character_tracking = NULL
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler');
    $this->schemaLoader = $schema_loader;
    $this->hexUtility = $hex_utility;
    $this->terrainGenerator = $terrain_generator;
    $this->numberGeneration = $number_generation;
    $this->characterTracking = $character_tracking;
  }

  /**
   * Place entities in a room.
   *
   * Orchestrates entity placement respecting:
   * - Passability (no walls, cliffs)
   * - No collisions with other entities
   * - Line-of-sight from entrance (visibility)
   * - Placement hints (near_door, center, back_corner, scattered)
   *
   * @param array $entities
   *   Array of entities to place:
   *   [
   *     {
   *       "entity_type": "creature|item|obstacle|trap|hazard",
   *       "entity_ref": "creature_id_or_item_id",
   *       "quantity": 1,
   *       "placement_hint": "near_door|center|back_corner|scattered"
   *     },
   *     ...
   *   ]
   *
   * @param array $hexes
   *   Room hex data:
   *   [
   *     {
   *       "q": 0,
   *       "r": 0,
   *       "terrain": "stone",
   *       "elevation_ft": 0,
   *       "objects": []
   *     },
   *     ...
   *   ]
   *
   * @param array $context
   *   Placement context:
   *   - campaign_id: int
   *   - room_id: string
   *   - dungeon_id: int
   *   - theme: string
   *   - party_level: int
   *
   * @return array
   *   Array of entity_instance.schema.json objects:
   *   [
   *     {
   *       "entity_id": "uuid",
   *       "entity_type": "creature",
   *       "entity_ref": "goblin_fighter_1",
   *       "placement": {
   *         "hex": {"q": 2, "r": 1},
   *         "direction": "north",
   *         "height_above_ground": 0
   *       },
   *       "state": {"active": true, "hidden": false, ...}
   *     },
   *     ...
   *   ]
   *
   * @see /docs/dungeoncrawler/ROOM_DUNGEON_GENERATOR_ARCHITECTURE.md
   */
  public function placeEntities(array $entities, array $hexes, array $context): array {
    $this->logger->info('Placing @count entities in room @room', [
      '@count' => count($entities),
      '@room' => $context['room_id'] ?? 'unknown',
    ]);

    $placed_entities = [];
    $occupied_hexes = [];
    $rng = $this->createScopedRng($context, 'entity_placement');

    // Get entrance hex (assumed to be center or first hex for now)
    $entrance_hex = $hexes[0] ?? ['q' => 0, 'r' => 0];

    // Build blocking hexes for LOS calculations
    $blocking_hexes = $this->getBlockingHexes($hexes);

    // Check for reusable characters if tracking enabled and requested.
    $reusable_characters = [];
    if ($this->characterTracking && ($context['allow_character_reuse'] ?? TRUE)) {
      $reusable_characters = $this->getReusableCharacters($context);
    }

    foreach ($entities as $entity) {
      $placement_hint = $entity['placement_hint'] ?? 'scattered';
      $entity_type = $entity['entity_type'] ?? 'creature';
      $quantity = $entity['quantity'] ?? 1;

      for ($i = 0; $i < $quantity; $i++) {
        // Try to reuse an existing character first (20% chance per creature).
        $entity_instance = NULL;
        if (!empty($reusable_characters) && $entity_type === 'creature' && $rng->chance(20)) {
          $entity_instance = $this->reuseCharacter($reusable_characters, $entity, $context);
        }

        // Find valid hex placement
        $hex = $this->findValidHex($placement_hint, $hexes, $occupied_hexes, $context, $rng);

        if ($hex === NULL) {
          $this->logger->warning('Could not find valid placement for entity @type', [
            '@type' => $entity_type,
          ]);
          continue;
        }

        // Create new entity_instance if not reusing.
        if ($entity_instance === NULL) {
          $entity_instance = $this->createEntityInstance(
            $entity,
            $hex,
            $context,
            $rng
          );
        }
        else {
          // Update placement for reused character.
          $entity_instance['placement']['hex'] = $hex;
        }

        $placed_entities[] = $entity_instance;
        $occupied_hexes[] = $hex;
      }
    }

    $this->logger->info('Successfully placed @count entities', [
      '@count' => count($placed_entities),
    ]);

    return $placed_entities;
  }

  /**
   * Find valid hex placement for a single entity.
   *
   * Respects:
   * - Passability (terrain type not impassable)
   * - No collisions with other entities
   * - Placement hint preferences (near_door, center, etc.)
   *
   * @param string $placement_hint
   *   'near_door', 'center', 'back_corner', or 'scattered'
   * @param array $hexes
   *   Room hexes
   * @param array $occupied_hexes
   *   Already-placed entity coordinates: [['q' => 0, 'r' => 0], ...]
   * @param array $context
   *   Placement context
   *
   * @return array|null
   *   Valid hex coordinate {'q': int, 'r': int} or null if no valid placement
   *
   * @see /docs/dungeoncrawler/ROOM_DUNGEON_GENERATOR_ARCHITECTURE.md
   */
  protected function findValidHex(
    string $placement_hint,
    array $hexes,
    array $occupied_hexes,
    array $context,
    SeededRandomSequence $rng
  ): ?array {
    // Filter passable, unoccupied hexes
    $valid_hexes = array_filter($hexes, function($hex) use ($occupied_hexes) {
      // Check passability
      if (!$this->isPassable($hex)) {
        return FALSE;
      }

      // Check if already occupied
      foreach ($occupied_hexes as $occupied) {
        if ($occupied['q'] === $hex['q'] && $occupied['r'] === $hex['r']) {
          return FALSE;
        }
      }

      return TRUE;
    });

    if (empty($valid_hexes)) {
      return NULL;
    }

    // Apply placement hint logic
    $center = ['q' => 0, 'r' => 0];

    switch ($placement_hint) {
      case 'center':
        // Sort by distance to center (ascending)
        usort($valid_hexes, function($a, $b) use ($center) {
          return $this->hexUtility->distance($a, $center) <=> $this->hexUtility->distance($b, $center);
        });
        // Return one of closest 3 hexes
        $candidates = array_slice($valid_hexes, 0, min(3, count($valid_hexes)));
        return $rng->pick(array_values($candidates));

      case 'back_corner':
        // Sort by distance to center (descending)
        usort($valid_hexes, function($a, $b) use ($center) {
          return $this->hexUtility->distance($b, $center) <=> $this->hexUtility->distance($a, $center);
        });
        // Return one of farthest 3 hexes
        $candidates = array_slice($valid_hexes, 0, min(3, count($valid_hexes)));
        return $rng->pick(array_values($candidates));

      case 'near_door':
        // Sort by distance to center (ascending)
        usort($valid_hexes, function($a, $b) use ($center) {
          return $this->hexUtility->distance($a, $center) <=> $this->hexUtility->distance($b, $center);
        });
        // Return one of closest 5 hexes
        $candidates = array_slice($valid_hexes, 0, min(5, count($valid_hexes)));
        return $rng->pick(array_values($candidates));

      case 'scattered':
      default:
        // Random placement
        return $rng->pick(array_values($valid_hexes));
    }
  }

  /**
   * Calculate line-of-sight from room entrance to target hex.
   *
   * Uses Bresenham-like algorithm for hex grids.
   * Creatures in line-of-sight are visible from entrance.
   *
   * @param array $from_hex
   *   Source hex {'q': int, 'r': int}
   * @param array $to_hex
   *   Target hex {'q': int, 'r': int}
   * @param array $blocking_hexes
   *   Hexes that block line-of-sight: [['q' => 0, 'r' => 0], ...]
   *
   * @return bool
   *   True if line-of-sight exists
   *
   * @see /docs/dungeoncrawler/ROOM_DUNGEON_GENERATOR_ARCHITECTURE.md
   */
  protected function hasLineOfSight(array $from_hex, array $to_hex, array $blocking_hexes): bool {
    // Use HexUtilityService line drawing
    $line_hexes = $this->hexUtility->getLine($from_hex, $to_hex);

    // Check if any hex in line is blocking
    foreach ($line_hexes as $line_hex) {
      // Skip source and target
      if (($line_hex['q'] === $from_hex['q'] && $line_hex['r'] === $from_hex['r']) ||
          ($line_hex['q'] === $to_hex['q'] && $line_hex['r'] === $to_hex['r'])) {
        continue;
      }

      // Check if this hex blocks LOS
      foreach ($blocking_hexes as $blocking) {
        if ($blocking['q'] === $line_hex['q'] && $blocking['r'] === $line_hex['r']) {
          return FALSE;
        }
      }
    }

    return TRUE;
  }

  /**
   * Check if hex is passable.
   *
   * @param array $hex
   *   Hex object
   *
   * @return bool
   *   True if passable by entities
   */
  protected function isPassable(array $hex): bool {
    // Check if hex has terrain_override
    if (isset($hex['terrain_override'])) {
      if (!$this->terrainGenerator->isPassable($hex['terrain_override'])) {
        return FALSE;
      }
    }

    // Check if any objects block movement
    if (isset($hex['objects']) && is_array($hex['objects'])) {
      foreach ($hex['objects'] as $object) {
        if (isset($object['blocks_movement']) && $object['blocks_movement'] === TRUE) {
          return FALSE;
        }
      }
    }

    return TRUE;
  }

  /**
   * Calculate hex distance.
   *
   * Uses axial coordinate distance for hex grids.
   * Distance = max(|Δq|, |Δr|, |Δq+Δr|)
   *
   * @param array $hex1
   *   First hex {'q': int, 'r': int}
   * @param array $hex2
   *   Second hex {'q': int, 'r': int}
   *
   * @return int
   *   Distance in hexes
   */
  protected function hexDistance(array $hex1, array $hex2): int {
    // Delegate to HexUtilityService
    return $this->hexUtility->distance($hex1, $hex2);
  }

  /**
   * Get hexes that block line of sight.
   *
   * @param array $hexes
   *   Room hexes
   *
   * @return array
   *   Array of blocking hex coordinates
   */
  protected function getBlockingHexes(array $hexes): array {
    $blocking = [];

    foreach ($hexes as $hex) {
      // Check if any objects block line of sight
      if (isset($hex['objects']) && is_array($hex['objects'])) {
        foreach ($hex['objects'] as $object) {
          if (isset($object['blocks_line_of_sight']) && $object['blocks_line_of_sight'] === TRUE) {
            $blocking[] = ['q' => $hex['q'], 'r' => $hex['r']];
            break;
          }
        }
      }
    }

    return $blocking;
  }

  /**
   * Create entity_instance per entity_instance.schema.json.
   *
   * @param array $entity
   *   Entity definition
   * @param array $hex
   *   Placement hex
   * @param array $context
   *   Generation context
   *
   * @return array
   *   entity_instance.schema.json compliant object
   */
  protected function createEntityInstance(array $entity, array $hex, array $context, SeededRandomSequence $rng): array {
    $entity_type = $entity['entity_type'] ?? 'creature';
    $entity_ref = $entity['entity_ref'] ?? 'unknown';

    // Generate UUID (simplified for Phase 2)
    $entity_instance_id = sprintf(
      'entity_%s_%d_%d',
      $entity_type,
      $hex['q'],
      $hex['r']
    );

    // Map numeric facing (0-5) to hex direction string for hexmap.js
    $facing_int = $rng->nextInt(0, 5);
    $facing_directions = ['n', 'ne', 'se', 's', 'sw', 'nw'];
    $orientation = $facing_directions[$facing_int] ?? 'n';

    // Resolve display name from entity data
    $display_name = $entity['name'] ?? $entity_ref;

    // Build PF2e-compatible stats based on creature level
    $creature_level = $entity['level'] ?? 1;
    $max_hp = $entity['max_hp'] ?? (10 + ($creature_level * 5));
    $ac = 14 + $creature_level;
    $attack_bonus = 4 + $creature_level;
    $perception = 2 + $creature_level;
    $speed = $entity['speed'] ?? 25;

    // Build entity_instance per schema, with state.metadata for hexmap.js ECS mapping
    $instance = [
      'schema_version' => '1.0.0',
      'entity_instance_id' => $entity_instance_id,
      'instance_id' => $entity_instance_id,
      'entity_type' => $entity_type,
      'display_name' => $display_name,
      'entity_ref' => [
        'content_type' => $entity_type,
        'content_id' => $entity_ref,
        'version' => NULL,
      ],
      'placement' => [
        'room_id' => $context['room_id'] ?? 'unknown',
        'hex' => [
          'q' => $hex['q'],
          'r' => $hex['r'],
        ],
        'elevation' => 0,
        'facing' => $facing_int,
        'orientation' => $orientation,
        'spawn_type' => $entity['spawn_type'] ?? 'permanent',
      ],
      'state' => [
        'active' => TRUE,
        'destroyed' => FALSE,
        'disabled' => FALSE,
        'hidden' => FALSE,
        'collected' => FALSE,
        'hit_points' => NULL,
        'inventory' => [],
        'metadata' => [
          'display_name' => $display_name,
          'name' => $display_name,
          'team' => $entity['team'] ?? ($entity_type === 'creature' ? 'enemy' : 'neutral'),
          'orientation' => $orientation,
          'movement_speed' => $speed,
          'actions_per_turn' => 3,
          'initiative_bonus' => $creature_level + 1,
          'stats' => [
            'speed' => $speed,
            'maxHp' => $max_hp,
            'currentHp' => $max_hp,
            'ac' => $ac,
            'perception' => $perception,
            'initiative_bonus' => $creature_level + 1,
            'attack_bonus' => $attack_bonus,
          ],
        ],
      ],
      'created_at' => date('c'),
      'updated_at' => date('c'),
    ];

    // Add hit points for creatures
    if ($entity_type === 'creature' && $max_hp > 0) {
      $instance['state']['hit_points'] = [
        'current' => $max_hp,
        'max' => $max_hp,
      ];
    }

    return $instance;
  }

  /**
   * Get reusable characters from campaign tracking.
   *
   * @param array $context
   *   Context including campaign_id, location preferences.
   *
   * @return array
   *   Array of reusable character records.
   */
  protected function getReusableCharacters(array $context): array {
    if (!$this->characterTracking) {
      return [];
    }

    try {
      $campaign_id = $context['campaign_id'] ?? 0;
      $location_type = $context['location_type'] ?? 'dungeon';

      // Determine disposition filter based on context.
      $disposition_filter = ['hostile', 'suspicious', 'neutral'];
      if ($location_type === 'tavern' || $location_type === 'town') {
        $disposition_filter = ['friendly', 'grateful', 'neutral', 'curious'];
      }

      $characters = $this->characterTracking->findReusableCharacters([
        'campaign_id' => $campaign_id,
        'location_type' => $location_type,
        'disposition_filter' => $disposition_filter,
        'max_count' => 10,
      ]);

      $this->logger->info('Found @count reusable characters for campaign @campaign', [
        '@count' => count($characters),
        '@campaign' => $campaign_id,
      ]);

      return $characters;
    }
    catch (\Exception $e) {
      $this->logger->warning('Failed to fetch reusable characters: @error', [
        '@error' => $e->getMessage(),
      ]);
      return [];
    }
  }

  /**
   * Reuse an existing character from campaign tracking.
   *
   * @param array $reusable_characters
   *   Available characters to reuse (passed by reference, will be modified).
   * @param array $entity
   *   Entity spec being placed.
   * @param array $context
   *   Placement context.
   *
   * @return array|null
   *   Entity instance from reused character, or NULL if none suitable.
   */
  protected function reuseCharacter(array &$reusable_characters, array $entity, array $context): ?array {
    if (empty($reusable_characters)) {
      return NULL;
    }

    // Find a matching character (same entity_id or compatible type).
    $entity_ref = $entity['entity_ref'] ?? null;
    $matching_index = NULL;

    foreach ($reusable_characters as $index => $character) {
      // Check if entity_id matches or is compatible.
      if ($character['entity_id'] === $entity_ref) {
        $matching_index = $index;
        break;
      }
    }

    // If no exact match, try first available character.
    if ($matching_index === NULL && !empty($reusable_characters)) {
      $matching_index = array_key_first($reusable_characters);
    }

    if ($matching_index === NULL) {
      return NULL;
    }

    // Get the character and remove from available pool.
    $character = $reusable_characters[$matching_index];
    unset($reusable_characters[$matching_index]);
    $reusable_characters = array_values($reusable_characters); // Re-index

    // Record reappearance.
    if ($this->characterTracking && isset($character['character_id'])) {
      $this->characterTracking->recordReappearance($character['character_id'], [
        'room_id' => $context['room_id'] ?? 'unknown',
        'outcome' => 'reappeared',
      ]);

      $this->logger->info('Reusing character @id (@entity_id) in room @room', [
        '@id' => $character['character_id'],
        '@entity_id' => $character['entity_id'],
        '@room' => $context['room_id'] ?? 'unknown',
      ]);
    }

    // Return the entity_instance from the character.
    return $character['entity_instance'];
  }

  /**
   * Create deterministic RNG for entity placement scope.
   */
  protected function createScopedRng(array $context, string $scope): SeededRandomSequence {
    $base_seed = isset($context['seed'])
      ? (int) $context['seed']
      : $this->numberGeneration->rollRange(1, 2147483647);

    return new SeededRandomSequence($base_seed ^ abs(crc32($scope)));
  }

}
