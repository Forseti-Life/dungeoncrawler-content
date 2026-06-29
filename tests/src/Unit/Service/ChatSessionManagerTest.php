<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\ChatSessionManager;
use Drupal\Tests\UnitTestCase;

/**
 * @group dungeoncrawler_content
 * @group chat
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\ChatSessionManager
 */
class ChatSessionManagerTest extends UnitTestCase {

  /**
   * @covers ::normalizeMessageRow
   */
  public function testNormalizeMessageRowDecodesJsonAndCastsIds(): void {
    $service = (new \ReflectionClass(ChatSessionManager::class))
      ->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod($service, 'normalizeMessageRow');
    $method->setAccessible(TRUE);

    $row = $method->invoke($service, [
      'id' => '42',
      'session_id' => '9',
      'metadata' => '{"event":"room_enter"}',
      'feed_targets' => '[1,2]',
      'message' => 'Hello',
    ]);

    $this->assertSame(42, $row['id']);
    $this->assertSame(9, $row['session_id']);
    $this->assertSame(['event' => 'room_enter'], $row['metadata']);
    $this->assertSame([1, 2], $row['feed_targets']);
    $this->assertSame('Hello', $row['message']);
  }

  /**
   * @covers ::normalizeMessageRow
   */
  public function testNormalizeMessageRowFallsBackToEmptyArraysOnInvalidJson(): void {
    $service = (new \ReflectionClass(ChatSessionManager::class))
      ->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod($service, 'normalizeMessageRow');
    $method->setAccessible(TRUE);

    $row = $method->invoke($service, [
      'metadata' => 'not-json',
      'feed_targets' => '',
    ]);

    $this->assertSame(0, $row['id']);
    $this->assertSame(0, $row['session_id']);
    $this->assertSame([], $row['metadata']);
    $this->assertSame([], $row['feed_targets']);
  }

}
