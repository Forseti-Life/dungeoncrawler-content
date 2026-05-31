<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\NumberGenerationService;
use Drupal\dungeoncrawler_content\Service\QuestGeneratorService;
use Drupal\dungeoncrawler_content\Service\StateValidationService;
use Drupal\dungeoncrawler_content\Service\StorylineManagerService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Covers canonical quest contract normalization.
 *
 * @group dungeoncrawler_content
 * @group quest
 */
class QuestGeneratorServiceTest extends UnitTestCase {

  private QuestGeneratorService $service;

  protected function setUp(): void {
    parent::setUp();

    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $state_validation = $this->createMock(StateValidationService::class);
    $state_validation->method('validateQuestSummary')->willReturn([
      'valid' => TRUE,
      'errors' => [],
    ]);

    $this->service = new QuestGeneratorService(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(NumberGenerationService::class),
      $state_validation
    );
  }

  /**
   * Verifies quest rows normalize into the canonical summary entry contract.
   */
  public function testBuildQuestSummaryEntryNormalizesQuestRow(): void {
    $entry = $this->service->buildQuestSummaryEntry([
      'quest_id' => 'tok-find-the-missing-teacher_65_123',
      'source_template_id' => 'tok-find-the-missing-teacher',
      'quest_name' => 'Find the Missing Teacher',
      'status' => 'active',
      'current_phase' => '1',
      'generated_objectives' => json_encode([
        [
          'phase' => 1,
          'objectives' => [
            [
              'objective_id' => 'identify_last_known_location',
              'type' => 'investigate',
              'description' => 'Determine where the teacher disappeared.',
              'completed' => FALSE,
              'current' => 0,
              'target_count' => 1,
              'target' => 'Magaambya Campus',
              'children' => [
                [
                  'objective_id' => 'question_the_gate_wardens',
                  'type' => 'investigate',
                  'description' => 'Question the gate wardens.',
                  'completed' => FALSE,
                  'current' => 0,
                  'target_count' => 1,
                  'target' => 'Gate Wardens',
                ],
              ],
            ],
          ],
        ],
      ]),
      'generated_rewards' => json_encode(['xp' => 40]),
      'quest_data' => json_encode(['difficulty' => 'moderate']),
      'location_id' => 'tavern_entrance',
      'storyline_id' => 'threshold-of-knowledge',
      'storyline_chapter_id' => 'magaambya-campus',
      'storyline_scene_id' => 'missing-teacher',
    ]);

    $this->assertSame('tok-find-the-missing-teacher_65_123', $entry['quest_id']);
    $this->assertSame('tok-find-the-missing-teacher', $entry['quest_key']);
    $this->assertSame('Find the Missing Teacher', $entry['title']);
    $this->assertSame('Find the Missing Teacher', $entry['quest_name']);
    $this->assertSame('active', $entry['status']);
    $this->assertSame(1, $entry['current_phase']);
    $this->assertSame($entry['generated_objectives'], $entry['objective_states']);
    $this->assertSame('tavern_entrance', $entry['location_id']);
    $this->assertSame('threshold-of-knowledge', $entry['storyline']['storyline_id']);
    $this->assertSame('all_children', $entry['generated_objectives'][0]['objectives'][0]['completion_criteria']['kind']);
    $this->assertSame('count', $entry['generated_objectives'][0]['objectives'][0]['children'][0]['completion_criteria']['kind']);
    $this->assertSame(
      'Investigate Magaambya Campus until the clue or lead is recorded.',
      $entry['generated_objectives'][0]['objectives'][0]['next_step']
    );
  }

  /**
   * Verifies generated objectives read canonical contract fields only.
   */
  public function testGenerateObjectiveNodeUsesCanonicalLocationField(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $state_validation = $this->createMock(StateValidationService::class);
    $state_validation->method('validateQuestSummary')->willReturn([
      'valid' => TRUE,
      'errors' => [],
    ]);

    $number_generation = $this->createMock(NumberGenerationService::class);
    $number_generation->method('rollRange')->willReturn(3);

    $service = new class(
      $this->createMock(Connection::class),
      $logger_factory,
      $number_generation,
      $state_validation
    ) extends QuestGeneratorService {
      public function exposedGenerateObjectiveNode(array $objective_schema, array $variables = [], array $context = []): array {
        return $this->generateObjectiveNode($objective_schema, $variables, $context);
      }
    };

    $objective = $service->exposedGenerateObjectiveNode([
      'objective_id' => 'reach-library',
      'type' => 'explore',
      'description' => 'Reach the library.',
      'location' => 'grandmas-house-library',
      'target' => 'legacy-target-should-not-win',
      'completion_criteria' => [
        'kind' => 'flag',
        'metric' => 'discovered',
        'required_value' => TRUE,
        'description' => 'Discover the required location.',
      ],
    ]);

    $this->assertSame('grandmas-house-library', $objective['location']);
  }

  /**
   * Verifies generated interact objectives preserve explicit location ids.
   */
  public function testGenerateObjectiveNodePreservesInteractLocationId(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $state_validation = $this->createMock(StateValidationService::class);
    $state_validation->method('validateQuestSummary')->willReturn([
      'valid' => TRUE,
      'errors' => [],
    ]);

    $service = new class(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(NumberGenerationService::class),
      $state_validation
    ) extends QuestGeneratorService {
      public function exposedGenerateObjectiveNode(array $objective_schema, array $variables = [], array $context = []): array {
        return $this->generateObjectiveNode($objective_schema, $variables, $context);
      }
    };

    $objective = $service->exposedGenerateObjectiveNode([
      'objective_id' => 'return_books',
      'type' => 'interact',
      'target' => 'scholar_npc',
      'location_id' => '{location}',
      'description' => 'Return the books to the scholar.',
    ], [
      'location' => 'tavern_entrance',
    ]);

    $this->assertSame('scholar_npc', $objective['target']);
    $this->assertSame('tavern_entrance', $objective['location_id']);
  }

