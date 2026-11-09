<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Character;

use Drupal\dungeoncrawler_content\Service\EditorGm\CharacterCreationGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * The character authority's read, plan, and latest-state apply operations.
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
      $arguments[] = EditorGmToolDefinition::argument('include_descriptions', 'boolean', FALSE, 'Include the actual native wizard option-card descriptions, facts, and disabled choices. Use for subclass, thesis, feature, or other wizard-option questions.');
      $arguments[] = EditorGmToolDefinition::argument('step', 'integer', FALSE, 'Optionally return only this native wizard step, 1-8.');
    }
    if ($applying) {
      $arguments[] = EditorGmToolDefinition::argument('expected_revision', 'integer', TRUE, 'Last inspected revision for audit. The authoritative GM revalidates and applies the patch to the latest locked character, even if this revision is older.');
    }
    if ($planning || $applying) {
      $arguments[] = EditorGmToolDefinition::argument('updates', 'object', TRUE, 'Character field patch. Use only fields and IDs from get_character_creation_reference. Include dependent feat_selections, spells, and boosts. Never set step, completion, ownership, or campaign IDs.');
    }
    return new EditorGmToolDefinition(
      $this->operation,
      $applying ? EditorGmToolDefinition::FAMILY_EXECUTION : ($planning ? EditorGmToolDefinition::FAMILY_PLANNING : EditorGmToolDefinition::FAMILY_CONTEXT),
      match ($this->operation) {
        'get_character_draft' => 'Inspect the canonical character object, current revision, and saved choices.',
        'get_character_creation_reference' => 'Inspect allowed fields, native wizard choices, and optional full option-card descriptions, including conditional subclass and feature selections.',
        'plan_character_draft_updates' => 'Validate a character patch and return a revision-bound plan. Read-only when called directly; the harness immediately applies valid plans requested in conversation.',
        'apply_character_draft_updates' => 'Save a valid authoritative GM patch immediately against the latest locked character. Requested fields override older edits; other fields are preserved. No approval or conflict resolution is required.',
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
      return [...$draft, 'process_flow' => $context->processFlow()];
    }
    if ($this->operation === 'get_character_creation_reference') {
      $include_descriptions = $arguments['include_descriptions'] ?? FALSE;
      $step = $arguments['step'] ?? NULL;
      if ($step !== NULL && ($step < 1 || $step > 8)) {
        throw new \InvalidArgumentException('Character reference step must be 1-8.');
      }
      $select_steps = static fn(array $steps): array => $step === NULL ? $steps : array_values(array_filter($steps, static fn(array $entry): bool => $entry['number'] === $step));
      $reference = [
        ...$context->drafts->getPathfinderCharacterCreationReference($draft['campaign_id']),
        'draft_revision' => $draft['revision'],
        'process_flow' => $context->processFlow(),
        'current_step_choices' => $select_steps($include_descriptions
          ? $context->drafts->validateData($draft['payload'], $draft['campaign_id'], TRUE)['steps']
          : $context->validation()['steps']),
      ];
      if (array_key_exists('updates', $arguments)) {
        $plan = $context->drafts->planUpdates($draft['character_id'], EditorGmToolContext::requireArray($arguments, 'updates'), $include_descriptions);
        $reference['candidate_step_choices'] = $select_steps($plan['validation']['steps']);
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
