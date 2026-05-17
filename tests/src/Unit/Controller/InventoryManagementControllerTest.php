<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Controller\InventoryManagementController;
use Drupal\dungeoncrawler_content\Service\CharacterStateService;
use Drupal\dungeoncrawler_content\Service\InventoryManagementService;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests inventory controller bulk and encumbrance responses.
 *
 * @group dungeoncrawler_content
 * @group inventory
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Controller\InventoryManagementController
 */
class InventoryManagementControllerTest extends UnitTestCase {

  /**
   * @covers ::getInventory
   */
  public function testGetInventoryPassesCampaignIdToBulkCalculation(): void {
    $inventory_service = $this->createMock(InventoryManagementService::class);
    $inventory_service->expects($this->once())
      ->method('getInventory')
      ->with('char-1', 'character', 42)
      ->willReturn([
        'carried' => [],
        'worn' => [],
        'currency' => ['cp' => 0, 'sp' => 0, 'gp' => 0, 'pp' => 0],
      ]);
    $inventory_service->expects($this->once())
      ->method('calculateCurrentBulk')
      ->with('char-1', 'character', 42)
      ->willReturn(6.0);
    $inventory_service->expects($this->once())
      ->method('getInventoryCapacity')
      ->with('char-1', 'character')
      ->willReturn(12.0);
    $inventory_service->expects($this->once())
      ->method('getEncumbranceStatus')
      ->with(6.0, 14.0)
      ->willReturn('unencumbered');

    $character_state = $this->createMock(CharacterStateService::class);
    $character_state->expects($this->once())
      ->method('getState')
      ->with('char-1')
      ->willReturn([
        'abilities' => [
          'strength' => 14,
        ],
      ]);

    $controller = new InventoryManagementController(
      $inventory_service,
      $character_state,
      $this->createMock(Connection::class),
    );

    $response = $controller->getInventory('character', 'char-1', new Request([
      'campaign_id' => '42',
    ]));
    $payload = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertTrue($payload['success']);
    $this->assertSame(6, $payload['bulk']['current']);
    $this->assertSame(12, $payload['bulk']['capacity']);
    $this->assertSame('unencumbered', $payload['bulk']['encumbrance']);
  }

}
