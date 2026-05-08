<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Implements PF2e Chapter 11 magic item system.
 *
 * Covers: investment, activation, daily uses, precious materials, rune system,
 * alchemical items, consumables (scrolls/potions/oils/talismans), staves, wands,
 * snares, and worn item slot management (apex items).
 *
 * REQs 2397–2550 (dc-cr-magic-ch11).
 */
class MagicItemService {

  /**
   * Maximum number of invested magic items per day (REQ 2399).
   */
  const MAX_INVESTED = 10;

  /**
   * Sustained activation fatigue threshold — rounds before fatigue (REQ 2415).
   */
  const SUSTAIN_FATIGUE_ROUNDS = 100;

  /**
   * Crafting proficiency rank gates by item level (REQ 2432).
   */
  const CRAFT_PROFICIENCY_GATES = [
    ['max_level' => 8,  'min_rank' => 1],  // Trained
    ['max_level' => 15, 'min_rank' => 3],  // Master
    ['max_level' => 99, 'min_rank' => 4],  // Legendary
  ];

  /**
   * Precious material grade proficiency requirements (REQ 2433).
   */
  const PRECIOUS_GRADE_CRAFT_RANK = [
    'low'      => 2,  // Expert
    'standard' => 3,  // Master
    'high'     => 4,  // Legendary
  ];

  /**
   * Precious material grade max item level (REQ 2436).
   */
  const PRECIOUS_GRADE_MAX_LEVEL = [
    'low'      => 8,
    'standard' => 15,
    'high'     => 99,
  ];

  /**
   * Precious material stats: [hardness, hp, bt] (REQ 2439).
   * Values are for medium-bulk items; callers may scale.
   */
  const PRECIOUS_MATERIAL_STATS = [
    'cold_iron'   => ['low' => [5, 20, 10], 'standard' => [9, 36, 18], 'high' => [13, 52, 26]],
    'adamantine'  => ['low' => [10, 40, 20], 'standard' => [14, 56, 28], 'high' => [20, 80, 40]],
    'darkwood'    => ['low' => [5, 20, 10], 'standard' => [9, 36, 18], 'high' => [13, 52, 26]],
    'dragonhide'  => ['low' => [4, 16, 8],  'standard' => [8, 32, 16], 'high' => [11, 44, 22]],
    'mithral'     => ['low' => [5, 20, 10], 'standard' => [9, 36, 18], 'high' => [13, 52, 26]],
    'orichalcum'  => ['low' => [14, 56, 28], 'standard' => [17, 68, 34], 'high' => [20, 80, 40]],
    'silver'      => ['low' => [3, 12, 6],  'standard' => [7, 28, 14], 'high' => [10, 40, 20]],
    'sovereign_steel' => ['low' => [7, 28, 14], 'standard' => [11, 44, 22], 'high' => [15, 60, 30]],
  ];

  /**
   * Wand overcharge DC (REQ 2510).
   */
  const WAND_OVERCHARGE_DC = 10;

  /**
   * Valid snare types (dc-cr-snares feature).
   * Effect resolution handled by triggerSnare().
   */
  const VALID_SNARE_TYPES = [
    'alarm',
    'hampering',
    'marking',
    'striking',
  ];

  protected NumberGenerationService $numberGenerationService;

  public function __construct(NumberGenerationService $number_generation_service) {
    $this->numberGenerationService = $number_generation_service;
  }

  // ---------------------------------------------------------------------------
  // Investment system (REQs 2397–2404)
  // ---------------------------------------------------------------------------

  /**
   * Attempt to invest a magic item for a character during daily preparations.
   *
   * @param string $char_id   Character identifier.
   * @param string $item_instance_id  Item instance ID.
   * @param array  $item_data  Item definition array (must include investment_required, worn_slot, is_focused, is_apex, apex_ability).
   * @param array  $game_state  Game state (mutated in place).
   *
   * @return array{invested: bool, reason: string, focus_point_granted: bool, apex_applied: bool}
   */
  public function investItem(string $char_id, string $item_instance_id, array $item_data, array &$game_state): array {
    $invested = $this->getInvestedItems($char_id, $game_state);

    // REQ 2399: Max 10 invested items per day.
    if (count($invested) >= self::MAX_INVESTED) {
      return ['invested' => FALSE, 'reason' => 'Investment limit reached (max 10 per day).', 'focus_point_granted' => FALSE, 'apex_applied' => FALSE];
    }

    // Already invested.
    if (in_array($item_instance_id, $invested, TRUE)) {
      return ['invested' => FALSE, 'reason' => 'Item is already invested.', 'focus_point_granted' => FALSE, 'apex_applied' => FALSE];
    }

    // REQ 2404: Item must require investment.
    if (empty($item_data['investment_required'])) {
      return ['invested' => FALSE, 'reason' => 'This item does not require investment.', 'focus_point_granted' => FALSE, 'apex_applied' => FALSE];
    }

    $game_state['magic_items'][$char_id]['invested'][] = $item_instance_id;
    $game_state['magic_items'][$char_id]['invested_at'][$item_instance_id] = time();

    $focus_granted = FALSE;
    $apex_applied  = FALSE;

    // REQ 2425 (focused trait): +1 Focus Point on investiture, max 1/day.
    if (!empty($item_data['is_focused'])) {
      if (empty($game_state['magic_items'][$char_id]['focused_bonus_used'])) {
        $game_state['magic_items'][$char_id]['focused_bonus_used'] = TRUE;
        $game_state['characters'][$char_id]['focus_points'] = ($game_state['characters'][$char_id]['focus_points'] ?? 0) + 1;
        $focus_granted = TRUE;
      }
    }

    // REQ 2477–2479: Apex items — +2 to ability score or raise to 18 (first invest per day).
    if (!empty($item_data['is_apex'])) {
      if (empty($game_state['magic_items'][$char_id]['apex_benefit_used'])) {
        $game_state['magic_items'][$char_id]['apex_benefit_used'] = TRUE;
        $ability = $item_data['apex_ability'] ?? NULL;
        if ($ability) {
          $current = (int) ($game_state['characters'][$char_id]['ability_scores'][$ability] ?? 10);
          $new_val = max($current + 2, 18);
          $game_state['characters'][$char_id]['ability_scores'][$ability] = $new_val;
          $apex_applied = TRUE;
        }
      }
    }

    return ['invested' => TRUE, 'reason' => 'Invested successfully.', 'focus_point_granted' => $focus_granted, 'apex_applied' => $apex_applied];
  }

  /**
   * Remove investiture when item is removed (REQ 2402).
   */
  public function removeInvestedItem(string $char_id, string $item_instance_id, array &$game_state): void {
    $invested = $this->getInvestedItems($char_id, $game_state);
    $game_state['magic_items'][$char_id]['invested'] = array_values(
      array_filter($invested, fn($id) => $id !== $item_instance_id)
    );
    unset($game_state['magic_items'][$char_id]['invested_at'][$item_instance_id]);
    // REQ 2405: Item used_slot flag persists for remainder of day.
    $game_state['magic_items'][$char_id]['used_investment_slot'][$item_instance_id] = TRUE;
  }

  /**
   * Get invested item IDs for a character.
   */
  public function getInvestedItems(string $char_id, array &$game_state): array {
    return $game_state['magic_items'][$char_id]['invested'] ?? [];
  }

  /**
   * Reset daily investment state during Daily Preparations (REQ 2400, 2403).
   */
  public function resetDailyInvestments(string $char_id, array &$game_state): void {
    $game_state['magic_items'][$char_id]['invested'] = [];
    $game_state['magic_items'][$char_id]['invested_at'] = [];
    $game_state['magic_items'][$char_id]['used_investment_slot'] = [];
    $game_state['magic_items'][$char_id]['focused_bonus_used'] = FALSE;
    $game_state['magic_items'][$char_id]['apex_benefit_used'] = FALSE;
    // Reset daily use counts for all items.
    $game_state['magic_items'][$char_id]['daily_uses'] = [];
    // Reset active activations.
    $game_state['magic_items'][$char_id]['active_activations'] = [];
  }

  // ---------------------------------------------------------------------------
  // Activation system (REQs 2405–2418)
  // ---------------------------------------------------------------------------

  /**
   * Determine if a character is wearing/invested an item with magic effects active.
   *
   * Returns TRUE if item requires investment and is currently invested.
   */
  public function isMagicEffectActive(string $char_id, string $item_instance_id, array $item_data, array &$game_state): bool {
    if (empty($item_data['investment_required'])) {
      return TRUE; // Passive/constant effects on non-invested items still fire.
    }
    return in_array($item_instance_id, $this->getInvestedItems($char_id, $game_state), TRUE);
  }