  /**
   * Verifies collect objectives use canonical generated counts and criteria.
   */
  public function testGenerateCollectObjectiveUsesTargetCountRangeForCriteria(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $state_validation = $this->createMock(StateValidationService::class);
    $state_validation->method('validateQuestSummary')->willReturn([
      'valid' => TRUE,
      'errors' => [],
    ]);

    $number_generation = $this->createMock(NumberGenerationService::class);
    $number_generation->expects($this->once())
      ->method('rollRange')
      ->with(2, 4)
      ->willReturn(4);

    $service = new class(
      $this->createMock(Connection::class),
      $logger_factory,
      $number_generation,
      $state_validation
    ) extends QuestGeneratorService {
      public function exposedGenerateObjectiveNode(array $objective_schema, array $variables = [], array $context = []): array {
        return $this->generateObjectiveNode($objective_schema, $variables, $context);
      }
    };

    $objective = $service->exposedGenerateObjectiveNode([
      'objective_id' => 'collect_books',
      'type' => 'collect',
      'item' => '{item_name}',
      'location_id' => '{location}',
      'target_count_range' => [2, 4],
      'description' => 'Find and collect {item_name} in {room_name}',
      'next_step' => 'Search {room_name} and pick up each {item_name} quest item.',
      'completion_criteria' => [
        'kind' => 'count',
        'metric' => 'current',
        'target_count' => 1,
        'description' => 'Collect {target_count} {item_name} in {room_name}.',
        ],
    ], [
        'item_name' => 'Spellbooks',
        'location' => 'tavern_entrance',
        'room_name' => 'The Gilded Tankard',
    ]);

    $this->assertSame('Spellbooks', $objective['item']);
    $this->assertSame('tavern_entrance', $objective['location_id']);
    $this->assertSame('Find and collect Spellbooks in The Gilded Tankard', $objective['description']);
    $this->assertSame('Search The Gilded Tankard and pick up each Spellbooks quest item.', $objective['next_step']);
    $this->assertSame(4, $objective['target_count']);
    $this->assertSame(4, $objective['completion_criteria']['target_count']);
    $this->assertSame('Collect 4 Spellbooks in The Gilded Tankard.', $objective['completion_criteria']['description']);
  }

  /**
   * Verifies investigate objectives inherit a discoverable location target.
   */
  public function testGenerateObjectiveNodeMapsInvestigateTargetToLocation(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $state_validation = $this->createMock(StateValidationService::class);
    $state_validation->method('validateQuestSummary')->willReturn([
      'valid' => TRUE,
      'errors' => [],
    ]);

    $service = new class(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(NumberGenerationService::class),
      $state_validation
    ) extends QuestGeneratorService {
      public function exposedGenerateObjectiveNode(array $objective_schema, array $variables = [], array $context = []): array {
        return $this->generateObjectiveNode($objective_schema, $variables, $context);
      }
    };

    $objective = $service->exposedGenerateObjectiveNode([
      'objective_id' => 'investigate_sanctum',
      'type' => 'investigate',
      'target' => 'sanctum',
      'target_count' => 1,
      'description' => 'Investigate the sanctum.',
    ]);

    $this->assertSame('sanctum', $objective['target']);
    $this->assertSame('sanctum', $objective['location']);
    $this->assertFalse($objective['discovered']);
  }

  /**
   * Verifies escort objectives materialize path encounters into runtime steps.
   */
  public function testGenerateObjectiveNodeBuildsEscortPathEncounters(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $state_validation = $this->createMock(StateValidationService::class);
    $state_validation->method('validateQuestSummary')->willReturn([
      'valid' => TRUE,
      'errors' => [],
    ]);

    $number_generation = $this->createMock(NumberGenerationService::class);
    $number_generation->expects($this->once())
      ->method('rollRange')
      ->with(1, 3)
      ->willReturn(3);

    $service = new class(
      $this->createMock(Connection::class),
      $logger_factory,
      $number_generation,
      $state_validation
    ) extends QuestGeneratorService {
      public function exposedGenerateObjectiveNode(array $objective_schema, array $variables = [], array $context = []): array {
        return $this->generateObjectiveNode($objective_schema, $variables, $context);
      }
    };

    $objective = $service->exposedGenerateObjectiveNode([
      'objective_id' => 'escort_to_safety',
      'type' => 'escort',
      'target' => 'Eldric',
      'npc_ref' => 'merchant_npc',
      'destination' => 'Safehouse',
      'encounter_count_range' => [1, 3],
      'encounter_types' => ['social', 'problem_solving', 'combat'],
      'encounter_profiles' => ['negotiation_collapse', 'chase_transition', 'ambush'],
      'description' => 'Escort Eldric back to safety.',
    ]);

    $this->assertCount(3, $objective['path_encounters']);
    $this->assertSame('social', $objective['path_encounters'][0]['encounter_type']);
    $this->assertSame('problem_solving', $objective['path_encounters'][1]['encounter_type']);
    $this->assertSame('combat', $objective['path_encounters'][2]['encounter_type']);
    $this->assertSame('negotiation_collapse', $objective['path_encounters'][0]['setup_profile']);
    $this->assertSame('ambush', $objective['path_encounters'][2]['setup_profile']);
    $this->assertFalse($objective['path_encounters'][0]['resolved']);
    $this->assertSame('all_children', $objective['completion_criteria']['kind']);
    $this->assertCount(4, $objective['children']);
    $this->assertSame('escort_to_safety_runtime_1', $objective['children'][0]['objective_id']);
    $this->assertFalse($objective['children'][0]['hidden']);
    $this->assertTrue($objective['children'][1]['hidden']);
    $this->assertTrue($objective['children'][3]['hidden']);
    $this->assertTrue($objective['children'][3]['escort_arrival']);
    $this->assertSame('Safehouse', $objective['children'][3]['location']);
  }

