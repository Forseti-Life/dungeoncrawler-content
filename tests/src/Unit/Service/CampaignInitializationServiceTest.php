<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\ConditionInterface;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\dungeoncrawler_content\Service\CampaignInitializationService;
use Drupal\dungeoncrawler_content\Service\CampaignClockService;
use Drupal\dungeoncrawler_content\Service\CampaignNameGeneratorService;
use Drupal\dungeoncrawler_content\Service\CanonicalQuestTemplateService;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalRoomProjectionService;
use Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleConversionReleaseService;
use Drupal\dungeoncrawler_content\Service\NpcSheetGenerationService;
use Drupal\dungeoncrawler_content\Service\QuestDestinationValidatorService;
use Drupal\dungeoncrawler_content\Service\QuestGeneratorService;
use Drupal\dungeoncrawler_content\Service\RoomViewImageService;
use Drupal\dungeoncrawler_content\Service\ChatSessionManager;
use Drupal\dungeoncrawler_content\Service\StorylineQuestLifecycleService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests starter tavern seed alignment for campaign initialization.
 *
 * @group dungeoncrawler_content
 * @group campaign_init
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\CampaignInitializationService
 */
class CampaignInitializationServiceTest extends UnitTestCase {

  /**
   * @covers ::loadStarterRoomSeed
   */
  public function testLoadStarterRoomSeedUsesCanonicalGildedTankardMetadata(): void {
    $select = $this->createMock(SelectInterface::class);
    $condition_group = $this->createMock(ConditionInterface::class);
    $result = new class() {
      public function fetchAssoc(): array {
        return [
          'room_id' => 'tavern_entrance',
          'source_room_id' => 'tavern_entrance',
          'name' => 'The Gilded Tankard',
          'description' => 'Eldric keeps watch while Marta the Scholar studies nearby.',
          'environment_tags' => json_encode(['indoor', 'tavern']),
          'layout_data' => json_encode(['shape' => 'seed']),
          'contents_data' => json_encode([
            'items' => [
              ['item_id' => 'wine-1'],
            ],
            'npcs' => [],
          ]),
        ];
      }
    };

    $condition_group->method('condition')->willReturnSelf();
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('orderBy')->willReturnSelf();
    $select->method('range')->willReturnSelf();
    $select->method('orConditionGroup')->willReturn($condition_group);
    $select->method('execute')->willReturn($result);

    $database = $this->createMock(Connection::class);
    $database->method('select')->willReturn($select);

    $module_list = $this->createMock(ModuleExtensionList::class);
    $module_list->method('getPath')->with('dungeoncrawler_content')
      ->willReturn('/path/that/does/not/exist');

    $service = new CampaignInitializationService(
      $database,
      $this->createMock(UuidInterface::class),
      $this->createMock(TimeInterface::class),
      $this->buildLoggerFactory(),
      $module_list,
      $this->createMock(QuestGeneratorService::class),
      $this->createMock(CampaignNameGeneratorService::class),
      $this->createMock(CampaignClockService::class),
      $this->createMock(StorylineQuestLifecycleService::class),
      $this->createMock(ChatSessionManager::class),
      $this->createMock(NpcSheetGenerationService::class),
      $this->createMock(RoomViewImageService::class),
      NULL,
      NULL,
      NULL,
      NULL,
      NULL,
      NULL,
      $this->createMock(ConfigFactoryInterface::class),
    );

    $method = new \ReflectionMethod(CampaignInitializationService::class, 'loadStarterRoomSeed');
    $method->setAccessible(TRUE);
    $room = $method->invoke($service, [
      'source_room_id' => 'tavern_entrance',
      'runtime_room_id' => 'tavern_entrance',
      'room_tags_default' => ['indoor', 'tavern'],
      'theme' => 'classic_dungeon',
    ]);

    $this->assertIsArray($room);
    $this->assertSame('tavern_entrance', $room['room_id']);
    $this->assertSame('tavern_entrance', $room['runtime_room_id']);
    $this->assertSame('The Gilded Tankard', $room['name']);
    $this->assertStringContainsString('Eldric', $room['description']);
    $this->assertStringContainsString('Marta the Scholar', $room['description']);
    $this->assertIsArray($room['contents_data'] ?? NULL);
    $this->assertNotEmpty($room['contents_data']['items'] ?? []);
  }

