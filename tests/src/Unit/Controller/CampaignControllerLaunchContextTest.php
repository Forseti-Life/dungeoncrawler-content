<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\dungeoncrawler_content\Controller\CampaignController;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;
use Drupal\dungeoncrawler_content\Service\QuestTrackerService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests campaign launch context behavior for character-specific resume.
 *
 * @group dungeoncrawler_content
 * @group controller
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Controller\CampaignController
 */
class CampaignControllerLaunchContextTest extends UnitTestCase {

  /**
   * Tests resume launch queries omit forced room and hex coordinates.
   *
   * @covers ::buildHexmapLaunchQuery
   */
  public function testBuildHexmapLaunchQueryForResumeOmitsForcedRoomAndHex(): void {
    $controller = $this->buildController();
    $method = new \ReflectionMethod(CampaignController::class, 'buildHexmapLaunchQuery');
    $method->setAccessible(TRUE);

    $decoded = [
      'level_id' => 'level-42',
      'hex_map' => [
        'map_id' => 'map-42',
      ],
      'rooms' => [
        ['room_id' => 'room-entry'],
        ['room_id' => 'room-next'],
      ],
    ];

    $query = $method->invoke($controller, 9, 77, $decoded, 'map-42', TRUE);

    $this->assertSame(9, $query['campaign_id']);
    $this->assertSame(77, $query['character_id']);
    $this->assertSame('level-42', $query['dungeon_level_id']);
    $this->assertSame('map-42', $query['map_id']);
    $this->assertArrayNotHasKey('room_id', $query);
    $this->assertArrayNotHasKey('next_room_id', $query);
    $this->assertArrayNotHasKey('start_q', $query);
    $this->assertArrayNotHasKey('start_r', $query);
  }

  /**
   * Tests non-resume launch queries still seed explicit entry-room defaults.
   *
   * @covers ::buildHexmapLaunchQuery
   */
  public function testBuildHexmapLaunchQueryForExplicitEntryIncludesRoomAndHex(): void {
    $controller = $this->buildController();
    $method = new \ReflectionMethod(CampaignController::class, 'buildHexmapLaunchQuery');
    $method->setAccessible(TRUE);

    $decoded = [
      'level_id' => 'level-7',
      'hex_map' => [
        'map_id' => 'map-7',
      ],
      'rooms' => [
        ['room_id' => 'room-entry'],
        ['room_id' => 'room-next'],
      ],
    ];

    $query = $method->invoke($controller, 5, 12, $decoded, 'map-7', FALSE);

    $this->assertSame('room-entry', $query['room_id']);
    $this->assertSame('room-next', $query['next_room_id']);
    $this->assertSame(0, $query['start_q']);
    $this->assertSame(0, $query['start_r']);
  }

  /**
   * Tests persisted location fields are preserved for attached characters.
   *
   * @covers ::resolveCharacterLocationFields
   */
  public function testResolveCharacterLocationFieldsPreservesExistingState(): void {
    $controller = $this->buildController();
    $method = new \ReflectionMethod(CampaignController::class, 'resolveCharacterLocationFields');
    $method->setAccessible(TRUE);

    $location = $method->invoke($controller, [
      'position_q' => 4,
      'position_r' => 2,
      'last_room_id' => 'room-current',
      'location_type' => 'room',
      'location_ref' => 'room-current',
    ]);

    $this->assertSame(4, $location['position_q']);
    $this->assertSame(2, $location['position_r']);
    $this->assertSame('room-current', $location['last_room_id']);
    $this->assertSame('room', $location['location_type']);
    $this->assertSame('room-current', $location['location_ref']);
  }

  /**
   * Tests missing location state falls back to global defaults.
   *
   * @covers ::resolveCharacterLocationFields
   */
  public function testResolveCharacterLocationFieldsDefaultsWhenMissing(): void {
    $controller = $this->buildController();
    $method = new \ReflectionMethod(CampaignController::class, 'resolveCharacterLocationFields');
    $method->setAccessible(TRUE);

    $location = $method->invoke($controller, NULL);

    $this->assertSame(0, $location['position_q']);
    $this->assertSame(0, $location['position_r']);
    $this->assertSame('', $location['last_room_id']);
    $this->assertSame('global', $location['location_type']);
    $this->assertSame('', $location['location_ref']);
  }

  /**
   * Builds a controller with mocked dependencies.
   */
  private function buildController(): CampaignController {
    return new CampaignController(
      $this->createMock(Connection::class),
      $this->createMock(CharacterManager::class),
      $this->createMock(FormBuilderInterface::class),
      $this->createMock(QuestTrackerService::class),
      $this->createMock(GeneratedImageRepository::class),
      $this->createMock(TimeInterface::class),
    );
  }

}
