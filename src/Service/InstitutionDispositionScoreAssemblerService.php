<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Centralized assembler for actor->target institutional disposition signals.
 */
class InstitutionDispositionScoreAssemblerService {

  protected const ACTOR_COMPONENT_WEIGHT = 0.00;
  protected const MATRIX_COMPONENT_WEIGHT = 1.00;
  protected const INSTITUTION_SCORE_MIN = -2000;
  protected const INSTITUTION_SCORE_MAX = 2000;

  protected const DOMAIN_WEIGHTS = [
    'political' => 0.50,
    'ancestry' => 0.30,
    'class' => 0.20,
  ];

  protected const MEMBERSHIP_WEIGHTS = [
    'ancestry' => 0.50,
    'class' => 0.50,
  ];
  protected const UNDEAD_INSTITUTION_SUBJECT_ID = 'institution_ancestry_undead';

  public function __construct(
    protected readonly InstitutionMembershipService $institutionMembershipService,
    protected readonly InstitutionDispositionMatrixService $institutionDispositionMatrixService,
    protected readonly RelationshipsActorIdentityResolverService $relationshipsActorIdentityResolver,
  ) {}

  /**
   * @var array<string,array<string,string>|null>
   */
  protected array $identityCache = [];

  /**
   * @var array<string,array<int,array<string,mixed>>>
   */
  protected array $sentimentsCache = [];

  /**
   * @var array<string,array<int,array<string,mixed>>>
   */
  protected array $membershipsCache = [];

  /**
   * @var array<string,array<int,array<string,mixed>>>
   */
  protected array $matrixEdgeCache = [];

  /**
   * @var array<string,array<string,mixed>>
   */
  protected array $adjustmentCache = [];
  /**
   * @var array<string,mixed>|null
   */
  protected ?array $assemblerPolicyCache = NULL;

  /**
   * Build one canonical institution adjustment for source->target actor pair.
   *
   * @return array<string,mixed>
   *   Canonical institutional adjustment payload.
   */
  public function buildActorTargetInstitutionAdjustment(
    int $campaign_id,
    string $source_actor_ref,
    string $target_actor_ref
  ): array {
    $cache_key = $campaign_id . '|' . trim($source_actor_ref) . '|' . trim($target_actor_ref);
    if (isset($this->adjustmentCache[$cache_key])) {
      return $this->adjustmentCache[$cache_key];
    }
    $authority = [
      'actor_sentiment' => 'institution_membership_service',
      'institution_matrix' => 'institution_disposition_matrix_service',
      'assembler' => 'institution_disposition_score_assembler',
    ];
    if ($campaign_id <= 0 || trim($source_actor_ref) === '' || trim($target_actor_ref) === '') {
      return $this->adjustmentCache[$cache_key] = $this->buildNeutralResponse($authority);
    }

    $source = $this->resolveActorIdentityCached($campaign_id, $source_actor_ref);
    $target = $this->resolveActorIdentityCached($campaign_id, $target_actor_ref);
    if (!is_array($source) || !is_array($target)) {
      return $this->adjustmentCache[$cache_key] = $this->buildNeutralResponse($authority);
    }

    $source_sentiments = $this->resolveActorSentimentsCached($campaign_id, (string) $source['source_type'], (string) $source['source_id']);
    $source_memberships = $this->resolveActorMembershipsCached($campaign_id, (string) $source['source_type'], (string) $source['source_id'], $source_actor_ref);
    $target_memberships = $this->resolveActorMembershipsCached($campaign_id, (string) $target['source_type'], (string) $target['source_id'], $target_actor_ref);

    $actor_component_payload = $this->buildActorSentimentComponent($source_sentiments, $target_memberships);
    $matrix_component_payload = $this->buildInstitutionMatrixComponent($campaign_id, $source_memberships, $target_memberships);
    [$actor_component_weight, $matrix_component_weight] = $this->resolveComponentBlendWeights();

    $actor_component = (int) ($actor_component_payload['score'] ?? 0);
    $matrix_component = (int) ($matrix_component_payload['score'] ?? 0);
    $assembled_score = $this->clampInstitutionScore(
      (int) round(
        ($actor_component * $actor_component_weight)
        + ($matrix_component * $matrix_component_weight)
      )
    );

    return $this->adjustmentCache[$cache_key] = [
      'score' => $assembled_score,
      'weighted_score' => (int) round($assembled_score * 0.20),
      'breakdown' => [
        'actor_sentiment' => is_array($actor_component_payload['breakdown'] ?? NULL) ? $actor_component_payload['breakdown'] : [],
        'institution_matrix' => is_array($matrix_component_payload['breakdown'] ?? NULL) ? $matrix_component_payload['breakdown'] : [],
      ],
      'components' => [
        'actor_sentiment_component' => $actor_component,
        'institution_matrix_component' => $matrix_component,
      ],
      'weights' => [
        'actor_sentiment_component' => $actor_component_weight,
        'institution_matrix_component' => $matrix_component_weight,
      ],
      'equation' => sprintf(
        'clamp(round((%d * %.2f) + (%d * %.2f)), %d, %d) = %d',
        $actor_component,
        $actor_component_weight,
        $matrix_component,
        $matrix_component_weight,
        self::INSTITUTION_SCORE_MIN,
        self::INSTITUTION_SCORE_MAX,
        $assembled_score
      ),
      'authority' => $authority,
    ];
  }

