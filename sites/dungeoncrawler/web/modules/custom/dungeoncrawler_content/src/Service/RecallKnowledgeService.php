<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Computes DCs for Recall Knowledge checks (PF2e Core p.243).
 *
 * Rules:
 * - General information: simple DC (availability-based).
 * - Creatures / hazards: level-based DC from Table 10-4 + rarity adjustment.
 * - All rarity adjustments applied via DcAdjustmentService::compute().
 *
 * Implements req 2320.
 */
class RecallKnowledgeService {

  protected DcAdjustmentService $dcAdjustment;

  public function __construct(DcAdjustmentService $dc_adjustment) {
    $this->dcAdjustment = $dc_adjustment;
  }

  /**
   * Compute the DC for a Recall Knowledge check.
   *
   * @param string $subject_type
   *   One of: 'general' (simple DC), 'creature', 'hazard', 'spell'.
   * @param int $level
   *   Creature/hazard/item level (used when subject_type != 'general').
   * @param string $rarity
   *   One of: common, uncommon, rare, unique.
   * @param int $spell_rank
   *   Spell rank (0–10); used only when subject_type == 'spell'.
   * @param string $availability
   *   For subject_type='general': proficiency rank name (e.g. 'trained').
   *
   * @return array{dc: int, base_dc: int, rarity_delta: int, subject_type: string}
   *   Computed DC plus a log of component values for transparency.
   */
  public function computeDc(
    string $subject_type,
    int $level = 0,
    string $rarity = 'common',
    int $spell_rank = 0,
    string $availability = 'trained'
  ): array {
    switch (strtolower($subject_type)) {
      case 'general':
        $base_dc = $this->dcAdjustment->simpleDc($availability);
        $dc      = $this->dcAdjustment->compute($base_dc, $rarity, 0);
        break;

      case 'spell':
        $base_dc = $this->dcAdjustment->spellLevelDc($spell_rank);
        $dc      = $this->dcAdjustment->compute($base_dc, $rarity, 0);
        break;

      case 'creature':
      case 'hazard':
      default:
        $base_dc = $this->dcAdjustment->levelDc($level);
        $dc      = $this->dcAdjustment->compute($base_dc, $rarity, 0);
        break;
    }

    $rarity_key   = strtolower(trim($rarity));
    $rarity_delta = DcAdjustmentService::RARITY_ADJUSTMENT[$rarity_key] ?? 0;

    return [
      'dc'           => $dc,
      'base_dc'      => $base_dc,
      'rarity_delta' => $rarity_delta,
      'subject_type' => $subject_type,
    ];
  }

}
