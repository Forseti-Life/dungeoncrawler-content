<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\QuestTrackerService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Covers nested objective tracking behavior.
 *
 * @group dungeoncrawler_content
 * @group quest
 */
class QuestTrackerServiceTest extends UnitTestCase {

  /**
   * Verifies nested child objectives drive parent completion and phase progress.
   */
  public function testNestedObjectivesCompleteParentWhenChildrenFinish(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $service = new class(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(TimeInterface::class)
    ) extends QuestTrackerService {
      public function initializeStates(array $objectives): array {
        return $this->initializeObjectiveStates($objectives);
      }

      public function applyUpdate(array &$states, int $phase, string $objective_id, int $progress): array {
        return $this->applyObjectiveUpdate($states, $phase, $objective_id, $progress);
      }

      public function phaseComplete(array $states, int $phase): bool {
        return $this->isPhaseComplete($states, $phase);
      }
    };

    $states = $service->initializeStates([
      [
        'phase' => 1,
        'objectives' => [
          [
            'objective_id' => 'investigate_library',
            'type' => 'investigate',
            'description' => 'Investigate the ruined library.',
            'completed' => FALSE,
            'children' => [
              [
                'objective_id' => 'question_the_warden',
                'type' => 'investigate',
                'description' => 'Question the library warden.',
                'completed' => FALSE,
                'current' => 0,
                'target_count' => 1,
              ],
              [
                'objective_id' => 'report_to_eldric',
                'type' => 'interact',
                'description' => 'Report the findings to Eldric.',
                'completed' => FALSE,
              ],
            ],
          ],
        ],
      ],
    ]);

    $this->assertSame('all_children', $states[0]['objectives'][0]['completion_criteria']['kind']);
    $this->assertFalse($service->phaseComplete($states, 1));

    $first_update = $service->applyUpdate($states, 1, 'question_the_warden', 1);
    $this->assertTrue($first_update['updated']);
    $this->assertTrue($first_update['objective_completed']);
    $this->assertFalse($service->phaseComplete($states, 1));
    $this->assertFalse($states[0]['objectives'][0]['completed']);

    $second_update = $service->applyUpdate($states, 1, 'report_to_eldric', 1);
    $this->assertTrue($second_update['updated']);
    $this->assertTrue($second_update['objective_completed']);
    $this->assertTrue($service->phaseComplete($states, 1));
    $this->assertTrue($states[0]['objectives'][0]['completed']);
  }

  /**
   * Verifies only the current phase is revealed when quest progress starts.
   */
  public function testInitializeStatesRevealsOnlyCurrentPhaseObjectives(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $service = new class(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(TimeInterface::class)
    ) extends QuestTrackerService {
      public function initializeStates(array $objectives): array {
        return $this->initializeObjectiveStates($objectives);
      }
    };

    $states = $service->initializeStates([
      [
        'phase' => 1,
        'objectives' => [[
          'objective_id' => 'speak_with_eldric',
          'type' => 'interact',
          'description' => 'Speak with Eldric.',
          'completed' => FALSE,
        ]],
      ],
      [
        'phase' => 2,
        'objectives' => [[
          'objective_id' => 'explore_the_archive',
          'type' => 'explore',
          'description' => 'Explore the hidden archive.',
          'completed' => FALSE,
        ]],
      ],
    ]);

    $this->assertTrue($states[0]['objectives'][0]['revealed']);
    $this->assertFalse($states[1]['objectives'][0]['revealed']);
  }

}
