<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Service for extracting combat-relevant data from item templates.
 *
 * Bridges the gap between item template storage (dungeoncrawler_content_registry)
 * and combat calculations (attack bonuses, damage, proficiency).
 *
 * This replaces hardcoded weapon constants by dynamically loading from database.
 */
class ItemCombatDataService {

  protected Connection $database;

  /**
   * Weapon category defaults based on common weapon types.
   */
  private const CATEGORY_DEFAULTS = [
    // Simple weapons
    'club' => 'simple',
    'dagger' => 'simple',
    'mace' => 'simple',
    'spear' => 'simple',
    'staff' => 'simple',
    'crossbow' => 'simple',
    'sling' => 'simple',
    
    // Martial weapons
    'longsword' => 'martial',
    'shortsword' => 'martial',
    'rapier' => 'martial',
    'scimitar' => 'martial',
    'battleaxe' => 'martial',
    'greataxe' => 'martial',
    'greatsword' => 'martial',
    'warhammer' => 'martial',
    'falchion' => 'martial',
    'longbow' => 'martial',
    'shortbow' => 'martial',
    'composite_longbow' => 'martial',
    'composite_shortbow' => 'martial',
  ];

  /**
   * Weapon group defaults.
   */
  private const GROUP_DEFAULTS = [
    'club' => 'club',
    'dagger' => 'knife',
    'mace' => 'club',
    'spear' => 'spear',
    'staff' => 'club',
    'longsword' => 'sword',
    'shortsword' => 'sword',
    'rapier' => 'sword',
    'scimitar' => 'sword',
    'battleaxe' => 'axe',
    'greataxe' => 'axe',
    'greatsword' => 'sword',
    'warhammer' => 'hammer',
    'falchion' => 'sword',
    'longbow' => 'bow',
    'shortbow' => 'bow',
    'composite_longbow' => 'bow',
    'composite_shortbow' => 'bow',
    'crossbow' => 'bow',
    'sling' => 'sling',
  ];

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * Get combat data for a weapon by item ID.
   *
   * @param string $item_id
   *   Item identifier (e.g., 'longsword', 'dagger').
   *
   * @return array|null
   *   Combat data array or NULL if not found:
   *   - name: Display name
   *   - damage: Damage dice (e.g., '1d8')
   *   - damage_type: Damage type ('slashing', 'piercing', 'bludgeoning')
   *   - category: Weapon proficiency category ('simple', 'martial', 'advanced', 'unarmed')
   *   - group: Weapon group ('sword', 'axe', 'bow', etc.)
   *   - hands: Number of hands required
   *   - traits: Array of weapon traits
   *   - range: Range increment in feet (NULL for melee)
   */
  public function getWeaponCombatData(string $item_id): ?array {
    // Query item template
    $query = $this->database->select('dungeoncrawler_content_registry', 'r')
      ->fields('r', ['name', 'schema_data'])
      ->condition('content_id', $item_id)
      ->condition('content_type', 'item');

    $result = $query->execute()->fetchAssoc();

    if (!$result) {
      return NULL;
    }

    $schema_data = json_decode($result['schema_data'], TRUE) ?? [];
    $weapon_stats = is_array($schema_data['weapon_stats'] ?? NULL)
      ? $schema_data['weapon_stats']
      : [];
    $item_type = $schema_data['item_type'] ?? NULL;

    if ($item_type !== 'weapon') {
      return NULL;
    }

    $damage_parts = $this->extractWeaponDamage($schema_data, $weapon_stats);
    $traits = $this->extractWeaponTraits($schema_data, $weapon_stats);
    $range = $this->extractWeaponRange($traits, $weapon_stats);

    // Determine category (simple/martial) with fallback
    $category = $weapon_stats['category'] ??
                $schema_data['weapon_category'] ??
                self::CATEGORY_DEFAULTS[$item_id] ?? 
                $this->inferCategoryFromTraits($traits);

    // Determine weapon group
    $group = $weapon_stats['group'] ??
             $schema_data['weapon_group'] ?? 
             self::GROUP_DEFAULTS[$item_id] ?? 
             'unknown';

    $normalized_traits = $this->normalizeTraits($traits);

    // PF2E reqs 2111/2112: determine Str modifier application mode.
    $has_thrown = FALSE;
    $has_propulsive = FALSE;
    foreach ($normalized_traits as $t) {
      $t_lower = strtolower($t);
      if (str_starts_with($t_lower, 'thrown')) {
        $has_thrown = TRUE;
      }
      if ($t_lower === 'propulsive') {
        $has_propulsive = TRUE;
      }
    }
    if ($has_thrown) {
      $damage_str_mode = 'full';
    }
    elseif ($has_propulsive) {
      $damage_str_mode = 'half_positive';
    }
    else {
      $damage_str_mode = 'none';
    }

    return [
      'name' => $result['name'] ?? ucfirst(str_replace('_', ' ', $item_id)),
      'damage' => $damage_parts['dice'] ?? '1d4',
      'damage_type' => $damage_parts['type'] ?? 'bludgeoning',
      'category' => $category,
      'group' => $group,
      'hands' => $this->normalizeHands($schema_data['hands'] ?? 1),
      'traits' => $normalized_traits,
      'range' => $range,
      'damage_str_mode' => $damage_str_mode,
    ];
  }

