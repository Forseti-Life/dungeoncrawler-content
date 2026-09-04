<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Validation tool: runs a deterministic validation profile on the draft.
 */
final class ValidateDraftTool implements EditorGmToolInterface {

  private const PROFILES = ['editing', 'preview', 'publication'];

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'validate_draft',
      EditorGmToolDefinition::FAMILY_VALIDATION,
      'Run a deterministic Room Editor validation profile against the active draft.',
      FALSE,
      'RoomEditorService::validateDraft()',
      [
        EditorGmToolDefinition::argument('profile', 'string', FALSE, 'One of editing, preview, publication.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $profile = isset($arguments['profile'])
      ? (string) $arguments['profile']
      : $context->validationProfile;
    if (!in_array($profile, self::PROFILES, TRUE)) {
      throw new \InvalidArgumentException('validation_profile_invalid');
    }
    return $context->validation($profile);
  }

}
