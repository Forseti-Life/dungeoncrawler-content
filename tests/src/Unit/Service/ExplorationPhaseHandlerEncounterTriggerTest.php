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
 * Tests hostile encounter trigger classification in exploration mode.
 *
 * @group dungeoncrawler_content
 * @group exploration
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\ExplorationPhaseHandler
 */
class ExplorationPhaseHandlerEncounterTriggerTest extends UnitTestCase {

  /**
   * Neutral NPCs do not seed hostile encounters.
   *
   * @covers ::isHostileEncounterEntity
   */
  public function testNeutralNpcDoesNotSeedEncounter(): void {
    $handler = $this->buildHandler();

    $this->assertFalse($handler->exposedIsHostileEncounterEntity([
      'entity_type' => 'npc',
      'entity_ref' => ['content_type' => 'npc', 'content_id' => 'tavern_keeper'],
      'state' => ['metadata' => ['team' => 'neutral']],
    ]));
  }

  /**
   * Explicit hostile enemies still seed encounters.
   *
   * @covers ::isHostileEncounterEntity
   */
  public function testHostileNpcStillSeedsEncounter(): void {
    $handler = $this->buildHandler();

    $this->assertTrue($handler->exposedIsHostileEncounterEntity([
      'entity_type' => 'npc',
      'entity_ref' => ['content_type' => 'npc', 'content_id' => 'bandit'],
      'state' => ['metadata' => ['team' => 'hostile']],
    ]));
  }

  /**
   * Builds an ExplorationPhaseHandler test double exposing encounter checks.
   */
  private function buildHandler(): ExplorationPhaseHandler {
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerInterface::class));

    return new class(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(RoomChatService::class),
      $this->createMock(DungeonStateService::class),
      $this->createMock(CharacterStateService::class),
      $this->createMock(NumberGenerationService::class),
      $this->createMock(AiGmService::class)
    ) extends ExplorationPhaseHandler {
      public function exposedIsHostileEncounterEntity(array $entity): bool {
        return $this->isHostileEncounterEntity($entity);
      }
    };
  }

}