  /**
   * Activate an item.
   *
   * REQ 2405: variable-action activity; cost and components item-specific.
   * REQ 2406: components add traits.
   * REQ 2407: Cast a Spell activation requires spellcasting class feature.
   * REQ 2408: Long-duration activations blocked in encounter.
   * REQ 2409: Disrupted activations — actions lost, daily use consumed.
   * REQ 2410: Daily use counts tracked; reset at Daily Preparations.
   *
   * @param string $char_id
   * @param string $item_instance_id
   * @param array  $item_data  Item definition (activation, daily_uses, investment_required).
   * @param array  $char_data  Character state (has_spellcasting_class, current_phase).
   * @param array  $game_state
   *
   * @return array{success: bool, reason: string, traits_added: string[], daily_uses_remaining: int}
   */
  public function activateItem(
    string $char_id,
    string $item_instance_id,
    array $item_data,
    array $char_data,
    array &$game_state
  ): array {
    $activation = $item_data['activation'] ?? NULL;
    if (!$activation) {
      return ['success' => FALSE, 'reason' => 'Item has no activation.', 'traits_added' => [], 'daily_uses_remaining' => 0];
    }

    // REQ 2410: Check daily use limit.
    $max_uses = (int) ($item_data['daily_uses'] ?? 0);
    $used = (int) ($game_state['magic_items'][$char_id]['daily_uses'][$item_instance_id] ?? 0);
    if ($max_uses > 0 && $used >= $max_uses) {
      return ['success' => FALSE, 'reason' => 'Daily use limit reached.', 'traits_added' => [], 'daily_uses_remaining' => 0];
    }

    // REQ 2407: Cast a Spell requires spellcasting class feature.
    $component = strtolower($activation['component'] ?? '');
    if ($component === 'cast_a_spell' && empty($char_data['has_spellcasting_class'])) {
      return ['success' => FALSE, 'reason' => 'Cast a Spell activation requires a spellcasting class feature.', 'traits_added' => [], 'daily_uses_remaining' => 0];
    }

    // REQ 2408: Long-duration activations blocked in encounter.
    $duration = strtolower($activation['duration'] ?? '');
    $is_long = in_array($duration, ['minutes', 'hours', 'days'], TRUE) ||
               (int) ($activation['duration_minutes'] ?? 0) >= 1;
    $phase = $game_state['phase'] ?? 'exploration';
    if ($is_long && $phase === 'encounter') {
      return ['success' => FALSE, 'reason' => 'Long-duration activations cannot be started in encounter mode.', 'traits_added' => [], 'daily_uses_remaining' => 0];
    }

    // Consume a daily use.
    if ($max_uses > 0) {
      $game_state['magic_items'][$char_id]['daily_uses'][$item_instance_id] = $used + 1;
    }

    // Track sustained activations.
    if (!empty($activation['can_sustain'])) {
      $game_state['magic_items'][$char_id]['active_activations'][$item_instance_id] = [
        'activated_at' => time(),
        'sustain_count' => 0,
        'item_data' => $item_data,
      ];
    }

    // REQ 2406: Add traits per component.
    $traits_added = $this->activationTraits($component);

    $remaining = $max_uses > 0 ? ($max_uses - ($used + 1)) : -1;
    return ['success' => TRUE, 'reason' => 'Activated.', 'traits_added' => $traits_added, 'daily_uses_remaining' => $remaining];
  }

  /**
   * Sustain an existing activation (REQ 2413).
   */
  public function sustainActivation(string $char_id, string $item_instance_id, array &$game_state): array {
    $activation = $game_state['magic_items'][$char_id]['active_activations'][$item_instance_id] ?? NULL;
    if (!$activation) {
      return ['success' => FALSE, 'reason' => 'No active activation to sustain.'];
    }

    $activation['sustain_count'] = ($activation['sustain_count'] ?? 0) + 1;

    // REQ 2415: Sustaining > 100 rounds causes fatigue.
    if ($activation['sustain_count'] > self::SUSTAIN_FATIGUE_ROUNDS) {
      unset($game_state['magic_items'][$char_id]['active_activations'][$item_instance_id]);
      $game_state['characters'][$char_id]['conditions']['fatigued'] = TRUE;
      return ['success' => FALSE, 'reason' => 'Sustained too long; fatigue applied, activation ends.', 'fatigued' => TRUE];
    }

    $game_state['magic_items'][$char_id]['active_activations'][$item_instance_id] = $activation;
    return ['success' => TRUE, 'reason' => 'Activation sustained for one more round.', 'sustain_count' => $activation['sustain_count']];
  }

  /**
   * Dismiss an activation (REQ 2416).
   */
  public function dismissActivation(string $char_id, string $item_instance_id, array &$game_state): array {
    if (!isset($game_state['magic_items'][$char_id]['active_activations'][$item_instance_id])) {
      return ['success' => FALSE, 'reason' => 'No active activation found.'];
    }
    unset($game_state['magic_items'][$char_id]['active_activations'][$item_instance_id]);
    return ['success' => TRUE, 'reason' => 'Activation dismissed.'];
  }

  /**
   * Return trait additions for a given activation component (REQ 2406).
   */
  public function activationTraits(string $component): array {
    $map = [
      'command'     => ['auditory', 'concentrate'],
      'envision'    => ['concentrate'],
      'interact'    => ['manipulate'],
      'cast_a_spell' => ['concentrate', 'manipulate', 'somatic', 'verbal'],
    ];
    return $map[strtolower($component)] ?? [];
  }

  // ---------------------------------------------------------------------------
  // Item rarity (REQs 2419–2422)
  // ---------------------------------------------------------------------------

  /**
   * Validate item rarity value.
   */
  public function validateRarity(string $rarity): bool {
    return in_array(strtolower($rarity), ['common', 'uncommon', 'rare', 'unique'], TRUE);
  }

  /**
   * Determine if an item formula is purchasable given rarity (REQs 2420–2422).
   */
  public function isFormulaPurchasable(string $rarity): bool {
    return in_array(strtolower($rarity), ['common', 'uncommon'], TRUE);
  }

  // ---------------------------------------------------------------------------
  // Crafting requirements (REQs 2432–2435)
  // ---------------------------------------------------------------------------

  /**
   * Validate crafting prerequisites for a magic item.
   *
   * @param array $char_state   Character state: crafting_rank, feats, level.
   * @param array $item_data    Item definition: level, rarity, is_magical, is_snare, traits.
   *
   * @return array{valid: bool, failures: string[]}
   */
  public function validateMagicCraftPrereqs(array $char_state, array $item_data): array {
    $failures = [];
    $item_level  = (int) ($item_data['level'] ?? 0);
    $char_level  = (int) ($char_state['basicInfo']['level'] ?? 0);
    $craft_rank  = (int) ($char_state['crafting_rank'] ?? $char_state['skills']['crafting']['rank'] ?? 0);
    $feats       = $char_state['feats'] ?? [];
    $feat_ids    = array_column($feats, 'id');

    // REQ 2432: Character level ≥ item level.
    if ($char_level < $item_level) {
      $failures[] = "character_level_too_low: Level {$char_level} < item level {$item_level}.";
    }

    // REQ 2432: Crafting proficiency gate by item level.
    $required_rank = 1;
    foreach (self::CRAFT_PROFICIENCY_GATES as $gate) {
      if ($item_level <= $gate['max_level']) {
        $required_rank = $gate['min_rank'];
        break;
      }
    }
    if ($craft_rank < $required_rank) {
      $rank_names = [1 => 'Trained', 2 => 'Expert', 3 => 'Master', 4 => 'Legendary'];
      $failures[] = "crafting_proficiency: Requires {$rank_names[$required_rank]} Crafting; have rank {$craft_rank}.";
    }

    $traits = array_map('strtolower', $item_data['traits'] ?? []);

    // REQ 2434: Feat requirements.
    if (in_array('magical', $traits, TRUE) || !empty($item_data['is_magical'])) {
      if (!in_array('magical-crafting', $feat_ids, TRUE) && !in_array('magical_crafting', $feat_ids, TRUE)) {
        $failures[] = 'missing_feat: Magical Crafting feat required for magical items.';
      }
    }
    if (in_array('alchemical', $traits, TRUE)) {
      if (!in_array('alchemical-crafting', $feat_ids, TRUE) && !in_array('alchemical_crafting', $feat_ids, TRUE)) {
        $failures[] = 'missing_feat: Alchemical Crafting feat required for alchemical items.';
      }
    }
    if (!empty($item_data['is_snare']) || in_array('snare', $traits, TRUE)) {
      if (!in_array('snare-crafting', $feat_ids, TRUE) && !in_array('snare_crafting', $feat_ids, TRUE)) {
        $failures[] = 'missing_feat: Snare Crafting feat required for snares.';
      }
    }

    return ['valid' => empty($failures), 'failures' => $failures];
  }

  /**
   * Calculate item upgrade crafting cost (REQ 2435).
   *
   * @param float $new_price_gp   New item price in gold.
   * @param float $old_price_gp   Old item price in gold.
   *
   * @return float   Gold cost to upgrade.
   */
  public function calculateUpgradeCost(float $new_price_gp, float $old_price_gp): float {
    return max(0.0, $new_price_gp - $old_price_gp);
  }

  // ---------------------------------------------------------------------------
  // Precious materials (REQs 2436–2447)
  // ---------------------------------------------------------------------------

  /**
   * Validate that a precious material can be applied at a given grade/level.
   *
   * REQ 2436: One precious material per item.
   * REQ 2437: Grade proficiency requirements.
   * REQ 2438: Item level must meet grade minimum.
   */
  public function validatePreciousMaterial(
    string $material,
    string $grade,
    int $item_level,
    int $craft_rank
  ): array {
    $failures = [];

    if (!isset(self::PRECIOUS_MATERIAL_STATS[$material])) {
      $failures[] = "unknown_material: '{$material}' is not a recognized precious material.";
      return ['valid' => FALSE, 'failures' => $failures];
    }

    $grade = strtolower($grade);
    $required_rank = self::PRECIOUS_GRADE_CRAFT_RANK[$grade] ?? 4;
    if ($craft_rank < $required_rank) {
      $rank_names = [2 => 'Expert', 3 => 'Master', 4 => 'Legendary'];
      $failures[] = "proficiency: {$grade}-grade requires {$rank_names[$required_rank]} Crafting.";
    }

    $max_level = self::PRECIOUS_GRADE_MAX_LEVEL[$grade] ?? 99;
    if ($item_level > $max_level) {
      $failures[] = "item_level: {$grade}-grade {$material} supports max item level {$max_level}.";
    }

    return ['valid' => empty($failures), 'failures' => $failures];
  }

  /**
   * Get Hardness/HP/BT for a precious material at a given grade (REQ 2439).
   *
   * @return array{hardness: int, hp: int, bt: int}
   */
  public function getPreciousMaterialStats(string $material, string $grade): array {
    $stats = self::PRECIOUS_MATERIAL_STATS[$material][$grade] ?? [5, 20, 10];
    return ['hardness' => $stats[0], 'hp' => $stats[1], 'bt' => $stats[2]];
  }

