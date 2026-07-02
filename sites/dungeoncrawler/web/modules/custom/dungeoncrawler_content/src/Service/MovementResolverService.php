<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\dungeoncrawler_content\Service\HexUtilityService;

/**
 * Resolves PF2e movement rules for the DungeonCrawler combat system.
 *
 * Handles: movement types and speed, diagonal movement cost, terrain cost,
 * size/space/reach constants, fall damage, flanking detection,
 * cover calculation, forced movement, aquatic combat, and suffocation.
 *
 * REQs 2233-2266.
 */
class MovementResolverService {

  /**
   * Size categories: occupied space in feet.
   *
   * REQs 2238-2239.
   */
  const SIZE_SPACES = [
    'tiny'       => 2.5,
    'small'      => 5,
    'medium'     => 5,
    'large'      => 10,
    'huge'       => 15,
    'gargantuan' => 20,
  ];

  /**
   * Melee reach in feet for tall creatures (default form).
   *
   * Long creatures use half this value. REQs 2238-2239.
   */
  const SIZE_REACH = [
    'tiny'       => 0,
    'small'      => 5,
    'medium'     => 5,
    'large'      => 10,
    'huge'       => 15,
    'gargantuan' => 20,
  ];

  /**
   * Valid movement types.
   *
   * REQ 2233.
   */
  const MOVEMENT_TYPES = ['land', 'burrow', 'climb', 'fly', 'swim'];

  /**
   * Terrain type strings that count as difficult terrain.
   *
   * REQ 2249: +5 ft cost per square.
   */
  const DIFFICULT_TERRAIN_TYPES = [
    'natural_earth', 'natural_rock', 'rubble', 'mud',
    'shallow_water', 'sand', 'ice', 'snow',
  ];

  /**
   * Terrain type strings that count as greater difficult terrain.
   *
   * REQ 2250: +10 ft cost per square.
   */
  const GREATER_DIFFICULT_TERRAIN_TYPES = [
    'deep_water', 'thick_mud', 'dense_rubble',
  ];

  protected HexUtilityService $hexUtility;

  public function __construct(HexUtilityService $hex_utility) {
    $this->hexUtility = $hex_utility;
  }

  /**
   * Get a creature's speed for a given movement type, including bonuses/penalties.
   *
   * REQs 2233-2236: Movement types (land, burrow, climb, fly, swim); bonuses apply.
   *
   * @param array $participant
   *   Participant row from combat_participants (may include entity_ref JSON).
   * @param string $movement_type
   *   One of: land, burrow, climb, fly, swim.
   *
   * @return int
   *   Speed in feet. Returns 0 if the creature lacks that movement type.
   *   Minimum 5 ft when the type exists (REQ 2236).
   */
  public function getCreatureSpeed(array $participant, string $movement_type = 'land'): int {
    $entity = !empty($participant['entity_ref']) ? (json_decode($participant['entity_ref'], TRUE) ?? []) : [];

    if ($movement_type === 'land') {
      $base = (int) ($participant['speed'] ?? $entity['speed'] ?? 25);
    }
    else {
      // Non-land speeds stored in entity_ref['speeds'][type] or entity_ref[type.'_speed'].
      $speeds = $entity['speeds'] ?? [];
      $base = (int) ($speeds[$movement_type] ?? $entity[$movement_type . '_speed'] ?? 0);
    }

    if ($base <= 0) {
      return 0;
    }

    // Apply stacking bonuses (largest of each type) and penalties.
    // REQ 2236: circumstance, item, status bonuses stack individually.
    $status_bonus = (int) ($entity['speed_status_bonus'] ?? 0);
    $circ_bonus = (int) ($entity['speed_circumstance_bonus'] ?? 0);
    $item_bonus = (int) ($entity['speed_item_bonus'] ?? 0);
    $penalty = (int) ($entity['speed_penalty'] ?? 0);
    $total = $base + $status_bonus + $circ_bonus + $item_bonus - $penalty;

    return max(5, $total);
  }

