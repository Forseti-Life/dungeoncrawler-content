<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\dungeoncrawler_content\Controller\FocusSpellCatalogController;
use Drupal\dungeoncrawler_content\Service\SpellCatalogService;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Controller\FocusSpellCatalogController
 */
class FocusSpellCatalogControllerTest extends UnitTestCase {

  /**
   * @covers ::catalog
   */
  public function testCatalogReturnsServiceUnavailableWhenRegistryIsNotReady(): void {
    $controller = new FocusSpellCatalogController(new FailingFocusSpellCatalogService());

    $response = $controller->catalog(new Request());
    $data = json_decode($response->getContent(), TRUE);

    $this->assertSame(503, $response->getStatusCode());
    $this->assertSame('Spell registry contains no spell records.', $data['error']);
  }

  /**
   * @covers ::catalog
   */
  public function testCatalogFiltersRegistryBackedFocusSpellsAndAddsRangerPoolInfo(): void {
    $controller = new FocusSpellCatalogController(new StubFocusSpellCatalogService([
      [
        'id' => 'hymn-of-healing',
        'name' => 'Hymn of Healing',
        'rank' => 1,
        'spell_type' => 'focus',
        'source_book' => 'advanced_players_guide',
        'focus_class' => 'bard',
        'traditions' => ['occult'],
        'traits' => ['composition', 'healing'],
      ],
      [
        'id' => 'force-fang',
        'name' => 'Force Fang',
        'rank' => 1,
        'spell_type' => 'focus',
        'source_book' => 'secrets_of_magic',
        'focus_class' => 'none',
        'traditions' => ['arcane'],
        'traits' => ['magus', 'force'],
      ],
      [
        'id' => 'enlarge-companion',
        'name' => 'Enlarge Companion',
        'rank' => 4,
        'spell_type' => 'focus',
        'source_book' => 'advanced_players_guide',
        'focus_class' => 'ranger',
        'traditions' => ['primal'],
        'traits' => ['polymorph'],
      ],
    ]));

    $response = $controller->catalog(new Request([
      'source_book' => 'som',
      'class' => 'magus',
    ]));
    $data = json_decode($response->getContent(), TRUE);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(1, $data['count']);
    $this->assertSame('force-fang', $data['items'][0]['id']);
    $this->assertSame('magus', $data['items'][0]['class']);
    $this->assertSame('som', $data['items'][0]['source_book']);

    $rangerResponse = $controller->catalog(new Request([
      'source_book' => 'apg',
      'class' => 'ranger',
    ]));
    $rangerData = json_decode($rangerResponse->getContent(), TRUE);

    $this->assertSame(200, $rangerResponse->getStatusCode());
    $this->assertSame(1, $rangerData['count']);
    $this->assertSame('enlarge-companion', $rangerData['items'][0]['id']);
    $this->assertSame('10 minutes spent in nature', $rangerData['items'][0]['pool_info']['refocus_method']);
  }

}

class FailingFocusSpellCatalogService extends SpellCatalogService {

  public function __construct() {}

  public function getSpells(array $filters = []): array {
    throw new \RuntimeException('Spell registry contains no spell records.');
  }

}

class StubFocusSpellCatalogService extends SpellCatalogService {

  /**
   * @var array<int, array<string, mixed>>
   */
  private array $stubSpells;

  /**
   * @param array<int, array<string, mixed>> $spells
   *   Stub focus spell rows.
   */
  public function __construct(array $spells) {
    $this->stubSpells = $spells;
  }

  public function getSpells(array $filters = []): array {
    return $this->stubSpells;
  }

}