  /**
   * @covers ::buildStarterRoomIntroMessage
   */
  public function testBuildStarterRoomIntroMessageUsesRoomDescriptionWhenProvided(): void {
    $service = (new \ReflectionClass(CampaignInitializationService::class))
      ->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod(CampaignInitializationService::class, 'buildStarterRoomIntroMessage');
    $method->setAccessible(TRUE);

    $message = $method->invoke(
      $service,
      'The Gilded Tankard',
      'Warm light and low voices fill the tavern.'
    );

    $this->assertStringStartsWith('The Gilded Tankard', $message);
    $this->assertStringContainsString('Warm light and low voices fill the tavern.', $message);
  }

  /**
   * @covers ::buildStarterRoomIntroMessage
   */
  public function testBuildStarterRoomIntroMessageFallsBackToRoomArrivalTextWhenDescriptionMissing(): void {
    $service = (new \ReflectionClass(CampaignInitializationService::class))
      ->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod(CampaignInitializationService::class, 'buildStarterRoomIntroMessage');
    $method->setAccessible(TRUE);

    $message = $method->invoke($service, 'The Gilded Tankard', '');

    $this->assertStringContainsString('You arrive at The Gilded Tankard. The adventure begins...', $message);
  }

  /**
   * @dataProvider invalidCampaignSourceProvider
   * @covers ::normalizeCampaignSource
   */
  public function testNormalizeCampaignSourceHardFailsInvalidContracts(?array $source): void {
    $service = (new \ReflectionClass(CampaignInitializationService::class))
      ->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod(CampaignInitializationService::class, 'normalizeCampaignSource');
    $method->setAccessible(TRUE);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('campaign_source_invalid');
    $method->invoke($service, $source, 'classic_dungeon');
  }

  /**
   * Invalid source fixtures.
   */
  public static function invalidCampaignSourceProvider(): array {
    return [
      'unknown kind' => [['kind' => 'legacy']],
      'missing dungeon id' => [['kind' => 'published_dungeon', 'version_id' => '11111111-1111-4111-8111-111111111111']],
      'missing version id' => [['kind' => 'published_dungeon', 'dungeon_id' => 'd2_fixture']],
      'missing module release id' => [['kind' => 'module_release']],
      'blank theme' => [['kind' => 'theme', 'theme' => '']],
    ];
  }

  /**
   * @covers ::normalizeCampaignSource
   */
  public function testNormalizeCampaignSourceResolvesModuleReleaseToPinnedPublishedDungeonSource(): void {
    $service = (new \ReflectionClass(CampaignInitializationService::class))
      ->newInstanceWithoutConstructor();
    $release_service = $this->createMock(ModuleConversionReleaseService::class);
    $release_service->expects($this->once())
      ->method('resolveCampaignSource')
      ->with('11111111-1111-4111-8111-111111111111')
      ->willReturn([
        'release_id' => '11111111-1111-4111-8111-111111111111',
        'module_id' => 'fixture-module',
        'module_title' => 'Fixture Module',
        'module_version' => '1.0.0',
        'archive_sha256' => str_repeat('a', 64),
        'dungeon_id' => 'fixture_dungeon',
        'version_id' => '22222222-2222-4222-8222-222222222222',
      ]);
    $property = new \ReflectionProperty(CampaignInitializationService::class, 'moduleConversionReleaseService');
    $property->setAccessible(TRUE);
    $property->setValue($service, $release_service);
    $method = new \ReflectionMethod(CampaignInitializationService::class, 'normalizeCampaignSource');
    $method->setAccessible(TRUE);

    $normalized = $method->invoke($service, [
      'kind' => 'module_release',
      'release_id' => '11111111-1111-4111-8111-111111111111',
    ], 'classic_dungeon');

    $this->assertSame('published_dungeon', $normalized['kind']);
    $this->assertSame('fixture_dungeon', $normalized['dungeon_id']);
    $this->assertSame('22222222-2222-4222-8222-222222222222', $normalized['version_id']);
    $this->assertSame('11111111-1111-4111-8111-111111111111', $normalized['module_release_id']);
    $this->assertSame('fixture-module', $normalized['module_id']);
    $this->assertSame('1.0.0', $normalized['module_version']);
    $this->assertSame(str_repeat('a', 64), $normalized['module_archive_sha256']);
  }

