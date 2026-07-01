<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\ConnectorDefinitionService;
use Drupal\dungeoncrawler_content\Service\NavigationService;
use Drupal\Tests\UnitTestCase;

/**
 * Covers formalized navigation capability resolution.
 *
 * @group dungeoncrawler_content
 * @group navigation
 */
class NavigationServiceTest extends UnitTestCase {

  private NavigationService $service;

  protected function setUp(): void {
    parent::setUp();
    $this->service = new NavigationService();
  }

  /**
   * Verifies the service formalizes adjacent room capabilities deterministically.
   */
  public function testBuildNavigationCapabilitiesIncludesAvailabilityMetadata(): void {
    $service = new NavigationService();

    $capabilities = $service->buildNavigationCapabilities([
      'hex_map' => [
        'connections' => [
          [
            'connection_id' => 'guard_to_boss',
            'from_room' => 'guard_chamber',
            'to_room' => 'boss_chamber',
            'type' => 'locked_door',
            'from_hex' => ['q' => 2, 'r' => 1],
            'to_hex' => ['q' => 0, 'r' => 0],
            'is_discovered' => TRUE,
            'is_passable' => FALSE,
          ],
          [
            'connection_id' => 'guard_to_hall',
            'from_room' => 'guard_chamber',
            'to_room' => 'great_hall',
            'type' => 'open_passage',
            'from_hex' => ['q' => 3, 'r' => 1],
            'to_hex' => ['q' => 0, 'r' => 1],
            'is_discovered' => TRUE,
            'is_passable' => TRUE,
          ],
        ],
      ],
    ], 'guard_chamber');

    $this->assertCount(2, $capabilities);
    $this->assertSame('guard_to_hall', $capabilities[0]['connection_id']);
    $this->assertTrue($capabilities[0]['available']);
    $this->assertSame('great_hall', $capabilities[0]['target_room_id']);
    $this->assertSame('room', $capabilities[0]['destination_type']);
    $this->assertSame('great_hall', $capabilities[0]['destination_id']);
    $this->assertSame(0, $capabilities[0]['distance']);
    $this->assertSame('guard_to_boss', $capabilities[1]['connection_id']);
    $this->assertFalse($capabilities[1]['available']);
    $this->assertSame('blocked', $capabilities[1]['blocked_reason']);
    $this->assertTrue($capabilities[1]['requires_interaction']);
  }

  /**
   * Verifies requested navigation resolves from the clicked origin hex.
   */
  public function testResolveRequestedCapabilityMatchesOriginHex(): void {
    $service = new NavigationService();

    $capability = $service->resolveRequestedCapability([
      'hex_map' => [
        'connections' => [
          [
            'from_room' => 'front_room',
            'to_room' => 'back_room',
            'type' => 'door',
            'from_hex' => ['q' => 4, 'r' => 2],
            'to_hex' => ['q' => 0, 'r' => 0],
          ],
        ],
      ],
    ], 'front_room', NULL, ['q' => 4, 'r' => 2]);

    $this->assertNotNull($capability);
    $this->assertSame('back_room', $capability['target_room_id']);
    $this->assertTrue($capability['available']);
  }

  /**
   * Verifies fallback ids disambiguate parallel edges when explicit ids are absent.
   */
  public function testBuildNavigationCapabilitiesDerivesDistinctFallbackConnectionIds(): void {
    $service = new NavigationService();

    $capabilities = $service->buildNavigationCapabilities([
      'hex_map' => [
        'connections' => [
          [
            'from_room' => 'hall',
            'to_room' => 'atrium',
            'type' => 'door',
            'from_hex' => ['q' => 0, 'r' => 0],
            'to_hex' => ['q' => 1, 'r' => 0],
            'is_discovered' => TRUE,
            'is_passable' => TRUE,
          ],
          [
            'from_room' => 'hall',
            'to_room' => 'atrium',
            'type' => 'door',
            'from_hex' => ['q' => 2, 'r' => 0],
            'to_hex' => ['q' => 3, 'r' => 0],
            'is_discovered' => TRUE,
            'is_passable' => TRUE,
          ],
        ],
      ],
    ], 'hall');

    $connection_ids = array_values(array_map(static fn(array $capability): string => (string) ($capability['connection_id'] ?? ''), $capabilities));
    $this->assertCount(2, $connection_ids);
    $this->assertCount(2, array_values(array_unique($connection_ids)));
  }

  /**
   * Verifies canonical connections are merged from both payload sources.
   */
  public function testBuildNavigationCapabilitiesMergesHexMapAndTopLevelConnections(): void {
    $service = new NavigationService();

    $capabilities = $service->buildNavigationCapabilities([
      'hex_map' => [
        'connections' => [],
      ],
      'connections' => [
        [
          'from_room_id' => 'tavern_entrance',
          'to_room_id' => 'aca99b77-e480-4d34-bd28-0314dce5cd7f',
          'type' => 'passage',
          'from' => ['q' => 0, 'r' => 0],
          'to' => ['q' => 1, 'r' => 0],
          'is_discovered' => TRUE,
          'is_passable' => TRUE,
        ],
      ],
    ], 'tavern_entrance');

    $this->assertCount(1, $capabilities);
    $this->assertSame('aca99b77-e480-4d34-bd28-0314dce5cd7f', $capabilities[0]['target_room_id']);
    $this->assertSame('tavern_entrance__aca99b77-e480-4d34-bd28-0314dce5cd7f__passage__0:0__1:0', $capabilities[0]['connection_id']);
    $this->assertTrue($capabilities[0]['available']);
  }

