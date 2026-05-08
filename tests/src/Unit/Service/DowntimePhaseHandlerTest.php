<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\dungeoncrawler_content\Service\DowntimePhaseHandler;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;
use Drupal\dungeoncrawler_content\Service\CharacterStateService;
use Drupal\dungeoncrawler_content\Service\CraftingService;
use Drupal\dungeoncrawler_content\Service\NpcPsychologyService;

/**
 * Tests for DowntimePhaseHandler service.
 *
 * Covers: earn_income, getAvailableActions, long_rest, retrain, advance_day.
 *
 * @group dungeoncrawler_content
 * @group downtime
 * @group pf2e-rules
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\DowntimePhaseHandler
 */
class DowntimePhaseHandlerTest extends UnitTestCase {

  /**
   * @var \Drupal\dungeoncrawler_content\Service\DowntimePhaseHandler
   */
  protected DowntimePhaseHandler $handler;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $db     = $this->createMock(Connection::class);
    $logger = $this->createMock(LoggerInterface::class);
    $lf     = $this->createMock(LoggerChannelFactoryInterface::class);
    $lf->method('get')->willReturn($logger);
    $css    = $this->createMock(CharacterStateService::class);
    $craft  = $this->createMock(CraftingService::class);
    $npc    = $this->createMock(NpcPsychologyService::class);

