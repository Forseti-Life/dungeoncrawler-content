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
      'Summarize blockers preventing canonical storyline creation.',
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
      'draft_id' => $context->draftId,
      'revision' => (int) ($draft['revision'] ?? 0),
      'status' => (string) ($draft['status'] ?? ''),
      'blockers' => $blockers,
      'warnings' => array_values((array) ($validation['warnings'] ?? [])),
    ];
  }

}
