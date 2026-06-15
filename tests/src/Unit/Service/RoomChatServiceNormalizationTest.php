<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\RoomChatService;
use Drupal\Tests\UnitTestCase;

/**
 * Covers dungeon chat payload normalization in RoomChatService.
 *
 * @group dungeoncrawler_content
 * @group room_chat
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\RoomChatService
 */
class RoomChatServiceNormalizationTest extends UnitTestCase {

  /**
   * @covers ::normalizeDungeonChatPayload
   */
  public function testNormalizeDungeonChatPayloadFiltersMalformedRows(): void {
    $reflection = new \ReflectionClass(RoomChatService::class);
    $service = $reflection->newInstanceWithoutConstructor();
    $method = $reflection->getMethod('normalizeDungeonChatPayload');
    $method->setAccessible(TRUE);

    $normalized = $method->invoke($service, [
      'rooms' => [
        [
          'room_id' => 'tavern_entrance',
          'chat' => [
            'legacy-string-row',
            ['speaker' => 'GM', 'message' => 'Welcome.'],
            ['speaker' => 'NPC', 'message' => 'Hello', 'channel' => 'whisper:npc_1'],
          ],
        ],
      ],
    ]);

    $chat = $normalized['rooms'][0]['chat'] ?? [];
    $this->assertCount(2, $chat);
    $this->assertSame('room', $chat[0]['channel']);
    $this->assertSame('whisper:npc_1', $chat[1]['channel']);
  }

}

