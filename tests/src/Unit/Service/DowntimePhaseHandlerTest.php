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
use Drupal\dungeoncrawler_content\Service\NumberGenerationService;

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
    return [
      'phase' => 'downtime',
      'downtime' => ['days_elapsed' => 0],
      'campaign_clock' => [
        'datetime' => '2024-01-01T08:00:00Z',
        'date' => '2024-01-01',
        'time' => '08:00',
        'timezone' => 'UTC',
        'year' => 2024,
        'month' => 1,
        'day' => 1,
        'hour' => 8,
        'minute' => 0,
        'weekday' => 'Monday',
        'season' => 'winter',
      ],
      'game_time' => [
        'day' => 1,
        'hour' => 8,
        'minute' => 0,
        'date' => '2024-01-01',
        'datetime' => '2024-01-01T08:00:00Z',
        'timezone' => 'UTC',
      ],
    ];
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
    $this->assertSame('2024-01-04', $game_state['campaign_clock']['date']);
    $this->assertSame(4, $game_state['game_time']['day']);
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

  /**
   * Starvation advancement writes canonical survival state and syncs projection.
   */
  public function testAdvanceStarvationUsesCanonicalStateAndSyncsProjection(): void {
    $update = $this->createMock(\Drupal\Core\Database\Query\Update::class);
    $update->method('fields')->willReturnSelf();
    $update->method('condition')->willReturnSelf();
    $update->method('execute')->willReturn(1);

    $db = $this->createMock(Connection::class);
    $db->method('update')->willReturn($update);

    $lf = $this->createMock(LoggerChannelFactoryInterface::class);
    $lf->method('get')->willReturn($this->createMock(LoggerInterface::class));

    $css = $this->createMock(CharacterStateService::class);
    $css->expects($this->once())
      ->method('getState')
      ->with('745', 42, 'pc-1')
      ->willReturn([
        'resources' => [
          'hitPoints' => ['current' => 20, 'max' => 20],
          'survival' => [
            'daysWithoutFood' => 0,
            'daysWithoutWater' => 0,
            'starvationDamagePhase' => FALSE,
            'thirstDamagePhase' => FALSE,
          ],
        ],
        'conditions' => [],
      ]);
    $css->expects($this->once())
      ->method('setState')
      ->with(
        '745',
        $this->callback(function (array $state): bool {
          return (int) ($state['resources']['survival']['daysWithoutFood'] ?? -1) === 1
            && (int) ($state['resources']['survival']['daysWithoutWater'] ?? -1) === 1
            && !empty($state['conditions'])
            && strtolower((string) ($state['conditions'][0]['name'] ?? '')) === 'fatigued';
        }),
        NULL,
        42,
        'pc-1'
      )
      ->willReturnCallback(static function ($character_id, array $state): array {
        return $state;
      });

    $craft = $this->createMock(CraftingService::class);
    $npc = $this->createMock(NpcPsychologyService::class);
    $roller = $this->createMock(NumberGenerationService::class);
    $roller->expects($this->never())->method('rollPathfinderDie');

    $handler = new DowntimePhaseHandler($db, $lf, $css, $craft, $npc, NULL, $roller);
    $game_state = ['phase' => 'downtime', 'downtime' => ['days_elapsed' => 0]];
    $dungeon_data = [
      'entities' => [
        [
          'entity_instance_id' => 'pc-1',
          'instance_id' => 'pc-1',
          'stats' => ['con_modifier' => 1],
          'state' => [
            'metadata' => [
              'campaign_character_id' => '745',
              'runtime_entity_id' => 'pc-1',
            ],
            'hit_points' => ['current' => 20, 'max' => 20],
            'conditions' => [],
          ],
        ],
      ],
    ];

    $response = $handler->processIntent([
      'type' => 'advance_starvation',
      'actor' => 'pc-1',
      'params' => ['char_ids' => ['pc-1'], 'resource' => 'both'],
    ], $game_state, $dungeon_data, 42);

    $this->assertTrue($response['success']);
    $this->assertSame(1, (int) ($response['result']['results']['pc-1']['days_without_food'] ?? -1));
    $this->assertSame(1, (int) ($response['result']['results']['pc-1']['days_without_water'] ?? -1));
    $this->assertSame(1, (int) ($dungeon_data['entities'][0]['state']['days_without_food'] ?? -1));
    $this->assertSame(1, (int) ($dungeon_data['entities'][0]['state']['days_without_water'] ?? -1));
    $this->assertFalse((bool) ($dungeon_data['entities'][0]['state']['starvation_damage_phase'] ?? TRUE));
    $this->assertFalse((bool) ($dungeon_data['entities'][0]['state']['thirst_damage_phase'] ?? TRUE));
    $this->assertSame('fatigued', strtolower((string) ($dungeon_data['entities'][0]['state']['conditions'][0]['name'] ?? '')));
  }

  /**
   * Starvation advancement rejects updates when canonical identity is missing.
   */
  public function testAdvanceStarvationRequiresCanonicalCharacterIdentity(): void {
    $update = $this->createMock(\Drupal\Core\Database\Query\Update::class);
    $update->method('fields')->willReturnSelf();
    $update->method('condition')->willReturnSelf();
    $update->method('execute')->willReturn(1);

    $db = $this->createMock(Connection::class);
    $db->method('update')->willReturn($update);

    $lf = $this->createMock(LoggerChannelFactoryInterface::class);
    $lf->method('get')->willReturn($this->createMock(LoggerInterface::class));

    $css = $this->createMock(CharacterStateService::class);
    $css->expects($this->never())->method('getState');
    $css->expects($this->never())->method('setState');

    $craft = $this->createMock(CraftingService::class);
    $npc = $this->createMock(NpcPsychologyService::class);
    $handler = new DowntimePhaseHandler($db, $lf, $css, $craft, $npc);

    $game_state = ['phase' => 'downtime', 'downtime' => ['days_elapsed' => 0]];
    $dungeon_data = [
      'entities' => [
        [
          'entity_instance_id' => 'pc-1',
          'instance_id' => 'pc-1',
          'stats' => ['con_modifier' => 1],
          'state' => ['hit_points' => ['current' => 20, 'max' => 20]],
        ],
      ],
    ];

    $response = $handler->processIntent([
      'type' => 'advance_starvation',
      'actor' => 'pc-1',
      'params' => ['char_ids' => ['pc-1'], 'resource' => 'both'],
    ], $game_state, $dungeon_data, 42);

    $this->assertTrue($response['success']);
    $this->assertSame(
      'Canonical character sheet is required for starvation/thirst updates.',
      $response['result']['results']['pc-1']['error'] ?? ''
    );
  }

  /**
   * Starvation/thirst damage phase activates once thresholds are exceeded.
   */
  public function testAdvanceStarvationAppliesDamageAfterThresholdTransition(): void {
    $update = $this->createMock(\Drupal\Core\Database\Query\Update::class);
    $update->method('fields')->willReturnSelf();
    $update->method('condition')->willReturnSelf();
    $update->method('execute')->willReturn(1);

    $db = $this->createMock(Connection::class);
    $db->method('update')->willReturn($update);

    $lf = $this->createMock(LoggerChannelFactoryInterface::class);
    $lf->method('get')->willReturn($this->createMock(LoggerInterface::class));

    $css = $this->createMock(CharacterStateService::class);
    $css->expects($this->once())
      ->method('getState')
      ->with('745', 42, 'pc-1')
      ->willReturn([
        'resources' => [
          'hitPoints' => ['current' => 20, 'max' => 20],
          'survival' => [
            'daysWithoutFood' => 1,
            'daysWithoutWater' => 1,
            'starvationDamagePhase' => FALSE,
            'thirstDamagePhase' => FALSE,
          ],
        ],
        'conditions' => [],
      ]);
    $css->expects($this->once())
      ->method('setState')
      ->with(
        '745',
        $this->callback(function (array $state): bool {
          return (int) ($state['resources']['hitPoints']['current'] ?? -1) === 16
            && !empty($state['resources']['survival']['starvationDamagePhase'])
            && !empty($state['resources']['survival']['thirstDamagePhase']);
        }),
        NULL,
        42,
        'pc-1'
      )
      ->willReturnCallback(static function ($character_id, array $state): array {
        return $state;
      });

    $craft = $this->createMock(CraftingService::class);
    $npc = $this->createMock(NpcPsychologyService::class);
    $roller = $this->createMock(NumberGenerationService::class);
    $roller->expects($this->once())
      ->method('rollPathfinderDie')
      ->with(4)
      ->willReturn(3);

    $handler = new DowntimePhaseHandler($db, $lf, $css, $craft, $npc, NULL, $roller);
    $game_state = ['phase' => 'downtime', 'downtime' => ['days_elapsed' => 0]];
    $dungeon_data = [
      'entities' => [
        [
          'entity_instance_id' => 'pc-1',
          'instance_id' => 'pc-1',
          'stats' => ['con_modifier' => 0],
          'state' => [
            'metadata' => [
              'campaign_character_id' => '745',
              'runtime_entity_id' => 'pc-1',
            ],
            'hit_points' => ['current' => 20, 'max' => 20],
            'conditions' => [],
          ],
        ],
      ],
    ];

    $response = $handler->processIntent([
      'type' => 'advance_starvation',
      'actor' => 'pc-1',
      'params' => ['char_ids' => ['pc-1'], 'resource' => 'both'],
    ], $game_state, $dungeon_data, 42);

    $this->assertTrue($response['success']);
    $this->assertSame(4, (int) ($response['result']['results']['pc-1']['damage_taken'] ?? -1));
    $this->assertTrue((bool) ($response['result']['results']['pc-1']['starvation_damage_phase'] ?? FALSE));
    $this->assertTrue((bool) ($response['result']['results']['pc-1']['thirst_damage_phase'] ?? FALSE));
    $this->assertSame(16, (int) ($dungeon_data['entities'][0]['state']['hit_points']['current'] ?? -1));
    $this->assertTrue((bool) ($dungeon_data['entities'][0]['state']['starvation_damage_phase'] ?? FALSE));
    $this->assertTrue((bool) ($dungeon_data['entities'][0]['state']['thirst_damage_phase'] ?? FALSE));
  }

  /**
   * Legacy survival mirrors are ignored; canonical survival contract is required.
   */
  public function testAdvanceStarvationIgnoresLegacyCanonicalMirrorFields(): void {
    $update = $this->createMock(\Drupal\Core\Database\Query\Update::class);
    $update->method('fields')->willReturnSelf();
    $update->method('condition')->willReturnSelf();
    $update->method('execute')->willReturn(1);

    $db = $this->createMock(Connection::class);
    $db->method('update')->willReturn($update);

    $lf = $this->createMock(LoggerChannelFactoryInterface::class);
    $lf->method('get')->willReturn($this->createMock(LoggerInterface::class));

    $css = $this->createMock(CharacterStateService::class);
    $css->expects($this->once())
      ->method('getState')
      ->with('745', 42, 'pc-1')
      ->willReturn([
        'resources' => [
          'hitPoints' => ['current' => 20, 'max' => 20],
        ],
        'days_without_food' => 7,
        'days_without_water' => 9,
        'starvation_damage_phase' => TRUE,
        'thirst_damage_phase' => TRUE,
        'conditions' => [],
      ]);
    $css->expects($this->once())
      ->method('setState')
      ->with(
        '745',
        $this->callback(function (array $state): bool {
          return (int) ($state['resources']['survival']['daysWithoutFood'] ?? -1) === 1
            && (int) ($state['resources']['survival']['daysWithoutWater'] ?? -1) === 1
            && empty($state['resources']['survival']['starvationDamagePhase'])
            && empty($state['resources']['survival']['thirstDamagePhase']);
        }),
        NULL,
        42,
        'pc-1'
      )
      ->willReturnCallback(static function ($character_id, array $state): array {
        return $state;
      });

    $craft = $this->createMock(CraftingService::class);
    $npc = $this->createMock(NpcPsychologyService::class);
    $handler = new DowntimePhaseHandler($db, $lf, $css, $craft, $npc);

    $game_state = ['phase' => 'downtime', 'downtime' => ['days_elapsed' => 0]];
    $dungeon_data = [
      'entities' => [
        [
          'entity_instance_id' => 'pc-1',
          'instance_id' => 'pc-1',
          'stats' => ['con_modifier' => 5],
          'state' => [
            'metadata' => [
              'campaign_character_id' => '745',
              'runtime_entity_id' => 'pc-1',
            ],
            'hit_points' => ['current' => 20, 'max' => 20],
            'conditions' => [],
          ],
        ],
      ],
    ];

    $response = $handler->processIntent([
      'type' => 'advance_starvation',
      'actor' => 'pc-1',
      'params' => ['char_ids' => ['pc-1'], 'resource' => 'both'],
    ], $game_state, $dungeon_data, 42);

    $this->assertTrue($response['success']);
    $this->assertSame(1, (int) ($response['result']['results']['pc-1']['days_without_food'] ?? -1));
    $this->assertSame(1, (int) ($response['result']['results']['pc-1']['days_without_water'] ?? -1));
    $this->assertSame(0, (int) ($response['result']['results']['pc-1']['damage_taken'] ?? -1));
    $this->assertFalse((bool) ($response['result']['results']['pc-1']['starvation_damage_phase'] ?? TRUE));
    $this->assertFalse((bool) ($response['result']['results']['pc-1']['thirst_damage_phase'] ?? TRUE));
    $this->assertSame(1, (int) ($dungeon_data['entities'][0]['state']['days_without_food'] ?? -1));
    $this->assertSame(1, (int) ($dungeon_data['entities'][0]['state']['days_without_water'] ?? -1));
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
