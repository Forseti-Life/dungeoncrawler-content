<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Canonical state projection and synchronization helpers.
 */
class CanonicalProjectionService {

  protected CombatEncounterStore $encounterStore;
  protected CharacterStateService $characterStateService;
  protected LoggerInterface $logger;
  protected ?EffectLifecycleService $effectLifecycleService;

  public function __construct(
    CombatEncounterStore $encounter_store,
    CharacterStateService $character_state_service,
    LoggerChannelFactoryInterface $logger_factory,
    ?EffectLifecycleService $effect_lifecycle_service = NULL
  ) {
    $this->encounterStore = $encounter_store;
    $this->characterStateService = $character_state_service;
    $this->logger = $logger_factory->get('dungeoncrawler');
    $this->effectLifecycleService = $effect_lifecycle_service;
  }

  public function findEncounterParticipantByEntityId(array $encounter, string $entity_id): ?array {
    foreach (($encounter['participants'] ?? []) as $participant) {
      if ((string) ($participant['entity_id'] ?? '') === (string) $entity_id) {
        return $participant;
      }
    }

    return NULL;
  }

  public function loadCanonicalTurnState(int $encounter_id): ?array {
    if ($encounter_id <= 0) {
      return NULL;
    }

    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $participants = is_array($encounter['participants'] ?? NULL) ? array_values($encounter['participants']) : [];
    if ($participants === []) {
      return NULL;
    }

    $turn_index = (int) ($encounter['turn_index'] ?? 0);
    if ($turn_index < 0 || $turn_index >= count($participants)) {
      $turn_index = 0;
    }

    $active = $participants[$turn_index] ?? NULL;
    if (!is_array($active)) {
      return NULL;
    }

    return [
      'encounter_id' => $encounter_id,
      'round' => max(1, (int) ($encounter['current_round'] ?? 1)),
      'turn_index' => $turn_index,
      'entity_id' => (string) ($active['entity_id'] ?? ''),
      'actions_remaining' => max(0, (int) ($active['actions_remaining'] ?? 3)),
      'attacks_this_turn' => max(0, (int) ($active['attacks_this_turn'] ?? 0)),
      'reaction_available' => !empty($active['reaction_available']),
      'participants' => $participants,
    ];
  }

  public function syncGameStateWithCanonicalTurn(array &$game_state, array $canonical_turn): void {
    $entity_id = trim((string) ($canonical_turn['entity_id'] ?? ''));
    if ($entity_id === '') {
      return;
    }

    $game_state['encounter_id'] = (int) ($canonical_turn['encounter_id'] ?? ($game_state['encounter_id'] ?? 0));
    $game_state['round'] = max(1, (int) ($canonical_turn['round'] ?? ($game_state['round'] ?? 1)));
    if (is_array($canonical_turn['participants'] ?? NULL)) {
      $game_state['initiative_order'] = array_values($canonical_turn['participants']);
    }

    $existing_turn = is_array($game_state['turn'] ?? NULL) ? $game_state['turn'] : [];
    $game_state['turn'] = [
      'entity' => $entity_id,
      'index' => (int) ($canonical_turn['turn_index'] ?? 0),
      'actions_remaining' => max(0, (int) ($canonical_turn['actions_remaining'] ?? 3)),
      'attacks_this_turn' => max(0, (int) ($canonical_turn['attacks_this_turn'] ?? 0)),
      'reaction_available' => !empty($canonical_turn['reaction_available']),
      'delayed' => !empty($existing_turn['delayed']),
    ];
  }

  public function findDungeonEntityIndexByInstanceId(array $dungeon_data, string $entity_id): ?int {
    foreach (($dungeon_data['entities'] ?? []) as $index => $entity) {
      $candidates = [
        $entity['entity_instance_id'] ?? NULL,
        $entity['instance_id'] ?? NULL,
        $entity['id'] ?? NULL,
      ];
      foreach ($candidates as $candidate) {
        if (is_scalar($candidate) && (string) $candidate === $entity_id) {
          return $index;
        }
      }
    }
    return NULL;
  }

  public function loadCanonicalCharacterState(array $entity, int $campaign_id): ?array {
    $identity = $this->resolveCanonicalCharacterIdentity($entity);
    $character_id = (string) ($identity['character_id'] ?? '');
    $instance_id = is_string($identity['instance_id'] ?? NULL) ? $identity['instance_id'] : NULL;
    if (!ctype_digit($character_id) || (int) $character_id <= 0) {
      return NULL;
    }
    return $this->characterStateService->getState($character_id, $campaign_id, $instance_id) ?: NULL;
  }

