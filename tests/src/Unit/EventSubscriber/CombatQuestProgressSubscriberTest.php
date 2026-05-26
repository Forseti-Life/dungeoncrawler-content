<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\EventSubscriber\CombatQuestProgressSubscriber;
use Drupal\dungeoncrawler_content\Service\QuestTrackerService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Covers nested kill-objective matching.
 *
 * @group dungeoncrawler_content
 * @group quest
 */
class CombatQuestProgressSubscriberTest extends UnitTestCase {

  /**
   * Verifies kill updates match the specific enemy target in nested objectives.
   */
  public function testFindMatchingKillObjectiveIdsMatchesNestedTarget(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $subscriber = new class(
      $this->createMock(QuestTrackerService::class),
      $this->createMock(Connection::class),
      $logger_factory
    ) extends CombatQuestProgressSubscriber {
      public function exposedFindMatchingKillObjectiveIds(array $states, int $phase, string $enemy_name, ?string $entity_ref): array {
        return $this->findMatchingKillObjectiveIds($states, $phase, $enemy_name, $entity_ref);
      }
    };

    $matches = $subscriber->exposedFindMatchingKillObjectiveIds([
      [
        'phase' => 2,
        'objectives' => [
          [
            'objective_id' => 'escort_parent',
            'type' => 'escort',
            'completed' => FALSE,
            'children' => [
              [
                'objective_id' => 'ambush_attackers',
                'type' => 'kill',
                'target' => 'ambush_bandit',
                'completed' => FALSE,
                'current' => 0,
                'target_count' => 1,
              ],
            ],
          ],
          [
            'objective_id' => 'wrong_enemy',
            'type' => 'kill',
            'target' => 'goblin',
            'completed' => FALSE,
            'current' => 0,
            'target_count' => 1,
          ],
        ],
      ],
    ], 2, 'Ambush Bandit', 'ambush_bandit');

    $this->assertSame(['ambush_attackers'], $matches);
  }

}
