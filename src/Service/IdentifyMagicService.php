<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Computes DCs for Identify Magic checks (PF2e Core p.238).
 *
 * Rules:
 * - DC = level-based DC (item/spell level from Table 10-4) + rarity adjustment.
 * - For spells: use the spell-level DC table; for items/effects: level DC.
 *
 * Implements req 2321.
 */
class IdentifyMagicService {

  protected DcAdjustmentService $dcAdjustment;

  public function __construct(DcAdjustmentService $dc_adjustment) {
    $this->dcAdjustment = $dc_adjustment;
  }

  /**
   * Compute the DC for an Identify Magic check.
   *
   * @param string $magic_type
   *   One of: 'item', 'spell', 'effect'.
   * @param int $level
   *   Item or effect level (used when magic_type != 'spell').
   * @param string $rarity
   *   One of: common, uncommon, rare, unique.
   * @param int $spell_rank
   *   Spell rank 0–10 (used when magic_type == 'spell').
   * @param int $spell_rank_delta
   *   How many ranks the spell is above the standard caster level (0 if n/a).
   *
   * @return array{dc: int, base_dc: int, rarity_delta: int, rank_delta_bonus: int}
   *   Computed DC plus component log.
   */
  public function computeDc(
    string $magic_type = 'item',
    int $level = 0,
    string $rarity = 'common',
    int $spell_rank = 0,
    int $spell_rank_delta = 0
  ): array {
    switch (strtolower($magic_type)) {
      case 'spell':
        $base_dc = $this->dcAdjustment->spellLevelDc($spell_rank);
        break;

      case 'item':
      case 'effect':
      default:
        $base_dc = $this->dcAdjustment->levelDc($level);
        break;
    }

    $dc = $this->dcAdjustment->compute($base_dc, $rarity, $spell_rank_delta);

    $rarity_key      = strtolower(trim($rarity));
    $rarity_delta    = DcAdjustmentService::RARITY_ADJUSTMENT[$rarity_key] ?? 0;
    $rank_delta_bonus = max(0, $spell_rank_delta) * 2;

    return [
      'dc'               => $dc,
      'base_dc'          => $base_dc,
      'rarity_delta'     => $rarity_delta,
      'rank_delta_bonus' => $rank_delta_bonus,
    ];
  }

}
