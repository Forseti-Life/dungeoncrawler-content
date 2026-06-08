<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\ChatSessionManager;
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
        'gm',
        '',
        $this->stringContains('Objective completed for The Missing Spellbooks'),
        'system',
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
   * Verifies quest-completion notes fall back to system log when room context is missing.
   */
  public function testQuestCompletionNarratorNoteFallsBackToSystemLogWithoutRoomContext(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $chat_session_manager = $this->createMock(ChatSessionManager::class);
    $chat_session_manager->expects($this->once())
      ->method('ensureCampaignSessions')
      ->with(305);
    $chat_session_manager->expects($this->once())
      ->method('systemLogSessionKey')
      ->with(305)
      ->willReturn('campaign.305.system_log');
    $chat_session_manager->expects($this->once())
      ->method('loadSession')
      ->with('campaign.305.system_log')
      ->willReturn(['id' => 44]);
    $chat_session_manager->expects($this->once())
      ->method('postMessage')
      ->with(
        44,
        305,
        'Narrator',
        'gm',
        '',
        'Quest completed: Hollow Promise. All goals accomplished.',
        'system',
        'public',
        $this->callback(function (array $metadata): bool {
          return ($metadata['event'] ?? '') === 'quest_completed'
            && ($metadata['message_class'] ?? '') === 'quest_completion';
        })
      )
      ->willReturn(1);
    $chat_session_manager->expects($this->never())
      ->method('ensureRoomSession');

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

    $service->emitQuestCompletionNarratorNote(305, [
      'quest_id' => 'hollow_promise',
      'quest_name' => 'Hollow Promise',
    ], 812);

    $this->assertSame([], $service->legacyNotes);
  }

}
