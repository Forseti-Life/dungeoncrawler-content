<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Handles PF2e character leveling and advancement.
 *
 * Milestone-based: leveling gates on session milestone flag, not XP threshold.
 * PM decision (2026-03-08): dc-cr-xp-rewards dependency removed.
 *
 * Level-up state is stored in character_data JSON under 'levelUpState':
 *   - milestoneReady (bool): GM has granted advancement eligibility.
 *   - inProgress (bool): a transition is active with unresolved player choices.
 *   - transitionTo (int): target level during in-progress transitions.
 *   - pendingChoices (array): unresolved player-choice slots.
 *   - completedChoices (array): resolved slots per level (audit trail).
 *   - autoApplied (array): names of features auto-applied at last trigger.
 *   - hpGranted (int): HP bonus applied at last trigger.
 */
class CharacterLevelingService {

  const MAX_LEVEL = 20;

  /** Proficiency rank order for skill increase validation. */
  const RANK_ORDER = ['untrained', 'trained', 'expert', 'master', 'legendary'];

  /** Valid ability score names. */
  const ABILITIES = ['strength', 'dexterity', 'constitution', 'intelligence', 'wisdom', 'charisma'];

  public function __construct(
    protected readonly Connection $database,
    protected readonly MulticlassArchetypeService $multiclassArchetypeService = new MulticlassArchetypeService(),
    protected readonly ?DeityService $deityService = NULL
  ) {}

  // ── Public API ─────────────────────────────────────────────────────────────

  /**
   * Get the level-up status for a character.
   *
   * @param string $character_id  Character ID.
   * @return array  Status payload.
   * @throws \InvalidArgumentException  If character not found.
   */
  public function getStatus(string $character_id): array {
    $record = $this->loadRecord($character_id);
    $char_data = json_decode($record->character_data, TRUE) ?? [];
    $level = (int) ($char_data['basicInfo']['level'] ?? $record->level ?? 1);
    $lus = $char_data['levelUpState'] ?? [];

    return [
      'success' => TRUE,
      'characterId' => $character_id,
      'currentLevel' => $level,
      'maxLevel' => self::MAX_LEVEL,
      'milestoneReady' => (bool) ($lus['milestoneReady'] ?? FALSE),
      'inProgress' => (bool) ($lus['inProgress'] ?? FALSE),
      'transitionTo' => (int) ($lus['transitionTo'] ?? 0),
      'pendingChoices' => $lus['pendingChoices'] ?? [],
      'autoApplied' => $lus['autoApplied'] ?? [],
      'hpGranted' => $lus['hpGranted'] ?? 0,
      'canTrigger' => ($level < self::MAX_LEVEL)
        && !empty($lus['milestoneReady'])
        && empty($lus['inProgress']),
    ];
  }

  /**
   * Set or clear the session milestone for a character (GM/admin action).
   *
   * @param string $character_id  Character ID.
   * @param bool $ready  TRUE to grant milestone; FALSE to clear it.
   * @return array  Updated status.
   * @throws \InvalidArgumentException  If character not found.
   */
  public function setMilestone(string $character_id, bool $ready): array {
    $record = $this->loadRecord($character_id);
    $char_data = json_decode($record->character_data, TRUE) ?? [];
    $char_data['levelUpState']['milestoneReady'] = $ready;
    $this->persistCharacterData($character_id, $char_data);
    return $this->getStatus($character_id);
  }

  /**
   * Trigger a level-up for a character.
   *
   * Checks milestone (unless admin_force). Auto-applies no-choice class features.
   * Computes pending player choices (ability boosts, skill increases, feat slots).
   * If no choices are required (auto-only level), finalizes immediately.
   *
   * Idempotent for the same level transition: calling trigger while a transition
   * is already in progress returns the current pending state without re-applying.
   *
   * @param string $character_id  Character ID.
   * @param bool $admin_force  Skip milestone check (admin/GM override).
   * @return array  Status payload with pending choices.
   * @throws \InvalidArgumentException  On max-level, no milestone, or bad char.
   */
  public function triggerLevelUp(string $character_id, bool $admin_force = FALSE): array {
    $record = $this->loadRecord($character_id);
    $char_data = json_decode($record->character_data, TRUE) ?? [];
    $level = (int) ($char_data['basicInfo']['level'] ?? $record->level ?? 1);
    $lus = $char_data['levelUpState'] ?? [];

    // Already at max level.
    if ($level >= self::MAX_LEVEL) {
      throw new \InvalidArgumentException('Already at maximum level', 400);
    }

    // Idempotent: in-progress for the same target level returns current state.
    // After triggerLevelUp runs, level is already incremented to transitionTo, so compare directly.
    if (!empty($lus['inProgress']) && (int) ($lus['transitionTo'] ?? 0) === $level) {
      return $this->buildStatusResponse($char_data, $character_id);
    }

    // Milestone check (admin_force bypasses).
    if (!$admin_force && empty($lus['milestoneReady'])) {
      throw new \InvalidArgumentException('Session milestone has not been reached', 403);
    }

    $new_level = $level + 1;
    $class_name = strtolower($char_data['basicInfo']['class'] ?? $record->class ?? 'fighter');
    $advancement = CharacterManager::getClassAdvancement($class_name, $new_level);

    // Auto-apply class features (no player choice).
    $auto_applied = [];
    $char_data['features'] = $char_data['features'] ?? ['classFeatures' => [], 'feats' => []];
    $char_data['features']['classFeatures'] = $char_data['features']['classFeatures'] ?? [];
    foreach ($advancement['auto_features'] as $feature) {
      // Skip if already applied (idempotent).
      $existing_ids = array_column($char_data['features']['classFeatures'], 'id');
      if (!in_array($feature['id'], $existing_ids, TRUE)) {
        $char_data['features']['classFeatures'][] = $feature;
      }
      $auto_applied[] = $feature['name'];
    }

    // Auto-apply HP bonus (class HP per level, full per PF2e standard).
    $hp_bonus = $advancement['hp_bonus'];
    $char_data['resources'] = $char_data['resources'] ?? ['hitPoints' => ['current' => 0, 'max' => 0]];
    $char_data['resources']['hitPoints'] = $char_data['resources']['hitPoints'] ?? ['current' => 0, 'max' => 0];
    $new_max_hp = (int) ($char_data['resources']['hitPoints']['max'] ?? 0) + $hp_bonus;
    $char_data['resources']['hitPoints']['max'] = $new_max_hp;
    $char_data['resources']['hitPoints']['current'] = min(
      (int) ($char_data['resources']['hitPoints']['current'] ?? 0) + $hp_bonus,
      $new_max_hp
    );

    // Increment level.
    $char_data['basicInfo']['level'] = $new_level;
    $char_data['level'] = $new_level;

    // Build pending player choices.
    $pending = [];
    if ($advancement['ability_boosts'] > 0) {
      $pending[] = [
        'type'     => 'ability_boosts',
        'count'    => $advancement['ability_boosts'],
        'label'    => "Choose {$advancement['ability_boosts']} ability boosts (each ability at most once per milestone)",
        'resolved' => FALSE,
      ];
    }
    for ($i = 0; $i < $advancement['skill_increases']; $i++) {
      $pending[] = [
        'type'     => 'skill_increase',
        'label'    => 'Raise one skill proficiency rank by one step',
        'resolved' => FALSE,
      ];
    }
    foreach ($advancement['feat_slots'] as $slot) {
      $pending[] = [
        'type'      => 'feat_choice',
        'slot_type' => $slot['slot_type'],
        'label'     => $slot['label'],
        'resolved'  => FALSE,
      ];
    }

    // Set level-up state.
    $char_data['levelUpState'] = [
      'milestoneReady'   => FALSE,
      'inProgress'       => !empty($pending),
      'transitionTo'     => $new_level,
      'pendingChoices'   => $pending,
      'completedChoices' => $lus['completedChoices'] ?? [],
      'autoApplied'      => $auto_applied,
      'hpGranted'        => $hp_bonus,
    ];

    // If no pending choices, finalize immediately.
    if (empty($pending)) {
      $char_data['levelUpState']['inProgress'] = FALSE;
      $char_data['levelUpState']['completedChoices'][] = [
        'level'   => $new_level,
        'choices' => [],
        'note'    => 'Auto-completed (no player choices required)',
      ];
    }

    // Sync the dc_campaign_characters.level column.
    $this->database->update('dc_campaign_characters')
      ->fields(['level' => $new_level])
      ->condition('id', $character_id)
      ->condition('campaign_id', 0)
      ->execute();

    $this->persistCharacterData($character_id, $char_data);

    return $this->buildStatusResponse($char_data, $character_id);
  }

