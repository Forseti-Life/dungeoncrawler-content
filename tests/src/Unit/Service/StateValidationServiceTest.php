<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\StateValidationService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Covers schema-backed state validation for quest runtime payloads.
 *
 * @group dungeoncrawler_content
 * @group quest
 */
class StateValidationServiceTest extends UnitTestCase {

  private StateValidationService $service;

  protected function setUp(): void {
    parent::setUp();

    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $this->service = new StateValidationService($logger_factory);
  }

  /**
   * Verifies canonical quest summary payloads pass validation.
   */
  public function testValidateQuestSummaryAcceptsCanonicalPayload(): void {
    $payload = [
      'schema_version' => 'quest-summary-v1',
      'location_id' => 'tavern_entrance',
      'active' => [
        [
          'quest_id' => 'tok-find-the-missing-teacher_65_123',
          'quest_key' => 'tok-find-the-missing-teacher',
          'source_template_id' => 'tok-find-the-missing-teacher',
          'title' => 'Find the Missing Teacher',
          'quest_name' => 'Find the Missing Teacher',
          'status' => 'active',
          'current_phase' => 1,
          'generated_objectives' => [
            [
              'phase' => 1,
              'objectives' => [
                [
                  'objective_id' => 'identify_last_known_location',
                  'type' => 'investigate',
                  'description' => 'Determine where the teacher disappeared from the Magaambya.',
                  'completed' => FALSE,
                  'current' => 0,
                  'target_count' => 1,
                  'target' => 'Magaambya Campus',
                  'completion_criteria' => [
                    'kind' => 'all_children',
                    'metric' => 'children_completed',
                    'description' => 'Complete all nested objectives.',
                    'required_value' => TRUE,
                  ],
                  'children' => [
                    [
                      'objective_id' => 'question_the_gate_wardens',
                      'type' => 'investigate',
                      'description' => 'Question the gate wardens about the teacher.',
                      'completed' => FALSE,
                      'current' => 0,
                      'target_count' => 1,
                      'target' => 'Gate Wardens',
                      'completion_criteria' => [
                        'kind' => 'count',
                        'metric' => 'current',
                        'description' => 'Reach the required progress count.',
                        'target_count' => 1,
                      ],
                    ],
                  ],
                ],
              ],
            ],
          ],
          'objective_states' => [
            [
              'phase' => 1,
              'objectives' => [
                [
                  'objective_id' => 'identify_last_known_location',
                  'type' => 'investigate',
                  'description' => 'Determine where the teacher disappeared from the Magaambya.',
                  'completed' => FALSE,
                  'current' => 0,
                  'target_count' => 1,
                  'target' => 'Magaambya Campus',
                  'completion_criteria' => [
                    'kind' => 'all_children',
                    'metric' => 'children_completed',
                    'description' => 'Complete all nested objectives.',
                    'required_value' => TRUE,
                  ],
                  'children' => [
                    [
                      'objective_id' => 'question_the_gate_wardens',
                      'type' => 'investigate',
                      'description' => 'Question the gate wardens about the teacher.',
                      'completed' => FALSE,
                      'current' => 0,
                      'target_count' => 1,
                      'target' => 'Gate Wardens',
                      'completion_criteria' => [
                        'kind' => 'count',
                        'metric' => 'current',
                        'description' => 'Reach the required progress count.',
                        'target_count' => 1,
                      ],
                    ],
                  ],
                ],
              ],
            ],
          ],
          'generated_rewards' => ['xp' => 40, 'gold' => 0],
          'quest_data' => ['variables' => [], 'difficulty' => 'moderate'],
          'location_id' => 'tavern_entrance',
          'storyline' => [
            'storyline_id' => 'threshold-of-knowledge',
            'chapter_id' => 'magaambya-campus',
            'scene_id' => 'missing-teacher',
          ],
        ],
      ],
      'available' => [],
      'management_tree' => [],
      'counts' => [
        'active' => 1,
        'available' => 0,
      ],
    ];

    $result = $this->service->validateQuestSummary($payload);
    $this->assertTrue($result['valid'], implode('; ', $result['errors'] ?? []));
  }

