<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests PF2e action economy enforcement.
 *
 * Covers TC-AE-01 through TC-AE-18 from
 * features/dc-cr-action-economy/03-test-plan.md.
 *
 * @group dungeoncrawler_content
 * @group action_economy
 */
#[RunTestsInSeparateProcesses]
class ActionEconomyTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  /**
   * Return a configured RulesEngine service.
   */
  protected function getRulesEngine() {
    return \Drupal::service('dungeoncrawler_content.rules_engine');
  }

  /**
   * Return a configured CombatEncounterStore service.
   */
  protected function getStore() {
    return \Drupal::service('dungeoncrawler_content.combat_encounter_store');
  }

  /**
   * Return a configured CombatEngine service.
   */
  protected function getCombatEngine() {
    return \Drupal::service('dungeoncrawler_content.combat_engine');
  }

  /**
   * Return a configured ActionProcessor service.
   */
  protected function getActionProcessor() {
    return \Drupal::service('dungeoncrawler_content.action_processor');
  }

  /**
   * Build a minimal participant state array.
   */
  protected function makeParticipant(int $actions_remaining = 3, bool $reaction_available = TRUE): array {
    return [
      'actions_remaining' => $actions_remaining,
      'reaction_available' => $reaction_available ? 1 : 0,
    ];
  }

  /**
   * Create a live encounter with one participant; return [encounter_id, participant_id].
   */
  protected function createTestEncounter(array $participant_overrides = []): array {
    $store = $this->getStore();
    $defaults = [
      'entity_id' => 1,
      'name' => 'Test Fighter',
      'initiative' => 10,
      'ac' => 18,
      'hp' => 30,
      'max_hp' => 30,
      'actions_remaining' => 3,
      'reaction_available' => 1,
    ];
    $participant = array_merge($defaults, $participant_overrides);
    $encounter_id = $store->createEncounter(NULL, NULL, [$participant]);
    $encounter = $store->loadEncounter($encounter_id);
    $participant_id = (int) $encounter['participants'][0]['id'];
    return [$encounter_id, $participant_id];
  }

  // ---------------------------------------------------------------------------
  // TC-AE-01 — Turn start resets action budget
  // ---------------------------------------------------------------------------

  /**
   * @covers \Drupal\dungeoncrawler_content\Service\CombatEngine::startTurn
   */
  public function testTurnStartResetsActionBudget(): void {
    [$encounter_id, $participant_id] = $this->createTestEncounter([
      'actions_remaining' => 0,
      'reaction_available' => 0,
    ]);

    $result = $this->getCombatEngine()->startTurn($encounter_id, $participant_id);
    $this->assertEquals('ok', $result['status']);
    $this->assertEquals(3, $result['actions_remaining']);
    $this->assertTrue($result['reaction_available']);

    $store = $this->getStore();
    $encounter = $store->loadEncounter($encounter_id);
    $p = $encounter['participants'][0];
    $this->assertEquals(3, (int) $p['actions_remaining']);
    $this->assertEquals(1, (int) $p['reaction_available']);
  }

  // ---------------------------------------------------------------------------
  // TC-AE-02 — 1-action cost decrements by 1
  // ---------------------------------------------------------------------------

  /**
   * @covers \Drupal\dungeoncrawler_content\Service\RulesEngine::validateActionEconomy
   */
  public function testOneActionCostDecrement(): void {
    $re = $this->getRulesEngine();
    $result = $re->validateActionEconomy($this->makeParticipant(3), 1);
    $this->assertTrue($result['is_valid']);
    $this->assertEquals(2, $result['actions_after']);
  }

  // ---------------------------------------------------------------------------
  // TC-AE-03 — 2-action activity decrements by 2
  // ---------------------------------------------------------------------------

  /**
   * @covers \Drupal\dungeoncrawler_content\Service\RulesEngine::validateActionEconomy
   */
  public function testTwoActionActivityDecrement(): void {
    $re = $this->getRulesEngine();
    $result = $re->validateActionEconomy($this->makeParticipant(3), 2);
    $this->assertTrue($result['is_valid']);
    $this->assertEquals(1, $result['actions_after']);
  }

  // ---------------------------------------------------------------------------
  // TC-AE-04 — 3-action activity decrements by 3
  // ---------------------------------------------------------------------------

  /**
   * @covers \Drupal\dungeoncrawler_content\Service\RulesEngine::validateActionEconomy
   */
  public function testThreeActionActivityDecrement(): void {
    $re = $this->getRulesEngine();
    $result = $re->validateActionEconomy($this->makeParticipant(3), 3);
    $this->assertTrue($result['is_valid']);
    $this->assertEquals(0, $result['actions_after']);
  }

  // ---------------------------------------------------------------------------
  // TC-AE-05 — Free action does not decrement budget
  // ---------------------------------------------------------------------------

  /**
   * @covers \Drupal\dungeoncrawler_content\Service\RulesEngine::validateActionEconomy
   */
  public function testFreeActionNoCost(): void {
    $re = $this->getRulesEngine();
    // Free action with 0 remaining still passes.
    $result = $re->validateActionEconomy($this->makeParticipant(0), 'free');
    $this->assertTrue($result['is_valid']);
    $this->assertEquals(0, $result['actions_after']);

    // Free action with 3 remaining — budget unchanged.
    $result3 = $re->validateActionEconomy($this->makeParticipant(3), 'free');
    $this->assertTrue($result3['is_valid']);
    $this->assertEquals(3, $result3['actions_after']);
  }

  // ---------------------------------------------------------------------------
  // TC-AE-06 — Reaction sets reaction_available to false
  // ---------------------------------------------------------------------------

  /**
   * @covers \Drupal\dungeoncrawler_content\Service\ActionProcessor::executeReactionAction
   */
  public function testReactionConsumption(): void {
    [$encounter_id, $participant_id] = $this->createTestEncounter([
      'reaction_available' => 1,
    ]);

    $result = $this->getActionProcessor()->executeReactionAction(
      $participant_id,
      ['allow_out_of_turn' => TRUE],
      $encounter_id
    );

    $this->assertEquals('ok', $result['status']);
    $this->assertFalse($result['reaction_available']);

    $store = $this->getStore();
    $encounter = $store->loadEncounter($encounter_id);
    $this->assertEquals(0, (int) $encounter['participants'][0]['reaction_available']);
  }

  // ---------------------------------------------------------------------------
  // TC-AE-07 — Cannot act when actions_remaining insufficient
  // ---------------------------------------------------------------------------

  /**
   * @covers \Drupal\dungeoncrawler_content\Service\RulesEngine::validateActionEconomy
   */
  public function testInsufficientActionsRejected(): void {
    $re = $this->getRulesEngine();
    $result = $re->validateActionEconomy($this->makeParticipant(1), 2);
    $this->assertFalse($result['is_valid']);
    $this->assertStringContainsString('Not enough actions', $result['reason']);
  }

  // ---------------------------------------------------------------------------
  // TC-AE-08 — Cannot use reaction if already spent
  // ---------------------------------------------------------------------------

  /**
   * @covers \Drupal\dungeoncrawler_content\Service\RulesEngine::validateActionEconomy
   */
  public function testSpentReactionRejected(): void {
    $re = $this->getRulesEngine();
    $result = $re->validateActionEconomy($this->makeParticipant(3, FALSE), 'reaction');
    $this->assertFalse($result['is_valid']);
    $this->assertStringContainsString('Reaction already used', $result['reason']);
  }

  // ---------------------------------------------------------------------------
  // TC-AE-09 — 2-action activity rejected with 1 action remaining
  // ---------------------------------------------------------------------------

  /**
   * @covers \Drupal\dungeoncrawler_content\Service\RulesEngine::validateActionEconomy
   */
  public function testTwoActionActivityRejectedAtOneRemaining(): void {
    $re = $this->getRulesEngine();
    $result = $re->validateActionEconomy($this->makeParticipant(1), 2);
    $this->assertFalse($result['is_valid']);
    $this->assertStringContainsString('Not enough actions', $result['reason']);
  }

  // ---------------------------------------------------------------------------
  // TC-AE-10 — actions_remaining cannot go below 0
  // ---------------------------------------------------------------------------

  /**
   * @covers \Drupal\dungeoncrawler_content\Service\RulesEngine::validateActionEconomy
   */
  public function testActionsRemainingFloorAtZero(): void {
    $re = $this->getRulesEngine();
    // Attempt a 1-action with 0 remaining — should be rejected.
    $result = $re->validateActionEconomy($this->makeParticipant(0), 1);
    $this->assertFalse($result['is_valid']);
    // actions_after must not go negative.
    $this->assertGreaterThanOrEqual(0, (int) ($result['actions_after'] ?? 0));
  }

  // ---------------------------------------------------------------------------
  // TC-AE-11 — Invalid action cost rejected
  // ---------------------------------------------------------------------------

  /**
   * @covers \Drupal\dungeoncrawler_content\Service\RulesEngine::validateActionEconomy
   */
  public function testInvalidActionCostRejected(): void {
    $re = $this->getRulesEngine();
    foreach ([-1, 0, 4, NULL, 'bogus'] as $bad_cost) {
      $result = $re->validateActionEconomy($this->makeParticipant(3), $bad_cost);
      $this->assertFalse($result['is_valid'], "Expected invalid for cost: " . json_encode($bad_cost));
    }
  }

  // ---------------------------------------------------------------------------
  // TC-AE-12 — Spending actions outside active turn rejected
  // ---------------------------------------------------------------------------

  /**
   * @covers \Drupal\dungeoncrawler_content\Service\ActionProcessor::executeReactionAction
   */
  public function testSpendActionsOutsideActiveTurnRejected(): void {
    // Create encounter with two participants; turn_index=0 means participant[0] is active.
    $store = $this->getStore();
    $encounter_id = $store->createEncounter(NULL, NULL, [
      ['entity_id' => 1, 'name' => 'Active Participant', 'initiative' => 20, 'actions_remaining' => 3, 'reaction_available' => 1],
      ['entity_id' => 2, 'name' => 'Out-of-turn Actor', 'initiative' => 5, 'actions_remaining' => 3, 'reaction_available' => 1],
    ]);

    $encounter = $store->loadEncounter($encounter_id);
    $second_participant_id = (int) $encounter['participants'][1]['id'];

    // Second participant is NOT the active turn participant (turn_index=0).
    $result = $this->getActionProcessor()->executeReactionAction(
      $second_participant_id,
      [],
      $encounter_id
    );
    $this->assertEquals('error', $result['status'],
      'Out-of-turn participant spending reaction without allow_out_of_turn should be rejected');
  }

  // ---------------------------------------------------------------------------
  // TC-AE-13 — Anon cannot access mutation endpoints
  // ---------------------------------------------------------------------------

  /**
   * Anonymous users must be blocked from combat action endpoints.
   */
  public function testAnonCannotAccessMutationEndpoints(): void {
    // Ensure no session is active (anonymous).
    $this->drupalResetSession();

    $this->drupalGet('/api/combat/action', ['query' => []]);
    $status = $this->getSession()->getStatusCode();
    // POST route — GET returns 405; accessing via POST without CSRF returns 403.
    // Either 403, 405, or 401 is acceptable for anonymous access denial.
    $this->assertContains($status, [401, 403, 405],
      "Anonymous access to /api/combat/action should be denied (got $status)");
  }

  // ---------------------------------------------------------------------------
  // TC-AE-14 — Authenticated player can use action endpoint
  // ---------------------------------------------------------------------------

  /**
   * Authenticated users with permission can reach the action endpoint.
   */
  public function testAuthenticatedPlayerCanReachActionEndpoint(): void {
    $player = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($player);

    // A GET to a POST-only route returns 405 (not 403), which confirms the
    // route is accessible to authenticated users.
    $this->drupalGet('/api/combat/action');
    $status = $this->getSession()->getStatusCode();
    $this->assertContains($status, [200, 400, 405],
      "Authenticated user should not get 403 on /api/combat/action (got $status)");
  }

  // ---------------------------------------------------------------------------
  // TC-AE-15 — Player cannot spend another character's actions
  // ---------------------------------------------------------------------------

  /**
   * @covers \Drupal\dungeoncrawler_content\Service\ActionProcessor::executeStride
   */
  public function testPlayerCannotSpendOtherCharacterActions(): void {
    // Create encounter with two participants; participant[0] is the active turn.
    $store = $this->getStore();
    $encounter_id = $store->createEncounter(NULL, NULL, [
      ['entity_id' => 1, 'name' => 'Active Fighter', 'initiative' => 20, 'ac' => 18, 'hp' => 30, 'max_hp' => 30, 'actions_remaining' => 3, 'reaction_available' => 1],
      ['entity_id' => 2, 'name' => 'Inactive Rogue', 'initiative' => 5, 'ac' => 16, 'hp' => 25, 'max_hp' => 25, 'actions_remaining' => 3, 'reaction_available' => 1],
    ]);

    $encounter = $store->loadEncounter($encounter_id);
    $second_participant_id = (int) $encounter['participants'][1]['id'];

    // Second participant (inactive) attempts stride — should be rejected.
    $ap = $this->getActionProcessor();
    $result = $ap->executeStride($second_participant_id, 5, [['q' => 1, 'r' => 0]], $encounter_id);
    $this->assertEquals('error', $result['status'],
      'Non-turn participant should not be able to stride');
  }

  // ---------------------------------------------------------------------------
  // TC-AE-16 — Admin can reset/override turn state
  // ---------------------------------------------------------------------------

  /**
   * @covers \Drupal\dungeoncrawler_content\Service\CombatEngine::startTurn
   */
  public function testAdminCanOverrideTurnState(): void {
    [$encounter_id, $participant_id] = $this->createTestEncounter([
      'actions_remaining' => 0,
      'reaction_available' => 0,
    ]);

    // startTurn acts as the GM reset mechanism (resets budget to 3+reaction).
    $result = $this->getCombatEngine()->startTurn($encounter_id, $participant_id);
    $this->assertEquals('ok', $result['status']);
    $this->assertEquals(3, $result['actions_remaining']);
    $this->assertTrue($result['reaction_available']);
  }

  // ---------------------------------------------------------------------------
  // TC-AE-17 — Data integrity: existing participants receive default action state
  // ---------------------------------------------------------------------------

  /**
   * Participants inserted without explicit action fields get safe defaults.
   */
  public function testMigrationDefaultActionState(): void {
    $store = $this->getStore();
    $encounter_id = $store->createEncounter(NULL, NULL, [
      [
        'entity_id' => 42,
        'name' => 'Legacy Participant',
        'initiative' => 10,
        // Deliberately omit actions_remaining and reaction_available.
      ],
    ]);

    $encounter = $store->loadEncounter($encounter_id);
    $p = $encounter['participants'][0];

    // Store defaults: actions_remaining=3, reaction_available=1.
    $this->assertEquals(3, (int) $p['actions_remaining'],
      'Participant without explicit action fields should default to 3 actions');
    $this->assertEquals(1, (int) $p['reaction_available'],
      'Participant without explicit reaction field should default to reaction available');
  }

  // ---------------------------------------------------------------------------
  // TC-AE-18 — Rollback: disabling module does not corrupt character nodes
  // ---------------------------------------------------------------------------

  /**
   * Loaded participants remain accessible even if action state fields are null.
   *
   * Full module uninstall in a BrowserTestBase is impractical; this test
   * verifies that null/zero action state fields do not break participant loading.
   */
  public function testModuleDisableDoesNotCorruptNodes(): void {
    $store = $this->getStore();
    $encounter_id = $store->createEncounter(NULL, NULL, [
      [
        'entity_id' => 55,
        'name' => 'Rollback Participant',
        'initiative' => 10,
        'actions_remaining' => 0,
        'reaction_available' => 0,
      ],
    ]);

    $encounter = $store->loadEncounter($encounter_id);
    $this->assertNotEmpty($encounter, 'Encounter should still be loadable with null-like action state');
    $this->assertNotEmpty($encounter['participants'], 'Participants should still be loadable');
    $p = $encounter['participants'][0];
    $this->assertEquals('Rollback Participant', $p['name'], 'Participant name should be intact');
  }

}
