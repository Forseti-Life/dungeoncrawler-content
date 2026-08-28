<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\ActorAutoplayCoordinator;
use Drupal\dungeoncrawler_content\Service\EncounterAiIntegrationService;
use Drupal\dungeoncrawler_content\Service\HazardService;
use Drupal\dungeoncrawler_content\Service\MovementResolverService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests for NPC autoplay movement goal resolution.
 *
 * @group dungeoncrawler_content
 * @group actor-autoplay
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\ActorAutoplayCoordinator
 */
class ActorAutoplayCoordinatorTest extends UnitTestCase {

  /**
   * Inclusive lower bound of the simulated open room on both axes.
   */
  const ROOM_MIN = -8;

  /**
   * Inclusive upper bound of the simulated open room on both axes.
   */
  const ROOM_MAX = 8;

  /**
   * Build an open room hex set matching live room layout payload shape.
   *
   * @return array<int,array<string,mixed>>
   *   Hex payloads.
   */
  protected function buildOpenRoomHexes(): array {
    $hexes = [];
    for ($q = self::ROOM_MIN; $q <= self::ROOM_MAX; $q++) {
      for ($r = self::ROOM_MIN; $r <= self::ROOM_MAX; $r++) {
        $hexes[] = [
          'q' => $q,
          'r' => $r,
          'terrain_type' => 'stone_floor',
          'elevation_ft' => 0,
          'objects' => [],
        ];
      }
    }
    return $hexes;
  }

  /**
   * @covers ::autoPlayTurn
   * @covers ::resolveStrideDestinationHex
   */
  public function testAggressiveStrideUsesFullBudgetToReachStrikeRange(): void {
    $service = $this->buildServiceWithMovementBudget(25);
    $captured_hex = NULL;
    $origin = ['q' => 3, 'r' => 2];
    $target = ['q' => 0, 'r' => 1];

    $game_state = [
      'round' => 1,
      'turn' => [
        'entity' => 'npc-1',
        'actions_remaining' => 1,
      ],
      'initiative_order' => [
        [
          'entity_id' => 'npc-1',
          'team' => 'enemy',
          'speed' => 25,
          'position_q' => $origin['q'],
          'position_r' => $origin['r'],
        ],
        [
          'entity_id' => 'pc-1',
          'team' => 'player',
          'speed' => 25,
          'position_q' => $target['q'],
          'position_r' => $target['r'],
          'is_defeated' => FALSE,
        ],
      ],
    ];
    $dungeon_data = [];

    $service->autoPlayTurn(
      11,
      'npc-1',
      $game_state,
      $dungeon_data,
      893,
      fn(string $entity_id, array $state, array $dungeon): array => [],
      fn(string $entity_id, array $state, int $campaign_id, ?array $ai_seed_action): array => [
        'intent_contract' => ['intent' => 'aggressive_engage'],
        'steps' => [[
          'action_type' => 'stride',
          'target' => 'pc-1',
          'decision_reason' => 'Close to melee range.',
          'decision_basis' => [],
        ]],
      ],
      fn(int $encounter_id, string $entity_id, string $target_id, array &$state, array &$dungeon, int $campaign_id): array => [],
      function (int $encounter_id, string $entity_id, array $to_hex, array &$state, array &$dungeon, int $campaign_id) use (&$captured_hex): array {
        $captured_hex = $to_hex;
        return [
          'from_hex' => ['q' => 3, 'r' => 2],
          'to_hex' => $to_hex,
          'mutations' => [],
        ];
      },
      function (string $target_id, string $entity_id, array &$state, array &$events, array $dungeon, int $campaign_id): void {},
      fn(string $entity_id, array $state): ?string => 'pc-1',
      fn(string $entity_id, string $decision_reason, array $decision_basis, array &$state, array &$dungeon, int $campaign_id): array => [],
      fn(string $entity_id, array $pending_dialogue, array &$state, array &$dungeon, int $campaign_id, string $decision_intent): array => []
    );

    $this->assertIsArray($captured_hex);
    $this->assertGreaterThan(1, $this->hexDistance($origin, $captured_hex), 'Aggressive stride should spend more than one hex of movement when needed.');
    $this->assertLessThanOrEqual(1, $this->hexDistance($target, $captured_hex), 'Aggressive stride should end within striking range when the target is reachable this turn.');
  }