  /**
   * Build actor-held sentiment component toward target memberships.
   *
   * @param array<int,array<string,mixed>> $source_sentiments
   * @param array<int,array<string,mixed>> $target_memberships
   *
   * @return array{score:int,breakdown:array<int,array<string,mixed>>}
   *   Component score and explainability rows.
   */
  protected function buildActorSentimentComponent(array $source_sentiments, array $target_memberships): array {
    $sentiments_by_subject = [];
    foreach ($source_sentiments as $sentiment_row) {
      if (!is_array($sentiment_row)) {
        continue;
      }
      $subject_id = trim((string) ($sentiment_row['target_id'] ?? ''));
      if ($subject_id === '') {
        continue;
      }
      $sentiments_by_subject[$subject_id] = $sentiment_row;
    }

    $weighted_total = 0.0;
    $weight_total = 0.0;
    $breakdown = [];
    foreach ($target_memberships as $membership_row) {
      if (!is_array($membership_row)) {
        continue;
      }
      $subject_id = trim((string) ($membership_row['target_id'] ?? ''));
      if ($subject_id === '') {
        continue;
      }
      $domain = trim((string) ($membership_row['sentiment_domain'] ?? ''));
      $domain_weight = $this->resolveDomainWeight($domain);
      $sentiment = is_array($sentiments_by_subject[$subject_id] ?? NULL) ? $sentiments_by_subject[$subject_id] : NULL;
      $knowledge_state = strtolower(trim((string) ($sentiment['knowledge_state'] ?? 'unknown')));
      $knowledge_weight = $knowledge_state === 'known' ? 1.0 : 0.35;
      $raw_score = $sentiment !== NULL ? (int) ($sentiment['score'] ?? 0) : 0;

      $effective_weight = $domain_weight * $knowledge_weight;
      $weighted_component = $raw_score * $effective_weight;
      $weighted_total += $weighted_component;
      $weight_total += $effective_weight;

      $breakdown[] = [
        'institution_subject_id' => $subject_id,
        'institution_name' => (string) ($membership_row['target_display_name'] ?? $subject_id),
        'sentiment_domain' => $domain,
        'domain_weight' => $domain_weight,
        'knowledge_state' => $knowledge_state,
        'knowledge_weight' => $knowledge_weight,
        'raw_score' => $raw_score,
        'weighted_component' => (int) round($weighted_component),
        'source' => $sentiment !== NULL ? 'actor_sentiment' : 'missing-neutral-default',
      ];
    }

    $score = $weight_total > 0 ? (int) round($weighted_total / $weight_total) : 0;
    return [
      'score' => $this->clampInstitutionScore($score),
      'breakdown' => $breakdown,
    ];
  }

