<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Builds the quest record a storyline task is published as.
 *
 * Everything in the record comes from the task's own draft rows. Nothing is
 * invented: a row with no type, target or player action stays without one
 * and fails the canonical objective contract when it is validated.
 */
final class StorylineQuestRecordBuilder {

  /**
   * The draft field that carries each objective type's target, and the
   * canonical fields it fills.
   */
  private const TARGET_FIELDS = [
    'collect' => ['item' => ['item']],
    'kill' => ['target' => ['target']],
    'investigate' => ['target' => ['target']],
    'interact' => ['target' => ['target']],
    'explore' => ['location' => ['location', 'location_id']],
    'escort' => ['destination' => ['destination', 'destination_id']],
  ];

  /**
   * Checks an interact objective can require before it completes.
   *
   * request: a player's Request against the target must be granted.
   */
  public const REQUIRED_CHECKS = ['request'];

  public function __construct(
    private readonly ObjectiveTypeService $objectiveTypes,
  ) {}

  /**
   * Objective rows grouped by the task they belong to, in row order.
   *
   * Rows without a task are not returned; they are reported as unlinked.
   *
   * @return array<string, array<int, array<string, mixed>>>
   */
  public static function rowsByTask(array $objective_rows): array {
    $by_task = [];
    foreach (array_values(array_filter($objective_rows, 'is_array')) as $row) {
      $task_id = trim((string) ($row['task_id'] ?? ''));
      if ($task_id !== '') {
        $by_task[$task_id][] = $row;
      }
    }
    return $by_task;
  }

  /**
   * One canonical objective from one draft objective row.
   */
  public function objective(array $row): array {
    $type = strtolower(trim((string) ($row['type'] ?? '')));
    $objective = [
      'objective_id' => trim((string) ($row['objective_id'] ?? '')),
      'type' => $type,
      'description' => trim((string) ($row['description'] ?? '')),
      'next_step' => trim((string) ($row['next_step'] ?? '')),
      'depends_on' => [],
    ];
    foreach (self::TARGET_FIELDS[$type] ?? [] as $draft_field => $canonical_fields) {
      $value = trim((string) ($row[$draft_field] ?? ''));
      if ($value !== '') {
        foreach ($canonical_fields as $canonical_field) {
          $objective[$canonical_field] = $value;
        }
      }
    }
    $requires_check = trim((string) ($row['requires_check'] ?? ''));
    if ($requires_check !== '') {
      $objective['requires_check'] = $requires_check;
    }
    if ($type === 'explore' && trim((string) ($row['search_narration'] ?? '')) !== '') {
      $objective['search_narration'] = trim($row['search_narration']);
    }
    $target_label = trim((string) ($row['target_label'] ?? ''));
    if ($target_label !== '') {
      $objective['target_label'] = $target_label;
    }
    $target_aliases = array_values(array_unique(array_filter(array_map(
      static fn(mixed $alias): string => trim((string) $alias),
      (array) ($row['target_aliases'] ?? []),
    ), static fn(string $alias): bool => $alias !== '')));
    if ($target_aliases !== []) {
      $objective['target_aliases'] = $target_aliases;
    }
    $wayfinding_hint = trim((string) ($row['wayfinding_hint'] ?? ''));
    if ($wayfinding_hint !== '') {
      $objective['wayfinding_hint'] = $wayfinding_hint;
    }
    $definition = $this->objectiveTypes->getObjectiveTypeDefinition($type);
    if ($definition !== []) {
      $criteria = (array) ($definition['default_completion_criteria'] ?? []);
      $metric = trim((string) ($row['metric'] ?? ''));
      if ($metric !== '') {
        $criteria['metric'] = $metric;
      }
      if (isset($objective['search_narration'])) {
        $criteria['description'] = 'Use Search in the objective room to find the clue.';
      }
      if (($criteria['kind'] ?? '') === 'count') {
        $criteria['target_count'] = $row['required'] ?? NULL;
        $objective['target_count'] = $criteria['target_count'];
      }
      $objective['completion_criteria'] = $criteria;
    }
    return $objective;
  }