    $this->handler = new DowntimePhaseHandler($db, $lf, $css, $craft, $npc);
  }

  // ---------------------------------------------------------------------------
  // getAvailableActions
  // ---------------------------------------------------------------------------

  /**
   * Without active retrain, retrain is available; advance_day is not.
   */
  public function testGetAvailableActionsDefaultIncludesEarnIncomeAndRetrain(): void {
    $game_state = ['phase' => 'downtime', 'downtime' => ['days_elapsed' => 0]];
    $actions    = $this->handler->getAvailableActions($game_state, []);

    $this->assertContains('earn_income', $actions);
    $this->assertContains('craft', $actions);
    $this->assertContains('craft_snare', $actions);
    $this->assertContains('subsist', $actions);
    $this->assertContains('long_rest', $actions);
    $this->assertContains('downtime_rest', $actions);
    $this->assertContains('retrain', $actions);
    $this->assertContains('return_to_exploration', $actions);
    $this->assertNotContains('advance_day', $actions);
  }

  /**
   * With active retrain, advance_day is available; retrain is not.
   */
  public function testGetAvailableActionsWithActiveRetrain(): void {
    $game_state = [
      'phase'    => 'downtime',
      'downtime' => ['days_elapsed' => 1, 'retraining' => ['type' => 'feat', 'days_remaining' => 5]],
    ];
    $actions = $this->handler->getAvailableActions($game_state, []);

    $this->assertContains('advance_day', $actions);
    $this->assertNotContains('retrain', $actions);
  }

  /**
   * Chameleon Gnomes expose dramatic color shift during downtime.
   */
  public function testGetAvailableActionsIncludesDramaticColorShiftForChameleonGnome(): void {
    $game_state = ['phase' => 'downtime', 'downtime' => ['days_elapsed' => 0]];
    $dungeon_data = [
      'entities' => [
        [
          'instance_id' => 'char-001',
          'heritage' => 'chameleon',
        ],
      ],
    ];
    $actions = $this->handler->getAvailableActions($game_state, $dungeon_data, 'char-001');

    $this->assertContains('dramatic_color_shift', $actions);
  }

  /**
   * Dramatic Color Shift updates coloration for Chameleon Gnomes.
   */
  public function testProcessIntentDramaticColorShiftUpdatesColoration(): void {
    $game_state = $this->makeGameState();
    $dungeon_data = [];
    $intent = [
      'type' => 'dramatic_color_shift',
      'actor' => 'char-001',
      'params' => [
        'heritage' => 'chameleon',
        'target_terrain_color' => 'ashen_gray',
      ],
    ];

    $response = $this->handler->processIntent($intent, $game_state, $dungeon_data, 42);

    $this->assertTrue($response['success']);
    $this->assertSame('ashen_gray', $response['result']['coloration_tag']);
    $this->assertSame('up to 1 hour', $response['result']['duration']);
    $this->assertSame(
      ['type' => 'char_state', 'key' => 'coloration_tag', 'value' => 'ashen_gray'],
      $response['mutations'][0]
    );
  }

  /**
   * Dramatic Color Shift rejects non-Chameleon heritages.
   */
  public function testProcessIntentDramaticColorShiftRejectsNonChameleonActor(): void {
    $game_state = $this->makeGameState();
    $dungeon_data = [];
    $intent = [
      'type' => 'dramatic_color_shift',
      'actor' => 'char-001',
      'params' => [
        'heritage' => 'sensate',
        'target_terrain_color' => 'ashen_gray',
      ],
    ];

    $response = $this->handler->processIntent($intent, $game_state, $dungeon_data, 42);

    $this->assertFalse($response['success']);
    $this->assertSame('Dramatic Color Shift requires Chameleon Gnome heritage.', $response['result']['error']);
  }

  // ---------------------------------------------------------------------------
  // earn_income via processIntent
  // ---------------------------------------------------------------------------

  /**
   * Helper: build a minimal game state for earn_income tests.
   */
  private function makeGameState(): array {
    return ['phase' => 'downtime', 'downtime' => ['days_elapsed' => 0]];
  }

  /**
   * earn_income success (trained, task level 3) awards correct copper.
   *
   * CRB Table 4-2: Trained success at level 3 = 50 cp/day.
   */
  public function testEarnIncomeSuccessAwardsCp(): void {
    // addCurrency calls DB — mock a character record.
    $char_data = json_encode(['currency' => ['pp' => 0, 'gp' => 0, 'sp' => 0, 'cp' => 0]]);
    $stmt = $this->createMock(\Drupal\Core\Database\StatementInterface::class);
    $stmt->method('fetchAssoc')->willReturn(['character_data' => $char_data]);

    $select = $this->createMock(\Drupal\Core\Database\Query\Select::class);
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('execute')->willReturn($stmt);

    $update = $this->createMock(\Drupal\Core\Database\Query\Update::class);
    $update->method('fields')->willReturnSelf();
    $update->method('condition')->willReturnSelf();
    $update->method('execute')->willReturn(1);

    $db = $this->createMock(Connection::class);
    $db->method('select')->willReturn($select);
    $db->method('update')->willReturn($update);

    $lf     = $this->createMock(LoggerChannelFactoryInterface::class);
    $lf->method('get')->willReturn($this->createMock(LoggerInterface::class));
    $css    = $this->createMock(CharacterStateService::class);
    $craft  = $this->createMock(CraftingService::class);
    $npc    = $this->createMock(NpcPsychologyService::class);

    $handler = new DowntimePhaseHandler($db, $lf, $css, $craft, $npc);

    $game_state = $this->makeGameState();
    $intent = [
      'type'   => 'earn_income',
      'actor'  => 'char-001',
      'params' => [
        'skill'            => 'crafting',
        'proficiency_rank' => 1,   // Trained.
        'task_level'       => 3,   // DC 18.
        'degree'           => 'success',
        'days'             => 1,
      ],
    ];

    $dd = [];
    $response = $handler->processIntent($intent, $game_state, $dd, 42);

    $this->assertTrue($response['success']);
    $this->assertSame(50, $response['result']['earned_cp']); // Trained success level 3 = 50 cp.
    $this->assertSame(3, $response['result']['task_level']);
    $this->assertSame(18, $response['result']['task_dc']);
  }

  /**
   * earn_income critical success earns level+1 income.
   *
   * Trained critical success at level 3 → income for level 4 = 70 cp.
   */
  public function testEarnIncomeCriticalSuccessUsesNextLevel(): void {
    $char_data = json_encode(['currency' => ['pp' => 0, 'gp' => 0, 'sp' => 0, 'cp' => 0]]);
    $stmt = $this->createMock(\Drupal\Core\Database\StatementInterface::class);
    $stmt->method('fetchAssoc')->willReturn(['character_data' => $char_data]);

    $select = $this->createMock(\Drupal\Core\Database\Query\Select::class);
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('execute')->willReturn($stmt);

    $update = $this->createMock(\Drupal\Core\Database\Query\Update::class);
    $update->method('fields')->willReturnSelf();
    $update->method('condition')->willReturnSelf();
    $update->method('execute')->willReturn(1);

    $db = $this->createMock(Connection::class);
    $db->method('select')->willReturn($select);
    $db->method('update')->willReturn($update);

    $lf    = $this->createMock(LoggerChannelFactoryInterface::class);
    $lf->method('get')->willReturn($this->createMock(LoggerInterface::class));
    $css   = $this->createMock(CharacterStateService::class);
    $craft = $this->createMock(CraftingService::class);
    $npc   = $this->createMock(NpcPsychologyService::class);

    $handler = new DowntimePhaseHandler($db, $lf, $css, $craft, $npc);

    $game_state = $this->makeGameState();
    $intent = [
      'type'   => 'earn_income',
      'actor'  => 'char-001',
      'params' => [
        'skill'            => 'crafting',
        'proficiency_rank' => 1,
        'task_level'       => 3,
        'degree'           => 'critical_success',
        'days'             => 1,
      ],
    ];

    $dd = [];
    $response = $handler->processIntent($intent, $game_state, $dd, 42);

    $this->assertTrue($response['success']);
    $this->assertSame(70, $response['result']['earned_cp']); // Trained success level 4 = 70 cp.
  }

  /**
   * earn_income failure earns reduced (failure) income.
   *
   * Failure at task level 3 = 8 cp.
   */
  public function testEarnIncomeFailureEarnsFailureAmount(): void {
    $char_data = json_encode(['currency' => ['pp' => 0, 'gp' => 0, 'sp' => 0, 'cp' => 0]]);
    $stmt = $this->createMock(\Drupal\Core\Database\StatementInterface::class);
    $stmt->method('fetchAssoc')->willReturn(['character_data' => $char_data]);

    $select = $this->createMock(\Drupal\Core\Database\Query\Select::class);
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('execute')->willReturn($stmt);

    $update = $this->createMock(\Drupal\Core\Database\Query\Update::class);
    $update->method('fields')->willReturnSelf();
    $update->method('condition')->willReturnSelf();
    $update->method('execute')->willReturn(1);

    $db = $this->createMock(Connection::class);
    $db->method('select')->willReturn($select);
    $db->method('update')->willReturn($update);

    $lf    = $this->createMock(LoggerChannelFactoryInterface::class);
    $lf->method('get')->willReturn($this->createMock(LoggerInterface::class));
    $css   = $this->createMock(CharacterStateService::class);
    $craft = $this->createMock(CraftingService::class);
    $npc   = $this->createMock(NpcPsychologyService::class);

    $handler = new DowntimePhaseHandler($db, $lf, $css, $craft, $npc);

    $game_state = $this->makeGameState();
    $intent = [
      'type'   => 'earn_income',
      'actor'  => 'char-001',
      'params' => [
        'skill'            => 'crafting',
        'proficiency_rank' => 1,
        'task_level'       => 3,
        'degree'           => 'failure',
        'days'             => 1,
      ],
    ];

    $dd = [];
    $response = $handler->processIntent($intent, $game_state, $dd, 42);

    $this->assertTrue($response['success']);
    $this->assertSame(8, $response['result']['earned_cp']); // Failure level 3 = 8 cp.
  }

  /**
   * Critical failure earns nothing and sets 7-day cooldown.
   */
  public function testEarnIncomeCriticalFailureSetsSevenDayCooldown(): void {
    $handler    = $this->handler; // Uses no-op DB mock; actor is NULL so no addCurrency call.
    $game_state = $this->makeGameState();
    $intent = [
      'type'   => 'earn_income',
      'actor'  => NULL,
      'params' => [
        'skill'            => 'performance',
        'proficiency_rank' => 1,
        'task_level'       => 2,
        'degree'           => 'critical_failure',
        'days'             => 1,
      ],
    ];

    $dd = [];
    $response = $handler->processIntent($intent, $game_state, $dd, 42);

    $this->assertTrue($response['success']);
    $this->assertSame(0, $response['result']['earned_cp']);
    $this->assertSame(7, $game_state['downtime']['earn_income_cooldown_performance']);
  }

  /**
   * earn_income is blocked by an active critical failure cooldown.
   */
  public function testEarnIncomeBlockedByCooldown(): void {
    $handler    = $this->handler;
    $game_state = [
      'phase'    => 'downtime',
      'downtime' => [
        'days_elapsed'                       => 3,
        'earn_income_cooldown_performance'   => 5,
      ],
    ];
    $intent = [
      'type'   => 'earn_income',
      'actor'  => NULL,
      'params' => [
        'skill'            => 'performance',
        'proficiency_rank' => 1,
        'task_level'       => 2,
        'degree'           => 'success',
        'days'             => 1,
      ],
    ];

    $dd = [];
    $response = $handler->processIntent($intent, $game_state, $dd, 42);

    $this->assertFalse($response['success']);
    $this->assertSame('critical_failure_cooldown', $response['result']['error']);
  }

  /**
   * Rank insufficient for task level returns error.
   *
   * Untrained (rank 0) cannot access task level 3 Expert column.
   * Specifically: untrained CAN access level 3 (untrained column is not NULL),
   * but a rank that has NULL for that level cannot.
   * Legendary (rank 4) has NULL for task levels 0–14.
   */
  public function testEarnIncomeRankInsufficientReturnsError(): void {
    $handler    = $this->handler;
    $game_state = $this->makeGameState();
    $intent = [
      'type'   => 'earn_income',
      'actor'  => NULL,
      'params' => [
        'skill'            => 'crafting',
        'proficiency_rank' => 4,   // Legendary — NULL for task level 3.
        'task_level'       => 3,
        'degree'           => 'success',
        'days'             => 1,
      ],
    ];

    $dd = [];
    $response = $handler->processIntent($intent, $game_state, $dd, 42);

    $this->assertFalse($response['success']);
    $this->assertSame('rank_insufficient', $response['result']['error']);
  }

  /**
   * Multiple days multiplies income.
   *
   * Trained success level 5 = 90 cp/day × 3 days = 270 cp.
   */
  public function testEarnIncomeMultipleDaysMultipliesIncome(): void {
    $char_data = json_encode(['currency' => ['pp' => 0, 'gp' => 0, 'sp' => 0, 'cp' => 0]]);
    $stmt = $this->createMock(\Drupal\Core\Database\StatementInterface::class);
    $stmt->method('fetchAssoc')->willReturn(['character_data' => $char_data]);

    $select = $this->createMock(\Drupal\Core\Database\Query\Select::class);
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('execute')->willReturn($stmt);

    $update = $this->createMock(\Drupal\Core\Database\Query\Update::class);
    $update->method('fields')->willReturnSelf();
    $update->method('condition')->willReturnSelf();
    $update->method('execute')->willReturn(1);

    $db = $this->createMock(Connection::class);
    $db->method('select')->willReturn($select);
    $db->method('update')->willReturn($update);

    $lf    = $this->createMock(LoggerChannelFactoryInterface::class);
    $lf->method('get')->willReturn($this->createMock(LoggerInterface::class));
    $css   = $this->createMock(CharacterStateService::class);
    $craft = $this->createMock(CraftingService::class);
    $npc   = $this->createMock(NpcPsychologyService::class);

    $handler = new DowntimePhaseHandler($db, $lf, $css, $craft, $npc);

    $game_state = $this->makeGameState();
    $intent = [
      'type'   => 'earn_income',
      'actor'  => 'char-001',
      'params' => [
        'skill'            => 'crafting',
        'proficiency_rank' => 1,
        'task_level'       => 5,
        'degree'           => 'success',
        'days'             => 3,
      ],
    ];

    $dd = [];
    $response = $handler->processIntent($intent, $game_state, $dd, 42);

    $this->assertTrue($response['success']);
    $this->assertSame(270, $response['result']['earned_cp']); // 90 × 3.
    $this->assertSame(3, $response['result']['days_elapsed']);
  }

  // ---------------------------------------------------------------------------
  // AC-005: subsist
  // ---------------------------------------------------------------------------

  /**
   * Subsist success covers living expenses with no penalty.
   */
  public function testSubsistSuccessCoversCost(): void {
    $handler    = $this->handler;
    $game_state = $this->makeGameState();
    $intent = [
      'type'   => 'subsist',
      'actor'  => NULL,
      'params' => [
        'skill'       => 'survival',
        'degree'      => 'success',
        'environment' => 'settled_town',
      ],
    ];

    $dd = [];
    $response = $handler->processIntent($intent, $game_state, $dd, 42);

    $this->assertTrue($response['success']);
    $this->assertTrue($response['result']['covered']);
    $this->assertSame(0, $response['result']['penalty_cp']);
    $this->assertSame(0, $response['result']['extra_covered']);
  }

  /**
   * Subsist critical success covers self AND one extra person.
   */
  public function testSubsistCritSuccessCoversExtra(): void {
    $handler    = $this->handler;
    $game_state = $this->makeGameState();
    $intent = [
      'type'   => 'subsist',
      'actor'  => NULL,
      'params' => [
        'skill'       => 'survival',
        'degree'      => 'critical_success',
        'environment' => 'settled_town',
      ],
    ];

    $dd = [];
    $response = $handler->processIntent($intent, $game_state, $dd, 42);

    $this->assertTrue($response['success']);
    $this->assertTrue($response['result']['covered']);
    $this->assertSame(1, $response['result']['extra_covered']);
  }

  /**
   * Subsist failure returns covered=false with 10 cp penalty.
   */
  public function testSubsistFailurePenalizesTenCp(): void {
    $handler    = $this->handler;
    $game_state = $this->makeGameState();
    $intent = [
      'type'   => 'subsist',
      'actor'  => NULL,
      'params' => [
        'skill'       => 'survival',
        'degree'      => 'failure',
        'environment' => 'settled_town',
      ],
    ];

    $dd = [];
    $response = $handler->processIntent($intent, $game_state, $dd, 42);

    $this->assertTrue($response['success']);
    $this->assertFalse($response['result']['covered']);
    $this->assertSame(10, $response['result']['penalty_cp']);
  }

  // ---------------------------------------------------------------------------
  // AC-005: treat_disease
  // ---------------------------------------------------------------------------

  /**
   * Treat disease success reduces affliction stage by 1.
   */
  public function testTreatDiseaseSuccessReducesStage(): void {
    $stmt = $this->createMock(\Drupal\Core\Database\StatementInterface::class);
    $stmt->method('fetchAssoc')->willReturn([
      'id'             => 7,
      'current_stage'  => 3,
      'max_stage'      => 5,
      'affliction_type' => 'disease',
    ]);

    $select = $this->createMock(\Drupal\Core\Database\Query\Select::class);
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('execute')->willReturn($stmt);

    $update = $this->createMock(\Drupal\Core\Database\Query\Update::class);
    $update->method('fields')->willReturnSelf();
    $update->method('condition')->willReturnSelf();
    $update->method('execute')->willReturn(1);

    $db = $this->createMock(Connection::class);
    $db->method('select')->willReturn($select);
    $db->method('update')->willReturn($update);

    $lf    = $this->createMock(LoggerChannelFactoryInterface::class);
    $lf->method('get')->willReturn($this->createMock(LoggerInterface::class));
    $css   = $this->createMock(CharacterStateService::class);
    $craft = $this->createMock(CraftingService::class);
    $npc   = $this->createMock(NpcPsychologyService::class);

    $handler    = new DowntimePhaseHandler($db, $lf, $css, $craft, $npc);
    $game_state = $this->makeGameState();
    $intent = [
      'type'   => 'treat_disease',
      'actor'  => NULL,
      'params' => [
        'affliction_id' => 7,
        'degree'        => 'success',
      ],
    ];

    $dd = [];
    $response = $handler->processIntent($intent, $game_state, $dd, 42);

    $this->assertTrue($response['success']);
    $this->assertSame(3, $response['result']['old_stage']);
    $this->assertSame(2, $response['result']['new_stage']);
    $this->assertFalse($response['result']['cured']);
  }

  /**
   * Treat disease with missing affliction_id returns error.
   */
  public function testTreatDiseaseMissingAfflictionIdReturnsError(): void {
    $handler    = $this->handler;
    $game_state = $this->makeGameState();
    $intent = [
      'type'   => 'treat_disease',
      'actor'  => NULL,
      'params' => [],
    ];

    $dd = [];
    $response = $handler->processIntent($intent, $game_state, $dd, 42);

    $this->assertFalse($response['success']);
    $this->assertSame('missing_affliction_id', $response['result']['error']);
  }

  // ---------------------------------------------------------------------------
  // AC-005: run_business
  // ---------------------------------------------------------------------------

  /**
   * Run business success returns earned_cp with activity=run_business.
   */
  public function testRunBusinessSuccessEarnsIncome(): void {
    $char_data = json_encode(['currency' => ['pp' => 0, 'gp' => 0, 'sp' => 0, 'cp' => 0]]);
    $stmt = $this->createMock(\Drupal\Core\Database\StatementInterface::class);
    $stmt->method('fetchAssoc')->willReturn(['character_data' => $char_data]);

    $select = $this->createMock(\Drupal\Core\Database\Query\Select::class);
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('execute')->willReturn($stmt);

    $update = $this->createMock(\Drupal\Core\Database\Query\Update::class);
    $update->method('fields')->willReturnSelf();
    $update->method('condition')->willReturnSelf();
    $update->method('execute')->willReturn(1);

    $db = $this->createMock(Connection::class);
    $db->method('select')->willReturn($select);
    $db->method('update')->willReturn($update);

    $lf    = $this->createMock(LoggerChannelFactoryInterface::class);
    $lf->method('get')->willReturn($this->createMock(LoggerInterface::class));
    $css   = $this->createMock(CharacterStateService::class);
    $craft = $this->createMock(CraftingService::class);
    $npc   = $this->createMock(NpcPsychologyService::class);

    $handler    = new DowntimePhaseHandler($db, $lf, $css, $craft, $npc);
    $game_state = $this->makeGameState();
    $intent = [
      'type'   => 'run_business',
      'actor'  => 'char-001',
      'params' => [
        'skill'            => 'crafting',
        'proficiency_rank' => 1,
        'task_level'       => 3,
        'degree'           => 'success',
        'days'             => 1,
      ],
    ];

    $dd = [];
    $response = $handler->processIntent($intent, $game_state, $dd, 42);

    $this->assertTrue($response['success']);
    $this->assertSame('run_business', $response['result']['activity']);
    $this->assertGreaterThan(0, $response['result']['earned_cp']);
  }

}