  /**
   * Build institution->institution matrix component for source/target memberships.
   *
   * @param array<int,array<string,mixed>> $source_memberships
   * @param array<int,array<string,mixed>> $target_memberships
   *
   * @return array{score:int,breakdown:array<int,array<string,mixed>>}
   *   Component score and explainability rows.
   */
  protected function buildInstitutionMatrixComponent(int $campaign_id, array $source_memberships, array $target_memberships): array {
    $weighted_total = 0.0;
    $weight_total = 0.0;
    $breakdown = [];

    foreach ($source_memberships as $source_membership) {
      if (!is_array($source_membership)) {
        continue;
      }
      $source_subject_id = trim((string) ($source_membership['target_id'] ?? ''));
      $source_domain = trim((string) ($source_membership['sentiment_domain'] ?? ''));
      if ($source_subject_id === '' || !$this->isMatrixDomain($source_domain)) {
        continue;
      }

      foreach ($target_memberships as $target_membership) {
        if (!is_array($target_membership)) {
          continue;
        }
        $target_subject_id = trim((string) ($target_membership['target_id'] ?? ''));
        $target_domain = trim((string) ($target_membership['sentiment_domain'] ?? ''));
        if ($target_subject_id === '' || !$this->isMatrixDomain($target_domain)) {
          continue;
        }
        if ($source_subject_id === $target_subject_id) {
          continue;
        }

        $edge = $this->loadMatrixEdgeCached($campaign_id, $source_subject_id, $target_subject_id);
        $state = is_array($edge['relationship_state'] ?? NULL) ? $edge['relationship_state'] : [];
        $raw_score = $edge !== NULL ? (int) ($state['score'] ?? 0) : 0;
        // NOTE: Membership weights are currently policy constants in code.
        // If we expand relationship-complexity tiers later, lift this into a
        // configurable/runtime policy surface instead of static weighting.
        $source_weight = $this->resolveMembershipWeight($source_domain, $source_subject_id);
        $target_weight = $this->resolveMembershipWeight($target_domain, $target_subject_id);
        if (
          $source_domain === 'ancestry'
          && $target_domain === 'ancestry'
          && (
            $source_subject_id === self::UNDEAD_INSTITUTION_SUBJECT_ID
            || $target_subject_id === self::UNDEAD_INSTITUTION_SUBJECT_ID
          )
        ) {
          $undead_pair_weight = $this->resolveUndeadAncestryPairWeight();
          $source_weight = $undead_pair_weight;
          $target_weight = $undead_pair_weight;
        }
        $effective_weight = $source_weight * $target_weight;
        $weighted_component = $raw_score * $effective_weight;

        $weighted_total += $weighted_component;
        $weight_total += $effective_weight;

        $breakdown[] = [
          'source_subject_id' => $source_subject_id,
          'source_subject_name' => (string) ($source_membership['target_display_name'] ?? $source_subject_id),
          'source_domain' => $source_domain,
          'source_weight' => $source_weight,
          'target_subject_id' => $target_subject_id,
          'target_subject_name' => (string) ($target_membership['target_display_name'] ?? $target_subject_id),
          'target_domain' => $target_domain,
          'target_weight' => $target_weight,
          'raw_score' => $raw_score,
          'weighted_component' => (int) round($weighted_component),
          'matrix_state' => (string) ($state['matrix_state'] ?? ($edge !== NULL ? 'defaulted' : 'defaulted')),
          'mutation_state' => (string) ($state['mutation_state'] ?? ($edge !== NULL ? 'seeded' : 'seeded')),
          'source' => $edge !== NULL ? 'institution_matrix_edge' : 'missing-neutral-default',
        ];
      }
    }

    $score = $weight_total > 0 ? (int) round($weighted_total / $weight_total) : 0;
    return [
      'score' => $this->clampInstitutionScore($score),
      'breakdown' => $breakdown,
    ];
  }

  /**
   * Returns whether a sentiment domain participates in matrix pairing.
   */
  protected function isMatrixDomain(string $sentiment_domain): bool {
    return in_array($sentiment_domain, ['ancestry', 'class'], TRUE);
  }

  /**
   * Resolve matrix membership weight with undead ancestry override.
   */
  protected function resolveMembershipWeight(string $domain, string $subject_id): float {
    $membership_weights = $this->resolveMembershipWeights();
    $base_weight = (float) ($membership_weights[$domain] ?? 0.15);
    if ($domain === 'ancestry' && trim($subject_id) === self::UNDEAD_INSTITUTION_SUBJECT_ID) {
      return $this->resolveUndeadAncestryPairWeight();
    }
    return $base_weight;
  }

