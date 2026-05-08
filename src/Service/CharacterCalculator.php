<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Service for calculating character statistics.
 *
 * Implements PF2e rules for character stat calculations including HP, AC,
 * ability modifiers, proficiency bonuses, and other derived statistics.
 *
 * @see docs/dungeoncrawler/issues/issue-testing-strategy-design.md
 *   Section: "Mock Service Designs" - CharacterCalculator Service
 *
 * Design Reference:
 * - Testing Strategy: Unit Tests (80% coverage target, 90% for services)
 * - Mock Strategy: Use test fixtures, no database operations
 * - PF2e Rules: HP calculation, ability modifiers, proficiency
 */
class CharacterCalculator {

  /**
   * Calculate total hit points for a character.
   *
   * Formula: (class_hp * level) + (con_modifier * level) + ancestry_hp + other
   *
   * @param array $characterData
   *   Character data array containing:
   *   - class_hp: int - Base HP from class
   *   - level: int - Character level
   *   - abilities: array - Ability scores
   *   - ancestry_hp_bonus: int - HP bonus from ancestry
   *
   * @return array
   *   HP calculation result:
   *   - total: int - Total HP
   *   - breakdown: array - Detailed breakdown of HP sources
   *
   * @see docs/dungeoncrawler/testing/fixtures/pf2e_reference/core_mechanics.json
   *   hp_calculation section for official PF2e rules
   *
   * @see docs/dungeoncrawler/testing/fixtures/characters/level_1_fighter.json
   *   Example: Fighter with 10 base HP, 16 CON = 13 total HP
   *
   * TODO: Implement per design document Section "Service Layer Design"
   */
  public function calculateHP(array $characterData): array {
    $level = max(1, (int) ($characterData['level'] ?? 1));

    $classHp = (int) ($characterData['class_hp'] ?? 0);
    if ($classHp <= 0 && !empty($characterData['class'])) {
      $classId = strtolower((string) $characterData['class']);
      $classData = CharacterManager::CLASSES[$classId] ?? NULL;
      $classHp = (int) ($classData['hp'] ?? 8);
    }
    if ($classHp <= 0) {
      $classHp = 8;
    }

    $abilities = $characterData['abilities'] ?? [];
    $conScore = (int) (
      $abilities['constitution']
      ?? $abilities['con']
      ?? 10
    );
    $conModifier = $this->calculateAbilityModifier($conScore);

    $ancestryHp = 0;
    if (isset($characterData['ancestry_hp_bonus'])) {
      $ancestryHp = (int) $characterData['ancestry_hp_bonus'];
    }
    elseif (!empty($characterData['ancestry'])) {
      $ancestryId = strtolower((string) $characterData['ancestry']);
      $ancestryData = CharacterManager::ANCESTRIES[$ancestryId] ?? NULL;
      $ancestryHp = (int) ($ancestryData['hp'] ?? 0);
    }

    $otherBonuses = (int) ($characterData['other_hp_bonus'] ?? 0);
    $baseHp = $ancestryHp + $classHp + $conModifier;
    $levelHp = ($level - 1) * ($classHp + $conModifier);
    $totalHp = max(1, $baseHp + $levelHp + $otherBonuses);

    return [
      'total' => $totalHp,
      'breakdown' => [
        'ancestry_bonus' => $ancestryHp,
        'class_base' => $classHp,
        'con_modifier' => $conModifier,
        'level_multiplier' => $levelHp,
        'other_bonuses' => $otherBonuses,
      ],
    ];
  }

