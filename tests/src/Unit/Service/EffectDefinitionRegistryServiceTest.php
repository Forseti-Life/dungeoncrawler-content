<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\EffectDefinitionRegistryService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\EffectDefinitionRegistryService
 *
 * @group dungeoncrawler_content
 */
class EffectDefinitionRegistryServiceTest extends UnitTestCase {

  /**
   * @covers ::getDefinition
   * @covers ::hasDefinition
   */
  public function testMageArmorDefinitionIsRegistered(): void {
    $registry = new EffectDefinitionRegistryService();

    $definition = $registry->getDefinition('mage_armor');

    $this->assertTrue($registry->hasDefinition('mage_armor'));
    $this->assertSame('Mage Armor', $definition['label'] ?? NULL);
    $this->assertSame('next_daily_preparations', $definition['expiration_policy']['trigger'] ?? NULL);
  }

  /**
   * @covers ::listDefinitionsByExpirationTrigger
   */
  public function testListDefinitionsByExpirationTrigger(): void {
    $registry = new EffectDefinitionRegistryService();

    $definitions = $registry->listDefinitionsByExpirationTrigger('next_daily_preparations');

    $this->assertCount(1, $definitions);
    $this->assertSame('mage_armor', $definitions[0]['definition_id'] ?? NULL);
  }

  /**
   * @covers ::buildTooltipModel
   */
  public function testBuildTooltipModelIncludesMechanicalStats(): void {
    $registry = new EffectDefinitionRegistryService();

    $tooltip = $registry->buildTooltipModel('mage_armor');

    $this->assertSame('Mage Armor', $tooltip['name'] ?? NULL);
    $this->assertSame('Armor Class', $tooltip['stats'][0]['stat'] ?? NULL);
    $this->assertSame('+1 AC', $tooltip['stats'][0]['val'] ?? NULL);
  }

  /**
   * @covers ::buildTooltipModel
   */
  public function testBuildTooltipModelSupportsConditionValueContext(): void {
    $registry = new EffectDefinitionRegistryService();

    $tooltip = $registry->buildTooltipModel('frightened', [], ['value' => 3]);

    $this->assertSame('Frightened', $tooltip['name'] ?? NULL);
    $this->assertSame('-3 AC', $tooltip['stats'][0]['val'] ?? NULL);
  }

  /**
   * @covers ::isInstanceManagedDefinition
   */
  public function testInstanceManagedDefinitionFlags(): void {
    $registry = new EffectDefinitionRegistryService();

    $this->assertTrue($registry->isInstanceManagedDefinition('mage_armor'));
    $this->assertFalse($registry->isInstanceManagedDefinition('frightened'));
  }

}
