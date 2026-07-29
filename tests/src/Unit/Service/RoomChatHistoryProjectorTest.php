<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\ChatChannelManager;
use Drupal\dungeoncrawler_content\Service\RoomChat\EncounterTranscriptPrefixService;
use Drupal\dungeoncrawler_content\Service\RoomChat\RoomChatHistoryProjector;
use Drupal\Tests\UnitTestCase;

/**
 * Covers room-chat history projection edge cases.
 *
 * @group dungeoncrawler_content
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\RoomChat\RoomChatHistoryProjector
 */
class RoomChatHistoryProjectorTest extends UnitTestCase {

  /**
   * @covers ::projectHistory
   */
  public function testProjectHistorySuppressesLeadingDuplicateNarratorSceneIntro(): void {
    $channel_manager = $this->createMock(ChatChannelManager::class);
    $channel_manager->method('filterMessagesByChannel')->willReturnCallback(
      static fn(array $chat): array => $chat
    );

    $prefix_service = new EncounterTranscriptPrefixService();

    $projector = new RoomChatHistoryProjector();
    $history = $projector->projectHistory(
      [
        'rooms' => [[
          'room_id' => 'tavern',
          'name' => 'The Gilded Tankard',
          'description' => 'Warm candlelight washes over the empty tavern.',
          'chat' => [
            [
              'speaker' => 'Narrator',
              'message' => 'Warm candlelight washes over the empty tavern.',
              'type' => 'narrator',
              'channel' => 'room',
              'timestamp' => '2026-07-29T12:00:00+00:00',
              'scene_intro' => TRUE,
            ],
            [
              'speaker' => 'System',
              'message' => "It's your turn, Burasco.",
              'type' => 'system',
              'channel' => 'room',
              'timestamp' => '2026-07-29T12:00:01+00:00',
            ],
          ],
        ]],
      ],
      'tavern',
      'room',
      NULL,
      $channel_manager,
      $prefix_service
    );

    $this->assertCount(1, $history);
    $this->assertSame('System', $history[0]['speaker']);
    $this->assertSame("It's your turn, Burasco.", $history[0]['message']);
  }

  /**
   * @covers ::projectHistory
   */
  public function testProjectHistoryKeepsNonIntroNarratorChat(): void {
    $channel_manager = $this->createMock(ChatChannelManager::class);
    $channel_manager->method('filterMessagesByChannel')->willReturnCallback(
      static fn(array $chat): array => $chat
    );

    $prefix_service = new EncounterTranscriptPrefixService();

    $projector = new RoomChatHistoryProjector();
    $history = $projector->projectHistory(
      [
        'rooms' => [[
          'room_id' => 'tavern',
          'name' => 'The Gilded Tankard',
          'description' => 'Warm candlelight washes over the empty tavern.',
          'chat' => [
            [
              'speaker' => 'Narrator',
              'message' => 'A server slips through the kitchen door with a fresh kettle.',
              'type' => 'narrator',
              'channel' => 'room',
              'timestamp' => '2026-07-29T12:00:00+00:00',
            ],
          ],
        ]],
      ],
      'tavern',
      'room',
      NULL,
      $channel_manager,
      $prefix_service
    );

    $this->assertCount(1, $history);
    $this->assertSame('Narrator', $history[0]['speaker']);
    $this->assertSame('A server slips through the kitchen door with a fresh kettle.', $history[0]['message']);
  }

}