  /**
   * Calculate ability modifier from ability score.
   *
   * Formula: floor((score - 10) / 2)
   * Per PF2e Core Rulebook pp. 20-21
   *
   * @param int $score
   *   Ability score (1-30, typically 8-18 for starting characters).
   *
   * @return int
   *   Ability modifier (-5 to +10).
   *
   * @see docs/dungeoncrawler/testing/fixtures/pf2e_reference/core_mechanics.json
   *   ability_scores.modifiers section for official table
   *
   * Examples:
   * - Score 10 → Modifier 0
   * - Score 18 → Modifier 4
   * - Score 8 → Modifier -1
   *
   * TODO: Implement per PF2e Core Rulebook rules
   */
  public function calculateAbilityModifier(int $score): int {
    return (int) floor(($score - 10) / 2);
  }

  /**
   * Apply ability boost per PF2e rules.
   *
   * Boosts add +2 to scores under 18, or +1 to scores at 18 or higher.
   * Per PF2e Core Rulebook p. 20
   *
   * @param int $score
   *   Current ability score.
   *
   * @return int
   *   New ability score after boost.
   *
   * @see docs/dungeoncrawler/testing/fixtures/pf2e_reference/core_mechanics.json
   *   ability_scores.boost_rules section
   *
   * TODO: Implement boost rules
   */
  public function applyAbilityBoost(int $score): int {
    if ($score < 18) {
      return $score + 2;
    }
    return $score + 1;
  }

  /**
   * Calculate proficiency bonus.
   *
   * Formula: proficiency_rank_bonus + level
   * Ranks: Untrained (0), Trained (2), Expert (4), Master (6), Legendary (8)
   *
   * @param string $rank
   *   Proficiency rank (untrained, trained, expert, master, legendary).
   * @param int $level
   *   Character level.
   *
   * @return int
   *   Total proficiency bonus.
   *
   * @see docs/dungeoncrawler/testing/fixtures/pf2e_reference/core_mechanics.json
   *   proficiency_ranks section
   *
   * TODO: Implement proficiency calculation
   */
  public function calculateProficiencyBonus(string $rank, int $level): int {
    // Untrained: no proficiency bonus, no level contribution.
    if (strtolower($rank) === 'untrained') {
      return 0;
    }
    $rankBonuses = [
      'trained'   => 2,
      'expert'    => 4,
      'master'    => 6,
      'legendary' => 8,
    ];
    return ($rankBonuses[strtolower($rank)] ?? 0) + max(0, $level);
  }

  /**
   * Calculate armor class.
   *
   * Formula: 10 + DEX (capped by armor max_dex) + armor_bonus + shield + proficiency + level + other
   *
   * @param array $characterData
   *   Character data with DEX, armor bonuses, proficiency.
   *   Optional keys:
   *     - armor_dex_cap: int  Maximum DEX modifier allowed by equipped armor.
   *       NULL means no cap (unarmored). When set to 0 the cap is respected (e.g. Full Plate).
   *
   * @return array
   *   AC calculation with total and breakdown.
   *
   * @see docs/dungeoncrawler/testing/fixtures/pf2e_reference/core_mechanics.json
   *   ac_calculation section
   */
  public function calculateArmorClass(array $characterData): array {
    $abilities = $characterData['abilities'] ?? [];
    $dexScore = (int) (
      $abilities['dexterity']
      ?? $abilities['dex']
      ?? 10
    );
    $dexModifier = $this->calculateAbilityModifier($dexScore);

    // Apply armor dex cap when armor is equipped.
    // Key presence (even with value 0) means the cap applies.
    if (array_key_exists('armor_dex_cap', $characterData) && $characterData['armor_dex_cap'] !== NULL) {
      $dexModifier = min($dexModifier, (int) $characterData['armor_dex_cap']);
    }

    $level = max(0, (int) ($characterData['level'] ?? 1));
    $armorBonus = (int) ($characterData['armor_bonus'] ?? 0);
    $shieldBonus = (int) ($characterData['shield_bonus'] ?? 0);
    $otherBonuses = (int) ($characterData['other_ac_bonus'] ?? 0);
    $proficiencyRank = (string) ($characterData['proficiency_rank'] ?? 'untrained');
    $proficiency = $this->calculateProficiencyBonus($proficiencyRank, $level);

    $total = 10 + $dexModifier + $armorBonus + $shieldBonus + $proficiency + $otherBonuses;

    return [
      'total' => $total,
      'breakdown' => [
        'base' => 10,
        'dex_modifier' => $dexModifier,
        'armor_bonus' => $armorBonus,
        'shield_bonus' => $shieldBonus,
        'proficiency' => $proficiency,
        'other_bonuses' => $otherBonuses,
      ],
    ];
  }