  /**
   * @covers ::resolvePublishedModuleQuest
   */
  public function testPublishedModuleQuestRequiresVerifiedReleaseAndExplicitReference(): void {
    $database = $this->createMock(Connection::class);
    $database->expects($this->never())->method('select');
    $service = $this->questResolutionService($database);
    $method = new \ReflectionMethod(CampaignInitializationService::class, 'resolvePublishedModuleQuest');
    $method->setAccessible(TRUE);
    $published = $this->moduleQuestFixture();

    $published['source'] = ['dungeon_id' => 'dungeon', 'version_id' => 'version'];
    $this->assertNull($method->invoke($service, $published));

    $published = $this->moduleQuestFixture();
    unset($published['aggregate']['metadata']['module_conversion']['quest_template_id']);
    $this->assertNull($method->invoke($service, $published));
  }

  /**
   * @covers ::resolvePublishedModuleQuest
   * @covers ::collectPublishedModuleQuestObjectives
   */
  public function testPublishedModuleQuestValidatesCanonicalSceneBinding(): void {
    $service = $this->questResolutionService($this->canonicalQuestDatabase([
      'template_id' => 'island-authored',
      'version' => '1.0.0',
      'objectives_schema' => json_encode([
        ['phase' => 1, 'objectives' => [
          self::actionableObjective('enter_lair', 'placement-a'),
          self::actionableObjective('find_clue', 'placement-b'),
        ]],
      ]),
    ]));
    $method = new \ReflectionMethod(CampaignInitializationService::class, 'resolvePublishedModuleQuest');
    $method->setAccessible(TRUE);
    $this->assertSame(['template_id' => 'island-authored'], $method->invoke($service, $this->moduleQuestFixture()));
  }

  /**
   * @covers ::resolvePublishedModuleQuest
   * @covers ::collectPublishedModuleQuestObjectives
   */
  public function testNestedActionableObjectivesCoverEveryPinnedScene(): void {
    $service = $this->questResolutionService($this->canonicalQuestDatabase([
      'template_id' => 'island-authored',
      'version' => '1.0.0',
      'objectives_schema' => json_encode([['phase' => 1, 'objectives' => [[
        'objective_id' => 'explore_both',
        'type' => 'composite',
        'description' => 'Explore both scenes.',
        'next_step' => 'Complete both explorations.',
        'completion_criteria' => ['kind' => 'all_children', 'metric' => 'children_completed', 'description' => 'Complete both scenes.'],
        'children' => [
          self::actionableObjective('enter_lair', 'placement-a'),
          self::actionableObjective('find_clue', 'placement-b'),
        ],
      ]]]]),
    ]));
    $this->assertSame(
      ['template_id' => 'island-authored'],
      (new \ReflectionMethod(CampaignInitializationService::class, 'resolvePublishedModuleQuest'))
        ->invoke($service, $this->moduleQuestFixture())
    );
  }

  /**
   * @dataProvider invalidModuleQuestProvider
   * @covers ::resolvePublishedModuleQuest
   * @covers ::collectPublishedModuleQuestObjectives
   */
  public function testPublishedModuleQuestRejectsInvalidBinding(array $changes, ?array $template): void {
    $service = $this->questResolutionService($this->canonicalQuestDatabase($template));
    $published = $this->moduleQuestFixture();
    foreach ($changes as $key => $value) {
      $published['aggregate']['metadata']['module_conversion'][$key] = $value;
    }
    $method = new \ReflectionMethod(CampaignInitializationService::class, 'resolvePublishedModuleQuest');
    $method->setAccessible(TRUE);
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('campaign_source_module_quest_invalid');
    $method->invoke($service, $published);
  }

