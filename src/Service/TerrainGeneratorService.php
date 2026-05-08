<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Generates terrain and environmental features for dungeon hexes.
 *
 * Responsible for:
 * - Assigning terrain types to hexes
 * - Calculating elevation/depth variations
 * - Placing obstacles (walls, pillars, chasms)
 * - Creating thematic environmental features
 *
 * @see /docs/dungeoncrawler/ROOM_DUNGEON_GENERATOR_ARCHITECTURE.md
 */
class TerrainGeneratorService {

  /**
   * Available terrain types with properties.
   *
   * Maps to room.schema.json terrain.type enum values.
   *
   * @var array
   */
  protected static array $terrainTypes = [
    'stone_floor' => [
      'passable' => TRUE,
      'difficult_terrain' => FALSE,
      'description' => 'Solid stone floor',
    ],
    'rough_stone' => [
      'passable' => TRUE,
      'difficult_terrain' => TRUE,
      'description' => 'Rough, uneven stone',
    ],
    'smooth_stone' => [
      'passable' => TRUE,
      'difficult_terrain' => FALSE,
      'description' => 'Smooth stone floor',
    ],
    'sand' => [
      'passable' => TRUE,
      'difficult_terrain' => TRUE,
      'description' => 'Loose sand, slows movement',
    ],
    'crystal' => [
      'passable' => TRUE,
      'difficult_terrain' => FALSE,
      'description' => 'Glowing crystal formations',
    ],
    'lava' => [
      'passable' => FALSE,
      'difficult_terrain' => FALSE,
      'description' => 'Molten lava (hazardous)',
    ],
    'water_shallow' => [
      'passable' => TRUE,
      'difficult_terrain' => TRUE,
      'description' => 'Shallow water, difficult terrain',
    ],
    'water_deep' => [
      'passable' => TRUE,
      'difficult_terrain' => TRUE,
      'description' => 'Deep water, difficult terrain',
    ],
    'fungal_growth' => [
      'passable' => TRUE,
      'difficult_terrain' => TRUE,
      'description' => 'Fungus and moss, slippery',
    ],
    'bone' => [
      'passable' => TRUE,
      'difficult_terrain' => FALSE,
      'description' => 'Bone and skeletal remains',
    ],
    'ice' => [
      'passable' => TRUE,
      'difficult_terrain' => TRUE,
      'description' => 'Ice hazard',
    ],
    'void' => [
      'passable' => FALSE,
      'difficult_terrain' => FALSE,
      'description' => 'Chasm/void (impassable)',
    ],
  ];

  /**
   * Theme-to-terrain mappings.
   *
   * Maps themes to room.schema.json terrain.type enum values.
   *
   * @var array
   */
  protected static array $themeTerrains = [
    'goblin_warrens' => ['stone_floor', 'sand', 'fungal_growth'],
    'fungal_caverns' => ['rough_stone', 'fungal_growth', 'water_shallow'],
    'spider_nests' => ['rough_stone', 'bone', 'sand'],
    'undead_crypts' => ['smooth_stone', 'bone', 'fungal_growth'],
    'flooded_depths' => ['rough_stone', 'water_deep', 'water_shallow'],
    'lava_forge' => ['stone_floor', 'lava', 'crystal'],
    'shadow_realm' => ['smooth_stone', 'void', 'rough_stone'],
    'crystal_caves' => ['crystal', 'smooth_stone', 'ice'],
    'beast_den' => ['rough_stone', 'sand', 'fungal_growth'],
    'abandoned_mine' => ['rough_stone', 'sand', 'water_shallow'],
    'eldritch_library' => ['smooth_stone', 'crystal', 'bone'],
    'dragon_lair' => ['smooth_stone', 'lava', 'crystal'],
    'elemental_nexus' => ['crystal', 'lava', 'ice'],
    'demonic_sanctum' => ['smooth_stone', 'bone', 'void'],
    'ancient_ruins' => ['rough_stone', 'sand', 'fungal_growth'],
  ];

