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
          'cast' => '[two-actions] somatic, verbal',
          'range' => '30 feet',
          'duration' => 'sustained up to 10 minutes',
          'save' => 'none',
          'components' => ['somatic', 'verbal'],
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
          'cast' => '[two-actions] somatic, verbal',
          'range' => '30 feet',
          'duration' => 'sustained up to 10 minutes',
          'save' => 'none',
          'components' => ['somatic', 'verbal'],
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
    $schema = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['tableExists'])
      ->getMock();
    $schema->method('tableExists')->with('dungeoncrawler_content_registry')->willReturn(TRUE);
    $database->method('schema')->willReturn($schema);

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
    $this->assertSame('[two-actions] somatic, verbal', $spells[0]['cast']);
    $this->assertSame('30 feet', $spells[0]['range']);
    $this->assertSame('sustained up to 10 minutes', $spells[0]['duration']);
    $this->assertSame('none', $spells[0]['save']);
    $this->assertSame(['somatic', 'verbal'], $spells[0]['components']);
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
    $schema = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['tableExists'])
      ->getMock();
    $schema->method('tableExists')->with('dungeoncrawler_content_registry')->willReturn(TRUE);
    $database->method('schema')->willReturn($schema);

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

  /**
   * @covers ::normalizePersistentCharacterPayload
   */
  public function testNormalizePersistentCharacterPayloadHardensFeatSelectionsAndSpellResources(): void {
    $payload = CharacterManager::normalizePersistentCharacterPayload([
      'basicInfo' => [
        'class' => 'wizard',
      ],
      'features' => [
        'feats' => [
          'Adapted Cantrip',
          [
            'id' => 'Toughness',
            'name' => 'Toughness',
          ],
        ],
        'featSelections' => [
          'Adapted Cantrip' => [
            'selected_tradition' => 'occult',
            'selected_cantrip' => 'daze',
          ],
          'broken-entry' => 'not-an-array',
          7 => [
            'feat_id' => 'Weapon Proficiency',
            'selected_weapon' => 'longsword',
          ],
        ],
      ],
      'spells' => [
        'cantrips' => ['shield', '', 'detect-magic'],
        'first_level' => ['magic-missile'],
        'slots' => ['1st' => 2],
      ],
      'resources' => [
        'spellSlots' => [
          '1st' => ['current' => 5, 'max' => 1],
        ],
        'focusPoints' => [
          'current' => 3,
          'max' => 1,
        ],
      ],
    ]);

    $this->assertSame(['shield', 'detect-magic'], $payload['cantrips']);
    $this->assertSame(['magic-missile'], $payload['spells_first']);
    $this->assertArrayHasKey('adapted-cantrip', $payload['feat_selections']);
    $this->assertArrayHasKey('weapon-proficiency', $payload['feat_selections']);
    $this->assertArrayNotHasKey('broken-entry', $payload['feat_selections']);
    $this->assertSame(2, $payload['resources']['spellSlots']['1']['max']);
    $this->assertSame(1, $payload['resources']['spellSlots']['1']['current']);
    $this->assertSame(1, $payload['spells']['slots_used']['first']);
    $this->assertSame(1, $payload['resources']['focusPoints']['current']);
    $this->assertSame(1, $payload['resources']['focusPoints']['max']);
  }

  /**
   * @covers ::normalizePersistentCharacterPayload
   */
  public function testNormalizePersistentCharacterPayloadRepairsLegacySpellUsageFromCanonicalResources(): void {
    $payload = CharacterManager::normalizePersistentCharacterPayload([
      'spells' => [
        'slots' => ['first' => 2, 'second' => 1],
        'slots_used' => ['first' => 0, 'second' => 1],
      ],
      'resources' => [
        'spellSlots' => [
          '1' => ['current' => 1, 'max' => 2],
          '2' => ['current' => 1, 'max' => 1],
        ],
      ],
    ]);

    $this->assertSame(['current' => 1, 'max' => 2], $payload['resources']['spellSlots']['1']);
    $this->assertSame(['current' => 1, 'max' => 1], $payload['resources']['spellSlots']['2']);
    $this->assertSame(1, $payload['spells']['slots_used']['first']);
    $this->assertSame(0, $payload['spells']['slots_used']['second']);
  }

  /**
   * @covers ::normalizeCurrencyDenominations
   * @covers ::currencyDenominationsToGoldValue
   */
  public function testNormalizeCurrencyDenominationsConvertsFractionalGoldToWholeBuckets(): void {
    $currency = CharacterManager::normalizeCurrencyDenominations(['gp' => 0.04]);

    $this->assertSame([
      'cp' => 4,
      'sp' => 0,
      'gp' => 0,
      'pp' => 0,
    ], $currency);
    $this->assertSame(0.04, CharacterManager::currencyDenominationsToGoldValue($currency));
  }

}