  /**
   * Verifies quest updates must carry the explicit runtime contract fields.
   */
  public function testValidateQuestUpdateRejectsMissingSource(): void {
    $payload = [
      'schema_version' => 'quest-update-v1',
      'type' => 'quest_started',
      'quest_id' => 'ltba-enter-the-vault_65_123',
      'quest_name' => 'Enter the Vault',
      'status' => 'active',
      'objectives' => [
        'Reach the vault entry and begin the delve.',
      ],
      'storyline_id' => 'little-trouble-in-big-absalom',
    ];

    $result = $this->service->validateQuestUpdate($payload);
    $this->assertFalse($result['valid']);
    $this->assertStringContainsString('Missing required field: source', implode('; ', $result['errors'] ?? []));
  }

  /**
   * Verifies the service exposes a canonical contract registry.
   */
  public function testGetContractRegistryIncludesCanonicalRuntimeContracts(): void {
    $registry = $this->service->getContractRegistry();

    $this->assertArrayHasKey('storyline_definition', $registry);
    $this->assertSame('storyline_definition.schema.json', $registry['storyline_definition']['schema'] ?? NULL);
    $this->assertArrayHasKey('item_definition', $registry);
    $this->assertSame('item.schema.json', $registry['item_definition']['schema'] ?? NULL);
    $this->assertArrayHasKey('quest_update', $registry);
    $this->assertSame('quest_update.schema.json', $registry['quest_update']['schema'] ?? NULL);
    $this->assertArrayHasKey('objective_type_options', $registry);
    $this->assertSame('objective_type_options.schema.json', $registry['objective_type_options']['schema'] ?? NULL);
    $this->assertArrayHasKey('npc_quest_giver_policies', $registry);
    $this->assertSame('npc_quest_giver_policies.schema.json', $registry['npc_quest_giver_policies']['schema'] ?? NULL);
    $this->assertArrayHasKey('room_chat_response', $registry);
    $this->assertSame('room_chat_response.schema.json', $registry['room_chat_response']['schema'] ?? NULL);
  }

  /**
   * Verifies the canonical objective type options registry passes validation.
   */
  public function testValidateObjectiveTypeOptionsAcceptsCanonicalPayload(): void {
    $path = dirname(__DIR__, 4) . '/config/objective_type_options.json';
    $payload = json_decode((string) file_get_contents($path), TRUE);

    $result = $this->service->validateObjectiveTypeOptions($payload ?? []);
    $this->assertTrue($result['valid'], implode('; ', $result['errors'] ?? []));
  }

  /**
   * Verifies the canonical NPC quest-giver policy registry passes validation.
   */
  public function testValidateNpcQuestGiverPoliciesAcceptsCanonicalPayload(): void {
    $path = dirname(__DIR__, 4) . '/config/npc_quest_giver_policies.json';
    $payload = json_decode((string) file_get_contents($path), TRUE);

    $result = $this->service->validateNpcQuestGiverPolicies($payload ?? []);
    $this->assertTrue($result['valid'], implode('; ', $result['errors'] ?? []));
  }

  /**
   * Verifies canonical generated item payloads pass validation.
   */
  public function testValidateItemDefinitionAcceptsCanonicalPayload(): void {
    $payload = [
      'schema_version' => '1.0.0',
      'item_id' => 'storyline-relic',
      'name' => 'Storyline Relic',
      'item_type' => 'artifact',
      'level' => 1,
      'rarity' => 'common',
      'description' => 'A generated relic used as a storyline quest item.',
      'traits' => ['storyline', 'generated'],
    ];

    $result = $this->service->validateItemDefinition($payload);
    $this->assertTrue($result['valid'], implode('; ', $result['errors'] ?? []));
  }

