<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\ActiveEffectStoreService;
use Drupal\dungeoncrawler_content\Service\CharacterStateService;
use Drupal\dungeoncrawler_content\Service\FeatEffectManager;
use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;
use Drupal\dungeoncrawler_content\Service\ImpactContractService;
use Drupal\dungeoncrawler_content\Service\NumberGenerationService;
use Drupal\Tests\UnitTestCase;

/**
 * @group dungeoncrawler_content
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\CharacterStateService
 */
class CharacterStateServiceArmorClassTest extends UnitTestCase {

  private function createService(?ActiveEffectStoreService $active_effect_store = NULL): CharacterStateService {
    return new CharacterStateService(
      $this->createMock(Connection::class),
      $this->createMock(AccountProxyInterface::class),
      new FeatEffectManager(),
      $this->createMock(GeneratedImageRepository::class),
      $this->createMock(NumberGenerationService::class),
      new ImpactContractService(),
      $active_effect_store ?? $this->createMock(ActiveEffectStoreService::class),
    );
  }

  /**
   * @covers ::applyDerivedDefensesToState
   */
  public function testApplyDerivedDefensesUsesWornArmorForArmorClass(): void {
    $service = $this->createService();

    $method = new \ReflectionMethod($service, 'applyDerivedDefensesToState');
    $method->setAccessible(TRUE);

    $state = [
      'basicInfo' => [
        'level' => 1,
      ],
      'abilities' => [
        'dexterity' => 14,
      ],
      'defenses' => [
        'armorClass' => 12,
      ],
      'inventory' => [
        'worn' => [
          'armor' => [
            'item_id' => 'leather',
            'armor_stats' => [
              'ac_bonus' => 1,
              'dex_cap' => 4,
            ],
          ],
        ],
      ],
    ];

    $updated = $method->invoke($service, $state);

    $this->assertSame(13, $updated['defenses']['armorClass']['base']);
    $this->assertSame(13, $updated['defenses']['armorClass']['total']);
    $this->assertSame(1, $updated['defenses']['armorClass']['armorBonus']);
    $this->assertSame(4, $updated['defenses']['armorClass']['armorDexCap']);
    $this->assertSame(2, $updated['defenses']['armorClass']['breakdown']['dex_modifier']);
  }

  /**
   * @covers ::resolveEffectiveState
   */
  public function testResolveEffectiveStateBuildsEquipmentMetadataAndArmorSpeedPenalty(): void {
    $service = $this->createService();

    $method = new \ReflectionMethod($service, 'resolveEffectiveState');
    $method->setAccessible(TRUE);

    $state = [
      'basicInfo' => [
        'level' => 1,
        'class' => 'fighter',
      ],
      'abilities' => [
        'dexterity' => 14,
      ],
      'speed' => 25,
      'resources' => [
        'hitPoints' => [
          'current' => 10,
          'max' => 10,
          'temporary' => 0,
        ],
      ],
      'defenses' => [
        'armorClass' => [],
      ],
      'inventory' => [
        'worn' => [
          'armor' => [
            'item_id' => 'scale-mail',
            'name' => 'Scale Mail',
            'armor_stats' => [
              'ac_bonus' => 3,
              'dex_cap' => 2,
              'speed_penalty' => -5,
              'check_penalty' => -2,
            ],
          ],
          'weapons' => [],
          'shield' => NULL,
          'accessories' => [],
        ],
        'carried' => [],
        'currency' => ['cp' => 0, 'sp' => 0, 'gp' => 0, 'pp' => 0],
        'totalBulk' => 0,
        'encumbrance' => 'unencumbered',
      ],
      'features' => [
        'feats' => [],
        'featSelections' => [],
        'classFeatures' => [],
      ],
      'conditions' => [],
      'spells' => [],
    ];

    $updated = $method->invoke($service, $state);

    $this->assertSame(15, $updated['defenses']['armorClass']['total']);
    $this->assertSame(15, $updated['effectiveState']['applied']['armorClass']['total']);
    $this->assertSame('scale-mail', $updated['effectiveState']['sources']['equipment']['armor']['item_id']);
    $this->assertSame(-5, $updated['effectiveState']['sources']['equipment']['armor']['speed_penalty']);
    $this->assertSame(0, $updated['effectiveState']['breakdowns']['armorClass']['proficiency']);
    $this->assertSame(20, $updated['movement']['speed']['total']);
    $this->assertSame(-5, $updated['effectiveState']['breakdowns']['speed']['armorPenalty']);
    $armor_impacts = array_values(array_filter(
      $updated['effectiveState']['impacts'],
      fn (array $impact): bool => $impact['source_type'] === 'equipment'
        && $impact['source_id'] === 'scale-mail'
        && $impact['target'] === 'defenses.armorClass.armorBonus'
    ));
    $this->assertNotEmpty($armor_impacts);
    $this->assertSame('add', $armor_impacts[0]['operation']);
    $this->assertSame('persistent-sheet', $armor_impacts[0]['phase']);
  }

