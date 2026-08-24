<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Canonical writer/reader façade for actor-wide disposition.
 */
class ActorDispositionService {
  protected const LIVING_BASELINE_DEFAULT_SCORE = 50;
  protected const UNDEAD_HOSTILITY_THRESHOLD_SCORE = -70;

  protected NpcPsychologyService $npcPsychologyService;
  protected DispositionEventStoreService $dispositionEventStoreService;
  protected DispositionStateStoreService $dispositionStateStoreService;
  protected ?DispositionTriggerService $dispositionTriggerService;
  protected ?RelationshipAttitudeService $relationshipAttitudeService;
  protected ?RelationshipsActorIdentityResolverService $relationshipsActorIdentityResolver;

  public function __construct(
    NpcPsychologyService $npc_psychology_service,
    DispositionEventStoreService $disposition_event_store_service,
    DispositionStateStoreService $disposition_state_store_service,
    ?DispositionTriggerService $disposition_trigger_service = NULL,
    ?RelationshipAttitudeService $relationship_attitude_service = NULL,
    ?RelationshipsActorIdentityResolverService $relationships_actor_identity_resolver = NULL
  ) {
    $this->npcPsychologyService = $npc_psychology_service;
    $this->dispositionEventStoreService = $disposition_event_store_service;
    $this->dispositionStateStoreService = $disposition_state_store_service;
    $this->dispositionTriggerService = $disposition_trigger_service;
    $this->relationshipAttitudeService = $relationship_attitude_service;
    $this->relationshipsActorIdentityResolver = $relationships_actor_identity_resolver;
  }

  /**
   * Build a normalized disposition summary for one actor.
   */
  public function getDispositionSummary(int $campaign_id, string $entity_ref, array $live_entity = [], bool $converge_state = FALSE): array {
    $resolved_ref = $this->resolveEntityRef($campaign_id, $entity_ref);
    if (!$converge_state) {
      $stored = $this->dispositionStateStoreService->loadLatestState($campaign_id, $resolved_ref);
      $stored_summary = is_array($stored['summary'] ?? NULL) ? $stored['summary'] : [];
      $stored_attitude = DispositionAuthorityContract::normalizeAttitudeLabel((string) ($stored_summary['current_attitude'] ?? ''));
      if ($stored_attitude !== '') {
        $stored_score = isset($stored_summary['current_score']) && is_numeric($stored_summary['current_score'])
          ? DispositionAuthorityContract::clampScore((int) round((float) $stored_summary['current_score']))
          : (DispositionAuthorityContract::attitudeToScore($stored_attitude) ?? 0);
        return [
          'entity_ref' => $entity_ref,
          'display_name' => (string) ($stored_summary['display_name'] ?? $entity_ref),
          'current_attitude' => $stored_attitude,
          'current_score' => $stored_score,
          'score_source' => (string) ($stored_summary['score_source'] ?? 'attitude_projection'),
          'personality_axes' => is_array($stored_summary['personality_axes'] ?? NULL)
            ? $stored_summary['personality_axes']
            : NpcPsychologyService::PERSONALITY_AXES,
          'motivations' => (string) ($stored_summary['motivations'] ?? ''),
          'fears' => (string) ($stored_summary['fears'] ?? ''),
          'bonds' => (string) ($stored_summary['bonds'] ?? ''),
          'latest_thought' => is_array($stored_summary['latest_thought'] ?? NULL) ? $stored_summary['latest_thought'] : NULL,
          'summary_reason' => 'state_store',
        ];
      }
    }

    $context = $this->npcPsychologyService->buildUnifiedActorContext($campaign_id, $resolved_ref, $live_entity);
    $profile = is_array($context['decision_profile'] ?? NULL) ? $context['decision_profile'] : [];
    $attitude = DispositionAuthorityContract::normalizeAttitudeLabel((string) ($profile['attitude'] ?? DispositionAuthorityContract::LABEL_INDIFFERENT));
    if ($attitude === '') {
      $attitude = DispositionAuthorityContract::LABEL_INDIFFERENT;
    }

    $current_score = $this->resolveDefaultDispositionScore($context, $resolved_ref, $attitude);
    $current_attitude = $this->usesAncestryDefaultDisposition($context, $resolved_ref)
      ? DispositionAuthorityContract::scoreToAttitude($current_score)
      : $attitude;

    $summary = [
      'entity_ref' => $entity_ref,
      'display_name' => (string) ($profile['display_name'] ?? $entity_ref),
      'current_attitude' => $current_attitude,
      'current_score' => $current_score,
      'score_source' => $this->resolveScoreSource($context, $resolved_ref, $attitude),
      'personality_axes' => is_array($profile['personality_axes'] ?? NULL)
        ? $profile['personality_axes']
        : NpcPsychologyService::PERSONALITY_AXES,
      'motivations' => (string) ($profile['motivations'] ?? ''),
      'fears' => (string) ($profile['fears'] ?? ''),
      'bonds' => (string) ($profile['bonds'] ?? ''),
      'latest_thought' => is_array($profile['latest_thought'] ?? NULL) ? $profile['latest_thought'] : NULL,
      'summary_reason' => !empty($context['profile']) ? 'profile_resolved' : 'profile_defaulted',
    ];

    if ($converge_state) {
      // Keep canonical state store converged when psychology fallback is used.
      $this->dispositionStateStoreService->storeLatestState($campaign_id, $resolved_ref, $summary, [
        'source' => 'psychology_projection_sync',
      ]);
    }

    return $summary;
  }

