<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext;
use Drupal\dungeoncrawler_content\Service\StorylineActorSheetService;

final class StorylineActorSheetTool implements EditorGmToolInterface {

  public function __construct(
    private readonly StorylineActorSheetService $sheets,
    private readonly bool $build,
  ) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      $this->build ? 'build_storyline_actor_sheets' : 'inspect_storyline_actor_sheets',
      $this->build ? EditorGmToolDefinition::FAMILY_EXECUTION : EditorGmToolDefinition::FAMILY_CONTEXT,
      $this->build
        ? 'Build and link complete native character sheets for selected roster actors, or the whole roster when actor_ids is omitted. Derives valid class, ancestry and background from authored roles using the native catalog, then invokes native autofill. Reuses existing library actors; retries never duplicate sheets. No existing character draft is needed. Saves immediately when explicitly requested; never asks the author to choose classes after role-driven generation was authorized. Failures identify the failed actor and already completed actors.'
        : 'Inspect roster character-sheet links, current native classes, ancestry, levels, readiness and sheet URLs, plus the native character-creation catalog. Read-only.',
      $this->build,
      'StorylineActorSheetService::' . ($this->build ? 'build' : 'inspect'),
      $this->build ? [
        EditorGmToolDefinition::argument('expected_revision', 'integer', TRUE, 'Current grounded Storyline draft revision.'),
        EditorGmToolDefinition::argument('actor_ids', 'array', FALSE, 'Explicit roster actor IDs; omitted or [] means the whole roster.'),
        EditorGmToolDefinition::argument('preferences', 'object', FALSE, 'Optional map keyed by selected actor_id with explicit native class/ancestry/background IDs. Omit unspecified choices; the role-driven planner handles them. The native wizard owns sheet level and statistics.'),
      ] : [],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = StorylineBuilderGmToolContext::of($context);
    if (!$this->build) {
      return $this->sheets->inspect($context->draftId);
    }
    $result = $this->sheets->build(
      $context->draftId,
      EditorGmToolContext::requireInt($arguments, 'expected_revision'),
      array_key_exists('actor_ids', $arguments) ? EditorGmToolContext::requireArray($arguments, 'actor_ids') : [],
      array_key_exists('preferences', $arguments) ? EditorGmToolContext::requireArray($arguments, 'preferences') : [],
    );
    $context->invalidate();
    return $result;
  }

}
