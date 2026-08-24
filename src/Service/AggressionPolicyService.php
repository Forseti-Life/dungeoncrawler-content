<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Canonical policy evaluator for aggression and combat-entry readiness.
 */
class AggressionPolicyService {

  /**
   * Evaluate current escalation state from normalized aggression signals.
   *
   * @param array<string, mixed> $input
   *   Policy input payload.
   *
   * @return array<string, mixed>
   *   Normalized policy result.
   */
  public function evaluateAggressionState(array $input): array {
    $current_state = strtolower(trim((string) ($input['current_state'] ?? 'calm')));
    $attitude = strtolower(trim((string) ($input['actor_attitude'] ?? 'indifferent')));
    $relationship_attitude = strtolower(trim((string) ($input['relationship_attitude'] ?? '')));
    $actor_attitude_source = strtolower(trim((string) ($input['actor_attitude_source'] ?? '')));
    $relationship_attitude_source = strtolower(trim((string) ($input['relationship_attitude_source'] ?? '')));
    $aggression_signal = strtolower(trim((string) ($input['aggression_signal'] ?? 'none')));
    $threat_level = strtolower(trim((string) ($input['threat_level'] ?? 'none')));
    $explicit_attack_declared = !empty($input['explicit_attack_declared']);
    $actor_stance = strtolower(trim((string) ($input['actor_stance'] ?? '')));
    $actor_stance_confidence = isset($input['actor_stance_confidence']) && is_numeric($input['actor_stance_confidence'])
      ? max(0, min(100, (int) round((float) $input['actor_stance_confidence'])))
      : 0;
    $actor_stance_reason = trim((string) ($input['actor_stance_reason'] ?? ''));
    $actor_process_flow = strtolower(trim((string) ($input['actor_process_flow'] ?? '')));
    $actor_process_flow_reason = trim((string) ($input['actor_process_flow_reason'] ?? ''));
    $actor_process_flow_blockers = is_array($input['actor_process_flow_blockers'] ?? NULL)
      ? array_values(array_filter(array_map(
        static fn($value): string => trim((string) $value),
        (array) $input['actor_process_flow_blockers']
      )))
      : [];
    $actor_score = $this->resolveDispositionScore(
      $input['actor_score'] ?? NULL,
      $attitude
    );
    $relationship_score = $this->resolveDispositionScore(
      $input['relationship_score'] ?? NULL,
      $relationship_attitude
    );
    $fear_score = isset($input['fear_score']) && is_numeric($input['fear_score'])
      ? DispositionAuthorityContract::clampScore((int) round((float) $input['fear_score']))
      : 0;
    $aggression_bias_score = isset($input['aggression_bias_score']) && is_numeric($input['aggression_bias_score'])
      ? DispositionAuthorityContract::clampScore((int) round((float) $input['aggression_bias_score']))
      : 0;
    $recent_harm_score = isset($input['recent_harm_score']) && is_numeric($input['recent_harm_score'])
      ? DispositionAuthorityContract::clampScore((int) round((float) $input['recent_harm_score']))
      : 0;
    $recent_help_score = isset($input['recent_help_score']) && is_numeric($input['recent_help_score'])
      ? DispositionAuthorityContract::clampScore((int) round((float) $input['recent_help_score']))
      : 0;
    $valid_targets = is_array($input['valid_target_ids'] ?? NULL) ? array_values(array_filter(array_map(
      static fn($value): string => trim((string) $value),
      (array) $input['valid_target_ids']
    ))) : [];

    $state = $this->resolveBaselineEscalationState($current_state, $threat_level, $aggression_signal);
    $threat_score = $this->threatLevelToScore($threat_level);
    $hostility_pressure = (int) round(
      (0.35 * $actor_score)
      + (0.35 * $relationship_score)
      + (0.15 * $aggression_bias_score)
      + (0.25 * $recent_harm_score)
      - (0.20 * $recent_help_score)
      - (0.10 * $fear_score)
      + (0.20 * $threat_score)
    );
    $hostility_pressure = DispositionAuthorityContract::clampScore($hostility_pressure);

    $entry_authorized = FALSE;
    $reason = 'Combat entry evaluated from numeric hostility pressure.';
    if ($hostility_pressure <= -65) {
      $state = DispositionAuthorityContract::LABEL_HOSTILE;
      $entry_authorized = TRUE;
      $reason = 'Combat entry authorized: hostility pressure is critically negative.';
    }
    elseif ($hostility_pressure <= -40 && ($explicit_attack_declared || in_array($state, ['threatened', 'hostile', 'engaged'], TRUE))) {
      $state = DispositionAuthorityContract::LABEL_HOSTILE;
      $entry_authorized = TRUE;
      $reason = 'Combat entry authorized: strong hostility pressure with declared/active threat.';
    }
    elseif ($explicit_attack_declared && $hostility_pressure <= -20 && $threat_score >= 25) {
      $state = 'threatened';
      $entry_authorized = TRUE;
      $reason = 'Combat entry authorized: explicit attack with elevated threat and negative disposition pressure.';
    }

    $entry_blockers = [];
    if ($entry_authorized && $valid_targets === []) {
      $entry_authorized = FALSE;
      $entry_blockers[] = 'no_valid_targets';
    }

    return [
      'escalation_state' => $state,
      'can_initiate_combat' => $entry_authorized,
      'entry_authorized' => $entry_authorized,
      'entry_blockers' => $entry_blockers,
      'target_ids' => $valid_targets,
      'reason' => $reason,
      'basis' => [
        'current_state' => $current_state,
        'actor_attitude' => $attitude,
        'actor_score' => $actor_score,
        'actor_attitude_source' => $actor_attitude_source,
        'relationship_attitude' => $relationship_attitude,
        'relationship_score' => $relationship_score,
        'relationship_attitude_source' => $relationship_attitude_source,
        'fear_score' => $fear_score,
        'aggression_bias_score' => $aggression_bias_score,
        'recent_harm_score' => $recent_harm_score,
        'recent_help_score' => $recent_help_score,
        'aggression_signal' => $aggression_signal,
        'threat_level' => $threat_level,
        'threat_score' => $threat_score,
        'hostility_pressure' => $hostility_pressure,
        'explicit_attack_declared' => $explicit_attack_declared,
        'actor_stance' => $actor_stance,
        'actor_stance_confidence' => $actor_stance_confidence,
        'actor_stance_reason' => $actor_stance_reason,
        'actor_process_flow' => $actor_process_flow,
        'actor_process_flow_reason' => $actor_process_flow_reason,
        'actor_process_flow_blockers' => $actor_process_flow_blockers,
      ],
    ];
  }

