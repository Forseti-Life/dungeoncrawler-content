<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Controller\HexMapController;
use Drupal\dungeoncrawler_content\Service\CampaignCharacterRuntimeResolverService;
use Drupal\dungeoncrawler_content\Service\CampaignCharacterRuntimeSyncService;
use Drupal\dungeoncrawler_content\Service\CampaignAuthorizationService;
use Drupal\dungeoncrawler_content\Service\ActorStateService;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\DungeonSnapshotRefresherService;
use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;
use Drupal\dungeoncrawler_content\Service\GraphVersionService;
use Drupal\dungeoncrawler_content\Service\H3ProjectionQueueService;
use Drupal\dungeoncrawler_content\Service\MapVisualStateProjector;
use Drupal\dungeoncrawler_content\Service\NavigationRuntimeService;
use Drupal\dungeoncrawler_content\Service\NavigationService;
use Drupal\dungeoncrawler_content\Service\QuestGeneratorService;
use Drupal\dungeoncrawler_content\Service\QuestTrackerService;
use Drupal\dungeoncrawler_content\Service\RelationshipManagerService;
use Drupal\dungeoncrawler_content\Service\RuntimeGraphAssemblerService;
use Drupal\dungeoncrawler_content\Service\StateValidationService;
use Drupal\dungeoncrawler_content\Service\StorylineManagerService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Unit tests for HexMapController visual-state payload projection.
 *
 * @group dungeoncrawler_content
 * @group hexmap
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Controller\HexMapController
 */
class HexMapControllerVisualStatePayloadTest extends UnitTestCase {

  public function testCanonicalSourceSceneSurvivesRuntimeNormalization(): void {
    $controller = $this->getMockBuilder(HexMapController::class)
      ->disableOriginalConstructor()
      ->onlyMethods([
        'ensureRoomsHaveAtLeastOneExit',
        'ensureConnectionsHaveLinkedHexes',
        'loadAuthoritativeSparseH3Payload',
        'reconcilePlacementSurfaceWithAuthoritativeH3',
        'getLogger',
      ])
      ->getMock();
    $controller->method('ensureRoomsHaveAtLeastOneExit')->willReturn([]);
    $controller->method('ensureConnectionsHaveLinkedHexes')->willReturn([]);
    $controller->method('loadAuthoritativeSparseH3Payload')->willReturn(['anchors' => [], 'cells' => []]);
    $controller->method('reconcilePlacementSurfaceWithAuthoritativeH3')->willReturn([]);
    $controller->method('getLogger')->willReturn($this->createMock(LoggerInterface::class));

    $source_scene = [
      'conversion_state' => 'original_gameplay_generated_map_staged',
      'width_px' => 5000, 'height_px' => 5000,
      'map_assets' => [
        'background' => ['url' => '/map.png', 'license_notice_url' => '/map.LICENSE.txt', 'width' => 500, 'height' => 500],
        'tiles' => [],
      ],
    ];
    $method = new \ReflectionMethod($controller, 'normalizeDungeonPayload');
    $normalized = $method->invoke($controller, [
      'rooms' => [[
        'room_id' => 'room-a',
        'hexes' => [['q' => 0, 'r' => 0]],
        'metadata' => [
          'module_conversion' => ['source_scene' => $source_scene],
          'unrelated_private_data' => 'not for projection',
        ],
      ]],
      'hex_map' => ['map_id' => 'test-map'],
    ], ['campaign_id' => 0, 'room_id' => 'room-a']);
    $this->assertSame(['module_conversion' => ['source_scene' => $source_scene]], $normalized['rooms']['room-a']['metadata']);
    $projected = (new MapVisualStateProjector())->project($normalized);
    $bootstrap = (new \ReflectionMethod($controller, 'buildClientBootstrapMapVisualState'))->invoke($controller, $projected);
    $this->assertSame('/map.png', $bootstrap['topology']['rooms']['room-a']['source_map']['background_url']);
    $this->assertArrayNotHasKey('unrelated_private_data', $bootstrap['topology']['rooms']['room-a']);
  }