  /**
   * The objective phases of one task: its rows are required steps, in order.
   *
   * @return array<int, array<string, mixed>>
   */
  public function objectivePhases(array $task_rows): array {
    return [[
      'phase' => 1,
      'objectives' => array_map(fn(array $row): array => $this->objective($row), array_values($task_rows)),
    ]];
  }

  /**
   * Contract errors of one row that the canonical objective check cannot see.
   *
   * @return array<int, string>
   */
  public function rowErrors(array $row): array {
    $errors = [];
    $objective_id = trim((string) ($row['objective_id'] ?? ''));
    $type = strtolower(trim((string) ($row['type'] ?? '')));
    if (!isset(self::TARGET_FIELDS[$type])) {
      return [$type === ''
        ? "Objective '{$objective_id}' has no type. Set type to one of: " . implode(', ', array_keys(self::TARGET_FIELDS)) . '.'
        : "Objective '{$objective_id}' has type '{$type}', which a storyline objective row cannot express. Allowed: " . implode(', ', array_keys(self::TARGET_FIELDS)) . '.',
      ];
    }
    $definition = $this->objectiveTypes->getObjectiveTypeDefinition($type);
    if ($type !== 'explore' && trim((string) ($row['search_narration'] ?? '')) !== '') {
      $errors[] = "Objective '{$objective_id}' sets search_narration, which only an explore objective uses.";
    }
    if ($definition === []) {
      throw new \LogicException('storyline_objective_type_registry_missing:' . $type);
    }
    $type_kind = (string) ($definition['default_completion_criteria']['kind'] ?? '');
    $kind = strtolower(trim((string) ($row['kind'] ?? '')));
    if ($kind !== '' && $kind !== $type_kind) {
      $errors[] = "Objective '{$objective_id}' has kind '{$kind}', but type '{$type}' completes by '{$type_kind}'. Remove kind or set it to '{$type_kind}'.";
    }
    $required = $row['required'] ?? NULL;
    if ($type_kind === 'count' && (!is_numeric($required) || (int) $required < 1)) {
      $errors[] = "Objective '{$objective_id}' has type '{$type}', which counts progress. Set required to the number needed, 1 or more.";
    }
    if ($type_kind !== 'count' && $required !== NULL && $required !== '' && !in_array($required, [TRUE, 'true', '1', 1], TRUE)) {
      $errors[] = "Objective '{$objective_id}' has type '{$type}', which completes when achieved. Remove required; every objective row is a required step.";
    }
    $requires_check = trim((string) ($row['requires_check'] ?? ''));
    if ($requires_check !== '' && !in_array($requires_check, self::REQUIRED_CHECKS, TRUE)) {
      $errors[] = "Objective '{$objective_id}' has requires_check '{$requires_check}'. Allowed: " . implode(', ', self::REQUIRED_CHECKS) . '.';
    }
    elseif ($requires_check !== '' && $type !== 'interact') {
      $errors[] = "Objective '{$objective_id}' has type '{$type}' but sets requires_check, which only an interact objective uses.";
    }
    elseif ($requires_check !== '' && trim((string) ($row['target'] ?? '')) === '') {
      $errors[] = "Objective '{$objective_id}' requires a successful {$requires_check} but names no target. Set target to the actor who must agree.";
    }
    foreach (array_keys(self::TARGET_FIELDS) as $other_type) {
      foreach (array_keys(self::TARGET_FIELDS[$other_type]) as $draft_field) {
        if (!isset(self::TARGET_FIELDS[$type][$draft_field]) && trim((string) ($row[$draft_field] ?? '')) !== '') {
          $errors[] = "Objective '{$objective_id}' has type '{$type}' but sets '{$draft_field}', which that type does not use. Use '" . implode("', '", array_keys(self::TARGET_FIELDS[$type])) . "'.";
        }
      }
    }
    return array_values(array_unique($errors));
  }