  /**
   * Submit ability boost choices.
   *
   * @param string $character_id  Character ID.
   * @param array $abilities  Array of ability names (e.g. ['strength', 'wisdom']).
   * @return array  Updated status.
   * @throws \InvalidArgumentException  On validation failure.
   */
  public function submitAbilityBoosts(string $character_id, array $abilities): array {
    $record = $this->loadRecord($character_id);
    $char_data = json_decode($record->character_data, TRUE) ?? [];
    $lus = $char_data['levelUpState'] ?? [];

    if (empty($lus['inProgress'])) {
      throw new \InvalidArgumentException('No level-up in progress', 400);
    }

    $slot_idx = $this->findPendingSlot($lus['pendingChoices'] ?? [], 'ability_boosts');
    if ($slot_idx === -1) {
      throw new \InvalidArgumentException('No ability boost choice pending at this level', 400);
    }

    $required = (int) ($lus['pendingChoices'][$slot_idx]['count'] ?? 4);

    // Validate count.
    if (count($abilities) !== $required) {
      throw new \InvalidArgumentException(
        "Exactly {$required} ability boost(s) required; received " . count($abilities), 400
      );
    }

    // Normalize and validate unique, valid ability names.
    $normalized = array_map('strtolower', $abilities);
    if (count(array_unique($normalized)) !== $required) {
      throw new \InvalidArgumentException('Each ability may only be boosted once per milestone', 400);
    }
    foreach ($normalized as $ability) {
      if (!in_array($ability, self::ABILITIES, TRUE)) {
        $valid = implode(', ', self::ABILITIES);
        throw new \InvalidArgumentException("Unknown ability '{$ability}'. Valid: {$valid}", 400);
      }
    }

    // Apply boosts (+2 each; post-creation boosts may exceed 18 per PF2e rules).
    $char_data['abilities'] = $char_data['abilities'] ?? [];
    foreach ($normalized as $ability) {
      $current = (int) ($char_data['abilities'][$ability] ?? 10);
      $char_data['abilities'][$ability] = $current + 2;
    }

    // Mark slot resolved.
    $char_data['levelUpState']['pendingChoices'][$slot_idx]['resolved'] = TRUE;
    $char_data['levelUpState']['pendingChoices'][$slot_idx]['choices']  = $normalized;

    $this->checkAndFinalizeLevelUp($char_data);
    $this->persistCharacterData($character_id, $char_data);

    return $this->buildStatusResponse($char_data, $character_id);
  }

  /**
   * Submit a skill increase choice.
   *
   * @param string $character_id  Character ID.
   * @param string $skill  Skill name (e.g. 'arcana').
   * @return array  Updated status.
   * @throws \InvalidArgumentException  On validation failure.
   */
  public function submitSkillIncrease(string $character_id, string $skill): array {
    $record = $this->loadRecord($character_id);
    $char_data = json_decode($record->character_data, TRUE) ?? [];
    $lus = $char_data['levelUpState'] ?? [];

    if (empty($lus['inProgress'])) {
      throw new \InvalidArgumentException('No level-up in progress', 400);
    }

    $slot_idx = $this->findPendingSlot($lus['pendingChoices'] ?? [], 'skill_increase');
    if ($slot_idx === -1) {
      throw new \InvalidArgumentException('No skill increase pending at this level', 400);
    }

    $skill = strtolower(trim($skill));
    $valid_skills = array_keys(CharacterCalculator::SKILLS);
    if (!in_array($skill, $valid_skills, TRUE)) {
      $valid = implode(', ', $valid_skills);
      throw new \InvalidArgumentException("Unknown skill '{$skill}'. Valid: {$valid}", 400);
    }

    // Advance rank by one step.
    $current_rank = strtolower($char_data['skills'][$skill] ?? 'untrained');
    $rank_idx = array_search($current_rank, self::RANK_ORDER, TRUE);
    if ($rank_idx === FALSE) {
      $rank_idx = 0;
    }
    if ($rank_idx >= count(self::RANK_ORDER) - 1) {
      throw new \InvalidArgumentException("Skill '{$skill}' is already at maximum rank (Legendary)", 400);
    }

    $new_rank = ucfirst(self::RANK_ORDER[$rank_idx + 1]);

    // Level ceiling enforcement (REQ 1555-1556).
    // Expert → Master requires level ≥ 7; Master → Legendary requires level ≥ 15.
    $char_level = (int) ($char_data['basicInfo']['level'] ?? $char_data['level'] ?? 1);
    if ($new_rank === 'Master' && $char_level < 7) {
      throw new \InvalidArgumentException(
        "Cannot increase '{$skill}' to Master: requires level 7 (current level {$char_level})", 400
      );
    }
    if ($new_rank === 'Legendary' && $char_level < 15) {
      throw new \InvalidArgumentException(
        "Cannot increase '{$skill}' to Legendary: requires level 15 (current level {$char_level})", 400
      );
    }

    $char_data['skills'] = $char_data['skills'] ?? [];
    $char_data['skills'][$skill] = $new_rank;

    // Mark slot resolved.
    $char_data['levelUpState']['pendingChoices'][$slot_idx]['resolved'] = TRUE;
    $char_data['levelUpState']['pendingChoices'][$slot_idx]['choice']   = [
      'skill'   => $skill,
      'newRank' => $new_rank,
    ];

    $this->checkAndFinalizeLevelUp($char_data);
    $this->persistCharacterData($character_id, $char_data);

    return $this->buildStatusResponse($char_data, $character_id);
  }

