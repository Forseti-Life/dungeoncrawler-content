<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon;

use Drupal\dungeoncrawler_content\Geometry\RoomPlacementTransformer;
use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Compact level-space view of the dungeon: placements, ports, links, regions.
 *
 * Every port is reported in level space so the assistant can reason about
 * adjacency with the same transform the validator uses.
 */
final class SummarizeLevelTopologyTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'summarize_level_topology',
      EditorGmToolDefinition::FAMILY_CONTEXT,
      'Summarize the level: each placement with its level-space bounds and ports (exit ports flagged linked/unlinked), the link graph, regions and level entrances.',
      FALSE,
      'DungeonEditorService::describe() + RoomPlacementTransformer',
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = DungeonEditorGmToolContext::of($context);
    $model = $context->model();

    $linked_exits = [];
    foreach ((array) ($model['port_links'] ?? []) as $link) {
      $linked_exits[$link['from']['placement_id'] . ':' . $link['from']['port_id']] = $link['link_id'];
    }

    $placements = [];
    $min_q = $min_r = PHP_INT_MAX;
    $max_q = $max_r = PHP_INT_MIN;
    foreach ((array) ($model['placements'] ?? []) as $placement) {
      $entry = [
        'placement_id' => $placement['placement_id'],
        'room_id' => $placement['room_id'],
        'version_id' => $placement['version_id'],
        'label' => $placement['label'],
        'origin' => $placement['origin'],
        'rotation_steps' => $placement['rotation_steps'],
        'is_level_entrance' => $placement['is_level_entrance'],
        'resolved' => $placement['resolved'],
        'hex_count' => count((array) ($placement['level_hexes'] ?? [])),
        'bounds' => NULL,
        'ports' => [],
      ];
      if ($placement['resolved']) {
        $b = self::bounds((array) $placement['level_hexes']);
        $entry['bounds'] = $b;
        $min_q = min($min_q, $b['min_q']);
        $max_q = max($max_q, $b['max_q']);
        $min_r = min($min_r, $b['min_r']);
        $max_r = max($max_r, $b['max_r']);
        foreach ((array) ($placement['room']['ports'] ?? []) as $port) {
          $level = RoomPlacementTransformer::toLevelPort(['q' => $port['q'], 'r' => $port['r']], (int) $port['edge'], $placement);
          $key = $placement['placement_id'] . ':' . $port['port_id'];
          $entry['ports'][] = [
            'port_id' => $port['port_id'],
            'kind' => $port['kind'],
            'level_hex' => ['q' => $level['q'], 'r' => $level['r']],
            'level_edge' => $level['edge'],
            'link_id' => $linked_exits[$key] ?? NULL,
          ];
        }
      }
      $placements[] = $entry;
    }

    $links = array_map(static fn(array $link): array => [
      'link_id' => $link['link_id'],
      'from' => $link['from'],
      'to' => $link['to'],
      'kind' => $link['kind'],
      'direction' => $link['direction'],
      'default_state' => $link['default_state'],
    ], (array) ($model['port_links'] ?? []));

    $regions = array_map(static fn(array $region): array => [
      'region_id' => $region['region_id'],
      'name' => $region['name'],
      'placement_ids' => $region['placement_ids'],
    ], (array) ($model['regions'] ?? []));

    return [
      'name' => $model['name'],
      'revision' => $model['revision'],
      'level_bounds' => $placements === [] || $min_q === PHP_INT_MAX ? NULL : ['min_q' => $min_q, 'max_q' => $max_q, 'min_r' => $min_r, 'max_r' => $max_r],
      'placement_count' => count($placements),
      'placements' => $placements,
      'link_count' => count($links),
      'links' => $links,
      'region_count' => count($regions),
      'regions' => $regions,
      'overlapping_hexes' => array_values(array_filter((array) ($model['occupancy'] ?? []), static fn(array $ids): bool => count($ids) > 1)),
    ];
  }

  /**
   * @param array<int, array{q: int, r: int}> $hexes
   */
  private static function bounds(array $hexes): array {
    $qs = array_column($hexes, 'q');
    $rs = array_column($hexes, 'r');
    return ['min_q' => min($qs), 'max_q' => max($qs), 'min_r' => min($rs), 'max_r' => max($rs)];
  }

}
