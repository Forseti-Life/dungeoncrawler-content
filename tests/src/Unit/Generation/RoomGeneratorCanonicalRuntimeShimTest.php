<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Generation;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorCanonicalGenerationPlanService;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalRoomProjectionService;
use Drupal\dungeoncrawler_content\Service\Generation\RuntimeCanonicalRoomService;
use Drupal\dungeoncrawler_content\Service\Generation\RuntimeCanonicalContentResolver;
use Drupal\dungeoncrawler_content\Service\Generation\RuntimeGenerationException;
use Drupal\dungeoncrawler_content\Service\RoomGeneratorService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\NullLogger;

/**
 * Tests R3 RoomGeneratorService canonical-runtime shim behavior.
 *
 * @group dungeoncrawler_content
 */
final class RoomGeneratorCanonicalRuntimeShimTest extends UnitTestCase {

  public function testGenerateRoomSelectsPublishedCanonicalRoomWithVersionProvenance(): void {
    $service = new RuntimeCanonicalRoomService($this->resolver(), new CanonicalRoomProjectionService());

    $room = $service->generateRoom([
      'campaign_id' => 11,
      'dungeon_id' => 'runtime-dungeon',
      'level_id' => 'level-1',
      'room_index' => 2,
      'theme' => 'sewer',
      'room_type' => 'chamber',
      'terrain_type' => 'stone_floor',
      'room_size' => 'medium',
      'defer_room_persistence' => TRUE,
      'seed' => 42,
    ]);

    $this->assertSame('room_runtime-dungeon_level-1_2', $room['room_id']);
    $this->assertSame('canonical-room-a', $room['source_room_id']);
    $this->assertSame('version-a', $room['source_room_version_id']);
    $this->assertSame('version-a', $room['room_version_id']);
    $this->assertTrue($room['from_canonical_runtime']);
    $this->assertSame('runtime_canonical_content_resolver', $room['metadata']['campaign_source']['source']);
  }

  public function testMissingCanonicalRuntimeServicesHardFailWithoutLegacyFallback(): void {
    $service = new class($this->configFactory(TRUE)) extends RoomGeneratorService {
      public function __construct(ConfigFactoryInterface $config_factory) {
        $this->logger = new NullLogger();
        $this->configFactory = $config_factory;
        $this->runtimeCanonicalRoom = NULL;
      }
    };

    $this->expectException(RuntimeGenerationException::class);
    $this->expectExceptionMessage('runtime_selection_failed');
    $service->generateRoom([
      'campaign_id' => 11,
      'dungeon_id' => 'runtime-dungeon',
      'level_id' => 'level-1',
      'room_index' => 2,
    ]);
  }

  public function testHotPathSelectionDoesNotCallGenerationAdapter(): void {
    $generation_adapter = new class extends EditorCanonicalGenerationPlanService {
      public function __construct() {}

      public function generateRoomLayout(array $arguments, \Drupal\dungeoncrawler_content\Service\EditorGm\RoomEditorGmToolContext $context): array {
        throw new \RuntimeException('generation adapter must not be called on hot path');
      }
    };
    $service = new RuntimeCanonicalRoomService(
      $this->resolver(),
      new CanonicalRoomProjectionService(),
      $generation_adapter
    );

    $started = microtime(TRUE);
    $room = $service->generateRoom([
      'campaign_id' => 11,
      'dungeon_id' => 'runtime-dungeon',
      'level_id' => 'level-1',
      'room_index' => 2,
      'theme' => 'sewer',
      'room_type' => 'chamber',
      'terrain_type' => 'stone_floor',
      'room_size' => 'medium',
      'defer_room_persistence' => TRUE,
      'seed' => 42,
    ]);
    $elapsed_ms = (microtime(TRUE) - $started) * 1000;

    $this->assertSame('version-a', $room['source_room_version_id']);
    $this->assertLessThan(2000, $elapsed_ms, 'Navigation hot path must stay within the selection/projection latency budget.');
  }

  private function resolver(): RuntimeCanonicalContentResolver {
    return new class extends RuntimeCanonicalContentResolver {
      public function __construct() {}

      public function selectPublishedRoom(array $criteria): array {
        return [
          'room_version_id' => 'version-a',
          'room_payload' => [
            'room_id' => 'canonical-room-a',
            'name' => 'Canonical Sewer Room',
            'description' => 'A published canonical room.',
            'room_type' => 'chamber',
            'size_category' => 'medium',
            'hexes' => [['q' => 0, 'r' => 0, 'terrain_type' => 'stone_floor']],
            'terrain' => ['type' => 'stone_floor'],
            'lighting' => ['level' => 'dim_light'],
            'entry_ports' => [['port_id' => 'entry-1', 'hex' => ['q' => 0, 'r' => 0], 'edge' => 3]],
            'exit_ports' => [['port_id' => 'exit-1', 'hex' => ['q' => 0, 'r' => 0], 'edge' => 0]],
            'placements' => [],
            'metadata' => ['tags' => ['sewer']],
          ],
          'room_id' => 'canonical-room-a',
          'version' => '1.0.0',
          'row' => ['room_id' => 'canonical-room-a', 'version_id' => 'version-a', 'version' => '1.0.0'],
          'selection' => ['criteria' => $criteria, 'score' => 10, 'matched_tags' => ['sewer']],
        ];
      }
    };
  }

  private function configFactory(bool $enabled): ConfigFactoryInterface {
    $config = $this->createMock(Config::class);
    $config->method('get')
      ->with('canonical_runtime_generation.r7')
      ->willReturn($enabled);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')
      ->with('dungeoncrawler_content.settings')
      ->willReturn($config);
    return $factory;
  }

}
