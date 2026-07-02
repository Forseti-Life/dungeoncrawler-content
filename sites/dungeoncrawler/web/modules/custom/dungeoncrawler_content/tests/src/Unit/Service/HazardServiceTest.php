<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\dungeoncrawler_content\Service\HazardService;
use Drupal\dungeoncrawler_content\Service\NumberGenerationService;

/**
 * Unit tests for HazardService — dc-cr-hazards acceptance criteria.
 *
 * Covers REQs 2373–2396: detection, triggering, disabling, Hardness/HP/BT,
 * counteract (magical), XP (once-per-hazard), complex hazard initiative.
 *
 * @group dungeoncrawler_content
 * @group hazards
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\HazardService
 */
class HazardServiceTest extends UnitTestCase {

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  /**
   * Build a mock NumberGenerationService that always rolls a fixed value.
   */
  private function mockDie(int $fixed_roll): NumberGenerationService {
    $mock = $this->createMock(NumberGenerationService::class);
    $mock->method('rollPathfinderDie')->willReturn($fixed_roll);
    return $mock;
  }

  /**
   * Build a minimal simple hazard entity with no min-proficiency requirements.
   */
  private function simplePitTrap(array $overrides = []): array {
    return array_replace_recursive([
      'instance_id'  => 'trap-001',
      'name'         => 'Pit Trap',
      'type'         => 'hazard',
      'complexity'   => 'simple',
      'level'        => 3,
      'stealth_dc'   => 20,
      'stealth_modifier' => 10,
      'is_magical'   => FALSE,
      'trigger'      => ['type' => 'passive'],
      'disable'      => ['skill' => 'thievery', 'dc' => 20, 'successes_needed' => 1],
      'effect'       => ['damage' => '2d6', 'damage_type' => 'bludgeoning'],
      'stats'        => ['ac' => 10, 'saves' => [], 'hardness' => 5, 'hp' => 20, 'bt' => 10],
      'state'        => ['detected' => FALSE, 'triggered' => FALSE, 'disabled' => FALSE,
                         'current_hp' => 20, 'successes' => 0, 'xp_awarded' => FALSE, 'broken' => FALSE],
    ], $overrides);
  }

  /**
   * Build a magical hazard entity.
   */
  private function magicalHazard(array $overrides = []): array {
    return array_replace_recursive([
      'instance_id'   => 'magic-001',
      'name'          => 'Spell Rune',
      'type'          => 'hazard',
      'complexity'    => 'simple',
      'level'         => 5,
      'stealth_dc'    => 25,
      'is_magical'    => TRUE,
      'spell_level'   => 3,
      'counteract_dc' => 20,
      'trigger'       => ['type' => 'passive'],
      'disable'       => ['skill' => 'thievery', 'dc' => 22, 'successes_needed' => 1],
      'effect'        => ['damage' => '4d8', 'damage_type' => 'fire'],
      'stats'         => ['ac' => 10, 'saves' => [], 'hardness' => 0, 'hp' => 30, 'bt' => 15],
      'state'         => ['detected' => TRUE, 'triggered' => FALSE, 'disabled' => FALSE,
                          'current_hp' => 30, 'successes' => 0, 'xp_awarded' => FALSE, 'broken' => FALSE],
    ], $overrides);
  }

  // ---------------------------------------------------------------------------
  // TC-HAZ-01: Detection — no min-prof; any character auto-rolls
  // ---------------------------------------------------------------------------

  /**
   * @covers ::rollHazardDetection
   */
  public function testDetectionNoMinProfRollsForNonSearchingCharacter(): void {
    $hazard = $this->simplePitTrap();
    $service = new HazardService($this->mockDie(18)); // 18+5=23 >= DC 20 → success

    $result = $service->rollHazardDetection($hazard, 5, 1, FALSE);

    $this->assertFalse($result['blocked'], 'No-min-prof hazard should not block non-searching character');
    $this->assertSame(20, $result['dc']);
    $this->assertTrue($result['detected']);
  }

  /**
   * @covers ::rollHazardDetection
   */
  public function testDetectionFailureWhenRollBelowDc(): void {
    $hazard = $this->simplePitTrap();
    $service = new HazardService($this->mockDie(5)); // 5+3=8 < DC 20 → fail

    $result = $service->rollHazardDetection($hazard, 3, 1, FALSE);

    $this->assertFalse($result['detected']);
  }

  // ---------------------------------------------------------------------------
  // TC-HAZ-02: Detection — min-prof hazard: only Searching qualifying chars
  // ---------------------------------------------------------------------------

