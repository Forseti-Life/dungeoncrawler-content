<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorCanonicalGenerationPlanService;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\RoomEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalDefinitionGenerationService;
use Drupal\dungeoncrawler_content\Service\RoomEditorService;
use Drupal\dungeoncrawler_content\Service\CanonicalQuestTemplateService;

/**
 * Executes a validated Storyline remediation through existing authorities.
 */
final class ApplyStorylineRemediationTool implements EditorGmToolInterface {

  public function __construct(
    private readonly CanonicalDefinitionGenerationService $definitionGeneration,
    private readonly EditorCanonicalGenerationPlanService $roomGeneration,
    private readonly RoomEditorService $roomEditor,
    private readonly UuidInterface $uuid,
    private readonly CanonicalQuestTemplateService $questTemplates,
  ) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'apply_storyline_remediation',
      EditorGmToolDefinition::FAMILY_EXECUTION,
      'Create planned missing canonical assets, repair planned linked-quest references, apply Storyline bindings, and return fresh validation.',
      TRUE,
      'CanonicalDefinitionService + RoomEditorService + StorylineAuthoringDraftService',
      [
        EditorGmToolDefinition::argument('expected_revision', 'integer', TRUE, 'Storyline draft revision the plan targets.'),
        EditorGmToolDefinition::argument('operation_id', 'string', TRUE, 'Unique remediation operation id.'),
        EditorGmToolDefinition::argument('definitions', 'array', FALSE, 'Planned canonical definition generation specifications.'),
        EditorGmToolDefinition::argument('rooms', 'array', FALSE, 'Planned canonical room generation specifications.'),
        EditorGmToolDefinition::argument('dungeons', 'array', FALSE, 'Planned canonical dungeon contract specifications.'),
        EditorGmToolDefinition::argument('quests', 'array', FALSE, 'Planned linked-quest storyline-reference repairs. Quest records themselves are written when the storyline is published.'),
        EditorGmToolDefinition::argument('operations', 'array', FALSE, 'Planned Storyline draft operations.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = StorylineBuilderGmToolContext::of($context);
    $expected_revision = EditorGmToolContext::requireInt($arguments, 'expected_revision');
    $operation_id = EditorGmToolContext::requireString($arguments, 'operation_id');
    $definitions = $this->listArgument($arguments, 'definitions');
    $rooms = $this->listArgument($arguments, 'rooms');
    $dungeons = $this->listArgument($arguments, 'dungeons');
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

    $room_receipts = [];
    foreach ($rooms as $spec) {
      $room_id = (string) $spec['room_id'];
      if ($this->roomEditor->publishedRoom($room_id) !== NULL) {
        $room_receipts[] = ['room_id' => $room_id, 'created' => FALSE];
        continue;
      }
      if (($definition_receipts !== [] || $room_receipts !== []) && (microtime(TRUE) - $started_at) >= $time_budget_seconds) {
        $deferred_tasks[] = ['kind' => 'room', 'room_id' => $room_id];
        continue;
      }
      $sub_tool_calls[] = [
        'tool' => 'generate_room_layout',
        'message' => sprintf("Now generating canonical room '%s'…", $room_id),
      ];
      $room_draft = $this->roomEditor->createDraft();
      $room_draft_id = (string) $room_draft['draft_id'];
      $revision = (int) $room_draft['revision'];
      $metadata = $this->roomEditor->applyCommand($room_draft_id, [
        'command_id' => $this->uuid->generate(),
        'expected_revision' => $revision,
        'type' => 'set_room_metadata',
        'payload' => ['changes' => ['room_id' => $room_id]],
        'issued_at' => gmdate(DATE_RFC3339),
      ]);
      $revision = (int) $metadata['draft']['revision'];
      $room_context = new RoomEditorGmToolContext($room_draft_id, 'editing', $this->roomEditor, $context->definitions);
      $generation_arguments = $spec;
      unset($generation_arguments['room_id']);
      $generated = $this->roomGeneration->generateRoomLayout($generation_arguments, $room_context);
      foreach ((array) ($generated['command_plan']['steps'] ?? []) as $step) {
        $result = $this->roomEditor->applyCommand($room_draft_id, [
          'command_id' => $this->uuid->generate(),
          'expected_revision' => $revision,
          'type' => (string) ($step['command_type'] ?? ''),
          'payload' => (array) ($step['payload'] ?? []),
          'issued_at' => gmdate(DATE_RFC3339),
        ]);
        $revision = (int) $result['draft']['revision'];
      }
      $validation = $this->roomEditor->validateDraft($room_draft_id, 'publication');
      if (empty($validation['valid'])) {
        throw new \DomainException('storyline_remediation_room_invalid:' . $room_id);
      }
      $this->roomEditor->publish($room_draft_id, [
        'version' => '1.0.0',
        'expected_revision' => $revision,
        'expected_base_version_id' => NULL,
        'publication_note' => 'Created by Storyline GM validation remediation.',
      ]);
      $room_receipts[] = ['room_id' => $room_id, 'created' => TRUE];
    }

    $dungeon_receipts = [];
    foreach ($dungeons as $spec) {
      if (!is_array($spec)) {
        continue;
      }
      $dungeon_id = trim((string) ($spec['dungeon_id'] ?? ''));
      if ($dungeon_id === '') {
        continue;
      }
      $sub_tool_calls[] = [
        'tool' => 'publish_canonical_dungeon',
        'message' => sprintf("Now publishing canonical dungeon contract '%s'…", $dungeon_id),
      ];
      $dungeon_receipts[] = $this->upsertCanonicalDungeon($spec, $deferred_tasks);
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
      'rooms' => $room_receipts,
      'dungeons' => $dungeon_receipts,
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

  private function upsertCanonicalDungeon(array $spec, array $deferred_tasks): array {
    $dungeon_id = trim((string) ($spec['dungeon_id'] ?? ''));
    $entrance_room = trim((string) ($spec['entrance_room'] ?? ''));
    $rooms = array_values(array_unique(array_filter(array_map('strval', (array) ($spec['rooms'] ?? [])))));
    if ($entrance_room === '' && $rooms !== []) {
      $entrance_room = $rooms[0];
    }
    if ($entrance_room !== '' && !in_array($entrance_room, $rooms, TRUE)) {
      array_unshift($rooms, $entrance_room);
    }

    foreach ($deferred_tasks as $deferred) {
      if (($deferred['kind'] ?? '') === 'room' && in_array((string) ($deferred['room_id'] ?? ''), $rooms, TRUE)) {
        return ['dungeon_id' => $dungeon_id, 'created' => FALSE, 'deferred' => TRUE];
      }
    }

    if (!\Drupal::hasContainer() || !\Drupal::getContainer()->has('database')) {
      return ['dungeon_id' => $dungeon_id, 'created' => FALSE];
    }
    $db = \Drupal::database();
    if (!$db->schema()->tableExists('dungeoncrawler_content_dungeons')) {
      return ['dungeon_id' => $dungeon_id, 'created' => FALSE];
    }

    $existing = $db->select('dungeoncrawler_content_dungeons', 'd')
      ->fields('d')
      ->condition('dungeon_id', $dungeon_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    $now = time();
    $storyline_id = trim((string) ($spec['storyline_id'] ?? ''));
    $purpose = trim((string) ($spec['purpose'] ?? 'storyline-site')) ?: 'storyline-site';
    $human_name = ucwords(str_replace(['-', '_'], ' ', $dungeon_id));

    if (is_array($existing)) {
      $decoded = is_string($existing['dungeon_data'] ?? NULL) ? json_decode($existing['dungeon_data'], TRUE) : [];
      if (!is_array($decoded)) {
        $decoded = [];
      }
      if (is_array($decoded['room_placements'] ?? NULL)) {
        $placed_ids = [];
        foreach ($decoded['room_placements'] as $placement) {
          if (is_array($placement) && trim((string) ($placement['room_id'] ?? '')) !== '') {
            $placed_ids[trim((string) $placement['room_id'])] = TRUE;
          }
        }
        $offset = count($decoded['room_placements']);
        foreach ($rooms as $r_id) {
          if (isset($placed_ids[$r_id])) {
            continue;
          }
          $decoded['room_placements'][] = [
            'placement_id' => $this->uuid->generate(),
            'room_id' => $r_id,
            'origin' => ['q' => $offset * 6, 'r' => 0],
            'rotation_steps' => 0,
            'label' => ucwords(str_replace(['-', '_'], ' ', $r_id)),
            'is_level_entrance' => $r_id === $entrance_room && $offset === 0,
            'tags' => ['storyline_remediation'],
          ];
          $offset++;
        }
      }
      else {
        $merged_rooms = array_values(array_unique(array_merge(
          array_map('strval', (array) ($decoded['rooms'] ?? [])),
          $rooms,
        )));
        $decoded['schema_version'] = $decoded['schema_version'] ?? '1.0.0';
        $decoded['entry_room'] = trim((string) ($decoded['entry_room'] ?? '')) ?: $entrance_room;
        $decoded['rooms'] = $merged_rooms;
      }

      $db->update('dungeoncrawler_content_dungeons')
        ->fields([
          'dungeon_data' => json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
          'updated' => $now,
        ])
        ->condition('dungeon_id', $dungeon_id)
        ->execute();

      return ['dungeon_id' => $dungeon_id, 'created' => FALSE, 'updated' => TRUE];
    }

    $canonical_dungeon_data = [
      'schema_version' => '1.0.0',
      'storyline_id' => $storyline_id,
      'goal_alignment' => $purpose,
      'entry_room' => $entrance_room,
      'rooms' => $rooms,
      'generation_context' => [
        'source' => 'storyline_remediation',
        'generated_at' => gmdate('c', $now),
      ],
    ];

    $db->merge('dungeoncrawler_content_dungeons')
      ->keys(['dungeon_id' => $dungeon_id])
      ->fields([
        'name' => $human_name,
        'description' => $purpose,
        'theme' => 'forest',
        'dungeon_data' => json_encode($canonical_dungeon_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'source_dungeon_id' => $storyline_id !== '' ? $storyline_id : NULL,
        'updated' => $now,
      ])
      ->expression('created', 'COALESCE(created, :created)', [':created' => $now])
      ->execute();

    return ['dungeon_id' => $dungeon_id, 'created' => TRUE];
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
