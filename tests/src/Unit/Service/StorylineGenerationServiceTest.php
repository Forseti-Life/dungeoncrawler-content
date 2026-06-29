<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\ai_conversation\Service\AIApiService;
use Drupal\dungeoncrawler_content\Service\CampaignStateService;
use Drupal\dungeoncrawler_content\Service\QuestTrackerService;
use Drupal\dungeoncrawler_content\Service\StateValidationService;
use Drupal\dungeoncrawler_content\Service\StorylineGenerationService;
use Drupal\dungeoncrawler_content\Service\StorylineManagerService;
use Drupal\dungeoncrawler_content\Service\StorylineRealizationService;
use Drupal\dungeoncrawler_content\Service\TreasureByLevelService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Covers generated storyline package normalization.
 *
 * @group dungeoncrawler_content
 * @group storyline
 */
class StorylineGenerationServiceTest extends UnitTestCase {

  /**
   * Verifies fallback generation creates a full three-dungeon boss arc.
   */
  public function testFallbackGenerationCreatesThreeBossDungeonsWithFiveRoomsEach(): void {
    $campaign_state = $this->createMock(CampaignStateService::class);
    $campaign_state->method('getState')->willReturn([
      'current_room_id' => 'tavern_entrance',
      'characters' => [
        ['level' => 2],
        ['level' => 2],
        ['level' => 3],
        ['level' => 3],
      ],
    ]);

    $storyline_manager = $this->buildStorylineManager($campaign_state);

    $service = new StorylineGenerationService(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      NULL,
      $storyline_manager,
      $campaign_state,
      new TreasureByLevelService(),
      $this->buildUuid()
    );

    $package = $service->generateStorylinePackage(65, [
      'prompt' => 'Stop a relic cult from awakening an ash-crowned tyrant beneath the city',
      'level_range' => '2-5',
      'tone' => 'occult ruin crawl',
    ]);

    $storyline = $package['storyline_definition'] ?? [];
    $outline = $package['campaign_outline'] ?? [];

    $this->assertSame('fallback', $package['generation_source']);
    $this->assertSame('storyline-definition-v1', $storyline['schema_version'] ?? NULL);
    $this->assertCount(3, $outline['dungeons'] ?? []);
    $this->assertCount(15, $package['quest_templates'] ?? []);
    $this->assertCount(3, $storyline['chapters'] ?? []);
    $this->assertCount(4, $outline['progression_connectors'] ?? []);
    $this->assertSame(($outline['dungeons'][0]['entrance_room_id'] ?? NULL), $outline['progression_connectors'][0]['target_room_id'] ?? NULL);
    $this->assertSame(($outline['dungeons'][1]['entrance_room_id'] ?? NULL), $outline['progression_connectors'][1]['target_room_id'] ?? NULL);
    $this->assertSame(($outline['dungeons'][2]['entrance_room_id'] ?? NULL), $outline['progression_connectors'][2]['target_room_id'] ?? NULL);
    $this->assertSame(($outline['dungeons'][2]['boss_room_id'] ?? NULL), $outline['progression_connectors'][3]['target_room_id'] ?? NULL);
    foreach ($outline['dungeons'] as $dungeon) {
      $this->assertCount(5, $dungeon['rooms'] ?? []);
    }
  }

  /**
   * Verifies quest templates align to every generated room scene.
   */
  public function testGeneratedQuestTemplatesAlignToStorylineScenes(): void {
    $campaign_state = $this->createMock(CampaignStateService::class);
    $campaign_state->method('getState')->willReturn([
      'current_room_id' => 'tavern_entrance',
      'characters' => [
        ['level' => 1],
        ['level' => 1],
        ['level' => 1],
        ['level' => 1],
      ],
    ]);

    $storyline_manager = $this->buildStorylineManager($campaign_state);

    $service = new StorylineGenerationService(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      NULL,
      $storyline_manager,
      $campaign_state,
      new TreasureByLevelService(),
      $this->buildUuid()
    );

    $package = $service->generateStorylinePackage(70, [
      'prompt' => 'Break the whispering chain of lieutenants guarding a buried crown',
    ]);

    $template_ids = array_column($package['quest_templates'] ?? [], 'template_id');
    foreach (($package['storyline_definition']['chapters'] ?? []) as $chapter) {
      foreach (($chapter['scenes'] ?? []) as $scene) {
        $this->assertSame(1, count($scene['quest_ids'] ?? []));
        $this->assertContains($scene['quest_ids'][0], $template_ids);
      }
    }
  }

  /**
   * Verifies generated boss-room quest templates target canonical boss ids.
   */
  public function testBuildQuestTemplateUsesBossIdForKillObjectiveTarget(): void {
    $campaign_state = $this->createMock(CampaignStateService::class);
    $campaign_state->method('getState')->willReturn([
      'current_room_id' => 'tavern_entrance',
      'characters' => [['level' => 2]],
    ]);

    $storyline_manager = $this->buildStorylineManager($campaign_state);

    $service = new class(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      NULL,
      $storyline_manager,
      $campaign_state,
      new TreasureByLevelService(),
      $this->buildUuid()
    ) extends StorylineGenerationService {
      public function exposeBuildQuestTemplate(
        string $template_id,
        array $boss,
        string $room_role,
        string $room_style,
        int $level,
        string $room_id,
        string $loot_table_id,
        array $encounter_plan,
        array $treasure_plan
      ): array {
        return $this->buildQuestTemplate($template_id, $boss, $room_role, $room_style, $level, $room_id, $loot_table_id, $encounter_plan, $treasure_plan);
      }
    };

    $template = $service->exposeBuildQuestTemplate(
      'boss-room-template',
      [
        'boss_id' => 'gate-king',
        'name' => 'Gate King',
        'dungeon_name' => 'Throne of Gates',
      ],
      'boss',
      'void ruin',
      4,
      'throne-of-gates-boss-room',
      'boss-loot',
      [],
      []
    );

    $this->assertSame('gate-king', $template['objectives_schema'][0]['objectives'][0]['target'] ?? NULL);
  }

