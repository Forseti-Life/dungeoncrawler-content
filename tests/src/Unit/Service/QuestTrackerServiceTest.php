<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\ChatSessionManager;
use Drupal\dungeoncrawler_content\Service\CharacterStateService;
use Drupal\dungeoncrawler_content\Service\InventoryManagementService;
use Drupal\dungeoncrawler_content\Service\QuestTrackerService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Covers nested objective tracking behavior.
 *
 * @group dungeoncrawler_content
 * @group quest
 */
class QuestTrackerServiceTest extends UnitTestCase {

  /**
   * Verifies nested child objectives drive parent completion and phase progress.
   */
  public function testNestedObjectivesCompleteParentWhenChildrenFinish(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $service = new class(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(TimeInterface::class)
    ) extends QuestTrackerService {
      public function initializeStates(array $objectives): array {
        return $this->initializeObjectiveStates($objectives);
      }

      public function applyUpdate(array &$states, int $phase, string $objective_id, int $progress): array {
        return $this->applyObjectiveUpdate($states, $phase, $objective_id, $progress);
      }

      public function phaseComplete(array $states, int $phase): bool {
        return $this->isPhaseComplete($states, $phase);
      }
    };

    $states = $service->initializeStates([
      [
        'phase' => 1,
        'objectives' => [
          [
            'objective_id' => 'investigate_library',
            'type' => 'investigate',
            'description' => 'Investigate the ruined library.',
            'completed' => FALSE,
            'children' => [
              [
                'objective_id' => 'question_the_warden',
                'type' => 'investigate',
                'description' => 'Question the library warden.',
                'completed' => FALSE,
                'current' => 0,
                'target_count' => 1,
              ],
              [
                'objective_id' => 'report_to_eldric',
                'type' => 'interact',
                'description' => 'Report the findings to Eldric.',
                'completed' => FALSE,
              ],
            ],
          ],
        ],
      ],
    ]);

    $this->assertSame('all_children', $states[0]['objectives'][0]['completion_criteria']['kind']);
    $this->assertFalse($service->phaseComplete($states, 1));

    $first_update = $service->applyUpdate($states, 1, 'question_the_warden', 1);
    $this->assertTrue($first_update['updated']);
    $this->assertTrue($first_update['objective_completed']);
    $this->assertFalse($service->phaseComplete($states, 1));
    $this->assertFalse($states[0]['objectives'][0]['completed']);

    $second_update = $service->applyUpdate($states, 1, 'report_to_eldric', 1);
    $this->assertTrue($second_update['updated']);
    $this->assertTrue($second_update['objective_completed']);
    $this->assertTrue($service->phaseComplete($states, 1));
    $this->assertTrue($states[0]['objectives'][0]['completed']);
  }

  /**
   * Verifies only the current phase is revealed when quest progress starts.
   */
  public function testInitializeStatesRevealsOnlyCurrentPhaseObjectives(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $service = new class(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(TimeInterface::class)
    ) extends QuestTrackerService {
      public function initializeStates(array $objectives): array {
        return $this->initializeObjectiveStates($objectives);
      }
    };

    $states = $service->initializeStates([
      [
        'phase' => 1,
        'objectives' => [[
          'objective_id' => 'speak_with_eldric',
          'type' => 'interact',
          'description' => 'Speak with Eldric.',
          'completed' => FALSE,
        ]],
      ],
      [
        'phase' => 2,
        'objectives' => [[
          'objective_id' => 'explore_the_archive',
          'type' => 'explore',
          'description' => 'Explore the hidden archive.',
          'completed' => FALSE,
        ]],
      ],
    ]);

    $this->assertTrue($states[0]['objectives'][0]['revealed']);
    $this->assertFalse($states[1]['objectives'][0]['revealed']);
  }

  /**
   * Verifies prompt formatting avoids leaking raw opaque identifiers.
   */
  public function testFormatObjectiveForPromptHumanizesOpaqueTargets(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $service = new class(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(TimeInterface::class)
    ) extends QuestTrackerService {
      public function formatObjective(array $objective): string {
        return $this->formatObjectiveForPrompt($objective);
      }
    };

    $formatted = $service->formatObjective([
      'objective_id' => 'accept_demo_briefing',
      'type' => 'interact',
      'description' => 'Hear the mission briefing and commit the party to the adventure.',
      'target' => 'tal-mission-handler',
    ]);

    $this->assertStringContainsString('Tal Mission Handler', $formatted);
    $this->assertStringNotContainsString('target: tal-mission-handler', $formatted);
  }

