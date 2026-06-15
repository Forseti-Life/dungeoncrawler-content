<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\GameEventLogger;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Covers malformed event-log handling in GameEventLogger.
 *
 * @group dungeoncrawler_content
 * @group game_event_logger
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\GameEventLogger
 */
class GameEventLoggerResilienceTest extends UnitTestCase {

  protected function createLogger(): GameEventLogger {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->with('dungeoncrawler')->willReturn($logger);
    return new GameEventLogger($logger_factory);
  }

  /**
   * @covers ::getEventsSince
   */
  public function testGetEventsSinceSkipsMalformedRows(): void {
    $service = $this->createLogger();
    $events = $service->getEventsSince([
      'event_log' => [
        'legacy-string-row',
        ['id' => 1, 'type' => 'a'],
        ['id' => 3, 'type' => 'b'],
        42,
      ],
    ], 1);

    $this->assertCount(1, $events);
    $this->assertSame(3, $events[0]['id']);
  }

  /**
   * @covers ::logEvents
   */
  public function testLogEventsFindsNextIdAfterMalformedTail(): void {
    $service = $this->createLogger();
    $dungeon_data = [
      'event_log' => [
        ['id' => 7, 'type' => 'existing'],
        'malformed-tail',
      ],
      'game_state' => [],
    ];

    $logged = $service->logEvents($dungeon_data, [
      ['type' => 'new_event', 'phase' => 'encounter'],
    ]);

    $this->assertCount(1, $logged);
    $this->assertSame(8, $logged[0]['id']);
    $this->assertSame(8, $dungeon_data['game_state']['event_log_cursor']);
  }

}

