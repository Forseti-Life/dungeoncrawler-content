<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\dungeoncrawler_content\Controller\MerchantApiController;
use Drupal\dungeoncrawler_content\Service\MerchantTransactionService;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests merchant API controller wiring.
 *
 * @group dungeoncrawler_content
 * @group merchant
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Controller\MerchantApiController
 */
class MerchantApiControllerTest extends UnitTestCase {

  /**
   * @covers ::getContext
   */
  public function testGetContextPassesCharacterIdThrough(): void {
    $merchant_service = $this->createMock(MerchantTransactionService::class);
    $merchant_service->expects($this->once())
      ->method('getMerchantPanelContext')
      ->with(42, 'room-1', 'merchant-1', 99)
      ->willReturn([
        'merchant' => ['merchant_ref' => 'merchant-1', 'name' => 'Quartermaster Dain'],
        'stock' => [],
        'player' => ['character_id' => 99],
      ]);

    $controller = new MerchantApiController($merchant_service);
    $response = $controller->getContext(42, 'room-1', 'merchant-1', new Request([
      'character_id' => '99',
    ]));

    $payload = json_decode((string) $response->getContent(), TRUE);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertTrue($payload['success']);
    $this->assertSame('merchant-1', $payload['context']['merchant']['merchant_ref']);
  }

  /**
   * @covers ::searchCatalog
   */
  public function testSearchCatalogPassesQueryThrough(): void {
    $merchant_service = $this->createMock(MerchantTransactionService::class);
    $merchant_service->expects($this->once())
      ->method('searchMerchantCatalog')
      ->with(42, 'room-1', 'merchant-1', 'canteen')
      ->willReturn([[
        'item_id' => 'canteen',
        'name' => 'Canteen',
        'price_cp' => 50,
      ]]);

    $controller = new MerchantApiController($merchant_service);
    $response = $controller->searchCatalog(42, 'room-1', 'merchant-1', new Request([
      'query' => 'canteen',
    ]));

    $payload = json_decode((string) $response->getContent(), TRUE);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertTrue($payload['success']);
    $this->assertSame('canteen', $payload['items'][0]['item_id']);
  }

  /**
   * @covers ::transactPanel
   */
  public function testTransactPanelReturnsSharedBackendPayload(): void {
    $merchant_service = $this->createMock(MerchantTransactionService::class);
    $merchant_service->expects($this->once())
      ->method('executePanelTransaction')
      ->with(42, 'room-1', 'merchant-1', [
        'action' => 'buy',
        'item_id' => 'club',
        'character_id' => 99,
      ])
      ->willReturn([
        'success' => TRUE,
        'status' => 'completed_purchase',
        'message' => 'Purchased 1 Club for 0 cp.',
        'context' => [
          'merchant' => ['merchant_ref' => 'merchant-1'],
          'stock' => [],
          'player' => ['character_id' => 99],
        ],
      ]);

    $controller = new MerchantApiController($merchant_service);
    $request = Request::create(
      '/api/campaign/42/room/room-1/merchant/merchant-1/transaction',
      'POST',
      [],
      [],
      [],
      [],
      json_encode([
        'action' => 'buy',
        'item_id' => 'club',
        'character_id' => 99,
      ])
    );

    $response = $controller->transactPanel(42, 'room-1', 'merchant-1', $request);
    $payload = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertTrue($payload['success']);
    $this->assertSame('completed_purchase', $payload['status']);
  }

  /**
   * @covers ::transactChat
   */
  public function testTransactChatRequiresMessage(): void {
    $merchant_service = $this->createMock(MerchantTransactionService::class);
    $controller = new MerchantApiController($merchant_service);
    $request = Request::create(
      '/api/campaign/42/room/room-1/merchant/merchant-1/chat-transaction',
      'POST',
      [],
      [],
      [],
      [],
      json_encode([
        'character_id' => 99,
      ])
    );

    $response = $controller->transactChat(42, 'room-1', 'merchant-1', $request);
    $payload = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(400, $response->getStatusCode());
    $this->assertFalse($payload['success']);
    $this->assertSame('blocked', $payload['status']);
  }

}