  public function testBootstrapPreservesActiveRoomSourceMapOnly(): void {
    $controller = (new \ReflectionClass(HexMapController::class))->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod($controller, 'buildClientBootstrapMapVisualState');
    $result = $method->invoke($controller, [
      'map_meta' => ['active_room_id' => 'room-a'],
      'topology' => [
        'rooms' => [
          'room-a' => ['room_id' => 'room-a', 'source_map' => ['background_url' => '/map-a.webp']],
          'room-b' => ['room_id' => 'room-b', 'source_map' => ['background_url' => '/map-b.webp']],
        ],
        'connections' => [['from_room_id' => 'room-a', 'to_room_id' => 'room-b']],
      ],
    ]);
    $this->assertSame('/map-a.webp', $result['topology']['rooms']['room-a']['source_map']['background_url']);
    $this->assertArrayNotHasKey('source_map', $result['topology']['rooms']['room-b']);
  }

  protected function buildController(): HexMapController {
    $controller = $this->getMockBuilder(HexMapController::class)
      ->setConstructorArgs([
        $this->createMock(RequestStack::class),
        $this->createMock(Connection::class),
        $this->createMock(CampaignAuthorizationService::class),
        $this->createMock(CampaignCharacterRuntimeResolverService::class),
        $this->createMock(CampaignCharacterRuntimeSyncService::class),
        $this->createMock(QuestTrackerService::class),
        $this->createMock(QuestGeneratorService::class),
        $this->createMock(GeneratedImageRepository::class),
        $this->createMock(MapVisualStateProjector::class),
        $this->createMock(DungeonSnapshotRefresherService::class),
        $this->createMock(GraphVersionService::class),
        $this->createMock(NavigationRuntimeService::class),
        $this->createMock(NavigationService::class),
        $this->createMock(StorylineManagerService::class),
        $this->createMock(RelationshipManagerService::class),
        $this->createMock(RuntimeGraphAssemblerService::class),
        $this->createMock(StateValidationService::class),
        $this->createMock(CharacterManager::class),
        $this->createMock(ActorStateService::class),
        $this->createMock(H3ProjectionQueueService::class),
      ])
      ->onlyMethods(['buildCampaignAccessBootstrap'])
      ->getMock();
    $controller->method('buildCampaignAccessBootstrap')->willReturn([]);
    return $controller;
  }

  /**
   * @covers ::buildVisualStatePayload
   */
  public function testBuildVisualStatePayloadMapsCanonicalFields(): void {
    $controller = $this->buildController();
    $method = new \ReflectionMethod($controller, 'buildVisualStatePayload');
    $method->setAccessible(TRUE);

    $launch_context = [
      'campaign_id' => 21,
      'character_id' => 9,
      'room_id' => 'tavern_entrance',
    ];
    $hexmap_state = [
      'dungeon_payload' => ['rooms' => [['room_id' => 'tavern_entrance']]],
      'map_visual_state' => ['map_meta' => ['active_room_id' => 'tavern_entrance']],
      'launch_character' => ['name' => 'Alyssa'],
      'quest_summary' => [['id' => 'q-1']],
      'storyline_contacts' => [['id' => 'eldric']],
      'campaign_title' => 'Campaign 21',
    ];

    $payload = $method->invoke($controller, $launch_context, $hexmap_state);

    $this->assertTrue($payload['success']);
    $this->assertSame($launch_context, $payload['launch_context']);
    $this->assertSame('1.0.0', $payload['dungeon_payload']['schema_version']);
    $this->assertSame([], (array) $payload['dungeon_payload']['rooms']);
    $this->assertSame('tavern_entrance', $payload['map_visual_state']['map_meta']['active_room_id']);
    $this->assertSame($hexmap_state['launch_character'], $payload['launch_character']);
    $this->assertSame($hexmap_state['quest_summary'], $payload['quest_summary']);
    $this->assertSame($hexmap_state['storyline_contacts'], $payload['storyline_contacts']);
    $this->assertSame('Campaign 21', $payload['campaign_title']);
  }

}