  /**
   * @covers ::autoPlayTurn
   * @covers ::resolveStrideDestinationHex
   */
  public function testSelfPreserveStrideUsesBudgetToOpenDistance(): void {
    $service = $this->buildServiceWithMovementBudget(25);
    $captured_hex = NULL;
    $origin = ['q' => 0, 'r' => 0];
    $target = ['q' => 1, 'r' => 0];

    $game_state = [
      'round' => 1,
      'turn' => [
        'entity' => 'npc-1',
        'actions_remaining' => 1,
      ],
      'initiative_order' => [
        [
          'entity_id' => 'npc-1',
          'team' => 'enemy',
          'speed' => 25,
          'position_q' => $origin['q'],
          'position_r' => $origin['r'],
        ],
        [
          'entity_id' => 'pc-1',
          'team' => 'player',
          'speed' => 25,
          'position_q' => $target['q'],
          'position_r' => $target['r'],
          'is_defeated' => FALSE,
        ],
      ],
    ];
    $dungeon_data = [];

    $service->autoPlayTurn(
      11,
      'npc-1',
      $game_state,
      $dungeon_data,
      893,
      fn(string $entity_id, array $state, array $dungeon): array => [],
      fn(string $entity_id, array $state, int $campaign_id, ?array $ai_seed_action): array => [
        'intent_contract' => ['intent' => 'self_preserve'],
        'steps' => [[
          'action_type' => 'stride',
          'target' => 'pc-1',
          'decision_reason' => 'Open distance from the threat.',
          'decision_basis' => [],
        ]],
      ],
      fn(int $encounter_id, string $entity_id, string $target_id, array &$state, array &$dungeon, int $campaign_id): array => [],
      function (int $encounter_id, string $entity_id, array $to_hex, array &$state, array &$dungeon, int $campaign_id) use (&$captured_hex): array {
        $captured_hex = $to_hex;
        return [
          'from_hex' => ['q' => 0, 'r' => 0],
          'to_hex' => $to_hex,
          'mutations' => [],
        ];
      },
      function (string $target_id, string $entity_id, array &$state, array &$events, array $dungeon, int $campaign_id): void {},
      fn(string $entity_id, array $state): ?string => 'pc-1',
      fn(string $entity_id, string $decision_reason, array $decision_basis, array &$state, array &$dungeon, int $campaign_id): array => [],
      fn(string $entity_id, array $pending_dialogue, array &$state, array &$dungeon, int $campaign_id, string $decision_intent): array => []
    );

    $this->assertIsArray($captured_hex);
    $this->assertGreaterThan(1, $this->hexDistance($origin, $captured_hex), 'Self-preserve stride should spend movement to create separation.');
    $this->assertGreaterThan($this->hexDistance($origin, $target), $this->hexDistance($captured_hex, $target), 'Self-preserve stride should increase distance from the hostile.');
  }

