<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext;

/** Reports whether the current draft can create its canonical storyline. */
final class CheckStorylineCreationReadinessTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'check_storyline_creation_readiness',
      EditorGmToolDefinition::FAMILY_VALIDATION,
      'Complete canonical review of the saved revision and report creation readiness. Read-only: final signoff is recorded by publish_storyline_draft, not a separate review flag.',
      FALSE,
      'StorylineAuthoringDraftService::validateDraft()',
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = StorylineBuilderGmToolContext::of($context);
    $draft = $context->draft();
    $validation = $context->validation();
    $blockers = [];
    foreach ((array) ($validation['errors'] ?? []) as $error) {
      $blockers[] = [
        'code' => 'canonical_validation_error',
        'message' => (string) $error,
      ];
    }
    if ((string) ($draft['status'] ?? '') !== 'editing') {
      $blockers[] = [
        'code' => 'storyline_draft_not_editable',
        'message' => 'Only an editing draft can create a canonical storyline.',
      ];
    }
    return [
      'ready' => $blockers === [] && ($validation['valid'] ?? FALSE) === TRUE,
      'review_completed' => ($validation['valid'] ?? FALSE) === TRUE,
      'publication_receipt' => $draft['publication_receipt'] ?? NULL,
      'draft_id' => $context->draftId,
      'revision' => (int) ($draft['revision'] ?? 0),
      'status' => (string) ($draft['status'] ?? ''),
      'blockers' => $blockers,
      'warnings' => array_values((array) ($validation['warnings'] ?? [])),
    ];
  }

}