  public function persistCanonicalCharacterState(array $entity, int $campaign_id, array $character_state): void {
    $identity = $this->resolveCanonicalCharacterIdentity($entity);
    $character_id = (string) ($identity['character_id'] ?? '');
    $instance_id = is_string($identity['instance_id'] ?? NULL) ? $identity['instance_id'] : NULL;
    if (!ctype_digit($character_id) || (int) $character_id <= 0) {
      return;
    }
    $this->characterStateService->setState($character_id, $character_state, NULL, $campaign_id, $instance_id);
  }

  /**
   * @return array{character_id: string, instance_id: ?string}
   */
  public function resolveCanonicalCharacterIdentity(array $entity): array {
    $character_id = (string) (
      $entity['state']['metadata']['campaign_character_id']
      ?? $entity['state']['metadata']['character_id']
      ?? $entity['character_id']
      ?? $entity['state']['character_id']
      ?? $entity['entity_ref']['character_id']
      ?? ''
    );
    $instance_id = trim((string) (
      $entity['state']['metadata']['runtime_entity_id']
      ?? $entity['instance_id']
      ?? $entity['entity_instance_id']
      ?? ''
    ));

    return [
      'character_id' => $character_id,
      'instance_id' => $instance_id !== '' ? $instance_id : NULL,
    ];
  }

  /**
   * @return array{character_id: string, instance_id: ?string}
   */
  public function resolveCanonicalCharacterIdentityFromParticipantEntityRef(array $entity_ref, string $fallback_instance_id = ''): array {
    $character_id = (string) (
      $entity_ref['state']['metadata']['campaign_character_id']
      ?? $entity_ref['state']['metadata']['character_id']
      ?? $entity_ref['character_id']
      ?? $entity_ref['state']['character_id']
      ?? $entity_ref['entity_ref']['character_id']
      ?? ''
    );
    $instance_id = trim((string) (
      $entity_ref['state']['metadata']['runtime_entity_id']
      ?? $entity_ref['instance_id']
      ?? $entity_ref['entity_instance_id']
      ?? $fallback_instance_id
    ));

    return [
      'character_id' => $character_id,
      'instance_id' => $instance_id !== '' ? $instance_id : NULL,
    ];
  }

  public function syncCanonicalSpellcastingProjectionForActor(?int $encounter_id, string $actor_id, int $campaign_id, array &$dungeon_data, ?array $canonical_state = NULL): void {
    $actor_entity_index = $this->findDungeonEntityIndexByInstanceId($dungeon_data, $actor_id);
    $has_actor_entity = $actor_entity_index !== NULL
      && isset($dungeon_data['entities'][$actor_entity_index])
      && is_array($dungeon_data['entities'][$actor_entity_index]);
    $canonical_identity = ['character_id' => '', 'instance_id' => NULL];
    if ($has_actor_entity) {
      $canonical_identity = $this->resolveCanonicalCharacterIdentity($dungeon_data['entities'][$actor_entity_index]);
    }

    $encounter = NULL;
    $participant = NULL;
    if ($encounter_id) {
      $encounter = $this->encounterStore->loadEncounter((int) $encounter_id);
      if (is_array($encounter)) {
        $participant = $this->findEncounterParticipantByEntityId($encounter, $actor_id);
        if (
          (string) ($canonical_identity['character_id'] ?? '') === ''
          && is_array($participant)
        ) {
          $participant_entity_ref = !empty($participant['entity_ref']) ? json_decode((string) $participant['entity_ref'], TRUE) : [];
          if (is_array($participant_entity_ref)) {
            $canonical_identity = $this->resolveCanonicalCharacterIdentityFromParticipantEntityRef($participant_entity_ref, $actor_id);
          }
        }
      }
    }

    if (!is_array($canonical_state)) {
      $character_id = (string) ($canonical_identity['character_id'] ?? '');
      $instance_id = is_string($canonical_identity['instance_id'] ?? NULL) ? $canonical_identity['instance_id'] : NULL;
      if (!ctype_digit($character_id) || (int) $character_id <= 0) {
        return;
      }
      try {
        $canonical_state = $this->characterStateService->getState(
          $character_id,
          $campaign_id > 0 ? $campaign_id : NULL,
          $instance_id
        );
      }
      catch (\InvalidArgumentException $exception) {
        $this->logger->warning('Spellcasting projection sync skipped: @error', ['@error' => $exception->getMessage()]);
        return;
      }
      if (!is_array($canonical_state)) {
        return;
      }
    }

    if ($has_actor_entity) {
      $this->applyCanonicalSpellcastingResourcesToDungeonEntity($dungeon_data['entities'][$actor_entity_index], $canonical_state);
    }

    if (!$encounter_id) {
      return;
    }

    if (!is_array($participant)) {
      if (!is_array($encounter)) {
        $encounter = $this->encounterStore->loadEncounter((int) $encounter_id);
      }
      if (is_array($encounter)) {
        $participant = $this->findEncounterParticipantByEntityId($encounter, $actor_id);
      }
    }
    if (!$participant) {
      return;
    }

    $participant_id = (int) ($participant['id'] ?? 0);
    if ($participant_id <= 0) {
      return;
    }
    $participant_entity_ref = !empty($participant['entity_ref']) ? json_decode((string) $participant['entity_ref'], TRUE) : [];
    if (!is_array($participant_entity_ref)) {
      $participant_entity_ref = [];
    }
    $this->applyCanonicalSpellcastingResourcesToParticipantEntityRef($participant_entity_ref, $canonical_state);
    $this->persistEncounterParticipantEntityRef($participant_id, $participant_entity_ref);
  }