  /**
   * Submit a feat selection for an open feat slot.
   *
   * @param string $character_id  Character ID.
   * @param string $slot_type  'class_feat', 'skill_feat', 'general_feat', 'ancestry_feat'.
   * @param string $feat_id  Feat ID from CharacterManager catalogs.
   * @return array  Updated status.
   * @throws \InvalidArgumentException  On invalid feat or prerequisites.
   */
  public function submitFeat(string $character_id, string $slot_type, string $feat_id, array $feat_params = []): array {
    $record = $this->loadRecord($character_id);
    $char_data = json_decode($record->character_data, TRUE) ?? [];
    $lus = $char_data['levelUpState'] ?? [];

    if (empty($lus['inProgress'])) {
      throw new \InvalidArgumentException('No level-up in progress', 400);
    }

    $slot_idx = $this->findPendingFeatSlot($lus['pendingChoices'] ?? [], $slot_type);
    if ($slot_idx === -1) {
      throw new \InvalidArgumentException("No open {$slot_type} feat slot pending at this level", 400);
    }

    $level = (int) ($char_data['basicInfo']['level'] ?? 1);
    $class_name = strtolower($char_data['basicInfo']['class'] ?? 'fighter');

    // Validate feat and prerequisites.
    $feat = $this->validateFeat($feat_id, $slot_type, $class_name, $level, $char_data, $feat_params);

    // AC-002 / AC-004: if this is a dedication feat, run multiclass dedication
    // validation (breadth rule, no duplicate dedication, level minimum).
    if ($slot_type === 'class_feat' && !empty($feat['traits']) && in_array('Dedication', $feat['traits'], TRUE)) {
      $this->multiclassArchetypeService->validateDedicationSelection($feat_id, $char_data);
    }

    // Add feat to character.
    $char_data['features'] = $char_data['features'] ?? ['classFeatures' => [], 'feats' => []];
    $char_data['features']['feats'] = $char_data['features']['feats'] ?? [];
    $feat_entry = [
      'id'              => $feat_id,
      'name'            => $feat['name'] ?? $feat_id,
      'slot_type'       => $slot_type,
      'gained_at_level' => $level,
    ];
    if (!empty($feat_params)) {
      $feat_entry['feat_params'] = $feat_params;
    }
    $char_data['features']['feats'][] = $feat_entry;

    // Mark slot resolved.
    $char_data['levelUpState']['pendingChoices'][$slot_idx]['resolved'] = TRUE;
    $char_data['levelUpState']['pendingChoices'][$slot_idx]['choice']   = [
      'feat_id'   => $feat_id,
      'feat_name' => $feat['name'] ?? $feat_id,
    ];

    $this->checkAndFinalizeLevelUp($char_data);
    $this->persistCharacterData($character_id, $char_data);

    return $this->buildStatusResponse($char_data, $character_id);
  }

  /**
   * Admin: force-apply a level-up, bypassing the milestone requirement.
   *
   * @param string $character_id  Character ID.
   * @return array  Status payload with pending choices.
   */
  public function adminForceLevelUp(string $character_id): array {
    return $this->triggerLevelUp($character_id, TRUE);
  }

  /**
   * Admin: reset a level-up, reverting the character to the previous level.
   *
   * Reverts: level, HP bonus, auto-applied class features.
   * Does NOT revert feat selections or ability boosts if already resolved
   * (use with caution; intended for GM tooling / test scenarios).
   *
   * @param string $character_id  Character ID.
   * @return array  Result with previous level.
   * @throws \InvalidArgumentException  If no transition to revert.
   */
  public function adminResetLevelUp(string $character_id): array {
    $record = $this->loadRecord($character_id);
    $char_data = json_decode($record->character_data, TRUE) ?? [];
    $lus = $char_data['levelUpState'] ?? [];

    $transition_to = (int) ($lus['transitionTo'] ?? 0);
    if ($transition_to < 2) {
      throw new \InvalidArgumentException(
        'No level-up to reset (character is at base level or no transition recorded)', 400
      );
    }

    $prev_level = $transition_to - 1;
    $class_name = strtolower($char_data['basicInfo']['class'] ?? 'fighter');
    $advancement = CharacterManager::getClassAdvancement($class_name, $transition_to);

    // Revert level.
    $char_data['basicInfo']['level'] = $prev_level;
    $char_data['level'] = $prev_level;

    // Revert HP.
    $hp_granted = (int) ($lus['hpGranted'] ?? $advancement['hp_bonus']);
    $char_data['resources']['hitPoints']['max'] = max(
      0, (int) ($char_data['resources']['hitPoints']['max'] ?? 0) - $hp_granted
    );
    $char_data['resources']['hitPoints']['current'] = min(
      (int) ($char_data['resources']['hitPoints']['current'] ?? 0),
      $char_data['resources']['hitPoints']['max']
    );

    // Revert auto-applied class features.
    $auto_ids = array_column($advancement['auto_features'], 'id');
    $char_data['features']['classFeatures'] = array_values(array_filter(
      $char_data['features']['classFeatures'] ?? [],
      static fn($f) => !in_array($f['id'] ?? '', $auto_ids, TRUE)
    ));

    // Clear level-up state.
    $char_data['levelUpState'] = [
      'milestoneReady'   => FALSE,
      'inProgress'       => FALSE,
      'transitionTo'     => 0,
      'pendingChoices'   => [],
      'completedChoices' => $lus['completedChoices'] ?? [],
    ];

    $this->database->update('dc_campaign_characters')
      ->fields(['level' => $prev_level])
      ->condition('id', $character_id)
      ->condition('campaign_id', 0)
      ->execute();

    $this->persistCharacterData($character_id, $char_data);

    return [
      'success'      => TRUE,
      'message'      => "Level reset from {$transition_to} to {$prev_level}",
      'currentLevel' => $prev_level,
    ];
  }

  /**
   * Get feats eligible for a given character and slot type.
   *
   * Filters by level prerequisite and deduplicates against already-owned feats.
   *
   * @param string $character_id  Character ID.
   * @param string $slot_type  'class_feat', 'skill_feat', 'general_feat', 'ancestry_feat'.
   * @return array  Eligible feat entries.
   */
  public function getEligibleFeats(string $character_id, string $slot_type): array {
    $record = $this->loadRecord($character_id);
    $char_data = json_decode($record->character_data, TRUE) ?? [];
    $level = (int) ($char_data['basicInfo']['level'] ?? 1);
    $class_name = strtolower($char_data['basicInfo']['class'] ?? 'fighter');
    $owned_ids = array_column($char_data['features']['feats'] ?? [], 'id');
    $gm_unlocked = $char_data['gm_unlocked_feats'] ?? [];

    $catalog = match ($slot_type) {
      'class_feat'    => CharacterManager::getClassFeats($class_name),
      'skill_feat'    => CharacterManager::SKILL_FEATS,
      'general_feat'  => CharacterManager::getGeneralFeats(),
      'ancestry_feat' => $this->getAvailableAncestryFeatCatalog($char_data),
      default         => [],
    };

    // AC-003: merge multiclass archetype feats into class feat slots so
    // players with active dedications can choose archetype feats at even levels.
    if ($slot_type === 'class_feat') {
      $archetype_feats = $this->multiclassArchetypeService->getEligibleArchetypeFeats($char_data);
      $catalog = array_merge($catalog, $archetype_feats);
    }

    // Resolve character's deity for domain feat eligibility filtering.
    $deity_id = $char_data['personality']['deity'] ?? $char_data['basicInfo']['deity'] ?? '';
    $deity_domains = [];
    if ($deity_id !== '' && $this->deityService !== NULL) {
      $deity_domains = $this->deityService->getDomainsForInput($deity_id);
    }
    $deityService = $this->deityService;

    return array_values(array_filter($catalog, static function (array $feat) use ($level, $owned_ids, $gm_unlocked, $deity_domains, $deityService): bool {
      if (isset($feat['level']) && (int) $feat['level'] > $level) {
        return FALSE;
      }
      if (in_array($feat['id'] ?? '', $owned_ids, TRUE)) {
        return FALSE;
      }
      // Uncommon feats require GM unlock in character data.
      if (!empty($feat['uncommon']) && !in_array($feat['id'] ?? '', $gm_unlocked, TRUE)) {
        return FALSE;
      }
      // Domain feats (Domain Initiate, Advanced Domain) require matching deity domains.
      if (!empty($feat['requires_domain']) && $deityService !== NULL) {
        $required_domain = $feat['requires_domain'];
        if (!in_array($required_domain, $deity_domains, TRUE)) {
          return FALSE;
        }
      }
      return TRUE;
    }));
  }

  // ── Private helpers ─────────────────────────────────────────────────────────

  /**
   * Load the library record (campaign_id = 0) for a character.
   *
   * @throws \InvalidArgumentException  If character not found.
   */
  private function loadRecord(string $character_id): object {
    $record = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c')
      ->condition('id', $character_id)
      ->condition('campaign_id', 0)
      ->execute()
      ->fetchObject();

    if (!$record) {
      throw new \InvalidArgumentException("Character not found: {$character_id}", 404);
    }

    return $record;
  }

