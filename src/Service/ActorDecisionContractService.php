<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Canonical helpers for actor decision contract normalization and hashing.
 */
class ActorDecisionContractService {

  /**
   * Canonical actor context contract version identifier.
   */
  public const ACTOR_CONTEXT_CONTRACT_VERSION = 'actor_context_v1';

  /**
   * Canonical action contract envelope version identifier.
   */
  public const ACTION_CONTRACT_VERSION = 'action_contract_v1';

  /**
   * Canonical actor decision envelope version identifier.
   */
  public const ACTOR_DECISION_CONTRACT_VERSION = 'actor_decision_v1';

  /**
   * Canonical actor execution envelope version identifier.
   */
  public const ACTOR_EXECUTION_CONTRACT_VERSION = 'actor_execution_v1';

  /**
   * Normalize action IDs for deterministic contract payload hashing.
   *
   * @param array<int, mixed> $actions
   *   Candidate action IDs.
   *
   * @return array<int, string>
   *   Lowercase, trimmed, unique action IDs.
   */
  public function normalizeActionIds(array $actions): array {
    return array_values(array_unique(array_filter(array_map(
      static fn($action): string => strtolower(trim((string) $action)),
      $actions
    ))));
  }

  /**
   * Build deterministic hash for an actor action contract payload.
   *
   * @param array<string, mixed> $action_contract
   *   Canonical action contract payload.
   * @param array<int, mixed> $allowed_actions
   *   Candidate allowed action IDs.
   */
  public function buildActionContractHash(array $action_contract, array $allowed_actions): string {
    $payload = [
      'available_actions' => $this->normalizeActionIds($allowed_actions),
      'action_contract' => $action_contract,
    ];
    return hash('sha256', (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
  }

  /**
   * Build a unified actor decision envelope from an action recommendation.
   *
   * @param array<string, mixed> $recommendation
   *   Validated recommendation payload.
   * @param array<string, mixed> $context
   *   Actor context envelope.
   * @param string $provider
   *   Decision provider name.
   *
   * @return array<string, mixed>
   *   Unified actor decision envelope.
   */
  public function buildActorDecisionEnvelopeFromRecommendation(array $recommendation, array $context, string $provider = ''): array {
    $recommended_action = is_array($recommendation['recommended_action'] ?? NULL) ? $recommendation['recommended_action'] : [];
    $decision_basis = is_array($recommendation['decision_basis'] ?? NULL) ? $recommendation['decision_basis'] : [];
    return [
      'decision_contract_version' => self::ACTOR_DECISION_CONTRACT_VERSION,
      'actor_instance_id' => (string) ($recommendation['actor_instance_id'] ?? ''),
      'tool' => 'action',
      'provider' => $provider !== '' ? $provider : (string) ($recommendation['provider'] ?? ''),
      'decision_reason' => (string) ($recommendation['decision_reason'] ?? ''),
      'decision_basis' => [
        'used_profile' => (bool) ($decision_basis['used_profile'] ?? FALSE),
        'used_psychology' => (bool) ($decision_basis['used_psychology'] ?? FALSE),
        'used_availability' => (bool) ($decision_basis['used_availability'] ?? FALSE),
      ],
      'confidence' => is_numeric($recommendation['confidence'] ?? NULL)
        ? max(0.0, min(1.0, (float) $recommendation['confidence']))
        : 0.0,
      'contract_version' => (string) ($recommendation['contract_version'] ?? ($context['action_contract_hash'] ?? '')),
      'payload' => [
        'action' => [
          'type' => (string) ($recommended_action['type'] ?? ''),
          'target_instance_id' => $recommended_action['target_instance_id'] ?? NULL,
          'action_cost' => is_numeric($recommended_action['action_cost'] ?? NULL) ? (int) $recommended_action['action_cost'] : 0,
          'parameters' => is_array($recommended_action['parameters'] ?? NULL) ? $recommended_action['parameters'] : [],
        ],
      ],
    ];
  }

  /**
   * Build a unified actor decision envelope from chat dialogue output.
   *
   * @param array<string, mixed> $dialogue_payload
   *   Canonical character dialogue payload.
   * @param string $provider
   *   Decision provider name.
   * @param array<string, mixed> $decision_basis
   *   Decision basis booleans.
   * @param string|null $contract_version
   *   Optional contract version/hash.
   */
  public function buildActorDecisionEnvelopeFromChatDialogue(
    array $dialogue_payload,
    string $provider = '',
    array $decision_basis = [],
    ?string $contract_version = NULL
  ): array {
    $context = is_array($dialogue_payload['context'] ?? NULL) ? $dialogue_payload['context'] : [];
    $entity_ref = trim((string) ($dialogue_payload['entity_ref'] ?? ''));
    $channel = trim((string) ($dialogue_payload['channel'] ?? 'room'));
    $text = (string) ($dialogue_payload['text'] ?? '');
    $delivery_type = trim((string) ($dialogue_payload['delivery_type'] ?? 'direct_reply'));
    $generation_source = trim((string) ($context['generation_source'] ?? 'model'));

    return [
      'decision_contract_version' => self::ACTOR_DECISION_CONTRACT_VERSION,
      'actor_instance_id' => $entity_ref,
      'tool' => 'chat',
      'provider' => $provider !== '' ? $provider : $generation_source,
      'decision_reason' => sprintf('Generated %s dialogue via %s path.', $delivery_type, $generation_source),
      'decision_basis' => [
        'used_profile' => (bool) ($decision_basis['used_profile'] ?? TRUE),
        'used_psychology' => (bool) ($decision_basis['used_psychology'] ?? TRUE),
        'used_availability' => (bool) ($decision_basis['used_availability'] ?? TRUE),
      ],
      'confidence' => is_numeric($decision_basis['confidence'] ?? NULL)
        ? max(0.0, min(1.0, (float) $decision_basis['confidence']))
        : 0.7,
      'contract_version' => trim((string) ($contract_version ?? 'chat-decision-v1')),
      'payload' => [
        'chat' => [
          'channel' => $channel !== '' ? $channel : 'room',
          'message' => trim($text),
          'delivery_type' => $delivery_type,
        ],
      ],
    ];
  }

}