  public function syncCanonicalSurvivalProjectionForActor(?int $encounter_id, string $actor_id, int $campaign_id, array &$dungeon_data, ?array $canonical_state = NULL): void {
    $actor_entity_index = $this->findDungeonEntityIndexByInstanceId($dungeon_data, $actor_id);
    $has_actor_entity = $actor_entity_index !== NULL
      && isset($dungeon_data['entities'][$actor_entity_index])
      && is_array($dungeon_data['entities'][$actor_entity_index]);
    $canonical_identity = ['character_id' => '', 'instance_id' => NULL];
    if ($has_actor_entity) {
      $canonical_identity = $this->resolveCanonicalCharacterIdentity($dungeon_data['entities'][$actor_entity_index]);
    }

    $encounter = NULL;
    $participant = NULL;
    if ($encounter_id) {
      $encounter = $this->encounterStore->loadEncounter((int) $encounter_id);
      if (is_array($encounter)) {
        $participant = $this->findEncounterParticipantByEntityId($encounter, $actor_id);
        if (
          (string) ($canonical_identity['character_id'] ?? '') === ''
          && is_array($participant)
        ) {
          $participant_entity_ref = !empty($participant['entity_ref']) ? json_decode((string) $participant['entity_ref'], TRUE) : [];
          if (is_array($participant_entity_ref)) {
            $canonical_identity = $this->resolveCanonicalCharacterIdentityFromParticipantEntityRef($participant_entity_ref, $actor_id);
          }
        }
      }
    }

    if (!is_array($canonical_state)) {
      $character_id = (string) ($canonical_identity['character_id'] ?? '');
      $instance_id = is_string($canonical_identity['instance_id'] ?? NULL) ? $canonical_identity['instance_id'] : NULL;
      if (!ctype_digit($character_id) || (int) $character_id <= 0) {
        return;
      }
      try {
        $canonical_state = $this->characterStateService->getState(
          $character_id,
          $campaign_id > 0 ? $campaign_id : NULL,
          $instance_id
        );
      }
      catch (\InvalidArgumentException $exception) {
        $this->logger->warning('Survival projection sync skipped: @error', ['@error' => $exception->getMessage()]);
        return;
      }
      if (!is_array($canonical_state)) {
        return;
      }
    }

    if ($has_actor_entity) {
      $this->applyCanonicalSurvivalResourcesToDungeonEntity($dungeon_data['entities'][$actor_entity_index], $canonical_state);
    }

    if (!$encounter_id) {
      return;
    }

    if (!is_array($participant)) {
      if (!is_array($encounter)) {
        $encounter = $this->encounterStore->loadEncounter((int) $encounter_id);
      }
      if (is_array($encounter)) {
        $participant = $this->findEncounterParticipantByEntityId($encounter, $actor_id);
      }
    }
    if (!$participant) {
      return;
    }

    $participant_id = (int) ($participant['id'] ?? 0);
    if ($participant_id <= 0) {
      return;
    }
    $participant_entity_ref = !empty($participant['entity_ref']) ? json_decode((string) $participant['entity_ref'], TRUE) : [];
    if (!is_array($participant_entity_ref)) {
      $participant_entity_ref = [];
    }
    $this->applyCanonicalSurvivalResourcesToParticipantEntityRef($participant_entity_ref, $canonical_state);
    $this->persistEncounterParticipantEntityRef($participant_id, $participant_entity_ref);
  }