  /**
   * Extract weapon damage from canonical or legacy schema locations.
   */
  protected function extractWeaponDamage(array $schema_data, array $weapon_stats): array {
    $damage = $weapon_stats['damage'] ?? NULL;
    if (is_array($damage)) {
      $dice_count = max(1, (int) ($damage['dice_count'] ?? 1));
      $die_size = (string) ($damage['die_size'] ?? 'd4');
      $damage_type = strtolower((string) ($damage['damage_type'] ?? 'bludgeoning'));

      if (preg_match('/^d\d+$/', $die_size)) {
        return [
          'dice' => $dice_count . $die_size,
          'type' => $damage_type !== '' ? $damage_type : 'bludgeoning',
        ];
      }
    }

    return $this->parseDamageString((string) ($schema_data['damage'] ?? ''));
  }

  /**
   * Extract weapon traits from canonical and legacy schema locations.
   */
  protected function extractWeaponTraits(array $schema_data, array $weapon_stats): array {
    $traits = $schema_data['traits'] ?? [];
    if (!is_array($traits)) {
      $traits = [];
    }

    $weapon_traits = $weapon_stats['weapon_traits'] ?? [];
    if (!is_array($weapon_traits)) {
      $weapon_traits = [];
    }

    return array_values(array_unique(array_merge($traits, $weapon_traits)));
  }

  /**
   * Extract weapon range from canonical stats or legacy traits.
   */
  protected function extractWeaponRange(array $traits, array $weapon_stats): ?string {
    if (array_key_exists('range', $weapon_stats) && $weapon_stats['range'] !== NULL) {
      $range = (int) $weapon_stats['range'];
      if ($range > 0) {
        return $range . ' feet';
      }
    }

    return $this->extractRangeFromTraits($traits);
  }

  /**
   * Get combat data for multiple weapons.
   *
   * @param array $item_ids
   *   Array of item IDs.
   *
   * @return array
   *   Keyed array of combat data, keyed by item_id.
   */
  public function getWeaponsCombatData(array $item_ids): array {
    $weapons = [];
    foreach ($item_ids as $item_id) {
      $weapon_data = $this->getWeaponCombatData($item_id);
      if ($weapon_data) {
        $weapons[$item_id] = $weapon_data;
      }
    }
    return $weapons;
  }