  /**
   * Verifies quest summary objectives do not leak label-only helper fields.
   */
  public function testBuildQuestSummaryEntryOmitsObjectiveLabelHelperFields(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $service = new class(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(NumberGenerationService::class)
    ) extends QuestGeneratorService {
      protected function resolveObjectiveReferenceLabel(array $quest_row, string $value): string {
        return match ($value) {
          'tavern_entrance' => 'Tavern Entrance',
          'scholar_npc' => 'Scholar',
          default => parent::resolveObjectiveReferenceLabel($quest_row, $value),
        };
      }
    };

    $entry = $service->buildQuestSummaryEntry([
      'quest_id' => 'quest-123',
      'quest_name' => 'Speak to the Scholar',
      'generated_objectives' => [[
        'phase' => 1,
        'objectives' => [[
          'objective_id' => 'talk-to-scholar',
          'type' => 'interact',
          'description' => 'Talk to scholar_npc in tavern_entrance.',
          'target' => 'scholar_npc',
          'location' => 'tavern_entrance',
          'completed' => FALSE,
        ]],
      ]],
    ]);

    $objective = $entry['generated_objectives'][0]['objectives'][0];
    $this->assertArrayNotHasKey('target_label', $objective);
    $this->assertArrayNotHasKey('location_label', $objective);
    $this->assertSame('Talk to Scholar in Tavern Entrance.', $objective['description']);
  }

  /**
   * Verifies management-tree verification fails closed on invalid storyline data.
   */
  public function testBuildQuestManagementTreeRejectsInvalidStorylineRuntimeContract(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $state_validation = $this->createMock(StateValidationService::class);
    $state_validation->method('validateQuestSummary')->willReturn([
      'valid' => TRUE,
      'errors' => [],
    ]);

    $storyline_manager = $this->createMock(StorylineManagerService::class);
    $storyline_manager->expects($this->once())
      ->method('validateRuntimeStorylineContract')
      ->willReturn([
        'valid' => FALSE,
        'errors' => ["Progression connector 'broken-handoff' points to unknown room 'missing-room'."],
      ]);

    $service = new class(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(NumberGenerationService::class),
      $state_validation,
      NULL,
      $storyline_manager
    ) extends QuestGeneratorService {
      protected function loadCampaignStorylineRows(int $campaign_id): array {
        return [[
          'storyline_id' => 'broken-storyline',
          'template_id' => 'broken-storyline',
          'name' => 'Broken Storyline',
          'status' => 'active',
          'priority' => 100,
          'current_chapter_id' => 'chapter-one',
          'current_scene_id' => 'scene-one',
          'storyline_data' => [
            'storyline_type' => 'questline',
            'metadata' => [
              'goal' => 'Follow the broken lead.',
            ],
            'chapters' => [[
              'chapter_id' => 'chapter-one',
              'name' => 'Chapter One',
              'scenes' => [[
                'scene_id' => 'scene-one',
                'name' => 'Scene One',
                'quest_ids' => ['broken-quest'],
              ]],
            ]],
            'linked_quests' => [
              'broken-quest' => [
                'quest_id' => 'broken-quest',
                'chapter_id' => 'chapter-one',
                'scene_id' => 'scene-one',
                'status' => 'active',
              ],
            ],
            'questline' => [
              'primary_quest_id' => 'broken-quest',
              'ordered_quest_ids' => ['broken-quest'],
              'quest_nodes' => [[
                'quest_id' => 'broken-quest',
                'chapter_id' => 'chapter-one',
                'scene_id' => 'scene-one',
                'status' => 'active',
                'unlocks_after' => [],
              ]],
            ],
            'asset_references' => [],
            'contacts' => [],
          ],
        ]];
      }

      protected function loadCampaignStorylineContactItems(int $campaign_id): array {
        return [];
      }
    };

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Storyline management contract failed validation for storyline broken-storyline');

    $service->buildQuestManagementTree(70, [[
      'quest_id' => 'broken-quest_70_abc',
      'quest_key' => 'broken-quest',
      'source_template_id' => 'broken-quest',
      'quest_name' => 'Broken Quest',
      'title' => 'Broken Quest',
      'status' => 'active',
      'current_phase' => 1,
      'generated_objectives' => [],
      'objective_states' => [],
      'generated_rewards' => [],
      'quest_data' => [],
      'location_id' => 'tavern_entrance',
      'storyline' => [
        'storyline_id' => 'broken-storyline',
        'chapter_id' => 'chapter-one',
        'scene_id' => 'scene-one',
      ],
    ]], [], 'tavern_entrance');
  }

  /**
   * Verifies quest summary payloads are emitted in the canonical schema shape.
   */
  public function testBuildQuestSummaryPayloadReturnsCanonicalEnvelope(): void {
    $payload = $this->service->buildQuestSummaryPayload('tavern_entrance', [], [[
      'quest_id' => 'ltba-enter-the-vault_65_123',
      'source_template_id' => 'ltba-enter-the-vault',
      'quest_name' => 'Enter the Vault',
      'status' => 'offered',
      'generated_objectives' => [
        [
          'phase' => 1,
          'objectives' => [
            [
              'objective_id' => 'reach_vault',
              'type' => 'explore',
              'description' => 'Reach the vault entrance.',
              'completed' => FALSE,
              'location' => 'Vault Entrance',
              'discovered' => FALSE,
              'completion_criteria' => [
                'kind' => 'flag',
                'metric' => 'discovered',
                'description' => 'Discover the required location.',
                'required_value' => TRUE,
              ],
            ],
          ],
        ],
      ],
      'generated_rewards' => ['xp' => 50],
      'quest_data' => ['difficulty' => 'moderate'],
      'location_id' => 'tavern_entrance',
    ]], [[
      'quest_id' => 'tok-find-the-missing-teacher_65_456',
      'source_template_id' => 'tok-find-the-missing-teacher',
      'quest_name' => 'Find the Missing Teacher',
      'status' => 'lead',
      'generated_objectives' => [],
      'generated_rewards' => ['xp' => 25],
      'quest_data' => ['difficulty' => 'moderate'],
      'location_id' => 'tavern_entrance',
    ]]);

    $this->assertSame(QuestGeneratorService::QUEST_SUMMARY_SCHEMA_VERSION, $payload['schema_version']);
    $this->assertSame('tavern_entrance', $payload['location_id']);
    $this->assertCount(0, $payload['active']);
    $this->assertCount(1, $payload['offers']);
    $this->assertCount(1, $payload['leads']);
    $this->assertSame([], $payload['management_tree']);
    $this->assertSame(0, $payload['counts']['active']);
    $this->assertSame(1, $payload['counts']['offers']);
    $this->assertSame(1, $payload['counts']['leads']);
    $this->assertArrayNotHasKey('available', $payload);
    $this->assertSame('Enter the Vault', $payload['offers'][0]['quest_name']);
    $this->assertSame('Find the Missing Teacher', $payload['leads'][0]['quest_name']);
  }

