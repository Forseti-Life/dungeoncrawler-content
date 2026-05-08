<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\AiGmService;
use Drupal\dungeoncrawler_content\Service\CharacterStateService;
use Drupal\dungeoncrawler_content\Service\DungeonStateService;
use Drupal\dungeoncrawler_content\Service\ExplorationPhaseHandler;
use Drupal\dungeoncrawler_content\Service\NumberGenerationService;
use Drupal\dungeoncrawler_content\Service\RoomChatService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests Survival action availability in exploration mode.
 *
 * @group dungeoncrawler_content
 * @group exploration
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\ExplorationPhaseHandler
 */
class ExplorationPhaseHandlerSurvivalAvailabilityTest extends UnitTestCase {

  /**
   * Survival exploration actions are exposed through available actions.
   *
   * @covers ::getAvailableActions
   */
  public function testGetAvailableActionsIncludesSurvivalNavigationActions(): void {
    $handler = $this->buildHandler();

    $actions = $handler->getAvailableActions(['phase' => 'exploration'], []);

    $this->assertContains('sense_direction', $actions);
    $this->assertContains('cover_tracks', $actions);
    $this->assertContains('track', $actions);
  }

  /**
   * Builds an ExplorationPhaseHandler with lightweight mocks.
   */
  private function buildHandler(): ExplorationPhaseHandler {
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerInterface::class));

    return new ExplorationPhaseHandler(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(RoomChatService::class),
      $this->createMock(DungeonStateService::class),
      $this->createMock(CharacterStateService::class),
      $this->createMock(NumberGenerationService::class),
      $this->createMock(AiGmService::class)
    );
  }

}
