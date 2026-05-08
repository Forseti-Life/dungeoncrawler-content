<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Unified knowledge-acquisition action handler for PF2e exploration activities.
 *
 * Implements three related exploration-phase skill actions:
 *  1. Decipher Writing  (Core p.234) — Arcana/Occultism/Religion/Society
 *  2. Identify Magic    (Core p.238) — tradition-matched skill; +5 DC wrong trad.
 *  3. Learn a Spell     (Core p.238) — tradition skill; spell_rank × 10 gp cost
 *
 * State tracking (retry penalties, 1-day blocks, false-result markers) is
 * persisted to dc_knowledge_attempt_state via MERGE (upsert).
 *
 * Implements reqs 1574–1590 (dc-cr-decipher-identify-learn).
 */
class KnowledgeAcquisitionService {

  // ---------------------------------------------------------------------------
  // Constants
  // ---------------------------------------------------------------------------

  // Degree of success labels (shared with CraftingService / CreatureIdentificationService)
  const DEGREE_CRIT_SUCCESS  = 'critical_success';
  const DEGREE_SUCCESS       = 'success';
  const DEGREE_FAILURE       = 'failure';
  const DEGREE_CRIT_FAILURE  = 'critical_failure';

  // Seconds in one day (used for Identify Magic 1-day block)
  const ONE_DAY_SECONDS = 86400;

  // Material cost per spell rank for Learn a Spell (in gp)
  const LEARN_SPELL_GP_PER_RANK = 10;

  // Decipher Writing timing (minutes per page)
  const DECIPHER_NORMAL_MINUTES  = 1;
  const DECIPHER_CIPHER_MINUTES  = 60;

  // Identify Magic timing (minutes)
  const IDENTIFY_MAGIC_MINUTES   = 10;

  // Learn a Spell timing (minutes)
  const LEARN_SPELL_MINUTES      = 60;

  // Retry penalty for Decipher Writing on Failure (circumstance penalty)
  const DECIPHER_RETRY_PENALTY   = -2;

  // Wrong-tradition DC penalty for Identify Magic
  const WRONG_TRADITION_PENALTY  = 5;

  /**
   * Skill routing for Decipher Writing by text type.
   * (Primary skill for each category of text.)
   */
  const DECIPHER_TEXT_SKILL = [
    'arcane'      => 'arcana',
    'esoteric'    => 'arcana',
    'metaphysical'=> 'occultism',
    'occult'      => 'occultism',
    'religious'   => 'religion',
    'divine'      => 'religion',
    'coded'       => 'society',
    'legal'       => 'society',
    'historical'  => 'society',
    'mundane'     => 'society',
  ];

  /**
   * Tradition to Identify Magic skill mapping.
   */
  const TRADITION_SKILL = [
    'arcane'  => 'arcana',
    'primal'  => 'nature',
    'occult'  => 'occultism',
    'divine'  => 'religion',
  ];

  // ---------------------------------------------------------------------------
  // Dependencies
  // ---------------------------------------------------------------------------

  protected Connection $db;
  protected CharacterStateService $characterStateService;
  protected IdentifyMagicService $identifyMagicService;
  protected LearnASpellService $learnASpellService;
  protected DcAdjustmentService $dcAdjustment;

  public function __construct(
    Connection $database,
    CharacterStateService $character_state_service,
    IdentifyMagicService $identify_magic_service,
    LearnASpellService $learn_a_spell_service,
    DcAdjustmentService $dc_adjustment
  ) {
    $this->db                   = $database;
    $this->characterStateService = $character_state_service;
    $this->identifyMagicService  = $identify_magic_service;
    $this->learnASpellService    = $learn_a_spell_service;
    $this->dcAdjustment          = $dc_adjustment;
  }

  // ===========================================================================
  // Decipher Writing [Exploration, Secret, Trained]
  // ===========================================================================