  /**
   * @covers ::autoPlayTurn
   * @covers ::resolveGoalAlignedActionType
   */
  public function testAggressiveTurnKeepsSpendingActionsOnStrideUntilStrikeRange(): void {
    $service = $this->buildServiceWithMovementBudget(25);
    $stride_calls = [];
    $strike_calls = 0;

    $game_state = [
      'round' => 1,
      'turn' => [
        'entity' => 'npc-1',
        'actions_remaining' => 3,
      ],
      'initiative_order' => [
        [
          'entity_id' => 'npc-1',
          'team' => 'enemy',
          'speed' => 25,
          'position_q' => 0,
          'position_r' => 0,
        ],
        [
          'entity_id' => 'pc-1',
          'team' => 'player',
          'speed' => 25,
          'position_q' => 7,
          'position_r' => 0,
          'is_defeated' => FALSE,
        ],
      ],
    ];
    $dungeon_data = [];

    $service->autoPlayTurn(
      11,
      'npc-1',
      $game_state,
      $dungeon_data,
      893,
      fn(string $entity_id, array $state, array $dungeon): array => [],
      fn(string $entity_id, array $state, int $campaign_id, ?array $ai_seed_action): array => [
        'intent_contract' => ['intent' => 'aggressive_engage'],
        'steps' => [
          ['action_type' => 'stride', 'target' => 'pc-1', 'decision_reason' => 'Close distance.', 'decision_basis' => []],
          ['action_type' => 'strike', 'target' => 'pc-1', 'decision_reason' => 'Attack when able.', 'decision_basis' => []],
          ['action_type' => 'strike', 'target' => 'pc-1', 'decision_reason' => 'Attack when able.', 'decision_basis' => []],
        ],
      ],
      function (int $encounter_id, string $entity_id, string $target_id, array &$state, array &$dungeon, int $campaign_id) use (&$strike_calls): array {
        $strike_calls++;
        return ['hit' => TRUE, 'degree' => 'success', 'damage' => 4];
      },
      function (int $encounter_id, string $entity_id, array $to_hex, array &$state, array &$dungeon, int $campaign_id) use (&$stride_calls): array {
        $from_hex = [
          'q' => (int) $state['initiative_order'][0]['position_q'],
          'r' => (int) $state['initiative_order'][0]['position_r'],
        ];
        $stride_calls[] = ['from' => $from_hex, 'to' => $to_hex];
        $state['initiative_order'][0]['position_q'] = (int) $to_hex['q'];
        $state['initiative_order'][0]['position_r'] = (int) $to_hex['r'];
        return [
          'from_hex' => $from_hex,
          'to_hex' => $to_hex,
          'mutations' => [],
        ];
      },
      function (string $target_id, string $entity_id, array &$state, array &$events, array $dungeon, int $campaign_id): void {},
      fn(string $entity_id, array $state): ?string => 'pc-1',
      fn(string $entity_id, string $decision_reason, array $decision_basis, array &$state, array &$dungeon, int $campaign_id): array => [],
      fn(string $entity_id, array $pending_dialogue, array &$state, array &$dungeon, int $campaign_id, string $decision_intent): array => []
    );

    $this->assertCount(2, $stride_calls, 'Aggressive NPC should keep spending actions on stride until the target is reachable.');
    $this->assertSame(1, $strike_calls, 'Aggressive NPC should convert the first in-range follow-up action into a strike.');
    $this->assertLessThanOrEqual(1, $this->hexDistance(
      ['q' => (int) $game_state['initiative_order'][0]['position_q'], 'r' => (int) $game_state['initiative_order'][0]['position_r']],
      ['q' => 7, 'r' => 0]
    ));
  }