  /**
   * Verifies generated storyline definitions pass validation.
   */
  public function testValidateStorylineDefinitionAcceptsCanonicalPayload(): void {
    $payload = [
      'schema_version' => 'storyline-definition-v1',
      'template_id' => 'generated-threshold',
      'name' => 'Generated Threshold',
      'synopsis' => 'A generated boss arc.',
      'level_range' => '1-4',
      'source' => 'storyline-generator',
      'tags' => ['generated', 'ruin'],
      'storyline_type' => 'questline',
      'metadata' => [
        'goal' => 'Stop the cult before it opens the gate.',
        'generated_outline' => [
          'generation_phase' => 'expanded',
          'goal' => 'Stop the cult before it opens the gate.',
          'big_boss' => [
            'boss_id' => 'gate-king',
            'name' => 'Gate King',
            'style' => 'void ruin',
            'dungeon_id' => 'throne-of-gates',
          ],
          'sub_bosses' => [
            [
              'boss_id' => 'ash-warden',
              'name' => 'Ash Warden',
              'style' => 'fortified ruin',
              'dungeon_id' => 'vault-of-ashes',
            ],
            [
              'boss_id' => 'echo-seer',
              'name' => 'Echo Seer',
              'style' => 'occult ruin',
              'dungeon_id' => 'catacomb-of-echoes',
            ],
          ],
          'dungeons' => [
            [
              'dungeon_id' => 'vault-of-ashes',
              'name' => 'Vault of Ashes',
              'boss_id' => 'ash-warden',
              'style' => 'fortified ruin',
              'entrance_room_id' => 'vault-of-ashes-room-1',
              'boss_room_id' => 'vault-of-ashes-room-5',
              'room_count' => 5,
              'rooms' => [
                [
                  'room_id' => 'vault-of-ashes-room-1',
                  'name' => 'Entrance',
                  'room_role' => 'entrance',
                  'style' => 'fortified ruin',
                  'summary' => 'Threshold room.',
                  'npc_ids' => ['vault-of-ashes-entrance-sentinel'],
                  'item_ids' => ['core-starter-adventure-entrance-cache'],
                  'encounter_connector' => ['threat_level' => 'low'],
                  'treasure_connector' => ['loot_table_id' => 'core_starter_adventure'],
                ],
              ],
            ],
            [
              'dungeon_id' => 'catacomb-of-echoes',
              'name' => 'Catacomb of Echoes',
              'boss_id' => 'echo-seer',
              'style' => 'occult ruin',
              'entrance_room_id' => 'catacomb-of-echoes-room-1',
              'boss_room_id' => 'catacomb-of-echoes-room-5',
              'room_count' => 5,
              'rooms' => [
                [
                  'room_id' => 'catacomb-of-echoes-room-1',
                  'name' => 'Entrance',
                  'room_role' => 'entrance',
                  'style' => 'occult ruin',
                  'summary' => 'Threshold room.',
                  'npc_ids' => ['catacomb-of-echoes-entrance-sentinel'],
                  'item_ids' => ['core-ruin-scavengers-entrance-cache'],
                  'encounter_connector' => ['threat_level' => 'low'],
                  'treasure_connector' => ['loot_table_id' => 'core_ruin_scavengers'],
                ],
              ],
            ],
            [
              'dungeon_id' => 'throne-of-gates',
              'name' => 'Throne of Gates',
              'boss_id' => 'gate-king',
              'style' => 'void throne',
              'entrance_room_id' => 'throne-of-gates-room-1',
              'boss_room_id' => 'throne-of-gates-room-5',
              'room_count' => 5,
              'rooms' => [
                [
                  'room_id' => 'throne-of-gates-room-1',
                  'name' => 'Entrance',
                  'room_role' => 'entrance',
                  'style' => 'void throne',
                  'summary' => 'Threshold room.',
                  'npc_ids' => ['throne-of-gates-entrance-sentinel'],
                  'item_ids' => ['gmg-story-treasures-entrance-cache'],
                  'encounter_connector' => ['threat_level' => 'low'],
                  'treasure_connector' => ['loot_table_id' => 'gmg_story_treasures'],
                ],
              ],
            ],
          ],
          'progression_connectors' => [
            [
              'connector_id' => 'generated-threshold-handoff-1',
              'source_type' => 'npc',
              'source_id' => 'generated-threshold-patron',
              'mechanism' => 'npc_direction',
              'clue_item_id' => 'vault-of-ashes-entrance-relic',
              'from_location_id' => 'tavern_entrance',
              'target_dungeon_id' => 'vault-of-ashes',
              'target_room_id' => 'vault-of-ashes-room-1',
              'narrative' => 'The quest giver directs the party to the first dungeon entrance.',
            ],
            [
              'connector_id' => 'generated-threshold-handoff-2',
              'source_type' => 'npc',
              'source_id' => 'ash-warden',
              'mechanism' => 'clue_or_confession',
              'clue_item_id' => 'vault-of-ashes-room-5-relic',
              'from_location_id' => 'vault-of-ashes-room-5',
              'target_dungeon_id' => 'catacomb-of-echoes',
              'target_room_id' => 'catacomb-of-echoes-room-1',
              'narrative' => 'Sub-boss 1 reveals or drops the clue to dungeon entrance 2.',
            ],
            [
              'connector_id' => 'generated-threshold-handoff-3',
              'source_type' => 'npc',
              'source_id' => 'echo-seer',
              'mechanism' => 'clue_or_confession',
              'clue_item_id' => 'catacomb-of-echoes-room-5-relic',
              'from_location_id' => 'catacomb-of-echoes-room-5',
              'target_dungeon_id' => 'throne-of-gates',
              'target_room_id' => 'throne-of-gates-room-1',
              'narrative' => 'Sub-boss 2 points the party to dungeon entrance 3.',
            ],
            [
              'connector_id' => 'generated-threshold-handoff-4',
              'source_type' => 'npc',
              'source_id' => 'gate-king',
              'mechanism' => 'goal_anchor',
              'clue_item_id' => 'throne-of-gates-room-5-relic',
              'from_location_id' => 'throne-of-gates-room-5',
              'target_dungeon_id' => 'throne-of-gates',
              'target_room_id' => 'throne-of-gates-room-5',
              'goal' => 'Stop the cult before it opens the gate.',
              'narrative' => 'The final boss directly embodies the campaign goal.',
            ],
          ],
        ],
      ],
      'chapters' => [
        [
          'chapter_id' => 'vault-of-ashes',
          'name' => 'Vault of Ashes',
          'summary' => 'Break the first seal.',
          'order' => 0,
          'quest_ids' => [],
          'asset_references' => [],
          'gates' => [],
          'scenes' => [
            [
              'scene_id' => 'vault-of-ashes-room-1',
              'name' => 'Entrance',
              'summary' => 'Threshold room.',
              'order' => 0,
              'quest_ids' => ['vault-of-ashes-room-1-quest'],
              'asset_references' => [],
              'gates' => [],
            ],
          ],
        ],
      ],
      'linked_quests' => [
        'vault-of-ashes-room-1-quest' => [
          'quest_id' => 'vault-of-ashes-room-1-quest',
          'chapter_id' => 'vault-of-ashes',
          'scene_id' => 'vault-of-ashes-room-1',
          'status' => 'available',
        ],
      ],
      'questline' => [
        'primary_quest_id' => 'vault-of-ashes-room-1-quest',
        'ordered_quest_ids' => ['vault-of-ashes-room-1-quest'],
        'quest_nodes' => [
          [
            'quest_id' => 'vault-of-ashes-room-1-quest',
            'chapter_id' => 'vault-of-ashes',
            'scene_id' => 'vault-of-ashes-room-1',
            'status' => 'available',
            'unlocks_after' => [],
            'unlocks_to' => [],
            'unlock_condition' => 'initially_available',
          ],
        ],
      ],
      'asset_references' => [
        [
          'asset_type' => 'dungeon',
          'asset_id' => 'vault-of-ashes',
          'asset_role' => 'boss-dungeon',
          'chapter_id' => '',
          'scene_id' => '',
          'source_scope' => 'storyline',
          'notes' => 'fortified ruin',
          'link_data' => [],
        ],
      ],
      'contacts' => [
        [
          'contact_id' => 'generated-patron',
          'entity_type' => 'npc_template',
          'entity_id' => 'generated-patron',
          'role' => 'quest_giver',
          'display_name' => 'Keeper Althea',
          'attitude' => 'friendly',
          'availability' => 'available',
          'notes' => 'Opens the generated arc.',
          'relationship_state' => [],
          'introduces_to' => [],
        ],
      ],
    ];

    $result = $this->service->validateStorylineDefinition($payload);
    $this->assertTrue($result['valid'], implode('; ', $result['errors'] ?? []));
  }

