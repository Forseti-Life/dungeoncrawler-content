<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * The contract for a quest's goal and the approaches that can achieve it.
 *
 * A quest may state a goal ("End the goblin threat") separately from the ways
 * of reaching it. Each way is an approach. Resolving any one approach achieves
 * the goal and completes the quest.
 *
 * Definition, stored at `story_impact.goal` on a quest template and copied to
 * `quest_data.goal` on a campaign quest:
 *
 *   goal_text        string  the aim, shown to players
 *   gm_notes         string  optional; never shown to players
 *   open_resolution  bool    an unplanned way may also achieve the goal
 *   approaches[]
 *     approach_id    string  unique within the goal
 *     label          string  the way, as players read it once they know it
 *     visibility     known | discoverable | secret
 *     reveal_when    discoverable only; one of
 *                      {objective_id}                       that objective completes
 *                      {touchpoint: {objective_type, ref}}  that event happens
 *     objective_ids  string[]  top-level objectives that make up this way;
 *                    empty means only the GM can judge it achieved
 *     outcome_label  string  shown once the goal is achieved this way
 *     rewards        object  optional; replaces the quest reward
 *     branch         {decision_id, choice_id}  optional storyline choice
 *
 * Runtime state lives beside it on the campaign quest:
 *
 *   revealed   string[]  approach ids the party has learned of
 *   resolved   {approach_id, label, outcome_label, evidence, resolved_by,
 *               character_id, at}
 *
 * Violations are \DomainException with a stable code as the message.
 */
final class QuestGoalContract {

  public const VISIBILITIES = ['known', 'discoverable', 'secret'];

  /** The approach id used when the goal is achieved in an unplanned way. */
  public const UNPLANNED = 'other';

  /**
   * Checks a goal definition against the quest's objective phases.
   *
   * @param array|null $phases
   *   The quest's objective phases. NULL when they are not known yet (a
   *   storyline being authored before its quests exist); references to
   *   objectives are then left for the check made when the quest is created.
   *
   * @return string[]
   *   One message per defect; empty when the goal is valid.
   */
  public static function validate(array $goal, ?array $phases): array {
    $errors = [];
    if (trim((string) ($goal['goal_text'] ?? '')) === '') {
      $errors[] = 'goal.goal_text is required.';
    }
    if (isset($goal['gm_notes']) && !is_string($goal['gm_notes'])) {
      $errors[] = 'goal.gm_notes must be a string.';
    }
    if (isset($goal['open_resolution']) && !is_bool($goal['open_resolution'])) {
      $errors[] = 'goal.open_resolution must be true or false.';
    }
    $approaches = $goal['approaches'] ?? NULL;
    if (!is_array($approaches) || !array_is_list($approaches)) {
      return array_merge($errors, ['goal.approaches must be a list.']);
    }

    $objective_ids = NULL;
    if ($phases !== NULL) {
      $objective_ids = [];
      foreach ($phases as $phase) {
        foreach ((array) ($phase['objectives'] ?? []) as $objective) {
          $objective_ids[(string) ($objective['objective_id'] ?? '')] = TRUE;
        }
      }
    }

    $seen_approaches = [];
    $claimed_objectives = [];
    $has_known = FALSE;
    foreach ($approaches as $index => $approach) {
      $path = "goal.approaches[{$index}]";
      if (!is_array($approach)) {
        $errors[] = "{$path} must be an object.";
        continue;
      }
      $approach_id = trim((string) ($approach['approach_id'] ?? ''));
      if ($approach_id === '' || $approach_id === self::UNPLANNED) {
        $errors[] = "{$path}.approach_id is required and may not be \"" . self::UNPLANNED . '".';
      }
      elseif (isset($seen_approaches[$approach_id])) {
        $errors[] = "{$path}.approach_id \"{$approach_id}\" is used twice.";
      }
      $seen_approaches[$approach_id] = TRUE;
      foreach (['label', 'outcome_label'] as $field) {
        if (trim((string) ($approach[$field] ?? '')) === '') {
          $errors[] = "{$path}.{$field} is required.";
        }
      }

      $visibility = $approach['visibility'] ?? NULL;
      if (!in_array($visibility, self::VISIBILITIES, TRUE)) {
        $errors[] = "{$path}.visibility must be one of: " . implode(', ', self::VISIBILITIES) . '.';
      }
      $has_known = $has_known || $visibility === 'known';
      $reveal = $approach['reveal_when'] ?? NULL;
      if ($visibility === 'discoverable') {
        $errors = array_merge($errors, self::validateReveal($reveal, $objective_ids, "{$path}.reveal_when"));
      }
      elseif ($reveal !== NULL) {
        $errors[] = "{$path}.reveal_when is only for a discoverable approach.";
      }

      $ids = $approach['objective_ids'] ?? [];
      if (!is_array($ids) || !array_is_list($ids)) {
        $errors[] = "{$path}.objective_ids must be a list.";
        $ids = [];
      }
      foreach ($ids as $objective_id) {
        if (!is_string($objective_id) || $objective_id === '' || ($objective_ids !== NULL && !isset($objective_ids[$objective_id]))) {
          $errors[] = "{$path}.objective_ids names an objective the quest does not have: " . json_encode($objective_id) . '.';
        }
        elseif (isset($claimed_objectives[$objective_id])) {
          $errors[] = "{$path}.objective_ids: objective \"{$objective_id}\" already belongs to approach \"{$claimed_objectives[$objective_id]}\".";
        }
        else {
          $claimed_objectives[$objective_id] = $approach_id;
        }
      }

      if (isset($approach['rewards']) && !is_array($approach['rewards'])) {
        $errors[] = "{$path}.rewards must be an object.";
      }
      if (isset($approach['branch'])) {
        $branch = $approach['branch'];
        if (!is_array($branch) || trim((string) ($branch['decision_id'] ?? '')) === '' || trim((string) ($branch['choice_id'] ?? '')) === '') {
          $errors[] = "{$path}.branch needs decision_id and choice_id.";
        }
      }
    }

    $open = ($goal['open_resolution'] ?? FALSE) === TRUE;
    if ($approaches === [] && !$open) {
      $errors[] = 'goal needs at least one approach, or open_resolution.';
    }
    if ($approaches !== [] && !$has_known && !$open) {
      $errors[] = 'goal needs a known approach, or open_resolution, so players always have a way forward.';
    }

    return $errors;
  }

