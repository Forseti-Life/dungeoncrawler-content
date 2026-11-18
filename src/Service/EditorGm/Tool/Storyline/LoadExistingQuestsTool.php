<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline;

use Drupal\dungeoncrawler_content\Service\CanonicalQuestTemplateService;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext;

/**
 * Context tool: the published quests this draft's tasks share an ID with.
 */
final class LoadExistingQuestsTool implements EditorGmToolInterface {

  public function __construct(
    private readonly CanonicalQuestTemplateService $quests,
  ) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'load_existing_quests',
      EditorGmToolDefinition::FAMILY_CONTEXT,
      "Load the already-published quest that shares an ID with a task of this draft: its name, description, quest giver, starting place, the storyline it belongs to and its objectives. Use it when the author asks about goals, tasks or objectives established earlier, and before writing objectives for a task that came from an existing quest. Text in {braces} in a published quest is an unfilled placeholder, not a value to copy.",
      FALSE,
      'CanonicalQuestTemplateService::describeQuests()',
      [
        EditorGmToolDefinition::argument('task_id', 'string', FALSE, 'One task ID of this draft. Omit for every task that shares an ID with a published quest.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    if (array_key_exists('task_id', $arguments) && !is_string($arguments['task_id'])) {
      throw new \InvalidArgumentException('argument_invalid:task_id');
    }
    $payload = StorylineBuilderGmToolContext::of($context)->draft()['payload'];
    $flow = is_array($payload['process_flow'] ?? NULL) ? $payload['process_flow'] : [];
    $task_ids = array_values(array_filter(array_map(
      static fn(mixed $task): string => is_array($task) ? trim((string) ($task['task_id'] ?? '')) : '',
      (array) ($flow['tasks'] ?? []),
    )));
    $wanted = trim($arguments['task_id'] ?? '');
    if ($wanted !== '' && !in_array($wanted, $task_ids, TRUE)) {
      throw new \DomainException(sprintf("storyline_task_unknown: '%s' is not a task of this draft.", $wanted));
    }
    $rows_per_task = array_count_values(array_filter(array_map(
      static fn(mixed $row): string => is_array($row) ? trim((string) ($row['task_id'] ?? '')) : '',
      (array) ($flow['objective_completion_criteria'] ?? []),
    )));
    $quests = $this->quests->describeQuests($wanted !== '' ? [$wanted] : $task_ids);
    $storyline_id = trim((string) ($payload['storyline_id'] ?? ''));
    foreach ($quests as &$quest) {
      $quest['objective_rows_in_this_draft'] = $rows_per_task[$quest['task_id']] ?? 0;
      $quest['belongs_to_this_storyline'] = $quest['belongs_to_storyline'] === $storyline_id;
    }
    unset($quest);
    return [
      'tasks_checked' => $wanted !== '' ? 1 : count($task_ids),
      'tasks_without_a_published_quest' => array_values(array_diff($wanted !== '' ? [$wanted] : $task_ids, array_column($quests, 'task_id'))),
      'published_quests' => $quests,
      'note' => 'Publishing this draft writes each task\'s quest from the objective rows in this draft. A published quest shown here is replaced by its task when the draft is published, including one that belongs to another storyline.',
    ];
  }

}
