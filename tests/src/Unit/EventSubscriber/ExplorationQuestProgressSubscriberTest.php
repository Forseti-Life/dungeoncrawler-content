<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Event\RoomDiscoveredEvent;
use Drupal\dungeoncrawler_content\EventSubscriber\ExplorationQuestProgressSubscriber;
use Drupal\dungeoncrawler_content\Service\QuestTrackerService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Covers nested exploration objective matching.
 *
 * @group dungeoncrawler_content
 * @group quest
 */
class ExplorationQuestProgressSubscriberTest extends UnitTestCase {

  /**
   * Verifies nested discovery objectives match the actual discovered location.
   */
  public function testFindMatchingDiscoveryObjectiveIdsMatchesNestedObjectiveByLocation(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $subscriber = new class(
      $this->createMock(QuestTrackerService::class),
      $this->createMock(Connection::class),
      $logger_factory
    ) extends ExplorationQuestProgressSubscriber {
      public function exposedFindMatchingDiscoveryObjectiveIds(array $states, int $phase, RoomDiscoveredEvent $event): array {
        return $this->findMatchingDiscoveryObjectiveIds($states, $phase, $event);
      }
    };

    $matches = $subscriber->exposedFindMatchingDiscoveryObjectiveIds([
      [
        'phase' => 1,
        'objectives' => [
          [
            'objective_id' => 'escort_parent',
            'type' => 'escort',
            'completed' => FALSE,
            'children' => [
              [
                'objective_id' => 'reach_safehouse',
                'type' => 'explore',
                'location' => 'safehouse',
                'completed' => FALSE,
              ],
            ],
          ],
          [
            'objective_id' => 'wrong_room',
            'type' => 'explore',
            'location' => 'watchtower',
            'completed' => FALSE,
          ],
        ],
      ],
    ], 1, new RoomDiscoveredEvent(85, 'safehouse', 'road', 'Safehouse', 'A secure room.'));

    $this->assertSame(['reach_safehouse'], $matches);
  }

  /**
   * Verifies investigate objectives can resolve from the same room-discovery flow.
   */
  public function testFindMatchingDiscoveryObjectiveIdsMatchesInvestigateTarget(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $subscriber = new class(
      $this->createMock(QuestTrackerService::class),
      $this->createMock(Connection::class),
      $logger_factory
    ) extends ExplorationQuestProgressSubscriber {
      public function exposedFindMatchingDiscoveryObjectiveIds(array $states, int $phase, RoomDiscoveredEvent $event): array {
        return $this->findMatchingDiscoveryObjectiveIds($states, $phase, $event);
      }
    };

    $matches = $subscriber->exposedFindMatchingDiscoveryObjectiveIds([
      [
        'phase' => 1,
        'objectives' => [
          [
            'objective_id' => 'investigate_sanctum',
            'type' => 'investigate',
            'target' => 'sanctum',
            'completed' => FALSE,
            'current' => 0,
            'target_count' => 1,
          ],
        ],
      ],
    ], 1, new RoomDiscoveredEvent(85, 'sanctum', 'ruins', 'Sanctum', 'A hidden sanctum.'));

    $this->assertSame(['investigate_sanctum'], $matches);
  }

}
