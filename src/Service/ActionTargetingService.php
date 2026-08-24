<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Shared target normalization + requirement policy for action intents.
 */
class ActionTargetingService {

  /**
   * Resolve a stable target reference from intent-level or param-level payloads.
   */
  public function normalizeTargetRef(?string $intent_target, array $params = []): ?string {
    $target_refs = $this->normalizeTargetRefs($intent_target, $params);
    if ($target_refs !== []) {
      return $target_refs[0];
    }

    return NULL;
  }

  /**
   * Resolve stable target references from legacy single-target and targets[] payloads.
   *
   * @return string[]
   *   Ordered target refs (duplicates preserved for duplicate-allowed actions).
   */
  public function normalizeTargetRefs(?string $intent_target, array $params = []): array {
    $candidates = [
      $intent_target,
      $params['target'] ?? NULL,
      $params['target_id'] ?? NULL,
      $params['targetId'] ?? NULL,
      $params['target_ref'] ?? NULL,
      $params['targetRef'] ?? NULL,
      $params['target_entity_id'] ?? NULL,
      $params['targetEntityId'] ?? NULL,
      $params['entity_id'] ?? NULL,
      $params['entityId'] ?? NULL,
      $params['target_instance_id'] ?? NULL,
      $params['targetInstanceId'] ?? NULL,
      $params['primary_target_ref'] ?? NULL,
      $params['targeting']['primary_target_ref'] ?? NULL,
    ];

    $normalized_refs = [];
    $seen = [];

    foreach ($candidates as $candidate) {
      if (!is_scalar($candidate)) {
        continue;
      }
      $normalized = trim((string) $candidate);
      if ($normalized !== '') {
        if (!isset($seen[$normalized])) {
          $seen[$normalized] = TRUE;
          $normalized_refs[] = $normalized;
        }
      }
    }

    $target_rows = $params['targets'] ?? NULL;
    if (is_array($target_rows)) {
      $row_refs = [];
      foreach ($target_rows as $row) {
        if (is_scalar($row)) {
          $normalized = trim((string) $row);
          if ($normalized !== '') {
            $row_refs[] = $normalized;
          }
          continue;
        }
        if (!is_array($row)) {
          continue;
        }
        $row_candidates = [
          $row['target_ref'] ?? NULL,
          $row['targetRef'] ?? NULL,
          $row['ref'] ?? NULL,
          $row['target_entity_id'] ?? NULL,
          $row['targetEntityId'] ?? NULL,
          $row['target_id'] ?? NULL,
          $row['targetId'] ?? NULL,
          $row['entity_id'] ?? NULL,
          $row['entityId'] ?? NULL,
          $row['target_instance_id'] ?? NULL,
          $row['targetInstanceId'] ?? NULL,
        ];
        foreach ($row_candidates as $target_ref) {
          if (!is_scalar($target_ref)) {
            continue;
          }
          $normalized = trim((string) $target_ref);
          if ($normalized === '') {
            continue;
          }
          $row_refs[] = $normalized;
          break;
        }
      }
      if ($row_refs !== []) {
        \Drupal::logger('dungeoncrawler_targeting')->debug(
          'normalizeTargetRefs using targets[] rows refs=@refs',
          ['@refs' => json_encode($row_refs, JSON_UNESCAPED_SLASHES)]
        );
        return $row_refs;
      }
    }

    if ($normalized_refs !== []) {
      \Drupal::logger('dungeoncrawler_targeting')->debug(
        'normalizeTargetRefs using legacy refs refs=@refs',
        ['@refs' => json_encode($normalized_refs, JSON_UNESCAPED_SLASHES)]
      );
    }
    return $normalized_refs;
  }

  /**
   * Resolve effective targeting mode for an action.
   */
  public function resolveTargetingMode(string $action_type, array $params = []): string {
    $action_type = strtolower(trim($action_type));
    if ($action_type === 'strike') {
      return 'hostile_entity';
    }

    $candidate = $params['targeting_mode']
      ?? $params['targeting']
      ?? $params['targeting_rules']['mode']
      ?? $params['targeting']['mode']
      ?? NULL;

    if (is_scalar($candidate)) {
      $normalized = strtolower(trim((string) $candidate));
      if ($normalized !== '') {
        return $normalized;
      }
    }

    return 'contextual';
  }

  /**
   * Whether this action mode requires an explicit target reference.
   */
  public function requiresTarget(string $action_type, string $targeting_mode): bool {
    $action_type = strtolower(trim($action_type));
    if ($action_type === 'strike') {
      return TRUE;
    }

    $targeting_mode = strtolower(trim($targeting_mode));
    return in_array($targeting_mode, [
      'hostile_entity',
      'entity_or_object',
      'entity_or_room',
      'connected_room',
      'room_hazard',
      'ally',
    ], TRUE);
  }