  /**
   * Resolve fallback default disposition score from profile/context.
   */
  protected function resolveDefaultDispositionScore(array $context, string $entity_ref, string $attitude): int {
    $attitude_score = DispositionAuthorityContract::attitudeToScore($attitude) ?? 0;
    if ($this->isUndeadDispositionDefault($context, $entity_ref)) {
      return min($attitude_score, self::UNDEAD_HOSTILITY_THRESHOLD_SCORE);
    }
    if (!$this->isNeutralAttitudeDefault($attitude)) {
      return $attitude_score;
    }
    return max($attitude_score, self::LIVING_BASELINE_DEFAULT_SCORE);
  }

  /**
   * Resolve score source label for actor summary baseline.
   */
  protected function resolveScoreSource(array $context, string $entity_ref, string $attitude): string {
    if ($this->isUndeadDispositionDefault($context, $entity_ref)) {
      return 'undead_hostility_threshold_default';
    }
    if ($this->isNeutralAttitudeDefault($attitude)) {
      return 'living_affinity_default';
    }
    return 'attitude_projection';
  }

  /**
   * Determine whether actor baseline should be score-derived from ancestry defaults.
   */
  protected function usesAncestryDefaultDisposition(array $context, string $entity_ref): bool {
    if ($this->isUndeadDispositionDefault($context, $entity_ref)) {
      return TRUE;
    }
    $profile = is_array($context['decision_profile'] ?? NULL) ? $context['decision_profile'] : [];
    return $this->isNeutralAttitudeDefault((string) ($profile['attitude'] ?? ''));
  }

  /**
   * Returns TRUE when the incoming attitude should use baseline defaulting.
   */
  protected function isNeutralAttitudeDefault(string $attitude): bool {
    $normalized = DispositionAuthorityContract::normalizeAttitudeLabel($attitude);
    return $normalized === '' || $normalized === DispositionAuthorityContract::LABEL_INDIFFERENT;
  }

  /**
   * Determine whether this actor should use undead hostility defaulting.
   */
  protected function isUndeadDispositionDefault(array $context, string $entity_ref): bool {
    $profile = is_array($context['profile'] ?? NULL) ? $context['profile'] : [];
    $sheet = is_array($profile['character_sheet'] ?? NULL) ? $profile['character_sheet'] : [];
    $ancestry = strtolower(trim((string) ($sheet['ancestry'] ?? '')));
    $creature_type = strtolower(trim((string) ($sheet['creature_type'] ?? '')));
    $entity_ref = strtolower(trim($entity_ref));
    if ($ancestry === 'undead') {
      return TRUE;
    }
    if ($creature_type === 'undead' || str_contains($creature_type, 'undead')) {
      return TRUE;
    }
    return str_contains($entity_ref, 'skeleton_')
      || str_contains($entity_ref, 'zombie_')
      || str_contains($entity_ref, 'undead');
  }

