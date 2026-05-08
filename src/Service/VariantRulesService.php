<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Variant rules management for PF2e GMG Chapter 4.
 *
 * Manages feature flags for: free_archetype, ancestry_paragon,
 * automatic_bonus_progression (ABP), proficiency_without_level.
 *
 * Each rule is stored per-campaign; compatibility checks alert when a variant
 * conflicts with other active rules or campaign assumptions.
 *
 * Implements: dc-gmg-subsystems
 */
class VariantRulesService {

  const VALID_RULES = [
    'free_archetype',
    'ancestry_paragon',
    'automatic_bonus_progression',
    'proficiency_without_level',
  ];

  // Mutual-exclusion groups: rules in the same group cannot both be active.
  const CONFLICT_GROUPS = [
    // ABP and standard treasure/rune economy are in tension. Not hard-blocked
    // but flagged.
    ['automatic_bonus_progression', 'standard_runes'],
    // Proficiency Without Level changes all DCs, so paired with ABP it alters
    // math significantly — flag but allow.
  ];

  // Compatibility notes per rule: keyed on rule name -> [conflicting rule -> note].
  const COMPATIBILITY_NOTES = [
    'automatic_bonus_progression' => [
      'proficiency_without_level' => 'Both ABP and Proficiency Without Level alter math at every level. Combined, they dramatically reduce expected character power — suitable only for very low-fantasy campaigns.',
    ],
    'proficiency_without_level' => [
      'automatic_bonus_progression' => 'See ABP note.',
    ],
  ];

  // ABP bonus table: indexed by character level.
  // Values: [attack_potency, damage_striking, ac_resilient, saves_resilient, perception_sharp, skill_apex]
  // Based on PF2e GMG ABP table (Player Core / GMG 2nd edition).
  const ABP_TABLE = [
    1  => ['attack_potency' => 0, 'damage_striking' => 0, 'ac_resilient' => 0, 'saves_resilient' => 0, 'perception_sharp' => 0, 'skill_apex' => 0],
    2  => ['attack_potency' => 0, 'damage_striking' => 0, 'ac_resilient' => 0, 'saves_resilient' => 0, 'perception_sharp' => 0, 'skill_apex' => 0],
    3  => ['attack_potency' => 1, 'damage_striking' => 0, 'ac_resilient' => 0, 'saves_resilient' => 0, 'perception_sharp' => 0, 'skill_apex' => 0],
    4  => ['attack_potency' => 1, 'damage_striking' => 1, 'ac_resilient' => 0, 'saves_resilient' => 0, 'perception_sharp' => 0, 'skill_apex' => 0],
    5  => ['attack_potency' => 1, 'damage_striking' => 1, 'ac_resilient' => 1, 'saves_resilient' => 0, 'perception_sharp' => 0, 'skill_apex' => 0],
    6  => ['attack_potency' => 1, 'damage_striking' => 1, 'ac_resilient' => 1, 'saves_resilient' => 1, 'perception_sharp' => 0, 'skill_apex' => 0],
    7  => ['attack_potency' => 1, 'damage_striking' => 1, 'ac_resilient' => 1, 'saves_resilient' => 1, 'perception_sharp' => 0, 'skill_apex' => 0],
    8  => ['attack_potency' => 1, 'damage_striking' => 1, 'ac_resilient' => 1, 'saves_resilient' => 1, 'perception_sharp' => 1, 'skill_apex' => 0],
    9  => ['attack_potency' => 2, 'damage_striking' => 2, 'ac_resilient' => 1, 'saves_resilient' => 1, 'perception_sharp' => 1, 'skill_apex' => 0],
    10 => ['attack_potency' => 2, 'damage_striking' => 2, 'ac_resilient' => 2, 'saves_resilient' => 1, 'perception_sharp' => 1, 'skill_apex' => 0],
    11 => ['attack_potency' => 2, 'damage_striking' => 2, 'ac_resilient' => 2, 'saves_resilient' => 2, 'perception_sharp' => 1, 'skill_apex' => 0],
    12 => ['attack_potency' => 2, 'damage_striking' => 2, 'ac_resilient' => 2, 'saves_resilient' => 2, 'perception_sharp' => 2, 'skill_apex' => 0],
    13 => ['attack_potency' => 2, 'damage_striking' => 2, 'ac_resilient' => 2, 'saves_resilient' => 2, 'perception_sharp' => 2, 'skill_apex' => 0],
    14 => ['attack_potency' => 2, 'damage_striking' => 2, 'ac_resilient' => 2, 'saves_resilient' => 2, 'perception_sharp' => 2, 'skill_apex' => 1],
    15 => ['attack_potency' => 2, 'damage_striking' => 2, 'ac_resilient' => 2, 'saves_resilient' => 2, 'perception_sharp' => 2, 'skill_apex' => 1],
    16 => ['attack_potency' => 2, 'damage_striking' => 2, 'ac_resilient' => 2, 'saves_resilient' => 2, 'perception_sharp' => 2, 'skill_apex' => 1],
    17 => ['attack_potency' => 3, 'damage_striking' => 3, 'ac_resilient' => 2, 'saves_resilient' => 2, 'perception_sharp' => 2, 'skill_apex' => 1],
    18 => ['attack_potency' => 3, 'damage_striking' => 3, 'ac_resilient' => 3, 'saves_resilient' => 2, 'perception_sharp' => 2, 'skill_apex' => 1],
    19 => ['attack_potency' => 3, 'damage_striking' => 3, 'ac_resilient' => 3, 'saves_resilient' => 3, 'perception_sharp' => 2, 'skill_apex' => 1],
    20 => ['attack_potency' => 3, 'damage_striking' => 3, 'ac_resilient' => 3, 'saves_resilient' => 3, 'perception_sharp' => 3, 'skill_apex' => 2],
  ];

