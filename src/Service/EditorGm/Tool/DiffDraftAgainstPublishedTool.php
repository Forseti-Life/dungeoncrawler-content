<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\RoomEditorGmToolContext;

/**
 * Validation tool: diffs the active draft against the published version.
 */
final class DiffDraftAgainstPublishedTool implements EditorGmToolInterface {

  private const METADATA_FIELDS = ['room_id', 'name', 'description', 'room_type', 'size_category'];

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'diff_draft_vs_published',
      EditorGmToolDefinition::FAMILY_VALIDATION,
      'Report metadata, hex, placement, and port differences between draft and published version.',
      FALSE,
      'draft payload + published version payload',
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = RoomEditorGmToolContext::of($context);
    $draft_room = $context->room();
    $published = $context->publishedRoom();
    if ($published === NULL) {
      return [
        'has_published_version' => FALSE,
        'metadata_changes' => [],
        'hexes' => ['added' => count((array) ($draft_room['hexes'] ?? [])), 'removed' => 0, 'changed' => 0],
        'placements' => ['added' => count((array) ($draft_room['placements'] ?? [])), 'removed' => 0, 'changed' => 0],
        'entry_ports' => ['added' => count((array) ($draft_room['entry_ports'] ?? [])), 'removed' => 0, 'changed' => 0],
        'exit_ports' => ['added' => count((array) ($draft_room['exit_ports'] ?? [])), 'removed' => 0, 'changed' => 0],
      ];
    }

    $metadata_changes = [];
    foreach (self::METADATA_FIELDS as $field) {
      $before = $published[$field] ?? NULL;
      $after = $draft_room[$field] ?? NULL;
      if ($before !== $after) {
        $metadata_changes[] = ['field' => $field, 'published' => $before, 'draft' => $after];
      }
    }

    return [
      'has_published_version' => TRUE,
      'metadata_changes' => $metadata_changes,
      'hexes' => $this->diffKeyed(
        $published['hexes'] ?? [],
        $draft_room['hexes'] ?? [],
        static fn(array $hex): string => ((int) ($hex['q'] ?? 0)) . ':' . ((int) ($hex['r'] ?? 0)),
      ),
      'placements' => $this->diffKeyed(
        $published['placements'] ?? [],
        $draft_room['placements'] ?? [],
        static fn(array $item): string => (string) ($item['instance_id'] ?? ''),
      ),
      'entry_ports' => $this->diffKeyed(
        $published['entry_ports'] ?? [],
        $draft_room['entry_ports'] ?? [],
        static fn(array $item): string => (string) ($item['port_id'] ?? ''),
      ),
      'exit_ports' => $this->diffKeyed(
        $published['exit_ports'] ?? [],
        $draft_room['exit_ports'] ?? [],
        static fn(array $item): string => (string) ($item['port_id'] ?? ''),
      ),
    ];
  }

  /**
   * Diffs two collections by a stable identity key.
   */
  private function diffKeyed(mixed $before, mixed $after, callable $key): array {
    $before_map = $this->index(is_array($before) ? $before : [], $key);
    $after_map = $this->index(is_array($after) ? $after : [], $key);

    $added = array_values(array_diff(array_keys($after_map), array_keys($before_map)));
    $removed = array_values(array_diff(array_keys($before_map), array_keys($after_map)));
    $changed = [];
    foreach (array_intersect(array_keys($before_map), array_keys($after_map)) as $identity) {
      if ($before_map[$identity] !== $after_map[$identity]) {
        $changed[] = $identity;
      }
    }

    return [
      'added' => count($added),
      'removed' => count($removed),
      'changed' => count($changed),
      'added_keys' => array_slice($added, 0, 50),
      'removed_keys' => array_slice($removed, 0, 50),
      'changed_keys' => array_slice($changed, 0, 50),
    ];
  }

  /**
   * Indexes a collection by identity key.
   */
  private function index(array $items, callable $key): array {
    $map = [];
    foreach ($items as $item) {
      if (!is_array($item)) {
        continue;
      }
      $identity = (string) $key($item);
      if ($identity !== '') {
        $map[$identity] = $item;
      }
    }
    return $map;
  }

}