  /**
   * Apply an explicit attitude shift and return the updated summary.
   */
  public function shiftActorAttitude(int $campaign_id, string $entity_ref, int $shift, string $reason = ''): array {
    $resolved_ref = $this->resolveEntityRef($campaign_id, $entity_ref);
    $profile = $this->npcPsychologyService->loadProfile($campaign_id, $resolved_ref);
    $current = DispositionAuthorityContract::normalizeAttitudeLabel((string) ($profile['attitude'] ?? DispositionAuthorityContract::LABEL_INDIFFERENT));
    if ($current === '') {
      $current = DispositionAuthorityContract::LABEL_INDIFFERENT;
    }
    $next = $this->npcPsychologyService->shiftAttitude($current, $shift);

    $history = is_array($profile['attitude_history'] ?? NULL) ? $profile['attitude_history'] : [];
    if ($next !== $current) {
      $history[] = [
        'attitude' => $next,
        'reason' => $reason !== '' ? $reason : 'Disposition command shift',
        'timestamp' => date('c'),
      ];
    }

    $this->npcPsychologyService->updateProfile($campaign_id, $resolved_ref, [
      'attitude' => $next,
      'attitude_history' => $history,
    ]);

    $summary = $this->getDispositionSummary($campaign_id, $resolved_ref, [], TRUE);
    $before_score = DispositionAuthorityContract::attitudeToScore($current);
    $after_score = isset($summary['current_score']) && is_numeric($summary['current_score'])
      ? DispositionAuthorityContract::clampScore((int) round((float) $summary['current_score']))
      : (DispositionAuthorityContract::attitudeToScore((string) ($summary['current_attitude'] ?? '')) ?? 0);
    $this->dispositionEventStoreService->recordDispositionEvent($campaign_id, $resolved_ref, [
      'event_type' => 'attitude_shift',
      'reason' => $reason !== '' ? $reason : 'Disposition command shift',
      'attitude_before' => $current,
      'attitude_after' => (string) ($summary['current_attitude'] ?? $next),
      'score_before' => $before_score,
      'score_after' => $after_score,
      'summary' => $summary,
      'context' => ['shift' => $shift],
    ]);
    $this->dispositionStateStoreService->storeLatestState($campaign_id, $resolved_ref, $summary, [
      'source' => 'shift_actor_attitude',
      'reason' => $reason,
    ]);

    return $summary;
  }

