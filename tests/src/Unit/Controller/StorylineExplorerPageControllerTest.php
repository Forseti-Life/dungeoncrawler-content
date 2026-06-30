<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\dungeoncrawler_content\Controller\StorylineExplorerPageController;
use Drupal\dungeoncrawler_content\Service\StorylineManagerService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Controller\StorylineExplorerPageController
 */
class StorylineExplorerPageControllerTest extends UnitTestCase {

  /**
   * @covers ::collectTaskContractDiagnostics
   */
  public function testCollectTaskContractDiagnosticsFlagsCompositeWithoutChildrenAndDuplicateTaskIds(): void {
    $storyline_manager = $this->createMock(StorylineManagerService::class);
    $storyline_manager->expects($this->once())
      ->method('getCanonicalQuestTemplateObjectivePhases')
      ->with('quest-alpha')
      ->willReturn([
        [
          'phase' => 1,
          'objectives' => [
            [
              'objective_id' => 'boss-parent-empty',
              'type' => 'composite',
              'description' => 'Composite without children.',
              'completion_criteria' => [
                'kind' => 'all_children',
                'metric' => 'children_completed',
                'required_value' => TRUE,
                'description' => 'Complete all children.',
              ],
            ],
            [
              'objective_id' => 'boss-parent-duplicate',
              'type' => 'composite',
              'description' => 'Composite with duplicate task ids.',
              'completion_criteria' => [
                'kind' => 'all_children',
                'metric' => 'children_completed',
                'required_value' => TRUE,
                'description' => 'Complete all children.',
              ],
              'children' => [
                [
                  'objective_id' => 'task-dup',
                  'type' => 'investigate',
                  'description' => 'Investigate the chamber.',
                  'next_step' => 'Search the room.',
                  'completion_criteria' => [
                    'kind' => 'count',
                    'metric' => 'current',
                    'target_count' => 1,
                    'description' => 'Investigate once.',
                  ],
                ],
                [
                  'objective_id' => 'task-dup',
                  'type' => 'kill',
                  'description' => 'Defeat the guard.',
                  'next_step' => 'Engage the guard.',
                  'completion_criteria' => [
                    'kind' => 'count',
                    'metric' => 'current',
                    'target_count' => 1,
                    'description' => 'Defeat the guard.',
                  ],
                ],
              ],
            ],
          ],
        ],
      ]);

    $controller = new class($storyline_manager) extends StorylineExplorerPageController {
      public function exposeCollectTaskContractDiagnostics(array $template_data): array {
        return $this->collectTaskContractDiagnostics($template_data);
      }
    };

    $errors = $controller->exposeCollectTaskContractDiagnostics([
      'chapters' => [
        [
          'scenes' => [
            [
              'quest_ids' => ['quest-alpha'],
            ],
          ],
        ],
      ],
    ]);

    $error_text = implode('; ', $errors);
    $this->assertStringContainsString('composite objectives must include children tasks', $error_text);
    $this->assertStringContainsString("duplicate objective_id 'task-dup' in quest 'quest-alpha'", $error_text);
  }

