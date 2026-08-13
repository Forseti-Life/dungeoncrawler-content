<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\EffectInstanceService;
use Drupal\dungeoncrawler_content\Service\EffectProjectionService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\EffectProjectionService
 *
 * @group dungeoncrawler_content
 */
class EffectProjectionServiceTest extends UnitTestCase {

  /**
   * @covers ::projectPersistentActorEffects
   */
  public function testProjectPersistentActorEffectsBuildsAdjustmentsAndTooltips(): void {
    $instance_service = $this->createMock(EffectInstanceService::class);
    $instance_service->expects($this->once())
      ->method('hasStorage')
      ->willReturn(TRUE);
    $instance_service->expects($this->once())
      ->method('listActivePersistentActorEffectInstances')
      ->with('4754', 812, 'pc-812-1033')
      ->willReturn([
        [
          'definition_id' => 'mage_armor',
          'target_subscope' => 'mage_armor',
          'value_payload' => [
            'impacts' => [
              ['target' => 'defenses.armorClass.otherBonuses', 'operation' => 'add', 'value' => 1],
            ],
          ],
        ],
      ]);
    $instance_service->expects($this->once())
      ->method('buildPersistentAdjustmentProjection')
      ->willReturn(['armor_class' => 1, 'speed' => 0]);
    $instance_service->expects($this->once())
      ->method('buildTooltipModelForInstance')
      ->willReturn([
        'name' => 'Mage Armor',
        'type' => 'condition',
        'desc' => 'You gain +1 status bonus to AC until your next daily preparations.',
        'stats' => [['stat' => 'Armor Class', 'val' => '+1 AC']],
        'effects' => [],
        'notes' => ['Expires: next daily preparations'],
      ]);

    $service = new EffectProjectionService($instance_service);
    $projection = $service->projectPersistentActorEffects(
      '4754',
      812,
      'pc-812-1033',
      [['condition_type' => 'mage_armor', 'name' => 'Mage Armor']]
    );

    $this->assertSame(1, $projection['adjustments']['armor_class']);
    $this->assertSame('mage_armor', $projection['instances'][0]['definition_id']);
    $this->assertSame('Mage Armor', $projection['condition_tooltips']['mage_armor']['name']);
  }

}

