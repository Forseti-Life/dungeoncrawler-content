<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\dungeoncrawler_content\Service\CampaignStateService;
use Drupal\dungeoncrawler_content\Service\ObjectiveTypeService;
use Drupal\dungeoncrawler_content\Service\StorylineManagerService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests storyline normalization and deterministic progression logic.
 *
 * @group dungeoncrawler_content
 * @group storyline
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\StorylineManagerService
 */
class StorylineManagerServiceTest extends UnitTestCase {

  /**
   * @covers ::normalizeTemplateDefinition
   */
  public function testNormalizeTemplateDefinitionBuildsChapterSceneQuestMap(): void {
    $service = $this->buildService();
    $method = new \ReflectionMethod(StorylineManagerService::class, 'normalizeTemplateDefinition');
    $method->setAccessible(TRUE);

    $normalized = $method->invoke($service, [
      'name' => 'Little Trouble in Big Absalom',
      'chapters' => [
        [
          'name' => 'The Tomb',
          'scenes' => [
            [
              'name' => 'Vault Entry',
              'quest_ids' => ['kobold-scout'],
            ],
          ],
        ],
        [
          'name' => "Grandma's House",
          'quest_ids' => ['find-trimmer'],
        ],
      ],
    ]);

    $this->assertSame('little-trouble-in-big-absalom', $normalized['template_id']);
    $this->assertSame('the-tomb', $normalized['chapters'][0]['chapter_id']);
    $this->assertSame('vault-entry', $normalized['chapters'][0]['scenes'][0]['scene_id']);
    $this->assertSame('the-tomb', $normalized['linked_quests']['kobold-scout']['chapter_id']);
    $this->assertSame('vault-entry', $normalized['linked_quests']['kobold-scout']['scene_id']);
    $this->assertSame('grandma-s-house', $normalized['linked_quests']['find-trimmer']['chapter_id']);
    $this->assertSame('questline', $normalized['storyline_type']);
    $this->assertSame('kobold-scout', $normalized['questline']['primary_quest_id']);
    $this->assertSame(['kobold-scout', 'find-trimmer'], $normalized['questline']['ordered_quest_ids']);
    $this->assertSame(['find-trimmer'], $normalized['questline']['quest_nodes'][0]['unlocks_to']);
    $this->assertSame(['kobold-scout'], $normalized['questline']['quest_nodes'][1]['unlocks_after']);
    $this->assertTrue(
      in_array('quest', array_column($normalized['asset_references'], 'asset_type'), TRUE)
    );
  }

  /**
   * @covers ::normalizeTemplateDefinition
   */
  public function testNormalizeTemplateDefinitionCollectsTopLevelAndSceneAssetReferences(): void {
    $service = $this->buildService();
    $method = new \ReflectionMethod(StorylineManagerService::class, 'normalizeTemplateDefinition');
    $method->setAccessible(TRUE);

    $normalized = $method->invoke($service, [
      'name' => 'Asset Heavy Story',
      'asset_references' => [
        [
          'asset_type' => 'character',
          'asset_id' => 'hero-1',
          'asset_role' => 'protagonist',
        ],
      ],
      'chapters' => [
        [
          'name' => 'Chapter One',
          'scenes' => [
            [
              'name' => 'Scene One',
              'quest_ids' => ['quest-a'],
              'asset_references' => [
                [
                  'asset_type' => 'room',
                  'asset_id' => 'room-1',
                  'asset_role' => 'set-piece',
                ],
              ],
            ],
          ],
        ],
      ],
    ]);

    $this->assertContains([
      'asset_type' => 'character',
      'asset_id' => 'hero-1',
      'asset_role' => 'protagonist',
      'chapter_id' => '',
      'scene_id' => '',
      'source_scope' => 'storyline',
      'notes' => '',
      'link_data' => [],
    ], $normalized['asset_references']);

    $this->assertContains([
      'asset_type' => 'room',
      'asset_id' => 'room-1',
      'asset_role' => 'set-piece',
      'chapter_id' => 'chapter-one',
      'scene_id' => 'scene-one',
      'source_scope' => 'scene',
      'notes' => '',
      'link_data' => [],
    ], $normalized['asset_references']);
  }

  /**
   * @covers ::normalizeTemplateDefinition
   */
  public function testNormalizeTemplateDefinitionNormalizesContactsAndSeedsBrokerFallback(): void {
    $service = $this->buildService();
    $method = new \ReflectionMethod(StorylineManagerService::class, 'normalizeTemplateDefinition');
    $method->setAccessible(TRUE);

    $normalized = $method->invoke($service, [
      'name' => 'Contact Story',
      'asset_references' => [
        [
          'asset_type' => 'npc',
          'asset_id' => 'quest-contact',
          'asset_role' => 'quest-giver',
        ],
      ],
      'contacts' => [
        [
          'contact_id' => 'quest-giver-contact',
          'entity_type' => 'npc_template',
          'entity_id' => 'quest-contact',
          'role' => 'quest_giver',
          'display_name' => 'Quest Contact',
          'attitude' => 'friendly',
        ],
      ],
      'chapters' => [],
    ]);

    $this->assertCount(2, $normalized['contacts']);
    $this->assertSame('quest_giver', $normalized['contacts'][0]['role']);
    $this->assertSame('campaign_npc', $normalized['contacts'][1]['entity_type']);
    $this->assertSame('npc_tavern_keeper', $normalized['contacts'][1]['entity_id']);
    $this->assertSame('knows', $normalized['contacts'][1]['introduces_to'][0]['relationship_type']);
  }

  /**
   * @covers ::normalizeTemplateDefinition
   */
  public function testNormalizeTemplateDefinitionBackfillsCanonicalMetadataContract(): void {
    $service = $this->buildService();
    $method = new \ReflectionMethod(StorylineManagerService::class, 'normalizeTemplateDefinition');
    $method->setAccessible(TRUE);

    $normalized = $method->invoke($service, [
      'name' => 'Bootstrap Lead Story',
      'source' => 'npc-storyline-bootstrap',
      'contacts' => [
        [
          'contact_id' => 'eldric-contact',
          'entity_type' => 'campaign_npc',
          'entity_id' => 'npc_tavern_keeper',
          'role' => 'quest_giver',
          'display_name' => 'Eldric',
          'attitude' => 'friendly',
        ],
      ],
      'chapters' => [
        [
          'name' => 'Threshold of Lore',
          'scenes' => [
            [
              'name' => 'Old Library Stairs',
              'quest_ids' => ['threshold-lore-quest'],
            ],
          ],
        ],
      ],
    ]);

    $this->assertSame('Bootstrap Lead Story', $normalized['metadata']['goal']);
    $this->assertSame('bootstrap', $normalized['metadata']['generated_outline']['generation_phase']);
    $this->assertSame('Bootstrap Lead Story', $normalized['metadata']['generated_outline']['goal']);
    $this->assertSame('threshold-of-lore', $normalized['metadata']['generated_outline']['entry_dungeon']['dungeon_id']);
    $this->assertSame('old-library-stairs', $normalized['metadata']['generated_outline']['entry_dungeon']['entrance_room_id']);
    $this->assertSame('npc_tavern_keeper', $normalized['metadata']['generated_outline']['bootstrap_handoff']['speaker_npc_id']);
    $this->assertSame('Eldric', $normalized['metadata']['generated_outline']['bootstrap_handoff']['speaker_name']);
    $this->assertSame('threshold-of-lore-bootstrap-handoff', $normalized['metadata']['generated_outline']['progression_connectors'][0]['connector_id']);
    $this->assertSame('npc_direction', $normalized['metadata']['generated_outline']['progression_connectors'][0]['mechanism']);
    $this->assertSame('tavern_entrance', $normalized['metadata']['generated_outline']['progression_connectors'][0]['from_location_id']);
    $this->assertSame('old-library-stairs', $normalized['metadata']['generated_outline']['progression_connectors'][0]['target_room_id']);
    $this->assertSame('threshold-of-lore', $normalized['contacts'][0]['relationship_state']['chapter_id']);
    $this->assertSame('old-library-stairs', $normalized['contacts'][0]['relationship_state']['scene_id']);
    $this->assertTrue(
      in_array('npc_tavern_keeper', array_map(static fn(array $reference): string => (string) ($reference['asset_id'] ?? ''), array_filter($normalized['asset_references'], static fn(array $reference): bool => (string) ($reference['asset_role'] ?? '') === 'quest-giver')), TRUE)
    );
  }

