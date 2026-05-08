<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Creature Identification via Recall Knowledge (PF2e Core p.235, 243).
 *
 * Wraps RecallKnowledgeService with creature-specific outcome resolution:
 *  - Validates the chosen skill against mapped creature traits.
 *  - Computes the identification DC (creature level + rarity adjustment).
 *  - Resolves degree of success and returns a structured reveal payload.
 *  - Tracks per-(character, creature) attempt state in dc_creature_id_attempts.
 *
 * Degrees of success (PF2e Core Chapter 9):
 *  - Critical Success: full stat block + one bonus fact.
 *  - Success: partial reveal (name, traits, 1–2 abilities).
 *  - Failure: no information returned.
 *  - Critical Failure: misleading info presented as true (no failure indicator).
 *
 * Implements reqs from dc-cr-creature-identification.
 */
class CreatureIdentificationService {

  protected Connection $db;
  protected RecallKnowledgeService $recallKnowledge;

  // ---------------------------------------------------------------------------
  // Degree of success constants
  // ---------------------------------------------------------------------------

  const DEGREE_CRIT_SUCCESS  = 'critical_success';
  const DEGREE_SUCCESS       = 'success';
  const DEGREE_FAILURE       = 'failure';
  const DEGREE_CRIT_FAILURE  = 'critical_failure';

  // revealed_info_level values stored in dc_creature_id_attempts
  const REVEAL_NONE    = 'none';
  const REVEAL_PARTIAL = 'partial';
  const REVEAL_FULL    = 'full';

  public function __construct(
    Connection $database,
    RecallKnowledgeService $recall_knowledge
  ) {
    $this->db              = $database;
    $this->recallKnowledge = $recall_knowledge;
  }

  // ---------------------------------------------------------------------------
  // Primary entry point
  // ---------------------------------------------------------------------------

  /**
   * Attempt to identify a creature via Recall Knowledge.
   *
   * @param array  $character_state  Full character state array.
   * @param string $creature_id      Key into CharacterManager::CREATURES.
   * @param string $skill            Skill used ('arcana', 'nature', etc.).
   * @param int    $roll             Raw d20 result (before modifiers).
   * @param int    $skill_bonus      Total skill modifier (stat + proficiency + misc).
   *
   * @return array{
   *   success: bool,
   *   degree: string,
   *   dc: int,
   *   roll: int,
   *   total: int,
   *   valid_skills: string[],
   *   reveal: array|null,
   *   error: string|null
   * }
   */
  public function attemptIdentification(
    array $character_state,
    string $creature_id,
    string $skill,
    int $roll,
    int $skill_bonus
  ): array {
    $creature = CharacterManager::CREATURES[$creature_id] ?? NULL;
    if ($creature === NULL) {
      return $this->errorResult("Unknown creature: {$creature_id}");
    }

    // Validate skill choice.
    $valid_skills = CharacterManager::recallKnowledgeSkillsForTraits(
      $creature['traits'] ?? []
    );
    $skill_lc = strtolower(trim($skill));
    if (!$this->isValidSkill($skill_lc, $valid_skills)) {
      return $this->errorResult(
        "Skill '{$skill}' is not applicable to this creature type. " .
        'Valid skills: ' . implode(', ', $valid_skills),
        ['valid_skills' => $valid_skills]
      );
    }

    // Compute DC.
    $level    = (int) ($creature['level'] ?? 0);
    $rarity   = strtolower($creature['rarity'] ?? 'common');
    $dc_data  = $this->recallKnowledge->computeDc('creature', $level, $rarity);
    $dc       = $dc_data['dc'];

    // Compute total and resolve degree.
    $total  = $roll + $skill_bonus;
    $degree = $this->resolveDegree($total, $dc);

    // Build reveal payload.
    $char_id = $character_state['character_id'] ?? ($character_state['id'] ?? NULL);
    $reveal  = $this->buildReveal($creature, $degree);

    // Record attempt.
    if ($char_id !== NULL) {
      $this->recordAttempt((string) $char_id, $creature_id, $degree, $reveal);
    }

    return [
      'success'      => in_array($degree, [self::DEGREE_SUCCESS, self::DEGREE_CRIT_SUCCESS], TRUE),
      'degree'       => $degree,
      'dc'           => $dc,
      'roll'         => $roll,
      'total'        => $total,
      'valid_skills' => $valid_skills,
      'reveal'       => $reveal,
      'error'        => NULL,
    ];
  }

