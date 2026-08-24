<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Canonical behavioral stance resolver for actor action-lane selection.
 */
class ActorStanceResolverService {

  public function __construct(
    protected readonly ActorDispositionService $actorDispositionService,
    protected readonly DispositionResolverService $dispositionResolverService,
    protected readonly ?StanceStateStoreService $stanceStateStoreService = NULL,
    protected readonly ?StanceEventStoreService $stanceEventStoreService = NULL,
  ) {}

  /**
   * Resolve one actor stance envelope for a runtime mode/context.
   *
   * @param array<string, mixed> $context
   *   Runtime evaluation context.
   *
   * @return array<string, mixed>
   *   Canonical actor stance envelope.
   */
  public function resolveStance(int $campaign_id, string $actor_ref, array $context = []): array {
    $actor_ref = trim($actor_ref);
    $mode = $this->normalizeMode((string) ($context['mode'] ?? 'room'));
    $target_refs = $this->normalizeTargetRefs(
      is_array($context['target_entity_refs'] ?? NULL) ? $context['target_entity_refs'] : [],
      (string) ($context['target_actor_ref'] ?? '')
    );
    $disposition_summary = $this->actorDispositionService->getDispositionSummary($campaign_id, $actor_ref, [], FALSE);
    $actor_attitude = DispositionAuthorityContract::normalizeAttitudeLabel((string) ($disposition_summary['current_attitude'] ?? ''));
    if ($actor_attitude === '') {
      $actor_attitude = DispositionAuthorityContract::LABEL_INDIFFERENT;
    }
    $actor_score = isset($disposition_summary['current_score']) && is_numeric($disposition_summary['current_score'])
      ? DispositionAuthorityContract::normalizeScore($disposition_summary['current_score'])
      : (DispositionAuthorityContract::attitudeToScore($actor_attitude) ?? 0);
    $is_hostile = DispositionAuthorityContract::isHostileScore($actor_score);

    $resolved_disposition = $target_refs === [] || $actor_ref === ''
      ? []
      : $this->dispositionResolverService->resolveDispositionMap($campaign_id, $actor_ref, $target_refs, is_array($context['disposition_context'] ?? NULL) ? $context['disposition_context'] : []);
    $target_summary = $this->pickPrimaryTargetSummary($resolved_disposition);
    $target_actor_ref = $target_summary['target_actor_ref'] ?? NULL;
    $target_score = isset($target_summary['effective_disposition_score']) && is_numeric($target_summary['effective_disposition_score'])
      ? (int) $target_summary['effective_disposition_score']
      : 0;

    $threat_score = isset($context['threat_score']) && is_numeric($context['threat_score'])
      ? (int) round((float) $context['threat_score'])
      : $this->threatLevelToScore((string) ($context['threat_level'] ?? 'none'));
    $explicit_attack_declared = !empty($context['explicit_attack_declared']);
    $direct_addressed = !empty($context['direct_addressed']);
    $scripted_scene_required = !empty($context['scripted_scene_required']) || $mode === 'scripted_scene';
    $hp_ratio = $this->normalizeRatio($context['hp_ratio'] ?? ($context['survival']['hp_ratio'] ?? NULL), 1.0);
    $danger_level = strtolower(trim((string) ($context['danger_level'] ?? ($context['survival']['danger_level'] ?? 'none'))));

    [$stance, $reason] = $this->evaluateStance(
      $mode,
      $is_hostile,
      $explicit_attack_declared,
      $direct_addressed,
      $scripted_scene_required,
      $target_actor_ref !== NULL,
      $target_score,
      $threat_score,
      $hp_ratio,
      $danger_level
    );

    $confidence = $this->resolveConfidence($actor_ref, $target_refs, $explicit_attack_declared, $direct_addressed, $scripted_scene_required);
    $policy_flags = $this->derivePolicyFlags($stance, $mode);

    $envelope = [
      'contract_version' => 'actor_stance_contract_v1',
      'actor_ref' => $actor_ref,
      'campaign_id' => $campaign_id,
      'mode' => $mode,
      'stance' => $stance,
      'confidence' => $confidence,
      'reason' => $reason,
      'target_actor_ref' => $target_actor_ref,
      'policy_flags' => $policy_flags,
      'basis' => [
        'mode' => [
          'mode' => $mode,
          'scripted_scene_required' => $scripted_scene_required,
        ],
        'profile' => [
          'attitude' => $actor_attitude,
          'score' => $actor_score,
          'score_source' => (string) ($disposition_summary['score_source'] ?? 'attitude_projection'),
        ],
        'resolved_disposition' => [
          'target_count' => count($resolved_disposition),
          'primary_target_ref' => $target_actor_ref,
          'primary_target_score' => $target_score,
          'primary_target_label' => (string) ($target_summary['effective_disposition_label'] ?? ''),
        ],
        'aggression' => [
          'explicit_attack_declared' => $explicit_attack_declared,
          'threat_score' => $threat_score,
        ],
        'survival' => [
          'hp_ratio' => $hp_ratio,
          'danger_level' => $danger_level,
        ],
        'narrative' => [
          'direct_addressed' => $direct_addressed,
          'scripted_scene_required' => $scripted_scene_required,
        ],
        'targeting' => [
          'candidate_targets' => array_keys($resolved_disposition),
          'primary_target_ref' => $target_actor_ref,
        ],
      ],
      'resolved_at' => gmdate('c'),
    ];
    $this->persistBehavioralStance($campaign_id, $actor_ref, $envelope);
    return $envelope;
  }

