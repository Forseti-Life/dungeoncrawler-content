<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon;

use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmSurface;
use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Runs one deterministic Dungeon Editor validation profile.
 */
final class ValidateDungeonTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'validate_dungeon',
      EditorGmToolDefinition::FAMILY_VALIDATION,
      'Run a deterministic Dungeon Editor validation profile against the active draft.',
      FALSE,
      'DungeonEditorService::validateDraft()',
      [
        EditorGmToolDefinition::argument('profile', 'string', FALSE, 'One of ' . implode(', ', DungeonEditorGmSurface::VALIDATION_PROFILES) . '.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = DungeonEditorGmToolContext::of($context);
    $profile = isset($arguments['profile']) ? (string) $arguments['profile'] : $context->validationProfile;
    if (!in_array($profile, DungeonEditorGmSurface::VALIDATION_PROFILES, TRUE)) {
      throw new \InvalidArgumentException('validation_profile_invalid');
    }
    return $context->validation($profile);
  }

}