  // Proficiency Without Level: fixed bonus by rank (does NOT add character level).
  const PWL_BONUSES = [
    'untrained' => 0,
    'trained'   => 2,
    'expert'    => 4,
    'master'    => 6,
    'legendary' => 8,
  ];

  // Valid even levels for Free Archetype feat slots (2, 4, 6, ... 20).
  const FREE_ARCHETYPE_LEVELS = [2, 4, 6, 8, 10, 12, 14, 16, 18, 20];

  public function __construct(
    private readonly Connection $database
  ) {}

  // ── Campaign variant rules ────────────────────────────────────────────────

  /**
   * Get the variant rule configuration for a campaign.
   *
   * @param int $campaign_id
   * @return array
   *   Keyed by rule name => ['enabled' => bool, 'config' => array].
   */
  public function getVariantRules(int $campaign_id): array {
    $rows = $this->database->select('dc_variant_rule', 'v')
      ->fields('v')
      ->condition('campaign_id', $campaign_id)
      ->execute()
      ->fetchAllAssoc('rule_name', \PDO::FETCH_ASSOC);

    $result = [];
    foreach (self::VALID_RULES as $rule) {
      if (isset($rows[$rule])) {
        $result[$rule] = [
          'enabled'     => (bool) $rows[$rule]['enabled'],
          'config'      => json_decode($rows[$rule]['config_json'] ?? '{}', TRUE) ?? [],
          'updated'     => (int) $rows[$rule]['updated'],
        ];
      }
      else {
        $result[$rule] = ['enabled' => FALSE, 'config' => []];
      }
    }
    return $result;
  }

