<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\dungeoncrawler_content\Controller\HexMapController;
use Drupal\dungeoncrawler_content\Service\CampaignCharacterRuntimeResolverService;
use Drupal\dungeoncrawler_content\Service\CampaignCharacterRuntimeSyncService;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\CharacterStateService;
use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;
use Drupal\dungeoncrawler_content\Service\MapVisualStateProjector;
use Drupal\dungeoncrawler_content\Service\NavigationRuntimeService;
use Drupal\dungeoncrawler_content\Service\NavigationService;
use Drupal\dungeoncrawler_content\Service\QuestGeneratorService;
use Drupal\dungeoncrawler_content\Service\QuestTrackerService;
use Drupal\dungeoncrawler_content\Service\RelationshipManagerService;
use Drupal\dungeoncrawler_content\Service\RoomChatService;
use Drupal\dungeoncrawler_content\Service\StateValidationService;
use Drupal\dungeoncrawler_content\Service\StorylineManagerService;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests HexMap NPC psychology bootstrap ingress.
 *
 * @group dungeoncrawler_content
 * @group hexmap
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Controller\HexMapController
 */
class HexMapControllerPsychologyBootstrapTest extends UnitTestCase {

  protected function tearDown(): void {
    \Drupal::setContainer(new ContainerBuilder());
    parent::tearDown();
  }

  private function buildController(): TestableHexMapController {
    return new TestableHexMapController(
      $this->createMock(RequestStack::class),
      $this->createMock(Connection::class),
      $this->createMock(CampaignCharacterRuntimeResolverService::class),
      $this->createMock(CampaignCharacterRuntimeSyncService::class),
      $this->createMock(QuestTrackerService::class),
      $this->createMock(QuestGeneratorService::class),
      $this->createMock(GeneratedImageRepository::class),
      $this->createMock(MapVisualStateProjector::class),
      $this->createMock(NavigationRuntimeService::class),
      $this->createMock(NavigationService::class),
      $this->createMock(StorylineManagerService::class),
      $this->createMock(RelationshipManagerService::class),
      $this->createMock(StateValidationService::class),
      $this->createMock(CharacterManager::class),
      $this->createMock(CharacterStateService::class),
    );
  }

  private function buildLoggerFactory(): LoggerChannelFactoryInterface {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);
    return $logger_factory;
  }

  /**
   * @covers ::ensureRoomNpcPsychologyProfiles
   */
  public function testEnsureRoomNpcPsychologyProfilesBootstrapsRoomEntitiesOnly(): void {
    $room_chat_service = $this->getMockBuilder(RoomChatService::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['ensureNpcProfiles'])
      ->getMock();
    $room_chat_service->expects($this->once())
      ->method('ensureNpcProfiles')
      ->with(77, $this->callback(static function (array $room_entities): bool {
        if (count($room_entities) !== 2) {
          return FALSE;
        }
        foreach ($room_entities as $entity) {
          if (($entity['placement']['room_id'] ?? '') !== 'room-1') {
            return FALSE;
          }
        }
        return TRUE;
      }))
      ->willReturn(2);

    $container = new ContainerBuilder();
    $container->set('dungeoncrawler_content.room_chat_service', $room_chat_service);
    $container->set('logger.factory', $this->buildLoggerFactory());
    \Drupal::setContainer($container);

    $controller = $this->buildController();
    $controller->publicEnsureRoomNpcPsychologyProfiles(
      [
        'entities' => [
          ['entity_instance_id' => 'npc-1', 'placement' => ['room_id' => 'room-1']],
          ['entity_instance_id' => 'npc-2', 'placement' => ['room_id' => 'room-1']],
          ['entity_instance_id' => 'npc-3', 'placement' => ['room_id' => 'room-2']],
        ],
      ],
      [
        'campaign_id' => 77,
        'room_id' => 'room-1',
      ]
    );
  }

  /**
   * @covers ::ensureRoomNpcPsychologyProfiles
   */
  public function testEnsureRoomNpcPsychologyProfilesSkipsWhenContextMissing(): void {
    $room_chat_service = $this->getMockBuilder(RoomChatService::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['ensureNpcProfiles'])
      ->getMock();
    $room_chat_service->expects($this->never())
      ->method('ensureNpcProfiles');

    $container = new ContainerBuilder();
    $container->set('dungeoncrawler_content.room_chat_service', $room_chat_service);
    $container->set('logger.factory', $this->buildLoggerFactory());
    \Drupal::setContainer($container);

    $controller = $this->buildController();
    $controller->publicEnsureRoomNpcPsychologyProfiles(
      [
        'entities' => [
          ['entity_instance_id' => 'npc-1', 'placement' => ['room_id' => 'room-1']],
        ],
      ],
      [
        'campaign_id' => 0,
        'room_id' => '',
      ]
    );
  }

}

/**
 * Test wrapper for exposing protected HexMapController helpers.
 */
class TestableHexMapController extends HexMapController {

  public function publicEnsureRoomNpcPsychologyProfiles(array $dungeon_payload, array $launch_context): void {
    $this->ensureRoomNpcPsychologyProfiles($dungeon_payload, $launch_context);
  }

}
