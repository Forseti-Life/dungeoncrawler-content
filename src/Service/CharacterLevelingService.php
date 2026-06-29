<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Handles XP-gated PF2e character leveling with auditable draft/apply records.
 */
class CharacterLevelingService {

  const MAX_LEVEL = 20;

  /** Proficiency rank order for skill increase validation. */
  const RANK_ORDER = ['untrained', 'trained', 'expert', 'master', 'legendary'];

  /** Valid ability score names. */
  const ABILITIES = ['strength', 'dexterity', 'constitution', 'intelligence', 'wisdom', 'charisma'];

  protected readonly CharacterProgressionRegistry $progressionRegistry;
  protected readonly FeatLibraryService $featLibrary;

  public function __construct(
    protected readonly Connection $database,
    protected readonly MulticlassArchetypeService $multiclassArchetypeService = new MulticlassArchetypeService(),
    protected readonly ?DeityService $deityService = NULL,
    ?CharacterProgressionRegistry $progression_registry = NULL,
    ?FeatLibraryService $feat_library = NULL,
  ) {
    $this->progressionRegistry = $progression_registry ?? new CharacterProgressionRegistry();
    $this->featLibrary = $feat_library ?? new FeatLibraryService($database);
  }

  // ── Public API ─────────────────────────────────────────────────────────────

  /**
   * Get the level-up status for a character.
   */
  public function getStatus(string $character_id): array {
    $record = $this->loadRecord($character_id);
    $char_data = $this->decodeCharacterData($record);
    $advancement = $this->loadLatestAdvancement((int) $record->id);
    $this->syncProgressionSummary($char_data, $advancement);

    return $this->buildStatusResponse($char_data, $character_id, $advancement);
  }

  /**
   * Preserve the legacy milestone flag for GM tooling, but do not gate on it.
   */
  public function setMilestone(string $character_id, bool $ready): array {
    $record = $this->loadRecord($character_id);
    $char_data = $this->decodeCharacterData($record);
    $char_data['levelUpState']['milestoneReady'] = $ready;
    $this->persistCharacterData($character_id, $char_data);
    return $this->getStatus($character_id);
  }

  /**
   * Create or reopen a draft advancement plan for the next XP-earned level.
   */
  public function triggerLevelUp(string $character_id, bool $admin_force = FALSE): array {
    $record = $this->loadRecord($character_id);
    $char_data = $this->decodeCharacterData($record);
    $current_level = (int) ($char_data['basicInfo']['level'] ?? $record->level ?? 1);

    if ($current_level >= self::MAX_LEVEL) {
      throw new \InvalidArgumentException('Already at maximum level', 400);
    }

    $xp_status = $this->buildXpStatus($current_level, (int) ($char_data['basicInfo']['experiencePoints'] ?? 0));
    if (!$admin_force && !$xp_status['levelUpAvailable']) {
      throw new \InvalidArgumentException('Character has not reached the XP threshold for the next level', 403);
    }

    $target_level = $current_level + 1;
    $active_advancement = $this->loadActiveAdvancement((int) $record->id, $target_level);
    if ($active_advancement !== NULL) {
      $this->syncProgressionSummary($char_data, $active_advancement);
      return $this->buildStatusResponse($char_data, $character_id, $active_advancement);
    }

    $class_name = strtolower((string) ($char_data['basicInfo']['class'] ?? $record->class ?? 'fighter'));
    $plan = $this->progressionRegistry->buildLevelPlan($class_name, $target_level, $char_data);
    $pending_choices = $plan['choice_slots'] ?? [];
    $auto_applied = array_values(array_filter(array_map(
      static fn(array $feature): string => (string) ($feature['name'] ?? ''),
      $plan['auto_grants'] ?? []
    )));

    $char_data['levelUpState'] = [
      'milestoneReady' => (bool) ($char_data['levelUpState']['milestoneReady'] ?? FALSE),
      'inProgress' => !empty($pending_choices),
      'transitionTo' => $target_level,
      'pendingChoices' => $pending_choices,
      'completedChoices' => is_array($char_data['levelUpState']['completedChoices'] ?? NULL)
        ? $char_data['levelUpState']['completedChoices']
        : [],
      'autoApplied' => $auto_applied,
      'hpGranted' => (int) ($plan['hp_bonus'] ?? 0),
      'draftPlan' => $plan,
    ];

    $advancement = $this->createAdvancementDraft($record, $char_data, $plan, empty($pending_choices) ? 'ready' : 'draft');
    $this->syncProgressionSummary($char_data, $advancement);

    if (empty($pending_choices)) {
      $this->finalizeLevelUp($record, $char_data, $advancement);
      $advancement = $this->loadLatestAdvancement((int) $record->id);
    }
    else {
      // Keep the hot level column at the last applied level while a draft is in
      // progress; transitionTo carries the pending target level for the shell.
      $this->persistCharacterData($character_id, $char_data);
    }

    return $this->buildStatusResponse($char_data, $character_id, $advancement);
  }

  /**
   * Submit ability boost selections into the active draft plan.
   */
  public function submitAbilityBoosts(string $character_id, array $abilities): array {
    $record = $this->loadRecord($character_id);
    $char_data = $this->decodeCharacterData($record);
    $advancement = $this->requireActiveAdvancement($record, $char_data);

    $slot_idx = $this->findPendingSlot($char_data['levelUpState']['pendingChoices'] ?? [], 'ability_boosts');
    if ($slot_idx === -1) {
      throw new \InvalidArgumentException('No ability boost choice pending at this level', 400);
    }

    $required = (int) ($char_data['levelUpState']['pendingChoices'][$slot_idx]['count'] ?? 4);
    if (count($abilities) !== $required) {
      throw new \InvalidArgumentException("Exactly {$required} ability boost(s) required; received " . count($abilities), 400);
    }

    $normalized = array_map(static fn($ability): string => strtolower(trim((string) $ability)), $abilities);
    if (count(array_unique($normalized)) !== $required) {
      throw new \InvalidArgumentException('Each ability may only be boosted once per level-up', 400);
    }
    foreach ($normalized as $ability) {
      if (!in_array($ability, self::ABILITIES, TRUE)) {
        throw new \InvalidArgumentException("Unknown ability '{$ability}'", 400);
      }
    }

    $char_data['levelUpState']['pendingChoices'][$slot_idx]['resolved'] = TRUE;
    $char_data['levelUpState']['pendingChoices'][$slot_idx]['choices'] = $normalized;
    $advancement['plan']['choice_slots'] = $char_data['levelUpState']['pendingChoices'];
    $this->updateAdvancementPlan((int) $advancement['id'], $advancement['plan'], $this->resolvePlanStatus($char_data));

    return $this->persistOrFinalize($record, $char_data, (int) $advancement['id']);
  }

  /**
   * Submit a skill increase choice into the active draft plan.
   */
  public function submitSkillIncrease(string $character_id, string $skill): array {
    $record = $this->loadRecord($character_id);
    $char_data = $this->decodeCharacterData($record);
    $advancement = $this->requireActiveAdvancement($record, $char_data);

    $slot_idx = $this->findPendingSlot($char_data['levelUpState']['pendingChoices'] ?? [], 'skill_increase');
    if ($slot_idx === -1) {
      throw new \InvalidArgumentException('No skill increase pending at this level', 400);
    }

    $choice = $this->resolveSkillIncreaseChoice($char_data, $skill);

    $char_data['levelUpState']['pendingChoices'][$slot_idx]['resolved'] = TRUE;
    $char_data['levelUpState']['pendingChoices'][$slot_idx]['choice'] = $choice;
    $advancement['plan']['choice_slots'] = $char_data['levelUpState']['pendingChoices'];
    $this->updateAdvancementPlan((int) $advancement['id'], $advancement['plan'], $this->resolvePlanStatus($char_data));

    return $this->persistOrFinalize($record, $char_data, (int) $advancement['id']);
  }

