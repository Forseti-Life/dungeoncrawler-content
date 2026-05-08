<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Centralized DC calculation utility.
 *
 * Implements PF2e Core Rulebook Chapter 10 DC tables and adjustments:
 * - Simple DC table (proficiency rank → DC)
 * - Level-based DC table (character/creature/item level → DC) [Table 10-4]
 * - Spell-level DC table (spell rank → identify/recall DC) [Table 10-5]
 * - DC adjustment table (narrative difficulty → delta) [Table 10-3]
 * - Rarity adjustments mapped as DC adjustments [Core p.13]
 *
 * Primary entry point: compute(base_dc, rarity, spell_rank_delta).
 *
 * Implements reqs 2320, 2321, 2322, 2328.
 */
class DcAdjustmentService {

  // ---------------------------------------------------------------------------
  // Tables
  // ---------------------------------------------------------------------------

  /** Simple DC by proficiency rank [Table 10-2]. */
  const SIMPLE_DC = [
    'untrained' => 10,
    'trained'   => 15,
    'expert'    => 20,
    'master'    => 30,
    'legendary' => 40,
  ];

  /**
   * Level-based DC table, levels 0–25 [Table 10-4].
   * Index = creature/item/character level.
   */
  const LEVEL_DC = [
     0 => 14,
     1 => 15,
     2 => 16,
     3 => 18,
     4 => 19,
     5 => 20,
     6 => 22,
     7 => 23,
     8 => 24,
     9 => 26,
    10 => 27,
    11 => 28,
    12 => 30,
    13 => 31,
    14 => 32,
    15 => 34,
    16 => 35,
    17 => 36,
    18 => 38,
    19 => 39,
    20 => 40,
    21 => 42,
    22 => 44,
    23 => 46,
    24 => 48,
    25 => 50,
  ];

  /**
   * Spell-level DC table for Identify Spell / Recall Knowledge (levels 0–10).
   * Cantrips (rank 0) use DC 13.
   */
  const SPELL_LEVEL_DC = [
     0 => 13,
     1 => 15,
     2 => 18,
     3 => 20,
     4 => 23,
     5 => 26,
     6 => 28,
     7 => 31,
     8 => 34,
     9 => 36,
    10 => 39,
  ];

  /**
   * Named DC adjustments → numeric delta [Table 10-3].
   * These are narrative difficulty labels that modify any base DC.
   */
  const DC_ADJUSTMENT = [
    'incredibly_easy' => -10,
    'very_easy'       =>  -5,
    'easy'            =>  -2,
    'normal'          =>   0,
    'hard'            =>   2,
    'very_hard'       =>   5,
    'incredibly_hard' =>  10,
  ];

  /**
   * Rarity to DC adjustment delta [Core p.13].
   * Common maps to no adjustment; the rest use their DC_ADJUSTMENT equivalents.
   */
  const RARITY_ADJUSTMENT = [
    'common'   => 0,
    'uncommon' => 2,
    'rare'     => 5,
    'unique'   => 10,
  ];

  /**
   * NPC attitude to DC adjustment delta [Core p.247].
   */
  const ATTITUDE_ADJUSTMENT = [
    'helpful'    => -5,
    'friendly'   => -2,
    'indifferent' => 0,
    'unfriendly' =>  2,
    'hostile'    =>  5,
  ];

  // ---------------------------------------------------------------------------
  // Core compute
  // ---------------------------------------------------------------------------

  /**
   * Compute the final DC given a base DC, rarity, and spell-rank delta.
   *
   * Formula: base_dc + RARITY_ADJUSTMENT[rarity] + max(0, spell_rank_delta × 2)
   *
   * @param int $base_dc
   *   Base DC from the level table, spell-level table, or simple DC.
   * @param string $rarity
   *   One of: common, uncommon, rare, unique.
   * @param int $spell_rank_delta
   *   How many spell ranks above the standard caster level. Must be >= 0;
   *   negative values are treated as 0 (higher caster level doesn't reduce DC).
   *
   * @return int
   *   The adjusted DC (minimum 0).
   */
  public function compute(int $base_dc, string $rarity = 'common', int $spell_rank_delta = 0): int {
    $rarity_key = strtolower(trim($rarity));
    if (!isset(self::RARITY_ADJUSTMENT[$rarity_key])) {
      throw new \InvalidArgumentException("Unknown rarity: $rarity. Valid values: common, uncommon, rare, unique.");
    }
    $rarity_bonus = self::RARITY_ADJUSTMENT[$rarity_key];
    $rank_bonus   = max(0, $spell_rank_delta) * 2;

    return max(0, $base_dc + $rarity_bonus + $rank_bonus);
  }

  // ---------------------------------------------------------------------------
  // Table lookups
  // ---------------------------------------------------------------------------

  /**
   * Return the Simple DC for a given proficiency rank.
   *
   * @param string $rank
   *   One of: untrained, trained, expert, master, legendary.
   *
   * @return int
   *   The DC value.
   *
   * @throws \InvalidArgumentException
   */
  public function simpleDc(string $rank): int {
    $key = strtolower(trim($rank));
    if (!isset(self::SIMPLE_DC[$key])) {
      throw new \InvalidArgumentException("Unknown proficiency rank: $rank. Valid: untrained, trained, expert, master, legendary.");
    }
    return self::SIMPLE_DC[$key];
  }

