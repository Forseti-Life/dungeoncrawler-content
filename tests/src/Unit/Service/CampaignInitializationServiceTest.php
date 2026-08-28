<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\ConditionInterface;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\dungeoncrawler_content\Service\CampaignInitializationService;
use Drupal\dungeoncrawler_content\Service\CampaignClockService;
use Drupal\dungeoncrawler_content\Service\CampaignNameGeneratorService;
use Drupal\dungeoncrawler_content\Service\NpcSheetGenerationService;
use Drupal\dungeoncrawler_content\Service\QuestGeneratorService;
use Drupal\dungeoncrawler_content\Service\RoomViewImageService;
use Drupal\dungeoncrawler_content\Service\ChatSessionManager;
use Drupal\dungeoncrawler_content\Service\StorylineQuestLifecycleService;
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
    $select = $this->createMock(SelectInterface::class);
    $condition_group = $this->createMock(ConditionInterface::class);
    $result = new class() {
      public function fetchAssoc(): array {
        return [
          'room_id' => 'tavern_entrance',
          'source_room_id' => 'tavern_entrance',
          'name' => 'The Gilded Tankard',
          'description' => 'Eldric keeps watch while Marta the Scholar studies nearby.',
          'environment_tags' => json_encode(['indoor', 'tavern']),
          'layout_data' => json_encode(['shape' => 'seed']),
          'contents_data' => json_encode([
            'items' => [
              ['item_id' => 'wine-1'],
            ],
            'npcs' => [],
          ]),
        ];
      }
    };

    $condition_group->method('condition')->willReturnSelf();
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('orderBy')->willReturnSelf();
    $select->method('range')->willReturnSelf();
    $select->method('orConditionGroup')->willReturn($condition_group);
    $select->method('execute')->willReturn($result);

    $database = $this->createMock(Connection::class);
    $database->method('select')->willReturn($select);

    $module_list = $this->createMock(ModuleExtensionList::class);
    $module_list->method('getPath')->with('dungeoncrawler_content')
      ->willReturn('/path/that/does/not/exist');

    $service = new CampaignInitializationService(
      $database,
      $this->createMock(UuidInterface::class),
      $this->createMock(TimeInterface::class),
      $this->buildLoggerFactory(),
      $module_list,
      $this->createMock(QuestGeneratorService::class),
      $this->createMock(CampaignNameGeneratorService::class),
      $this->createMock(CampaignClockService::class),
      $this->createMock(StorylineQuestLifecycleService::class),
      $this->createMock(ChatSessionManager::class),
      $this->createMock(NpcSheetGenerationService::class),
      $this->createMock(RoomViewImageService::class),
      NULL,
      NULL,
      NULL,
      NULL,
      NULL,
      NULL,
      $this->createMock(ConfigFactoryInterface::class),
    );

    $method = new \ReflectionMethod(CampaignInitializationService::class, 'loadStarterRoomSeed');
    $method->setAccessible(TRUE);
    $room = $method->invoke($service, [
      'source_room_id' => 'tavern_entrance',
      'runtime_room_id' => 'tavern_entrance',
      'room_tags_default' => ['indoor', 'tavern'],
      'theme' => 'classic_dungeon',
    ]);

    $this->assertIsArray($room);
    $this->assertSame('tavern_entrance', $room['room_id']);
    $this->assertSame('tavern_entrance', $room['runtime_room_id']);
    $this->assertSame('The Gilded Tankard', $room['name']);
    $this->assertStringContainsString('Eldric', $room['description']);
    $this->assertStringContainsString('Marta the Scholar', $room['description']);
    $this->assertIsArray($room['contents_data'] ?? NULL);
    $this->assertNotEmpty($room['contents_data']['items'] ?? []);
  }

  /**
   * @covers ::buildStarterRoomIntroMessage
   */
  public function testBuildStarterRoomIntroMessageUsesRoomDescriptionWhenProvided(): void {
    $service = (new \ReflectionClass(CampaignInitializationService::class))
      ->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod(CampaignInitializationService::class, 'buildStarterRoomIntroMessage');
    $method->setAccessible(TRUE);

    $message = $method->invoke(
      $service,
      'The Gilded Tankard',
      'Warm light and low voices fill the tavern.'
    );

    $this->assertStringStartsWith('The Gilded Tankard', $message);
    $this->assertStringContainsString('Warm light and low voices fill the tavern.', $message);
  }

  /**
   * @covers ::buildStarterRoomIntroMessage
   */
  public function testBuildStarterRoomIntroMessageFallsBackToRoomArrivalTextWhenDescriptionMissing(): void {
    $service = (new \ReflectionClass(CampaignInitializationService::class))
      ->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod(CampaignInitializationService::class, 'buildStarterRoomIntroMessage');
    $method->setAccessible(TRUE);

    $message = $method->invoke($service, 'The Gilded Tankard', '');

    $this->assertStringContainsString('You arrive at The Gilded Tankard. The adventure begins...', $message);
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