  /**
   * @covers ::rollHazardDetection
   */
  public function testMinProfHazardBlocksNonSearchingCharacter(): void {
    $hazard = $this->simplePitTrap(['min_proficiency' => 'trained']);
    $service = new HazardService($this->mockDie(15));

    $result = $service->rollHazardDetection($hazard, 8, 1, FALSE); // is_searching=FALSE

    $this->assertTrue($result['blocked']);
    $this->assertStringContainsString('Searching', $result['blocked_reason']);
    $this->assertFalse($result['detected']);
  }

  /**
   * @covers ::rollHazardDetection
   */
  public function testMinProfHazardBlocksInsufficientProfCharacterEvenIfSearching(): void {
    $hazard = $this->simplePitTrap(['min_proficiency' => 'expert']); // rank 2 required
    $service = new HazardService($this->mockDie(15));

    $result = $service->rollHazardDetection($hazard, 8, 1, TRUE); // rank 1 = trained

    $this->assertTrue($result['blocked']);
    $this->assertStringContainsString('proficiency', $result['blocked_reason']);
  }

  /**
   * @covers ::rollHazardDetection
   */
  public function testMinProfHazardAllowsSearchingCharacterWithSufficientProficiency(): void {
    $hazard = $this->simplePitTrap(['min_proficiency' => 'trained']);
    $service = new HazardService($this->mockDie(18)); // 18+5=23 >= DC 20

    $result = $service->rollHazardDetection($hazard, 5, 1, TRUE); // rank 1 = trained

    $this->assertFalse($result['blocked']);
    $this->assertTrue($result['detected']);
  }

  // ---------------------------------------------------------------------------
  // TC-HAZ-03: Triggering
  // ---------------------------------------------------------------------------

  /**
   * @covers ::triggerHazard
   */
  public function testTriggerHazardSetsTriggeredStateAndReturnsEffect(): void {
    $hazard = $this->simplePitTrap();
    $service = new HazardService($this->mockDie(10));

    $result = $service->triggerHazard($hazard);

    $this->assertTrue($result['triggered']);
    $this->assertFalse($result['already_triggered']);
    $this->assertNotEmpty($result['effect']);
    $this->assertTrue($hazard['state']['triggered']);
  }

  /**
   * @covers ::triggerHazard
   */
  public function testTriggerHazardReturnsFalseIfAlreadyTriggered(): void {
    $hazard = $this->simplePitTrap(['state' => ['triggered' => TRUE]]);
    $service = new HazardService($this->mockDie(10));

    $result = $service->triggerHazard($hazard);

    $this->assertFalse($result['triggered']);
    $this->assertTrue($result['already_triggered']);
  }

  /**
   * @covers ::triggerHazard
   */
  public function testTriggerHazardReturnsFalseIfDisabled(): void {
    $hazard = $this->simplePitTrap(['state' => ['disabled' => TRUE, 'triggered' => FALSE]]);
    $service = new HazardService($this->mockDie(10));

    $result = $service->triggerHazard($hazard);

    $this->assertFalse($result['triggered']);
  }

  // ---------------------------------------------------------------------------
  // TC-HAZ-04: Disable — happy path (single success needed)
  // ---------------------------------------------------------------------------

  /**
   * @covers ::disableHazard
   */
  public function testDisableBlockedWhenHazardNotDetected(): void {
    $hazard = $this->simplePitTrap(); // detected=FALSE by default
    $service = new HazardService($this->mockDie(15));

    $result = $service->disableHazard($hazard, 8, 1, ['has_thieves_tools' => TRUE]);

    $this->assertTrue($result['blocked']);
    $this->assertStringContainsString('detected', $result['blocked_reason']);
  }

  /**
   * @covers ::disableHazard
   */
  public function testDisableSuccessDisablesHazardOnSingleSuccessNeeded(): void {
    $hazard = $this->simplePitTrap([
      'state' => ['detected' => TRUE, 'triggered' => FALSE, 'disabled' => FALSE, 'successes' => 0],
    ]);
    $service = new HazardService($this->mockDie(15)); // 15+8=23 >= DC 20 → success

    $result = $service->disableHazard($hazard, 8, 1, ['has_thieves_tools' => TRUE]);

    $this->assertSame('success', $result['degree']);
    $this->assertTrue($result['disabled']);
    $this->assertFalse($result['triggered']);
    $this->assertTrue($hazard['state']['disabled']);
  }

  /**
   * @covers ::disableHazard
   */
  public function testDisableCritFailTriggersHazard(): void {
    $hazard = $this->simplePitTrap([
      'state' => ['detected' => TRUE, 'triggered' => FALSE, 'disabled' => FALSE, 'successes' => 0],
    ]);
    // Roll 1 = critical failure (natural 1 always crit fail).
    $service = new HazardService($this->mockDie(1));

    $result = $service->disableHazard($hazard, 5, 1, ['has_thieves_tools' => TRUE]);

    $this->assertSame('critical_failure', $result['degree']);
    $this->assertTrue($result['triggered']);
    $this->assertFalse($result['disabled']);
  }

