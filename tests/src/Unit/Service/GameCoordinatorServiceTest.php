<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\AiGmService;
use Drupal\dungeoncrawler_content\Service\CampaignCharacterRuntimeSyncService;
use Drupal\dungeoncrawler_content\Service\CampaignTimeResolverService;
use Drupal\dungeoncrawler_content\Service\EncounterPhaseHandler;
use Drupal\dungeoncrawler_content\Service\GameCoordinatorService;
use Drupal\dungeoncrawler_content\Service\GameEventLogger;
use Drupal\dungeoncrawler_content\Service\NarrationEngine;
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
          $narration_engine,
          $text_to_speech_integration,
          $file_url_generator
        );
      }

      public function resolveActorIdForCharacterIdForTest(int $campaign_id, int $character_id): ?string {
        return parent::resolveActorIdForCharacterId($campaign_id, $character_id);
      }

      protected function loadDungeonData(int $campaign_id, ?string $preferred_actor_id = NULL): ?array {
        return $this->fixture;
      }

    };
  }

}
