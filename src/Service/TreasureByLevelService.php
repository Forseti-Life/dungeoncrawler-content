<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Provides treasure-by-level tables and party-size budget adjustments.
 *
 * Source: PF2E Core Rulebook (Fourth Printing), Chapter 10, Table 10-9.
 *
 * Design:
 * - Static lookup table: party_level → {currency_gp, permanent_items[], consumable_items[]}
 * - Per-encounter budget: total_level_gp / DEFAULT_ENCOUNTERS_PER_LEVEL (8)
 * - Party-size adjustment: +/− currency_gp per extra/missing PC above/below 4-PC baseline
 * - Starting wealth by level for new/replacement characters
 */
class TreasureByLevelService {

  /**
   * Default encounters per level used to compute per-encounter budget.
   */
  public const DEFAULT_ENCOUNTERS_PER_LEVEL = 8;

  /**
   * Baseline party size for the treasure table (4 PCs).
   */
  public const BASELINE_PARTY_SIZE = 4;

  /**
   * What the currency column represents in the treasure-by-level table.
   */
  public const CURRENCY_INCLUDES = [
    'coins',
    'gems',
    'art_objects',
    'half_price_items',
  ];

  /**
   * Item types that sell for full price rather than half.
   */
  public const FULL_PRICE_SALE_TYPES = [
    'gem',
    'art_object',
    'raw_material',
  ];

  /**
   * Treasure by level table (4-PC baseline).
   *
   * Keys:
   *   - currency_gp: total GP equivalent for the level (coins + lower-level items)
   *   - currency_gp_per_extra_pc: additional GP per PC above baseline
   *   - permanent_items: array of item levels to award as permanent items
   *   - consumable_items: array of item levels to award as consumables
   *
   * Source: CRB Table 10-9.
   */
  public const TREASURE_TABLE = [
    1  => ['currency_gp' => 175,    'currency_gp_per_extra_pc' => 40,   'permanent_items' => [2, 1, 1],     'consumable_items' => [2, 2, 1]],
    2  => ['currency_gp' => 300,    'currency_gp_per_extra_pc' => 70,   'permanent_items' => [3, 2, 2],     'consumable_items' => [3, 3, 2]],
    3  => ['currency_gp' => 500,    'currency_gp_per_extra_pc' => 120,  'permanent_items' => [4, 3, 3],     'consumable_items' => [4, 4, 3]],
    4  => ['currency_gp' => 850,    'currency_gp_per_extra_pc' => 200,  'permanent_items' => [5, 4, 4],     'consumable_items' => [5, 5, 4]],
    5  => ['currency_gp' => 1400,   'currency_gp_per_extra_pc' => 320,  'permanent_items' => [6, 5, 5],     'consumable_items' => [6, 6, 5]],
    6  => ['currency_gp' => 2100,   'currency_gp_per_extra_pc' => 500,  'permanent_items' => [7, 6, 6],     'consumable_items' => [7, 7, 6]],
    7  => ['currency_gp' => 3200,   'currency_gp_per_extra_pc' => 750,  'permanent_items' => [8, 7, 7],     'consumable_items' => [8, 8, 7]],
    8  => ['currency_gp' => 4800,   'currency_gp_per_extra_pc' => 1100, 'permanent_items' => [9, 8, 8],     'consumable_items' => [9, 9, 8]],
    9  => ['currency_gp' => 7000,   'currency_gp_per_extra_pc' => 1600, 'permanent_items' => [10, 9, 9],    'consumable_items' => [10, 10, 9]],
    10 => ['currency_gp' => 10000,  'currency_gp_per_extra_pc' => 2300, 'permanent_items' => [11, 10, 10],  'consumable_items' => [11, 11, 10]],
    11 => ['currency_gp' => 15000,  'currency_gp_per_extra_pc' => 3400, 'permanent_items' => [12, 11, 11],  'consumable_items' => [12, 12, 11]],
    12 => ['currency_gp' => 21000,  'currency_gp_per_extra_pc' => 4800, 'permanent_items' => [13, 12, 12],  'consumable_items' => [13, 13, 12]],
    13 => ['currency_gp' => 32000,  'currency_gp_per_extra_pc' => 7200, 'permanent_items' => [14, 13, 13],  'consumable_items' => [14, 14, 13]],
    14 => ['currency_gp' => 46000,  'currency_gp_per_extra_pc' => 10500,'permanent_items' => [15, 14, 14],  'consumable_items' => [15, 15, 14]],
    15 => ['currency_gp' => 67000,  'currency_gp_per_extra_pc' => 15000,'permanent_items' => [16, 15, 15],  'consumable_items' => [16, 16, 15]],
    16 => ['currency_gp' => 95000,  'currency_gp_per_extra_pc' => 21000,'permanent_items' => [17, 16, 16],  'consumable_items' => [17, 17, 16]],
    17 => ['currency_gp' => 135000, 'currency_gp_per_extra_pc' => 30000,'permanent_items' => [18, 17, 17],  'consumable_items' => [18, 18, 17]],
    18 => ['currency_gp' => 195000, 'currency_gp_per_extra_pc' => 44000,'permanent_items' => [19, 18, 18],  'consumable_items' => [19, 19, 18]],
    19 => ['currency_gp' => 280000, 'currency_gp_per_extra_pc' => 64000,'permanent_items' => [20, 19, 19],  'consumable_items' => [20, 20, 19]],
    20 => ['currency_gp' => 400000, 'currency_gp_per_extra_pc' => 90000,'permanent_items' => [20, 20, 20],  'consumable_items' => [20, 20, 20]],
  ];

