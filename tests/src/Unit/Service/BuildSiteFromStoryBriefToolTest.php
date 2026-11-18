<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\DungeonEditorService;
use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorCanonicalGenerationPlanService;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon\BuildSiteFromStoryBriefTool;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalRoomBuildService;
use Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests that a story's site is built in steps from saved state.
 *
 * @group dungeoncrawler_content
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon\BuildSiteFromStoryBriefTool
 */
class BuildSiteFromStoryBriefToolTest extends UnitTestCase {

  /**
   * Missing rooms are built first; nothing is laid out until they exist.
   */
  public function testBuildsTheRoomsTheStoryNamesBeforeLayingOut(): void {
    $rooms = $this->createMock(CanonicalRoomBuildService::class);
    $rooms->method('isPublished')->willReturnCallback(static fn(string $id): bool => $id === 'cave-mouth');
    $rooms->expects($this->once())->method('build')->with(
      'warren',
      $this->callback(static fn(array $arguments): bool => str_contains($arguments['prompt'], 'Narrow tunnels.')
        && str_contains($arguments['prompt'], 'Skrix') && $arguments['min_hexes'] === 16),
      // The last room of the site has no exit.
      0,
      "Built for the storyline 'The Clan's Blight'.",
    );
    $generation = $this->createMock(EditorCanonicalGenerationPlanService::class);
    $generation->expects($this->once())->method('generateDungeonLayout')->with(
      $this->callback(static fn(array $arguments): bool => $arguments['required_room_ids'] === ['cave-mouth', 'warren']
        && $arguments['room_count'] === 2 && $arguments['publication_ready'] === TRUE
        && str_contains($arguments['prompt'], 'cave-mouth')),
    )->willReturn(['command_plan' => ['steps' => [
      ['command_type' => 'place_room', 'payload' => ['room_id' => 'cave-mouth']],
      ['command_type' => 'place_room', 'payload' => ['room_id' => 'warren']],
    ]]]);
    $editor = $this->createMock(DungeonEditorService::class);
    $editor->method('getDraft')->willReturn(['draft_id' => 'd1', 'revision' => 3, 'dungeon' => ['dungeon_id' => 'kobold-caves', 'room_placements' => []]]);
    $applied = [];
    $editor->method('applyCommand')->willReturnCallback(function (string $draft_id, array $command) use (&$applied): array {
      $applied[] = [$command['type'], $command['expected_revision']];
      return ['draft' => ['revision' => $command['expected_revision'] + 1]];
    });
    $editor->method('validateDraft')->willReturn(['is_valid' => TRUE, 'counts' => ['error' => 0, 'warning' => 0, 'info' => 0]]);
    $editor->method('roomLibrary')->willReturn([
      ['room_id' => 'cave-mouth', 'exit_port_count' => 1],
      ['room_id' => 'warren', 'exit_port_count' => 0],
    ]);

    $result = $this->tool($this->brief(['cave-mouth', 'warren']), $rooms, $generation)->execute(['expected_revision' => 3], $this->context($editor));

    $this->assertSame(['warren'], $result['rooms_built_now']);
    $this->assertSame('laid_out', $result['state']);
    $this->assertSame([['place_room', 3], ['place_room', 4]], $applied);
    $this->assertSame(5, $result['revision']);
  }

  /**
   * A site larger than one layout can hold is refused before anything runs.
   */
  public function testRefusesASiteLargerThanOneLayout(): void {
    $rooms = $this->createMock(CanonicalRoomBuildService::class);
    $rooms->expects($this->never())->method('build');
    $editor = $this->createMock(DungeonEditorService::class);
    $editor->method('getDraft')->willReturn(['draft_id' => 'd1', 'revision' => 3, 'dungeon' => ['dungeon_id' => 'kobold-caves', 'room_placements' => []]]);

    $this->expectException(\DomainException::class);
    $this->expectExceptionMessage("puts 31 rooms in 'kobold-caves'; one site can be laid out with at most 30");
    $ids = array_map(static fn(int $number): string => 'room-' . $number, range(1, 31));
    $this->tool($this->brief($ids), $rooms, $this->createMock(EditorCanonicalGenerationPlanService::class))
      ->execute(['expected_revision' => 3], $this->context($editor));
  }

  private function tool(array $brief, CanonicalRoomBuildService $rooms, EditorCanonicalGenerationPlanService $generation): BuildSiteFromStoryBriefTool {
    $storylines = $this->createMock(StorylineAuthoringDraftService::class);
    $storylines->method('storyBriefs')->with('dungeon', 'kobold-caves')->willReturn($brief);
    $uuid = $this->createMock(UuidInterface::class);
    $uuid->method('generate')->willReturn('00000000-0000-4000-8000-000000000000');
    return new BuildSiteFromStoryBriefTool($storylines, $rooms, $generation, $uuid);
  }

  /**
   * Existing rooms that cannot chain into one path are refused by name.
   */
  public function testRefusesRoomsThatCannotFormOnePath(): void {
    $rooms = $this->createMock(CanonicalRoomBuildService::class);
    $rooms->method('isPublished')->willReturn(TRUE);
    $generation = $this->createMock(EditorCanonicalGenerationPlanService::class);
    $generation->expects($this->never())->method('generateDungeonLayout');
    $editor = $this->createMock(DungeonEditorService::class);
    $editor->method('getDraft')->willReturn(['draft_id' => 'd1', 'revision' => 3, 'dungeon' => ['dungeon_id' => 'kobold-caves', 'room_placements' => []]]);
    $editor->method('roomLibrary')->willReturn([
      ['room_id' => 'cave-mouth', 'exit_port_count' => 2],
      ['room_id' => 'warren', 'exit_port_count' => 1],
    ]);

    $this->expectException(\DomainException::class);
    $this->expectExceptionMessageMatches('/^story_site_not_a_path: .*Rooms without an exit: none\. Rooms with more than one exit: cave-mouth \(2 exits\)\./');
    $this->tool($this->brief(['cave-mouth', 'warren']), $rooms, $generation)->execute(['expected_revision' => 3], $this->context($editor));
  }

  private function context(DungeonEditorService $editor): DungeonEditorGmToolContext {
    return new DungeonEditorGmToolContext('d1', 'editing', $editor, $this->createMock(CanonicalDefinitionService::class));
  }

  private function brief(array $room_ids): array {
    return ['kind' => 'dungeon', 'id' => 'kobold-caves', 'storylines' => [[
      'draft_id' => 's1',
      'title' => 'The Clan\'s Blight',
      'status' => 'editing',
      'revision' => 9,
      'premise' => 'A clan must leave its home.',
      'purpose' => 'The kobold cave system.',
      'entrance_room' => 'cave-mouth',
      'starting_cast_at_site' => [],
      'rooms' => array_map(static fn(string $id): array => [
        'room_id' => $id,
        'dungeon_id' => 'kobold-caves',
        'narrative' => 'Narrow tunnels.',
        'scene' => 'The Kobold Caves',
        'scene_summary' => 'The clan bargains or fights.',
        'tasks' => ['Clear or bargain for the caves.'],
        'objectives' => [],
        'starting_cast' => [['name' => 'Skrix', 'role' => 'kobold leader', 'attitude' => 'unfriendly']],
      ], $room_ids),
    ]]];
  }

}