  public static function invalidModuleQuestProvider(): array {
    $matching = [
      'template_id' => 'island-authored',
      'version' => '1.0.0',
      'objectives_schema' => json_encode([['phase' => 1, 'objectives' => [
        self::actionableObjective('enter_lair', 'placement-a'),
        self::actionableObjective('find_clue', 'placement-b'),
      ]]]),
    ];
    $valid = [
      'template_id' => 'island-authored',
      'version' => '1.0.0',
      'objectives_schema' => '{"phases":"malformed"}',
    ];
    return [
      'empty reference' => [['quest_template_id' => ''], NULL],
      'missing canonical row' => [[], NULL],
      'ambiguous template versions' => [[], [$matching, $matching]],
      'invalid canonical schema' => [[], $valid],
      'second destination not pinned' => [[], [
        'template_id' => 'island-authored',
        'version' => '1.0.0',
        'objectives_schema' => '[{"phase":1,"objectives":[{"objective_id":"enter_lair","destination_id":"placement-a"},{"objective_id":"find_clue","location_id":"source-room-id"}]}]',
      ]],
      'human scene alias masks pinned id' => [[], [
        'template_id' => 'island-authored',
        'version' => '1.0.0',
        'objectives_schema' => json_encode([['phase' => 1, 'objectives' => [
          self::actionableObjective('enter_lair', 'placement-a'),
          [...self::actionableObjective('find_clue', 'placement-b'), 'location' => 'scene-alias'],
        ]]]),
      ]],
      'different pinned location and location id' => [[], [
        'template_id' => 'island-authored',
        'version' => '1.0.0',
        'objectives_schema' => json_encode([['phase' => 1, 'objectives' => [
          self::actionableObjective('enter_lair', 'placement-a'),
          [...self::actionableObjective('find_clue', 'placement-b'), 'location' => 'placement-a'],
        ]]]),
      ]],
      'explore missing required location' => [[], [
        'template_id' => 'island-authored',
        'version' => '1.0.0',
        'objectives_schema' => json_encode([['phase' => 1, 'objectives' => [
          self::actionableObjective('enter_lair', 'placement-a'),
          array_diff_key(self::actionableObjective('find_clue', 'placement-b'), ['location' => TRUE]),
        ]]]),
      ]],
      'location without location id' => [[], [
        'template_id' => 'island-authored',
        'version' => '1.0.0',
        'objectives_schema' => json_encode([['phase' => 1, 'objectives' => [
          self::actionableObjective('enter_lair', 'placement-a'),
          [...array_diff_key(self::actionableObjective('find_clue', 'placement-b'), ['location_id' => TRUE]), 'type' => 'interact', 'destination_id' => 'placement-b'],
        ]]]),
      ]],
      'objective without scene destination' => [[], [
        'template_id' => 'island-authored',
        'version' => '1.0.0',
        'objectives_schema' => '[{"phase":1,"objectives":[{"objective_id":"enter_lair"}]}]',
      ]],
      'unexpected scene override' => [['scene_objectives' => []], NULL],
      'unplaced destination' => [[], [
        'template_id' => 'island-authored',
        'version' => '1.0.0',
        'objectives_schema' => '[{"phase":1,"objectives":[{"objective_id":"enter_lair","destination_id":"elsewhere"}]}]',
      ]],
      'duplicate objective' => [[], [
        'template_id' => 'island-authored',
        'version' => '1.0.0',
        'objectives_schema' => '[{"phase":1,"objectives":[{"objective_id":"enter_lair","destination_id":"placement-a"},{"objective_id":"enter_lair","location_id":"placement-b"}]}]',
      ]],
      'uncovered pinned scene' => [[], [
        'template_id' => 'island-authored',
        'version' => '1.0.0',
        'objectives_schema' => json_encode([['phase' => 1, 'objectives' => [
          self::actionableObjective('enter_lair', 'placement-a'),
        ]]]),
      ]],
      'nonactionable scene destination' => [[], [
        'template_id' => 'island-authored',
        'version' => '1.0.0',
        'objectives_schema' => json_encode([['phase' => 1, 'objectives' => [
          self::actionableObjective('enter_lair', 'placement-a'),
          [
            'objective_id' => 'narrative_only',
            'type' => 'group',
            'location_id' => 'placement-b',
            'description' => 'Review lair narrative.',
            'next_step' => 'Complete nested objective.',
            'completion_criteria' => ['kind' => 'all_children', 'metric' => 'children_completed', 'description' => 'Complete nested objective.'],
            'children' => [self::actionableObjective('nested_lair', 'placement-a')],
          ],
        ]]]),
      ]],
    ];
  }

