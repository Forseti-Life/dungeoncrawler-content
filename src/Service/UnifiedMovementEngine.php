<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Shared movement packet seam for stride/forced movement convergence.
 */
class UnifiedMovementEngine {

  protected CombatResolutionContractService $combatResolutionContractService;

  public function __construct(CombatResolutionContractService $combat_resolution_contract_service) {
    $this->combatResolutionContractService = $combat_resolution_contract_service;
  }

  /**
   * @param array<string, int> $from_hex
   * @param array<string, int> $to_hex
   * @param array<string, mixed> $metadata
   *
   * @return array<string, mixed>
   */
  public function buildMovementResolutionPacket(
    string $actor_entity_ref,
    string $movement_mode,
    array $from_hex,
    array $to_hex,
    int $distance_ft,
    int $action_cost,
    array $metadata = []
  ): array {
    return $this->combatResolutionContractService->buildMovementResolutionPacket(
      $actor_entity_ref,
      $movement_mode,
      $from_hex,
      $to_hex,
      $distance_ft,
      $action_cost,
      $metadata
    );
  }

}
