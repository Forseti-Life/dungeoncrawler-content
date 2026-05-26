<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Event\EntityDefeatedEvent;
use Drupal\dungeoncrawler_content\Event\RoomDiscoveredEvent;
use Drupal\dungeoncrawler_content\EventSubscriber\EscortQuestProgressSubscriber;
use Drupal\dungeoncrawler_content\Service\QuestTrackerService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Covers escort arrival and survival matching.
 *
 * @group dungeoncrawler_content
 * @group quest
 */
class EscortQuestProgressSubscriberTest extends UnitTestCase {

  /**
   * Verifies escort destination matching uses the configured destination.
   */
  public function testFindMatchingEscortObjectiveIdsMatchesDestination(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $subscriber = new class(
      $this->createMock(QuestTrackerService::class),
      $this->createMock(Connection::class),
      $logger_factory
    ) extends EscortQuestProgressSubscriber {
      public function exposedFindMatchingEscortObjectiveIds(array $states, int $phase, RoomDiscoveredEvent $event, bool $arrival_only = FALSE): array {
        return $this->findMatchingEscortObjectiveIds($states, $phase, $event, $arrival_only);
      }

      public function exposedEscortObjectiveMatchesDefeatedEntity(array $states, int $phase, EntityDefeatedEvent $event): bool {
        return $this->escortObjectiveMatchesDefeatedEntity($states, $phase, $event);
      }
    };

    $matches = $subscriber->exposedFindMatchingEscortObjectiveIds([
      [
        'phase' => 2,
        'objectives' => [
          [
            'objective_id' => 'escort_to_safety',
            'type' => 'escort',
            'destination' => 'safehouse',
            'target' => 'Marta',
            'npc_ref' => 'marta',
            'completed' => FALSE,
          ],
        ],
      ],
    ], 2, new RoomDiscoveredEvent(85, 'safehouse', 'road', 'Safehouse', 'A secure room.'));

    $this->assertSame(['escort_to_safety'], $matches);
  }

  /**
   * Verifies room discovery does not auto-complete escort encounter steps.
   */
  public function testFindMatchingEscortObjectiveIdsDoesNotAutoCompleteEncounterStep(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $subscriber = new class(
      $this->createMock(QuestTrackerService::class),
      $this->createMock(Connection::class),
      $logger_factory
    ) extends EscortQuestProgressSubscriber {
      public function exposedFindMatchingEscortObjectiveIds(array $states, int $phase, RoomDiscoveredEvent $event, bool $arrival_only = FALSE): array {
        return $this->findMatchingEscortObjectiveIds($states, $phase, $event, $arrival_only);
      }
    };

    $matches = $subscriber->exposedFindMatchingEscortObjectiveIds([
      [
        'phase' => 2,
        'objectives' => [
          [
            'objective_id' => 'escort_to_safety',
            'type' => 'escort',
            'destination' => 'safehouse',
            'completed' => FALSE,
            'revealed' => TRUE,
            'children' => [
              [
                'objective_id' => 'escort_to_safety_runtime_1',
                'type' => 'interact',
                'encounter_id' => 'escort_to_safety_path_encounter_1',
                'completed' => FALSE,
                'revealed' => TRUE,
              ],
              [
                'objective_id' => 'escort_to_safety_arrive',
                'type' => 'explore',
                'escort_arrival' => TRUE,
                'completed' => FALSE,
                'revealed' => FALSE,
              ],
            ],
          ],
        ],
      ],
    ], 2, new RoomDiscoveredEvent(85, 'crossroads', 'road:crossroads', 'Crossroads', 'A fork in the road.'));

    $this->assertSame([], $matches);
  }

  /**
   * Verifies destination arrival can resolve the revealed arrival step.
   */
  public function testFindMatchingEscortObjectiveIdsMatchesArrivalStepAtDestination(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $subscriber = new class(
      $this->createMock(QuestTrackerService::class),
      $this->createMock(Connection::class),
      $logger_factory
    ) extends EscortQuestProgressSubscriber {
      public function exposedFindMatchingEscortObjectiveIds(array $states, int $phase, RoomDiscoveredEvent $event, bool $arrival_only = FALSE): array {
        return $this->findMatchingEscortObjectiveIds($states, $phase, $event, $arrival_only);
      }
    };

    $matches = $subscriber->exposedFindMatchingEscortObjectiveIds([
      [
        'phase' => 2,
        'objectives' => [
          [
            'objective_id' => 'escort_to_safety',
            'type' => 'escort',
            'destination' => 'safehouse',
            'completed' => FALSE,
            'revealed' => TRUE,
            'children' => [
              [
                'objective_id' => 'escort_to_safety_runtime_1',
                'type' => 'interact',
                'encounter_id' => 'escort_to_safety_path_encounter_1',
                'completed' => TRUE,
                'revealed' => TRUE,
              ],
              [
                'objective_id' => 'escort_to_safety_arrive',
                'type' => 'explore',
                'escort_arrival' => TRUE,
                'completed' => FALSE,
                'revealed' => TRUE,
              ],
            ],
          ],
        ],
      ],
    ], 2, new RoomDiscoveredEvent(85, 'safehouse', 'road:safehouse', 'Safehouse', 'A secure room.'), TRUE);

    $this->assertSame(['escort_to_safety_arrive'], $matches);
  }

  /**
   * Verifies escorted NPC defeats can be matched for failure handling.
   */
  public function testEscortObjectiveMatchesDefeatedEntityByNpcReference(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $subscriber = new class(
      $this->createMock(QuestTrackerService::class),
      $this->createMock(Connection::class),
      $logger_factory
    ) extends EscortQuestProgressSubscriber {
      public function exposedEscortObjectiveMatchesDefeatedEntity(array $states, int $phase, EntityDefeatedEvent $event): bool {
        return $this->escortObjectiveMatchesDefeatedEntity($states, $phase, $event);
      }
    };

    $matched = $subscriber->exposedEscortObjectiveMatchesDefeatedEntity([
      [
        'phase' => 2,
        'objectives' => [
          [
            'objective_id' => 'escort_to_safety',
            'type' => 'escort',
            'destination' => 'safehouse',
            'target' => 'Marta',
            'npc_ref' => 'npc_marta',
            'completed' => FALSE,
          ],
        ],
      ],
    ], 2, new EntityDefeatedEvent(85, 9, 321, [
      'name' => 'Marta',
      'team' => 'npc',
      'entity_ref' => 'npc_marta',
      'hp' => 0,
    ], 99, 8));

    $this->assertTrue($matched);
  }

}
