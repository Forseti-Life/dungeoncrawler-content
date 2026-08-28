<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Reusable deterministic subflows for actor turn decisions.
 */
class ActorProcessFlowIntentHelper {

  /**
   * Attach explicit flow metadata to one decision payload.
   *
   * @param array<string,mixed> $decision
   * @param array<string,mixed> $meta
   *
   * @return array<string,mixed>
   *   Decorated decision.
   */
  public function attachDecisionMeta(array $decision, array $meta): array {
    $existing = is_array($decision['decision_meta'] ?? NULL) ? $decision['decision_meta'] : [];
    $decision['decision_meta'] = array_filter(
      $meta + $existing,
      static fn($value): bool => $value !== NULL && $value !== ''
    );
    return $decision;
  }

  /**
   * Determine whether the snapshot currently belongs to this actor's turn.
   *
   * @param array<string,mixed> $profile
   * @param array<string,mixed> $snapshot
   */
  public function isActorsTurn(array $profile, array $snapshot): bool {
    $actor_id = $this->resolveActorId($profile, $snapshot);
    $turn_actor = trim((string) ($snapshot['game_state']['turn']['entity'] ?? ''));
    return $actor_id !== '' && $turn_actor !== '' && $actor_id === $turn_actor;
  }

  /**
   * Resolve the canonical actor id for one decision context.
   *
   * @param array<string,mixed> $profile
   * @param array<string,mixed> $snapshot
   */
  public function resolveActorId(array $profile, array $snapshot): string {
    $actor_id = trim((string) ($profile['actor_id'] ?? $snapshot['actor_id'] ?? ''));
    return $actor_id;
  }

  /**
   * Return available action ids normalized to unique strings.
   *
   * @param array<string,mixed> $snapshot
   *
   * @return array<int,string>
   *   Unique action ids.
   */
  public function resolveAvailableActions(array $snapshot): array {
    return array_values(array_unique(array_filter(array_map(
      static fn($value): string => trim((string) $value),
      (array) ($snapshot['available_actions'] ?? [])
    ))));
  }

  /**
   * Deterministic subflow: open the encounter with a configured battle cry.
   *
   * @param array<string,mixed> $profile
   * @param array<string,mixed> $snapshot
   * @param array<string,mixed> $run_state
   *
   * @return array<string,mixed>|null
   *   Talk intent or NULL.
   */
  public function chooseBattleCryAction(array $profile, array $snapshot, array $run_state, string $stage = 'encounter_battle_cry', int $priority = 20): ?array {
    $available_actions = $this->resolveAvailableActions($snapshot);
    $encounter_id = trim((string) ($snapshot['game_state']['encounter_id'] ?? ''));
    $battle_cries = is_array($run_state['memory']['encounter_battle_cries'] ?? NULL)
      ? $run_state['memory']['encounter_battle_cries']
      : [];
    $combat_loadout = is_array($profile['combat_loadout'] ?? NULL) ? $profile['combat_loadout'] : [];
    $battle_cry = trim((string) ($combat_loadout['battle_cry'] ?? ''));
    if (!in_array('talk', $available_actions, TRUE) || $encounter_id === '' || isset($battle_cries[$encounter_id]) || $battle_cry === '') {
      return NULL;
    }

    $actor_id = $this->resolveActorId($profile, $snapshot);
    if ($actor_id === '') {
      return NULL;
    }

    return $this->attachDecisionMeta([
      'type' => 'intent',
      'reason' => 'Open the encounter in character before committing other actions.',
      'intent' => [
        'type' => 'talk',
        'actor' => $actor_id,
        'params' => [
          'message' => $battle_cry,
        ],
      ],
    ], [
      'stage' => $stage,
      'priority' => $priority,
    ]);
  }

  /**
   * Deterministic subflow: strike the first visible hostile with a weapon.
   *
   * @param array<string,mixed> $profile
   * @param array<string,mixed> $snapshot
   *
   * @return array<string,mixed>|null
   *   Strike intent or NULL.
   */
  public function chooseWeaponStrikeAction(array $profile, array $snapshot, string $stage = 'deterministic_weapon_strike', int $priority = 30): ?array {
    $available_actions = $this->resolveAvailableActions($snapshot);
    $combat_loadout = is_array($profile['combat_loadout'] ?? NULL) ? $profile['combat_loadout'] : [];
    $weapon = is_array($combat_loadout['weapon'] ?? NULL)
      ? $combat_loadout['weapon']
      : [];
    $hostile_target = $this->resolvePrimaryHostileTarget($snapshot);
    $target_id = is_array($hostile_target) ? trim((string) ($hostile_target['entity_id'] ?? '')) : '';
    if (!in_array('strike', $available_actions, TRUE) || $target_id === '') {
      return NULL;
    }

    $actor_id = $this->resolveActorId($profile, $snapshot);
    if ($actor_id === '') {
      return NULL;
    }

    return $this->attachDecisionMeta([
      'type' => 'intent',
      'reason' => 'Attack the nearest active hostile target using the configured combat loadout.',
      'intent' => [
        'type' => 'strike',
        'actor' => $actor_id,
        'target' => $target_id,
        'params' => $weapon !== [] ? ['weapon' => $weapon] : [],
      ],
    ], [
      'stage' => $stage,
      'priority' => $priority,
      'target' => $target_id,
    ]);
  }

