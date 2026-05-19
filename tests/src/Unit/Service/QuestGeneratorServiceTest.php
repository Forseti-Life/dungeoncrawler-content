<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\NumberGenerationService;
use Drupal\dungeoncrawler_content\Service\QuestGeneratorService;
use Drupal\dungeoncrawler_content\Service\StateValidationService;
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
   * Verifies quest summary payloads are emitted in the canonical schema shape.
   */
  public function testBuildQuestSummaryPayloadReturnsCanonicalEnvelope(): void {
    $payload = $this->service->buildQuestSummaryPayload('tavern_entrance', [], [[
      'quest_id' => 'ltba-enter-the-vault_65_123',
      'source_template_id' => 'ltba-enter-the-vault',
      'quest_name' => 'Enter the Vault',
      'status' => 'available',
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
    ]]);

    $this->assertSame(QuestGeneratorService::QUEST_SUMMARY_SCHEMA_VERSION, $payload['schema_version']);
    $this->assertSame('tavern_entrance', $payload['location_id']);
    $this->assertCount(1, $payload['available']);
    $this->assertSame([], $payload['management_tree']);
    $this->assertSame(0, $payload['counts']['active']);
    $this->assertSame(1, $payload['counts']['available']);
    $this->assertSame('Enter the Vault', $payload['available'][0]['quest_name']);
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
          'status' => 'available',
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
      'current_phase' => 1,
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
