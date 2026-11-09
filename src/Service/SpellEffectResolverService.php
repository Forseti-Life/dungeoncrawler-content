<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Resolves canonical spell contracts into encounter mutations.
 */
class SpellEffectResolverService {

  public function __construct(
    protected EncounterStateService $encounterState,
    protected NumberGenerationService $numberGenerationService,
    protected CombatCalculator $combatCalculator,
    protected HPManager $hpManager,
    protected ConditionManager $conditionManager,
    protected CombatResolutionContractService $combatResolutionContractService
  ) {}

  /**
   * Resolve one canonical spell contract against selected encounter targets.
   *
   * @param array<int, string> $target_refs
   *
   * @return array<string, mixed>
   */
  public function resolve(
    int $encounter_id,
    string $source_actor_id,
    array $target_refs,
    array $spell,
    int $cast_rank,
    int $spell_dc,
    int $spell_attack_modifier,
    int $action_cost
  ): array {
    $encounter = $this->encounterState->getState($encounter_id);

    $spell_id = trim((string) ($spell['id'] ?? ''));
    $spell_name = trim((string) ($spell['name'] ?? $spell_id));
    $targets = [];
    foreach (array_values(array_unique(array_filter(array_map('strval', $target_refs)))) as $target_ref) {
      $participant = $this->findParticipant($encounter, $target_ref);
      if ($participant === NULL) {
        throw new \InvalidArgumentException("Spell target '{$target_ref}' is not present in encounter {$encounter_id}.");
      }
      $targets[] = $participant;
    }

    if ($targets === [] && $this->targetsSelf($spell)) {
      $source = $this->findParticipant($encounter, $source_actor_id);
      if ($source !== NULL) {
        $targets[] = $source;
      }
    }

    $result = [
      'spell_id' => $spell_id,
      'spell_name' => $spell_name,
      'cast_rank' => $cast_rank,
      'target_results' => [],
      'damage' => 0,
      'healing' => 0,
      'damage_packets' => [],
      'state_effect_packets' => [],
      'mutations' => [],
      'is_defeated' => FALSE,
    ];

    foreach ($targets as $target) {
      $target_result = $this->resolveTarget(
        $encounter_id,
        $source_actor_id,
        $target,
        $spell,
        $cast_rank,
        $spell_dc,
        $spell_attack_modifier,
        $action_cost
      );
      $result['target_results'][] = $target_result;
      $result['damage'] += (int) ($target_result['damage'] ?? 0);
      $result['healing'] += (int) ($target_result['healing'] ?? 0);
      $result['is_defeated'] = $result['is_defeated'] || !empty($target_result['is_defeated']);
      $result['damage_packets'] = array_merge($result['damage_packets'], (array) ($target_result['damage_packets'] ?? []));
      $result['state_effect_packets'] = array_merge($result['state_effect_packets'], (array) ($target_result['state_effect_packets'] ?? []));
      $result['mutations'] = array_merge($result['mutations'], (array) ($target_result['mutations'] ?? []));
    }

    return $result;
  }

  /**
   * Resolve spell mechanics for one encounter participant.
   */
  protected function resolveTarget(
    int $encounter_id,
    string $source_actor_id,
    array $target,
    array $spell,
    int $cast_rank,
    int $spell_dc,
    int $spell_attack_modifier,
    int $action_cost
  ): array {
    $target_ref = $this->participantEntityRef($target);
    $target_participant_id = (int) ($target['id'] ?? 0);
    $spell_id = trim((string) ($spell['id'] ?? ''));
    $spell_name = trim((string) ($spell['name'] ?? $spell_id));
    $degree = 'automatic';
    $roll = NULL;
    $total = NULL;

    if (!empty($spell['requires_attack_roll'])) {
      $roll = $this->numberGenerationService->rollPathfinderDie(20);
      $total = $roll + $spell_attack_modifier;
      $degree = $this->combatCalculator->calculateDegreeOfSuccess(
        $total,
        (int) ($target['ac'] ?? 10),
        $roll
      );
    }
    else {
      $save_type = SpellCatalogService::normalizeSaveType((string) ($spell['save_type'] ?? ''));
      if ($save_type !== NULL && $save_type !== 'none') {
        $base_save_type = str_replace('basic_', '', $save_type);
        $roll = $this->numberGenerationService->rollPathfinderDie(20);
        $save_bonus = $this->resolveSaveBonus($target, $base_save_type);
        $total = $roll + $save_bonus;
        $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $spell_dc, $roll);
      }
    }