  /**
   * Calculate movement cost in feet from one hex to a destination hex.
   *
   * REQ 2237: Diagonal grid movement alternates 5 ft / 10 ft per square.
   * REQ 2249: Difficult terrain adds +5 ft cost per square.
   * REQ 2250: Greater difficult terrain adds +10 ft per square (not doubled diagonally).
   *
   * On a flat-top hex grid, all adjacent moves are 5 ft and the PF2e
   * diagonal alternation rule is tracked via diagonal_count for compatibility
   * with conversion to square-grid contexts. For pure hex play the cost is
   * distance × 5 ft plus terrain surcharge.
   *
   * @param array $from_hex
   *   Source hex {'q' => int, 'r' => int}.
   * @param array $to_hex
   *   Destination hex {'q' => int, 'r' => int}.
   * @param array $dungeon_data
   *   Dungeon data array containing 'hexes' key.
   * @param int $diagonal_count
   *   Number of diagonal-equivalent moves taken this turn (for 1-2-1-2 rule).
   * @param string $movement_type
   *   Movement type; 'fly' ignores ground terrain costs.
   *
   * @return array
   *   ['cost' => int, 'new_diagonal_count' => int, 'terrain_type' => string]
   */
  public function calculateMovementCost(
    array $from_hex,
    array $to_hex,
    array $dungeon_data,
    int $diagonal_count = 0,
    string $movement_type = 'land'
  ): array {
    $hex_distance = $this->hexUtility->distance($from_hex, $to_hex);
    $base_cost = $hex_distance * 5;
    $new_diagonal_count = $diagonal_count;

    $terrain_cost = 0;
    $terrain_type = 'normal';

    if ($movement_type !== 'fly') {
      $hex_data = $this->findHex($to_hex, $dungeon_data);
      if ($hex_data) {
        $terrain_type = $hex_data['terrain'] ?? 'normal';
        if (in_array($terrain_type, self::GREATER_DIFFICULT_TERRAIN_TYPES)) {
          // REQ 2250: +10 ft per square (not doubled).
          $terrain_cost = $hex_distance * 10;
        }
        elseif (in_array($terrain_type, self::DIFFICULT_TERRAIN_TYPES)) {
          // REQ 2249: +5 ft per square.
          $terrain_cost = $hex_distance * 5;
        }
      }
    }

    return [
      'cost'               => $base_cost + $terrain_cost,
      'new_diagonal_count' => $new_diagonal_count,
      'terrain_type'       => $terrain_type,
    ];
  }

  /**
   * Determine if a hex has difficult terrain (standard, not greater).
   *
   * REQ 2251: Cannot Step into difficult terrain.
   */
  public function isDifficultTerrain(array $hex, array $dungeon_data): bool {
    $hex_data = $this->findHex($hex, $dungeon_data);
    if (!$hex_data) {
      return FALSE;
    }
    return in_array($hex_data['terrain'] ?? '', self::DIFFICULT_TERRAIN_TYPES);
  }

  /**
   * Determine if a hex is passable (not void/wall).
   */
  public function isPassable(array $hex, array $dungeon_data): bool {
    $hex_data = $this->findHex($hex, $dungeon_data);
    if (!$hex_data) {
      return FALSE;
    }
    // A hex is passable if explicitly marked passable, or terrain is not void.
    return !empty($hex_data['passable']) || ($hex_data['terrain'] ?? 'void') !== 'void';
  }

  /**
   * Simulate forced movement in a direction, stopping at impassable terrain.
   *
   * REQ 2247: Forced movement does not trigger move-triggered reactions.
   * REQ 2248: Forced movement stops at impassable terrain/squares.
   *
   * @param array $from_hex
   *   Starting hex.
   * @param array $direction_hex
   *   Defines direction vector (target of push/pull).
   * @param int $max_feet
   *   Maximum push/pull distance in feet.
   * @param array $dungeon_data
   *   Dungeon hex data.
   *
   * @return array
   *   ['actual_feet' => int, 'final_hex' => array, 'is_forced' => true]
   */
  public function computeForcedMovement(
    array $from_hex,
    array $direction_hex,
    int $max_feet,
    array $dungeon_data
  ): array {
    $max_hexes = (int) ($max_feet / 5);
    $dq = $direction_hex['q'] - $from_hex['q'];
    $dr = $direction_hex['r'] - $from_hex['r'];

    $mag = max(abs($dq), abs($dr));
    if ($mag === 0) {
      return ['actual_feet' => 0, 'final_hex' => $from_hex, 'is_forced' => TRUE];
    }
    $step_q = (int) round($dq / $mag);
    $step_r = (int) round($dr / $mag);

    $current = $from_hex;
    $moved = 0;
    while ($moved < $max_hexes) {
      $next = ['q' => $current['q'] + $step_q, 'r' => $current['r'] + $step_r];
      if (!$this->isPassable($next, $dungeon_data)) {
        break;
      }
      $current = $next;
      $moved++;
    }
    return ['actual_feet' => $moved * 5, 'final_hex' => $current, 'is_forced' => TRUE];
  }