  /**
   * Build neutral fallback response payload.
   *
   * @param array<string,string> $authority
   *   Canonical authority metadata.
   *
   * @return array<string,mixed>
   *   Neutral output payload.
   */
  protected function buildNeutralResponse(array $authority): array {
    [$actor_component_weight, $matrix_component_weight] = $this->resolveComponentBlendWeights();
    return [
      'score' => 0,
      'weighted_score' => 0,
      'breakdown' => [
        'actor_sentiment' => [],
        'institution_matrix' => [],
      ],
      'components' => [
        'actor_sentiment_component' => 0,
        'institution_matrix_component' => 0,
      ],
      'weights' => [
        'actor_sentiment_component' => $actor_component_weight,
        'institution_matrix_component' => $matrix_component_weight,
      ],
      'equation' => sprintf(
        'clamp(round((0 * %.2f) + (0 * %.2f)), %d, %d) = 0',
        $actor_component_weight,
        $matrix_component_weight,
        self::INSTITUTION_SCORE_MIN,
        self::INSTITUTION_SCORE_MAX
      ),
      'authority' => $authority,
    ];
  }

  /**
   * Clamp institution-only component scores to matrix-supported bounds.
   */
  protected function clampInstitutionScore(int $score): int {
    return max(self::INSTITUTION_SCORE_MIN, min(self::INSTITUTION_SCORE_MAX, $score));
  }

  /**
   * Resolve policy configuration with safe defaults.
   *
   * @return array<string,mixed>
   */
  protected function resolveAssemblerPolicy(): array {
    if ($this->assemblerPolicyCache !== NULL) {
      return $this->assemblerPolicyCache;
    }
    $policy = [
      'actor_component_weight' => self::ACTOR_COMPONENT_WEIGHT,
      'matrix_component_weight' => self::MATRIX_COMPONENT_WEIGHT,
      'domain_weights' => self::DOMAIN_WEIGHTS,
      'membership_weights' => self::MEMBERSHIP_WEIGHTS,
      'undead_ancestry_pair_weight' => 1.00,
    ];
    if (\Drupal::hasService('config.factory')) {
      $config = \Drupal::config('dungeoncrawler_content.settings');
      if ($config) {
        $actor_weight = $config->get('institution_disposition.actor_component_weight');
        $matrix_weight = $config->get('institution_disposition.matrix_component_weight');
        $domain_political = $config->get('institution_disposition.domain_weight_political');
        $domain_ancestry = $config->get('institution_disposition.domain_weight_ancestry');
        $domain_class = $config->get('institution_disposition.domain_weight_class');
        $membership_ancestry = $config->get('institution_disposition.membership_weight_ancestry');
        $membership_class = $config->get('institution_disposition.membership_weight_class');
        $undead_pair_weight = $config->get('institution_disposition.undead_ancestry_pair_weight');
        if (is_numeric($actor_weight)) {
          $policy['actor_component_weight'] = (float) $actor_weight;
        }
        if (is_numeric($matrix_weight)) {
          $policy['matrix_component_weight'] = (float) $matrix_weight;
        }
        if (is_numeric($domain_political)) {
          $policy['domain_weights']['political'] = (float) $domain_political;
        }
        if (is_numeric($domain_ancestry)) {
          $policy['domain_weights']['ancestry'] = (float) $domain_ancestry;
        }
        if (is_numeric($domain_class)) {
          $policy['domain_weights']['class'] = (float) $domain_class;
        }
        if (is_numeric($membership_ancestry)) {
          $policy['membership_weights']['ancestry'] = (float) $membership_ancestry;
        }
        if (is_numeric($membership_class)) {
          $policy['membership_weights']['class'] = (float) $membership_class;
        }
        if (is_numeric($undead_pair_weight)) {
          $policy['undead_ancestry_pair_weight'] = (float) $undead_pair_weight;
        }
      }
    }
    $this->assemblerPolicyCache = $policy;
    return $policy;
  }

  /**
   * @return array{0:float,1:float}
   */
  protected function resolveComponentBlendWeights(): array {
    $policy = $this->resolveAssemblerPolicy();
    return [
      (float) ($policy['actor_component_weight'] ?? self::ACTOR_COMPONENT_WEIGHT),
      (float) ($policy['matrix_component_weight'] ?? self::MATRIX_COMPONENT_WEIGHT),
    ];
  }