  /**
   * Verifies canonical connector-table fields are normalized for navigation.
   */
  public function testBuildNavigationCapabilitiesNormalizesCanonicalConnectorContract(): void {
    $service = new NavigationService();

    $capabilities = $service->buildNavigationCapabilities([
      'connections' => [
        [
          'connection_id' => 'tavern::market::door',
          'from_room_id' => 'tavern_entrance',
          'to_room_id' => 'market_square',
          'kind' => 'door',
          'direction' => 'one_way',
          'default_state' => 'locked',
          'is_discovered_default' => 1,
        ],
      ],
    ], 'tavern_entrance');

    $this->assertCount(1, $capabilities);
    $this->assertSame('tavern::market::door', $capabilities[0]['connection_id']);
    $this->assertSame('market_square', $capabilities[0]['target_room_id']);
    $this->assertSame('door', $capabilities[0]['type']);
    $this->assertFalse($capabilities[0]['available']);
    $this->assertSame('blocked', $capabilities[0]['blocked_reason']);
    $this->assertTrue($capabilities[0]['requires_interaction']);
    $this->assertFalse($capabilities[0]['bidirectional']);
  }

  /**
   * Verifies connector-table rows are authoritative over payload fallback arrays.
   */
  public function testBuildNavigationCapabilitiesPrefersConnectorTableRows(): void {
    $connector_service = $this->getMockBuilder(ConnectorDefinitionService::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['loadCanonicalConnectorsForDungeon'])
      ->getMock();

    $connector_service->expects($this->once())
      ->method('loadCanonicalConnectorsForDungeon')
      ->with('starter_dungeon')
      ->willReturn([
        [
          'connection_id' => 'canonical_tavern_market',
          'dungeon_id' => 'starter_dungeon',
          'from_room_id' => 'tavern_entrance',
          'to_room_id' => 'market_square',
          'kind' => 'door',
          'direction' => 'bidirectional',
          'default_state' => 'open',
          'is_discovered_default' => 1,
        ],
      ]);

    $service = new NavigationService(NULL, $connector_service);

    $capabilities = $service->buildNavigationCapabilities([
      'dungeon_id' => 'starter_dungeon',
      'connections' => [
        [
          'connection_id' => 'payload_tavern_wrong',
          'from_room' => 'tavern_entrance',
          'to_room' => 'wrong_room',
          'type' => 'door',
          'is_discovered' => TRUE,
          'is_passable' => TRUE,
        ],
      ],
    ], 'tavern_entrance');

    $this->assertCount(1, $capabilities);
    $this->assertSame('canonical_tavern_market', $capabilities[0]['connection_id']);
    $this->assertSame('market_square', $capabilities[0]['target_room_id']);
  }

  /**
   * Verifies malformed connector-table rows fail hard.
   */
  public function testBuildNavigationCapabilitiesRejectsInvalidCanonicalConnectorDirection(): void {
    $connector_service = $this->getMockBuilder(ConnectorDefinitionService::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['loadCanonicalConnectorsForDungeon'])
      ->getMock();

    $connector_service->expects($this->once())
      ->method('loadCanonicalConnectorsForDungeon')
      ->with('starter_dungeon')
      ->willReturn([
        [
          'connection_id' => 'canonical_invalid_direction',
          'dungeon_id' => 'starter_dungeon',
          'from_room_id' => 'tavern_entrance',
          'to_room_id' => 'market_square',
          'kind' => 'door',
          'direction' => 'sideways',
          'default_state' => 'open',
        ],
      ]);

    $service = new NavigationService(NULL, $connector_service);
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('invalid direction');

    $service->buildNavigationCapabilities([
      'dungeon_id' => 'starter_dungeon',
    ], 'tavern_entrance');
  }

  /**
   * Verifies conflicting duplicate connection identities fail hard.
   */
  public function testBuildNavigationCapabilitiesRejectsConflictingDuplicateIdentityContracts(): void {
    $service = new NavigationService();
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('duplicate identity');

    $service->buildNavigationCapabilities([
      'connections' => [
        [
          'connection_id' => 'hall_exit',
          'from_room' => 'hall',
          'to_room' => 'atrium',
          'type' => 'door',
          'is_discovered' => TRUE,
          'is_passable' => TRUE,
        ],
        [
          'connection_id' => 'hall_exit',
          'from_room' => 'hall',
          'to_room' => 'library',
          'type' => 'door',
          'is_discovered' => TRUE,
          'is_passable' => TRUE,
        ],
      ],
    ], 'hall');
  }

