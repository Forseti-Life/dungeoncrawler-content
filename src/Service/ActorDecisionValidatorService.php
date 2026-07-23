<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Validates unified actor decision envelopes across tool lanes.
 */
class ActorDecisionValidatorService {

  /**
   * Validate unified actor decision envelope shape and tool payload contract.
   *
   * @param array<string, mixed> $decision
   *   Decision envelope.
   * @param string|null $expected_actor_instance_id
   *   Optional expected actor identifier.
   *
   * @return array{valid: bool, errors: array<int, string>}
   *   Validation result.
   */
  public function validateDecision(array $decision, ?string $expected_actor_instance_id = NULL): array {
    $errors = [];

    foreach (['decision_contract_version', 'actor_instance_id', 'tool', 'decision_reason', 'decision_basis', 'confidence', 'contract_version', 'payload'] as $field) {
      if (!array_key_exists($field, $decision)) {
        $errors[] = sprintf('missing required field "%s".', $field);
      }
    }

    $contract_version = trim((string) ($decision['decision_contract_version'] ?? ''));
    if ($contract_version !== ActorDecisionContractService::ACTOR_DECISION_CONTRACT_VERSION) {
      $errors[] = 'decision_contract_version is invalid.';
    }

    $actor_instance_id = trim((string) ($decision['actor_instance_id'] ?? ''));
    if ($actor_instance_id === '') {
      $errors[] = 'actor_instance_id must be a non-empty string.';
    }
    if ($expected_actor_instance_id !== NULL && $expected_actor_instance_id !== '' && $actor_instance_id !== $expected_actor_instance_id) {
      $errors[] = 'actor_instance_id does not match expected actor.';
    }

    $tool = strtolower(trim((string) ($decision['tool'] ?? '')));
    if (!in_array($tool, ['chat', 'action', 'end_turn'], TRUE)) {
      $errors[] = 'tool must be one of: chat, action, end_turn.';
    }

    $decision_reason = trim((string) ($decision['decision_reason'] ?? ''));
    if ($decision_reason === '') {
      $errors[] = 'decision_reason must be a non-empty string.';
    }

    $decision_basis = $decision['decision_basis'] ?? NULL;
    if (!is_array($decision_basis)) {
      $errors[] = 'decision_basis must be an object.';
    }
    else {
      foreach (['used_profile', 'used_psychology', 'used_availability'] as $basis_flag) {
        if (!array_key_exists($basis_flag, $decision_basis) || !is_bool($decision_basis[$basis_flag])) {
          $errors[] = sprintf('decision_basis.%s must be boolean.', $basis_flag);
        }
      }
    }

    if (!is_numeric($decision['confidence'] ?? NULL)) {
      $errors[] = 'confidence must be numeric.';
    }
    else {
      $confidence = (float) $decision['confidence'];
      if ($confidence < 0.0 || $confidence > 1.0) {
        $errors[] = 'confidence must be between 0 and 1.';
      }
    }

    $action_contract_version = trim((string) ($decision['contract_version'] ?? ''));
    if ($action_contract_version === '') {
      $errors[] = 'contract_version must be a non-empty string.';
    }

    $payload = $decision['payload'] ?? NULL;
    if (!is_array($payload)) {
      $errors[] = 'payload must be an object.';
      return ['valid' => $errors === [], 'errors' => $errors];
    }

    if ($tool === 'action') {
      $action = $payload['action'] ?? NULL;
      if (!is_array($action)) {
        $errors[] = 'payload.action must be an object for action tool.';
      }
      else {
        if (trim((string) ($action['type'] ?? '')) === '') {
          $errors[] = 'payload.action.type must be a non-empty string.';
        }
        if (!is_numeric($action['action_cost'] ?? NULL)) {
          $errors[] = 'payload.action.action_cost must be numeric.';
        }
        if (!is_array($action['parameters'] ?? NULL)) {
          $errors[] = 'payload.action.parameters must be an object.';
        }
      }
    }
    elseif ($tool === 'chat') {
      $chat = $payload['chat'] ?? NULL;
      if (!is_array($chat)) {
        $errors[] = 'payload.chat must be an object for chat tool.';
      }
      else {
        if (trim((string) ($chat['channel'] ?? '')) === '') {
          $errors[] = 'payload.chat.channel must be a non-empty string.';
        }
        if (trim((string) ($chat['message'] ?? '')) === '') {
          $errors[] = 'payload.chat.message must be a non-empty string.';
        }
      }
    }

    return [
      'valid' => $errors === [],
      'errors' => $errors,
    ];
  }

}