  /**
   * Determine stance and reason.
   */
  protected function evaluateStance(
    string $mode,
    bool $is_hostile,
    bool $explicit_attack_declared,
    bool $direct_addressed,
    bool $scripted_scene_required,
    bool $has_target,
    int $target_score,
    int $threat_score,
    float $hp_ratio,
    string $danger_level
  ): array {
    if ($scripted_scene_required) {
      return ['engage_dialogue', 'Scripted-scene obligation overrides lower-order posture heuristics.'];
    }

    if ($hp_ratio <= 0.20 || in_array($danger_level, ['critical', 'fatal'], TRUE)) {
      return ['flee', 'Survival pressure is critical; actor should break contact.'];
    }
    if ($hp_ratio <= 0.35 || $danger_level === 'high') {
      return ['self_preserve', 'Survival pressure is high; actor should prioritize defensive behavior.'];
    }

    if ($mode === 'encounter') {
      if ($is_hostile && $has_target) {
        if ($target_score <= -250) {
          return ['finish_weakest', 'Encounter hostility and vulnerable target posture favor focused aggression.'];
        }
        return ['aggressive_engage', 'Encounter posture is hostile with valid target pressure.'];
      }
      return ['pass', 'Encounter turn posture has no aggressive commitment signal.'];
    }

    if ($mode === 'combat_entry') {
      if ($explicit_attack_declared && $has_target) {
        return ['aggressive_engage', 'Combat-entry declaration with a valid target signals aggressive engagement.'];
      }
      if ($is_hostile && $has_target && $threat_score >= 25) {
        return ['aggressive_engage', 'Hostility and elevated threat make combat entry behaviorally plausible.'];
      }
      if ($is_hostile && $has_target) {
        return ['threaten', 'Hostility is elevated but does not yet cross aggressive-entry threshold.'];
      }
      if ($direct_addressed) {
        return ['warn', 'Direct-address room pressure indicates guarded response posture.'];
      }
      return ['observe', 'No combat-entry trigger exceeded; continue observing.'];
    }

    if ($direct_addressed && !$is_hostile) {
      return ['engage_dialogue', 'Direct-address and non-hostile baseline favor active dialogue.'];
    }
    if ($is_hostile && $has_target) {
      return ['warn', 'Hostile baseline with target context favors cautionary escalation.'];
    }
    return ['observe', 'No explicit trigger requires active escalation or response.'];
  }

  /**
   * Derive minimum required policy flags from the stance.
   *
   * @return array<string, bool>
   */
  protected function derivePolicyFlags(string $stance, string $mode): array {
    $chat_allowed = in_array($stance, ['engage_dialogue', 'deescalate', 'warn', 'threaten', 'observe'], TRUE);
    $combat_entry_candidate = in_array($stance, ['threaten', 'aggressive_engage', 'finish_weakest'], TRUE);
    $aggressive_action_allowed = in_array($stance, ['aggressive_engage', 'finish_weakest'], TRUE);
    $turn_action_required = $mode === 'encounter' && !in_array($stance, ['flee', 'pass'], TRUE);
    $room_silence_allowed = in_array($stance, ['observe', 'self_preserve', 'flee', 'pass'], TRUE);

    return [
      'chat_allowed' => $chat_allowed,
      'combat_entry_candidate' => $combat_entry_candidate,
      'aggressive_action_allowed' => $aggressive_action_allowed,
      'turn_action_required' => $turn_action_required,
      'room_silence_allowed' => $room_silence_allowed,
    ];
  }

  /**
   * Build confidence score from available decision inputs.
   */
  protected function resolveConfidence(
    string $actor_ref,
    array $target_refs,
    bool $explicit_attack_declared,
    bool $direct_addressed,
    bool $scripted_scene_required
  ): int {
    $confidence = 45;
    if ($actor_ref !== '') {
      $confidence += 20;
    }
    if ($target_refs !== []) {
      $confidence += 15;
    }
    if ($explicit_attack_declared || $direct_addressed) {
      $confidence += 10;
    }
    if ($scripted_scene_required) {
      $confidence += 10;
    }
    return max(0, min(100, $confidence));
  }

