<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Canonical actor-context projection builder for room/combat surfaces.
 */
class ActorContextProjectionService {

  protected ActorDispositionService $actorDispositionService;
  protected RelationshipAttitudeService $relationshipAttitudeService;
  protected StanceStateStoreService $stanceStateStoreService;
  protected DispositionResolverService $dispositionResolverService;
  protected ?InstitutionDispositionScoreAssemblerService $institutionDispositionScoreAssemblerService;
  protected ?ActorNarrativeContextService $actorNarrativeContextService;

  public function __construct(
    ActorDispositionService $actor_disposition_service,
    RelationshipAttitudeService $relationship_attitude_service,
    StanceStateStoreService $stance_state_store_service,
    DispositionResolverService $disposition_resolver_service,
    InstitutionDispositionScoreAssemblerService|ActorNarrativeContextService|null $institution_disposition_score_assembler_service = NULL,
    InstitutionDispositionScoreAssemblerService|ActorNarrativeContextService|null $actor_narrative_context_service = NULL
  ) {
    $institution_score_assembler = NULL;
    $actor_narrative_context = NULL;
    foreach ([
      $institution_disposition_score_assembler_service,
      $actor_narrative_context_service,
    ] as $dependency) {
      if ($dependency instanceof InstitutionDispositionScoreAssemblerService) {
        $institution_score_assembler = $dependency;
      }
      if ($dependency instanceof ActorNarrativeContextService) {
        $actor_narrative_context = $dependency;
      }
    }

    $this->actorDispositionService = $actor_disposition_service;
    $this->relationshipAttitudeService = $relationship_attitude_service;
    $this->stanceStateStoreService = $stance_state_store_service;
    $this->dispositionResolverService = $disposition_resolver_service;
    $this->institutionDispositionScoreAssemblerService = $institution_score_assembler;
    $this->actorNarrativeContextService = $actor_narrative_context;
  }

  /**
   * Build a normalized disposition summary projection.
   */
  public function buildDispositionSummary(int $campaign_id, string $entity_ref, array $live_entity = []): array {
    $summary = $this->actorDispositionService->getDispositionSummary($campaign_id, $entity_ref, $live_entity);
    $attitude = DispositionAuthorityContract::normalizeAttitudeLabel((string) ($summary['current_attitude'] ?? ''));
    if ($attitude === '') {
      $attitude = DispositionAuthorityContract::LABEL_INDIFFERENT;
    }

    $score = isset($summary['current_score']) && is_numeric($summary['current_score'])
      ? DispositionAuthorityContract::clampScore((int) round((float) $summary['current_score']))
      : DispositionAuthorityContract::attitudeToScore($attitude);

    return [
      'entity_ref' => (string) ($summary['entity_ref'] ?? $entity_ref),
      'display_name' => (string) ($summary['display_name'] ?? $entity_ref),
      'attitude' => $attitude,
      'score' => $score ?? 0,
      'score_source' => (string) ($summary['score_source'] ?? 'attitude_projection'),
      'motivations' => (string) ($summary['motivations'] ?? ''),
      'fears' => (string) ($summary['fears'] ?? ''),
      'bonds' => (string) ($summary['bonds'] ?? ''),
      'latest_thought' => is_array($summary['latest_thought'] ?? NULL) ? $summary['latest_thought'] : NULL,
      'source' => (string) ($summary['summary_reason'] ?? 'profile_defaulted'),
      'authority' => [
        'writer' => DispositionAuthorityContract::AUTHORITY_ACTOR_BASELINE_STATE,
        'resolver' => DispositionAuthorityContract::AUTHORITY_RESOLVER,
      ],
    ];
  }

  /**
   * Build normalized aggression policy summary projection.
   */
  public function buildAggressionSummary(array $policy): array {
    $basis = is_array($policy['basis'] ?? NULL) ? $policy['basis'] : [];
    return [
      'state' => (string) ($policy['escalation_state'] ?? 'calm'),
      'entry_authorized' => !empty($policy['can_initiate_combat']),
      'entry_blockers' => is_array($policy['entry_blockers'] ?? NULL) ? array_values($policy['entry_blockers']) : [],
      'reason' => (string) ($policy['reason'] ?? ''),
      'target_ids' => is_array($policy['target_ids'] ?? NULL) ? array_values($policy['target_ids']) : [],
      'basis' => [
        'aggression_signal' => (string) ($basis['aggression_signal'] ?? 'none'),
        'threat_level' => (string) ($basis['threat_level'] ?? 'none'),
        'threat_score' => isset($basis['threat_score']) && is_numeric($basis['threat_score']) ? (int) $basis['threat_score'] : 0,
        'hostility_pressure' => isset($basis['hostility_pressure']) && is_numeric($basis['hostility_pressure']) ? (int) $basis['hostility_pressure'] : 0,
        'actor_attitude' => (string) ($basis['actor_attitude'] ?? ''),
        'actor_score' => isset($basis['actor_score']) && is_numeric($basis['actor_score']) ? (int) $basis['actor_score'] : 0,
        'relationship_attitude' => (string) ($basis['relationship_attitude'] ?? ''),
        'relationship_score' => isset($basis['relationship_score']) && is_numeric($basis['relationship_score']) ? (int) $basis['relationship_score'] : 0,
        'actor_attitude_source' => (string) ($basis['actor_attitude_source'] ?? ''),
        'relationship_attitude_source' => (string) ($basis['relationship_attitude_source'] ?? ''),
        'actor_stance' => (string) ($basis['actor_stance'] ?? ''),
        'actor_stance_confidence' => isset($basis['actor_stance_confidence']) && is_numeric($basis['actor_stance_confidence']) ? (int) $basis['actor_stance_confidence'] : 0,
        'actor_stance_reason' => (string) ($basis['actor_stance_reason'] ?? ''),
        'actor_process_flow' => (string) ($basis['actor_process_flow'] ?? ''),
        'actor_process_flow_reason' => (string) ($basis['actor_process_flow_reason'] ?? ''),
        'actor_process_flow_blockers' => is_array($basis['actor_process_flow_blockers'] ?? NULL)
          ? array_values($basis['actor_process_flow_blockers'])
          : [],
      ],
    ];
  }