  /**
   * Verifies generated packages fail closed when quest templates violate the
   * strict objective contract.
   */
  public function testNormalizeGeneratedPackageRejectsInvalidQuestObjectiveContract(): void {
    $campaign_state = $this->createMock(CampaignStateService::class);
    $campaign_state->method('getState')->willReturn([
      'current_room_id' => 'tavern_entrance',
      'characters' => [['level' => 2]],
    ]);

    $storyline_manager = $this->buildStorylineManager($campaign_state);

    $service = new class(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      NULL,
      $storyline_manager,
      $campaign_state,
      new TreasureByLevelService(),
      $this->buildUuid()
    ) extends StorylineGenerationService {
      public function exposeNormalizeGeneratedPackage(int $campaign_id, array $request, array $context, array $package, string $generation_source): array {
        return $this->normalizeGeneratedPackage($campaign_id, $request, $context, $package, $generation_source);
      }
    };

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('missing required field completion_criteria');

    $service->exposeNormalizeGeneratedPackage(65, [
      'prompt' => 'Break the relic thieves.',
      'name' => 'Relic Hunt',
      'level_range' => '1-2',
      'tone' => 'occult',
      'theme' => '',
      'source' => 'storyline-generator',
      'template_id' => '',
      'entry_dungeon_id' => '',
      'entry_room_id' => '',
      'first_quest_id' => '',
      'speaker_npc_id' => '',
      'speaker_name' => '',
      'lead_location_id' => '',
      'character_id' => 0,
      'party_id' => 0,
      'tags' => [],
      'activate' => FALSE,
      'is_primary' => FALSE,
      'status' => 'available',
      'priority' => 0,
    ], [
      'party_level' => 2,
      'party_size' => 4,
      'location_id' => 'tavern_entrance',
    ], [
      'storyline' => [
        'name' => 'Relic Hunt',
        'template_id' => 'relic-hunt',
        'synopsis' => 'Track the thieves.',
        'level_range' => '1-2',
        'source' => 'storyline-generator',
        'tags' => ['generated'],
        'metadata' => [
          'goal' => 'Track the thieves.',
          'generated_outline' => [
            'generation_phase' => 'bootstrap',
            'goal' => 'Track the thieves.',
            'entry_dungeon' => [
              'dungeon_id' => 'relic-hunt-entry',
              'name' => 'Relic Hunt Entry',
              'style' => 'occult',
              'entrance_room_id' => 'relic-hunt-entry-room',
              'lead_location_id' => 'tavern_entrance',
              'lead_location_hint' => 'Start at the tavern.',
            ],
            'progression_connectors' => [[
              'connector_id' => 'relic-hunt-bootstrap',
              'source_type' => 'npc',
              'source_id' => 'npc_tavern_keeper',
              'mechanism' => 'npc_direction',
              'from_location_id' => 'tavern_entrance',
              'target_dungeon_id' => 'relic-hunt-entry',
              'target_room_id' => 'relic-hunt-entry-room',
              'narrative' => 'Eldric points the party toward the first lead.',
            ]],
            'bootstrap_handoff' => [
              'speaker_npc_id' => 'npc_tavern_keeper',
              'speaker_name' => 'Eldric',
              'lead_text' => 'Follow the first lead.',
            ],
          ],
        ],
        'asset_references' => [[
          'asset_type' => 'room',
          'asset_id' => 'tavern_entrance',
          'asset_role' => 'lead-location',
          'notes' => 'Start here.',
        ]],
        'contacts' => [[
          'contact_id' => 'eldric-broker',
          'entity_type' => 'campaign_npc',
          'entity_id' => 'npc_tavern_keeper',
          'role' => 'broker',
          'display_name' => 'Eldric',
          'attitude' => 'friendly',
          'notes' => 'Starts the hunt.',
        ]],
        'chapters' => [[
          'chapter_id' => 'relic-hunt-entry',
          'name' => 'Relic Hunt Entry',
          'summary' => 'Begin the hunt.',
          'scenes' => [[
            'scene_id' => 'relic-hunt-entry-room',
            'name' => 'Entry Room',
            'summary' => 'The first clue.',
            'quest_ids' => ['relic-hunt-entry-quest'],
          ]],
        ]],
      ],
      'quest_templates' => [[
        'template_id' => 'relic-hunt-entry-quest',
        'name' => 'Broken Quest',
        'description' => 'Missing completion criteria.',
        'quest_type' => 'main',
        'level_min' => 1,
        'level_max' => 1,
        'tags' => ['generated'],
        'objectives_schema' => [[
          'phase' => 1,
          'objectives' => [[
            'objective_id' => 'broken-objective',
            'type' => 'explore',
            'location' => 'relic-hunt-entry-room',
            'description' => 'This objective is invalid.',
          ]],
        ]],
        'rewards_schema' => ['xp' => 10, 'gold' => 0, 'items' => []],
        'prerequisites' => [],
        'story_impact' => ['generated' => TRUE],
        'estimated_duration_minutes' => 10,
        'version' => '1.0.0',
      ]],
    ], 'ai');
  }