  /**
   * Select the primary target summary as the most hostile resolved target.
   *
   * @param array<string, array<string, mixed>> $resolved_disposition
   *   Resolved target disposition map.
   *
   * @return array<string, mixed>
   *   Selected target summary.
   */
  protected function pickPrimaryTargetSummary(array $resolved_disposition): array {
    $selected = [];
    $selected_score = PHP_INT_MAX;
    foreach ($resolved_disposition as $target_ref => $summary) {
      if (!is_array($summary)) {
        continue;
      }
      $score = isset($summary['effective_disposition_score']) && is_numeric($summary['effective_disposition_score'])
        ? (int) $summary['effective_disposition_score']
        : 0;
      if ($score < $selected_score) {
        $selected_score = $score;
        $selected = $summary + ['target_actor_ref' => $target_ref];
      }
    }
    return $selected;
  }

  /**
   * Normalize stance runtime mode.
   */
  protected function normalizeMode(string $mode): string {
    $mode = strtolower(trim($mode));
    return in_array($mode, ['room', 'combat_entry', 'encounter', 'scripted_scene'], TRUE)
      ? $mode
      : 'room';
  }

  /**
   * Normalize target refs for resolver input.
   *
   * @param array<int, mixed> $target_refs
   *   Candidate refs.
   *
   * @return array<int, string>
   *   Normalized refs.
   */
  protected function normalizeTargetRefs(array $target_refs, string $single_target_ref = ''): array {
    if (trim($single_target_ref) !== '') {
      $target_refs[] = $single_target_ref;
    }
    return array_values(array_unique(array_filter(array_map(
      static fn($value): string => trim((string) $value),
      $target_refs
    ))));
  }

  /**
   * Normalize ratio into [0,1] with fallback.
   */
  protected function normalizeRatio(mixed $raw, float $fallback): float {
    if (!is_numeric($raw)) {
      return $fallback;
    }
    $ratio = (float) $raw;
    return max(0.0, min(1.0, $ratio));
  }

  /**
   * Convert threat label to stance threat score.
   */
  protected function threatLevelToScore(string $threat_level): int {
    return match (strtolower(trim($threat_level))) {
      'critical', 'major', 'high' => 40,
      'elevated', 'medium' => 25,
      'minor', 'low' => 10,
      default => 0,
    };
  }

  /**
   * Persist behavioral stance projection and transition event when available.
   *
   * @param array<string, mixed> $envelope
   *   Resolved stance envelope.
   */
  protected function persistBehavioralStance(int $campaign_id, string $actor_ref, array $envelope): void {
    if (
      $campaign_id <= 0
      || $actor_ref === ''
      || !$this->stanceStateStoreService instanceof StanceStateStoreService
      || !$this->stanceEventStoreService instanceof StanceEventStoreService
    ) {
      return;
    }

    $existing = $this->stanceStateStoreService->loadLatestState($campaign_id, $actor_ref);
    $summary = is_array($existing['summary'] ?? NULL) ? $existing['summary'] : [];
    $summary['behavioral_stance'] = [
      'contract_version' => (string) ($envelope['contract_version'] ?? 'actor_stance_contract_v1'),
      'mode' => (string) ($envelope['mode'] ?? 'room'),
      'stance' => (string) ($envelope['stance'] ?? 'observe'),
      'confidence' => isset($envelope['confidence']) && is_numeric($envelope['confidence']) ? (int) $envelope['confidence'] : 0,
      'reason' => (string) ($envelope['reason'] ?? ''),
      'target_actor_ref' => (string) ($envelope['target_actor_ref'] ?? ''),
      'resolved_at' => (string) ($envelope['resolved_at'] ?? gmdate('c')),
    ];
    $summary['updated_at'] = (string) ($envelope['resolved_at'] ?? gmdate('c'));
    $this->stanceStateStoreService->storeLatestState($campaign_id, $actor_ref, $summary, [
      'source_type' => 'actor_stance_resolver',
      'source_id' => 'actor_stance_contract_v1',
    ]);

    $this->stanceEventStoreService->recordStanceEvent($campaign_id, $actor_ref, [
      'event_type' => 'behavioral_stance_resolved',
      'stance_id' => (string) ($envelope['stance'] ?? 'observe'),
      'summary' => [
        'mode' => (string) ($envelope['mode'] ?? 'room'),
        'confidence' => isset($envelope['confidence']) && is_numeric($envelope['confidence']) ? (int) $envelope['confidence'] : 0,
        'target_actor_ref' => (string) ($envelope['target_actor_ref'] ?? ''),
      ],
      'context' => [
        'reason' => (string) ($envelope['reason'] ?? ''),
      ],
    ]);
  }

}