  /**
   * Verifies minimal bootstrap storyline definitions pass validation.
   */
  public function testValidateStorylineDefinitionAcceptsBootstrapPayload(): void {
    $payload = [
      'schema_version' => 'storyline-definition-v1',
      'template_id' => 'bootstrap-threshold',
      'name' => 'Bootstrap Threshold',
      'synopsis' => 'A minimal storyline bootstrap.',
      'level_range' => '1-3',
      'source' => 'storyline-bootstrap',
      'tags' => ['generated', 'bootstrap'],
      'storyline_type' => 'questline',
      'metadata' => [
        'goal' => 'Track the relic thieves.',
        'generated_outline' => [
          'generation_phase' => 'bootstrap',
          'goal' => 'Track the relic thieves.',
          'entry_dungeon' => [
            'dungeon_id' => 'bootstrap-threshold-entry-dungeon',
            'name' => 'Threshold of Bootstrap',
            'style' => 'threshold ruin',
            'entrance_room_id' => 'bootstrap-threshold-entry-dungeon-entrance',
            'lead_location_id' => 'tavern_entrance',
            'lead_location_hint' => 'Start at the tavern and follow the marked trail to the threshold.',
          ],
          'progression_connectors' => [
            [
              'connector_id' => 'bootstrap-threshold-handoff',
              'source_type' => 'npc',
              'source_id' => 'npc_tavern_keeper',
              'mechanism' => 'npc_direction',
              'target_dungeon_id' => 'bootstrap-threshold-entry-dungeon',
              'target_room_id' => 'bootstrap-threshold-entry-dungeon-entrance',
              'narrative' => 'The questgiver points the party to the first dungeon entrance.',
            ],
          ],
          'bootstrap_handoff' => [
            'speaker_npc_id' => 'npc_tavern_keeper',
            'speaker_name' => 'Eldric',
            'lead_text' => 'Start with the threshold trail beyond the tavern.',
          ],
        ],
      ],
      'chapters' => [
        [
          'chapter_id' => 'bootstrap-threshold-entry-dungeon',
          'name' => 'Threshold of Bootstrap',
          'summary' => 'Reach the first entrance.',
          'order' => 0,
          'quest_ids' => [],
          'asset_references' => [],
          'gates' => [],
          'scenes' => [
            [
              'scene_id' => 'bootstrap-threshold-entry-dungeon-entrance',
              'name' => 'Dungeon Entrance',
              'summary' => 'The first threshold.',
              'order' => 0,
              'quest_ids' => ['bootstrap-threshold-entry-dungeon-entrance-quest'],
              'asset_references' => [],
              'gates' => [],
            ],
          ],
        ],
      ],
      'linked_quests' => [
        'bootstrap-threshold-entry-dungeon-entrance-quest' => [
          'quest_id' => 'bootstrap-threshold-entry-dungeon-entrance-quest',
          'chapter_id' => 'bootstrap-threshold-entry-dungeon',
          'scene_id' => 'bootstrap-threshold-entry-dungeon-entrance',
          'status' => 'available',
        ],
      ],
      'questline' => [
        'primary_quest_id' => 'bootstrap-threshold-entry-dungeon-entrance-quest',
        'ordered_quest_ids' => ['bootstrap-threshold-entry-dungeon-entrance-quest'],
        'quest_nodes' => [
          [
            'quest_id' => 'bootstrap-threshold-entry-dungeon-entrance-quest',
            'chapter_id' => 'bootstrap-threshold-entry-dungeon',
            'scene_id' => 'bootstrap-threshold-entry-dungeon-entrance',
            'status' => 'available',
            'unlocks_after' => [],
            'unlocks_to' => [],
            'unlock_condition' => 'initially_available',
          ],
        ],
      ],
      'asset_references' => [
        [
          'asset_type' => 'dungeon',
          'asset_id' => 'bootstrap-threshold-entry-dungeon',
          'asset_role' => 'entry-dungeon',
          'chapter_id' => 'bootstrap-threshold-entry-dungeon',
          'scene_id' => '',
          'source_scope' => 'storyline',
          'notes' => 'First dungeon stub.',
          'link_data' => [],
        ],
      ],
      'contacts' => [
        [
          'contact_id' => 'bootstrap-threshold-questgiver',
          'entity_type' => 'campaign_npc',
          'entity_id' => 'npc_tavern_keeper',
          'role' => 'quest_giver',
          'display_name' => 'Eldric',
          'attitude' => 'friendly',
          'availability' => 'available',
          'notes' => 'Bootstraps the storyline.',
          'relationship_state' => [],
          'introduces_to' => [],
        ],
      ],
    ];

    $result = $this->service->validateStorylineDefinition($payload);
    $this->assertTrue($result['valid'], implode('; ', $result['errors'] ?? []));
  }

