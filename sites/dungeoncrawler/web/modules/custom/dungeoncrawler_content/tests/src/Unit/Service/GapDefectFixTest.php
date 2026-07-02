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
 * Unit tests for GAP defect fixes.
 *
 * GAP-2166: doomed instant-death in applyDyingCondition
 * GAP-2178: regeneration_bypassed flag auto-set from damage type
 *
 * @group dungeoncrawler_content
 * @group gap_defects
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\HPManager
 */
class GapDefectFixTest extends UnitTestCase {

  private Connection $db;
  private ConditionManager $conditionManager;
  private HPManager $hpManager;

  protected function setUp(): void {
    parent::setUp();
    $this->db = $this->createMock(Connection::class);
    $this->conditionManager = $this->createMock(ConditionManager::class);
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

  private function wireUpdates(array &$captured = []): void {
    $upd = $this->createMock(Update::class);
    $upd->method('fields')->willReturnCallback(function (array $f) use (&$captured, $upd) {
      $captured = array_merge($captured, $f);
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

  private function makeParticipant(int $hp, int $maxHp, string $entityRef = ''): array {
    return [
      'id' => 1,
      'hp' => $hp,
      'max_hp' => $maxHp,
      'temp_hp' => 0,
      'is_defeated' => 0,
      'entity_ref' => $entityRef ?: '{}',
    ];
  }

  // ---------------------------------------------------------------------------
  // GAP-2166: Doomed instant-death
  // applyDyingCondition with doomed 3 → threshold = max(1, 4-3) = 1
  // If effective_dying (dying_val + wounded_val) >= 1, should mark dead immediately.
  // ---------------------------------------------------------------------------

  /**
   * @covers ::applyDyingCondition
   * GAP-2166: Doomed 3 reduces death_threshold to 1.
   * Applying dying 1 to a doomed-3 character should result in immediate death.
   */
  public function testGap2166DoomedInstantDeathThresholdOne(): void {
    // doomed=3 → threshold=1; dying_value=1 → effective_dying=1 >= 1 → instant death
    $entity_ref = json_encode(['doomed' => 3]);
    $p = $this->makeParticipant(0, 30, $entity_ref);
    $this->wireSelect($p);
    $captured = [];
    $this->wireUpdates($captured);

    // getActiveConditions(int $participant_id, int $encounter_id)
    $this->conditionManager->method('getActiveConditions')
      ->willReturn([['condition_type' => 'doomed', 'value' => 3]]);
    $this->conditionManager->method('hasCondition')->willReturn(FALSE);
    $this->conditionManager->method('applyCondition')->willReturn(1);

    $result = $this->hpManager->applyDyingCondition(1, 1, 1);

    $this->assertTrue(
      $result['instant_death'] ?? FALSE,
      'GAP-2166: doomed 3 + dying 1 should trigger instant_death.'
    );
  }

  /**
   * @covers ::applyDyingCondition
   * GAP-2166: Doomed 1 reduces threshold to 3.
   * Applying dying 2 (effective_dying=2) should NOT trigger instant death.
   */
  public function testGap2166DoomedOneDoesNotInstantKillAtDyingTwo(): void {
    // doomed=1 → threshold=3; dying_value=2 → effective_dying=2 < 3 → no instant death
    $entity_ref = json_encode(['doomed' => 1]);
    $p = $this->makeParticipant(0, 30, $entity_ref);
    $this->wireSelect($p);
    $captured = [];
    $this->wireUpdates($captured);

    $this->conditionManager->method('getActiveConditions')
      ->willReturn([['condition_type' => 'doomed', 'value' => 1]]);
    $this->conditionManager->method('hasCondition')->willReturn(FALSE);
    $this->conditionManager->method('applyCondition')->willReturn(1);

    $result = $this->hpManager->applyDyingCondition(1, 2, 1);

    $this->assertFalse(
      $result['instant_death'] ?? FALSE,
      'GAP-2166: doomed 1 + dying 2 should NOT trigger instant death (threshold=3).'
    );
  }

  // ---------------------------------------------------------------------------
  // GAP-2178: regeneration_bypassed flag set from damage type
  // ---------------------------------------------------------------------------

  /**
   * @covers ::applyDamage
   * GAP-2178: Fire damage against a creature with regeneration_bypassed_by=['fire']
   * should set regeneration_bypassed=TRUE in entity_ref.
   */
  public function testGap2178RegenerationBypassedFlagSetOnFireDamage(): void {
    $entity_ref = json_encode([
      'regeneration' => 5,
      'regeneration_bypassed_by' => ['fire'],
    ]);
    $p = array_merge($this->makeParticipant(20, 30, $entity_ref), ['entity_ref' => $entity_ref]);
    $this->wireSelect($p);

    $savedEntityRef = NULL;
    $upd = $this->createMock(Update::class);
    $upd->method('fields')->willReturnCallback(function (array $f) use (&$savedEntityRef, $upd) {
      if (isset($f['entity_ref'])) {
        $savedEntityRef = $f['entity_ref'];
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

    $this->conditionManager->method('getActiveConditions')->willReturn([]);
    $this->conditionManager->method('hasCondition')->willReturn(FALSE);
    $this->conditionManager->method('applyCondition')->willReturn(1);

    $this->hpManager->applyDamage(1, 5, 'fire', 'fire_breath', 1);

    $this->assertNotNull($savedEntityRef, 'GAP-2178: entity_ref should be updated when fire damage hits regen creature.');
    $decoded = json_decode($savedEntityRef, TRUE);
    $this->assertTrue(
      $decoded['regeneration_bypassed'] ?? FALSE,
      'GAP-2178: regeneration_bypassed must be TRUE after fire damage on fire-bypassed regen creature.'
    );
  }

  /**
   * @covers ::applyDamage
   * GAP-2178: Physical damage against a creature with regeneration_bypassed_by=['fire']
   * should NOT set regeneration_bypassed flag.
   */
  public function testGap2178RegenerationBypassedNotSetOnNonBypassDamageType(): void {
    $entity_ref = json_encode([
      'regeneration' => 5,
      'regeneration_bypassed_by' => ['fire'],
    ]);
    $p = array_merge($this->makeParticipant(20, 30, $entity_ref), ['entity_ref' => $entity_ref]);
    $this->wireSelect($p);

    $savedEntityRef = NULL;
    $upd = $this->createMock(Update::class);
    $upd->method('fields')->willReturnCallback(function (array $f) use (&$savedEntityRef, $upd) {
      if (isset($f['entity_ref'])) {
        $savedEntityRef = $f['entity_ref'];
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

    $this->conditionManager->method('getActiveConditions')->willReturn([]);
    $this->conditionManager->method('hasCondition')->willReturn(FALSE);
    $this->conditionManager->method('applyCondition')->willReturn(1);

    $this->hpManager->applyDamage(1, 5, 'slashing', 'sword', 1);

    // If entity_ref was saved, regeneration_bypassed must NOT be true
    if ($savedEntityRef !== NULL) {
      $decoded = json_decode($savedEntityRef, TRUE);
      $this->assertFalse(
        $decoded['regeneration_bypassed'] ?? FALSE,
        'GAP-2178: regeneration_bypassed must NOT be set for non-bypass damage type.'
      );
    }
    else {
      // entity_ref not updated at all — also acceptable (no bypass flag set).
      $this->assertTrue(TRUE, 'GAP-2178: entity_ref not updated for non-bypass damage — correct.');
    }
  }

}