  /**
   * Persist character_data back to the library record.
   */
  private function persistCharacterData(string $character_id, array $char_data): void {
    $this->database->update('dc_campaign_characters')
      ->fields([
        'character_data' => json_encode($char_data),
        'changed'        => time(),
      ])
      ->condition('id', $character_id)
      ->condition('campaign_id', 0)
      ->execute();
  }

  /**
   * Find the first unresolved slot of a given type.
   *
   * @return int  Index in $pending, or -1 if not found.
   */
  private function findPendingSlot(array $pending, string $type): int {
    foreach ($pending as $idx => $slot) {
      if ($slot['type'] === $type && empty($slot['resolved'])) {
        return $idx;
      }
    }
    return -1;
  }

  /**
   * Find the first unresolved feat_choice slot matching slot_type.
   *
   * Falls back to any unresolved feat_choice if the requested slot_type
   * has no dedicated match (enables flexible slot assignment).
   *
   * @return int  Index in $pending, or -1 if not found.
   */
  private function findPendingFeatSlot(array $pending, string $slot_type): int {
    foreach ($pending as $idx => $slot) {
      if ($slot['type'] === 'feat_choice'
        && ($slot['slot_type'] ?? '') === $slot_type
        && empty($slot['resolved'])
      ) {
        return $idx;
      }
    }
    // Fallback: any open feat_choice slot.
    foreach ($pending as $idx => $slot) {
      if ($slot['type'] === 'feat_choice' && empty($slot['resolved'])) {
        return $idx;
      }
    }
    return -1;
  }

  /**
   * Check whether all pending choices are resolved; if so, finalize the level-up.
   *
   * Moves pendingChoices to completedChoices audit trail and marks inProgress = FALSE.
   */
  private function checkAndFinalizeLevelUp(array &$char_data): void {
    $pending = $char_data['levelUpState']['pendingChoices'] ?? [];
    if (empty($pending)) {
      return;
    }

    $all_resolved = array_reduce(
      $pending,
      static fn(bool $carry, array $slot): bool => $carry && !empty($slot['resolved']),
      TRUE
    );

    if ($all_resolved) {
      $char_data['levelUpState']['inProgress'] = FALSE;
      $char_data['levelUpState']['completedChoices'][] = [
        'level'   => $char_data['levelUpState']['transitionTo'],
        'choices' => $pending,
      ];
      $char_data['levelUpState']['pendingChoices'] = [];
    }
  }

  /**
   * Build the standard status response payload from char_data.
   */
  private function buildStatusResponse(array $char_data, string $character_id): array {
    $lus = $char_data['levelUpState'] ?? [];
    return [
      'success'       => TRUE,
      'characterId'   => $character_id,
      'currentLevel'  => (int) ($char_data['basicInfo']['level'] ?? 1),
      'inProgress'    => (bool) ($lus['inProgress'] ?? FALSE),
      'transitionTo'  => (int) ($lus['transitionTo'] ?? 0),
      'pendingChoices' => $lus['pendingChoices'] ?? [],
      'autoApplied'   => $lus['autoApplied'] ?? [],
      'hpGranted'     => $lus['hpGranted'] ?? 0,
      'milestoneReady' => (bool) ($lus['milestoneReady'] ?? FALSE),
    ];
  }