  /**
   * Process a Decipher Writing check.
   *
   * @param string $character_id
   * @param array  $params
   *   - text_id       string  Required. Unique ID of the text being deciphered.
   *   - text_type     string  Text category (arcane|esoteric|occult|...|coded|cipher|etc.)
   *   - skill_used    string  Skill chosen by the player.
   *   - skill_bonus   int     Total skill modifier.
   *   - dc            int     (optional) Override DC; defaults to trained simple DC (15).
   *   - languages     string[] Character's known languages.
   *   - text_language string  Language the text is written in.
   *   - gm_override   bool    GM-granted override for unfamiliar language.
   *   - page_count    int     Pages to decipher (determines time cost).
   *
   * @return array{
   *   success: bool,
   *   degree: string,
   *   dc: int,
   *   roll: int,
   *   total: int,
   *   outcome: string|null,
   *   is_false: bool,
   *   skill_used: string,
   *   valid_skills: string[],
   *   time_cost_minutes: int,
   *   retry_penalty: int,
   *   error: string|null
   * }
   */
  public function processDecipherWriting(string $character_id, array $params): array {
    $text_id       = $params['text_id'] ?? 'unknown_text';
    $text_type     = strtolower(trim($params['text_type'] ?? 'mundane'));
    $skill_used    = strtolower(trim($params['skill_used'] ?? 'society'));
    $skill_bonus   = (int) ($params['skill_bonus'] ?? 0);
    $languages     = array_map('strtolower', (array) ($params['languages'] ?? []));
    $text_language = strtolower(trim($params['text_language'] ?? ''));
    $gm_override   = (bool) ($params['gm_override'] ?? FALSE);
    $page_count    = max(1, (int) ($params['page_count'] ?? 1));

    // Language literacy gate.
    if ($text_language && !in_array($text_language, $languages, TRUE) && !$gm_override) {
      return $this->errorResult(
        "Character does not know the text's language '{$text_language}'. A GM override is required for unfamiliar languages.",
        ['valid_skills' => array_values(array_unique(array_values(self::DECIPHER_TEXT_SKILL)))]
      );
    }

    // Skill routing.
    $expected_skill = self::DECIPHER_TEXT_SKILL[$text_type] ?? 'society';
    $valid_skills   = array_values(array_unique(array_values(self::DECIPHER_TEXT_SKILL)));

    // Validate skill (wrong-skill = blocked, not just +5 DC, per PF2e Decipher Writing rules)
    if (!in_array($skill_used, $valid_skills, TRUE)) {
      return $this->errorResult(
        "Skill '{$skill_used}' is not a valid Decipher Writing skill. Valid: " . implode(', ', $valid_skills),
        ['valid_skills' => $valid_skills]
      );
    }

    // Load stored retry penalty for this text+character pair.
    $stored = $this->loadAttemptState($character_id, 'decipher_writing', $text_id);
    $existing_penalty = (int) ($stored['penalty'] ?? 0);

    // Compute DC (default = trained simple DC = 15; override allowed).
    $dc = !empty($params['dc']) ? (int) $params['dc'] : $this->dcAdjustment->simpleDc('trained');
    // Apply circumstance penalty from prior failure.
    $dc -= $existing_penalty; // penalty is negative, so subtracting a negative = increasing DC
    // Actually the -2 penalty applies to the roll, not the DC. Store as roll modifier.
    $effective_bonus = $skill_bonus + $existing_penalty; // existing_penalty is negative (e.g. -2)

    $d20   = $this->rollD20();
    $total = $d20 + $effective_bonus;
    $degree = $this->resolveDegree($total, $dc, $d20);

    // Timing: cipher = 60 min/page, other = 1 min/page.
    $is_cipher = ($text_type === 'cipher');
    $time_cost_minutes = $page_count * ($is_cipher ? self::DECIPHER_CIPHER_MINUTES : self::DECIPHER_NORMAL_MINUTES);

    // Resolve outcome payload.
    $outcome   = NULL;
    $is_false  = FALSE;
    $new_penalty = $existing_penalty;

    switch ($degree) {
      case self::DEGREE_CRIT_SUCCESS:
        $outcome = 'full_meaning';
        break;
      case self::DEGREE_SUCCESS:
        $outcome = $is_cipher ? 'general_summary' : 'true_meaning';
        break;
      case self::DEGREE_FAILURE:
        $outcome = NULL;
        $new_penalty = $existing_penalty + self::DECIPHER_RETRY_PENALTY; // e.g. 0 + (-2) = -2
        break;
      case self::DEGREE_CRIT_FAILURE:
        $outcome = 'false_interpretation';
        $is_false = TRUE;
        break;
    }

    // Persist attempt state.
    $this->saveAttemptState($character_id, 'decipher_writing', $text_id, $degree, [
      'penalty'   => $new_penalty,
      'is_false'  => (int) $is_false,
      'outcome'   => $outcome ?? '',
    ]);

    return [
      'success'           => in_array($degree, [self::DEGREE_SUCCESS, self::DEGREE_CRIT_SUCCESS], TRUE),
      'degree'            => $degree,
      'dc'                => $dc,
      'roll'              => $d20,
      'total'             => $total,
      'outcome'           => $outcome,
      'is_false'          => $is_false,
      'skill_used'        => $skill_used,
      'expected_skill'    => $expected_skill,
      'valid_skills'      => $valid_skills,
      'time_cost_minutes' => $time_cost_minutes,
      'retry_penalty'     => $new_penalty,
      'error'             => NULL,
    ];
  }

