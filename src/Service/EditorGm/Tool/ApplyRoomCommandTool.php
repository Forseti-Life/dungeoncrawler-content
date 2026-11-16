<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Single-command adapter; the batch executor remains the sole execution path.
 */
final class ApplyRoomCommandTool implements EditorGmToolInterface {

  public function __construct(private readonly ApplyRoomCommandsTool $executor) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'apply_room_command',
      EditorGmToolDefinition::FAMILY_EXECUTION,
      'Apply one approved native room command without a separate planning round-trip.',
      TRUE,
      'ApplyRoomCommandsTool -> RoomEditorService::applyCommand()',
      [
        EditorGmToolDefinition::argument('expected_revision', 'integer', TRUE, 'Current draft revision.'),
        EditorGmToolDefinition::argument('type', 'string', TRUE, 'One supported native room command type.'),
        EditorGmToolDefinition::argument('payload', 'object', TRUE, 'Native payload from the grounded command contract.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    return $this->executor->execute([
      'expected_revision' => EditorGmToolContext::requireInt($arguments, 'expected_revision'),
      'commands' => [[
        'type' => EditorGmToolContext::requireString($arguments, 'type'),
        'payload' => EditorGmToolContext::requireArray($arguments, 'payload'),
      ]],
    ], $context);
  }
}
