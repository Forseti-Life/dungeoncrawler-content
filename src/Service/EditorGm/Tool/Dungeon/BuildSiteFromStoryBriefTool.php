<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorCanonicalGenerationPlanService;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalDungeonLayoutPlanService;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalRoomBuildService;
use Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService;

/**
 * Builds the site a storyline specifies, from its story brief.
 *
 * Saved state is the cursor: each call builds the next rooms the brief names
 * that the room library lacks, within a time budget, and once every room
 * exists it lays them out and links them in this dungeon draft. Calling it
 * again continues where the last call stopped. It never publishes.
 */
final class BuildSiteFromStoryBriefTool implements EditorGmToolInterface {

  private const TIME_BUDGET_SECONDS = 60.0;
  private const MAX_ROOMS = CanonicalDungeonLayoutPlanService::DUNGEON_MAX_PLACEMENTS;

  public function __construct(
    private readonly StorylineAuthoringDraftService $storylines,
    private readonly CanonicalRoomBuildService $rooms,
    private readonly EditorCanonicalGenerationPlanService $generation,
    private readonly UuidInterface $uuid,
  ) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'build_site_from_story_brief',
      EditorGmToolDefinition::FAMILY_EXECUTION,
      'Build this dungeon as the storyline that uses it specifies: create each room the story names that does not exist yet, then place and link the rooms in this draft. Works in steps; call it again to continue. Does not publish.',
      TRUE,
      'CanonicalRoomBuildService::build(); DungeonEditorService::applyCommand()',
      [
        EditorGmToolDefinition::argument('expected_revision', 'integer', TRUE, 'Current dungeon draft revision.'),
        EditorGmToolDefinition::argument('storyline_draft_id', 'string', FALSE, 'Which storyline to build for, when more than one names this dungeon.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = DungeonEditorGmToolContext::of($context);
    $draft = $context->draft();
    if (EditorGmToolContext::requireInt($arguments, 'expected_revision') !== (int) $draft['revision']) {
      throw new \DomainException('dungeon_draft_revision_conflict');
    }
    $dungeon_id = (string) $context->dungeon()['dungeon_id'];
    $story = $this->story($dungeon_id, $arguments['storyline_draft_id'] ?? NULL);
    $room_briefs = array_column($story['rooms'], NULL, 'room_id');
    // The site is a path from its entrance: the entrance comes first and the
    // last room in story order is the end of it.
    $entrance = $story['entrance_room'];
    if ($entrance !== '' && isset($room_briefs[$entrance])) {
      $room_briefs = [$entrance => $room_briefs[$entrance]] + $room_briefs;
    }
    $last_room_id = array_key_last($room_briefs);
    if ($room_briefs === []) {
      throw new \DomainException(sprintf("story_site_has_no_rooms: storyline '%s' names no rooms for '%s'.", $story['title'], $dungeon_id));
    }
    if (count($room_briefs) > self::MAX_ROOMS) {
      throw new \DomainException(sprintf(
        "story_site_too_large: storyline '%s' puts %d rooms in '%s'; one site can be laid out with at most %d. Split the site in the storyline.",
        $story['title'],
        count($room_briefs),
        $dungeon_id,
        self::MAX_ROOMS,
      ));
    }

    $started = microtime(TRUE);
    $built = [];
    $waiting = [];
    foreach ($room_briefs as $room_id => $room) {
      if ($this->rooms->isPublished($room_id)) {
        continue;
      }
      if ($built !== [] && (microtime(TRUE) - $started) >= self::TIME_BUDGET_SECONDS) {
        $waiting[] = $room_id;
        continue;
      }
      $this->rooms->build($room_id, [
        'prompt' => mb_substr(implode(' ', array_filter([
          $room['narrative'],
          $room['scene'] !== '' ? 'Scene: ' . $room['scene'] . '. ' . $room['scene_summary'] : '',
          $room['tasks'] !== [] ? 'What happens here: ' . implode(' ', $room['tasks']) : '',
          $room['starting_cast'] !== [] ? 'Who starts here: ' . implode(', ', array_column($room['starting_cast'], 'name')) . '.' : '',
          'Site: ' . $story['purpose'],
        ])), 0, 2000),
        'min_hexes' => 16,
      ], $room_id === $last_room_id ? 0 : 1, sprintf("Built for the storyline '%s'.", $story['title']));
      $built[] = $room_id;
    }
    $progress = [
      'storyline' => $story['title'],
      'dungeon_id' => $dungeon_id,
      'rooms_in_story' => count($room_briefs),
      'rooms_built_now' => $built,
      'rooms_waiting' => $waiting,
    ];
    if ($waiting !== []) {
      return $progress + [
        'state' => 'building_rooms',
        'next_step' => sprintf('%d of %d rooms still to build. Continue building the site.', count($waiting), count($room_briefs)),
      ];
    }
    if ($context->dungeon()['room_placements'] !== []) {
      return $progress + [
        'state' => 'laid_out',
        'next_step' => 'Every room exists and this dungeon already has rooms placed. Review the layout and validate it.',
      ];
    }

    $this->assertRoomsFormOnePath(array_keys($room_briefs), $context->roomLibrary(), $story['title']);
    $plan = $this->generation->generateDungeonLayout([
      'prompt' => mb_substr(sprintf(
        '%s Connect every room so each can be reached from the entrance. The level entrance is the room %s.',
        $story['purpose'],
        $story['entrance_room'],
      ), 0, 2000),
      'room_count' => count($room_briefs),
      'required_room_ids' => array_keys($room_briefs),
      'publication_ready' => TRUE,
    ], $context);
    $revision = (int) $draft['revision'];
    foreach ($plan['command_plan']['steps'] as $step) {
      $result = $context->dungeonEditor->applyCommand($context->draftId, [
        'command_id' => $this->uuid->generate(),
        'expected_revision' => $revision,
        'type' => (string) $step['command_type'],
        'payload' => (array) $step['payload'],
        'issued_at' => gmdate(DATE_RFC3339),
      ]);
      $revision = (int) $result['draft']['revision'];
    }
    $validation = $context->dungeonEditor->validateDraft($context->draftId, 'publication');
    return $progress + [
      'state' => 'laid_out',
      'revision' => $revision,
      'placed_rooms' => count($room_briefs),
      'is_valid' => $validation['is_valid'],
      'counts' => $validation['counts'],
      'next_step' => $validation['is_valid']
        ? 'The site is laid out and passes publication checks. Review it, then publish.'
        : 'The site is laid out. Address the validation findings before publishing.',
    ];
  }

  /**
   * A site is laid out as one path: one end room, every other room passing on.
   *
   * @throws \DomainException
   *   `story_site_not_a_path`, naming each room that does not fit.
   */
  private function assertRoomsFormOnePath(array $room_ids, array $library, string $storyline): void {
    $exits = array_column($library, 'exit_port_count', 'room_id');
    $ends = [];
    $branching = [];
    foreach ($room_ids as $room_id) {
      $count = $exits[$room_id] ?? throw new \LogicException('story_site_room_not_in_library:' . $room_id);
      if ($count === 0) {
        $ends[] = $room_id;
      }
      elseif ($count > 1) {
        $branching[] = sprintf('%s (%d exits)', $room_id, $count);
      }
    }
    if (count($ends) === 1 && $branching === []) {
      return;
    }
    throw new \DomainException(sprintf(
      "story_site_not_a_path: the rooms of '%s' cannot be laid out as one path. A site needs exactly one room without an exit, the end, and every other room with exactly one exit. Rooms without an exit: %s. Rooms with more than one exit: %s. Publish a version of the room that fits in the Room Editor; rooms are shared, so check the other dungeons that place it first.",
      $storyline,
      $ends === [] ? 'none' : implode(', ', $ends),
      $branching === [] ? 'none' : implode(', ', $branching),
    ));
  }

  /**
   * The one storyline this build serves.
   */
  private function story(string $dungeon_id, mixed $storyline_draft_id): array {
    if ($dungeon_id === '') {
      throw new \DomainException('story_brief_requires_a_saved_dungeon');
    }
    $stories = $this->storylines->storyBriefs('dungeon', $dungeon_id)['storylines'];
    if ($storyline_draft_id !== NULL) {
      $stories = array_values(array_filter($stories, static fn(array $story): bool => $story['draft_id'] === $storyline_draft_id));
    }
    if (count($stories) !== 1) {
      throw new \DomainException(sprintf(
        "story_site_storyline_%s: '%s' is named by %d storyline draft(s)%s.",
        $stories === [] ? 'missing' : 'ambiguous',
        $dungeon_id,
        count($stories),
        $stories === [] ? '' : ': ' . implode('; ', array_map(static fn(array $story): string => $story['title'] . ' (' . $story['status'] . ', ' . $story['draft_id'] . ')', $stories)) . '. Name one with storyline_draft_id',
      ));
    }
    return $stories[0];
  }

}