  /**
   * @throws \DomainException
   *   `quest_goal_invalid:<messages>`.
   */
  public static function assertValid(array $goal, array $phases): void {
    $errors = self::validate($goal, $phases);
    if ($errors !== []) {
      throw new \DomainException('quest_goal_invalid:' . implode(' ', $errors));
    }
  }

  private static function validateReveal(mixed $reveal, ?array $objective_ids, string $path): array {
    if (!is_array($reveal)) {
      return ["{$path} is required for a discoverable approach."];
    }
    if (isset($reveal['objective_id']) && count($reveal) === 1) {
      return $objective_ids === NULL || isset($objective_ids[(string) $reveal['objective_id']])
        ? []
        : ["{$path}.objective_id names an objective the quest does not have."];
    }
    if (isset($reveal['touchpoint']) && count($reveal) === 1) {
      $touchpoint = $reveal['touchpoint'];
      return is_array($touchpoint)
        && trim((string) ($touchpoint['objective_type'] ?? '')) !== ''
        && trim((string) ($touchpoint['ref'] ?? '')) !== ''
        ? []
        : ["{$path}.touchpoint needs objective_type and ref."];
    }

    return ["{$path} must be {objective_id} or {touchpoint: {objective_type, ref}}."];
  }

  /**
   * The goal a storyline quest node states, built from the node and its
   * choices that are marked as approaches. NULL when the node states none.
   *
   * @throws \DomainException
   *   `quest_goal_approach_without_goal:<quest_id>` when a choice is an
   *   approach but its quest has no goal.
   */
  public static function fromStorylineNode(array $node): ?array {
    $approaches = [];
    foreach ((array) ($node['choices'] ?? []) as $choice) {
      if (!is_array($choice) || !is_array($choice['approach'] ?? NULL)) {
        continue;
      }
      $approach = [
        'approach_id' => (string) $choice['choice_id'],
        'label' => (string) $choice['label'],
        'branch' => ['decision_id' => (string) $choice['decision_id'], 'choice_id' => (string) $choice['choice_id']],
      ] + $choice['approach'];
      $approach['objective_ids'] = array_values((array) ($approach['objective_ids'] ?? []));
      $approaches[] = $approach;
    }
    $goal = is_array($node['goal'] ?? NULL) ? $node['goal'] : NULL;
    if ($goal === NULL) {
      if ($approaches !== []) {
        throw new \DomainException('quest_goal_approach_without_goal:' . (string) ($node['quest_id'] ?? ''));
      }
      return NULL;
    }

    return $goal + ['approaches' => $approaches];
  }

  /**
   * The goal as a campaign quest first holds it: known approaches revealed.
   */
  public static function start(array $goal): array {
    $goal['revealed'] = [];
    foreach ($goal['approaches'] as $approach) {
      if ($approach['visibility'] === 'known') {
        $goal['revealed'][] = $approach['approach_id'];
      }
    }
    $goal['resolved'] = NULL;

    return $goal;
  }

  public static function approach(array $goal, string $approach_id): ?array {
    foreach ($goal['approaches'] as $approach) {
      if ($approach['approach_id'] === $approach_id) {
        return $approach;
      }
    }

    return NULL;
  }

  public static function isRevealed(array $goal, string $approach_id): bool {
    return in_array($approach_id, $goal['revealed'], TRUE);
  }

  /**
   * Objective ids that belong to any approach.
   *
   * @return array<string, string>
   *   Objective id => approach id.
   */
  public static function approachObjectives(array $goal): array {
    $map = [];
    foreach ($goal['approaches'] as $approach) {
      foreach ($approach['objective_ids'] ?? [] as $objective_id) {
        $map[$objective_id] = $approach['approach_id'];
      }
    }

    return $map;
  }