  /**
   * Resolve baseline escalation state before disposition authorization overlay.
   */
  protected function resolveBaselineEscalationState(string $current_state, string $threat_level, string $aggression_signal): string {
    $valid_states = ['calm', 'alert', 'suspicious', 'threatened', 'hostile', 'engaged'];
    if (!in_array($current_state, $valid_states, TRUE)) {
      $current_state = 'calm';
    }

    if (in_array($aggression_signal, ['direct_attack', 'harmful_spell_targeted', 'violent_threat'], TRUE)) {
      return 'threatened';
    }

    return match ($threat_level) {
      'critical', 'major', 'high' => 'threatened',
      'elevated', 'medium' => 'suspicious',
      'minor', 'low' => 'alert',
      default => $current_state,
    };
  }

  /**
   * Resolve one disposition score from numeric input or fallback label.
   */
  protected function resolveDispositionScore(mixed $score, string $attitude): int {
    if (is_numeric($score)) {
      return DispositionAuthorityContract::clampScore((int) round((float) $score));
    }
    return DispositionAuthorityContract::attitudeToScore($attitude) ?? 0;
  }

  /**
   * Convert threat level to numeric pressure contribution.
   */
  protected function threatLevelToScore(string $threat_level): int {
    return match (strtolower(trim($threat_level))) {
      'critical', 'major', 'high' => 40,
      'elevated', 'medium' => 25,
      'minor', 'low' => 10,
      default => 0,
    };
  }

}
