<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\EditorGm\StorylineValidationTroubleshootingGuide;
use Drupal\dungeoncrawler_content\Service\AgentHarness\AgentHarnessManagementService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the one-finding packet built from native validation.
 *
 * @group dungeoncrawler_content
 */
class StorylineValidationTroubleshootingGuideTest extends UnitTestCase {

  public function testScopesTargetLabelFindingToOneObjective(): void {
    $warning = '[DCV_OBJ_PLAYABILITY_MISSING_TARGET_LABEL] quest.follow_the_whispers.phase[0].objective[0]: target_label is required for meet/travel guidance objectives with canonical target refs.';
    $finding = [
      'severity' => 'warning',
      'code' => 'DCV_OBJ_PLAYABILITY_MISSING_TARGET_LABEL',
      'message' => $warning,
      'entity_id' => 'follow_the_whispers',
      'entity_type' => 'storyline_quest_objective',
      'phase_index' => 0,
      'objective_index' => 0,
      'field' => 'target_label',
    ];
    $packet = StorylineValidationTroubleshootingGuide::packet([
      'process_flow' => [
        'tasks' => [[
          'task_id' => 'follow_the_whispers',
          'chapter_id' => 'intro',
          'scene_id' => 'tavern',
          'goal_text' => 'Find a whisper lead.',
        ]],
        'objective_completion_criteria' => [[
          'objective_id' => 'gather-whisper-network',
          'task_id' => 'follow_the_whispers',
          'type' => 'interact',
          'target' => 'gribbles_rindsworth',
          'description' => 'Question Gribbles.',
        ], [
          'objective_id' => 'unrelated-objective',
          'task_id' => 'another-task',
        ]],
      ],
      'objects' => ['actors' => ['generation_specs' => [[
        'actor_id' => 'gribbles_rindsworth',
        'display_name' => 'Gribbles Rindsworth',
        'role' => 'fence',
      ]]]],
    ], [
      'stages' => [[
        'stage' => 'objective_playability',
        'route' => 'draft_operation',
        'warnings' => [$warning, 'Unrelated warning.'],
        'findings' => [$finding],
      ]],
    ], ['first_open_warning' => $warning]);

    $this->assertSame($warning, $packet['finding']['message']);
    $this->assertSame('gather-whisper-network', $packet['object']['id']);
    $this->assertSame(['field' => 'objective_id', 'value' => 'gather-whisper-network'], $packet['editable']['match']);
    $this->assertSame('Gribbles Rindsworth', $packet['suggested_values']['target_label']);
    $this->assertArrayNotHasKey('unrelated-objective', $packet);
    $this->assertNull($packet['user_question']);
    $this->assertSame('StorylineAuthoringDraftService', $packet['owner']);
    $this->assertFalse($packet['code_change_required']);
  }

  public function testUnregisteredFindingBecomesACodingHandoff(): void {
    $warning = '[DCV_NEW_RULE] object.example: the stored field cannot be represented.';
    $packet = StorylineValidationTroubleshootingGuide::packet([], [
      'stages' => [[
        'stage' => 'schema',
        'route' => 'draft_operation',
        'warnings' => [$warning],
        'findings' => [],
      ]],
    ], ['first_open_warning' => $warning]);

    $this->assertTrue($packet['code_change_required']);
    $this->assertSame('submit_backlog_suggestion', $packet['coding_handoff']['defect_tool']);
    $this->assertStringContainsString('Do not alter storyline data', $packet['coding_handoff']['scope']);
  }

  public function testRuntimeMaterializationUsesActorProjectionWithoutPatchingDerivedFields(): void {
    $warning = "quest.whispers: DCV_ACTOR_RUNTIME_MATERIALIZATION_REQUIRED_FLAG contact 'gribbles' must set required_before_activation=true.";
    $packet = StorylineValidationTroubleshootingGuide::packet([
      'objects' => ['actors' => ['generation_specs' => [[
        'actor_id' => 'gribbles',
        'contact_id' => 'gribbles',
        'display_name' => 'Gribbles',
        'location_id' => 'tavern',
      ]]]],
    ], [
      'stages' => [[
        'stage' => 'entity_linkage',
        'route' => 'draft_operation',
        'warnings' => [$warning],
        'findings' => [[
          'severity' => 'warning',
          'code' => 'DCV_ACTOR_RUNTIME_MATERIALIZATION_REQUIRED_FLAG',
          'message' => $warning,
          'entity_type' => 'storyline_actor_materialization',
          'entity_id' => 'gribbles',
          'contact_id' => 'gribbles',
        ]],
      ]],
    ], ['first_open_warning' => $warning]);
    $this->assertTrue($packet['system_resolves']);
    $this->assertSame([], $packet['editable']);
    $this->assertSame('npc_template', $packet['derived_contract']['entity_type']);
    $this->assertSame('gribbles', $packet['derived_contract']['canonical_actor_id']);
    $this->assertFalse($packet['code_change_required']);
  }