  /**
   * Build a normalized stance summary projection.
   */
  public function buildStanceSummary(array $character_data = [], int $campaign_id = 0, string $entity_ref = ''): array {
    $stored_summary = [];
    $stored_updated_at = '';
    $stored = ($campaign_id > 0 && trim($entity_ref) !== '')
      ? $this->stanceStateStoreService->loadLatestState($campaign_id, $entity_ref)
      : NULL;
    if (is_array($stored['summary'] ?? NULL)) {
      $stored_summary = $stored['summary'];
      $stored_updated_at = (string) ($stored_summary['updated_at'] ?? '');
      if ($stored_updated_at === '' && isset($stored['updated_at'])) {
        $timestamp = (int) $stored['updated_at'];
        $stored_updated_at = $timestamp > 0 ? date('c', $timestamp) : '';
      }
    }

    $stance_state = is_array($character_data['stance_state'] ?? NULL) ? $character_data['stance_state'] : [];
    $active = is_array($stored_summary['active_stances'] ?? NULL)
      ? array_values($stored_summary['active_stances'])
      : (is_array($stance_state['active_stances'] ?? NULL) ? array_values($stance_state['active_stances']) : []);
    $normalized_active = [];
    foreach ($active as $entry) {
      if (!is_array($entry)) {
        continue;
      }
      $stance_id = trim((string) ($entry['stance_id'] ?? ''));
      if ($stance_id === '') {
        continue;
      }
      $normalized_active[] = [
        'stance_id' => $stance_id,
        'entered_at' => (string) ($entry['entered_at'] ?? ''),
        'source_type' => (string) ($entry['source_type'] ?? ''),
        'source_id' => (string) ($entry['source_id'] ?? ''),
      ];
    }

    $arcane_cascade_active = !empty($stored_summary['arcane_cascade_active']);
    foreach ($normalized_active as $entry) {
      if (($entry['stance_id'] ?? '') === 'arcane_cascade') {
        $arcane_cascade_active = TRUE;
        break;
      }
    }

    return [
      'active_stances' => $normalized_active,
      'max_active_stances' => max(1, (int) ($stored_summary['max_active_stances'] ?? ($stance_state['max_active_stances'] ?? 1))),
      'arcane_cascade_active' => $arcane_cascade_active,
      'behavioral_stance' => is_array($stored_summary['behavioral_stance'] ?? NULL) ? $stored_summary['behavioral_stance'] : [],
      'updated_at' => $stored_updated_at !== '' ? $stored_updated_at : (string) ($stance_state['updated_at'] ?? ''),
    ];
  }

  /**
   * Build per-target relationship attitude projection from one source actor.
   *
   * @param array<int, string> $target_entity_refs
   *   Candidate target actor/entity refs.
   *
   * @return array<string, string>
   *   Map keyed by target ref with normalized attitude values.
   */
  public function buildRelationshipAttitudesSummary(int $campaign_id, string $entity_ref, array $target_entity_refs = []): array {
    if ($campaign_id <= 0 || trim($entity_ref) === '') {
      return [];
    }

    $resolved_disposition_map = $this->buildResolvedDispositionByTarget(
      $campaign_id,
      $entity_ref,
      $target_entity_refs
    );
    return $this->projectRelationshipAttitudesFromResolvedDispositionMap($resolved_disposition_map);
  }

