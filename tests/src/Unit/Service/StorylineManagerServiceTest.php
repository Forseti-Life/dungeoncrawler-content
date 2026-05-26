<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\dungeoncrawler_content\Service\CampaignStateService;
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
   * Builds a logger factory mock returning a channel mock.
   */
  private function buildLoggerFactory(): LoggerChannelFactoryInterface {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($logger);
    return $factory;
  }

}
