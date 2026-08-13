<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Shared damage-mutation seam for strike/spell onboarding.
 */
class UnifiedDamageEngine {

  protected CombatEncounterStore $encounterStore;
  protected NumberGenerationService $numberGenerationService;
  protected CombatResolutionContractService $combatResolutionContractService;

  public function __construct(
    CombatEncounterStore $encounter_store,
    NumberGenerationService $number_generation_service,
    CombatResolutionContractService $combat_resolution_contract_service
  ) {
    $this->encounterStore = $encounter_store;
    $this->numberGenerationService = $number_generation_service;
    $this->combatResolutionContractService = $combat_resolution_contract_service;
  }

  /**
   * Generic canonical damage packet builder for converged mutation lanes.
   *
   * @param array<int, string> $tags
   * @param array<string, mixed> $metadata
   *
   * @return array<string, mixed>
   */
  public function buildDamageApplicationPacket(
    string $source_entity_ref,
    string $target_entity_ref,
    string $delivery_mode,
    int $amount,
    string $damage_type,
    array $tags = [],
    array $metadata = []
  ): array {
    return $this->combatResolutionContractService->buildDamageApplicationPacket(
      $source_entity_ref,
      $target_entity_ref,
      $delivery_mode,
      $amount,
      $damage_type,
      $tags,
      $metadata
    );
  }

  /**
   * Build canonical strike damage packet + mutation projection.
   *
   * @param array<string, mixed> $weapon
   * @param array<string, mixed> $attack_result
   * @param array<string, mixed> $target_participant
   * @param array<string, mixed> $updated_target
   *
   * @return array{damage_packet: ?array<string, mixed>, mutations: array<int, array<string, mixed>>}
   */
  public function resolveStrikeDamage(
    string $actor_id,
    string $target_id,
    int $encounter_id,
    array $weapon,
    array $attack_result,
    array $target_participant,
    array $updated_target
  ): array {
    if (empty($attack_result['damage_dealt'])) {
      return ['damage_packet' => NULL, 'mutations' => []];
    }

    $damage_amount = (int) ($attack_result['damage_dealt'] ?? 0);
    $damage_type = (string) ($weapon['damage_type'] ?? 'physical');
    $damage_packet = $this->combatResolutionContractService->buildDamageApplicationPacket(
      $actor_id,
      $target_id,
      'attack',
      $damage_amount,
      $damage_type,
      [$weapon['weapon_category'] ?? 'strike'],
      [
        'encounter_id' => $encounter_id,
        'weapon_name' => (string) ($weapon['name'] ?? $weapon['weapon_name'] ?? 'strike'),
        'degree' => (string) ($attack_result['degree'] ?? ''),
        'roll' => $attack_result['roll'] ?? NULL,
        'total' => $attack_result['total'] ?? NULL,
        'target_ac' => $attack_result['target_ac'] ?? NULL,
      ]
    );

    return [
      'damage_packet' => $damage_packet,
      'mutations' => [[
        'entity' => $target_id,
        'field' => 'hp',
        'from' => $target_participant['hp'] ?? NULL,
        'to' => $updated_target['hp'] ?? ($attack_result['damage_result']['new_hp'] ?? NULL),
      ]],
    ];
  }

  /**
   * Apply direct spell damage for deterministic spell lanes currently onboarded.
   *
   * @return array<string, mixed>
   */
  public function applySupportedSpellDamageToEncounterTarget(
    int $encounter_id,
    string $source_actor_id,
    string $target_id,
    string $spell_id,
    string $spell_name,
    int $cast_rank,
    int $action_cost
  ): array {
    $normalized_spell = strtolower(trim($spell_id !== '' ? $spell_id : $spell_name));
    $canonical_spell = preg_replace('/[^a-z0-9]+/', '', $normalized_spell) ?? '';
    if ($canonical_spell !== 'magicmissile') {
      return [];
    }

    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $target_participant = $encounter ? $this->findEncounterParticipantByEntityId($encounter, $target_id) : NULL;
    $target_participant_id = (int) ($target_participant['id'] ?? 0);
    if (!$target_participant || $target_participant_id <= 0 || !is_numeric($target_participant['hp'] ?? NULL)) {
      return [];
    }

    $target_hp_before = (int) $target_participant['hp'];
    $rank = max(1, (int) $cast_rank);
    $actions_spent = max(1, min(3, (int) $action_cost));
    $missiles_per_action = 1 + max(0, (int) floor(($rank - 1) / 2));
    $missiles_fired = max(1, $actions_spent * $missiles_per_action);

    $damage_total = 0;
    for ($i = 0; $i < $missiles_fired; $i++) {
      $damage_total += (int) $this->numberGenerationService->rollPathfinderDie(4) + 1;
    }

    $target_hp_after = max(0, $target_hp_before - $damage_total);
    $is_defeated = $target_hp_after <= 0;

    $this->encounterStore->updateParticipant($target_participant_id, [
      'hp' => $target_hp_after,
      'is_defeated' => $is_defeated ? 1 : 0,
    ]);

    return [
      'damage' => $damage_total,
      'damage_type' => 'force',
      'damage_packet' => $this->combatResolutionContractService->buildDamageApplicationPacket(
        $source_actor_id,
        $target_id,
        'spell',
        $damage_total,
        'force',
        ['magic_missile'],
        [
          'spell_id' => $spell_id !== '' ? $spell_id : $spell_name,
          'cast_rank' => $rank,
          'actions_spent' => $actions_spent,
          'missiles_fired' => $missiles_fired,
          'target_hp_before' => $target_hp_before,
          'target_hp_after' => $target_hp_after,
        ]
      ),
      'missiles_fired' => $missiles_fired,
      'is_defeated' => $is_defeated,
      'mutations' => [[
        'entity' => $target_id,
        'field' => 'hp',
        'from' => $target_hp_before,
        'to' => $target_hp_after,
      ]],
    ];
  }

  /**
   * @param array<string, mixed> $encounter
   *
   * @return array<string, mixed>|null
   */
  protected function findEncounterParticipantByEntityId(array $encounter, string $entity_id): ?array {
    foreach (($encounter['participants'] ?? []) as $participant) {
      if ((string) ($participant['entity_id'] ?? '') === $entity_id) {
        return is_array($participant) ? $participant : NULL;
      }
    }
    return NULL;
  }

}
