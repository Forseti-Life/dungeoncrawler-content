<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\CombatEncounterStore;
use Drupal\dungeoncrawler_content\Service\EncounterStateService;
use PHPUnit\Framework\TestCase;

/**
 * @group dungeoncrawler_content
 * @group encounter_state
 */
class EncounterStateServiceTest extends TestCase {

  public function testGetStateLoadsEncounterFromStore(): void {
    $store = $this->createMock(CombatEncounterStore::class);
    $store->expects($this->once())
      ->method('loadEncounter')
      ->with(99001)
      ->willReturn(['id' => 99001, 'participants' => []]);

    $service = new EncounterStateService($store);
    $result = $service->getState(99001);

    $this->assertSame(99001, $result['id']);
  }

  public function testGetStateRejectsMissingEncounter(): void {
    $store = $this->createMock(CombatEncounterStore::class);
    $store->expects($this->once())
      ->method('loadEncounter')
      ->with(99001)
      ->willReturn(NULL);

    $service = new EncounterStateService($store);
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Encounter not found: 99001');
    $service->getState(99001);
  }

}
