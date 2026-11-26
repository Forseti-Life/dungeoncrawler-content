<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext;

/**
 * Plans scene-only room unassignments without removing shared places.
 */
final class PlanStorylineSceneRoomRemovalTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'plan_storyline_scene_room_removal',
      EditorGmToolDefinition::FAMILY_PLANNING,
      'Remove specified rooms from one scene only, keeping the rooms and their actors, objectives, and other scene uses in the storyline. Use for requests to remove rooms from a particular scene or keep only particular rooms in it.',
      FALSE,
      'StorylineAuthoringDraftService::planDraftOperations()',
      [
        EditorGmToolDefinition::argument('expected_revision', 'integer', TRUE, 'Current saved draft revision.'),
        EditorGmToolDefinition::argument('scene_id', 'string', TRUE, 'Exact saved scene ID.'),
        EditorGmToolDefinition::argument('remove_room_ids', 'array', TRUE, 'Exact saved room IDs to remove from this scene, not from the storyline.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = StorylineBuilderGmToolContext::of($context);
    $revision = EditorGmToolContext::requireInt($arguments, 'expected_revision');
    $scene_id = EditorGmToolContext::requireString($arguments, 'scene_id');
    $ids = EditorGmToolContext::requireArray($arguments, 'remove_room_ids');
    if (!array_is_list($ids) || $ids === []) {
      throw new \InvalidArgumentException('storyline_scene_room_removal_ids_invalid');
    }
    foreach ($ids as $id) {
      if (!is_string($id) || trim($id) === '') {
        throw new \InvalidArgumentException('storyline_scene_room_removal_id_invalid');
      }
    }
    $ids = array_fill_keys(array_map('trim', $ids), TRUE);
    $draft = $context->draft();
    if ($revision !== (int) ($draft['revision'] ?? -1)) {
      throw new \DomainException('storyline_draft_revision_conflict');
    }
    $payload = $draft['payload'] ?? [];
    $scenes = array_values(array_filter(
      (array) ($payload['process_flow']['journal_entries'] ?? []),
      static fn($scene): bool => is_array($scene) && ($scene['scene_id'] ?? NULL) === $scene_id,
    ));
    if (count($scenes) !== 1) {
      throw new \DomainException('storyline_scene_room_removal_scene_not_unique:' . $scene_id);
    }

    $scene = $scenes[0];
    $raw = $scene['room_ids'] ?? [];
    $scene_rooms = is_array($raw) ? $raw : explode(',', (string) $raw);
    $scene_rooms = array_values(array_filter(array_map('trim', $scene_rooms), static fn(string $id): bool => $id !== ''));
    $found = array_fill_keys(array_intersect($scene_rooms, array_keys($ids)), TRUE);
    $remaining = array_values(array_filter($scene_rooms, static fn(string $id): bool => !isset($ids[$id])));
    $operations = [];
    if ($remaining !== $scene_rooms) {
      $operations[] = [
        'type' => 'patch',
        'path' => '/process_flow/journal_entries',
        'match' => ['field' => 'scene_id', 'value' => $scene_id],
        'value' => ['room_ids' => is_string($raw) ? implode(', ', $remaining) : $remaining],
      ];
    }
    foreach ((array) ($payload['objects']['rooms']['generation_specs'] ?? []) as $index => $room) {
      if (!is_array($room) || ($room['scene_id'] ?? NULL) !== $scene_id || !isset($ids[$room['room_id'] ?? ''])) {
        continue;
      }
      $found[$room['room_id']] = TRUE;
      $operations[] = [
        'type' => 'set',
        'path' => '/objects/rooms/generation_specs/' . $index . '/scene_id',
        'value' => '',
      ];
    }
    $missing = array_diff_key($ids, $found);
    if ($missing !== []) {
      throw new \DomainException('storyline_scene_room_not_assigned:' . implode(',', array_keys($missing)));
    }
    return (new PlanStorylineDraftOperationsTool())->execute([
      'expected_revision' => $revision,
      'operations' => $operations,
    ], $context);
  }

}