  /**
   * @covers ::resolvePublishedModuleQuest
   */
  public function testPublishedModuleQuestRejectsQuarantinedCanonicalTemplate(): void {
    $template = [
      'template_id' => 'island-authored',
      'version' => '1.0.0',
      'objectives_schema' => json_encode([['phase' => 1, 'objectives' => [
        self::actionableObjective('enter_lair', 'placement-a'),
        self::actionableObjective('find_clue', 'placement-b'),
      ]]]),
    ];
    $service = $this->questResolutionService($this->canonicalQuestDatabase($template));
    $authority = $this->createMock(CanonicalQuestTemplateService::class);
    $authority->expects($this->once())->method('requireCanonicalQuestTemplateRef')
      ->with('island-authored', '1.0.0')
      ->willThrowException(new \OutOfBoundsException('quest_template_quarantined:island-authored'));
    (new \ReflectionProperty(CampaignInitializationService::class, 'canonicalQuestTemplateService'))->setValue($service, $authority);
    $this->expectException(\OutOfBoundsException::class);
    $this->expectExceptionMessage('quest_template_quarantined:island-authored');
    (new \ReflectionMethod(CampaignInitializationService::class, 'resolvePublishedModuleQuest'))
      ->invoke($service, $this->moduleQuestFixture());
  }

  private static function actionableObjective(string $id, string $location): array {
    return [
      'objective_id' => $id,
      'type' => 'explore',
      'description' => 'Explore ' . $location,
      'location_id' => $location,
      'location' => $location,
      'next_step' => 'Explore ' . $location,
      'completion_criteria' => ['kind' => 'flag', 'metric' => 'discovered', 'description' => 'Discover the scene.'],
    ];
  }

  /**
   * @covers \Drupal\dungeoncrawler_content\Service\QuestDestinationValidatorService::validateQuestDestination
   */
  public function testPinnedPlacementUuidIsAcceptedAsRuntimeQuestLocation(): void {
    $placement_id = '22222222-2222-4222-8222-222222222222';
    $validator = new QuestDestinationValidatorService();
    $validator->validateQuestDestination(
      ['location_id' => $placement_id],
      ['rooms' => [['room_id' => $placement_id, 'name' => 'Published scene']]]
    );
    $this->addToAssertionCount(1);
  }

  /**
   * @covers ::seedPublishedModuleQuest
   */
  public function testPublishedModuleQuestUsesGeneratorAndLifecycleWithoutCustomInsert(): void {
    $service = $this->questResolutionService($this->createMock(Connection::class));
    $generator = $this->createMock(QuestGeneratorService::class);
    $generator->expects($this->once())->method('generateQuestFromTemplate')
      ->with('island-authored', 42, [
        'party_level' => 1,
        'difficulty' => 'severe',
        'location' => 'placement-a',
        'dungeon_id' => 'runtime-dungeon',
        'initial_status' => 'offered',
      ])
      ->willReturn(['quest_id' => 'quest-42', 'source_template_id' => 'island-authored']);
    $lifecycle = $this->createMock(StorylineQuestLifecycleService::class);
    $lifecycle->expects($this->once())->method('ensureOfferedQuestFromTemplate')
      ->with(42, 'island-authored', $this->callback(static function (callable $factory): bool {
        return $factory() === ['quest_id' => 'quest-42', 'source_template_id' => 'island-authored'];
      }))
      ->willReturn(TRUE);
    (new \ReflectionProperty(CampaignInitializationService::class, 'questGenerator'))->setValue($service, $generator);
    (new \ReflectionProperty(CampaignInitializationService::class, 'storylineQuestLifecycleService'))->setValue($service, $lifecycle);
    (new \ReflectionMethod(CampaignInitializationService::class, 'seedPublishedModuleQuest'))
      ->invoke($service, 42, 'island-authored', 'hard', 'placement-a', 'runtime-dungeon');
  }

  /**
   * @covers ::seedPublishedModuleQuest
   */
  public function testPublishedModuleQuestDoesNotSuppressGenerationFailure(): void {
    $service = $this->questResolutionService($this->createMock(Connection::class));
    $generator = $this->createMock(QuestGeneratorService::class);
    $generator->method('generateQuestFromTemplate')
      ->willThrowException(new \RuntimeException('canonical destination not materialized'));
    $lifecycle = $this->createMock(StorylineQuestLifecycleService::class);
    $lifecycle->method('ensureOfferedQuestFromTemplate')
      ->willReturnCallback(static function (int $campaign_id, string $template_id, callable $factory): bool {
        $factory();
        return TRUE;
      });
    (new \ReflectionProperty(CampaignInitializationService::class, 'questGenerator'))->setValue($service, $generator);
    (new \ReflectionProperty(CampaignInitializationService::class, 'storylineQuestLifecycleService'))->setValue($service, $lifecycle);
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('canonical destination not materialized');
    (new \ReflectionMethod(CampaignInitializationService::class, 'seedPublishedModuleQuest'))
      ->invoke($service, 42, 'island-authored', 'normal', 'placement-a', 'runtime-dungeon');
  }

