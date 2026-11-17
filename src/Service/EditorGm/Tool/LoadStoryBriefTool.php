<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService;

/**
 * Context tool: what the storylines using this dungeon or room need from it.
 */
final class LoadStoryBriefTool implements EditorGmToolInterface {

  /**
   * @param string $kind
   *   dungeon or room.
   * @param \Closure $subjectId
   *   Returns the ID of the dungeon or room the active draft edits.
   */
  public function __construct(
    private readonly StorylineAuthoringDraftService $storylines,
    private readonly string $kind,
    private readonly \Closure $subjectId,
  ) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'load_story_brief',
      EditorGmToolDefinition::FAMILY_CONTEXT,
      sprintf(
        'Load what each storyline that uses this %s needs from it: its purpose in the story, the scenes and tasks that happen there, the objectives that name it and the cast that starts there. Read it before generating, laying out or stocking the %s.',
        $this->kind,
        $this->kind,
      ),
      FALSE,
      'StorylineAuthoringDraftService::storyBriefs()',
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $id = ($this->subjectId)($context);
    if (!is_string($id) || trim($id) === '') {
      throw new \DomainException('story_brief_requires_a_saved_' . $this->kind);
    }
    return $this->storylines->storyBriefs($this->kind, $id);
  }

}
