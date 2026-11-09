<?php

declare(strict_types=1);

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleSceneHexTraceService;
use Drupal\dungeoncrawler_content\Geometry\RoomPlacementTransformer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleSceneHexTraceService::class)]
final class ModuleSceneHexTraceServiceTest extends TestCase {

  private const HASH = 'a12c496b0b9e91946d94b024280702f46a0b90ae0506a71214a42307be140df5';

  private static function scene(): array {
    return [
      'resource_id' => 'scene-fixture',
      'resource_type' => 'scene',
      'source' => [
        'counts' => ['walls' => 0],
        'source_context' => [
          'width' => 300, 'height' => 200, 'grid_size' => 100,
          'grid_type' => 1, 'grid_distance' => 5, 'grid_units' => 'ft',
        ],
      ],
    ];
  }

  private static function review(): array {
    $cells = [];
    foreach (range(0, 1) as $y) {
      foreach (range(0, 2) as $x) {
        $included = !($x === 2 && $y === 1);
        $cells[] = [
          'x' => $x, 'y' => $y, 'in_footprint' => $included,
          'terrain_type' => $included ? ($x === 1 ? 'water_shallow' : 'dirt') : NULL,
        ];
      }
    }
    return [
      'archive_sha256' => self::HASH,
      'scene_resource_id' => 'scene-fixture',
      'cells' => $cells,
      'ports' => [
        ['x' => 0, 'y' => 0, 'side' => 'west', 'kind' => 'entry'],
        ['x' => 1, 'y' => 1, 'side' => 'east', 'kind' => 'exit'],
      ],
    ];
  }

  public function testReviewedCellsProduceStableSourceTraceWithoutMutation(): void {
    $scene = self::scene();
    $review = self::review();
    $result = (new ModuleSceneHexTraceService())->trace($scene, $review, self::HASH);
    self::assertSame('module-scene-hex-trace-v1', $result['schema_version']);
    self::assertSame(['columns' => 3, 'rows' => 2, 'distance_ft' => 5, 'embedding' => 'induced_square_graph_two_hex_v1'], $result['source_grid']);
    self::assertSame([
      ['q' => 0, 'r' => 0, 'terrain_type' => 'dirt', 'elevation_ft' => 0],
      ['q' => 1, 'r' => 0, 'terrain_type' => 'dirt', 'elevation_ft' => 0],
      ['q' => 2, 'r' => -1, 'terrain_type' => 'water_shallow', 'elevation_ft' => 0],
      ['q' => 3, 'r' => -1, 'terrain_type' => 'water_shallow', 'elevation_ft' => 0],
      ['q' => 4, 'r' => -2, 'terrain_type' => 'dirt', 'elevation_ft' => 0],
      ['q' => 5, 'r' => -2, 'terrain_type' => 'dirt', 'elevation_ft' => 0],
      ['q' => 1, 'r' => 1, 'terrain_type' => 'dirt', 'elevation_ft' => 0],
      ['q' => 2, 'r' => 1, 'terrain_type' => 'dirt', 'elevation_ft' => 0],
      ['q' => 3, 'r' => 0, 'terrain_type' => 'water_shallow', 'elevation_ft' => 0],
      ['q' => 4, 'r' => 0, 'terrain_type' => 'water_shallow', 'elevation_ft' => 0],
    ], $result['hexes']);
    self::assertSame(['q' => 4, 'r' => 0], $result['exit_ports'][0]['hex']);
    self::assertSame(5, $result['exit_ports'][0]['edge']);
    self::assertTrue($result['entry_ports'][0]['is_default']);
    self::assertSame(5, $result['entry_ports'][0]['arrival_facing']);
    self::assertSame($result, (new ModuleSceneHexTraceService())->trace($scene, $review, self::HASH));
    self::assertSame(6, count($review['cells']));
    $review['cells'][1]['terrain_type'] = 'rubble';
    self::assertNotSame($result['review_sha256'], (new ModuleSceneHexTraceService())->trace($scene, $review, self::HASH)['review_sha256']);
    unset($scene['source']['counts']['walls']);
    self::assertSame($result['hexes'], (new ModuleSceneHexTraceService())->trace($scene, self::review(), self::HASH)['hexes']);
  }

  public function testTraceDoesNotClaimMovementReachability(): void {
    $review = self::review();
    $review['cells'][1]['terrain_type'] = 'void';
    $trace = (new ModuleSceneHexTraceService())->trace(self::scene(), $review, self::HASH);
    self::assertSame('void', $trace['hexes'][2]['terrain_type']);
    self::assertSame('void', $trace['hexes'][3]['terrain_type']);
    self::assertContains('room_editor_and_movement_validation_not_performed', $trace['unresolved']);
    self::assertFalse($trace['publication_ready']);
  }