  /**
   * Validate target selection cardinality/uniqueness constraints.
   *
   * @return array{valid: bool, error: ?string}
   *   Validation result payload.
   */
  public function validateTargetSelectionConstraints(array $target_refs, array $params = []): array {
    $min_targets = is_scalar($params['min_targets'] ?? NULL)
      ? max(1, (int) $params['min_targets'])
      : NULL;
    $max_targets = is_scalar($params['max_targets'] ?? NULL)
      ? max(1, (int) $params['max_targets'])
      : NULL;
    $allow_duplicate_targets = !empty($params['allow_duplicate_targets']);
    $count = count($target_refs);

    if ($min_targets !== NULL && $count < $min_targets) {
      \Drupal::logger('dungeoncrawler_targeting')->warning(
        'target constraint failed: below min min=@min count=@count refs=@refs',
        [
          '@min' => (string) $min_targets,
          '@count' => (string) $count,
          '@refs' => json_encode($target_refs, JSON_UNESCAPED_SLASHES),
        ]
      );
      return [
        'valid' => FALSE,
        'error' => sprintf('Action requires at least %d target(s), but %d were selected.', $min_targets, $count),
      ];
    }
    if ($max_targets !== NULL && $count > $max_targets) {
      \Drupal::logger('dungeoncrawler_targeting')->warning(
        'target constraint failed: above max max=@max count=@count refs=@refs',
        [
          '@max' => (string) $max_targets,
          '@count' => (string) $count,
          '@refs' => json_encode($target_refs, JSON_UNESCAPED_SLASHES),
        ]
      );
      return [
        'valid' => FALSE,
        'error' => sprintf('Action allows at most %d target(s), but %d were selected.', $max_targets, $count),
      ];
    }
    if (!$allow_duplicate_targets) {
      $unique_count = count(array_unique($target_refs));
      if ($unique_count !== $count) {
        \Drupal::logger('dungeoncrawler_targeting')->warning(
          'target constraint failed: duplicate refs not allowed refs=@refs',
          ['@refs' => json_encode($target_refs, JSON_UNESCAPED_SLASHES)]
        );
        return [
          'valid' => FALSE,
          'error' => 'Duplicate targets are not allowed for this action.',
        ];
      }
    }

    return ['valid' => TRUE, 'error' => NULL];
  }

  /**
   * Validate a range constraint between origin/target hexes when range is defined.
   *
   * @param array<string, mixed>|null $origin_hex
   * @param array<string, mixed>|null $target_hex
   *
   * @return array{valid: bool, error: ?string, distance_ft: ?int}
   *   Validation result payload.
   */
  public function validateRangeConstraint(?array $origin_hex, ?array $target_hex, array $params = []): array {
    $range_ft = is_scalar($params['range_ft'] ?? NULL)
      ? (int) $params['range_ft']
      : NULL;
    if ($range_ft === NULL || $range_ft <= 0) {
      return ['valid' => TRUE, 'error' => NULL, 'distance_ft' => NULL];
    }

    $origin_q = is_scalar($origin_hex['q'] ?? NULL) ? (int) $origin_hex['q'] : NULL;
    $origin_r = is_scalar($origin_hex['r'] ?? NULL) ? (int) $origin_hex['r'] : NULL;
    $target_q = is_scalar($target_hex['q'] ?? NULL) ? (int) $target_hex['q'] : NULL;
    $target_r = is_scalar($target_hex['r'] ?? NULL) ? (int) $target_hex['r'] : NULL;
    if ($origin_q === NULL || $origin_r === NULL || $target_q === NULL || $target_r === NULL) {
      return [
        'valid' => FALSE,
        'error' => 'Range validation failed because origin or target position is unavailable.',
        'distance_ft' => NULL,
      ];
    }

    $dq = $target_q - $origin_q;
    $dr = $target_r - $origin_r;
    $distance_hex = max(abs($dq), abs($dr), abs($dq + $dr));
    $hex_cost_ft = is_scalar($params['hex_cost_ft'] ?? NULL)
      ? max(1, (int) $params['hex_cost_ft'])
      : 5;
    $distance_ft = (int) ($distance_hex * $hex_cost_ft);
    if ($distance_ft > $range_ft) {
      return [
        'valid' => FALSE,
        'error' => sprintf('Target is out of range (%d ft > %d ft).', $distance_ft, $range_ft),
        'distance_ft' => $distance_ft,
      ];
    }

    return ['valid' => TRUE, 'error' => NULL, 'distance_ft' => $distance_ft];
  }
}
