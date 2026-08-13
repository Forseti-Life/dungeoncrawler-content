<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\GameEventLogger;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests GameEventLogger normalization and cursor behavior.
 *
 * @group dungeoncrawler_content
 * @group event_log
 */
class GameEventLoggerTest extends UnitTestCase {

  /**
   * Ensures next ID uses max existing ID and normalizes scalar actor/target IDs.
   */
  public function testLogEventsUsesMaxIdAndNormalizesData(): void {
    $logger = $this->buildLogger();
    $dungeon_data = [
      'event_log' => [
        ['id' => 3, 'type' => 'old'],
        ['id' => 10, 'type' => 'old'],
      ],
      'game_state' => [],
    ];

    $logged = $logger->logEvents($dungeon_data, [
      [
        'type' => 'strike',
        'phase' => 'encounter',
        'actor' => 123,
        'target' => 456,
        'data' => ['result' => 'ok'],
      ],
    ]);

    $this->assertCount(1, $logged);
    $this->assertSame(11, $logged[0]['id']);
    $this->assertSame('123', $logged[0]['actor']);
    $this->assertSame('456', $logged[0]['target']);
    $this->assertSame(['result' => 'ok'], $logged[0]['data']);
    $this->assertSame(11, $dungeon_data['game_state']['event_log_cursor']);
  }

  /**
   * Ensures malformed event payloads fail loudly instead of being normalized.
   */
  public function testLogEventsRejectsMalformedEventContracts(): void {
    $logger = $this->buildLogger();
    $dungeon_data = [
      'event_log' => [],
      'game_state' => [],
    ];

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Game event contract violation');
    $logger->logEvents($dungeon_data, [
      [
        'type' => 'turn_start',
        'phase' => 'encounter',
        'data' => 'not-an-array',
      ],
    ]);
  }

  /**
   * Ensures malformed non-array events are skipped safely.
   */
  public function testLogEventsSkipsMalformedItems(): void {
    $logger = $this->buildLogger();
    $dungeon_data = [
      'event_log' => [],
      'game_state' => [],
    ];

    $logged = $logger->logEvents($dungeon_data, [
      'bad-event',
      ['type' => 'turn_start', 'phase' => 'encounter'],
    ]);

    $this->assertCount(1, $logged);
    $this->assertSame('turn_start', $logged[0]['type']);
    $this->assertSame(1, $dungeon_data['game_state']['event_log_cursor']);
  }

  /**
   * Builds a logger service with mocked Drupal logger dependencies.
   */
  protected function buildLogger(): GameEventLogger {
    $channel = $this->createMock(LoggerInterface::class);
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->with('dungeoncrawler')->willReturn($channel);

    return new GameEventLogger($factory);
  }

}
