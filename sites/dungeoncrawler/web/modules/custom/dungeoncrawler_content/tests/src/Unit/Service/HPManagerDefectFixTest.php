<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Insert;
use Drupal\Core\Database\Query\Select;
use Drupal\Core\Database\Query\Update;
use Drupal\Core\Database\StatementInterface;
use Drupal\dungeoncrawler_content\Service\ConditionManager;
use Drupal\dungeoncrawler_content\Service\HPManager;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for HPManager defect fixes.
 *
 * DEF-2151: HP stored negative (no max(0,...) clamp)
 * DEF-2154: Crit kills apply dying 1 instead of dying 2
 * DEF-2155: Normal kills bypass applyDyingCondition (wounded not added)
 *
 * @group dungeoncrawler_content
 * @group hp_manager
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\HPManager
 */
class HPManagerDefectFixTest extends UnitTestCase {

  private $db;
  private $conditionManager;
  private HPManager $hpManager;

  protected function setUp(): void {
    parent::setUp();
    $this->db = $this->createMock(Connection::class);
    $this->conditionManager = $this->createMock(ConditionManager::class);
    // startTransaction() returns null — HPManager stores it but never calls
    // any method on the return value, so null is safe and avoids the
    // Transaction typed-property error in unit test environments.
    $this->db->method('startTransaction')->willReturn(NULL);
    $this->hpManager = new HPManager($this->db, $this->conditionManager);
  }

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  private function wireSelect(array $participant): void {
    $stmt = $this->createMock(StatementInterface::class);
    $stmt->method('fetchAssoc')->willReturn($participant);
    $sel = $this->createMock(Select::class);
    $sel->method('fields')->willReturnSelf();
    $sel->method('condition')->willReturnSelf();
    $sel->method('range')->willReturnSelf();
    $sel->method('execute')->willReturn($stmt);
    $this->db->method('select')->willReturn($sel);
  }

  private function wireUpdates(array &$capturedHp = []): void {
    $upd = $this->createMock(Update::class);
    $upd->method('fields')->willReturnCallback(function (array $f) use (&$capturedHp, $upd) {
      if (array_key_exists('hp', $f)) {
        $capturedHp = $f;
      }
      return $upd;
    });
    $upd->method('condition')->willReturnSelf();
    $upd->method('execute')->willReturn(1);
    $ins = $this->createMock(Insert::class);
    $ins->method('fields')->willReturnSelf();
    $ins->method('execute')->willReturn(1);
    $this->db->method('update')->willReturn($upd);
    $this->db->method('insert')->willReturn($ins);
  }

  private function wireConditions(array &$dyingLog = [], int $wounded = 0): void {
    $active = $wounded > 0 ? [['condition_type' => 'wounded', 'value' => $wounded]] : [];
    $this->conditionManager->method('getActiveConditions')->willReturn($active);
    $this->conditionManager->method('hasCondition')->willReturn(FALSE);
    $this->conditionManager->method('applyCondition')
      ->willReturnCallback(function ($p, $type, $val) use (&$dyingLog) {
        if ($type === 'dying') {
          $dyingLog[] = (int) $val;
        }
        return 1;
      });
  }

  private function makeParticipant(int $hp, int $maxHp, int $tempHp = 0): array {
    return ['id' => 1, 'hp' => $hp, 'max_hp' => $maxHp, 'temp_hp' => $tempHp, 'is_defeated' => 0];
  }

  // ---------------------------------------------------------------------------
  // DEF-2151: HP clamped to >= 0
  // ---------------------------------------------------------------------------

  /**
   * @covers ::applyDamage
   * damage > HP: new_hp must be 0, not negative.
   */
  public function testHpClampedToZeroWhenDamageExceedsHp(): void {
    $p = $this->makeParticipant(5, 30);
    $captured = [];
    $this->wireSelect($p);
    $this->wireUpdates($captured);
    $dying = [];
    $this->wireConditions($dying);

    $result = $this->hpManager->applyDamage(1, 20, 'physical', 'test', 1);

    $this->assertSame(0, $result['new_hp'], 'DEF-2151: new_hp must be 0 (clamped), not -15.');
    $this->assertGreaterThanOrEqual(0, $captured['hp'] ?? 0, 'DB hp field must not be negative.');
  }

  /**
   * @covers ::applyDamage
   * damage == HP: new_hp must be exactly 0.
   */
  public function testHpExactlyZeroWhenDamageEqualsHp(): void {
    $p = $this->makeParticipant(10, 30);
    $captured = [];
    $this->wireSelect($p);
    $this->wireUpdates($captured);
    $dying = [];
    $this->wireConditions($dying);

    $result = $this->hpManager->applyDamage(1, 10, 'physical', 'test', 1);

    $this->assertSame(0, $result['new_hp'], 'DEF-2151: new_hp exactly 0 when damage = HP.');
  }

