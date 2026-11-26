<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\RoomEditorGmToolContext;

/**
 * Execution tool: publishes an immutable canonical room version.
 *
 * Publication remains fully contract-gated inside RoomEditorService::publish();
 * this tool only forwards an approved request.
 */
final class PublishRoomVersionTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'publish_room_version',
      EditorGmToolDefinition::FAMILY_EXECUTION,
      'Publish the active draft as an immutable canonical room version.',
      TRUE,
      'RoomEditorService::publish()',
      [
        EditorGmToolDefinition::argument('version', 'string', TRUE, 'Semantic version, e.g. 1.2.0.'),
        EditorGmToolDefinition::argument('expected_revision', 'integer', TRUE, 'Draft revision being published.'),
        EditorGmToolDefinition::argument('publication_note', 'string', FALSE, 'Publication note.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = RoomEditorGmToolContext::of($context);
    $draft = $context->draft();
    $version = EditorGmToolContext::requireString($arguments, 'version');
    $current_version = trim((string) $context->roomEditor->publishedVersion(
      (string) ($draft['room_id'] ?? $context->roomId()),
    ));
    if ($current_version !== '' && version_compare($version, $current_version, '<=')) {
      $parts = explode('.', $current_version);
      if (count($parts) !== 3 || array_filter($parts, static fn(string $part): bool => !ctype_digit($part)) !== []) {
        throw new \DomainException('published_room_version_invalid');
      }
      $version = sprintf('%d.%d.%d', (int) $parts[0], (int) $parts[1], (int) $parts[2] + 1);
    }
    $request = [
      'version' => $version,
      'expected_revision' => EditorGmToolContext::requireInt($arguments, 'expected_revision'),
      'expected_base_version_id' => $draft['base_version_id'] ?? NULL,
      'publication_note' => isset($arguments['publication_note']) ? (string) $arguments['publication_note'] : '',
    ];

    $result = $context->roomEditor->publish($context->draftId, $request);
    $context->invalidate();

    return [
      'publication' => $result,
      'idempotent_replay' => (bool) ($result['idempotent_replay'] ?? FALSE),
      'changed' => empty($result['idempotent_replay']),
      'requested_version' => EditorGmToolContext::requireString($arguments, 'version'),
      'published_version' => $version,
    ];
  }

}