  /**
   * Skills that incur an armor check penalty (STR/DEX-based physical skills).
   * PF2e CRB p. 275: armor check penalty applies unless the action has the
   * attack trait, in which case the penalty is already baked into MAP math.
   */
  const ARMOR_CHECK_PENALTY_SKILLS = ['acrobatics', 'athletics', 'stealth', 'thievery'];

  /**
   * PF2E core skills with their governing ability score.
   * Key: skill id (lowercase), value: ability score key.
   */
  const SKILLS = [
    'acrobatics'   => 'dexterity',
    'arcana'       => 'intelligence',
    'athletics'    => 'strength',
    'crafting'     => 'intelligence',
    'deception'    => 'charisma',
    'diplomacy'    => 'charisma',
    'intimidation' => 'charisma',
    'lore'         => 'intelligence',
    'medicine'     => 'wisdom',
    'nature'       => 'wisdom',
    'occultism'    => 'intelligence',
    'performance'  => 'charisma',
    'religion'     => 'wisdom',
    'society'      => 'intelligence',
    'stealth'      => 'dexterity',
    'survival'     => 'wisdom',
    'thievery'     => 'dexterity',
  ];

  /**
   * PF2E proficiency rank names (index = rank value 0–4).
   */
  const PROFICIENCY_RANKS = ['untrained', 'trained', 'expert', 'master', 'legendary'];

