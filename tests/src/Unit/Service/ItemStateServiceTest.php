<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\InventoryManagementService;
use Drupal\dungeoncrawler_content\Service\ItemStateService;
use PHPUnit\Framework\TestCase;

/**
 * @group dungeoncrawler_content
 * @group item_state
 */
class ItemStateServiceTest extends TestCase {

  public function testGetStateDelegatesToInventoryManagementService(): void {
    $inventory = $this->createMock(InventoryManagementService::class);
    $inventory->expects($this->once())
      ->method('getItemState')
      ->with('item-123', 845)
      ->willReturn(['item_instance_id' => 'item-123']);

    $service = new ItemStateService($inventory);
    $result = $service->getState('item-123', 845);

    $this->assertSame('item-123', $result['item_instance_id']);
  }

}