  // ===========================================================================
  // Identify Magic [Exploration, Trained]
  // ===========================================================================

  /**
   * Process an Identify Magic check.
   *
   * @param string $character_id
   * @param array  $params
   *   - item_id         string  Unique ID of the item/spell/effect.
   *   - magic_type      string  One of: item, spell, effect.
   *   - level           int     Item or effect level (used when magic_type != 'spell').
   *   - spell_rank      int     Spell rank (0–10; used when magic_type == 'spell').
   *   - rarity          string  common|uncommon|rare|unique.
   *   - spell_rank_delta int    Ranks above standard caster level.
   *   - tradition       string  arcane|primal|occult|divine (item/spell tradition).
   *   - skill_used      string  Skill the character is using.
   *   - skill_bonus     int     Total skill modifier.
   *
   * @return array{
   *   success: bool,
   *   degree: string,
   *   dc: int,
   *   roll: int,
   *   total: int,
   *   tradition_match: bool,
   *   is_false: bool,
   *   identified_properties: array|null,
   *   bonus_fact: string|null,
   *   blocked_until: int|null,
   *   time_cost_minutes: int,
   *   error: string|null
   * }
   */
  public function processIdentifyMagic(string $character_id, array $params): array {
    $item_id          = $params['item_id'] ?? 'unknown_item';
    $magic_type       = $params['magic_type'] ?? 'item';
    $level            = (int) ($params['level'] ?? 0);
    $spell_rank       = (int) ($params['spell_rank'] ?? 0);
    $rarity           = $params['rarity'] ?? 'common';
    $spell_rank_delta = (int) ($params['spell_rank_delta'] ?? 0);
    $tradition        = strtolower(trim($params['tradition'] ?? ''));
    $skill_used       = strtolower(trim($params['skill_used'] ?? 'arcana'));
    $skill_bonus      = (int) ($params['skill_bonus'] ?? 0);

    // 1-day block check.
    $stored = $this->loadAttemptState($character_id, 'identify_magic', $item_id);
    if ($stored && !empty($stored['blocked_until'])) {
      $blocked_until = (int) $stored['blocked_until'];
      $now           = \Drupal::time()->getCurrentTime();
      if ($blocked_until > $now) {
        $hours_left = ceil(($blocked_until - $now) / 3600);
        return $this->errorResult(
          "Cannot attempt Identify Magic on this item for another {$hours_left} hour(s).",
          ['blocked_until' => $blocked_until]
        );
      }
    }

    // Tradition–skill match.
    $expected_skill   = self::TRADITION_SKILL[$tradition] ?? NULL;
    $tradition_match  = ($expected_skill === NULL) || ($skill_used === $expected_skill);
    $wrong_trad_penalty = $tradition_match ? 0 : self::WRONG_TRADITION_PENALTY;

    // Compute DC.
    $dc_result = $this->identifyMagicService->computeDc(
      $magic_type, $level, $rarity, $spell_rank, $spell_rank_delta
    );
    $dc = $dc_result['dc'] + $wrong_trad_penalty;

    $d20   = $this->rollD20();
    $total = $d20 + $skill_bonus;
    $degree = $this->resolveDegree($total, $dc, $d20);

    $is_false             = FALSE;
    $identified_properties = NULL;
    $bonus_fact            = NULL;
    $blocked_until         = NULL;

    switch ($degree) {
      case self::DEGREE_CRIT_SUCCESS:
        $identified_properties = $params['item_properties'] ?? ['tradition' => $tradition, 'level' => $level];
        $bonus_fact = $params['bonus_fact'] ?? 'hidden property revealed';
        break;
      case self::DEGREE_SUCCESS:
        $identified_properties = $params['item_properties'] ?? ['tradition' => $tradition, 'level' => $level];
        break;
      case self::DEGREE_FAILURE:
        $blocked_until = \Drupal::time()->getCurrentTime() + self::ONE_DAY_SECONDS;
        break;
      case self::DEGREE_CRIT_FAILURE:
        $is_false = TRUE;
        $identified_properties = $params['false_properties'] ?? ['tradition' => 'unknown', 'level' => 0, '_misleading' => TRUE];
        break;
    }

    // Persist state.
    $state_fields = ['is_false' => (int) $is_false];
    if ($blocked_until !== NULL) {
      $state_fields['blocked_until'] = $blocked_until;
    }
    $this->saveAttemptState($character_id, 'identify_magic', $item_id, $degree, $state_fields);

    return [
      'success'               => in_array($degree, [self::DEGREE_SUCCESS, self::DEGREE_CRIT_SUCCESS], TRUE),
      'degree'                => $degree,
      'dc'                    => $dc,
      'roll'                  => $d20,
      'total'                 => $total,
      'tradition_match'       => $tradition_match,
      'wrong_tradition_penalty' => $wrong_trad_penalty,
      'is_false'              => $is_false,
      'identified_properties' => $identified_properties,
      'bonus_fact'            => $bonus_fact,
      'blocked_until'         => $blocked_until,
      'time_cost_minutes'     => self::IDENTIFY_MAGIC_MINUTES,
      'error'                 => NULL,
    ];
  }