  /**
   * Deterministic subflow: move one safe hex closer to the primary hostile.
   *
   * @param array<string,mixed> $profile
   * @param array<string,mixed> $snapshot
   *
   * @return array<string,mixed>|null
   *   Stride intent or NULL.
   */
  public function chooseAdvanceTowardHostileAction(array $profile, array $snapshot, string $stage = 'deterministic_advance_to_hostile', int $priority = 40): ?array {
    $available_actions = $this->resolveAvailableActions($snapshot);
    if (!in_array('stride', $available_actions, TRUE)) {
      return NULL;
    }

    $actor_id = $this->resolveActorId($profile, $snapshot);
    if ($actor_id === '') {
      return NULL;
    }

    $actor_entity = is_array($snapshot['actor_entity'] ?? NULL) ? $snapshot['actor_entity'] : [];
    $actor_hex = $this->resolveEntityHex($actor_entity);
    if ($actor_hex === NULL) {
      return NULL;
    }

    $hostile_target = $this->resolvePrimaryHostileTarget($snapshot);
    $target_id = is_array($hostile_target) ? trim((string) ($hostile_target['entity_id'] ?? '')) : '';
    if ($target_id === '') {
      return NULL;
    }

    $target_entity = $this->findVisibleEntityById($snapshot, $target_id);
    $target_hex = $this->resolveEntityHex($target_entity);
    if ($target_hex === NULL) {
      return NULL;
    }

    if ($this->axialDistance($actor_hex, $target_hex) <= 1) {
      return NULL;
    }

    $occupied = $this->buildOccupiedHexIndex($snapshot, $actor_id);
    $best_hex = NULL;
    $best_distance = $this->axialDistance($actor_hex, $target_hex);
    foreach ($this->buildAdjacentHexes($actor_hex) as $candidate_hex) {
      $candidate_key = $candidate_hex['q'] . ':' . $candidate_hex['r'];
      if (!empty($occupied[$candidate_key])) {
        continue;
      }

      $candidate_distance = $this->axialDistance($candidate_hex, $target_hex);
      if ($candidate_distance >= $best_distance) {
        continue;
      }

      $best_hex = $candidate_hex;
      $best_distance = $candidate_distance;
    }

    if ($best_hex === NULL) {
      return NULL;
    }

    return $this->attachDecisionMeta([
      'type' => 'intent',
      'reason' => 'Advance toward the active hostile target to preserve obvious melee pressure.',
      'intent' => [
        'type' => 'stride',
        'actor' => $actor_id,
        'params' => [
          'target_hex' => $best_hex,
          'distance_ft' => 5,
        ],
      ],
    ], [
      'stage' => $stage,
      'priority' => $priority,
      'target' => $target_id,
    ]);
  }

  /**
   * Deterministic subflow: emit a simple warning line.
   *
   * @param array<string,mixed> $profile
   * @param array<string,mixed> $snapshot
   *
   * @return array<string,mixed>|null
   *   Talk intent or NULL.
   */
  public function chooseWarningTalkAction(array $profile, array $snapshot, string $message, string $stage = 'deterministic_warning_talk', int $priority = 20): ?array {
    $available_actions = $this->resolveAvailableActions($snapshot);
    $actor_id = $this->resolveActorId($profile, $snapshot);
    $message = trim($message);
    if (!in_array('talk', $available_actions, TRUE) || $actor_id === '' || $message === '') {
      return NULL;
    }

    return $this->attachDecisionMeta([
      'type' => 'intent',
      'reason' => 'Issue a deterministic warning instead of escalating into an unnecessary LLM call.',
      'intent' => [
        'type' => 'talk',
        'actor' => $actor_id,
        'params' => [
          'message' => $message,
        ],
      ],
    ], [
      'stage' => $stage,
      'priority' => $priority,
    ]);
  }

