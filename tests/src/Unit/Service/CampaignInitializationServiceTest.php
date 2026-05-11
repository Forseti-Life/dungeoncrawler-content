<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\dungeoncrawler_content\Service\CampaignInitializationService;
use Drupal\dungeoncrawler_content\Service\CampaignNameGeneratorService;
use Drupal\dungeoncrawler_content\Service\NpcSheetGenerationService;
use Drupal\dungeoncrawler_content\Service\QuestGeneratorService;
use Drupal\dungeoncrawler_content\Service\RoomViewImageService;
use Drupal\dungeoncrawler_content\Service\ChatSessionManager;
use Drupal\Tests\UnitTestCase;

/**
 * Tests starter tavern seed alignment for campaign initialization.
 *
 * @group dungeoncrawler_content
 * @group campaign_init
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\CampaignInitializationService
 */
class CampaignInitializationServiceTest extends UnitTestCase {

  /**
   * @covers ::loadStarterRoomSeed
   */
  public function testLoadStarterRoomSeedUsesCanonicalGildedTankardMetadata(): void {
    $service = new CampaignInitializationService(
      $this->createMock(Connection::class),
      $this->createMock(UuidInterface::class),
      $this->createMock(TimeInterface::class),
      $this->buildLoggerFactory(),
      $this->createMock(ModuleExtensionList::class),
      $this->createMock(QuestGeneratorService::class),
      $this->createMock(CampaignNameGeneratorService::class),
      $this->createMock(ChatSessionManager::class),
      $this->createMock(NpcSheetGenerationService::class),
      $this->createMock(RoomViewImageService::class),
    );

    $method = new \ReflectionMethod(CampaignInitializationService::class, 'loadStarterRoomSeed');
    $method->setAccessible(TRUE);
    $room = $method->invoke($service);

    $this->assertIsArray($room);
    $this->assertSame('tavern_entrance', $room['room_id']);
    $this->assertSame('7f2f1051-5f88-45a2-a66a-0f7063900001', $room['runtime_room_id']);
    $this->assertSame('The Gilded Tankard', $room['name']);
    $this->assertStringContainsString('Eldric', $room['description']);
    $this->assertStringContainsString('Marta the Scholar', $room['description']);
    $this->assertIsArray($room['contents_data'] ?? NULL);
    $this->assertNotEmpty($room['contents_data']['items'] ?? []);
  }

  /**
   * Builds a logger factory mock returning a channel mock.
   */
  private function buildLoggerFactory(): LoggerChannelFactoryInterface {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($logger);
    return $factory;
  }

}
