<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\Schema;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\CanonicalActionRegistryService;
use Drupal\dungeoncrawler_content\Service\SpellFeatActionDataValidatorService;
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
   * Verifies room-scoped actors must include an aligned last_room_id.
   */
  public function testValidateCanonicalActorLibraryContractsRejectsRoomWithoutLastRoomId(): void {
    $row = [
      'id' => 1176,
      'campaign_id' => 296,
      'character_id' => 0,
      'source_character_id' => 1033,
      'name' => 'Grandma',
      'level' => 1,
      'instance_id' => 'npc-ltba-grandmother',
      'type' => 'npc',
      'lifecycle_state' => 'campaign_npc',
      'location_type' => 'room',
      'location_ref' => 'ltba-grandmas-house-parlor',
      'last_room_id' => '',
      'status' => 1,
      'character_data' => json_encode(['content_id' => 'ltba-grandmother']),
    ];

    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAll')->willReturn([$row]);

    $query = $this->createMock(SelectInterface::class);
    $query->method('fields')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('orderBy')->willReturnSelf();
    $query->method('execute')->willReturn($statement);

    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')
      ->willReturnCallback(static fn(string $table): bool => $table === 'dc_campaign_characters');

    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);
    $database->method('select')
      ->with('dc_campaign_characters', 'c')
      ->willReturn($query);

    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);
    $service = new StateValidationService($logger_factory, $database);

    $result = $service->validateCanonicalActorLibraryContracts();
    $this->assertFalse($result['valid']);
    $this->assertStringContainsString(
      'last_room_id is required for location_type values outside global/roster.',
      implode('; ', $result['items'][0]['errors'] ?? [])
    );
  }

  /**
   * Verifies spell object validation delegates to the rules validator service.
   */
  public function testValidateSpellDefinitionDelegatesToRulesValidator(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);
    $rules_validator = $this->createMock(SpellFeatActionDataValidatorService::class);
    $rules_validator->expects($this->once())
      ->method('validateSpellDefinition')
      ->with(['id' => 'test_spell'])
      ->willReturn(['valid' => TRUE, 'errors' => []]);

    $service = new StateValidationService($logger_factory, NULL, NULL, $rules_validator);
    $result = $service->validateSpellDefinition(['id' => 'test_spell']);

    $this->assertTrue($result['valid']);
    $this->assertSame([], $result['errors']);
  }

  /**
   * Verifies canonical action contract report validates registry definitions.
   */
  public function testValidateCanonicalActionRegistryContractsBuildsValidationReport(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $rules_validator = $this->createMock(SpellFeatActionDataValidatorService::class);
    $rules_validator->method('validateActionDefinition')
      ->willReturn(['valid' => TRUE, 'errors' => []]);

    $action_registry = $this->createMock(CanonicalActionRegistryService::class);
    $action_registry->method('getCanonicalActions')
      ->willReturn([
        'cast_spell' => [
          'label' => 'Cast spell',
          'validator' => 'GameplayActionProcessor::validateCharacterActionResources',
          'executor' => 'GameplayActionProcessor::applyCharacterStateChanges',
          'scope' => 'character',
          'status' => 'active',
        ],
      ]);

    $service = new StateValidationService($logger_factory, NULL, NULL, $rules_validator, $action_registry);
    $result = $service->validateCanonicalActionRegistryContracts();

    $this->assertTrue($result['valid']);
    $this->assertSame(1, $result['summary']['total_items']);
    $this->assertSame(0, $result['summary']['invalid_items']);
    $this->assertSame('cast_spell', $result['items'][0]['content_id'] ?? NULL);
  }

  /**
   * Verifies canonical quest summary payloads pass validation.
   */
  public function testValidateQuestSummaryAcceptsCanonicalPayload(): void {
    $payload = [
      'schema_version' => 'quest-summary-v2',
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
                  'next_step' => 'Investigate the Magaambya Campus and gather reports about the missing teacher.',
                  'depends_on' => [],
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
                      'next_step' => 'Speak with the gate wardens and record what they observed.',
                      'depends_on' => ['identify_last_known_location'],
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
                  'next_step' => 'Investigate the Magaambya Campus and gather reports about the missing teacher.',
                  'depends_on' => [],
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
                      'next_step' => 'Speak with the gate wardens and record what they observed.',
                      'depends_on' => ['identify_last_known_location'],
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
      'offers' => [],
      'leads' => [],
      'completed' => [],
      'management_tree' => [],
      'counts' => [
        'active' => 1,
        'offers' => 0,
        'leads' => 0,
        'completed' => 0,
      ],
    ];

    $result = $this->service->validateQuestSummary($payload);
    $this->assertTrue($result['valid'], implode('; ', $result['errors'] ?? []));
  }

  /**
   * Verifies compact room runtime payloads normalize into the canonical state fragment.
   */
  public function testValidateRoomStateAcceptsCompactRuntimePayload(): void {
    $payload = [
      'roomId' => 'room_1_1_0',
      'dungeonId' => 'dungeon_1_1_1',
      'explored' => TRUE,
      'exploredAt' => '2026-05-20T17:00:00+00:00',
      'exploredByParty' => 'party-alpha',
      'isCleared' => FALSE,
      'looted' => FALSE,
      'trapsDisarmed' => TRUE,
      'visibility' => 'visible',
      'visibleHexIds' => ['hex-a', 'hex-b'],
    ];

    $result = $this->service->validateRoomState($payload);
    $this->assertTrue($result['valid'], implode('; ', $result['errors'] ?? []));
  }

  /**
   * Verifies invalid room runtime fragments still fail closed.
   */
  public function testValidateRoomStateRejectsInvalidVisibility(): void {
    $payload = [
      'explored' => TRUE,
      'visibility' => 'blinding',
    ];

    $result = $this->service->validateRoomState($payload);
    $this->assertFalse($result['valid']);
    $this->assertStringContainsString('visibility', implode('; ', $result['errors'] ?? []));
  }

  /**
   * Verifies room runtime date-time fields enforce canonical format constraints.
   */
  public function testValidateRoomStateRejectsInvalidExploredAtFormat(): void {
    $payload = [
      'explored' => TRUE,
      'exploredAt' => 'not-a-date',
      'visibility' => 'visible',
    ];

    $result = $this->service->validateRoomState($payload);
    $this->assertFalse($result['valid']);
    $this->assertStringContainsString('explored_at', implode('; ', $result['errors'] ?? []));
    $this->assertStringContainsString('valid date-time value', implode('; ', $result['errors'] ?? []));
  }

  /**
   * Verifies navigation receipts accept canonical room hex metadata.
   */
  public function testValidateNavigationReceiptAcceptsHexElevationAndObjects(): void {
    $payload = [
      'schema_version' => 'navigation-receipt-v1',
      'target_room_id' => 'tavern_entrance',
      'origin_room_id' => 'street_entry',
      'destination' => 'Tavern Entrance',
      'destination_description' => 'A warm tavern with stocked shelves and scattered tables.',
      'travel_type' => 'walk',
      'estimated_distance' => 'adjacent',
      'source' => 'room-chat',
      'template_id' => NULL,
      'room' => [
        'room_id' => 'tavern_entrance',
        'name' => 'The Gilded Tankard',
        'description' => 'A comfortable tavern room.',
        'hexes' => [
          [
            'q' => 0,
            'r' => 0,
            'elevation_ft' => 0,
            'objects' => [
              ['object_id' => 'table_round_a'],
            ],
          ],
          [
            'q' => 1,
            'r' => 0,
            'terrain_override' => 'raised_platform',
            'elevation_ft' => 5,
            'objects' => [],
          ],
        ],
        'terrain' => [
          'type' => 'stone_floor',
        ],
        'lighting' => 'bright',
        'room_type' => 'tavern',
        'size_category' => 'large',
        'gameplay_state' => [],
        'connections' => [
          [
            'target_room_id' => 'street_entry',
            'type' => 'door',
          ],
        ],
      ],
      'entities' => [],
      'connections' => [],
      'navigation_capabilities' => [
        [
          'connection_id' => 'street_entry__tavern_entrance',
          'origin_room_id' => 'tavern_entrance',
          'target_room_id' => 'street_entry',
          'type' => 'door',
          'available' => TRUE,
          'blocked_reason' => NULL,
          'is_discovered' => TRUE,
          'is_passable' => TRUE,
          'bidirectional' => TRUE,
          'requires_interaction' => FALSE,
          'travel_time_seconds' => 60,
          'origin_hex' => [
            'q' => 0,
            'r' => 0,
          ],
          'target_hex' => [
            'q' => 1,
            'r' => 0,
          ],
        ],
      ],
      'entry_hex' => [
        'q' => 0,
        'r' => 0,
      ],
    ];

    $result = $this->service->validateNavigationReceipt($payload);
    $this->assertTrue($result['valid'], implode('; ', $result['errors'] ?? []));
  }

  /**
   * Verifies compact dungeon runtime payloads normalize into the canonical level-state fragment.
   */
  public function testValidateDungeonStateAcceptsCompactRuntimePayload(): void {
    $payload = [
      'dungeonId' => 'dungeon_1_1_1',
      'isFullyGenerated' => TRUE,
      'roomsGenerated' => 8,
      'roomsExplored' => 3,
      'bossDefeated' => FALSE,
      'completionPercent' => 37.5,
      'firstEnteredAt' => '2026-05-20T17:00:00+00:00',
      'lastVisitedAt' => '2026-05-20T17:15:00+00:00',
      'timesVisited' => 2,
    ];

    $result = $this->service->validateDungeonState($payload);
    $this->assertTrue($result['valid'], implode('; ', $result['errors'] ?? []));
  }

  /**
   * Verifies dungeon runtime date-time fields enforce canonical format constraints.
   */
  public function testValidateDungeonStateRejectsInvalidFirstEnteredAtFormat(): void {
    $payload = [
      'isFullyGenerated' => TRUE,
      'roomsGenerated' => 8,
      'roomsExplored' => 3,
      'bossDefeated' => FALSE,
      'completionPercent' => 37.5,
      'firstEnteredAt' => 'bad-date',
      'lastVisitedAt' => '2026-05-20T17:15:00+00:00',
      'timesVisited' => 2,
    ];

    $result = $this->service->validateDungeonState($payload);
    $this->assertFalse($result['valid']);
    $this->assertStringContainsString('first_entered_at', implode('; ', $result['errors'] ?? []));
    $this->assertStringContainsString('valid date-time value', implode('; ', $result['errors'] ?? []));
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
    $this->assertSame('validateItemDefinition', $registry['item_definition']['validator'] ?? NULL);
    $this->assertSame('database', $registry['item_definition']['authority'] ?? NULL);
    $this->assertArrayHasKey('quest_update', $registry);
    $this->assertSame('quest_update.schema.json', $registry['quest_update']['schema'] ?? NULL);
    $this->assertArrayHasKey('objective_type_options', $registry);
    $this->assertSame('objective_type_options.schema.json', $registry['objective_type_options']['schema'] ?? NULL);
    $this->assertArrayHasKey('npc_quest_giver_policies', $registry);
    $this->assertSame('npc_quest_giver_policies.schema.json', $registry['npc_quest_giver_policies']['schema'] ?? NULL);
    $this->assertArrayHasKey('room_chat_response', $registry);
    $this->assertSame('room_chat_response.schema.json', $registry['room_chat_response']['schema'] ?? NULL);
    $this->assertArrayHasKey('navigation_receipt', $registry);
    $this->assertSame('navigation_receipt.schema.json', $registry['navigation_receipt']['schema'] ?? NULL);
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
    $row = [
      'content_id' => 'storyline-relic',
      'name' => 'Storyline Relic',
      'level' => 1,
      'rarity' => 'common',
      'schema_data' => json_encode([
        'item_id' => 'storyline-relic',
        'item_type' => 'artifact',
        'name' => 'Storyline Relic',
        'level' => 1,
        'rarity' => 'common',
      ]),
    ];
    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAssoc')->willReturn($row);
    $query = $this->createMock(SelectInterface::class);
    $query->method('fields')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn($statement);
    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')
      ->willReturnCallback(static fn(string $table): bool => $table === 'dungeoncrawler_content_registry');
    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);
    $database->method('select')
      ->with('dungeoncrawler_content_registry', 'r')
      ->willReturn($query);
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);
    $service = new StateValidationService($logger_factory, $database);

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

    $result = $service->validateItemDefinition($payload);
    $this->assertTrue($result['valid'], implode('; ', $result['errors'] ?? []));
  }

  /**
   * Verifies item_type-specific required contracts are enforced.
   */
  public function testValidateItemDefinitionRejectsWeaponWithoutWeaponStats(): void {
    $row = [
      'content_id' => 'broken-longsword',
      'name' => 'Broken Longsword',
      'level' => 1,
      'rarity' => 'common',
      'schema_data' => json_encode([
        'item_id' => 'broken-longsword',
        'item_type' => 'weapon',
        'name' => 'Broken Longsword',
        'level' => 1,
        'rarity' => 'common',
      ]),
    ];
    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAssoc')->willReturn($row);
    $query = $this->createMock(SelectInterface::class);
    $query->method('fields')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn($statement);
    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')
      ->willReturnCallback(static fn(string $table): bool => $table === 'dungeoncrawler_content_registry');
    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);
    $database->method('select')
      ->with('dungeoncrawler_content_registry', 'r')
      ->willReturn($query);
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);
    $service = new StateValidationService($logger_factory, $database);

    $payload = [
      'schema_version' => '1.0.0',
      'item_id' => 'broken-longsword',
      'name' => 'Broken Longsword',
      'item_type' => 'weapon',
      'level' => 1,
      'rarity' => 'common',
    ];

    $result = $service->validateItemDefinition($payload);
    $this->assertFalse($result['valid']);
    $this->assertContains('Missing required field: weapon_stats when item_type is weapon', $result['errors']);
  }

  /**
   * Verifies DB-backed canonical contracts remain authoritative for existing IDs.
   */
  public function testValidateItemDefinitionRejectsDatabaseContractMismatch(): void {
    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAssoc')->willReturn([
      'content_id' => 'storyline-relic',
      'name' => 'Canonical Storyline Relic',
      'level' => 2,
      'rarity' => 'rare',
      'schema_data' => json_encode([
        'item_type' => 'weapon',
        'name' => 'Canonical Storyline Relic',
        'level' => 2,
        'rarity' => 'rare',
      ]),
    ]);

    $query = $this->createMock(SelectInterface::class);
    $query->method('fields')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn($statement);

    $database = $this->createMock(Connection::class);
    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')
      ->willReturnCallback(static fn(string $table): bool => $table === 'dungeoncrawler_content_registry');
    $database->method('schema')->willReturn($schema);
    $database->method('select')
      ->with('dungeoncrawler_content_registry', 'r')
      ->willReturn($query);

    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);
    $service = new StateValidationService($logger_factory, $database);

    $payload = [
      'schema_version' => '1.0.0',
      'item_id' => 'storyline-relic',
      'name' => 'Storyline Relic',
      'item_type' => 'artifact',
      'level' => 1,
      'rarity' => 'common',
    ];

    $result = $service->validateItemDefinition($payload);
    $this->assertFalse($result['valid']);
    $this->assertStringContainsString('does not match canonical DB contract', implode('; ', $result['errors']));
  }

  /**
   * Verifies DB-authoritative item validation rejects missing canonical rows.
   */
  public function testValidateItemDefinitionRejectsWhenCanonicalRowMissing(): void {
    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAssoc')->willReturn(FALSE);
    $query = $this->createMock(SelectInterface::class);
    $query->method('fields')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn($statement);
    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')
      ->willReturnCallback(static fn(string $table): bool => $table === 'dungeoncrawler_content_registry');
    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);
    $database->method('select')
      ->with('dungeoncrawler_content_registry', 'r')
      ->willReturn($query);
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);
    $service = new StateValidationService($logger_factory, $database);

    $payload = [
      'schema_version' => '1.0.0',
      'item_id' => 'missing-item',
      'name' => 'Missing Item',
      'item_type' => 'artifact',
      'level' => 1,
      'rarity' => 'common',
    ];

    $result = $service->validateItemDefinition($payload);
    $this->assertFalse($result['valid']);
    $this->assertContains("Canonical DB contract not found for item 'missing-item'.", $result['errors']);
  }

  /**
   * Verifies canonical library item validation passes for valid registry rows.
   */
  public function testValidateCanonicalItemLibraryContractsAcceptsCanonicalRows(): void {
    $row = [
      'content_id' => 'storyline-relic',
      'name' => 'Storyline Relic',
      'level' => 1,
      'rarity' => 'common',
      'schema_data' => json_encode([
        'schema_version' => '1.0.0',
        'item_id' => 'storyline-relic',
        'name' => 'Storyline Relic',
        'item_type' => 'artifact',
        'level' => 1,
        'rarity' => 'common',
      ]),
    ];

    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAll')->willReturn([$row]);
    $statement->method('fetchAssoc')->willReturn($row);

    $query = $this->createMock(SelectInterface::class);
    $query->method('fields')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('orderBy')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn($statement);

    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')
      ->willReturnCallback(static fn(string $table): bool => $table === 'dungeoncrawler_content_registry');

    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);
    $database->method('select')
      ->with('dungeoncrawler_content_registry', 'r')
      ->willReturn($query);

    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);
    $service = new StateValidationService($logger_factory, $database);

    $result = $service->validateCanonicalItemLibraryContracts();
    $this->assertTrue($result['valid']);
    $this->assertSame(1, $result['summary']['total_items']);
    $this->assertSame(1, $result['summary']['valid_items']);
    $this->assertSame(0, $result['summary']['invalid_items']);
    $this->assertSame('storyline-relic', $result['items'][0]['content_id'] ?? NULL);
  }

  /**
   * Verifies canonical library item validation fails when registry table is missing.
   */
  public function testValidateCanonicalItemLibraryContractsFailsWhenRegistryTableMissing(): void {
    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')->willReturn(FALSE);

    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);

    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);
    $service = new StateValidationService($logger_factory, $database);

    $result = $service->validateCanonicalItemLibraryContracts();
    $this->assertFalse($result['valid']);
    $this->assertContains('Canonical content registry table dungeoncrawler_content_registry is unavailable.', $result['errors']);
  }

  /**
   * Verifies schema item_id/content_id mismatches fail canonical library validation.
   */
  public function testValidateCanonicalItemLibraryContractsRejectsSchemaIdMismatch(): void {
    $row = [
      'content_id' => 'canonical-item-id',
      'name' => 'Canonical Item',
      'level' => 1,
      'rarity' => 'common',
      'schema_data' => json_encode([
        'schema_version' => '1.0.0',
        'item_id' => 'different-item-id',
        'name' => 'Canonical Item',
        'item_type' => 'artifact',
        'level' => 1,
        'rarity' => 'common',
      ]),
    ];

    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAll')->willReturn([$row]);
    $statement->method('fetchAssoc')->willReturn(FALSE);

    $query = $this->createMock(SelectInterface::class);
    $query->method('fields')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('orderBy')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn($statement);

    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')
      ->willReturnCallback(static fn(string $table): bool => $table === 'dungeoncrawler_content_registry');

    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);
    $database->method('select')
      ->with('dungeoncrawler_content_registry', 'r')
      ->willReturn($query);

    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);
    $service = new StateValidationService($logger_factory, $database);

    $result = $service->validateCanonicalItemLibraryContracts();
    $this->assertFalse($result['valid']);
    $this->assertStringContainsString(
      "schema_data item_id/content_id 'different-item-id' must match registry content_id 'canonical-item-id'.",
      implode('; ', $result['items'][0]['errors'] ?? [])
    );
  }

  /**
   * Verifies canonical actor validation passes for valid actor rows.
   */
  public function testValidateCanonicalActorLibraryContractsAcceptsCanonicalRows(): void {
    $row = [
      'id' => 101,
      'campaign_id' => 307,
      'character_id' => 10,
      'source_character_id' => 10,
      'name' => 'Valeros',
      'level' => 3,
      'instance_id' => 'pc-307-valeros',
      'type' => 'pc',
      'lifecycle_state' => 'campaign_runtime',
      'location_type' => 'room',
      'location_ref' => 'vault-room-2',
      'last_room_id' => 'vault-room-2',
      'status' => 1,
      'character_data' => json_encode(['ability_scores' => ['str' => 18]]),
    ];

    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAll')->willReturn([$row]);

    $query = $this->createMock(SelectInterface::class);
    $query->method('fields')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('orderBy')->willReturnSelf();
    $query->method('execute')->willReturn($statement);

    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')
      ->willReturnCallback(static fn(string $table): bool => $table === 'dc_campaign_characters');

    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);
    $database->method('select')
      ->with('dc_campaign_characters', 'c')
      ->willReturn($query);

    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);
    $service = new StateValidationService($logger_factory, $database);

    $result = $service->validateCanonicalActorLibraryContracts();
    $this->assertTrue($result['valid']);
    $this->assertSame(1, $result['summary']['total_items']);
    $this->assertSame(1, $result['summary']['valid_items']);
    $this->assertSame(0, $result['summary']['invalid_items']);
  }

  /**
   * Verifies archived roster rows remain valid canonical actor records.
   */
  public function testValidateCanonicalActorLibraryContractsAcceptsArchivedRosterRows(): void {
    $row = [
      'id' => 197,
      'campaign_id' => 0,
      'character_id' => 0,
      'source_character_id' => NULL,
      'name' => 'Archived Character',
      'level' => 1,
      'instance_id' => 'pc-library-archived-197',
      'type' => 'pc',
      'lifecycle_state' => 'detached_roster',
      'location_type' => 'roster',
      'location_ref' => '',
      'last_room_id' => '',
      'status' => 2,
      'character_data' => json_encode(['profile' => ['name' => 'Archived Character']]),
    ];

    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAll')->willReturn([$row]);

    $query = $this->createMock(SelectInterface::class);
    $query->method('fields')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('orderBy')->willReturnSelf();
    $query->method('execute')->willReturn($statement);

    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')
      ->willReturnCallback(static fn(string $table): bool => $table === 'dc_campaign_characters');

    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);
    $database->method('select')
      ->with('dc_campaign_characters', 'c')
      ->willReturn($query);

    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);
    $service = new StateValidationService($logger_factory, $database);

    $result = $service->validateCanonicalActorLibraryContracts();
    $this->assertTrue($result['valid']);
    $this->assertSame(1, $result['summary']['total_items']);
    $this->assertSame(1, $result['summary']['valid_items']);
    $this->assertSame(0, $result['summary']['invalid_items']);
  }

  /**
   * Verifies room-scoped actors still require a location reference.
   */
  public function testValidateCanonicalActorLibraryContractsRejectsRoomWithoutLocationRef(): void {
    $row = [
      'id' => 1175,
      'campaign_id' => 296,
      'character_id' => 0,
      'source_character_id' => 1033,
      'name' => 'Mimi',
      'level' => 1,
      'instance_id' => 'familiar-1033',
      'type' => 'npc',
      'lifecycle_state' => 'campaign_npc',
      'location_type' => 'room',
      'location_ref' => '',
      'last_room_id' => '',
      'status' => 1,
      'character_data' => json_encode(['follower_kind' => 'familiar']),
    ];

    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAll')->willReturn([$row]);

    $query = $this->createMock(SelectInterface::class);
    $query->method('fields')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('orderBy')->willReturnSelf();
    $query->method('execute')->willReturn($statement);

    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')
      ->willReturnCallback(static fn(string $table): bool => $table === 'dc_campaign_characters');

    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);
    $database->method('select')
      ->with('dc_campaign_characters', 'c')
      ->willReturn($query);

    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);
    $service = new StateValidationService($logger_factory, $database);

    $result = $service->validateCanonicalActorLibraryContracts();
    $this->assertFalse($result['valid']);
    $this->assertStringContainsString(
      'location_ref is required for location_type values outside global/roster.',
      implode('; ', $result['items'][0]['errors'] ?? [])
    );
  }

  /**
   * Verifies canonical actor validation fails when actor table is missing.
   */
  public function testValidateCanonicalActorLibraryContractsFailsWhenTableMissing(): void {
    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')->willReturn(FALSE);

    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);

    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);
    $service = new StateValidationService($logger_factory, $database);

    $result = $service->validateCanonicalActorLibraryContracts();
    $this->assertFalse($result['valid']);
    $this->assertContains('Canonical actor table dc_campaign_characters is unavailable.', $result['errors']);
  }

  /**
   * Verifies canonical room validation passes for valid room rows.
   */
  public function testValidateCanonicalRoomLibraryContractsAcceptsCanonicalRows(): void {
    $row = [
      'room_id' => 'tpl_room_tavern_entrance',
      'name' => 'The Gilded Tankard',
      'description' => 'Entry room.',
      'environment_tags' => json_encode(['urban', 'tavern']),
      'layout_data' => json_encode([
        'hexes' => [
          [
            'q' => 0,
            'r' => 0,
            'elevation_ft' => 0,
            'objects' => [],
            'terrain_type' => 'wooden_floor',
            'lighting' => 'bright',
            'is_discovered' => TRUE,
            'is_visible' => TRUE,
            'is_entry' => TRUE,
          ],
          [
            'q' => 1,
            'r' => 0,
            'elevation_ft' => 0,
            'objects' => [],
            'terrain_type' => 'wooden_floor',
            'lighting' => 'bright',
            'is_discovered' => TRUE,
            'is_visible' => TRUE,
            'is_entry' => FALSE,
          ],
        ],
        'entry_points' => [['q' => 0, 'r' => 0, 'label' => 'Entry']],
        'exit_points' => [['q' => 1, 'r' => 0, 'label' => 'Exit']],
        'exits' => [['target_room_id' => 'tpl_room_tavern_backroom']],
      ]),
      'contents_data' => json_encode([
        'npcs' => [
          ['content_id' => 'npc_tavern_keeper'],
        ],
        'items' => [],
        'entities' => [],
        'obstacles' => [],
        'hazards' => [],
        'interactables' => [],
      ]),
      'source_room_id' => 'tpl_room_tavern_entrance',
    ];
    $linked_row = [
      'room_id' => 'tpl_room_tavern_backroom',
      'name' => 'The Gilded Tankard Backroom',
      'description' => 'Linked room.',
      'environment_tags' => json_encode(['urban', 'tavern']),
      'layout_data' => json_encode([
        'hexes' => [
          [
            'q' => 0,
            'r' => 0,
            'elevation_ft' => 0,
            'objects' => [],
            'terrain_type' => 'wooden_floor',
            'lighting' => 'bright',
            'is_discovered' => TRUE,
            'is_visible' => TRUE,
            'is_entry' => TRUE,
          ],
          [
            'q' => 1,
            'r' => 0,
            'elevation_ft' => 0,
            'objects' => [],
            'terrain_type' => 'wooden_floor',
            'lighting' => 'bright',
            'is_discovered' => TRUE,
            'is_visible' => TRUE,
            'is_entry' => FALSE,
          ],
        ],
        'entry_points' => [['q' => 0, 'r' => 0, 'label' => 'Entry']],
        'exit_points' => [['q' => 1, 'r' => 0, 'label' => 'Exit']],
        'exits' => [['target_room_id' => 'tpl_room_tavern_entrance']],
      ]),
      'contents_data' => json_encode([
        'npcs' => [],
        'items' => [],
        'entities' => [],
        'obstacles' => [],
        'hazards' => [],
        'interactables' => [],
      ]),
      'source_room_id' => 'tpl_room_tavern_backroom',
    ];

    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAll')->willReturn([$row, $linked_row]);

    $query = $this->createMock(SelectInterface::class);
    $query->method('fields')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('orderBy')->willReturnSelf();
    $query->method('execute')->willReturn($statement);

    $registry_statement = $this->createMock(StatementInterface::class);
    $registry_statement->method('fetchAll')->willReturn([
      ['content_id' => 'npc_tavern_keeper'],
    ]);

    $registry_query = $this->createMock(SelectInterface::class);
    $registry_query->method('fields')->willReturnSelf();
    $registry_query->method('condition')->willReturnSelf();
    $registry_query->method('orderBy')->willReturnSelf();
    $registry_query->method('execute')->willReturn($registry_statement);

    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')
      ->willReturnCallback(static fn(string $table): bool => in_array($table, ['dungeoncrawler_content_rooms', 'dungeoncrawler_content_registry'], TRUE));

    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);
    $database->method('select')
      ->willReturnCallback(static function (string $table, string $alias) use ($query, $registry_query): SelectInterface {
        if ($table === 'dungeoncrawler_content_rooms' && $alias === 'r') {
          return $query;
        }
        if ($table === 'dungeoncrawler_content_registry' && $alias === 'r') {
          return $registry_query;
        }
        throw new \InvalidArgumentException("Unexpected select target: {$table} {$alias}");
      });

    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);
    $service = new StateValidationService($logger_factory, $database);

    $result = $service->validateCanonicalRoomLibraryContracts();
    $this->assertTrue($result['valid']);
    $this->assertSame(2, $result['summary']['total_items']);
    $this->assertSame(2, $result['summary']['valid_items']);
    $this->assertSame(0, $result['summary']['invalid_items']);
    $this->assertSame('tpl_room_tavern_entrance', $result['items'][0]['content_id'] ?? NULL);
  }

  /**
   * Verifies canonical validation rejects rooms assigned to multiple dungeons.
   */
  public function testValidateCanonicalRoomLibraryContractsRejectsRoomInMultipleDungeons(): void {
    $row = [
      'room_id' => 'tpl_room_tavern_entrance',
      'name' => 'The Gilded Tankard',
      'description' => 'Entry room.',
      'environment_tags' => json_encode(['urban', 'tavern']),
      'layout_data' => json_encode([
        'hexes' => [
          ['q' => 0, 'r' => 0, 'terrain_type' => 'wooden_floor', 'lighting' => 'bright'],
          ['q' => 1, 'r' => 0, 'terrain_type' => 'wooden_floor', 'lighting' => 'bright'],
        ],
        'entry_points' => [['q' => 0, 'r' => 0, 'label' => 'Entry']],
        'exit_points' => [['q' => 1, 'r' => 0, 'label' => 'Exit']],
        'exits' => [['target_room_id' => 'tpl_room_tavern_backroom']],
      ]),
      'contents_data' => json_encode([
        'npcs' => [],
        'items' => [],
        'entities' => [],
        'obstacles' => [],
        'hazards' => [],
        'interactables' => [],
      ]),
      'source_room_id' => 'tpl_room_tavern_entrance',
    ];
    $linked_row = [
      'room_id' => 'tpl_room_tavern_backroom',
      'name' => 'The Gilded Tankard Backroom',
      'description' => 'Linked room.',
      'environment_tags' => json_encode(['urban', 'tavern']),
      'layout_data' => json_encode([
        'hexes' => [
          ['q' => 0, 'r' => 0, 'terrain_type' => 'wooden_floor', 'lighting' => 'bright'],
          ['q' => 1, 'r' => 0, 'terrain_type' => 'wooden_floor', 'lighting' => 'bright'],
        ],
        'entry_points' => [['q' => 0, 'r' => 0, 'label' => 'Entry']],
        'exit_points' => [['q' => 1, 'r' => 0, 'label' => 'Exit']],
        'exits' => [['target_room_id' => 'tpl_room_tavern_entrance']],
      ]),
      'contents_data' => json_encode([
        'npcs' => [],
        'items' => [],
        'entities' => [],
        'obstacles' => [],
        'hazards' => [],
        'interactables' => [],
      ]),
      'source_room_id' => 'tpl_room_tavern_backroom',
    ];

    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAll')->willReturn([$row, $linked_row]);

    $query = $this->createMock(SelectInterface::class);
    $query->method('fields')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('orderBy')->willReturnSelf();
    $query->method('execute')->willReturn($statement);

    $registry_statement = $this->createMock(StatementInterface::class);
    $registry_statement->method('fetchAll')->willReturn([]);

    $registry_query = $this->createMock(SelectInterface::class);
    $registry_query->method('fields')->willReturnSelf();
    $registry_query->method('condition')->willReturnSelf();
    $registry_query->method('orderBy')->willReturnSelf();
    $registry_query->method('execute')->willReturn($registry_statement);

    $dungeons_statement = $this->createMock(StatementInterface::class);
    $dungeons_statement->method('fetchAll')->willReturn([
      [
        'dungeon_id' => 'dun_a',
        'dungeon_data' => json_encode([
          'rooms' => [
            'tpl_room_tavern_entrance',
            'tpl_room_tavern_backroom',
          ],
          'hex_map' => [
            'connections' => [
              [
                'from_room' => 'tpl_room_tavern_entrance',
                'to_room' => 'tpl_room_tavern_backroom',
                'bidirectional' => TRUE,
              ],
            ],
          ],
        ]),
      ],
      [
        'dungeon_id' => 'dun_b',
        'dungeon_data' => json_encode([
          'rooms' => [
            'tpl_room_tavern_entrance',
            'tpl_room_tavern_backroom',
          ],
          'hex_map' => [
            'connections' => [
              [
                'from_room' => 'tpl_room_tavern_entrance',
                'to_room' => 'tpl_room_tavern_backroom',
                'bidirectional' => TRUE,
              ],
            ],
          ],
        ]),
      ],
    ]);

    $dungeons_query = $this->createMock(SelectInterface::class);
    $dungeons_query->method('fields')->willReturnSelf();
    $dungeons_query->method('orderBy')->willReturnSelf();
    $dungeons_query->method('execute')->willReturn($dungeons_statement);

    $connectors_statement = $this->createMock(StatementInterface::class);
    $connectors_statement->method('fetchAll')->willReturn([
      [
        'dungeon_id' => 'dun_a',
        'from_room_id' => 'tpl_room_tavern_entrance',
        'to_room_id' => 'tpl_room_tavern_backroom',
        'direction' => 'bidirectional',
        'default_state' => 'open',
        'from_hex_q' => 1,
        'from_hex_r' => 0,
        'to_hex_q' => 0,
        'to_hex_r' => 0,
      ],
      [
        'dungeon_id' => 'dun_b',
        'from_room_id' => 'tpl_room_tavern_entrance',
        'to_room_id' => 'tpl_room_tavern_backroom',
        'direction' => 'bidirectional',
        'default_state' => 'open',
        'from_hex_q' => 1,
        'from_hex_r' => 0,
        'to_hex_q' => 0,
        'to_hex_r' => 0,
      ],
    ]);

    $connectors_query = $this->createMock(SelectInterface::class);
    $connectors_query->method('fields')->willReturnSelf();
    $connectors_query->method('orderBy')->willReturnSelf();
    $connectors_query->method('execute')->willReturn($connectors_statement);

    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')
      ->willReturnCallback(static fn(string $table): bool => in_array($table, ['dungeoncrawler_content_rooms', 'dungeoncrawler_content_registry', 'dungeoncrawler_content_dungeons', 'dungeoncrawler_content_connections'], TRUE));
    $schema->method('fieldExists')
      ->willReturnCallback(static fn(string $table, string $field): bool => $table === 'dungeoncrawler_content_connections' && in_array($field, ['from_hex_q', 'from_hex_r', 'to_hex_q', 'to_hex_r'], TRUE));

    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);
    $database->method('select')
      ->willReturnCallback(static function (string $table, string $alias) use ($query, $registry_query, $dungeons_query, $connectors_query): SelectInterface {
        if ($table === 'dungeoncrawler_content_rooms' && $alias === 'r') {
          return $query;
        }
        if ($table === 'dungeoncrawler_content_registry' && $alias === 'r') {
          return $registry_query;
        }
        if ($table === 'dungeoncrawler_content_dungeons' && $alias === 'd') {
          return $dungeons_query;
        }
        if ($table === 'dungeoncrawler_content_connections' && $alias === 'c') {
          return $connectors_query;
        }
        throw new \InvalidArgumentException("Unexpected select target: {$table} {$alias}");
      });

    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);
    $service = new StateValidationService($logger_factory, $database);

    $result = $service->validateCanonicalRoomLibraryContracts();
    $this->assertFalse($result['valid']);
    $this->assertStringContainsString(
      "Room 'tpl_room_tavern_entrance' appears in multiple dungeons",
      implode('; ', $result['errors'] ?? [])
    );
  }

  /**
   * Verifies canonical validation rejects dungeon_data/connector direction mismatches.
   */
  public function testValidateCanonicalRoomLibraryContractsRejectsConnectorDirectionMismatch(): void {
    $row = [
      'room_id' => 'tpl_room_tavern_entrance',
      'name' => 'The Gilded Tankard',
      'description' => 'Entry room.',
      'environment_tags' => json_encode(['urban', 'tavern']),
      'layout_data' => json_encode([
        'hexes' => [
          ['q' => 0, 'r' => 0, 'elevation_ft' => 0, 'objects' => [], 'terrain_type' => 'wooden_floor', 'lighting' => 'bright', 'is_discovered' => TRUE, 'is_visible' => TRUE, 'is_entry' => TRUE],
          ['q' => 1, 'r' => 0, 'elevation_ft' => 0, 'objects' => [], 'terrain_type' => 'wooden_floor', 'lighting' => 'bright', 'is_discovered' => TRUE, 'is_visible' => TRUE, 'is_entry' => FALSE],
        ],
        'entry_points' => [['q' => 0, 'r' => 0, 'label' => 'Entry']],
        'exit_points' => [['q' => 1, 'r' => 0, 'label' => 'Exit']],
        'exits' => [['target_room_id' => 'tpl_room_tavern_backroom', 'q' => 1, 'r' => 0]],
      ]),
      'contents_data' => json_encode(['npcs' => [], 'items' => [], 'entities' => [], 'obstacles' => [], 'hazards' => [], 'interactables' => []]),
      'source_room_id' => 'tpl_room_tavern_entrance',
    ];
    $linked_row = [
      'room_id' => 'tpl_room_tavern_backroom',
      'name' => 'The Gilded Tankard Backroom',
      'description' => 'Linked room.',
      'environment_tags' => json_encode(['urban', 'tavern']),
      'layout_data' => json_encode([
        'hexes' => [
          ['q' => 0, 'r' => 0, 'elevation_ft' => 0, 'objects' => [], 'terrain_type' => 'wooden_floor', 'lighting' => 'bright', 'is_discovered' => TRUE, 'is_visible' => TRUE, 'is_entry' => TRUE],
          ['q' => 1, 'r' => 0, 'elevation_ft' => 0, 'objects' => [], 'terrain_type' => 'wooden_floor', 'lighting' => 'bright', 'is_discovered' => TRUE, 'is_visible' => TRUE, 'is_entry' => FALSE],
        ],
        'entry_points' => [['q' => 0, 'r' => 0, 'label' => 'Entry']],
        'exit_points' => [['q' => 1, 'r' => 0, 'label' => 'Exit']],
        'exits' => [['target_room_id' => 'tpl_room_tavern_entrance', 'q' => 0, 'r' => 0]],
      ]),
      'contents_data' => json_encode(['npcs' => [], 'items' => [], 'entities' => [], 'obstacles' => [], 'hazards' => [], 'interactables' => []]),
      'source_room_id' => 'tpl_room_tavern_backroom',
    ];

    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAll')->willReturn([$row, $linked_row]);

    $query = $this->createMock(SelectInterface::class);
    $query->method('fields')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('orderBy')->willReturnSelf();
    $query->method('execute')->willReturn($statement);

    $registry_statement = $this->createMock(StatementInterface::class);
    $registry_statement->method('fetchAll')->willReturn([]);
    $registry_query = $this->createMock(SelectInterface::class);
    $registry_query->method('fields')->willReturnSelf();
    $registry_query->method('condition')->willReturnSelf();
    $registry_query->method('orderBy')->willReturnSelf();
    $registry_query->method('execute')->willReturn($registry_statement);

    $dungeons_statement = $this->createMock(StatementInterface::class);
    $dungeons_statement->method('fetchAll')->willReturn([
      [
        'dungeon_id' => 'dun_dir',
        'dungeon_data' => json_encode([
          'rooms' => ['tpl_room_tavern_entrance', 'tpl_room_tavern_backroom'],
          'hex_map' => [
            'connections' => [
              [
                'from_room' => 'tpl_room_tavern_entrance',
                'to_room' => 'tpl_room_tavern_backroom',
                'bidirectional' => FALSE,
              ],
            ],
          ],
        ]),
      ],
    ]);
    $dungeons_query = $this->createMock(SelectInterface::class);
    $dungeons_query->method('fields')->willReturnSelf();
    $dungeons_query->method('orderBy')->willReturnSelf();
    $dungeons_query->method('execute')->willReturn($dungeons_statement);

    $connectors_statement = $this->createMock(StatementInterface::class);
    $connectors_statement->method('fetchAll')->willReturn([
      [
        'dungeon_id' => 'dun_dir',
        'from_room_id' => 'tpl_room_tavern_entrance',
        'to_room_id' => 'tpl_room_tavern_backroom',
        'direction' => 'bidirectional',
        'default_state' => 'open',
        'from_hex_q' => 1,
        'from_hex_r' => 0,
        'to_hex_q' => 0,
        'to_hex_r' => 0,
      ],
    ]);
    $connectors_query = $this->createMock(SelectInterface::class);
    $connectors_query->method('fields')->willReturnSelf();
    $connectors_query->method('orderBy')->willReturnSelf();
    $connectors_query->method('execute')->willReturn($connectors_statement);

    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')
      ->willReturnCallback(static fn(string $table): bool => in_array($table, ['dungeoncrawler_content_rooms', 'dungeoncrawler_content_registry', 'dungeoncrawler_content_dungeons', 'dungeoncrawler_content_connections'], TRUE));
    $schema->method('fieldExists')
      ->willReturnCallback(static fn(string $table, string $field): bool => $table === 'dungeoncrawler_content_connections' && in_array($field, ['from_hex_q', 'from_hex_r', 'to_hex_q', 'to_hex_r'], TRUE));

    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);
    $database->method('select')
      ->willReturnCallback(static function (string $table, string $alias) use ($query, $registry_query, $dungeons_query, $connectors_query): SelectInterface {
        if ($table === 'dungeoncrawler_content_rooms' && $alias === 'r') {
          return $query;
        }
        if ($table === 'dungeoncrawler_content_registry' && $alias === 'r') {
          return $registry_query;
        }
        if ($table === 'dungeoncrawler_content_dungeons' && $alias === 'd') {
          return $dungeons_query;
        }
        if ($table === 'dungeoncrawler_content_connections' && $alias === 'c') {
          return $connectors_query;
        }
        throw new \InvalidArgumentException("Unexpected select target: {$table} {$alias}");
      });

    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);
    $service = new StateValidationService($logger_factory, $database);

    $result = $service->validateCanonicalRoomLibraryContracts();
    $this->assertFalse($result['valid']);
    $this->assertStringContainsString(
      "connection direction mismatch for 'tpl_room_tavern_entrance' -> 'tpl_room_tavern_backroom'",
      implode('; ', $result['errors'] ?? [])
    );
  }

  /**
   * Verifies canonical room validation fails when room table is missing.
   */
  public function testValidateCanonicalRoomLibraryContractsFailsWhenTableMissing(): void {
    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')->willReturn(FALSE);

    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);

    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);
    $service = new StateValidationService($logger_factory, $database);

    $result = $service->validateCanonicalRoomLibraryContracts();
    $this->assertFalse($result['valid']);
    $this->assertContains('Canonical room table dungeoncrawler_content_rooms is unavailable.', $result['errors']);
  }

  /**
   * Verifies canonical room validation rejects rows missing required layout hexes.
   */
  public function testValidateCanonicalRoomLibraryContractsRejectsMissingLayoutHexes(): void {
    $row = [
      'room_id' => 'tpl_room_incomplete',
      'name' => 'Incomplete Room',
      'description' => 'Incomplete room.',
      'environment_tags' => json_encode(['test']),
      'layout_data' => json_encode([
        'hexes' => [],
        'entry_points' => [['q' => 0, 'r' => 0]],
        'exit_points' => [['q' => 1, 'r' => 0]],
      ]),
      'contents_data' => json_encode([
        'npcs' => [],
        'items' => [],
        'entities' => [],
        'obstacles' => [],
        'hazards' => [],
        'interactables' => [],
      ]),
      'source_room_id' => 'tpl_room_incomplete',
    ];

    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAll')->willReturn([$row]);

    $query = $this->createMock(SelectInterface::class);
    $query->method('fields')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('orderBy')->willReturnSelf();
    $query->method('execute')->willReturn($statement);

    $registry_statement = $this->createMock(StatementInterface::class);
    $registry_statement->method('fetchAll')->willReturn([]);

    $registry_query = $this->createMock(SelectInterface::class);
    $registry_query->method('fields')->willReturnSelf();
    $registry_query->method('condition')->willReturnSelf();
    $registry_query->method('orderBy')->willReturnSelf();
    $registry_query->method('execute')->willReturn($registry_statement);

    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')
      ->willReturnCallback(static fn(string $table): bool => in_array($table, ['dungeoncrawler_content_rooms', 'dungeoncrawler_content_registry'], TRUE));

    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);
    $database->method('select')
      ->willReturnCallback(static function (string $table, string $alias) use ($query, $registry_query): SelectInterface {
        if ($table === 'dungeoncrawler_content_rooms' && $alias === 'r') {
          return $query;
        }
        if ($table === 'dungeoncrawler_content_registry' && $alias === 'r') {
          return $registry_query;
        }
        throw new \InvalidArgumentException("Unexpected select target: {$table} {$alias}");
      });

    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);
    $service = new StateValidationService($logger_factory, $database);

    $result = $service->validateCanonicalRoomLibraryContracts();
    $this->assertFalse($result['valid']);
    $this->assertStringContainsString(
      'layout_data.hexes must define at least one hex.',
      implode('; ', $result['items'][0]['errors'] ?? [])
    );
  }

  /**
   * Verifies canonical room validation requires explicit links to another room.
   */
  public function testValidateCanonicalRoomLibraryContractsRejectsMissingRoomExitLinks(): void {
    $row = [
      'room_id' => 'tpl_room_missing_links',
      'name' => 'Missing Link Room',
      'description' => 'Missing linked exits.',
      'environment_tags' => json_encode(['test']),
      'layout_data' => json_encode([
        'hexes' => [
          [
            'q' => 0,
            'r' => 0,
            'elevation_ft' => 0,
            'objects' => [],
            'terrain_type' => 'stone_floor',
            'lighting' => 'dim',
            'is_discovered' => TRUE,
            'is_visible' => TRUE,
            'is_entry' => TRUE,
          ],
          [
            'q' => 1,
            'r' => 0,
            'elevation_ft' => 0,
            'objects' => [],
            'terrain_type' => 'stone_floor',
            'lighting' => 'dim',
            'is_discovered' => TRUE,
            'is_visible' => TRUE,
            'is_entry' => FALSE,
          ],
        ],
        'entry_points' => [['q' => 0, 'r' => 0]],
        'exit_points' => [['q' => 1, 'r' => 0]],
      ]),
      'contents_data' => json_encode([
        'npcs' => [],
        'items' => [],
        'entities' => [],
        'obstacles' => [],
        'hazards' => [],
        'interactables' => [],
      ]),
      'source_room_id' => 'tpl_room_missing_links',
    ];
    $linked_row = [
      'room_id' => 'tpl_room_missing_links_peer',
      'name' => 'Peer Room',
      'description' => 'Peer room for linkage checks.',
      'environment_tags' => json_encode(['test']),
      'layout_data' => json_encode([
        'hexes' => [
          [
            'q' => 0,
            'r' => 0,
            'elevation_ft' => 0,
            'objects' => [],
            'terrain_type' => 'stone_floor',
            'lighting' => 'dim',
            'is_discovered' => TRUE,
            'is_visible' => TRUE,
            'is_entry' => TRUE,
          ],
          [
            'q' => 1,
            'r' => 0,
            'elevation_ft' => 0,
            'objects' => [],
            'terrain_type' => 'stone_floor',
            'lighting' => 'dim',
            'is_discovered' => TRUE,
            'is_visible' => TRUE,
            'is_entry' => FALSE,
          ],
        ],
        'entry_points' => [['q' => 0, 'r' => 0]],
        'exit_points' => [['q' => 1, 'r' => 0]],
        'exits' => [['target_room_id' => 'tpl_room_missing_links']],
      ]),
      'contents_data' => json_encode([
        'npcs' => [],
        'items' => [],
        'entities' => [],
        'obstacles' => [],
        'hazards' => [],
        'interactables' => [],
      ]),
      'source_room_id' => 'tpl_room_missing_links_peer',
    ];

    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAll')->willReturn([$row, $linked_row]);

    $query = $this->createMock(SelectInterface::class);
    $query->method('fields')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('orderBy')->willReturnSelf();
    $query->method('execute')->willReturn($statement);

    $registry_statement = $this->createMock(StatementInterface::class);
    $registry_statement->method('fetchAll')->willReturn([]);

    $registry_query = $this->createMock(SelectInterface::class);
    $registry_query->method('fields')->willReturnSelf();
    $registry_query->method('condition')->willReturnSelf();
    $registry_query->method('orderBy')->willReturnSelf();
    $registry_query->method('execute')->willReturn($registry_statement);

    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')
      ->willReturnCallback(static fn(string $table): bool => in_array($table, ['dungeoncrawler_content_rooms', 'dungeoncrawler_content_registry'], TRUE));

    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);
    $database->method('select')
      ->willReturnCallback(static function (string $table, string $alias) use ($query, $registry_query): SelectInterface {
        if ($table === 'dungeoncrawler_content_rooms' && $alias === 'r') {
          return $query;
        }
        if ($table === 'dungeoncrawler_content_registry' && $alias === 'r') {
          return $registry_query;
        }
        throw new \InvalidArgumentException("Unexpected select target: {$table} {$alias}");
      });

    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);
    $service = new StateValidationService($logger_factory, $database);

    $result = $service->validateCanonicalRoomLibraryContracts();
    $this->assertFalse($result['valid']);
    $this->assertStringContainsString(
      'layout_data.exits must define at least one linked target_room_id.',
      implode('; ', $result['items'][0]['errors'] ?? [])
    );
  }

  /**
   * Verifies canonical room validation rejects blocked prompt-derived room IDs.
   */
  public function testValidateCanonicalRoomLibraryContractsRejectsBlockedPromptDerivedRoomId(): void {
    $row = [
      'room_id' => 'i-want-prompt-room',
      'name' => 'Prompt Room',
      'description' => 'Prompt room.',
      'environment_tags' => json_encode(['test']),
      'layout_data' => json_encode([
        'hexes' => [
          [
            'q' => 0,
            'r' => 0,
            'elevation_ft' => 0,
            'objects' => [],
            'terrain_type' => 'stone_floor',
            'lighting' => 'dim',
            'is_discovered' => TRUE,
            'is_visible' => TRUE,
            'is_entry' => TRUE,
          ],
          [
            'q' => 1,
            'r' => 0,
            'elevation_ft' => 0,
            'objects' => [],
            'terrain_type' => 'stone_floor',
            'lighting' => 'dim',
            'is_discovered' => TRUE,
            'is_visible' => TRUE,
            'is_entry' => FALSE,
          ],
        ],
        'entry_points' => [['q' => 0, 'r' => 0]],
        'exit_points' => [['q' => 1, 'r' => 0]],
      ]),
      'contents_data' => json_encode([
        'npcs' => [],
        'items' => [],
        'entities' => [],
        'obstacles' => [],
        'hazards' => [],
        'interactables' => [],
      ]),
      'source_room_id' => 'i-want-prompt-room',
    ];

    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAll')->willReturn([$row]);

    $query = $this->createMock(SelectInterface::class);
    $query->method('fields')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('orderBy')->willReturnSelf();
    $query->method('execute')->willReturn($statement);

    $registry_statement = $this->createMock(StatementInterface::class);
    $registry_statement->method('fetchAll')->willReturn([]);

    $registry_query = $this->createMock(SelectInterface::class);
    $registry_query->method('fields')->willReturnSelf();
    $registry_query->method('condition')->willReturnSelf();
    $registry_query->method('orderBy')->willReturnSelf();
    $registry_query->method('execute')->willReturn($registry_statement);

    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')
      ->willReturnCallback(static fn(string $table): bool => in_array($table, ['dungeoncrawler_content_rooms', 'dungeoncrawler_content_registry'], TRUE));

    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);
    $database->method('select')
      ->willReturnCallback(static function (string $table, string $alias) use ($query, $registry_query): SelectInterface {
        if ($table === 'dungeoncrawler_content_rooms' && $alias === 'r') {
          return $query;
        }
        if ($table === 'dungeoncrawler_content_registry' && $alias === 'r') {
          return $registry_query;
        }
        throw new \InvalidArgumentException("Unexpected select target: {$table} {$alias}");
      });

    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);
    $service = new StateValidationService($logger_factory, $database);

    $result = $service->validateCanonicalRoomLibraryContracts();
    $this->assertFalse($result['valid']);
    $this->assertStringContainsString(
      'uses a blocked prompt-derived prefix',
      implode('; ', $result['items'][0]['errors'] ?? [])
    );
  }

  /**
   * Verifies canonical room validation rejects disconnected entry/exit paths.
   */
  public function testValidateCanonicalRoomLibraryContractsRejectsDisconnectedPath(): void {
    $row = [
      'room_id' => 'tpl_room_disconnected_path',
      'name' => 'Disconnected Path Room',
      'description' => 'Disconnected path room.',
      'environment_tags' => json_encode(['test']),
      'layout_data' => json_encode([
        'hexes' => [
          [
            'q' => 0,
            'r' => 0,
            'elevation_ft' => 0,
            'objects' => [],
            'terrain_type' => 'stone_floor',
            'lighting' => 'dim',
            'is_discovered' => TRUE,
            'is_visible' => TRUE,
            'is_entry' => TRUE,
          ],
          [
            'q' => 5,
            'r' => 5,
            'elevation_ft' => 0,
            'objects' => [],
            'terrain_type' => 'stone_floor',
            'lighting' => 'dim',
            'is_discovered' => TRUE,
            'is_visible' => TRUE,
            'is_entry' => FALSE,
          ],
        ],
        'entry_points' => [['q' => 0, 'r' => 0]],
        'exit_points' => [['q' => 5, 'r' => 5]],
      ]),
      'contents_data' => json_encode([
        'npcs' => [],
        'items' => [],
        'entities' => [],
        'obstacles' => [],
        'hazards' => [],
        'interactables' => [],
      ]),
      'source_room_id' => 'tpl_room_disconnected_path',
    ];

    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAll')->willReturn([$row]);

    $query = $this->createMock(SelectInterface::class);
    $query->method('fields')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('orderBy')->willReturnSelf();
    $query->method('execute')->willReturn($statement);

    $registry_statement = $this->createMock(StatementInterface::class);
    $registry_statement->method('fetchAll')->willReturn([]);

    $registry_query = $this->createMock(SelectInterface::class);
    $registry_query->method('fields')->willReturnSelf();
    $registry_query->method('condition')->willReturnSelf();
    $registry_query->method('orderBy')->willReturnSelf();
    $registry_query->method('execute')->willReturn($registry_statement);

    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')
      ->willReturnCallback(static fn(string $table): bool => in_array($table, ['dungeoncrawler_content_rooms', 'dungeoncrawler_content_registry'], TRUE));

    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);
    $database->method('select')
      ->willReturnCallback(static function (string $table, string $alias) use ($query, $registry_query): SelectInterface {
        if ($table === 'dungeoncrawler_content_rooms' && $alias === 'r') {
          return $query;
        }
        if ($table === 'dungeoncrawler_content_registry' && $alias === 'r') {
          return $registry_query;
        }
        throw new \InvalidArgumentException("Unexpected select target: {$table} {$alias}");
      });

    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);
    $service = new StateValidationService($logger_factory, $database);

    $result = $service->validateCanonicalRoomLibraryContracts();
    $this->assertFalse($result['valid']);
    $this->assertStringContainsString(
      'must provide at least one traversable path from an entry point to an exit point.',
      implode('; ', $result['items'][0]['errors'] ?? [])
    );
  }

  /**
   * Verifies non-registry obstacle content_id values are accepted for room-local objects.
   */
  public function testValidateCanonicalRoomLibraryContractsAcceptsRoomLocalObstacleContentIds(): void {
    $row = [
      'room_id' => 'tpl_room_local_obstacle',
      'name' => 'Local Obstacle Room',
      'description' => 'Obstacle IDs can be room-local.',
      'environment_tags' => json_encode(['test']),
      'layout_data' => json_encode([
        'hexes' => [
          [
            'q' => 0,
            'r' => 0,
            'elevation_ft' => 0,
            'objects' => [],
            'terrain_type' => 'stone_floor',
            'lighting' => 'dim',
            'is_discovered' => TRUE,
            'is_visible' => TRUE,
            'is_entry' => TRUE,
          ],
          [
            'q' => 1,
            'r' => 0,
            'elevation_ft' => 0,
            'objects' => [],
            'terrain_type' => 'stone_floor',
            'lighting' => 'dim',
            'is_discovered' => TRUE,
            'is_visible' => TRUE,
            'is_entry' => FALSE,
          ],
        ],
        'entry_points' => [['q' => 0, 'r' => 0]],
        'exit_points' => [['q' => 1, 'r' => 0]],
        'exits' => [['target_room_id' => 'tpl_room_local_obstacle_linked']],
      ]),
      'contents_data' => json_encode([
        'npcs' => [],
        'items' => [],
        'entities' => [],
        'obstacles' => [
          ['content_id' => 'local_bar_counter', 'label' => 'Bar Counter'],
        ],
        'hazards' => [],
        'interactables' => [],
      ]),
      'source_room_id' => 'tpl_room_local_obstacle',
    ];
    $linked_row = [
      'room_id' => 'tpl_room_local_obstacle_linked',
      'name' => 'Linked Local Obstacle Room',
      'description' => 'Linked room.',
      'environment_tags' => json_encode(['test']),
      'layout_data' => json_encode([
        'hexes' => [
          [
            'q' => 0,
            'r' => 0,
            'elevation_ft' => 0,
            'objects' => [],
            'terrain_type' => 'stone_floor',
            'lighting' => 'dim',
            'is_discovered' => TRUE,
            'is_visible' => TRUE,
            'is_entry' => TRUE,
          ],
          [
            'q' => 1,
            'r' => 0,
            'elevation_ft' => 0,
            'objects' => [],
            'terrain_type' => 'stone_floor',
            'lighting' => 'dim',
            'is_discovered' => TRUE,
            'is_visible' => TRUE,
            'is_entry' => FALSE,
          ],
        ],
        'entry_points' => [['q' => 0, 'r' => 0]],
        'exit_points' => [['q' => 1, 'r' => 0]],
        'exits' => [['target_room_id' => 'tpl_room_local_obstacle']],
      ]),
      'contents_data' => json_encode([
        'npcs' => [],
        'items' => [],
        'entities' => [],
        'obstacles' => [],
        'hazards' => [],
        'interactables' => [],
      ]),
      'source_room_id' => 'tpl_room_local_obstacle_linked',
    ];

    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAll')->willReturn([$row, $linked_row]);

    $query = $this->createMock(SelectInterface::class);
    $query->method('fields')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('orderBy')->willReturnSelf();
    $query->method('execute')->willReturn($statement);

    $registry_statement = $this->createMock(StatementInterface::class);
    $registry_statement->method('fetchAll')->willReturn([]);

    $registry_query = $this->createMock(SelectInterface::class);
    $registry_query->method('fields')->willReturnSelf();
    $registry_query->method('condition')->willReturnSelf();
    $registry_query->method('orderBy')->willReturnSelf();
    $registry_query->method('execute')->willReturn($registry_statement);

    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')
      ->willReturnCallback(static fn(string $table): bool => in_array($table, ['dungeoncrawler_content_rooms', 'dungeoncrawler_content_registry'], TRUE));

    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);
    $database->method('select')
      ->willReturnCallback(static function (string $table, string $alias) use ($query, $registry_query): SelectInterface {
        if ($table === 'dungeoncrawler_content_rooms' && $alias === 'r') {
          return $query;
        }
        if ($table === 'dungeoncrawler_content_registry' && $alias === 'r') {
          return $registry_query;
        }
        throw new \InvalidArgumentException("Unexpected select target: {$table} {$alias}");
      });

    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);
    $service = new StateValidationService($logger_factory, $database);

    $result = $service->validateCanonicalRoomLibraryContracts();
    $this->assertTrue(
      $result['valid'],
      implode('; ', $result['items'][0]['errors'] ?? [])
    );
    $this->assertSame(0, $result['summary']['invalid_items']);
  }

  /**
   * Verifies mixed blocking/passable objects keep an entry hex traversable.
   */
  public function testValidateCanonicalRoomLibraryContractsAcceptsDoorOnBoundaryEntryHex(): void {
    $row = [
      'room_id' => 'tpl_room_boundary_door_entry',
      'name' => 'Boundary Door Room',
      'description' => 'Entry hex includes wall and door objects.',
      'environment_tags' => json_encode(['test']),
      'layout_data' => json_encode([
        'hexes' => [
          [
            'q' => 0,
            'r' => 0,
            'elevation_ft' => 0,
            'objects' => [
              [
                'category' => 'wall',
                'passable' => FALSE,
                'blocks_movement' => TRUE,
                'label' => 'Wall',
                'object_id' => 'wall_stone_flat',
              ],
              [
                'category' => 'door',
                'passable' => TRUE,
                'blocks_movement' => FALSE,
                'label' => 'Door',
                'object_id' => 'wooden_door',
              ],
            ],
            'terrain_type' => 'stone_floor',
            'lighting' => 'dim',
            'is_discovered' => TRUE,
            'is_visible' => TRUE,
            'is_entry' => TRUE,
          ],
          [
            'q' => 1,
            'r' => 0,
            'elevation_ft' => 0,
            'objects' => [],
            'terrain_type' => 'stone_floor',
            'lighting' => 'dim',
            'is_discovered' => TRUE,
            'is_visible' => TRUE,
            'is_entry' => FALSE,
          ],
        ],
        'entry_points' => [['q' => 0, 'r' => 0]],
        'exit_points' => [['q' => 1, 'r' => 0]],
        'exits' => [['target_room_id' => 'tpl_room_boundary_door_linked']],
      ]),
      'contents_data' => json_encode([
        'npcs' => [],
        'items' => [],
        'entities' => [],
        'obstacles' => [],
        'hazards' => [],
        'interactables' => [],
      ]),
      'source_room_id' => 'tpl_room_boundary_door_entry',
    ];
    $linked_row = [
      'room_id' => 'tpl_room_boundary_door_linked',
      'name' => 'Boundary Door Linked Room',
      'description' => 'Linked room.',
      'environment_tags' => json_encode(['test']),
      'layout_data' => json_encode([
        'hexes' => [
          [
            'q' => 0,
            'r' => 0,
            'elevation_ft' => 0,
            'objects' => [],
            'terrain_type' => 'stone_floor',
            'lighting' => 'dim',
            'is_discovered' => TRUE,
            'is_visible' => TRUE,
            'is_entry' => TRUE,
          ],
          [
            'q' => 1,
            'r' => 0,
            'elevation_ft' => 0,
            'objects' => [],
            'terrain_type' => 'stone_floor',
            'lighting' => 'dim',
            'is_discovered' => TRUE,
            'is_visible' => TRUE,
            'is_entry' => FALSE,
          ],
        ],
        'entry_points' => [['q' => 0, 'r' => 0]],
        'exit_points' => [['q' => 1, 'r' => 0]],
        'exits' => [['target_room_id' => 'tpl_room_boundary_door_entry']],
      ]),
      'contents_data' => json_encode([
        'npcs' => [],
        'items' => [],
        'entities' => [],
        'obstacles' => [],
        'hazards' => [],
        'interactables' => [],
      ]),
      'source_room_id' => 'tpl_room_boundary_door_linked',
    ];

    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAll')->willReturn([$row, $linked_row]);

    $query = $this->createMock(SelectInterface::class);
    $query->method('fields')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('orderBy')->willReturnSelf();
    $query->method('execute')->willReturn($statement);

    $registry_statement = $this->createMock(StatementInterface::class);
    $registry_statement->method('fetchAll')->willReturn([]);

    $registry_query = $this->createMock(SelectInterface::class);
    $registry_query->method('fields')->willReturnSelf();
    $registry_query->method('condition')->willReturnSelf();
    $registry_query->method('orderBy')->willReturnSelf();
    $registry_query->method('execute')->willReturn($registry_statement);

    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')
      ->willReturnCallback(static fn(string $table): bool => in_array($table, ['dungeoncrawler_content_rooms', 'dungeoncrawler_content_registry'], TRUE));

    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);
    $database->method('select')
      ->willReturnCallback(static function (string $table, string $alias) use ($query, $registry_query): SelectInterface {
        if ($table === 'dungeoncrawler_content_rooms' && $alias === 'r') {
          return $query;
        }
        if ($table === 'dungeoncrawler_content_registry' && $alias === 'r') {
          return $registry_query;
        }
        throw new \InvalidArgumentException("Unexpected select target: {$table} {$alias}");
      });

    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);
    $service = new StateValidationService($logger_factory, $database);

    $result = $service->validateCanonicalRoomLibraryContracts();
    $this->assertTrue($result['valid']);
    $this->assertSame(0, $result['summary']['invalid_items']);
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