  /**
   * Verifies the management tree nests NPCs, storylines, quests, and objectives.
   */
  public function testBuildQuestManagementTreeNestsStorylinesUnderQuestGivers(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $state_validation = $this->createMock(StateValidationService::class);
    $state_validation->method('validateQuestSummary')->willReturn([
      'valid' => TRUE,
      'errors' => [],
    ]);

    $service = new class(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(NumberGenerationService::class),
      $state_validation
    ) extends QuestGeneratorService {
      protected function loadCampaignStorylineRows(int $campaign_id): array {
        return [[
          'storyline_id' => 'threshold-of-knowledge',
          'template_id' => 'threshold-of-knowledge',
          'name' => 'Threshold of Knowledge',
          'status' => 'lead',
          'priority' => 100,
          'current_chapter_id' => 'magaambya-campus',
          'current_scene_id' => 'missing-teacher',
          'storyline_data' => [
            'storyline_type' => 'questline',
            'metadata' => [
              'goal' => 'Find the missing teacher.',
              'generated_outline' => [
                'bootstrap_handoff' => [
                  'lead_text' => 'Speak to Venture-Captain Nhyira near the Magaambya gate.',
                ],
              ],
            ],
            'chapters' => [[
              'chapter_id' => 'magaambya-campus',
              'name' => 'Magaambya Campus',
              'summary' => 'Search the campus for clues.',
              'quest_ids' => [],
              'asset_references' => [],
              'gates' => [],
              'scenes' => [[
                'scene_id' => 'missing-teacher',
                'name' => 'Missing Teacher',
                'summary' => 'Determine where the teacher vanished.',
                'quest_ids' => ['tok-find-the-missing-teacher'],
                'asset_references' => [],
                'gates' => [],
              ]],
            ]],
            'linked_quests' => [
              'tok-find-the-missing-teacher' => [
                'quest_id' => 'tok-find-the-missing-teacher',
                'chapter_id' => 'magaambya-campus',
                'scene_id' => 'missing-teacher',
                'status' => 'available',
              ],
            ],
            'questline' => [
              'primary_quest_id' => 'tok-find-the-missing-teacher',
              'ordered_quest_ids' => ['tok-find-the-missing-teacher'],
              'quest_nodes' => [[
                'quest_id' => 'tok-find-the-missing-teacher',
                'chapter_id' => 'magaambya-campus',
                'scene_id' => 'missing-teacher',
                'status' => 'available',
                'unlocks_after' => [],
                'unlocks_to' => [],
                'unlock_condition' => 'initially_available',
              ]],
            ],
            'asset_references' => [],
            'contacts' => [],
          ],
        ]];
      }

      protected function loadCampaignStorylineContactItems(int $campaign_id): array {
        return [[
          'storyline_id' => 'threshold-of-knowledge',
          'name' => 'Threshold of Knowledge',
          'lead_location' => [
            'id' => 'magaambya_gate',
            'label' => 'Magaambya Gate',
          ],
          'broker' => [
            'entity_id' => 'npc_tavern_keeper',
            'display_name' => 'Eldric',
          ],
          'quest_giver' => [
            'entity_id' => 'venture-captain-nhyira',
            'display_name' => 'Venture-Captain Nhyira',
            'notes' => 'She keeps the campus watch roster.',
          ],
        ]];
      }
    };

    $tree = $service->buildQuestManagementTree(70, [[
      'quest_id' => 'tok-find-the-missing-teacher_70_abc',
      'quest_key' => 'tok-find-the-missing-teacher',
      'source_template_id' => 'tok-find-the-missing-teacher',
      'quest_name' => 'Find the Missing Teacher',
      'title' => 'Find the Missing Teacher',
      'status' => 'active',
      'current_phase' => 1,
      'generated_objectives' => [[
        'phase' => 1,
        'objectives' => [[
          'objective_id' => 'identify_last_known_location',
          'type' => 'investigate',
          'description' => 'Determine where the teacher disappeared.',
          'completed' => FALSE,
          'target' => 'Magaambya Campus',
          'children' => [[
            'objective_id' => 'question_the_gate_wardens',
            'type' => 'investigate',
            'description' => 'Question the gate wardens.',
            'completed' => FALSE,
            'target' => 'Gate Wardens',
          ]],
        ]],
      ]],
      'objective_states' => [[
        'phase' => 1,
        'objectives' => [[
          'objective_id' => 'identify_last_known_location',
          'type' => 'investigate',
          'description' => 'Determine where the teacher disappeared.',
          'completed' => FALSE,
          'target' => 'Magaambya Campus',
          'children' => [[
            'objective_id' => 'question_the_gate_wardens',
            'type' => 'investigate',
            'description' => 'Question the gate wardens.',
            'completed' => FALSE,
            'target' => 'Gate Wardens',
          ]],
        ]],
      ]],
      'generated_rewards' => ['xp' => 40],
      'quest_data' => ['difficulty' => 'moderate'],
      'location_id' => 'magaambya_gate',
      'storyline' => [
        'storyline_id' => 'threshold-of-knowledge',
        'chapter_id' => 'magaambya-campus',
        'scene_id' => 'missing-teacher',
      ],
    ]], [], 'magaambya_gate');

    $quest_giver_branch = array_values(array_filter($tree, static fn(array $npc): bool => ($npc['npc_id'] ?? '') === 'venture-captain-nhyira'));
    $this->assertCount(1, $quest_giver_branch);
    $this->assertSame('Venture-Captain Nhyira', $quest_giver_branch[0]['npc_name']);
    $this->assertSame('Magaambya Gate', $quest_giver_branch[0]['location']['label']);
    $this->assertCount(1, $quest_giver_branch[0]['storylines']);
    $this->assertSame('Threshold of Knowledge', $quest_giver_branch[0]['storylines'][0]['name']);
    $this->assertCount(1, $quest_giver_branch[0]['storylines'][0]['quests']);
    $this->assertSame('Find the Missing Teacher', $quest_giver_branch[0]['storylines'][0]['quests'][0]['quest_name']);
    $this->assertCount(1, $quest_giver_branch[0]['storylines'][0]['quests'][0]['objectives']);
    $this->assertSame(
      'Determine where the teacher disappeared.',
      $quest_giver_branch[0]['storylines'][0]['quests'][0]['objectives'][0]['description']
    );
    $this->assertCount(1, $quest_giver_branch[0]['storylines'][0]['quests'][0]['objectives'][0]['children']);
    $this->assertSame(
      'Complete all nested objectives.',
      $quest_giver_branch[0]['storylines'][0]['quests'][0]['objectives'][0]['completion_criteria']['description']
    );
  }