  /**
   * @covers ::resolveEffectiveState
   */
  public function testResolveEffectiveStateSurfacesPersistedActiveEffectRows(): void {
    $active_effect_store = $this->createMock(ActiveEffectStoreService::class);
    $active_effect_store->expects($this->once())
      ->method('hasStorage')
      ->willReturn(TRUE);
    $active_effect_store->expects($this->once())
      ->method('listActiveEffects')
      ->with('267', 70, 'pc-70-267')
      ->willReturn([
        [
          'source_type' => 'spell-augment',
          'source_id' => 'adapted-cantrip:daze',
          'target' => 'spells.innate',
          'impact' => ['value' => ['spell_id' => 'daze']],
        ],
      ]);

    $service = $this->createService($active_effect_store);
    $method = new \ReflectionMethod($service, 'resolveEffectiveState');
    $method->setAccessible(TRUE);

    $resolved = $method->invoke($service, [
      'characterId' => '267',
      'campaignId' => 70,
      'instanceId' => 'pc-70-267',
      'basicInfo' => [
        'level' => 1,
        'class' => 'wizard',
      ],
      'resources' => [
        'hitPoints' => [
          'current' => 10,
          'max' => 10,
          'temporary' => 0,
        ],
      ],
      'spells' => [],
      'inventory' => [],
      'conditions' => [],
      'defenses' => [],
    ]);

    $this->assertSame(
      'adapted-cantrip:daze',
      $resolved['effectiveState']['sources']['active_effects'][0]['source_id']
    );
    $this->assertTrue($resolved['effectiveState']['sources']['active_effect_store']['enabled']);
  }

  /**
   * @covers ::resolveEffectiveState
   */
  public function testResolveEffectiveStateFlagsActiveEffectStoreDrift(): void {
    $active_effect_store = $this->createMock(ActiveEffectStoreService::class);
    $active_effect_store->expects($this->once())
      ->method('hasStorage')
      ->willReturn(TRUE);
    $active_effect_store->expects($this->once())
      ->method('listActiveEffects')
      ->with('267', 70, 'pc-70-267')
      ->willReturn([
        [
          'source_type' => 'equipment',
          'source_id' => 'stale-armor',
          'target' => 'defenses.armorClass.armorBonus',
          'phase' => 'persistent-sheet',
          'impact' => [
            'source_type' => 'equipment',
            'source_id' => 'stale-armor',
            'target' => 'defenses.armorClass.armorBonus',
            'operation' => 'add',
            'value' => 1,
            'stacking' => 'item',
            'phase' => 'persistent-sheet',
            'conditions' => [],
            'breakdown_key' => 'armorBonus',
            'metadata' => [],
          ],
        ],
      ]);
    $active_effect_store->expects($this->once())
      ->method('extractStoredImpacts')
      ->willReturn([
        [
          'source_type' => 'equipment',
          'source_id' => 'stale-armor',
          'target' => 'defenses.armorClass.armorBonus',
          'operation' => 'add',
          'value' => 1,
          'stacking' => 'item',
          'phase' => 'persistent-sheet',
          'conditions' => [],
          'breakdown_key' => 'armorBonus',
          'metadata' => [],
        ],
      ]);
    $active_effect_store->method('buildImpactIdentity')
      ->willReturnCallback(static fn (array $impact): string => implode(':', [
        (string) ($impact['source_type'] ?? ''),
        (string) ($impact['source_id'] ?? ''),
        (string) ($impact['target'] ?? ''),
        (string) ($impact['phase'] ?? ''),
      ]));

    $service = $this->createService($active_effect_store);
    $method = new \ReflectionMethod($service, 'resolveEffectiveState');
    $method->setAccessible(TRUE);

    $resolved = $method->invoke($service, [
      'characterId' => '267',
      'campaignId' => 70,
      'instanceId' => 'pc-70-267',
      'basicInfo' => [
        'level' => 1,
        'class' => 'fighter',
      ],
      'abilities' => [
        'dexterity' => 14,
      ],
      'speed' => 25,
      'resources' => [
        'hitPoints' => [
          'current' => 10,
          'max' => 10,
          'temporary' => 0,
        ],
      ],
      'spells' => [],
      'inventory' => [
        'worn' => [
          'armor' => [
            'item_id' => 'leather',
            'armor_stats' => [
              'ac_bonus' => 1,
              'dex_cap' => 4,
            ],
          ],
          'shield' => NULL,
          'accessories' => [],
        ],
      ],
      'conditions' => [],
      'defenses' => [],
    ]);

    $this->assertTrue($resolved['effectiveState']['flags']['active_effect_store_desynced']);
    $this->assertTrue($resolved['effectiveState']['sources']['active_effect_store']['desynced']);
    $this->assertNotEmpty($resolved['effectiveState']['sources']['active_effect_store']['missing_impacts']);
    $this->assertNotEmpty($resolved['effectiveState']['sources']['active_effect_store']['unexpected_impacts']);
  }