  /**
   * Verifies bootstrap generation only creates the first lead and first quest node.
   */
  public function testBootstrapGenerationCreatesMinimalEntryDungeon(): void {
    $campaign_state = $this->createMock(CampaignStateService::class);
    $campaign_state->method('getState')->willReturn([
      'current_room_id' => 'tavern_entrance',
      'characters' => [
        ['level' => 2],
        ['level' => 2],
      ],
    ]);

    $storyline_manager = $this->buildStorylineManager($campaign_state);

    $service = new StorylineGenerationService(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      NULL,
      $storyline_manager,
      $campaign_state,
      new TreasureByLevelService(),
      $this->buildUuid()
    );

    $package = $service->generateStorylineBootstrapPackage(65, [
      'prompt' => 'I want a storyline about hunting relic thieves',
      'speaker_npc_id' => 'npc_tavern_keeper',
      'speaker_name' => 'Eldric',
      'lead_location_id' => 'tavern_entrance',
    ]);

    $outline = $package['campaign_outline'] ?? [];
    $storyline = $package['storyline_definition'] ?? [];

    $this->assertSame('bootstrap', $outline['generation_phase'] ?? NULL);
    $this->assertArrayHasKey('entry_dungeon', $outline);
    $this->assertCount(1, $package['quest_templates'] ?? []);
    $this->assertCount(1, $storyline['chapters'] ?? []);
    $this->assertCount(1, $storyline['questline']['ordered_quest_ids'] ?? []);
  }

  /**
   * Verifies conversational prompts do not become canonical storyline identity.
   */
  public function testSuggestCanonicalStorylineIdentityAvoidsPromptEcho(): void {
    $campaign_state = $this->createMock(CampaignStateService::class);
    $campaign_state->method('getState')->willReturn([
      'current_room_id' => 'tavern_entrance',
      'characters' => [['level' => 2]],
    ]);

    $storyline_manager = $this->buildStorylineManager($campaign_state);

    $service = new StorylineGenerationService(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      NULL,
      $storyline_manager,
      $campaign_state,
      new TreasureByLevelService(),
      $this->buildUuid()
    );

    $identity = $service->suggestCanonicalStorylineIdentity(
      'Hey Marta, you have any storylines for me?',
      [],
      TRUE
    );

    $this->assertSame('New Storyline Lead', $identity['name']);
    $this->assertMatchesRegularExpression('/^storyline-bootstrap-[a-z0-9]{8}$/', $identity['template_id']);
    $this->assertStringNotContainsString('marta', $identity['template_id']);
  }

  /**
   * Verifies level-range parsing clamps bounds and orders min/max safely.
   */
  public function testParseLevelRangeClampsAndOrdersBounds(): void {
    $campaign_state = $this->createMock(CampaignStateService::class);
    $campaign_state->method('getState')->willReturn([
      'current_room_id' => 'tavern_entrance',
      'characters' => [['level' => 2]],
    ]);

    $storyline_manager = $this->buildStorylineManager($campaign_state);

    $service = new class(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      NULL,
      $storyline_manager,
      $campaign_state,
      new TreasureByLevelService(),
      $this->buildUuid()
    ) extends StorylineGenerationService {
      public function exposeParseLevelRange(string $level_range): array {
        return $this->parseLevelRange($level_range);
      }
    };

    $this->assertSame(['min' => 20, 'max' => 20], $service->exposeParseLevelRange('40-90'));
    $this->assertSame(['min' => 1, 'max' => 1], $service->exposeParseLevelRange('0-0'));
    $this->assertSame(['min' => 7, 'max' => 7], $service->exposeParseLevelRange('7-3'));
  }