  /**
   * Calculate precious material investment cost as fraction of item price (REQ 2438).
   *
   * @return float  Fraction of item price (0.10, 0.25, or 1.0).
   */
  public function getPreciousMaterialInvestmentFraction(string $grade): float {
    return ['low' => 0.10, 'standard' => 0.25, 'high' => 1.0][strtolower($grade)] ?? 0.10;
  }

  /**
   * Apply multi-material rule — use strongest Hardness (REQ 2444).
   */
  public function resolveMultiMaterialStats(array $material_stats_list): array {
    $best = ['hardness' => 0, 'hp' => 0, 'bt' => 0];
    foreach ($material_stats_list as $stats) {
      if (($stats['hardness'] ?? 0) > $best['hardness']) {
        $best = $stats;
      }
    }
    return $best;
  }

  /**
   * Apply special precious material effects on Strike (REQs 2445–2447).
   *
   * @param string $material  Precious material type.
   * @param string $degree    Degree of success: critical_success, success, failure, critical_failure.
   * @param array  $target_data  Target creature data.
   * @param array  $game_state
   *
   * @return array  Special effect results.
   */
  public function applyPreciousMaterialEffect(
    string $material,
    string $degree,
    array $target_data,
    array &$game_state
  ): array {
    $effects = [];

    if ($material === 'cold_iron' && $degree === 'critical_failure') {
      // REQ 2445: Cold iron — Sickened 1 on crit fail unarmed vs weak-to-cold-iron creature.
      $effects['attacker_sickened'] = 1;
    }

    if ($material === 'adamantine') {
      // REQ 2446: Adamantine — halve Hardness of struck objects.
      $effects['hardness_halved'] = TRUE;
    }

    if ($material === 'darkwood') {
      // REQ 2447: Darkwood — reduce bulk by 1 (not applied at strike; handled at equip time).
      $effects['bulk_reduction'] = 1;
    }

    if ($material === 'mithral') {
      // REQ 2447: Mithral counts as silver; bulk -1; armor: –2 Str, –5 speed (handled at equip).
      $effects['counts_as_silver'] = TRUE;
      $effects['bulk_reduction'] = 1;
    }

    if ($material === 'dragonhide') {
      // REQ 2447: Dragonhide — immunity to dragon type's damage; +1 circumstance to AC/saves vs that type.
      // Caller retrieves dragonhide_type from item_data and applies immunity/bonus accordingly.
      $effects['dragon_damage_immunity'] = TRUE;
      $effects['circumstance_ac_vs_dragon'] = 1;
      $effects['circumstance_save_vs_dragon'] = 1;
    }

    return $effects;
  }

  /**
   * Apply orichalcum self-repair check (REQ 2447).
   *
   * Called at the start of a new day to restore HP on damaged-but-not-destroyed orichalcum items.
   */
  public function applyOrichalcumSelfRepair(array &$item_instance): bool {
    if (($item_instance['precious_material'] ?? '') !== 'orichalcum') {
      return FALSE;
    }
    $max_hp = (int) ($item_instance['stats']['max_hp'] ?? 0);
    $cur_hp = (int) ($item_instance['stats']['current_hp'] ?? 0);
    $is_destroyed = !empty($item_instance['state']['destroyed']);
    if (!$is_destroyed && $cur_hp < $max_hp) {
      $item_instance['stats']['current_hp'] = $max_hp;
      $item_instance['state']['broken'] = FALSE;
      return TRUE;
    }
    return FALSE;
  }

  // ---------------------------------------------------------------------------
  // Rune system (REQs 2448–2463)
  // ---------------------------------------------------------------------------

  /**
   * Get available property rune slots for an item (REQ 2449).
   *
   * Property slots = potency rune value. Orichalcum gets +4.
   * Specific magic items have 0 property slots (REQ 2462).
   *
   * @param array $item_data  Item with rune_data and precious_material fields.
   *
   * @return int  Number of property rune slots.
   */
  public function getPropertyRuneSlots(array $item_data): int {
    if (!empty($item_data['specific_locked'])) {
      return 0; // REQ 2462: specific locked items have no property rune slots.
    }
    $potency = (int) ($item_data['rune_data']['potency'] ?? 0);
    $bonus_slots = ($item_data['precious_material'] ?? '') === 'orichalcum' ? 4 : 0;
    return $potency + $bonus_slots;
  }

  /**
   * Validate rune compatibility before etching/transfer (REQs 2454–2461).
   *
   * @param array  $item_data  Current item definition.
   * @param string $rune_type  'potency'|'striking'|'resilient'|'property'.
   * @param array  $rune_data  Rune definition: id, type, level, category ('fundamental'|'property').
   *
   * @return array{valid: bool, failures: string[]}
   */
  public function validateRuneCompatibility(array $item_data, string $rune_type, array $rune_data): array {
    $failures = [];
    $is_weapon  = in_array('weapon', $item_data['traits'] ?? [], TRUE) || ($item_data['type'] ?? '') === 'weapon';
    $is_armor   = in_array('armor', $item_data['traits'] ?? [], TRUE)  || ($item_data['type'] ?? '') === 'armor';

    // REQ 2450: Each item holds at most 1 armor potency, 1 resilient, 1 weapon potency, 1 striking.
    if ($rune_type === 'potency' && $is_weapon && !empty($item_data['rune_data']['potency'])) {
      $failures[] = 'duplicate_potency: Item already has a weapon potency rune.';
    }
    if ($rune_type === 'striking' && !empty($item_data['rune_data']['striking_tier'])) {
      $failures[] = 'duplicate_striking: Item already has a striking rune.';
    }
    if ($rune_type === 'resilient' && !empty($item_data['rune_data']['resilient_tier'])) {
      $failures[] = 'duplicate_resilient: Item already has a resilient rune.';
    }

    // REQ 2462: Specific locked items have 0 property slots.
    if ($rune_type === 'property' && !empty($item_data['specific_locked'])) {
      $failures[] = 'specific_locked: Property runes cannot be etched onto specific locked magic items.';
    }

    // REQ 2461: Duplicate property rune check (only higher applies; energy-resistant different types OK).
    if ($rune_type === 'property') {
      $existing_props = $item_data['rune_data']['property_runes'] ?? [];
      foreach ($existing_props as $existing) {
        if ($existing['id'] === $rune_data['id'] && (int) $existing['level'] >= (int) $rune_data['level']) {
          $failures[] = 'duplicate_property: Existing rune is same level or higher; only higher level applies.';
          break;
        }
        // Different energy-resistance types are allowed (exception in REQ 2461).
        $is_energy_resist = ($rune_data['is_energy_resistant'] ?? FALSE) && ($existing['is_energy_resistant'] ?? FALSE);
        if ($is_energy_resist && $existing['damage_type'] !== ($rune_data['damage_type'] ?? '')) {
          // Different energy type — compatible.
          continue;
        }
        if ($existing['category'] === 'energy_resistant' && ($existing['damage_type'] ?? '') === ($rune_data['damage_type'] ?? '')) {
          $failures[] = 'duplicate_energy_rune: Same energy resistance type already present.';
          break;
        }
      }
    }

    // REQ 2459: Only same-category swaps.
    if (!empty($rune_data['category']) && !empty($item_data['rune_data'])) {
      // Check that the transfer destination matches category — validation logic delegated to caller context.
    }

    return ['valid' => empty($failures), 'failures' => $failures];
  }

  /**
   * Etch a rune onto an item (REQs 2453–2454).
   *
   * @param array  $item_data  Item definition (mutated).
   * @param string $rune_type  'potency'|'striking'|'resilient'|'property'.
   * @param array  $rune_data  Rune definition.
   * @param array  $char_state  Character state.
   *
   * @return array{success: bool, failures: string[]}
   */
  public function etchRune(array &$item_data, string $rune_type, array $rune_data, array $char_state): array {
    $compat = $this->validateRuneCompatibility($item_data, $rune_type, $rune_data);
    if (!$compat['valid']) {
      return ['success' => FALSE, 'failures' => $compat['failures']];
    }

    // REQ 2453: Magical Crafting feat required.
    $feat_ids = array_column($char_state['feats'] ?? [], 'id');
    if (!in_array('magical-crafting', $feat_ids, TRUE) && !in_array('magical_crafting', $feat_ids, TRUE)) {
      return ['success' => FALSE, 'failures' => ['missing_feat: Magical Crafting feat required to etch runes.']];
    }

    switch ($rune_type) {
      case 'potency':
        $item_data['rune_data']['potency'] = (int) ($rune_data['potency_value'] ?? 1);
        break;
      case 'striking':
        $item_data['rune_data']['striking_tier'] = (int) ($rune_data['tier'] ?? 1);
        break;
      case 'resilient':
        $item_data['rune_data']['resilient_tier'] = (int) ($rune_data['tier'] ?? 1);
        break;
      case 'property':
        $item_data['rune_data']['property_runes'][] = $rune_data;
        break;
    }

    // REQ 2452: Any armor with etched rune gets invested trait.
    if (in_array('armor', $item_data['traits'] ?? [], TRUE) || ($item_data['type'] ?? '') === 'armor') {
      $item_data['investment_required'] = TRUE;
    }

    // REQ 2451: Effective level = max(base level, all rune levels).
    $this->updateEffectiveLevel($item_data);

    return ['success' => TRUE, 'failures' => []];
  }

