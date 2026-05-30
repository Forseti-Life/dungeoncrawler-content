<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\RoomGeneratorService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\NullLogger;

/**
 * Tests focused room-generator contract helpers.
 *
 * @group dungeoncrawler_content
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\RoomGeneratorService
 */
class RoomGeneratorServiceContractTest extends UnitTestCase {

  /**
   * Creates a testable room generator service.
   */
  protected function createService(): RoomGeneratorService {
    return new class extends RoomGeneratorService {
      public function __construct() {
        $this->logger = new NullLogger();
      }

      public function callBuildRoomId(array $context): string {
        return $this->buildRoomId($context);
      }
    };
  }

  /**
   * @covers ::buildRoomId
   */
  public function testBuildRoomIdIsStableAndUrlSafe(): void {
    $service = $this->createService();

    $context = [
      'dungeon_id' => 12,
      'level_id' => 3,
      'room_index' => 7,
    ];

    $this->assertSame('room_12_3_7', $service->callBuildRoomId($context));
    $this->assertSame('room_12_3_7', $service->callBuildRoomId($context));
    $this->assertMatchesRegularExpression('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $service->callBuildRoomId($context));
  }

  /**
   * @covers ::buildRoomId
   */
  public function testBuildRoomIdPreservesStableStringDungeonIdentifiers(): void {
    $service = $this->createService();

    $context = [
      'dungeon_id' => 'dungeon_12_4_9',
      'level_id' => 3,
      'room_index' => 7,
    ];

    $this->assertSame('room_dungeon_12_4_9_3_7', $service->callBuildRoomId($context));
  }

  /**
   * @covers ::buildRoomId
   */
  public function testBuildRoomIdNormalizesMissingContextToStableFallback(): void {
    $service = $this->createService();

    $this->assertSame('room_0_0_0', $service->callBuildRoomId([]));
  }

}
