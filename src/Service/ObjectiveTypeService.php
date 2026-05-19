<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Centralizes deterministic objective type and completion behavior.
 */
class ObjectiveTypeService {

  /**
   * Cached objective type registry payload.
   */
  protected ?array $objectiveTypeRegistry = NULL;

  /**
   * Return the canonical objective type options.
   */
  public function getObjectiveTypeOptions(): array {
    $registry = $this->loadObjectiveTypeRegistry();
    return array_values(array_filter(is_array($registry['objective_options'] ?? NULL) ? $registry['objective_options'] : [], 'is_array'));
  }

  /**
   * Return the canonical definition for one objective type.
   */
  public function getObjectiveTypeDefinition(string $type): array {
    $normalized_type = strtolower(trim($type));
    foreach ($this->getObjectiveTypeOptions() as $option) {
      $canonical_type = strtolower(trim((string) ($option['type'] ?? '')));
      if ($canonical_type === '') {
        continue;
      }
      if ($canonical_type === $normalized_type) {
        return $option;
      }
      foreach ((array) ($option['aliases'] ?? []) as $alias) {
        if (strtolower(trim((string) $alias)) === $normalized_type) {
          return $option;
        }
      }
    }

    return [];
  }

  /**
   * Validate a single objective definition against the strict contract.
   *
   * @return array<int, string>
   *   Validation errors.
   */
  public function validateObjectiveDefinition(array $objective, string $path = 'objective'): array {
    $errors = [];
    $type = trim((string) ($objective['type'] ?? ''));
    if ($type === '') {
      $errors[] = "{$path}: missing required field type";
      return $errors;
    }

    $definition = $this->getObjectiveTypeDefinition($type);
    if ($definition === []) {
      $errors[] = "{$path}: unsupported objective type {$type}";
      return $errors;
    }

    foreach ((array) ($definition['object_contract']['required_fields'] ?? []) as $field) {
      $value = $objective[$field] ?? NULL;
      $is_empty_array = is_array($value) && $value === [];
      if ($value === NULL || $value === '' || $is_empty_array) {
        $errors[] = "{$path}: missing required field {$field}";
      }
    }

    if (!array_key_exists('completion_criteria', $objective) || !is_array($objective['completion_criteria'])) {
      $errors[] = "{$path}: missing required field completion_criteria";
    }

    $children = $this->extractNestedObjectiveDefinitions($objective);
    if ($children !== [] && empty($definition['supports_children'])) {
      $errors[] = "{$path}: objective type {$type} does not support children";
    }

    foreach ($children as $index => $child_objective) {
      $errors = array_merge($errors, $this->validateObjectiveDefinition($child_objective, "{$path}.children[{$index}]"));
    }

    return $errors;
  }

  /**
   * Validate a phased objective definition payload.
   *
   * @return array<int, string>
   *   Validation errors.
   */
  public function validateObjectivePhases(array $phases, string $path = 'objectives_schema'): array {
    $errors = [];
    foreach ($phases as $phase_index => $phase) {
      if (!is_array($phase)) {
        $errors[] = "{$path}[{$phase_index}]: phase must be an object";
        continue;
      }
      if (!isset($phase['phase'])) {
        $errors[] = "{$path}[{$phase_index}]: missing required field phase";
      }
      if (!is_array($phase['objectives'] ?? NULL)) {
        $errors[] = "{$path}[{$phase_index}]: missing required field objectives";
        continue;
      }
      foreach ($phase['objectives'] as $objective_index => $objective) {
        if (!is_array($objective)) {
          $errors[] = "{$path}[{$phase_index}].objectives[{$objective_index}]: objective must be an object";
          continue;
        }
        $errors = array_merge($errors, $this->validateObjectiveDefinition($objective, "{$path}[{$phase_index}].objectives[{$objective_index}]"));
      }
    }

    return $errors;
  }

  /**
   * Assert a phased objective payload conforms to the strict contract.
   */
  public function assertObjectivePhases(array $phases, string $path = 'objectives_schema'): void {
    $errors = $this->validateObjectivePhases($phases, $path);
    if ($errors !== []) {
      throw new \InvalidArgumentException(implode('; ', $errors));
    }
  }