  /**
   * Transfer a rune between items (REQs 2455–2458).
   *
   * Cost: 10% of rune price; minimum 1 day; incompatible = crit fail.
   *
   * @param array  $source_item   Item data to transfer FROM (mutated).
   * @param array  $dest_item     Item data to transfer TO (mutated).
   * @param string $rune_type     Rune type to transfer.
   * @param array  $rune_data     Rune definition.
   * @param int    $craft_bonus   Character's Crafting bonus.
   * @param bool   $from_runestone  Free if from runestone (REQ 2456).
   *
   * @return array{success: bool, degree: string, cost_gp: float, failures: string[]}
   */
  public function transferRune(
    array &$source_item,
    array &$dest_item,
    string $rune_type,
    array $rune_data,
    int $craft_bonus,
    bool $from_runestone = FALSE
  ): array {
    // REQ 2457: Incompatible rune transfer = automatic crit fail.
    $compat = $this->validateRuneCompatibility($dest_item, $rune_type, $rune_data);
    if (!$compat['valid']) {
      return ['success' => FALSE, 'degree' => 'critical_failure', 'cost_gp' => 0.0, 'failures' => $compat['failures']];
    }

    // Determine DC by rune level.
    $rune_level = (int) ($rune_data['level'] ?? 1);
    $dc = $this->runeLevelDc($rune_level);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $craft_bonus;
    $degree = $this->degreeOfSuccess($total, $dc, $d20);

    // REQ 2456: Free transfer from runestone.
    $cost_gp = $from_runestone ? 0.0 : (float) ($rune_data['price_gp'] ?? 0) * 0.10;

    if ($degree === 'critical_success' || $degree === 'success') {
      // Remove from source.
      $this->removeRuneFromItem($source_item, $rune_type, $rune_data);
      // Add to destination.
      $this->applyRuneToItem($dest_item, $rune_type, $rune_data);
      $this->updateEffectiveLevel($dest_item);
      return ['success' => TRUE, 'degree' => $degree, 'cost_gp' => $cost_gp, 'failures' => []];
    }

    return ['success' => FALSE, 'degree' => $degree, 'cost_gp' => $cost_gp, 'failures' => ['transfer_failed: Crafting check failed.']];
  }

  /**
   * Mark property runes as dormant when potency rune is removed (REQ 2460).
   */
  public function applyOrphanedRuneDormancy(array &$item_data): void {
    $potency = (int) ($item_data['rune_data']['potency'] ?? 0);
    if ($potency === 0) {
      $props = $item_data['rune_data']['property_runes'] ?? [];
      foreach ($props as &$prop) {
        if ($prop['category'] !== 'fundamental') {
          $prop['dormant'] = TRUE;
        }
      }
      unset($prop);
      $item_data['rune_data']['property_runes'] = $props;
    }
  }

  // ---------------------------------------------------------------------------
  // Alchemical items (REQs 2464–2476)
  // ---------------------------------------------------------------------------

  /**
   * Apply bomb throw with splash damage (REQs 2467–2469).
   *
   * REQ 2467: Bomb is a martial thrown weapon Strike; gains manipulate trait.
   * REQ 2468: Splash applies within 5 feet on any outcome except crit fail.
   * REQ 2469: Resistance/weakness — combine splash + initial before applying.
   *
   * @param array $bomb_data    Bomb item definition: damage, damage_type, splash_damage.
   * @param array $target_data  Primary target.
   * @param array $adjacent     Adjacent creatures (for splash).
   * @param string $degree      Degree of success.
   *
   * @return array{primary_damage: int, splash_damage: int, splash_targets: array, traits: string[]}
   */
  public function applyBombThrow(array $bomb_data, array $target_data, array $adjacent, string $degree): array {
    $base_damage   = (int) ($bomb_data['damage'] ?? 0);
    $splash_amount = (int) ($bomb_data['splash_damage'] ?? 0);
    $damage_type   = $bomb_data['damage_type'] ?? 'fire';

    $primary_damage = 0;
    if ($degree === 'critical_success') {
      $primary_damage = $base_damage * 2;  // Crit doubles
    }
    elseif ($degree === 'success') {
      $primary_damage = $base_damage;
    }
    // No primary damage on failure/crit_fail.

    // REQ 2468: Splash on all outcomes except crit_fail.
    $splash_targets = [];
    if ($degree !== 'critical_failure') {
      foreach ($adjacent as $adj) {
        $splash_targets[] = ['id' => $adj['id'] ?? '', 'damage' => $splash_amount, 'damage_type' => $damage_type];
      }
      // Attacker is also in splash zone — no attacker damage here, just record.
    }

    // REQ 2469: Combine primary + splash for the main target before resistance/weakness.
    $combined_primary = $primary_damage + ($degree !== 'critical_failure' ? $splash_amount : 0);

    return [
      'primary_damage'               => $primary_damage,
      'splash_damage'                => $splash_amount,
      'combined_damage_primary_target' => $combined_primary,
      'splash_targets'               => $splash_targets,
      'traits'                       => ['manipulate'],
      'damage_type'                  => $damage_type,
    ];
  }

  /**
   * Apply injury poison to a weapon on Strike (REQs 2476).
   *
   * - Consumed on crit_fail Strike (even if immune).
   * - Remains on weapon after failed Strike.
   * - Consumed after successful piercing/slashing Strike.
   */
  public function applyInjuryPoison(array &$weapon_instance, string $degree, string $damage_type): array {
    $poison = $weapon_instance['applied_poison'] ?? NULL;
    if (!$poison) {
      return ['poison_applied' => FALSE, 'poison_consumed' => FALSE];
    }

    $consumes = ['critical_success', 'success', 'critical_failure'];
    $is_piercing_slashing = in_array(strtolower($damage_type), ['piercing', 'slashing'], TRUE);

    if ($degree === 'critical_failure') {
      unset($weapon_instance['applied_poison']);
      return ['poison_applied' => FALSE, 'poison_consumed' => TRUE];
    }

    if (in_array($degree, ['critical_success', 'success'], TRUE) && $is_piercing_slashing) {
      $applied_poison = $poison;
      unset($weapon_instance['applied_poison']);
      return ['poison_applied' => TRUE, 'poison_data' => $applied_poison, 'poison_consumed' => TRUE];
    }

    return ['poison_applied' => FALSE, 'poison_consumed' => FALSE];
  }

  // ---------------------------------------------------------------------------
  // Alchemical: mutagens + other poison exposure types (REQs 2470–2475)
  // ---------------------------------------------------------------------------

  /**
   * Apply a mutagen to a character (REQs 2470–2471).
   *
   * REQ 2470: Benefit + Drawback apply simultaneously; polymorph effect.
   * REQ 2471: New polymorph attempt counteracts existing using item level.
   *
   * @param string $char_id
   * @param array  $mutagen_data  Must include: level, duration_rounds, benefit[], drawback[].
   * @param array  $game_state
   *
   * @return array{success: bool, reason: string, benefit: array, drawback: array, item_level: int}
   */
  public function applyMutagen(
    string $char_id,
    array $mutagen_data,
    array &$game_state
  ): array {
    $benefit         = $mutagen_data['benefit'] ?? [];
    $drawback        = $mutagen_data['drawback'] ?? [];
    $item_level      = (int) ($mutagen_data['level'] ?? 1);
    $duration_rounds = (int) ($mutagen_data['duration_rounds'] ?? 10);

    // REQ 2471: Counteract existing polymorph using item level (higher or equal wins).
    $existing = $game_state['characters'][$char_id]['polymorph'] ?? NULL;
    if ($existing) {
      $existing_level = (int) ($existing['item_level'] ?? 0);
      if ($item_level < $existing_level) {
        return ['success' => FALSE, 'reason' => 'Counteract failed: existing polymorph level is higher.', 'benefit' => [], 'drawback' => [], 'item_level' => $item_level];
      }
    }

    // REQ 2470: Apply benefit + drawback simultaneously as polymorph.
    $game_state['characters'][$char_id]['polymorph'] = [
      'type'            => 'mutagen',
      'item_level'      => $item_level,
      'benefit'         => $benefit,
      'drawback'        => $drawback,
      'duration_rounds' => $duration_rounds,
      'applied_at'      => time(),
    ];

    return ['success' => TRUE, 'reason' => 'Mutagen applied.', 'benefit' => $benefit, 'drawback' => $drawback, 'item_level' => $item_level];
  }

  /**
   * Place an inhaled poison cloud on a hex (REQs 2473–2474).
   *
   * REQ 2473: Inhaled — 10-ft cube cloud; entering cloud triggers save.
   * REQ 2474: Holding breath (1 action): +2 circumstance to save for 1 round.
   *
   * @return array{cloud_placed: bool, hex_id: string, duration_rounds: int}
   */
  public function applyInhaledPoison(
    string $hex_id,
    array $poison_data,
    array &$game_state
  ): array {
    $game_state['map']['poison_clouds'][$hex_id] = [
      'poison_data'     => $poison_data,
      'duration_rounds' => 10, // 1 minute = 10 combat rounds
      'applied_at'      => time(),
    ];
    return ['cloud_placed' => TRUE, 'hex_id' => $hex_id, 'duration_rounds' => 10];
  }

  /**
   * Check whether a character entering a hex triggers an inhaled poison save.
   *
   * REQ 2474: +2 circumstance bonus to the save if character is holding breath.
   *
   * @return array{triggered: bool, poison_data?: array, circumstance_bonus: int}
   */
  public function checkInhaledPoisonEntry(
    string $char_id,
    string $hex_id,
    array &$game_state
  ): array {
    $cloud = $game_state['map']['poison_clouds'][$hex_id] ?? NULL;
    if (!$cloud) {
      return ['triggered' => FALSE, 'circumstance_bonus' => 0];
    }
    $holding_breath     = !empty($game_state['characters'][$char_id]['holding_breath']);
    $circumstance_bonus = $holding_breath ? 2 : 0;
    return ['triggered' => TRUE, 'poison_data' => $cloud['poison_data'], 'circumstance_bonus' => $circumstance_bonus];
  }

  /**
   * Hold breath action: +2 circumstance vs inhaled poisons for 1 round (REQ 2474).
   */
  public function holdBreath(string $char_id, array &$game_state): void {
    $game_state['characters'][$char_id]['holding_breath'] = TRUE;
  }