  /**
   * Verifies storyline quest journal guidance prefers the authored scene over a
   * generic quest-row location id.
   */
  public function testBuildQuestManagementTreePrefersStorylineSceneOverGenericLocationId(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $state_validation = $this->createMock(StateValidationService::class);
    $state_validation->method('validateQuestSummary')->willReturn([
      'valid' => TRUE,
      'errors' => [],
    ]);

    $service = new class(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(NumberGenerationService::class),
      $state_validation
    ) extends QuestGeneratorService {
      protected function loadCampaignStorylineRows(int $campaign_id): array {
        return [[
          'storyline_id' => 'torment-and-legacy',
          'template_id' => 'torment-and-legacy',
          'name' => 'Torment and Legacy',
          'status' => 'active',
          'priority' => 100,
          'current_chapter_id' => 'onboarding',
          'current_scene_id' => 'briefing',
          'storyline_data' => [
            'storyline_type' => 'questline',
            'metadata' => [
              'goal' => 'Accept the mission.',
            ],
            'chapters' => [[
              'chapter_id' => 'onboarding',
              'name' => 'Onboarding',
              'summary' => 'Introduce the opening mission.',
              'quest_ids' => [],
              'asset_references' => [],
              'gates' => [],
              'scenes' => [[
                'scene_id' => 'briefing',
                'name' => 'Adventure Briefing',
                'summary' => 'Meet Venture-Captain Celia Arvanxi for the mission briefing.',
                'quest_ids' => ['tal-accept-the-mission'],
                'asset_references' => [],
                'gates' => [],
              ]],
            ]],
            'linked_quests' => [
              'tal-accept-the-mission' => [
                'quest_id' => 'tal-accept-the-mission',
                'chapter_id' => 'onboarding',
                'scene_id' => 'briefing',
                'status' => 'active',
              ],
            ],
            'questline' => [
              'primary_quest_id' => 'tal-accept-the-mission',
              'ordered_quest_ids' => ['tal-accept-the-mission'],
              'quest_nodes' => [[
                'quest_id' => 'tal-accept-the-mission',
                'chapter_id' => 'onboarding',
                'scene_id' => 'briefing',
                'status' => 'active',
                'unlocks_after' => [],
                'unlocks_to' => [],
                'unlock_condition' => 'initially_available',
              ]],
            ],
            'asset_references' => [],
            'contacts' => [[
              'contact_id' => 'tal-mission-handler-contact',
              'entity_type' => 'npc_template',
              'entity_id' => 'tal-mission-handler',
              'role' => 'quest_giver',
              'display_name' => 'Venture-Captain Celia Arvanxi',
              'relationship_state' => [
                'chapter_id' => 'onboarding',
                'scene_id' => 'briefing',
              ],
            ]],
          ],
        ]];
      }

      protected function loadCampaignStorylineContactItems(int $campaign_id): array {
        return [[
          'storyline_id' => 'torment-and-legacy',
          'name' => 'Torment and Legacy',
          'lead_location' => [
            'id' => 'tavern_entrance',
            'label' => 'Tavern Entrance',
          ],
          'quest_giver' => [
            'entity_id' => 'tal-mission-handler',
            'display_name' => 'Venture-Captain Celia Arvanxi',
            'notes' => 'Celia briefs the party on the opening mission.',
          ],
        ]];
      }
    };

    $tree = $service->buildQuestManagementTree(82, [[
      'quest_id' => 'tal-accept-the-mission_82_314',
      'quest_key' => 'tal-accept-the-mission',
      'source_template_id' => 'tal-accept-the-mission',
      'quest_name' => 'Accept the Mission',
      'title' => 'Accept the Mission',
      'status' => 'active',
      'current_phase' => 1,
      'generated_objectives' => [[
        'phase' => 1,
        'objectives' => [[
          'objective_id' => 'accept_demo_briefing',
          'type' => 'interact',
          'description' => 'Hear the mission briefing and commit the party to the adventure.',
          'completed' => FALSE,
          'target' => 'tal-mission-handler',
          'completion_criteria' => [
            'kind' => 'flag',
            'metric' => 'completed',
            'description' => 'Mark this objective complete.',
            'required_value' => TRUE,
          ],
        ]],
      ]],
      'objective_states' => [[
        'phase' => 1,
        'objectives' => [[
          'objective_id' => 'accept_demo_briefing',
          'type' => 'interact',
          'description' => 'Hear the mission briefing and commit the party to the adventure.',
          'completed' => FALSE,
          'target' => 'tal-mission-handler',
          'completion_criteria' => [
            'kind' => 'flag',
            'metric' => 'completed',
            'description' => 'Mark this objective complete.',
            'required_value' => TRUE,
          ],
        ]],
      ]],
      'generated_rewards' => [],
      'quest_data' => [],
      'location_id' => 'tavern_entrance',
      'storyline' => [
        'storyline_id' => 'torment-and-legacy',
        'chapter_id' => 'onboarding',
        'scene_id' => 'briefing',
      ],
    ]], [], 'tavern_entrance');

    $quest_giver_branch = array_values(array_filter($tree, static fn(array $npc): bool => ($npc['npc_id'] ?? '') === 'tal-mission-handler'));
    $this->assertCount(1, $quest_giver_branch);
    $quest = $quest_giver_branch[0]['storylines'][0]['quests'][0] ?? [];
    $objective = $quest['objectives'][0] ?? [];

    $this->assertSame('Adventure Briefing', $quest['location']['label'] ?? NULL);
    $this->assertSame('Adventure Briefing', $objective['location']['label'] ?? NULL);
    $this->assertSame(
      'Speak with or interact with Venture-Captain Celia Arvanxi at Adventure Briefing.',
      $objective['next_step'] ?? NULL
    );
  }

