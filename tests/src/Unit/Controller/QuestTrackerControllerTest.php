<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Controller\QuestTrackerController;
use Drupal\dungeoncrawler_content\Service\QuestGeneratorService;
use Drupal\dungeoncrawler_content\Service\QuestTrackerService;
use Drupal\dungeoncrawler_content\Service\RoomChatService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Controller\QuestTrackerController
 */
class QuestTrackerControllerTest extends UnitTestCase {

  /**
   * @covers ::getAvailableQuests
   */
  public function testGetAvailableQuestsUsesDiscoveryAwareLocationFilter(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $available = [[
      'quest_id' => 'library_mystery_65_abc',
      'quest_name' => 'Library Mystery',
      'status' => 'available',
      'location_id' => 'grandmas_house_library',
    ]];

    $quest_tracker = $this->createMock(QuestTrackerService::class);
    $quest_tracker->expects($this->once())
      ->method('getAvailableQuests')
      ->with(65, 'grandmas_house_library', 12)
      ->willReturn($available);

    $quest_generator = $this->createMock(QuestGeneratorService::class);
    $quest_generator->expects($this->once())
      ->method('buildQuestSummaryPayload')
      ->with('grandmas_house_library', [], $available, 65)
      ->willReturn([
        'schema_version' => 'quest-summary-v1',
        'location_id' => 'grandmas_house_library',
        'active' => [],
        'available' => $available,
        'management_tree' => [],
        'counts' => ['active' => 0, 'available' => 1],
      ]);

    $controller = new QuestTrackerController(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(RoomChatService::class),
      $quest_generator,
      $quest_tracker
    );

    $response = $controller->getAvailableQuests(65, Request::create(
      '/api/campaign/65/quests/available',
      'GET',
      ['location_id' => 'grandmas_house_library', 'character_id' => 12]
    ));
    $data = json_decode($response->getContent(), TRUE);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertTrue($data['success']);
    $this->assertSame(1, $data['count']);
    $this->assertSame('library_mystery_65_abc', $data['quests'][0]['quest_id']);
  }

  /**
   * @covers ::getAvailableQuests
   */
  public function testGetAvailableQuestsReturnsEmptyWhenLocationIsUnknown(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $quest_tracker = $this->createMock(QuestTrackerService::class);
    $quest_tracker->expects($this->never())
      ->method('getAvailableQuests');

    $quest_generator = $this->createMock(QuestGeneratorService::class);
    $quest_generator->expects($this->once())
      ->method('buildQuestSummaryPayload')
      ->with('campaign-65', [], [], 65)
      ->willReturn([
        'schema_version' => 'quest-summary-v1',
        'location_id' => 'campaign-65',
        'active' => [],
        'available' => [],
        'management_tree' => [],
        'counts' => ['active' => 0, 'available' => 0],
      ]);

    $controller = new QuestTrackerController(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(RoomChatService::class),
      $quest_generator,
      $quest_tracker
    );

    $response = $controller->getAvailableQuests(65, Request::create(
      '/api/campaign/65/quests/available',
      'GET'
    ));
    $data = json_decode($response->getContent(), TRUE);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertTrue($data['success']);
    $this->assertSame(0, $data['count']);
    $this->assertSame([], $data['quests']);
  }

}