  public function applyCanonicalSurvivalResourcesToDungeonEntity(array &$entity, array $character_state): void {
    if (!isset($entity['state']) || !is_array($entity['state'])) {
      $entity['state'] = [];
    }

    $survival = $this->readCanonicalSurvivalStateFromCanonicalState($character_state);
    $entity['state']['days_without_food'] = (int) ($survival['daysWithoutFood'] ?? 0);
    $entity['state']['days_without_water'] = (int) ($survival['daysWithoutWater'] ?? 0);
    $entity['state']['starvation_damage_phase'] = !empty($survival['starvationDamagePhase']);
    $entity['state']['thirst_damage_phase'] = !empty($survival['thirstDamagePhase']);

    if (is_array($character_state['resources']['hitPoints'] ?? NULL)) {
      $current = (int) ($character_state['resources']['hitPoints']['current'] ?? ($entity['state']['hit_points']['current'] ?? 0));
      $max = (int) ($character_state['resources']['hitPoints']['max'] ?? ($entity['state']['hit_points']['max'] ?? $current));
      $entity['state']['hit_points']['current'] = $current;
      $entity['state']['hit_points']['max'] = $max;
      $entity['state']['hp_current'] = $current;
      $entity['state']['hp_max'] = $max;
      if (isset($entity['hit_points']) && is_array($entity['hit_points'])) {
        $entity['hit_points']['current'] = $current;
        $entity['hit_points']['max'] = $max;
      }
    }
  }

  public function applyCanonicalSurvivalResourcesToParticipantEntityRef(array &$entity_ref, array $character_state): void {
    if (!isset($entity_ref['state']) || !is_array($entity_ref['state'])) {
      $entity_ref['state'] = [];
    }

    $survival = $this->readCanonicalSurvivalStateFromCanonicalState($character_state);
    $entity_ref['state']['days_without_food'] = (int) ($survival['daysWithoutFood'] ?? 0);
    $entity_ref['state']['days_without_water'] = (int) ($survival['daysWithoutWater'] ?? 0);
    $entity_ref['state']['starvation_damage_phase'] = !empty($survival['starvationDamagePhase']);
    $entity_ref['state']['thirst_damage_phase'] = !empty($survival['thirstDamagePhase']);

    if (is_array($character_state['resources']['hitPoints'] ?? NULL)) {
      $current = (int) ($character_state['resources']['hitPoints']['current'] ?? 0);
      $max = (int) ($character_state['resources']['hitPoints']['max'] ?? $current);
      if (!isset($entity_ref['state']['hit_points']) || !is_array($entity_ref['state']['hit_points'])) {
        $entity_ref['state']['hit_points'] = [];
      }
      $entity_ref['state']['hit_points']['current'] = $current;
      $entity_ref['state']['hit_points']['max'] = $max;
      $entity_ref['state']['hp_current'] = $current;
      $entity_ref['state']['hp_max'] = $max;
    }
  }

  /**
   * @return array{daysWithoutFood:int,daysWithoutWater:int,starvationDamagePhase:bool,thirstDamagePhase:bool}
   */
  public function readCanonicalSurvivalStateFromCanonicalState(array $character_state): array {
    $survival = is_array($character_state['resources']['survival'] ?? NULL) ? $character_state['resources']['survival'] : [];

    return [
      'daysWithoutFood' => max(0, (int) ($survival['daysWithoutFood'] ?? 0)),
      'daysWithoutWater' => max(0, (int) ($survival['daysWithoutWater'] ?? 0)),
      'starvationDamagePhase' => (bool) ($survival['starvationDamagePhase'] ?? FALSE),
      'thirstDamagePhase' => (bool) ($survival['thirstDamagePhase'] ?? FALSE),
    ];
  }

