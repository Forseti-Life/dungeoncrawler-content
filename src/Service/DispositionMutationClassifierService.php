<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Classifies encounter action mutation scope for disposition trigger routing.
 */
class DispositionMutationClassifierService {

  /**
   * Classify an action into disposition mutation scope and trigger contract.
   *
   * @param array<string,mixed> $action_context
   *   Action context payload.
   *
   * @return array<string,mixed>
   *   Classification contract for mutation routing.
   */
  public function classifyActionMutationScope(
    int $campaign_id,
    string $action_type,
    array $action_context = []
  ): array {
    $normalized_action_type = strtolower(trim($action_type));
    $target_entity_ref = trim((string) ($action_context['target_entity_ref'] ?? ''));
    $matched = FALSE;
    $event_type = '';

    if ($campaign_id > 0 && $target_entity_ref !== '') {
      if ($normalized_action_type === 'strike') {
        $matched = TRUE;
        $event_type = 'attack';
      }
      elseif ($normalized_action_type === 'talk') {
        $matched = TRUE;
        $event_type = 'conversation';
      }
      elseif ($normalized_action_type === 'demoralize' && empty($action_context['immune'])) {
        $degree = strtolower(trim((string) ($action_context['degree'] ?? '')));
        if ($degree === 'critical_success') {
          $matched = TRUE;
          $event_type = 'intimidation_critical_success';
        }
        elseif ($degree === 'success') {
          $matched = TRUE;
          $event_type = 'intimidation_success';
        }
        elseif ($degree === 'failure') {
          $matched = TRUE;
          $event_type = 'intimidation_failure';
        }
        elseif ($degree === 'critical_failure') {
          $matched = TRUE;
          $event_type = 'intimidation_critical_failure';
        }
      }
      elseif ($normalized_action_type === 'cast_spell') {
        $is_negative_effect_spell = !empty($action_context['is_negative_effect_spell']);
        $requires_attack_roll = !empty($action_context['requires_attack_roll']);
        if ($is_negative_effect_spell || $requires_attack_roll) {
          $matched = TRUE;
          $event_type = 'negative_effect_spell';
        }
      }
    }

    $trigger = $matched ? DispositionTriggerCatalog::resolve($event_type) : [];

    return [
      'matched' => $matched,
      'event_type' => $event_type,
      'target_scope' => $matched ? 'direct_actor' : 'none',
      'apply_actor_disposition' => $matched,
      'apply_relationship_disposition' => $matched,
      'apply_institution_disposition' => FALSE,
      'trigger_context' => [
        'campaign_id' => $campaign_id,
        'action_type' => $normalized_action_type,
        'source_entity_ref' => (string) ($action_context['source_entity_ref'] ?? ''),
        'target_entity_ref' => $target_entity_ref,
      ],
      'trigger' => $trigger,
    ];
  }

}