  /**
   * Verifies final-phase completion is recognized as terminal quest completion.
   */
  public function testFinalPhaseCompletionMarksQuestComplete(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $service = new class(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(TimeInterface::class)
    ) extends QuestTrackerService {
      public function initializeStates(array $objectives): array {
        return $this->initializeObjectiveStates($objectives);
      }

      public function applyUpdate(array &$states, int $phase, string $objective_id, int $progress): array {
        return $this->applyObjectiveUpdate($states, $phase, $objective_id, $progress);
      }

      public function questComplete(array $states): bool {
        return $this->isQuestCompleted($states);
      }
    };

    $states = $service->initializeStates([
      [
        'phase' => 1,
        'objectives' => [
          [
            'objective_id' => 'speak_to_eldric',
            'type' => 'interact',
            'description' => 'Speak to Eldric.',
            'completed' => FALSE,
            'target' => 'tavern_keeper',
          ],
          [
            'objective_id' => 'speak_to_marta',
            'type' => 'interact',
            'description' => 'Speak to Marta.',
            'completed' => FALSE,
            'target' => 'scholar_npc',
          ],
        ],
      ],
    ]);

    $service->applyUpdate($states, 1, 'speak_to_eldric', 1);
    $this->assertFalse($service->questComplete($states));

    $result = $service->applyUpdate($states, 1, 'speak_to_marta', 1);
    $this->assertTrue($result['updated']);
    $this->assertTrue($result['objective_completed']);
    $this->assertTrue($service->questComplete($states));
  }

  /**
   * Verifies objective completion resolves a concrete next-step label.
   */
  public function testNextStepLabelUsesNextIncompleteObjective(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $service = new class(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(TimeInterface::class)
    ) extends QuestTrackerService {
      public function initializeStates(array $objectives): array {
        return $this->initializeObjectiveStates($objectives);
      }

      public function applyUpdate(array &$states, int $phase, string $objective_id, int $progress): array {
        return $this->applyObjectiveUpdate($states, $phase, $objective_id, $progress);
      }

      public function nextStepLabel(array $states, int $phase): string {
        return $this->resolveNextObjectiveNarrationLabel($states, $phase);
      }
    };

    $states = $service->initializeStates([
      [
        'phase' => 1,
        'objectives' => [
          [
            'objective_id' => 'speak_to_eldric',
            'type' => 'interact',
            'description' => 'Speak to Eldric.',
            'completed' => FALSE,
          ],
          [
            'objective_id' => 'speak_to_marta',
            'type' => 'interact',
            'description' => 'Talk to Marta.',
            'completed' => FALSE,
          ],
        ],
      ],
    ]);

    $service->applyUpdate($states, 1, 'speak_to_eldric', 1);
    $this->assertSame('Talk to Marta.', $service->nextStepLabel($states, 1));
  }

  /**
   * Verifies hidden escort runtime steps reveal sequentially and sync metadata.
   */
  public function testEscortRuntimeStepsRevealSequentially(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $service = new class(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(TimeInterface::class)
    ) extends QuestTrackerService {
      public function initializeStates(array $objectives): array {
        return $this->initializeObjectiveStates($objectives);
      }

      public function applyUpdate(array &$states, int $phase, string $objective_id, int $progress): array {
        return $this->applyObjectiveUpdate($states, $phase, $objective_id, $progress);
      }
    };

    $states = $service->initializeStates([
      [
        'phase' => 1,
        'objectives' => [
          [
            'objective_id' => 'escort_to_safety',
            'type' => 'escort',
            'description' => 'Escort Marta to the safehouse.',
            'destination' => 'safehouse',
            'arrived' => FALSE,
            'path_encounters' => [
              [
                'encounter_id' => 'escort_to_safety_path_encounter_1',
                'resolved' => FALSE,
              ],
              [
                'encounter_id' => 'escort_to_safety_path_encounter_2',
                'resolved' => FALSE,
              ],
            ],
            'children' => [
              [
                'objective_id' => 'escort_to_safety_runtime_1',
                'type' => 'interact',
                'target' => 'escort_to_safety_path_encounter_1',
                'description' => 'Handle the first complication.',
                'encounter_id' => 'escort_to_safety_path_encounter_1',
                'completed' => FALSE,
                'hidden' => FALSE,
              ],
              [
                'objective_id' => 'escort_to_safety_runtime_2',
                'type' => 'interact',
                'target' => 'escort_to_safety_path_encounter_2',
                'description' => 'Handle the second complication.',
                'encounter_id' => 'escort_to_safety_path_encounter_2',
                'completed' => FALSE,
                'hidden' => TRUE,
              ],
              [
                'objective_id' => 'escort_to_safety_arrive',
                'type' => 'explore',
                'location' => 'safehouse',
                'description' => 'Reach the safehouse.',
                'escort_arrival' => TRUE,
                'completed' => FALSE,
                'hidden' => TRUE,
              ],
            ],
          ],
        ],
      ],
    ]);

    $escort = $states[0]['objectives'][0];
    $this->assertTrue($escort['children'][0]['revealed']);
    $this->assertFalse($escort['children'][1]['revealed']);
    $this->assertFalse($escort['children'][2]['revealed']);

    $service->applyUpdate($states, 1, 'escort_to_safety_runtime_1', 1);
    $escort = $states[0]['objectives'][0];
    $this->assertTrue($escort['path_encounters'][0]['resolved']);
    $this->assertTrue($escort['children'][1]['revealed']);
    $this->assertFalse($escort['children'][2]['revealed']);

    $service->applyUpdate($states, 1, 'escort_to_safety_runtime_2', 1);
    $escort = $states[0]['objectives'][0];
    $this->assertTrue($escort['path_encounters'][1]['resolved']);
    $this->assertTrue($escort['children'][2]['revealed']);

    $service->applyUpdate($states, 1, 'escort_to_safety_arrive', 1);
    $escort = $states[0]['objectives'][0];
    $this->assertTrue($escort['arrived']);
    $this->assertTrue($escort['completed']);
  }

