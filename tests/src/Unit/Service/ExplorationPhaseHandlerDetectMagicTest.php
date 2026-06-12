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
   * @covers ::processIntent
   */
  public function testCastSpellConsumesCanonicalFocusPointsAndSyncsProjection(): void {
    $update = $this->createMock(Update::class);
    $update->method('fields')->willReturnSelf();
    $update->method('condition')->willReturnSelf();
    $update->method('execute')->willReturn(1);

    $database = $this->createMock(Connection::class);
    $database->method('update')->willReturn($update);

    $character_state = $this->createMock(CharacterStateService::class);
    $character_state->expects($this->exactly(2))
      ->method('getState')
      ->with('745', 42, 'pc-1')
      ->willReturnOnConsecutiveCalls(
        [
          'spells' => ['tradition' => 'arcane'],
          'resources' => [
            'focusPoints' => ['current' => 2, 'max' => 2],
            'spellSlots' => ['1' => ['current' => 2, 'max' => 2]],
          ],
        ],
        [
          'spells' => ['tradition' => 'arcane'],
          'resources' => [
            'focusPoints' => ['current' => 1, 'max' => 2],
            'spellSlots' => ['1' => ['current' => 2, 'max' => 2]],
          ],
        ]
      );
    $character_state->expects($this->once())
      ->method('castSpell')
      ->with('745', 'focus-blast', 0, TRUE, 42, 'pc-1')
      ->willReturn(['level' => 'focus', 'remaining' => 1]);

    $handler = $this->buildHandler(NULL, $character_state, $database);
    $game_state = [
      'phase' => 'exploration',
      'exploration' => ['time_elapsed_minutes' => 0],
    ];
    $dungeon_data = [
      'entities' => [
        [
          'entity_instance_id' => 'pc-1',
          'name' => 'Burasco',
          'stats' => [
            'casting_type' => 'spontaneous',
            'spellcasting_tradition' => 'arcane',
          ],
          'state' => [
            'metadata' => [
              'campaign_character_id' => '745',
              'runtime_entity_id' => 'pc-1',
            ],
          ],
        ],
      ],
    ];

    $response = $handler->processIntent([
      'type' => 'cast_spell',
      'actor' => 'pc-1',
      'params' => [
        'spell_id' => 'focus-blast',
        'spell_name' => 'Focus Blast',
        'spell_level' => 1,
        'is_focus_spell' => TRUE,
      ],
    ], $game_state, $dungeon_data, 42);

    $this->assertTrue($response['success']);
    $this->assertTrue($response['result']['cast']);
    $this->assertSame(1, $response['result']['focus_points_remaining']);
    $this->assertSame(1, (int) ($dungeon_data['entities'][0]['state']['resources']['focusPoints']['current'] ?? -1));
    $this->assertSame(1, (int) ($dungeon_data['entities'][0]['state']['focus_points'] ?? -1));
  }

  /**
   * @covers ::processIntent
   */
  public function testCastSpellRejectsSpendWithoutCanonicalIdentity(): void {
    $character_state = $this->createMock(CharacterStateService::class);
    $character_state->expects($this->never())->method('castSpell');

    $handler = $this->buildHandler(NULL, $character_state);
    $game_state = [
      'phase' => 'exploration',
      'exploration' => ['time_elapsed_minutes' => 0],
    ];
    $dungeon_data = [
      'entities' => [
        [
          'entity_instance_id' => 'pc-1',
          'name' => 'Burasco',
          'stats' => [
            'casting_type' => 'spontaneous',
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
        'spell_id' => 'focus-blast',
        'spell_name' => 'Focus Blast',
        'spell_level' => 1,
        'is_focus_spell' => TRUE,
      ],
    ], $game_state, $dungeon_data, 42);

    $this->assertTrue($response['success']);
    $this->assertFalse($response['result']['cast']);
    $this->assertSame('Canonical character sheet is required for spellcasting resource updates.', $response['result']['error']);
  }

  /**
   * @covers ::processIntent
   */
  public function testRefocusRequiresCanonicalIdentity(): void {
    $handler = $this->buildHandler();
    $game_state = [
      'phase' => 'exploration',
      'exploration' => ['time_elapsed_minutes' => 0],
    ];
    $dungeon_data = [
      'entities' => [
        [
          'entity_instance_id' => 'pc-1',
          'name' => 'Burasco',
          'state' => [],
        ],
      ],
    ];

    $response = $handler->processIntent([
      'type' => 'refocus',
      'actor' => 'pc-1',
      'params' => [],
    ], $game_state, $dungeon_data, 42);

    $this->assertFalse($response['success']);
    $this->assertSame('Canonical character sheet is required for spellcasting resource updates.', $response['result']['error']);
  }

  /**
   * Builds an ExplorationPhaseHandler with lightweight mocks.
   */
  private function buildHandler(
    ?GameplayActionProcessor $action_processor = NULL,
    ?CharacterStateService $character_state = NULL,
    ?Connection $database = NULL
  ): ExplorationPhaseHandler {
    if ($database === NULL) {
      $update = $this->createMock(Update::class);
      $update->method('fields')->willReturnSelf();
      $update->method('condition')->willReturnSelf();
      $update->method('execute')->willReturn(1);

      $database = $this->createMock(Connection::class);
      $database->method('update')->willReturn($update);
    }

    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerInterface::class));

    return new ExplorationPhaseHandler(
      $database,
      $logger_factory,
      $this->createMock(RoomChatService::class),
      $this->createMock(DungeonStateService::class),
      $character_state ?? $this->createMock(CharacterStateService::class),
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
