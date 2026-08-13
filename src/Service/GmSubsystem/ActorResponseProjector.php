<?php

namespace Drupal\dungeoncrawler_content\Service\GmSubsystem;

/**
 * Projects one actor-scoped room-chat response payload.
 */
class ActorResponseProjector {

  protected const RESPONSE_SCHEMA_VERSION = 'room-chat-actor-response-v1';

  /**
   * Build one actor-scoped response projection from turn results.
   *
   * @param array<string,mixed> $chat_result
   *   Room chat turn result payload.
   * @param array<string,mixed> $actor_turn_context
   *   Actor turn context payload.
   *
   * @return array<string,mixed>
   *   Actor-scoped response payload.
   */
  public function project(array $chat_result, array $actor_turn_context): array {
    $legal_actions = is_array($actor_turn_context['legal_actions'] ?? NULL)
      ? $actor_turn_context['legal_actions']
      : [];

    $response = [
      'schema_version' => self::RESPONSE_SCHEMA_VERSION,
      'message' => is_array($chat_result['message'] ?? NULL) ? $chat_result['message'] : [],
      'gm_response' => is_array($chat_result['gm_response'] ?? NULL) ? $chat_result['gm_response'] : NULL,
      'npc_interjections' => array_values(is_array($chat_result['npc_interjections'] ?? NULL) ? $chat_result['npc_interjections'] : []),
      'turn_harness' => is_array($chat_result['turn_harness'] ?? NULL) ? $chat_result['turn_harness'] : NULL,
      'turn_logs' => array_values(is_array($chat_result['turn_logs'] ?? NULL) ? $chat_result['turn_logs'] : []),
      'quest_updates' => array_values(is_array($chat_result['quest_updates'] ?? NULL) ? $chat_result['quest_updates'] : []),
      'navigation' => is_array($chat_result['navigation'] ?? NULL) ? $chat_result['navigation'] : NULL,
      'aggression_summary' => is_array($chat_result['aggression_summary'] ?? NULL) ? $chat_result['aggression_summary'] : NULL,
      'combat_entry_summary' => is_array($chat_result['combat_entry_summary'] ?? NULL) ? $chat_result['combat_entry_summary'] : NULL,
      'runtime_snapshot' => is_array($actor_turn_context['runtime_snapshot'] ?? NULL)
        ? $actor_turn_context['runtime_snapshot']
        : [],
      'quest_context' => is_array($actor_turn_context['quest_context'] ?? NULL)
        ? $actor_turn_context['quest_context']
        : [],
      'available_actions' => array_values(is_array($legal_actions['available_actions'] ?? NULL) ? $legal_actions['available_actions'] : []),
      'action_contract' => is_array($legal_actions['action_contract'] ?? NULL) ? $legal_actions['action_contract'] : NULL,
      'action_option_families' => is_array($legal_actions['action_option_families'] ?? NULL) ? $legal_actions['action_option_families'] : [],
      'timing' => is_array($chat_result['timing'] ?? NULL) ? $chat_result['timing'] : NULL,
      'gm_actor_runtime' => is_array($chat_result['gm_actor_runtime'] ?? NULL) ? $chat_result['gm_actor_runtime'] : NULL,
      'gm_actor_harness' => is_array($chat_result['gm_actor_harness'] ?? NULL) ? $chat_result['gm_actor_harness'] : NULL,
      'gm_subsystem' => is_array($chat_result['gm_subsystem'] ?? NULL) ? $chat_result['gm_subsystem'] : NULL,
    ];
    $runtime_snapshot = is_array($response['runtime_snapshot'] ?? NULL) ? $response['runtime_snapshot'] : [];
    if (is_array($runtime_snapshot['aggression_state'] ?? NULL)) {
      $response['aggression_state'] = $runtime_snapshot['aggression_state'];
    }
    if (is_array($runtime_snapshot['disposition_state'] ?? NULL)) {
      $response['disposition_state'] = $runtime_snapshot['disposition_state'];
    }
    $resolved_disposition_by_target = is_array($runtime_snapshot['resolved_disposition_by_target'] ?? NULL)
      ? $runtime_snapshot['resolved_disposition_by_target']
      : [];
    if ($resolved_disposition_by_target !== []) {
      $response['resolved_disposition_by_target'] = $resolved_disposition_by_target;
    }
    if (is_array($runtime_snapshot['relationship_attitudes'] ?? NULL)) {
      $response['relationship_attitudes'] = $runtime_snapshot['relationship_attitudes'];
    }
    elseif ($resolved_disposition_by_target !== []) {
      // Compatibility projection only: behavioral consumers should read resolver DTOs.
      $response['relationship_attitudes'] = $this->projectCompatibilityRelationshipAttitudesFromResolvedDisposition($resolved_disposition_by_target);
    }
    if (is_array($runtime_snapshot['stance_state'] ?? NULL)) {
      $response['stance_state'] = $runtime_snapshot['stance_state'];
    }
    if (!is_array($response['stance_summary'] ?? NULL) && is_array($runtime_snapshot['stance_state']['summary'] ?? NULL)) {
      $response['stance_summary'] = $runtime_snapshot['stance_state']['summary'];
    }
    $response['resolved_actor_context'] = $this->buildResolvedActorContextEnvelope($response, $runtime_snapshot);

    if (array_key_exists('turn_log_key', $chat_result)) {
      $response['turn_log_key'] = $chat_result['turn_log_key'] !== NULL
        ? (string) $chat_result['turn_log_key']
        : NULL;
    }
    if (array_key_exists('client_request_id', $chat_result)) {
      $response['client_request_id'] = $chat_result['client_request_id'] !== NULL
        ? (string) $chat_result['client_request_id']
        : NULL;
    }

    return $response;
  }