  public function normalizeSpellSlotRankKey(string $slot_key): ?string {
    $normalized = strtolower(trim($slot_key));
    return match ($normalized) {
      '1', '1st', 'first' => '1',
      '2', '2nd', 'second' => '2',
      '3', '3rd', 'third' => '3',
      '4', '4th', 'fourth' => '4',
      '5', '5th', 'fifth' => '5',
      '6', '6th', 'sixth' => '6',
      '7', '7th', 'seventh' => '7',
      '8', '8th', 'eighth' => '8',
      '9', '9th', 'ninth' => '9',
      '10', '10th', 'tenth' => '10',
      default => NULL,
    };
  }

  public function resolveEffectiveCantripLevel(?array $canonical_state, array $participant_entity_ref): int {
    $levels = [];

    $canonical_slots = is_array($canonical_state['resources']['spellSlots'] ?? NULL)
      ? $canonical_state['resources']['spellSlots']
      : [];
    foreach ($canonical_slots as $rank_key => $slot_state) {
      if (!is_array($slot_state)) {
        continue;
      }
      $normalized_rank = $this->normalizeSpellSlotRankKey((string) $rank_key);
      if ($normalized_rank === NULL) {
        continue;
      }
      $max = (int) ($slot_state['max'] ?? $slot_state['current'] ?? 0);
      if ($max > 0) {
        $levels[] = (int) $normalized_rank;
      }
    }

    if ($levels === []) {
      $participant_slots = is_array($participant_entity_ref['spell_slots'] ?? NULL)
        ? $participant_entity_ref['spell_slots']
        : [];
      foreach ($participant_slots as $rank_key => $slot_state) {
        if (!is_array($slot_state)) {
          continue;
        }
        $normalized_rank = $this->normalizeSpellSlotRankKey((string) $rank_key);
        if ($normalized_rank === NULL) {
          continue;
        }
        $max = (int) ($slot_state['max'] ?? 0);
        if ($max > 0) {
          $levels[] = (int) $normalized_rank;
        }
      }
    }

    return $levels !== [] ? max($levels) : 1;
  }

  public function resolveParticipantFocusPointCurrent(array $participant_entity_ref): int {
    $candidates = [
      $participant_entity_ref['focus_points'] ?? NULL,
      $participant_entity_ref['state']['focus_points']['current'] ?? NULL,
      $participant_entity_ref['state']['resources']['focusPoints']['current'] ?? NULL,
    ];
    foreach ($candidates as $candidate) {
      if (is_numeric($candidate)) {
        return max(0, (int) $candidate);
      }
    }
    return 0;
  }

  public function applyCanonicalSpellcastingResourcesToDungeonEntity(array &$entity, array $character_state): void {
    $resources = is_array($character_state['resources'] ?? NULL) ? $character_state['resources'] : [];
    if (!isset($entity['state']) || !is_array($entity['state'])) {
      $entity['state'] = [];
    }

    $spell_slots = is_array($resources['spellSlots'] ?? NULL) ? $resources['spellSlots'] : [];
    if ($spell_slots !== []) {
      if (!isset($entity['state']['resources']) || !is_array($entity['state']['resources'])) {
        $entity['state']['resources'] = [];
      }
      $entity['state']['resources']['spellSlots'] = $spell_slots;
      $entity['state']['spell_slots'] = $this->buildLegacySpellSlotProjection($spell_slots);
    }

    if (is_array($resources['focusPoints'] ?? NULL)) {
      $focus_max = max(0, (int) ($resources['focusPoints']['max'] ?? 0));
      $focus_current = max(0, min((int) ($resources['focusPoints']['current'] ?? $focus_max), $focus_max));
      $this->writeEntityFocusPoints($entity, $focus_current, $focus_max);
    }
  }

