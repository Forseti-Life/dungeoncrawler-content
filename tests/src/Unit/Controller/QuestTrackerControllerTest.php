<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Controller\QuestTrackerController;
use Drupal\dungeoncrawler_content\Service\QuestConfirmationService;
use Drupal\dungeoncrawler_content\Service\QuestGeneratorService;
use Drupal\dungeoncrawler_content\Service\QuestTouchpointService;
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

    $offers = [[
      'quest_id' => 'library_mystery_65_abc',
      'quest_name' => 'Library Mystery',
      'status' => 'offered',
      'location_id' => 'grandmas_house_library',
    ]];
    $leads = [[
      'quest_id' => 'missing_teacher_65_xyz',
      'quest_name' => 'Find the Missing Teacher',
      'status' => 'lead',
      'location_id' => 'grandmas_house_library',
    ]];

    $quest_tracker = $this->createMock(QuestTrackerService::class);
    $quest_tracker->expects($this->once())
      ->method('getOfferQuests')
      ->with(65, 'grandmas_house_library', 12)
      ->willReturn($offers);
    $quest_tracker->expects($this->once())
      ->method('getLeadQuests')
      ->with(65, 'grandmas_house_library', 12)
      ->willReturn($leads);

    $quest_generator = $this->createMock(QuestGeneratorService::class);
    $quest_generator->expects($this->once())
      ->method('buildQuestSummaryPayload')
      ->with('grandmas_house_library', [], $offers, $leads, 65)
      ->willReturn([
        'schema_version' => 'quest-summary-v2',
        'location_id' => 'grandmas_house_library',
        'active' => [],
        'offers' => $offers,
        'leads' => $leads,
        'management_tree' => [],
        'counts' => ['active' => 0, 'offers' => 1, 'leads' => 1],
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
    $this->assertSame(2, $data['count']);
    $this->assertSame('library_mystery_65_abc', $data['quests'][0]['quest_id']);
    $this->assertSame('quest-summary-v2', $data['quest_summary']['schema_version']);
    $this->assertSame(1, $data['quest_summary']['counts']['offers']);
    $this->assertSame(1, $data['quest_summary']['counts']['leads']);
    $this->assertArrayNotHasKey('available', $data['quest_summary']);
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
      ->method('getOfferQuests');
    $quest_tracker->expects($this->never())
      ->method('getLeadQuests');

    $quest_generator = $this->createMock(QuestGeneratorService::class);
    $quest_generator->expects($this->once())
      ->method('buildQuestSummaryPayload')
      ->with('campaign-65', [], [], [], 65)
      ->willReturn([
        'schema_version' => 'quest-summary-v2',
        'location_id' => 'campaign-65',
        'active' => [],
        'offers' => [],
        'leads' => [],
        'management_tree' => [],
        'counts' => ['active' => 0, 'offers' => 0, 'leads' => 0],
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
    $this->assertSame('quest-summary-v2', $data['quest_summary']['schema_version']);
    $this->assertArrayNotHasKey('available', $data['quest_summary']);
  }

  /**
   * @covers ::resolveTouchpointConfirmation
   */
  public function testResolveTouchpointConfirmationAppliesSingleCandidateAsTypedReceipt(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $confirmation_service = $this->createMock(QuestConfirmationService::class);
    $confirmation_service->expects($this->once())
      ->method('get')
      ->with('qcf_123')
      ->willReturn([
        'confirmation_id' => 'qcf_123',
        'campaign_id' => 85,
        'status' => 'pending',
      ]);
    $confirmation_service->expects($this->once())
      ->method('resolve')
      ->with('qcf_123', 'approved', NULL, 'gm')
      ->willReturn([
        'confirmation_id' => 'qcf_123',
        'campaign_id' => 85,
        'status' => 'approved',
        'touchpoint_event' => [
          'character_id' => 99,
          'touchpoint' => [
            'objective_type' => 'interact',
            'room_id' => 'crossroads',
          ],
        ],
        'candidates' => [
          ['objective_id' => 'escort_to_safety_runtime_1'],
        ],
      ]);

    $touchpoint_service = $this->createMock(QuestTouchpointService::class);
    $touchpoint_service->expects($this->once())
      ->method('ingestEvent')
      ->with(85, $this->callback(static function (array $payload): bool {
        return ($payload['touchpoint']['objective_id'] ?? '') === 'escort_to_safety_runtime_1'
          && ($payload['touchpoint']['matching_mode'] ?? '') === 'typed_receipt';
      }))
      ->willReturn([
        'success' => TRUE,
        'decision' => 'APPLY_PROGRESS',
      ]);

    $container = new ContainerBuilder();
    $container->set('dungeoncrawler_content.quest_confirmation', $confirmation_service);
    $container->set('dungeoncrawler_content.quest_touchpoint', $touchpoint_service);
    \Drupal::setContainer($container);

    $controller = new QuestTrackerController(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(RoomChatService::class),
      $this->createMock(QuestGeneratorService::class),
      $this->createMock(QuestTrackerService::class)
    );

    $response = $controller->resolveTouchpointConfirmation(85, 'qcf_123', Request::create(
      '/api/campaign/85/quest-confirmations/qcf_123/resolve',
      'POST',
      [],
      [],
      [],
      [],
      json_encode([
        'resolution' => 'approved',
        'resolved_by' => 'gm',
      ])
    ));
    $data = json_decode($response->getContent(), TRUE);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertTrue($data['success']);
    $this->assertSame('APPLY_PROGRESS', $data['apply_result']['decision']);
  }

}