  /**
   * Submit a feat selection into the active draft plan.
   */
  public function submitFeat(string $character_id, string $slot_type, string $feat_id, array $feat_params = []): array {
    $record = $this->loadRecord($character_id);
    $char_data = $this->decodeCharacterData($record);
    $advancement = $this->requireActiveAdvancement($record, $char_data);

    $slot_idx = $this->findPendingFeatSlot($char_data['levelUpState']['pendingChoices'] ?? [], $slot_type);
    if ($slot_idx === -1) {
      throw new \InvalidArgumentException("No open {$slot_type} feat slot pending at this level", 400);
    }

    $level = (int) ($char_data['levelUpState']['transitionTo'] ?? $char_data['basicInfo']['level'] ?? 1);
    $class_name = strtolower((string) ($char_data['basicInfo']['class'] ?? 'fighter'));
    $feat = $this->validateFeat($feat_id, $slot_type, $class_name, $level, $char_data, $feat_params);

    if ($slot_type === 'class_feat' && !empty($feat['traits']) && in_array('dedication', array_map('strtolower', $feat['traits']), TRUE)) {
      $this->multiclassArchetypeService->validateDedicationSelection($feat_id, $char_data);
    }

    $char_data['levelUpState']['pendingChoices'][$slot_idx]['resolved'] = TRUE;
    $char_data['levelUpState']['pendingChoices'][$slot_idx]['choice'] = [
      'feat_id' => $feat_id,
      'feat_name' => $feat['name'] ?? $feat_id,
      'slot_type' => $slot_type,
      'feat_params' => $feat_params,
    ];
    $advancement['plan']['choice_slots'] = $char_data['levelUpState']['pendingChoices'];
    $this->updateAdvancementPlan((int) $advancement['id'], $advancement['plan'], $this->resolvePlanStatus($char_data));

    return $this->persistOrFinalize($record, $char_data, (int) $advancement['id']);
  }

  /**
   * Admin: bypass the XP gate for one draft/apply.
   */
  public function adminForceLevelUp(string $character_id): array {
    return $this->triggerLevelUp($character_id, TRUE);
  }

  /**
   * Admin: cancel a draft or revert the last applied advancement.
   */
  public function adminResetLevelUp(string $character_id): array {
    $record = $this->loadRecord($character_id);
    $char_data = $this->decodeCharacterData($record);
    $active_advancement = $this->loadActiveAdvancement((int) $record->id);

    if ($active_advancement !== NULL) {
      $this->cancelAdvancement((int) $active_advancement['id']);
      $char_data['levelUpState'] = [
        'milestoneReady' => FALSE,
        'inProgress' => FALSE,
        'transitionTo' => 0,
        'pendingChoices' => [],
        'completedChoices' => is_array($char_data['levelUpState']['completedChoices'] ?? NULL)
          ? $char_data['levelUpState']['completedChoices']
          : [],
      ];
      $char_data['progression']['pendingAdvancementId'] = NULL;
      $this->persistCharacterData($character_id, $char_data);
      return [
        'success' => TRUE,
        'message' => 'Active level-up draft cancelled',
        'currentLevel' => (int) ($char_data['basicInfo']['level'] ?? 1),
      ];
    }

    $applied_advancement = $this->loadLatestAppliedAdvancement((int) $record->id);
    if ($applied_advancement === NULL || empty($applied_advancement['applied'])) {
      throw new \InvalidArgumentException('No level-up to reset', 400);
    }

    $summary = $applied_advancement['applied'];
    $previous_level = (int) ($summary['previous_level'] ?? 1);
    $target_level = (int) ($summary['target_level'] ?? ($previous_level + 1));

    $char_data['basicInfo']['level'] = $previous_level;
    $char_data['level'] = $previous_level;
    $this->applyAbilityAdjustments($char_data, (array) ($summary['ability_adjustments'] ?? []), TRUE);
    $this->revertSkillChanges($char_data, (array) ($summary['skill_changes'] ?? []));
    $this->revertGrantedFeatures($char_data, (array) ($summary['auto_grant_ids'] ?? []), (array) ($summary['feat_ids'] ?? []));
    $this->revertHitPointGrant($char_data, (int) ($summary['hp_granted'] ?? 0));
    $this->revertSpellcastingChanges($char_data, (array) ($summary['spellcasting_before'] ?? []), (string) ($char_data['basicInfo']['class'] ?? ''));
    $this->trimProgressionHistory($char_data, (int) $applied_advancement['id']);
    $char_data['levelUpState'] = [
      'milestoneReady' => FALSE,
      'inProgress' => FALSE,
      'transitionTo' => 0,
      'pendingChoices' => [],
      'completedChoices' => is_array($char_data['levelUpState']['completedChoices'] ?? NULL)
        ? $char_data['levelUpState']['completedChoices']
        : [],
    ];
    $char_data['progression']['pendingAdvancementId'] = NULL;

    $this->database->update('dc_campaign_characters')
      ->fields(['level' => $previous_level])
      ->condition('id', $character_id)
      ->condition('campaign_id', 0)
      ->execute();

    $this->markAdvancementCancelled((int) $applied_advancement['id']);
    $this->persistCharacterData($character_id, $char_data);

    return [
      'success' => TRUE,
      'message' => "Level reset from {$target_level} to {$previous_level}",
      'currentLevel' => $previous_level,
    ];
  }