  /**
   * Build one canonical resolved actor-context envelope from response slices.
   *
   * @param array<string,mixed> $response
   * @param array<string,mixed> $runtime_snapshot
   *
   * @return array<string,mixed>|null
   *   Unified actor-context projection, or NULL when no slices exist.
   */
  protected function buildResolvedActorContextEnvelope(array $response, array $runtime_snapshot): ?array {
    $disposition = [];
    if (is_array($response['disposition_summary'] ?? NULL)) {
      $disposition = $response['disposition_summary'];
    }
    elseif (is_array($runtime_snapshot['disposition_state']['summary'] ?? NULL)) {
      $disposition = $runtime_snapshot['disposition_state']['summary'];
    }

    $aggression = [];
    if (is_array($response['aggression_summary'] ?? NULL)) {
      $aggression = $response['aggression_summary'];
    }
    elseif (is_array($runtime_snapshot['aggression_state']['aggression_summary'] ?? NULL)) {
      $aggression = $runtime_snapshot['aggression_state']['aggression_summary'];
    }

    $stance = [];
    if (is_array($response['stance_summary'] ?? NULL)) {
      $stance = $response['stance_summary'];
    }
    elseif (is_array($runtime_snapshot['stance_state']['summary'] ?? NULL)) {
      $stance = $runtime_snapshot['stance_state']['summary'];
    }

    $resolved_disposition_by_target = is_array($runtime_snapshot['resolved_disposition_by_target'] ?? NULL)
      ? $runtime_snapshot['resolved_disposition_by_target']
      : [];
    $relationship_attitudes = is_array($runtime_snapshot['relationship_attitudes'] ?? NULL)
      ? $runtime_snapshot['relationship_attitudes']
      : [];
    if ($relationship_attitudes === [] && $resolved_disposition_by_target !== []) {
      $relationship_attitudes = $this->projectCompatibilityRelationshipAttitudesFromResolvedDisposition($resolved_disposition_by_target);
    }

    if ($disposition === [] && $aggression === [] && $stance === [] && $relationship_attitudes === [] && $resolved_disposition_by_target === []) {
      return NULL;
    }

    return [
      'disposition' => $disposition,
      'aggression' => $aggression,
      'stance' => $stance,
      'resolved_disposition_by_target' => $resolved_disposition_by_target,
      'relationship_attitudes' => $relationship_attitudes,
      'narrative_context' => is_array($response['quest_context'] ?? NULL)
        ? $response['quest_context']
        : [],
    ];
  }

  /**
   * Project compatibility relationship attitude labels from resolver DTO map.
   *
   * @param array<string,mixed> $resolved_disposition_by_target
   *   Canonical resolver DTO map.
   *
   * @return array<string,string>
   *   Compatibility attitude map keyed by target entity id.
   */
  protected function projectCompatibilityRelationshipAttitudesFromResolvedDisposition(array $resolved_disposition_by_target): array {
    $attitudes = [];
    foreach ($resolved_disposition_by_target as $target_entity_id => $dto) {
      if (!is_array($dto)) {
        continue;
      }
      $label = strtolower(trim((string) ($dto['effective_disposition_label'] ?? 'neutral')));
      $attitudes[(string) $target_entity_id] = $label !== '' ? $label : 'neutral';
    }

    return $attitudes;
  }

}
