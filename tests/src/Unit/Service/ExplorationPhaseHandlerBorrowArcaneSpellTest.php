<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Update;
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
 * Tests Borrow Arcane Spell and Arcana Recall Knowledge behavior.
 *
 * @group dungeoncrawler_content
 * @group exploration
 * @group pf2e-rules
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\ExplorationPhaseHandler
 */
class ExplorationPhaseHandlerBorrowArcaneSpellTest extends UnitTestCase {

  /**
   * Builds a handler with the provided DB and roller mocks.
   */
  protected function buildHandler(Connection $database, NumberGenerationService $roller): ExplorationPhaseHandler {
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerInterface::class));

    return new ExplorationPhaseHandler(
      $database,
      $logger_factory,
      $this->createMock(RoomChatService::class),
      $this->createMock(DungeonStateService::class),
      $this->createMock(CharacterStateService::class),
      $roller,
      $this->createMock(AiGmService::class)
    );
  }

  /**
   * Builds a minimal actor entity for spellcasting tests.
   */
  protected function makeActorEntity(array $stats = [], array $state = []): array {
    return [
      'entity_instance_id' => 'pc-1',
      'name' => 'Wizard',
      'stats' => $stats + [
        'casting_type' => 'prepared',
        'spellcasting_tradition' => 'arcane',
      ],
      'state' => $state,
    ];
  }

  /**
   * Tests Arcana Recall Knowledge does not require training.
   *
   * @covers ::processIntent
   */
  public function testRecallKnowledgeAllowsUntrainedArcana(): void {
    $database = $this->createMock(Connection::class);
    $roller = $this->createMock(NumberGenerationService::class);
    $roller->expects($this->once())->method('rollPathfinderDie')->with(20)->willReturn(12);
    $handler = $this->buildHandler($database, $roller);

    $game_state = ['phase' => 'exploration', 'exploration' => ['time_elapsed_minutes' => 0]];
    $dungeon_data = ['entities' => [$this->makeActorEntity()]];
    $intent = [
      'type' => 'recall_knowledge',
      'actor' => 'pc-1',
      'params' => [
        'dc' => 10,
        'skill_used' => 'arcana',
        'skill_bonus' => 0,
        'availability' => 'untrained',
        'known_info' => 'Arcane fact',
      ],
    ];

    $response = $handler->processIntent($intent, $game_state, $dungeon_data, 42);

    $this->assertTrue($response['success']);
    $this->assertSame('arcana', $response['result']['skill_used']);
    $this->assertTrue($response['result']['secret']);
  }

  /**
   * Tests Borrow Arcane Spell is blocked for untrained Arcana.
   *
   * @covers ::processIntent
   */
  public function testBorrowArcaneSpellRequiresTrainedArcana(): void {
    $database = $this->createMock(Connection::class);
    $roller = $this->createMock(NumberGenerationService::class);
    $roller->expects($this->never())->method('rollPathfinderDie');
    $handler = $this->buildHandler($database, $roller);

    $game_state = ['phase' => 'exploration', 'exploration' => ['time_elapsed_minutes' => 0]];
    $dungeon_data = ['entities' => [$this->makeActorEntity()]];
    $intent = [
      'type' => 'borrow_arcane_spell',
      'actor' => 'pc-1',
      'params' => [
        'arcana_proficiency_rank' => 0,
        'spell_name' => 'Magic Missile',
      ],
    ];

    $response = $handler->processIntent($intent, $game_state, $dungeon_data, 42);

    $this->assertFalse($response['success']);
    $this->assertSame('Borrow Arcane Spell requires Trained Arcana.', $response['result']['error']);
  }

  /**
   * Tests Borrow Arcane Spell is blocked for non-arcane prepared casters.
   *
   * @covers ::processIntent
   */
  public function testBorrowArcaneSpellRequiresArcanePreparedSpellcaster(): void {
    $database = $this->createMock(Connection::class);
    $roller = $this->createMock(NumberGenerationService::class);
    $roller->expects($this->never())->method('rollPathfinderDie');
    $handler = $this->buildHandler($database, $roller);

    $game_state = ['phase' => 'exploration', 'exploration' => ['time_elapsed_minutes' => 0]];
    $dungeon_data = [
      'entities' => [
        $this->makeActorEntity([
          'casting_type' => 'spontaneous',
          'spellcasting_tradition' => 'arcane',
        ]),
      ],
    ];
    $intent = [
      'type' => 'borrow_arcane_spell',
      'actor' => 'pc-1',
      'params' => [
        'arcana_proficiency_rank' => 1,
        'spell_name' => 'Magic Missile',
      ],
    ];

    $response = $handler->processIntent($intent, $game_state, $dungeon_data, 42);

    $this->assertFalse($response['success']);
    $this->assertSame('Borrow Arcane Spell requires an arcane prepared spellcaster.', $response['result']['error']);
  }

  /**
   * Tests borrow failure blocks retry until the next daily preparation cycle.
   *
   * @covers ::processIntent
   * @covers ::processDailyPrepare
   */
  public function testBorrowArcaneSpellRetryBlockClearsAfterDailyPrepare(): void {
    $update = $this->createMock(Update::class);
    $update->method('fields')->willReturnSelf();
    $update->method('condition')->willReturnSelf();
    $update->method('execute')->willReturn(1);

    $database = $this->createMock(Connection::class);
    $database->method('update')->willReturn($update);

    $roller = $this->createMock(NumberGenerationService::class);
    $roller->expects($this->exactly(2))
      ->method('rollPathfinderDie')
      ->with(20)
      ->willReturnOnConsecutiveCalls(1, 20);

    $handler = $this->buildHandler($database, $roller);

    $game_state = ['phase' => 'exploration', 'exploration' => ['time_elapsed_minutes' => 0]];
    $dungeon_data = ['entities' => [$this->makeActorEntity()]];

    $borrow_intent = [
      'type' => 'borrow_arcane_spell',
      'actor' => 'pc-1',
      'params' => [
        'dc' => 20,
        'arcana_bonus' => 0,
        'arcana_proficiency_rank' => 1,
        'spell_name' => 'Magic Missile',
      ],
    ];

    $first = $handler->processIntent($borrow_intent, $game_state, $dungeon_data, 42);
    $this->assertTrue($first['success']);
    $this->assertFalse($first['result']['borrowed']);
    $this->assertTrue($first['result']['retry_blocked_until_next_prep']);

    $second = $handler->processIntent($borrow_intent, $game_state, $dungeon_data, 42);
    $this->assertFalse($second['success']);
    $this->assertSame(
      'Borrow Arcane Spell cannot be retried until the next daily preparation cycle.',
      $second['result']['error']
    );

    $prepare = $handler->processIntent([
      'type' => 'daily_prepare',
      'actor' => 'pc-1',
      'params' => [],
    ], $game_state, $dungeon_data, 42);
    $this->assertTrue($prepare['success']);

    $third = $handler->processIntent($borrow_intent, $game_state, $dungeon_data, 42);
    $this->assertTrue($third['success']);
    $this->assertTrue($third['result']['borrowed']);
    $this->assertSame('Magic Missile', $game_state['exploration']['borrowed_spell_pc-1']);
  }

}
