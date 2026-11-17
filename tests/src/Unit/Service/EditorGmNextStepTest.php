<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\AgentHarness\AgentHarnessManagementService;
use PHPUnit\Framework\TestCase;

/**
 * The Dungeon and Room GMs take their next step from saved state.
 *
 * @group dungeoncrawler_content
 */
class EditorGmNextStepTest extends TestCase {

  private const HALL = '00000001-0000-4000-8000-000000000001';
  private const CRYPT = '00000002-0000-4000-8000-000000000002';

  private function dungeonSnapshot(array $findings): array {
    return [
      'current_state' => ['status' => 'active', 'missing_content' => []],
      'validation_summary' => ['error_count' => count($findings), 'warning_count' => 0, 'findings' => $findings],
      'dungeon' => [
        'placements' => [
          ['placement_id' => self::HALL, 'label' => 'Great Hall', 'room_name' => 'Feast Hall'],
          ['placement_id' => self::CRYPT, 'label' => 'Lower Crypt', 'room_name' => 'Old Crypt'],
        ],
        'port_links' => [],
        'regions' => [],
      ],
    ];
  }

  public function testDungeonDraftCommandFindingIsOfferedAsAnEditInAuthorTerms(): void {
    $step = (new AgentHarnessManagementService())->nextStepFromSavedState($this->dungeonSnapshot([[
      'severity' => 'error',
      'code' => 'placement_overlap',
      'message' => sprintf('Placements %s and %s claim the same level hex, first at (0, 0).', self::HALL, self::CRYPT),
      'route' => 'draft_command',
      'subjects' => [['placement_id' => self::HALL], ['placement_id' => self::CRYPT]],
    ]]), 'Dungeon Editor');

    $this->assertSame([
      'Validation finding: The places "Great Hall" and "Lower Crypt" overlap on the map. Options: ask the GM to fix this finding now, skip to the next finding, or switch focus to warnings.',
    ], $step);
  }

  public function testDungeonRoomEditorFindingNamesTheRoomAndComesAfterDraftEdits(): void {
    $unresolved = [
      'severity' => 'error',
      'code' => 'placement_version_unresolved',
      'message' => sprintf('Placement "Lower Crypt" pins version %s, which is not a published room version.', self::CRYPT),
      'route' => 'room_editor',
      'subjects' => [['placement_id' => self::CRYPT]],
    ];
    $management = new AgentHarnessManagementService();

    $this->assertSame([
      'Validation finding: The place "Lower Crypt" uses a version of the room "Old Crypt" that is not published. This needs work in the Room Editor, not a dungeon edit. Options: open the room "Old Crypt" in the Room Editor, or continue dungeon changes.',
    ], $management->nextStepFromSavedState($this->dungeonSnapshot([$unresolved]), 'Dungeon Editor'));

    $step = $management->nextStepFromSavedState($this->dungeonSnapshot([$unresolved, [
      'severity' => 'error',
      'code' => 'port_already_linked',
      'message' => 'Exit port "east" is used by 2 links.',
      'route' => 'draft_command',
      'subjects' => [['placement_id' => self::HALL], ['port_id' => 'east'], ['link_id' => 'a'], ['link_id' => 'b']],
    ]]), 'Dungeon Editor');
    $this->assertStringStartsWith('Validation finding: The exit "east" on "Great Hall" is used by 2 links;', $step[0]);
  }

  public function testDungeonMissingContentComesBeforeFindings(): void {
    $snapshot = $this->dungeonSnapshot([['severity' => 'error', 'code' => 'placement_overlap', 'message' => 'Overlap.', 'route' => 'draft_command', 'subjects' => []]]);
    $snapshot['current_state']['missing_content'] = ['Exactly one level entrance'];

    $this->assertSame(
      ['Choose which room is the level entrance.'],
      (new AgentHarnessManagementService())->nextStepFromSavedState($snapshot, 'Dungeon Editor'),
    );
  }

  private function roomSnapshot(array $findings): array {
    return [
      'current_state' => ['status' => 'active', 'missing_content' => []],
      'validation_summary' => ['error_count' => count($findings), 'warning_count' => 0, 'findings' => $findings],
      'room' => [
        'placements' => [['definition_ref' => ['family' => 'item', 'definition_id' => 'iron-brazier', 'version' => '1.0.0']]],
        'entry_ports' => [['port_id' => 'entry-1', 'label' => 'West door']],
        'exit_ports' => [],
      ],
    ];
  }

  public function testRoomDraftCommandFindingIsOfferedAsAnEditNamingThePort(): void {
    $step = (new AgentHarnessManagementService())->nextStepFromSavedState($this->roomSnapshot([[
      'severity' => 'error',
      'code' => 'port_edge_not_boundary',
      'message' => 'Port edge must face out of the room: the neighbouring hex across this edge is inside the footprint.',
      'route' => 'draft_command',
      'path' => '/entry_ports/0/edge',
      'context' => [],
    ]]), 'Room Editor');

    $this->assertSame([
      'Validation finding: The entrance "West door": Port edge must face out of the room: the neighbouring hex across this edge is inside the footprint. Options: ask the GM to fix this finding now, skip to the next finding, or switch focus to warnings.',
    ], $step);
  }

  public function testRoomDefinitionFindingIsSentToTheDefinitionEditor(): void {
    $step = (new AgentHarnessManagementService())->nextStepFromSavedState($this->roomSnapshot([[
      'severity' => 'error',
      'code' => 'definition_not_found',
      'message' => 'Pinned placement definition is not available from canonical authority.',
      'route' => 'definition_editor',
      'path' => '/placements/0/definition_ref',
      'context' => [],
    ]]), 'Room Editor');

    $this->assertSame([
      'Validation finding: The object "iron brazier": Pinned placement definition is not available from canonical authority. This needs the object\'s definition fixed, not a room edit. Options: fix the definition in the Definition Editor, or continue room changes.',
    ], $step);
  }

  public function testAnUnknownSurfaceIsRejected(): void {
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('next_step_surface_unsupported:Definition Editor');
    (new AgentHarnessManagementService())->nextStepFromSavedState([], 'Definition Editor');
  }

}