  /**
   * Calculate a skill check result.
   *
   * Formula: d20 + ability_modifier + proficiency_bonus + item_bonus
   *           [+ armor_check_penalty if applicable]
   *
   * @param array $characterData  Character data (abilities[], level, skills[]).
   * @param string $skillName     Lowercase skill name (e.g. 'athletics').
   *                              Lore specializations: 'sailing lore', etc.
   * @param int $dc               Difficulty class.
   * @param int $itemBonus        Item or circumstance bonus (default 0).
   * @param object|null $numberGen NumberGenerationService; NULL = PHP rand (tests).
   * @param array $options        Optional flags:
   *   - 'trained_only' (bool): block untrained characters (returns blocked=TRUE).
   *   - 'is_attack_trait' (bool): suppress armor check penalty (attack-trait
   *     actions such as Grapple, Trip, Disarm already include the penalty via
   *     MAP math per PF2e CRB p. 275).
   *
   * @return array With keys: roll, ability_modifier, proficiency_bonus,
   *               item_bonus, armor_check_penalty, total, dc, degree, skill,
   *               rank, error, blocked.
   */
  public function calculateSkillCheck(array $characterData, string $skillName, int $dc, int $itemBonus = 0, $numberGen = NULL, array $options = []): array {
    $skillKey = strtolower(trim($skillName));

    // Resolve governing ability: Lore specializations use intelligence.
    $abilityKey = self::SKILLS[$skillKey] ?? NULL;
    if ($abilityKey === NULL) {
      if (str_ends_with($skillKey, 'lore') || strpos($skillKey, ' lore') !== FALSE) {
        $abilityKey = 'intelligence';
      }
      else {
        return ['error' => "Unknown skill: {$skillName}. Valid skills: " . implode(', ', array_keys(self::SKILLS)), 'blocked' => FALSE];
      }
    }

    $abilities = $characterData['abilities'] ?? [];
    // Support both full key ('strength') and short key ('str').
    $abilityScore = (int) ($abilities[$abilityKey] ?? $abilities[substr($abilityKey, 0, 3)] ?? 10);
    $abilityMod = $this->calculateAbilityModifier($abilityScore);

    $level = max(0, (int) ($characterData['level'] ?? 1));

    // Resolve proficiency rank from stored skills map.
    $skills = $characterData['skills'] ?? [];
    $rank = 'untrained';
    if (isset($skills[$skillKey])) {
      $stored = $skills[$skillKey];
      $rank = is_numeric($stored)
        ? (self::PROFICIENCY_RANKS[(int) $stored] ?? 'untrained')
        : strtolower((string) $stored);
    }
    // Check lore_skills array for specializations.
    if ($skillKey !== 'lore' && isset($characterData['lore_skills'])) {
      foreach ($characterData['lore_skills'] as $lore) {
        $loreName = strtolower($lore['specialization'] ?? $lore['name'] ?? '');
        if ($loreName === $skillKey || $loreName . ' lore' === $skillKey) {
          $rank = $lore['rank'] ?? 'trained';
          break;
        }
      }
    }

    // Trained-only gating (REQ 1554): block untrained characters.
    if (!empty($options['trained_only']) && $rank === 'untrained') {
      return [
        'error'   => "This action requires training in {$skillName}. Character is untrained.",
        'blocked' => TRUE,
        'skill'   => $skillKey,
        'rank'    => $rank,
      ];
    }

    // Armor check penalty (REQ 1600): applies to STR/DEX physical skills
    // unless the action has the attack trait (attack-trait actions use MAP).
    $armorCheckPenalty = 0;
    $isAttackTrait = !empty($options['is_attack_trait']);
    if (!$isAttackTrait && in_array($skillKey, self::ARMOR_CHECK_PENALTY_SKILLS, TRUE)) {
      $armorCheckPenalty = (int) ($characterData['armor_check_penalty'] ?? 0);
    }

    $profBonus = $this->calculateProficiencyBonus($rank, $level);
    $roll = $numberGen ? $numberGen->rollPathfinderDie(20) : rand(1, 20);
    $total = $roll + $abilityMod + $profBonus + $itemBonus + $armorCheckPenalty;

    $baseDegree = $this->skillBaseDegree($total, $dc);
    if ($roll === 20) {
      $degree = $this->skillBumpDegreeUp($baseDegree);
    }
    elseif ($roll === 1) {
      $degree = $this->skillBumpDegreeDown($baseDegree);
    }
    else {
      $degree = $baseDegree;
    }

    return [
      'roll'                => $roll,
      'ability_modifier'    => $abilityMod,
      'proficiency_bonus'   => $profBonus,
      'item_bonus'          => $itemBonus,
      'armor_check_penalty' => $armorCheckPenalty,
      'total'               => $total,
      'dc'                  => $dc,
      'degree'              => $degree,
      'skill'               => $skillKey,
      'rank'                => $rank,
      'error'               => NULL,
      'blocked'             => FALSE,
    ];
  }

  /**
   * Base degree of success from total vs DC (PF2E rules).
   */
  protected function skillBaseDegree(int $total, int $dc): string {
    if ($total >= $dc + 10) {
      return 'critical_success';
    }
    if ($total >= $dc) {
      return 'success';
    }
    if ($total <= $dc - 10) {
      return 'critical_failure';
    }
    return 'failure';
  }

  /** Bump degree of success up one step (natural 20). */
  protected function skillBumpDegreeUp(string $degree): string {
    return match ($degree) {
      'critical_failure' => 'failure',
      'failure'          => 'success',
      'success'          => 'critical_success',
      default            => 'critical_success',
    };
  }

  /** Bump degree of success down one step (natural 1). */
  protected function skillBumpDegreeDown(string $degree): string {
    return match ($degree) {
      'critical_success' => 'success',
      'success'          => 'failure',
      'failure'          => 'critical_failure',
      default            => 'critical_failure',
    };
  }

}
