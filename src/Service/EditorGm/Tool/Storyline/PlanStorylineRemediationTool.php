<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext;
use Drupal\dungeoncrawler_content\Service\Generation\GenerationVocabulary;
use Drupal\dungeoncrawler_content\Service\RoomEditorService;

/**
 * Plans cross-authority remediation for current Storyline validation findings.
 */
final class PlanStorylineRemediationTool implements EditorGmToolInterface {

  public function __construct(
    private readonly ?RoomEditorService $roomEditor = NULL,
  ) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'plan_storyline_remediation',
      EditorGmToolDefinition::FAMILY_PLANNING,
      'Plan creation of missing canonical definitions (items, actors, creatures, obstacles, hazards, traps) and rooms, optional draft bindings, and final revalidation.',
      FALSE,
      'CanonicalDefinitionGenerationService + RoomEditorService + StorylineAuthoringDraftService',
      [
        EditorGmToolDefinition::argument('expected_revision', 'integer', TRUE, 'Current Storyline draft revision.'),
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
    if ($operations !== []) {
      $planned = $context->drafts->planDraftOperations($context->draftId, $expected_revision, $operations);
      if (is_array($planned['payload'] ?? NULL)) {
        $planned_draft['payload'] = $planned['payload'];
      }
    }

    [$definitions, $rooms] = $this->deriveAssetPlans($planned_draft, $context->validation(), $context, $explicit_definitions);
    if ($definitions === [] && $rooms === [] && $operations === []) {
      throw new \InvalidArgumentException('storyline_remediation_empty');
    }

    return [
      'has_changes' => TRUE,
      'definition_count' => count($definitions),
      'room_count' => count($rooms),
      'draft_operation_count' => count($operations),
      'proposed_execution' => [
        'tool_name' => 'apply_storyline_remediation',
        'authority' => 'existing canonical definition, room, and Storyline draft authorities',
        'arguments' => [
          'expected_revision' => $expected_revision,
          'operation_id' => 'storyline-remediation-' . bin2hex(random_bytes(12)),
          'definitions' => $definitions,
          'rooms' => $rooms,
          'operations' => $operations,
        ],
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
    foreach ((array) ($payload['objects']['actors']['generation_specs'] ?? []) as $actor) {
      if (!is_array($actor)) {
        continue;
      }
      $id = trim((string) ($actor['actor_id'] ?? ''));
      if ($id === '') {
        continue;
      }
      if (!isset($missing_ids[$id]) && $context->definitions->catalogEntry('actor', $id) !== NULL) {
        continue;
      }
      $spec = [
        'family' => 'actor',
        'definition_id' => $id,
        'prompt' => mb_substr(implode(' ', array_filter([
          (string) ($actor['display_name'] ?? ''),
          (string) ($actor['role'] ?? ''),
          (string) ($actor['motivation'] ?? ''),
          (string) ($actor['dialogue'] ?? ''),
          (string) ($actor['knowledge'] ?? ''),
          $story_context,
        ])), 0, 2000),
      ];
      if (trim((string) ($actor['attitude'] ?? '')) !== '') {
        $spec['attitude'] = (string) $actor['attitude'];
      }
      $definitions['actor:' . $id] = $spec;
    }
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

    $rooms = [];
    foreach ((array) ($payload['objects']['rooms']['generation_specs'] ?? []) as $room) {
      if (!is_array($room)) {
        continue;
      }
      $id = trim((string) ($room['room_id'] ?? ''));
      if ($id === '') {
        continue;
      }
      $is_missing = isset($missing_ids[$id])
        || ($this->roomEditor !== NULL && $this->roomEditor->publishedRoom($id) === NULL);
      if (!$is_missing) {
        continue;
      }
      $scene_id = trim((string) ($room['scene_id'] ?? ''));
      $scene_context = [];
      foreach ((array) ($payload['process_flow']['journal_entries'] ?? []) as $scene) {
        if (is_array($scene) && (string) ($scene['scene_id'] ?? '') === $scene_id) {
          $scene_context[] = implode(' ', array_filter([
            (string) ($scene['title'] ?? ''),
            (string) ($scene['summary'] ?? ''),
            (string) ($scene['trigger'] ?? ''),
            (string) ($scene['central_conflict'] ?? ''),
          ]));
        }
      }
      foreach ((array) ($payload['objects']['actors']['generation_specs'] ?? []) as $actor) {
        if (is_array($actor) && (string) ($actor['location_id'] ?? '') === $id) {
          $scene_context[] = implode(' ', array_filter([
            (string) ($actor['display_name'] ?? ''),
            (string) ($actor['motivation'] ?? ''),
          ]));
        }
      }
      $rooms[$id] = [
        'room_id' => $id,
        'prompt' => mb_substr(implode(' ', array_filter([
          (string) ($room['narrative'] ?? ''),
          ...$scene_context,
          $story_context,
        ])), 0, 2000),
        'min_hexes' => 16,
      ];
    }

    return [array_values($definitions), array_values($rooms)];
  }

}