  public function applyCanonicalSpellcastingResourcesToParticipantEntityRef(array &$entity_ref, array $character_state): void {
    $resources = is_array($character_state['resources'] ?? NULL) ? $character_state['resources'] : [];
    if (!isset($entity_ref['state']) || !is_array($entity_ref['state'])) {
      $entity_ref['state'] = [];
    }
    if (!isset($entity_ref['state']['resources']) || !is_array($entity_ref['state']['resources'])) {
      $entity_ref['state']['resources'] = [];
    }

    $spell_slots = is_array($resources['spellSlots'] ?? NULL) ? $resources['spellSlots'] : [];
    if ($spell_slots !== []) {
      $legacy_projection = $this->buildLegacySpellSlotProjection($spell_slots);
      $entity_ref['state']['resources']['spellSlots'] = $spell_slots;
      $entity_ref['state']['spell_slots'] = $legacy_projection;
      $entity_ref['spell_slots'] = $legacy_projection;
    }

    if (is_array($resources['focusPoints'] ?? NULL)) {
      $focus_max = max(0, (int) ($resources['focusPoints']['max'] ?? 0));
      $focus_current = max(0, min((int) ($resources['focusPoints']['current'] ?? $focus_max), $focus_max));
      $entity_ref['state']['resources']['focusPoints'] = [
        'current' => $focus_current,
        'max' => $focus_max,
      ];
      $entity_ref['state']['focus_points'] = [
        'current' => $focus_current,
        'max' => $focus_max,
      ];
      $entity_ref['focus_points'] = $focus_current;
    }
  }

  public function buildLegacySpellSlotProjection(array $spell_slots): array {
    $projection = [];
    $append_projection = function (string $rank_key, array $slot_state) use (&$projection): void {
      $normalized_rank = $this->normalizeSpellSlotRankKey($rank_key);
      if ($normalized_rank === NULL) {
        return;
      }
      $max = max(0, (int) ($slot_state['max'] ?? 0));
      $current = max(0, min((int) ($slot_state['current'] ?? $max), $max));
      $projection[$normalized_rank] = [
        'max' => $max,
        'current' => $current,
        'used' => max(0, $max - $current),
      ];
    };

    foreach ($spell_slots as $rank_key => $slot_state) {
      if (!is_array($slot_state)) {
        continue;
      }
      if (array_key_exists('max', $slot_state) || array_key_exists('current', $slot_state)) {
        $append_projection((string) $rank_key, $slot_state);
        continue;
      }
      foreach ($slot_state as $nested_rank_key => $nested_slot_state) {
        if (is_array($nested_slot_state)) {
          $append_projection((string) $nested_rank_key, $nested_slot_state);
        }
      }
    }

    if ($projection !== []) {
      ksort($projection, SORT_NATURAL);
    }
    return $projection;
  }

  public function persistEncounterParticipantEntityRef(int $participant_id, array $entity_ref): void {
    if ($participant_id <= 0) {
      return;
    }
    try {
      $this->encounterStore->updateParticipant($participant_id, ['entity_ref' => json_encode($entity_ref)]);
    }
    catch (\Throwable $e) {
      $this->logger->warning('Encounter participant spell resource sync failed: @error', ['@error' => $e->getMessage()]);
    }
  }

  public function applyCanonicalHealing(array &$character_state, int $delta): void {
    $resources = $character_state['resources'] ?? [];
    $current = (int) ($resources['hitPoints']['current'] ?? 0);
    $max = (int) ($resources['hitPoints']['max'] ?? $current);
    $resources['hitPoints']['current'] = max(0, min($max, $current + $delta));
    $resources['hitPoints']['max'] = $max;
    $character_state['resources'] = $resources;
  }

  public function restoreCanonicalSpellSlots(array &$character_state): void {
    if (empty($character_state['resources']['spellSlots']) || !is_array($character_state['resources']['spellSlots'])) {
      return;
    }
    foreach ($character_state['resources']['spellSlots'] as &$slot_group) {
      if (!is_array($slot_group)) {
        continue;
      }
      if (array_key_exists('max', $slot_group)) {
        $slot_group['current'] = (int) ($slot_group['max'] ?? $slot_group['current'] ?? 0);
      }
      else {
        foreach ($slot_group as &$slot_row) {
          if (is_array($slot_row) && array_key_exists('max', $slot_row)) {
            $slot_row['current'] = (int) ($slot_row['max'] ?? $slot_row['current'] ?? 0);
          }
        }
        unset($slot_row);
      }
    }
    unset($slot_group);
  }

  public function restoreCanonicalFocusPoints(array &$character_state): void {
    if (!is_array($character_state['resources']['focusPoints'] ?? NULL)) {
      return;
    }
    $max = (int) ($character_state['resources']['focusPoints']['max'] ?? 0);
    $character_state['resources']['focusPoints']['current'] = $max;
  }