  /**
   * Verifies interact objectives fail closed when a storyline contact has no
   * canonical location anchor instead of inheriting a generic fallback location.
   */
  public function testBuildQuestManagementObjectivesDoNotInventContactLocationFromFallback(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $state_validation = $this->createMock(StateValidationService::class);
    $state_validation->method('validateQuestSummary')->willReturn([
      'valid' => TRUE,
      'errors' => [],
    ]);

    $service = new class(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(NumberGenerationService::class),
      $state_validation
    ) extends QuestGeneratorService {
      public function exposedBuildQuestManagementObjectives(
        array $quest,
        array $fallback_location,
        bool $blocked,
        ?string $current_location_id,
        array $storyline_contacts
      ): array {
        return $this->buildQuestManagementObjectives($quest, $fallback_location, $blocked, $current_location_id, $storyline_contacts);
      }
    };

    $objectives = $service->exposedBuildQuestManagementObjectives([
      'quest_id' => 'mystery_briefing_82_314',
      'quest_name' => 'Mystery Briefing',
      'status' => 'active',
      'current_phase' => 1,
      'generated_objectives' => [],
      'objective_states' => [[
        'phase' => 1,
        'objectives' => [[
          'objective_id' => 'meet_mystery_contact',
          'type' => 'interact',
          'description' => 'Meet the hidden contact.',
          'target' => 'hidden-contact',
          'completed' => FALSE,
        ]],
      ]],
    ], [
      'id' => 'tavern_entrance',
      'label' => 'Tavern Entrance',
    ], FALSE, 'tavern_entrance', [[
      'entity_id' => 'hidden-contact',
      'display_name' => 'Hidden Contact',
      'relationship_state' => [],
    ]]);

    $this->assertCount(1, $objectives);
    $this->assertNull($objectives[0]['location']['id']);
    $this->assertNull($objectives[0]['location']['label']);
    $this->assertSame('Speak with or interact with Hidden Contact.', $objectives[0]['next_step']);
  }

  /**
   * Verifies numeric giver_npc_id values resolve to campaign character names.
   */
  public function testBuildQuestManagementTreeResolvesStandaloneQuestgiverIdsToNames(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $state_validation = $this->createMock(StateValidationService::class);
    $state_validation->method('validateQuestSummary')->willReturn([
      'valid' => TRUE,
      'errors' => [],
    ]);

    $service = new class(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(NumberGenerationService::class),
      $state_validation
    ) extends QuestGeneratorService {
      protected function loadCampaignStorylineRows(int $campaign_id): array {
        return [];
      }

      protected function loadCampaignStorylineContactItems(int $campaign_id): array {
        return [];
      }

      protected function loadCampaignCharacterReference(int $campaign_id, string $npc_reference): array {
        if ($campaign_id === 70 && $npc_reference === '264') {
          return [
            'id' => 264,
            'instance_id' => 'npc_tavern_keeper',
            'name' => 'Eldric',
            'location_type' => 'room',
            'location_ref' => 'tavern_entrance',
            'location_label' => 'Tavern Entrance',
          ];
        }

        return [];
      }
    };

    $tree = $service->buildQuestManagementTree(70, [], [[
      'quest_id' => 'tavern_storyline_leads_70_abc',
      'quest_key' => 'tavern_storyline_leads',
      'source_template_id' => 'tavern_storyline_leads',
      'quest_name' => 'Gather Storyline Leads in the Tavern',
      'title' => 'Gather Storyline Leads in the Tavern',
      'status' => 'available',
      'current_phase' => 1,
      'generated_objectives' => [[
        'phase' => 1,
        'objectives' => [[
          'objective_id' => 'speak_with_eldric',
          'type' => 'interact',
          'description' => 'Speak with Eldric to gather leads.',
          'completed' => FALSE,
          'target' => 'Eldric',
          'completion_criteria' => [
            'kind' => 'flag',
            'metric' => 'completed',
            'description' => 'Mark this objective complete.',
            'required_value' => TRUE,
          ],
        ]],
      ]],
      'objective_states' => [[
        'phase' => 1,
        'objectives' => [[
          'objective_id' => 'speak_with_eldric',
          'type' => 'interact',
          'description' => 'Speak with Eldric to gather leads.',
          'completed' => FALSE,
          'target' => 'Eldric',
          'completion_criteria' => [
            'kind' => 'flag',
            'metric' => 'completed',
            'description' => 'Mark this objective complete.',
            'required_value' => TRUE,
          ],
        ]],
      ]],
      'generated_rewards' => [],
      'quest_data' => [
        'variables' => [
          'giver_npc_id' => 264,
        ],
      ],
      'location_id' => 'tavern_entrance',
      'storyline' => [
        'storyline_id' => NULL,
        'chapter_id' => NULL,
        'scene_id' => NULL,
      ],
    ]], 'tavern_entrance');

    $eldric_branch = array_values(array_filter($tree, static fn(array $npc): bool => ($npc['npc_id'] ?? '') === '264'));
    $this->assertCount(1, $eldric_branch);
    $this->assertSame('Eldric', $eldric_branch[0]['npc_name']);
    $this->assertSame('Tavern Entrance', $eldric_branch[0]['location']['label']);
    $this->assertCount(1, $eldric_branch[0]['storylines']);
    $this->assertSame('Standalone Quests', $eldric_branch[0]['storylines'][0]['name']);
  }