  /**
   * Deterministic subflow: close the turn when nothing else is obvious.
   *
   * @param array<string,mixed> $profile
   * @param array<string,mixed> $snapshot
   *
   * @return array<string,mixed>|null
   *   Turn-closure intent or NULL.
   */
  public function chooseTurnClosureAction(array $profile, array $snapshot, string $reason, string $fallback_reason, string $stage = 'deterministic_turn_close', int $priority = 90): ?array {
    $available_actions = $this->resolveAvailableActions($snapshot);
    $actor_id = $this->resolveActorId($profile, $snapshot);
    if ($actor_id === '') {
      return NULL;
    }

    if (in_array('choose_not_to_act', $available_actions, TRUE)) {
      return $this->attachDecisionMeta([
        'type' => 'intent',
        'reason' => $reason,
        'intent' => [
          'type' => 'choose_not_to_act',
          'actor' => $actor_id,
          'params' => [
            'reason' => $fallback_reason,
          ],
        ],
      ], [
        'stage' => $stage,
        'priority' => $priority,
      ]);
    }

    if (in_array('end_turn', $available_actions, TRUE)) {
      return $this->attachDecisionMeta([
        'type' => 'intent',
        'reason' => $reason,
        'intent' => [
          'type' => 'end_turn',
          'actor' => $actor_id,
          'params' => [
            'reason' => $fallback_reason,
          ],
        ],
      ], [
        'stage' => $stage,
        'priority' => $priority,
      ]);
    }

    return NULL;
  }

  /**
   * Resolve the first hostile target in canonical order.
   *
   * @param array<string,mixed> $snapshot
   *
   * @return array<string,mixed>|null
   *   Hostile target payload or NULL.
   */
  protected function resolvePrimaryHostileTarget(array $snapshot): ?array {
    foreach ((array) ($snapshot['hostile_targets'] ?? []) as $target) {
      if (is_array($target)) {
        return $target;
      }
    }
    return NULL;
  }

  /**
   * Resolve one visible entity by runtime instance id.
   *
   * @param array<string,mixed> $snapshot
   *
   * @return array<string,mixed>|null
   *   Visible entity row or NULL.
   */
  protected function findVisibleEntityById(array $snapshot, string $entity_id): ?array {
    foreach ((array) ($snapshot['visible_entities'] ?? []) as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      $candidate_id = trim((string) ($entity['entity_instance_id'] ?? $entity['instance_id'] ?? $entity['id'] ?? ''));
      if ($candidate_id === $entity_id) {
        return $entity;
      }
    }
    return NULL;
  }

  /**
   * Resolve an entity's placed hex when available.
   *
   * @param array<string,mixed>|null $entity
   *
   * @return array{q:int,r:int}|null
   *   Axial hex coordinate or NULL.
   */
  protected function resolveEntityHex(?array $entity): ?array {
    if (!is_array($entity)) {
      return NULL;
    }
    $hex = is_array($entity['placement']['hex'] ?? NULL) ? $entity['placement']['hex'] : NULL;
    if (!is_array($hex) || !is_numeric($hex['q'] ?? NULL) || !is_numeric($hex['r'] ?? NULL)) {
      return NULL;
    }
    return [
      'q' => (int) $hex['q'],
      'r' => (int) $hex['r'],
    ];
  }

  /**
   * Build an occupied-hex lookup for visible placed entities.
   *
   * @param array<string,mixed> $snapshot
   *
   * @return array<string,bool>
   *   Occupancy index keyed by "q:r".
   */
  protected function buildOccupiedHexIndex(array $snapshot, string $exclude_actor_id): array {
    $occupied = [];
    foreach ((array) ($snapshot['visible_entities'] ?? []) as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      $entity_id = trim((string) ($entity['entity_instance_id'] ?? $entity['instance_id'] ?? $entity['id'] ?? ''));
      if ($entity_id === '' || $entity_id === $exclude_actor_id) {
        continue;
      }
      $hex = $this->resolveEntityHex($entity);
      if ($hex === NULL) {
        continue;
      }
      $occupied[$hex['q'] . ':' . $hex['r']] = TRUE;
    }
    return $occupied;
  }

  /**
   * Build all six adjacent axial hexes.
   *
   * @param array{q:int,r:int} $hex
   *
   * @return array<int,array{q:int,r:int}>
   *   Adjacent axial coordinates.
   */
  protected function buildAdjacentHexes(array $hex): array {
    $directions = [
      [1, 0],
      [1, -1],
      [0, -1],
      [-1, 0],
      [-1, 1],
      [0, 1],
    ];
    $neighbors = [];
    foreach ($directions as [$dq, $dr]) {
      $neighbors[] = [
        'q' => (int) $hex['q'] + $dq,
        'r' => (int) $hex['r'] + $dr,
      ];
    }
    return $neighbors;
  }

  /**
   * Calculate axial hex distance.
   *
   * @param array{q:int,r:int} $from
   * @param array{q:int,r:int} $to
   */
  protected function axialDistance(array $from, array $to): int {
    $dq = (int) $to['q'] - (int) $from['q'];
    $dr = (int) $to['r'] - (int) $from['r'];
    return max(abs($dq), abs($dr), abs($dq + $dr));
  }

}
