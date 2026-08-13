<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Canonical contract helpers for combat-resolution payloads.
 */
class CombatResolutionContractService {

  public const EXECUTION_REQUEST_CONTRACT_VERSION = 'combat.execution_request.v1';
  public const RESOLUTION_ENVELOPE_CONTRACT_VERSION = 'combat.resolution_envelope.v1';
  public const DAMAGE_PACKET_CONTRACT_VERSION = 'combat.damage_packet.v1';
  public const MOVEMENT_PACKET_CONTRACT_VERSION = 'combat.movement_packet.v1';
  public const STATE_EFFECT_PACKET_CONTRACT_VERSION = 'combat.state_effect_packet.v1';
  public const REACTION_PACKET_CONTRACT_VERSION = 'combat.reaction_packet.v1';

  /**
   * Build a canonical combat execution request payload.
   *
   * @param array<string, mixed> $params
   *
   * @return array<string, mixed>
   */
  public function buildCombatExecutionRequest(
    string $action_type,
    string $actor_entity_ref,
    ?string $target_entity_ref = NULL,
    array $params = []
  ): array {
    $action_type = strtolower(trim($action_type));
    $actor_entity_ref = trim($actor_entity_ref);
    $target_entity_ref = is_string($target_entity_ref) ? trim($target_entity_ref) : NULL;
    if ($action_type === '') {
      throw new \InvalidArgumentException('Execution request action_type is required.');
    }
    if ($actor_entity_ref === '') {
      throw new \InvalidArgumentException('Execution request actor_entity_ref is required.');
    }

    return [
      'contract_version' => self::EXECUTION_REQUEST_CONTRACT_VERSION,
      'kind' => 'combat_execution_request',
      'action_type' => $action_type,
      'actor_entity_ref' => $actor_entity_ref,
      'target_entity_ref' => $target_entity_ref !== '' ? $target_entity_ref : NULL,
      'params' => $params,
    ];
  }