  /**
   * Apply a domain event to disposition via psychology monologue engine.
   */
  public function applyDispositionEvent(
    int $campaign_id,
    string $entity_ref,
    string $event_type,
    string $event_description,
    array $event_context = []
  ): array {
    $resolved_ref = $this->resolveEntityRef($campaign_id, $entity_ref);
    $before_summary = $this->getDispositionSummary($campaign_id, $resolved_ref, [], FALSE);
    $before_attitude = DispositionAuthorityContract::normalizeAttitudeLabel((string) ($before_summary['current_attitude'] ?? ''));
    if ($before_attitude === '') {
      $before_attitude = DispositionAuthorityContract::LABEL_INDIFFERENT;
    }
    $before_score = isset($before_summary['current_score']) && is_numeric($before_summary['current_score'])
      ? DispositionAuthorityContract::clampScore((int) round((float) $before_summary['current_score']))
      : (DispositionAuthorityContract::attitudeToScore($before_attitude) ?? 0);

    $trigger = $this->dispositionTriggerService
      ? $this->dispositionTriggerService->normalizeTrigger($event_type, [
        'campaign_id' => $campaign_id,
        'source_entity_ref' => $resolved_ref,
        'reason' => $event_description,
      ] + $event_context)
      : NULL;
    $trigger_idempotency_key = is_array($trigger)
      ? trim((string) ($trigger['idempotency_key'] ?? ''))
      : '';

    if (
      is_array($trigger)
      && !empty($trigger['durable'])
      && $trigger_idempotency_key !== ''
      && $this->dispositionEventStoreService->hasDispositionEventIdempotencyKey($campaign_id, $resolved_ref, $trigger_idempotency_key)
    ) {
      return $this->getDispositionSummary($campaign_id, $resolved_ref, [], FALSE);
    }

    if (is_array($trigger) && !empty($trigger['durable'])) {
      $delta = (int) ($trigger['actor_delta'] ?? 0);
      $after_score = DispositionAuthorityContract::clampScore($before_score + $delta);
      $after_attitude = DispositionAuthorityContract::scoreToAttitude($after_score);

      $profile = $this->npcPsychologyService->loadProfile($campaign_id, $resolved_ref);
      $history = is_array($profile['attitude_history'] ?? NULL) ? $profile['attitude_history'] : [];
      if ($after_attitude !== $before_attitude) {
        $history[] = [
          'attitude' => $after_attitude,
          'reason' => $event_description !== '' ? $event_description : $event_type,
          'timestamp' => date('c'),
        ];
      }
      $this->npcPsychologyService->updateProfile($campaign_id, $resolved_ref, [
        'attitude' => $after_attitude,
        'attitude_history' => $history,
      ]);
    }
    else {
      $this->npcPsychologyService->recordInnerMonologue(
        $campaign_id,
        $resolved_ref,
        $event_type,
        $event_description,
        $event_context
      );
    }

    $relationship_mutation_applied = $this->applyTriggerRelationshipMutation(
      $campaign_id,
      $resolved_ref,
      (string) ($event_context['target_entity_ref'] ?? ''),
      $event_type,
      $event_description,
      $trigger,
      $trigger_idempotency_key,
      $event_context
    );

    $summary = $this->getDispositionSummary($campaign_id, $resolved_ref, [], TRUE);
    $after_score = isset($summary['current_score']) && is_numeric($summary['current_score'])
      ? DispositionAuthorityContract::clampScore((int) round((float) $summary['current_score']))
      : (DispositionAuthorityContract::attitudeToScore((string) ($summary['current_attitude'] ?? '')) ?? 0);
    $this->dispositionEventStoreService->recordDispositionEvent($campaign_id, $resolved_ref, [
      'event_type' => $event_type,
      'idempotency_key' => $trigger_idempotency_key,
      'reason' => $event_description,
      'attitude_before' => $before_attitude,
      'attitude_after' => (string) ($summary['current_attitude'] ?? ''),
      'score_before' => $before_score,
      'score_after' => $after_score,
      'summary' => $summary,
      'context' => $event_context + [
        'trigger' => $trigger,
        'relationship_trigger_applied' => $relationship_mutation_applied,
      ],
    ]);
    $this->dispositionStateStoreService->storeLatestState($campaign_id, $resolved_ref, $summary, [
      'source' => 'apply_disposition_event',
      'event_type' => $event_type,
      'reason' => $event_description,
    ]);

    return $summary;
  }