  /**
   * Whether a row names a type a storyline objective row can express.
   */
  public static function hasExpressibleType(array $row): bool {
    return isset(self::TARGET_FIELDS[strtolower(trim((string) ($row['type'] ?? '')))]);
  }

  /**
   * The quest record one task is published as.
   *
   * @param array $task
   *   The draft task row.
   * @param array $task_rows
   *   The task's objective rows, in order. Must not be empty.
   * @param array $payload
   *   The draft payload the task belongs to.
   * @param string $storyline_id
   *   The published storyline's template ID.
   */
  public function questRecord(array $task, array $task_rows, array $payload, string $storyline_id): array {
    $task_id = trim((string) ($task['task_id'] ?? ''));
    if ($task_id === '' || $storyline_id === '') {
      throw new \InvalidArgumentException('storyline_quest_record_identity_required');
    }
    if ($task_rows === []) {
      throw new \DomainException('storyline_quest_record_objective_required:' . $task_id);
    }
    $goal = trim((string) ($task['goal_text'] ?? ''));
    $name = $goal !== '' ? $goal : ucfirst(trim(str_replace(['-', '_'], ' ', $task_id)));
    $description = $goal !== ''
      ? $goal
      : implode(' ', array_filter(array_map(static fn(array $row): string => trim((string) ($row['description'] ?? '')), $task_rows)));
    // Rewards, level and duration are carried over unchanged from the
    // previous quest publication; this builder only changed where the
    // objectives come from.
    $reward_items = [];
    $gold = 0;
    foreach ((array) ($payload['objects']['items']['generation_specs'] ?? []) as $item) {
      $item_id = is_array($item) ? trim((string) ($item['item_id'] ?? '')) : '';
      $is_reward = strtolower(trim((string) ($item['acquisition'] ?? ''))) === 'reward'
        || trim((string) ($item['reward'] ?? '')) !== '';
      if ($item_id === '' || !$is_reward || trim((string) ($item['quest_id'] ?? '')) !== $task_id) {
        continue;
      }
      $reward_items[] = $item_id;
      $reward_text = trim((string) ($item['reward'] ?? '') . ' ' . (string) ($item['purpose'] ?? ''));
      if (preg_match('/\b(\d+)\s*(?:gold|gp)\b/i', $reward_text, $matches)) {
        $gold = max($gold, (int) $matches[1]);
      }
    }
    $chapter_id = trim((string) ($task['chapter_id'] ?? ''));
    $scene_id = trim((string) ($task['scene_id'] ?? ''));
    $entry_actor_id = trim((string) ($payload['entry_actor_id'] ?? ''));
    $entry_location_id = trim((string) ($payload['entry_location_id'] ?? ''));

    return [
      'template_id' => $task_id,
      'version' => '1.0.0',
      'name' => mb_substr($name, 0, 255),
      'description' => $description,
      'quest_type' => 'module_story',
      'level_min' => 1,
      'level_max' => 1,
      'prerequisites' => [],
      'estimated_duration_minutes' => 20,
      'tags' => array_values(array_unique(array_filter(['module', $storyline_id, $chapter_id, $scene_id]))),
      'storyline_template_id' => $storyline_id,
      'entry_point_actor_id' => $entry_actor_id !== '' ? $entry_actor_id : NULL,
      'entry_point_location_id' => $entry_location_id !== '' ? $entry_location_id : NULL,
      'objectives_schema' => $this->objectivePhases($task_rows),
      'rewards_schema' => [
        'xp' => 60,
        'gold' => $gold,
        'items' => array_values(array_unique($reward_items)),
      ],
      'story_impact' => [
        'storyline_id' => $storyline_id,
        'chapter_id' => $chapter_id,
        'scene_id' => $scene_id,
      ],
    ];
  }

}