  // ===========================================================================
  // Learn a Spell [Exploration, Trained]
  // ===========================================================================

  /**
   * Process a Learn a Spell attempt.
   *
   * Gold is deducted from character state at the start of the attempt.
   * On Failure, gold is RESTORED (not consumed).
   * On Crit Success, half the cost is refunded.
   * On Crit Failure, cost is fully lost.
   *
   * @param string $character_id
   * @param string $campaign_id
   * @param array  $entity       Entity from dungeon state (for spellcasting check).
   * @param array  $params
   *   - spell_id          string   Spell identifier.
   *   - spell_rank        int      Spell rank (0–10).
   *   - rarity            string   Spell rarity.
   *   - tradition         string   Spell tradition (arcane|primal|occult|divine).
   *   - skill_used        string   Skill the character is using.
   *   - skill_bonus       int      Total skill modifier.
   *   - dc                int      (optional) Override DC.
   *
   * @return array{
   *   success: bool,
   *   degree: string,
   *   dc: int,
   *   roll: int,
   *   total: int,
   *   gp_spent: int,
   *   gp_refunded: int,
   *   spell_learned: bool,
   *   time_cost_minutes: int,
   *   error: string|null
   * }
   */
  public function processLearnASpell(
    string $character_id,
    string $campaign_id,
    array $entity,
    array $params
  ): array {
    $spell_id    = $params['spell_id'] ?? 'unknown_spell';
    $spell_rank  = (int) ($params['spell_rank'] ?? 1);
    $rarity      = $params['rarity'] ?? 'common';
    $tradition   = strtolower(trim($params['tradition'] ?? ''));
    $skill_used  = strtolower(trim($params['skill_used'] ?? 'arcana'));
    $skill_bonus = (int) ($params['skill_bonus'] ?? 0);

    // Spellcasting class feature gate.
    $char_tradition = strtolower(trim($entity['stats']['spellcasting_tradition'] ?? ''));
    if (empty($char_tradition)) {
      return $this->errorResult('Character does not have a spellcasting class feature.');
    }

    // Spell must be on the character's tradition list.
    if ($tradition && $char_tradition && $tradition !== $char_tradition) {
      return $this->errorResult(
        "Spell tradition '{$tradition}' is not on this character's tradition list ('{$char_tradition}')."
      );
    }

    // Material cost: spell_rank × 10 gp (rank 0 cantrips cost 0).
    $gp_cost  = max(0, $spell_rank) * self::LEARN_SPELL_GP_PER_RANK;

    // Load character state to deduct materials.
    $char_state = $this->characterStateService->getState($character_id, $campaign_id);
    if ($char_state === NULL) {
      return $this->errorResult("Character state not found for character '{$character_id}'.");
    }

    if ($gp_cost > 0) {
      $currency = $this->getCurrency($char_state);
      $total_cp = $this->toCp($currency);
      $cost_cp  = $gp_cost * 100;
      if ($total_cp < $cost_cp) {
        $have_gp = number_format($total_cp / 100, 2);
        return $this->errorResult(
          "Insufficient funds. Need {$gp_cost} gp to attempt this spell; character has {$have_gp} gp."
        );
      }
      // Deduct materials immediately.
      $char_state = $this->deductGp($char_state, $gp_cost);
      $this->characterStateService->setState($character_id, $char_state, NULL, $campaign_id);
    }

    // Compute DC.
    if (!empty($params['dc'])) {
      $dc = (int) $params['dc'];
    }
    else {
      $dc_result = $this->learnASpellService->computeDc($spell_rank, $rarity);
      $dc = $dc_result['dc'];
    }

    $d20   = $this->rollD20();
    $total = $d20 + $skill_bonus;
    $degree = $this->resolveDegree($total, $dc, $d20);

    $spell_learned  = FALSE;
    $gp_refunded    = 0;

    switch ($degree) {
      case self::DEGREE_CRIT_SUCCESS:
        $spell_learned = TRUE;
        // Refund half the cost.
        $gp_refunded = (int) floor($gp_cost / 2);
        if ($gp_refunded > 0) {
          $char_state = $this->reloadAndRefundGp($character_id, $campaign_id, $gp_refunded);
        }
        break;

      case self::DEGREE_SUCCESS:
        $spell_learned = TRUE;
        // Full cost consumed — no refund.
        break;

      case self::DEGREE_FAILURE:
        // Materials NOT consumed — restore full cost.
        if ($gp_cost > 0) {
          $char_state = $this->reloadAndRefundGp($character_id, $campaign_id, $gp_cost);
          $gp_refunded = $gp_cost;
        }
        break;

      case self::DEGREE_CRIT_FAILURE:
        // Materials lost — no restoration.
        break;
    }

    // If spell was learned, add to character spells.
    if ($spell_learned) {
      $this->addSpellToCharacter($character_id, $campaign_id, $spell_id, $spell_rank, $tradition);
    }

    // Log attempt.
    $this->saveAttemptState($character_id, 'learn_a_spell', $spell_id, $degree, [
      'gp_spent'    => $gp_cost - $gp_refunded,
      'gp_refunded' => $gp_refunded,
    ]);

    return [
      'success'           => $spell_learned,
      'degree'            => $degree,
      'dc'                => $dc,
      'roll'              => $d20,
      'total'             => $total,
      'gp_spent'          => max(0, $gp_cost - $gp_refunded),
      'gp_refunded'       => $gp_refunded,
      'spell_learned'     => $spell_learned,
      'time_cost_minutes' => self::LEARN_SPELL_MINUTES,
      'error'             => NULL,
    ];
  }

