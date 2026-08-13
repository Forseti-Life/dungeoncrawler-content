<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Shared state/effect packet seam for condition/effect lifecycle convergence.
 */
class UnifiedStateEffectEngine {

  protected CombatResolutionContractService $combatResolutionContractService;

  public function __construct(CombatResolutionContractService $combat_resolution_contract_service) {
    $this->combatResolutionContractService = $combat_resolution_contract_service;
  }

  /**
   * @param array<string, mixed> $metadata
   *
   * @return array<string, mixed>
   */
  public function buildStateEffectChangePacket(
    string $actor_entity_ref,
    string $target_entity_ref,
    string $effect_kind,
    string $effect_name,
    string $change_type,
    ?int $value = NULL,
    array $metadata = []
  ): array {
    return $this->combatResolutionContractService->buildStateEffectChangePacket(
      $actor_entity_ref,
      $target_entity_ref,
      $effect_kind,
      $effect_name,
      $change_type,
      $value,
      $metadata
    );
  }

}