  /**
   * Approaches not yet revealed whose objective-based condition is now met.
   *
   * @param string[] $completed_objective_ids
   *
   * @return string[]
   *   Approach ids to reveal.
   */
  public static function revealedByObjectives(array $goal, array $completed_objective_ids): array {
    $reveal = [];
    foreach ($goal['approaches'] as $approach) {
      $objective_id = $approach['reveal_when']['objective_id'] ?? NULL;
      if ($objective_id !== NULL && !self::isRevealed($goal, $approach['approach_id']) && in_array($objective_id, $completed_objective_ids, TRUE)) {
        $reveal[] = $approach['approach_id'];
      }
    }

    return $reveal;
  }

  /**
   * Approaches not yet revealed whose event-based condition this event meets.
   *
   * @param array $touchpoint
   *   A quest touchpoint: objective_type plus entity/npc/item/room references.
   *
   * @return string[]
   *   Approach ids to reveal.
   */
  public static function revealedByTouchpoint(array $goal, array $touchpoint): array {
    $type = strtolower(trim((string) ($touchpoint['objective_type'] ?? '')));
    $refs = [];
    foreach (['entity_ref', 'npc_ref', 'item_ref', 'room_id', 'location_id', 'target_name'] as $key) {
      $value = strtolower(trim((string) ($touchpoint[$key] ?? '')));
      if ($value !== '') {
        $refs[$value] = TRUE;
      }
    }

    $reveal = [];
    foreach ($goal['approaches'] as $approach) {
      $condition = $approach['reveal_when']['touchpoint'] ?? NULL;
      if ($condition === NULL || self::isRevealed($goal, $approach['approach_id'])) {
        continue;
      }
      if (strtolower($condition['objective_type']) === $type && isset($refs[strtolower($condition['ref'])])) {
        $reveal[] = $approach['approach_id'];
      }
    }

    return $reveal;
  }

  /**
   * The first approach whose own objectives are all complete, if any.
   *
   * @param string[] $completed_objective_ids
   */
  public static function achievedByObjectives(array $goal, array $completed_objective_ids): ?string {
    foreach ($goal['approaches'] as $approach) {
      $ids = $approach['objective_ids'] ?? [];
      if ($ids !== [] && array_diff($ids, $completed_objective_ids) === []) {
        return $approach['approach_id'];
      }
    }

    return NULL;
  }

  /**
   * Whether an objective is part of what this audience may see.
   *
   * Objectives outside every approach are always shown. An approach's
   * objectives are shown to players only once the approach is revealed, and
   * after the goal is achieved only for the approach that achieved it.
   */
  public static function showsObjective(array $goal, string $objective_id, string $audience): bool {
    $approach_id = self::approachObjectives($goal)[$objective_id] ?? NULL;
    if ($approach_id === NULL || $audience === 'gm') {
      return TRUE;
    }
    if ($goal['resolved'] !== NULL) {
      return $goal['resolved']['approach_id'] === $approach_id;
    }

    return self::isRevealed($goal, $approach_id);
  }

  /**
   * The goal as one audience may see it.
   *
   * @param string $audience
   *   `player` or `gm`.
   */
  public static function view(array $goal, string $audience): array {
    $resolved = $goal['resolved'];
    $ways = [];
    $hidden = 0;
    foreach ($goal['approaches'] as $approach) {
      $revealed = self::isRevealed($goal, $approach['approach_id']);
      $achieved = $resolved !== NULL && $resolved['approach_id'] === $approach['approach_id'];
      if ($audience !== 'gm' && !$revealed && !$achieved) {
        $hidden++;
        continue;
      }
      $way = [
        'approach_id' => $approach['approach_id'],
        'label' => $approach['label'],
        'judged' => ($approach['objective_ids'] ?? []) === [],
        'achieved' => $achieved,
      ];
      if ($audience === 'gm') {
        $way += [
          'visibility' => $approach['visibility'],
          'revealed' => $revealed,
          'reveal_when' => $approach['reveal_when'] ?? NULL,
          'objective_ids' => $approach['objective_ids'] ?? [],
          'outcome_label' => $approach['outcome_label'],
          'changes_reward' => isset($approach['rewards']),
        ];
      }
      $ways[] = $way;
    }

    $view = [
      'goal_text' => $goal['goal_text'],
      'ways' => $ways,
      'other_ways' => $resolved === NULL && ($hidden > 0 || ($goal['open_resolution'] ?? FALSE) === TRUE),
      'resolved' => $resolved === NULL ? NULL : [
        'approach_id' => $resolved['approach_id'],
        'label' => $resolved['label'],
        'outcome_label' => $resolved['outcome_label'],
      ],
    ];
    if ($audience === 'gm') {
      $view['gm_notes'] = (string) ($goal['gm_notes'] ?? '');
      $view['open_resolution'] = ($goal['open_resolution'] ?? FALSE) === TRUE;
      if ($resolved !== NULL) {
        $view['resolved'] += [
          'evidence' => $resolved['evidence'],
          'resolved_by' => $resolved['resolved_by'],
        ];
      }
    }

    return $view;
  }

}
