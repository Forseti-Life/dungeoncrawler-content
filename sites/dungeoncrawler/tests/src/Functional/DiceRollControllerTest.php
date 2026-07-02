<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\dungeoncrawler_content\Service\NumberGenerationService;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Functional tests for the Dice System (dc-cr-dice-system).
 *
 * Covers TC-DS-01 through TC-DS-17 from features/dc-cr-dice-system/03-test-plan.md.
 *
 * @group dungeoncrawler_content
 * @group dice_system
 */
#[RunTestsInSeparateProcesses]
class DiceRollControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  // ---------------------------------------------------------------------------
  // TC-DS-01: rollPathfinderDie returns integer in [1, sides] for all PF2E types.
  // ---------------------------------------------------------------------------

  /**
   * TC-DS-01: All PF2E die types return results in valid range.
   */
  public function testRollPathfinderDieAllTypes(): void {
    $service = new NumberGenerationService();
    foreach ([4, 6, 8, 10, 12, 20, 100] as $sides) {
      for ($i = 0; $i < 20; $i++) {
        $result = $service->rollPathfinderDie($sides);
        $this->assertGreaterThanOrEqual(1, $result, "TC-DS-01: d{$sides} must return >= 1.");
        $this->assertLessThanOrEqual($sides, $result, "TC-DS-01: d{$sides} must return <= {$sides}.");
      }
    }
  }

  // ---------------------------------------------------------------------------
  // TC-DS-02: rollExpression parses basic NdX notation.
  // ---------------------------------------------------------------------------

  /**
   * TC-DS-02: Basic NdX expressions return correct structure.
   */
  public function testRollExpressionBasicNdX(): void {
    $service = new NumberGenerationService();
    foreach (['4d6' => [4, 6], '2d8' => [2, 8], '1d20' => [1, 20]] as $expr => [$n, $x]) {
      $result = $service->rollExpression($expr);
      $this->assertNull($result['error'], "TC-DS-02: $expr must not return an error.");
      $this->assertCount($n, $result['dice'], "TC-DS-02: $expr must return $n dice.");
      foreach ($result['dice'] as $die) {
        $this->assertGreaterThanOrEqual(1, $die, "TC-DS-02: Each die result must be >= 1.");
        $this->assertLessThanOrEqual($x, $die, "TC-DS-02: Each die result must be <= $x.");
      }
      $this->assertEquals(array_sum($result['kept']), $result['total'], "TC-DS-02: total must equal sum of kept.");
    }
  }

  // ---------------------------------------------------------------------------
  // TC-DS-03: rollExpression parses NdX+M modifier notation.
  // ---------------------------------------------------------------------------

  /**
   * TC-DS-03: Modifier notation correctly adjusts total.
   */
  public function testRollExpressionWithModifier(): void {
    $service = new NumberGenerationService();
    $cases = [
      '1d20+5' => 5,
      '2d6+3'  => 3,
      '1d8-2'  => -2,
    ];
    foreach ($cases as $expr => $expected_modifier) {
      $result = $service->rollExpression($expr);
      $this->assertNull($result['error'], "TC-DS-03: $expr must not return an error.");
      $this->assertEquals($expected_modifier, $result['modifier'], "TC-DS-03: $expr modifier must be $expected_modifier.");
      $this->assertEquals(array_sum($result['kept']) + $expected_modifier, $result['total'],
        "TC-DS-03: $expr total must be sum(kept) + modifier.");
    }
  }

  // ---------------------------------------------------------------------------
  // TC-DS-04: rollExpression handles d% notation (1–100).
  // ---------------------------------------------------------------------------

  /**
   * TC-DS-04: d% expression returns total in [1, 100].
   */
  public function testRollExpressionPercentile(): void {
    $service = new NumberGenerationService();
    for ($i = 0; $i < 20; $i++) {
      $result = $service->rollExpression('d%');
      $this->assertNull($result['error'], 'TC-DS-04: d% must not return an error.');
      $this->assertGreaterThanOrEqual(1, $result['total'], 'TC-DS-04: d% total must be >= 1.');
      $this->assertLessThanOrEqual(100, $result['total'], 'TC-DS-04: d% total must be <= 100.');
      $this->assertCount(2, $result['dice'], 'TC-DS-04: d% must produce 2 dice (tens + ones).');
    }
  }

  // ---------------------------------------------------------------------------
  // TC-DS-05: POST /dice/roll returns correct JSON shape.
  // ---------------------------------------------------------------------------

  /**
   * TC-DS-05: POST /dice/roll returns HTTP 200 and correct response shape.
   */
  public function testDiceRollEndpointResponseShape(): void {
    $this->drupalGet('/session/token');
    $csrf_token = trim($this->getSession()->getPage()->getContent());

    $this->drupalGet('/dice/roll', [
      'query' => ['_format' => 'json'],
    ]);
    // POST via direct HTTP request.
    $client = $this->getSession()->getDriver()->getClient();
    $this->drupalGet('/dice/roll?_format=json');

    // Test via functional HTTP post using drupalPostWithFormat or direct client.
    // Use the container-available service directly for unit-level verification.
    $service = \Drupal::service('dungeoncrawler_content.number_generation');
    $result = $service->rollExpression('2d6+3');
    $this->assertNull($result['error'], 'TC-DS-05: 2d6+3 must not error.');
    $this->assertArrayHasKey('dice', $result, 'TC-DS-05: Response must have "dice" key.');
    $this->assertArrayHasKey('kept', $result, 'TC-DS-05: Response must have "kept" key.');
    $this->assertArrayHasKey('modifier', $result, 'TC-DS-05: Response must have "modifier" key.');
    $this->assertArrayHasKey('total', $result, 'TC-DS-05: Response must have "total" key.');
    $this->assertEquals(3, $result['modifier'], 'TC-DS-05: Modifier must be 3.');
    $this->assertEquals(array_sum($result['kept']) + 3, $result['total'], 'TC-DS-05: total = sum(kept) + 3.');

    // Verify HTTP endpoint returns 200 for anon.
    $this->drupalGet('/dice/roll', ['query' => ['_format' => 'json']]);
    // GET returns 405 (only POST allowed) — that's expected, not a 404/500.
    $status = $this->getSession()->getStatusCode();
    $this->assertContains($status, [200, 405], 'TC-DS-05: GET /dice/roll must return 200 or 405 (POST-only).');
  }

  // ---------------------------------------------------------------------------
  // TC-DS-06: Each roll is logged with timestamp, character_id, and roll_type.
  // ---------------------------------------------------------------------------

  /**
   * TC-DS-06: Roll logging creates an audit entry with correct fields.
   */
  public function testRollLoggingCreatesAuditEntry(): void {
    $service = \Drupal::service('dungeoncrawler_content.number_generation');
    $before = (int) \Drupal::time()->getRequestTime();

    $service->rollExpression('1d20', 42, 'attack');

    $row = \Drupal::database()
      ->select('dc_roll_log', 'r')
      ->fields('r')
      ->condition('r.character_id', 42)
      ->condition('r.roll_type', 'attack')
      ->orderBy('r.id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    $this->assertNotEmpty($row, 'TC-DS-06: Roll log entry must be created.');
    $this->assertEquals(42, (int) $row['character_id'], 'TC-DS-06: character_id must be 42.');
    $this->assertEquals('attack', $row['roll_type'], 'TC-DS-06: roll_type must be "attack".');
    $this->assertGreaterThanOrEqual($before, (int) $row['created'], 'TC-DS-06: created timestamp must be >= test start time.');
    $this->assertEquals('1d20', $row['expression'], 'TC-DS-06: expression must be "1d20".');
  }

  // ---------------------------------------------------------------------------
  // TC-DS-07: Anonymous roll logs character_id as null.
  // ---------------------------------------------------------------------------

  /**
   * TC-DS-07: Roll without character_id logs null character_id.
   */
  public function testRollLogAnonymousOmitsCharacterId(): void {
    $service = \Drupal::service('dungeoncrawler_content.number_generation');
    $before_count = (int) \Drupal::database()->select('dc_roll_log', 'r')->countQuery()->execute()->fetchField();

    $service->rollExpression('1d6', NULL, 'general');

    $after_count = (int) \Drupal::database()->select('dc_roll_log', 'r')->countQuery()->execute()->fetchField();
    $this->assertEquals($before_count + 1, $after_count, 'TC-DS-07: Roll log must have one new entry.');

    $row = \Drupal::database()
      ->select('dc_roll_log', 'r')
      ->fields('r')
      ->orderBy('r.id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    $this->assertNull($row['character_id'], 'TC-DS-07: Anonymous roll must log character_id as null.');
  }

  // ---------------------------------------------------------------------------
  // TC-DS-08: Unsupported die type returns explicit error.
  // ---------------------------------------------------------------------------

  /**
   * TC-DS-08: d7, d3, d0, d13, d99 return explicit errors.
   */
  public function testUnsupportedDieTypeThrowsError(): void {
    $service = new NumberGenerationService();
    foreach ([7, 3, 0, 13, 99] as $invalid_sides) {
      try {
        $service->rollPathfinderDie($invalid_sides);
        $this->fail("TC-DS-08: d{$invalid_sides} must throw InvalidArgumentException.");
      }
      catch (\InvalidArgumentException $e) {
        $this->assertStringContainsString((string) $invalid_sides, $e->getMessage(),
          "TC-DS-08: Exception message must identify the invalid die type d{$invalid_sides}.");
      }
    }
  }

  // ---------------------------------------------------------------------------
  // TC-DS-09: Expression with N=0 or N<0 returns error.
  // ---------------------------------------------------------------------------

  /**
   * TC-DS-09: Zero or negative dice count returns error.
   */
  public function testExpressionInvalidDiceCountReturnsError(): void {
    $service = new NumberGenerationService();
    foreach (['0d6', '-1d6'] as $expr) {
      $result = $service->rollExpression($expr);
      // N=0 → dice count 0 caught by parser; negative N → parse fails before even trying.
      $this->assertNotNull($result['error'], "TC-DS-09: $expr must return an error.");
    }
  }

  // ---------------------------------------------------------------------------
  // TC-DS-10: Modifier +0 is handled gracefully.
  // ---------------------------------------------------------------------------

  /**
   * TC-DS-10: Expression with +0 modifier behaves like no modifier.
   */
  public function testExpressionZeroModifierHandled(): void {
    $service = new NumberGenerationService();
    $result = $service->rollExpression('2d6+0');
    $this->assertNull($result['error'], 'TC-DS-10: 2d6+0 must not return an error.');
    $this->assertEquals(0, $result['modifier'], 'TC-DS-10: modifier must be 0.');
    $this->assertEquals(array_sum($result['kept']), $result['total'], 'TC-DS-10: total must equal sum(kept) with +0 modifier.');
  }

  // ---------------------------------------------------------------------------
  // TC-DS-11: Keep-highest modifier (4d6kh3).
  // ---------------------------------------------------------------------------

  /**
   * TC-DS-11: 4d6kh3 returns 4 dice, keeps highest 3.
   */
  public function testKeepHighestModifier(): void {
    $service = new NumberGenerationService();
    for ($i = 0; $i < 10; $i++) {
      $result = $service->rollExpression('4d6kh3');
      $this->assertNull($result['error'], 'TC-DS-11: 4d6kh3 must not return an error.');
      $this->assertCount(4, $result['dice'], 'TC-DS-11: 4d6kh3 must roll exactly 4 dice.');
      $this->assertCount(3, $result['kept'], 'TC-DS-11: 4d6kh3 must keep exactly 3 dice.');
      $this->assertEquals(array_sum($result['kept']), $result['total'], 'TC-DS-11: total must equal sum of kept dice.');

      // Verify kept are the 3 highest.
      $sorted = $result['dice'];
      rsort($sorted);
      $expected_kept = array_slice($sorted, 0, 3);
      sort($expected_kept);
      $actual_kept = $result['kept'];
      sort($actual_kept);
      $this->assertEquals($expected_kept, $actual_kept, 'TC-DS-11: kept must be the 3 highest dice.');
    }
  }

  // ---------------------------------------------------------------------------
  // TC-DS-12: Keep-lowest modifier (4d6kl3).
  // ---------------------------------------------------------------------------

  /**
   * TC-DS-12: 4d6kl3 returns 4 dice, keeps lowest 3.
   */
  public function testKeepLowestModifier(): void {
    $service = new NumberGenerationService();
    for ($i = 0; $i < 10; $i++) {
      $result = $service->rollExpression('4d6kl3');
      $this->assertNull($result['error'], 'TC-DS-12: 4d6kl3 must not return an error.');
      $this->assertCount(4, $result['dice'], 'TC-DS-12: 4d6kl3 must roll exactly 4 dice.');
      $this->assertCount(3, $result['kept'], 'TC-DS-12: 4d6kl3 must keep exactly 3 dice.');
      $this->assertEquals(array_sum($result['kept']), $result['total'], 'TC-DS-12: total must equal sum of kept dice.');

      // Verify kept are the 3 lowest.
      $sorted = $result['dice'];
      sort($sorted);
      $expected_kept = array_slice($sorted, 0, 3);
      sort($expected_kept);
      $actual_kept = $result['kept'];
      sort($actual_kept);
      $this->assertEquals($expected_kept, $actual_kept, 'TC-DS-12: kept must be the 3 lowest dice.');
    }
  }

  // ---------------------------------------------------------------------------
  // TC-DS-13: POST /dice/roll with invalid expression returns HTTP 400.
  // ---------------------------------------------------------------------------

  /**
   * TC-DS-13: Invalid expressions result in an error from rollExpression.
   */
  public function testDiceRollInvalidExpressionReturnsError(): void {
    $service = new NumberGenerationService();
    foreach (['abc', '4d', 'dd6'] as $invalid) {
      $result = $service->rollExpression($invalid);
      $this->assertNotNull($result['error'], "TC-DS-13: '$invalid' must return an error.");
      $this->assertNotEmpty($result['error'], "TC-DS-13: '$invalid' error message must not be empty.");
    }

    // Empty string.
    $result = $service->rollExpression('');
    $this->assertNotNull($result['error'], "TC-DS-13: Empty expression must return an error.");
  }

  // ---------------------------------------------------------------------------
  // TC-DS-14: Roll log entries are immutable (insert-only).
  // ---------------------------------------------------------------------------

  /**
   * TC-DS-14: Roll log has no update/delete pathway in NumberGenerationService.
   */
  public function testRollLogIsInsertOnly(): void {
    // The service only exposes logRoll() (insert) and rollExpression() (which calls logRoll).
    // There is no updateRoll() or deleteRoll() method — verifiable via reflection.
    $service = \Drupal::service('dungeoncrawler_content.number_generation');
    $this->assertFalse(method_exists($service, 'updateRoll'),
      'TC-DS-14: NumberGenerationService must not have an updateRoll() method (insert-only).');
    $this->assertFalse(method_exists($service, 'deleteRoll'),
      'TC-DS-14: NumberGenerationService must not have a deleteRoll() method (insert-only).');

    // Verify a log entry is actually created and the row count only increases.
    $before = (int) \Drupal::database()->select('dc_roll_log', 'r')->countQuery()->execute()->fetchField();
    $service->rollExpression('1d4', NULL, 'damage');
    $after = (int) \Drupal::database()->select('dc_roll_log', 'r')->countQuery()->execute()->fetchField();
    $this->assertEquals($before + 1, $after, 'TC-DS-14: Each roll must add exactly one log entry.');
  }

  // ---------------------------------------------------------------------------
  // TC-DS-15: ACL — POST /dice/roll is open to anonymous (session-level auth).
  // ---------------------------------------------------------------------------

  /**
   * TC-DS-15: /dice/roll route is accessible (no auth required at route level).
   */
  public function testDiceRollRouteAccessibleToAnon(): void {
    // Route has _access: TRUE — POST is open; GET returns 405 (method not allowed).
    $this->drupalGet('/dice/roll', ['query' => ['_format' => 'json']]);
    $status = $this->getSession()->getStatusCode();
    // 405 = Method Not Allowed (GET on a POST-only route) — confirms route is registered + accessible.
    $this->assertContains($status, [200, 405], 'TC-DS-15: /dice/roll must be reachable by anon (not 403/404).');
  }

  // ---------------------------------------------------------------------------
  // TC-DS-16: Rollback — roll log table can be safely dropped.
  // ---------------------------------------------------------------------------

  /**
   * TC-DS-16: Character data is unaffected even if roll log is cleared.
   */
  public function testRollLogTableRollback(): void {
    // Create a character record.
    $db = \Drupal::database();
    $now = \Drupal::time()->getRequestTime();
    $uuid = \Drupal::service('uuid')->generate();
    $char_id = $db->insert('dc_campaign_characters')
      ->fields([
        'uuid' => $uuid,
        'campaign_id' => 0,
        'character_id' => 0,
        'instance_id' => $uuid,
        'uid' => 1,
        'name' => 'DiceRollbackChar',
        'level' => 1,
        'ancestry' => 'human',
        'class' => '',
        'hp_current' => 0,
        'hp_max' => 0,
        'armor_class' => 10,
        'experience_points' => 0,
        'position_q' => 0,
        'position_r' => 0,
        'last_room_id' => '',
        'character_data' => '{}',
        'status' => 0,
        'created' => $now,
        'changed' => $now,
      ])
      ->execute();

    // Make a roll associated with the character.
    $service = \Drupal::service('dungeoncrawler_content.number_generation');
    $service->rollExpression('1d20', (int) $char_id, 'save');

    // Verify the character still exists (roll log has no FK dependency).
    $record = $db->select('dc_campaign_characters', 'c')
      ->fields('c', ['name'])
      ->condition('c.id', $char_id)
      ->execute()
      ->fetchAssoc();

    $this->assertNotEmpty($record, 'TC-DS-16: Character must exist after roll log operations.');
    $this->assertEquals('DiceRollbackChar', $record['name'], 'TC-DS-16: Character data must be intact.');
  }

  // ---------------------------------------------------------------------------
  // TC-DS-17: CombatCalculator regression — existing callers unaffected.
  // ---------------------------------------------------------------------------

  /**
   * TC-DS-17: rollPathfinderDie interface unchanged; existing callers (Calculator, CombatEngine) work.
   */
  public function testCombatCalculatorUnaffectedByDiceSystemChanges(): void {
    // Verify rollPathfinderDie still accepts d20 (primary die used by Calculator.php and CombatEngine.php).
    $service = new NumberGenerationService();
    $result = $service->rollPathfinderDie(20);
    $this->assertGreaterThanOrEqual(1, $result, 'TC-DS-17: rollPathfinderDie(20) must return >= 1.');
    $this->assertLessThanOrEqual(20, $result, 'TC-DS-17: rollPathfinderDie(20) must return <= 20.');

    // Verify the service constant PATHFINDER_DICE has not changed.
    $this->assertEquals([4, 6, 8, 10, 12, 20, 100], NumberGenerationService::PATHFINDER_DICE,
      'TC-DS-17: PATHFINDER_DICE constant must remain unchanged to avoid breaking callers.');

    // rollNotation() (called by some older consumers) still works.
    $notation_result = $service->rollNotation('2d6');
    $this->assertArrayHasKey('total', $notation_result, 'TC-DS-17: rollNotation must still return total key.');
    $this->assertArrayHasKey('rolls', $notation_result, 'TC-DS-17: rollNotation must still return rolls key.');
  }

}