  /**
   * Validate a feat selection against level prerequisites and ownership.
   *
   * @return array  The feat definition.
   * @throws \InvalidArgumentException  If feat is invalid, level-gated, or already owned.
   */
  private function validateFeat(
    string $feat_id,
    string $slot_type,
    string $class_name,
    int $level,
    array $char_data,
    array $feat_params = []
  ): array {
    // Build the eligible catalog for the slot type.
    $catalog = match ($slot_type) {
      'class_feat'    => CharacterManager::getClassFeats($class_name),
      'skill_feat'    => CharacterManager::SKILL_FEATS,
      'general_feat'  => CharacterManager::getGeneralFeats(),
      'ancestry_feat' => $this->getAvailableAncestryFeatCatalog($char_data),
      default         => array_merge(
        CharacterManager::getClassFeats($class_name),
        CharacterManager::SKILL_FEATS,
        CharacterManager::getGeneralFeats(),
      ),
    };

    // AC-003: also search archetype feats for class_feat slots so dedication
    // + archetype feat selections are accepted by the validator.
    if ($slot_type === 'class_feat') {
      $owned_feat_ids = array_column($char_data['features']['feats'] ?? [], 'id');
      $held = $this->multiclassArchetypeService->getHeldArchetypeIds($owned_feat_ids);
      // Include archetype feats from held dedications (no level filter here —
      // that check follows below using the feat's own level key).
      foreach (CharacterManager::MULTICLASS_ARCHETYPES as $archetype) {
        if (!in_array($archetype['id'], $held, TRUE)) {
          continue;
        }
        $catalog = array_merge($catalog, $archetype['archetype_feats']);
      }
      // Also include dedication feats themselves so validateFeat finds them.
      foreach (CharacterManager::MULTICLASS_ARCHETYPES as $archetype) {
        $catalog[] = $archetype['dedication'];
      }
    }

    $feat = NULL;
    foreach ($catalog as $f) {
      if (($f['id'] ?? '') === $feat_id) {
        $feat = $f;
        break;
      }
    }

    if ($feat === NULL) {
      throw new \InvalidArgumentException(
        "Unknown feat '{$feat_id}' for slot type '{$slot_type}' and class '{$class_name}'", 400
      );
    }

    // Level prerequisite check.
    if (isset($feat['level']) && (int) $feat['level'] > $level) {
      throw new \InvalidArgumentException(
        "Feat '{$feat_id}' requires level {$feat['level']}; character is level {$level}", 400
      );
    }

    // AC-001/AC-006: skill_feat slots require the feat to have the Skill trait.
    if ($slot_type === 'skill_feat' && !in_array('Skill', $feat['traits'] ?? [], TRUE)) {
      throw new \InvalidArgumentException(
        "Feat '{$feat_id}' does not have the Skill trait and cannot fill a skill_feat slot", 400
      );
    }

    // Already-owned check — with repeatable and per-skill exceptions.
    $owned_feats = $char_data['features']['feats'] ?? [];
    $owned_ids = array_column($owned_feats, 'id');

    if (!empty($feat['repeatable'])) {
      // Repeatable feats (e.g., Armor Proficiency, Weapon Proficiency): allow
      // re-selection up to repeatable_max times.
      $owned_count = count(array_keys($owned_ids, $feat_id, TRUE));
      $max = (int) ($feat['repeatable_max'] ?? 1);
      if ($owned_count >= $max) {
        throw new \InvalidArgumentException(
          "Feat '{$feat_id}' has already been selected the maximum {$max} time(s)", 400
        );
      }
    }
    elseif (!empty($feat['assurance_per_skill'])) {
      // Assurance can be taken once per skill — block same skill, allow new skills.
      $selected_skill = strtolower(trim($feat_params['skill'] ?? ''));
      if ($selected_skill === '') {
        throw new \InvalidArgumentException(
          "Feat '{$feat_id}' requires a 'skill' in feat_params (e.g. Acrobatics)", 400
        );
      }
      foreach ($owned_feats as $owned) {
        if (($owned['id'] ?? '') === $feat_id) {
          $owned_skill = strtolower(trim($owned['feat_params']['skill'] ?? ''));
          if ($owned_skill === $selected_skill) {
            throw new \InvalidArgumentException(
              "Assurance ({$selected_skill}) is already in character's feat list", 400
            );
          }
        }
      }
    }
    elseif (in_array($feat_id, $owned_ids, TRUE)) {
      throw new \InvalidArgumentException("Feat '{$feat_id}' is already in character's feat list", 400);
    }

    // Uncommon feats require explicit GM unlock in character data.
    if (!empty($feat['uncommon'])) {
      $gm_unlocked = $char_data['gm_unlocked_feats'] ?? [];
      if (!in_array($feat_id, $gm_unlocked, TRUE)) {
        throw new \InvalidArgumentException("Feat '{$feat_id}' is Uncommon and requires GM unlock", 403);
      }
    }

    // Primal innate spell prerequisite (e.g. First World Adept).
    if (!empty($feat['prerequisite_primal_innate_spell'])) {
      if (!$this->characterHasPrimalInnateSpell($char_data)) {
        throw new \InvalidArgumentException(
          "Feat '{$feat_id}' requires at least one primal innate spell (from heritage or feat)", 400
        );
      }
    }

    // Gnome Weapon Familiarity prerequisite (e.g. Gnome Weapon Expertise).
    if (!empty($feat['prerequisite_gnome_weapon_familiarity'])) {
      if (!$this->characterHasGnomeWeaponFamiliarity($char_data)) {
        throw new \InvalidArgumentException(
          "Feat '{$feat_id}' requires Gnome Weapon Familiarity", 400
        );
      }
    }

    // Goblin Weapon Familiarity prerequisite (e.g. Goblin Weapon Frenzy).
    if (!empty($feat['prerequisite_goblin_weapon_familiarity'])) {
      if (!$this->characterHasGoblinWeaponFamiliarity($char_data)) {
        throw new \InvalidArgumentException(
          "Feat '{$feat_id}' requires Goblin Weapon Familiarity", 400
        );
      }
    }

    if ($feat_id === 'lesson-of-elements') {
      $selected_spell = strtolower(trim((string) ($feat_params['selected_spell'] ?? '')));
      $valid_spells = ['burning-hands', 'gust-of-wind', 'hydraulic-push', 'pummeling-rubble'];
      if ($selected_spell === '' || !in_array($selected_spell, $valid_spells, TRUE)) {
        throw new \InvalidArgumentException(
          "Feat 'lesson-of-elements' requires feat_params['selected_spell'] to be one of: "
          . implode(', ', $valid_spells),
          400
        );
      }
    }
    if ($feat_id === 'weapon-proficiency') {
      $grant_state = CharacterManager::resolveWeaponProficiencyGrant($char_data);
      if (($grant_state['mode'] ?? '') === 'no_upgrade') {
        throw new \InvalidArgumentException(
          "Feat 'weapon-proficiency' does not grant an additional benefit for the character's current class",
          400
        );
      }
      if (($grant_state['mode'] ?? '') === 'advanced_choice') {
        $selected_weapon_id = trim((string) ($feat_params['selected_weapon_id'] ?? ''));
        $advanced_weapon_options = CharacterManager::getAdvancedWeaponOptions();
        if ($selected_weapon_id === '' || !array_key_exists($selected_weapon_id, $advanced_weapon_options)) {
          throw new \InvalidArgumentException(
            "Feat 'weapon-proficiency' requires feat_params['selected_weapon_id'] to be a valid advanced weapon id",
            400
          );
        }
        if (in_array($selected_weapon_id, $grant_state['owned_advanced_weapon_ids'] ?? [], TRUE)) {
          throw new \InvalidArgumentException(
            "Feat 'weapon-proficiency' already grants the advanced weapon '{$selected_weapon_id}'",
            400
          );
        }
      }
    }
    if ($feat_id === 'adopted-ancestry') {
      $this->validateAdoptedAncestrySelection($char_data, $feat_params);
    }
    if ($feat_id === 'unconventional-weaponry') {
      $selected_weapon_id = trim((string) ($feat_params['selected_weapon_id'] ?? ''));
      $weapon_options = CharacterManager::getUnconventionalWeaponOptions();
      if ($selected_weapon_id === '' || !array_key_exists($selected_weapon_id, $weapon_options)) {
        throw new \InvalidArgumentException(
          "Feat 'unconventional-weaponry' requires feat_params['selected_weapon_id'] to be a valid uncommon weapon id",
          400
        );
      }
    }
    if ($feat_id === 'domain-initiate') {
      $this->validateDomainFeatSelection($char_data, $feat_params, 'domain-initiate');
    }
    if ($feat_id === 'advanced-domain') {
      $selected_domain = $this->validateDomainFeatSelection($char_data, $feat_params, 'advanced-domain');
      if (!in_array($selected_domain, $this->getOwnedDomainInitiateDomains($char_data), TRUE)) {
        throw new \InvalidArgumentException(
          "Feat 'advanced-domain' requires feat_params['selected_domain'] to match a domain already granted by Domain Initiate",
          400
        );
      }
    }
    if ($feat_id === 'advanced-school-spell') {
      $school_id = $this->resolveWizardSchoolId($char_data);
      if ($school_id === NULL || $school_id === 'universalist' || !isset(CharacterManager::ARCANE_SCHOOLS[$school_id])) {
        throw new \InvalidArgumentException(
          "Feat 'advanced-school-spell' requires a persisted specialist wizard school",
          400
        );
      }
    }
    if ($feat_id === 'spell-combination') {
      $school_id = $this->resolveWizardSchoolId($char_data);
      $thesis_id = $this->resolveWizardArcaneThesisId($char_data);
      if ($school_id !== 'universalist' && $thesis_id !== 'spell-blending') {
        throw new \InvalidArgumentException(
          "Feat 'spell-combination' requires a persisted Spell Blending thesis or Universalist school",
          400
        );
      }
    }
    if ($feat_id === 'cantrip-expansion-wizard') {
      $this->validateSelectedCantripsForTradition($feat_params, 'arcane', 'cantrip-expansion-wizard');
    }
    if ($feat_id === 'cantrip-expansion') {
      $this->validateSelectedCantripsForTradition($feat_params, 'occult', 'cantrip-expansion');
    }
    if ($feat_id === 'cantrip-expansion-sorcerer') {
      $tradition = $this->resolveSorcererTradition($char_data);
      if ($tradition === NULL) {
        throw new \InvalidArgumentException(
          "Feat 'cantrip-expansion-sorcerer' requires a persisted sorcerer bloodline to resolve its spell tradition",
          400
        );
      }
      $this->validateSelectedCantripsForTradition($feat_params, $tradition, 'cantrip-expansion-sorcerer');
    }
    if ($feat_id === 'arcane-evolution') {
      if ($this->resolveSorcererTradition($char_data) !== 'arcane') {
        throw new \InvalidArgumentException(
          "Feat 'arcane-evolution' requires a sorcerer with an arcane bloodline",
          400
        );
      }
      $highest_rank = $this->resolveHighestSpellRank($char_data);
      $this->validateSelectedRankedSpellForTradition($feat_params, 'arcane', $highest_rank, 'arcane-evolution');
    }
    if ($feat_id === 'crossblooded-evolution') {
      $current_bloodline = $this->resolveSorcererBloodline($char_data);
      $current_tradition = $this->resolveSorcererTradition($char_data);
      if ($current_bloodline === NULL || $current_tradition === NULL) {
        throw new \InvalidArgumentException(
          "Feat 'crossblooded-evolution' requires a persisted sorcerer bloodline",
          400
        );
      }
      $selected_bloodline = strtolower(trim((string) ($feat_params['selected_bloodline'] ?? '')));
      if ($selected_bloodline === '' || !isset(CharacterManager::SORCERER_BLOODLINES[$selected_bloodline])) {
        throw new \InvalidArgumentException(
          "Feat 'crossblooded-evolution' requires feat_params['selected_bloodline'] to be a valid sorcerer bloodline id",
          400
        );
      }
      if ($selected_bloodline === $current_bloodline) {
        throw new \InvalidArgumentException(
          "Feat 'crossblooded-evolution' requires feat_params['selected_bloodline'] to differ from the character's current bloodline",
          400
        );
      }
      if ((CharacterManager::SORCERER_BLOODLINES[$selected_bloodline]['tradition'] ?? NULL) !== $current_tradition) {
        throw new \InvalidArgumentException(
          "Feat 'crossblooded-evolution' requires the selected bloodline to share the character's bloodline tradition",
          400
        );
      }
      $highest_rank = $this->resolveHighestSpellRank($char_data);
      $this->validateSelectedRankedSpellForTradition($feat_params, $current_tradition, $highest_rank, 'crossblooded-evolution');
    }
    if ($feat_id === 'greater-mental-evolution') {
      $selected_spell = trim((string) ($feat_params['selected_spell'] ?? ''));
      if ($selected_spell === '') {
        throw new \InvalidArgumentException(
          "Feat 'greater-mental-evolution' requires feat_params['selected_spell'] to be a valid mental spell id",
          400
        );
      }
      $spell = $this->loadSpellRegistryEntry($selected_spell);
      if ($spell === NULL) {
        throw new \InvalidArgumentException(
          "Feat 'greater-mental-evolution' spell '{$selected_spell}' is not a known spell id",
          400
        );
      }
      $spell_rank = (int) ($spell['level'] ?? 0);
      if ($spell_rank < 1 || $spell_rank > 6) {
        throw new \InvalidArgumentException(
          "Feat 'greater-mental-evolution' requires a spell of rank 1 through 6",
          400
        );
      }
      $traits = array_map('strtolower', $spell['traits'] ?? []);
      if (!in_array('mental', $traits, TRUE)) {
        throw new \InvalidArgumentException(
          "Feat 'greater-mental-evolution' requires a spell with the Mental trait",
          400
        );
      }
    }
    if ($feat_id === 'studious-capacity') {
      $this->validateSelectedCantripsForTradition($feat_params, 'occult', 'studious-capacity');
      $highest_rank = $this->resolveHighestSpellRank($char_data);
      $selected_spell = trim((string) ($feat_params['selected_spell'] ?? ''));
      if ($selected_spell === '') {
        throw new \InvalidArgumentException(
          "Feat 'studious-capacity' requires feat_params['selected_spell'] for your highest available spell rank",
          400
        );
      }
      $valid_spell_ids = array_map(
        static fn(array $spell): string => (string) ($spell['id'] ?? ''),
        $this->getCharacterManager()->getSpellsByTradition('occult', $highest_rank)
      );
      if (!in_array($selected_spell, $valid_spell_ids, TRUE)) {
        throw new \InvalidArgumentException(
          "Feat 'studious-capacity' spell '{$selected_spell}' is not a valid occult rank {$highest_rank} spell",
          400
        );
      }
    }
    if ($feat_id === 'greater-vital-evolution') {
      $selected_spells = $feat_params['selected_spells'] ?? [];
      if (!is_array($selected_spells) || count($selected_spells) !== 2) {
        throw new \InvalidArgumentException(
          "Feat 'greater-vital-evolution' requires feat_params['selected_spells'] with exactly 2 arcane spell ids",
          400
        );
      }
      $normalized = [];
      foreach ($selected_spells as $spell_id) {
        if (!is_string($spell_id) || trim($spell_id) === '') {
          throw new \InvalidArgumentException(
            "Feat 'greater-vital-evolution' requires non-empty spell ids in feat_params['selected_spells']",
            400
          );
        }
        $normalized[] = trim($spell_id);
      }
      if (count(array_unique($normalized)) !== 2) {
        throw new \InvalidArgumentException(
          "Feat 'greater-vital-evolution' requires two distinct arcane spell ids",
          400
        );
      }
      $valid_spell_ids = [];
      for ($rank = 1; $rank <= 10; $rank++) {
        foreach ($this->getCharacterManager()->getSpellsByTradition('arcane', $rank) as $spell) {
          $valid_spell_ids[] = (string) ($spell['id'] ?? '');
        }
      }
      foreach ($normalized as $spell_id) {
        if (!in_array($spell_id, $valid_spell_ids, TRUE)) {
          throw new \InvalidArgumentException(
            "Feat 'greater-vital-evolution' spell '{$spell_id}' is not a valid arcane spell",
            400
          );
        }
      }
    }
    if ($feat_id === 'spell-mastery') {
      $selected_spells = $feat_params['selected_spells'] ?? [];
      if (!is_array($selected_spells) || count($selected_spells) !== 4) {
        throw new \InvalidArgumentException(
          "Feat 'spell-mastery' requires feat_params['selected_spells'] with exactly 4 arcane spell ids",
          400
        );
      }
      $normalized = [];
      foreach ($selected_spells as $spell_id) {
        if (!is_string($spell_id) || trim($spell_id) === '') {
          throw new \InvalidArgumentException(
            "Feat 'spell-mastery' requires non-empty spell ids in feat_params['selected_spells']",
            400
          );
        }
        $normalized[] = trim($spell_id);
      }
      if (count(array_unique($normalized)) !== 4) {
        throw new \InvalidArgumentException(
          "Feat 'spell-mastery' requires four distinct arcane spell ids",
          400
        );
      }
      $valid_spell_ids = [];
      for ($rank = 1; $rank <= 9; $rank++) {
        foreach ($this->getCharacterManager()->getSpellsByTradition('arcane', $rank) as $spell) {
          $valid_spell_ids[] = (string) ($spell['id'] ?? '');
        }
      }
      foreach ($normalized as $spell_id) {
        if (!in_array($spell_id, $valid_spell_ids, TRUE)) {
          throw new \InvalidArgumentException(
            "Feat 'spell-mastery' spell '{$spell_id}' is not a valid arcane rank-9-or-lower spell",
            400
          );
        }
      }
    }
    if ($feat_id === 'infinite-possibilities') {
      $selected_spells = $feat_params['selected_spells'] ?? [];
      if (!is_array($selected_spells) || count($selected_spells) < 1 || count($selected_spells) > 3) {
        throw new \InvalidArgumentException(
          "Feat 'infinite-possibilities' requires feat_params['selected_spells'] with 1 to 3 spell ids",
          400
        );
      }
      $normalized = [];
      foreach ($selected_spells as $spell_id) {
        if (!is_string($spell_id) || trim($spell_id) === '') {
          throw new \InvalidArgumentException(
            "Feat 'infinite-possibilities' requires non-empty spell ids in feat_params['selected_spells']",
            400
          );
        }
        $normalized[] = trim($spell_id);
      }
      if (count(array_unique($normalized)) !== count($normalized)) {
        throw new \InvalidArgumentException(
          "Feat 'infinite-possibilities' requires distinct spell ids",
          400
        );
      }
      $valid_spell_ids = [];
      foreach (['arcane', 'divine', 'occult', 'primal'] as $tradition) {
        for ($rank = 1; $rank <= 10; $rank++) {
          foreach ($this->getCharacterManager()->getSpellsByTradition($tradition, $rank) as $spell) {
            $valid_spell_ids[] = (string) ($spell['id'] ?? '');
          }
        }
      }
      foreach ($normalized as $spell_id) {
        if (!in_array($spell_id, $valid_spell_ids, TRUE)) {
          throw new \InvalidArgumentException(
            "Feat 'infinite-possibilities' spell '{$spell_id}' is not a valid common spell id",
            400
          );
        }
      }
    }
    if ($feat_id === 'scroll-savant') {
      $selected_spells = $feat_params['selected_spells'] ?? [];
      if (!is_array($selected_spells) || count($selected_spells) !== 2) {
        throw new \InvalidArgumentException(
          "Feat 'scroll-savant' requires feat_params['selected_spells'] with exactly 2 arcane spell ids",
          400
        );
      }
      $normalized = [];
      foreach ($selected_spells as $spell_id) {
        if (!is_string($spell_id) || trim($spell_id) === '') {
          throw new \InvalidArgumentException(
            "Feat 'scroll-savant' requires non-empty spell ids in feat_params['selected_spells']",
            400
          );
        }
        $normalized[] = trim($spell_id);
      }
      if (count(array_unique($normalized)) !== 2) {
        throw new \InvalidArgumentException(
          "Feat 'scroll-savant' requires two distinct arcane spell ids",
          400
        );
      }
      $valid_spell_ids = [];
      for ($rank = 1; $rank <= 10; $rank++) {
        foreach ($this->getCharacterManager()->getSpellsByTradition('arcane', $rank) as $spell) {
          $valid_spell_ids[] = (string) ($spell['id'] ?? '');
        }
      }
      foreach ($normalized as $spell_id) {
        if (!in_array($spell_id, $valid_spell_ids, TRUE)) {
          throw new \InvalidArgumentException(
            "Feat 'scroll-savant' spell '{$spell_id}' is not a valid arcane spell",
            400
          );
        }
      }
    }

    return $feat;
  }