  /**
   * Apply relationship mutation derived from a normalized trigger.
   */
  protected function applyTriggerRelationshipMutation(
    int $campaign_id,
    string $source_entity_ref,
    string $target_entity_ref,
    string $event_type,
    string $event_description,
    ?array $trigger,
    string $trigger_idempotency_key,
    array $event_context
  ): bool {
    if (
      $campaign_id <= 0
      || !is_array($trigger)
      || $this->relationshipAttitudeService === NULL
      || $this->relationshipsActorIdentityResolver === NULL
    ) {
      return FALSE;
    }

    $target_entity_ref = trim($target_entity_ref);
    if ($target_entity_ref === '') {
      return FALSE;
    }

    $source_identity = $this->relationshipsActorIdentityResolver->resolveInstitutionActorIdentity($campaign_id, $source_entity_ref);
    $target_identity = $this->relationshipsActorIdentityResolver->resolveInstitutionActorIdentity($campaign_id, $target_entity_ref);
    if (!is_array($source_identity) || !is_array($target_identity)) {
      return FALSE;
    }

    $source_type = trim((string) ($source_identity['source_type'] ?? ''));
    $source_id = trim((string) ($source_identity['source_id'] ?? ''));
    $target_type = trim((string) ($target_identity['source_type'] ?? ''));
    $target_id = trim((string) ($target_identity['source_id'] ?? ''));
    if ($source_type === '' || $source_id === '' || $target_type === '' || $target_id === '') {
      return FALSE;
    }

    $relationship_type = trim((string) ($event_context['relationship_type'] ?? 'knows'));
    $relationship_status = trim((string) ($event_context['relationship_status'] ?? 'known'));
    $reason = $event_description !== '' ? $event_description : $event_type;
    $relationship_idempotency_key = $trigger_idempotency_key !== ''
      ? $trigger_idempotency_key . ':relationship'
      : '';
    $shared_context = [
      'idempotency_key' => $relationship_idempotency_key,
      'mutation_source' => 'disposition_trigger',
      'trigger_event_type' => strtolower(trim($event_type)),
      'relationship_type' => $relationship_type,
      'status' => $relationship_status,
    ];

    $relationship_score_override = isset($trigger['relationship_score_override']) && is_numeric($trigger['relationship_score_override'])
      ? DispositionAuthorityContract::clampScore((int) $trigger['relationship_score_override'])
      : NULL;
    if ($relationship_score_override !== NULL) {
      $attitude = DispositionAuthorityContract::scoreToAttitude($relationship_score_override);
      $this->relationshipAttitudeService->upsertRelationshipAttitude(
        $campaign_id,
        $source_type,
        $source_id,
        $target_type,
        $target_id,
        $attitude,
        $relationship_type !== '' ? $relationship_type : 'knows',
        $relationship_status !== '' ? $relationship_status : 'known',
        [
          'score' => $relationship_score_override,
          'score_source' => 'relationship_state_score',
          'reason' => $reason,
        ] + $shared_context
      );
      return TRUE;
    }

    $relationship_delta = (int) ($trigger['relationship_delta'] ?? 0);
    if ($relationship_delta === 0) {
      return FALSE;
    }

    $this->relationshipAttitudeService->applyRelationshipDispositionDelta(
      $campaign_id,
      $source_type,
      $source_id,
      $target_type,
      $target_id,
      $relationship_delta,
      $reason,
      $shared_context
    );

    return TRUE;
  }

  /**
   * Resolve canonical entity_ref candidates for psychology profile ownership.
   */
  protected function resolveEntityRef(int $campaign_id, string $entity_ref): string {
    $entity_ref = trim($entity_ref);
    if ($entity_ref === '') {
      return $entity_ref;
    }

    $candidates = [$entity_ref];
    $colon_pos = strpos($entity_ref, ':');
    if ($colon_pos !== FALSE && $colon_pos < strlen($entity_ref) - 1) {
      $candidates[] = substr($entity_ref, $colon_pos + 1);
    }
    foreach (array_values($candidates) as $candidate) {
      if (!str_starts_with($candidate, 'npc_')) {
        $candidates[] = 'npc_' . $candidate;
      }
      else {
        $unprefixed = substr($candidate, 4);
        if ($unprefixed !== '') {
          $candidates[] = $unprefixed;
        }
      }
    }

    $candidates = array_values(array_unique(array_filter(array_map(static fn($value): string => trim((string) $value), $candidates), static fn($value): bool => $value !== '')));
    foreach ($candidates as $candidate) {
      if (is_array($this->npcPsychologyService->loadProfile($campaign_id, $candidate))) {
        return $candidate;
      }
    }

    return $candidates[0] ?? $entity_ref;
  }

}
