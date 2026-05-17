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
 * Tests tiered room perception search behavior.
 *
 * @group dungeoncrawler_content
 * @group exploration
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\ExplorationPhaseHandler
 */
class ExplorationPhaseHandlerRoomPerceptionTest extends UnitTestCase {

  /**
   * @covers ::processIntent
   */
  public function testSearchRevealsFirstAvailableSensoryTier(): void {
    $roller = $this->createMock(NumberGenerationService::class);
    $roller->expects($this->once())
      ->method('rollPathfinderDie')
      ->with(20)
      ->willReturn(14);

    $handler = $this->buildHandler($roller);
    $game_state = $this->minimalGameState();
    $dungeon_data = $this->buildDungeonData();

    $response = $handler->processIntent([
      'type' => 'search',
      'actor' => 'pc-1',
      'params' => [
        'perception_bonus' => 4,
      ],
    ], $game_state, $dungeon_data, 42);

    $this->assertTrue($response['success']);
    $this->assertSame('revealed', $response['result']['sensory_status']);
    $this->assertSame('smell', $response['result']['sensory_reveals'][0]['key']);
    $this->assertStringContainsString('Smell:', (string) $response['narration']);
    $this->assertSame(
      'A sour mildew smell rises from the soaked flagstones.',
      $dungeon_data['rooms'][0]['gameplay_state']['revealed_sensory_details']['smell']['detail']
    );
  }

  /**
   * @covers ::processIntent
   */
  public function testRepeatedSearchAdvancesToNextSensoryTier(): void {
    $roller = $this->createMock(NumberGenerationService::class);
    $roller->expects($this->exactly(2))
      ->method('rollPathfinderDie')
      ->with(20)
      ->willReturnOnConsecutiveCalls(14, 16);

    $handler = $this->buildHandler($roller);
    $game_state = $this->minimalGameState();
    $dungeon_data = $this->buildDungeonData();

    $first = $handler->processIntent([
      'type' => 'search',
      'actor' => 'pc-1',
      'params' => [
        'perception_bonus' => 12,
      ],
    ], $game_state, $dungeon_data, 42);

    $second = $handler->processIntent([
      'type' => 'search',
      'actor' => 'pc-1',
      'params' => [
        'perception_bonus' => 12,
      ],
    ], $game_state, $dungeon_data, 42);

    $this->assertSame('smell', $first['result']['sensory_reveals'][0]['key']);
    $this->assertSame('sound', $second['result']['sensory_reveals'][0]['key']);
    $this->assertArrayHasKey('sound', $dungeon_data['rooms'][0]['gameplay_state']['revealed_sensory_details']);
    $this->assertStringContainsString('Sound:', (string) $second['narration']);
  }

  /**
   * @covers ::processIntent
   */
  public function testSearchFailureDoesNotLeakLockedSensoryDetail(): void {
    $roller = $this->createMock(NumberGenerationService::class);
    $roller->expects($this->once())
      ->method('rollPathfinderDie')
      ->with(20)
      ->willReturn(6);

    $handler = $this->buildHandler($roller);
    $game_state = $this->minimalGameState();
    $dungeon_data = $this->buildDungeonData();

    $response = $handler->processIntent([
      'type' => 'search',
      'actor' => 'pc-1',
      'params' => [
        'perception_bonus' => 3,
      ],
    ], $game_state, $dungeon_data, 42);

    $this->assertTrue($response['success']);
    $this->assertSame('miss', $response['result']['sensory_status']);
    $this->assertSame([], $response['result']['sensory_reveals']);
    $this->assertStringNotContainsString('A sour mildew smell rises from the soaked flagstones.', (string) $response['narration']);
    $this->assertSame([], $dungeon_data['rooms'][0]['gameplay_state']['revealed_sensory_details']);
  }