  /**
   * Verifies normalized bootstrap request payloads pass validation.
   */
  public function testValidateStorylineBootstrapRequestAcceptsNormalizedPayload(): void {
    $payload = [
      'prompt' => 'I want a storyline about relic thieves.',
      'name' => 'Relic Thief Trail',
      'level_range' => '1-4',
      'tone' => 'tense mystery',
      'theme' => 'ruined catacombs',
      'source' => 'npc-storyline-bootstrap',
      'template_id' => '',
      'entry_dungeon_id' => '',
      'entry_room_id' => '',
      'first_quest_id' => '',
      'speaker_npc_id' => 'npc_tavern_keeper',
      'speaker_name' => 'Eldric',
      'lead_location_id' => 'tavern_entrance',
      'tags' => ['generated', 'bootstrap'],
      'activate' => FALSE,
      'is_primary' => FALSE,
      'status' => 'bootstrapping',
      'priority' => 0,
    ];

    $result = $this->service->validateStorylineBootstrapRequest($payload);
    $this->assertTrue($result['valid'], implode('; ', $result['errors'] ?? []));
  }

  /**
   * Verifies expansion queue payloads pass validation.
   */
  public function testValidateStorylineExpansionJobAcceptsCanonicalPayload(): void {
    $payload = [
      'schema_version' => 'storyline-expansion-job-v1',
      'campaign_id' => 65,
      'storyline_id' => 'bootstrap-threshold-65',
      'request' => [
        'prompt' => 'Stop the relic cult before it opens the gate.',
        'name' => 'Bootstrap Threshold',
        'level_range' => '1-4',
        'tone' => 'occult ruin crawl',
        'theme' => 'threshold ruin',
        'source' => 'storyline-expansion',
        'template_id' => 'bootstrap-threshold',
        'entry_dungeon_id' => 'bootstrap-threshold-entry-dungeon',
        'entry_room_id' => 'bootstrap-threshold-entry-room',
        'first_quest_id' => 'bootstrap-threshold-entry-quest',
        'speaker_npc_id' => 'npc_tavern_keeper',
        'speaker_name' => 'Eldric',
        'lead_location_id' => 'tavern_entrance',
        'tags' => ['generated'],
        'activate' => FALSE,
        'is_primary' => FALSE,
        'status' => 'available',
        'priority' => 0,
      ],
    ];

    $result = $this->service->validateStorylineExpansionJob($payload);
    $this->assertTrue($result['valid'], implode('; ', $result['errors'] ?? []));
  }

