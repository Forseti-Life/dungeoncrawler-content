<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Shared damage-mutation seam for strike/spell onboarding.
 */
class UnifiedDamageEngine {

  protected CombatEncounterStore $encounterStore;
  protected NumberGenerationService $numberGenerationService;
  protected CombatResolutionContractService $combatResolutionContractService;
  protected ?HPManager $hpManager;

  public function __construct(
    CombatEncounterStore $encounter_store,
    NumberGenerationService $number_generation_service,
    CombatResolutionContractService $combat_resolution_contract_service,
    ?HPManager $hp_manager = NULL
  ) {
    $this->encounterStore = $encounter_store;
    $this->numberGenerationService = $number_generation_service;
    $this->combatResolutionContractService = $combat_resolution_contract_service;
    $this->hpManager = $hp_manager;
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

}
