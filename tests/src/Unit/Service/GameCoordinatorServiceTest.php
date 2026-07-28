<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\AiGmService;
use Drupal\dungeoncrawler_content\Service\ActorRuntimeStateStore;
use Drupal\dungeoncrawler_content\Service\ActorRuntimeMutationService;
use Drupal\dungeoncrawler_content\Service\CampaignCharacterRuntimeSyncService;
use Drupal\dungeoncrawler_content\Service\CampaignRuntimeMutationService;
use Drupal\dungeoncrawler_content\Service\CampaignRuntimeStateStore;
use Drupal\dungeoncrawler_content\Service\CampaignTimeResolverService;
use Drupal\dungeoncrawler_content\Service\ConnectionRuntimeStateStore;
use Drupal\dungeoncrawler_content\Service\ConnectionRuntimeMutationService;
use Drupal\dungeoncrawler_content\Service\DungeonPayloadStatePersistenceService;
use Drupal\dungeoncrawler_content\Service\EncounterPhaseHandler;
use Drupal\dungeoncrawler_content\Service\GameCoordinatorService;
use Drupal\dungeoncrawler_content\Service\GameEventLogger;
use Drupal\dungeoncrawler_content\Service\NarrationEngine;
use Drupal\dungeoncrawler_content\Service\RoomRuntimeStateStore;
use Drupal\dungeoncrawler_content\Service\RoomRuntimeMutationService;
use Drupal\dungeoncrawler_content\Service\RuntimeBootstrapService;
use Drupal\dungeoncrawler_content\Service\RuntimeGraphAssemblerService;
use Drupal\dungeoncrawler_content\Service\TextToSpeechIntegrationService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\GameCoordinatorService
 * @group dungeoncrawler_content
 * @group service
 */
final class GameCoordinatorServiceTest extends UnitTestCase {

  /**
   * @covers ::resolveActorIdForCharacterId
   */
  public function testResolveActorIdForCharacterIdUsesSourceCharacterIdFromRuntimeMetadata(): void {
    $service = $this->createTestService([
      'entities' => [
        [
          'entity_instance_id' => 'pc-277-1033',
          'instance_id' => 'pc-277-1033',
          'state' => [
            'metadata' => [
              'campaign_character_id' => 1033,
              'source_character_id' => 1032,
              'runtime_entity_id' => 'pc-277-1033',
            ],
          ],
        ],
      ],
    ]);

    $this->assertSame('pc-277-1033', $service->resolveActorIdForCharacterIdForTest(277, 1032));
  }

  /**
   * @covers ::resolveActionAvailabilityContext
   */
  public function testResolveActionAvailabilityContextBuildsEncounterContext(): void {
    $service = $this->createTestService([
      'active_room_id' => 'room-a',
      'rooms' => [
        ['room_id' => 'room-a', 'name' => 'Entry'],
      ],
    ]);

    $context = $service->resolveActionAvailabilityContextForTest(277, 'pc-1');

    $this->assertIsArray($context);
    $this->assertSame('encounter', $context['game_state']['phase']);
    $this->assertSame('room-a', $context['dungeon_data']['active_room_id']);
    $this->assertInstanceOf(EncounterPhaseHandler::class, $context['handler']);
  }

  /**
   * @covers ::emptyActionAvailabilityPayload
   */
  public function testEmptyActionAvailabilityPayloadShape(): void {
    $service = $this->createTestService(['rooms' => []]);

    $this->assertSame([
      'available_actions' => [],
      'action_contract' => NULL,
    ], $service->emptyActionAvailabilityPayloadForTest());
  }

  /**
   * @covers ::getActionAvailabilityForActor
   */
  public function testGetActionAvailabilityForActorReturnsCanonicalEnvelopeKeys(): void {
    $service = $this->createTestService([
      'active_room_id' => 'room-a',
      'rooms' => [
        ['room_id' => 'room-a', 'name' => 'Entry'],
      ],
    ]);

    $payload = $service->getActionAvailabilityForActor(277, 'pc-1');

    $this->assertSame(['available_actions', 'action_contract'], array_keys($payload));
  }