  /**
   * @covers ::validateNormalizedStorylineDefinition
   */
  public function testValidateNormalizedStorylineDefinitionRejectsUnknownRoomNpcReferences(): void {
    $service = $this->buildService();
    $normalize = new \ReflectionMethod(StorylineManagerService::class, 'normalizeTemplateDefinition');
    $normalize->setAccessible(TRUE);

    $normalized = $normalize->invoke($service, [
      'name' => 'Broken NPC Story',
      'source' => 'storyline-generator',
      'tags' => ['generated'],
      'metadata' => [
        'goal' => 'Stop the sentinel.',
        'generated_outline' => [
          'generation_phase' => 'expanded',
          'goal' => 'Stop the sentinel.',
          'big_boss' => [
            'boss_id' => 'known-boss',
            'name' => 'Known Boss',
            'style' => 'ruin',
            'dungeon_id' => 'broken-vault',
          ],
          'dungeons' => [[
            'dungeon_id' => 'broken-vault',
            'name' => 'Broken Vault',
            'boss_id' => 'known-boss',
            'style' => 'ruin',
            'entrance_room_id' => 'broken-vault-room-1',
            'boss_room_id' => 'broken-vault-room-1',
            'room_count' => 1,
            'rooms' => [[
              'room_id' => 'broken-vault-room-1',
              'name' => 'Broken Entrance',
              'room_role' => 'entrance',
              'style' => 'ruin',
              'summary' => 'A broken room.',
              'npc_ids' => ['missing-npc'],
              'item_ids' => [],
              'encounter_connector' => ['threat_level' => 'low'],
              'treasure_connector' => ['loot_table_id' => 'core_starter_adventure'],
            ]],
          ]],
        ],
      ],
      'contacts' => [[
        'contact_id' => 'quest-giver-contact',
        'entity_type' => 'npc_template',
        'entity_id' => 'known-contact',
        'role' => 'quest_giver',
        'display_name' => 'Known Contact',
        'attitude' => 'friendly',
      ]],
      'chapters' => [[
        'name' => 'Broken Vault',
        'scenes' => [[
          'name' => 'Broken Entrance',
          'quest_ids' => ['broken-quest'],
        ]],
      ]],
    ]);

    $validation = $service->validateNormalizedStorylineDefinition($normalized);
    $this->assertFalse($validation['valid']);
    $this->assertStringContainsString("unknown NPC 'missing-npc'", implode('; ', $validation['errors'] ?? []));
  }

  /**
   * @covers ::validateRuntimeStorylineContract
   */
  public function testValidateRuntimeStorylineContractRejectsUnknownRoomNpcReferences(): void {
    $service = $this->buildService();
    $normalize = new \ReflectionMethod(StorylineManagerService::class, 'normalizeTemplateDefinition');
    $normalize->setAccessible(TRUE);

    $normalized = $normalize->invoke($service, [
      'name' => 'Broken Runtime Story',
      'source' => 'storyline-generator',
      'metadata' => [
        'goal' => 'Stop the sentinel.',
        'generated_outline' => [
          'generation_phase' => 'expanded',
          'goal' => 'Stop the sentinel.',
          'big_boss' => [
            'boss_id' => 'known-boss',
            'name' => 'Known Boss',
            'style' => 'ruin',
            'dungeon_id' => 'broken-vault',
          ],
          'dungeons' => [[
            'dungeon_id' => 'broken-vault',
            'name' => 'Broken Vault',
            'boss_id' => 'known-boss',
            'style' => 'ruin',
            'entrance_room_id' => 'broken-vault-room-1',
            'boss_room_id' => 'broken-vault-room-1',
            'room_count' => 1,
            'rooms' => [[
              'room_id' => 'broken-vault-room-1',
              'name' => 'Broken Entrance',
              'room_role' => 'entrance',
              'style' => 'ruin',
              'summary' => 'A broken room.',
              'npc_ids' => ['missing-npc'],
              'item_ids' => [],
              'encounter_connector' => ['threat_level' => 'low'],
              'treasure_connector' => ['loot_table_id' => 'core_starter_adventure'],
            ]],
          ]],
        ],
      ],
      'contacts' => [[
        'contact_id' => 'quest-giver-contact',
        'entity_type' => 'npc_template',
        'entity_id' => 'known-contact',
        'role' => 'quest_giver',
        'display_name' => 'Known Contact',
        'attitude' => 'friendly',
      ]],
      'chapters' => [[
        'name' => 'Broken Vault',
        'scenes' => [[
          'name' => 'Broken Entrance',
          'quest_ids' => ['broken-quest'],
        ]],
      ]],
    ]);

    $runtime = [
      'schema_version' => StorylineManagerService::STORYLINE_RUNTIME_SCHEMA_VERSION,
      'storyline_type' => 'questline',
      'metadata' => $normalized['metadata'],
      'chapters' => $normalized['chapters'],
      'linked_quests' => $normalized['linked_quests'],
      'questline' => $normalized['questline'],
      'asset_references' => $normalized['asset_references'],
      'contacts' => $normalized['contacts'],
      'unlocked_chapter_ids' => ['broken-vault'],
      'unlocked_scene_ids' => ['broken-entrance'],
      'current_chapter_id' => 'broken-vault',
      'current_scene_id' => 'broken-entrance',
      'status' => 'active',
      'variables' => [],
    ];

    $validation = $service->validateRuntimeStorylineContract($runtime);
    $this->assertFalse($validation['valid']);
    $this->assertStringContainsString("unknown NPC 'missing-npc'", implode('; ', $validation['errors'] ?? []));
  }