  /**
   * Get stored identification data for a character+creature pair.
   *
   * @return array|null  Most recent attempt row, or NULL if none exists.
   */
  public function getAttemptRecord(string $character_id, string $creature_id): ?array {
    $row = $this->db->select('dc_creature_id_attempts', 'a')
      ->fields('a')
      ->condition('character_id', $character_id)
      ->condition('creature_id', $creature_id)
      ->orderBy('attempted_at', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    return $row ?: NULL;
  }

  // ---------------------------------------------------------------------------
  // Degree of success
  // ---------------------------------------------------------------------------

  /**
   * Compute degree of success using PF2e rules (±10 from DC = crit).
   *
   * @param int $total  d20 + all modifiers.
   * @param int $dc     Target difficulty class.
   *
   * @return string  One of the DEGREE_* constants.
   */
  public function resolveDegree(int $total, int $dc): string {
    if ($total >= $dc + 10) {
      return self::DEGREE_CRIT_SUCCESS;
    }
    if ($total >= $dc) {
      return self::DEGREE_SUCCESS;
    }
    if ($total <= $dc - 10) {
      return self::DEGREE_CRIT_FAILURE;
    }
    return self::DEGREE_FAILURE;
  }

  // ---------------------------------------------------------------------------
  // Reveal builders
  // ---------------------------------------------------------------------------

  /**
   * Build the reveal payload based on degree of success.
   *
   * Critical Failure: returns a plausible but misleading stat block.
   *   The caller/UI must present this as if it were true — no failure flag.
   * Failure: returns NULL (no information).
   * Success: returns a partial reveal (name, traits, 1–2 abilities).
   * Critical Success: returns a full reveal plus a bonus fact.
   *
   * @param array  $creature  Stat block from CharacterManager::CREATURES.
   * @param string $degree    DEGREE_* constant.
   *
   * @return array|null
   */
  public function buildReveal(array $creature, string $degree): ?array {
    switch ($degree) {
      case self::DEGREE_CRIT_SUCCESS:
        return $this->buildFullReveal($creature, include_bonus_fact: TRUE);

      case self::DEGREE_SUCCESS:
        return $this->buildPartialReveal($creature);

      case self::DEGREE_FAILURE:
        return NULL;

      case self::DEGREE_CRIT_FAILURE:
        return $this->buildMisleadingReveal($creature);
    }
    return NULL;
  }

  /**
   * Full reveal: all stat block fields + one bonus fact.
   */
  protected function buildFullReveal(array $creature, bool $include_bonus_fact): array {
    $reveal = [
      'reveal_level'   => self::REVEAL_FULL,
      'id'             => $creature['id'],
      'name'           => $creature['name'],
      'level'          => $creature['level'],
      'rarity'         => $creature['rarity'],
      'size'           => $creature['size'] ?? NULL,
      'traits'         => $creature['traits'] ?? [],
      'ac'             => $creature['ac'] ?? NULL,
      'hp'             => $creature['hp'] ?? NULL,
      'saves'          => $creature['saves'] ?? [],
      'speeds'         => $creature['speeds'] ?? [],
      'attacks'        => $creature['attacks'] ?? [],
      'abilities'      => $creature['abilities'] ?? [],
      'immunities'     => $creature['immunities'] ?? [],
      'weaknesses'     => $creature['weaknesses'] ?? [],
      'resistances'    => $creature['resistances'] ?? [],
      'senses'         => $creature['senses'] ?? [],
      'languages'      => $creature['languages'] ?? [],
      'skills'         => $creature['skills'] ?? [],
      'perception'     => $creature['perception'] ?? NULL,
    ];

    if ($include_bonus_fact) {
      $reveal['bonus_fact'] = $this->extractBonusFact($creature);
    }

    return $reveal;
  }

  /**
   * Partial reveal: name, level, traits, and up to 2 abilities.
   */
  protected function buildPartialReveal(array $creature): array {
    $abilities = array_slice($creature['abilities'] ?? [], 0, 2);
    return [
      'reveal_level' => self::REVEAL_PARTIAL,
      'id'           => $creature['id'],
      'name'         => $creature['name'],
      'level'        => $creature['level'],
      'rarity'       => $creature['rarity'],
      'traits'       => $creature['traits'] ?? [],
      'abilities'    => $abilities,
    ];
  }

  /**
   * Misleading reveal: fabricated values for AC, HP, saves, and abilities.
   *
   * The creature name and traits are shown truthfully (player knows what they
   * are looking at); only numeric stats and abilities are falsified.
   * The internal log marks this as a crit-fail.
   */
  protected function buildMisleadingReveal(array $creature): array {
    $real_ac  = (int) ($creature['ac']  ?? 14);
    $real_hp  = (int) ($creature['hp']  ?? 20);
    $fake_ac  = $real_ac  + ($real_ac  > 12 ? -3 : 3);
    $fake_hp  = $real_hp  + ($real_hp  > 20 ? -10 : 10);

    $real_saves   = $creature['saves'] ?? [];
    $fake_saves   = [];
    foreach ($real_saves as $save => $val) {
      $fake_saves[$save] = (int) $val + (((int) $val) > 4 ? -3 : 3);
    }

    $abilities = $creature['abilities'] ?? [];
    $fake_abilities = empty($abilities)
      ? ['regeneration 5']
      : ['resistant to bludgeoning (5)'];

    return [
      'reveal_level'   => self::REVEAL_PARTIAL,
      '_misleading'    => TRUE,   // internal marker — must NOT be sent to player UI
      'id'             => $creature['id'],
      'name'           => $creature['name'],
      'level'          => $creature['level'],
      'rarity'         => $creature['rarity'],
      'traits'         => $creature['traits'] ?? [],
      'ac'             => $fake_ac,
      'hp'             => $fake_hp,
      'saves'          => $fake_saves,
      'abilities'      => $fake_abilities,
    ];
  }

  /**
   * Extract a bonus fact (first non-trivial entry from weaknesses, abilities,
   * or resistances).  Falls back to AC value if nothing else is available.
   */
  protected function extractBonusFact(array $creature): array {
    if (!empty($creature['weaknesses'])) {
      $entry = reset($creature['weaknesses']);
      return ['type' => 'weakness', 'detail' => $entry];
    }
    if (!empty($creature['abilities'])) {
      return ['type' => 'special_ability', 'detail' => $creature['abilities'][0]];
    }
    if (!empty($creature['resistances'])) {
      $entry = reset($creature['resistances']);
      return ['type' => 'resistance', 'detail' => $entry];
    }
    return ['type' => 'stat', 'detail' => "AC {$creature['ac']}"];
  }

  // ---------------------------------------------------------------------------
  // Attempt tracking
  // ---------------------------------------------------------------------------

  /**
   * Persist an identification attempt to dc_creature_id_attempts.
   */
  protected function recordAttempt(
    string $character_id,
    string $creature_id,
    string $degree,
    ?array $reveal
  ): void {
    if (!$this->db->schema()->tableExists('dc_creature_id_attempts')) {
      return;
    }

    $reveal_level = match ($degree) {
      self::DEGREE_CRIT_SUCCESS => self::REVEAL_FULL,
      self::DEGREE_SUCCESS      => self::REVEAL_PARTIAL,
      default                   => self::REVEAL_NONE,
    };

    $this->db->merge('dc_creature_id_attempts')
      ->keys([
        'character_id' => $character_id,
        'creature_id'  => $creature_id,
      ])
      ->fields([
        'degree'             => $degree,
        'revealed_info_level'=> $reveal_level,
        'reveal_data'        => $reveal !== NULL ? json_encode($reveal) : NULL,
        'attempted_at'       => \Drupal::time()->getCurrentTime(),
      ])
      ->execute();
  }

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  /**
   * Returns TRUE if $skill is in the valid list or matches a Lore subcategory.
   */
  protected function isValidSkill(string $skill, array $valid_skills): bool {
    if (in_array($skill, $valid_skills, TRUE)) {
      return TRUE;
    }
    // Allow any "lore_*" skill when lore_gm is valid.
    if (in_array('lore_gm', $valid_skills, TRUE) && str_starts_with($skill, 'lore_')) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Build a standard error result array.
   */
  protected function errorResult(string $message, array $extra = []): array {
    return array_merge([
      'success'      => FALSE,
      'degree'       => NULL,
      'dc'           => NULL,
      'roll'         => NULL,
      'total'        => NULL,
      'valid_skills' => [],
      'reveal'       => NULL,
      'error'        => $message,
    ], $extra);
  }

}