  /**
   * Apply a contact poison on skin contact (REQ 2473).
   *
   * REQ 2473: Contact — triggers on touch; save triggered on contact.
   *
   * @return array{poison_applied: bool, poison_data?: array}
   */
  public function applyContactPoison(
    string $target_id,
    array $poison_data,
    array &$game_state
  ): array {
    $game_state['characters'][$target_id]['pending_saves'][] = [
      'type'        => 'contact_poison',
      'poison_data' => $poison_data,
    ];
    return ['poison_applied' => TRUE, 'poison_data' => $poison_data];
  }

  /**
   * Apply an ingested poison on consumption (REQ 2473).
   *
   * REQ 2473: Ingested — triggers when consumed; save triggered on ingestion.
   *
   * @return array{poison_applied: bool, poison_data: array}
   */
  public function applyIngestedPoison(
    string $target_id,
    array $poison_data,
    array &$game_state
  ): array {
    $game_state['characters'][$target_id]['pending_saves'][] = [
      'type'        => 'ingested_poison',
      'poison_data' => $poison_data,
    ];
    return ['poison_applied' => TRUE, 'poison_data' => $poison_data];
  }

  // ---------------------------------------------------------------------------
  // Consumables: scrolls (REQs 2482–2488)
  // ---------------------------------------------------------------------------

  /**
   * Cast a spell from a scroll.
   *
   * REQ 2482: Spell must be on caster's spell list.
   * REQ 2483: Uses caster's attack/DC and tradition.
   * REQ 2484: Material components replaced by somatic.
   * REQ 2485: Scroll destroyed on use.
   *
   * @param array  $scroll_data  Scroll item: stored_spell, spell_level, tradition.
   * @param array  $char_data    Character: spell_list, spell_attack, spell_dc, tradition.
   * @param array  $game_state
   *
   * @return array{success: bool, reason: string, spell: string, spell_level: int}
   */
  public function castFromScroll(array $scroll_data, array $char_data, array &$game_state): array {
    $spell_id  = $scroll_data['stored_spell'] ?? '';
    $spell_lvl = (int) ($scroll_data['spell_level'] ?? 0);

    if (!$spell_id) {
      return ['success' => FALSE, 'reason' => 'Scroll has no stored spell.', 'spell' => '', 'spell_level' => 0];
    }

    // REQ 2482: Spell must be on caster's spell list.
    $spell_list = $char_data['spell_list'] ?? [];
    $has_spell  = in_array($spell_id, $spell_list, TRUE);
    if (!$has_spell) {
      return ['success' => FALSE, 'reason' => "Spell '{$spell_id}' is not on your spell list.", 'spell' => $spell_id, 'spell_level' => $spell_lvl];
    }

    return ['success' => TRUE, 'reason' => 'Casting from scroll.', 'spell' => $spell_id, 'spell_level' => $spell_lvl, 'components' => ['somatic']];
  }

  // ---------------------------------------------------------------------------
  // Talismans (REQs 2489–2494)
  // ---------------------------------------------------------------------------

  /**
   * Affix a talisman to an item (REQ 2490).
   *
   * REQ 2490: One per item; multiple talismans = all deactivated.
   * REQ 2491: Affix = 10 minutes + repair kit + two hands.
   * REQ 2492: Must be wielding/wearing item to activate; talisman destroyed on activation.
   */
  public function affixTalisman(
    string $item_instance_id,
    array $talisman_data,
    array &$game_state
  ): array {
    $current = $game_state['magic_items']['talismans'][$item_instance_id] ?? [];

    // REQ 2490: Multiple talismans deactivate all.
    if (!empty($current)) {
      $game_state['magic_items']['talismans'][$item_instance_id][] = array_merge($talisman_data, ['deactivated' => TRUE]);
      // Deactivate existing.
      foreach ($game_state['magic_items']['talismans'][$item_instance_id] as &$t) {
        $t['deactivated'] = TRUE;
      }
      unset($t);
      return ['success' => FALSE, 'reason' => 'Multiple talismans affixed; all deactivated.', 'all_deactivated' => TRUE];
    }

    $game_state['magic_items']['talismans'][$item_instance_id] = [array_merge($talisman_data, ['deactivated' => FALSE])];
    return ['success' => TRUE, 'reason' => 'Talisman affixed.', 'all_deactivated' => FALSE];
  }

  /**
   * Activate a talisman (REQ 2492).
   *
   * Talisman is destroyed after activation; item must be wielded/worn.
   */
  public function activateTalisman(
    string $item_instance_id,
    string $char_id,
    array &$game_state
  ): array {
    $talismans = $game_state['magic_items']['talismans'][$item_instance_id] ?? [];
    if (empty($talismans)) {
      return ['success' => FALSE, 'reason' => 'No talisman affixed to this item.'];
    }

    $active = array_values(array_filter($talismans, fn($t) => empty($t['deactivated'])));
    if (empty($active)) {
      return ['success' => FALSE, 'reason' => 'Talisman is deactivated (multiple talismans affixed).'];
    }

    $talisman = $active[0];
    // Destroy after activation.
    $game_state['magic_items']['talismans'][$item_instance_id] = [];
    return ['success' => TRUE, 'reason' => 'Talisman activated and destroyed.', 'talisman' => $talisman];
  }

  // ---------------------------------------------------------------------------
  // Staves (REQs 2495–2505)
  // ---------------------------------------------------------------------------

  /**
   * Prepare a staff during Daily Preparations (REQs 2495–2501).
   *
   * REQ 2495: Only one preparer per day; only that character expends charges.
   * REQ 2498: Base charges = preparer's highest-level spell slot level.
   * REQ 2499: Prepared caster may sacrifice spell slot → add charges equal to slot level (once/day).
   * REQ 2500: Spontaneous caster: 1 charge + 1 spell slot to cast.
   */
  public function prepareStaff(
    string $item_instance_id,
    string $char_id,
    array $char_data,
    array &$game_state,
    ?int $sacrificed_slot_level = NULL
  ): array {
    // REQ 2495: One preparer per day.
    $existing_preparer = $game_state['magic_items']['staff_state'][$item_instance_id]['preparer'] ?? NULL;
    if ($existing_preparer && $existing_preparer !== $char_id) {
      return ['success' => FALSE, 'reason' => 'Staff already prepared by another character today.'];
    }

    // REQ 2497: Only one staff per day per character.
    $already_prepared = $game_state['magic_items']['char_staff_prepared'][$char_id] ?? NULL;
    if ($already_prepared && $already_prepared !== $item_instance_id) {
      return ['success' => FALSE, 'reason' => 'Character may only prepare one staff per day.'];
    }

    $highest_slot = (int) ($char_data['highest_spell_slot_level'] ?? 0);
    $base_charges = $highest_slot;

    if ($sacrificed_slot_level !== NULL && $sacrificed_slot_level > 0) {
      // REQ 2499: Sacrifice once per day for extra charges.
      if (!empty($game_state['magic_items']['staff_state'][$item_instance_id]['sacrificed_today'])) {
        return ['success' => FALSE, 'reason' => 'Already sacrificed a slot for this staff today.'];
      }
      $base_charges += $sacrificed_slot_level;
      $game_state['magic_items']['staff_state'][$item_instance_id]['sacrificed_today'] = TRUE;
    }

    $game_state['magic_items']['staff_state'][$item_instance_id] = array_merge(
      $game_state['magic_items']['staff_state'][$item_instance_id] ?? [],
      [
        'preparer'  => $char_id,
        'charges'   => $base_charges,
        'max_charges' => $base_charges,
        'prepared_at' => time(),
        'sacrificed_today' => !empty($game_state['magic_items']['staff_state'][$item_instance_id]['sacrificed_today']),
      ]
    );
    $game_state['magic_items']['char_staff_prepared'][$char_id] = $item_instance_id;

    return ['success' => TRUE, 'charges' => $base_charges, 'preparer' => $char_id];
  }

  /**
   * Cast a spell from a staff (REQs 2501–2503).
   *
   * REQ 2495: Only the preparer can expend charges.
   * REQ 2501: Casting costs charges equal to spell level.
   * REQ 2502: Spontaneous caster: 1 charge + 1 spell slot.
   * REQ 2503: Cantrip from staff costs 0 charges.
   */
  public function castFromStaff(
    string $item_instance_id,
    string $char_id,
    array $staff_item_data,
    string $spell_id,
    int $spell_level,
    array $char_data,
    array &$game_state
  ): array {
    $state = $game_state['magic_items']['staff_state'][$item_instance_id] ?? [];
    if (($state['preparer'] ?? '') !== $char_id) {
      return ['success' => FALSE, 'reason' => 'Only the preparer can expend staff charges.'];
    }

    // REQ 2505: Charges expire after 24 hours.
    $prepared_at = (int) ($state['prepared_at'] ?? 0);
    if ($prepared_at && (time() - $prepared_at) > 86400) {
      $game_state['magic_items']['staff_state'][$item_instance_id]['charges'] = 0;
      return ['success' => FALSE, 'reason' => 'Staff charges have expired (24 hours elapsed).'];
    }

    $spell_list = $staff_item_data['spell_list'] ?? [];
    $on_list = FALSE;
    foreach ($spell_list as $entry) {
      if (($entry['spell_id'] ?? '') === $spell_id && (int) ($entry['min_level'] ?? 0) <= $spell_level) {
        $on_list = TRUE;
        break;
      }
    }
    if (!$on_list) {
      return ['success' => FALSE, 'reason' => "Spell '{$spell_id}' at level {$spell_level} is not on this staff's spell list."];
    }

    $is_cantrip  = $spell_level === 0;
    $charge_cost = $is_cantrip ? 0 : $spell_level;
    $charges     = (int) ($state['charges'] ?? 0);

    if (!$is_cantrip && $charges < $charge_cost) {
      return ['success' => FALSE, 'reason' => "Insufficient charges ({$charges}); need {$charge_cost}."];
    }

    $game_state['magic_items']['staff_state'][$item_instance_id]['charges'] = max(0, $charges - $charge_cost);

    return ['success' => TRUE, 'spell' => $spell_id, 'spell_level' => $spell_level, 'charges_remaining' => max(0, $charges - $charge_cost)];
  }

