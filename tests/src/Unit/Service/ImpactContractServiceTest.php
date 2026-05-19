<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\ImpactContractService;
use Drupal\Tests\UnitTestCase;

/**
 * @group dungeoncrawler_content
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\ImpactContractService
 */
class ImpactContractServiceTest extends UnitTestCase {

  /**
   * @covers ::buildPersistentImpacts
   */
  public function testBuildPersistentImpactsReturnsCanonicalContracts(): void {
    $service = new ImpactContractService();

    $impacts = $service->buildPersistentImpacts(
      [
        'derived_adjustments' => [
          'hp_max_bonus' => 1,
          'speed_bonus' => 5,
        ],
        'spell_augments' => [
          'metamagic' => [
            ['id' => 'reach-spell', 'name' => 'Reach Spell'],
          ],
          'innate_spells' => [
            ['id' => 'adapted-cantrip', 'spell_id' => 'daze', 'tradition' => 'occult'],
          ],
        ],
        'senses' => [],
        'applied_feats' => ['toughness', 'fleet', 'adapted-cantrip'],
      ],
      [
        'armor' => [
          'item_id' => 'leather',
          'name' => 'Leather Armor',
          'armor_bonus' => 1,
          'dex_cap' => 4,
          'speed_penalty' => 0,
          'check_penalty' => -1,
        ],
        'shield' => [
          'item_id' => '',
          'name' => '',
          'shield_bonus' => 0,
        ],
        'accessories' => [],
      ],
      [
        'active' => [
          ['id' => 'c1', 'code' => 'frightened', 'label' => 'Frightened', 'value' => 1],
        ],
        'supported_adjustments' => [
          'armor_class' => -1,
          'speed' => 0,
        ],
        'unsupported' => [],
      ]
    );

    $armor_impact = array_values(array_filter(
      $impacts,
      fn (array $impact): bool => $impact['source_type'] === ImpactContractService::SOURCE_EQUIPMENT
        && $impact['target'] === ImpactContractService::TARGET_AC_ARMOR_BONUS
    ));
    $spell_impact = array_values(array_filter(
      $impacts,
      fn (array $impact): bool => $impact['source_type'] === ImpactContractService::SOURCE_SPELL_AUGMENT
        && $impact['target'] === ImpactContractService::TARGET_SPELLS_INNATE
    ));
    $condition_impact = array_values(array_filter(
      $impacts,
      fn (array $impact): bool => $impact['source_type'] === ImpactContractService::SOURCE_CONDITION
        && $impact['target'] === ImpactContractService::TARGET_AC_OTHER_BONUSES
    ));

    $this->assertNotEmpty($armor_impact);
    $this->assertSame(ImpactContractService::OPERATION_ADD, $armor_impact[0]['operation']);
    $this->assertSame(ImpactContractService::STACKING_ITEM, $armor_impact[0]['stacking']);
    $this->assertSame(ImpactContractService::PHASE_PERSISTENT_SHEET, $armor_impact[0]['phase']);

    $this->assertNotEmpty($spell_impact);
    $this->assertSame('adapted-cantrip', $spell_impact[0]['source_id']);
    $this->assertSame('daze', $spell_impact[0]['metadata']['spell_id']);

    $this->assertNotEmpty($condition_impact);
    $this->assertSame(-1, $condition_impact[0]['value']);
    $this->assertSame('Frightened', $condition_impact[0]['metadata']['label']);
  }

}