  /**
   * Starting wealth for a new or replacement character by level.
   *
   * Source: CRB Chapter 10 — "Starting Character Wealth" sidebar.
   * Values represent total GP equivalent (currency + items) the character
   * begins with; the GM distributes as appropriate.
   *
   * Keys: character_level → starting_gp
   */
  public const STARTING_WEALTH = [
    1  => 15,
    2  => 30,
    3  => 75,
    4  => 140,
    5  => 270,
    6  => 450,
    7  => 720,
    8  => 1100,
    9  => 1700,
    10 => 2500,
    11 => 3750,
    12 => 5250,
    13 => 8000,
    14 => 11500,
    15 => 17000,
    16 => 24000,
    17 => 34000,
    18 => 49000,
    19 => 70000,
    20 => 100000,
  ];

  /**
   * Get the full treasure budget for a party at a given level.
   *
   * @param int $party_level
   *   Party level (1–20).
   * @param int $party_size
   *   Number of PCs. Default 4 (baseline).
   *
   * @return array
   *   {
   *     'party_level': int,
   *     'party_size': int,
   *     'currency_gp': float,          // total GP-equivalent currency budget
   *     'permanent_items': int[],      // item levels for permanent awards
   *     'consumable_items': int[],     // item levels for consumable awards
   *     'per_encounter_gp': float,     // currency_gp / DEFAULT_ENCOUNTERS_PER_LEVEL
   *   }
   *
   * @throws \InvalidArgumentException
   *   If party_level is outside 1–20.
   */
  public function getLevelBudget(int $party_level, int $party_size = self::BASELINE_PARTY_SIZE): array {
    if ($party_level < 1 || $party_level > 20) {
      throw new \InvalidArgumentException("Party level must be 1–20; got {$party_level}.");
    }

    $row = self::TREASURE_TABLE[$party_level];
    $extra_pcs = $party_size - self::BASELINE_PARTY_SIZE;
    $currency_gp = $row['currency_gp'] + ($extra_pcs * $row['currency_gp_per_extra_pc']);
    // For party sizes below 4, proportionally reduce (not blocking, just advisory).
    if ($extra_pcs < 0) {
      $currency_gp = max(0, $currency_gp);
    }

    return [
      'party_level'       => $party_level,
      'party_size'        => $party_size,
      'currency_gp'       => (float) $currency_gp,
      'currency_includes' => self::CURRENCY_INCLUDES,
      'permanent_items'   => $row['permanent_items'],
      'consumable_items'  => $row['consumable_items'],
      'per_encounter_gp'  => round($currency_gp / self::DEFAULT_ENCOUNTERS_PER_LEVEL, 2),
    ];
  }