  /**
   * Reset staff state at Daily Preparations (REQ 2506).
   */
  public function resetStaffState(string $item_instance_id, array &$game_state): void {
    unset($game_state['magic_items']['staff_state'][$item_instance_id]);
  }

  // ---------------------------------------------------------------------------
  // Wands (REQs 2507–2513)
  // ---------------------------------------------------------------------------

  /**
   * Cast from a wand (REQs 2507–2509).
   *
   * REQ 2507: No cantrips/focus/rituals from wands.
   * REQ 2508: Daily use limit = 1; spell must be on caster's spell list.
   * REQ 2509: Caster's attack/DC/tradition; material → somatic.
   */
  public function castFromWand(
    string $item_instance_id,
    array $wand_data,
    array $char_data,
    array &$game_state
  ): array {
    $state = $game_state['magic_items']['wand_state'][$item_instance_id] ?? [];

    if (!empty($state['used_today'])) {
      return ['success' => FALSE, 'reason' => 'Wand already used today.'];
    }

    $spell_id  = $wand_data['stored_spell'] ?? '';
    $spell_lvl = (int) ($wand_data['spell_level'] ?? 0);

    if ($spell_lvl === 0) {
      return ['success' => FALSE, 'reason' => 'Wands cannot contain cantrips.'];
    }

    $spell_list = $char_data['spell_list'] ?? [];
    if (!in_array($spell_id, $spell_list, TRUE)) {
      return ['success' => FALSE, 'reason' => "Spell '{$spell_id}' is not on your spell list."];
    }

    $game_state['magic_items']['wand_state'][$item_instance_id]['used_today'] = TRUE;
    return ['success' => TRUE, 'spell' => $spell_id, 'spell_level' => $spell_lvl, 'components' => ['somatic']];
  }

  /**
   * Overcharge a wand (REQs 2510–2511).
   *
   * REQ 2510: Flat DC 10; success = Broken, failure = Destroyed.
   * REQ 2511: Overcharging already-overcharged = auto Destroyed, no spell.
   */
  public function overchargeWand(string $item_instance_id, array &$game_state): array {
    $state = $game_state['magic_items']['wand_state'][$item_instance_id] ?? [];

    // REQ 2511: Already overcharged = auto Destroyed.
    if (!empty($state['overcharged'])) {
      $game_state['magic_items']['wand_state'][$item_instance_id]['destroyed'] = TRUE;
      return ['success' => FALSE, 'destroyed' => TRUE, 'spell_cast' => FALSE, 'reason' => 'Already overcharged; wand destroyed.'];
    }

    if (!empty($state['used_today'])) {
      return ['success' => FALSE, 'destroyed' => FALSE, 'spell_cast' => FALSE, 'reason' => 'Wand already used; overcharge is the second use.'];
    }

    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $degree = $this->degreeOfSuccess($d20, self::WAND_OVERCHARGE_DC, $d20);

    $game_state['magic_items']['wand_state'][$item_instance_id]['overcharged'] = TRUE;
    $game_state['magic_items']['wand_state'][$item_instance_id]['used_today']  = TRUE;

    if ($degree === 'critical_success' || $degree === 'success') {
      $game_state['magic_items']['wand_state'][$item_instance_id]['broken'] = TRUE;
      return ['success' => TRUE, 'destroyed' => FALSE, 'broken' => TRUE, 'spell_cast' => TRUE, 'degree' => $degree, 'roll' => $d20];
    }

    $game_state['magic_items']['wand_state'][$item_instance_id]['destroyed'] = TRUE;
    return ['success' => FALSE, 'destroyed' => TRUE, 'spell_cast' => FALSE, 'degree' => $degree, 'roll' => $d20];
  }

  /**
   * Reset wand daily state at Daily Preparations.
   */
  public function resetWandState(string $item_instance_id, array &$game_state): void {
    $existing = $game_state['magic_items']['wand_state'][$item_instance_id] ?? [];
    $game_state['magic_items']['wand_state'][$item_instance_id] = [
      'used_today'   => FALSE,
      'overcharged'  => FALSE,
      'broken'       => $existing['broken'] ?? FALSE,
      'destroyed'    => $existing['destroyed'] ?? FALSE,
    ];
  }

  // ---------------------------------------------------------------------------
  // Oils (REQs 2474–2477)
  // ---------------------------------------------------------------------------

  /**
   * Apply a magical oil to an item or creature (REQs 2474–2477).
   *
   * REQ 2474: Applying oil requires two free hands and a single Interact action.
   * REQ 2475: Cannot be applied to an unwilling creature that is not incapacitated.
   * REQ 2476: Oil is consumed on application (remove from inventory).
   * REQ 2477: Effects from oil_data['effect'] are merged onto the target in game_state.
   *
   * @param string $char_id            Actor applying the oil.
   * @param string $oil_instance_id    Oil item instance being consumed.
   * @param array  $oil_data           Oil item data (effect, name, etc.).
   * @param array  $target_item_data   Target item/entity data; must include 'willing' or 'incapacitated'.
   * @param array  $game_state         Game state (passed by reference).
   *
   * @return array{success: bool, reason?: string}
   */
  public function applyOil(
    string $char_id,
    string $oil_instance_id,
    array $oil_data,
    array $target_item_data,
    array &$game_state
  ): array {
    // REQ 2475: Block on unwilling non-incapacitated targets.
    $is_creature = !empty($target_item_data['is_creature']);
    if ($is_creature) {
      $willing       = !empty($target_item_data['willing']);
      $incapacitated = !empty($target_item_data['incapacitated']);
      if (!$willing && !$incapacitated) {
        return ['success' => FALSE, 'reason' => 'Cannot apply oil to an unwilling, non-incapacitated creature.'];
      }
    }

    // REQ 2476: Consume oil.
    $game_state['magic_items']['consumed_oils'][] = $oil_instance_id;

    // REQ 2477: Apply effect.
    $effect = $oil_data['effect'] ?? [];
    if (!empty($effect)) {
      $target_id = $target_item_data['instance_id'] ?? $target_item_data['id'] ?? NULL;
      if ($target_id) {
        foreach ($effect as $key => $value) {
          $game_state['magic_items']['oil_effects'][$target_id][$key] = $value;
        }
      }
    }

    return ['success' => TRUE, 'oil_instance_id' => $oil_instance_id];
  }

  // ---------------------------------------------------------------------------
  // Snares (REQs 2514–2520)
  // ---------------------------------------------------------------------------

  /**
   * Craft and place a snare (REQs 2514–2519).
   *
   * REQ 2514: Requires Snare Crafting feat + snare kit.
   * REQ 2515: Occupies one 5-ft square; cannot be relocated.
   * REQ 2516: Quick craft = 1 minute at full price.
   * REQ 2517: Detection DC = creator's Crafting DC; Disable DC = same (Thievery).
   * REQ 2518: Expert+ crafter snares: only actively-searching creatures find them.
   *
   * @return array{success: bool, snare: array, failures: string[]}
   */
  public function craftSnare(
    array $snare_data,
    array $char_state,
    string $location_id,
    array &$game_state
  ): array {
    $feat_ids = array_column($char_state['feats'] ?? [], 'id');
    if (!in_array('snare-crafting', $feat_ids, TRUE) && !in_array('snare_crafting', $feat_ids, TRUE)) {
      return ['success' => FALSE, 'snare' => [], 'failures' => ['missing_feat: Snare Crafting feat required.']];
    }

    $has_kit = !empty($char_state['inventory']['snare_kit']) || !empty($char_state['has_snare_kit']);
    if (!$has_kit) {
      return ['success' => FALSE, 'snare' => [], 'failures' => ['missing_kit: Snare kit required.']];
    }

    // Validate snare type.
    $snare_type = $snare_data['snare_type'] ?? ($snare_data['type'] ?? NULL);
    if (!in_array($snare_type, self::VALID_SNARE_TYPES, TRUE)) {
      return ['success' => FALSE, 'snare' => [], 'failures' => [
        'invalid_snare_type: Must be one of: ' . implode(', ', self::VALID_SNARE_TYPES) . '.',
      ]];
    }

    $craft_rank = (int) ($char_state['crafting_rank'] ?? $char_state['skills']['crafting']['rank'] ?? 0);
    $craft_bonus = (int) ($char_state['skills']['crafting']['bonus'] ?? 0);
    $craft_dc  = 10 + $craft_bonus;

    // REQ 2518: Expert+ crafter — snare only found by actively searching.
    $requires_search = $craft_rank >= 2;

    // Placed square (grid hex {q, r} or NULL if placement is room-level).
    $placed_square = $snare_data['placed_square'] ?? NULL;

    // Block placement in an occupied square.
    if ($placed_square !== NULL) {
      foreach ($game_state['snares'][$location_id] ?? [] as $existing) {
        if (!($existing['triggered'] ?? FALSE) && !($existing['disarmed'] ?? FALSE)) {
          $es = $existing['placed_square'] ?? NULL;
          if ($es !== NULL
            && (int) $es['q'] === (int) $placed_square['q']
            && (int) $es['r'] === (int) $placed_square['r']
          ) {
            return ['success' => FALSE, 'snare' => [], 'failures' => ['occupied_square: A snare already occupies that square.']];
          }
        }
      }
    }

    $snare_instance = [
      'snare_id'        => $snare_data['id'] ?? uniqid('snare_', TRUE),
      'snare_type'      => $snare_type,
      'location_id'     => $location_id,
      'placed_square'   => $placed_square,
      'creator_id'      => $char_state['id'] ?? '',
      'detection_dc'    => $craft_dc,
      'disable_dc'      => $craft_dc,
      'requires_search' => $requires_search,
      'min_prof_detect' => $craft_rank >= 3 ? 3 : ($craft_rank >= 2 ? 2 : 1),
      'triggered'       => FALSE,
      'disarmed'        => FALSE,
      'level'           => (int) ($snare_data['level'] ?? 1),
      'data'            => $snare_data,
    ];

    $game_state['snares'][$location_id][] = $snare_instance;

    return ['success' => TRUE, 'snare' => $snare_instance, 'failures' => []];
  }

