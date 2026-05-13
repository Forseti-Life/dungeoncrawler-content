<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\dungeoncrawler_content\Controller\SpellCatalogController;
use Drupal\dungeoncrawler_content\Service\SpellCatalogService;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Controller\SpellCatalogController
 */
class SpellCatalogControllerTest extends UnitTestCase {

  /**
   * @covers ::list
   */
  public function testListReturnsServiceUnavailableWhenRegistryIsNotReady(): void {
    $controller = new SpellCatalogController(new FailingSpellCatalogService());

    $response = $controller->list(new Request());
    $data = json_decode($response->getContent(), TRUE);

    $this->assertSame(503, $response->getStatusCode());
    $this->assertSame('Spell registry contains no spell records.', $data['error']);
  }

  /**
   * @covers ::get
   */
  public function testGetReturnsServiceUnavailableWhenRegistryIsNotReady(): void {
    $controller = new SpellCatalogController(new FailingSpellCatalogService());

    $response = $controller->get('fireball');
    $data = json_decode($response->getContent(), TRUE);

    $this->assertSame(503, $response->getStatusCode());
    $this->assertSame('Spell registry contains no spell records.', $data['error']);
  }

}

class FailingSpellCatalogService extends SpellCatalogService {

  public function __construct() {}

  public function getSpells(array $filters = []): array {
    throw new \RuntimeException('Spell registry contains no spell records.');
  }

  public function getSpell(string $spell_id): ?array {
    throw new \RuntimeException('Spell registry contains no spell records.');
  }

}
