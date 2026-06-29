<?php

namespace Drupal\dungeoncrawler_content\Service\GmSubsystem;

/**
 * Classifies room-turn intents into subsystem route families.
 *
 * This extraction is phase 1 of the generateGmReply() subsystem migration:
 * keep behavior stable while making route-family mapping explicit and testable.
 */
class TurnIntentRouter {

  /**
   * Route categories that should attempt deterministic execution first.
   */
  protected const DETERMINISTIC_INTENT_ROUTE_MAP = [
    'room_roster_query' => 'lookup_then_narrate',
    'room_description_query' => 'lookup_then_narrate',
    'navigation_query' => 'lookup_then_narrate',
    'merchant_inquiry' => 'lookup_then_narrate',
    'quest_query' => 'lookup_then_narrate',
    'navigation_travel' => 'navigation',
    'combat_engagement' => 'combat_transition',
    'direct_npc_transaction' => 'transactional',
  ];

  /**
   * Intents that remain narration/adjudication-first (LLM-eligible).
   */
  protected const NARRATIVE_INTENT_ROUTE_MAP = [
    'direct_npc_dialogue' => 'narrative_only',
    'gm_adjudication_query' => 'narrative_only',
    'gm_role_correction' => 'narrative_only',
    'ooc_meta' => 'narrative_only',
    'gm_narration' => 'narrative_only',
  ];

  /**
   * Build route metadata for a classified room-turn intent.
   *
   * @return array{
   *   route_family: string,
   *   deterministic_eligible: bool,
   *   llm_required: bool,
   *   resolution_outcome: string
   * }
   */
  public function routeFromIntent(string $intent, bool $is_room_entry = FALSE): array {
    if ($intent === 'gm_narration' && $is_room_entry) {
      return [
        'route_family' => 'lookup_then_narrate',
        'deterministic_eligible' => TRUE,
        'llm_required' => FALSE,
        'resolution_outcome' => 'resolved',
      ];
    }

    if (isset(self::DETERMINISTIC_INTENT_ROUTE_MAP[$intent])) {
      return [
        'route_family' => self::DETERMINISTIC_INTENT_ROUTE_MAP[$intent],
        'deterministic_eligible' => TRUE,
        'llm_required' => FALSE,
        'resolution_outcome' => 'resolved',
      ];
    }

    if (isset(self::NARRATIVE_INTENT_ROUTE_MAP[$intent])) {
      return [
        'route_family' => self::NARRATIVE_INTENT_ROUTE_MAP[$intent],
        'deterministic_eligible' => FALSE,
        'llm_required' => TRUE,
        'resolution_outcome' => 'fallback_to_llm',
      ];
    }

    // Unknown intents remain explicit fallback routes.
    return [
      'route_family' => 'llm_fallback',
      'deterministic_eligible' => FALSE,
      'llm_required' => TRUE,
      'resolution_outcome' => 'fallback_to_llm',
    ];
  }

}