  // ===========================================================================
  // Degree of success
  // ===========================================================================

  /**
   * Resolve degree of success using PF2e rules.
   *
   * Natural 1: degree shifts one step down.
   * Natural 20: degree shifts one step up.
   *
   * @param int $total  d20 + modifiers.
   * @param int $dc     Target DC.
   * @param int $raw    Raw d20 result (for natural 1/20 adjustment).
   */
  public function resolveDegree(int $total, int $dc, int $raw = 10): string {
    // Base degree.
    if ($total >= $dc + 10) {
      $degree = self::DEGREE_CRIT_SUCCESS;
    }
    elseif ($total >= $dc) {
      $degree = self::DEGREE_SUCCESS;
    }
    elseif ($total <= $dc - 10) {
      $degree = self::DEGREE_CRIT_FAILURE;
    }
    else {
      $degree = self::DEGREE_FAILURE;
    }

    // Natural 1/20 shift.
    $order = [
      self::DEGREE_CRIT_FAILURE,
      self::DEGREE_FAILURE,
      self::DEGREE_SUCCESS,
      self::DEGREE_CRIT_SUCCESS,
    ];
    $idx = array_search($degree, $order, TRUE);
    if ($raw === 1 && $idx > 0) {
      $degree = $order[$idx - 1];
    }
    elseif ($raw === 20 && $idx < count($order) - 1) {
      $degree = $order[$idx + 1];
    }

    return $degree;
  }

