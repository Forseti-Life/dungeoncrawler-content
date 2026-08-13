<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Builds relationships matrix read-model payloads for campaign actors.
 */
class RelationshipsMatrixReadModelService {

  protected ActorDispositionService $actorDispositionService;
  protected RelationshipAttitudeService $relationshipAttitudeService;
  protected InstitutionDispositionScoreAssemblerService $institutionDispositionScoreAssemblerService;
  protected RelationshipsActorIdentityResolverService $relationshipsActorIdentityResolver;
  protected DispositionResolverService $dispositionResolverService;
  protected LoggerChannelFactoryInterface $loggerFactory;

  public function __construct(
    ActorDispositionService $actor_disposition_service,
    RelationshipAttitudeService $relationship_attitude_service,
    InstitutionDispositionScoreAssemblerService|InstitutionMembershipService|null $institution_disposition_score_assembler_service = NULL,
    RelationshipsActorIdentityResolverService|InstitutionDispositionMatrixService|null $relationships_actor_identity_resolver = NULL,
    DispositionResolverService|RelationshipsActorIdentityResolverService|null $disposition_resolver_service = NULL,
    LoggerChannelFactoryInterface|DispositionResolverService|null $logger_factory = NULL,
    ?LoggerChannelFactoryInterface $logger_factory_fallback = NULL,
  ) {
    $identity_resolver = NULL;
    if ($relationships_actor_identity_resolver instanceof RelationshipsActorIdentityResolverService) {
      $identity_resolver = $relationships_actor_identity_resolver;
    }
    if ($disposition_resolver_service instanceof RelationshipsActorIdentityResolverService) {
      $identity_resolver = $disposition_resolver_service;
    }

    $disposition_resolver = NULL;
    if ($disposition_resolver_service instanceof DispositionResolverService) {
      $disposition_resolver = $disposition_resolver_service;
    }
    if ($logger_factory instanceof DispositionResolverService) {
      $disposition_resolver = $logger_factory;
    }

    $resolved_logger_factory = NULL;
    if ($logger_factory instanceof LoggerChannelFactoryInterface) {
      $resolved_logger_factory = $logger_factory;
    }
    if ($logger_factory_fallback instanceof LoggerChannelFactoryInterface) {
      $resolved_logger_factory = $logger_factory_fallback;
    }

    $institution_score_assembler = NULL;
    if ($institution_disposition_score_assembler_service instanceof InstitutionDispositionScoreAssemblerService) {
      $institution_score_assembler = $institution_disposition_score_assembler_service;
    }
    if (
      $institution_score_assembler === NULL
      && $institution_disposition_score_assembler_service instanceof InstitutionMembershipService
      && $relationships_actor_identity_resolver instanceof InstitutionDispositionMatrixService
      && $identity_resolver instanceof RelationshipsActorIdentityResolverService
    ) {
      $institution_score_assembler = new InstitutionDispositionScoreAssemblerService(
        $institution_disposition_score_assembler_service,
        $relationships_actor_identity_resolver,
        $identity_resolver
      );
    }

    if (!$institution_score_assembler instanceof InstitutionDispositionScoreAssemblerService && \Drupal::hasService('dungeoncrawler_content.institution_disposition_score_assembler')) {
      $service = \Drupal::service('dungeoncrawler_content.institution_disposition_score_assembler');
      if ($service instanceof InstitutionDispositionScoreAssemblerService) {
        $institution_score_assembler = $service;
      }
    }
    if (!$identity_resolver instanceof RelationshipsActorIdentityResolverService && \Drupal::hasService('dungeoncrawler_content.relationships_actor_identity_resolver')) {
      $service = \Drupal::service('dungeoncrawler_content.relationships_actor_identity_resolver');
      if ($service instanceof RelationshipsActorIdentityResolverService) {
        $identity_resolver = $service;
      }
    }
    if (!$disposition_resolver instanceof DispositionResolverService && \Drupal::hasService('dungeoncrawler_content.disposition_resolver_service')) {
      $service = \Drupal::service('dungeoncrawler_content.disposition_resolver_service');
      if ($service instanceof DispositionResolverService) {
        $disposition_resolver = $service;
      }
    }
    if (!$resolved_logger_factory instanceof LoggerChannelFactoryInterface && \Drupal::hasService('logger.factory')) {
      $service = \Drupal::service('logger.factory');
      if ($service instanceof LoggerChannelFactoryInterface) {
        $resolved_logger_factory = $service;
      }
    }

    if (!$institution_score_assembler instanceof InstitutionDispositionScoreAssemblerService || !$identity_resolver instanceof RelationshipsActorIdentityResolverService || !$disposition_resolver instanceof DispositionResolverService || !$resolved_logger_factory instanceof LoggerChannelFactoryInterface) {
      throw new \InvalidArgumentException(sprintf(
        'RelationshipsMatrixReadModelService dependency resolution failed (assembler=%s, identity=%s, resolver=%s, logger=%s).',
        is_object($institution_score_assembler) ? get_class($institution_score_assembler) : gettype($institution_score_assembler),
        is_object($identity_resolver) ? get_class($identity_resolver) : gettype($identity_resolver),
        is_object($disposition_resolver) ? get_class($disposition_resolver) : gettype($disposition_resolver),
        is_object($resolved_logger_factory) ? get_class($resolved_logger_factory) : gettype($resolved_logger_factory),
      ));
    }

    $this->actorDispositionService = $actor_disposition_service;
    $this->relationshipAttitudeService = $relationship_attitude_service;
    $this->institutionDispositionScoreAssemblerService = $institution_score_assembler;
    $this->relationshipsActorIdentityResolver = $identity_resolver;
    $this->dispositionResolverService = $disposition_resolver;
    $this->loggerFactory = $resolved_logger_factory;
  }

