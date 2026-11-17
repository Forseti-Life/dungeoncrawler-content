<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\StorylineGmWorkService;

/**
 * Plans and advances bounded authoring work through the native draft authority.
 */
final class StorylineWorkTool implements EditorGmToolInterface {

  public function __construct(
    private readonly StorylineGmWorkService $work,
    private readonly bool $apply,
  ) {}

  public function definition(): EditorGmToolDefinition {
    $arguments = [
      EditorGmToolDefinition::argument('expected_revision', 'integer', TRUE, 'Current saved draft revision.'),
      EditorGmToolDefinition::argument('steps', 'array', !$this->apply, 'Ordered units {label,instruction,scene_id?} or {label,instruction,scene_ids:[grounded scene IDs]}. scene_ids must use exact saved scene_id values from journal_entries; never invent IDs. scene_ids is expanded by the authority into one bounded unit per scene. A scene-scoped unit may change only that scene and its tasks/choices. Create shared destination scenes/tasks FIRST in separate unscoped units. Never put all/every scene in an unscoped instruction. Separate destination creation, scene-local links, and final closure into distinct phases. Maximum ten compact operations per unit; instructions max 500 characters; labels max 80; total plan max 6000 JSON bytes. No operation payloads in the plan.'),
    ];
    if ($this->apply) {
      $arguments[] = EditorGmToolDefinition::argument('job_id', 'string', TRUE, 'Grounded saved work ID from the plan or active_work; never invent it.');
      $arguments[] = EditorGmToolDefinition::argument('step', 'integer', TRUE, 'Grounded continuation cursor, starting at zero.');
      $arguments[] = EditorGmToolDefinition::argument('action', 'string', FALSE, 'advance (default), cancel only when the author explicitly asks to stop saved work, or skip only when the author explicitly asks to skip the paused unit. Cancellation retains all completed edits.');
    }
    return new EditorGmToolDefinition(
      $this->apply ? 'apply_storyline_work' : 'plan_storyline_work',
      $this->apply ? EditorGmToolDefinition::FAMILY_EXECUTION : EditorGmToolDefinition::FAMILY_PLANNING,
      $this->apply
        ? 'Advance one saved authoring work unit; retries reuse its checkpoint and native operation receipt. The client continues remaining units automatically.'
        : 'Plan draft edits as short work units BEFORE writing operation JSON. Use for multi-scene, branching, ending, or other substantial storyline rewrites so no response must contain the entire patch. The harness executes the plan in bounded, resumable calls.',
      $this->apply,
      'StorylineGmWorkService with StorylineAuthoringDraftService::applyDraftOperations()',
      $arguments,
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = StorylineBuilderGmToolContext::of($context);
    $revision = EditorGmToolContext::requireInt($arguments, 'expected_revision');
    if (!$this->apply) {
      return $this->work->plan($context->draftId, $revision, EditorGmToolContext::requireArray($arguments, 'steps'));
    }
    $action = $arguments['action'] ?? 'advance';
    if (!in_array($action, ['advance', 'cancel', 'skip'], TRUE)) {
      throw new \InvalidArgumentException('storyline_work_action_invalid');
    }
    $job_id = EditorGmToolContext::requireString($arguments, 'job_id');
    $step = EditorGmToolContext::requireInt($arguments, 'step');
    $result = match ($action) {
      'cancel' => $this->work->cancel($context->draftId, $job_id, $revision, $step),
      'skip' => $this->work->skip($context->draftId, $job_id, $revision, $step),
      default => $this->work->advance($context->draftId, $job_id, $revision, $step, isset($arguments['steps']) ? EditorGmToolContext::requireArray($arguments, 'steps') : []),
    };
    $context->invalidate();
    return $result;
  }

}