  /**
   * Verifies bootstrap handoff activates the storyline and starts the first quest.
   */
  public function testBootstrapCampaignStorylineActivatesInitialQuestHandoff(): void {
    $campaign_state = $this->createMock(CampaignStateService::class);
    $campaign_state->method('getState')->willReturn([
      'current_room_id' => 'tavern_entrance',
      'characters' => [
        ['level' => 2],
      ],
    ]);

    $storyline_manager = $this->createMock(StorylineManagerService::class);
    $relationship_manager = $this->createMock(\Drupal\dungeoncrawler_content\Service\RelationshipManagerService::class);
    $quest_tracker = $this->createMock(QuestTrackerService::class);

    $package = [
      'storyline_definition' => [
        'name' => 'Relic Thief Pursuit',
        'template_id' => 'relic-thief-pursuit',
        'level_range' => '2-3',
        'metadata' => [
          'goal' => 'Hunt the relic thieves.',
        ],
        'questline' => [
          'primary_quest_id' => 'relic-vault-threshold-entry-quest',
        ],
      ],
      'quest_templates' => [
        ['template_id' => 'relic-vault-threshold-entry-quest'],
      ],
      'campaign_outline' => [
        'entry_dungeon' => [
          'dungeon_id' => 'relic-vault-threshold',
          'entrance_room_id' => 'relic-vault-threshold-entry',
        ],
      ],
      'generation_source' => 'fallback',
    ];
    $created_storyline = [
      'storyline_id' => 'relic-thief-pursuit-65',
      'name' => 'Relic Thief Pursuit',
      'status' => 'bootstrapping',
      'storyline_data' => [
        'questline' => [
          'primary_quest_id' => 'relic-vault-threshold-entry-quest',
        ],
      ],
    ];
    $activated_storyline = $created_storyline;
    $activated_storyline['status'] = 'active';
    $initial_quest = [
      'quest_id' => 'relic-vault-threshold-entry-quest-65',
      'quest_name' => 'Find the Hidden Vault',
      'status' => 'available',
    ];

    $storyline_manager->expects($this->once())
      ->method('createCampaignStoryline')
      ->with(65, $package['storyline_definition'], $this->callback(static fn(array $payload): bool => ($payload['character_id'] ?? 0) === 267))
      ->willReturn($created_storyline);
    $storyline_manager->expects($this->once())
      ->method('activateCampaignStoryline')
      ->with(65, 'relic-thief-pursuit-65', FALSE)
      ->willReturn($activated_storyline);

    $relationship_manager->expects($this->once())->method('seedLibraryRelationships')->with(65);
    $relationship_manager->expects($this->once())->method('seedStorylineContacts')->with(65, $activated_storyline);
    $relationship_manager->expects($this->once())->method('refreshCampaignStorylineContacts')->with(65, 'npc_tavern_keeper');

    $quest_tracker->expects($this->once())
      ->method('startQuest')
      ->with(65, 'relic-vault-threshold-entry-quest-65', 267, NULL)
      ->willReturn(TRUE);

    $service = new class(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      NULL,
      $storyline_manager,
      $campaign_state,
      new TreasureByLevelService(),
      $this->buildUuid(),
      $relationship_manager,
      NULL,
      $this->buildStateValidationService(),
      NULL,
      NULL,
      $quest_tracker,
      $package,
      $initial_quest
    ) extends StorylineGenerationService {
      public function __construct(
        Connection $database,
        LoggerChannelFactoryInterface $logger_factory,
        ?AIApiService $ai_api_service,
        StorylineManagerService $storyline_manager,
        CampaignStateService $campaign_state_service,
        TreasureByLevelService $treasure_by_level_service,
        UuidInterface $uuid,
        ?\Drupal\dungeoncrawler_content\Service\RelationshipManagerService $relationship_manager,
        ?\Drupal\dungeoncrawler_content\Service\QuestGeneratorService $quest_generator,
        ?StateValidationService $state_validation_service,
        ?\Drupal\dungeoncrawler_content\Service\NpcSheetGenerationService $npc_sheet_generation_service,
        ?StorylineRealizationService $storyline_realization_service,
        ?QuestTrackerService $quest_tracker,
        private array $package,
        private array $initialQuest
      ) {
        parent::__construct(
          $database,
          $logger_factory,
          $ai_api_service,
          $storyline_manager,
          $campaign_state_service,
          $treasure_by_level_service,
          $uuid,
          $relationship_manager,
          $quest_generator,
          $state_validation_service,
          $npc_sheet_generation_service,
          $storyline_realization_service,
          $quest_tracker
        );
      }

      public function generateStorylineBootstrapPackage(int $campaign_id, array $request): array {
        return $this->package;
      }

      public function persistQuestTemplates(array $templates): array {
        return $templates;
      }

      protected function materializeBootstrapQuest(int $campaign_id, array $storyline, array $request): ?array {
        return $this->initialQuest;
      }

      public function enqueueStorylineExpansion(int $campaign_id, string $storyline_id, array $request, bool $auto_start = TRUE): bool {
        return TRUE;
      }
    };

    $result = $service->bootstrapCampaignStoryline(65, [
      'prompt' => 'I want a storyline about hunting relic thieves',
      'speaker_npc_id' => 'npc_tavern_keeper',
      'speaker_name' => 'Eldric',
      'lead_location_id' => 'tavern_entrance',
      'character_id' => 267,
    ]);

    $this->assertSame('active', $result['storyline']['status'] ?? NULL);
    $this->assertSame('active', $result['initial_quest']['status'] ?? NULL);
    $this->assertTrue($result['expansion_queued']);
  }

