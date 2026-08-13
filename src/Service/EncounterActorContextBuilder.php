<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Builds encounter actor decision context payloads.
 */
class EncounterActorContextBuilder {

  protected NpcPsychologyService $psychologyService;
  protected ActorActionAvailabilityService $actionAvailability;
  protected ActorDecisionContractService $decisionContractService;
  protected ?ActorContextProjectionService $actorContextProjectionService;

  public function __construct(
    NpcPsychologyService $psychology_service,
    ActorActionAvailabilityService $action_availability,
    ?ActorDecisionContractService $decision_contract_service = NULL,
    ?ActorContextProjectionService $actor_context_projection_service = NULL
  ) {
    $this->psychologyService = $psychology_service;
    $this->actionAvailability = $action_availability;
    $this->decisionContractService = $decision_contract_service ?? new ActorDecisionContractService();
    $this->actorContextProjectionService = $actor_context_projection_service;
  }

  /**
   * Build canonical actor context for encounter action recommendation.
   */
  public function buildActorContext(string $entity_id, array $game_state, array $dungeon_data, array $current_actor_tactical_intent): array {
    $initiative_order = $game_state['initiative_order'] ?? [];
    $actor = NULL;
    $allies = [];
    $threats = [];

    foreach ($initiative_order as $combatant) {
      $cid = $combatant['entity_id'] ?? '';
      if ($cid === $entity_id) {
        $actor = $combatant;
        continue;
      }
      if (!empty($combatant['is_defeated'])) {
        continue;
      }
      $team = $combatant['team'] ?? 'enemy';
      if ($team === 'player') {
        $threats[] = [
          'entity_id' => $cid,
          'name' => $combatant['name'] ?? $cid,
          'hp_ratio' => $this->hpRatio($combatant),
          'position_q' => (int) ($combatant['position_q'] ?? 0),
          'position_r' => (int) ($combatant['position_r'] ?? 0),
          'ac' => (int) ($combatant['ac'] ?? 10),
        ];
      }
      else {
        $allies[] = [
          'entity_id' => $cid,
          'name' => $combatant['name'] ?? $cid,
          'hp_ratio' => $this->hpRatio($combatant),
        ];
      }
    }

    $context_game_state = $game_state;
    if (!is_array($context_game_state['turn'] ?? NULL)) {
      $context_game_state['turn'] = [];
    }
    if (trim((string) ($context_game_state['turn']['entity'] ?? '')) === '') {
      $context_game_state['turn']['entity'] = $entity_id;
    }
    if (!is_numeric($context_game_state['turn']['actions_remaining'] ?? NULL)) {
      $context_game_state['turn']['actions_remaining'] = 3;
    }
    if (!array_key_exists('reaction_available', $context_game_state['turn'])) {
      $context_game_state['turn']['reaction_available'] = FALSE;
    }

    $availability = $this->actionAvailability->resolveEncounterAvailability($context_game_state, $dungeon_data, $entity_id);
    $allowed_actions = $availability['available_actions'];
    $action_contract = $availability['action_contract'];
    $actions_available_to_me_this_turn = $availability['availability_envelope'];
    $action_contract_hash = $this->decisionContractService->buildActionContractHash($action_contract, $allowed_actions);
    $psychology_context = $this->buildUnifiedPsychologyContext($entity_id, $game_state);
    $resolved_entity_ref = (string) ($psychology_context['entity_ref'] ?? $entity_id);
    $resolved_actor_context = $this->buildResolvedActorProjection($entity_id, $resolved_entity_ref, $game_state);

    return [
      'encounter_id' => $game_state['encounter_id'] ?? NULL,
      'campaign_id' => $game_state['campaign_id'] ?? NULL,
      'round' => $game_state['round'] ?? NULL,
      'entity_id' => $entity_id,
      'current_actor' => $actor ? [
        'entity_id' => $entity_id,
        'entity_ref' => $resolved_entity_ref,
        'name' => $actor['name'] ?? $entity_id,
        'team' => $actor['team'] ?? 'enemy',
        'hp' => (int) ($actor['hp'] ?? 0),
        'max_hp' => (int) ($actor['max_hp'] ?? 0),
        'hp_ratio' => $this->hpRatio($actor ?? []),
        'ac' => (int) ($actor['ac'] ?? 12),
        'position_q' => (int) ($actor['position_q'] ?? 0),
        'position_r' => (int) ($actor['position_r'] ?? 0),
        'actions_remaining' => (int) ($game_state['turn']['actions_remaining'] ?? 3),
      ] : ['entity_id' => $entity_id, 'entity_ref' => $resolved_entity_ref],
      'current_actor_profile' => $psychology_context['decision_profile'],
      'participants' => $initiative_order,
      'allies' => $allies,
      'threats' => $threats,
      'allowed_actions' => $allowed_actions,
      'action_contract' => $action_contract,
      'action_contract_hash' => $action_contract_hash,
      'actions_available_to_me_this_turn' => $actions_available_to_me_this_turn,
      'npc_psychology' => $psychology_context['combat_psychology_context'],
      'current_actor_tactical_intent' => $current_actor_tactical_intent,
      'resolved_actor_context' => $resolved_actor_context,
      'disposition_summary' => is_array($resolved_actor_context['disposition'] ?? NULL) ? $resolved_actor_context['disposition'] : NULL,
      'aggression_summary' => is_array($resolved_actor_context['aggression'] ?? NULL) ? $resolved_actor_context['aggression'] : NULL,
      'stance_summary' => is_array($resolved_actor_context['stance'] ?? NULL) ? $resolved_actor_context['stance'] : NULL,
      'resolved_disposition_by_target' => is_array($resolved_actor_context['resolved_disposition_by_target'] ?? NULL)
        ? $resolved_actor_context['resolved_disposition_by_target']
        : [],
      'relationship_attitudes' => is_array($resolved_actor_context['relationship_attitudes'] ?? NULL)
        ? $resolved_actor_context['relationship_attitudes']
        : [],
    ];
  }