  /**
   * @covers ::normalizeRuntimeStorylineData
   */
  public function testNormalizeRuntimeStorylineDataRepairsLegacyBootstrapOutlineReferences(): void {
    $service = $this->buildService();
    $method = new \ReflectionMethod(StorylineManagerService::class, 'normalizeRuntimeStorylineData');
    $method->setAccessible(TRUE);

    $normalized = $method->invoke($service, [
      'schema_version' => StorylineManagerService::STORYLINE_RUNTIME_SCHEMA_VERSION,
      'storyline_type' => 'questline',
      'storyline_id' => 'torment-and-legacy',
      'template_id' => 'torment-and-legacy',
      'name' => 'Torment and Legacy',
      'metadata' => [
        'generated_outline' => [
          'generation_phase' => 'bootstrap',
          'entry_dungeon' => [
            'name' => 'Onboarding',
            'dungeon_id' => 'onboarding',
            'entrance_room_id' => 'briefing',
          ],
          'progression_connectors' => [[
            'connector_id' => 'onboarding-bootstrap-handoff',
            'source_type' => 'npc',
            'source_id' => 'tal-mission-handler',
            'target_dungeon_id' => 'onboarding',
            'target_room_id' => 'briefing',
          ]],
        ],
      ],
      'chapters' => [[
        'chapter_id' => 'torment-and-legacy-entry-dungeon',
        'name' => 'Onboarding',
        'scenes' => [[
          'scene_id' => 'torment-and-legacy-entry-dungeon-entrance',
          'name' => 'Adventure Briefing',
          'quest_ids' => ['torment-and-legacy-entry-dungeon-entrance-quest'],
        ]],
      ]],
      'linked_quests' => [],
      'questline' => [
        'primary_quest_id' => 'torment-and-legacy-entry-dungeon-entrance-quest',
        'ordered_quest_ids' => ['torment-and-legacy-entry-dungeon-entrance-quest'],
        'quest_nodes' => [],
      ],
      'asset_references' => [],
      'contacts' => [],
      'unlocked_chapter_ids' => ['torment-and-legacy-entry-dungeon'],
      'unlocked_scene_ids' => ['torment-and-legacy-entry-dungeon-entrance'],
      'current_chapter_id' => 'torment-and-legacy-entry-dungeon',
      'current_scene_id' => 'torment-and-legacy-entry-dungeon-entrance',
      'status' => 'available',
      'variables' => [],
    ]);

    $this->assertArrayNotHasKey('storyline_id', $normalized);
    $this->assertArrayNotHasKey('template_id', $normalized);
    $this->assertArrayNotHasKey('name', $normalized);
    $this->assertSame('torment-and-legacy', $normalized['metadata']['template_id']);
    $this->assertSame('Torment and Legacy', $normalized['metadata']['name']);
    $this->assertSame('torment-and-legacy-entry-dungeon', $normalized['metadata']['generated_outline']['entry_dungeon']['dungeon_id']);
    $this->assertSame('torment-and-legacy-entry-dungeon-entrance', $normalized['metadata']['generated_outline']['entry_dungeon']['entrance_room_id']);
    $this->assertSame('torment-and-legacy-entry-dungeon', $normalized['metadata']['generated_outline']['progression_connectors'][0]['target_dungeon_id']);
    $this->assertSame('torment-and-legacy-entry-dungeon-entrance', $normalized['metadata']['generated_outline']['progression_connectors'][0]['target_room_id']);
  }

  /**
   * @covers ::synchronizeStorylineDataWithQuestStates
   */
  public function testSynchronizeStorylineDataAdvancesToNextSceneWhenCurrentSceneQuestsComplete(): void {
    $service = $this->buildService();
    $method = new \ReflectionMethod(StorylineManagerService::class, 'synchronizeStorylineDataWithQuestStates');
    $method->setAccessible(TRUE);

    $storyline_data = [
      'metadata' => [
        'generated_outline' => [
          'entry_point' => [
            'primary_quest_giver_id' => 'test-questgiver',
            'primary_quest_giver_name' => 'Test Questgiver',
            'primary_dungeon_id' => 'tpl_dungeon_tavern_basement',
            'primary_chapter_id' => 'chapter-1',
            'primary_scene_id' => 'scene-1',
            'primary_location_id' => 'tpl_room_tavern_entrance',
            'introduction_path' => 'direct',
            'detail_summary' => 'A test storyline.',
          ],
        ],
      ],
      'contacts' => [[
        'entity_id' => 'test-questgiver',
        'entity_type' => 'campaign_npc',
        'role' => 'quest_giver',
        'relationship_state' => ['chapter_id' => 'chapter-1', 'scene_id' => 'scene-1'],
      ]],
      'asset_references' => [[
        'asset_type' => 'room',
        'asset_id' => 'tpl_room_tavern_entrance',
        'asset_role' => 'entrance',
        'chapter_id' => 'chapter-1',
        'scene_id' => 'scene-1',
      ]],
      'chapters' => [
        [
          'chapter_id' => 'chapter-1',
          'name' => 'Chapter One',
          'quest_ids' => [],
          'scenes' => [
            [
              'scene_id' => 'scene-1',
              'name' => 'Scene One',
              'quest_ids' => ['quest-a'],
            ],
            [
              'scene_id' => 'scene-2',
              'name' => 'Scene Two',
              'quest_ids' => ['quest-b'],
            ],
          ],
        ],
      ],
      'linked_quests' => [
        'quest-a' => ['quest_id' => 'quest-a', 'chapter_id' => 'chapter-1', 'scene_id' => 'scene-1', 'status' => 'active'],
        'quest-b' => ['quest_id' => 'quest-b', 'chapter_id' => 'chapter-1', 'scene_id' => 'scene-2', 'status' => 'available'],
      ],
      'unlocked_chapter_ids' => ['chapter-1'],
      'unlocked_scene_ids' => ['scene-1'],
      'status' => 'active',
      'variables' => [],
    ];

    $result = $method->invoke($service, $storyline_data, 'chapter-1', 'scene-1', [
      'quest-a' => 'completed',
      'quest-b' => 'available',
    ]);

    $this->assertSame('chapter-1', $result['current_chapter_id']);
    $this->assertSame('scene-2', $result['current_scene_id']);
    $this->assertSame('active', $result['status']);
    $this->assertContains('scene-2', $result['storyline_data']['unlocked_scene_ids']);
    $this->assertSame('completed', $result['storyline_data']['linked_quests']['quest-a']['status']);
  }