  public function applyCanonicalDailyPreparationConditionRecovery(array &$character_state): void {
    $expired_condition_codes = $this->expireActorEffectInstancesOnDailyPreparations($character_state);
    $conditions = $character_state['conditions'] ?? [];
    foreach ($conditions as $index => $condition) {
      if (is_array($condition)) {
        $code = $this->normalizeConditionCode($condition);
        if ($code !== '' && in_array($code, $expired_condition_codes, TRUE)) {
          unset($conditions[$index]);
          continue;
        }
        if ($this->expiresOnNextDailyPreparations($condition)) {
          unset($conditions[$index]);
          continue;
        }
        $name = strtolower((string) ($condition['name'] ?? ''));
        if ($name === 'doomed') {
          $value = max(0, (int) ($condition['value'] ?? 1) - 1);
          if ($value <= 0) {
            unset($conditions[$index]);
          }
          else {
            $conditions[$index]['value'] = $value;
          }
        }
        if ($name === 'wounded') {
          unset($conditions[$index]);
        }
      }
      elseif (strtolower((string) $condition) === 'wounded') {
        unset($conditions[$index]);
      }
    }
    $character_state['conditions'] = array_values($conditions);
  }

  public function readEntityFocusPoints(array $entity, string $field): int {
    $candidates = [
      $entity['state']['resources']['focusPoints'][$field] ?? NULL,
      $entity['state']['focus_points'][$field] ?? NULL,
      $field === 'current' ? ($entity['state']['focus_points'] ?? NULL) : NULL,
      $entity['focus_points'][$field] ?? NULL,
    ];
    foreach ($candidates as $candidate) {
      if (is_numeric($candidate)) {
        return max(0, (int) $candidate);
      }
    }
    return 0;
  }

  public function writeEntityFocusPoints(array &$entity, int $current, int $max): void {
    $entity['state']['resources']['focusPoints']['current'] = $current;
    $entity['state']['resources']['focusPoints']['max'] = $max;
    $entity['state']['focus_points']['current'] = $current;
    $entity['state']['focus_points']['max'] = $max;
    if (isset($entity['focus_points']) && !is_array($entity['focus_points'])) {
      $entity['focus_points'] = $current;
    }
  }

  public function resolveCharacterFocusPoints(?array $character_state, array $entity, string $field): int {
    if (is_array($character_state) && isset($character_state['resources']['focusPoints'][$field]) && is_numeric($character_state['resources']['focusPoints'][$field])) {
      return max(0, (int) $character_state['resources']['focusPoints'][$field]);
    }
    return $this->readEntityFocusPoints($entity, $field);
  }

  public function resolveCharacterLevel(?array $character_state, array $entity): int {
    if (is_array($character_state) && isset($character_state['basicInfo']['level']) && is_numeric($character_state['basicInfo']['level'])) {
      return max(1, (int) $character_state['basicInfo']['level']);
    }
    return max(1, (int) ($entity['state']['level'] ?? $entity['level'] ?? 1));
  }

  public function resolveCharacterConstitutionModifier(?array $character_state, array $entity): int {
    if (is_array($character_state)) {
      if (isset($character_state['abilities']['constitution']['modifier']) && is_numeric($character_state['abilities']['constitution']['modifier'])) {
        return (int) $character_state['abilities']['constitution']['modifier'];
      }
      if (isset($character_state['abilities']['constitution']) && is_numeric($character_state['abilities']['constitution'])) {
        return (int) floor(((int) $character_state['abilities']['constitution'] - 10) / 2);
      }
      if (isset($character_state['abilityScores']['constitution']['modifier']) && is_numeric($character_state['abilityScores']['constitution']['modifier'])) {
        return (int) $character_state['abilityScores']['constitution']['modifier'];
      }
    }
    return (int) ($entity['state']['constitution_modifier'] ?? 0);
  }

  public function restoreEntitySpellSlots(array &$entity): void {
    if (!empty($entity['state']['resources']['spellSlots']) && is_array($entity['state']['resources']['spellSlots'])) {
      foreach ($entity['state']['resources']['spellSlots'] as &$slot_group) {
        if (!is_array($slot_group)) {
          continue;
        }
        if (array_key_exists('max', $slot_group)) {
          $slot_group['current'] = (int) ($slot_group['max'] ?? $slot_group['current'] ?? 0);
        }
      }
      unset($slot_group);
    }
    if (!empty($entity['state']['spell_slots']) && is_array($entity['state']['spell_slots'])) {
      foreach ($entity['state']['spell_slots'] as &$slot_group) {
        if (!is_array($slot_group)) {
          continue;
        }
        if (array_key_exists('max', $slot_group)) {
          $slot_group['current'] = (int) ($slot_group['max'] ?? $slot_group['current'] ?? 0);
          if (array_key_exists('used', $slot_group)) {
            $slot_group['used'] = 0;
          }
        }
      }
      unset($slot_group);
    }
  }

