<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\RoomChatService;
use Drupal\Tests\UnitTestCase;

/**
 * Covers room transcript projection ordering.
 *
 * @group dungeoncrawler_content
 * @group room_chat
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\RoomChatService
 */
class RoomChatServiceTranscriptProjectionTest extends UnitTestCase {

  /**
   * Projected encounter transcript lines merge into chronological room history.
   *
   * @covers ::appendProjectedEncounterEventTranscript
   * @covers ::parseRoomTranscriptTimestamp
   */
  public function testProjectedEncounterTranscriptMergesChronologically(): void {
    $service = new class extends RoomChatService {
      public function __construct() {}

      public function exposedAppendProjectedEncounterEventTranscript(
        array $messages,
        array $dungeon_data,
        string $room_id,
        ?int $character_id,
        int &$next_sequence_index
      ): array {
        return $this->appendProjectedEncounterEventTranscript(
          $messages,
          $dungeon_data,
          $room_id,
          $character_id,
          $next_sequence_index
        );
      }
    };

    $messages = [
      [
        'speaker' => 'Eldric',
        'message' => 'Room response one.',
        'type' => 'npc',
        'message_class' => 'authoritative_transcript',
        'channel' => 'room',
        'timestamp' => '2026-06-30T10:00:05+00:00',
        'sequence_index' => 1,
        'character_id' => NULL,
        'user_id' => 0,
        'internal_log' => FALSE,
      ],
      [
        'speaker' => 'Marta',
        'message' => 'Room response two.',
        'type' => 'npc',
        'message_class' => 'authoritative_transcript',
        'channel' => 'room',
        'timestamp' => '2026-06-30T10:00:15+00:00',
        'sequence_index' => 2,
        'character_id' => NULL,
        'user_id' => 0,
        'internal_log' => FALSE,
      ],
    ];
    $dungeon_data = [
      'event_log' => [
        [
          'type' => 'search',
          'phase' => 'encounter',
          'timestamp' => '2026-06-30T10:00:00+00:00',
          'narration' => 'Search step one.',
          'data' => [
            'room_id' => 'room-a',
          ],
        ],
        [
          'type' => 'search',
          'phase' => 'encounter',
          'timestamp' => '2026-06-30T10:00:10+00:00',
          'narration' => 'Search step two.',
          'data' => [
            'room_id' => 'room-a',
          ],
        ],
      ],
    ];

    $next_sequence_index = 2;
    $merged = $service->exposedAppendProjectedEncounterEventTranscript(
      $messages,
      $dungeon_data,
      'room-a',
      NULL,
      $next_sequence_index
    );

    $this->assertSame(
      ['Narrator', 'Eldric', 'Narrator', 'Marta'],
      array_map(static fn(array $line): string => (string) ($line['speaker'] ?? ''), $merged)
    );
    $this->assertSame(
      ['Search step one.', 'Room response one.', 'Search step two.', 'Room response two.'],
      array_map(static fn(array $line): string => (string) ($line['message'] ?? ''), $merged)
    );
    $this->assertSame([1, 2, 3, 4], array_map(
      static fn(array $line): int => (int) ($line['sequence_index'] ?? 0),
      $merged
    ));
    $this->assertSame(4, $next_sequence_index);
    $this->assertArrayNotHasKey('_projection_order', $merged[0]);
    $this->assertArrayNotHasKey('_room_order', $merged[0]);
  }

}

