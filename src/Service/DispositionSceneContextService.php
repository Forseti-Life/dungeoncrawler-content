<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Resolves transient scene modifiers for disposition calculation.
 */
class DispositionSceneContextService {

  /**
   * @param array<string,mixed> $context
   *
   * @return array<string,mixed>
   *   Keys: situational_score, institution_score, recent_harm_score,
   *   recent_help_score, coercion_score, recent_impulse_score, factors.
   */
  public function resolveSceneContext(array $context = []): array {
    $threat_level = strtolower(trim((string) ($context['threat_level'] ?? 'none')));
    $threat_score = match ($threat_level) {
      'critical', 'major', 'high' => -20,
      'elevated', 'medium' => -10,
      'minor', 'low' => -4,
      default => 0,
    };

    $institution_score = DispositionAuthorityContract::normalizeScore($context['institution_score'] ?? 0);
    $recent_harm_score = DispositionAuthorityContract::normalizeScore($context['recent_harm_score'] ?? 0);
    $recent_help_score = DispositionAuthorityContract::normalizeScore($context['recent_help_score'] ?? 0);
    $coercion_score = DispositionAuthorityContract::normalizeScore($context['coercion_score'] ?? 0);
    $recent_impulse_score = DispositionAuthorityContract::normalizeScore($context['recent_impulse_score'] ?? 0);

    return [
      'situational_score' => DispositionAuthorityContract::normalizeScore($threat_score),
      'institution_score' => $institution_score,
      'recent_harm_score' => $recent_harm_score,
      'recent_help_score' => $recent_help_score,
      'coercion_score' => $coercion_score,
      'recent_impulse_score' => $recent_impulse_score,
      'factors' => [
        'threat_level' => $threat_level,
      ],
    ];
  }

}