  /**
   * Verifies objective completion notes write to room chat and session chat.
   */
  public function testObjectiveCompletionNarratorNoteWritesRoomAndSessionChat(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $chat_session_manager = $this->createMock(ChatSessionManager::class);
    $chat_session_manager->expects($this->once())
      ->method('ensureRoomSession')
      ->with(221, '77', 'tavern_entrance', 'Tavern Entrance')
      ->willReturn(['id' => 901]);
    $chat_session_manager->expects($this->once())
      ->method('postMessage')
      ->with(
        901,
        221,
        'Narrator',
        'narrator',
        '',
        $this->stringContains('Objective completed for The Missing Spellbooks'),
        'narrative',
        'public',
        $this->callback(function (array $metadata): bool {
          return ($metadata['event'] ?? '') === 'quest_objective_completed'
            && ($metadata['message_class'] ?? '') === 'quest_objective_completion';
        })
      )
      ->willReturn(1);
    $chat_session_manager->expects($this->never())
      ->method('ensureCampaignSessions');

    $service = new class(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(TimeInterface::class),
      NULL,
      NULL,
      $chat_session_manager
    ) extends QuestTrackerService {
      public array $legacyNotes = [];

      protected function resolveQuestNarrationContext(int $campaign_id, array $quest): array {
        return ['77', 'tavern_entrance', 'Tavern Entrance'];
      }

      protected function appendQuestNarratorNoteToLegacyRoomChat(
        int $campaign_id,
        string $dungeon_id,
        string $room_id,
        string $message,
        array $metadata = []
      ): bool {
        $this->legacyNotes[] = [
          'campaign_id' => $campaign_id,
          'dungeon_id' => $dungeon_id,
          'room_id' => $room_id,
          'message' => $message,
          'metadata' => $metadata,
        ];
        return TRUE;
      }

      public function emitObjectiveNarratorNote(int $campaign_id, array $quest, string $objective_id, ?int $character_id, string $next_step): void {
        $this->postQuestObjectiveCompletionNarratorNote($campaign_id, $quest, $objective_id, $character_id, $next_step);
      }
    };

    $quest = [
      'quest_id' => 'missing_spellbooks',
      'quest_name' => 'The Missing Spellbooks',
      'generated_objectives' => json_encode([
        [
          'phase' => 1,
          'objectives' => [
            [
              'objective_id' => 'collect_books',
              'description' => 'Collect all missing spellbooks',
              'completed' => TRUE,
            ],
          ],
        ],
      ]),
    ];

    $service->emitObjectiveNarratorNote(221, $quest, 'collect_books', 812, 'Return to Archivist Myra');

    $this->assertCount(1, $service->legacyNotes);
    $this->assertSame(221, $service->legacyNotes[0]['campaign_id']);
    $this->assertSame('77', $service->legacyNotes[0]['dungeon_id']);
    $this->assertSame('tavern_entrance', $service->legacyNotes[0]['room_id']);
    $this->assertStringContainsString('Objective completed for The Missing Spellbooks', $service->legacyNotes[0]['message']);
    $this->assertStringContainsString('Next step: Return to Archivist Myra.', $service->legacyNotes[0]['message']);
    $this->assertSame('quest_objective_completion', $service->legacyNotes[0]['metadata']['message_class'] ?? '');
  }

