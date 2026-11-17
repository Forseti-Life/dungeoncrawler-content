<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext;
use Drupal\dungeoncrawler_content\Service\Generation\GenerationVocabulary;
use Drupal\dungeoncrawler_content\Service\RoomEditorService;
use Drupal\dungeoncrawler_content\Service\CanonicalQuestTemplateService;

/**
 * Plans cross-authority remediation for current Storyline validation findings.
 */
final class PlanStorylineRemediationTool implements EditorGmToolInterface {

  public function __construct(
    private readonly CanonicalQuestTemplateService $questTemplates,
    private readonly ?RoomEditorService $roomEditor = NULL,
  ) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'plan_storyline_remediation',
      EditorGmToolDefinition::FAMILY_PLANNING,
      'Plan missing canonical assets, linked quest storyline-reference repairs, optional draft bindings, and final revalidation. Use scope=quest_storyline_references for only stale linked-quest references.',
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
    if ($operations !== []) {
      $planned = $context->drafts->planDraftOperations($context->draftId, $expected_revision, $operations);
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
      [$definitions, $rooms, $dungeons, $quests] = [[], [], [], $reference_repairs];
    }
    else {
      [$definitions, $rooms, $dungeons, $quests] = $this->deriveAssetPlans($planned_draft, $context->validation(), $context, $explicit_definitions);
      $quests = array_column($quests, NULL, 'template_id');
      foreach ($reference_repairs as $repair) {
        if (isset($quests[$repair['template_id']])) {
          $quests[$repair['template_id']]['storyline_reference_repair'] = $repair;
        }
        else {
          $quests[$repair['template_id']] = $repair;
        }
      }
      $quests = array_values($quests);
    }
    if ($definitions === [] && $rooms === [] && $dungeons === [] && $quests === [] && $operations === []) {
      return [
        'has_changes' => FALSE,
        'definition_count' => 0,
        'room_count' => 0,
        'dungeon_count' => 0,
        'quest_count' => 0,
        'draft_operation_count' => 0,
      ];
    }

    return [
      'has_changes' => TRUE,
      'definition_count' => count($definitions),
      'room_count' => count($rooms),
      'dungeon_count' => count($dungeons),
      'quest_count' => count($quests),
      'draft_operation_count' => count($operations),
      'proposed_execution' => [
        'tool_name' => 'apply_storyline_remediation',
        'authority' => 'existing canonical definition, room, dungeon, quest, and Storyline draft authorities',
        'arguments' => [
          'expected_revision' => $expected_revision,
          'operation_id' => 'storyline-remediation-' . bin2hex(random_bytes(12)),
          'definitions' => $definitions,
          'rooms' => $rooms,
          'dungeons' => $dungeons,
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

    $missing_dungeon_ids = [];
    foreach (['errors', 'warnings'] as $bucket) {
      foreach ((array) ($validation[$bucket] ?? []) as $finding) {
        $text = (string) $finding;
        if (preg_match("/dungeon '([^']+)'/i", $text, $matches)) {
          $missing_dungeon_ids[trim($matches[1])] = TRUE;
        }
      }
    }

    $dungeons = [];
    $authored_rooms_by_dungeon = [];
    foreach ((array) ($payload['objects']['rooms']['generation_specs'] ?? []) as $room) {
      if (!is_array($room)) {
        continue;
      }
      $r_id = trim((string) ($room['room_id'] ?? ''));
      $d_id = trim((string) ($room['dungeon_id'] ?? ''));
      if ($r_id !== '' && $d_id !== '') {
        $authored_rooms_by_dungeon[$d_id][$r_id] = $r_id;
      }
    }

    foreach ((array) ($payload['objects']['dungeons']['generation_specs'] ?? []) as $dungeon) {
      if (!is_array($dungeon)) {
        continue;
      }
      $dungeon_id = trim((string) ($dungeon['dungeon_id'] ?? ''));
      if ($dungeon_id === '') {
        continue;
      }
      $dungeon_rooms = array_values($authored_rooms_by_dungeon[$dungeon_id] ?? []);
      $entrance_room = trim((string) ($dungeon['entrance_room'] ?? ''));
      if ($entrance_room === '' && $dungeon_rooms !== []) {
        $entrance_room = $dungeon_rooms[0];
      }
      if ($entrance_room !== '' && !in_array($entrance_room, $dungeon_rooms, TRUE)) {
        array_unshift($dungeon_rooms, $entrance_room);
      }
      if ($dungeon_rooms === []) {
        continue;
      }

      $needs_dungeon = isset($missing_dungeon_ids[$dungeon_id]) || !$this->canonicalDungeonCoversRooms($dungeon_id, $entrance_room, $dungeon_rooms);
      if (!$needs_dungeon) {
        continue;
      }
      $dungeons[$dungeon_id] = [
        'dungeon_id' => $dungeon_id,
        'chapter_id' => trim((string) ($dungeon['chapter_id'] ?? '')),
        'purpose' => trim((string) ($dungeon['purpose'] ?? 'storyline-site')) ?: 'storyline-site',
        'entrance_room' => $entrance_room,
        'rooms' => $dungeon_rooms,
        'storyline_id' => trim((string) ($payload['storyline_id'] ?? $draft['source_template_id'] ?? '')),
      ];
    }

    // Quest records are written from the draft when the storyline is
    // published; only linked-quest reference repairs are planned here.
    return [array_values($definitions), array_values($rooms), array_values($dungeons), []];
  }

  private function canonicalDungeonCoversRooms(string $dungeon_id, string $entrance_room, array $required_rooms): bool {
    if (!\Drupal::hasContainer() || !\Drupal::getContainer()->has('database')) {
      return FALSE;
    }
    $db = \Drupal::database();
    if (!$db->schema()->tableExists('dungeoncrawler_content_dungeons')) {
      return FALSE;
    }
    $row = $db->select('dungeoncrawler_content_dungeons', 'd')
      ->fields('d', ['dungeon_data'])
      ->condition('dungeon_id', $dungeon_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($row)) {
      return FALSE;
    }
    $decoded = is_string($row['dungeon_data'] ?? NULL) ? json_decode($row['dungeon_data'], TRUE) : NULL;
    if (!is_array($decoded)) {
      return FALSE;
    }
    $existing_rooms = [];
    if (is_array($decoded['room_placements'] ?? NULL)) {
      foreach ($decoded['room_placements'] as $placement) {
        if (is_array($placement) && trim((string) ($placement['room_id'] ?? '')) !== '') {
          $existing_rooms[trim((string) $placement['room_id'])] = TRUE;
        }
      }
    }
    else {
      foreach ((array) ($decoded['rooms'] ?? []) as $r_id) {
        if (trim((string) $r_id) !== '') {
          $existing_rooms[trim((string) $r_id)] = TRUE;
        }
      }
    }
    if ($entrance_room !== '' && !isset($existing_rooms[$entrance_room])) {
      return FALSE;
    }
    foreach ($required_rooms as $req_room) {
      if (!isset($existing_rooms[$req_room])) {
        return FALSE;
      }
    }
    return TRUE;
  }

}