  /**
   * Verifies undiscovered future storyline quest nodes stay hidden.
   */
  public function testBuildQuestManagementTreeHidesUndiscoveredStorylineQuestPlaceholders(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $state_validation = $this->createMock(StateValidationService::class);
    $state_validation->method('validateQuestSummary')->willReturn([
      'valid' => TRUE,
      'errors' => [],
    ]);

    $service = new class(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(NumberGenerationService::class),
      $state_validation
    ) extends QuestGeneratorService {
      protected function loadCampaignStorylineRows(int $campaign_id): array {
        return [[
          'storyline_id' => 'threshold-of-knowledge',
          'template_id' => 'threshold-of-knowledge',
          'name' => 'Threshold of Knowledge',
          'status' => 'active',
          'priority' => 100,
          'current_chapter_id' => 'magaambya-campus',
          'current_scene_id' => 'missing-teacher',
          'storyline_data' => [
            'storyline_type' => 'questline',
            'metadata' => [
              'goal' => 'Find the missing teacher.',
            ],
            'unlocked_chapter_ids' => ['magaambya-campus'],
            'unlocked_scene_ids' => ['missing-teacher'],
            'chapters' => [[
              'chapter_id' => 'magaambya-campus',
              'name' => 'Magaambya Campus',
              'summary' => 'Search the campus for clues.',
              'scenes' => [[
                'scene_id' => 'missing-teacher',
                'name' => 'Missing Teacher',
                'summary' => 'Determine where the teacher vanished.',
              ], [
                'scene_id' => 'forbidden-wing',
                'name' => 'Forbidden Wing',
                'summary' => 'Search the forbidden wing for evidence.',
              ]],
            ]],
            'linked_quests' => [
              'tok-find-the-missing-teacher' => [
                'quest_id' => 'tok-find-the-missing-teacher',
                'chapter_id' => 'magaambya-campus',
                'scene_id' => 'missing-teacher',
                'status' => 'active',
              ],
              'tok-search-the-wing' => [
                'quest_id' => 'tok-search-the-wing',
                'chapter_id' => 'magaambya-campus',
                'scene_id' => 'forbidden-wing',
                'status' => 'available',
              ],
            ],
            'questline' => [
              'primary_quest_id' => 'tok-find-the-missing-teacher',
              'ordered_quest_ids' => ['tok-find-the-missing-teacher', 'tok-search-the-wing'],
              'quest_nodes' => [[
                'quest_id' => 'tok-find-the-missing-teacher',
                'chapter_id' => 'magaambya-campus',
                'scene_id' => 'missing-teacher',
                'status' => 'active',
                'unlocks_after' => [],
              ], [
                'quest_id' => 'tok-search-the-wing',
                'chapter_id' => 'magaambya-campus',
                'scene_id' => 'forbidden-wing',
                'status' => 'available',
                'unlocks_after' => ['tok-find-the-missing-teacher'],
              ]],
            ],
            'asset_references' => [],
            'contacts' => [],
          ],
        ]];
      }

      protected function loadCampaignStorylineContactItems(int $campaign_id): array {
        return [[
          'storyline_id' => 'threshold-of-knowledge',
          'quest_giver' => [
            'entity_id' => 'venture-captain-nhyira',
            'display_name' => 'Venture-Captain Nhyira',
          ],
          'lead_location' => [
            'id' => 'magaambya_gate',
            'label' => 'Magaambya Gate',
          ],
        ]];
      }
    };

    $tree = $service->buildQuestManagementTree(70, [[
      'quest_id' => 'tok-find-the-missing-teacher_70_abc',
      'quest_key' => 'tok-find-the-missing-teacher',
      'source_template_id' => 'tok-find-the-missing-teacher',
      'quest_name' => 'Find the Missing Teacher',
      'title' => 'Find the Missing Teacher',
      'status' => 'active',
      'current_phase' => 1,
      'generated_objectives' => [[
        'phase' => 1,
        'objectives' => [[
          'objective_id' => 'identify_last_known_location',
          'type' => 'investigate',
          'description' => 'Determine where the teacher disappeared.',
          'completed' => FALSE,
          'revealed' => TRUE,
        ]],
      ]],
      'objective_states' => [[
        'phase' => 1,
        'objectives' => [[
          'objective_id' => 'identify_last_known_location',
          'type' => 'investigate',
          'description' => 'Determine where the teacher disappeared.',
          'completed' => FALSE,
          'revealed' => TRUE,
        ]],
      ]],
      'generated_rewards' => [],
      'quest_data' => [],
      'location_id' => 'magaambya_gate',
      'storyline' => [
        'storyline_id' => 'threshold-of-knowledge',
        'chapter_id' => 'magaambya-campus',
        'scene_id' => 'missing-teacher',
      ],
    ]], [], 'magaambya_gate');

    $quests = $tree[0]['storylines'][0]['quests'] ?? [];
    $this->assertCount(1, $quests);
    $this->assertSame('Find the Missing Teacher', $quests[0]['quest_name']);
  }

  /**
   * Verifies unrevealed future objectives stay hidden while completed discovered
   * objectives remain visible.
   */
  public function testBuildQuestManagementTreeShowsOnlyRevealedObjectives(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $state_validation = $this->createMock(StateValidationService::class);
    $state_validation->method('validateQuestSummary')->willReturn([
      'valid' => TRUE,
      'errors' => [],
    ]);

    $service = new class(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(NumberGenerationService::class),
      $state_validation
    ) extends QuestGeneratorService {
      protected function loadCampaignStorylineRows(int $campaign_id): array {
        return [];
      }

      protected function loadCampaignStorylineContactItems(int $campaign_id): array {
        return [];
      }

      protected function loadCampaignCharacterReference(int $campaign_id, string $npc_reference): array {
        return [];
      }
    };

    $tree = $service->buildQuestManagementTree(70, [[
      'quest_id' => 'library_mystery_70_abc',
      'quest_key' => 'library_mystery',
      'source_template_id' => 'library_mystery',
      'quest_name' => 'Library Mystery',
      'title' => 'Library Mystery',
      'status' => 'active',
      'current_phase' => 1,
      'generated_objectives' => [],
      'objective_states' => [[
        'phase' => 1,
        'objectives' => [[
          'objective_id' => 'question_the_warden',
          'type' => 'investigate',
          'description' => 'Question the library warden.',
          'completed' => TRUE,
          'revealed' => TRUE,
        ]],
      ], [
        'phase' => 2,
        'objectives' => [[
          'objective_id' => 'search_the_hidden_archive',
          'type' => 'explore',
          'description' => 'Search the hidden archive.',
          'completed' => FALSE,
          'revealed' => FALSE,
        ]],
      ]],
      'generated_rewards' => [],
      'quest_data' => [
        'variables' => [
          'giver_npc_id' => 'quest_giver',
          'giver_name' => 'Archivist Selene',
        ],
      ],
      'location_id' => 'grandmas_house_library',
      'storyline' => [
        'storyline_id' => NULL,
        'chapter_id' => NULL,
        'scene_id' => NULL,
      ],
    ]], [], 'grandmas_house_library');

    $objectives = $tree[0]['storylines'][0]['quests'][0]['objectives'] ?? [];
    $this->assertCount(1, $objectives);
    $this->assertSame('question_the_warden', $objectives[0]['objective_id']);
    $this->assertTrue($objectives[0]['completed']);
  }