  /**
   * Generate terrain overrides for room hexes.
   *
   * Per room.schema.json: terrain is at room level, hexes have optional
   * terrain_override for per-hex variations.
   *
   * @param array $hexes
   *   Array of hex coordinates [{q: int, r: int}, ...]
   * @param string $theme
   *   Dungeon theme key
   * @param string $primary_terrain
   *   Primary terrain type for room (room.schema.json enum)
   * @param int $seed
   *   Random seed for reproducibility
   *
   * @return array
   *   Array of hex data matching room.schema.json hexes structure:
   *   [
   *     {
   *       "q": int,
   *       "r": int,
   *       "elevation_ft": float,
   *       "terrain_override": string (optional, only if differs from room terrain),
   *       "objects": array
   *     },
   *     ...
   *   ]
   */
  public function generateTerrain(
    array $hexes,
    string $theme,
    string $primary_terrain,
    int $seed
  ): array {
    $rng = $this->createScopedRng($seed, 'terrain');

    $terrain_options = static::$themeTerrains[$theme] ?? ['stone_floor'];

    // Ensure primary terrain is available
    if (!in_array($primary_terrain, $terrain_options, TRUE)) {
      $terrain_options[0] = $primary_terrain;
    }

    $result = [];

    foreach ($hexes as $hex) {
      $q = $hex['q'] ?? 0;
      $r = $hex['r'] ?? 0;

      $hex_data = [
        'q' => $q,
        'r' => $r,
        'elevation_ft' => $this->generateElevation($q, $r, $theme, $seed),
        'objects' => [],
      ];

      // 85% use room primary terrain (no override needed)
      // 15% use terrain_override for variation
      if ($rng->chance(15)) {
        $variations = array_filter($terrain_options, fn($t) => $t !== $primary_terrain);
        if (!empty($variations)) {
          $hex_data['terrain_override'] = $rng->pick(array_values($variations));
        }
      }

      $result[] = $hex_data;
    }

    return $result;
  }

  /**
   * Generate elevation for a hex.
   *
   * Creates natural-looking elevation patterns with valleys and peaks.
   *
   * @param int $q
   *   Hex Q coordinate
   * @param int $r
   *   Hex R coordinate
   * @param string $theme
   *   Dungeon theme
   * @param int $seed
   *   Random seed
   *
   * @return float
   *   Elevation in feet (-50 to 200)
   */
  protected function generateElevation(int $q, int $r, string $theme, int $seed): float {
    // Use Perlin-like noise based on coordinates and seed
    // For Phase 2, use simple pseudo-random based on hash
    $hash = abs(crc32(json_encode([$q, $r, $theme, $seed])));

    // Scale to -50 to 200 feet
    // Most dungeons have slight variations (0-20 ft)
    $elevation = (($hash % 1000) / 1000) * 20 - 10;

    // Add occasional major elevation changes (cliffs)
    if (($hash % 100) < 5) {
      $elevation += (($hash % 500) / 500) * 80 - 40;
    }

    return round($elevation, 1);
  }

  /**
   * Get terrain properties per room.schema.json.
   *
   * @param string $terrain_type
   *   Terrain type key (room.schema.json terrain.type enum)
   *
   * @return array
   *   Terrain properties:
   *   - passable: bool
   *   - difficult_terrain: bool
   *   - description: string
   */
  public function getTerrainProperties(string $terrain_type): array {
    return static::$terrainTypes[$terrain_type] ?? [
      'passable' => FALSE,
      'difficult_terrain' => FALSE,
      'description' => 'Unknown terrain',
    ];
  }

  /**
   * Check if terrain is passable.
   *
   * @param string $terrain_type
   *   Terrain type
   *
   * @return bool
   *   True if passable
   */
  public function isPassable(string $terrain_type): bool {
    return $this->getTerrainProperties($terrain_type)['passable'] ?? FALSE;
  }

  /**
   * Get passable terrain types for theme.
   *
   * @param string $theme
   *   Theme key
   *
   * @return array
   *   Array of passable terrain types for theme
   */
  public function getPassableTerrains(string $theme): array {
    $theme_terrains = static::$themeTerrains[$theme] ?? ['stone'];

    return array_filter($theme_terrains, fn($t) => $this->isPassable($t));
  }