  /**
   * @covers ::applyDamage
   * Temp HP absorbs all damage: base HP unchanged, dying not applied.
   */
  public function testHpPositiveWhenTempAbsorbsDamage(): void {
    $p = $this->makeParticipant(15, 30, 10);
    $captured = [];
    $this->wireSelect($p);
    $this->wireUpdates($captured);
    $dying = [];
    $this->wireConditions($dying);

    $result = $this->hpManager->applyDamage(1, 8, 'physical', 'test', 1);

    $this->assertSame(15, $result['new_hp'], 'Temp HP absorbs, base HP unchanged.');
    $this->assertEmpty($dying, 'No dying when HP stays positive.');
  }

  // ---------------------------------------------------------------------------
  // DEF-2155: Normal kill routes through applyDyingCondition (adds wounded)
  // ---------------------------------------------------------------------------

  /**
   * @covers ::applyDamage
   * Normal kill + wounded 1 → dying 2.
   */
  public function testNormalKillWoundedOneGivesDyingTwo(): void {
    $p = $this->makeParticipant(5, 30);
    $captured = [];
    $this->wireSelect($p);
    $this->wireUpdates($captured);
    $dying = [];
    $this->wireConditions($dying, 1);

    $this->hpManager->applyDamage(1, 20, 'physical', 'test', 1, FALSE, FALSE);

    $this->assertNotEmpty($dying, 'DEF-2155: dying must be applied on lethal hit.');
    $this->assertSame(2, $dying[0], 'DEF-2155: Normal kill + wounded 1 → dying 2.');
  }

  /**
   * @covers ::applyDamage
   * Normal kill + wounded 2 → dying 3.
   */
  public function testNormalKillWoundedTwoGivesDyingThree(): void {
    $p = $this->makeParticipant(5, 30);
    $captured = [];
    $this->wireSelect($p);
    $this->wireUpdates($captured);
    $dying = [];
    $this->wireConditions($dying, 2);

    $this->hpManager->applyDamage(1, 20, 'physical', 'test', 1, FALSE, FALSE);

    $this->assertSame(3, $dying[0], 'DEF-2155-B: Normal kill + wounded 2 → dying 3.');
  }

  /**
   * @covers ::applyDamage
   * Normal kill, no wounded → dying 1.
   */
  public function testNormalKillNoWoundedGivesDyingOne(): void {
    $p = $this->makeParticipant(5, 30);
    $captured = [];
    $this->wireSelect($p);
    $this->wireUpdates($captured);
    $dying = [];
    $this->wireConditions($dying, 0);

    $this->hpManager->applyDamage(1, 20, 'physical', 'test', 1, FALSE, FALSE);

    $this->assertSame(1, $dying[0], 'Normal kill, no wounded → dying 1.');
  }

  // ---------------------------------------------------------------------------
  // DEF-2154: Crit kill applies dying 2 base (not 1)
  // ---------------------------------------------------------------------------

  /**
   * @covers ::applyDamage
   * Crit kill, no wounded → dying 2.
   */
  public function testCritKillNoWoundedGivesDyingTwo(): void {
    $p = $this->makeParticipant(5, 30);
    $captured = [];
    $this->wireSelect($p);
    $this->wireUpdates($captured);
    $dying = [];
    $this->wireConditions($dying, 0);

    $this->hpManager->applyDamage(1, 20, 'physical', 'test', 1, FALSE, TRUE);

    $this->assertSame(2, $dying[0], 'DEF-2154: Crit kill, no wounded → dying 2 (not 1).');
  }

  /**
   * @covers ::applyDamage
   * Crit kill + wounded 1 → dying 3.
   */
  public function testCritKillWoundedOneGivesDyingThree(): void {
    $p = $this->makeParticipant(5, 30);
    $captured = [];
    $this->wireSelect($p);
    $this->wireUpdates($captured);
    $dying = [];
    $this->wireConditions($dying, 1);

    $this->hpManager->applyDamage(1, 20, 'physical', 'test', 1, FALSE, TRUE);

    $this->assertSame(3, $dying[0], 'DEF-2154-B: Crit + wounded 1 → dying 3.');
  }

  // ---------------------------------------------------------------------------
  // REQ 2156: Nonlethal → unconscious, not dying
  // ---------------------------------------------------------------------------

  /**
   * @covers ::applyDamage
   * Nonlethal lethal hit → unconscious applied, dying NOT applied.
   */
  public function testNonlethalKillGivesUnconsciousNotDying(): void {
    $p = $this->makeParticipant(5, 30);
    $captured = [];
    $this->wireSelect($p);
    $this->wireUpdates($captured);

    $dyingFlag = FALSE;
    $unconsciousFlag = FALSE;
    $this->conditionManager->method('getActiveConditions')->willReturn([]);
    $this->conditionManager->method('hasCondition')->willReturn(FALSE);
    $this->conditionManager->method('applyCondition')
      ->willReturnCallback(function ($pid, $type) use (&$dyingFlag, &$unconsciousFlag) {
        if ($type === 'dying') { $dyingFlag = TRUE; }
        if ($type === 'unconscious') { $unconsciousFlag = TRUE; }
        return 1;
      });

    $this->hpManager->applyDamage(1, 20, 'physical', 'test', 1, TRUE, FALSE);

    $this->assertFalse($dyingFlag, 'REQ 2156: Nonlethal must NOT apply dying.');
    $this->assertTrue($unconsciousFlag, 'REQ 2156: Nonlethal kill must apply unconscious.');
  }

}