  /**
   * @covers ::stripEffectiveStateFromPersistence
   */
  public function testStripEffectiveStateFromPersistenceRemovesComputedMetadata(): void {
    $service = $this->createService();

    $method = new \ReflectionMethod($service, 'stripEffectiveStateFromPersistence');
    $method->setAccessible(TRUE);

    $stripped = $method->invoke($service, [
      'basicInfo' => ['name' => 'Test'],
      'effectiveState' => [
        'sources' => ['equipment' => []],
      ],
    ]);

    $this->assertArrayNotHasKey('effectiveState', $stripped);
    $this->assertSame('Test', $stripped['basicInfo']['name']);
  }

  /**
   * @covers ::resolveEffectiveState
   */
  public function testResolveEffectiveStateAppliesSupportedPersistentConditions(): void {
    $service = $this->createService();

    $method = new \ReflectionMethod($service, 'resolveEffectiveState');
    $method->setAccessible(TRUE);

    $state = [
      'basicInfo' => [
        'level' => 1,
        'class' => 'fighter',
      ],
      'abilities' => [
        'dexterity' => 14,
      ],
      'speed' => 25,
      'resources' => [
        'hitPoints' => [
          'current' => 10,
          'max' => 10,
          'temporary' => 0,
        ],
      ],
      'defenses' => [
        'armorClass' => [],
      ],
      'inventory' => [
        'worn' => [
          'armor' => [
            'item_id' => 'leather',
            'name' => 'Leather Armor',
            'armor_stats' => [
              'ac_bonus' => 1,
              'dex_cap' => 4,
              'speed_penalty' => 0,
            ],
          ],
          'weapons' => [],
          'shield' => NULL,
          'accessories' => [],
        ],
        'carried' => [],
        'currency' => ['cp' => 0, 'sp' => 0, 'gp' => 0, 'pp' => 0],
        'totalBulk' => 0,
        'encumbrance' => 'unencumbered',
      ],
      'features' => [
        'feats' => [],
        'featSelections' => [],
        'classFeatures' => [],
      ],
      'conditions' => [
        ['condition_type' => 'frightened', 'value' => 1],
        ['condition_type' => 'speed_penalty_5'],
        ['condition_type' => 'drained', 'value' => 1],
      ],
      'spells' => [],
    ];

    $updated = $method->invoke($service, $state);

    $this->assertSame(12, $updated['defenses']['armorClass']['total']);
    $this->assertSame(20, $updated['movement']['speed']['total']);
    $this->assertSame(-1, $updated['effectiveState']['sources']['conditions']['supported_adjustments']['armor_class']);
    $this->assertSame(-5, $updated['effectiveState']['sources']['conditions']['supported_adjustments']['speed']);
    $this->assertSame('drained', $updated['effectiveState']['sources']['conditions']['unsupported'][0]['code']);
  }

  /**
   * @covers ::resolveEffectiveState
   */
  public function testResolveEffectiveStateBuildsSpellImpactContracts(): void {
    $service = $this->createService();

    $method = new \ReflectionMethod($service, 'resolveEffectiveState');
    $method->setAccessible(TRUE);

    $state = [
      'basicInfo' => [
        'level' => 1,
        'ancestry' => 'elf',
        'heritage' => '',
      ],
      'speed' => 30,
      'movement' => ['speed' => ['base' => 30]],
      'resources' => [
        'hitPoints' => ['current' => 16, 'max' => 16, 'temporary' => 0],
        'featResources' => [],
      ],
      'defenses' => [
        'armorClass' => [],
      ],
      'inventory' => [
        'worn' => ['weapons' => [], 'armor' => NULL, 'shield' => NULL, 'accessories' => []],
        'carried' => [],
        'currency' => ['cp' => 0, 'sp' => 0, 'gp' => 0, 'pp' => 0],
        'totalBulk' => 0,
        'encumbrance' => 'unencumbered',
      ],
      'features' => [
        'feats' => [
          ['id' => 'adapted-cantrip', 'name' => 'Adapted Cantrip', 'type' => 'ancestry', 'level' => 1],
        ],
        'featSelections' => [
          'adapted-cantrip' => [
            'selected_tradition' => 'occult',
            'selected_cantrip' => 'daze',
          ],
        ],
        'classFeatures' => [],
      ],
      'conditions' => [],
      'actions' => ['availableActions' => []],
      'spells' => [],
    ];

    $updated = $method->invoke($service, $state);
    $spell_impacts = array_values(array_filter(
      $updated['effectiveState']['impacts'],
      fn (array $impact): bool => $impact['source_type'] === 'spell-augment'
    ));

    $this->assertNotEmpty($spell_impacts);
    $this->assertSame('adapted-cantrip', $spell_impacts[0]['source_id']);
    $this->assertSame('spells.innate', $spell_impacts[0]['target']);
    $this->assertSame('grant', $spell_impacts[0]['operation']);
    $this->assertSame('daze', $spell_impacts[0]['metadata']['spell_id']);
    $this->assertSame('occult', $spell_impacts[0]['metadata']['tradition']);
  }

}
