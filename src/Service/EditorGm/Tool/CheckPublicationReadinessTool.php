<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\RoomEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\RoomEditorService;

/**
 * Validation tool: reports whether the draft can be published right now.
 */
final class CheckPublicationReadinessTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'check_publication_readiness',
      EditorGmToolDefinition::FAMILY_VALIDATION,
      'Summarize blockers preventing publication of the active draft.',
      FALSE,
      'RoomEditorService::validateDraft() publication profile',
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = RoomEditorGmToolContext::of($context);
    $draft = $context->draft();
    $validation = $context->validation('publication');
    $room_id = $context->roomId();

    $blockers = RoomEditorService::errorFindings($validation);
    if ($room_id === '') {
      $blockers[] = RoomEditorService::finding('room_id_required', '/room_id', 'The draft has no room_id and cannot be published.');
    }
    if ((string) ($draft['status'] ?? '') !== 'active') {
      $blockers[] = RoomEditorService::finding('draft_not_active', '/status', 'Only an active draft can be published.');
    }

    return [
      'ready' => $blockers === [],
      'room_id' => $room_id,
      'revision' => (int) ($draft['revision'] ?? 0),
      'base_version_id' => $draft['base_version_id'] ?? NULL,
      'blockers' => $blockers,
      'warnings' => array_values(array_filter(
        $validation['findings'],
        static fn(array $finding): bool => $finding['severity'] === 'warning',
      )),
    ];
  }

}