    $damage_total = 0;
    $healing_total = 0;
    $damage_packets = [];
    $state_effect_packets = [];
    $mutations = [];
    $target_hp_before = (int) ($target['hp'] ?? $target['current_hp'] ?? 0);
    $target_hp_after = $target_hp_before;
    $is_defeated = FALSE;

    foreach ((array) ($spell['damage'] ?? []) as $damage) {
      if (!is_array($damage)) {
        continue;
      }
      $formula = trim((string) ($damage['formula'] ?? ''));
      $damage_type = trim((string) ($damage['type'] ?? ''));
      if (!$this->isDiceFormula($formula) || $damage_type === '' || $damage_type === 'none') {
        continue;
      }
      if (!empty($damage['persistent'])) {
        if (!$this->persistentDamageApplies($spell, $target, $degree)) {
          continue;
        }
        $heightening_bonus = $this->persistentDamageHeightening($spell, $cast_rank);
        $amount = $this->rollFormula($formula) + $heightening_bonus;
        $persistent_formula = str_replace(' ', '', strtolower($formula))
          . ($heightening_bonus > 0 ? '+' . $heightening_bonus : '');
        $condition = 'persistent_damage_' . strtolower($damage_type);
        $this->conditionManager->applyCondition(
          $target_participant_id,
          $condition,
          $amount,
          NULL,
          'spell:' . $spell_id . ':' . $persistent_formula,
          $encounter_id
        );
        $state_effect_packets[] = $this->combatResolutionContractService->buildStateEffectChangePacket(
          $source_actor_id,
          $target_ref,
          'persistent_damage',
          $condition,
          'apply',
          $amount,
          ['spell_id' => $spell_id, 'cast_rank' => $cast_rank, 'damage_type' => $damage_type]
        );
        continue;
      }
      $amount = $this->rollHeightenedFormula($formula, $spell, $cast_rank);
      if ($this->isMagicMissile($spell_id)) {
        $missiles_per_action = 1 + max(0, (int) floor((max(1, $cast_rank) - 1) / 2));
        $amount = 0;
        for ($i = 0; $i < max(1, min(3, $action_cost)) * $missiles_per_action; $i++) {
          $amount += $this->rollFormula($formula);
        }
      }
      else {
        $amount = $this->adjustDamageForDegree($amount, $degree, $spell);
      }
      if ($amount <= 0) {
        continue;
      }
      $damage_result = $this->hpManager->applyDamage(
        $target_participant_id,
        $amount,
        $damage_type,
        'spell:' . $spell_id,
        $encounter_id
      );
      $target_hp_after = (int) ($damage_result['new_hp'] ?? max(0, $target_hp_after - $amount));
      $is_defeated = ($damage_result['new_status'] ?? 'active') !== 'active';
      $damage_total += $amount;
      $damage_packets[] = $this->combatResolutionContractService->buildDamageApplicationPacket(
        $source_actor_id,
        $target_ref,
        'spell',
        $amount,
        $damage_type,
        [$spell_id],
        [
          'cast_rank' => $cast_rank,
          'degree' => $degree,
          'target_hp_before' => $target_hp_before,
          'target_hp_after' => $target_hp_after,
        ]
      );
    }

    $healing_formula = $this->resolveHealingFormula($spell);
    if ($healing_formula !== NULL && !$this->isUndead($target)) {
      $healing = $this->rollHeightenedHealing($healing_formula, $spell, $cast_rank);
      if ($spell_id === 'heal' && $action_cost >= 2) {
        $healing += 8 * max(1, $cast_rank);
      }
      if ($healing > 0) {
        $healing_result = $this->hpManager->applyHealing(
          $target_participant_id,
          $healing,
          'spell:' . $spell_id,
          $encounter_id
        );
        $healing_total = (int) ($healing_result['healing_applied'] ?? $healing);
        $target_hp_after = (int) ($healing_result['new_hp'] ?? ($target_hp_after + $healing_total));
      }
    }