  private function questResolutionService(Connection $database): CampaignInitializationService {
    $service = (new \ReflectionClass(CampaignInitializationService::class))->newInstanceWithoutConstructor();
    (new \ReflectionProperty(CampaignInitializationService::class, 'database'))->setValue($service, $database);
    $authority = $this->createMock(CanonicalQuestTemplateService::class);
    $authority->method('requireCanonicalQuestTemplateRef')
      ->willReturnCallback(static fn(string $id, ?string $version): array => [
        'template_id' => $id,
        'version' => $version,
        'name' => 'Authored scene quest',
        'quest_type' => 'module_story',
        'source_table' => 'dc_canonical_quests',
      ]);
    (new \ReflectionProperty(CampaignInitializationService::class, 'canonicalQuestTemplateService'))->setValue($service, $authority);
    return $service;
  }

  private function canonicalQuestDatabase(?array $template): Connection {
    $select = $this->createMock(SelectInterface::class);
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('range')->with(0, 2)->willReturnSelf();
    $select->method('execute')->willReturn(new class($template) {
      public function __construct(private ?array $template) {}
      public function fetchAll(int $fetch_style): array {
        return $this->template === NULL ? [] : (array_is_list($this->template) ? $this->template : [$this->template]);
      }
    });
    $database = $this->createMock(Connection::class);
    $database->method('select')->with('dc_canonical_quests', 'q')->willReturn($select);
    return $database;
  }

  private function moduleQuestFixture(): array {
    return [
      'source' => ['module_release_id' => 'verified-release', 'dungeon_id' => 'dungeon', 'version_id' => 'version'],
      'aggregate' => [
        'room_placements' => [
          ['placement_id' => 'placement-a'],
          ['placement_id' => 'placement-b'],
        ],
        'metadata' => ['module_conversion' => [
          'execution_mode' => 'original_scene_generation',
          'quest_template_id' => 'island-authored',
        ]],
      ],
    ];
  }

  /**
   * @covers ::buildPublishedDungeonCampaignProfile
   */
  public function testPublishedDungeonProfilePinsFallbackLiteralsAndEntrancePlacement(): void {
    $service = (new \ReflectionClass(CampaignInitializationService::class))
      ->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod(CampaignInitializationService::class, 'buildPublishedDungeonCampaignProfile');
    $method->setAccessible(TRUE);

    $profile = $method->invoke($service, [
      'source' => [
        'dungeon_id' => 'd2_fixture',
        'version_id' => '11111111-1111-4111-8111-111111111111',
      ],
      'version_row' => ['version' => '1.0.0'],
      'aggregate' => [
        'name' => 'D-2 Fixture',
        'description' => 'Fixture dungeon.',
        'metadata' => ['tags' => ['d2-fixture']],
      ],
      'entrance_placement' => [
        'placement_id' => '22222222-2222-4222-8222-222222222222',
        'room_id' => 'd2_fixture_room_a',
        'tags' => ['entrance'],
      ],
    ], 'runtime-dungeon-id');

    $this->assertSame('published-dungeon-v1', $profile['content_profile_id']);
    $this->assertSame('22222222-2222-4222-8222-222222222222', $profile['launch_policy']['default_active_room_id']);
    $this->assertSame('runtime-dungeon-id', $profile['launch_policy']['default_active_dungeon_id']);
    $this->assertSame('campaign_profile', $profile['narrative_hub_policy']['fallback_mode']);
    $this->assertSame('campaign_profile_only', $profile['launch_policy']['runtime_fallback_mode']);
    $this->assertNull($profile['narrative_hub_policy']['primary_contact_actor_id']);
  }