  /**
   * @covers ::synchronizeStorylineDataWithQuestStates
   */
  public function testSynchronizeStorylineDataCompletesStorylineAtEndOfFinalScene(): void {
    $service = $this->buildService();
    $method = new \ReflectionMethod(StorylineManagerService::class, 'synchronizeStorylineDataWithQuestStates');
    $method->setAccessible(TRUE);

    $storyline_data = [
      'metadata' => [
        'generated_outline' => [
          'entry_point' => [
            'primary_quest_giver_id' => 'test-questgiver',
            'primary_quest_giver_name' => 'Test Questgiver',
            'primary_dungeon_id' => 'tpl_dungeon_tavern_basement',
            'primary_chapter_id' => 'chapter-1',
            'primary_scene_id' => 'scene-1',
            'primary_location_id' => 'tpl_room_tavern_entrance',
            'introduction_path' => 'direct',
            'detail_summary' => 'A test storyline.',
          ],
        ],
      ],
      'contacts' => [[
        'entity_id' => 'test-questgiver',
        'entity_type' => 'campaign_npc',
        'role' => 'quest_giver',
        'relationship_state' => ['chapter_id' => 'chapter-1', 'scene_id' => 'scene-1'],
      ]],
      'asset_references' => [[
        'asset_type' => 'room',
        'asset_id' => 'tpl_room_tavern_entrance',
        'asset_role' => 'entrance',
        'chapter_id' => 'chapter-1',
        'scene_id' => 'scene-1',
      ]],
      'chapters' => [
        [
          'chapter_id' => 'chapter-1',
          'name' => 'Chapter One',
          'quest_ids' => [],
          'scenes' => [
            [
              'scene_id' => 'scene-1',
              'name' => 'Scene One',
              'quest_ids' => ['quest-a'],
            ],
          ],
        ],
      ],
      'linked_quests' => [
        'quest-a' => ['quest_id' => 'quest-a', 'chapter_id' => 'chapter-1', 'scene_id' => 'scene-1', 'status' => 'active'],
      ],
      'unlocked_chapter_ids' => ['chapter-1'],
      'unlocked_scene_ids' => ['scene-1'],
      'status' => 'active',
      'variables' => [],
    ];

    $result = $method->invoke($service, $storyline_data, 'chapter-1', 'scene-1', [
      'quest-a' => 'completed',
    ]);

    $this->assertSame('completed', $result['status']);
    $this->assertCount(1, $result['events']);
    $this->assertSame('storyline_completed', $result['events'][0]['event_type']);
  }

  /**
   * @covers ::createCampaignStoryline
   */
  public function testCreateCampaignStorylineFinalizesPersistedStorylineLifecycle(): void {
    $insert = $this->createMock(\Drupal\Core\Database\Query\Insert::class);
    $insert->expects($this->once())
      ->method('fields')
      ->with($this->callback(static function (array $fields): bool {
        return $fields['storyline_id'] === 'test-storyline-65'
          && $fields['template_id'] === 'test-template'
          && $fields['name'] === 'Test Storyline';
      }))
      ->willReturnSelf();
    $insert->expects($this->once())
      ->method('execute')
      ->willReturn('1');

    $database = $this->createMock(Connection::class);
    $database->expects($this->once())
      ->method('insert')
      ->with('dc_campaign_storylines')
      ->willReturn($insert);

    $campaign_state = $this->createMock(CampaignStateService::class);
    $campaign_state->expects($this->once())
      ->method('getState')
      ->with(65)
      ->willReturn(['state' => []]);

    $uuid = $this->createMock(UuidInterface::class);
    $service = new class($database, $this->buildLoggerFactory(), $uuid, $campaign_state) extends StorylineManagerService {
      public array $finalizedStorylines = [];

      protected function assertStorylineStorageReady(): void {}

      public function normalizeStorylineDefinition(array $definition): array {
        return [
          'template_id' => 'test-template',
          'name' => 'Test Storyline',
          'asset_references' => [],
          'linked_quests' => [],
        ];
      }

      protected function buildInitialStorylineState(array $normalized, array $options): array {
        return [
          'current_chapter_id' => '',
          'current_scene_id' => '',
          'variables' => [],
          'storyline_data' => [
            'linked_quests' => [],
            'asset_references' => [],
          ],
        ];
      }

      protected function generateCampaignStorylineId(int $campaign_id, string $base): string {
        return 'test-storyline-65';
      }

      protected function attachQuestReferences(int $campaign_id, string $storyline_id, array $linked_quests): void {}

      protected function syncCampaignStorylineAssetLinks(int $campaign_id, string $storyline_id, array $asset_references): void {}

      protected function logStorylineEvent(int $campaign_id, string $storyline_id, string $event_type, array $event_data, ?string $narrative_text = NULL): void {}

      protected function persistCampaignStorylinePointers(int $campaign_id, string $storyline_id, bool $primary): void {}

      protected function finalizePersistedCampaignStoryline(int $campaign_id, string $storyline_id): ?array {
        $this->finalizedStorylines[] = [$campaign_id, $storyline_id];
        return [
          'storyline_id' => $storyline_id,
          'storyline_data' => [],
        ];
      }
    };

    $result = $service->createCampaignStoryline(65, ['name' => 'Ignored']);

    $this->assertSame([['65', 'test-storyline-65']], array_map(
      static fn(array $call): array => [(string) $call[0], (string) $call[1]],
      $service->finalizedStorylines
    ));
    $this->assertSame('test-storyline-65', $result['storyline_id']);
  }

