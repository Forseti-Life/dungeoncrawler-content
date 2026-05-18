<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\dungeoncrawler_content\Service\CampaignStateService;
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

    $storyline_manager = new StorylineManagerService(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      $this->buildUuid(),
      $campaign_state,
      $this->buildStateValidationService()
    );

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

    $storyline_manager = new StorylineManagerService(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      $this->buildUuid(),
      $campaign_state,
      $this->buildStateValidationService()
    );

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

    $storyline_manager = new StorylineManagerService(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      $this->buildUuid(),
      $campaign_state,
      $this->buildStateValidationService()
    );

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

    $storyline_manager = new StorylineManagerService(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      $this->buildUuid(),
      $campaign_state,
      $this->buildStateValidationService()
    );

    $ai_api = $this->createMock(\Drupal\dungeoncrawler_content\Service\AiApiService::class);
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
                    'source_id' => 'npc_tavern_keeper',
                    'target_dungeon_id' => 'relic-vault-threshold',
                    'target_room_id' => 'relic-vault-threshold-entry',
                  ],
                ],
                'bootstrap_handoff' => [
                  'speaker_npc_id' => 'npc_tavern_keeper',
                  'speaker_name' => 'Eldric',
                  'lead_text' => 'Start with the locked stairs beneath the cellar.',
                ],
              ],
            ],
            'asset_references' => [],
            'contacts' => [
              [
                'contact_id' => 'relic-thief-patron',
                'entity_type' => 'campaign_npc',
                'entity_id' => 'npc_tavern_keeper',
                'role' => 'quest_giver',
                'display_name' => 'Eldric',
                'attitude' => 'friendly',
                'notes' => 'Knows where the first clue begins.',
                'relationship_state' => [
                  'points_to_dungeon_id' => 'relic-vault-threshold',
                  'points_to_room_id' => 'relic-vault-threshold-entry',
                  'mechanism' => 'npc_direction',
                ],
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
              'objective_flow' => [
                [
                  'objective_id' => 'reach-vault-entry',
                  'type' => 'travel',
                  'summary' => 'Reach the cellar stairs.',
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

    $storyline_manager = new StorylineManagerService(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      $this->buildUuid(),
      $campaign_state,
      $this->buildStateValidationService()
    );

    $ai_api = $this->createMock(\Drupal\dungeoncrawler_content\Service\AiApiService::class);
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
                      'source_id' => 'npc_tavern_keeper',
                      'target_dungeon_id' => 'nested-vault-threshold',
                      'target_room_id' => 'nested-vault-threshold-entry',
                    ],
                  ],
                  'bootstrap_handoff' => [
                    'speaker_npc_id' => 'npc_tavern_keeper',
                    'speaker_name' => 'Eldric',
                    'lead_text' => 'Look behind the cellar casks.',
                  ],
                ],
              ],
              'asset_references' => [],
              'contacts' => [
                [
                  'contact_id' => 'nested-relic-patron',
                  'entity_type' => 'campaign_npc',
                  'entity_id' => 'npc_tavern_keeper',
                  'role' => 'quest_giver',
                  'display_name' => 'Eldric',
                  'attitude' => 'friendly',
                  'notes' => 'Knows where the cellar clue begins.',
                  'relationship_state' => [
                    'points_to_dungeon_id' => 'nested-vault-threshold',
                    'points_to_room_id' => 'nested-vault-threshold-entry',
                    'mechanism' => 'npc_direction',
                  ],
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
              'objective_flow' => [
                [
                  'objective_id' => 'find-hidden-latch',
                  'type' => 'search',
                  'summary' => 'Search behind the casks for the hidden latch.',
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

    $storyline_manager = new StorylineManagerService(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      $this->buildUuid(),
      $campaign_state,
      $this->buildStateValidationService()
    );

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

    $storyline_manager = new StorylineManagerService(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      $this->buildUuid(),
      $campaign_state,
      $this->buildStateValidationService()
    );

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

    $storyline_manager = new StorylineManagerService(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      $this->buildUuid(),
      $campaign_state,
      $this->buildStateValidationService()
    );

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

    $storyline_manager = new StorylineManagerService(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      $this->buildUuid(),
      $campaign_state,
      $this->buildStateValidationService()
    );

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

  private function buildStateValidationService(): StateValidationService {
    $logger = $this->createMock(LoggerInterface::class);
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($logger);
    return new StateValidationService($factory);
  }

}
