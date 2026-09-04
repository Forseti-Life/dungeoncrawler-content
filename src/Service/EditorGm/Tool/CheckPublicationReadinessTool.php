<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\RoomEditorGmToolContext;

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

    $blockers = [];
    foreach ((array) ($validation['errors'] ?? []) as $finding) {
      $blockers[] = [
        'code' => (string) ($finding['code'] ?? 'unknown'),
        'message' => (string) ($finding['message'] ?? ''),
        'path' => (string) ($finding['path'] ?? ''),
      ];
    }
    if ($room_id === '') {
      $blockers[] = [
        'code' => 'room_id_required',
        'message' => 'The draft has no room_id and cannot be published.',
        'path' => 'room_id',
      ];
    }
    if ((string) ($draft['status'] ?? '') !== 'active') {
      $blockers[] = [
        'code' => 'draft_not_active',
        'message' => 'Only an active draft can be published.',
        'path' => 'status',
      ];
    }

    return [
      'ready' => $blockers === [],
      'room_id' => $room_id,
      'revision' => (int) ($draft['revision'] ?? 0),
      'base_version_id' => $draft['base_version_id'] ?? NULL,
      'blockers' => $blockers,
      'warnings' => array_values((array) ($validation['warnings'] ?? [])),
    ];
  }

}