  /**
   * Get feats eligible for a given character and slot type.
   */
  public function getEligibleFeats(string $character_id, string $slot_type): array {
    $record = $this->loadRecord($character_id);
    $char_data = $this->decodeCharacterData($record);
    $level = (int) ($char_data['levelUpState']['transitionTo'] ?? $char_data['basicInfo']['level'] ?? 1);
    $class_name = strtolower((string) ($char_data['basicInfo']['class'] ?? 'fighter'));
    $owned_ids = $this->getTakenFeatIds($char_data);
    $gm_unlocked = is_array($char_data['gm_unlocked_feats'] ?? NULL) ? $char_data['gm_unlocked_feats'] : [];

    $catalog = $this->getSlotFeatCatalog($slot_type, $class_name, $char_data);

    if ($slot_type === 'class_feat') {
      $catalog = array_merge($catalog, $this->multiclassArchetypeService->getEligibleArchetypeFeats($char_data));
    }

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
      $is_uncommon = !empty($feat['uncommon']) || (($feat['rarity'] ?? 'common') === 'uncommon');
      if ($is_uncommon && !in_array($feat['id'] ?? '', $gm_unlocked, TRUE)) {
        return FALSE;
      }
      if (!empty($feat['requires_domain']) && $deityService !== NULL && !in_array($feat['requires_domain'], $deity_domains, TRUE)) {
        return FALSE;
      }
      return TRUE;
    }));
  }

  // ── Private helpers ─────────────────────────────────────────────────────────

  /**
   * Load the canonical/library character record.
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
   * Decode character_data and normalize the legacy mirrors we mutate directly.
   */
  private function decodeCharacterData(object $record): array {
    $char_data = json_decode((string) ($record->character_data ?? '{}'), TRUE) ?? [];
    $char_data['basicInfo'] = is_array($char_data['basicInfo'] ?? NULL) ? $char_data['basicInfo'] : [];
    $char_data['basicInfo']['level'] = (int) ($char_data['basicInfo']['level'] ?? $record->level ?? 1);
    $char_data['basicInfo']['experiencePoints'] = (int) ($char_data['basicInfo']['experiencePoints'] ?? $record->experience_points ?? 0);
    $char_data['basicInfo']['class'] = (string) ($char_data['basicInfo']['class'] ?? $record->class ?? '');
    $char_data['features'] = is_array($char_data['features'] ?? NULL) ? $char_data['features'] : [];
    $char_data['features']['classFeatures'] = is_array($char_data['features']['classFeatures'] ?? NULL) ? $char_data['features']['classFeatures'] : [];
    $char_data['features']['feats'] = is_array($char_data['features']['feats'] ?? NULL) ? $char_data['features']['feats'] : [];
    $char_data['levelUpState'] = is_array($char_data['levelUpState'] ?? NULL) ? $char_data['levelUpState'] : [];
    $char_data['progression'] = is_array($char_data['progression'] ?? NULL) ? $char_data['progression'] : [];
    $char_data['resources'] = is_array($char_data['resources'] ?? NULL) ? $char_data['resources'] : [];
    $char_data['abilities'] = is_array($char_data['abilities'] ?? NULL) ? $char_data['abilities'] : [];
    return $char_data;
  }

  /**
   * Persist character_data back to the canonical record.
   */
  private function persistCharacterData(string $character_id, array $char_data): void {
    $this->syncLegacyMirrors($char_data);
    $now = time();
    $this->database->update('dc_campaign_characters')
      ->fields([
        'character_data' => json_encode($char_data),
        'experience_points' => (int) ($char_data['basicInfo']['experiencePoints'] ?? 0),
        'changed' => $now,
      ])
      ->condition('id', $character_id)
      ->condition('campaign_id', 0)
      ->execute();

    $this->syncRuntimeRowsFromCanonical((int) $character_id, $char_data, $now);
  }

  /**
   * Ensure legacy mirrors remain consistent for existing sheet readers.
   */
  private function syncLegacyMirrors(array &$char_data): void {
    $char_data['level'] = (int) ($char_data['basicInfo']['level'] ?? $char_data['level'] ?? 1);
    $char_data['experiencePoints'] = (int) ($char_data['basicInfo']['experiencePoints'] ?? $char_data['experiencePoints'] ?? 0);
    $char_data = CharacterManager::normalizePersistentCharacterPayload($char_data);
  }

  /**
   * Propagate canonical progression changes onto campaign runtime rows.
   */
  private function syncRuntimeRowsFromCanonical(int $character_id, array $char_data, int $now): void {
    if ($character_id <= 0) {
      return;
    }

    foreach ($this->loadRuntimeRows($character_id) as $runtime_row) {
      $runtime_state = json_decode((string) ($runtime_row['state_data'] ?? ''), TRUE);
      $runtime_state = is_array($runtime_state) ? $runtime_state : [];

      $runtime_state['basicInfo'] = array_replace(
        is_array($runtime_state['basicInfo'] ?? NULL) ? $runtime_state['basicInfo'] : [],
        array_filter([
          'name' => (string) ($char_data['basicInfo']['name'] ?? ''),
          'level' => (int) ($char_data['basicInfo']['level'] ?? 1),
          'experiencePoints' => (int) ($char_data['basicInfo']['experiencePoints'] ?? 0),
          'ancestry' => (string) ($char_data['basicInfo']['ancestry'] ?? ''),
          'heritage' => (string) ($char_data['basicInfo']['heritage'] ?? ''),
          'background' => (string) ($char_data['basicInfo']['background'] ?? ''),
          'class' => (string) ($char_data['basicInfo']['class'] ?? ''),
          'alignment' => (string) ($char_data['basicInfo']['alignment'] ?? ''),
          'deity' => $char_data['basicInfo']['deity'] ?? NULL,
          'age' => $char_data['basicInfo']['age'] ?? NULL,
          'appearance' => $char_data['basicInfo']['appearance'] ?? NULL,
          'personality' => $char_data['basicInfo']['personality'] ?? NULL,
          'backstory' => $char_data['basicInfo']['backstory'] ?? NULL,
        ], static fn($value) => $value !== NULL)
      );

      if (isset($char_data['abilities']) && is_array($char_data['abilities'])) {
        $runtime_state['abilities'] = $char_data['abilities'];
      }
      if (isset($char_data['skills']) && is_array($char_data['skills'])) {
        $runtime_state['skills'] = $char_data['skills'];
      }
      if (isset($char_data['features']) && is_array($char_data['features'])) {
        $runtime_state['features'] = $char_data['features'];
      }
      if (isset($char_data['spells']) && is_array($char_data['spells'])) {
        $runtime_state['spells'] = $char_data['spells'];
      }

      $runtime_state['progression'] = is_array($char_data['progression'] ?? NULL) ? $char_data['progression'] : [];
      $runtime_state['levelUpState'] = is_array($char_data['levelUpState'] ?? NULL) ? $char_data['levelUpState'] : [];
      $runtime_state['resources'] = $this->mergeRuntimeResources($char_data, $runtime_state, $runtime_row);
      $runtime_state['level'] = (int) ($char_data['level'] ?? $char_data['basicInfo']['level'] ?? 1);
      $runtime_state['experiencePoints'] = (int) ($char_data['experiencePoints'] ?? $char_data['basicInfo']['experiencePoints'] ?? 0);

      $this->syncLegacyMirrors($runtime_state);

      $runtime_hp = is_array($runtime_state['resources']['hitPoints'] ?? NULL) ? $runtime_state['resources']['hitPoints'] : [];
      $this->database->update('dc_campaign_characters')
        ->fields([
          'state_data' => json_encode($runtime_state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
          'level' => (int) ($runtime_state['basicInfo']['level'] ?? $runtime_state['level'] ?? 1),
          'hp_current' => (int) ($runtime_hp['current'] ?? ($runtime_row['hp_current'] ?? 0)),
          'hp_max' => (int) ($runtime_hp['max'] ?? ($runtime_row['hp_max'] ?? 0)),
          'experience_points' => (int) ($runtime_state['basicInfo']['experiencePoints'] ?? $runtime_state['experiencePoints'] ?? 0),
          'updated' => $now,
          'changed' => $now,
        ])
        ->condition('id', (int) ($runtime_row['id'] ?? 0))
        ->condition('campaign_id', (int) ($runtime_row['campaign_id'] ?? 0))
        ->execute();
    }
  }

  /**
   * Load active campaign runtime rows for a canonical character.
   *
   * @return array<int, array<string, mixed>>
   */
  private function loadRuntimeRows(int $character_id): array {
    return $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['id', 'campaign_id', 'instance_id', 'state_data', 'hp_current', 'hp_max', 'experience_points'])
      ->condition('character_id', $character_id)
      ->condition('campaign_id', 0, '>')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
  }

  /**
   * Merge canonical resources into runtime state while preserving runtime deficits.
   */
  private function mergeRuntimeResources(array $canonical_data, array $runtime_state, array $runtime_row): array {
    $canonical_resources = is_array($canonical_data['resources'] ?? NULL) ? $canonical_data['resources'] : [];
    $runtime_resources = is_array($runtime_state['resources'] ?? NULL) ? $runtime_state['resources'] : [];
    $merged = array_replace_recursive($runtime_resources, $canonical_resources);

    $canonical_hit_points = is_array($canonical_resources['hitPoints'] ?? NULL) ? $canonical_resources['hitPoints'] : [];
    if ($canonical_hit_points !== []) {
      $old_max = (int) (($runtime_resources['hitPoints']['max'] ?? NULL) ?? ($runtime_row['hp_max'] ?? 0));
      $old_current = (int) (($runtime_resources['hitPoints']['current'] ?? NULL) ?? ($runtime_row['hp_current'] ?? 0));
      $new_max = (int) ($canonical_hit_points['max'] ?? $old_max);
      $merged['hitPoints'] = array_replace($canonical_hit_points, [
        'max' => $new_max,
        'current' => $this->preserveRuntimeDeficit($old_current, $old_max, $new_max),
        'temporary' => (int) ($runtime_resources['hitPoints']['temporary'] ?? $canonical_hit_points['temporary'] ?? 0),
      ]);
    }

    $merged['spellSlots'] = $this->preserveResourceDeficits(
      is_array($canonical_resources['spellSlots'] ?? NULL) ? $canonical_resources['spellSlots'] : [],
      is_array($runtime_resources['spellSlots'] ?? NULL) ? $runtime_resources['spellSlots'] : []
    );

    if (!empty($canonical_resources['focusPoints']) && is_array($canonical_resources['focusPoints'])) {
      $focus_max = (int) ($canonical_resources['focusPoints']['max'] ?? 0);
      $old_focus_max = (int) ($runtime_resources['focusPoints']['max'] ?? $focus_max);
      $old_focus_current = (int) ($runtime_resources['focusPoints']['current'] ?? $focus_max);
      $merged['focusPoints'] = array_replace($canonical_resources['focusPoints'], [
        'max' => $focus_max,
        'current' => $this->preserveRuntimeDeficit($old_focus_current, $old_focus_max, $focus_max),
      ]);
    }

    return $merged;
  }

  /**
   * Preserve spent-resource deficit when the canonical max changes.
   */
  private function preserveRuntimeDeficit(int $old_current, int $old_max, int $new_max): int {
    $deficit = max(0, $old_max - $old_current);
    return max(0, min($new_max, $new_max - $deficit));
  }

  /**
   * Preserve slot deficits across spell-slot max changes.
   *
   * @param array<string, array<string, int>> $canonical_slots
   * @param array<string, array<string, int>> $runtime_slots
   *
   * @return array<string, array<string, int>>
   */
  private function preserveResourceDeficits(array $canonical_slots, array $runtime_slots): array {
    if ($canonical_slots === []) {
      return $runtime_slots;
    }

    $merged = [];
    foreach ($canonical_slots as $slot_key => $slot_state) {
      if (!is_array($slot_state)) {
        continue;
      }
      $new_max = (int) ($slot_state['max'] ?? $slot_state['current'] ?? 0);
      $old_state = is_array($runtime_slots[$slot_key] ?? NULL) ? $runtime_slots[$slot_key] : [];
      $old_max = (int) ($old_state['max'] ?? $new_max);
      $old_current = (int) ($old_state['current'] ?? $old_max);
      $merged[$slot_key] = array_replace($slot_state, [
        'max' => $new_max,
        'current' => $this->preserveRuntimeDeficit($old_current, $old_max, $new_max),
      ]);
    }

    return $merged;
  }

  /**
   * Resolve XP-driven level-up availability using the same math as CharacterStateService.
   */
  private function buildXpStatus(int $level, int $xp): array {
    $xp_to_next_level = max(0, (1000 * $level) - $xp);
    return [
      'experiencePoints' => $xp,
      'levelUpAvailable' => $xp >= (1000 * $level),
      'xpToNextLevel' => $xp_to_next_level,
    ];
  }

  /**
   * Require an active draft advancement for the character.
   */
  private function requireActiveAdvancement(object $record, array &$char_data): array {
    $advancement = $this->loadActiveAdvancement((int) $record->id);
    if ($advancement === NULL || empty($char_data['levelUpState']['transitionTo'])) {
      throw new \InvalidArgumentException('No level-up in progress', 400);
    }
    $this->syncProgressionSummary($char_data, $advancement);
    return $advancement;
  }

  /**
   * Return the current plan status from pending choices.
   */
  private function resolvePlanStatus(array $char_data): string {
    $pending = $char_data['levelUpState']['pendingChoices'] ?? [];
    if ($pending === []) {
      return 'ready';
    }

    foreach ($pending as $slot) {
      if (empty($slot['resolved'])) {
        return 'draft';
      }
    }

    return 'ready';
  }

  /**
   * Persist the draft or finalize if every required choice is resolved.
   */
  private function persistOrFinalize(object $record, array &$char_data, int $advancement_id): array {
    $latest = $this->loadAdvancementById($advancement_id);
    if ($this->resolvePlanStatus($char_data) === 'ready') {
      $this->finalizeLevelUp($record, $char_data, $latest);
      $latest = $this->loadAdvancementById($advancement_id);
    }
    else {
      $this->persistCharacterData((string) $record->id, $char_data);
    }

    return $this->buildStatusResponse($char_data, (string) $record->id, $latest);
  }

  /**
   * Finalize a ready draft and mutate the character sheet exactly once.
   */
  private function finalizeLevelUp(object $record, array &$char_data, array $advancement): void {
    $plan = is_array($advancement['plan'] ?? NULL) ? $advancement['plan'] : (is_array($char_data['levelUpState']['draftPlan'] ?? NULL) ? $char_data['levelUpState']['draftPlan'] : []);
    $target_level = (int) ($plan['target_level'] ?? $char_data['levelUpState']['transitionTo'] ?? 0);
    if ($target_level < 2) {
      throw new \InvalidArgumentException('Cannot finalize an invalid advancement plan', 400);
    }

    $previous_level = (int) ($char_data['basicInfo']['level'] ?? 1);
    $ability_adjustments = $this->applyResolvedAbilityBoosts($char_data, $char_data['levelUpState']['pendingChoices'] ?? []);
    $skill_changes = $this->applyResolvedSkillIncreases($char_data, $char_data['levelUpState']['pendingChoices'] ?? [], $target_level);
    $feat_ids = $this->applyResolvedFeatChoices($char_data, $char_data['levelUpState']['pendingChoices'] ?? [], $target_level);
    $auto_grant_ids = $this->applyAutoGrants($char_data, (array) ($plan['auto_grants'] ?? []));
    $hp_granted = $this->applyHitPointGrant($char_data, (int) ($plan['hp_bonus'] ?? 0));
    $spellcasting_before = $this->applySpellcastingDeltas($char_data, (array) ($plan['spellcasting_deltas'] ?? []), (string) ($char_data['basicInfo']['class'] ?? ''));

    $char_data['basicInfo']['level'] = $target_level;
    $char_data['level'] = $target_level;
    $char_data['levelUpState']['inProgress'] = FALSE;
    $char_data['levelUpState']['completedChoices'][] = [
      'level' => $target_level,
      'choices' => $char_data['levelUpState']['pendingChoices'] ?? [],
      'advancementId' => (int) $advancement['id'],
    ];
    $char_data['levelUpState']['pendingChoices'] = [];
    unset($char_data['levelUpState']['draftPlan']);
    $char_data['progression']['pendingAdvancementId'] = NULL;
    $char_data['progression']['history'] = is_array($char_data['progression']['history'] ?? NULL) ? $char_data['progression']['history'] : [];
    $char_data['progression']['history'][] = [
      'advancementId' => (int) $advancement['id'],
      'level' => $target_level,
      'appliedAt' => time(),
      'autoApplied' => $char_data['levelUpState']['autoApplied'] ?? [],
    ];

    $applied_summary = [
      'previous_level' => $previous_level,
      'target_level' => $target_level,
      'hp_granted' => $hp_granted,
      'ability_adjustments' => $ability_adjustments,
      'skill_changes' => $skill_changes,
      'feat_ids' => $feat_ids,
      'auto_grant_ids' => $auto_grant_ids,
      'spellcasting_before' => $spellcasting_before,
    ];

    $this->database->update('dc_campaign_characters')
      ->fields(['level' => $target_level])
      ->condition('id', $record->id)
      ->condition('campaign_id', 0)
      ->execute();

    $this->completeAdvancement((int) $advancement['id'], $plan, $applied_summary);
    $this->persistCharacterData((string) $record->id, $char_data);
  }

  /**
   * Apply auto-granted class features and return the ids actually added.
   */
  private function applyAutoGrants(array &$char_data, array $auto_grants): array {
    $char_data['features']['classFeatures'] = is_array($char_data['features']['classFeatures'] ?? NULL) ? $char_data['features']['classFeatures'] : [];
    $existing_ids = array_column($char_data['features']['classFeatures'], 'id');
    $added = [];
    foreach ($auto_grants as $feature) {
      $feature_id = (string) ($feature['id'] ?? '');
      if ($feature_id === '' || in_array($feature_id, $existing_ids, TRUE)) {
        continue;
      }
      $char_data['features']['classFeatures'][] = $feature;
      $existing_ids[] = $feature_id;
      $added[] = $feature_id;
    }
    return $added;
  }

  /**
   * Apply the HP gain for the level and return the actual bonus granted.
   */
  private function applyHitPointGrant(array &$char_data, int $hp_bonus): int {
    if ($hp_bonus <= 0) {
      return 0;
    }
    $char_data['resources']['hitPoints'] = is_array($char_data['resources']['hitPoints'] ?? NULL)
      ? $char_data['resources']['hitPoints']
      : ['current' => 0, 'max' => 0];
    $new_max = (int) ($char_data['resources']['hitPoints']['max'] ?? 0) + $hp_bonus;
    $char_data['resources']['hitPoints']['max'] = $new_max;
    $char_data['resources']['hitPoints']['current'] = min(
      (int) ($char_data['resources']['hitPoints']['current'] ?? 0) + $hp_bonus,
      $new_max
    );
    return $hp_bonus;
  }

  /**
   * Revert a previously granted HP increase.
   */
  private function revertHitPointGrant(array &$char_data, int $hp_bonus): void {
    if ($hp_bonus <= 0) {
      return;
    }
    $char_data['resources']['hitPoints'] = is_array($char_data['resources']['hitPoints'] ?? NULL)
      ? $char_data['resources']['hitPoints']
      : ['current' => 0, 'max' => 0];
    $char_data['resources']['hitPoints']['max'] = max(0, (int) ($char_data['resources']['hitPoints']['max'] ?? 0) - $hp_bonus);
    $char_data['resources']['hitPoints']['current'] = min(
      (int) ($char_data['resources']['hitPoints']['current'] ?? 0),
      (int) ($char_data['resources']['hitPoints']['max'] ?? 0)
    );
  }

  /**
   * Apply resolved ability boosts and return the actual deltas applied.
   */
  private function applyResolvedAbilityBoosts(array &$char_data, array $pending_choices): array {
    $adjustments = [];
    foreach ($pending_choices as $slot) {
      if (($slot['type'] ?? '') !== 'ability_boosts' || empty($slot['resolved'])) {
        continue;
      }
      foreach ((array) ($slot['choices'] ?? []) as $ability) {
        $ability = strtolower((string) $ability);
        if (!in_array($ability, self::ABILITIES, TRUE)) {
          continue;
        }
        $current = (int) ($char_data['abilities'][$ability] ?? 10);
        $delta = $current >= 18 ? 1 : 2;
        $char_data['abilities'][$ability] = $current + $delta;
        $adjustments[$ability] = ($adjustments[$ability] ?? 0) + $delta;
      }
    }
    return $adjustments;
  }

  /**
   * Apply or revert recorded ability score deltas.
   */
  private function applyAbilityAdjustments(array &$char_data, array $adjustments, bool $reverse = FALSE): void {
    foreach ($adjustments as $ability => $delta) {
      $ability = strtolower((string) $ability);
      if (!in_array($ability, self::ABILITIES, TRUE)) {
        continue;
      }
      $current = (int) ($char_data['abilities'][$ability] ?? 10);
      $char_data['abilities'][$ability] = $current + ($reverse ? -((int) $delta) : (int) $delta);
    }
  }

  /**
   * Apply resolved skill increases and return the before/after transitions.
   */
  private function applyResolvedSkillIncreases(array &$char_data, array $pending_choices, int $target_level): array {
    $changes = [];
    foreach ($pending_choices as $slot) {
      if (($slot['type'] ?? '') !== 'skill_increase' || empty($slot['resolved'])) {
        continue;
      }
      $choice = is_array($slot['choice'] ?? NULL) ? $slot['choice'] : [];
      $skill = strtolower((string) ($choice['skill'] ?? ''));
      if ($skill === '') {
        continue;
      }
      $previous_rank = $this->getSkillRank($char_data, $skill);
      $new_rank = strtolower((string) ($choice['newRank'] ?? ''));
      if ($new_rank === '') {
        continue;
      }
      $this->setSkillRank($char_data, $skill, $new_rank, $target_level);
      $changes[] = [
        'skill' => $skill,
        'previous_rank' => $previous_rank,
        'new_rank' => $new_rank,
      ];
    }
    return $changes;
  }

  /**
   * Revert skill rank transitions recorded during the last apply.
   */
  private function revertSkillChanges(array &$char_data, array $skill_changes): void {
    $level = (int) ($char_data['basicInfo']['level'] ?? 1);
    foreach ($skill_changes as $change) {
      $skill = strtolower((string) ($change['skill'] ?? ''));
      $previous_rank = strtolower((string) ($change['previous_rank'] ?? 'untrained'));
      if ($skill === '') {
        continue;
      }
      $this->setSkillRank($char_data, $skill, $previous_rank, $level);
    }
  }

  /**
   * Resolve and validate a pending skill increase choice payload.
   */
  private function resolveSkillIncreaseChoice(array $char_data, string $skill): array {
    $skill = strtolower(trim($skill));
    if (!array_key_exists($skill, CharacterCalculator::SKILLS)) {
      throw new \InvalidArgumentException("Unknown skill '{$skill}'", 400);
    }

    $current_rank = $this->getSkillRank($char_data, $skill);
    $rank_idx = array_search($current_rank, self::RANK_ORDER, TRUE);
    $rank_idx = $rank_idx === FALSE ? 0 : $rank_idx;
    if ($rank_idx >= count(self::RANK_ORDER) - 1) {
      throw new \InvalidArgumentException("Skill '{$skill}' is already at maximum rank", 400);
    }

    $new_rank = self::RANK_ORDER[$rank_idx + 1];
    $target_level = (int) ($char_data['levelUpState']['transitionTo'] ?? 0);
    if ($new_rank === 'master' && $target_level < 7) {
      throw new \InvalidArgumentException("Cannot increase '{$skill}' to master before level 7", 400);
    }
    if ($new_rank === 'legendary' && $target_level < 15) {
      throw new \InvalidArgumentException("Cannot increase '{$skill}' to legendary before level 15", 400);
    }

    return [
      'skill' => $skill,
      'previousRank' => $current_rank,
      'newRank' => $new_rank,
    ];
  }

  /**
   * Read the stored skill rank regardless of array/list payload shape.
   */
  private function getSkillRank(array $char_data, string $skill): string {
    $skills = $char_data['skills'] ?? [];
    if (is_array($skills) && !array_is_list($skills)) {
      $value = strtolower((string) ($skills[$skill] ?? 'untrained'));
      return in_array($value, self::RANK_ORDER, TRUE) ? $value : 'untrained';
    }

    foreach ((array) $skills as $entry) {
      if (!is_array($entry)) {
        continue;
      }
      $name = strtolower(trim((string) ($entry['name'] ?? '')));
      if ($name === $skill) {
        $value = strtolower((string) ($entry['proficiency'] ?? $entry['rank'] ?? 'untrained'));
        return in_array($value, self::RANK_ORDER, TRUE) ? $value : 'untrained';
      }
    }

    return 'untrained';
  }

  /**
   * Persist a skill rank and keep the rendered list modifiers consistent.
   */
  private function setSkillRank(array &$char_data, string $skill, string $rank, int $level): void {
    $rank = in_array($rank, self::RANK_ORDER, TRUE) ? $rank : 'untrained';
    $skills = $char_data['skills'] ?? [];
    if (is_array($skills) && !array_is_list($skills)) {
      $char_data['skills'][$skill] = $rank;
      return;
    }

    $skills = is_array($skills) ? array_values($skills) : [];
    $ability_key = CharacterCalculator::SKILLS[$skill] ?? 'intelligence';
    $ability_score = (int) ($char_data['abilities'][$ability_key] ?? 10);
    $calculator = new CharacterCalculator();
    $modifier = $calculator->calculateAbilityModifier($ability_score) + $calculator->calculateProficiencyBonus($rank, $level);
    $updated = FALSE;

    foreach ($skills as &$entry) {
      if (!is_array($entry)) {
        continue;
      }
      $name = strtolower(trim((string) ($entry['name'] ?? '')));
      if ($name !== $skill) {
        continue;
      }
      $entry['proficiency'] = $rank;
      $entry['modifier'] = $modifier;
      $updated = TRUE;
      break;
    }
    unset($entry);

    if (!$updated) {
      $skills[] = [
        'name' => ucwords($skill),
        'modifier' => $modifier,
        'proficiency' => $rank,
      ];
    }

    $char_data['skills'] = $skills;
  }

  /**
   * Apply resolved feat picks and return the feat ids actually added.
   */
  private function applyResolvedFeatChoices(array &$char_data, array $pending_choices, int $target_level): array {
    $char_data['features']['feats'] = is_array($char_data['features']['feats'] ?? NULL) ? $char_data['features']['feats'] : [];
    $existing_ids = array_column($char_data['features']['feats'], 'id');
    $added = [];

    foreach ($pending_choices as $slot) {
      if (($slot['type'] ?? '') !== 'feat_choice' || empty($slot['resolved'])) {
        continue;
      }
      $choice = is_array($slot['choice'] ?? NULL) ? $slot['choice'] : [];
      $feat_id = (string) ($choice['feat_id'] ?? '');
      if ($feat_id === '' || in_array($feat_id, $existing_ids, TRUE)) {
        continue;
      }

      $entry = [
        'id' => $feat_id,
        'name' => (string) ($choice['feat_name'] ?? $feat_id),
        'slot_type' => (string) ($choice['slot_type'] ?? ($slot['slot_type'] ?? 'class_feat')),
        'gained_at_level' => $target_level,
      ];
      if (!empty($choice['feat_params']) && is_array($choice['feat_params'])) {
        $entry['feat_params'] = $choice['feat_params'];
      }

      $char_data['features']['feats'][] = $entry;
      $existing_ids[] = $feat_id;
      $added[] = $feat_id;
    }

    return $added;
  }

  /**
   * Remove auto grants and feats added by a reverted advancement.
   */
  private function revertGrantedFeatures(array &$char_data, array $auto_grant_ids, array $feat_ids): void {
    $char_data['features']['classFeatures'] = array_values(array_filter(
      is_array($char_data['features']['classFeatures'] ?? NULL) ? $char_data['features']['classFeatures'] : [],
      static fn(array $feature): bool => !in_array((string) ($feature['id'] ?? ''), $auto_grant_ids, TRUE)
    ));
    $char_data['features']['feats'] = array_values(array_filter(
      is_array($char_data['features']['feats'] ?? NULL) ? $char_data['features']['feats'] : [],
      static fn(array $feat): bool => !in_array((string) ($feat['id'] ?? ''), $feat_ids, TRUE)
    ));
  }

  /**
   * Apply spellcasting slot/resource changes and return the previous snapshot.
   */
  private function applySpellcastingDeltas(array &$char_data, array $spellcasting_deltas, string $class_name): array {
    if ($spellcasting_deltas === []) {
      return [];
    }

    $spells = is_array($char_data['spells'] ?? NULL) ? $char_data['spells'] : [];
    $resources = is_array($char_data['resources'] ?? NULL) ? $char_data['resources'] : [];
    $before = [
      'slots' => is_array($spells['slots'] ?? NULL) ? $spells['slots'] : [],
      'spellbook_size' => (int) ($spells['spellbook_size'] ?? 0),
      'focusPoints' => is_array($resources['focusPoints'] ?? NULL) ? $resources['focusPoints'] : [],
    ];

    if (!empty($spellcasting_deltas['tradition'])) {
      $spells['tradition'] = $spellcasting_deltas['tradition'];
    }
    if (!empty($spellcasting_deltas['casting_ability'])) {
      $spells['casting_ability'] = $spellcasting_deltas['casting_ability'];
    }
    if (!empty($spellcasting_deltas['slots']) && is_array($spellcasting_deltas['slots'])) {
      $spells['slots'] = array_replace(is_array($spells['slots'] ?? NULL) ? $spells['slots'] : [], $spellcasting_deltas['slots']);
    }
    if (!empty($spellcasting_deltas['cantrips'])) {
      $spells['slots'] = is_array($spells['slots'] ?? NULL) ? $spells['slots'] : [];
      $spells['slots']['cantrips'] = (int) $spellcasting_deltas['cantrips'];
    }
    if (!empty($spellcasting_deltas['spellbook_size_delta'])) {
      $spells['spellbook_size'] = max(0, (int) ($spells['spellbook_size'] ?? 0) + (int) $spellcasting_deltas['spellbook_size_delta']);
    }
    if (!empty($spellcasting_deltas['focus_pool_start'])) {
      $resources['focusPoints'] = [
        'current' => max((int) ($resources['focusPoints']['current'] ?? 0), (int) $spellcasting_deltas['focus_pool_start']),
        'max' => max((int) ($resources['focusPoints']['max'] ?? 0), (int) $spellcasting_deltas['focus_pool_start']),
      ];
    }

    $normalized = CharacterManager::normalizeSpellcastingResources($spells, $resources, $class_name);
    $char_data['spells'] = $normalized['spells'];
    $char_data['resources'] = array_replace(is_array($char_data['resources'] ?? NULL) ? $char_data['resources'] : [], $normalized['resources']);

    return $before;
  }

  /**
   * Revert spellcasting state from a saved snapshot.
   */
  private function revertSpellcastingChanges(array &$char_data, array $before, string $class_name): void {
    if ($before === []) {
      return;
    }
    $char_data['spells'] = is_array($char_data['spells'] ?? NULL) ? $char_data['spells'] : [];
    $char_data['resources'] = is_array($char_data['resources'] ?? NULL) ? $char_data['resources'] : [];
    $char_data['spells']['slots'] = is_array($before['slots'] ?? NULL) ? $before['slots'] : [];
    if (array_key_exists('spellbook_size', $before)) {
      $char_data['spells']['spellbook_size'] = (int) $before['spellbook_size'];
    }
    if (array_key_exists('focusPoints', $before)) {
      $char_data['resources']['focusPoints'] = is_array($before['focusPoints'] ?? NULL) ? $before['focusPoints'] : [];
    }
    $normalized = CharacterManager::normalizeSpellcastingResources($char_data['spells'], $char_data['resources'], $class_name);
    $char_data['spells'] = $normalized['spells'];
    $char_data['resources'] = array_replace($char_data['resources'], $normalized['resources']);
  }

  /**
   * Build the set of feat ids already taken or reserved in a pending draft.
   */
  private function getTakenFeatIds(array $char_data): array {
    $ids = array_column(is_array($char_data['features']['feats'] ?? NULL) ? $char_data['features']['feats'] : [], 'id');
    foreach ((array) ($char_data['levelUpState']['pendingChoices'] ?? []) as $slot) {
      $choice = is_array($slot['choice'] ?? NULL) ? $slot['choice'] : [];
      $feat_id = (string) ($choice['feat_id'] ?? '');
      if ($feat_id !== '') {
        $ids[] = $feat_id;
      }
    }
    return array_values(array_unique(array_filter($ids)));
  }

  /**
   * Create a draft advancement row and cancel stale active rows for the same level.
   */
  private function createAdvancementDraft(object $record, array $char_data, array $plan, string $status): array {
    $now = time();
    $target_level = (int) ($plan['target_level'] ?? 0);
    $this->database->update('dc_character_advancement')
      ->fields([
        'is_active' => 0,
        'status' => 'cancelled',
        'updated' => $now,
      ])
      ->condition('character_id', (int) $record->id)
      ->condition('campaign_id', 0)
      ->condition('target_level', $target_level)
      ->condition('is_active', 1)
      ->execute();

    $id = $this->database->insert('dc_character_advancement')
      ->fields([
        'character_id' => (int) $record->id,
        'uid' => (int) ($record->uid ?? 0),
        'campaign_id' => 0,
        'target_level' => $target_level,
        'status' => $status,
        'is_active' => 1,
        'class_name' => (string) ($plan['class_id'] ?? $record->class ?? ''),
        'plan_data' => json_encode($plan),
        'created' => $now,
        'updated' => $now,
      ])
      ->execute();

    return $this->loadAdvancementById((int) $id);
  }

  /**
   * Update a draft advancement row.
   */
  private function updateAdvancementPlan(int $advancement_id, array $plan, string $status): void {
    $this->database->update('dc_character_advancement')
      ->fields([
        'plan_data' => json_encode($plan),
        'status' => $status,
        'updated' => time(),
      ])
      ->condition('id', $advancement_id)
      ->execute();
  }

  /**
   * Mark an advancement as applied and persist the final summary.
   */
  private function completeAdvancement(int $advancement_id, array $plan, array $applied_summary): void {
    $now = time();
    $this->database->update('dc_character_advancement')
      ->fields([
        'plan_data' => json_encode($plan),
        'applied_data' => json_encode($applied_summary),
        'status' => 'applied',
        'is_active' => 0,
        'updated' => $now,
        'applied' => $now,
      ])
      ->condition('id', $advancement_id)
      ->execute();
  }

  /**
   * Mark an advancement as cancelled.
   */
  private function markAdvancementCancelled(int $advancement_id): void {
    $this->database->update('dc_character_advancement')
      ->fields([
        'status' => 'cancelled',
        'is_active' => 0,
        'updated' => time(),
      ])
      ->condition('id', $advancement_id)
      ->execute();
  }

  /**
   * Cancel a draft advancement without reverting already-applied mutations.
   */
  private function cancelAdvancement(int $advancement_id): void {
    $this->markAdvancementCancelled($advancement_id);
  }

  /**
   * Load a single advancement row by id.
   */
  private function loadAdvancementById(int $advancement_id): ?array {
    $row = $this->database->select('dc_character_advancement', 'a')
      ->fields('a')
      ->condition('id', $advancement_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    return $row ? $this->normalizeAdvancementRow($row) : NULL;
  }

  /**
   * Load the active draft/ready advancement for a character.
   */
  private function loadActiveAdvancement(int $record_id, ?int $target_level = NULL): ?array {
    $query = $this->database->select('dc_character_advancement', 'a')
      ->fields('a')
      ->condition('character_id', $record_id)
      ->condition('campaign_id', 0)
      ->condition('is_active', 1)
      ->orderBy('id', 'DESC')
      ->range(0, 1);
    if ($target_level !== NULL) {
      $query->condition('target_level', $target_level);
    }
    $row = $query->execute()->fetchAssoc();
    return $row ? $this->normalizeAdvancementRow($row) : NULL;
  }

  /**
   * Load the most recent advancement row regardless of status.
   */
  private function loadLatestAdvancement(int $record_id): ?array {
    $row = $this->database->select('dc_character_advancement', 'a')
      ->fields('a')
      ->condition('character_id', $record_id)
      ->condition('campaign_id', 0)
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    return $row ? $this->normalizeAdvancementRow($row) : NULL;
  }

  /**
   * Load the most recent applied advancement row.
   */
  private function loadLatestAppliedAdvancement(int $record_id): ?array {
    $row = $this->database->select('dc_character_advancement', 'a')
      ->fields('a')
      ->condition('character_id', $record_id)
      ->condition('campaign_id', 0)
      ->condition('status', 'applied')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    return $row ? $this->normalizeAdvancementRow($row) : NULL;
  }

  /**
   * Decode plan/applied json columns from an advancement row.
   */
  private function normalizeAdvancementRow(array $row): array {
    $row['plan'] = json_decode((string) ($row['plan_data'] ?? '{}'), TRUE) ?? [];
    $row['applied'] = json_decode((string) ($row['applied_data'] ?? '{}'), TRUE) ?? [];
    $row['id'] = (int) ($row['id'] ?? 0);
    $row['target_level'] = (int) ($row['target_level'] ?? 0);
    return $row;
  }

  /**
   * Surface advancement summary data on the canonical character payload.
   */
  private function syncProgressionSummary(array &$char_data, ?array $advancement): void {
    $char_data['progression'] = is_array($char_data['progression'] ?? NULL) ? $char_data['progression'] : [];
    $char_data['progression']['history'] = is_array($char_data['progression']['history'] ?? NULL) ? $char_data['progression']['history'] : [];
    $char_data['progression']['pendingAdvancementId'] = ($advancement !== NULL && !empty($advancement['is_active']))
      ? (int) ($advancement['id'] ?? 0)
      : NULL;
  }

  /**
   * Remove the reverted advancement from history if present.
   */
  private function trimProgressionHistory(array &$char_data, int $advancement_id): void {
    $history = is_array($char_data['progression']['history'] ?? NULL) ? $char_data['progression']['history'] : [];
    $char_data['progression']['history'] = array_values(array_filter(
      $history,
      static fn(array $entry): bool => (int) ($entry['advancementId'] ?? 0) !== $advancement_id
    ));
  }

  /**
   * Find the first unresolved slot of a given type.
   */
  private function findPendingSlot(array $pending, string $type): int {
    foreach ($pending as $idx => $slot) {
      if (($slot['type'] ?? '') === $type && empty($slot['resolved'])) {
        return $idx;
      }
    }
    return -1;
  }

  /**
   * Find the first unresolved feat slot matching slot_type.
   */
  private function findPendingFeatSlot(array $pending, string $slot_type): int {
    foreach ($pending as $idx => $slot) {
      if (($slot['type'] ?? '') === 'feat_choice'
        && (($slot['slot_type'] ?? '') === $slot_type)
        && empty($slot['resolved'])
      ) {
        return $idx;
      }
    }
    foreach ($pending as $idx => $slot) {
      if (($slot['type'] ?? '') === 'feat_choice' && empty($slot['resolved'])) {
        return $idx;
      }
    }
    return -1;
  }

  /**
   * Build the standard status payload.
   */
  private function buildStatusResponse(array $char_data, string $character_id, ?array $advancement = NULL): array {
    $lus = is_array($char_data['levelUpState'] ?? NULL) ? $char_data['levelUpState'] : [];
    $current_level = (int) ($char_data['basicInfo']['level'] ?? 1);
    $xp_status = $this->buildXpStatus($current_level, (int) ($char_data['basicInfo']['experiencePoints'] ?? 0));

    return [
      'success' => TRUE,
      'characterId' => $character_id,
      'currentLevel' => $current_level,
      'maxLevel' => self::MAX_LEVEL,
      'experiencePoints' => $xp_status['experiencePoints'],
      'levelUpAvailable' => $xp_status['levelUpAvailable'],
      'xpToNextLevel' => $xp_status['xpToNextLevel'],
      'milestoneReady' => (bool) ($lus['milestoneReady'] ?? FALSE),
      'inProgress' => (bool) ($lus['inProgress'] ?? FALSE),
      'transitionTo' => (int) ($lus['transitionTo'] ?? 0),
      'pendingChoices' => $lus['pendingChoices'] ?? [],
      'autoApplied' => $lus['autoApplied'] ?? [],
      'hpGranted' => (int) ($lus['hpGranted'] ?? 0),
      'canTrigger' => $current_level < self::MAX_LEVEL && $xp_status['levelUpAvailable'] && empty($lus['inProgress']),
      'pendingAdvancementId' => $char_data['progression']['pendingAdvancementId'] ?? NULL,
      'progression' => $char_data['progression'] ?? [],
      'activePlanStatus' => $advancement['status'] ?? NULL,
      'activePlan' => $advancement['plan'] ?? ($lus['draftPlan'] ?? NULL),
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
    $catalog = $this->getSlotFeatCatalog($slot_type, $class_name, $char_data);
    if ($slot_type !== 'class_feat' && $slot_type !== 'skill_feat' && $slot_type !== 'general_feat' && $slot_type !== 'ancestry_feat') {
      $catalog = array_merge(
        $this->featLibrary->getClassFeats($class_name),
        $this->featLibrary->getSkillFeats(),
        $this->featLibrary->getGeneralFeats(),
      );
    }

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
    $owned_feats = is_array($char_data['features']['feats'] ?? NULL) ? $char_data['features']['feats'] : [];
    $owned_ids = $this->getTakenFeatIds($char_data);

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
      $this->assertSpellIdsAreValid(
        [$selected_spell],
        $this->collectValidSpellIds(['occult'], $highest_rank, $highest_rank),
        'studious-capacity',
        "occult rank {$highest_rank} spell"
      );
    }
    if ($feat_id === 'greater-vital-evolution') {
      $this->assertSpellIdsAreValid(
        $this->normalizeSelectedSpellIds(
          $feat_params['selected_spells'] ?? [],
          'greater-vital-evolution',
          2,
          2,
          'exactly 2 arcane spell ids',
          'two distinct arcane spell ids'
        ),
        $this->collectValidSpellIds(['arcane'], 1, 10),
        'greater-vital-evolution',
        'arcane spell'
      );
    }
    if ($feat_id === 'spell-mastery') {
      $this->assertSpellIdsAreValid(
        $this->normalizeSelectedSpellIds(
          $feat_params['selected_spells'] ?? [],
          'spell-mastery',
          4,
          4,
          'exactly 4 arcane spell ids',
          'four distinct arcane spell ids'
        ),
        $this->collectValidSpellIds(['arcane'], 1, 9),
        'spell-mastery',
        'arcane rank-9-or-lower spell'
      );
    }
    if ($feat_id === 'infinite-possibilities') {
      $this->assertSpellIdsAreValid(
        $this->normalizeSelectedSpellIds(
          $feat_params['selected_spells'] ?? [],
          'infinite-possibilities',
          1,
          3,
          '1 to 3 spell ids',
          'distinct spell ids'
        ),
        $this->collectValidSpellIds(['arcane', 'divine', 'occult', 'primal'], 1, 10),
        'infinite-possibilities',
        'common spell id'
      );
    }
    if ($feat_id === 'scroll-savant') {
      $this->assertSpellIdsAreValid(
        $this->normalizeSelectedSpellIds(
          $feat_params['selected_spells'] ?? [],
          'scroll-savant',
          2,
          2,
          'exactly 2 arcane spell ids',
          'two distinct arcane spell ids'
        ),
        $this->collectValidSpellIds(['arcane'], 1, 10),
        'scroll-savant',
        'arcane spell'
      );
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
    $pools = [$ancestry_name];
    if ($heritage_id !== '') {
      foreach (CharacterManager::HERITAGES[$ancestry_name] ?? [] as $heritage) {
        if (($heritage['id'] ?? '') !== $heritage_id) {
          continue;
        }
        foreach ((array) ($heritage['cross_ancestry_feat_pool'] ?? []) as $pool_ancestry) {
          if (is_string($pool_ancestry) && $pool_ancestry !== '' && !in_array($pool_ancestry, $pools, TRUE)) {
            $pools[] = $pool_ancestry;
          }
        }
        break;
      }
    }

    $catalog = [];
    $seen = [];
    foreach ($pools as $pool) {
      foreach ($this->featLibrary->getAncestryFeats($pool) as $feat) {
        $feat_id = (string) ($feat['id'] ?? '');
        if ($feat_id === '' || isset($seen[$feat_id])) {
          continue;
        }
        $seen[$feat_id] = TRUE;
        $catalog[] = $feat;
      }
    }

    $adopted_ancestry = $this->getSelectedAdoptedAncestryName($char_data);
    if ($adopted_ancestry === '') {
      return $catalog;
    }

    foreach ($this->featLibrary->getAncestryFeats($adopted_ancestry) as $feat) {
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
   * Resolve the feat catalog for a given slot type.
   */
  private function getSlotFeatCatalog(string $slot_type, string $class_name, array $char_data): array {
    return match ($slot_type) {
      'class_feat' => $this->featLibrary->getClassFeats($class_name),
      'skill_feat' => $this->featLibrary->getSkillFeats(),
      'general_feat' => $this->featLibrary->getGeneralFeats(),
      'ancestry_feat' => $this->getAvailableAncestryFeatCatalog($char_data),
      default => [],
    };
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

    $this->assertSpellIdsAreValid(
      [$selected_spell],
      $this->collectValidSpellIds([$tradition], 1, $highest_rank),
      $feat_id,
      "{$tradition} spell of rank {$highest_rank} or lower"
    );

    return $selected_spell;
  }

  /**
   * Normalize feat-selected spell IDs and enforce count/distinctness contracts.
   *
   * @return array<int, string>
   *   Canonical selected spell IDs.
   */
  private function normalizeSelectedSpellIds(
    mixed $selected_spells,
    string $feat_id,
    int $min_count,
    int $max_count,
    string $count_requirement,
    string $distinct_requirement,
  ): array {
    if (!is_array($selected_spells) || count($selected_spells) < $min_count || count($selected_spells) > $max_count) {
      throw new \InvalidArgumentException(
        "Feat '{$feat_id}' requires feat_params['selected_spells'] with {$count_requirement}",
        400
      );
    }

    $normalized = [];
    foreach ($selected_spells as $spell_id) {
      if (!is_string($spell_id) || trim($spell_id) === '') {
        throw new \InvalidArgumentException(
          "Feat '{$feat_id}' requires non-empty spell ids in feat_params['selected_spells']",
          400
        );
      }
      $normalized[] = trim($spell_id);
    }

    if (count(array_unique($normalized)) !== count($normalized)) {
      throw new \InvalidArgumentException(
        "Feat '{$feat_id}' requires {$distinct_requirement}",
        400
      );
    }

    return $normalized;
  }

  /**
   * Collect valid spell IDs across one or more traditions and rank ranges.
   *
   * @param array<int, string> $traditions
   *   Traditions to query.
   *
   * @return array<int, string>
   *   Distinct valid spell IDs.
   */
  private function collectValidSpellIds(array $traditions, int $min_rank, int $max_rank): array {
    $valid_spell_ids = [];
    foreach ($traditions as $tradition) {
      for ($rank = $min_rank; $rank <= $max_rank; $rank++) {
        foreach ($this->getCharacterManager()->getSpellsByTradition($tradition, $rank) as $spell) {
          $spell_id = trim((string) ($spell['id'] ?? ''));
          if ($spell_id !== '') {
            $valid_spell_ids[] = $spell_id;
          }
        }
      }
    }

    return array_values(array_unique($valid_spell_ids));
  }

  /**
   * Assert that selected spell IDs resolve against the canonical spell catalog.
   *
   * @param array<int, string> $selected_spell_ids
   *   Selected spell IDs to validate.
   * @param array<int, string> $valid_spell_ids
   *   Canonical valid IDs.
   */
  private function assertSpellIdsAreValid(array $selected_spell_ids, array $valid_spell_ids, string $feat_id, string $spell_label): void {
    foreach ($selected_spell_ids as $spell_id) {
      if (!in_array($spell_id, $valid_spell_ids, TRUE)) {
        throw new \InvalidArgumentException(
          "Feat '{$feat_id}' spell '{$spell_id}' is not a valid {$spell_label}",
          400
        );
      }
    }
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