  /**
   * Verifies malformed connection entries fail instead of being skipped.
   */
  public function testBuildNavigationCapabilitiesRejectsNonArrayConnectionPayload(): void {
    $service = new NavigationService();
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('must be an object payload');

    $service->buildNavigationCapabilities([
      'connections' => [
        'not-an-object',
      ],
    ], 'hall');
  }

  /**
   * Verifies direct room transitions enforce zero-distance contracts.
   */
  public function testBuildNavigationCapabilitiesRejectsNonZeroDirectRoomDistance(): void {
    $service = new NavigationService();

    $capabilities = $service->buildNavigationCapabilities([
      'hex_map' => [
        'connections' => [
          [
            'connection_id' => 'hall_to_atrium',
            'from_room' => 'hall',
            'to_room' => 'atrium',
            'type' => 'open_passage',
            'distance' => 5,
            'is_discovered' => TRUE,
            'is_passable' => TRUE,
          ],
        ],
      ],
    ], 'hall');

    $this->assertCount(1, $capabilities);
    $this->assertFalse($capabilities[0]['available']);
    $this->assertSame('invalid_distance_contract', $capabilities[0]['blocked_reason']);
    $this->assertSame(5, $capabilities[0]['distance']);
  }

  /**
   * Verifies road connections without a room anchor are rejected.
   */
  public function testBuildNavigationCapabilitiesRejectsRoadWithoutAnchor(): void {
    $service = new NavigationService();

    $capabilities = $service->buildNavigationCapabilities([
      'hex_map' => [
        'connections' => [
          [
            'connection_id' => 'hall_to_road',
            'from_room' => 'hall',
            'to_room' => '',
            'to_type' => 'road',
            'road_node_id' => 'north_road_node_1',
            'type' => 'gate',
            'distance' => 2,
            'is_discovered' => TRUE,
            'is_passable' => TRUE,
          ],
        ],
      ],
      'room_road_anchors' => [],
    ], 'hall');

    $this->assertCount(1, $capabilities);
    $this->assertFalse($capabilities[0]['available']);
    $this->assertSame('missing_road_anchor', $capabilities[0]['blocked_reason']);
    $this->assertSame('road', $capabilities[0]['destination_type']);
  }

  /**
   * Verifies road connections with a room anchor are allowed.
   */
  public function testBuildNavigationCapabilitiesAllowsRoadWithAnchor(): void {
    $service = new NavigationService();

    $capabilities = $service->buildNavigationCapabilities([
      'hex_map' => [
        'connections' => [
          [
            'connection_id' => 'hall_to_road',
            'from_room' => 'hall',
            'to_room' => '',
            'to_type' => 'road',
            'road_node_id' => 'north_road_node_1',
            'type' => 'gate',
            'distance' => 2,
            'is_discovered' => TRUE,
            'is_passable' => TRUE,
          ],
        ],
      ],
      'room_road_anchors' => [
        [
          'room_id' => 'hall',
          'road_node_id' => 'north_road_node_1',
          'access_distance' => 2,
        ],
      ],
    ], 'hall');

    $this->assertCount(1, $capabilities);
    $this->assertTrue($capabilities[0]['available']);
    $this->assertSame('road', $capabilities[0]['destination_type']);
    $this->assertSame('north_road_node_1', $capabilities[0]['destination_id']);
    $this->assertSame(2, $capabilities[0]['distance']);
  }

  /**
   * Verifies missing destination metadata blocks a capability.
   */
  public function testBuildNavigationCapabilitiesRejectsMissingDestinationType(): void {
    $service = new NavigationService();

    $capabilities = $service->buildNavigationCapabilities([
      'hex_map' => [
        'connections' => [
          [
            'connection_id' => 'hall_to_unknown',
            'from_room' => 'hall',
            'to_room' => '',
            'to_type' => '',
            'is_discovered' => TRUE,
            'is_passable' => TRUE,
          ],
        ],
      ],
    ], 'hall');

    $this->assertCount(1, $capabilities);
    // Missing destination falls through to 'room' type, but empty to_room causes unresolved_destination
    $this->assertFalse($capabilities[0]['available']);
    $this->assertSame('unresolved_destination', $capabilities[0]['blocked_reason']);
  }

  /**
   * Verifies conflicting duplicate exits are hard-failed.
   */
  public function testBuildNavigationCapabilitiesRejectsDuplicateExitDestinationConflict(): void {
    $service = new NavigationService();

    $capabilities = $service->buildNavigationCapabilities([
      'hex_map' => [
        'connections' => [
          [
            'connection_id' => 'north_gate',
            'from_room' => 'hall',
            'to_room' => 'atrium',
            'type' => 'open_passage',
            'distance' => 0,
            'is_discovered' => TRUE,
            'is_passable' => TRUE,
          ],
          [
            'connection_id' => 'north_gate',
            'from_room' => 'hall',
            'to_room' => 'barracks',
            'type' => 'open_passage',
            'distance' => 0,
            'is_discovered' => TRUE,
            'is_passable' => TRUE,
          ],
        ],
      ],
    ], 'hall');

    $this->assertCount(2, $capabilities);
    $this->assertFalse($capabilities[0]['available']);
    $this->assertFalse($capabilities[1]['available']);
    $this->assertSame('duplicate_exit_conflict', $capabilities[0]['blocked_reason']);
    $this->assertSame('duplicate_exit_conflict', $capabilities[1]['blocked_reason']);
  }