  #[DataProvider('invalidCases')]
  public function testFailsClosedOnUnsupportedOrUnreviewedSource(string $case, string $expected): void {
    $scene = self::scene();
    $review = self::review();
    $hash = self::HASH;
    switch ($case) {
      case 'other_archive': $hash = str_repeat('f', 64); break;
      case 'other_scene': $review['scene_resource_id'] = 'different'; break;
      case 'overland': $scene['source']['source_context']['grid_type'] = 5; break;
      case 'wrong_scale': $scene['source']['source_context']['grid_distance'] = 3; break;
      case 'partial_cell': array_pop($review['cells']); break;
      case 'unreviewed_cell': $review['cells'][2]['terrain_type'] = NULL; break;
      case 'shifted_cell': $review['cells'][1]['x'] = 2; break;
      case 'partial_grid': $scene['source']['source_context']['width'] = 299; break;
      case 'walls': $scene['source']['counts']['walls'] = 1; break;
      case 'invalid_walls': $scene['source']['counts']['walls'] = '0'; break;
      case 'interior_port': $review['ports'][1]['side'] = 'west'; break;
      case 'duplicate_port': $review['ports'][] = $review['ports'][0]; break;
      case 'port_outside': $review['ports'][1]['x'] = 2; break;
      case 'missing_entry': $review['ports'] = [$review['ports'][1]]; break;
    }
    $this->expectException(\Throwable::class);
    $this->expectExceptionMessage($expected);
    (new ModuleSceneHexTraceService())->trace($scene, $review, $hash);
  }

  public static function invalidCases(): array {
    return [
      'archive binding' => ['other_archive', 'scene_trace_provenance_invalid'],
      'scene binding' => ['other_scene', 'scene_trace_provenance_invalid'],
      'island overview' => ['overland', 'scene_trace_square_grid_unsupported'],
      'wrong distance' => ['wrong_scale', 'scene_trace_square_grid_unsupported'],
      'missing review' => ['partial_cell', 'scene_trace_review_or_walls_unsupported'],
      'missing terrain' => ['unreviewed_cell', 'scene_trace_cell_review_invalid'],
      'row order' => ['shifted_cell', 'scene_trace_cell_review_invalid'],
      'fractional cell' => ['partial_grid', 'scene_trace_square_grid_unsupported'],
      'vector walls' => ['walls', 'scene_trace_review_or_walls_unsupported'],
      'invalid wall count' => ['invalid_walls', 'scene_trace_review_or_walls_unsupported'],
      'interior port' => ['interior_port', 'scene_trace_port_not_unique_boundary'],
      'duplicate port' => ['duplicate_port', 'scene_trace_port_not_unique_boundary'],
      'excluded port' => ['port_outside', 'scene_trace_port_invalid'],
      'missing entry' => ['missing_entry', 'scene_trace_entry_review_required'],
    ];
  }

  public function testInducedEmbeddingNeverAddsDiagonalCellEdges(): void {
    $scene = self::scene();
    $scene['source']['source_context']['width'] = 200;
    $review = self::review();
    $review['cells'] = [
      ['x' => 0, 'y' => 0, 'in_footprint' => TRUE, 'terrain_type' => 'dirt'],
      ['x' => 1, 'y' => 0, 'in_footprint' => TRUE, 'terrain_type' => 'void'],
      ['x' => 0, 'y' => 1, 'in_footprint' => TRUE, 'terrain_type' => 'void'],
      ['x' => 1, 'y' => 1, 'in_footprint' => TRUE, 'terrain_type' => 'dirt'],
    ];
    $review['ports'] = [['x' => 0, 'y' => 0, 'side' => 'north', 'kind' => 'entry']];
    $trace = (new ModuleSceneHexTraceService())->trace($scene, $review, self::HASH);
    $floor = array_values(array_filter($trace['hexes'], static fn(array $h): bool => $h['terrain_type'] === 'dirt'));
    foreach ($floor as $a) {
      foreach ($floor as $b) {
        if ($a === $b) {
          continue;
        }
        $neighbor = FALSE;
        foreach (range(0, 5) as $edge) {
          if (RoomPlacementTransformer::neighbor($a, $edge) === ['q' => $b['q'], 'r' => $b['r']]) {
            $neighbor = TRUE;
          }
        }
        if ($a['q'] < 2 && $b['q'] >= 3 || $b['q'] < 2 && $a['q'] >= 3) {
          self::assertFalse($neighbor, 'Diagonal-only floor cells must have no direct hex edge.');
        }
      }
    }
    self::assertCount(8, $trace['hexes']);
  }

