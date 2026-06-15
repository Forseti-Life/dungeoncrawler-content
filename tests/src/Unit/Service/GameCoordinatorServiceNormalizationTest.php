<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\GameCoordinatorService;
use Drupal\Tests\UnitTestCase;

/**
 * Covers runtime payload normalization in GameCoordinatorService.
 *
 * @group dungeoncrawler_content
 * @group game_coordinator
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\GameCoordinatorService
 */
class GameCoordinatorServiceNormalizationTest extends UnitTestCase {

  /**
   * @covers ::normalizeDungeonRuntimePayload
   */
  public function testNormalizeDungeonRuntimePayloadRemovesMalformedEvents(): void {
    $reflection = new \ReflectionClass(GameCoordinatorService::class);
    $service = $reflection->newInstanceWithoutConstructor();
    $method = $reflection->getMethod('normalizeDungeonRuntimePayload');
    $method->setAccessible(TRUE);

    $normalized = $method->invoke($service, [
      'event_log' => [
        'legacy-string-row',
        ['id' => 5, 'type' => 'room_entered'],
        ['type' => 'turn_started'],
      ],
      'game_state' => 'legacy-state-string',
    ]);

    $this->assertCount(2, $normalized['event_log']);
    $this->assertSame(5, $normalized['event_log'][0]['id']);
    $this->assertSame(6, $normalized['event_log'][1]['id']);
    $this->assertArrayNotHasKey('game_state', $normalized);
  }

}