  /**
   * Verifies quest-completion notes fail fast when room context is missing.
   */
  public function testQuestCompletionNarratorNoteFailsWithoutRoomContext(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $chat_session_manager = $this->createMock(ChatSessionManager::class);
    $chat_session_manager->expects($this->never())->method('ensureCampaignSessions');
    $chat_session_manager->expects($this->never())->method('systemLogSessionKey');
    $chat_session_manager->expects($this->never())->method('loadSession');
    $chat_session_manager->expects($this->never())->method('postMessage');
    $chat_session_manager->expects($this->never())->method('ensureRoomSession');

    $service = new class(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(TimeInterface::class),
      NULL,
      NULL,
      $chat_session_manager
    ) extends QuestTrackerService {
      public array $legacyNotes = [];

      protected function resolveQuestNarrationContext(int $campaign_id, array $quest): array {
        return ['', '', ''];
      }

      protected function appendQuestNarratorNoteToLegacyRoomChat(
        int $campaign_id,
        string $dungeon_id,
        string $room_id,
        string $message,
        array $metadata = []
      ): bool {
        $this->legacyNotes[] = $message;
        return TRUE;
      }

      public function emitQuestCompletionNarratorNote(int $campaign_id, array $quest, ?int $character_id): void {
        $this->postQuestCompletionNarratorNote($campaign_id, $quest, $character_id);
      }
    };

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('requires resolved dungeon_id and room_id context');
    $service->emitQuestCompletionNarratorNote(305, [
      'quest_id' => 'hollow_promise',
      'quest_name' => 'Hollow Promise',
    ], 812);
  }

  /**
   * Verifies quest rewards persist into campaign character state and inventory.
   */
  public function testApplyQuestRewardsPersistsCampaignCharacterState(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $character_state = $this->createMock(CharacterStateService::class);
    $character_state->expects($this->once())
      ->method('gainExperience')
      ->with('901', 120, 222, 'inst-1');
    $character_state->expects($this->once())
      ->method('getState')
      ->with('901', 222, 'inst-1')
      ->willReturn([
        'basicInfo' => ['experiencePoints' => 340, 'gold' => 5],
        'inventory' => ['currency' => ['cp' => 0, 'sp' => 0, 'gp' => 5, 'pp' => 0]],
      ]);
    $character_state->expects($this->once())
      ->method('setState')
      ->with(
        '901',
        $this->callback(function (array $state): bool {
          return (int) ($state['inventory']['currency']['gp'] ?? 0) === 35
            && (int) ($state['gold'] ?? 0) === 35
            && (int) ($state['basicInfo']['gold'] ?? 0) === 35;
        }),
        NULL,
        222,
        'inst-1'
      )
      ->willReturn([]);

    $inventory_service = $this->createMock(InventoryManagementService::class);
    $inventory_service->expects($this->once())
      ->method('addItemToInventory')
      ->with(
        '901',
        'character',
        $this->callback(function (array $item): bool {
          return ($item['id'] ?? '') === 'quest_reward_bundle'
            && ($item['item_type'] ?? '') === 'treasure';
        }),
        'carried',
        2,
        222
      )
      ->willReturn(['success' => TRUE]);

    $service = new class(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(TimeInterface::class),
      NULL,
      NULL,
      NULL,
      $character_state,
      $inventory_service
    ) extends QuestTrackerService {
      protected function resolveQuestRewardCharacterTarget(int $campaign_id, int $character_id): ?array {
        return [
          'row_id' => 901,
          'campaign_id' => $campaign_id,
          'instance_id' => 'inst-1',
        ];
      }

      public function applyRewardsForTest(int $campaign_id, ?int $character_id, array $rewards): array {
        return $this->applyQuestRewards($campaign_id, $character_id, $rewards);
      }
    };

    $applied = $service->applyRewardsForTest(222, 816, [
      'xp' => 120,
      'gold' => 30,
      'items' => [[
        'item_id' => 'quest_reward_bundle',
        'quantity' => 2,
      ]],
    ]);

    $this->assertSame(120, (int) ($applied['xp'] ?? 0));
    $this->assertSame(30, (int) ($applied['gold'] ?? 0));
    $this->assertCount(1, $applied['items'] ?? []);
    $this->assertSame('quest_reward_bundle', $applied['items'][0]['item_id'] ?? '');
    $this->assertSame(2, (int) ($applied['items'][0]['quantity'] ?? 0));
  }

  /**
   * Verifies quest reward application fails fast on non-canonical reward keys.
   */
  public function testApplyQuestRewardsRejectsNonCanonicalRewardShape(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $service = new class(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(TimeInterface::class),
      NULL,
      NULL,
      NULL,
      $this->createMock(CharacterStateService::class),
      $this->createMock(InventoryManagementService::class)
    ) extends QuestTrackerService {
      public function assertRewardsForTest(mixed $rewards, string $quest_id): array {
        return $this->assertQuestRewardContract($rewards, $quest_id);
      }
    };

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('missing required key "xp"');

    $service->assertRewardsForTest([
      'experience_points' => 50,
      'gp' => 5,
      'items' => [],
    ], 'quest_alpha');
  }

}