  /**
   * @covers \Drupal\dungeoncrawler_content\Service\Generation\CanonicalRoomProjectionService::buildRuntimeRoom
   */
  public function testPublishedDungeonRoomInstantiationPreservesMetadataInsideLayoutData(): void {
    $service = new CanonicalRoomProjectionService();
    $room = $this->publishedRoomFixture();
    $placement = [
      'placement_id' => '22222222-2222-4222-8222-222222222222',
      'room_id' => 'd2_fixture_room_a',
      'version_id' => '11111111-1111-4111-8111-111111111111',
    ];
    $version_row = [
      'room_id' => 'd2_fixture_room_a',
      'version_id' => '11111111-1111-4111-8111-111111111111',
      'version' => '1.0.0',
    ];

    $runtime = $service->buildRuntimeRoom([
      'room_id' => 'd2_fixture_room_a',
      'room_version_id' => '11111111-1111-4111-8111-111111111111',
      'room_payload' => $room,
      'row' => $version_row,
    ], [
      'runtime_room_id' => '22222222-2222-4222-8222-222222222222',
      'placement' => $placement,
      'source_kind' => 'published_dungeon',
    ]);
    $layout = $runtime['_layout_data'];
    $contents = $runtime['_contents_data'];
    $decoded_layout = json_decode(json_encode($layout, JSON_THROW_ON_ERROR), TRUE, 512, JSON_THROW_ON_ERROR);
    $decoded_contents = json_decode(json_encode($contents, JSON_THROW_ON_ERROR), TRUE, 512, JSON_THROW_ON_ERROR);

    $this->assertSame('D-2 Room A', $decoded_layout['metadata']['module_source']['licence_notice']);
    $this->assertSame('d2_fixture_room_a', $decoded_layout['metadata']['campaign_source']['source_room_id']);
    $this->assertSame('11111111-1111-4111-8111-111111111111', $decoded_contents['_source']['room_version_id']);
  }

  /**
   * @covers \Drupal\dungeoncrawler_content\Service\Generation\CanonicalRoomProjectionService::buildPublishedCampaignSparseH3Room
   */
  public function testPublishedDungeonSparseH3MappingsUseTransformedLevelSpace(): void {
    $service = new CanonicalRoomProjectionService();

    $room = $this->publishedRoomFixture();
    $room['hexes'] = [
      ['q' => 0, 'r' => 0, 'h3_index_res14' => '8f0000000000001'],
      ['q' => 1, 'r' => 0, 'h3_index_res14' => '8f0000000000002'],
    ];
    $room['entry_ports'][0]['hex'] = ['q' => 0, 'r' => 0];
    $placement = [
      'placement_id' => '22222222-2222-4222-8222-222222222222',
      'room_id' => 'd2_fixture_room_a',
      'version_id' => '11111111-1111-4111-8111-111111111111',
      'origin' => ['q' => 5, 'r' => -2],
      'rotation_steps' => 0,
    ];

    $sparse_room = $service->buildPublishedCampaignSparseH3Room($placement, $room);

    $this->assertSame('22222222-2222-4222-8222-222222222222', $sparse_room['room_id']);
    $this->assertSame(['q' => 5, 'r' => -2, 'h3_index_res14' => '8f0000000000001', 'lat' => NULL, 'lng' => NULL], $sparse_room['anchor']);
    $this->assertSame(5, $sparse_room['hexes'][0]['q']);
    $this->assertSame(-2, $sparse_room['hexes'][0]['r']);
    $this->assertSame(6, $sparse_room['hexes'][1]['q']);
    $this->assertSame(-2, $sparse_room['hexes'][1]['r']);
  }

  /**
   * @covers ::buildPublishedDungeonCampaignConnectorPayload
   * @covers ::resolveConnectorEndpointPlacement
   */
  public function testPublishedDungeonConnectorRemapsByTransformedFootprintAndPassesH3(): void {
    $service = (new \ReflectionClass(CampaignInitializationService::class))
      ->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod(CampaignInitializationService::class, 'buildPublishedDungeonCampaignConnectorPayload');
    $method->setAccessible(TRUE);

    $payload = $method->invoke($service, 'runtime-dungeon-id', [
      'connection_id' => '33333333-3333-4333-8333-333333333333',
      'from_room_id' => 'd2_fixture_room_a',
      'to_room_id' => 'd2_fixture_room_b',
      'from_hex_q' => 1,
      'from_hex_r' => 0,
      'to_hex_q' => 2,
      'to_hex_r' => 0,
      'from_h3_index_res14' => '8f0000000000001',
      'to_h3_index_res14' => '8f0000000000002',
      'kind' => 'door',
      'direction' => 'bidirectional',
      'default_state' => 'open',
      'requirements_data' => '[]',
    ], [
      'd2_fixture_room_a' => ['1:0' => ['22222222-2222-4222-8222-222222222222']],
      'd2_fixture_room_b' => ['2:0' => ['44444444-4444-4444-8444-444444444444']],
    ]);

    $this->assertSame('runtime-dungeon-id', $payload['dungeon_id']);
    $this->assertSame('22222222-2222-4222-8222-222222222222', $payload['from_room_id']);
    $this->assertSame('44444444-4444-4444-8444-444444444444', $payload['to_room_id']);
    $this->assertSame('8f0000000000001', $payload['from_h3_index_res14']);
    $this->assertSame('8f0000000000002', $payload['to_h3_index_res14']);
  }