  /**
   * Creator disarms their own snare (REQ 2520).
   */
  public function creatorDisarmsSnare(
    string $char_id,
    string $location_id,
    int $snare_index,
    array &$game_state
  ): array {
    $snare = $game_state['snares'][$location_id][$snare_index] ?? NULL;
    if (!$snare) {
      return ['success' => FALSE, 'reason' => 'Snare not found.'];
    }
    if (($snare['creator_id'] ?? '') !== $char_id) {
      return ['success' => FALSE, 'reason' => 'Only the creator can use this instant disarm.'];
    }
    $game_state['snares'][$location_id][$snare_index]['disarmed'] = TRUE;
    return ['success' => TRUE, 'reason' => 'Snare disarmed in 1 action.'];
  }

  /**
   * Attempt to detect a snare at a hex (REQ 2517–2518).
   *
   * REQ 2517: Detection DC = creator's Crafting DC (stored on snare as detection_dc).
   * REQ 2518: Expert+ crafter snares require active searching; passive observers auto-fail.
   * REQ 2518: Minimum proficiency gate: detector must meet min_prof_detect rank.
   *
   * @param string $actor_id         Detecting entity.
   * @param string $location_id      Room/area ID.
   * @param array  $to_hex           Hex {q, r} being examined.
   * @param int    $perception_bonus  Actor's total Perception modifier.
   * @param int    $perception_rank   Actor's Perception proficiency rank (0=U,1=T,2=E,3=M,4=L).
   * @param bool   $is_searching      TRUE if the actor is actively Searching (not passive movement).
   * @param array  $game_state        Modified by reference to mark detected snares.
   *
   * @return array  List of detection result arrays, one per snare at the hex.
   *                Each: {snare_id, detected, reason, degree, roll, dc}
   */
  public function detectSnareAtHex(
    string $actor_id,
    string $location_id,
    array $to_hex,
    int $perception_bonus,
    int $perception_rank,
    bool $is_searching,
    array &$game_state
  ): array {
    $results = [];
    foreach ($game_state['snares'][$location_id] ?? [] as $idx => &$snare) {
      if ($snare['triggered'] || $snare['disarmed'] || !empty($snare['detected'][$actor_id])) {
        continue;
      }
      $ps = $snare['placed_square'] ?? NULL;
      if ($ps === NULL) {
        continue;
      }
      if ((int) $ps['q'] !== (int) $to_hex['q'] || (int) $ps['r'] !== (int) $to_hex['r']) {
        continue;
      }

      // REQ 2518: Expert+ crafter snares require active searching to find.
      if (!empty($snare['requires_search']) && !$is_searching) {
        $results[] = [
          'snare_id'   => $snare['snare_id'],
          'detected'   => FALSE,
          'reason'     => 'requires_active_search',
          'degree'     => NULL,
          'roll'       => NULL,
          'dc'         => $snare['detection_dc'] ?? 0,
        ];
        continue;
      }

      // Minimum proficiency gate.
      $min_rank = (int) ($snare['min_prof_detect'] ?? 1);
      if ($perception_rank < $min_rank) {
        $results[] = [
          'snare_id'   => $snare['snare_id'],
          'detected'   => FALSE,
          'reason'     => 'insufficient_proficiency',
          'degree'     => NULL,
          'roll'       => NULL,
          'dc'         => $snare['detection_dc'] ?? 0,
        ];
        continue;
      }

      $dc  = (int) ($snare['detection_dc'] ?? 15);
      $d20 = $this->numberGenerationService->rollPathfinderDie(20);
      $total = $d20 + $perception_bonus;
      $degree = $this->degreeOfSuccess($total, $dc, $d20);
      $detected = in_array($degree, ['success', 'critical_success'], TRUE);

      if ($detected) {
        $game_state['snares'][$location_id][$idx]['detected'][$actor_id] = TRUE;
      }

      $results[] = [
        'snare_id' => $snare['snare_id'],
        'detected' => $detected,
        'reason'   => $detected ? 'detected' : 'undetected',
        'degree'   => $degree,
        'roll'     => $total,
        'dc'       => $dc,
      ];
    }
    unset($snare);

    return $results;
  }

  /**
   * Disable a detected snare via Thievery check (REQ 2517).
   *
   * REQ 2517: Disable DC = creator's Crafting DC (stored on snare as disable_dc).
   * Minimum proficiency gate: same as detection (min_prof_detect).
   * Creator instant-disarm is handled by creatorDisarmsSnare() (no check needed).
   *
   * @param string $char_id         Character attempting to disable.
   * @param string $location_id     Room/area ID.
   * @param int    $snare_index     Index of snare in $game_state['snares'][$location_id].
   * @param int    $thievery_bonus  Character's total Thievery modifier.
   * @param int    $thievery_rank   Character's Thievery proficiency rank.
   * @param array  $game_state      Modified by reference on successful disable.
   *
   * @return array  {success, degree, roll, dc, reason}
   */
  public function disableSnare(
    string $char_id,
    string $location_id,
    int $snare_index,
    int $thievery_bonus,
    int $thievery_rank,
    array &$game_state
  ): array {
    $snare = $game_state['snares'][$location_id][$snare_index] ?? NULL;
    if (!$snare) {
      return ['success' => FALSE, 'reason' => 'Snare not found.'];
    }
    if ($snare['triggered'] || $snare['disarmed']) {
      return ['success' => FALSE, 'reason' => 'Snare already triggered or disarmed.'];
    }

    // Minimum proficiency gate.
    $min_rank = (int) ($snare['min_prof_detect'] ?? 1);
    if ($thievery_rank < $min_rank) {
      return [
        'success' => FALSE,
        'reason'  => 'insufficient_proficiency',
        'degree'  => NULL,
        'roll'    => NULL,
        'dc'      => $snare['disable_dc'] ?? 0,
      ];
    }

    $dc  = (int) ($snare['disable_dc'] ?? 15);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $thievery_bonus;
    $degree = $this->degreeOfSuccess($total, $dc, $d20);

    if (in_array($degree, ['success', 'critical_success'], TRUE)) {
      $game_state['snares'][$location_id][$snare_index]['disarmed'] = TRUE;
    }

    return [
      'success' => in_array($degree, ['success', 'critical_success'], TRUE),
      'degree'  => $degree,
      'roll'    => $total,
      'dc'      => $dc,
      'reason'  => in_array($degree, ['success', 'critical_success'], TRUE) ? 'disabled' : 'failed',
    ];
  }

  /**
   * Find and trigger any active snare at a given hex in the given location.
   *
   * Called by EncounterPhaseHandler::processStride() after position update.
   * Returns NULL if no snare was triggered; returns trigger result if one fires.
   *
   * @param string $target_id     Entity entering the square (triggering creature).
   * @param string $location_id   Room/area ID (key in $game_state['snares']).
   * @param array  $to_hex        Target hex {q, r}.
   * @param array  $game_state    Modified by reference when snare is triggered.
   *
   * @return array|null  NULL if no snare triggered; effect result array otherwise.
   */
  public function checkSnareAtHex(
    string $target_id,
    string $location_id,
    array $to_hex,
    array &$game_state
  ): ?array {
    if (empty($game_state['snares'][$location_id])) {
      return NULL;
    }

    foreach ($game_state['snares'][$location_id] as $idx => &$snare) {
      if ($snare['triggered'] || $snare['disarmed']) {
        continue;
      }
      $ps = $snare['placed_square'] ?? NULL;
      if ($ps === NULL) {
        continue;
      }
      if ((int) $ps['q'] !== (int) $to_hex['q'] || (int) $ps['r'] !== (int) $to_hex['r']) {
        continue;
      }

      // Mark triggered before resolving so recursive calls can't re-fire.
      $snare['triggered'] = TRUE;
      $game_state['snares'][$location_id][$idx]['triggered'] = TRUE;

      $effect = $this->triggerSnare($snare, $target_id, $game_state);
      return $effect;
    }
    unset($snare);

    return NULL;
  }