  /**
   * Build relationships matrix payload from canonical actor refs.
   *
   * @param array<int,string> $actor_refs
   *   Unique list of actor refs.
   *
   * @return array<string,mixed>
   *   Matrix API payload.
   */
  public function buildPayload(int $campaign_id, array $actor_refs, string $selected_actor_ref = ''): array {
    try {
      $stage_errors = [];
      $resolved_identities = [];
      foreach ($actor_refs as $actor_ref) {
        try {
          $identity = $this->relationshipsActorIdentityResolver->resolveInstitutionActorIdentity($campaign_id, $actor_ref);
          if (is_array($identity)) {
            $resolved_identities[$actor_ref] = $identity;
          }
        }
        catch (\Throwable $e) {
          $stage_errors[] = [
            'stage' => 'identity_resolution',
            'source_ref' => $actor_ref,
            'message' => $e->getMessage(),
          ];
          $this->loggerFactory->get('dungeoncrawler_content')->error('Relationships matrix identity resolution failed for campaign @campaign actor @actor: @message', [
            '@campaign' => $campaign_id,
            '@actor' => $actor_ref,
            '@message' => $e->getMessage(),
          ]);
        }
      }

      $actor_dispositions = [];
      $disposition_profiles = [];
      $source_summary_overrides = [];
      foreach ($actor_refs as $source_ref) {
        if (!isset($resolved_identities[$source_ref])) {
          $actor_dispositions[$source_ref] = 'indifferent';
          $disposition_profiles[$source_ref] = [
            'attitude' => 'indifferent',
            'score' => 0,
            'summary_reason' => 'unresolved_actor_ref',
            'motivations' => '',
            'fears' => '',
            'bonds' => '',
            'score_formula' => 'attitude_bucket(helpful=100,friendly=50,indifferent=0,unfriendly=-50,hostile=-100)',
          ];
          $source_summary_overrides[$source_ref] = [
            'current_attitude' => 'indifferent',
            'current_score' => 0,
            'summary_reason' => 'unresolved_actor_ref',
          ];
          continue;
        }

        try {
          $summary = $this->actorDispositionService->getDispositionSummary($campaign_id, $source_ref, [], FALSE);
          $normalized_default = $this->normalizeAttitude((string) ($summary['current_attitude'] ?? ''));
          $default_score = isset($summary['current_score']) && is_numeric($summary['current_score'])
            ? $this->clampDispositionScore((int) round((float) $summary['current_score']))
            : $this->attitudeScore($normalized_default);
          $actor_dispositions[$source_ref] = $normalized_default;
          $disposition_profiles[$source_ref] = [
            'attitude' => $normalized_default,
            'score' => $default_score,
            'summary_reason' => (string) ($summary['summary_reason'] ?? ''),
            'motivations' => (string) ($summary['motivations'] ?? ''),
            'fears' => (string) ($summary['fears'] ?? ''),
            'bonds' => (string) ($summary['bonds'] ?? ''),
            'score_formula' => 'state_score_or_attitude_projection',
          ];
          $source_summary_overrides[$source_ref] = $summary;
        }
        catch (\Throwable $e) {
          $stage_errors[] = [
            'stage' => 'disposition_summary',
            'source_ref' => $source_ref,
            'message' => $e->getMessage(),
          ];
          $this->loggerFactory->get('dungeoncrawler_content')->error('Relationships matrix disposition summary failed for campaign @campaign source @source: @message', [
            '@campaign' => $campaign_id,
            '@source' => $source_ref,
            '@message' => $e->getMessage(),
          ]);
          $actor_dispositions[$source_ref] = 'indifferent';
          $disposition_profiles[$source_ref] = [
            'attitude' => 'indifferent',
            'score' => 0,
            'summary_reason' => 'disposition_summary_error',
            'motivations' => '',
            'fears' => '',
            'bonds' => '',
            'score_formula' => 'attitude_bucket(helpful=100,friendly=50,indifferent=0,unfriendly=-50,hostile=-100)',
          ];
          $source_summary_overrides[$source_ref] = [
            'current_attitude' => 'indifferent',
            'current_score' => 0,
            'summary_reason' => 'disposition_summary_error',
          ];
        }
      }

      $matrix = [];
      $calculations = [];
      foreach ($actor_refs as $source_ref) {
        $matrix[$source_ref] = [];
        $calculations[$source_ref] = [];
        $source_default = $actor_dispositions[$source_ref] ?? '';
        foreach ($actor_refs as $target_ref) {
          if ($source_ref === $target_ref) {
            $matrix[$source_ref][$target_ref] = '';
            $calculations[$source_ref][$target_ref] = [
              'rule' => 'self',
              'formula' => 'self',
              'source_default_attitude' => $source_default,
              'source_default_score' => $this->attitudeScore($source_default),
              'edge_attitude' => '',
              'edge_score' => NULL,
              'edge_score_source' => 'none',
              'institution_score' => 0,
              'institution_weighted_score' => 0,
              'weights' => $this->defaultDispositionWeights(FALSE),
              'final_attitude' => '',
              'final_score' => 0,
              'equation' => 'self',
              'institution_breakdown' => [],
            ];
            continue;
          }
          $edge_details = [];
          $edge_attitude = '';
          $edge_score = NULL;
          $edge_score_source = 'none';
          $source_default_score = isset($disposition_profiles[$source_ref]['score']) && is_numeric($disposition_profiles[$source_ref]['score'])
            ? $this->clampDispositionScore((int) $disposition_profiles[$source_ref]['score'])
            : $this->attitudeScore($source_default);
          $institution = [
            'score' => 0,
            'weighted_score' => 0,
            'breakdown' => [],
          ];

          if (isset($resolved_identities[$source_ref]) && isset($resolved_identities[$target_ref])) {
            try {
              $edge_details = $this->relationshipAttitudeService->resolveEdgeDispositionDetails($source_ref, $target_ref, $campaign_id);
              $edge_attitude = $this->normalizeAttitude((string) ($edge_details['attitude'] ?? ''));
              $edge_score = isset($edge_details['score']) && is_numeric($edge_details['score'])
                ? $this->clampDispositionScore((int) $edge_details['score'])
                : NULL;
              $edge_score_source = trim((string) ($edge_details['score_source'] ?? 'none'));
              $institution = $this->institutionDispositionScoreAssemblerService
                ->buildActorTargetInstitutionAdjustment($campaign_id, $source_ref, $target_ref);
            }
            catch (\Throwable $e) {
              $stage_errors[] = [
                'stage' => 'edge_calculation',
                'source_ref' => $source_ref,
                'target_ref' => $target_ref,
                'message' => $e->getMessage(),
              ];
              $this->loggerFactory->get('dungeoncrawler_content')->error('Relationships matrix edge calculation failed for campaign @campaign edge @source -> @target: @message', [
                '@campaign' => $campaign_id,
                '@source' => $source_ref,
                '@target' => $target_ref,
                '@message' => $e->getMessage(),
              ]);
            }
          }

          $weighted_institution = (int) ($institution['weighted_score'] ?? 0);
          $resolver_dto = $this->dispositionResolverService->resolveActorTargetDisposition(
            $campaign_id,
            $source_ref,
            $target_ref,
            [
              'source_summary_override' => $source_summary_overrides[$source_ref] ?? NULL,
              'relationship_edge_override' => $edge_details,
              'institution_score' => (int) ($institution['score'] ?? 0),
              'recent_impulse_score' => 0,
              'threat_level' => 'none',
            ]
          );
          $final_score = isset($resolver_dto['effective_disposition_score']) && is_numeric($resolver_dto['effective_disposition_score'])
            ? $this->clampDispositionScore((int) $resolver_dto['effective_disposition_score'])
            : 0;
          $final_attitude = $this->normalizeAttitude((string) ($resolver_dto['effective_disposition_label'] ?? ''));
          if ($final_attitude === '') {
            $final_attitude = $this->scoreToAttitude($final_score);
          }
          $weights = is_array($resolver_dto['weights'] ?? NULL)
            ? $resolver_dto['weights']
            : $this->defaultDispositionWeights($edge_score !== NULL);
          $edge_component = $edge_score !== NULL ? $edge_score : 0;
          $matrix[$source_ref][$target_ref] = $final_attitude;
          $calculations[$source_ref][$target_ref] = [
            'rule' => $edge_score !== NULL ? 'weighted_edge_plus_institutions' : 'weighted_default_plus_institutions',
            'formula' => 'final_score = resolver(source_default_score, edge_score_or_0, institution_score, scene_components...)',
            'weights' => $weights,
            'source_default_attitude' => $source_default,
            'source_default_score' => $source_default_score,
            'edge_attitude' => $edge_attitude,
            'edge_score' => $edge_score,
            'edge_score_source' => $edge_score_source !== '' ? $edge_score_source : 'none',
            'relationship_type' => (string) ($edge_details['relationship_type'] ?? ''),
            'relationship_status' => (string) ($edge_details['status'] ?? ''),
            'institution_score' => (int) ($institution['score'] ?? 0),
            'institution_weighted_score' => $weighted_institution,
            'institution_breakdown' => is_array($institution['breakdown'] ?? NULL) ? $institution['breakdown'] : [],
            'final_attitude' => $final_attitude,
            'final_score' => $final_score,
            'equation' => (string) ($resolver_dto['equation'] ?? sprintf(
              'clamp((%.2f*%d) + (%.2f*%d) + (%d), %d, %d) = %d',
              (float) ($weights['baseline'] ?? $weights['default'] ?? 0),
              $source_default_score,
              (float) ($weights['relationship'] ?? $weights['edge'] ?? 0),
              $edge_component,
              $weighted_institution,
              DispositionAuthorityContract::SCORE_MIN,
              DispositionAuthorityContract::SCORE_MAX,
              $final_score
            )),
            'resolver_snapshot' => $resolver_dto,
          ];
        }
      }

      return [
        'success' => TRUE,
        'degraded' => $stage_errors !== [],
        'campaign_id' => $campaign_id,
        'selected_actor_ref' => $selected_actor_ref !== '' && in_array($selected_actor_ref, $actor_refs, TRUE)
          ? $selected_actor_ref
          : '',
        'actor_refs' => $actor_refs,
        'matrix' => $matrix,
        'calculations' => $calculations,
        'disposition_profiles' => $disposition_profiles,
        'stage_errors' => $stage_errors,
      ];
    }
    catch (\Throwable $e) {
      $this->loggerFactory->get('dungeoncrawler_content')->error('Relationships matrix fatal fallback for campaign @campaign: @message', [
        '@campaign' => $campaign_id,
        '@message' => $e->getMessage(),
      ]);
      return [
        'success' => TRUE,
        'degraded' => TRUE,
        'campaign_id' => $campaign_id,
        'selected_actor_ref' => $selected_actor_ref !== '' && in_array($selected_actor_ref, $actor_refs, TRUE)
          ? $selected_actor_ref
          : '',
        'actor_refs' => $actor_refs,
        'matrix' => new \stdClass(),
        'calculations' => new \stdClass(),
        'disposition_profiles' => new \stdClass(),
        'stage_errors' => [[
          'stage' => 'fatal_fallback',
          'message' => $e->getMessage(),
        ]],
      ];
    }
  }

  /**
   * Normalize attitude labels for relationship matrix display.
   */
  protected function normalizeAttitude(string $attitude): string {
    return DispositionAuthorityContract::normalizeAttitudeLabel($attitude);
  }

  /**
   * Resolve numeric score used in disposition calculations.
   */
  protected function attitudeScore(string $attitude): int {
    $score = DispositionAuthorityContract::attitudeToScore($attitude);
    return $score ?? 0;
  }

  /**
   * Clamp score to canonical disposition range.
   */
  protected function clampDispositionScore(int $score): int {
    return DispositionAuthorityContract::clampScore($score);
  }

  /**
   * Resolve canonical attitude bucket from a computed score.
   */
  protected function scoreToAttitude(int $score): string {
    return DispositionAuthorityContract::scoreToAttitude($score);
  }

  /**
   * Weights for the full disposition formula.
   *
   * @return array<string,float>
   *   Formula weights keyed by source.
   */
  protected function defaultDispositionWeights(bool $has_edge_score): array {
    return [
      'default' => $has_edge_score ? 0.35 : 0.80,
      'edge' => $has_edge_score ? 0.45 : 0.00,
      'institution' => 0.20,
    ];
  }

}
