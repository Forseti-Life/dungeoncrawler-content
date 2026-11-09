<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

final class CharacterCreationGmContextAssembler implements EditorGmContextAssemblerInterface {

  public function __construct(
    private readonly EditorGmToolRegistry $registry,
    private readonly EditorGmIntentParser $intentParser,
  ) {}

  public function assemble(EditorGmToolContext $context): array {
    $context = CharacterCreationGmToolContext::of($context);
    $draft = $context->draft();
    $validation = $context->validation();
    return [
      'tool_id' => CharacterCreationGmSurface::ID,
      'draft' => ['draft_id' => $draft['draft_id'], 'revision' => $draft['revision']],
      'character' => $draft['payload'],
      'summary' => $draft['summary'],
      'legacy_conversation' => array_slice($context->drafts->getChatHistory($draft['payload']), -12),
      'current_state' => $validation,
      'validation_summary' => [
        'valid' => $validation['valid'],
        'ready' => $validation['ready'],
        'error_count' => count($validation['errors']),
        'warning_count' => count($validation['warnings']),
        'errors' => $validation['errors'],
        'warnings' => $validation['warnings'],
      ],
      'character_reference' => $context->drafts->getPathfinderCharacterCreationReference($draft['campaign_id']),
      'authority_boundary' => [
        'source_of_truth' => 'dc_campaign_characters.character_data',
        'validation_authority' => 'CharacterCreationStepForm / Drupal Form API',
        'mutations_require_approval' => TRUE,
        'revision_conflicts_are_rejected' => TRUE,
        'finalization' => 'Explicit final wizard submission only; the GM must not finalize.',
        'campaign_runtime_mutation' => 'forbidden',
      ],
      'assistant' => [
        'natural_language_available' => $this->intentParser->isAvailable(),
        'must_return_next_steps' => TRUE,
        'mutation_requests_must_use_plan_apply' => TRUE,
      ],
      'tools' => $this->registry->manifest(),
    ];
  }

}
