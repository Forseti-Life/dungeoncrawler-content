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
      'process_flow' => $context->processFlow(),
      'creation_contract' => [
        'object' => 'The actor and character are the same canonical object; character_data is its current saved state.',
        'review_authority' => 'Use validate_draft and explain_validation_findings for player work. player_review is projected from the same authoritative findings, not a second completeness check.',
        'choice_authority' => 'Obey current_state.steps.choices exactly, including selection_count and unique_items. Ability aliases name the same ability; they cannot fill two boost slots.',
        'candidate_choices' => 'For a new ancestry, heritage, background, class, or subclass, inspect get_character_creation_reference with an updates patch containing those choices before selecting conditional feats, skills, spells, or boosts. Changing heritage can change both boost count and excluded abilities. Reuse matching VERIFIED CHARACTER CHOICES from conversation_context at the current revision; do not loop through the same lookup. Use any blocking_errors to repair the proposal. This is read-only. Do not queue a guessed dependent plan before receiving its results.',
        'invalid_proposals' => 'Prior assistant suggestions are not rules authority. Never repeat an invalid suggestion or invent IDs; choose from native options and plan only valid, distinct selections.',
        'workflow' => 'Follow process_flow in the same order as the native wizard. Guide the player through decision_points for current_step before moving on. Present real native options as a numbered list. After saving, re-inspect the canonical object: conditional decisions and the next step can change. Explicit requests to revisit or edit another step are allowed; never invent answers to unresolved decisions or finalize the character.',
      ],
      'legacy_conversation' => array_slice($context->drafts->getChatHistory($draft['payload']), -12),
      'current_state' => $validation,
      'validation_summary' => [
        'valid' => $validation['valid'],
        'ready' => $validation['ready'],
        'error_count' => count($validation['errors']),
        'warning_count' => count($validation['warnings']),
        'errors' => $validation['errors'],
        'warnings' => $validation['warnings'],
        'player_review' => $validation['player_review'],
      ],
      'character_reference' => $context->drafts->getPathfinderCharacterCreationReference($draft['campaign_id']),
      'authority_boundary' => [
        'source_of_truth' => 'dc_campaign_characters.character_data',
        'validation_authority' => 'CharacterCreationStepForm / Drupal Form API',
        'mutations_require_approval' => FALSE,
        'requested_valid_changes_are_saved_immediately' => TRUE,
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
