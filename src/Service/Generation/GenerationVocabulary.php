<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\Generation;

/**
 * Shared vocabulary for reconciled canonical and legacy runtime generation.
 */
final class GenerationVocabulary {

  public const ROOM_TYPES = [
    'corridor', 'chamber', 'cavern', 'hall', 'shrine', 'vault', 'lair', 'nest',
    'workshop', 'library', 'prison', 'throne_room', 'armory', 'pantry', 'garden',
    'pool', 'mine', 'crypt', 'laboratory', 'barracks', 'marketplace', 'arena',
    'boss_chamber', 'entrance', 'exit', 'stairwell', 'crossroads', 'dead_end',
    'trap_room', 'puzzle_room', 'vault_room', 'safe_room',
  ];

  public const SIZE_CATEGORIES = ['tiny', 'small', 'medium', 'large', 'huge', 'gargantuan'];

  public const CANONICAL_TERRAIN_TYPES = [
    'stone_floor', 'rough_stone', 'smooth_stone', 'dirt', 'mud', 'sand',
    'water_shallow', 'water_deep', 'ice', 'lava', 'fungal_growth', 'bone',
    'crystal', 'metal_grate', 'wooden_floor', 'carpet', 'rubble', 'void',
  ];

  public const LIGHTING_LEVELS = ['bright_light', 'dim_light', 'darkness', 'magical_darkness'];
  public const PLACEABLE_FAMILIES = ['creature', 'actor', 'item', 'obstacle', 'trap', 'hazard'];
  public const LINK_KINDS = ['hallway', 'archway', 'door', 'hatch', 'portcullis', 'secret_door', 'magical_barrier', 'collapsed', 'bridge', 'one_way_drop'];
  public const LINK_DIRECTIONS = ['bidirectional', 'one_way'];
  public const LINK_STATES = ['open', 'closed', 'locked', 'barred', 'trapped', 'triggered', 'destroyed'];
  public const ITEM_TYPES = ['weapon', 'armor', 'shield', 'consumable', 'potion', 'scroll', 'wand', 'talisman', 'worn_item', 'held_item', 'material', 'adventuring_gear', 'relic', 'artifact'];
  public const ITEM_RARITIES = ['common', 'uncommon', 'rare', 'epic', 'legendary'];
  public const CREATURE_TYPES = ['aberration', 'animal', 'astral', 'beast', 'celestial', 'construct', 'dragon', 'dream', 'elemental', 'ethereal', 'fey', 'fiend', 'fungus', 'giant', 'humanoid', 'monitor', 'ooze', 'plant', 'spirit', 'undead'];
  public const CREATURE_RARITIES = ['common', 'uncommon', 'rare', 'unique'];
  public const NPC_ATTITUDES = ['hostile', 'unfriendly', 'indifferent', 'friendly', 'helpful'];

  /**
   * Legacy runtime terrain choices by dungeon theme.
   *
   * Kept byte-for-byte compatible with DungeonGeneratorService's historical
   * choices so existing runtime generation remains stable while sharing the
   * vocabulary source with the canonical generation path.
   *
   * @return string[]
   */
  public static function runtimeTerrainOptionsForTheme(string $theme): array {
    $theme_terrains = [
      'dungeon' => ['stone_floor', 'cobblestone', 'flagstone'],
      'cave' => ['dirt', 'stone_rough', 'gravel'],
      'crypt' => ['stone_floor', 'flagstone', 'marble'],
      'ruins' => ['stone_rough', 'cobblestone', 'rubble', 'overgrown'],
      'underground' => ['dirt', 'stone_rough', 'mud'],
      'demonic' => ['obsidian', 'lava_rock', 'sulfur'],
      'underdark' => ['stone_rough', 'crystal', 'fungal'],
      'sewer' => ['mud', 'water_shallow', 'slime'],
      'mine' => ['stone_rough', 'gravel', 'ore_deposits'],
    ];

    return $theme_terrains[$theme] ?? ['stone_floor'];
  }

}