  /**
   * Resolve the mechanical effect of a triggered snare (dc-cr-snares feature).
   *
   * Snare types:
   *   alarm     — emits a loud noise (300 ft radius). No save.
   *   hampering — difficult terrain in that square for the triggering creature
   *               (persists for 1 minute / 10 rounds).
   *   marking   — applies 'marked' condition to triggering creature (visible
   *               marker useful for tracking; no save).
   *   striking  — deals physical piercing damage; damage = 2d6 per snare level.
   *               (No save; damage is halved on a separate per-AC check if caller
   *               passes snare_data['is_magical'], but basic damage is always dealt.)
   *
   * @param array  $snare_instance  The triggered snare instance.
   * @param string $target_id       Entity that triggered the snare.
   * @param array  $game_state      Modified by reference for condition/terrain changes.
   *
   * @return array  Effect result with keys: snare_type, target_id, effect.
   */
  public function triggerSnare(array $snare_instance, string $target_id, array &$game_state): array {
    $snare_type = $snare_instance['snare_type'] ?? 'unknown';
    $snare_id   = $snare_instance['snare_id'] ?? 'unknown';
    $level      = (int) ($snare_instance['level'] ?? 1);
    $effect     = [];

    switch ($snare_type) {
      case 'alarm':
        // Alarm snare: loud noise in 300 ft radius; record in game_state.
        if (!isset($game_state['snare_alarms'])) {
          $game_state['snare_alarms'] = [];
        }
        $game_state['snare_alarms'][] = [
          'snare_id'    => $snare_id,
          'location_id' => $snare_instance['location_id'] ?? '',
          'hex'         => $snare_instance['placed_square'] ?? NULL,
          'radius_ft'   => 300,
          'target_id'   => $target_id,
        ];
        $effect = ['alarm_radius_ft' => 300, 'message' => 'A loud alarm rings out!'];
        break;

      case 'hampering':
        // Hampering snare: difficult terrain for triggering creature for 10 rounds.
        if (!isset($game_state['snare_difficult_terrain'])) {
          $game_state['snare_difficult_terrain'] = [];
        }
        $game_state['snare_difficult_terrain'][$target_id][] = [
          'snare_id'     => $snare_id,
          'hex'          => $snare_instance['placed_square'] ?? NULL,
          'rounds_left'  => 10,
          'type'         => 'difficult',
        ];
        $effect = ['terrain' => 'difficult', 'duration_rounds' => 10, 'target_id' => $target_id];
        break;

      case 'marking':
        // Marking snare: applies 'marked' condition (no save).
        if (!isset($game_state['conditions'][$target_id])) {
          $game_state['conditions'][$target_id] = [];
        }
        $game_state['conditions'][$target_id][] = [
          'condition' => 'marked',
          'source'    => 'snare:' . $snare_id,
          'visible'   => TRUE,
        ];
        $effect = ['condition' => 'marked', 'target_id' => $target_id];
        break;

      case 'striking':
        // Striking snare: 2d6 piercing damage per snare level. No save required.
        $dice_count = 2 * max(1, $level);
        $damage = 0;
        for ($i = 0; $i < $dice_count; $i++) {
          $damage += $this->numberGenerationService->rollPathfinderDie(6);
        }
        if (!isset($game_state['snare_damage'])) {
          $game_state['snare_damage'] = [];
        }
        $game_state['snare_damage'][] = [
          'target_id'   => $target_id,
          'snare_id'    => $snare_id,
          'damage'      => $damage,
          'damage_type' => 'piercing',
        ];
        $effect = ['damage' => $damage, 'damage_type' => 'piercing', 'target_id' => $target_id];
        break;

      default:
        $effect = ['error' => "Unknown snare type: {$snare_type}."];
    }

    return [
      'snare_type' => $snare_type,
      'snare_id'   => $snare_id,
      'target_id'  => $target_id,
      'effect'     => $effect,
    ];
  }

  // ---------------------------------------------------------------------------
  // Worn items and apex items (REQs 2521–2529)
  // ---------------------------------------------------------------------------

  /**
   * Enforce worn slot uniqueness (REQ 2521).
   *
   * @param string $char_id
   * @param string $slot     Slot name (e.g., 'head', 'belt', 'ring', 'cloak').
   * @param string $item_instance_id
   * @param array  $game_state
   *
   * @return array{valid: bool, reason: string}
   */
  public function validateWornSlot(string $char_id, string $slot, string $item_instance_id, array &$game_state): array {
    // REQ 2522: Rings have no slot limit.
    if (strtolower($slot) === 'ring') {
      return ['valid' => TRUE, 'reason' => 'Rings allow multiple.'];
    }

    $current = $game_state['magic_items']['worn_slots'][$char_id][$slot] ?? NULL;
    if ($current && $current !== $item_instance_id) {
      return ['valid' => FALSE, 'reason' => "Worn slot '{$slot}' is already occupied."];
    }
    return ['valid' => TRUE, 'reason' => ''];
  }

  /**
   * Equip a worn item to its slot (REQ 2521).
   */
  public function equipWornItem(string $char_id, string $slot, string $item_instance_id, array &$game_state): array {
    $check = $this->validateWornSlot($char_id, $slot, $item_instance_id, $game_state);
    if (!$check['valid']) {
      return ['success' => FALSE, 'reason' => $check['reason']];
    }
    if (strtolower($slot) !== 'ring') {
      $game_state['magic_items']['worn_slots'][$char_id][$slot] = $item_instance_id;
    } else {
      $game_state['magic_items']['worn_slots'][$char_id]['rings'][] = $item_instance_id;
    }
    return ['success' => TRUE, 'reason' => 'Item equipped.'];
  }

  /**
   * REQ 2524: Explorer's clothing can be etched with armor runes.
   */
  public function allowArmorRuneOnExplorerClothing(array $item_data): bool {
    return strtolower($item_data['base_type'] ?? '') === "explorer's clothing";
  }

  // ---------------------------------------------------------------------------
  // Daily Preparations reset (REQ 2406, 2403, 2506)
  // ---------------------------------------------------------------------------

  /**
   * Full magic item state reset for a character at Daily Preparations.
   * Covers: investments, daily uses, staff prep, wand states.
   *
   * @param string $char_id
   * @param array  $char_data  Character state for determining staff defaults.
   * @param array  $owned_item_instances  Array of item instance IDs owned by char.
   * @param array  $game_state
   */
  public function performDailyPreparations(
    string $char_id,
    array $char_data,
    array $owned_item_instances,
    array &$game_state
  ): void {
    // REQ 2403: Reset investments.
    $this->resetDailyInvestments($char_id, $game_state);

    // REQ 2506: Reset staff state for staves the character prepared.
    $staff_prepared = $game_state['magic_items']['char_staff_prepared'][$char_id] ?? NULL;
    if ($staff_prepared) {
      $this->resetStaffState($staff_prepared, $game_state);
    }
    unset($game_state['magic_items']['char_staff_prepared'][$char_id]);

    // REQ 2513: Reset wand daily states.
    foreach ($owned_item_instances as $iid) {
      if (isset($game_state['magic_items']['wand_state'][$iid])) {
        $this->resetWandState($iid, $game_state);
      }
    }

    // Orichalcum self-repair for owned item instances (REQ 2447).
    // Caller passes item_instances as arrays with stats; mutate them externally.

    // Reset spell slots to full (all used counts → 0) for this character.
    if (isset($game_state['spells'][$char_id]['spell_slots'])) {
      foreach ($game_state['spells'][$char_id]['spell_slots'] as $level_key => &$slot) {
        $slot['used'] = 0;
      }
      unset($slot);
    }

    // Restore Focus Points to pool max.
    if (isset($game_state['spells'][$char_id]['focus_points'])) {
      $fp_max = (int) ($game_state['spells'][$char_id]['focus_points']['max'] ?? 0);
      $game_state['spells'][$char_id]['focus_points']['current'] = $fp_max;
    }

    // Reset innate spell used_today flags.
    if (isset($game_state['spells'][$char_id]['innate_spells'])) {
      foreach ($game_state['spells'][$char_id]['innate_spells'] as $spell_id => &$innate_def) {
        if (empty($innate_def['is_cantrip'])) {
          $innate_def['used_today'] = FALSE;
        }
      }
      unset($innate_def);
    }
  }

  // ---------------------------------------------------------------------------
  // Private helpers
  // ---------------------------------------------------------------------------

  /**
   * Update item effective level = max(base_level, all rune levels).
   */
  private function updateEffectiveLevel(array &$item_data): void {
    $base = (int) ($item_data['level'] ?? 0);
    $rune_data = $item_data['rune_data'] ?? [];
    $levels = [$base];
    // Potency rune levels by value.
    if (!empty($rune_data['potency'])) {
      $levels[] = $rune_data['potency'] <= 1 ? 2 : ($rune_data['potency'] <= 2 ? 10 : 16);
    }
    foreach ($rune_data['property_runes'] ?? [] as $prop) {
      $levels[] = (int) ($prop['level'] ?? 0);
    }
    $item_data['effective_level'] = max($levels);
  }

  /**
   * Simple DC table for rune level (mirrors DcAdjustmentService scale).
   */
  private function runeLevelDc(int $level): int {
    $table = [1 => 15, 2 => 16, 3 => 18, 4 => 19, 5 => 20, 6 => 22, 7 => 23, 8 => 24, 9 => 26, 10 => 27, 11 => 28, 12 => 30, 13 => 31, 14 => 32, 15 => 34, 16 => 35, 17 => 36, 18 => 38, 19 => 39, 20 => 40];
    return $table[max(1, min(20, $level))] ?? 15;
  }

  /**
   * Remove a rune from an item (used by transferRune).
   */
  private function removeRuneFromItem(array &$item, string $rune_type, array $rune_data): void {
    if ($rune_type === 'property') {
      $item['rune_data']['property_runes'] = array_values(
        array_filter($item['rune_data']['property_runes'] ?? [], fn($r) => ($r['id'] ?? '') !== ($rune_data['id'] ?? ''))
      );
    } elseif ($rune_type === 'potency') {
      $item['rune_data']['potency'] = 0;
    } elseif ($rune_type === 'striking') {
      $item['rune_data']['striking_tier'] = 0;
    } elseif ($rune_type === 'resilient') {
      $item['rune_data']['resilient_tier'] = 0;
    }
  }

  /**
   * Apply a rune to a destination item.
   */
  private function applyRuneToItem(array &$item, string $rune_type, array $rune_data): void {
    if ($rune_type === 'property') {
      $item['rune_data']['property_runes'][] = $rune_data;
    } elseif ($rune_type === 'potency') {
      $item['rune_data']['potency'] = (int) ($rune_data['potency_value'] ?? 1);
    } elseif ($rune_type === 'striking') {
      $item['rune_data']['striking_tier'] = (int) ($rune_data['tier'] ?? 1);
    } elseif ($rune_type === 'resilient') {
      $item['rune_data']['resilient_tier'] = (int) ($rune_data['tier'] ?? 1);
    }
  }

  /**
   * Calculate degree of success (mirrors CombatCalculator pattern).
   */
  private function degreeOfSuccess(int $total, int $dc, int $d20): string {
    $diff = $total - $dc;
    if ($d20 === 20) {
      return $diff >= 0 ? 'critical_success' : 'success';
    }
    if ($d20 === 1) {
      return $diff >= 10 ? 'success' : ($diff >= 0 ? 'failure' : 'critical_failure');
    }
    if ($diff >= 10) return 'critical_success';
    if ($diff >= 0)  return 'success';
    if ($diff >= -9) return 'failure';
    return 'critical_failure';
  }

}