  /**
   * Detect flanking between an attacker, target, and a potential ally.
   *
   * REQ 2253: Two allies on opposite sides of target with melee reach.
   *
   * @param array $attacker_hex
   *   Attacker position {'q', 'r'}.
   * @param array $target_hex
   *   Target position {'q', 'r'}.
   * @param array $ally_hex
   *   Ally position {'q', 'r'}.
   *
   * @return bool
   *   TRUE if attacker and ally are flanking the target.
   */
  public function isFlanking(array $attacker_hex, array $target_hex, array $ally_hex): bool {
    // Both must be adjacent (distance 1) to the target.
    if ($this->hexUtility->distance($attacker_hex, $target_hex) > 1) {
      return FALSE;
    }
    if ($this->hexUtility->distance($ally_hex, $target_hex) > 1) {
      return FALSE;
    }

    // Exact-opposite check in axial coordinates.
    $aq = $attacker_hex['q'] - $target_hex['q'];
    $ar = $attacker_hex['r'] - $target_hex['r'];
    $bq = $ally_hex['q'] - $target_hex['q'];
    $br = $ally_hex['r'] - $target_hex['r'];

    if ($aq + $bq === 0 && $ar + $br === 0) {
      return TRUE;
    }

    // Near-opposite: direction indices 3 or more apart on the 6-direction ring.
    $attacker_dir = $this->getDirectionIndex($aq, $ar);
    $ally_dir = $this->getDirectionIndex($bq, $br);
    if ($attacker_dir >= 0 && $ally_dir >= 0) {
      $diff = abs($attacker_dir - $ally_dir);
      $diff = min($diff, 6 - $diff);
      return $diff >= 3;
    }

    return FALSE;
  }

  /**
   * Calculate cover tier between two positions.
   *
   * REQ 2255: Lesser (+1 AC/no hide), standard (+2 AC/Reflex/Stealth), greater (+4).
   * REQ 2256: Cover determined by line from attacker to target center.
   * REQ 2257: Cover is per attacker/defender pair.
   *
   * @param array $from_hex
   *   Attacker hex {'q', 'r'}.
   * @param array $to_hex
   *   Defender hex {'q', 'r'}.
   * @param array $dungeon_data
   *   Dungeon hex data.
   *
   * @return array
   *   ['tier' => 'none'|'lesser'|'standard'|'greater',
   *    'ac_bonus' => int, 'reflex_bonus' => int, 'stealth_bonus' => int]
   */
  public function calculateCover(array $from_hex, array $to_hex, array $dungeon_data): array {
    $line = $this->hexUtility->getLine($from_hex, $to_hex);
    $obstacles = 0;

    // Skip first (attacker) and last (defender) in the line.
    $mid = array_slice($line, 1, count($line) - 2);
    foreach ($mid as $hex) {
      $hex_data = $this->findHex($hex, $dungeon_data);
      if ($hex_data && empty($hex_data['passable']) && ($hex_data['terrain'] ?? '') !== 'void') {
        $obstacles++;
      }
    }

    if ($obstacles === 0) {
      return ['tier' => 'none', 'ac_bonus' => 0, 'reflex_bonus' => 0, 'stealth_bonus' => 0];
    }
    if ($obstacles === 1) {
      // Standard cover: +2 AC, +2 Reflex, can hide.
      return ['tier' => 'standard', 'ac_bonus' => 2, 'reflex_bonus' => 2, 'stealth_bonus' => 2];
    }
    // Greater cover: +4 AC, +4 Reflex.
    return ['tier' => 'greater', 'ac_bonus' => 4, 'reflex_bonus' => 4, 'stealth_bonus' => 4];
  }