  /**
   * Enable or disable a variant rule for a campaign.
   *
   * @param int $campaign_id
   * @param string $rule
   *   One of VALID_RULES.
   * @param bool $enabled
   * @param array $config
   *   Optional rule-specific configuration (e.g. custom ABP table overrides).
   * @return array
   *   The updated rule record including any compatibility warnings.
   */
  public function setVariantRule(int $campaign_id, string $rule, bool $enabled, array $config = []): array {
    $this->assertValidRule($rule);
    $now      = time();
    $existing = $this->database->select('dc_variant_rule', 'v')
      ->fields('v', ['id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('rule_name', $rule)
      ->execute()
      ->fetchField();

    if ($existing) {
      $this->database->update('dc_variant_rule')
        ->fields([
          'enabled'     => (int) $enabled,
          'config_json' => json_encode($config),
          'updated'     => $now,
        ])
        ->condition('id', $existing)
        ->execute();
    }
    else {
      $this->database->insert('dc_variant_rule')
        ->fields([
          'campaign_id' => $campaign_id,
          'rule_name'   => $rule,
          'enabled'     => (int) $enabled,
          'config_json' => json_encode($config),
          'created'     => $now,
          'updated'     => $now,
        ])
        ->execute();
    }

    $compatibility = $this->checkCompatibility($campaign_id, $rule);
    return [
      'rule'          => $rule,
      'enabled'       => $enabled,
      'config'        => $config,
      'compatibility' => $compatibility,
    ];
  }

  /**
   * Check compatibility of a rule against all other active rules for a campaign.
   *
   * @param int $campaign_id
   * @param string $rule
   * @return array
   *   ['warnings' => [...], 'conflicts' => [...]]
   */
  public function checkCompatibility(int $campaign_id, string $rule): array {
    $this->assertValidRule($rule);
    $active = $this->getActiveRuleNames($campaign_id);
    $warnings  = [];
    $conflicts = [];

    $notes = self::COMPATIBILITY_NOTES[$rule] ?? [];
    foreach ($notes as $other_rule => $note) {
      if (in_array($other_rule, $active, TRUE)) {
        $warnings[] = [
          'with_rule' => $other_rule,
          'note'      => $note,
        ];
      }
    }

    return [
      'rule'     => $rule,
      'warnings' => $warnings,
      'conflicts' => $conflicts,
    ];
  }

  // ── ABP ───────────────────────────────────────────────────────────────────

  /**
   * Get ABP bonus values for a character level.
   *
   * If the campaign has a custom ABP config, merge overrides.
   *
   * @param int $level
   *   Character level 1–20.
   * @param int|null $campaign_id
   *   Optional: load campaign ABP overrides.
   * @return array
   */
  public function getAbpBonuses(int $level, ?int $campaign_id = NULL): array {
    if ($level < 1 || $level > 20) {
      throw new BadRequestHttpException("Level must be 1–20; got $level.");
    }
    $bonuses = self::ABP_TABLE[$level];

    if ($campaign_id !== NULL) {
      $overrides = $this->getCampaignAbpOverrides($campaign_id);
      if (!empty($overrides)) {
        $bonuses = array_replace($bonuses, $overrides);
      }
    }

    return [
      'level'   => $level,
      'bonuses' => $bonuses,
      'note'    => 'magic items may not provide item bonuses that stack with ABP values',
    ];
  }

  /**
   * Get the full ABP table (all levels), optionally with campaign overrides.
   */
  public function getFullAbpTable(?int $campaign_id = NULL): array {
    $table = [];
    foreach (self::ABP_TABLE as $level => $row) {
      $table[$level] = $this->getAbpBonuses($level, $campaign_id)['bonuses'];
    }
    return ['table' => $table, 'configurable' => TRUE];
  }

  // ── Proficiency Without Level ─────────────────────────────────────────────

  /**
   * Get the Proficiency Without Level fixed bonus for a given rank.
   *
   * @param string $rank
   *   One of: untrained, trained, expert, master, legendary.
   * @return array
   */
  public function getPwlBonus(string $rank): array {
    $rank = strtolower($rank);
    if (!array_key_exists($rank, self::PWL_BONUSES)) {
      throw new BadRequestHttpException("Invalid rank '$rank'. Valid: " . implode(', ', array_keys(self::PWL_BONUSES)));
    }
    return [
      'rank'        => $rank,
      'bonus'       => self::PWL_BONUSES[$rank],
      'note'        => 'Does not add character level. NPC DCs must also be recalculated to remove level component.',
    ];
  }

  /**
   * Get the full Proficiency Without Level bonus table.
   */
  public function getPwlTable(): array {
    $rows = [];
    foreach (self::PWL_BONUSES as $rank => $bonus) {
      $rows[] = ['rank' => $rank, 'bonus' => $bonus];
    }
    return [
      'table' => $rows,
      'note'  => 'Apply to all check modifiers, DCs, and saving throws. NPC DCs must also strip level component.',
    ];
  }

  // ── Free Archetype ────────────────────────────────────────────────────────

  /**
   * Get the free archetype feat level schedule.
   *
   * @return array
   */
  public function getFreeArchetypeSchedule(): array {
    return [
      'feat_levels' => self::FREE_ARCHETYPE_LEVELS,
      'rules' => [
        'The free archetype slot at each even level is separate from the normal class feat.',
        'Only archetype feats may be taken in the free archetype slot.',
        'Dedicated feats and class feats cannot fill the free archetype slot.',
        'The free archetype slot is unaffected if a class feature already grants an archetype feat at that level.',
      ],
    ];
  }

  // ── Ancestry Paragon ──────────────────────────────────────────────────────

  /**
   * Get the ancestry paragon feat schedule.
   *
   * Under Ancestry Paragon, ancestry feats are granted at every even level.
   *
   * @param int $max_level
   * @return array
   */
  public function getAncestryParagonSchedule(int $max_level = 20): array {
    $levels = [];
    for ($l = 2; $l <= min($max_level, 20); $l += 2) {
      $levels[] = $l;
    }
    return [
      'ancestry_feat_levels' => $levels,
      'rules' => [
        'Ancestry feats are granted at every even level (double normal rate).',
        'Only ancestry feats may be taken via this variant; not general or class feats.',
      ],
    ];
  }

  // ── Internal helpers ──────────────────────────────────────────────────────

  private function assertValidRule(string $rule): void {
    if (!in_array($rule, self::VALID_RULES, TRUE)) {
      throw new BadRequestHttpException("Invalid variant rule '$rule'. Valid: " . implode(', ', self::VALID_RULES));
    }
  }

  private function getActiveRuleNames(int $campaign_id): array {
    return $this->database->select('dc_variant_rule', 'v')
      ->fields('v', ['rule_name'])
      ->condition('campaign_id', $campaign_id)
      ->condition('enabled', 1)
      ->execute()
      ->fetchCol();
  }

  private function getCampaignAbpOverrides(int $campaign_id): array {
    $row = $this->database->select('dc_variant_rule', 'v')
      ->fields('v', ['config_json'])
      ->condition('campaign_id', $campaign_id)
      ->condition('rule_name', 'automatic_bonus_progression')
      ->condition('enabled', 1)
      ->execute()
      ->fetchField();

    if (!$row) {
      return [];
    }
    $config = json_decode($row, TRUE) ?? [];
    return $config['abp_overrides'] ?? [];
  }

}
