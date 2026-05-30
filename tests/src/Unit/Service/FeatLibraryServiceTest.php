<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\FeatLibraryService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\FeatLibraryService
 */
class FeatLibraryServiceTest extends UnitTestCase {

  /**
   * @covers ::getFeat
   * @covers ::getRegistryCatalog
   */
  public function testGetFeatFailsWhenRegistryIsUnavailable(): void {
    $service = new FeatLibraryService();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Feat registry database connection is unavailable.');
    $service->getFeat('toughness');
  }

  /**
   * @covers ::buildRegistryFeatRecord
   */
  public function testBuildRegistryFeatRecordNormalizesRegistryData(): void {
    $service = new class extends FeatLibraryService {

      public function __construct() {}

      public function buildRecordForTest(object $row): ?array {
        return $this->buildRegistryFeatRecord($row);
      }

    };

    $record = $service->buildRecordForTest((object) [
      'content_id' => 'battle_medicine',
      'name' => 'Battle Medicine',
      'level' => 1,
      'rarity' => '',
      'source_book' => '',
      'schema_data' => json_encode([
        'name' => 'Battle Medicine',
        'type' => 'Skill',
        'traits' => ['General', 'Healing', 'general', ''],
        'prerequisites' => '',
        'description_snippet' => 'Patch someone up in combat.',
      ]),
    ]);

    $this->assertNotNull($record);
    $this->assertSame('battle-medicine', $record['id']);
    $this->assertSame('skill', $record['type']);
    $this->assertSame('crb', $record['source_book']);
    $this->assertSame('common', $record['rarity']);
    $this->assertSame('none', $record['prerequisites']);
    $this->assertSame(['general', 'healing'], $record['traits']);
    $this->assertSame('Patch someone up in combat.', $record['description']);
    $this->assertSame('description_snippet', $record['description_source']);
  }

  /**
   * @covers ::getClassFeats
   * @covers ::getSkillFeats
   * @covers ::getGeneralFeats
   * @covers ::getAncestryFeats
   */
  public function testTypedHelpersFilterNormalizedContextIds(): void {
    $service = new class extends FeatLibraryService {

      public function __construct() {}

      protected function getRegistryCatalog(): array {
        return [
          'power-attack' => [
            'id' => 'power-attack',
            'name' => 'Power Attack',
            'type' => 'class',
            'class_id' => 'fighter',
          ],
          'battle-medicine' => [
            'id' => 'battle-medicine',
            'name' => 'Battle Medicine',
            'type' => 'skill',
          ],
          'toughness' => [
            'id' => 'toughness',
            'name' => 'Toughness',
            'type' => 'general',
          ],
          'elven-lore' => [
            'id' => 'elven-lore',
            'name' => 'Elven Lore',
            'type' => 'ancestry',
            'ancestry_id' => 'elf',
          ],
        ];
      }

    };

    $this->assertSame(['power-attack'], array_column($service->getClassFeats('Fighter'), 'id'));
    $this->assertSame(['battle-medicine'], array_column($service->getSkillFeats(), 'id'));
    $this->assertSame(['toughness'], array_column($service->getGeneralFeats(), 'id'));
    $this->assertSame(['elven-lore'], array_column($service->getAncestryFeats('Elf'), 'id'));
  }

}