  /**
   * @covers ::collectEntityLinkageDiagnostics
   */
  public function testCollectEntityLinkageDiagnosticsValidatesTargetIdAndCanonicalIndexErrors(): void {
    $storyline_manager = $this->createMock(StorylineManagerService::class);
    $storyline_manager->expects($this->once())
      ->method('getCanonicalLocationTemplateIndex')
      ->willReturn([
        'dungeon_ids' => [],
        'room_ids' => ['canonical-room-1' => TRUE],
        'dungeon_room_ids' => [],
        'errors' => ['Canonical location index unavailable in test fixture.'],
      ]);
    $storyline_manager->expects($this->once())
      ->method('getCanonicalQuestTemplateObjectivePhases')
      ->with('quest-beta')
      ->willReturn([
        [
          'phase' => 1,
          'objectives' => [
            [
              'objective_id' => 'kill-missing-target',
              'type' => 'kill',
              'target_id' => 'npc-missing',
              'location_id' => 'canonical-room-1',
              'description' => 'Defeat the missing target.',
              'completion_criteria' => [
                'kind' => 'count',
                'metric' => 'current',
                'target_count' => 1,
                'description' => 'Defeat target once.',
              ],
            ],
          ],
        ],
      ]);

    $controller = new class($storyline_manager) extends StorylineExplorerPageController {
      public function exposeCollectEntityLinkageDiagnostics(array $template_data): array {
        return $this->collectEntityLinkageDiagnostics($template_data);
      }
    };

    $errors = $controller->exposeCollectEntityLinkageDiagnostics([
      'contacts' => [],
      'asset_references' => [],
      'metadata' => [],
      'chapters' => [
        [
          'chapter_id' => 'chapter-1',
          'scenes' => [
            [
              'scene_id' => 'scene-1',
              'quest_ids' => ['quest-beta'],
            ],
          ],
        ],
      ],
    ]);

    $error_text = implode('; ', $errors);
    $this->assertStringContainsString('Canonical location index unavailable in test fixture.', $error_text);
    $this->assertStringContainsString("target_id 'npc-missing' not in actor registry", $error_text);
    $this->assertStringNotContainsString("location_id 'canonical-room-1' not in entity registry", $error_text);
  }

  /**
   * @covers ::collectEntityLinkageDiagnostics
   */
  public function testCollectEntityLinkageDiagnosticsAllowsAnchoredInvestigateAndInteractTargets(): void {
    $storyline_manager = $this->createMock(StorylineManagerService::class);
    $storyline_manager->expects($this->once())
      ->method('getCanonicalLocationTemplateIndex')
      ->willReturn([
        'dungeon_ids' => [],
        'room_ids' => ['canonical-room-1' => TRUE],
        'dungeon_room_ids' => [],
        'errors' => [],
      ]);
    $storyline_manager->expects($this->once())
      ->method('getCanonicalQuestTemplateObjectivePhases')
      ->with('quest-gamma')
      ->willReturn([
        [
          'phase' => 1,
          'objectives' => [
            [
              'objective_id' => 'inspect-hazard',
              'type' => 'interact',
              'target_id' => 'hazard-threat',
              'location_id' => 'canonical-room-1',
              'description' => 'Inspect the hazard anchor.',
              'completion_criteria' => [
                'kind' => 'flag',
                'metric' => 'completed',
                'required_value' => TRUE,
                'description' => 'Inspect hazard.',
              ],
            ],
            [
              'objective_id' => 'investigate-faction',
              'type' => 'investigate',
              'target' => 'faction-anchor',
              'location' => 'scene-1',
              'description' => 'Investigate the faction anchor.',
              'completion_criteria' => [
                'kind' => 'count',
                'metric' => 'current',
                'target_count' => 1,
                'description' => 'Investigate once.',
              ],
            ],
          ],
        ],
      ]);

    $controller = new class($storyline_manager) extends StorylineExplorerPageController {
      public function exposeCollectEntityLinkageDiagnostics(array $template_data): array {
        return $this->collectEntityLinkageDiagnostics($template_data);
      }
    };

    $errors = $controller->exposeCollectEntityLinkageDiagnostics([
      'contacts' => [],
      'asset_references' => [
        ['asset_type' => 'hazard', 'asset_id' => 'hazard-threat'],
        ['asset_type' => 'faction', 'asset_id' => 'faction-anchor'],
      ],
      'metadata' => [],
      'chapters' => [
        [
          'chapter_id' => 'chapter-1',
          'scenes' => [
            [
              'scene_id' => 'scene-1',
              'quest_ids' => ['quest-gamma'],
            ],
          ],
        ],
      ],
    ]);

    $this->assertSame([], $errors);
  }

}