  /**
   * @covers ::replaceCampaignStorylineDefinition
   */
  public function testReplaceCampaignStorylineDefinitionFinalizesPersistedStorylineLifecycle(): void {
    $statement = $this->createMock(\Drupal\Core\Database\StatementInterface::class);
    $statement->expects($this->once())
      ->method('fetchAssoc')
      ->willReturn([
        'status' => 'available',
        'storyline_data' => json_encode([
          'metadata' => [
            'generated_outline' => [
              'generation_phase' => 'bootstrap',
            ],
          ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'variables' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'activated_at' => 0,
      ]);

    $select = $this->createMock(\Drupal\Core\Database\Query\SelectInterface::class);
    $select->expects($this->once())
      ->method('fields')
      ->with('s')
      ->willReturnSelf();
    $select->expects($this->exactly(2))
      ->method('condition')
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('range')
      ->with(0, 1)
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('execute')
      ->willReturn($statement);

    $update = $this->createMock(\Drupal\Core\Database\Query\Update::class);
    $update->expects($this->once())
      ->method('fields')
      ->with($this->callback(static function (array $fields): bool {
        return $fields['template_id'] === 'replacement-template'
          && $fields['name'] === 'Replacement Storyline'
          && $fields['status'] === 'available';
      }))
      ->willReturnSelf();
    $update->expects($this->exactly(2))
      ->method('condition')
      ->willReturnSelf();
    $update->expects($this->once())
      ->method('execute')
      ->willReturn(1);

    $database = $this->createMock(Connection::class);
    $database->expects($this->once())
      ->method('select')
      ->with('dc_campaign_storylines', 's')
      ->willReturn($select);
    $database->expects($this->once())
      ->method('update')
      ->with('dc_campaign_storylines')
      ->willReturn($update);

    $uuid = $this->createMock(UuidInterface::class);
    $service = new class($database, $this->buildLoggerFactory(), $uuid, $this->createMock(CampaignStateService::class)) extends StorylineManagerService {
      public array $finalizedStorylines = [];

      protected function assertStorylineStorageReady(): void {}

      public function normalizeStorylineDefinition(array $definition): array {
        return [
          'template_id' => 'replacement-template',
          'name' => 'Replacement Storyline',
          'asset_references' => [],
          'linked_quests' => [],
          'metadata' => [
            'generated_outline' => [
              'generation_phase' => 'expanded',
            ],
          ],
        ];
      }

      protected function buildInitialStorylineState(array $normalized, array $options): array {
        return [
          'current_chapter_id' => '',
          'current_scene_id' => '',
          'variables' => [],
          'storyline_data' => [
            'linked_quests' => [],
            'asset_references' => [],
            'metadata' => [
              'generated_outline' => [
                'generation_phase' => 'expanded',
              ],
            ],
          ],
        ];
      }

      protected function attachQuestReferences(int $campaign_id, string $storyline_id, array $linked_quests): void {}

      protected function syncCampaignStorylineAssetLinks(int $campaign_id, string $storyline_id, array $asset_references): void {}

      protected function logStorylineEvent(int $campaign_id, string $storyline_id, string $event_type, array $event_data, ?string $narrative_text = NULL): void {}

      protected function finalizePersistedCampaignStoryline(int $campaign_id, string $storyline_id): ?array {
        $this->finalizedStorylines[] = [$campaign_id, $storyline_id];
        return [
          'storyline_id' => $storyline_id,
          'storyline_data' => [],
        ];
      }
    };

    $result = $service->replaceCampaignStorylineDefinition(65, 'existing-storyline', ['name' => 'Ignored']);

    $this->assertSame([['65', 'existing-storyline']], array_map(
      static fn(array $call): array => [(string) $call[0], (string) $call[1]],
      $service->finalizedStorylines
    ));
    $this->assertSame('existing-storyline', $result['storyline_id']);
  }

  /**
   * @covers ::advanceCampaignStoryline
   */
  public function testAdvanceCampaignStorylineRejectsInvalidRuntimeContractBeforePersist(): void {
    $statement = $this->createMock(\Drupal\Core\Database\StatementInterface::class);
    $statement->expects($this->once())
      ->method('fetchAssoc')
      ->willReturn([
        'status' => 'active',
        'current_chapter_id' => 'chapter-1',
        'current_scene_id' => 'scene-1',
        'storyline_data' => json_encode([
          'schema_version' => StorylineManagerService::STORYLINE_RUNTIME_SCHEMA_VERSION,
          'storyline_type' => 'questline',
          'metadata' => [],
          'chapters' => [],
          'linked_quests' => [],
          'questline' => [
            'primary_quest_id' => '',
            'ordered_quest_ids' => [],
            'quest_nodes' => [],
          ],
          'asset_references' => [],
          'contacts' => [],
          'unlocked_chapter_ids' => ['chapter-1'],
          'unlocked_scene_ids' => ['scene-1'],
          'status' => 'active',
          'variables' => [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'variables' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      ]);

    $select = $this->createMock(\Drupal\Core\Database\Query\SelectInterface::class);
    $select->expects($this->once())
      ->method('fields')
      ->with('s')
      ->willReturnSelf();
    $select->expects($this->exactly(2))
      ->method('condition')
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('execute')
      ->willReturn($statement);

    $database = $this->createMock(Connection::class);
    $database->expects($this->once())
      ->method('select')
      ->with('dc_campaign_storylines', 's')
      ->willReturn($select);
    $database->expects($this->never())
      ->method('update');

    $uuid = $this->createMock(UuidInterface::class);
    $service = new class($database, $this->buildLoggerFactory(), $uuid, $this->createMock(CampaignStateService::class)) extends StorylineManagerService {
      protected function assertStorylineStorageReady(): void {}

      protected function synchronizeStorylineProgress(array $row): array {
        return $row;
      }

      public function validateRuntimeStorylineContract(array $storyline_data): array {
        return [
          'valid' => FALSE,
          'errors' => ['forced-invalid-runtime'],
        ];
      }
    };

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Storyline runtime failed validation during advance: forced-invalid-runtime');

    $service->advanceCampaignStoryline(65, 'existing-storyline', [
      'chapter_id' => 'chapter-2',
      'scene_id' => 'scene-2',
      'status' => 'active',
    ]);
  }

  /**
   * @covers ::validateStorylineEndToEndContract
   */
  public function testValidateStorylineEndToEndContractReturnsStagedResultsForValidRuntime(): void {
    $service = $this->buildService();
    $normalize = new \ReflectionMethod(StorylineManagerService::class, 'normalizeTemplateDefinition');
    $normalize->setAccessible(TRUE);

    $normalized = $normalize->invoke($service, [
      'name' => 'Valid Runtime Story',
      'source' => 'npc-storyline-bootstrap',
      'asset_references' => [
        [
          'asset_type' => 'dungeon',
          'asset_id' => 'tpl_dungeon_tavern_basement',
          'asset_role' => 'entry-dungeon',
          'chapter_id' => 'tpl_dungeon_tavern_basement',
          'scene_id' => 'tavern_entrance',
        ],
        [
          'asset_type' => 'room',
          'asset_id' => 'tavern_entrance',
          'asset_role' => 'entry-room',
          'chapter_id' => 'tpl_dungeon_tavern_basement',
          'scene_id' => 'tavern_entrance',
        ],
        [
          'asset_type' => 'room',
          'asset_id' => 'tpl_room_tavern_entrance',
          'asset_role' => 'entrance',
          'chapter_id' => 'tpl_dungeon_tavern_basement',
          'scene_id' => 'tavern_entrance',
        ],
      ],
      'contacts' => [[
        'contact_id' => 'eldric-contact',
        'entity_type' => 'campaign_npc',
        'entity_id' => 'npc_tavern_keeper',
        'role' => 'quest_giver',
        'display_name' => 'Eldric',
        'attitude' => 'friendly',
        'relationship_state' => [
          'chapter_id' => 'tpl_dungeon_tavern_basement',
          'scene_id' => 'tavern_entrance',
        ],
      ]],
      'chapters' => [[
        'chapter_id' => 'tpl_dungeon_tavern_basement',
        'name' => 'Absalom',
        'scenes' => [[
          'scene_id' => 'tavern_entrance',
          'name' => 'The Gilded Tankard',
          'quest_ids' => ['bootstrap-quest'],
        ]],
      ]],
      'metadata' => [
        'generated_outline' => [
          'entry_point' => [
            'primary_quest_giver_id' => 'npc_tavern_keeper',
            'primary_quest_giver_name' => 'Eldric',
            'primary_dungeon_id' => 'tpl_dungeon_tavern_basement',
            'primary_chapter_id' => 'tpl_dungeon_tavern_basement',
            'primary_scene_id' => 'tavern_entrance',
            'primary_location_id' => 'tpl_room_tavern_entrance',
            'introduction_path' => 'direct',
            'detail_summary' => 'Eldric briefs the party on the first lead.',
          ],
        ],
      ],
    ]);

    $runtime = [
      'schema_version' => StorylineManagerService::STORYLINE_RUNTIME_SCHEMA_VERSION,
      'storyline_type' => 'questline',
      'metadata' => $normalized['metadata'],
      'chapters' => $normalized['chapters'],
      'linked_quests' => $normalized['linked_quests'],
      'questline' => $normalized['questline'],
      'asset_references' => $normalized['asset_references'],
      'contacts' => $normalized['contacts'],
      'unlocked_chapter_ids' => ['tpl_dungeon_tavern_basement'],
      'unlocked_scene_ids' => ['tavern_entrance'],
      'current_chapter_id' => 'tpl_dungeon_tavern_basement',
      'current_scene_id' => 'tavern_entrance',
      'status' => 'active',
      'variables' => [],
    ];

    $validation = $service->validateStorylineEndToEndContract($runtime, 'runtime');

    $this->assertTrue($validation['valid']);
    $this->assertSame('runtime', $validation['payload_type']);
    $this->assertArrayHasKey('stages', $validation);
    $this->assertTrue($validation['stages']['schema']['valid']);
    $this->assertTrue($validation['stages']['cross_references']['valid']);
    $this->assertTrue($validation['stages']['questline_progression']['valid']);
    $this->assertTrue($validation['stages']['navigation_progression']['valid']);
    $this->assertTrue($validation['stages']['objective_control_chain']['valid']);
    $this->assertTrue($validation['stages']['entity_type_contracts']['valid']);
  }

  /**
   * @covers ::validateStorylineEndToEndContract
   */
  public function testValidateStorylineEndToEndContractRejectsUnknownCurrentScenePointer(): void {
    $service = $this->buildService();
    $normalize = new \ReflectionMethod(StorylineManagerService::class, 'normalizeTemplateDefinition');
    $normalize->setAccessible(TRUE);

    $normalized = $normalize->invoke($service, [
      'name' => 'Pointer Validation Story',
      'source' => 'npc-storyline-bootstrap',
      'chapters' => [[
        'name' => 'Bootstrap Chapter',
        'scenes' => [[
          'name' => 'Bootstrap Scene',
          'quest_ids' => ['bootstrap-quest'],
        ]],
      ]],
    ]);

    $runtime = [
      'schema_version' => StorylineManagerService::STORYLINE_RUNTIME_SCHEMA_VERSION,
      'storyline_type' => 'questline',
      'metadata' => $normalized['metadata'],
      'chapters' => $normalized['chapters'],
      'linked_quests' => $normalized['linked_quests'],
      'questline' => $normalized['questline'],
      'asset_references' => $normalized['asset_references'],
      'contacts' => $normalized['contacts'],
      'unlocked_chapter_ids' => ['bootstrap-chapter'],
      'unlocked_scene_ids' => ['bootstrap-scene'],
      'current_chapter_id' => 'bootstrap-chapter',
      'current_scene_id' => 'missing-scene',
      'status' => 'active',
      'variables' => [],
    ];

    $validation = $service->validateStorylineEndToEndContract($runtime, 'runtime');

    $this->assertFalse($validation['valid']);
    $this->assertFalse($validation['stages']['cross_references']['valid']);
    $this->assertStringContainsString(
      "Current scene 'missing-scene' is not defined by the storyline.",
      implode('; ', $validation['stages']['cross_references']['errors'] ?? [])
    );
  }

  /**
   * @covers ::validateStorylineEndToEndContract
   */
  public function testValidateStorylineEndToEndContractRequiresCurrentPointersToBeUnlocked(): void {
    $service = $this->buildService();
    $normalize = new \ReflectionMethod(StorylineManagerService::class, 'normalizeTemplateDefinition');
    $normalize->setAccessible(TRUE);

    $normalized = $normalize->invoke($service, [
      'name' => 'Unlocked Pointer Story',
      'source' => 'npc-storyline-bootstrap',
      'chapters' => [[
        'name' => 'Bootstrap Chapter',
        'scenes' => [[
          'name' => 'Bootstrap Scene',
          'quest_ids' => ['bootstrap-quest'],
        ]],
      ]],
    ]);

    $runtime = [
      'schema_version' => StorylineManagerService::STORYLINE_RUNTIME_SCHEMA_VERSION,
      'storyline_type' => 'questline',
      'metadata' => $normalized['metadata'],
      'chapters' => $normalized['chapters'],
      'linked_quests' => $normalized['linked_quests'],
      'questline' => $normalized['questline'],
      'asset_references' => $normalized['asset_references'],
      'contacts' => $normalized['contacts'],
      'unlocked_chapter_ids' => [],
      'unlocked_scene_ids' => [],
      'current_chapter_id' => 'bootstrap-chapter',
      'current_scene_id' => 'bootstrap-scene',
      'status' => 'active',
      'variables' => [],
    ];

    $validation = $service->validateStorylineEndToEndContract($runtime, 'runtime');

    $this->assertFalse($validation['valid']);
    $this->assertFalse($validation['stages']['cross_references']['valid']);
    $joined_errors = implode('; ', $validation['stages']['cross_references']['errors'] ?? []);
    $this->assertStringContainsString(
      "Current chapter 'bootstrap-chapter' must be present in unlocked_chapter_ids.",
      $joined_errors
    );
    $this->assertStringContainsString(
      "Current scene 'bootstrap-scene' must be present in unlocked_scene_ids.",
      $joined_errors
    );
  }

  /**
   * @covers ::validateStorylineEndToEndContract
   */
  public function testValidateStorylineEndToEndContractRejectsUnreachableQuestNode(): void {
    $service = $this->buildService();
    $normalize = new \ReflectionMethod(StorylineManagerService::class, 'normalizeTemplateDefinition');
    $normalize->setAccessible(TRUE);

    $normalized = $normalize->invoke($service, [
      'name' => 'Unreachable Quest Story',
      'source' => 'npc-storyline-bootstrap',
      'chapters' => [[
        'name' => 'Entry Chapter',
        'scenes' => [[
          'name' => 'Entry Scene',
          'quest_ids' => ['entry-quest'],
        ]],
      ]],
    ]);

    $runtime = [
      'schema_version' => StorylineManagerService::STORYLINE_RUNTIME_SCHEMA_VERSION,
      'storyline_type' => 'questline',
      'metadata' => $normalized['metadata'],
      'chapters' => $normalized['chapters'],
      'linked_quests' => $normalized['linked_quests'],
      'questline' => $normalized['questline'],
      'asset_references' => $normalized['asset_references'],
      'contacts' => $normalized['contacts'],
      'unlocked_chapter_ids' => ['entry-chapter'],
      'unlocked_scene_ids' => ['entry-scene'],
      'current_chapter_id' => 'entry-chapter',
      'current_scene_id' => 'entry-scene',
      'status' => 'active',
      'variables' => [],
    ];

    $runtime['linked_quests']['orphan-quest'] = [
      'quest_id' => 'orphan-quest',
      'chapter_id' => 'entry-chapter',
      'scene_id' => 'entry-scene',
      'status' => 'available',
    ];
    $runtime['questline']['ordered_quest_ids'][] = 'orphan-quest';
    $runtime['questline']['quest_nodes'][] = [
      'quest_id' => 'orphan-quest',
      'required_chapter_id' => 'entry-chapter',
      'required_scene_id' => 'entry-scene',
      'unlocks_after' => [],
      'unlocks_to' => [],
    ];

    $validation = $service->validateStorylineEndToEndContract($runtime, 'runtime');

    $this->assertFalse($validation['valid']);
    $this->assertFalse($validation['stages']['questline_progression']['valid']);
    $this->assertStringContainsString(
      "unreachable from primary quest",
      implode('; ', $validation['stages']['questline_progression']['errors'] ?? [])
    );
  }

  /**
   * @covers ::validateStorylineEndToEndContract
   */
  public function testValidateStorylineEndToEndContractRequiresEntryPointMetadata(): void {
    $service = $this->buildService();
    $normalize = new \ReflectionMethod(StorylineManagerService::class, 'normalizeTemplateDefinition');
    $normalize->setAccessible(TRUE);

    $normalized = $normalize->invoke($service, [
      'name' => 'Entry Point Required Story',
      'source' => 'npc-storyline-bootstrap',
      'contacts' => [[
        'contact_id' => 'quest-giver-contact',
        'entity_type' => 'npc_template',
        'entity_id' => 'entry-point-giver',
        'role' => 'quest_giver',
        'display_name' => 'Entry Point Giver',
        'attitude' => 'friendly',
      ]],
      'chapters' => [[
        'name' => 'Entry Chapter',
        'scenes' => [[
          'name' => 'Entry Scene',
          'quest_ids' => ['entry-quest'],
        ]],
      ]],
    ]);

    $runtime = [
      'schema_version' => StorylineManagerService::STORYLINE_RUNTIME_SCHEMA_VERSION,
      'storyline_type' => 'questline',
      'metadata' => $normalized['metadata'],
      'chapters' => $normalized['chapters'],
      'linked_quests' => $normalized['linked_quests'],
      'questline' => $normalized['questline'],
      'asset_references' => $normalized['asset_references'],
      'contacts' => $normalized['contacts'],
      'unlocked_chapter_ids' => ['entry-chapter'],
      'unlocked_scene_ids' => ['entry-scene'],
      'current_chapter_id' => 'entry-chapter',
      'current_scene_id' => 'entry-scene',
      'status' => 'active',
      'variables' => [],
    ];
    unset($runtime['metadata']['generated_outline']['entry_point']);

    $validation = $service->validateStorylineEndToEndContract($runtime, 'runtime');

    $this->assertFalse($validation['valid']);
    $this->assertFalse($validation['stages']['cross_references']['valid']);
    $this->assertStringContainsString(
      'entry_point is required',
      implode('; ', $validation['stages']['cross_references']['errors'] ?? [])
    );
  }

  /**
   * @covers ::validateStorylineEndToEndContract
   */
  public function testValidateStorylineEndToEndContractRequiresBrokerIntroductionEdgeWhenBrokered(): void {
    $service = $this->buildService();
    $normalize = new \ReflectionMethod(StorylineManagerService::class, 'normalizeTemplateDefinition');
    $normalize->setAccessible(TRUE);

    $normalized = $normalize->invoke($service, [
      'name' => 'Brokered Entry Story',
      'source' => 'npc-storyline-bootstrap',
      'contacts' => [
        [
          'contact_id' => 'eldric-broker',
          'entity_type' => 'campaign_npc',
          'entity_id' => 'npc_tavern_keeper',
          'role' => 'broker',
          'display_name' => 'Eldric',
          'attitude' => 'friendly',
          'introduces_to' => [],
        ],
        [
          'contact_id' => 'okoro-contact',
          'entity_type' => 'npc_template',
          'entity_id' => 'okoro',
          'role' => 'quest_giver',
          'display_name' => 'Okoro',
          'attitude' => 'friendly',
        ],
      ],
      'chapters' => [[
        'name' => 'Entry Chapter',
        'scenes' => [[
          'name' => 'Entry Scene',
          'quest_ids' => ['entry-quest'],
        ]],
      ]],
    ]);

    $runtime = [
      'schema_version' => StorylineManagerService::STORYLINE_RUNTIME_SCHEMA_VERSION,
      'storyline_type' => 'questline',
      'metadata' => $normalized['metadata'],
      'chapters' => $normalized['chapters'],
      'linked_quests' => $normalized['linked_quests'],
      'questline' => $normalized['questline'],
      'asset_references' => $normalized['asset_references'],
      'contacts' => $normalized['contacts'],
      'unlocked_chapter_ids' => ['entry-chapter'],
      'unlocked_scene_ids' => ['entry-scene'],
      'current_chapter_id' => 'entry-chapter',
      'current_scene_id' => 'entry-scene',
      'status' => 'active',
      'variables' => [],
    ];

    $runtime['metadata']['generated_outline']['entry_point']['introduction_path'] = 'brokered';
    $runtime['metadata']['generated_outline']['entry_point']['broker_id'] = 'npc_tavern_keeper';
    foreach ($runtime['contacts'] as &$contact) {
      if ((string) ($contact['entity_id'] ?? '') === 'npc_tavern_keeper') {
        $contact['introduces_to'] = [];
      }
    }
    unset($contact);

    $validation = $service->validateStorylineEndToEndContract($runtime, 'runtime');

    $this->assertFalse($validation['valid']);
    $this->assertFalse($validation['stages']['cross_references']['valid']);
    $this->assertStringContainsString(
      "must explicitly introduce primary quest giver",
      implode('; ', $validation['stages']['cross_references']['errors'] ?? [])
    );
  }

  /**
   * @covers ::validateStorylineEndToEndContract
   */
  public function testValidateStorylineEndToEndContractRejectsUnsupportedPayloadType(): void {
    $service = $this->buildService();
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Unsupported storyline payload type');

    $service->validateStorylineEndToEndContract([], 'unsupported');
  }

  /**
   * @covers ::validateQuestObjectiveControlChain
   */
  public function testValidateQuestObjectiveControlChainRejectsUnknownDependencies(): void {
    $service = $this->buildServiceWithObjectiveType();
    $method = new \ReflectionMethod(StorylineManagerService::class, 'validateQuestObjectiveControlChain');
    $method->setAccessible(TRUE);

    $errors = $method->invoke(
      $service,
      [[
        'phase' => 1,
        'objectives' => [[
          'objective_id' => 'speak-with-guide',
          'type' => 'interact',
          'description' => 'Speak with the guide.',
          'target' => 'npc-guide',
          'depends_on' => ['missing-objective'],
          'completion_criteria' => [
            'kind' => 'flag',
            'metric' => 'completed',
            'required_value' => TRUE,
            'description' => 'Complete after speaking with the guide.',
          ],
        ]],
      ]],
      [
        'target_ids' => ['npc-guide' => TRUE],
        'item_ids' => [],
        'location_ids' => [],
      ],
      'quest template dependency-check'
    );

    $this->assertNotEmpty($errors);
    $this->assertStringContainsString("depends_on references unknown objective 'missing-objective'", implode('; ', $errors));
  }

  /**
   * @covers ::validateQuestObjectiveControlChain
   */
  public function testValidateQuestObjectiveControlChainRejectsUnanchoredInteractionTargets(): void {
    $service = $this->buildServiceWithObjectiveType();
    $method = new \ReflectionMethod(StorylineManagerService::class, 'validateQuestObjectiveControlChain');
    $method->setAccessible(TRUE);

    $errors = $method->invoke(
      $service,
      [[
        'phase' => 1,
        'objectives' => [[
          'objective_id' => 'speak-with-guide',
          'type' => 'interact',
          'description' => 'Speak with the guide.',
          'target' => 'npc-missing',
          'completion_criteria' => [
            'kind' => 'flag',
            'metric' => 'completed',
            'required_value' => TRUE,
            'description' => 'Complete after speaking with the guide.',
          ],
        ]],
      ]],
      [
        'target_ids' => [],
        'item_ids' => [],
        'location_ids' => [],
      ],
      'quest template anchor-check'
    );

    $this->assertNotEmpty($errors);
    $this->assertStringContainsString("target 'npc-missing' is not anchored", implode('; ', $errors));
  }

  /**
   * @covers ::validateQuestObjectiveControlChain
   */
  public function testValidateQuestObjectiveControlChainRequiresHowTriggerNextStep(): void {
    $service = $this->buildServiceWithObjectiveType();
    $method = new \ReflectionMethod(StorylineManagerService::class, 'validateQuestObjectiveControlChain');
    $method->setAccessible(TRUE);

    $errors = $method->invoke(
      $service,
      [[
        'phase' => 1,
        'objectives' => [[
          'objective_id' => 'inspect-ledger',
          'type' => 'investigate',
          'description' => 'Inspect the ledger for clues.',
          'target' => 'campus-ledger',
          'completion_criteria' => [
            'kind' => 'count',
            'metric' => 'current',
            'target_count' => 1,
            'description' => 'Complete after one successful investigation pass.',
          ],
        ]],
      ]],
      [
        'target_ids' => ['campus-ledger' => TRUE],
        'item_ids' => [],
        'location_ids' => [],
      ],
      'quest template missing-how'
    );

    $this->assertNotEmpty($errors);
    $this->assertStringContainsString('next_step HOW trigger is required', implode('; ', $errors));
  }

  /**
   * @covers ::validateQuestObjectiveControlChain
   */
  public function testValidateQuestObjectiveControlChainAcceptsLinkedInteractionChain(): void {
    $service = $this->buildServiceWithObjectiveType();
    $method = new \ReflectionMethod(StorylineManagerService::class, 'validateQuestObjectiveControlChain');
    $method->setAccessible(TRUE);

    $errors = $method->invoke(
      $service,
      [[
        'phase' => 1,
        'objectives' => [
          [
            'objective_id' => 'speak-with-guide',
            'type' => 'interact',
            'description' => 'Speak with the guide.',
            'target' => 'npc-guide',
            'next_step' => 'Talk to the guide in the staging room.',
            'completion_criteria' => [
              'kind' => 'flag',
              'metric' => 'completed',
              'required_value' => TRUE,
              'description' => 'Complete after speaking with the guide.',
            ],
          ],
          [
            'objective_id' => 'reach-vault-entry',
            'type' => 'explore',
            'description' => 'Reach the vault entry.',
            'location' => 'Vault Entry',
            'location_id' => 'vault-entry-room',
            'depends_on' => ['speak-with-guide'],
            'next_step' => 'Move into the vault entry room.',
            'completion_criteria' => [
              'kind' => 'flag',
              'metric' => 'discovered',
              'required_value' => TRUE,
              'description' => 'Complete when vault entry is discovered.',
            ],
          ],
          [
            'objective_id' => 'recover-key',
            'type' => 'collect',
            'description' => 'Recover the vault key.',
            'item' => 'vault-key',
            'depends_on' => ['reach-vault-entry'],
            'next_step' => 'Search the room and loot the key.',
            'completion_criteria' => [
              'kind' => 'count',
              'metric' => 'current',
              'target_count' => 1,
              'description' => 'Collect the vault key.',
            ],
          ],
        ],
      ]],
      [
        'target_ids' => ['npc-guide' => TRUE],
        'item_ids' => ['vault-key' => TRUE],
        'location_ids' => ['vault-entry-room' => TRUE],
      ],
      'quest template valid-chain'
    );

    $this->assertSame([], $errors);
  }

  /**
   * @covers ::validateObjectiveControlChainForGeneratedTemplates
   */
  public function testValidateObjectiveControlChainForGeneratedTemplatesRejectsEmptyObjectiveSchemas(): void {
    $service = $this->buildServiceWithObjectiveType();

    $errors = $service->validateObjectiveControlChainForGeneratedTemplates(
      [
        'contacts' => [],
        'asset_references' => [],
        'chapters' => [],
      ],
      [
        [
          'template_id' => 'empty-template',
          'objectives_schema' => [],
        ],
      ]
    );

    $this->assertNotEmpty($errors);
    $this->assertStringContainsString(
      "Quest template 'empty-template' has an empty objectives_schema payload.",
      implode('; ', $errors)
    );
  }

  /**
   * @covers ::validateObjectiveControlChainForGeneratedTemplates
   */
  public function testValidateObjectiveControlChainForGeneratedTemplatesAcceptsValidGeneratedTemplatePayload(): void {
    $service = $this->buildServiceWithObjectiveType();

    $errors = $service->validateObjectiveControlChainForGeneratedTemplates(
      [
        'contacts' => [
          [
            'entity_id' => 'npc-guide',
          ],
        ],
        'asset_references' => [
          [
            'asset_type' => 'room',
            'asset_id' => 'vault-entry-room',
          ],
        ],
        'chapters' => [
          [
            'chapter_id' => 'vault-chapter',
            'scenes' => [
              [
                'scene_id' => 'vault-entry-room',
              ],
            ],
          ],
        ],
      ],
      [
        [
          'template_id' => 'generated-valid-template',
          'objectives_schema' => [
            [
              'phase' => 1,
              'objectives' => [
                [
                  'objective_id' => 'speak-with-guide',
                  'type' => 'interact',
                  'description' => 'Speak with the guide.',
                  'target' => 'npc-guide',
                  'next_step' => 'Talk to the guide in the entry chamber.',
                  'completion_criteria' => [
                    'kind' => 'flag',
                    'metric' => 'completed',
                    'required_value' => TRUE,
                    'description' => 'Complete after speaking with the guide.',
                  ],
                ],
              ],
            ],
          ],
        ],
      ]
    );

    $this->assertSame([], $errors);
  }

  /**
   * Builds a lightweight service instance.
   */
  private function buildService(): StorylineManagerService {
    $uuid = $this->createMock(UuidInterface::class);
    $uuid->method('generate')->willReturn('12345678-1234-1234-1234-1234567890ab');

    return new StorylineManagerService(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      $uuid,
      $this->createMock(CampaignStateService::class)
    );
  }

  /**
   * Builds a lightweight service instance with objective-type validation wired.
   */
  private function buildServiceWithObjectiveType(): StorylineManagerService {
    $uuid = $this->createMock(UuidInterface::class);
    $uuid->method('generate')->willReturn('12345678-1234-1234-1234-1234567890ab');

    return new StorylineManagerService(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      $uuid,
      $this->createMock(CampaignStateService::class),
      NULL,
      NULL,
      new ObjectiveTypeService(),
      NULL
    );
  }

  /**
   * Builds a logger factory mock returning a channel mock.
   */
  private function buildLoggerFactory(): LoggerChannelFactoryInterface {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($logger);
    return $factory;
  }

}
