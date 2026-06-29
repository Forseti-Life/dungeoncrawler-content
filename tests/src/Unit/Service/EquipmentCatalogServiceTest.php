<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\EquipmentCatalogService;
use Drupal\Tests\UnitTestCase;

/**
 * @group dungeoncrawler_content
 * @group equipment
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\EquipmentCatalogService
 */
class EquipmentCatalogServiceTest extends UnitTestCase {

  /**
   * @covers ::itemMatchesSourceBook
   */
  public function testItemMatchesSourceBookDefaultsMissingSourceToCrb(): void {
    $service = new EquipmentCatalogService();
    $method = new \ReflectionMethod($service, 'itemMatchesSourceBook');
    $method->setAccessible(TRUE);

    $this->assertTrue($method->invoke($service, ['id' => 'club'], 'crb'));
    $this->assertFalse($method->invoke($service, ['id' => 'club'], 'gng'));
    $this->assertTrue($method->invoke($service, ['id' => 'musket', 'source_book' => 'gng'], 'gng'));
  }

  /**
   * @covers ::getBySourceBook
   * @covers ::getByCriteria
   */
  public function testSourceBookFilteringMatchesAcrossPublicMethods(): void {
    $service = new EquipmentCatalogService();

    $this->assertSame(
      $service->getBySourceBook('gng'),
      $service->getByCriteria(NULL, 'gng')
    );
  }

  /**
   * @covers ::getByType
   * @covers ::getByCriteria
   */
  public function testCriteriaAllSourceLeavesTypeFilteringIntact(): void {
    $service = new EquipmentCatalogService();

    $this->assertSame(
      $service->getByType('weapon'),
      $service->getByCriteria('weapon', 'all')
    );
  }

}