  /**
   * Verifies duplicate exits with conflicting road distance are hard-failed.
   */
  public function testBuildNavigationCapabilitiesRejectsDuplicateExitDistanceConflict(): void {
    $service = new NavigationService();

    $capabilities = $service->buildNavigationCapabilities([
      'hex_map' => [
        'connections' => [
          [
            'connection_id' => 'hall_road_gate',
            'from_room' => 'hall',
            'to_room' => '',
            'to_type' => 'road',
            'road_node_id' => 'north_road_node_1',
            'type' => 'gate',
            'distance' => 2,
            'is_discovered' => TRUE,
            'is_passable' => TRUE,
          ],
          [
            'connection_id' => 'hall_road_gate',
            'from_room' => 'hall',
            'to_room' => '',
            'to_type' => 'road',
            'road_node_id' => 'north_road_node_1',
            'type' => 'gate',
            'distance' => 5,
            'is_discovered' => TRUE,
            'is_passable' => TRUE,
          ],
        ],
      ],
      'room_road_anchors' => [
        [
          'room_id' => 'hall',
          'road_node_id' => 'north_road_node_1',
          'access_distance' => 2,
        ],
      ],
    ], 'hall');

    $this->assertCount(2, $capabilities);
    $this->assertFalse($capabilities[0]['available']);
    $this->assertFalse($capabilities[1]['available']);
    $this->assertSame('duplicate_exit_conflict', $capabilities[0]['blocked_reason']);
    $this->assertSame('duplicate_exit_conflict', $capabilities[1]['blocked_reason']);
  }

  /**
   * Verifies identical duplicate exits are not falsely flagged as conflicts.
   */
  public function testBuildNavigationCapabilitiesAllowsIdenticalDuplicateExitContracts(): void {
    $service = new NavigationService();

    $capabilities = $service->buildNavigationCapabilities([
      'hex_map' => [
        'connections' => [
          [
            'connection_id' => 'north_gate',
            'from_room' => 'hall',
            'to_room' => 'atrium',
            'type' => 'open_passage',
            'distance' => 0,
            'is_discovered' => TRUE,
            'is_passable' => TRUE,
            'bidirectional' => TRUE,
          ],
          [
            'connection_id' => 'north_gate',
            'from_room' => 'hall',
            'to_room' => 'atrium',
            'type' => 'open_passage',
            'distance' => 0,
            'is_discovered' => TRUE,
            'is_passable' => TRUE,
            'bidirectional' => TRUE,
          ],
        ],
      ],
    ], 'hall');

    $this->assertCount(2, $capabilities);
    $this->assertTrue($capabilities[0]['available']);
    $this->assertTrue($capabilities[1]['available']);
    $this->assertNotSame('duplicate_exit_conflict', $capabilities[0]['blocked_reason']);
    $this->assertNotSame('duplicate_exit_conflict', $capabilities[1]['blocked_reason']);
  }

  /**
   * Tests buildNavigationCapabilitiesWithQuestTargets adds quest destinations.
   */
  public function testBuildNavigationCapabilitiesWithQuestTargetsAddsQuests(): void {
    $dungeon = [
      'connections' => [],
      'rooms' => [
        ['room_id' => 'start', 'name' => 'Starting Room'],
        ['room_id' => 'vault-entry', 'name' => 'Vault Entry'],
      ],
    ];

    $active_quests = [
      [
        'quest_id' => 'quest-1',
        'objectives' => [
          ['destination_id' => 'vault-entry'],
        ],
      ],
    ];

    $capabilities = $this->service->buildNavigationCapabilitiesWithQuestTargets(
      $dungeon,
      'start',
      $active_quests
    );

    // Should have the synthetic quest destination
    $quest_caps = array_filter($capabilities, fn($c) => 
      ($c['quest_reference'] ?? FALSE) === TRUE
    );
    $this->assertCount(1, $quest_caps);
    
    $quest_cap = array_values($quest_caps)[0];
    $this->assertSame('vault-entry', $quest_cap['target_room_id']);
    $this->assertSame(['quest-1'], $quest_cap['quest_ids']);
  }

  /**
   * Tests buildNavigationCapabilitiesWithQuestTargets resolves by room name.
   */
  public function testBuildNavigationCapabilitiesWithQuestTargetsResolvesByName(): void {
    $dungeon = [
      'connections' => [],
      'rooms' => [
        ['room_id' => 'start', 'name' => 'Starting Room'],
        ['room_id' => 'vault-entry', 'name' => 'Vault Entry'],
      ],
    ];

    $active_quests = [
      [
        'quest_id' => 'quest-1',
        'objectives' => [
          ['destination' => 'Vault Entry'], // Use name, not ID
        ],
      ],
    ];

    $capabilities = $this->service->buildNavigationCapabilitiesWithQuestTargets(
      $dungeon,
      'start',
      $active_quests
    );

    $quest_caps = array_filter($capabilities, fn($c) => 
      ($c['quest_reference'] ?? FALSE) === TRUE
    );
    $this->assertCount(1, $quest_caps);
  }