  /**
   * Verifies AI bootstrap normalization accepts a storyline_definition wrapper.
   */
  public function testBootstrapGenerationAcceptsStorylineDefinitionWrapperFromAi(): void {
    $campaign_state = $this->createMock(CampaignStateService::class);
    $campaign_state->method('getState')->willReturn([
      'current_room_id' => 'tavern_entrance',
      'characters' => [
        ['level' => 2],
      ],
    ]);

    $storyline_manager = $this->buildStorylineManager($campaign_state);

    $ai_api = $this->createMock(AIApiService::class);
    $ai_api->expects($this->once())
      ->method('invokeModelDirect')
      ->willReturn([
        'response' => json_encode([
          'storyline_definition' => [
            'name' => 'Relic Thief Pursuit',
            'template_id' => 'relic-thief-pursuit',
            'synopsis' => 'Track the thieves to the first hidden vault.',
            'level_range' => '2-3',
            'source' => 'storyline-bootstrap',
            'tags' => ['generated', 'bootstrap'],
            'metadata' => [
              'goal' => 'Hunt the relic thieves.',
              'generated_outline' => [
                'generation_phase' => 'bootstrap',
                'goal' => 'Hunt the relic thieves.',
                'entry_dungeon' => [
                  'dungeon_id' => 'relic-vault-threshold',
                  'name' => 'Relic Vault Threshold',
                  'style' => 'hidden vault',
                  'entrance_room_id' => 'relic-vault-threshold-entry',
                  'lead_location_id' => 'tavern_entrance',
                  'lead_location_hint' => 'The trail starts beneath the tavern cellar.',
                ],
                  'progression_connectors' => [
                    [
                      'connector_id' => 'relic-thief-pursuit-bootstrap-handoff',
                      'source_type' => 'npc',
                      'source_id' => 'npc_tavern_keeper',
                      'mechanism' => 'npc_direction',
                      'from_location_id' => 'tavern_entrance',
                      'target_dungeon_id' => 'relic-vault-threshold',
                      'target_room_id' => 'relic-vault-threshold-entry',
                      'narrative' => 'Eldric points the party toward the first vault threshold.',
                    ],
                  ],
                  'bootstrap_handoff' => [
                    'speaker_npc_id' => 'npc_tavern_keeper',
                    'speaker_name' => 'Eldric',
                    'lead_text' => 'Start with the locked stairs beneath the cellar.',
                  ],
                  'expansion_status' => 'pending',
                ],
              ],
              'asset_references' => [
                [
                  'asset_type' => 'location',
                  'asset_id' => 'tavern_entrance',
                  'asset_role' => 'lead-location',
                  'notes' => 'The current location where the questgiver gives the first lead.',
                ],
                [
                  'asset_type' => 'dungeon',
                  'asset_id' => 'relic-vault-threshold',
                  'asset_role' => 'entry-dungeon',
                  'chapter_id' => 'relic-vault-threshold',
                  'notes' => 'First storyline dungeon stub generated during bootstrap.',
                ],
                [
                  'asset_type' => 'room',
                  'asset_id' => 'relic-vault-threshold-entry',
                  'asset_role' => 'entrance-room',
                  'chapter_id' => 'relic-vault-threshold',
                  'scene_id' => 'relic-vault-threshold-entry',
                  'notes' => 'Initial storyline entrance room.',
                ],
              ],
              'contacts' => [
                [
                  'contact_id' => 'relic-thief-patron',
                  'entity_type' => 'campaign_npc',
                  'entity_id' => 'npc_tavern_keeper',
                  'role' => 'quest_giver',
                  'display_name' => 'Eldric',
                  'attitude' => 'friendly',
                  'availability' => 'available',
                  'notes' => 'Knows where the first clue begins.',
                  'relationship_state' => [
                    'points_to_dungeon_id' => 'relic-vault-threshold',
                    'points_to_room_id' => 'relic-vault-threshold-entry',
                    'mechanism' => 'npc_direction',
                  ],
                  'introduces_to' => [],
                ],
              ],
            'chapters' => [
              [
                'chapter_id' => 'relic-vault-threshold',
                'name' => 'First Lead',
                'scenes' => [
                  [
                    'scene_id' => 'relic-vault-threshold-entry',
                    'name' => 'Vault Entry',
                    'summary' => 'The first hidden door waits under the tavern.',
                    'quest_ids' => ['relic-vault-threshold-entry-quest'],
                  ],
                ],
              ],
            ],
          ],
          'quest_templates' => [
            [
              'template_id' => 'relic-vault-threshold-entry-quest',
              'name' => 'Find the Hidden Vault',
              'summary' => 'Follow Eldric into the cellar and uncover the hidden stairs.',
              'giver_npc_id' => 'npc_tavern_keeper',
              'objectives_schema' => [
                [
                  'phase' => 1,
                  'objectives' => [
                    [
                      'objective_id' => 'reach-vault-entry',
                      'type' => 'travel',
                      'location' => 'relic-vault-threshold-entry',
                      'description' => 'Reach the cellar stairs.',
                      'completion_criteria' => [
                        'kind' => 'flag',
                        'metric' => 'discovered',
                        'required_value' => TRUE,
                        'description' => 'Discover the required location.',
                      ],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ], JSON_UNESCAPED_SLASHES),
      ]);

    $service = new StorylineGenerationService(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      $ai_api,
      $storyline_manager,
      $campaign_state,
      new TreasureByLevelService(),
      $this->buildUuid()
    );

    $package = $service->generateStorylineBootstrapPackage(65, [
      'prompt' => 'I want a storyline about hunting relic thieves',
      'speaker_npc_id' => 'npc_tavern_keeper',
      'speaker_name' => 'Eldric',
      'lead_location_id' => 'tavern_entrance',
    ]);

    $this->assertSame('ai', $package['generation_source']);
    $this->assertSame('Relic Thief Pursuit', $package['storyline_definition']['name'] ?? NULL);
    $this->assertSame('bootstrap', $package['campaign_outline']['generation_phase'] ?? NULL);
    $this->assertSame('Hunt the relic thieves.', $package['storyline_definition']['metadata']['goal'] ?? NULL);
  }

  /**
   * Verifies bootstrap normalization unwraps nested storyline wrappers safely.
   */
  public function testBootstrapGenerationUnwrapsNestedStorylineWrappersFromAi(): void {
    $campaign_state = $this->createMock(CampaignStateService::class);
    $campaign_state->method('getState')->willReturn([
      'current_room_id' => 'tavern_entrance',
      'characters' => [
        ['level' => 2],
      ],
    ]);

    $storyline_manager = $this->buildStorylineManager($campaign_state);

    $ai_api = $this->createMock(AIApiService::class);
    $ai_api->expects($this->once())
      ->method('invokeModelDirect')
      ->willReturn([
        'response' => json_encode([
          'storyline_definition' => [
            'storyline' => [
              'name' => 'Nested Relic Lead',
              'template_id' => 'nested-relic-lead',
              'synopsis' => 'Follow the nested wrapper to the first vault.',
              'level_range' => '2-3',
              'source' => 'storyline-bootstrap',
              'tags' => ['generated', 'bootstrap'],
              'metadata' => [
                'goal' => 'Recover the relic map.',
                'generated_outline' => [
                  'generation_phase' => 'bootstrap',
                  'goal' => 'Recover the relic map.',
                  'entry_dungeon' => [
                    'dungeon_id' => 'nested-vault-threshold',
                    'name' => 'Nested Vault Threshold',
                    'style' => 'buried archive',
                    'entrance_room_id' => 'nested-vault-threshold-entry',
                    'lead_location_id' => 'tavern_entrance',
                    'lead_location_hint' => 'The clue is hidden behind the cellar casks.',
                  ],
                  'progression_connectors' => [
                    [
                      'connector_id' => 'nested-relic-lead-bootstrap-handoff',
                      'source_type' => 'npc',
                      'source_id' => 'npc_tavern_keeper',
                      'mechanism' => 'npc_direction',
                      'from_location_id' => 'tavern_entrance',
                      'target_dungeon_id' => 'nested-vault-threshold',
                      'target_room_id' => 'nested-vault-threshold-entry',
                      'narrative' => 'Eldric points the party toward the nested vault threshold.',
                    ],
                  ],
                  'bootstrap_handoff' => [
                    'speaker_npc_id' => 'npc_tavern_keeper',
                    'speaker_name' => 'Eldric',
                    'lead_text' => 'Look behind the cellar casks.',
                  ],
                  'expansion_status' => 'pending',
                ],
              ],
              'asset_references' => [
                [
                  'asset_type' => 'location',
                  'asset_id' => 'tavern_entrance',
                  'asset_role' => 'lead-location',
                  'notes' => 'The current location where the questgiver gives the first lead.',
                ],
                [
                  'asset_type' => 'dungeon',
                  'asset_id' => 'nested-vault-threshold',
                  'asset_role' => 'entry-dungeon',
                  'chapter_id' => 'nested-vault-threshold',
                  'notes' => 'First storyline dungeon stub generated during bootstrap.',
                ],
                [
                  'asset_type' => 'room',
                  'asset_id' => 'nested-vault-threshold-entry',
                  'asset_role' => 'entrance-room',
                  'chapter_id' => 'nested-vault-threshold',
                  'scene_id' => 'nested-vault-threshold-entry',
                  'notes' => 'Initial storyline entrance room.',
                ],
              ],
              'contacts' => [
                [
                  'contact_id' => 'nested-relic-patron',
                  'entity_type' => 'campaign_npc',
                  'entity_id' => 'npc_tavern_keeper',
                  'role' => 'quest_giver',
                  'display_name' => 'Eldric',
                  'attitude' => 'friendly',
                  'availability' => 'available',
                  'notes' => 'Knows where the cellar clue begins.',
                  'relationship_state' => [
                    'points_to_dungeon_id' => 'nested-vault-threshold',
                    'points_to_room_id' => 'nested-vault-threshold-entry',
                    'mechanism' => 'npc_direction',
                  ],
                  'introduces_to' => [],
                ],
              ],
              'chapters' => [
                [
                  'chapter_id' => 'nested-vault-threshold',
                  'name' => 'Cellar Clue',
                  'scenes' => [
                    [
                      'scene_id' => 'nested-vault-threshold-entry',
                      'name' => 'Hidden Cellar Door',
                      'summary' => 'A false wall opens to the first descent.',
                      'quest_ids' => ['nested-vault-threshold-entry-quest'],
                    ],
                  ],
                ],
              ],
            ],
          ],
          'quest_templates' => [
            [
              'template_id' => 'nested-vault-threshold-entry-quest',
              'name' => 'Open the Hidden Cellar Door',
              'summary' => 'Find the hidden latch behind the casks.',
              'giver_npc_id' => 'npc_tavern_keeper',
              'objectives_schema' => [
                [
                  'phase' => 1,
                  'objectives' => [
                    [
                      'objective_id' => 'find-hidden-latch',
                      'type' => 'search',
                      'target' => 'nested-vault-threshold-entry',
                      'target_count' => 1,
                      'description' => 'Search behind the casks for the hidden latch.',
                      'completion_criteria' => [
                        'kind' => 'count',
                        'metric' => 'current',
                        'target_count' => 1,
                        'description' => 'Reach the required progress count.',
                      ],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ], JSON_UNESCAPED_SLASHES),
      ]);

    $service = new StorylineGenerationService(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      $ai_api,
      $storyline_manager,
      $campaign_state,
      new TreasureByLevelService(),
      $this->buildUuid()
    );

    $package = $service->generateStorylineBootstrapPackage(65, [
      'prompt' => 'I want a storyline about recovering a relic map',
      'speaker_npc_id' => 'npc_tavern_keeper',
      'speaker_name' => 'Eldric',
      'lead_location_id' => 'tavern_entrance',
    ]);

    $this->assertSame('ai', $package['generation_source']);
    $this->assertSame('Nested Relic Lead', $package['storyline_definition']['name'] ?? NULL);
    $this->assertSame('Recover the relic map.', $package['storyline_definition']['metadata']['goal'] ?? NULL);
  }

  /**
   * Verifies deferred expansion can preserve the bootstrap handoff identifiers.
   */
  public function testExpandedGenerationPreservesBootstrapIdsWhenProvided(): void {
    $campaign_state = $this->createMock(CampaignStateService::class);
    $campaign_state->method('getState')->willReturn([
      'current_room_id' => 'tavern_entrance',
      'characters' => [
        ['level' => 3],
        ['level' => 3],
      ],
    ]);

    $storyline_manager = $this->buildStorylineManager($campaign_state);

    $service = new StorylineGenerationService(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      NULL,
      $storyline_manager,
      $campaign_state,
      new TreasureByLevelService(),
      $this->buildUuid()
    );

    $package = $service->generateStorylinePackage(65, [
      'prompt' => 'Stop the relic cult before it opens the gate',
      'template_id' => 'bootstrap-threshold',
      'entry_dungeon_id' => 'bootstrap-threshold-entry-dungeon',
      'entry_room_id' => 'bootstrap-threshold-entry-room',
      'first_quest_id' => 'bootstrap-threshold-entry-quest',
      'speaker_npc_id' => 'npc_tavern_keeper',
      'speaker_name' => 'Eldric',
      'lead_location_id' => 'tavern_entrance',
    ]);

    $outline = $package['campaign_outline'] ?? [];
    $chapters = $package['storyline_definition']['chapters'] ?? [];

    $this->assertSame('bootstrap-threshold', $package['storyline_definition']['template_id'] ?? NULL);
    $this->assertSame('bootstrap-threshold-entry-dungeon', $outline['dungeons'][0]['dungeon_id'] ?? NULL);
    $this->assertSame('bootstrap-threshold-entry-room', $outline['dungeons'][0]['entrance_room_id'] ?? NULL);
    $this->assertSame('bootstrap-threshold-entry-room', $outline['progression_connectors'][0]['target_room_id'] ?? NULL);
    $this->assertSame('bootstrap-threshold-entry-quest', $chapters[0]['scenes'][0]['quest_ids'][0] ?? NULL);
  }

  /**
   * Verifies storyline NPC specs are derived from contacts and boss outline data.
   */
  public function testBuildStorylineNpcSpecsIncludesQuestgiverAndBosses(): void {
    $campaign_state = $this->createMock(CampaignStateService::class);
    $campaign_state->method('getState')->willReturn([
      'current_room_id' => 'tavern_entrance',
      'characters' => [['level' => 2], ['level' => 4]],
    ]);

    $storyline_manager = $this->buildStorylineManager($campaign_state);

    $service = new class(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      NULL,
      $storyline_manager,
      $campaign_state,
      new TreasureByLevelService(),
      $this->buildUuid(),
      NULL,
      NULL,
      NULL,
      NULL,
      new StorylineRealizationService($this->createMock(Connection::class))
    ) extends StorylineGenerationService {
      public function exposeBuildStorylineNpcSpecs(array $storyline_data): array {
        return $this->buildStorylineNpcSpecs($storyline_data);
      }
    };

    $specs = $service->exposeBuildStorylineNpcSpecs([
      'metadata' => [
        'level_range' => '2-5',
        'generated_outline' => [
          'sub_bosses' => [
            ['boss_id' => 'ash-warden', 'name' => 'Ash Warden', 'style' => 'fortified ruin'],
            ['boss_id' => 'echo-seer', 'name' => 'Echo Seer', 'style' => 'occult ruin'],
          ],
          'big_boss' => [
            'boss_id' => 'gate-king',
            'name' => 'Gate King',
            'style' => 'void ruin',
          ],
        ],
      ],
      'contacts' => [
        [
          'entity_type' => 'campaign_npc',
          'entity_id' => 'npc_tavern_keeper',
          'display_name' => 'Eldric',
          'attitude' => 'friendly',
          'notes' => 'Knows where the trouble starts.',
        ],
      ],
    ]);

    $entity_refs = array_column($specs, 'entity_ref');
    $this->assertContains('npc_tavern_keeper', $entity_refs);
    $this->assertContains('ash-warden', $entity_refs);
    $this->assertContains('echo-seer', $entity_refs);
    $this->assertContains('gate-king', $entity_refs);
  }

  /**
   * Verifies bootstrap outlines synthesize a concrete entry dungeon bundle.
   */
  public function testExtractStorylineDungeonOutlinesSupportsBootstrapShape(): void {
    $campaign_state = $this->createMock(CampaignStateService::class);
    $campaign_state->method('getState')->willReturn([
      'current_room_id' => 'tavern_entrance',
      'characters' => [['level' => 1]],
    ]);

    $storyline_manager = $this->buildStorylineManager($campaign_state);

    $service = new class(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      NULL,
      $storyline_manager,
      $campaign_state,
      new TreasureByLevelService(),
      $this->buildUuid(),
      NULL,
      NULL,
      NULL,
      NULL,
      new StorylineRealizationService($this->createMock(Connection::class))
    ) extends StorylineGenerationService {
      public function exposeExtractStorylineDungeonOutlines(array $storyline_data): array {
        return $this->extractStorylineDungeonOutlines($storyline_data);
      }
    };

    $dungeons = $service->exposeExtractStorylineDungeonOutlines([
      'metadata' => [
        'goal' => 'Find the missing relic',
        'generated_outline' => [
          'generation_phase' => 'bootstrap',
          'goal' => 'Find the missing relic',
          'entry_dungeon' => [
            'dungeon_id' => 'relic-threshold',
            'name' => 'Threshold of Relics',
            'style' => 'threshold archive',
            'entrance_room_id' => 'relic-threshold-entrance',
            'lead_location_hint' => 'Follow the tavern map to the ruined stairs.',
          ],
        ],
      ],
      'questline' => [
        'primary_quest_id' => 'relic-threshold-entrance-quest',
      ],
      'chapters' => [[
        'scenes' => [[
          'scene_id' => 'relic-threshold-entrance',
          'name' => 'Dungeon Entrance',
          'summary' => 'A cracked stairway descends beneath the old tavern.',
          'quest_ids' => ['relic-threshold-entrance-quest'],
        ]],
      ]],
    ]);

    $this->assertCount(1, $dungeons);
    $this->assertSame('relic-threshold', $dungeons[0]['dungeon_id'] ?? NULL);
    $this->assertSame('relic-threshold-entrance', $dungeons[0]['rooms'][0]['room_id'] ?? NULL);
    $this->assertSame('relic-threshold-entrance-quest', $dungeons[0]['rooms'][0]['quest_template_id'] ?? NULL);
  }

  /**
   * Verifies room npc references are promoted into campaign NPC specs.
   */
  public function testBuildStorylineNpcSpecsIncludesRoomOccupants(): void {
    $campaign_state = $this->createMock(CampaignStateService::class);
    $campaign_state->method('getState')->willReturn([
      'current_room_id' => 'tavern_entrance',
      'characters' => [['level' => 3]],
    ]);

    $storyline_manager = $this->buildStorylineManager($campaign_state);

    $service = new class(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      NULL,
      $storyline_manager,
      $campaign_state,
      new TreasureByLevelService(),
      $this->buildUuid(),
      NULL,
      NULL,
      NULL,
      NULL,
      new StorylineRealizationService($this->createMock(Connection::class))
    ) extends StorylineGenerationService {
      public function exposeBuildStorylineNpcSpecs(array $storyline_data): array {
        return $this->buildStorylineNpcSpecs($storyline_data);
      }
    };

    $specs = $service->exposeBuildStorylineNpcSpecs([
      'metadata' => [
        'level_range' => '3-5',
        'generated_outline' => [
          'generation_phase' => 'expanded',
          'dungeons' => [[
            'dungeon_id' => 'vault-of-cinders',
            'name' => 'Vault of Cinders',
            'style' => 'ash vault',
            'rooms' => [[
              'room_id' => 'vault-of-cinders-room-1',
              'name' => 'Cinder Gate',
              'room_role' => 'entrance',
              'npc_ids' => ['vault-of-cinders-entrance-sentinel'],
              'item_ids' => [],
            ]],
          ]],
        ],
      ],
    ]);

    $entity_refs = array_column($specs, 'entity_ref');
    $this->assertContains('vault-of-cinders-entrance-sentinel', $entity_refs);
  }

  private function buildLoggerFactory(): LoggerChannelFactoryInterface {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($logger);
    return $factory;
  }

  private function buildUuid(): UuidInterface {
    $uuid = $this->createMock(UuidInterface::class);
    $uuid->method('generate')->willReturn('12345678-1234-1234-1234-1234567890ab');
    return $uuid;
  }

  private function buildStorylineManager(CampaignStateService $campaign_state, array $existing_storylines = []): StorylineManagerService {
    return new class(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      $this->buildUuid(),
      $campaign_state,
      $this->buildStateValidationService(),
      $existing_storylines
    ) extends StorylineManagerService {
      public function __construct(
        Connection $database,
        LoggerChannelFactoryInterface $logger_factory,
        UuidInterface $uuid,
        CampaignStateService $campaign_state_service,
        StateValidationService $state_validation_service,
        private readonly array $existingStorylines,
      ) {
        parent::__construct(
          $database,
          $logger_factory,
          $uuid,
          $campaign_state_service,
          $state_validation_service
        );
      }

      public function listCampaignStorylines(int $campaign_id, bool $refresh = FALSE): array {
        return $this->existingStorylines;
      }
    };
  }

  private function buildStateValidationService(): StateValidationService {
    $logger = $this->createMock(LoggerInterface::class);
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($logger);
    return new StateValidationService($factory);
  }

}
