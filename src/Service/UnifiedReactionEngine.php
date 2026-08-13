<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Shared reaction/interrupt packet seam for encounter action flows.
 */
class UnifiedReactionEngine {

  protected CombatResolutionContractService $combatResolutionContractService;

  public function __construct(CombatResolutionContractService $combat_resolution_contract_service) {
    $this->combatResolutionContractService = $combat_resolution_contract_service;
  }

  /**
   * @param array<string, mixed> $metadata
   *
   * @return array<string, mixed>
   */
  public function buildReactionResolutionPacket(
    string $reactor_entity_ref,
    string $triggering_actor_entity_ref,
    string $reaction_type,
    string $outcome,
    array $metadata = []
  ): array {
    return $this->combatResolutionContractService->buildReactionResolutionPacket(
      $reactor_entity_ref,
      $triggering_actor_entity_ref,
      $reaction_type,
      $outcome,
      $metadata
    );
  }

}