  /**
   * Get starting wealth (GP equivalent) for a new or replacement character.
   *
   * @param int $character_level
   *   Character level (1–20).
   *
   * @return float
   *   Starting wealth in GP.
   *
   * @throws \InvalidArgumentException
   *   If character_level is outside 1–20.
   */
  public function getStartingWealth(int $character_level): float {
    if ($character_level < 1 || $character_level > 20) {
      throw new \InvalidArgumentException("Character level must be 1–20; got {$character_level}.");
    }
    return (float) self::STARTING_WEALTH[$character_level];
  }

  /**
   * Get the party-size currency adjustment for a single level.
   *
   * Positive when party > 4 (more treasure), negative when party < 4.
   *
   * @param int $party_level
   *   Party level (1–20).
   * @param int $party_size
   *   Number of PCs.
   *
   * @return float
   *   GP adjustment (positive = more, negative = less).
   */
  public function getPartySizeAdjustment(int $party_level, int $party_size): float {
    if ($party_level < 1 || $party_level > 20) {
      throw new \InvalidArgumentException("Party level must be 1–20; got {$party_level}.");
    }
    $extra = $party_size - self::BASELINE_PARTY_SIZE;
    return (float) ($extra * self::TREASURE_TABLE[$party_level]['currency_gp_per_extra_pc']);
  }

  /**
   * Returns the metadata describing the treasure table currency column.
   */
  public function getCurrencyComposition(): array {
    return self::CURRENCY_INCLUDES;
  }

  /**
   * Validates whether trading is happening during downtime.
   */
  public function validateTradePhase(string $phase): array {
    $normalized_phase = strtolower(trim($phase));
    if ($normalized_phase === 'downtime') {
      return [
        'success' => TRUE,
        'flagged' => FALSE,
        'blocked' => FALSE,
        'gm_override_available' => FALSE,
        'reason' => NULL,
      ];
    }

    return [
      'success' => FALSE,
      'flagged' => TRUE,
      'blocked' => FALSE,
      'gm_override_available' => TRUE,
      'reason' => 'not_downtime',
    ];
  }

  /**
   * Computes the sell value for an item and enforces the PF2e sale rules.
   */
  public function sellItem(string $item_type, float $price, ?float $requested_price = NULL, string $phase = 'downtime'): array {
    $phase_result = $this->validateTradePhase($phase);
    if (!$phase_result['success']) {
      return $phase_result + [
        'sale_value' => 0.0,
        'item_type' => strtolower(trim($item_type)),
      ];
    }

    $normalized_type = strtolower(trim($item_type));
    $full_price = in_array($normalized_type, self::FULL_PRICE_SALE_TYPES, TRUE);
    $sale_value = $full_price ? $price : $price / 2;

    if ($requested_price !== NULL && abs($requested_price - $sale_value) > 0.00001) {
      return [
        'success' => FALSE,
        'flagged' => TRUE,
        'blocked' => TRUE,
        'gm_override_available' => FALSE,
        'reason' => $full_price ? 'incorrect_sale_value' : 'must_sell_at_half_price',
        'corrected_value' => $sale_value,
        'sale_value' => $sale_value,
        'item_type' => $normalized_type,
      ];
    }

    return [
      'success' => TRUE,
      'flagged' => FALSE,
      'blocked' => FALSE,
      'gm_override_available' => FALSE,
      'reason' => NULL,
      'sale_value' => $sale_value,
      'item_type' => $normalized_type,
      'full_price_sale' => $full_price,
    ];
  }

}