  protected function resolveDomainWeight(string $domain): float {
    $policy = $this->resolveAssemblerPolicy();
    $domain_weights = is_array($policy['domain_weights'] ?? NULL) ? $policy['domain_weights'] : self::DOMAIN_WEIGHTS;
    return (float) ($domain_weights[$domain] ?? 0.15);
  }

  /**
   * @return array<string,float>
   */
  protected function resolveMembershipWeights(): array {
    $policy = $this->resolveAssemblerPolicy();
    $weights = is_array($policy['membership_weights'] ?? NULL) ? $policy['membership_weights'] : self::MEMBERSHIP_WEIGHTS;
    return [
      'ancestry' => (float) ($weights['ancestry'] ?? 0.50),
      'class' => (float) ($weights['class'] ?? 0.50),
    ];
  }

  protected function resolveUndeadAncestryPairWeight(): float {
    $policy = $this->resolveAssemblerPolicy();
    $weight = (float) ($policy['undead_ancestry_pair_weight'] ?? 1.00);
    return $weight > 0 ? $weight : 1.00;
  }

  /**
   * Resolve actor identity with request-scope memoization.
   *
   * @return array<string,string>|null
   */
  protected function resolveActorIdentityCached(int $campaign_id, string $actor_ref): ?array {
    $cache_key = $campaign_id . '|' . trim($actor_ref);
    if (!array_key_exists($cache_key, $this->identityCache)) {
      $resolved = $this->relationshipsActorIdentityResolver->resolveInstitutionActorIdentity($campaign_id, $actor_ref);
      $this->identityCache[$cache_key] = is_array($resolved) ? $resolved : NULL;
    }
    return $this->identityCache[$cache_key];
  }

  /**
   * Resolve actor sentiments with request-scope memoization.
   *
   * @return array<int,array<string,mixed>>
   */
  protected function resolveActorSentimentsCached(int $campaign_id, string $source_type, string $source_id): array {
    $cache_key = $campaign_id . '|' . $source_type . '|' . $source_id;
    if (!isset($this->sentimentsCache[$cache_key])) {
      $rows = $this->institutionMembershipService->listActorInstitutionSentiments($campaign_id, $source_type, $source_id);
      $this->sentimentsCache[$cache_key] = is_array($rows) ? $rows : [];
    }
    return $this->sentimentsCache[$cache_key];
  }

  /**
   * Resolve actor memberships with fallback and request-scope memoization.
   *
   * @return array<int,array<string,mixed>>
   */
  protected function resolveActorMembershipsCached(
    int $campaign_id,
    string $source_type,
    string $source_id,
    string $actor_ref
  ): array {
    $cache_key = $campaign_id . '|' . $source_type . '|' . $source_id . '|' . trim($actor_ref);
    if (!isset($this->membershipsCache[$cache_key])) {
      $memberships = $this->institutionMembershipService->listActorInstitutionMemberships($campaign_id, $source_type, $source_id);
      if (!is_array($memberships) || $memberships === []) {
        $memberships = $this->relationshipsActorIdentityResolver->buildFallbackTargetInstitutionMemberships($campaign_id, $actor_ref);
      }
      $this->membershipsCache[$cache_key] = is_array($memberships) ? $memberships : [];
    }
    return $this->membershipsCache[$cache_key];
  }

  /**
   * Load matrix edge row with request-scope memoization.
   *
   * @return array<string,mixed>|null
   */
  protected function loadMatrixEdgeCached(int $campaign_id, string $source_subject_id, string $target_subject_id): ?array {
    $cache_key = $campaign_id . '|' . $source_subject_id . '|' . $target_subject_id;
    if (!array_key_exists($cache_key, $this->matrixEdgeCache)) {
      $edge = $this->institutionDispositionMatrixService->loadInstitutionDispositionEdge($campaign_id, $source_subject_id, $target_subject_id);
      $this->matrixEdgeCache[$cache_key] = is_array($edge) ? $edge : NULL;
    }
    return $this->matrixEdgeCache[$cache_key];
  }

}