  /**
   * Build resolved actor-context projection when projection service is available.
   */
  protected function buildResolvedActorProjection(string $entity_id, string $entity_ref, array $game_state): array {
    if (!$this->actorContextProjectionService) {
      return [];
    }

    $campaign_id = (int) ($game_state['campaign_id'] ?? 0);
    if ($campaign_id <= 0) {
      return [];
    }

    $live_entity = $this->resolveLiveCombatantSnapshot($entity_id, $game_state);
    $character_data = [];
    if (is_array($live_entity['state']['character_data'] ?? NULL)) {
      $character_data = $live_entity['state']['character_data'];
    }
    elseif (is_array($live_entity['character_data'] ?? NULL)) {
      $character_data = $live_entity['character_data'];
    }
    $target_entity_refs = [];
    foreach ((array) ($game_state['initiative_order'] ?? []) as $participant) {
      if (!is_array($participant) || !empty($participant['is_defeated'])) {
        continue;
      }
      $candidate = trim((string) (
        $participant['entity_ref']
        ?? $participant['entity_id']
        ?? ''
      ));
      if ($candidate === '') {
        continue;
      }
      $target_entity_refs[] = $candidate;
    }
    return $this->actorContextProjectionService->buildResolvedActorContext(
      $campaign_id,
      $entity_ref !== '' ? $entity_ref : $entity_id,
      $live_entity,
      $character_data,
      [],
      $target_entity_refs
    );
  }

  /**
   * Build structured actor profile for encounter action recommendation.
   */
  public function buildActorDecisionProfile(string $entity_id, array $game_state): array {
    $context = $this->buildUnifiedPsychologyContext($entity_id, $game_state);
    return is_array($context['decision_profile'] ?? NULL) ? $context['decision_profile'] : [];
  }

  /**
   * Build actor psychology context string for encounter recommendations.
   */
  public function buildActorPsychologyContext(string $entity_id, array $game_state): string {
    $context = $this->buildUnifiedPsychologyContext($entity_id, $game_state);
    return (string) ($context['combat_psychology_context'] ?? '');
  }

