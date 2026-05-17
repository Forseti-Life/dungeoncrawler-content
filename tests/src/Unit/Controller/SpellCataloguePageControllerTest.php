<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Controller\SpellCataloguePageController;
use Drupal\Tests\UnitTestCase;

/**
 * Tests registry normalization for the public spell catalogue page.
 *
 * @group dungeoncrawler_content
 * @group spells
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Controller\SpellCataloguePageController
 */
class SpellCataloguePageControllerTest extends UnitTestCase {

  /**
   * @covers ::normalizeRegistrySpellRows
   */
  public function testNormalizeRegistrySpellRowsDeduplicatesLegacySpellRecords(): void {
    $controller = new InspectableSpellCataloguePageController($this->createMock(Connection::class));

    $spells = $controller->exposeNormalizeRegistrySpellRows([
      (object) [
        'content_id' => 'magic_missile',
        'name' => 'Magic Missile',
        'level' => 1,
        'rarity' => 'common',
        'tags' => '["arcane","occult"]',
        'schema_data' => json_encode([
          'rank' => 1,
          'spell_type' => 'spell',
          'school' => 'evocation',
          'rarity' => 'common',
          'traditions' => ['arcane', 'occult'],
          'description_snippet' => 'Pelt creatures',
        ]),
        'source_file' => 'legacy.json',
        'version' => 'legacy',
      ],
      (object) [
        'content_id' => 'magic-missile',
        'name' => 'Magic Missile',
        'level' => 1,
        'rarity' => 'common',
        'tags' => '["arcane","occult"]',
        'schema_data' => json_encode([
          'rank' => 1,
          'spell_type' => 'spell',
          'school' => 'evocation',
          'rarity' => 'common',
          'traditions' => ['arcane', 'occult'],
          'description' => 'You send a dart of force streaking toward a creature that you can see.',
          'description_snippet' => 'Pelt creatures',
        ]),
        'source_file' => 'canonical.json',
        'version' => 'canonical',
      ],
      (object) [
        'content_id' => 'shield_c',
        'name' => 'Shield C',
        'level' => 0,
        'rarity' => 'rare',
        'tags' => '["primal"]',
        'schema_data' => json_encode([
          'rank' => 0,
          'spell_type' => 'cantrip',
          'school' => 'abjuration',
          'rarity' => 'rare',
          'traditions' => ['primal'],
          'description_snippet' => 'A shield of magical force blocks attacks',
        ]),
        'source_file' => 'som.json',
        'version' => 'legacy',
      ],
    ]);

    $this->assertCount(1, $spells);
    $spell = array_values($spells)[0];
    $this->assertSame('magic-missile', $spell['content_id']);
    $this->assertSame(
      'You send a dart of force streaking toward a creature that you can see.',
      $spell['schema_data']['description']
    );
  }

  /**
   * @covers ::normalizeRegistrySpellRows
   */
  public function testNormalizeRegistrySpellRowsAppendsOutcomeSummary(): void {
    $controller = new InspectableSpellCataloguePageController($this->createMock(Connection::class));

    $spells = $controller->exposeNormalizeRegistrySpellRows([
      (object) [
        'content_id' => 'tanglefoot',
        'name' => 'Tanglefoot',
        'level' => 0,
        'rarity' => 'common',
        'tags' => '["arcane","primal","attack","cantrip","conjuration"]',
        'schema_data' => json_encode([
          'rank' => 0,
          'spell_type' => 'cantrip',
          'school' => 'conjuration',
          'rarity' => 'common',
          'traditions' => ['arcane', 'primal'],
          'description' => 'A vine covered in sticky sap appears from thin air, flicking from your hand and lashing itself to the target. Attempt a spell attack against the target.',
          'effects' => [
            'outcomes' => [
              'Critical Success' => 'The target gains the immobilized condition and takes a -10-foot circumstance penalty to its Speeds for 1 round.',
              'Success' => 'The target takes a -10-foot circumstance penalty to its Speeds for 1 round.',
              'Failure' => 'The target is unaffected.',
            ],
          ],
        ]),
        'source_file' => 'canonical.json',
        'version' => 'canonical',
      ],
    ]);

    $this->assertCount(1, $spells);
    $spell = array_values($spells)[0];
    $this->assertStringContainsString('Critical Success:', $spell['schema_data']['description']);
    $this->assertStringContainsString('Failure: The target is unaffected.', $spell['schema_data']['description']);
  }

}

class InspectableSpellCataloguePageController extends SpellCataloguePageController {

  /**
   * Exposes registry normalization for unit coverage.
   *
   * @param array<int, object> $rows
   *   Raw database rows.
   *
   * @return array<string, array<string, mixed>>
   *   Normalized rows keyed by canonical ID.
   */
  public function exposeNormalizeRegistrySpellRows(array $rows): array {
    return $this->normalizeRegistrySpellRows($rows);
  }

}