  /**
   * @covers ::resolveMutationEnvelopeForPersistence
   */
  public function testResolveMutationEnvelopeForPersistenceBackfillsNonGameStateWhenChangedAndEnvelopeIsEmpty(): void {
    $dungeon_data = [
      'active_room_id' => 'room-a',
      'entities' => [['entity_instance_id' => 'pc-1']],
      'rooms' => [['room_id' => 'room-a']],
      'connections' => [['id' => 'conn-a']],
    ];
    $service = $this->createTestService($dungeon_data);

    $resolved = $service->resolveMutationEnvelopeForPersistenceForTest(
      277,
      [
        'campaign_id' => 277,
        'campaign_state' => ['phase' => 'exploration'],
        'actor_entities' => [],
        'rooms' => [],
        'connections' => [],
      ],
      ['phase' => 'exploration'],
      $dungeon_data,
      'before-fingerprint'
    );

    $this->assertSame($dungeon_data['entities'], $resolved['actor_entities']);
    $this->assertSame($dungeon_data['rooms'], $resolved['rooms']);
    $this->assertSame($dungeon_data['connections'], $resolved['connections']);
  }

  /**
   * @covers ::resolveMutationEnvelopeForPersistence
   */
  public function testResolveMutationEnvelopeForPersistenceKeepsEmptyNonGameStateWhenUnchanged(): void {
    $dungeon_data = [
      'active_room_id' => 'room-a',
      'entities' => [['entity_instance_id' => 'pc-1']],
      'rooms' => [['room_id' => 'room-a']],
      'connections' => [['id' => 'conn-a']],
    ];
    $service = $this->createTestService($dungeon_data);
    $before_fingerprint = $service->computeNonGameStatePayloadFingerprintForTest($dungeon_data);

    $resolved = $service->resolveMutationEnvelopeForPersistenceForTest(
      277,
      [
        'campaign_id' => 277,
        'campaign_state' => ['phase' => 'exploration'],
        'actor_entities' => [],
        'rooms' => [],
        'connections' => [],
      ],
      ['phase' => 'exploration'],
      $dungeon_data,
      $before_fingerprint
    );

    $this->assertSame([], $resolved['actor_entities']);
    $this->assertSame([], $resolved['rooms']);
    $this->assertSame([], $resolved['connections']);
  }

  /**
   * @covers ::resolveMutationEnvelopeForPersistence
   */
  public function testResolveMutationEnvelopeForPersistenceKeepsTargetedSlicesWhenChanged(): void {
    $dungeon_data = [
      'active_room_id' => 'room-a',
      'entities' => [['entity_instance_id' => 'pc-1']],
      'rooms' => [['room_id' => 'room-a']],
      'connections' => [['id' => 'conn-a']],
    ];
    $service = $this->createTestService($dungeon_data);

    $resolved = $service->resolveMutationEnvelopeForPersistenceForTest(
      277,
      [
        'campaign_id' => 277,
        'campaign_state' => ['phase' => 'exploration'],
        'actor_entities' => [['entity_instance_id' => 'pc-1']],
        'rooms' => [],
        'connections' => [],
      ],
      ['phase' => 'exploration'],
      $dungeon_data,
      'before-fingerprint'
    );

    $this->assertSame([['entity_instance_id' => 'pc-1']], $resolved['actor_entities']);
    $this->assertSame([], $resolved['rooms']);
    $this->assertSame([], $resolved['connections']);
  }

  /**
   * @covers ::resolveMutationEnvelopeForPersistence
   */
  public function testResolveMutationEnvelopeForPersistenceUsesAuthoritativeGameState(): void {
    $dungeon_data = [
      'active_room_id' => 'room-a',
      'entities' => [],
      'rooms' => [],
      'connections' => [],
    ];
    $service = $this->createTestService($dungeon_data);

    $resolved = $service->resolveMutationEnvelopeForPersistenceForTest(
      277,
      [
        'campaign_id' => 277,
        'campaign_state' => ['phase' => 'stale'],
      ],
      ['phase' => 'encounter', 'state_version' => 12],
      $dungeon_data,
      'stable-fingerprint'
    );

    $this->assertSame(['phase' => 'encounter', 'state_version' => 12], $resolved['campaign_state']);
  }

  /**
   * @covers ::resolveMutationEnvelopeForPersistence
   */
  public function testResolveMutationEnvelopeForPersistenceRejectsInvalidCampaignStateType(): void {
    $service = $this->createTestService(['active_room_id' => 'room-a']);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('campaign_state must be an array');

    $service->resolveMutationEnvelopeForPersistenceForTest(
      277,
      [
        'campaign_id' => 277,
        'campaign_state' => 'invalid',
      ],
      ['phase' => 'exploration'],
      ['active_room_id' => 'room-a'],
      'before-fingerprint'
    );
  }

  /**
   * @covers ::normalizeHandlerActionResult
   */
  public function testNormalizeHandlerActionResultRejectsInvalidEventsType(): void {
    $service = $this->createTestService(['active_room_id' => 'room-a']);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('key "events" must be an array');

    $service->normalizeHandlerActionResultForTest(
      [
        'success' => TRUE,
        'events' => 'not-an-array',
      ],
      277,
      'encounter'
    );
  }

