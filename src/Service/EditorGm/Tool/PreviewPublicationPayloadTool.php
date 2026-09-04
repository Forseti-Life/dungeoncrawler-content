<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Execution tool: previews what publication would write, without publishing.
 */
final class PreviewPublicationPayloadTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'preview_publication_payload',
      EditorGmToolDefinition::FAMILY_EXECUTION,
      'Show the canonical room payload and publication validation without publishing.',
      FALSE,
      'draft payload + publication validation',
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $draft = $context->draft();
    $validation = $context->validation('publication');

    return [
      'room_id' => $context->roomId(),
      'revision' => (int) ($draft['revision'] ?? 0),
      'expected_base_version_id' => $draft['base_version_id'] ?? NULL,
      'payload_hash' => (string) ($draft['payload_hash'] ?? ''),
      'would_publish' => (bool) ($validation['valid'] ?? FALSE),
      'room' => $context->room(),
      'validation' => $validation,
    ];
  }

}