  /**
   * @covers ::processIntent
   */
  public function testRoomEntryAutoRevealsCachedAndNewSensoryTiersUntilFailure(): void {
    $roller = $this->createMock(NumberGenerationService::class);
    $roller->expects($this->exactly(2))
      ->method('rollPathfinderDie')
      ->with(20)
      ->willReturnOnConsecutiveCalls(17, 4);

    $handler = $this->buildHandler($roller);
    $game_state = $this->minimalGameState();
    $dungeon_data = $this->buildDungeonData();
    $dungeon_data['active_room_id'] = 'room-0';
    $dungeon_data['rooms'][] = [
      'room_id' => 'room-0',
      'name' => 'Hallway',
      'description' => 'A blank corridor.',
      'room_type' => 'corridor',
      'lighting' => ['level' => 'bright_light'],
      'terrain' => ['type' => 'stone_floor'],
      'gameplay_state' => [],
    ];
    $dungeon_data['rooms'][0]['gameplay_state']['revealed_sensory_details'] = [
      'smell' => [
        'revealed_at' => '2026-05-16T00:00:00Z',
        'label' => 'Smell',
        'detail' => 'A sour mildew smell rises from the soaked flagstones.',
        'dc' => 15,
      ],
    ];
    $dungeon_data['entities'][0]['stats']['perception'] = 10;

    $response = $handler->processIntent([
      'type' => 'transition',
      'actor' => 'pc-1',
      'params' => [
        'target_room_id' => 'room-1',
        'entry_hex' => ['q' => 0, 'r' => 0],
      ],
    ], $game_state, $dungeon_data, 42);

    $this->assertTrue($response['success']);
    $this->assertCount(2, $response['result']['sensory_reveals']);
    $this->assertSame('smell', $response['result']['sensory_reveals'][0]['key']);
    $this->assertSame('cache', $response['result']['sensory_reveals'][0]['source']);
    $this->assertSame('sound', $response['result']['sensory_reveals'][1]['key']);
    $this->assertSame('secret_check', $response['result']['sensory_reveals'][1]['source']);
    $this->assertArrayNotHasKey('atmosphere_mood', $dungeon_data['rooms'][0]['gameplay_state']['revealed_sensory_details']);
    $this->assertArrayHasKey('sound', $dungeon_data['rooms'][0]['gameplay_state']['revealed_sensory_details']);
    $this->assertStringContainsString('Your trained senses immediately catch more of the room:', (string) $response['narration']);
    $this->assertStringContainsString('You enter Flooded Storehouse.', (string) $response['narration']);
    $this->assertStringContainsString('Smell:', (string) $response['narration']);
    $this->assertStringContainsString('Sound:', (string) $response['narration']);
  }

  /**
   * @covers ::processIntent
   */
  public function testRoomEntryGeneratesAndCachesMissingFirstTierBeforeReveal(): void {
    $roller = $this->createMock(NumberGenerationService::class);
    $roller->expects($this->exactly(2))
      ->method('rollPathfinderDie')
      ->with(20)
      ->willReturnOnConsecutiveCalls(15, 1);

    $handler = $this->buildHandler($roller);
    $game_state = $this->minimalGameState();
    $dungeon_data = $this->buildDungeonData();
    unset($dungeon_data['rooms'][0]['gameplay_state']['sensory_details']['smell']);
    unset($dungeon_data['rooms'][0]['gameplay_state']['revealed_sensory_details']);

    $response = $handler->processIntent([
      'type' => 'transition',
      'actor' => 'pc-1',
      'params' => [
        'target_room_id' => 'room-1',
        'entry_hex' => ['q' => 0, 'r' => 0],
      ],
    ], $game_state, $dungeon_data, 42);

    $this->assertTrue($response['success']);
    $this->assertSame('smell', $response['result']['sensory_reveals'][0]['key']);
    $this->assertArrayHasKey('smell', $dungeon_data['rooms'][0]['gameplay_state']['sensory_details']);
    $this->assertSame(
      'The air smells damp, stale, and faintly metallic.',
      $dungeon_data['rooms'][0]['gameplay_state']['sensory_details']['smell']['text']
    );
  }

