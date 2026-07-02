<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\ChatSessionManager;
use Drupal\dungeoncrawler_content\Service\GameplayActionProcessor;
use Drupal\dungeoncrawler_content\Service\NarrationEngine;
use Drupal\dungeoncrawler_content\Service\NumberGenerationService;
use Drupal\Tests\UnitTestCase;
use Drupal\ai_conversation\Service\AIApiService;
use Drupal\ai_conversation\Service\PromptManager;
use Psr\Log\LoggerInterface;

/**
 * Verifies mechanical events are mirrored to system-log reliably.
 *
 * @group dungeoncrawler_content
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\NarrationEngine
 */
class NarrationEngineMechanicalSystemLogTest extends UnitTestCase {

  /**
   * Ensures queueRoomEvent creates missing campaign/system sessions on demand.
   *
   * @covers ::queueRoomEvent
   */
  public function testMechanicalEventEnsuresSystemLogSessionExists(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')
      ->with('dungeoncrawler_narration')
      ->willReturn($logger);

    $session_manager = $this->createMock(ChatSessionManager::class);

    $session_manager->expects($this->once())
      ->method('ensureRoomSession')
      ->with(77, 5, 'room-a')
      ->willReturn(['id' => 101]);

    $session_manager->expects($this->once())
      ->method('systemLogSessionKey')
      ->with(77)
      ->willReturn('campaign.77.system_log');

    $session_manager->expects($this->once())
      ->method('ensureCampaignSessions')
      ->with(77);

    $session_manager->expects($this->exactly(2))
      ->method('loadSession')
      ->with('campaign.77.system_log')
      ->willReturnOnConsecutiveCalls(
        NULL,
        ['id' => 202]
      );

    $post_calls = [];
    $session_manager->expects($this->exactly(2))
      ->method('postMessage')
      ->willReturnCallback(function (
        int $session_id,
        int $campaign_id,
        string $speaker,
        string $speaker_type,
        string $speaker_ref,
        string $message,
        string $message_type = 'narrative',
        string $visibility = 'public',
        array $metadata = [],
        bool $feed_up = TRUE
      ) use (&$post_calls): int {
        $post_calls[] = [
          'session_id' => $session_id,
          'campaign_id' => $campaign_id,
          'speaker' => $speaker,
          'speaker_type' => $speaker_type,
          'speaker_ref' => $speaker_ref,
          'message' => $message,
          'message_type' => $message_type,
          'visibility' => $visibility,
          'metadata' => $metadata,
          'feed_up' => $feed_up,
        ];
        return count($post_calls);
      });

    $engine = new NarrationEngine(
      $this->createMock(Connection::class),
      $logger_factory,
      $session_manager,
      $this->createMock(AIApiService::class),
      $this->createMock(PromptManager::class),
      $this->createMock(GameplayActionProcessor::class),
      $this->createMock(NumberGenerationService::class)
    );

    $result = $engine->queueRoomEvent(
      77,
      5,
      'room-a',
      [
        'type' => 'dice_roll',
        'speaker' => 'System',
        'speaker_type' => 'system',
        'speaker_ref' => '',
        'content' => 'Attack roll 19 vs AC 17: success.',
        'visibility' => 'public',
        'mechanical_data' => [
          'action' => 'strike',
          'roll' => 15,
          'total' => 19,
          'dc' => 17,
          'degree' => 'success',
        ],
      ],
      []
    );

    $this->assertTrue($result['event_recorded']);
    $this->assertCount(2, $post_calls);
    $this->assertSame(101, $post_calls[0]['session_id'], 'Room session receives primary event.');
    $this->assertSame(202, $post_calls[1]['session_id'], 'System-log session receives mirrored mechanical event.');
    $this->assertSame('mechanical', $post_calls[1]['message_type']);
    $this->assertFalse($post_calls[1]['feed_up']);
  }

}

