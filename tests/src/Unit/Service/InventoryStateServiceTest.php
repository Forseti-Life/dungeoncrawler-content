<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\InventoryManagementService;
use Drupal\dungeoncrawler_content\Service\InventoryStateService;
use PHPUnit\Framework\TestCase;

/**
 * @group dungeoncrawler_content
 * @group inventory_state
 */
class InventoryStateServiceTest extends TestCase {

  public function testGetStateDelegatesToInventoryManagementService(): void {
    $inventory = $this->createMock(InventoryManagementService::class);
    $inventory->expects($this->once())
      ->method('getInventory')
      ->with('4928', 'character', 845)
      ->willReturn(['carried' => []]);

    $service = new InventoryStateService($inventory);
    $result = $service->getState('4928', 'character', 845);

    $this->assertSame(['carried' => []], $result);
  }

}
