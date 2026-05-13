<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\CharacterStateService;
use Drupal\dungeoncrawler_content\Service\FeatEffectManager;
use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;
use Drupal\dungeoncrawler_content\Service\NumberGenerationService;
use Drupal\Tests\UnitTestCase;

/**
 * @group dungeoncrawler_content
 * @group feats
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\CharacterStateService
 */
class CharacterStateServiceFeatSelectionTest extends UnitTestCase {

  /**
   * @covers ::applyFeatEffectsToState
   */
  public function testApplyFeatEffectsUsesFeatSelectionsFromRuntimeState(): void {
    $service = new CharacterStateService(
      $this->createMock(Connection::class),
      $this->createMock(AccountProxyInterface::class),
      new FeatEffectManager(),
      $this->createMock(GeneratedImageRepository::class),
      $this->createMock(NumberGenerationService::class),
    );

    $method = new \ReflectionMethod($service, 'applyFeatEffectsToState');
    $method->setAccessible(TRUE);

    $state = [
      'basicInfo' => [
        'level' => 1,
        'ancestry' => 'elf',
        'heritage' => '',
      ],
      'movement' => ['speed' => ['base' => 30]],
      'resources' => [
        'hitPoints' => ['max' => 16],
        'featResources' => [],
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
      'actions' => ['availableActions' => []],
      'spells' => [],
    ];

    $updated = $method->invoke($service, $state);

    $this->assertSame('daze', $updated['spells']['featAugments']['innate_spells'][0]['spell_id']);
    $this->assertSame('occult', $updated['spells']['featAugments']['innate_spells'][0]['tradition']);
    $this->assertSame(
      'daze',
      $updated['features']['featSelections']['adapted-cantrip']['selected_cantrip']
    );
  }

}
