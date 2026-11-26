<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext;
use Drupal\dungeoncrawler_content\Service\Generation\GenerationVocabulary;
use Drupal\dungeoncrawler_content\Service\CanonicalQuestTemplateService;

/**
 * Plans cross-authority remediation for current Storyline validation findings.
 */
final class PlanStorylineRemediationTool implements EditorGmToolInterface {

  public function __construct(
    private readonly CanonicalQuestTemplateService $questTemplates,
  ) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'plan_storyline_remediation',
      EditorGmToolDefinition::FAMILY_PLANNING,
      'Plan missing canonical assets and their draft bindings, or repair stale linked-quest metadata with scope=quest_storyline_references. This does not update existing canonical item definitions. To edit quest content or objectives, use plan_storyline_draft_operations instead.',
      FALSE,
      'CanonicalDefinitionGenerationService + RoomEditorService + StorylineAuthoringDraftService',
      [
        EditorGmToolDefinition::argument('expected_revision', 'integer', TRUE, 'Current Storyline draft revision.'),
        EditorGmToolDefinition::argument('scope', 'string', FALSE, 'all (default) or quest_storyline_references (metadata-only repairs; no asset generation or draft changes).'),
        EditorGmToolDefinition::argument('operations', 'array', FALSE, 'Optional Storyline draft set/append/replace/remove/move operations for bindings or structural corrections.'),
        EditorGmToolDefinition::argument('definitions', 'array', FALSE, 'Optional explicit canonical definition specifications ({family, definition_id, prompt, ...}) to create in the canonical library.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = StorylineBuilderGmToolContext::of($context);
    $expected_revision = EditorGmToolContext::requireInt($arguments, 'expected_revision');
    $draft = $context->draft();
    if ((int) ($draft['revision'] ?? 0) !== $expected_revision) {
      throw new \DomainException('storyline_draft_revision_conflict');
    }

    $operations = $this->listArgument($arguments, 'operations');
    $explicit_definitions = $this->listArgument($arguments, 'definitions');
    $planned_draft = $draft;
    $draft_changes = [];
    if ($operations !== []) {
      $planned = $context->drafts->planDraftOperations($context->draftId, $expected_revision, $operations);
      $draft_changes = $planned['changes'];
      if (is_array($planned['payload'] ?? NULL)) {
        $planned_draft['payload'] = $planned['payload'];
      }
    }

    $scope = $arguments['scope'] ?? 'all';
    if (!in_array($scope, ['all', 'quest_storyline_references'], TRUE)) {
      throw new \InvalidArgumentException('storyline_remediation_scope_invalid');
    }
    $reference_repairs = $this->planLinkedQuestReferenceRepairs($context, $planned_draft['payload']);
    if ($scope === 'quest_storyline_references') {
      if ($operations !== [] || $explicit_definitions !== []) {
        throw new \InvalidArgumentException('storyline_reference_repair_scope_disallows_other_changes');
      }
      $definitions = [];
    }
    else {
      $definitions = $this->deriveAssetPlans($planned_draft, $context->validation(), $context, $explicit_definitions);
    }
    // Quest creation uses the native storyline quest writer, not remediation.
    $quests = $reference_repairs;
    if ($definitions === [] && $quests === [] && $draft_changes === []) {
      return [
        'has_changes' => FALSE,
        'definition_count' => 0,
        'quest_count' => 0,
        'draft_operation_count' => 0,
      ];
    }

    return [
      'has_changes' => TRUE,
      'definition_count' => count($definitions),
      'quest_count' => count($quests),
      'draft_operation_count' => count($draft_changes),
      'draft_changes' => $draft_changes,
      'proposed_execution' => [
        'tool_name' => 'apply_storyline_remediation',
        'authority' => 'existing canonical definition, quest, and Storyline draft authorities',
        'arguments' => [
          'expected_revision' => $expected_revision,
          'operation_id' => 'storyline-remediation-' . bin2hex(random_bytes(12)),
          'definitions' => $definitions,
          'quests' => $quests,
          'operations' => $operations,
        ],
      ],
    ];
  }

  /**
   * Grounds exact quest-reference repair plans from current validator findings.
   */
  public function planLinkedQuestReferenceRepairs(StorylineBuilderGmToolContext $context, ?array $payload = NULL): array {
    $ids = [];
    $payload ??= $context->draft()['payload'];
    $tasks = array_column($payload['process_flow']['tasks'] ?? [], NULL, 'task_id');
    foreach (array_merge($context->validation()['errors'] ?? [], $context->validation()['warnings'] ?? []) as $finding) {
      if (is_string($finding)
        && preg_match("/^Linked quest '([^']+)' (?:has canonical storyline_template_id|has story_impact\\.storyline_id|is missing canonical storyline_template_id)/", $finding, $match)
        && isset($tasks[$match[1]])) {
        $ids[] = $match[1];
      }
    }
    if ($ids === []) {
      return [];
    }
    return $this->questTemplates->planStorylineReferenceRepairs($ids, (string) $payload['storyline_id']);
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

  /**
   * Derives exact missing-asset plans from validation ids and authored specs.
   */
  private function deriveAssetPlans(
    array $draft,
    array $validation,
    StorylineBuilderGmToolContext $context,
    array $explicit_definitions = [],
  ): array {
    $payload = is_array($draft['payload'] ?? NULL) ? $draft['payload'] : [];
    $story_context = implode(' ', array_filter([
      (string) ($payload['premise'] ?? ''),
      (string) ($payload['player_hook'] ?? ''),
    ]));
    $missing_ids = [];
    foreach (['errors', 'warnings'] as $bucket) {
      foreach ((array) ($validation[$bucket] ?? []) as $finding) {
        if (preg_match('/\(([^)]+)\): .*?(?:not found|missing from canonical)/i', (string) $finding, $matches)) {
          $missing_ids[$matches[1]] = TRUE;
        }
      }
    }

    $definitions = [];
    // Roster actors are created by the character sheet build, never here.
    foreach ((array) ($payload['objects']['items']['generation_specs'] ?? []) as $item) {
      if (!is_array($item)) {
        continue;
      }
      $id = trim((string) ($item['item_id'] ?? ''));
      if ($id === '') {
        continue;
      }
      if (!isset($missing_ids[$id]) && $context->definitions->catalogEntry('item', $id) !== NULL) {
        continue;
      }
      $definitions['item:' . $id] = [
        'family' => 'item',
        'definition_id' => $id,
        'prompt' => mb_substr(implode(' ', array_filter([
          (string) ($item['purpose'] ?? ''),
          (string) ($item['acquisition'] ?? ''),
          (string) ($item['reward'] ?? ''),
          $story_context,
        ])), 0, 2000),
      ];
    }

    foreach ($explicit_definitions as $explicit) {
      if (!is_array($explicit)) {
        continue;
      }
      $family = strtolower(trim((string) ($explicit['family'] ?? 'item')));
      if (!in_array($family, GenerationVocabulary::PLACEABLE_FAMILIES, TRUE)) {
        throw new \InvalidArgumentException('storyline_remediation_definition_family_invalid:' . $family);
      }
      $id = trim((string) ($explicit['definition_id'] ?? ''));
      if ($id === '') {
        throw new \InvalidArgumentException('storyline_remediation_definition_id_required');
      }
      if (!isset($missing_ids[$id]) && $context->definitions->catalogEntry($family, $id) !== NULL) {
        continue;
      }
      $prompt = trim((string) ($explicit['prompt'] ?? ''));
      if ($prompt === '') {
        $prompt = mb_substr(implode(' ', array_filter([
          (string) ($explicit['display_name'] ?? $explicit['name'] ?? $id),
          (string) ($explicit['purpose'] ?? $explicit['description'] ?? ''),
          $story_context,
        ])), 0, 2000);
      }
      $spec = $explicit;
      $spec['family'] = $family;
      $spec['definition_id'] = $id;
      $spec['prompt'] = mb_substr($prompt, 0, 2000);
      $definitions[$family . ':' . $id] = $spec;
    }

    // Rooms and dungeons are built in the Dungeon Editor from the story
    // brief, not here.
    return array_values($definitions);
  }


}
