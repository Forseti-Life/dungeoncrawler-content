<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\dungeoncrawler_content\Controller\FeatCatalogController;
use Drupal\dungeoncrawler_content\Service\FeatLibraryService;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Controller\FeatCatalogController
 */
class FeatCatalogControllerTest extends UnitTestCase {

  /**
   * @covers ::get
   */
  public function testGetReturnsLocalFeatWhenPresent(): void {
    $library = new class extends FeatLibraryService {

      public function __construct() {}

      public function getFeat(string $feat_id): ?array {
        if ($feat_id === 'toughness') {
          return [
            'id' => 'toughness',
            'name' => 'Toughness',
            'type' => 'general',
            'source_book' => 'crb',
          ];
        }
        return NULL;
      }

    };
    $controller = new FeatCatalogController($library);

    $response = $controller->get('toughness');
    $data = json_decode($response->getContent(), TRUE);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('Toughness', $data['name']);
  }

  /**
   * @covers ::get
   */
  public function testGetReturnsArchivesOfNethysFallbackWhenFeatIsMissing(): void {
    $library = new class extends FeatLibraryService {

      public function __construct() {}

      public function getFeat(string $feat_id): ?array {
        return NULL;
      }

    };
    $controller = new FeatCatalogController($library);

    $response = $controller->get('imaginary-feat');
    $data = json_decode($response->getContent(), TRUE);

    $this->assertSame(404, $response->getStatusCode());
    $this->assertTrue($data['not_in_catalog']);
    $this->assertSame('archives_of_nethys', $data['fallback_lookup']['provider']);
    $this->assertSame('imaginary feat', $data['fallback_lookup']['query']);
    $this->assertSame('https://2e.aonprd.com/Feats.aspx', $data['fallback_lookup']['feats_url']);
    $this->assertSame('https://2e.aonprd.com/Search.aspx?Query=imaginary%20feat', $data['fallback_lookup']['search_url']);
  }

  /**
   * @covers ::catalog
   */
  public function testCatalogUsesRegistryBackedLibraryFilters(): void {
    $library = new class extends FeatLibraryService {

      public function __construct() {}

      public function getFeats(array $filters = []): array {
        Assert::assertSame([
          'source_book' => 'apg',
          'type' => 'skill',
        ], $filters);

        return [[
          'id' => 'battle-medicine',
          'name' => 'Battle Medicine',
          'type' => 'skill',
          'source_book' => 'apg',
        ]];
      }

    };
    $controller = new FeatCatalogController($library);
    $request = Request::create('/api/feats', 'GET', [
      'source_book' => 'apg',
      'type' => 'skill',
    ]);

    $response = $controller->catalog($request);
    $data = json_decode($response->getContent(), TRUE);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(1, $data['count']);
    $this->assertSame('skill', $data['type']);
    $this->assertSame('Battle Medicine', $data['feats'][0]['name']);
  }

}