  /**
   * Resolve the canonical ancestry name from persisted character data.
   */
  private function resolveCharacterAncestryName(array $char_data): string {
    $ancestry_value = trim((string) ($char_data['basicInfo']['ancestry'] ?? $char_data['ancestry'] ?? ''));
    return $this->resolveCanonicalAncestryName($ancestry_value);
  }

  /**
   * Build the ancestry feat catalog available to the character.
   */
  private function getAvailableAncestryFeatCatalog(array $char_data): array {
    $ancestry_name = $this->resolveCharacterAncestryName($char_data);
    if ($ancestry_name === '') {
      return [];
    }

    $heritage_id = trim((string) ($char_data['basicInfo']['heritage'] ?? $char_data['heritage'] ?? ''));
    $catalog = CharacterManager::getEligibleAncestryFeats($ancestry_name, $heritage_id);
    $adopted_ancestry = $this->getSelectedAdoptedAncestryName($char_data);
    if ($adopted_ancestry === '') {
      return $catalog;
    }

    $seen = [];
    foreach ($catalog as $feat) {
      $feat_id = (string) ($feat['id'] ?? '');
      if ($feat_id !== '') {
        $seen[$feat_id] = TRUE;
      }
    }

    foreach (CharacterManager::getAncestryFeats($adopted_ancestry) as $feat) {
      $feat_id = (string) ($feat['id'] ?? '');
      if ($feat_id === '' || isset($seen[$feat_id])) {
        continue;
      }
      $seen[$feat_id] = TRUE;
      $catalog[] = $feat;
    }

    return $catalog;
  }