  // ===========================================================================
  // Currency helpers (adapted from CraftingService pattern)
  // ===========================================================================

  protected function getCurrency(array $char_data): array {
    $currency = $char_data['character']['equipment']['currency']
      ?? $char_data['equipment']['currency']
      ?? $char_data['currency']
      ?? ['cp' => 0, 'sp' => 0, 'gp' => 0, 'pp' => 0];

    // Alias normalisation.
    if (!isset($currency['gp']) && isset($currency['gold'])) {
      $currency = ['cp' => 0, 'sp' => 0, 'gp' => (int) $currency['gold'], 'pp' => 0];
    }
    foreach (['cp', 'sp', 'gp', 'pp'] as $k) {
      $currency[$k] = (int) ($currency[$k] ?? 0);
    }
    return $currency;
  }

  protected function toCp(array $currency): int {
    return ($currency['cp'] ?? 0)
      + ($currency['sp'] ?? 0) * 10
      + ($currency['gp'] ?? 0) * 100
      + ($currency['pp'] ?? 0) * 1000;
  }

  protected function deductGp(array $char_data, int $gp): array {
    $currency = $this->getCurrency($char_data);
    $total_cp = $this->toCp($currency) - ($gp * 100);
    $total_cp = max(0, $total_cp);

    $new_currency = ['cp' => 0, 'sp' => 0, 'gp' => 0, 'pp' => 0];
    foreach (['pp', 'gp', 'sp', 'cp'] as $denom) {
      $rates = ['cp' => 1, 'sp' => 10, 'gp' => 100, 'pp' => 1000];
      $rate = $rates[$denom];
      $new_currency[$denom] = (int) floor($total_cp / $rate);
      $total_cp -= $new_currency[$denom] * $rate;
    }

    if (isset($char_data['character']['equipment']['currency'])) {
      $char_data['character']['equipment']['currency'] = $new_currency;
    }
    elseif (isset($char_data['equipment']['currency'])) {
      $char_data['equipment']['currency'] = $new_currency;
    }
    else {
      $char_data['currency'] = $new_currency;
    }
    return $char_data;
  }

