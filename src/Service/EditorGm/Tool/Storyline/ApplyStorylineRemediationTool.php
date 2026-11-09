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

/**
 * Executes a validated Storyline remediation through existing authorities.
 */
final class ApplyStorylineRemediationTool implements EditorGmToolInterface {

  public function __construct(
    private readonly CanonicalDefinitionGenerationService $definitionGeneration,
    private readonly EditorCanonicalGenerationPlanService $roomGeneration,
    private readonly RoomEditorService $roomEditor,
    private readonly UuidInterface $uuid,
  ) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'apply_storyline_remediation',
      EditorGmToolDefinition::FAMILY_EXECUTION,
      'Create planned missing canonical assets, apply planned Storyline bindings, and return fresh validation.',
      TRUE,
      'CanonicalDefinitionService + RoomEditorService + StorylineAuthoringDraftService',
      [
        EditorGmToolDefinition::argument('expected_revision', 'integer', TRUE, 'Storyline draft revision the plan targets.'),
        EditorGmToolDefinition::argument('operation_id', 'string', TRUE, 'Unique remediation operation id.'),
        EditorGmToolDefinition::argument('definitions', 'array', FALSE, 'Planned canonical definition generation specifications.'),
        EditorGmToolDefinition::argument('rooms', 'array', FALSE, 'Planned canonical room generation specifications.'),
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
    $operations = $this->listArgument($arguments, 'operations');
    if ((int) ($context->draft()['revision'] ?? 0) !== $expected_revision) {
      throw new \DomainException('storyline_draft_revision_conflict');
    }

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
      $existing = $context->definitions->catalogEntry($family, $definition_id);
      if ($existing !== NULL) {
        $definition_receipts[] = ['family' => $family, 'definition_id' => $definition_id, 'created' => FALSE];
        continue;
      }
      $sub_tool_calls[] = [
        'tool' => 'generate_canonical_definition',
        'message' => sprintf("Now generating canonical %s definition '%s'…", $family, $definition_id),
      ];
      $generation_arguments = $spec;
      unset($generation_arguments['family']);
      if ($family === 'actor') {
        $actor_spec = $this->findDraftActorSpec($context->draft(), $definition_id);
        if (is_array($actor_spec)) {
          $generation_arguments['role'] = $generation_arguments['role'] ?? ($actor_spec['role'] ?? '');
          $generation_arguments['attitude'] = $generation_arguments['attitude'] ?? ($actor_spec['attitude'] ?? 'indifferent');
          $generation_arguments['storyline_binding'] = array_filter([
            'contact_id' => (string) ($actor_spec['contact_id'] ?? ''),
            'availability' => (string) ($actor_spec['availability'] ?? ''),
            'chapter_id' => (string) ($actor_spec['chapter_id'] ?? ''),
            'scene_id' => (string) ($actor_spec['scene_id'] ?? ''),
            'location_id' => (string) ($actor_spec['location_id'] ?? ''),
            'dialogue' => (string) ($actor_spec['dialogue'] ?? ''),
            'knowledge' => (string) ($actor_spec['knowledge'] ?? ''),
          ], static fn($v): bool => $v !== '');
        }
      }
      $result = $this->definitionGeneration->generateRuntimeDefinitionAndSave($family, $generation_arguments);
      if ((string) ($result['definition_id'] ?? '') !== $definition_id) {
        throw new \LogicException("storyline_remediation_definition_identity_mismatch:{$definition_id}");
      }
      if ($family === 'actor' && isset($result['payload']) && is_array($result['payload']) && \Drupal::hasContainer() && \Drupal::getContainer()->has('dungeoncrawler_content.character_manager')) {
        \Drupal::service('dungeoncrawler_content.character_manager')->saveCanonicalActorTemplate($definition_id, $result['payload'], 'storyline_remediation');
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

    $context->invalidate();
    $validation = $context->validation();

    return [
      'definitions' => $definition_receipts,
      'rooms' => $room_receipts,
      'draft' => $draft_receipt,
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

  private function findDraftActorSpec(array $draft, string $actor_id): ?array {
    $specs = $draft['payload']['objects']['actors']['generation_specs']
      ?? $draft['objects']['actors']['generation_specs']
      ?? [];
    if (!is_array($specs)) {
      return NULL;
    }
    foreach ($specs as $spec) {
      if (is_array($spec) && trim((string) ($spec['actor_id'] ?? '')) === $actor_id) {
        return $spec;
      }
    }
    return NULL;
  }

}
