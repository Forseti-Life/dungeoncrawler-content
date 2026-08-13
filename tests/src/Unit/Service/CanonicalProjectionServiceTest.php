<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\CanonicalProjectionService;
use Drupal\dungeoncrawler_content\Service\CharacterStateService;
use Drupal\dungeoncrawler_content\Service\CombatEncounterStore;
use Drupal\dungeoncrawler_content\Service\EffectLifecycleService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\CanonicalProjectionService
 *
 * @group dungeoncrawler_content
 */
class CanonicalProjectionServiceTest extends UnitTestCase {

  private function createService(?EffectLifecycleService $effect_lifecycle_service = NULL): CanonicalProjectionService {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->with('dungeoncrawler')->willReturn($logger);

    return new CanonicalProjectionService(
      $this->createMock(CombatEncounterStore::class),
      $this->createMock(CharacterStateService::class),
      $logger_factory,
      $effect_lifecycle_service,
    );
  }

  /**
   * @covers ::applyCanonicalDailyPreparationConditionRecovery
   */
  public function testApplyCanonicalDailyPreparationConditionRecoveryExpiresNextDailyPreparationConditions(): void {
    $service = $this->createService();
    $state = [
      'conditions' => [
        ['condition_type' => 'mage_armor', 'name' => 'Mage Armor', 'duration' => 'until_next_daily_preparations'],
        ['condition_type' => 'doomed', 'name' => 'doomed', 'value' => 2],
      ],
    ];

    $service->applyCanonicalDailyPreparationConditionRecovery($state);

    $this->assertCount(1, $state['conditions']);
    $this->assertSame('doomed', strtolower((string) ($state['conditions'][0]['name'] ?? '')));
    $this->assertSame(1, (int) ($state['conditions'][0]['value'] ?? 0));
  }

  /**
   * @covers ::applyDailyPreparationConditionRecovery
   */
  public function testApplyDailyPreparationConditionRecoveryReportsExpiredConditionChanges(): void {
    $service = $this->createService();
    $entity = [
      'state' => [
        'conditions' => [
          ['condition_type' => 'mage_armor', 'name' => 'Mage Armor', 'duration' => 'until_next_daily_preparations'],
          ['condition_type' => 'wounded', 'name' => 'wounded'],
        ],
      ],
    ];

    $changes = $service->applyDailyPreparationConditionRecovery($entity);

    $this->assertSame(['expired mage armor', 'removed wounded'], $changes);
    $this->assertSame([], $entity['state']['conditions']);
  }

  /**
   * @covers ::applyCanonicalDailyPreparationConditionRecovery
   */
  public function testApplyCanonicalDailyPreparationConditionRecoveryExpiresMageArmorViaEffectLifecycle(): void {
    $effect_lifecycle_service = $this->createMock(EffectLifecycleService::class);
    $effect_lifecycle_service->expects($this->once())
      ->method('expireActorEffectsForTrigger')
      ->with('4754', 812, 'pc-812-1033', 'next_daily_preparations')
      ->willReturn([
        'expired_count' => 1,
        'expired_definition_ids' => ['mage_armor'],
        'expired_condition_codes' => ['mage_armor'],
      ]);

    $service = $this->createService($effect_lifecycle_service);
    $state = [
      'characterId' => '4754',
      'campaignId' => 812,
      'instanceId' => 'pc-812-1033',
      'conditions' => [
        ['condition_type' => 'mage_armor', 'name' => 'Mage Armor'],
        ['condition_type' => 'doomed', 'name' => 'doomed', 'value' => 2],
      ],
    ];

    $service->applyCanonicalDailyPreparationConditionRecovery($state);

    $this->assertCount(1, $state['conditions']);
    $this->assertSame('doomed', strtolower((string) ($state['conditions'][0]['name'] ?? '')));
  }

}