  /**
   * Place obstacles in room.
   *
   * Adds hex_objects per room.schema.json hex_object definition.
   *
   * @param array $terrain
   *   Hex array from generateTerrain()
   * @param int $seed
   *   Random seed
   *
   * @return array
   *   Hex array with hex_object items added to objects property
   */
  public function placeObstacles(array $terrain, int $seed): array {
    $rng = $this->createScopedRng($seed, 'obstacles');

    foreach ($terrain as &$hex) {
      // 15% chance to place obstacle
      if ($rng->chance(15)) {
        $obstacle = $this->randomObstacle($rng);
        $hex['objects'][] = $obstacle;
      }
    }

    return $terrain;
  }

  /**
   * Generate random hex_object per room.schema.json.
   *
   * @return array
   *   hex_object matching room.schema.json definitions.hex_object:
   *   {
   *     "name": string,
   *     "type": enum,
   *     "provides_cover": enum,
   *     "blocks_movement": bool,
   *     "blocks_line_of_sight": bool,
   *     "description": string
   *   }
   */
  protected function randomObstacle(SeededRandomSequence $rng): array {
    // Use schema-compliant hex_object types
    $object_types = [
      [
        'type' => 'pillar',
        'name' => 'Stone Pillar',
        'description' => 'A sturdy stone pillar supporting the ceiling.',
        'blocks_movement' => TRUE,
        'blocks_line_of_sight' => FALSE,
        'provides_cover' => 'standard_cover',
      ],
      [
        'type' => 'rubble',
        'name' => 'Rubble Pile',
        'description' => 'A pile of broken stone and debris.',
        'blocks_movement' => FALSE,
        'blocks_line_of_sight' => FALSE,
        'provides_cover' => 'lesser_cover',
      ],
      [
        'type' => 'debris',
        'name' => 'Scattered Debris',
        'description' => 'Scattered rubble and broken materials.',
        'blocks_movement' => FALSE,
        'blocks_line_of_sight' => FALSE,
        'provides_cover' => 'none',
      ],
      [
        'type' => 'statue',
        'name' => 'Ancient Statue',
        'description' => 'A weathered statue of unknown origin.',
        'blocks_movement' => TRUE,
        'blocks_line_of_sight' => TRUE,
        'provides_cover' => 'greater_cover',
      ],
      [
        'type' => 'wall_segment',
        'name' => 'Crumbling Wall',
        'description' => 'A section of crumbling wall.',
        'blocks_movement' => TRUE,
        'blocks_line_of_sight' => TRUE,
        'provides_cover' => 'greater_cover',
      ],
    ];

    return $rng->pick($object_types);
  }

  /**
   * Create deterministic RNG for a terrain generation scope.
   */
  protected function createScopedRng(int $seed, string $scope): SeededRandomSequence {
    return new SeededRandomSequence($seed ^ abs(crc32($scope)));
  }

  /**
   * Generate room name suffix from terrain.
   *
   * @param string $primary_terrain
   *   Primary terrain type (room.schema.json terrain.type enum)
   *
   * @return string
   *   Terrain-based room name suffix
   */
  public function getTerrainNameSuffix(string $primary_terrain): string {
    $suffixes = [
      'stone_floor' => 'Stone Chamber',
      'rough_stone' => 'Rough Cavern',
      'smooth_stone' => 'Smooth Hall',
      'sand' => 'Sand Haven',
      'crystal' => 'Crystal Vault',
      'lava' => 'Magma Chamber',
      'water_shallow' => 'Shallow Pool',
      'water_deep' => 'Flooded Cavity',
      'fungal_growth' => 'Fungal Grotto',
      'bone' => 'Ossuary',
      'ice' => 'Frozen Hall',
      'void' => 'Abyssal Void',
    ];

    return $suffixes[$primary_terrain] ?? 'Chamber';
  }

}
