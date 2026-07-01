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
   * @covers ::loadStorylineTemplates
   */
  public function testLoadStorylineTemplatesUsesCanonicalDbRowsFromStorylineManager(): void {
    $storyline_manager = $this->createMock(StorylineManagerService::class);
    $storyline_manager->expects($this->once())
      ->method('listTemplates')
      ->willReturn([
        [
          'template_id' => 'beta-template',
          'name' => 'Beta Story',
          'template_data' => ['template_id' => 'beta-template'],
        ],
        [
          'template_id' => 'alpha-template',
          'name' => 'Alpha Story',
          'template_data' => ['template_id' => 'alpha-template'],
        ],
        [
          'template_id' => '',
          'name' => 'Invalid',
          'template_data' => [],
        ],
      ]);

    $controller = new class($storyline_manager) extends StorylineExplorerPageController {
      public function exposeLoadStorylineTemplates(): array {
        return $this->loadStorylineTemplates();
      }
    };

    $templates = $controller->exposeLoadStorylineTemplates();
    $this->assertCount(2, $templates);
    $this->assertSame('alpha-template', (string) ($templates[0]['template_id'] ?? ''));
    $this->assertSame('beta-template', (string) ($templates[1]['template_id'] ?? ''));
  }

  /**
   * @covers ::loadStorylineTemplates
   */
  public function testLoadStorylineTemplatesFailsWithoutStorylineManager(): void {
    $controller = new class(NULL) extends StorylineExplorerPageController {
      public function exposeLoadStorylineTemplates(): array {
        return $this->loadStorylineTemplates();
      }
    };

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Storyline explorer requires StorylineManagerService');
    $controller->exposeLoadStorylineTemplates();
  }

  /**
   * @covers ::buildPlayerPartyMermaidDiagram
   */
  public function testBuildPlayerPartyMermaidDiagramAvoidsMarkdownListPrefix(): void {
    $controller = new class(NULL) extends StorylineExplorerPageController {
      public function exposeBuildPlayerPartyMermaidDiagram(array $steps): string {
        return $this->buildPlayerPartyMermaidDiagram($steps);
      }
    };

    $diagram = $controller->exposeBuildPlayerPartyMermaidDiagram([
      [
        'title' => 'Talk to Eldric',
        'detail' => 'Intro handoff.',
        'touches' => ['npc.eldric'],
      ],
    ]);

    $this->assertStringContainsString('graph LR', $diagram);
    $this->assertStringContainsString('Step 1: Talk to Eldric', $diagram);
    $this->assertStringNotContainsString('1. Talk to Eldric', $diagram);
  }

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

  /**
   * @covers ::buildEntityTypeVerificationRows
   */
  public function testBuildEntityTypeVerificationRowsTracksPerTypeFailures(): void {
    $controller = new class(NULL) extends StorylineExplorerPageController {
      public function exposeBuildEntityTypeVerificationRows(array $template_data, array $stages): array {
        return $this->buildEntityTypeVerificationRows($template_data, $stages);
      }
    };

    $rows = $controller->exposeBuildEntityTypeVerificationRows([
      'asset_references' => [
        ['asset_type' => 'npc', 'asset_id' => 'npc-a'],
        ['asset_type' => 'hazard', 'asset_id' => 'hazard-a'],
      ],
      'contacts' => [
        ['entity_type' => 'campaign_npc', 'entity_id' => 'npc_tavern_keeper'],
      ],
    ], [
      'entity_type_contracts' => [
        'valid' => FALSE,
        'errors' => [
          "[entity_type_contracts:npc] asset_references[0] (npc-a): missing contract",
          "[entity_type_contracts:npc] contacts[0] (npc_tavern_keeper): missing contract",
        ],
      ],
    ]);

    $by_type = [];
    foreach ($rows as $row) {
      $by_type[(string) ($row['entity_type'] ?? '')] = $row;
    }

    $this->assertSame('FAIL', (string) ($by_type['npc']['status'] ?? ''));
    $this->assertSame(2, (int) ($by_type['npc']['error_count'] ?? 0));
    $this->assertSame('PASS', (string) ($by_type['hazard']['status'] ?? ''));
    $this->assertSame(0, (int) ($by_type['hazard']['error_count'] ?? 0));
    $this->assertSame('PASS', (string) ($by_type['campaign_npc']['status'] ?? ''));
    $this->assertSame(0, (int) ($by_type['campaign_npc']['error_count'] ?? 0));
  }

  /**
   * @covers ::buildEntityTypeVerificationRows
   */
  public function testBuildEntityTypeVerificationRowsMarksUnknownWhenStageUnavailable(): void {
    $controller = new class(NULL) extends StorylineExplorerPageController {
      public function exposeBuildEntityTypeVerificationRows(array $template_data, array $stages): array {
        return $this->buildEntityTypeVerificationRows($template_data, $stages);
      }
    };

    $rows = $controller->exposeBuildEntityTypeVerificationRows([
      'asset_references' => [
        ['asset_type' => 'hazard', 'asset_id' => 'hazard-a'],
      ],
      'contacts' => [],
    ], []);

    $this->assertSame('UNKNOWN', (string) ($rows[0]['status'] ?? ''));
    $this->assertSame(0, (int) ($rows[0]['error_count'] ?? 0));
  }

  /**
   * @covers ::buildEntityTypeVerificationRows
   */
  public function testBuildEntityTypeVerificationRowsMarksUnsupportedTypeUnknown(): void {
    $storyline_manager = $this->createMock(StorylineManagerService::class);
    $storyline_manager->expects($this->once())
      ->method('getSupportedEntityTypeContractTypes')
      ->willReturn(['npc', 'hazard']);

    $controller = new class($storyline_manager) extends StorylineExplorerPageController {
      public function exposeBuildEntityTypeVerificationRows(array $template_data, array $stages): array {
        return $this->buildEntityTypeVerificationRows($template_data, $stages);
      }
    };

    $rows = $controller->exposeBuildEntityTypeVerificationRows([
      'asset_references' => [
        ['asset_type' => 'quest', 'asset_id' => 'quest-a'],
      ],
      'contacts' => [],
    ], [
      'entity_type_contracts' => [
        'valid' => TRUE,
        'errors' => [],
      ],
    ]);

    $this->assertSame('UNKNOWN', (string) ($rows[0]['status'] ?? ''));
    $this->assertSame(0, (int) ($rows[0]['error_count'] ?? 0));
  }

  /**
   * @covers ::collectQuestTemplateDiagnostics
   */
  public function testCollectQuestTemplateDiagnosticsFlagsMissingAndEmptyCanonicalRows(): void {
    $storyline_manager = $this->createMock(StorylineManagerService::class);
    $storyline_manager->expects($this->exactly(2))
      ->method('getCanonicalQuestTemplateObjectivePhases')
      ->willReturnMap([
        ['quest-empty', []],
        ['quest-missing', NULL],
      ]);

    $controller = new class($storyline_manager) extends StorylineExplorerPageController {
      public function exposeCollectQuestTemplateDiagnostics(array $template_data): array {
        return $this->collectQuestTemplateDiagnostics($template_data);
      }
    };

    $rows = $controller->exposeCollectQuestTemplateDiagnostics([
      'linked_quests' => [
        ['quest_id' => 'quest-missing'],
      ],
      'chapters' => [
        [
          'scenes' => [
            [
              'quest_ids' => ['quest-empty'],
            ],
          ],
        ],
      ],
    ]);

    $by_quest = [];
    foreach ($rows as $row) {
      $by_quest[(string) ($row['quest_id'] ?? '')] = $row;
    }

    $this->assertSame('FAIL', (string) ($by_quest['quest-empty']['status'] ?? ''));
    $this->assertSame('FAIL', (string) ($by_quest['quest-missing']['status'] ?? ''));
    $this->assertContains('objectives_schema payload is empty.', (array) ($by_quest['quest-empty']['errors'] ?? []));
    $this->assertContains('canonical quest template row not found in dungeoncrawler_content_quest_templates.', (array) ($by_quest['quest-missing']['errors'] ?? []));
  }

  /**
   * @covers ::collectQuestTemplateDiagnostics
   */
  public function testCollectQuestTemplateDiagnosticsPassesValidTemplateAndFlagsMalformedObjectives(): void {
    $storyline_manager = $this->createMock(StorylineManagerService::class);
    $storyline_manager->expects($this->exactly(2))
      ->method('getCanonicalQuestTemplateObjectivePhases')
      ->willReturnMap([
        [
          'quest-invalid',
          [
            [
              'phase' => 1,
              'objectives' => [
                [
                  'description' => 'Missing required fields.',
                ],
              ],
            ],
          ],
        ],
        [
          'quest-valid',
          [
            [
              'phase' => 1,
              'objectives' => [
                [
                  'objective_id' => 'objective-1',
                  'type' => 'investigate',
                  'completion_criteria' => [
                    'kind' => 'count',
                    'metric' => 'current',
                    'target_count' => 1,
                    'description' => 'Investigate once.',
                  ],
                ],
              ],
            ],
          ],
        ],
      ]);

    $controller = new class($storyline_manager) extends StorylineExplorerPageController {
      public function exposeCollectQuestTemplateDiagnostics(array $template_data): array {
        return $this->collectQuestTemplateDiagnostics($template_data);
      }
    };

    $rows = $controller->exposeCollectQuestTemplateDiagnostics([
      'chapters' => [
        [
          'scenes' => [
            [
              'quest_ids' => ['quest-valid', 'quest-invalid'],
            ],
          ],
        ],
      ],
    ]);

    $by_quest = [];
    foreach ($rows as $row) {
      $by_quest[(string) ($row['quest_id'] ?? '')] = $row;
    }

    $this->assertSame('PASS', (string) ($by_quest['quest-valid']['status'] ?? ''));
    $this->assertSame(1, (int) ($by_quest['quest-valid']['phase_count'] ?? 0));
    $this->assertSame(1, (int) ($by_quest['quest-valid']['objective_count'] ?? 0));
    $this->assertSame([], (array) ($by_quest['quest-valid']['errors'] ?? []));

    $invalid_errors = (array) ($by_quest['quest-invalid']['errors'] ?? []);
    $this->assertSame('FAIL', (string) ($by_quest['quest-invalid']['status'] ?? ''));
    $this->assertContains('phase[0].objectives[0].objective_id is required.', $invalid_errors);
    $this->assertContains('phase[0].objectives[0].type is required.', $invalid_errors);
    $this->assertContains('phase[0].objectives[0].completion_criteria is required.', $invalid_errors);
  }

  /**
   * @covers ::collectTemplateQuestIds
   */
  public function testCollectTemplateQuestIdsReturnsSortedUniqueQuestIdsFromLinkedAndScenes(): void {
    $controller = new class(NULL) extends StorylineExplorerPageController {
      public function exposeCollectTemplateQuestIds(array $template_data): array {
        return $this->collectTemplateQuestIds($template_data);
      }
    };

    $quest_ids = $controller->exposeCollectTemplateQuestIds([
      'linked_quests' => [
        ['quest_id' => 'quest-zeta'],
        'quest-alpha',
      ],
      'chapters' => [
        [
          'scenes' => [
            ['quest_ids' => ['quest-gamma', 'quest-alpha']],
          ],
        ],
      ],
    ]);

    $this->assertSame(['quest-alpha', 'quest-gamma', 'quest-zeta'], $quest_ids);
  }

  /**
   * @covers ::collectQuestTemplateDiagnostics
   */
  public function testCollectQuestTemplateDiagnosticsCanScopeToSelectedQuest(): void {
    $storyline_manager = $this->createMock(StorylineManagerService::class);
    $storyline_manager->expects($this->once())
      ->method('getCanonicalQuestTemplateObjectivePhases')
      ->with('quest-target')
      ->willReturn([
        [
          'phase' => 1,
          'objectives' => [
            [
              'objective_id' => 'objective-1',
              'type' => 'investigate',
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
      public function exposeCollectQuestTemplateDiagnostics(array $template_data, string $selected_quest_id = ''): array {
        return $this->collectQuestTemplateDiagnostics($template_data, $selected_quest_id);
      }
    };

    $rows = $controller->exposeCollectQuestTemplateDiagnostics([
      'chapters' => [
        [
          'scenes' => [
            [
              'quest_ids' => ['quest-target', 'quest-other'],
            ],
          ],
        ],
      ],
    ], 'quest-target');

    $this->assertCount(1, $rows);
    $this->assertSame('quest-target', (string) ($rows[0]['quest_id'] ?? ''));
    $this->assertSame('PASS', (string) ($rows[0]['status'] ?? ''));
  }

  /**
   * @covers ::collectQuestTemplateDiagnostics
   */
  public function testCollectQuestTemplateDiagnosticsCanValidateSelectedUnlinkedQuest(): void {
    $storyline_manager = $this->createMock(StorylineManagerService::class);
    $storyline_manager->expects($this->once())
      ->method('getCanonicalQuestTemplateObjectivePhases')
      ->with('quest-unlinked')
      ->willReturn([
        [
          'phase' => 1,
          'objectives' => [
            [
              'objective_id' => 'objective-1',
              'type' => 'interact',
              'completion_criteria' => [
                'kind' => 'flag',
                'metric' => 'completed',
                'required_value' => TRUE,
                'description' => 'Interact once.',
              ],
            ],
          ],
        ],
      ]);

    $controller = new class($storyline_manager) extends StorylineExplorerPageController {
      public function exposeCollectQuestTemplateDiagnostics(array $template_data, string $selected_quest_id = ''): array {
        return $this->collectQuestTemplateDiagnostics($template_data, $selected_quest_id);
      }
    };

    $rows = $controller->exposeCollectQuestTemplateDiagnostics([
      'chapters' => [
        [
          'scenes' => [
            [
              'quest_ids' => ['quest-linked'],
            ],
          ],
        ],
      ],
    ], 'quest-unlinked');

    $this->assertCount(1, $rows);
    $this->assertSame('quest-unlinked', (string) ($rows[0]['quest_id'] ?? ''));
    $this->assertSame('PASS', (string) ($rows[0]['status'] ?? ''));
  }

  /**
   * @covers ::collectTaskContractDiagnostics
   */
  public function testCollectTaskContractDiagnosticsSkipsWhenSelectedQuestIsNotLinked(): void {
    $storyline_manager = $this->createMock(StorylineManagerService::class);
    $storyline_manager->expects($this->never())
      ->method('getCanonicalQuestTemplateObjectivePhases');

    $controller = new class($storyline_manager) extends StorylineExplorerPageController {
      public function exposeCollectTaskContractDiagnostics(array $template_data, string $selected_quest_id = ''): array {
        return $this->collectTaskContractDiagnostics($template_data, $selected_quest_id);
      }
    };

    $errors = $controller->exposeCollectTaskContractDiagnostics([
      'chapters' => [
        [
          'scenes' => [
            [
              'quest_ids' => ['quest-linked'],
            ],
          ],
        ],
      ],
    ], 'quest-unlinked');

    $this->assertSame([], $errors);
  }

  /**
   * @covers ::collectEntityLinkageDiagnostics
   */
  public function testCollectEntityLinkageDiagnosticsSkipsWhenSelectedQuestIsNotLinked(): void {
    $storyline_manager = $this->createMock(StorylineManagerService::class);
    $storyline_manager->expects($this->never())
      ->method('getCanonicalLocationTemplateIndex');
    $storyline_manager->expects($this->never())
      ->method('getCanonicalQuestTemplateObjectivePhases');

    $controller = new class($storyline_manager) extends StorylineExplorerPageController {
      public function exposeCollectEntityLinkageDiagnostics(array $template_data, string $selected_quest_id = ''): array {
        return $this->collectEntityLinkageDiagnostics($template_data, $selected_quest_id);
      }
    };

    $errors = $controller->exposeCollectEntityLinkageDiagnostics([
      'chapters' => [
        [
          'scenes' => [
            [
              'quest_ids' => ['quest-linked'],
            ],
          ],
        ],
      ],
    ], 'quest-unlinked');

    $this->assertSame([], $errors);
  }

  /**
   * @covers ::buildTemplateSelectorOptions
   */
  public function testBuildTemplateSelectorOptionsUsesTemplateIdAndName(): void {
    $controller = new class(NULL) extends StorylineExplorerPageController {
      public function exposeBuildTemplateSelectorOptions(array $templates): array {
        return $this->buildTemplateSelectorOptions($templates);
      }
    };

    $options = $controller->exposeBuildTemplateSelectorOptions([
      ['template_id' => 'tok', 'name' => 'Threshold of Knowledge'],
      ['template_id' => 'ltba', 'name' => 'Little Trouble in Big Absalom'],
      ['template_id' => '', 'name' => 'Invalid'],
    ]);

    $this->assertSame([
      'tok' => 'Threshold of Knowledge',
      'ltba' => 'Little Trouble in Big Absalom',
    ], $options);
  }

  /**
   * @covers ::buildQuestSelectorOptions
   */
  public function testBuildQuestSelectorOptionsIncludesLinkedAndUnlinkedGroups(): void {
    $controller = new class(NULL) extends StorylineExplorerPageController {
      public function t($string, array $args = [], array $options = []) {
        return (string) $string;
      }
      public function exposeBuildQuestSelectorOptions(array $linked, array $unlinked): array {
        return $this->buildQuestSelectorOptions($linked, $unlinked);
      }
    };

    $options = $controller->exposeBuildQuestSelectorOptions(
      ['quest-b', 'quest-a'],
      ['quest-z']
    );

    $this->assertSame('All linked quests', (string) ($options[''] ?? ''));
    $this->assertArrayHasKey('Linked to selected storyline', $options);
    $this->assertArrayHasKey('Unlinked canonical library quests', $options);
    $this->assertSame([
      'quest-a' => 'quest-a',
      'quest-b' => 'quest-b',
    ], $options['Linked to selected storyline']);
    $this->assertSame([
      'quest-z' => 'quest-z',
    ], $options['Unlinked canonical library quests']);
  }

  /**
   * @covers ::renderSelectOptionsMarkup
   */
  public function testRenderSelectOptionsMarkupRendersSelectedOptionAndOptgroup(): void {
    $controller = new class(NULL) extends StorylineExplorerPageController {
      public function exposeRenderSelectOptionsMarkup(array $options, string $selected): string {
        return $this->renderSelectOptionsMarkup($options, $selected);
      }
    };

    $markup = $controller->exposeRenderSelectOptionsMarkup([
      '' => 'All linked quests',
      'Unlinked canonical library quests' => [
        'quest-z' => 'quest-z',
      ],
    ], 'quest-z');

    $this->assertStringContainsString('<option value="">All linked quests</option>', $markup);
    $this->assertStringContainsString('<optgroup label="Unlinked canonical library quests">', $markup);
    $this->assertStringContainsString('<option value="quest-z" selected>quest-z</option>', $markup);
  }

}
