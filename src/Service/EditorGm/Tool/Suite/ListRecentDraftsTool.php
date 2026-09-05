<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Suite;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorSuiteGmToolContext;

/**
 * Lists the current author\'s active drafts across room and dungeon editors.
 */
final class ListRecentDraftsTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'list_recent_drafts',
      EditorGmToolDefinition::FAMILY_CONTEXT,
      'List the author\'s most recently touched active drafts across every editor, newest first, each with a deep link.',
      FALSE,
      'EditorSuiteService::recent()',
      [
        EditorGmToolDefinition::argument('kind', 'string', FALSE, 'Restrict to room or dungeon drafts.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = EditorSuiteGmToolContext::of($context);
    $kind = $arguments['kind'] ?? NULL;
    if ($kind !== NULL && !in_array($kind, ['room', 'dungeon'], TRUE)) {
      throw new \InvalidArgumentException('argument_invalid:kind');
    }
    $recent = $context->summary()['recent'];
    if ($kind !== NULL) {
      $recent = array_values(array_filter($recent, static fn(array $r): bool => $r['kind'] === $kind));
    }
    return ['recent' => $recent, 'count' => count($recent)];
  }

}