  /**
   * Verifies NPC quest-giver policies deterministically allow and deny template
   * issuance.
   */
  public function testQuestGiverPoliciesGateTemplatesAndStorylines(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $state_validation = $this->createMock(StateValidationService::class);
    $state_validation->method('validateQuestSummary')->willReturn([
      'valid' => TRUE,
      'errors' => [],
    ]);
    $state_validation->method('validateNpcQuestGiverPolicies')->willReturn([
      'valid' => TRUE,
      'errors' => [],
    ]);

    $service = new class(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(NumberGenerationService::class),
      $state_validation
    ) extends QuestGeneratorService {
      public function isAllowed(int $campaign_id, string $template_id, array $context): bool {
        return $this->isQuestTemplateAllowedForGiver($campaign_id, $template_id, $context);
      }

      public function policies(): array {
        return $this->getQuestGiverPolicies();
      }
    };

    $this->assertNotSame([], $service->policies());
    $this->assertTrue($service->isAllowed(70, 'tok-find-the-missing-teacher', [
      'giver_npc_id' => 'npc_tavern_keeper',
      'storyline_template_id' => 'threshold-of-knowledge',
    ]));
    $this->assertFalse($service->isAllowed(70, 'collect_spellbooks', [
      'giver_npc_id' => 'npc_tavern_keeper',
    ]));
    $this->assertFalse($service->isAllowed(70, 'tok-find-the-missing-teacher', [
      'giver_npc_id' => 'scholar_npc',
      'storyline_template_id' => 'threshold-of-knowledge',
    ]));
    $this->assertTrue($service->isAllowed(70, 'collect_spellbooks', [
      'giver_npc_id' => 'scholar_npc',
    ]));
    $this->assertTrue($service->isAllowed(70, 'collect_spellbooks', [
      'giver_npc_id' => 'npc_scholar_npc',
    ]));
  }

  /**
   * Verifies room quest-association hints can hydrate missing item variables.
   */
  public function testBuildVariablesHydratesItemNameFromQuestAssociationHints(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $state_validation = $this->createMock(StateValidationService::class);
    $state_validation->method('validateQuestSummary')->willReturn([
      'valid' => TRUE,
      'errors' => [],
    ]);

    $service = new class(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(NumberGenerationService::class),
      $state_validation
    ) extends QuestGeneratorService {
      public function exposedBuildVariables(array $template, array $context, int $campaign_id = 0): array {
        return $this->buildVariables($template, $context, $campaign_id);
      }

      public function exposedResolveVariables(string $text, array $variables): string {
        return $this->resolveVariables($text, $variables);
      }

      protected function loadQuestAssociationContextHints(int $campaign_id, string $location_id, string $template_id, array $template = []): array {
        return $campaign_id === 85 && $location_id === 'tavern_entrance' && $template_id === 'collect_spellbooks'
          ? ['item_name' => 'Spellbooks']
          : [];
      }
    };

    $variables = $service->exposedBuildVariables([
      'template_id' => 'collect_spellbooks',
      'name' => 'Collect Lost {item_name}',
    ], [
      'location' => 'tavern_entrance',
    ], 85);

    $this->assertSame('Spellbooks', $variables['item_name']);
    $this->assertSame('Collect Lost Spellbooks', $service->exposedResolveVariables('Collect Lost {item_name}', $variables));
  }

  /**
   * Verifies collect objectives do not inherit the quest giver room as fact.
   */
  public function testCollectObjectivesDoNotInheritFallbackQuestLocation(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $state_validation = $this->createMock(StateValidationService::class);
    $state_validation->method('validateQuestSummary')->willReturn([
      'valid' => TRUE,
      'errors' => [],
    ]);

    $service = new class(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(NumberGenerationService::class),
      $state_validation
    ) extends QuestGeneratorService {
      public function exposedBuildQuestManagementObjectives(
        array $quest,
        array $fallback_location,
        bool $blocked,
        ?string $current_location_id
      ): array {
        return $this->buildQuestManagementObjectives($quest, $fallback_location, $blocked, $current_location_id);
      }
    };

    $objectives = $service->exposedBuildQuestManagementObjectives([
      'quest_id' => 'collect_spellbooks_70_abc',
      'quest_name' => 'Recover Lost Spellbooks',
      'status' => 'active',
      'current_phase' => 2,
      'generated_objectives' => [],
      'objective_states' => [[
        'phase' => 1,
        'objectives' => [[
          'objective_id' => 'collect_books',
          'type' => 'collect',
          'description' => 'Find and collect spellbooks',
          'item' => 'spellbooks',
          'current' => 0,
          'target_count' => 3,
          'completed' => FALSE,
        ]],
      ], [
        'phase' => 2,
        'objectives' => [[
          'objective_id' => 'return_books',
          'type' => 'interact',
          'description' => 'Return the books to Marta',
          'target' => 'scholar_npc',
          'completed' => FALSE,
          'revealed' => TRUE,
        ]],
      ]],
    ], [
      'id' => 'tavern_entrance',
      'label' => 'Tavern Entrance',
    ], FALSE, 'tavern_entrance');

    $this->assertCount(2, $objectives);
    $this->assertNull($objectives[0]['location']['id']);
    $this->assertNull($objectives[0]['location']['label']);
    $this->assertSame('unclear', $objectives[0]['access']['sort_bucket']);
    $this->assertSame('tavern_entrance', $objectives[1]['location']['id']);
    $this->assertSame('current', $objectives[1]['access']['sort_bucket']);
  }

}
