<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\EditorGm\StorylineValidationTroubleshootingGuide;
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

}