  /**
   * Resolve canonical combatant entity_ref for downstream compatibility users.
   */
  public function resolveActorEntityRef(string $entity_id, array $game_state): string {
    return $this->resolveCombatantEntityRef($entity_id, $game_state);
  }

  /**
   * Load psychology profile for a combatant with canonical fallback semantics.
   */
  public function loadActorProfile(string $entity_id, array $game_state): ?array {
    $campaign_id = (int) ($game_state['campaign_id'] ?? 0);
    return $this->loadCombatantPsychologyProfile($entity_id, $game_state, $campaign_id);
  }

  /**
   * Resolve normalized actor goals including baseline defaults.
   */
  public function resolveGoalsFromProfile(?array $profile): array {
    return $this->resolveActorGoals($profile);
  }

  /**
   * Normalize personality-axis values for tactical decision compatibility.
   */
  public function normalizePersonalityAxes(array $axes): array {
    return $this->normalizeDecisionPersonalityAxes($axes);
  }

  /**
   * Normalize NPC attitude values against canonical ladder.
   */
  public function normalizeAttitudeValue(mixed $attitude): ?string {
    return $this->normalizeNpcAttitude($attitude);
  }

  /**
   * Build the canonical psychology envelope used across chat and encounter lanes.
   */
  protected function buildUnifiedPsychologyContext(string $entity_id, array $game_state): array {
    $campaign_id = (int) ($game_state['campaign_id'] ?? 0);
    $entity_ref = $this->resolveCombatantEntityRef($entity_id, $game_state);
    $live_entity = $this->resolveLiveCombatantSnapshot($entity_id, $game_state);
    $context = $this->psychologyService->buildUnifiedActorContext($campaign_id, $entity_ref, $live_entity);
    if ($campaign_id <= 0 || !$this->actorContextProjectionService || $entity_ref === '') {
      return $context;
    }

    $disposition = $this->actorContextProjectionService->buildDispositionSummary($campaign_id, $entity_ref, $live_entity);
    $attitude = DispositionAuthorityContract::normalizeAttitudeLabel((string) ($disposition['attitude'] ?? ''));
    if ($attitude === '') {
      return $context;
    }

    $decision_profile = is_array($context['decision_profile'] ?? NULL) ? $context['decision_profile'] : [];
    $decision_profile['attitude'] = $attitude;
    $decision_profile['attitude_source'] = 'actor_disposition_projection';
    $context['decision_profile'] = $decision_profile;

    return $context;
  }

  /**
   * Resolve a minimal live combatant snapshot for shared actor-context assembly.
   */
  protected function resolveLiveCombatantSnapshot(string $entity_id, array $game_state): array {
    foreach (($game_state['initiative_order'] ?? []) as $combatant) {
      if (($combatant['entity_id'] ?? '') !== $entity_id) {
        continue;
      }
      return [
        'name' => (string) ($combatant['name'] ?? $combatant['display_name'] ?? $entity_id),
        'state' => [
          'metadata' => [
            'display_name' => (string) ($combatant['name'] ?? $combatant['display_name'] ?? $entity_id),
            'stats' => [
              'ac' => (int) ($combatant['ac'] ?? 0),
            ],
          ],
          'hit_points' => [
            'current' => (int) ($combatant['hp'] ?? 0),
            'max' => (int) ($combatant['max_hp'] ?? 0),
          ],
        ],
      ];
    }
    return [];
  }

  /**
   * Load psychology profile for a combatant using entity_ref first, then entity_id.
   */
  protected function loadCombatantPsychologyProfile(string $entity_id, array $game_state, int $campaign_id): ?array {
    if ($campaign_id <= 0) {
      return NULL;
    }

    $entity_ref = $this->resolveCombatantEntityRef($entity_id, $game_state);
    $profile = $this->psychologyService->loadProfile($campaign_id, $entity_ref);
    if (!$profile && $entity_ref !== $entity_id) {
      $profile = $this->psychologyService->loadProfile($campaign_id, $entity_id);
    }
    return $profile ?: NULL;
  }

