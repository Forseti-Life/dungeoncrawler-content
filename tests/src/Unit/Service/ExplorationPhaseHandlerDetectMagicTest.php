<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Update;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\AiGmService;
use Drupal\dungeoncrawler_content\Service\CharacterStateService;
use Drupal\dungeoncrawler_content\Service\DungeonStateService;
use Drupal\dungeoncrawler_content\Service\ExplorationPhaseHandler;
use Drupal\dungeoncrawler_content\Service\GameplayActionProcessor;
use Drupal\dungeoncrawler_content\Service\NumberGenerationService;
use Drupal\dungeoncrawler_content\Service\RoomChatService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests Detect Magic handling in exploration spell casts.
 *
 * @group dungeoncrawler_content
 * @group exploration
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\ExplorationPhaseHandler
 */
class ExplorationPhaseHandlerDetectMagicTest extends UnitTestCase {

  /**
   * @covers ::processIntent
   */
  public function testDetectMagicNarratesMagicalAurasInActiveRoom(): void {
    $handler = $this->buildHandler();
    $game_state = [
      'phase' => 'exploration',
      'exploration' => ['time_elapsed_minutes' => 0],
      'campaign_clock' => [
        'datetime' => '2024-01-01T08:00:00Z',
        'date' => '2024-01-01',
        'time' => '08:00',
        'timezone' => 'UTC',
        'year' => 2024,
        'month' => 1,
        'day' => 1,
        'hour' => 8,
        'minute' => 0,
        'weekday' => 'Monday',
        'season' => 'winter',
      ],
      'game_time' => [
        'day' => 1,
        'hour' => 8,
        'minute' => 0,
        'date' => '2024-01-01',
        'datetime' => '2024-01-01T08:00:00Z',
        'timezone' => 'UTC',
      ],
    ];
    $dungeon_data = [
      'active_room_id' => 'room-burrow',
      'rooms' => [
        [
          'room_id' => 'room-burrow',
          'name' => 'Kobold Burrow',
          'items' => [
            [
              'name' => 'Wand of Burning Hands',
              'traits' => ['magical'],
            ],
          ],
        ],
      ],
      'entities' => [
        [
          'entity_instance_id' => 'pc-1',
          'name' => 'Burasco',
          'stats' => [
            'casting_type' => 'prepared',
            'spellcasting_tradition' => 'arcane',
          ],
          'state' => [],
        ],
        [
          'instance_id' => 'haz-1',
          'type' => 'hazard',
          'name' => 'Arcane Snare',
          'is_magical' => TRUE,
          'placement' => ['room_id' => 'room-burrow'],
          'state' => ['detected' => FALSE, 'triggered' => FALSE, 'disabled' => FALSE],
        ],
      ],
    ];

    $response = $handler->processIntent([
      'type' => 'cast_spell',
      'actor' => 'pc-1',
      'params' => [
        'spell_id' => 'detect-magic',
        'spell_name' => 'Detect Magic',
        'spell_level' => 0,
        'is_cantrip' => TRUE,
      ],
    ], $game_state, $dungeon_data, 42);

    $this->assertTrue($response['success']);
    $this->assertStringContainsString('Wand of Burning Hands', (string) $response['narration']);
    $this->assertStringContainsString('Arcane Snare', (string) $response['narration']);
  }

  /**
   * @covers ::processIntent
   */
  public function testDetectMagicNarratesMagicalItemsCarriedByRoomOccupants(): void {
    $action_processor = $this->createMock(GameplayActionProcessor::class);
    $action_processor->expects($this->once())
      ->method('buildRoomInventory')
      ->with(42, 'room-burrow', $this->isType('array'), $this->isType('array'))
      ->willReturn([
        'items' => [],
        'storage_owner_details' => [
          [
            'owner_id' => 'npc-17',
            'owner_type' => 'character',
            'name' => 'Tikka the Trapmaster',
            'items' => [
              ['name' => 'Potion of Healing'],
              ['name' => 'Clockwork Toolkit'],
            ],
          ],
        ],
      ]);

    $handler = $this->buildHandler($action_processor);
    $game_state = [
      'phase' => 'exploration',
      'exploration' => ['time_elapsed_minutes' => 0],
      'campaign_clock' => [
        'datetime' => '2024-01-01T08:00:00Z',
        'date' => '2024-01-01',
        'time' => '08:00',
        'timezone' => 'UTC',
        'year' => 2024,
        'month' => 1,
        'day' => 1,
        'hour' => 8,
        'minute' => 0,
        'weekday' => 'Monday',
        'season' => 'winter',
      ],
      'game_time' => [
        'day' => 1,
        'hour' => 8,
        'minute' => 0,
        'date' => '2024-01-01',
        'datetime' => '2024-01-01T08:00:00Z',
        'timezone' => 'UTC',
      ],
    ];
    $dungeon_data = [
      'active_room_id' => 'room-burrow',
      'rooms' => [
        [
          'room_id' => 'room-burrow',
          'name' => 'Kobold Burrow',
        ],
      ],
      'entities' => [
        [
          'entity_instance_id' => 'pc-1',
          'name' => 'Burasco',
          'stats' => [
            'casting_type' => 'prepared',
            'spellcasting_tradition' => 'arcane',
          ],
          'state' => [],
        ],
      ],
    ];

    $response = $handler->processIntent([
      'type' => 'cast_spell',
      'actor' => 'pc-1',
      'params' => [
        'spell_id' => 'detect-magic',
        'spell_name' => 'Detect Magic',
        'spell_level' => 0,
        'is_cantrip' => TRUE,
      ],
    ], $game_state, $dungeon_data, 42);

    $this->assertTrue($response['success']);
    $this->assertStringContainsString('Tikka the Trapmaster carries Potion of Healing.', (string) $response['narration']);
    $this->assertStringNotContainsString('Clockwork Toolkit', (string) $response['narration']);
  }

  /**
   * Builds an ExplorationPhaseHandler with lightweight mocks.
   */
  private function buildHandler(?GameplayActionProcessor $action_processor = NULL): ExplorationPhaseHandler {
    $update = $this->createMock(Update::class);
    $update->method('fields')->willReturnSelf();
    $update->method('condition')->willReturnSelf();
    $update->method('execute')->willReturn(1);

    $database = $this->createMock(Connection::class);
    $database->method('update')->willReturn($update);

    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerInterface::class));

    return new ExplorationPhaseHandler(
      $database,
      $logger_factory,
      $this->createMock(RoomChatService::class),
      $this->createMock(DungeonStateService::class),
      $this->createMock(CharacterStateService::class),
      $this->createMock(NumberGenerationService::class),
      $this->createMock(AiGmService::class),
      NULL,
      NULL,
      NULL,
      NULL,
      $action_processor
    );
  }

}