  /**
   * Verifies storyline runtime questline payloads pass validation.
   */
  public function testValidateStorylineRuntimeAcceptsQuestlinePayload(): void {
    $payload = [
      'schema_version' => 'storyline-runtime-v1',
      'storyline_type' => 'questline',
      'metadata' => [
        'template_id' => 'generated-threshold',
        'name' => 'Generated Threshold',
        'synopsis' => 'A generated boss arc.',
        'level_range' => '1-4',
        'source' => 'storyline-generator',
        'tags' => ['generated', 'ruin'],
      ],
      'chapters' => [
        [
          'chapter_id' => 'vault-of-ashes',
          'name' => 'Vault of Ashes',
          'summary' => 'Break the first seal.',
          'order' => 0,
          'quest_ids' => [],
          'asset_references' => [],
          'gates' => [],
          'scenes' => [
            [
              'scene_id' => 'vault-of-ashes-room-1',
              'name' => 'Entrance',
              'summary' => 'Threshold room.',
              'order' => 0,
              'quest_ids' => ['vault-of-ashes-room-1-quest'],
              'asset_references' => [],
              'gates' => [],
            ],
          ],
        ],
      ],
      'linked_quests' => [
        'vault-of-ashes-room-1-quest' => [
          'quest_id' => 'vault-of-ashes-room-1-quest',
          'chapter_id' => 'vault-of-ashes',
          'scene_id' => 'vault-of-ashes-room-1',
          'status' => 'active',
        ],
      ],
      'questline' => [
        'primary_quest_id' => 'vault-of-ashes-room-1-quest',
        'ordered_quest_ids' => ['vault-of-ashes-room-1-quest'],
        'quest_nodes' => [
          [
            'quest_id' => 'vault-of-ashes-room-1-quest',
            'chapter_id' => 'vault-of-ashes',
            'scene_id' => 'vault-of-ashes-room-1',
            'status' => 'active',
            'unlocks_after' => [],
            'unlocks_to' => [],
            'unlock_condition' => 'initially_available',
          ],
        ],
      ],
      'asset_references' => [],
      'contacts' => [],
      'unlocked_chapter_ids' => ['vault-of-ashes'],
      'unlocked_scene_ids' => ['vault-of-ashes-room-1'],
      'current_chapter_id' => 'vault-of-ashes',
      'current_scene_id' => 'vault-of-ashes-room-1',
      'status' => 'active',
      'variables' => [],
    ];

    $result = $this->service->validateStorylineRuntime($payload);
    $this->assertTrue($result['valid'], implode('; ', $result['errors'] ?? []));
  }

}
