<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon;

use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Publishes a canonical dungeon version through DungeonEditorService.
 */
final class PublishDungeonVersionTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'publish_dungeon_version',
      EditorGmToolDefinition::FAMILY_EXECUTION,
      'Publish the active dungeon draft and project its canonical connectors.',
      TRUE,
      'DungeonEditorService::publish()',
      [
        EditorGmToolDefinition::argument('expected_revision', 'integer', TRUE, 'Draft revision to publish.'),
        EditorGmToolDefinition::argument('version', 'string', TRUE, 'Semantic dungeon version, for example 1.0.0.'),
        EditorGmToolDefinition::argument('publication_note', 'string', FALSE, 'Optional publication note.'),
        EditorGmToolDefinition::argument('expected_base_version_id', 'string|null', FALSE, 'Optional base version concurrency guard.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = DungeonEditorGmToolContext::of($context);
    $expected_revision = EditorGmToolContext::requireInt($arguments, 'expected_revision');
    $version = EditorGmToolContext::requireString($arguments, 'version');
    $request = ['version' => $version];
    if (array_key_exists('publication_note', $arguments) && $arguments['publication_note'] !== NULL) {
      $request['publication_note'] = (string) $arguments['publication_note'];
    }
    if (array_key_exists('expected_base_version_id', $arguments)) {
      $request['expected_base_version_id'] = $arguments['expected_base_version_id'];
    }
    $draft = $context->draft();
    $result = $context->dungeonEditor->publish($context->draftId, $expected_revision, (int) ($draft['updated_by'] ?? $draft['created_by'] ?? 0), $request);
    $context->invalidate();
    return $result;
  }

}