  /**
   * @covers ::buildPublishedDungeonCampaignConnectorPayload
   */
  public function testPublishedDungeonConnectorHardFailsMissingH3(): void {
    $service = (new \ReflectionClass(CampaignInitializationService::class))
      ->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod(CampaignInitializationService::class, 'buildPublishedDungeonCampaignConnectorPayload');
    $method->setAccessible(TRUE);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('campaign_source_connector_h3_missing');
    $method->invoke($service, 'runtime-dungeon-id', [
      'connection_id' => '33333333-3333-4333-8333-333333333333',
      'from_room_id' => 'd2_fixture_room_a',
      'to_room_id' => 'd2_fixture_room_b',
      'from_hex_q' => 1,
      'from_hex_r' => 0,
      'to_hex_q' => 2,
      'to_hex_r' => 0,
      'from_h3_index_res14' => '',
      'to_h3_index_res14' => '8f0000000000002',
    ], []);
  }

  /**
   * @covers ::buildPublishedDungeonCampaignConnectorPayload
   */
  public function testPublishedDungeonConnectorHardFailsUnresolvableEndpoint(): void {
    $service = (new \ReflectionClass(CampaignInitializationService::class))
      ->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod(CampaignInitializationService::class, 'buildPublishedDungeonCampaignConnectorPayload');
    $method->setAccessible(TRUE);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('campaign_source_connector_unresolvable');
    $method->invoke($service, 'runtime-dungeon-id', [
      'connection_id' => '33333333-3333-4333-8333-333333333333',
      'from_room_id' => 'd2_fixture_room_a',
      'to_room_id' => 'd2_fixture_room_b',
      'from_hex_q' => 1,
      'from_hex_r' => 0,
      'to_hex_q' => 2,
      'to_hex_r' => 0,
      'from_h3_index_res14' => '8f0000000000001',
      'to_h3_index_res14' => '8f0000000000002',
    ], []);
  }

  /**
   * Published room fixture.
   */
  private function publishedRoomFixture(): array {
    return [
      'room_id' => 'd2_fixture_room_a',
      'name' => 'D-2 Room A',
      'description' => 'Fixture room.',
      'room_type' => 'chamber',
      'size_category' => 'small',
      'hexes' => [['q' => 0, 'r' => 0, 'terrain_type' => 'stone_floor', 'elevation_ft' => 0, 'h3_index_res14' => '8f0000000000001']],
      'terrain' => ['type' => 'stone_floor'],
      'lighting' => ['level' => 'bright_light', 'light_sources' => []],
      'entry_ports' => [[
        'port_id' => 'entry_a',
        'hex' => ['q' => 0, 'r' => 0],
        'edge' => 3,
        'label' => 'Entry',
        'arrival_facing' => 0,
        'is_default' => TRUE,
        'tags' => [],
      ]],
      'exit_ports' => [[
        'port_id' => 'exit_a',
        'hex' => ['q' => 0, 'r' => 0],
        'edge' => 0,
        'label' => 'Exit',
        'kind' => 'door',
        'direction' => 'bidirectional',
        'default_state' => 'open',
        'destination_hint' => NULL,
        'linked_placement_id' => NULL,
        'requirements' => [],
        'tags' => [],
      ]],
      'placements' => [],
      'environmental_effects' => [],
      'gameplay_defaults' => ['safe_for_rest' => FALSE, 'visibility' => 'visible'],
      'metadata' => [
        'tags' => ['d2-fixture'],
        'provenance' => ['author' => 'unit'],
        'module_source' => ['licence_notice' => 'D-2 Room A'],
      ],
    ];
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