  /**
   * Build canonical resolved disposition DTO map for source->target pairs.
   *
   * @param array<int,string> $target_entity_refs
   * @param array<string,mixed> $context
   *
   * @return array<string,array<string,mixed>>
   */
  public function buildResolvedDispositionByTarget(int $campaign_id, string $entity_ref, array $target_entity_refs = [], array $context = []): array {
    if ($campaign_id <= 0 || trim($entity_ref) === '') {
      return [];
    }

    $targets = array_values(array_unique(array_filter(array_map(
      static fn($value): string => trim((string) $value),
      $target_entity_refs
    ), static fn(string $value): bool => $value !== '' && $value !== trim($entity_ref))));

    if ($targets === []) {
      return [];
    }

    if (!$this->institutionDispositionScoreAssemblerService instanceof InstitutionDispositionScoreAssemblerService) {
      return $this->dispositionResolverService->resolveDispositionMap(
        $campaign_id,
        $entity_ref,
        $targets,
        $context
      );
    }

    $resolved = [];
    foreach ($targets as $target_ref) {
      $institution = $this->institutionDispositionScoreAssemblerService
        ->buildActorTargetInstitutionAdjustment($campaign_id, $entity_ref, $target_ref);
      $resolved[$target_ref] = $this->dispositionResolverService->resolveActorTargetDisposition(
        $campaign_id,
        $entity_ref,
        $target_ref,
        $context + [
          'institution_score' => (int) ($institution['score'] ?? 0),
        ]
      );
    }

    return $resolved;
  }

  /**
   * Build combat entry summary projection.
   */
  public function buildCombatEntrySummary(
    string $status,
    int $campaign_id,
    string $room_id,
    array $policy,
    array $transition = [],
    array $runtime_snapshot = []
  ): array {
    $encounter_id = (int) (
      $transition['encounter_id']
      ?? $transition['state']['encounter_id']
      ?? $runtime_snapshot['encounter_id']
      ?? 0
    );

    return [
      'status' => $status,
      'campaign_id' => $campaign_id,
      'room_id' => $room_id,
      'encounter_id' => $encounter_id > 0 ? $encounter_id : NULL,
      'aggression' => $this->buildAggressionSummary($policy),
      'started' => $status === 'entered',
    ];
  }

  /**
   * Build shared resolved actor context projection.
   */
  public function buildResolvedActorContext(
    int $campaign_id,
    string $entity_ref,
    array $live_entity = [],
    array $character_data = [],
    array $policy = [],
    array $target_entity_refs = []
  ): array {
    $resolved_disposition_by_target = $this->buildResolvedDispositionByTarget($campaign_id, $entity_ref, $target_entity_refs);

    return [
      'entity_ref' => $entity_ref,
      'disposition' => $this->buildDispositionSummary($campaign_id, $entity_ref, $live_entity),
      'resolved_disposition_by_target' => $resolved_disposition_by_target,
      'aggression' => $this->buildAggressionSummary($policy),
      'stance' => $this->buildStanceSummary($character_data, $campaign_id, $entity_ref),
      'process_flow' => $this->buildProcessFlowSummary($campaign_id, $entity_ref),
      'relationship_attitudes' => $this->projectRelationshipAttitudesFromResolvedDispositionMap($resolved_disposition_by_target),
      'narrative_context' => $this->actorNarrativeContextService
        ? $this->actorNarrativeContextService->buildContextEnvelope($campaign_id, $entity_ref)
        : [],
    ];
  }

  /**
   * Build normalized process-flow summary projection.
   */
  public function buildProcessFlowSummary(int $campaign_id, string $entity_ref): array {
    if ($campaign_id <= 0 || trim($entity_ref) === '') {
      return [];
    }
    if (!\Drupal::hasService('dungeoncrawler_content.process_flow_state_store_service')) {
      return [];
    }
    $service = \Drupal::service('dungeoncrawler_content.process_flow_state_store_service');
    if (!$service instanceof ProcessFlowStateStoreService) {
      return [];
    }
    $stored = $service->loadLatestState($campaign_id, $entity_ref);
    if (!is_array($stored)) {
      return [];
    }
    return [
      'entity_ref' => (string) ($stored['entity_ref'] ?? $entity_ref),
      'updated_at' => (int) ($stored['updated_at'] ?? 0),
      'summary' => is_array($stored['summary'] ?? NULL) ? $stored['summary'] : [],
      'meta' => is_array($stored['meta'] ?? NULL) ? $stored['meta'] : [],
    ];
  }

  /**
   * Project compatibility relationship-attitude labels from resolved disposition.
   *
   * @param array<string,array<string,mixed>> $resolved_disposition_map
   *   Resolver DTO map keyed by target ref.
   *
   * @return array<string,string>
   *   Target keyed attitude label map.
   */
  protected function projectRelationshipAttitudesFromResolvedDispositionMap(array $resolved_disposition_map): array {
    $projected = [];
    foreach ($resolved_disposition_map as $target_ref => $dto) {
      if (!is_array($dto)) {
        continue;
      }
      $attitude = DispositionAuthorityContract::normalizeAttitudeLabel((string) ($dto['effective_disposition_label'] ?? ''));
      if ($attitude === '') {
        $score = isset($dto['effective_disposition_score']) && is_numeric($dto['effective_disposition_score'])
          ? DispositionAuthorityContract::clampScore((int) round((float) $dto['effective_disposition_score']))
          : NULL;
        if ($score !== NULL) {
          $attitude = DispositionAuthorityContract::scoreToAttitude($score);
        }
      }
      if ($attitude === '') {
        continue;
      }
      $projected[(string) $target_ref] = $attitude;
    }

    return $projected;
  }

}
