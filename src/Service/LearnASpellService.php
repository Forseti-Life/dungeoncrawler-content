<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Computes DCs for Learn a Spell downtime actions (PF2e Core p.238).
 *
 * Rules:
 * - DC = spell-level DC + rarity adjustment.
 * - Uses DcAdjustmentService::spellLevelDc() for the base then applies rarity.
 *
 * Implements req 2322.
 */
class LearnASpellService {

  protected DcAdjustmentService $dcAdjustment;

  public function __construct(DcAdjustmentService $dc_adjustment) {
    $this->dcAdjustment = $dc_adjustment;
  }

  /**
   * Compute the DC for a Learn a Spell downtime check.
   *
   * @param int $spell_rank
   *   Spell rank (0–10).
   * @param string $rarity
   *   One of: common, uncommon, rare, unique.
   *
   * @return array{dc: int, base_dc: int, rarity_delta: int}
   *   Computed DC plus component log.
   */
  public function computeDc(int $spell_rank, string $rarity = 'common'): array {
    $base_dc = $this->dcAdjustment->spellLevelDc($spell_rank);
    $dc      = $this->dcAdjustment->compute($base_dc, $rarity, 0);

    $rarity_key   = strtolower(trim($rarity));
    $rarity_delta = DcAdjustmentService::RARITY_ADJUSTMENT[$rarity_key] ?? 0;

    return [
      'dc'           => $dc,
      'base_dc'      => $base_dc,
      'rarity_delta' => $rarity_delta,
    ];
  }

}
