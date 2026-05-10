<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\AbilityScoreTracker;
use Drupal\dungeoncrawler_content\Service\CharacterCreationGmService;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for GM chat response decoding.
 *
 * @group dungeoncrawler_content
 * @group service
 * @group unit
 */
class CharacterCreationGmServiceTest extends TestCase {

  /**
   * Tests plain-text model replies degrade to advice instead of an exception.
   */
  public function testDecodeResponsePayloadFallsBackToReplyText(): void {
    $service = new CharacterCreationGmService(
      $this->createMock(Connection::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(TimeInterface::class),
      $this->createMock(UuidInterface::class),
      $this->createMock(CharacterManager::class),
      $this->createMock(AbilityScoreTracker::class),
      NULL,
    );

    $method = new \ReflectionMethod(CharacterCreationGmService::class, 'decodeResponsePayload');
    $method->setAccessible(TRUE);

    $payload = $method->invoke($service, 'A dwarven fighter sounds like a sturdy frontline choice.');

    $this->assertSame([
      'reply' => 'A dwarven fighter sounds like a sturdy frontline choice.',
      'updates' => [],
    ], $payload);
  }
}