  /**
   * Return the level-based DC for a given level (0–25).
   *
   * @param int $level
   *   Character/creature/item level (0–25 inclusive).
   *
   * @return int
   *   The DC value.
   *
   * @throws \InvalidArgumentException
   */
  public function levelDc(int $level): int {
    if (!isset(self::LEVEL_DC[$level])) {
      throw new \InvalidArgumentException("Level $level is out of range. Valid range: 0–25.");
    }
    return self::LEVEL_DC[$level];
  }

  /**
   * Return the DC for identifying or recalling knowledge about a spell by rank.
   *
   * @param int $spell_level
   *   Spell rank (0 = cantrip, 1–10).
   *
   * @return int
   *   The DC value.
   *
   * @throws \InvalidArgumentException
   */
  public function spellLevelDc(int $spell_level): int {
    if (!isset(self::SPELL_LEVEL_DC[$spell_level])) {
      throw new \InvalidArgumentException("Spell level $spell_level is out of range. Valid range: 0–10.");
    }
    return self::SPELL_LEVEL_DC[$spell_level];
  }

  /**
   * Apply a named DC adjustment to a base DC.
   *
   * Multiple adjustments stack additively; call this method for each one.
   *
   * @param int $base_dc
   *   Starting DC value.
   * @param string $adjustment
   *   One of: incredibly_easy, very_easy, easy, normal, hard, very_hard,
   *   incredibly_hard.
   *
   * @return int
   *   The adjusted DC (minimum 0).
   *
   * @throws \InvalidArgumentException
   */
  public function applyAdjustment(int $base_dc, string $adjustment): int {
    $key = strtolower(trim($adjustment));
    if (!isset(self::DC_ADJUSTMENT[$key])) {
      throw new \InvalidArgumentException("Unknown DC adjustment: $adjustment. Valid: " . implode(', ', array_keys(self::DC_ADJUSTMENT)));
    }
    return max(0, $base_dc + self::DC_ADJUSTMENT[$key]);
  }

  /**
   * Apply multiple named DC adjustments additively.
   *
   * @param int $base_dc
   *   Starting DC value.
   * @param string[] $adjustments
   *   Array of adjustment names.
   *
   * @return int
   *   The adjusted DC (minimum 0).
   */
  public function applyAdjustments(int $base_dc, array $adjustments): int {
    $total_delta = 0;
    foreach ($adjustments as $adjustment) {
      $key = strtolower(trim($adjustment));
      if (!isset(self::DC_ADJUSTMENT[$key])) {
        throw new \InvalidArgumentException("Unknown DC adjustment: $adjustment.");
      }
      $total_delta += self::DC_ADJUSTMENT[$key];
    }
    return max(0, $base_dc + $total_delta);
  }

  /**
   * Return the DC adjustment delta for an NPC's attitude.
   *
   * @param string $attitude
   *   One of: helpful, friendly, indifferent, unfriendly, hostile.
   *
   * @return int
   *   The delta to add to (or subtract from) the base social DC.
   *
   * @throws \InvalidArgumentException
   */
  public function attitudeDelta(string $attitude): int {
    $key = strtolower(trim($attitude));
    if (!isset(self::ATTITUDE_ADJUSTMENT[$key])) {
      throw new \InvalidArgumentException("Unknown NPC attitude: $attitude. Valid: " . implode(', ', array_keys(self::ATTITUDE_ADJUSTMENT)));
    }
    return self::ATTITUDE_ADJUSTMENT[$key];
  }

  /**
   * Apply NPC attitude adjustment to a social check DC.
   *
   * @param int $base_dc
   *   The unmodified social check DC.
   * @param string $attitude
   *   One of: helpful, friendly, indifferent, unfriendly, hostile.
   *
   * @return int
   *   The final DC after applying the standard NPC attitude adjustment.
   */
  public function adjustDcForNpcAttitude(int $base_dc, string $attitude): int {
    return max(0, $base_dc + $this->attitudeDelta($attitude));
  }

  /**
   * Validate proficiency rank against a required minimum.
   *
   * Per PF2e rules, characters below the minimum required rank may attempt the
   * check but the maximum outcome is Failure (cannot Succeed or Crit Succeed).
   *
   * @param string $character_rank
   *   The character's current proficiency rank.
   * @param string $minimum_rank
   *   The minimum rank required for success.
   *
   * @return bool
   *   TRUE if the character meets the minimum rank; FALSE if below.
   */
  public function meetsMinimumRank(string $character_rank, string $minimum_rank): bool {
    $rank_order = array_keys(self::SIMPLE_DC);
    $char_index = array_search(strtolower(trim($character_rank)), $rank_order);
    $min_index  = array_search(strtolower(trim($minimum_rank)), $rank_order);

    if ($char_index === FALSE || $min_index === FALSE) {
      throw new \InvalidArgumentException("Invalid rank values: character=$character_rank, minimum=$minimum_rank.");
    }
    return $char_index >= $min_index;
  }

}