  /**
   * @covers ::normalizePhaseLifecycleResult
   */
  public function testNormalizePhaseLifecycleResultAcceptsLegacyEventList(): void {
    $service = $this->createTestService(['active_room_id' => 'room-a']);

    $normalized = $service->normalizePhaseLifecycleResultForTest(
      [
        ['type' => 'phase_entered', 'phase' => 'encounter'],
      ],
      'encounter',
      'onEnter',
      277
    );

    $this->assertSame([['type' => 'phase_entered', 'phase' => 'encounter']], $normalized['events']);
    $this->assertNull($normalized['mutation_envelope']);
  }

  /**
   * @covers ::normalizePhaseLifecycleResult
   */
  public function testNormalizePhaseLifecycleResultRejectsInvalidMutationEnvelopeType(): void {
    $service = $this->createTestService(['active_room_id' => 'room-a']);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('key "mutation_envelope" must be an array or null');

    $service->normalizePhaseLifecycleResultForTest(
      [
        'events' => [],
        'mutation_envelope' => 'invalid',
      ],
      'encounter',
      'onExit',
      277
    );
  }

  /**
   * @covers ::getFullRuntimeProjection
   */
  public function testGetFullRuntimeProjectionRejectsNonAllowlistedReason(): void {
    $service = $this->createTestService(['active_room_id' => 'room-a']);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('reason "unspecified" is not allowlisted');

    $service->getFullRuntimeProjection(277, NULL, 'unspecified');
  }

  /**
   * @covers ::getFullRuntimeProjection
   */
  public function testGetFullRuntimeProjectionAcceptsAllowlistedPrefixReason(): void {
    $service = $this->createTestService(['active_room_id' => 'room-a']);

    $projection = $service->getFullRuntimeProjection(277, NULL, 'debug:manual_trace');

    $this->assertSame('room-a', $projection['active_room_id'] ?? NULL);
  }

  /**
   * @covers ::getRuntimeHydratedDungeonData
   */
  public function testGetRuntimeHydratedDungeonDataUsesAllowlistedLegacyReason(): void {
    $service = $this->createTestService(['active_room_id' => 'room-a']);

    $projection = $service->getRuntimeHydratedDungeonData(277, NULL);

    $this->assertSame('room-a', $projection['active_room_id'] ?? NULL);
  }