  /**
   * Validate Adopted Ancestry selection and return canonical ancestry name.
   */
  private function validateAdoptedAncestrySelection(array $char_data, array $feat_params): string {
    $selected_ancestry = trim((string) ($feat_params['selected_ancestry'] ?? $feat_params['ancestry'] ?? ''));
    $canonical_ancestry = $this->resolveCanonicalAncestryName($selected_ancestry);
    $current_ancestry = $this->resolveCharacterAncestryName($char_data);
    if ($canonical_ancestry === '' || $canonical_ancestry === $current_ancestry) {
      throw new \InvalidArgumentException(
        "Feat 'adopted-ancestry' requires feat_params['selected_ancestry'] to be a different valid ancestry id",
        400
      );
    }

    return $canonical_ancestry;
  }

  /**
   * Resolve selected Adopted Ancestry from persisted character data.
   */
  private function getSelectedAdoptedAncestryName(array $char_data): string {
    foreach ([
      $char_data['feat_selections']['adopted-ancestry'] ?? NULL,
      $char_data['features']['featSelections']['adopted-ancestry'] ?? NULL,
    ] as $selection) {
      if (!is_array($selection)) {
        continue;
      }
      $canonical_ancestry = $this->resolveCanonicalAncestryName((string) ($selection['selected_ancestry'] ?? $selection['ancestry'] ?? ''));
      if ($canonical_ancestry !== '') {
        return $canonical_ancestry;
      }
    }

    foreach ($char_data['features']['feats'] ?? [] as $feat) {
      if (($feat['id'] ?? '') !== 'adopted-ancestry') {
        continue;
      }
      $canonical_ancestry = $this->resolveCanonicalAncestryName((string) (($feat['feat_params']['selected_ancestry'] ?? $feat['feat_params']['ancestry'] ?? '')));
      if ($canonical_ancestry !== '') {
        return $canonical_ancestry;
      }
    }

    return '';
  }

  /**
   * Resolve a canonical ancestry name from an input id or display name.
   */
  private function resolveCanonicalAncestryName(string $ancestry_value): string {
    $ancestry_value = trim($ancestry_value);
    if ($ancestry_value === '') {
      return '';
    }
    if (isset(CharacterManager::ANCESTRIES[$ancestry_value])) {
      return $ancestry_value;
    }

    $normalized_input = strtolower(str_replace([' ', "'"], ['-', ''], $ancestry_value));
    foreach (array_keys(CharacterManager::ANCESTRIES) as $ancestry_name) {
      $normalized_name = strtolower(str_replace([' ', "'"], ['-', ''], $ancestry_name));
      if ($normalized_name === $normalized_input) {
        return $ancestry_name;
      }
    }

    return '';
  }

  /**
   * Validate a domain-based feat selection against the character's deity.
   */
  private function validateDomainFeatSelection(array $char_data, array $feat_params, string $feat_id): string {
    $selected_domain = trim((string) ($feat_params['selected_domain'] ?? ''));
    $deity_input = trim((string) ($char_data['personality']['deity'] ?? $char_data['basicInfo']['deity'] ?? $char_data['deity'] ?? ''));
    $valid_domains = $deity_input !== '' && $this->deityService !== NULL
      ? $this->deityService->getDomainsForInput($deity_input)
      : [];

    if ($selected_domain === '' || !in_array($selected_domain, $valid_domains, TRUE)) {
      throw new \InvalidArgumentException(
        "Feat '{$feat_id}' requires feat_params['selected_domain'] to be one of the character deity's domains",
        400
      );
    }

    return $selected_domain;
  }

  /**
   * Resolve domains already granted by Domain Initiate across persisted feat data.
   *
   * @return string[]
   *   Canonical selected domain ids.
   */
  private function getOwnedDomainInitiateDomains(array $char_data): array {
    $owned_domains = [];

    foreach ([
      $char_data['feat_selections']['domain-initiate'] ?? NULL,
      $char_data['features']['featSelections']['domain-initiate'] ?? NULL,
    ] as $selection) {
      if (!is_array($selection)) {
        continue;
      }
      $selected_domain = trim((string) ($selection['selected_domain'] ?? $selection['domain'] ?? ''));
      if ($selected_domain !== '') {
        $owned_domains[] = $selected_domain;
      }
    }

    foreach ($char_data['features']['feats'] ?? [] as $feat) {
      if (($feat['id'] ?? '') !== 'domain-initiate') {
        continue;
      }
      $selected_domain = trim((string) (($feat['feat_params']['selected_domain'] ?? $feat['feat_params']['domain'] ?? '')));
      if ($selected_domain !== '') {
        $owned_domains[] = $selected_domain;
      }
    }

    return array_values(array_unique($owned_domains));
  }

