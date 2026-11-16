<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\StorylineManagerService;
use Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests evidence-backed room/site resolution without creative guesses.
 *
 * @group dungeoncrawler_content
 */
class StorylineRoomSiteBindingsTest extends UnitTestCase {

  /**
   * Importing canonical scene references must not create avoidable site gaps.
   */
  public function testNewDraftHydrationKeepsResolvableSiteBindings(): void {
    $drafts = new StorylineAuthoringDraftService(
      $this->createMock(Connection::class),
      $this->manager(),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(UuidInterface::class),
      $this->createMock(TimeInterface::class),
    );
    $template = [
      'asset_references' => [
        ['asset_type' => 'dungeon', 'asset_id' => 'watch-site'],
        ['asset_type' => 'dungeon', 'asset_id' => 'lodge-site'],
        ['asset_type' => 'room', 'asset_id' => 'watch-entry', 'chapter_id' => 'homecoming'],
        ['asset_type' => 'room', 'asset_id' => 'lodge-parlor', 'link_data' => ['dungeon_id' => 'lodge-site']],
        ['asset_type' => 'room', 'asset_id' => 'lodge-parlor', 'chapter_id' => 'initial-briefing'],
      ],
    ];
    $method = new \ReflectionMethod($drafts, 'hydrateDraftRowsFromCanonicalTemplate');
    $hydrated = $method->invoke($drafts, [], $template);
    $this->assertSame(
      ['watch-site', 'lodge-site', 'lodge-site'],
      array_column($hydrated['objects']['rooms']['generation_specs'], 'dungeon_id'),
    );
    $this->assertSame('homecoming', $hydrated['objects']['rooms']['generation_specs'][0]['chapter_id']);
  }

  /**
   * Reused scene rows inherit verified site links without changing their role.
   */
  public function testResolvesReusedRoomsWithoutUsingTheirNarrativeChapter(): void {
    $rooms = [
      ['room_id' => 'watch-entry', 'dungeon_id' => '', 'chapter_id' => 'return-to-lodge', 'narrative' => 'return-route'],
      ['room_id' => 'lodge-parlor', 'dungeon_id' => 'lodge-site', 'chapter_id' => 'homecoming'],
      ['room_id' => 'lodge-parlor', 'dungeon_id' => '', 'chapter_id' => 'initial-briefing'],
    ];
    $manager = $this->manager();
    $result = $manager->resolveAuthoredRoomDungeonBindings($rooms, $this->sites());
    $expected = $rooms;
    $expected[0]['dungeon_id'] = 'watch-site';
    $expected[2]['dungeon_id'] = 'lodge-site';
    $this->assertSame($expected, $result['rooms']);
    $this->assertSame([], $result['unresolved']);
    $this->assertSame(['unique_canonical_placement', 'authored_binding_and_canonical_placement'], array_column($result['resolved'], 'evidence'));
    $this->assertSame('', $rooms[0]['dungeon_id']);
    $repeated = $manager->resolveAuthoredRoomDungeonBindings($result['rooms'], $this->sites());
    $this->assertSame($expected, $repeated['rooms']);
    $this->assertSame([], $repeated['resolved']);
  }

  /**
   * Multiple sites and absent placements remain explicit unresolved decisions.
   */
  public function testDoesNotGuessAmbiguousOrUnplacedSites(): void {
    $rooms = [
      ['room_id' => 'shared-room', 'dungeon_id' => '', 'chapter_id' => 'watch-site'],
      ['room_id' => 'unplaced-room', 'dungeon_id' => ''],
    ];
    $result = $this->manager()->resolveAuthoredRoomDungeonBindings($rooms, $this->sites());
    $this->assertSame($rooms, $result['rooms']);
    $this->assertSame([], $result['resolved']);
    $this->assertSame(['multiple_canonical_sites', 'no_canonical_placement'], array_column($result['unresolved'], 'reason'));
    $this->assertSame(['watch-site', 'lodge-site'], $result['unresolved'][0]['candidate_dungeon_ids']);
  }

  /**
   * An invalid authored link must not be copied or silently overridden.
   */
  public function testPreservesInvalidExplicitLinksForValidation(): void {
    $rooms = [
      ['room_id' => 'watch-entry', 'dungeon_id' => 'lodge-site'],
      ['room_id' => 'watch-entry', 'dungeon_id' => ''],
    ];
    $result = $this->manager()->resolveAuthoredRoomDungeonBindings($rooms, $this->sites());
    $this->assertSame($rooms, $result['rooms']);
    $this->assertSame([], $result['resolved']);
    $this->assertSame('authored_site_not_unique_or_not_placed', $result['unresolved'][0]['reason']);
  }

  /**
   * Conflicting authored sites need a named-place decision, not a random pick.
   */
  public function testDoesNotChooseBetweenConflictingAuthoredLinks(): void {
    $rooms = [
      ['room_id' => 'shared-room', 'dungeon_id' => 'watch-site'],
      ['room_id' => 'shared-room', 'dungeon_id' => 'lodge-site'],
      ['room_id' => 'shared-room', 'dungeon_id' => ''],
    ];
    $result = $this->manager()->resolveAuthoredRoomDungeonBindings($rooms, $this->sites());
    $this->assertSame($rooms, $result['rooms']);
    $this->assertSame([], $result['resolved']);
    $this->assertCount(1, $result['unresolved']);
  }

  /**
   * Original fixture sites using both supported canonical placement shapes.
   */
  private function sites(): array {
    return [['dungeon_id' => 'watch-site'], ['dungeon_id' => 'lodge-site'], ['dungeon_id' => 'not-published']];
  }

  /**
   * Supplies original canonical contracts without importing published content.
   */
  private function manager(): StorylineManagerService {
    return new class() extends StorylineManagerService {

      public function __construct() {}

      /**
       * Supplies the original site's published room contract.
       */
      protected function loadCanonicalDungeonEntity(string $dungeon_id): ?array {
        $data = match ($dungeon_id) {
          'watch-site' => [
            'room_placements' => [
            ['room_id' => 'watch-entry', 'is_level_entrance' => TRUE],
            ['room_id' => 'shared-room'],
            ['room_id' => 'lodge-parlor'],
            ],
          ],
          'lodge-site' => ['rooms' => ['lodge-parlor', 'shared-room'], 'entry_room' => 'lodge-parlor'],
          default => NULL,
        };
        return $data === NULL ? NULL : [
          'dungeon_id' => $dungeon_id,
          'dungeon_data' => json_encode($data, JSON_THROW_ON_ERROR),
        ];
      }

    };
  }

}