  public function testActorHomeWarningUsesDraftAffiliationResolver(): void {
    $warning = '3 of 6 cast members lack a home: a kin group or a place: Celia.';
    $packet = StorylineValidationTroubleshootingGuide::packet([
      'objects' => [
        'actors' => ['generation_specs' => [[
          'actor_id' => 'celia',
          'display_name' => 'Celia',
          'location_id' => 'absalom',
        ]]],
        'institutions' => [[
          'institution_id' => 'absalom',
          'domain' => 'place',
          'name' => 'Absalom',
        ]],
      ],
    ], [
      'stages' => [[
        'stage' => 'actor_character_sheets',
        'route' => 'draft_operation',
        'warnings' => [$warning],
        'findings' => [[
          'severity' => 'warning',
          'code' => 'actor_home_missing',
          'message' => $warning,
          'entity_type' => 'storyline_actor_home',
          'entity_ids' => ['celia'],
          'actors' => [['actor_id' => 'celia', 'display_name' => 'Celia']],
        ]],
      ]],
    ], ['first_open_warning' => $warning]);
    $this->assertSame('storyline_actor_home', $packet['resolver']);
    $this->assertSame(['affiliations'], $packet['editable']['fields']);
    $this->assertFalse($packet['code_change_required']);
    $this->assertSame('Absalom', $packet['evidence']['available_institutions'][0]['name']);
  }

  public function testBlockingObjectiveErrorPrecedesWarningsAndProvidesAReplacement(): void {
    $error = "Objective 'collect_resonance_samples' has type 'interact' but sets 'item', which that type does not use. Use 'target'.";
    $warning = '[DCV_OBJ_PLAYABILITY_MISSING_TARGET_LABEL] quest.other.phase[0].objective[0]: missing label.';
    $packet = StorylineValidationTroubleshootingGuide::packet([
      'process_flow' => [
        'tasks' => [['task_id' => 'stabilize_arcane_resonance']],
        'objective_completion_criteria' => [[
          'objective_id' => 'collect_resonance_samples',
          'task_id' => 'stabilize_arcane_resonance',
          'type' => 'interact',
          'target' => 'scholar_npc',
          'item' => 'resonance_sample',
          'required' => '',
        ]],
      ],
    ], [
      'stages' => [[
        'stage' => 'objective_control_chain',
        'route' => 'draft_operation',
        'errors' => [$error],
        'warnings' => [],
        'findings' => [[
          'severity' => 'error',
          'code' => 'DCV_OBJECTIVE_TYPE_FIELD_CONFLICT',
          'message' => $error,
          'entity_type' => 'storyline_quest_objective',
          'entity_id' => 'stabilize_arcane_resonance',
          'objective_id' => 'collect_resonance_samples',
          'phase_index' => 0,
          'objective_index' => 0,
          'field' => 'item',
        ]],
      ], [
        'stage' => 'objective_playability',
        'route' => 'draft_operation',
        'errors' => [],
        'warnings' => [$warning],
        'findings' => [],
      ]],
    ], ['first_open_warning' => $warning]);

    $this->assertSame($error, $packet['finding']['message']);
    $this->assertSame('interact', $packet['replacement_value']['type']);
    $this->assertArrayNotHasKey('item', $packet['replacement_value']);
    $this->assertFalse($packet['code_change_required']);
  }

  public function testMissingCanonicalRoomRoutesToRoomEditor(): void {
    $warning = "[entity_type_contracts:room] asset_references[8] (cellar): referenced room is missing from canonical room templates.";
    $packet = StorylineValidationTroubleshootingGuide::packet([], [
      'stages' => [[
        'stage' => 'entity_type_contracts',
        'route' => 'canonical_definition',
        'warnings' => [$warning],
        'findings' => [[
          'severity' => 'warning',
          'code' => 'DCV_CANONICAL_ROOM_MISSING',
          'message' => $warning,
          'entity_type' => 'room',
          'entity_id' => 'cellar',
        ]],
      ]],
    ], ['first_open_warning' => $warning]);
    $this->assertSame('room_editor', $packet['route']);
    $this->assertSame(['kind' => 'room', 'id' => 'cellar'], $packet['editor_route']['arguments']);
    $this->assertFalse($packet['code_change_required']);
  }

  public function testEditorRouteResponseKeepsRoomCreationAndDungeonPlacementSeparate(): void {
    $harness = new AgentHarnessManagementService();
    $room = $harness->synthesizeDeterministicMutationResponse('route_to_editor', [
      'kind' => 'room',
      'id' => 'cellar',
      'route' => '/dungeoncrawler/room-editor/cellar',
    ], [], 'Storyline Builder');
    $this->assertStringContainsString('Create and save the canonical room', $room['answer']);
    $this->assertStringNotContainsString('Dungeon', $room['answer']);

    $dungeon = $harness->synthesizeDeterministicMutationResponse('route_to_editor', [
      'kind' => 'dungeon',
      'id' => 'city',
      'route' => '/dungeoncrawler/dungeon-editor/city',
    ], ['troubleshooting' => ['finding' => ['room_id' => 'cellar']]], 'Storyline Builder');
    $this->assertStringContainsString("Place room 'cellar' in dungeon 'city'", $dungeon['answer']);
    $this->assertStringNotContainsString('Create and save', $dungeon['answer']);
  }

}
