<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Canonical read-model resolver for effective disposition DTOs.
 */
class DispositionResolverService {

  public function __construct(
    protected readonly ActorDispositionService $actorDispositionService,
    protected readonly RelationshipAttitudeService $relationshipAttitudeService,
    protected readonly DispositionSceneContextService $sceneContextService,
  ) {}

  /**
   * Resolve effective disposition from source actor toward one target actor.
   *
   * @param array<string,mixed> $context
   *
   * @return array<string,mixed>
   *   Canonical resolved disposition DTO.
   */
  public function resolveActorTargetDisposition(
    int $campaign_id,
    string $source_entity_ref,
    string $target_entity_ref,
    array $context = []
  ): array {
    $source_summary = is_array($context['source_summary_override'] ?? NULL)
      ? $context['source_summary_override']
      : $this->actorDispositionService->getDispositionSummary($campaign_id, $source_entity_ref, [], FALSE);
    $source_score = isset($source_summary['current_score']) && is_numeric($source_summary['current_score'])
      ? DispositionAuthorityContract::clampScore((int) round((float) $source_summary['current_score']))
      : (DispositionAuthorityContract::attitudeToScore((string) ($source_summary['current_attitude'] ?? '')) ?? 0);
    $edge = is_array($context['relationship_edge_override'] ?? NULL)
      ? $context['relationship_edge_override']
      : $this->relationshipAttitudeService->resolveEdgeDispositionDetails($source_entity_ref, $target_entity_ref, $campaign_id);
    $relationship_score = isset($edge['score']) && is_numeric($edge['score'])
      ? DispositionAuthorityContract::clampScore((int) round((float) $edge['score']))
      : 0;

    $scene_context = $context;
    unset($scene_context['source_summary_override'], $scene_context['relationship_edge_override']);
    $scene = $this->sceneContextService->resolveSceneContext($scene_context);
    $situational_score = (int) ($scene['situational_score'] ?? 0);
    $institution_score = (int) ($scene['institution_score'] ?? 0);
    $recent_harm_score = (int) ($scene['recent_harm_score'] ?? 0);
    $recent_help_score = (int) ($scene['recent_help_score'] ?? 0);
    $coercion_score = (int) ($scene['coercion_score'] ?? 0);
    $recent_impulse_score = (int) ($scene['recent_impulse_score'] ?? 0);
    $threat_level = (string) ($scene['factors']['threat_level'] ?? 'none');

    $weights = [
      'baseline' => 0.20,
      'relationship' => 0.40,
      'situational' => 0.12,
      'institution' => 0.10,
      'recent_harm' => 0.08,
      'recent_help' => 0.08,
      'coercion' => 0.08,
      'recent_impulse' => 0.06,
    ];
    if (!is_numeric($edge['score'] ?? NULL)) {
      $weights['relationship'] = 0.00;
    }

    $weighted_baseline = $source_score * (float) $weights['baseline'];
    $weighted_relationship = $relationship_score * (float) $weights['relationship'];
    $weighted_situational = $situational_score * (float) $weights['situational'];
    $weighted_institution = $institution_score * (float) $weights['institution'];
    $weighted_recent_harm = $recent_harm_score * (float) $weights['recent_harm'];
    $weighted_recent_help = $recent_help_score * (float) $weights['recent_help'];
    $weighted_coercion = $coercion_score * (float) $weights['coercion'];
    $weighted_impulse = $recent_impulse_score * (float) $weights['recent_impulse'];
    $effective_score = DispositionAuthorityContract::clampScore(
      (int) round(
        $weighted_baseline
        + $weighted_relationship
        + $weighted_situational
        + $weighted_institution
        + $weighted_recent_harm
        + $weighted_recent_help
        + $weighted_coercion
        + $weighted_impulse
      )
    );
    $effective_label = DispositionAuthorityContract::scoreToAttitude($effective_score);
    $is_hostile = DispositionAuthorityContract::isHostileScore($effective_score);

    $confidence = 40;
    if (is_numeric($edge['score'] ?? NULL)) {
      $confidence += 30;
    }
    if (
      $situational_score !== 0
      || $institution_score !== 0
      || $recent_harm_score !== 0
      || $recent_help_score !== 0
      || $coercion_score !== 0
      || $recent_impulse_score !== 0
    ) {
      $confidence += 10;
    }
    if (($source_summary['summary_reason'] ?? '') === 'state_store') {
      $confidence += 20;
    }
    $confidence = max(0, min(100, $confidence));

    $positive_factors = [];
    $negative_factors = [];
    foreach ([
      'baseline' => $source_score,
      'relationship' => $relationship_score,
      'situational' => $situational_score,
      'institution' => $institution_score,
      'recent_harm' => $recent_harm_score,
      'recent_help' => $recent_help_score,
      'coercion' => $coercion_score,
      'recent_impulse' => $recent_impulse_score,
    ] as $factor => $value) {
      if ($value > 0) {
        $positive_factors[] = ['factor' => $factor, 'score' => $value];
      }
      elseif ($value < 0) {
        $negative_factors[] = ['factor' => $factor, 'score' => $value];
      }
    }

    return [
      'source_actor_ref' => $source_entity_ref,
      'target_actor_ref' => $target_entity_ref,
      'effective_disposition_score' => $effective_score,
      'effective_disposition_label' => $effective_label,
      'score_confidence' => $confidence,
      'components' => [
        'actor_baseline_score' => $source_score,
        'relationship_score' => $relationship_score,
        'situational_score' => $situational_score,
        'institution_score' => $institution_score,
        'recent_harm_score' => $recent_harm_score,
        'recent_help_score' => $recent_help_score,
        'coercion_score' => $coercion_score,
        'recent_impulse_score' => $recent_impulse_score,
      ],
      'weights' => $weights,
      'equation' => sprintf(
        'clamp((%.2f*%d) + (%.2f*%d) + (%.2f*%d) + (%.2f*%d) + (%.2f*%d) + (%.2f*%d) + (%.2f*%d) + (%.2f*%d), %d, %d) = %d',
        (float) $weights['baseline'],
        $source_score,
        (float) $weights['relationship'],
        $relationship_score,
        (float) $weights['situational'],
        $situational_score,
        (float) $weights['institution'],
        $institution_score,
        (float) $weights['recent_harm'],
        $recent_harm_score,
        (float) $weights['recent_help'],
        $recent_help_score,
        (float) $weights['coercion'],
        $coercion_score,
        (float) $weights['recent_impulse'],
        $recent_impulse_score,
        DispositionAuthorityContract::SCORE_MIN,
        DispositionAuthorityContract::SCORE_MAX,
        $effective_score
      ),
      'dominant_positive_factors' => $positive_factors,
      'dominant_negative_factors' => $negative_factors,
      'relationship_meta' => [
        'edge_attitude' => (string) ($edge['attitude'] ?? ''),
        'edge_score_source' => (string) ($edge['score_source'] ?? 'none'),
        'relationship_type' => (string) ($edge['relationship_type'] ?? ''),
        'relationship_status' => (string) ($edge['status'] ?? ''),
      ],
      'scene_factors' => is_array($scene['factors'] ?? NULL) ? $scene['factors'] : [],
      'aggression_relevance' => [
        'threat_level' => $threat_level ?? 'none',
        'hostility_gate' => $is_hostile,
      ],
      'policy_flags' => [
        'hostile' => $is_hostile,
        'attack_authorized_candidate' => $is_hostile && $confidence >= 50,
      ],
      'authority' => [
        'writer' => [
          DispositionAuthorityContract::AUTHORITY_ACTOR_BASELINE_STATE,
          DispositionAuthorityContract::AUTHORITY_RELATIONSHIP_EDGE_STATE,
          DispositionAuthorityContract::AUTHORITY_SCENE_CONTEXT_STATE,
        ],
        'resolver' => DispositionAuthorityContract::AUTHORITY_RESOLVER,
      ],
    ];
  }

  /**
   * Resolve effective disposition DTO map for one source against many targets.
   *
   * @param array<int,string> $target_entity_refs
   * @param array<string,mixed> $context
   *
   * @return array<string,array<string,mixed>>
   *   Keyed by target ref.
   */
  public function resolveDispositionMap(
    int $campaign_id,
    string $source_entity_ref,
    array $target_entity_refs,
    array $context = []
  ): array {
    $targets = array_values(array_unique(array_filter(array_map(
      static fn($value): string => trim((string) $value),
      $target_entity_refs
    ), static fn(string $value): bool => $value !== '' && $value !== trim($source_entity_ref))));
    $resolved = [];
    foreach ($targets as $target_ref) {
      $resolved[$target_ref] = $this->resolveActorTargetDisposition($campaign_id, $source_entity_ref, $target_ref, $context);
    }
    return $resolved;
  }

}