  /**
   * Invalid quest destinations hard-fail contract validation.
   */
  public function testBuildNavigationCapabilitiesWithQuestTargetsRejectsInvalidDestination(): void {
    $dungeon = [
      'connections' => [],
      'rooms' => [
        ['room_id' => 'start', 'name' => 'Starting Room'],
      ],
    ];

    $active_quests = [
      [
        'quest_id' => 'quest-1',
        'objectives' => [
          ['destination' => 'Non Existent Room'],
        ],
      ],
    ];

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Quest destination contract violation');
    $this->service->buildNavigationCapabilitiesWithQuestTargets(
      $dungeon,
      'start',
      $active_quests
    );
  }

  /**
   * Quest entries with destination metadata must include quest_id.
   */
  public function testBuildNavigationCapabilitiesWithQuestTargetsRejectsDestinationWithoutQuestId(): void {
    $dungeon = [
      'connections' => [],
      'rooms' => [
        ['room_id' => 'start', 'name' => 'Starting Room'],
        ['room_id' => 'room-a', 'name' => 'Room A'],
      ],
    ];

    $active_quests = [
      [
        'generated_objectives' => [
          [
            'phase' => 1,
            'objectives' => [
              ['destination_id' => 'room-a'],
            ],
          ],
        ],
      ],
    ];

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('quest_id is required');
    $this->service->buildNavigationCapabilitiesWithQuestTargets(
      $dungeon,
      'start',
      $active_quests
    );
  }

  /**
   * Tests buildNavigationCapabilitiesWithQuestTargets with multiple quests.
   */
  public function testBuildNavigationCapabilitiesWithQuestTargetsMultiple(): void {
    $dungeon = [
      'connections' => [],
      'rooms' => [
        ['room_id' => 'start', 'name' => 'Starting Room'],
        ['room_id' => 'room-a', 'name' => 'Room A'],
        ['room_id' => 'room-b', 'name' => 'Room B'],
      ],
    ];

    $active_quests = [
      [
        'quest_id' => 'quest-1',
        'objectives' => [['destination_id' => 'room-a']],
      ],
      [
        'quest_id' => 'quest-2',
        'objectives' => [['destination_id' => 'room-b']],
      ],
    ];

    $capabilities = $this->service->buildNavigationCapabilitiesWithQuestTargets(
      $dungeon,
      'start',
      $active_quests
    );

    $quest_caps = array_filter($capabilities, fn($c) => 
      ($c['quest_reference'] ?? FALSE) === TRUE
    );
    $this->assertCount(2, $quest_caps);
  }

  /**
   * Tests buildNavigationCapabilitiesWithQuestTargets handles empty quests gracefully.
   */
  public function testBuildNavigationCapabilitiesWithQuestTargetsEmptyQuests(): void {
    $dungeon = [
      'connections' => [],
      'rooms' => [
        ['room_id' => 'start', 'name' => 'Starting Room'],
      ],
    ];

    $capabilities = $this->service->buildNavigationCapabilitiesWithQuestTargets(
      $dungeon,
      'start',
      [] // Empty quests
    );

    // Should just return normal capabilities (empty in this case)
    $this->assertIsArray($capabilities);
  }

  /**
   * Quest summary-style phased objectives are recognized as quest destinations.
   */
  public function testBuildNavigationCapabilitiesWithQuestTargetsSupportsPhasedObjectiveShape(): void {
    $dungeon = [
      'connections' => [],
      'rooms' => [
        ['room_id' => 'start', 'name' => 'Starting Room'],
        ['room_id' => 'vault-entry', 'name' => 'Vault Entry'],
      ],
    ];

    $active_quests = [
      [
        'quest_id' => 'quest-1',
        'generated_objectives' => [
          [
            'phase' => 1,
            'objectives' => [
              ['destination_id' => 'vault-entry'],
            ],
          ],
        ],
      ],
    ];

    $capabilities = $this->service->buildNavigationCapabilitiesWithQuestTargets(
      $dungeon,
      'start',
      $active_quests
    );

    $quest_caps = array_values(array_filter($capabilities, static fn(array $capability): bool => !empty($capability['quest_reference'])));
    $this->assertCount(1, $quest_caps);
    $this->assertSame('vault-entry', (string) ($quest_caps[0]['target_room_id'] ?? ''));
    $this->assertSame(['quest-1'], (array) ($quest_caps[0]['quest_ids'] ?? []));
  }