  /**
   * @covers ::processIntent
   */
  public function testRoomEntryStopsAtTenSensoryReveals(): void {
    $roller = $this->createMock(NumberGenerationService::class);
    $roller->expects($this->exactly(10))
      ->method('rollPathfinderDie')
      ->with(20)
      ->willReturn(20);

    $handler = $this->buildHandler($roller);
    $game_state = $this->minimalGameState();
    $dungeon_data = $this->buildDungeonData();
    unset($dungeon_data['rooms'][0]['gameplay_state']['revealed_sensory_details']);
    $dungeon_data['entities'][0]['stats']['perception'] = 50;
    $dungeon_data['active_room_id'] = 'room-0';
    $dungeon_data['rooms'][] = [
      'room_id' => 'room-0',
      'name' => 'Hallway',
      'description' => 'A blank corridor.',
      'room_type' => 'corridor',
      'lighting' => ['level' => 'bright_light'],
      'terrain' => ['type' => 'stone_floor'],
      'gameplay_state' => [],
    ];

    $response = $handler->processIntent([
      'type' => 'transition',
      'actor' => 'pc-1',
      'params' => [
        'target_room_id' => 'room-1',
        'entry_hex' => ['q' => 0, 'r' => 0],
      ],
    ], $game_state, $dungeon_data, 42);

    $this->assertTrue($response['success']);
    $this->assertCount(10, $response['result']['sensory_reveals']);
    $this->assertSame('pressure', $response['result']['sensory_reveals'][9]['key']);
  }

  /**
   * Builds a handler with the provided dice roller.
   */
  private function buildHandler(NumberGenerationService $roller): ExplorationPhaseHandler {
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
      $roller,
      $this->createMock(AiGmService::class)
    );
  }

  /**
   * Minimal exploration game state.
   */
  private function minimalGameState(): array {
    return [
      'phase' => 'exploration',
      'exploration' => [
        'time_elapsed_minutes' => 0,
      ],
    ];
  }

  /**
   * Dungeon data with one active room and one player entity.
   */
  private function buildDungeonData(): array {
    return [
      'active_room_id' => 'room-1',
      'rooms' => [
        [
          'room_id' => 'room-1',
          'name' => 'Flooded Storehouse',
          'description' => 'Dim light glints off pooled water while distant drips echo from the ceiling.',
          'room_type' => 'storehouse',
          'lighting' => ['level' => 'dim_light'],
          'terrain' => ['type' => 'flooded_stone'],
          'gameplay_state' => [
            'search_dc' => 15,
            'sensory_details' => [
              'smell' => [
                'dc' => 15,
                'text' => 'A sour mildew smell rises from the soaked flagstones.',
              ],
              'touch_texture' => [
                'dc' => 30,
                'text' => 'The stones feel slick with condensation and soft moss.',
              ],
              'sound' => [
                'dc' => 25,
                'text' => 'Soft dripping water and distant runoff echo through the room.',
              ],
              'taste' => [
                'dc' => 30,
                'text' => 'A metallic dampness lingers on the tongue.',
              ],
              'atmosphere_mood' => [
                'dc' => 35,
                'text' => 'The room feels abandoned, but not entirely empty of intent.',
              ],
              'stability' => [
                'dc' => 40,
                'text' => 'The old stone shifts just enough to suggest hidden strain in the floor.',
              ],
              'air_current' => [
                'dc' => 45,
                'text' => 'A thin draft brushes past from somewhere deeper in the dungeon.',
              ],
              'temperature' => [
                'dc' => 50,
                'text' => 'The air settles into a cool, clammy chill against exposed skin.',
              ],
              'resonance' => [
                'dc' => 55,
                'text' => 'Every small movement leaves behind a lingering hollow echo.',
              ],
              'pressure' => [
                'dc' => 60,
                'text' => 'The close air presses in as if the room were holding its breath.',
              ],
            ],
          ],
        ],
      ],
      'entities' => [
        [
          'entity_instance_id' => 'pc-1',
          'instance_id' => 'pc-1',
          'name' => 'Burasco',
          'stats' => [
            'perception' => 4,
          ],
          'state' => [],
        ],
      ],
    ];
  }

}
