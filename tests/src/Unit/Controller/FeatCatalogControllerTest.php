<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\dungeoncrawler_content\Controller\FeatCatalogController;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Controller\FeatCatalogController
 */
class FeatCatalogControllerTest extends UnitTestCase {

  /**
   * @covers ::get
   */
  public function testGetReturnsLocalFeatWhenPresent(): void {
    $controller = new FeatCatalogController();

    $response = $controller->get('toughness');
    $data = json_decode($response->getContent(), TRUE);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('Toughness', $data['name']);
  }

  /**
   * @covers ::get
   */
  public function testGetReturnsArchivesOfNethysFallbackWhenFeatIsMissing(): void {
    $controller = new FeatCatalogController();

    $response = $controller->get('imaginary-feat');
    $data = json_decode($response->getContent(), TRUE);

    $this->assertSame(404, $response->getStatusCode());
    $this->assertTrue($data['not_in_catalog']);
    $this->assertSame('archives_of_nethys', $data['fallback_lookup']['provider']);
    $this->assertSame('imaginary feat', $data['fallback_lookup']['query']);
    $this->assertSame('https://2e.aonprd.com/Feats.aspx', $data['fallback_lookup']['feats_url']);
    $this->assertSame('https://2e.aonprd.com/Search.aspx?Query=imaginary%20feat', $data['fallback_lookup']['search_url']);
  }

}