  /**
   * Parse damage string into dice and type.
   *
   * Examples:
   *   "1d8 slashing" → ['dice' => '1d8', 'type' => 'slashing']
   *   "2d6 piercing" → ['dice' => '2d6', 'type' => 'piercing']
   *
   * @param string $damage_string
   *   Damage string from template.
   *
   * @return array
   *   Array with 'dice' and 'type' keys.
   */
  protected function parseDamageString(string $damage_string): array {
    $damage_string = trim($damage_string);
    
    if (empty($damage_string)) {
      return ['dice' => '1d4', 'type' => 'bludgeoning'];
    }

    // Match pattern: "1d8 slashing" or "2d6 piercing"
    if (preg_match('/^(\d+d\d+)\s+(\w+)$/i', $damage_string, $matches)) {
      return [
        'dice' => $matches[1],
        'type' => strtolower($matches[2]),
      ];
    }

    // Try just dice: "1d8"
    if (preg_match('/^(\d+d\d+)$/i', $damage_string, $matches)) {
      return [
        'dice' => $matches[1],
        'type' => 'bludgeoning', // Default
      ];
    }

    // Fallback
    return ['dice' => '1d4', 'type' => 'bludgeoning'];
  }

  /**
   * Extract range from weapon traits.
   *
   * Looks for traits like "thrown_10ft", "range_100ft", etc.
   *
   * @param array $traits
   *   Weapon traits.
   *
   * @return string|null
   *   Range string (e.g., "100 feet") or NULL for melee.
   */
  protected function extractRangeFromTraits(array $traits): ?string {
    foreach ($traits as $trait) {
      // Match "thrown_10ft", "range_100ft", etc.
      if (preg_match('/(?:thrown|range)[_\s]?(\d+)[_\s]?(?:ft|feet)/i', $trait, $matches)) {
        return $matches[1] . ' feet';
      }
    }

    return NULL;
  }

  /**
   * Normalize hands values from schema strings like "1+".
   */
  protected function normalizeHands($hands): int {
    if (is_int($hands)) {
      return $hands;
    }

    $hands = trim((string) $hands);
    return match ($hands) {
      '0' => 0,
      '1', '1+' => 1,
      '2' => 2,
      default => 1,
    };
  }

  /**
   * Infer weapon category from traits.
   *
   * @param array $traits
   *   Weapon traits.
   *
   * @return string
   *   Category: 'simple' or 'martial'.
   */
  protected function inferCategoryFromTraits(array $traits): string {
    // Traits like "agile", "finesse" are common on simple weapons
    $simple_indicators = ['agile', 'finesse', 'thrown'];
    
    foreach ($traits as $trait) {
      $trait_lower = strtolower($trait);
      if (in_array($trait_lower, $simple_indicators, TRUE)) {
        return 'simple';
      }
    }

    // Default to martial for unknown weapons
    return 'martial';
  }

  /**
   * Normalize trait names for consistency.
   *
   * Converts "thrown_10ft" → "Thrown 10 ft", "versatile_p" → "Versatile P".
   *
   * @param array $traits
   *   Raw trait strings.
   *
   * @return array
   *   Normalized trait strings.
   */
  protected function normalizeTraits(array $traits): array {
    $normalized = [];

    foreach ($traits as $trait) {
      // Convert underscores to spaces
      $trait = str_replace('_', ' ', $trait);
      
      // Capitalize words
      $trait = ucwords($trait);
      
      $normalized[] = $trait;
    }

    return $normalized;
  }

  /**
   * Get unarmed strike data.
   *
   * Every character has this by default.
   *
   * @return array
   *   Combat data for fist/unarmed strike.
   */
  public function getUnarmedStrikeData(): array {
    return [
      'name' => 'Fist',
      'damage' => '1d4',
      'damage_type' => 'bludgeoning',
      'category' => 'unarmed',
      'group' => 'brawling',
      'hands' => 0,
      'traits' => ['Agile', 'Finesse', 'Nonlethal', 'Unarmed'],
      'range' => NULL,
    ];
  }

}