  /**
   * Existing room capabilities are marked quest-referenced (not silently skipped).
   */
  public function testBuildNavigationCapabilitiesWithQuestTargetsMarksExistingCapabilityByReference(): void {
    $dungeon = [
      'connections' => [
        [
          'connection_id' => 'door-1',
          'from_room' => 'start',
          'to_room' => 'vault-entry',
          'type' => 'open_passage',
          'distance' => 0,
          'is_discovered' => TRUE,
          'is_passable' => TRUE,
          'bidirectional' => TRUE,
        ],
      ],
      'rooms' => [
        ['room_id' => 'start', 'name' => 'Starting Room'],
        ['room_id' => 'vault-entry', 'name' => 'Vault Entry'],
      ],
    ];

    $active_quests = [
      [
        'quest_id' => 'quest-1',
        'generated_objectives' => [
          [
            'phase' => 1,
            'objectives' => [
              ['destination_id' => 'vault-entry'],
            ],
          ],
        ],
      ],
    ];

    $capabilities = $this->service->buildNavigationCapabilitiesWithQuestTargets(
      $dungeon,
      'start',
      $active_quests
    );

    $vault_capability = NULL;
    foreach ($capabilities as $capability) {
      if ((string) ($capability['target_room_id'] ?? '') === 'vault-entry') {
        $vault_capability = $capability;
        break;
      }
    }

    $this->assertNotNull($vault_capability);
    $this->assertSame('door-1', (string) ($vault_capability['connection_id'] ?? ''));
    $this->assertTrue(!empty($vault_capability['quest_reference']));
    $this->assertSame(['quest-1'], (array) ($vault_capability['quest_ids'] ?? []));
  }

  /**
   * Tests hasRoadConnection detects road connections.
   */
  public function testHasRoadConnectionTrue(): void {
    $dungeon = [
      'connections' => [
        [
          'from_room' => 'tavern',
          'to_room' => 'market',
          'destination_type' => 'road',
          'type' => 'road',
        ],
      ],
      'rooms' => [
        ['room_id' => 'tavern', 'name' => 'Tavern'],
        ['room_id' => 'market', 'name' => 'Market'],
      ],
    ];

    $has_road = $this->getPrivateMethod('hasRoadConnection')->invoke(
      $this->service,
      $dungeon,
      'tavern'
    );

    $this->assertTrue($has_road);
  }

  /**
   * Tests hasRoadConnection returns false for room without road.
   */
  public function testHasRoadConnectionFalse(): void {
    $dungeon = [
      'connections' => [
        [
          'from_room' => 'tavern',
          'to_room' => 'back_room',
          'destination_type' => 'room',
          'type' => 'passage',
        ],
      ],
      'rooms' => [
        ['room_id' => 'tavern', 'name' => 'Tavern'],
        ['room_id' => 'back_room', 'name' => 'Back Room'],
      ],
    ];

    $has_road = $this->getPrivateMethod('hasRoadConnection')->invoke(
      $this->service,
      $dungeon,
      'tavern'
    );

    $this->assertFalse($has_road);
  }

  /**
   * Tests extractRoadNetworkRooms collects all road-connected rooms.
   */
  public function testExtractRoadNetworkRooms(): void {
    $dungeon = [
      'connections' => [
        ['from_room' => 'tavern', 'destination_type' => 'road'],
        ['from_room' => 'market', 'destination_type' => 'road'],
        ['from_room' => 'dungeon', 'destination_type' => 'room'],
        ['from_room' => 'garden', 'destination_type' => 'road'],
      ],
      'rooms' => [
        ['room_id' => 'tavern'],
        ['room_id' => 'market'],
        ['room_id' => 'dungeon'],
        ['room_id' => 'garden'],
      ],
    ];

    $road_rooms = $this->getPrivateMethod('extractRoadNetworkRooms')->invoke(
      $this->service,
      $dungeon,
      'tavern' // Current room (excluded from results)
    );

    // Should include market and garden, but NOT tavern (current) or dungeon (no road)
    $this->assertCount(2, $road_rooms);
    $this->assertContains('market', $road_rooms);
    $this->assertContains('garden', $road_rooms);
    $this->assertNotContains('tavern', $road_rooms); // Current room excluded
    $this->assertNotContains('dungeon', $road_rooms); // No road connection
  }