  private function createTestService(array $dungeon_data): object {
    $database = $this->createMock(Connection::class);
    $runtime_sync = $this->createMock(CampaignCharacterRuntimeSyncService::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
    $logger_factory->method('get')->willReturn($logger);
    $event_logger = $this->createMock(GameEventLogger::class);
    $encounter_handler = $this->createMock(EncounterPhaseHandler::class);
    $ai_gm_service = $this->createMock(AiGmService::class);
    $campaign_time_resolver = $this->createMock(CampaignTimeResolverService::class);
    $runtime_bootstrap = $this->createMock(RuntimeBootstrapService::class);
    $dungeon_payload_state_persistence = $this->createMock(DungeonPayloadStatePersistenceService::class);
    $runtime_graph_assembler = $this->createMock(RuntimeGraphAssemblerService::class);
    $campaign_runtime_state_store = $this->createMock(CampaignRuntimeStateStore::class);
    $campaign_runtime_mutation_service = $this->createMock(CampaignRuntimeMutationService::class);
    $actor_runtime_state_store = $this->createMock(ActorRuntimeStateStore::class);
    $actor_runtime_state_store
      ->method('loadActorEntities')
      ->willReturn(is_array($dungeon_data['entities'] ?? NULL) ? $dungeon_data['entities'] : []);
    $room_runtime_state_store = $this->createMock(RoomRuntimeStateStore::class);
    $connection_runtime_state_store = $this->createMock(ConnectionRuntimeStateStore::class);
    $actor_runtime_mutation_service = $this->createMock(ActorRuntimeMutationService::class);
    $room_runtime_mutation_service = $this->createMock(RoomRuntimeMutationService::class);
    $connection_runtime_mutation_service = $this->createMock(ConnectionRuntimeMutationService::class);
    $narration_engine = $this->createMock(NarrationEngine::class);
    $text_to_speech_integration = $this->createMock(TextToSpeechIntegrationService::class);
    $file_url_generator = $this->createMock(FileUrlGeneratorInterface::class);

    return new class(
      $database,
      $runtime_sync,
      $logger_factory,
      $event_logger,
      $encounter_handler,
      $ai_gm_service,
      $campaign_time_resolver,
      $runtime_bootstrap,
      $dungeon_payload_state_persistence,
      $runtime_graph_assembler,
      $campaign_runtime_state_store,
      $campaign_runtime_mutation_service,
      $actor_runtime_state_store,
      $room_runtime_state_store,
      $connection_runtime_state_store,
      $actor_runtime_mutation_service,
      $room_runtime_mutation_service,
      $connection_runtime_mutation_service,
      $narration_engine,
      $text_to_speech_integration,
      $file_url_generator,
      $dungeon_data
    ) extends GameCoordinatorService {

      public function __construct(
        Connection $database,
        CampaignCharacterRuntimeSyncService $runtime_sync,
        LoggerChannelFactoryInterface $logger_factory,
        GameEventLogger $event_logger,
        EncounterPhaseHandler $encounter_handler,
        AiGmService $ai_gm_service,
        CampaignTimeResolverService $campaign_time_resolver,
        RuntimeBootstrapService $runtime_bootstrap,
        DungeonPayloadStatePersistenceService $dungeon_payload_state_persistence,
        RuntimeGraphAssemblerService $runtime_graph_assembler,
        CampaignRuntimeStateStore $campaign_runtime_state_store,
        CampaignRuntimeMutationService $campaign_runtime_mutation_service,
        ActorRuntimeStateStore $actor_runtime_state_store,
        RoomRuntimeStateStore $room_runtime_state_store,
        ConnectionRuntimeStateStore $connection_runtime_state_store,
        ActorRuntimeMutationService $actor_runtime_mutation_service,
        RoomRuntimeMutationService $room_runtime_mutation_service,
        ConnectionRuntimeMutationService $connection_runtime_mutation_service,
        ?NarrationEngine $narration_engine,
        ?TextToSpeechIntegrationService $text_to_speech_integration,
        ?FileUrlGeneratorInterface $file_url_generator,
        private readonly array $fixture
      ) {
        parent::__construct(
          $database,
          $runtime_sync,
          $logger_factory,
          $event_logger,
          $encounter_handler,
          $ai_gm_service,
          $campaign_time_resolver,
          $runtime_bootstrap,
          $dungeon_payload_state_persistence,
          $runtime_graph_assembler,
          $campaign_runtime_state_store,
          $campaign_runtime_mutation_service,
          $actor_runtime_state_store,
          $room_runtime_state_store,
          $connection_runtime_state_store,
          $actor_runtime_mutation_service,
          $room_runtime_mutation_service,
          $connection_runtime_mutation_service,
          $narration_engine,
          $text_to_speech_integration,
          $file_url_generator
        );
      }

      public function resolveActorIdForCharacterIdForTest(int $campaign_id, int $character_id): ?string {
        return parent::resolveActorIdForCharacterId($campaign_id, $character_id);
      }

      public function resolveActionAvailabilityContextForTest(int $campaign_id, ?string $actor_id = NULL): ?array {
        return parent::resolveActionAvailabilityContext($campaign_id, $actor_id);
      }

      public function emptyActionAvailabilityPayloadForTest(): array {
        return parent::emptyActionAvailabilityPayload();
      }

      public function resolveMutationEnvelopeForPersistenceForTest(
        int $campaign_id,
        ?array $candidate_envelope,
        array $game_state,
        array $dungeon_data,
        string $before_non_game_state_fingerprint
      ): array {
        return parent::resolveMutationEnvelopeForPersistence(
          $campaign_id,
          $candidate_envelope,
          $game_state,
          $dungeon_data,
          $before_non_game_state_fingerprint
        );
      }

      public function computeNonGameStatePayloadFingerprintForTest(array $dungeon_data): string {
        return parent::computeNonGameStatePayloadFingerprint($dungeon_data);
      }

      public function normalizeHandlerActionResultForTest(mixed $raw_result, int $campaign_id, string $phase): array {
        return parent::normalizeHandlerActionResult($raw_result, $campaign_id, $phase);
      }

      public function normalizePhaseLifecycleResultForTest(mixed $raw_result, string $phase, string $hook, int $campaign_id): array {
        return parent::normalizePhaseLifecycleResult($raw_result, $phase, $hook, $campaign_id);
      }

      protected function loadDungeonData(
        int $campaign_id,
        ?string $preferred_actor_id = NULL,
        bool $rebuild_runtime_graph = TRUE,
        int $room_scope_depth = -1,
        ?string $requested_room_id = NULL
      ): ?array {
        return $this->fixture;
      }

    };
  }

}