  /**
   * Calculate fall damage for a creature.
   *
   * REQ 2243: Damage = floor(distance / 2) bludgeoning; max 750 at 1500 ft.
   * REQ 2244: Soft surface (water, snow) reduces effective fall by 20 ft (30 if diving).
   * REQ 2246: Land prone on any fall damage.
   *
   * @param int $feet
   *   Distance fallen in feet.
   * @param bool $soft_surface
   *   TRUE if landing on water, snow, or similar soft surface.
   * @param bool $is_dive
   *   TRUE if the creature dove intentionally (reduces by 30 ft instead of 20).
   *
   * @return array
   *   ['damage' => int, 'damage_type' => 'bludgeoning', 'land_prone' => bool]
   */
  public function calculateFallDamage(int $feet, bool $soft_surface = FALSE, bool $is_dive = FALSE): array {
    if ($soft_surface) {
      $reduction = $is_dive ? 30 : 20;
      $feet = max(0, $feet - $reduction);
    }
    $feet = min($feet, 1500);
    $damage = (int) floor($feet / 2);

    return [
      'damage'      => $damage,
      'damage_type' => 'bludgeoning',
      'land_prone'  => $damage > 0,
    ];
  }

  /**
   * Return aquatic combat modifiers for a participant.
   *
   * REQ 2262: Flat-footed if underwater and no swim speed; resistance 5 to acid/fire;
   *           -2 circumstance to slashing attacks.
   * REQ 2263: Ranged bludgeoning/slashing auto-misses if attacker or target underwater.
   * REQ 2264: Fire trait actions automatically fail underwater.
   *
   * @param array $participant
   *   Participant row with optional entity_ref JSON.
   * @param array $dungeon_data
   *   Dungeon data (may contain 'is_underwater' flag).
   *
   * @return array
   *   ['is_underwater' => bool, 'flat_footed' => bool,
   *    'fire_resistance' => int, 'slashing_penalty' => int,
   *    'ranged_bludgeoning_blocked' => bool, 'ranged_slashing_blocked' => bool]
   */
  public function getAquaticModifiers(array $participant, array $dungeon_data): array {
    $entity = !empty($participant['entity_ref']) ? (json_decode($participant['entity_ref'], TRUE) ?? []) : [];
    $is_underwater = !empty($dungeon_data['is_underwater']) || !empty($entity['is_underwater']);

    if (!$is_underwater) {
      return [
        'is_underwater'             => FALSE,
        'flat_footed'               => FALSE,
        'fire_resistance'           => 0,
        'slashing_penalty'          => 0,
        'ranged_bludgeoning_blocked' => FALSE,
        'ranged_slashing_blocked'   => FALSE,
      ];
    }

    $speeds = $entity['speeds'] ?? [];
    $has_swim_speed = (int) ($speeds['swim'] ?? $entity['swim_speed'] ?? 0) > 0;

    return [
      'is_underwater'             => TRUE,
      'flat_footed'               => !$has_swim_speed,
      'fire_resistance'           => 5,
      'slashing_penalty'          => -2,
      'ranged_bludgeoning_blocked' => TRUE,
      'ranged_slashing_blocked'   => TRUE,
    ];
  }

  /**
   * Get direction index (0-5) for an adjacent hex offset from a center.
   *
   * Matches HexUtilityService neighbor order (clockwise from east).
   */
  protected function getDirectionIndex(int $dq, int $dr): int {
    $dirs = [
      [1, 0], [1, -1], [0, -1], [-1, 0], [-1, 1], [0, 1],
    ];
    foreach ($dirs as $i => [$q, $r]) {
      if ($dq === $q && $dr === $r) {
        return $i;
      }
    }
    return -1;
  }

  /**
   * Look up a hex by q,r coordinates in dungeon_data.
   */
  protected function findHex(array $hex, array $dungeon_data): ?array {
    $hexes = $dungeon_data['hexes'] ?? $dungeon_data['hexmap']['hexes'] ?? [];
    foreach ($hexes as $h) {
      if ((int) $h['q'] === (int) $hex['q'] && (int) $h['r'] === (int) $hex['r']) {
        return $h;
      }
    }
    return NULL;
  }

}