  protected function resolveCombatantEntityRef(string $entity_id, array $game_state): string {
    foreach (($game_state['initiative_order'] ?? []) as $combatant) {
      if (($combatant['entity_id'] ?? '') === $entity_id) {
        return $this->normalizeCombatantEntityRef($combatant['entity_ref'] ?? NULL, $combatant['entity_id'] ?? $entity_id);
      }
    }
    return $entity_id;
  }

  /**
   * Normalize encounter participant entity_ref payloads.
   */
  protected function normalizeCombatantEntityRef(mixed $entity_ref, string $fallback): string {
    if (is_string($entity_ref)) {
      $decoded = json_decode($entity_ref, TRUE);
      if (is_array($decoded)) {
        $content_id = trim((string) ($decoded['content_id'] ?? ''));
        if ($content_id !== '') {
          return $content_id;
        }
      }
      $candidate = trim($entity_ref);
      return $candidate !== '' ? $candidate : $fallback;
    }

    if (is_array($entity_ref)) {
      $content_id = trim((string) ($entity_ref['content_id'] ?? ''));
      if ($content_id !== '') {
        return $content_id;
      }
    }

    return $fallback;
  }

  protected function resolveActorGoals(?array $profile): array {
    $goals = [];
    if (is_array($profile)) {
      $sheet = is_array($profile['character_sheet'] ?? NULL) ? $profile['character_sheet'] : [];
      if (is_array($sheet['goals'] ?? NULL)) {
        foreach ($sheet['goals'] as $goal) {
          if (is_string($goal) && trim($goal) !== '') {
            $goals[] = trim($goal);
          }
        }
      }
      $motivations = trim((string) ($profile['motivations'] ?? ''));
      if ($motivations !== '') {
        $motivation_goals = preg_split('/[;\n\r]+/', $motivations) ?: [];
        foreach ($motivation_goals as $motivation_goal) {
          $trimmed = trim((string) $motivation_goal);
          if ($trimmed !== '') {
            $goals[] = $trimmed;
          }
        }
      }
    }

    $goals[] = 'Gain XP';
    $goals[] = 'Gain Treasure';

    $seen = [];
    $normalized = [];
    foreach ($goals as $goal) {
      $key = strtolower($goal);
      if (isset($seen[$key])) {
        continue;
      }
      $seen[$key] = TRUE;
      $normalized[] = $goal;
    }
    return $normalized;
  }

  protected function normalizeDecisionPersonalityAxes(array $axes): array {
    $normalized = NpcPsychologyService::PERSONALITY_AXES;
    foreach ($normalized as $key => $default_value) {
      $value = is_numeric($axes[$key] ?? NULL) ? (int) $axes[$key] : (int) $default_value;
      $normalized[$key] = max(0, min(10, $value));
    }
    return $normalized;
  }

  protected function normalizeNpcAttitude(mixed $attitude): ?string {
    if (!is_string($attitude)) {
      return NULL;
    }

    $candidate = strtolower(trim($attitude));
    if ($candidate === '') {
      return NULL;
    }

    return in_array($candidate, NpcPsychologyService::ATTITUDE_LADDER, TRUE) ? $candidate : NULL;
  }

  protected function resolveEntityName(string $entity_id, array $game_state): string {
    foreach ($game_state['initiative_order'] ?? [] as $combatant) {
      if (($combatant['entity_id'] ?? '') === $entity_id) {
        return $combatant['name'] ?? $combatant['display_name'] ?? $entity_id;
      }
    }

    return $entity_id;
  }

  protected function hpRatio(array $combatant): float {
    $hp = (float) ($combatant['hp'] ?? 0);
    $max = (float) ($combatant['max_hp'] ?? 0);
    if ($max <= 0) {
      return 0.0;
    }
    $ratio = $hp / $max;
    return max(0.0, min(1.0, $ratio));
  }

}