  /**
   * Tests buildNavigationCapabilitiesWithRoadNetwork for road-connected room.
   */
  public function testBuildNavigationCapabilitiesWithRoadNetworkRoadRoom(): void {
    $dungeon = [
      'connections' => [
        // Direct connection
        [
          'from_room' => 'tavern',
          'to_room' => 'back_room',
          'destination_type' => 'room',
          'type' => 'passage',
        ],
        // Road connections
        [
          'from_room' => 'tavern',
          'to_room' => 'road-node-1',
          'destination_type' => 'road',
          'type' => 'road',
        ],
        [
          'from_room' => 'market',
          'to_room' => 'road-node-1',
          'destination_type' => 'road',
          'type' => 'road',
        ],
        [
          'from_room' => 'garden',
          'to_room' => 'road-node-1',
          'destination_type' => 'road',
          'type' => 'road',
        ],
      ],
      'rooms' => [
        ['room_id' => 'tavern', 'name' => 'Tavern'],
        ['room_id' => 'back_room', 'name' => 'Back Room'],
        ['room_id' => 'market', 'name' => 'Market'],
        ['room_id' => 'garden', 'name' => 'Garden'],
      ],
      'room_road_anchors' => [
        ['room_id' => 'tavern', 'road_node_id' => 'road-node-1', 'access_distance' => 1],
        ['room_id' => 'market', 'road_node_id' => 'road-node-2', 'access_distance' => 1],
        ['room_id' => 'garden', 'road_node_id' => 'road-node-3', 'access_distance' => 2],
      ],
      'road_edges' => [
        ['from_node_id' => 'road-node-1', 'to_node_id' => 'road-node-2', 'distance' => 4, 'bidirectional' => TRUE],
        ['from_node_id' => 'road-node-1', 'to_node_id' => 'road-node-3', 'distance' => 2, 'bidirectional' => TRUE],
      ],
    ];

    $capabilities = $this->service->buildNavigationCapabilitiesWithRoadNetwork(
      $dungeon,
      'tavern'
    );

    // Should have: back_room (direct) + market (road) + garden (road) = 3 capabilities
    $this->assertGreaterThanOrEqual(3, count($capabilities));

    // Verify back_room is there (direct connection)
    $back_room_cap = array_filter($capabilities, fn($c) => 
      ($c['target_room_id'] ?? '') === 'back_room'
    );
    $this->assertCount(1, $back_room_cap);

    // Verify market is there (road network)
    $market_cap = array_filter($capabilities, fn($c) => 
      ($c['target_room_id'] ?? '') === 'market'
    );
    $this->assertCount(1, $market_cap);
    $this->assertTrue(($market_cap[array_key_first($market_cap)]['is_road_network'] ?? FALSE));
    $this->assertTrue(($market_cap[array_key_first($market_cap)]['available'] ?? FALSE));
    $this->assertSame(6, $market_cap[array_key_first($market_cap)]['distance'] ?? NULL);

    // Verify garden is there (road network)
    $garden_cap = array_filter($capabilities, fn($c) => 
      ($c['target_room_id'] ?? '') === 'garden'
    );
    $this->assertCount(1, $garden_cap);
    $this->assertTrue(($garden_cap[array_key_first($garden_cap)]['is_road_network'] ?? FALSE));
    $this->assertTrue(($garden_cap[array_key_first($garden_cap)]['available'] ?? FALSE));
    $this->assertSame(5, $garden_cap[array_key_first($garden_cap)]['distance'] ?? NULL);
  }

  /**
   * Tests buildNavigationCapabilitiesWithRoadNetwork for non-road room.
   */
  public function testBuildNavigationCapabilitiesWithRoadNetworkNonRoadRoom(): void {
    $dungeon = [
      'connections' => [
        // Direct connections only
        [
          'from_room' => 'tavern',
          'to_room' => 'back_room',
          'destination_type' => 'room',
          'type' => 'passage',
        ],
        // Other rooms have road connections
        [
          'from_room' => 'market',
          'to_room' => 'road-node-1',
          'destination_type' => 'road',
          'type' => 'road',
        ],
      ],
      'rooms' => [
        ['room_id' => 'tavern', 'name' => 'Tavern'],
        ['room_id' => 'back_room', 'name' => 'Back Room'],
        ['room_id' => 'market', 'name' => 'Market'],
      ],
    ];

    $capabilities = $this->service->buildNavigationCapabilitiesWithRoadNetwork(
      $dungeon,
      'tavern'
    );

    // Should have ONLY back_room (direct connection)
    // Market should NOT be included because tavern has no road connection
    $this->assertCount(1, $capabilities);
    $this->assertSame('back_room', $capabilities[0]['target_room_id']);
  }

  /**
   * Tests buildNavigationCapabilitiesWithRoadNetwork with no direct connections.
   */
  public function testBuildNavigationCapabilitiesWithRoadNetworkOnlyRoad(): void {
    $dungeon = [
      'connections' => [
        // Only road connections from tavern
        [
          'from_room' => 'tavern',
          'to_room' => 'road-node-1',
          'destination_type' => 'road',
          'type' => 'road',
        ],
        [
          'from_room' => 'market',
          'to_room' => 'road-node-1',
          'destination_type' => 'road',
          'type' => 'road',
        ],
        [
          'from_room' => 'garden',
          'to_room' => 'road-node-1',
          'destination_type' => 'road',
          'type' => 'road',
        ],
      ],
      'rooms' => [
        ['room_id' => 'tavern', 'name' => 'Tavern'],
        ['room_id' => 'market', 'name' => 'Market'],
        ['room_id' => 'garden', 'name' => 'Garden'],
      ],
      'room_road_anchors' => [
        ['room_id' => 'tavern', 'road_node_id' => 'road-node-1', 'access_distance' => 1],
        ['room_id' => 'market', 'road_node_id' => 'road-node-2', 'access_distance' => 1],
        ['room_id' => 'garden', 'road_node_id' => 'road-node-3', 'access_distance' => 1],
      ],
      'road_edges' => [
        ['from_node_id' => 'road-node-1', 'to_node_id' => 'road-node-2', 'distance' => 2, 'bidirectional' => TRUE],
        ['from_node_id' => 'road-node-2', 'to_node_id' => 'road-node-3', 'distance' => 3, 'bidirectional' => TRUE],
      ],
    ];

    $capabilities = $this->service->buildNavigationCapabilitiesWithRoadNetwork(
      $dungeon,
      'tavern'
    );

    // Should have market + garden via road network (no direct connections)
    $this->assertCount(2, $capabilities);

    $road_caps = array_filter($capabilities, fn($c) => 
      ($c['is_road_network'] ?? FALSE) === TRUE
    );
    $this->assertCount(2, $road_caps);
    foreach ($road_caps as $capability) {
      $this->assertTrue($capability['available'] ?? FALSE);
      $this->assertGreaterThan(0, (int) ($capability['distance'] ?? 0));
    }
  }