  /**
   * Build a canonical damage-application packet.
   *
   * @param array<string, mixed> $metadata
   * @param array<int, string> $tags
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
    $source_entity_ref = trim($source_entity_ref);
    $target_entity_ref = trim($target_entity_ref);
    $delivery_mode = strtolower(trim($delivery_mode));
    $damage_type = strtolower(trim($damage_type));

    if ($source_entity_ref === '') {
      throw new \InvalidArgumentException('Damage packet source_entity_ref is required.');
    }
    if ($target_entity_ref === '') {
      throw new \InvalidArgumentException('Damage packet target_entity_ref is required.');
    }
    if ($delivery_mode === '') {
      throw new \InvalidArgumentException('Damage packet delivery_mode is required.');
    }
    if ($amount < 0) {
      throw new \InvalidArgumentException('Damage packet amount cannot be negative.');
    }
    if ($damage_type === '') {
      throw new \InvalidArgumentException('Damage packet damage_type is required.');
    }

    return [
      'contract_version' => self::DAMAGE_PACKET_CONTRACT_VERSION,
      'kind' => 'damage_application',
      'source_entity_ref' => $source_entity_ref,
      'target_entity_ref' => $target_entity_ref,
      'delivery_mode' => $delivery_mode,
      'damage_type' => $damage_type,
      'amount' => $amount,
      'tags' => array_values(array_filter(array_map(static function ($tag): string {
        return strtolower(trim((string) $tag));
      }, $tags), static fn (string $tag): bool => $tag !== '')),
      'metadata' => $metadata,
    ];
  }

  /**
   * Build a canonical movement-resolution packet.
   *
   * @param array<string, mixed> $metadata
   * @param array<string, int> $from_hex
   * @param array<string, int> $to_hex
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
    $actor_entity_ref = trim($actor_entity_ref);
    $movement_mode = strtolower(trim($movement_mode));
    if ($actor_entity_ref === '') {
      throw new \InvalidArgumentException('Movement packet actor_entity_ref is required.');
    }
    if ($movement_mode === '') {
      throw new \InvalidArgumentException('Movement packet movement_mode is required.');
    }
    if (!isset($from_hex['q'], $from_hex['r']) || !is_numeric($from_hex['q']) || !is_numeric($from_hex['r'])) {
      throw new \InvalidArgumentException('Movement packet from_hex must include numeric q/r.');
    }
    if (!isset($to_hex['q'], $to_hex['r']) || !is_numeric($to_hex['q']) || !is_numeric($to_hex['r'])) {
      throw new \InvalidArgumentException('Movement packet to_hex must include numeric q/r.');
    }
    if ($distance_ft < 0) {
      throw new \InvalidArgumentException('Movement packet distance_ft cannot be negative.');
    }
    if ($action_cost < 0) {
      throw new \InvalidArgumentException('Movement packet action_cost cannot be negative.');
    }

    return [
      'contract_version' => self::MOVEMENT_PACKET_CONTRACT_VERSION,
      'kind' => 'movement_resolution',
      'actor_entity_ref' => $actor_entity_ref,
      'movement_mode' => $movement_mode,
      'from_hex' => [
        'q' => (int) $from_hex['q'],
        'r' => (int) $from_hex['r'],
      ],
      'to_hex' => [
        'q' => (int) $to_hex['q'],
        'r' => (int) $to_hex['r'],
      ],
      'distance_ft' => $distance_ft,
      'action_cost' => $action_cost,
      'metadata' => $metadata,
    ];
  }

  /**
   * Build a canonical state-effect change packet.
   *
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
    $actor_entity_ref = trim($actor_entity_ref);
    $target_entity_ref = trim($target_entity_ref);
    $effect_kind = strtolower(trim($effect_kind));
    $effect_name = strtolower(trim($effect_name));
    $change_type = strtolower(trim($change_type));
    if ($actor_entity_ref === '') {
      throw new \InvalidArgumentException('State-effect packet actor_entity_ref is required.');
    }
    if ($target_entity_ref === '') {
      throw new \InvalidArgumentException('State-effect packet target_entity_ref is required.');
    }
    if ($effect_kind === '') {
      throw new \InvalidArgumentException('State-effect packet effect_kind is required.');
    }
    if ($effect_name === '') {
      throw new \InvalidArgumentException('State-effect packet effect_name is required.');
    }
    if ($change_type === '') {
      throw new \InvalidArgumentException('State-effect packet change_type is required.');
    }

    return [
      'contract_version' => self::STATE_EFFECT_PACKET_CONTRACT_VERSION,
      'kind' => 'state_effect_change',
      'actor_entity_ref' => $actor_entity_ref,
      'target_entity_ref' => $target_entity_ref,
      'effect_kind' => $effect_kind,
      'effect_name' => $effect_name,
      'change_type' => $change_type,
      'value' => $value,
      'metadata' => $metadata,
    ];
  }

  /**
   * Build a canonical reaction packet.
   *
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
    $reactor_entity_ref = trim($reactor_entity_ref);
    $triggering_actor_entity_ref = trim($triggering_actor_entity_ref);
    $reaction_type = strtolower(trim($reaction_type));
    $outcome = strtolower(trim($outcome));
    if ($reactor_entity_ref === '') {
      throw new \InvalidArgumentException('Reaction packet reactor_entity_ref is required.');
    }
    if ($triggering_actor_entity_ref === '') {
      throw new \InvalidArgumentException('Reaction packet triggering_actor_entity_ref is required.');
    }
    if ($reaction_type === '') {
      throw new \InvalidArgumentException('Reaction packet reaction_type is required.');
    }
    if ($outcome === '') {
      throw new \InvalidArgumentException('Reaction packet outcome is required.');
    }

    return [
      'contract_version' => self::REACTION_PACKET_CONTRACT_VERSION,
      'kind' => 'reaction_resolution',
      'reactor_entity_ref' => $reactor_entity_ref,
      'triggering_actor_entity_ref' => $triggering_actor_entity_ref,
      'reaction_type' => $reaction_type,
      'outcome' => $outcome,
      'metadata' => $metadata,
    ];
  }

  /**
   * Build a canonical action resolution envelope.
   *
   * @param array<string, mixed> $request
   * @param array<int, array<string, mixed>> $packets
   * @param array<string, mixed> $result
   *
   * @return array<string, mixed>
   */
  public function buildResolutionEnvelope(array $request, array $packets, array $result = []): array {
    return [
      'contract_version' => self::RESOLUTION_ENVELOPE_CONTRACT_VERSION,
      'kind' => 'combat_resolution_envelope',
      'request' => $request,
      'packets' => array_values(array_filter($packets, 'is_array')),
      'result' => $result,
    ];
  }

}