    foreach ($this->conditionsForDegree($spell, $degree) as $condition => $value) {
      $this->conditionManager->applyCondition(
        $target_participant_id,
        $condition,
        $value,
        $this->resolveDuration($spell),
        'spell:' . $spell_id,
        $encounter_id
      );
      $state_effect_packets[] = $this->combatResolutionContractService->buildStateEffectChangePacket(
        $source_actor_id,
        $target_ref,
        'condition',
        $condition,
        'apply',
        $value,
        ['spell_id' => $spell_id, 'cast_rank' => $cast_rank, 'degree' => $degree]
      );
    }

    if ($target_hp_after !== $target_hp_before) {
      $mutations[] = [
        'entity' => $target_ref,
        'field' => 'hp',
        'from' => $target_hp_before,
        'to' => $target_hp_after,
      ];
    }

    return [
      'target_id' => $target_ref,
      'degree' => $degree,
      'roll' => $roll,
      'total' => $total,
      'damage' => $damage_total,
      'healing' => $healing_total,
      'damage_packets' => $damage_packets,
      'state_effect_packets' => $state_effect_packets,
      'mutations' => $mutations,
      'is_defeated' => $is_defeated,
    ];
  }

  protected function adjustDamageForDegree(int $amount, string $degree, array $spell): int {
    $save_type = SpellCatalogService::normalizeSaveType((string) ($spell['save_type'] ?? ''));
    if (!empty($spell['requires_attack_roll'])) {
      return match ($degree) {
        'critical_success' => $amount * 2,
        'success' => $amount,
        default => 0,
      };
    }
    if ($save_type !== NULL && $save_type !== 'none') {
      return match ($degree) {
        'critical_success' => 0,
        'success' => str_starts_with($save_type, 'basic_') ? (int) floor($amount / 2) : $this->outcomeDamage($spell, 'Success', $amount),
        'failure' => $amount,
        'critical_failure' => str_starts_with($save_type, 'basic_') ? $amount * 2 : $this->outcomeDamage($spell, 'Critical Failure', $amount),
        default => $amount,
      };
    }
    return $amount;
  }

  protected function outcomeDamage(array $spell, string $label, int $amount): int {
    $text = strtolower((string) ($spell['effects']['outcomes'][$label] ?? ''));
    if (str_contains($text, 'unaffected') || str_contains($text, 'no damage')) {
      return 0;
    }
    if (str_contains($text, 'half damage')) {
      return (int) floor($amount / 2);
    }
    if (str_contains($text, 'double damage')) {
      return $amount * 2;
    }
    return $amount;
  }

  /**
   * @return array<string, int>
   */
  protected function conditionsForDegree(array $spell, string $degree): array {
    if ($degree === 'automatic' || ($degree === 'critical_success' && empty($spell['requires_attack_roll']))) {
      return [];
    }
    $label = match ($degree) {
      'success' => 'Success',
      'failure' => 'Failure',
      'critical_failure' => 'Critical Failure',
      default => '',
    };
    $outcome = (string) ($spell['effects']['outcomes'][$label] ?? '');
    $conditions = [];
    foreach ((array) ($spell['conditions_caused'] ?? []) as $condition) {
      $condition = strtolower(str_replace(' ', '_', trim((string) $condition)));
      if ($condition === '' || $condition === 'none') {
        continue;
      }
      if ($outcome === '' && !empty($spell['requires_attack_roll']) && in_array($degree, ['success', 'critical_success'], TRUE)) {
        $conditions[$condition] = 1;
        continue;
      }
      if ($outcome !== '' && preg_match('/\b' . preg_quote(str_replace('_', ' ', $condition), '/') . '\s*(\d+)?\b/i', $outcome, $match) === 1) {
        $conditions[$condition] = isset($match[1]) && $match[1] !== '' ? max(0, (int) $match[1]) : 1;
      }
    }
    return $conditions;
  }

  protected function resolveHealingFormula(array $spell): ?string {
    $description = (string) ($spell['description'] ?? '');
    if (preg_match('/\b(?:restore|restores|regain|regains)\s+(\d+d\d+(?:\s*[+-]\s*\d+)?|\d+)\s+(?:temporary\s+)?hit points\b/i', $description, $match) === 1) {
      return str_replace(' ', '', $match[1]);
    }
    return NULL;
  }

  protected function rollHeightenedHealing(string $formula, array $spell, int $cast_rank): int {
    $amount = $this->rollFormula($formula);
    $base_rank = max(1, (int) ($spell['rank'] ?? $spell['level'] ?? 1));
    $rank_increase = max(0, $cast_rank - $base_rank);
    foreach ((array) ($spell['heightened_scaling'] ?? $spell['heightened'] ?? []) as $heightening) {
      if (!is_array($heightening) || (string) ($heightening['type'] ?? '') !== 'step') {
        continue;
      }
      $step = max(1, (int) ($heightening['step'] ?? 1));
      $applications = (int) floor($rank_increase / $step);
      $text = (string) ($heightening['text'] ?? '');
      if ($applications > 0 && preg_match('/(?:healing|hit points)[^.]*increases? by\s+(\d+d\d+(?:\+\d+)?|\d+)/i', $text, $match) === 1) {
        for ($i = 0; $i < $applications; $i++) {
          $amount += $this->rollFormula($match[1]);
        }
      }
    }
    return $amount;
  }

  protected function rollHeightenedFormula(string $formula, array $spell, int $cast_rank): int {
    $amount = $this->rollFormula($formula);
    $base_rank = max(1, (int) ($spell['rank'] ?? $spell['level'] ?? 1));
    $rank_increase = max(0, $cast_rank - $base_rank);
    foreach ((array) ($spell['heightened_scaling'] ?? $spell['heightened'] ?? []) as $heightening) {
      if (!is_array($heightening) || (string) ($heightening['type'] ?? '') !== 'step') {
        continue;
      }
      $step = max(1, (int) ($heightening['step'] ?? 1));
      $applications = (int) floor($rank_increase / $step);
      $text = (string) ($heightening['text'] ?? '');
      if ($applications > 0 && preg_match('/damage increases? by\s+(\d+d\d+(?:\+\d+)?)/i', $text, $match) === 1) {
        for ($i = 0; $i < $applications; $i++) {
          $amount += $this->rollFormula($match[1]);
        }
      }
    }
    return $amount;
  }

  protected function persistentDamageApplies(array $spell, array $target, string $degree): bool {
    if (!in_array($degree, ['automatic', 'success', 'failure', 'critical_failure', 'critical_success'], TRUE)) {
      return FALSE;
    }
    if (!empty($spell['requires_attack_roll']) && !in_array($degree, ['success', 'critical_success'], TRUE)) {
      return FALSE;
    }
    $description = strtolower((string) ($spell['description'] ?? ''));
    if (!str_contains($description, 'wearing metal armor or is made of metal')) {
      return TRUE;
    }
    $entity = !empty($target['entity_ref']) ? json_decode((string) $target['entity_ref'], TRUE) : [];
    $searchable = strtolower(json_encode([
      'traits' => $entity['traits'] ?? [],
      'armor' => $entity['armor'] ?? $entity['equipment']['armor'] ?? [],
    ]) ?: '');
    return str_contains($searchable, 'metal');
  }

  protected function persistentDamageHeightening(array $spell, int $cast_rank): int {
    $base_rank = max(1, (int) ($spell['rank'] ?? $spell['level'] ?? 1));
    $rank_increase = max(0, $cast_rank - $base_rank);
    foreach ((array) ($spell['heightened_scaling'] ?? $spell['heightened'] ?? []) as $heightening) {
      if (!is_array($heightening) || (string) ($heightening['type'] ?? '') !== 'step') {
        continue;
      }
      $step = max(1, (int) ($heightening['step'] ?? 1));
      if (preg_match('/persistent [a-z]+ damage increases? by\s+(\d+)/i', (string) ($heightening['text'] ?? ''), $match) === 1) {
        return (int) floor($rank_increase / $step) * (int) $match[1];
      }
    }
    return 0;
  }

  protected function rollFormula(string $formula): int {
    $formula = str_replace(' ', '', strtolower($formula));
    if (ctype_digit($formula)) {
      return (int) $formula;
    }
    if (!$this->isDiceFormula($formula)) {
      throw new \InvalidArgumentException("Unsupported spell formula '{$formula}'.");
    }
    $roll = $this->numberGenerationService->rollExpression($formula);
    return max(0, (int) ($roll['total'] ?? 0));
  }

  protected function isDiceFormula(string $formula): bool {
    return preg_match('/^\d+d\d+(?:[+-]\d+)?$/', str_replace(' ', '', strtolower($formula))) === 1;
  }

  protected function resolveSaveBonus(array $target, string $save_type): int {
    $entity = !empty($target['entity_ref']) ? json_decode((string) $target['entity_ref'], TRUE) : [];
    if (!is_array($entity)) {
      $entity = [];
    }
    foreach ([
      $save_type . '_save',
      $save_type . '_modifier',
      'saves.' . $save_type,
    ] as $field) {
      if (str_contains($field, '.')) {
        [, $key] = explode('.', $field, 2);
        $value = $entity['saves'][$key] ?? NULL;
      }
      else {
        $value = $target[$field] ?? $entity[$field] ?? NULL;
      }
      if (is_numeric($value)) {
        return (int) $value;
      }
    }
    return 0;
  }

  protected function resolveDuration(array $spell): ?array {
    $duration = strtolower(trim((string) ($spell['duration'] ?? '')));
    if (preg_match('/(\d+)\s+round/', $duration, $match) === 1) {
      return ['type' => 'rounds', 'remaining' => (int) $match[1]];
    }
    if (preg_match('/(\d+)\s+minute/', $duration, $match) === 1) {
      return ['type' => 'minutes', 'remaining' => (int) $match[1]];
    }
    return NULL;
  }

  protected function targetsSelf(array $spell): bool {
    return str_contains(strtolower((string) ($spell['targets'] ?? '')), 'self')
      || strtolower(trim((string) ($spell['range'] ?? ''))) === 'self';
  }

  protected function isUndead(array $participant): bool {
    $entity = !empty($participant['entity_ref']) ? json_decode((string) $participant['entity_ref'], TRUE) : [];
    $traits = is_array($entity['traits'] ?? NULL) ? $entity['traits'] : [];
    return in_array('undead', array_map(static fn($trait): string => strtolower((string) $trait), $traits), TRUE);
  }

  protected function isMagicMissile(string $spell_id): bool {
    return preg_replace('/[^a-z0-9]+/', '', strtolower($spell_id)) === 'magicmissile';
  }

  protected function participantEntityRef(array $participant): string {
    $entity = !empty($participant['entity_ref']) ? json_decode((string) $participant['entity_ref'], TRUE) : [];
    foreach (['instance_id', 'entity_id', 'actor_id', 'character_id'] as $key) {
      if (is_scalar($entity[$key] ?? NULL) && trim((string) $entity[$key]) !== '') {
        return trim((string) $entity[$key]);
      }
    }
    return (string) ($participant['id'] ?? '');
  }

  protected function findParticipant(array $encounter, string $entity_ref): ?array {
    $needle = strtolower(trim($entity_ref));
    foreach ((array) ($encounter['participants'] ?? []) as $participant) {
      if (!is_array($participant)) {
        continue;
      }
      if (strtolower((string) ($participant['id'] ?? '')) === $needle) {
        return $participant;
      }
      $candidate = strtolower($this->participantEntityRef($participant));
      if ($candidate === $needle) {
        return $participant;
      }
    }
    return NULL;
  }

}
