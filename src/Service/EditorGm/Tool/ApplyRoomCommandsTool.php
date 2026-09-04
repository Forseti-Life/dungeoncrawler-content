<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Execution tool: applies an approved command plan to the active draft.
 *
 * Every step is dispatched through RoomEditorService::applyCommand(), which
 * keeps revision checking, idempotency, aggregate validation, and inverse
 * snapshots identical to manual editing. Steps are applied in order and the
 * first failure aborts the batch; the draft keeps whatever revision the last
 * successful step produced, exactly as it would with manual edits.
 */
final class ApplyRoomCommandsTool implements EditorGmToolInterface {

  public function __construct(private readonly UuidInterface $uuid) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'apply_room_commands',
      EditorGmToolDefinition::FAMILY_EXECUTION,
      'Apply an ordered list of approved Room Editor commands to the active draft.',
      TRUE,
      'RoomEditorService::applyCommand()',
      [
        EditorGmToolDefinition::argument('expected_revision', 'integer', TRUE, 'Draft revision the plan was built against.'),
        EditorGmToolDefinition::argument('commands', 'array', TRUE, 'Ordered list of {type, payload} command steps.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $revision = EditorGmToolContext::requireInt($arguments, 'expected_revision');
    $commands = EditorGmToolContext::requireArray($arguments, 'commands');
    if ($commands === []) {
      throw new \InvalidArgumentException('command_plan_empty');
    }

    $receipts = [];
    $draft = NULL;
    foreach (array_values($commands) as $index => $command) {
      if (!is_array($command)) {
        throw new \InvalidArgumentException(sprintf('command_step_invalid:%d', $index + 1));
      }
      $type = (string) ($command['type'] ?? '');
      if ($type === '') {
        throw new \InvalidArgumentException(sprintf('command_step_type_required:%d', $index + 1));
      }
      $payload = $command['payload'] ?? NULL;
      if (!is_array($payload)) {
        throw new \InvalidArgumentException(sprintf('command_step_payload_invalid:%d', $index + 1));
      }

      $command_id = $this->uuid->generate();
      $result = $context->roomEditor->applyCommand($context->draftId, [
        'command_id' => $command_id,
        'expected_revision' => $revision,
        'type' => $type,
        'payload' => $payload,
        'issued_at' => gmdate(DATE_RFC3339),
      ]);

      $draft = $result['draft'];
      $revision = (int) $draft['revision'];
      $receipts[] = [
        'step' => $index + 1,
        'command_id' => $command_id,
        'command_type' => $type,
        'result_revision' => $revision,
        'idempotent' => (bool) ($result['idempotent'] ?? FALSE),
      ];
    }

    $context->invalidate();

    return [
      'applied_count' => count($receipts),
      'final_revision' => $revision,
      'receipts' => $receipts,
      'draft' => $draft,
    ];
  }

}
