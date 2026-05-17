<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\Tests\UnitTestCase;

/**
 * Tests spell-catalog reads used by the wizard spell picker.
 *
 * @group dungeoncrawler_content
 * @group spells
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\CharacterManager
 */
class CharacterManagerSpellCatalogTest extends UnitTestCase {

  /**
   * @covers ::getSpellsByTradition
   */
  public function testGetSpellsByTraditionDeduplicatesLegacyRowsAndPrefersFullDescriptions(): void {
    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAll')->willReturn([
      (object) [
        'content_id' => 'detect_magic',
        'name' => 'Detect Magic',
        'level' => 0,
        'tags' => '["arcane","divine","occult","primal"]',
        'schema_data' => json_encode([
          'traditions' => ['arcane', 'divine', 'occult', 'primal'],
          'school' => 'divination',
          'rarity' => 'common',
          'description_snippet' => 'Sense whether',
          'source_display' => 'Core Rulebook (4th Printing)',
        ]),
      ],
      (object) [
        'content_id' => 'detect-magic',
        'name' => 'Detect Magic',
        'level' => 0,
        'tags' => '["arcane","divine","occult","primal"]',
        'schema_data' => json_encode([
          'traditions' => ['arcane', 'divine', 'occult', 'primal'],
          'school' => 'divination',
          'rarity' => 'common',
          'description' => 'Sense whether magic is nearby and determine the strength of the aura.',
          'description_snippet' => 'Sense whether',
          'source_display' => 'Core Rulebook (Fourth Printing)',
        ]),
      ],
      (object) [
        'content_id' => 'shield',
        'name' => 'Shield',
        'level' => 0,
        'tags' => '["arcane","divine","occult"]',
        'schema_data' => json_encode([
          'traditions' => ['arcane', 'divine', 'occult'],
          'school' => 'abjuration',
          'rarity' => 'common',
          'description_snippet' => 'A shield of magical force',
          'source_display' => 'Core Rulebook (4th Printing)',
        ]),
      ],
    ]);

    $query = $this->createMock(SelectInterface::class);
    $query->method('fields')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('orderBy')->willReturnSelf();
    $query->method('execute')->willReturn($statement);

    $database = $this->createMock(Connection::class);
    $database->method('select')->with('dungeoncrawler_content_registry', 'r')->willReturn($query);
    $database->method('escapeLike')->willReturnArgument(0);

    $manager = new CharacterManager(
      $database,
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(UuidInterface::class),
    );

    $spells = $manager->getSpellsByTradition('arcane', 0);

    $this->assertCount(2, $spells);
    $this->assertSame(['detect-magic', 'shield'], array_column($spells, 'id'));
    $this->assertSame(
      'Sense whether magic is nearby and determine the strength of the aura.',
      $spells[0]['description']
    );
    $this->assertSame('description', $spells[0]['description_source']);
    $this->assertSame('A shield of magical force', $spells[1]['description']);
    $this->assertSame('description_snippet', $spells[1]['description_source']);
  }

  /**
   * @covers ::getSpellsByTradition
   */
  public function testGetSpellsByTraditionAppendsOutcomeSummaryForOutcomeHeavySpells(): void {
    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAll')->willReturn([
      (object) [
        'content_id' => 'tanglefoot',
        'name' => 'Tanglefoot',
        'level' => 0,
        'tags' => '["arcane","primal","cantrip","attack","conjuration"]',
        'schema_data' => json_encode([
          'traditions' => ['arcane', 'primal'],
          'school' => 'conjuration',
          'rarity' => 'common',
          'description' => 'A vine covered in sticky sap appears from thin air, flicking from your hand and lashing itself to the target. Attempt a spell attack against the target.',
          'effects' => [
            'outcomes' => [
              'Critical Success' => 'The target gains the immobilized condition and takes a -10-foot circumstance penalty to its Speeds for 1 round.',
              'Success' => 'The target takes a -10-foot circumstance penalty to its Speeds for 1 round.',
              'Failure' => 'The target is unaffected.',
            ],
          ],
        ]),
      ],
    ]);

    $query = $this->createMock(SelectInterface::class);
    $query->method('fields')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('orderBy')->willReturnSelf();
    $query->method('execute')->willReturn($statement);

    $database = $this->createMock(Connection::class);
    $database->method('select')->with('dungeoncrawler_content_registry', 'r')->willReturn($query);
    $database->method('escapeLike')->willReturnArgument(0);

    $manager = new CharacterManager(
      $database,
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(UuidInterface::class),
    );

    $spells = $manager->getSpellsByTradition('arcane', 0);

    $this->assertCount(1, $spells);
    $this->assertStringContainsString('Critical Success:', $spells[0]['description']);
    $this->assertStringContainsString('Success: The target takes a -10-foot circumstance penalty', $spells[0]['description']);
    $this->assertStringContainsString('Failure: The target is unaffected.', $spells[0]['description']);
  }

}