  public function applyDailyPreparationConditionRecovery(array &$entity): array {
    $expired_condition_codes = $this->expireActorEffectInstancesOnDailyPreparations($entity['state'] ?? []);
    $changes = [];
    if (!isset($entity['state']['conditions']) || !is_array($entity['state']['conditions'])) {
      return $changes;
    }
    foreach ($entity['state']['conditions'] as $key => $condition) {
      if (is_array($condition)) {
        $code = $this->normalizeConditionCode($condition);
        if ($code !== '' && in_array($code, $expired_condition_codes, TRUE)) {
          unset($entity['state']['conditions'][$key]);
          $changes[] = sprintf('expired %s', str_replace('_', ' ', $code));
          continue;
        }
        if ($this->expiresOnNextDailyPreparations($condition)) {
          unset($entity['state']['conditions'][$key]);
          $changes[] = sprintf('expired %s', strtolower((string) ($condition['name'] ?? $condition['condition_type'] ?? $condition['id'] ?? 'condition')));
          continue;
        }
        $name = strtolower((string) ($condition['name'] ?? ''));
        if ($name === 'doomed') {
          $value = max(0, (int) ($condition['value'] ?? 1) - 1);
          if ($value <= 0) {
            unset($entity['state']['conditions'][$key]);
            $changes[] = 'removed doomed';
          }
          else {
            $entity['state']['conditions'][$key]['value'] = $value;
            $changes[] = sprintf('reduced doomed to %d', $value);
          }
        }
        if ($name === 'wounded') {
          unset($entity['state']['conditions'][$key]);
          $changes[] = 'removed wounded';
        }
      }
      elseif (strtolower((string) $condition) === 'wounded') {
        unset($entity['state']['conditions'][$key]);
        $changes[] = 'removed wounded';
      }
    }
    $entity['state']['conditions'] = array_values($entity['state']['conditions']);
    return $changes;
  }

  /**
   * Expires actor effect instances on daily preparations and returns condition codes.
   *
   * @return array<int,string>
   */
  protected function expireActorEffectInstancesOnDailyPreparations(array $state): array {
    if (!$this->effectLifecycleService instanceof EffectLifecycleService) {
      return [];
    }

    $character_id = trim((string) ($state['characterId'] ?? $state['character_id'] ?? ''));
    if ($character_id === '') {
      return [];
    }

    $campaign_id = isset($state['campaignId']) && $state['campaignId'] !== ''
      ? (int) $state['campaignId']
      : (isset($state['campaign_id']) && $state['campaign_id'] !== '' ? (int) $state['campaign_id'] : NULL);
    $instance_id = isset($state['instanceId']) && $state['instanceId'] !== ''
      ? (string) $state['instanceId']
      : (isset($state['instance_id']) && $state['instance_id'] !== '' ? (string) $state['instance_id'] : NULL);

    $result = $this->effectLifecycleService->expireActorEffectsForTrigger(
      $character_id,
      $campaign_id,
      $instance_id,
      EffectDefinitionRegistryService::TRIGGER_NEXT_DAILY_PREPARATIONS
    );

    return array_values(array_filter(array_map(
      static fn ($code): string => strtolower(trim((string) $code)),
      is_array($result['expired_condition_codes'] ?? NULL) ? $result['expired_condition_codes'] : []
    )));
  }

  protected function expiresOnNextDailyPreparations(array $condition): bool {
    $duration = strtolower(trim((string) ($condition['duration'] ?? '')));
    return $duration === 'until_next_daily_preparations' || $duration === 'next_daily_preparations';
  }

  /**
   * Normalizes condition row shape to canonical condition code.
   */
  protected function normalizeConditionCode(array $condition): string {
    $raw_code = (string) ($condition['condition_type'] ?? $condition['id'] ?? $condition['name'] ?? '');
    return strtolower(str_replace([' ', '-'], '_', trim($raw_code)));
  }

}