  protected function reloadAndRefundGp(string $character_id, string $campaign_id, int $gp): array {
    $char_state = $this->characterStateService->getState($character_id, $campaign_id);
    $currency   = $this->getCurrency($char_state);
    $total_cp   = $this->toCp($currency) + ($gp * 100);

    $new_currency = ['cp' => 0, 'sp' => 0, 'gp' => 0, 'pp' => 0];
    foreach (['pp', 'gp', 'sp', 'cp'] as $denom) {
      $rates = ['cp' => 1, 'sp' => 10, 'gp' => 100, 'pp' => 1000];
      $rate  = $rates[$denom];
      $new_currency[$denom] = (int) floor($total_cp / $rate);
      $total_cp -= $new_currency[$denom] * $rate;
    }

    if (isset($char_state['character']['equipment']['currency'])) {
      $char_state['character']['equipment']['currency'] = $new_currency;
    }
    elseif (isset($char_state['equipment']['currency'])) {
      $char_state['equipment']['currency'] = $new_currency;
    }
    else {
      $char_state['currency'] = $new_currency;
    }
    $this->characterStateService->setState($character_id, $char_state, NULL, $campaign_id);
    return $char_state;
  }

  // ===========================================================================
  // Spell learning helper
  // ===========================================================================

  /**
   * Add a learned spell to the character's spellbook or repertoire.
   * Uses the character state 'spells_known' or 'spellbook' array.
   */
  protected function addSpellToCharacter(
    string $character_id,
    string $campaign_id,
    string $spell_id,
    int $spell_rank,
    string $tradition
  ): void {
    $char_state = $this->characterStateService->getState($character_id, $campaign_id);
    if ($char_state === NULL) {
      return;
    }

    $casting_type = $char_state['character']['casting_type']
      ?? $char_state['casting_type']
      ?? 'spontaneous';

    if ($casting_type === 'prepared') {
      // Spellbook: keyed by rank → array of spell IDs.
      $char_state['spellbook'][$spell_rank][] = $spell_id;
      $char_state['spellbook'][$spell_rank]   = array_unique($char_state['spellbook'][$spell_rank]);
    }
    else {
      // Spontaneous repertoire.
      $char_state['spells_known'][$spell_rank][] = $spell_id;
      $char_state['spells_known'][$spell_rank]   = array_unique($char_state['spells_known'][$spell_rank]);
    }

    $this->characterStateService->setState($character_id, $char_state, NULL, $campaign_id);
  }

  // ===========================================================================
  // State persistence
  // ===========================================================================

  protected function rollD20(): int {
    // Use Drupal's built-in random for consistent seeding; fallback to PHP rand.
    return \Drupal::service('dungeoncrawler_content.number_generation')
      ->rollPathfinderDie(20);
  }

  protected function loadAttemptState(
    string $character_id,
    string $action_type,
    string $target_id
  ): ?array {
    if (!$this->db->schema()->tableExists('dc_knowledge_attempt_state')) {
      return NULL;
    }
    $row = $this->db->select('dc_knowledge_attempt_state', 's')
      ->fields('s')
      ->condition('character_id', $character_id)
      ->condition('action_type', $action_type)
      ->condition('target_id', $target_id)
      ->execute()
      ->fetchAssoc();

    return $row ?: NULL;
  }

  protected function saveAttemptState(
    string $character_id,
    string $action_type,
    string $target_id,
    string $degree,
    array $extra_fields = []
  ): void {
    if (!$this->db->schema()->tableExists('dc_knowledge_attempt_state')) {
      return;
    }
    $this->db->merge('dc_knowledge_attempt_state')
      ->keys([
        'character_id' => $character_id,
        'action_type'  => $action_type,
        'target_id'    => $target_id,
      ])
      ->fields(array_merge([
        'degree'       => $degree,
        'attempted_at' => \Drupal::time()->getCurrentTime(),
      ], $extra_fields))
      ->execute();
  }

  // ===========================================================================
  // Error helper
  // ===========================================================================

  protected function errorResult(string $message, array $extra = []): array {
    return array_merge([
      'success'           => FALSE,
      'degree'            => NULL,
      'dc'                => NULL,
      'roll'              => NULL,
      'total'             => NULL,
      'outcome'           => NULL,
      'is_false'          => FALSE,
      'skill_used'        => NULL,
      'valid_skills'      => [],
      'time_cost_minutes' => 0,
      'retry_penalty'     => 0,
      'spell_learned'     => FALSE,
      'gp_spent'          => 0,
      'gp_refunded'       => 0,
      'error'             => $message,
    ], $extra);
  }

}
