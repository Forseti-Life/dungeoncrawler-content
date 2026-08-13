<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\EffectInstanceService;
use Drupal\dungeoncrawler_content\Service\EffectLifecycleService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\EffectLifecycleService
 *
 * @group dungeoncrawler_content
 */
class EffectLifecycleServiceTest extends UnitTestCase {

  /**
   * @covers ::expireActorEffectsForTrigger
   */
  public function testExpireActorEffectsForTriggerDelegatesToInstanceStore(): void {
    $instance_service = $this->createMock(EffectInstanceService::class);
    $instance_service->expects($this->once())
      ->method('expirePersistentActorEffectsByTrigger')
      ->with('4754', 812, 'pc-812-1033', 'next_daily_preparations')
      ->willReturn([
        'expired_count' => 1,
        'expired_definition_ids' => ['mage_armor'],
        'expired_condition_codes' => ['mage_armor'],
      ]);

    $service = new EffectLifecycleService($instance_service);

    $result = $service->expireActorEffectsForTrigger('4754', 812, 'pc-812-1033', 'next_daily_preparations');

    $this->assertSame(1, $result['expired_count']);
    $this->assertSame(['mage_armor'], $result['expired_definition_ids']);
  }

}

