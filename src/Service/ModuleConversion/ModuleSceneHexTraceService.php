<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\ModuleConversion;

use Drupal\dungeoncrawler_content\Geometry\RoomPlacementTransformer;

/**
 * Produces a read-only hex trace for a NEW conversion revision.
 *
 * Foundry raster images and square-grid geometry do not establish playable
 * terrain or doors. Every cell and port must therefore be explicitly reviewed.
 * The output is not a canonical room and must pass Room Editor validation
 * before a separate release can use it.
 */
final class ModuleSceneHexTraceService {

  private const EMBEDDING_VERSION = 'induced_square_graph_two_hex_v1';

  private const TERRAIN = [
    'stone_floor', 'rough_stone', 'smooth_stone', 'dirt', 'mud', 'sand',
    'water_shallow', 'water_deep', 'ice', 'lava', 'fungal_growth', 'bone',
    'crystal', 'metal_grate', 'wooden_floor', 'carpet', 'rubble', 'void',
  ];

  /**
   * @param array $resource
   *   Scene resource from the immutable conversion plan.
   * @param array $review
   *   A project-authored review with archive_sha256, scene_resource_id,
   *   cells (row-major [{x,y,in_footprint,terrain_type}]), and ports
   *   ([{x,y,side,kind}]). Sides are north/east/south/west in the
   *   source square grid. Ports are reviewed assertions, not source doors.
   */
  public function trace(array $resource, array $review, string $archive_sha256): array {
    $context = $resource['source']['source_context'] ?? NULL;
    $id = $resource['resource_id'] ?? NULL;
    if (($resource['resource_type'] ?? NULL) !== 'scene'
      || !is_string($id) || $id === ''
      || !is_array($context) || !preg_match('/^[a-f0-9]{64}$/D', $archive_sha256)
      || ($review['archive_sha256'] ?? NULL) !== $archive_sha256
      || ($review['scene_resource_id'] ?? NULL) !== $id) {
      throw new \InvalidArgumentException('scene_trace_provenance_invalid');
    }
    $width = $context['width'] ?? NULL;
    $height = $context['height'] ?? NULL;
    $size = $context['grid_size'] ?? NULL;
    if (($context['grid_type'] ?? NULL) !== 1
      || ($context['grid_distance'] ?? NULL) !== 5
      || ($context['grid_units'] ?? NULL) !== 'ft'
      || !is_int($width) || !is_int($height) || !is_int($size)
      || $width < 1 || $height < 1 || $size < 1
      || $width % $size !== 0 || $height % $size !== 0
      || $width / $size > 100 || $height / $size > 100) {
      throw new \DomainException('scene_trace_square_grid_unsupported');
    }
    $columns = intdiv($width, $size);
    $rows = intdiv($height, $size);
    if (!is_array($review['cells'] ?? NULL) || !array_is_list($review['cells'])
      || count($review['cells']) !== $columns * $rows
      || !is_array($review['ports'] ?? NULL) || !array_is_list($review['ports'])
      || count($review['ports']) > 70
      || !is_array($resource['source']['counts'] ?? NULL)
      || !is_int($resource['source']['counts']['walls'] ?? 0)
      || ($resource['source']['counts']['walls'] ?? 0) !== 0) {
      throw new \DomainException('scene_trace_review_or_walls_unsupported');
    }
    $hexes = [];
    $included = [];
    foreach ($review['cells'] as $index => $cell) {
      $x = $index % $columns;
      $y = intdiv($index, $columns);
      if (!is_array($cell) || ($cell['x'] ?? NULL) !== $x || ($cell['y'] ?? NULL) !== $y
        || !is_bool($cell['in_footprint'] ?? NULL)
        || ($cell['in_footprint'] && !in_array($cell['terrain_type'] ?? NULL, self::TERRAIN, TRUE))
        || (!$cell['in_footprint'] && ($cell['terrain_type'] ?? NULL) !== NULL)) {
        throw new \DomainException('scene_trace_cell_review_invalid');
      }
      if ($cell['in_footprint']) {
        if (count($hexes) > 9998) {
          throw new \DomainException('scene_trace_hex_limit_exceeded');
        }
        $a = self::coordinate($x, $y);
        $b = ['q' => $a['q'] + 1, 'r' => $a['r']];
        $included["$x:$y"] = ['a' => $a, 'b' => $b];
        $hexes[] = $a + ['terrain_type' => $cell['terrain_type'], 'elevation_ft' => 0];
        $hexes[] = $b + ['terrain_type' => $cell['terrain_type'], 'elevation_ft' => 0];
      }
    }
    if ($hexes === []) {
      throw new \DomainException('scene_trace_empty_footprint');
    }
    $hex_keys = array_fill_keys(array_map(static fn(array $h): string => $h['q'] . ':' . $h['r'], $hexes), TRUE);
    $entries = [];
    $exits = [];
    $used = [];
    foreach ($review['ports'] as $index => $port) {
      if (!is_array($port) || !is_int($port['x'] ?? NULL) || !is_int($port['y'] ?? NULL)
        || !in_array($port['side'] ?? NULL, ['north', 'east', 'south', 'west'], TRUE)
        || !in_array($port['kind'] ?? NULL, ['entry', 'exit'], TRUE)
        || !isset($included[$port['x'] . ':' . $port['y']])) {
        throw new \DomainException('scene_trace_port_invalid');
      }
      [$part, $edge] = match ($port['side']) {
        'east' => ['b', 5],
        'south' => ['b', 1],
        'west' => ['a', 2],
        'north' => ['a', 4],
      };
      $hex = $included[$port['x'] . ':' . $port['y']][$part];
      $outside = RoomPlacementTransformer::neighbor($hex, $edge);
      $key = $hex['q'] . ':' . $hex['r'] . ':' . $edge;
      if (isset($hex_keys[$outside['q'] . ':' . $outside['r']]) || isset($used[$key])) {
        throw new \DomainException('scene_trace_port_not_unique_boundary');
      }
      $used[$key] = TRUE;
      $candidate = [
        'port_id' => $port['kind'] . '-' . ($index + 1),
        'hex' => $hex,
        'edge' => $edge,
        'label' => $port['kind'] === 'entry' ? 'Entry' : 'Exit',
        'tags' => [],
      ];
      if ($port['kind'] === 'entry') {
        $candidate += ['arrival_facing' => ($edge + 3) % 6, 'is_default' => $entries === []];
        $entries[] = $candidate;
      }
      else {
        $exits[] = $candidate;
      }
    }
    if ($entries === [] || count($entries) > 20 || count($exits) > 50) {
      throw new \DomainException('scene_trace_entry_review_required');
    }
    $payload = [self::EMBEDDING_VERSION, $archive_sha256, $id, $context, $review];
    return [
      'schema_version' => 'module-scene-hex-trace-v1',
      'archive_sha256' => $archive_sha256,
      'scene_resource_id' => $id,
      'review_sha256' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
      'source_grid' => ['columns' => $columns, 'rows' => $rows, 'distance_ft' => 5, 'embedding' => self::EMBEDDING_VERSION],
      'hexes' => $hexes,
      'entry_ports' => $entries,
      'exit_ports' => $exits,
      'fidelity' => 'reviewed_cell_annotations_not_raster_verified',
      'publication_ready' => FALSE,
      'unresolved' => [
        'two_hex_cell_embedding_preserves_orthogonal_graph_not_square_metric',
        'raster_terrain_and_doors_require_independent_review',
        'room_editor_and_movement_validation_not_performed',
      ],
    ];
  }

  private static function coordinate(int $x, int $y): array {
    // Contracting each A--B pair yields a square graph: B links only to
    // east/south A; A links only to west/north B. The omitted third axial
    // color prevents any cross-cell diagonal edges.
    return ['q' => 2 * $x + $y, 'r' => $y - $x];
  }

}
