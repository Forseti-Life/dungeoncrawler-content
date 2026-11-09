<?php

declare(strict_types=1);

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorCanonicalGenerationPlanService;
use Drupal\dungeoncrawler_content\Service\HexUtilityService;
use Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleSceneHexRoomValidationAdapter;
use Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleSceneHexTraceService;
use Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleSceneGenerationAdapter;
use Drupal\dungeoncrawler_content\Service\MovementResolverService;
use Drupal\dungeoncrawler_content\Service\RoomEditorService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleSceneHexRoomValidationAdapter::class)]
final class ModuleSceneHexRoomValidationAdapterTest extends TestCase {

  private function editor(): RoomEditorService {
    return new RoomEditorService(
      $this->createMock(Connection::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(UuidInterface::class),
      $this->createMock(CanonicalDefinitionService::class),
    );
  }

  private function trace(): array {
    $hash = str_repeat('a', 64);
    return (new ModuleSceneHexTraceService())->trace([
      'resource_id' => 'synthetic-map',
      'resource_type' => 'scene',
      'source' => [
        'source_context' => [
          'width' => 200, 'height' => 100, 'grid_type' => 1,
          'grid_size' => 100, 'grid_distance' => 5, 'grid_units' => 'ft',
        ],
        'counts' => [],
      ],
    ], [
      'archive_sha256' => $hash,
      'scene_resource_id' => 'synthetic-map',
      'cells' => [
        ['x' => 0, 'y' => 0, 'in_footprint' => TRUE, 'terrain_type' => 'dirt'],
        ['x' => 1, 'y' => 0, 'in_footprint' => TRUE, 'terrain_type' => 'stone_floor'],
      ],
      'ports' => [
        ['x' => 0, 'y' => 0, 'side' => 'west', 'kind' => 'entry'],
        ['x' => 1, 'y' => 0, 'side' => 'east', 'kind' => 'exit'],
      ],
    ], $hash);
  }

  private function room(): array {
    return [
      'schema_version' => 'canonical-room-v1',
      'room_id' => 'synthetic_trace_room',
      'name' => 'Project-authored fixture',
      'description' => 'A synthetic room for read-only validation.',
      'room_type' => 'chamber',
      'size_category' => 'small',
      'hexes' => [],
      'terrain' => ['type' => 'dirt'],
      'lighting' => ['level' => 'bright_light', 'light_sources' => []],
      'entry_ports' => [],
      'exit_ports' => [],
      'placements' => [],
      'environmental_effects' => [],
      'gameplay_defaults' => ['safe_for_rest' => FALSE, 'visibility' => 'visible'],
      'metadata' => ['tags' => [], 'provenance' => ['source' => 'reviewed_synthetic_trace']],
    ];
  }

  private function exits(): array {
    return ['exit-2' => [
      'kind' => 'archway',
      'direction' => 'bidirectional',
      'default_state' => 'open',
      'destination_hint' => NULL,
      'linked_placement_id' => NULL,
      'requirements' => [],
    ]];
  }

  private function project(array $trace, array $room, array $exits): array {
    return (new ModuleSceneHexRoomValidationAdapter())->validate(
      $trace,
      $room,
      $exits,
      $this->editor(),
      new ModuleSceneGenerationAdapter(
        $this->createMock(EditorCanonicalGenerationPlanService::class),
        new MovementResolverService(new HexUtilityService()),
      ),
    );
  }

  public function testActualRoomEditorPublicationValidatorAcceptsSyntheticProjectionWithoutWrites(): void {
    $trace = $this->trace();
    $shell = $this->room();
    $exits = $this->exits();
    $projection = $this->project($trace, $shell, $exits);
    self::assertTrue($projection['validation']['valid'], json_encode($projection['validation']['errors']));
    self::assertSame([], $projection['validation']['errors']);
    self::assertSame('room_has_no_placements', $projection['validation']['warnings'][0]['code']);
    self::assertSame($trace['hexes'], $projection['room']['hexes']);
    self::assertSame($trace['entry_ports'], $projection['room']['entry_ports']);
    self::assertSame('archway', $projection['room']['exit_ports'][0]['kind']);
    self::assertFalse($projection['publication_ready']);
    self::assertSame($trace['review_sha256'], $projection['trace_review_sha256']);
    self::assertSame([], $shell['hexes']);
    self::assertSame('archway', $exits['exit-2']['kind']);
  }

  public function testActualValidatorReportsBadCanonicalTerrainAndInteriorPort(): void {
    $trace = $this->trace();
    $trace['hexes'][0]['terrain_type'] = 'unreviewed';
    $trace['exit_ports'][0]['edge'] = 3;
    $projection = $this->project($trace, $this->room(), $this->exits());
    self::assertFalse($projection['validation']['valid']);
    $codes = array_column($projection['validation']['errors'], 'code');
    self::assertContains('terrain_type_invalid', $codes);
    self::assertContains('port_edge_not_boundary', $codes);
  }

  public function testMissingReviewedExitSemanticsAreRejectedBeforeValidation(): void {
    $this->expectExceptionMessage('scene_trace_exit_review_required');
    $this->project($this->trace(), $this->room(), []);
  }

  public function testExistingRoomContentCannotBeOverwrittenByProjection(): void {
    $shell = $this->room();
    $shell['hexes'] = [['q' => 0, 'r' => 0]];
    $this->expectExceptionMessage('scene_trace_room_projection_invalid');
    $this->project($this->trace(), $shell, $this->exits());
  }

  public function testVoidBridgeIsRejectedDespiteGeometricConnectivity(): void {
    $trace = $this->trace();
    $trace['hexes'][2]['terrain_type'] = 'void';
    $trace['hexes'][3]['terrain_type'] = 'void';
    $projection = $this->project($trace, $this->room(), $this->exits());
    self::assertSame([], $this->editor()->validateAggregate($projection['room'], 'publication')['errors']);
    self::assertContains('scene_trace_movement_unreachable', array_column($projection['validation']['errors'], 'code'));
    self::assertFalse($projection['validation']['valid']);
  }

  public function testDiagonalOnlyFloorsSeparatedByVoidFailTraversal(): void {
    $trace = $this->trace();
    $trace['hexes'] = [
      ['q' => 0, 'r' => 0, 'terrain_type' => 'dirt', 'elevation_ft' => 0],
      ['q' => 1, 'r' => 0, 'terrain_type' => 'dirt', 'elevation_ft' => 0],
      ['q' => 2, 'r' => -1, 'terrain_type' => 'void', 'elevation_ft' => 0],
      ['q' => 3, 'r' => -1, 'terrain_type' => 'void', 'elevation_ft' => 0],
      ['q' => 1, 'r' => 1, 'terrain_type' => 'void', 'elevation_ft' => 0],
      ['q' => 2, 'r' => 1, 'terrain_type' => 'void', 'elevation_ft' => 0],
      ['q' => 3, 'r' => 0, 'terrain_type' => 'dirt', 'elevation_ft' => 0],
      ['q' => 4, 'r' => 0, 'terrain_type' => 'dirt', 'elevation_ft' => 0],
    ];
    $trace['exit_ports'][0]['hex'] = ['q' => 4, 'r' => 0];
    $projection = $this->project($trace, $this->room(), $this->exits());
    self::assertFalse($projection['validation']['valid']);
    self::assertContains('scene_trace_movement_unreachable', array_column($projection['validation']['errors'], 'code'));
  }

  public function testDeepWaterAndLavaRequireSeparateLandMovementReview(): void {
    foreach (['water_deep', 'lava'] as $terrain) {
      $trace = $this->trace();
      $trace['hexes'][0]['terrain_type'] = $terrain;
      $projection = $this->project($trace, $this->room(), $this->exits());
      self::assertContains('scene_trace_land_traversal_unverified', array_column($projection['validation']['errors'], 'code'));
      self::assertFalse($projection['validation']['valid']);
    }
  }

}
