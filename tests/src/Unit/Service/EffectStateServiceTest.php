<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\ActiveEffectStoreService;
use Drupal\dungeoncrawler_content\Service\EffectStateService;
use PHPUnit\Framework\TestCase;

/**
 * @group dungeoncrawler_content
 * @group effect_state
 */
class EffectStateServiceTest extends TestCase {

  public function testGetStateDelegatesToActiveEffectStoreService(): void {
    $store = $this->createMock(ActiveEffectStoreService::class);
    $store->expects($this->once())
      ->method('listActiveEffects')
      ->with('4928', 845, 'pc-845-1033')
      ->willReturn([['id' => 1]]);

    $service = new EffectStateService($store);
    $result = $service->getState('4928', 845, 'pc-845-1033');

    $this->assertSame('4928', $result['character_id']);
    $this->assertSame(845, $result['campaign_id']);
    $this->assertSame('pc-845-1033', $result['instance_id']);
    $this->assertSame([['id' => 1]], $result['effects']);
  }

}