  /**
   * @covers ::disableHazard
   */
  public function testDisableCritSuccessCountsAsTwoSuccesses(): void {
    $hazard = $this->simplePitTrap([
      'disable' => ['skill' => 'thievery', 'dc' => 20, 'successes_needed' => 2],
      'state'   => ['detected' => TRUE, 'triggered' => FALSE, 'disabled' => FALSE, 'successes' => 0],
    ]);
    // Roll 20 = always critical success regardless of total.
    $service = new HazardService($this->mockDie(20));

    $result = $service->disableHazard($hazard, 0, 1, ['has_thieves_tools' => TRUE]);

    $this->assertSame('critical_success', $result['degree']);
    $this->assertTrue($result['disabled'], 'Crit success = 2 successes; should complete 2-success requirement in one roll');
    $this->assertSame(2, $result['successes']);
  }

  /**
   * @covers ::disableHazard
   */
  public function testDisableBlockedByInsufficientProficiency(): void {
    $hazard = $this->simplePitTrap([
      'disable' => ['skill' => 'thievery', 'dc' => 20, 'min_proficiency' => 'expert', 'successes_needed' => 1],
      'state'   => ['detected' => TRUE, 'triggered' => FALSE, 'disabled' => FALSE, 'successes' => 0],
    ]);
    $service = new HazardService($this->mockDie(15));

    $result = $service->disableHazard($hazard, 8, 1, ['has_thieves_tools' => TRUE]); // rank 1 < expert 2

    $this->assertTrue($result['blocked']);
    $this->assertStringContainsString('proficiency', $result['blocked_reason']);
  }

  // ---------------------------------------------------------------------------
  // TC-HAZ-05: Hardness / HP / Broken Threshold
  // ---------------------------------------------------------------------------

  /**
   * @covers ::applyDamageToHazard
   */
  public function testHardnessAbsorbsDamageBeforeHpReduction(): void {
    $hazard = $this->simplePitTrap(); // hardness=5, hp=20, bt=10
    $service = new HazardService($this->mockDie(10));

    $result = $service->applyDamageToHazard($hazard, 8); // 8-5=3 effective

    $this->assertSame(3, $result['effective_damage']);
    $this->assertSame(5, $result['hardness_absorbed']);
    $this->assertSame(17, $result['current_hp']);
    $this->assertFalse($result['broken']);
    $this->assertFalse($result['destroyed']);
    $this->assertTrue($result['triggered'], 'Hit but not destroyed → triggers hazard');
  }

  /**
   * @covers ::applyDamageToHazard
   */
  public function testHazardBrokenAtOrBelowBt(): void {
    $hazard = $this->simplePitTrap(['state' => ['current_hp' => 12]]);
    $service = new HazardService($this->mockDie(10));

    // 8-5=3 effective; 12-3=9 <= bt(10) → broken
    $result = $service->applyDamageToHazard($hazard, 8);

    $this->assertTrue($result['broken']);
    $this->assertFalse($result['destroyed']);
    $this->assertTrue($hazard['state']['broken']);
  }

  /**
   * @covers ::applyDamageToHazard
   */
  public function testHazardDestroyedAtZeroHp(): void {
    $hazard = $this->simplePitTrap(['stats' => ['hardness' => 0, 'hp' => 20, 'bt' => 10],
                                   'state' => ['current_hp' => 10]]);
    $service = new HazardService($this->mockDie(10));

    $result = $service->applyDamageToHazard($hazard, 10); // 10-0=10; 10-10=0

    $this->assertTrue($result['destroyed']);
    $this->assertFalse($result['triggered'], 'Destroyed outright: must NOT trigger');
    $this->assertTrue($hazard['state']['disabled']);
  }

  /**
   * @covers ::applyDamageToHazard
   */
  public function testDamageFullyAbsorbedByHardnessDoesNotTrigger(): void {
    $hazard = $this->simplePitTrap(); // hardness=5
    $service = new HazardService($this->mockDie(10));

    $result = $service->applyDamageToHazard($hazard, 3); // 3-5=0 effective

    $this->assertSame(0, $result['effective_damage']);
    $this->assertFalse($result['triggered'], 'No effective damage → should not trigger');
  }

  // ---------------------------------------------------------------------------
  // TC-HAZ-06: Magical hazard counteract
  // ---------------------------------------------------------------------------