  /**
   * Determine the normalized objective type for an objective payload.
   */
  public function determineObjectiveType(array $objective): string {
    $type = trim((string) ($objective['type'] ?? ''));
    if ($type !== '') {
      $definition = $this->getObjectiveTypeDefinition($type);
      if ($definition !== []) {
        return strtolower(trim((string) ($definition['type'] ?? $type)));
      }
      return match (strtolower($type)) {
        'travel' => 'explore',
        default => strtolower($type),
      };
    }

    $children = $this->extractNestedObjectiveDefinitions($objective);
    if ($children !== []) {
      return 'composite';
    }
    if (!empty($objective['item'])) {
      return 'collect';
    }
    if (!empty($objective['destination']) || array_key_exists('npc_id', $objective)) {
      return 'escort';
    }
    if (!empty($objective['location']) || array_key_exists('discovered', $objective)) {
      return 'explore';
    }
    if (isset($objective['target_count'])) {
      return 'investigate';
    }

    return 'interact';
  }

  /**
   * Return nested child-objective definitions from any supported objective shape.
   */
  public function extractNestedObjectiveDefinitions(array $objective): array {
    $children = [];
    if (!is_array($objective['children'] ?? NULL)) {
      return $children;
    }
    foreach ($objective['children'] as $child_objective) {
      if (is_array($child_objective)) {
        $children[] = $child_objective;
      }
    }

    return $children;
  }

  /**
   * Normalize objective completion criteria into a stable contract.
   */
  public function normalizeCompletionCriteria(mixed $criteria, array $objective): array {
    $default = $this->buildDefaultCompletionCriteria($objective);
    if (!is_array($criteria)) {
      return $default;
    }

    $kind = strtolower(trim((string) ($criteria['kind'] ?? $default['kind'] ?? 'flag')));
    if (!in_array($kind, ['count', 'flag', 'all_children'], TRUE)) {
      $kind = (string) ($default['kind'] ?? 'flag');
    }

    $normalized = [
      'kind' => $kind,
      'metric' => trim((string) ($criteria['metric'] ?? $default['metric'] ?? 'completed')) ?: (string) ($default['metric'] ?? 'completed'),
      'description' => trim((string) ($criteria['description'] ?? $default['description'] ?? 'Complete this objective.')) ?: (string) ($default['description'] ?? 'Complete this objective.'),
    ];

    if ($kind === 'count') {
      $normalized['target_count'] = max(1, (int) ($criteria['target_count'] ?? $objective['target_count'] ?? $default['target_count'] ?? 1));
    }
    else {
      $normalized['required_value'] = array_key_exists('required_value', $criteria)
        ? !empty($criteria['required_value'])
        : (bool) ($default['required_value'] ?? TRUE);
    }

    return $normalized;
  }

  /**
   * Build default completion rules for one objective node.
   */
  public function buildDefaultCompletionCriteria(array $objective): array {
    if ($this->extractNestedObjectiveDefinitions($objective) !== []) {
      $composite_definition = $this->getObjectiveTypeDefinition('composite');
      $composite_criteria = is_array($composite_definition['default_completion_criteria'] ?? NULL)
        ? $composite_definition['default_completion_criteria']
        : [];
      if ($composite_criteria !== []) {
        return $composite_criteria;
      }
      return [
        'kind' => 'all_children',
        'metric' => 'children_completed',
        'required_value' => TRUE,
        'description' => 'Complete all nested objectives.',
      ];
    }

    $type = $this->determineObjectiveType($objective);
    $definition = $this->getObjectiveTypeDefinition($type);
    $criteria = is_array($definition['default_completion_criteria'] ?? NULL) ? $definition['default_completion_criteria'] : [];
    if ($criteria === []) {
      $criteria = [
        'kind' => 'flag',
        'metric' => 'completed',
        'required_value' => TRUE,
        'description' => 'Mark this objective complete.',
      ];
    }
    if (($criteria['kind'] ?? '') === 'count') {
      $criteria['target_count'] = max(1, (int) ($objective['target_count'] ?? $criteria['target_count'] ?? 1));
    }
    return $criteria;
  }

