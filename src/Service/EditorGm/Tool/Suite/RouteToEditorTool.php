<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Suite;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorSuiteGmToolContext;

/**
 * Resolves the editor deep link for one subject so the assistant can route the author.
 */
final class RouteToEditorTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'route_to_editor',
      EditorGmToolDefinition::FAMILY_PLANNING,
      'Resolve the deep link into the editor that owns a room, dungeon, or definition family so the author can be routed there.',
      FALSE,
      'EditorSuiteService::editorUrl()',
      [
        EditorGmToolDefinition::argument('kind', 'string', TRUE, 'room, dungeon, or definition_family.'),
        EditorGmToolDefinition::argument('id', 'string', FALSE, 'Subject id; omit for the editor landing page.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = EditorSuiteGmToolContext::of($context);
    $kind = EditorGmToolContext::requireString($arguments, 'kind');
    $id = $arguments['id'] ?? '';
    if (!is_string($id)) {
      throw new \InvalidArgumentException('argument_invalid:id');
    }
    return ['kind' => $kind, 'id' => $id, 'route' => $context->suite->editorUrl($kind, trim($id))];
  }

}
