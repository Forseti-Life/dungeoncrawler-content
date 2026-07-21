<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Controller\HexMapController;
use Drupal\dungeoncrawler_content\Service\CampaignCharacterRuntimeResolverService;
use Drupal\dungeoncrawler_content\Service\CampaignCharacterRuntimeSyncService;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\CharacterStateService;
use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;
use Drupal\dungeoncrawler_content\Service\MapVisualStateProjector;
use Drupal\dungeoncrawler_content\Service\NavigationRuntimeService;
use Drupal\dungeoncrawler_content\Service\NavigationService;
use Drupal\dungeoncrawler_content\Service\QuestGeneratorService;
use Drupal\dungeoncrawler_content\Service\QuestTrackerService;
use Drupal\dungeoncrawler_content\Service\RelationshipManagerService;
use Drupal\dungeoncrawler_content\Service\StateValidationService;
use Drupal\dungeoncrawler_content\Service\StorylineManagerService;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Unit tests for HexMapController visual-state payload projection.
 *
 * @group dungeoncrawler_content
 * @group hexmap
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Controller\HexMapController
 */
class HexMapControllerVisualStatePayloadTest extends UnitTestCase {

  protected function buildController(): HexMapController {
    return new HexMapController(
      $this->createMock(RequestStack::class),
      $this->createMock(Connection::class),
      $this->createMock(CampaignCharacterRuntimeResolverService::class),
      $this->createMock(CampaignCharacterRuntimeSyncService::class),
      $this->createMock(QuestTrackerService::class),
      $this->createMock(QuestGeneratorService::class),
      $this->createMock(GeneratedImageRepository::class),
      $this->createMock(MapVisualStateProjector::class),
      $this->createMock(NavigationRuntimeService::class),
      $this->createMock(NavigationService::class),
      $this->createMock(StorylineManagerService::class),
      $this->createMock(RelationshipManagerService::class),
      $this->createMock(StateValidationService::class),
      $this->createMock(CharacterManager::class),
      $this->createMock(CharacterStateService::class),
    );
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
    $this->assertSame($hexmap_state['dungeon_payload'], $payload['dungeon_payload']);
    $this->assertSame($hexmap_state['map_visual_state'], $payload['map_visual_state']);
    $this->assertSame($hexmap_state['launch_character'], $payload['launch_character']);
    $this->assertSame($hexmap_state['quest_summary'], $payload['quest_summary']);
    $this->assertSame($hexmap_state['storyline_contacts'], $payload['storyline_contacts']);
    $this->assertSame('Campaign 21', $payload['campaign_title']);
  }

}
