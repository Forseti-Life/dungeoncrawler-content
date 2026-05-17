<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\dungeoncrawler_content\Controller\RoomChatController;
use Drupal\dungeoncrawler_content\Service\RoomChatService;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests player automation suggestion controller responses.
 *
 * @group dungeoncrawler_content
 * @group controller
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Controller\RoomChatController
 */
class RoomChatControllerSuggestionTest extends UnitTestCase {

  /**
   * @covers ::suggestPlayerAutomationMessage
   */
  public function testSuggestPlayerAutomationMessageReturnsSuggestionPayload(): void {
    $chat_service = $this->createMock(RoomChatService::class);
    $chat_service->expects($this->once())
      ->method('hasCampaignAccess')
      ->with(63)
      ->willReturn(TRUE);
    $chat_service->expects($this->once())
      ->method('suggestPlayerAutomationMessage')
      ->with(63, 'room-1', 218, 'room')
      ->willReturn([
        'message' => 'Let me take point and see what stirred that dust.',
        'character_name' => 'Brakouk',
        'channel' => 'room',
      ]);

    $controller = new RoomChatController($chat_service);
    $request = Request::create(
      '/api/campaign/63/room/room-1/chat/player-suggestion',
      'POST',
      [],
      [],
      [],
      [],
      json_encode([
        'character_id' => 218,
        'channel' => 'room',
      ])
    );

    $response = $controller->suggestPlayerAutomationMessage(63, 'room-1', $request);
    $payload = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertTrue($payload['success']);
    $this->assertSame('Brakouk', $payload['data']['character_name']);
    $this->assertSame('Let me take point and see what stirred that dust.', $payload['data']['message']);
  }

  /**
   * @covers ::suggestPlayerAutomationMessage
   */
  public function testSuggestPlayerAutomationMessageRejectsMissingCharacterId(): void {
    $chat_service = $this->createMock(RoomChatService::class);
    $chat_service->expects($this->once())
      ->method('hasCampaignAccess')
      ->with(63)
      ->willReturn(TRUE);
    $chat_service->expects($this->never())
      ->method('suggestPlayerAutomationMessage');

    $controller = new RoomChatController($chat_service);
    $request = Request::create(
      '/api/campaign/63/room/room-1/chat/player-suggestion',
      'POST',
      [],
      [],
      [],
      [],
      json_encode([])
    );

    $response = $controller->suggestPlayerAutomationMessage(63, 'room-1', $request);
    $payload = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(400, $response->getStatusCode());
    $this->assertFalse($payload['success']);
    $this->assertSame('character_id is required', $payload['error']);
  }

}