  /**
   * Regression: campaign 907 rendered two tokens stacked on hex (1,3).
   *
   * A defeated combatant still occupies its hex, so autoplay must never select
   * that hex as a stride destination.
   *
   * @covers ::autoPlayTurn
   * @covers ::resolveStrideDestinationHex
   * @covers ::buildOccupiedHexIndex
   */
  public function testStrideNeverTargetsHexOccupiedByDefeatedCombatant(): void {
    $service = $this->buildServiceWithMovementBudget(25);
    $captured_hex = NULL;
    $origin = ['q' => 3, 'r' => 2];
    $target = ['q' => 0, 'r' => 1];
    // Downed body parked on the hex this NPC would otherwise select: it is
    // adjacent to the target and scores highest for an aggressive closer.
    $body = ['q' => 0, 'r' => 0];

    $game_state = [
      'round' => 1,
      'turn' => [
        'entity' => 'npc-1',
        'actions_remaining' => 1,
      ],
      'initiative_order' => [
        [
          'entity_id' => 'npc-1',
          'team' => 'enemy',
          'speed' => 25,
          'position_q' => $origin['q'],
          'position_r' => $origin['r'],
        ],
        [
          'entity_id' => 'pc-1',
          'team' => 'player',
          'speed' => 25,
          'position_q' => $target['q'],
          'position_r' => $target['r'],
          'is_defeated' => FALSE,
        ],
        [
          'entity_id' => 'familiar-1',
          'team' => 'player',
          'speed' => 25,
          'position_q' => $body['q'],
          'position_r' => $body['r'],
          'is_defeated' => TRUE,
        ],
      ],
    ];
    $dungeon_data = [];

    $service->autoPlayTurn(
      11,
      'npc-1',
      $game_state,
      $dungeon_data,
      907,
      fn(string $entity_id, array $state, array $dungeon): array => [],
      fn(string $entity_id, array $state, int $campaign_id, ?array $ai_seed_action): array => [
        'intent_contract' => ['intent' => 'aggressive_engage'],
        'steps' => [[
          'action_type' => 'stride',
          'target' => 'pc-1',
          'decision_reason' => 'Close to melee range.',
          'decision_basis' => [],
        ]],
      ],
      fn(int $encounter_id, string $entity_id, string $target_id, array &$state, array &$dungeon, int $campaign_id): array => [],
      function (int $encounter_id, string $entity_id, array $to_hex, array &$state, array &$dungeon, int $campaign_id) use (&$captured_hex, $origin): array {
        $captured_hex = $to_hex;
        return [
          'from_hex' => $origin,
          'to_hex' => $to_hex,
          'mutations' => [],
        ];
      },
      function (string $target_id, string $entity_id, array &$state, array &$events, array $dungeon, int $campaign_id): void {},
      fn(string $entity_id, array $state): ?string => 'pc-1',
      fn(string $entity_id, string $decision_reason, array $decision_basis, array &$state, array &$dungeon, int $campaign_id): array => [],
      fn(string $entity_id, array $pending_dialogue, array &$state, array &$dungeon, int $campaign_id, string $decision_intent): array => []
    );

    $this->assertIsArray($captured_hex);
    $this->assertNotSame(
      [$body['q'], $body['r']],
      [(int) $captured_hex['q'], (int) $captured_hex['r']],
      'Autoplay must not stride onto a hex occupied by a defeated combatant.'
    );
    $this->assertLessThanOrEqual(
      1,
      $this->hexDistance($target, $captured_hex),
      'Autoplay should still close to striking range using an unoccupied hex.'
    );
  }

  /**
   * Build the service with deterministic movement behavior for tests.
   */
  protected function buildServiceWithMovementBudget(int $speed): ActorAutoplayCoordinator {
    $encounter_ai = $this->createMock(EncounterAiIntegrationService::class);
    $hazard_service = $this->getMockBuilder(HazardService::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['findHazardByInstanceId'])
      ->getMock();
    $hazard_service->method('findHazardByInstanceId')->willReturn(NULL);

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('encounter_ai_npc_autoplay_enabled')->willReturn(FALSE);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->with('dungeoncrawler_content.settings')->willReturn($config);

    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->with('dungeoncrawler')->willReturn($logger);

    $movement_resolver = $this->createMock(MovementResolverService::class);
    $movement_resolver->method('getCreatureSpeed')->willReturn($speed);
    $movement_resolver->method('calculateMovementCost')->willReturn(['cost' => 5, 'new_diagonal_count' => 0, 'terrain_type' => 'normal']);

    // Model an open room: every hex inside the bounds exists and is walkable,
    // matching live room layout data where walls are modelled by hex omission.
    $room_hexes = $this->buildOpenRoomHexes();
    $movement_resolver->method('buildMovementScope')->willReturn([
      '__scope_type' => 'movement_scope',
      'active_room_id' => 'test_room',
      'room_hexes' => $room_hexes,
      'hexes' => $room_hexes,
      'is_underwater' => FALSE,
    ]);
    $movement_resolver->method('isPassable')->willReturnCallback(
      function (array $hex): bool {
        $q = (int) ($hex['q'] ?? 0);
        $r = (int) ($hex['r'] ?? 0);
        return $q >= self::ROOM_MIN && $q <= self::ROOM_MAX
          && $r >= self::ROOM_MIN && $r <= self::ROOM_MAX;
      }
    );

    return new ActorAutoplayCoordinator(
      $encounter_ai,
      $hazard_service,
      NULL,
      $config_factory,
      $logger_factory,
      $movement_resolver
    );
  }

  /**
   * Compute hex distance for assertions.
   */
  protected function hexDistance(array $from, array $to): int {
    $dq = (int) $to['q'] - (int) $from['q'];
    $dr = (int) $to['r'] - (int) $from['r'];
    return max(abs($dq), abs($dr), abs($dq + $dr));
  }

}
