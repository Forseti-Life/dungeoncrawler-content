<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\ChatChannelManager;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Covers malformed room-message handling in ChatChannelManager.
 *
 * @group dungeoncrawler_content
 * @group chat_channel_manager
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\ChatChannelManager
 */
class ChatChannelManagerResilienceTest extends UnitTestCase {

  protected function createManager(): ChatChannelManager {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->with('dungeoncrawler_chat_channel')->willReturn($logger);

    return new ChatChannelManager(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(AccountProxyInterface::class)
    );
  }

  /**
   * @covers ::filterMessagesByChannel
   */
  public function testFilterMessagesByChannelSkipsMalformedRows(): void {
    $manager = $this->createManager();

    $filtered = $manager->filterMessagesByChannel([
      'legacy-string-row',
      ['message' => 'no channel defaults to room'],
      ['channel' => 'room', 'message' => 'room line'],
      ['channel' => 'whisper:npc_1', 'message' => 'private line'],
      123,
    ], 'room');

    $this->assertCount(2, $filtered);
    $this->assertSame('no channel defaults to room', $filtered[0]['message']);
    $this->assertSame('room line', $filtered[1]['message']);
  }

}

