<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\dungeoncrawler_content\Service\QuestConfirmationService;
use Drupal\dungeoncrawler_content\Service\QuestTouchpointService;
use Drupal\dungeoncrawler_content\Service\QuestTrackerService;
use Drupal\Tests\UnitTestCase;

/**
 * Covers recursive current-phase objective flattening for touchpoints.
 *
 * @group dungeoncrawler_content
 * @group quest
 */
class QuestTouchpointServiceTest extends UnitTestCase {

  /**
   * Verifies nested active child objectives are surfaced for touchpoint matching.
   */
  public function testGetActiveObjectivesForCurrentPhaseFlattensNestedChildren(): void {
    $store = $this->createMock(KeyValueStoreInterface::class);
    $factory = $this->createMock(KeyValueFactoryInterface::class);
    $factory->method('get')->willReturn($store);

    $service = new class(
      $this->createMock(QuestTrackerService::class),
      $this->createMock(QuestConfirmationService::class),
      $factory,
      $this->createMock(TimeInterface::class)
    ) extends QuestTouchpointService {
      public function exposedGetActiveObjectivesForCurrentPhase(array $quest): array {
        return $this->getActiveObjectivesForCurrentPhase($quest);
      }
    };

    $objectives = $service->exposedGetActiveObjectivesForCurrentPhase([
      'current_phase' => 1,
      'objective_states' => json_encode([
        [
          'phase' => 1,
          'objectives' => [
            [
              'objective_id' => 'escort_to_safety',
              'type' => 'escort',
              'description' => 'Escort the merchant to safety.',
              'completed' => FALSE,
              'children' => [
                [
                  'objective_id' => 'reach_safehouse',
                  'type' => 'explore',
                  'location' => 'safehouse',
                  'description' => 'Reach the safehouse.',
                  'completed' => FALSE,
                  'discovered' => FALSE,
                ],
                [
                  'objective_id' => 'speak_to_merchant',
                  'type' => 'interact',
                  'target' => 'merchant',
                  'description' => 'Check on the merchant.',
                  'completed' => FALSE,
                ],
              ],
            ],
          ],
        ],
      ]),
    ]);

    $this->assertCount(2, $objectives);
    $this->assertSame(['reach_safehouse', 'speak_to_merchant'], array_column($objectives, 'objective_id'));
  }

}
