<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool;

use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonValidationTroubleshootingGuide;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\RoomEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\RoomValidationTroubleshootingGuide;

/**
 * Read-only surface-specific troubleshooting workflow.
 */
final class DescribeValidationTroubleshootingTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'describe_validation_troubleshooting',
      EditorGmToolDefinition::FAMILY_CONTEXT,
      'Describe the current validation problem as one scoped, surface-specific troubleshooting workflow with its owner, native tool, prerequisite and verification rule.',
      FALSE,
      'DungeonValidationTroubleshootingGuide or RoomValidationTroubleshootingGuide',
      [],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    return [
      'troubleshooting' => match ($context->surfaceId()) {
        'dungeon_editor' => DungeonValidationTroubleshootingGuide::packet(DungeonEditorGmToolContext::of($context)),
        'room_editor' => RoomValidationTroubleshootingGuide::packet(RoomEditorGmToolContext::of($context)),
        default => throw new \LogicException('validation_troubleshooting_surface_unsupported'),
      },
    ];
  }

}