  /**
   * @covers ::counteractMagicalHazard
   */
  public function testCounteractBlockedOnNonMagicalHazard(): void {
    $hazard = $this->simplePitTrap();
    $service = new HazardService($this->mockDie(15));

    $result = $service->counteractMagicalHazard($hazard, 3, 25, 15);

    $this->assertTrue($result['blocked']);
    $this->assertFalse($result['counteracted']);
  }

  /**
   * @covers ::counteractMagicalHazard
   */
  public function testCounteractSuccessWithSufficientLevel(): void {
    $hazard = $this->magicalHazard(); // spell_level=3, counteract_dc=20
    $service = new HazardService($this->mockDie(15)); // 15+5=20 = success

    // Total 20 = exactly DC; natural 15 (not 1/20). Diff=0 → success.
    $result = $service->counteractMagicalHazard($hazard, 3, 20, 15); // counteract_level=3 >= spell_level=3

    $this->assertSame('success', $result['degree']);
    $this->assertTrue($result['counteracted']);
    $this->assertFalse($result['triggered']);
    $this->assertTrue($hazard['state']['disabled']);
  }

  /**
   * @covers ::counteractMagicalHazard
   */
  public function testCounteractCritFailTriggersHazard(): void {
    $hazard = $this->magicalHazard();
    $service = new HazardService($this->mockDie(1));

    // Natural 1 → always critical failure.
    $result = $service->counteractMagicalHazard($hazard, 3, 6, 1);

    $this->assertSame('critical_failure', $result['degree']);
    $this->assertTrue($result['triggered']);
    $this->assertFalse($result['counteracted']);
  }

  /**
   * @covers ::counteractMagicalHazard
   */
  public function testCounteractSuccessFailsWhenLevelTooLow(): void {
    $hazard = $this->magicalHazard(); // spell_level=3, counteract_dc=20
    $service = new HazardService($this->mockDie(12));

    // Total 17 = >= 0 diff → success; but counteract_level=2 < spell_level=3 → not counteracted.
    $result = $service->counteractMagicalHazard($hazard, 2, 20, 12);

    $this->assertFalse($result['counteracted'], 'Success degree but level too low → should not counteract');
  }

  // ---------------------------------------------------------------------------
  // TC-HAZ-07: XP — once-per-hazard guard
  // ---------------------------------------------------------------------------

  /**
   * @covers ::awardHazardXp
   */
  public function testXpAwardedOnFirstCall(): void {
    $hazard = $this->simplePitTrap(['level' => 3]); // level diff = 3-3=0 → simple=10
    $game_state = [];
    $service = new HazardService($this->mockDie(10));

    $xp = $service->awardHazardXp($game_state, $hazard, 3);

    $this->assertSame(10, $xp);
    $this->assertTrue($game_state['hazard_xp_awarded']['trap-001']);
  }

  /**
   * @covers ::awardHazardXp
   */
  public function testXpNotAwardedTwiceForSameHazard(): void {
    $hazard = $this->simplePitTrap();
    $game_state = [];
    $service = new HazardService($this->mockDie(10));

    $service->awardHazardXp($game_state, $hazard, 3);
    $second_xp = $service->awardHazardXp($game_state, $hazard, 3);

    $this->assertSame(0, $second_xp, 'XP must not be awarded twice for same hazard');
  }

  /**
   * @covers ::awardHazardXp
   */
  public function testComplexHazardAwardsMoreXpThanSimple(): void {
    $simple_hazard = $this->simplePitTrap(['level' => 3, 'complexity' => 'simple']);
    $complex_hazard = $this->simplePitTrap(['instance_id' => 'trap-002', 'level' => 3, 'complexity' => 'complex']);
    $game_state = [];
    $service = new HazardService($this->mockDie(10));

    $simple_xp = $service->awardHazardXp($game_state, $simple_hazard, 3);
    $complex_xp = $service->awardHazardXp($game_state, $complex_hazard, 3);

    $this->assertGreaterThan($simple_xp, $complex_xp, 'Complex hazard should award more XP than simple');
  }

  // ---------------------------------------------------------------------------
  // TC-HAZ-08: Complex hazard initiative
  // ---------------------------------------------------------------------------

  /**
   * @covers ::rollComplexHazardInitiative
   */
  public function testComplexHazardInitiativeUsesStealthModifier(): void {
    $hazard = $this->simplePitTrap(['stealth_modifier' => 7, 'complexity' => 'complex']);
    $service = new HazardService($this->mockDie(12)); // 12+7=19

    $result = $service->rollComplexHazardInitiative($hazard);

    $this->assertSame(12, $result['roll']);
    $this->assertSame(7, $result['modifier']);
    $this->assertSame(19, $result['total']);
  }

}
