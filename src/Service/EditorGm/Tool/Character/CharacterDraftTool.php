<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Character;

use Drupal\dungeoncrawler_content\Service\EditorGm\CharacterCreationGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * The character authority's read, plan, and revision-checked apply operations.
 */
final class CharacterDraftTool implements EditorGmToolInterface {

  public function __construct(private readonly string $operation) {
    if (!in_array($operation, [
      'get_character_draft', 'get_character_creation_reference',
      'plan_character_draft_updates', 'apply_character_draft_updates',
    ], TRUE)) {
      throw new \LogicException('Unsupported character draft tool: ' . $operation);
    }
  }

  public function definition(): EditorGmToolDefinition {
    $planning = $this->operation === 'plan_character_draft_updates';
    $applying = $this->operation === 'apply_character_draft_updates';
    $arguments = [];
    if ($this->operation === 'get_character_creation_reference') {
      $arguments[] = EditorGmToolDefinition::argument('updates', 'object', FALSE, 'Read-only candidate ancestry/background/class patch. Returns native conditional choices for that proposed object without saving or approving it.');
    }
    if ($applying) {
      $arguments[] = EditorGmToolDefinition::argument('expected_revision', 'integer', TRUE, 'The revision from the inspected draft or approved plan.');
    }
    if ($planning || $applying) {
      $arguments[] = EditorGmToolDefinition::argument('updates', 'object', TRUE, 'Character field patch. Use only fields and IDs from get_character_creation_reference. Include dependent feat_selections, spells, and boosts. Never set step, completion, ownership, or campaign IDs.');
    }
    return new EditorGmToolDefinition(
      $this->operation,
      $applying ? EditorGmToolDefinition::FAMILY_EXECUTION : ($planning ? EditorGmToolDefinition::FAMILY_PLANNING : EditorGmToolDefinition::FAMILY_CONTEXT),
      match ($this->operation) {
        'get_character_draft' => 'Inspect the canonical character object, current revision, and saved choices.',
        'get_character_creation_reference' => 'Inspect allowed fields and canonical character creation options.',
        'plan_character_draft_updates' => 'Validate a proposed character patch without saving; return an approval-bound plan.',
        'apply_character_draft_updates' => 'Apply an approved, valid patch only if the character revision still matches.',
      },
      $applying,
      'CharacterCreationGmService',
      $arguments,
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = CharacterCreationGmToolContext::of($context);
    $draft = $context->draft();
    if ($this->operation === 'get_character_draft') {
      return $draft;
    }
    if ($this->operation === 'get_character_creation_reference') {
      $reference = [
        ...$context->drafts->getPathfinderCharacterCreationReference($draft['campaign_id']),
        'draft_revision' => $draft['revision'],
        'current_step_choices' => $context->validation()['steps'],
      ];
      if (array_key_exists('updates', $arguments)) {
        $plan = $context->drafts->planUpdates($draft['character_id'], EditorGmToolContext::requireArray($arguments, 'updates'));
        $reference['candidate_step_choices'] = $plan['validation']['steps'];
        $reference['candidate_updates'] = $plan['updates'];
        $reference['candidate_findings'] = [
          'errors' => $plan['validation']['errors'],
          'warnings' => $plan['validation']['warnings'],
        ];
      }
      return $reference;
    }
    $updates = EditorGmToolContext::requireArray($arguments, 'updates');
    if ($this->operation === 'plan_character_draft_updates') {
      return $context->drafts->planUpdates($draft['character_id'], $updates);
    }
    $result = $context->drafts->applyDraftUpdates(
      $draft['character_id'],
      EditorGmToolContext::requireInt($arguments, 'expected_revision'),
      $updates,
    );
    $context->invalidate();
    return $result;
  }

}
