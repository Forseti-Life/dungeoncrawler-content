<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon;

use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmHarnessService;

/**
 * Shared helpers for building editor-gm-command-plan-v1 payloads.
 */
final class DungeonPlanSteps {

  public static function step(int $index, string $type, array $payload, string $rationale): array {
    return [
      'step' => $index,
      'command_type' => $type,
      'payload' => $payload,
      'rationale' => $rationale,
    ];
  }

  /**
   * Wraps ordered steps in a plan bound to the draft's current revision.
   */
  public static function plan(DungeonEditorGmToolContext $context, array $steps): array {
    if ($steps === []) {
      throw new \DomainException('planning_produced_no_steps');
    }
    return [
      'schema_version' => EditorGmHarnessService::COMMAND_PLAN_CONTRACT_VERSION,
      'draft_id' => $context->draftId,
      'base_revision' => (int) ($context->draft()['revision'] ?? 0),
      'steps' => array_values($steps),
    ];
  }

  /**
   * Finds one placement in the read model or hard-fails.
   */
  public static function placement(array $model, string $placement_id): array {
    foreach ((array) ($model['placements'] ?? []) as $placement) {
      if ($placement['placement_id'] === $placement_id) {
        return $placement;
      }
    }
    throw new \OutOfBoundsException(sprintf('placement_not_found:%s', $placement_id));
  }

  /**
   * Finds one published library room by room_id or hard-fails.
   */
  public static function libraryRoom(DungeonEditorGmToolContext $context, string $room_id): array {
    foreach ($context->roomLibrary() as $entry) {
      if ($entry['room_id'] === $room_id) {
        return $entry;
      }
    }
    throw new \OutOfBoundsException(sprintf('room_not_published:%s', $room_id));
  }

}
