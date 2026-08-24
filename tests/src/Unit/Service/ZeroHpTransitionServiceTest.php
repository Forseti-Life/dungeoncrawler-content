<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\ZeroHpTransitionService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for canonical 0 HP transition decisions.
 *
 * @group dungeoncrawler_content
 * @group zero_hp_transition
 */
class ZeroHpTransitionServiceTest extends TestCase {

  private ZeroHpTransitionService $service;

  protected function setUp(): void {
    parent::setUp();
    $this->service = new ZeroHpTransitionService();
  }

  public function testNonlethalZeroHpAppliesUnconsciousNotDying(): void {
    $result = $this->service->resolveDamageToZeroHp([
      'base_hp' => 5,
      'max_hp' => 20,
      'remaining_damage' => 5,
      'is_nonlethal' => TRUE,
      'is_critical' => FALSE,
      'already_dying' => FALSE,
      'existing_dying' => 0,
      'wounded' => 0,
      'doomed' => 0,
      'source' => 'test',
    ]);

    $this->assertSame('defeated', $result['new_status']);
    $this->assertTrue($result['apply_unconscious']);
    $this->assertFalse($result['apply_dying']);
  }

  public function testCriticalDropFromPositiveHpUsesDyingTwoPlusWounded(): void {
    $result = $this->service->resolveDamageToZeroHp([
      'base_hp' => 10,
      'max_hp' => 30,
      'remaining_damage' => 10,
      'is_nonlethal' => FALSE,
      'is_critical' => TRUE,
      'already_dying' => FALSE,
      'existing_dying' => 0,
      'wounded' => 1,
      'doomed' => 0,
      'source' => 'test',
    ]);

    $this->assertSame('defeated', $result['new_status']);
    $this->assertTrue($result['apply_dying']);
    $this->assertSame(3, $result['dying_value']);
  }

  public function testAlreadyDyingDamageIncrementsWithoutReaddingWounded(): void {
    $result = $this->service->resolveDamageToZeroHp([
      'base_hp' => 0,
      'max_hp' => 30,
      'remaining_damage' => 6,
      'is_nonlethal' => FALSE,
      'is_critical' => FALSE,
      'already_dying' => TRUE,
      'existing_dying' => 2,
      'wounded' => 2,
      'doomed' => 0,
      'source' => 'test',
    ]);

    $this->assertSame('defeated', $result['new_status']);
    $this->assertSame(3, $result['dying_value']);
  }

  public function testMassiveDamageUsesExcessDamageThreshold(): void {
    $result = $this->service->resolveDamageToZeroHp([
      'base_hp' => 5,
      'max_hp' => 20,
      'remaining_damage' => 25,
      'is_nonlethal' => FALSE,
      'is_critical' => FALSE,
      'already_dying' => FALSE,
      'existing_dying' => 0,
      'wounded' => 0,
      'doomed' => 0,
      'source' => 'test',
    ]);

    $this->assertSame('dead', $result['new_status']);
    $this->assertSame('massive_damage', $result['death_reason']);
  }

  public function testDeathEffectAlwaysReturnsDead(): void {
    $result = $this->service->resolveDamageToZeroHp([
      'base_hp' => 15,
      'max_hp' => 30,
      'remaining_damage' => 1,
      'is_nonlethal' => FALSE,
      'is_critical' => FALSE,
      'already_dying' => FALSE,
      'existing_dying' => 0,
      'wounded' => 0,
      'doomed' => 0,
      'source' => ['death_effect' => TRUE],
    ]);

    $this->assertSame('dead', $result['new_status']);
    $this->assertSame('death_effect', $result['death_reason']);
  }

}

