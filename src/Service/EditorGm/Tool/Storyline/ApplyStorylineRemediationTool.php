<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalDefinitionGenerationService;
use Drupal\dungeoncrawler_content\Service\CanonicalQuestTemplateService;

/**
 * Executes a validated Storyline remediation through existing authorities.
 */
final class ApplyStorylineRemediationTool implements EditorGmToolInterface {

  public function __construct(
    private readonly CanonicalDefinitionGenerationService $definitionGeneration,
    private readonly CanonicalQuestTemplateService $questTemplates,
  ) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'apply_storyline_remediation',
      EditorGmToolDefinition::FAMILY_EXECUTION,
      'Create planned missing canonical assets, repair planned linked-quest references, apply Storyline bindings, and return fresh validation.',
      TRUE,
      'CanonicalDefinitionService + StorylineAuthoringDraftService',
      [
        EditorGmToolDefinition::argument('expected_revision', 'integer', TRUE, 'Storyline draft revision the plan targets.'),
        EditorGmToolDefinition::argument('operation_id', 'string', TRUE, 'Unique remediation operation id.'),
        EditorGmToolDefinition::argument('definitions', 'array', FALSE, 'Planned canonical definition generation specifications.'),
        EditorGmToolDefinition::argument('quests', 'array', FALSE, 'Planned linked-quest storyline-reference repairs only. Use publish_storyline_quest_templates to create missing quest records.'),
        EditorGmToolDefinition::argument('operations', 'array', FALSE, 'Planned Storyline draft operations.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = StorylineBuilderGmToolContext::of($context);
    $expected_revision = EditorGmToolContext::requireInt($arguments, 'expected_revision');
    $operation_id = EditorGmToolContext::requireString($arguments, 'operation_id');
    $definitions = $this->listArgument($arguments, 'definitions');
    $quests = $this->listArgument($arguments, 'quests');
    $operations = $this->listArgument($arguments, 'operations');
    if ((int) ($context->draft()['revision'] ?? 0) !== $expected_revision) {
      throw new \DomainException('storyline_draft_revision_conflict');
    }

    $started_at = microtime(TRUE);
    $time_budget_seconds = 45.0;
    $deferred_tasks = [];
    $draft_receipt = NULL;
    $sub_tool_calls = [];
    if ($operations !== []) {
      $sub_tool_calls[] = [
        'tool' => 'apply_storyline_draft_operations',
        'message' => sprintf('Now applying %d storyline draft operation(s)…', count($operations)),
      ];
      $draft_receipt = $context->drafts->applyDraftOperations(
        $context->draftId,
        $expected_revision,
        $operation_id,
        $operations,
      );
      $context->invalidate();
    }

    $definition_receipts = [];
    foreach ($definitions as $spec) {
      $family = (string) $spec['family'];
      $definition_id = (string) $spec['definition_id'];
      // A storyline's actors are created only by the character sheet build,
      // which refuses a second identity for a character already in the
      // library. Generating one here would bypass that.
      if ($family === 'actor') {
        throw new \DomainException("storyline_remediation_actor_not_supported:{$definition_id}: build the roster's character sheets instead.");
      }
      $existing = $context->definitions->catalogEntry($family, $definition_id);
      if ($existing !== NULL) {
        $definition_receipts[] = ['family' => $family, 'definition_id' => $definition_id, 'created' => FALSE];
        continue;
      }
      if ($definition_receipts !== [] && (microtime(TRUE) - $started_at) >= $time_budget_seconds) {
        $deferred_tasks[] = ['kind' => 'definition', 'family' => $family, 'definition_id' => $definition_id];
        continue;
      }
      $sub_tool_calls[] = [
        'tool' => 'generate_canonical_definition',
        'message' => sprintf("Now generating canonical %s definition '%s'…", $family, $definition_id),
      ];
      $generation_arguments = $spec;
      unset($generation_arguments['family']);
      $result = $this->definitionGeneration->generateRuntimeDefinitionAndSave($family, $generation_arguments);
      if ((string) ($result['definition_id'] ?? '') !== $definition_id) {
        throw new \LogicException("storyline_remediation_definition_identity_mismatch:{$definition_id}");
      }
      $definition_receipts[] = ['family' => $family, 'definition_id' => $definition_id, 'created' => TRUE];
    }

    $quest_receipts = [];
    foreach ($quests as $spec) {
      if (!is_array($spec)) {
        continue;
      }
      $template_id = trim((string) ($spec['template_id'] ?? ''));
      if ($template_id === '') {
        continue;
      }
      $repair = ($spec['action'] ?? '') === 'repair_storyline_references' ? $spec : ($spec['storyline_reference_repair'] ?? NULL);
      if (is_array($repair)) {
        $draft = $context->draft();
        $task_ids = array_column($draft['payload']['process_flow']['tasks'] ?? [], 'task_id');
        if (!in_array($template_id, $task_ids, TRUE)
          || ($repair['template_id'] ?? '') !== $template_id
          || ($repair['storyline_template_id'] ?? '') !== $draft['payload']['storyline_id']) {
          throw new \DomainException('storyline_quest_reference_repair_outside_draft');
        }
        $quest_receipts[] = $this->questTemplates->applyStorylineReferenceRepair($repair);
        $sub_tool_calls[] = [
          'tool' => 'repair_linked_quest_references',
          'message' => sprintf("Now aligning linked quest '%s' with its storyline…", $template_id),
        ];
        continue;
      }
      // Quest records are written from the draft at publication.
      throw new \InvalidArgumentException('storyline_remediation_quest_publication_unsupported:' . $template_id);
    }

    $context->invalidate();
    $validation = $context->validation();

    return [
      'definitions' => $definition_receipts,
      'quests' => $quest_receipts,
      'draft' => $draft_receipt,
      'deferred_tasks' => $deferred_tasks,
      'tool_calls' => $sub_tool_calls,
      'validation' => [
        'valid' => (bool) ($validation['valid'] ?? FALSE),
        'error_count' => count((array) ($validation['errors'] ?? [])),
        'warning_count' => count((array) ($validation['warnings'] ?? [])),
        'errors' => array_values((array) ($validation['errors'] ?? [])),
        'warnings' => array_values((array) ($validation['warnings'] ?? [])),
      ],
    ];
  }

  private function listArgument(array $arguments, string $name): array {
    if (!array_key_exists($name, $arguments)) {
      return [];
    }
    $value = EditorGmToolContext::requireArray($arguments, $name);
    if (!array_is_list($value)) {
      throw new \InvalidArgumentException("storyline_remediation_{$name}_must_be_list");
    }
    return $value;
  }

}