  public function testFullSquareGridInducedEdgesMatchExactlyOrthogonalCellGraph(): void {
    $scene = self::scene();
    $scene['source']['source_context']['height'] = 300;
    $review = self::review();
    $review['cells'] = [];
    foreach (range(0, 2) as $y) {
      foreach (range(0, 2) as $x) {
        $review['cells'][] = ['x' => $x, 'y' => $y, 'in_footprint' => TRUE, 'terrain_type' => 'dirt'];
      }
    }
    $review['ports'] = [['x' => 0, 'y' => 0, 'side' => 'north', 'kind' => 'entry']];
    $hexes = (new ModuleSceneHexTraceService())->trace($scene, $review, self::HASH)['hexes'];
    self::assertCount(18, $hexes);
    self::assertCount(18, array_unique(array_map(static fn(array $h): string => $h['q'] . ':' . $h['r'], $hexes)));
    for ($a = 0; $a < 9; $a++) {
      for ($b = $a + 1; $b < 9; $b++) {
        $adjacent = FALSE;
        foreach ([$hexes[2 * $a], $hexes[2 * $a + 1]] as $left) {
          foreach ([$hexes[2 * $b], $hexes[2 * $b + 1]] as $right) {
            foreach (range(0, 5) as $edge) {
              $adjacent = $adjacent || RoomPlacementTransformer::neighbor($left, $edge)
                === ['q' => $right['q'], 'r' => $right['r']];
            }
          }
        }
        $distance = abs($a % 3 - $b % 3) + abs(intdiv($a, 3) - intdiv($b, 3));
        self::assertSame($distance === 1, $adjacent, "Cells $a and $b must preserve square adjacency.");
      }
    }
  }

  public function testReviewedSquareSidesProjectToCanonicalBoundaryEdges(): void {
    $scene = self::scene();
    $scene['source']['source_context']['width'] = 100;
    $scene['source']['source_context']['height'] = 100;
    $review = self::review();
    $review['cells'] = [['x' => 0, 'y' => 0, 'in_footprint' => TRUE, 'terrain_type' => 'dirt']];
    $review['ports'] = [
      ['x' => 0, 'y' => 0, 'side' => 'north', 'kind' => 'entry'],
      ['x' => 0, 'y' => 0, 'side' => 'east', 'kind' => 'exit'],
      ['x' => 0, 'y' => 0, 'side' => 'south', 'kind' => 'exit'],
      ['x' => 0, 'y' => 0, 'side' => 'west', 'kind' => 'exit'],
    ];
    $trace = (new ModuleSceneHexTraceService())->trace($scene, $review, self::HASH);
    self::assertSame(['q' => 0, 'r' => 0], $trace['entry_ports'][0]['hex']);
    self::assertSame(4, $trace['entry_ports'][0]['edge']);
    self::assertSame([5, 1, 2], array_column($trace['exit_ports'], 'edge'));
    self::assertSame([['q' => 1, 'r' => 0], ['q' => 1, 'r' => 0], ['q' => 0, 'r' => 0]], array_column($trace['exit_ports'], 'hex'));
  }

  public function testTwoHexExpansionEnforcesCanonicalTenThousandHexLimit(): void {
    $scene = self::scene();
    $scene['source']['source_context']['width'] = 10000;
    $scene['source']['source_context']['height'] = 5100;
    $review = self::review();
    $review['cells'] = [];
    foreach (range(0, 50) as $y) {
      foreach (range(0, 99) as $x) {
        $review['cells'][] = ['x' => $x, 'y' => $y, 'in_footprint' => TRUE, 'terrain_type' => 'dirt'];
      }
    }
    $this->expectExceptionMessage('scene_trace_hex_limit_exceeded');
    (new ModuleSceneHexTraceService())->trace($scene, $review, self::HASH);
  }

  public function testExactlyTenThousandHexesRemainWithinCanonicalBound(): void {
    $scene = self::scene();
    $scene['source']['source_context']['width'] = 10000;
    $scene['source']['source_context']['height'] = 5000;
    $review = self::review();
    $review['cells'] = [];
    foreach (range(0, 49) as $y) {
      foreach (range(0, 99) as $x) {
        $review['cells'][] = ['x' => $x, 'y' => $y, 'in_footprint' => TRUE, 'terrain_type' => 'dirt'];
      }
    }
    $review['ports'] = [['x' => 0, 'y' => 0, 'side' => 'north', 'kind' => 'entry']];
    self::assertCount(10000, (new ModuleSceneHexTraceService())->trace($scene, $review, self::HASH)['hexes']);
  }

}