  /**
   * Validates fixed cantrip selections for repertoire/spellbook expansion feats.
   */
  private function validateSelectedCantripsForTradition(array $feat_params, string $tradition, string $feat_id): void {
    $selected_cantrips = $feat_params['selected_cantrips'] ?? [];
    if (!is_array($selected_cantrips) || count($selected_cantrips) !== 2) {
      throw new \InvalidArgumentException(
        "Feat '{$feat_id}' requires feat_params['selected_cantrips'] with exactly 2 cantrip ids",
        400
      );
    }

    $normalized = [];
    foreach ($selected_cantrips as $cantrip_id) {
      if (!is_string($cantrip_id) || trim($cantrip_id) === '') {
        throw new \InvalidArgumentException(
          "Feat '{$feat_id}' requires non-empty cantrip ids in feat_params['selected_cantrips']",
          400
        );
      }
      $normalized[] = trim($cantrip_id);
    }
    if (count(array_unique($normalized)) !== 2) {
      throw new \InvalidArgumentException(
        "Feat '{$feat_id}' requires two distinct cantrip ids",
        400
      );
    }

    $valid_cantrip_ids = array_map(
      static fn(array $spell): string => (string) ($spell['id'] ?? ''),
      CharacterManager::SPELLS[$tradition]['cantrips'] ?? []
    );
    foreach ($normalized as $cantrip_id) {
      if (!in_array($cantrip_id, $valid_cantrip_ids, TRUE)) {
        throw new \InvalidArgumentException(
          "Feat '{$feat_id}' cantrip '{$cantrip_id}' is not a valid {$tradition} cantrip",
          400
        );
      }
    }
  }

  /**
   * Resolve sorcerer tradition from persisted bloodline/subclass data.
   */
  private function resolveSorcererTradition(array $char_data): ?string {
    $bloodline = $this->resolveSorcererBloodline($char_data);
    if ($bloodline === '') {
      return NULL;
    }

    return CharacterManager::SORCERER_BLOODLINES[$bloodline]['tradition'] ?? NULL;
  }

  /**
   * Resolve sorcerer bloodline id from persisted data.
   */
  private function resolveSorcererBloodline(array $char_data): ?string {
    $bloodline = strtolower(trim((string) (
      $char_data['subclass']
      ?? $char_data['bloodline']
      ?? $char_data['basicInfo']['subclass']
      ?? $char_data['basicInfo']['bloodline']
      ?? ''
    )));
    return $bloodline !== '' ? $bloodline : NULL;
  }

  /**
   * Resolve wizard arcane school id from persisted data.
   */
  private function resolveWizardSchoolId(array $char_data): ?string {
    $school_id = strtolower(trim((string) (
      $char_data['subclass']
      ?? $char_data['arcane_school']
      ?? $char_data['wizard']['subclass']
      ?? $char_data['wizard']['arcane_school']
      ?? $char_data['basicInfo']['subclass']
      ?? $char_data['basicInfo']['arcane_school']
      ?? ''
    )));
    return $school_id !== '' ? $school_id : NULL;
  }

  /**
   * Resolve wizard arcane thesis id from persisted data.
   */
  private function resolveWizardArcaneThesisId(array $char_data): ?string {
    $thesis_id = strtolower(trim((string) (
      $char_data['arcane_thesis']
      ?? $char_data['wizard']['arcane_thesis']
      ?? $char_data['basicInfo']['arcane_thesis']
      ?? ''
    )));
    return $thesis_id !== '' ? $thesis_id : NULL;
  }

  /**
   * Resolve highest spell rank available to a full caster by level.
   */
  private function resolveHighestSpellRank(array $char_data): int {
    $level = max(1, (int) ($char_data['level'] ?? $char_data['basicInfo']['level'] ?? 1));
    if ($level >= 19) {
      return 10;
    }
    return (int) floor(($level + 1) / 2);
  }

  /**
   * Validate a selected ranked spell against a tradition and max spell rank.
   */
  private function validateSelectedRankedSpellForTradition(array $feat_params, string $tradition, int $highest_rank, string $feat_id): string {
    $selected_spell = trim((string) ($feat_params['selected_spell'] ?? ''));
    if ($selected_spell === '') {
      throw new \InvalidArgumentException(
        "Feat '{$feat_id}' requires feat_params['selected_spell'] to be a valid {$tradition} spell id",
        400
      );
    }

    $valid_spell_ids = [];
    for ($rank = 1; $rank <= $highest_rank; $rank++) {
      foreach ($this->getCharacterManager()->getSpellsByTradition($tradition, $rank) as $spell) {
        $valid_spell_ids[] = (string) ($spell['id'] ?? '');
      }
    }

    if (!in_array($selected_spell, $valid_spell_ids, TRUE)) {
      throw new \InvalidArgumentException(
        "Feat '{$feat_id}' spell '{$selected_spell}' is not a valid {$tradition} spell of rank {$highest_rank} or lower",
        400
      );
    }

    return $selected_spell;
  }

  /**
   * Load a spell registry entry with schema metadata needed for validation.
   *
   * @return array<string,mixed>|null
   *   Normalized spell metadata or NULL when not found.
   */
  private function loadSpellRegistryEntry(string $spell_id): ?array {
    $row = $this->database->select('dungeoncrawler_content_registry', 'r')
      ->fields('r', ['content_id', 'level', 'schema_data'])
      ->condition('content_type', 'spell')
      ->condition('content_id', $spell_id)
      ->execute()
      ->fetchAssoc();

    if (!$row) {
      return NULL;
    }

    $schema = json_decode((string) ($row['schema_data'] ?? ''), TRUE) ?: [];
    return [
      'id' => (string) ($row['content_id'] ?? ''),
      'level' => (int) ($row['level'] ?? 0),
      'traits' => is_array($schema['traits'] ?? NULL) ? $schema['traits'] : [],
      'traditions' => is_array($schema['traditions'] ?? NULL) ? $schema['traditions'] : [],
    ];
  }

  /**
   * Resolve the character manager service on demand.
   */
  private function getCharacterManager(): CharacterManager {
    /** @var \Drupal\dungeoncrawler_content\Service\CharacterManager $character_manager */
    $character_manager = \Drupal::service('dungeoncrawler_content.character_manager');
    return $character_manager;
  }

  /**
   * Returns TRUE if the character has at least one primal innate spell source.
   *
   * Checks: fey-touched heritage, wellspring gnome with primal tradition,
   * first-world-magic feat, otherworldly-magic feat.
   */
  private function characterHasPrimalInnateSpell(array $char_data): bool {
    $heritage = strtolower(trim(
      $char_data['heritage'] ?? ($char_data['basicInfo']['heritage'] ?? '')
    ));

    if (in_array($heritage, ['fey-touched', 'fey_touched'], TRUE)) {
      return TRUE;
    }

    if (in_array($heritage, ['wellspring'], TRUE)) {
      $tradition = strtolower(trim(
        $char_data['wellspring_tradition'] ?? ($char_data['basicInfo']['wellspring_tradition'] ?? '')
      ));
      if ($tradition === 'primal') {
        return TRUE;
      }
    }

    $primal_innate_feats = ['first-world-magic', 'otherworldly-magic'];
    $owned_ids = array_column($char_data['features']['feats'] ?? [], 'id');
    foreach ($primal_innate_feats as $primal_feat_id) {
      if (in_array($primal_feat_id, $owned_ids, TRUE)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Returns TRUE if the character has Gnome Weapon Familiarity.
   */
  private function characterHasGnomeWeaponFamiliarity(array $char_data): bool {
    $owned_ids = array_column($char_data['features']['feats'] ?? [], 'id');
    return in_array('gnome-weapon-familiarity', $owned_ids, TRUE);
  }

  /**
   * Returns TRUE if the character has Goblin Weapon Familiarity.
   */
  private function characterHasGoblinWeaponFamiliarity(array $char_data): bool {
    $owned_ids = array_column($char_data['features']['feats'] ?? [], 'id');
    return in_array('goblin-weapon-familiarity', $owned_ids, TRUE);
  }

}