  /**
   * Apply progress to a single objective node.
   */
  public function applyProgress(array &$objective, int $progress): void {
    $definition = $this->getObjectiveTypeDefinition($this->determineObjectiveType($objective));
    $status_check = is_array($definition['status_check'] ?? NULL) ? $definition['status_check'] : [];
    $mode = strtolower(trim((string) ($status_check['mode'] ?? 'flag')));

    switch ($mode) {
      case 'count':
        $target_field = trim((string) ($status_check['target_field'] ?? 'target_count')) ?: 'target_count';
        $target_count = max(1, (int) ($objective['target_count'] ?? 1));
        if ($target_field !== 'target_count' && isset($objective[$target_field])) {
          $target_count = max(1, (int) $objective[$target_field]);
        }
        $current_count = (int) ($objective['current'] ?? 0);
        $objective['current'] = min($current_count + $progress, $target_count);
        break;

      case 'all_children':
      case 'flag':
        $metric = trim((string) ($status_check['metric'] ?? 'completed')) ?: 'completed';
        $objective[$metric] = TRUE;
        break;
    }
  }

  /**
   * Refresh the computed completion state for one objective node.
   */
  public function refreshCompletion(array &$objective): bool {
    $children = &$this->getObjectiveChildren($objective);
    foreach ($children as &$child_objective) {
      if (is_array($child_objective)) {
        $this->refreshCompletion($child_objective);
      }
    }

    $criteria = $this->normalizeCompletionCriteria($objective['completion_criteria'] ?? [], $objective);
    $objective['type'] = $this->determineObjectiveType($objective);
    $objective['completion_criteria'] = $criteria;

    $completed = FALSE;
    switch ($criteria['kind']) {
      case 'count':
        $target_count = max(1, (int) ($criteria['target_count'] ?? $objective['target_count'] ?? 1));
        $objective['target_count'] = $target_count;
        $completed = (int) ($objective['current'] ?? 0) >= $target_count;
        break;

      case 'all_children':
        $completed = $children !== [] && $this->areObjectiveCollectionCompleted($children);
        break;

      default:
        $metric = (string) ($criteria['metric'] ?? 'completed');
        $required_value = (bool) ($criteria['required_value'] ?? TRUE);
        $completed = !empty($objective[$metric]) === $required_value;
        break;
    }

    $objective['completed'] = $completed;
    return $completed;
  }

  /**
   * Determine whether every objective in a collection is complete.
   */
  public function areObjectiveCollectionCompleted(array $objectives): bool {
    foreach ($objectives as $objective) {
      if (!is_array($objective)) {
        continue;
      }
      if (empty($objective['completed'])) {
        return FALSE;
      }
    }

    return $objectives !== [];
  }

  /**
   * Return nested objective children by reference.
   */
  protected function &getObjectiveChildren(array &$objective): array {
    if (!isset($objective['children']) || !is_array($objective['children'])) {
      $objective['children'] = [];
    }

    return $objective['children'];
  }

  /**
   * Load the canonical objective type registry from disk.
   */
  protected function loadObjectiveTypeRegistry(): array {
    if ($this->objectiveTypeRegistry !== NULL) {
      return $this->objectiveTypeRegistry;
    }

    $path = dirname(__DIR__, 2) . '/config/objective_type_options.json';
    if (!file_exists($path)) {
      $this->objectiveTypeRegistry = ['schema_version' => 'objective-type-options-v1', 'objective_options' => []];
      return $this->objectiveTypeRegistry;
    }

    $decoded = json_decode((string) file_get_contents($path), TRUE);
    $this->objectiveTypeRegistry = is_array($decoded) ? $decoded : ['schema_version' => 'objective-type-options-v1', 'objective_options' => []];
    return $this->objectiveTypeRegistry;
  }

}