  /**
   * Tests road network synthetic capabilities are bidirectional.
   */
  public function testRoadNetworkSyntheticCapabilitiesBidirectional(): void {
    $dungeon = [
      'connections' => [
        ['from_room' => 'tavern', 'destination_type' => 'road'],
        ['from_room' => 'market', 'destination_type' => 'road'],
      ],
      'rooms' => [
        ['room_id' => 'tavern', 'name' => 'Tavern'],
        ['room_id' => 'market', 'name' => 'Market'],
      ],
      'room_road_anchors' => [
        ['room_id' => 'tavern', 'road_node_id' => 'road-node-1', 'access_distance' => 1],
        ['room_id' => 'market', 'road_node_id' => 'road-node-2', 'access_distance' => 1],
      ],
      'road_edges' => [
        ['from_node_id' => 'road-node-1', 'to_node_id' => 'road-node-2', 'distance' => 2, 'bidirectional' => TRUE],
      ],
    ];

    $tavern_caps = $this->service->buildNavigationCapabilitiesWithRoadNetwork(
      $dungeon,
      'tavern'
    );

    $market_caps = $this->service->buildNavigationCapabilitiesWithRoadNetwork(
      $dungeon,
      'market'
    );

    // Both should have access to each other
    $tavern_to_market = array_filter($tavern_caps, fn($c) => 
      ($c['target_room_id'] ?? '') === 'market'
    );
    $this->assertCount(1, $tavern_to_market);

    $market_to_tavern = array_filter($market_caps, fn($c) => 
      ($c['target_room_id'] ?? '') === 'tavern'
    );
    $this->assertCount(1, $market_to_tavern);

    // Both should be marked as road network
    $this->assertTrue(reset($tavern_to_market)['is_road_network'] ?? FALSE);
    $this->assertTrue(reset($market_to_tavern)['is_road_network'] ?? FALSE);
    $this->assertSame(4, reset($tavern_to_market)['distance'] ?? NULL);
    $this->assertSame(4, reset($market_to_tavern)['distance'] ?? NULL);
    $this->assertTrue(reset($tavern_to_market)['available'] ?? FALSE);
    $this->assertTrue(reset($market_to_tavern)['available'] ?? FALSE);
  }

  /**
   * Tests synthetic road-network entries hard-fail when no road path exists.
   */
  public function testBuildNavigationCapabilitiesWithRoadNetworkMissingPathBlocked(): void {
    $dungeon = [
      'connections' => [
        ['from_room' => 'tavern', 'destination_type' => 'road'],
        ['from_room' => 'market', 'destination_type' => 'road'],
      ],
      'rooms' => [
        ['room_id' => 'tavern', 'name' => 'Tavern'],
        ['room_id' => 'market', 'name' => 'Market'],
      ],
      'room_road_anchors' => [
        ['room_id' => 'tavern', 'road_node_id' => 'road-node-1', 'access_distance' => 1],
        ['room_id' => 'market', 'road_node_id' => 'road-node-9', 'access_distance' => 1],
      ],
      'road_edges' => [
        ['from_node_id' => 'road-node-1', 'to_node_id' => 'road-node-2', 'distance' => 2, 'bidirectional' => TRUE],
      ],
    ];

    $capabilities = $this->service->buildNavigationCapabilitiesWithRoadNetwork($dungeon, 'tavern');
    $market_cap = array_values(array_filter($capabilities, static fn(array $c): bool => ($c['target_room_id'] ?? '') === 'market'));
    $this->assertCount(1, $market_cap);
    $this->assertFalse($market_cap[0]['available'] ?? TRUE);
    $this->assertSame('missing_road_path', $market_cap[0]['blocked_reason'] ?? NULL);
  }

  /**
   * Helper to get private method via reflection for testing.
   */
  private function getPrivateMethod(string $method_name) {
    $reflection = new \ReflectionClass($this->service);
    $method = $reflection->getMethod($method_name);
    $method->setAccessible(TRUE);
    return $method;
  }

}
