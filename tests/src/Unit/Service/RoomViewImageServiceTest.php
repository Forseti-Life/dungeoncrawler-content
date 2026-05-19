<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\dungeoncrawler_content\Service\ChatSessionManager;
use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;
use Drupal\dungeoncrawler_content\Service\ImageGenerationIntegrationService;
use Drupal\dungeoncrawler_content\Service\RoomViewImageService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests transition-only room view gallery behavior.
 *
 * @group dungeoncrawler_content
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\RoomViewImageService
 */
class RoomViewImageServiceTest extends UnitTestCase {

  /**
   * @covers ::getRoomViewImage
   */
  public function testGetRoomViewImageUsesTransitionSnapshots(): void {
    $chat_session_manager = $this->createMock(ChatSessionManager::class);
    $chat_session_manager->method('ensureRoomSession')
      ->willReturn(['id' => 455]);

    $service = new TestRoomViewImageService(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      $this->createMock(ImageGenerationIntegrationService::class),
      $chat_session_manager,
      $this->createMock(GeneratedImageRepository::class),
      $this->createMock(FileSystemInterface::class),
    );

    $service->latestDungeonRecord = [
      'dungeon_id' => 'dungeon-1',
      'dungeon_data' => json_encode([
        'rooms' => [
          'room-1' => [
            'room_id' => 'room-1',
            'name' => 'The Gilded Tankard',
            'description' => 'A warm tavern room with tense corners.',
            'room_type' => 'tavern',
            'size_category' => 'medium',
          ],
        ],
      ]),
    ];
    $service->transitionEntries = [[
      'id' => 'transition-1',
      'entry_type' => 'phase_transition',
      'title' => 'Encounter Begins',
      'summary' => 'Exploration gives way to a tavern brawl.',
      'status' => 'ready',
      'provider' => 'vertex',
      'mode' => RoomViewImageService::GALLERY_MODE_TRANSITION,
      'created' => 1778984943,
      'message_window' => [
        'index' => 1,
        'count' => 0,
        'start_id' => 0,
        'end_id' => 0,
        'label' => 'Exploration -> Encounter',
      ],
      'transition' => [
        'type' => 'encounter_start',
        'phase' => 'encounter',
        'encounter_id' => 292,
      ],
      'image' => [
        'url' => 'https://example.com/transition-1.png',
        'data_uri' => NULL,
        'mime_type' => 'image/png',
      ],
      'room' => [
        'room_id' => 'room-1',
        'name' => 'The Gilded Tankard',
      ],
    ]];
    $service->establishingEntry = [
      'id' => 'establishing-shot',
      'entry_type' => 'establishing',
      'title' => 'Establishing Shot',
      'summary' => 'A warm tavern room with tense corners.',
      'status' => 'ready',
      'provider' => 'vertex',
      'mode' => 'cache',
      'created' => 0,
      'message_window' => [
        'index' => 0,
        'count' => 0,
        'label' => 'Room opening view',
      ],
      'image' => [
        'url' => 'https://example.com/establishing.png',
        'data_uri' => NULL,
        'mime_type' => 'image/png',
      ],
    ];

    $result = $service->getRoomViewImage(66, 'room-1');

    $this->assertSame('transition_gallery', $result['mode']);
    $this->assertSame(1, $result['generated_entry_count']);
    $this->assertSame(0, $result['message_batch_size']);
    $this->assertStringContainsString('transition snapshot', $result['message']);
    $this->assertStringNotContainsString('50 room messages', $result['message']);
    $this->assertSame('establishing', $result['entries'][0]['entry_type']);
    $this->assertSame('phase_transition', $result['entries'][1]['entry_type']);
  }

  /**
   * @covers ::getRoomViewImage
   */
  public function testGetRoomViewImageExplainsTransitionOnlyIdleState(): void {
    $chat_session_manager = $this->createMock(ChatSessionManager::class);
    $chat_session_manager->method('ensureRoomSession')
      ->willReturn(['id' => 455]);

    $service = new TestRoomViewImageService(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      $this->createMock(ImageGenerationIntegrationService::class),
      $chat_session_manager,
      $this->createMock(GeneratedImageRepository::class),
      $this->createMock(FileSystemInterface::class),
    );

    $service->latestDungeonRecord = [
      'dungeon_id' => 'dungeon-1',
      'dungeon_data' => json_encode([
        'rooms' => [
          'room-1' => [
            'room_id' => 'room-1',
            'name' => 'The Gilded Tankard',
            'description' => 'A warm tavern room with tense corners.',
          ],
        ],
      ]),
    ];
    $service->transitionEntries = [];
    $service->establishingEntry = NULL;

    $result = $service->getRoomViewImage(66, 'room-1');

    $this->assertSame('establishing', $result['mode']);
    $this->assertSame('Scene snapshots appear only when the room transitions between exploration and encounter.', $result['message']);
    $this->assertSame(0, $result['generated_entry_count']);
  }

  /**
   * @covers ::persistGalleryGenerationResult
   */
  public function testPersistGalleryGenerationResultPromotesDataUriToStoredUrl(): void {
    $chat_session_manager = $this->createMock(ChatSessionManager::class);
    $chat_session_manager->method('ensureRoomSession')
      ->willReturn(['id' => 455]);

    $generated_image_repository = $this->createMock(GeneratedImageRepository::class);
    $generated_image_repository->expects($this->once())
      ->method('persistGeneratedImage')
      ->willReturn([
        'stored' => TRUE,
        'url' => '/sites/default/files/generated-images/2026/05/transition-1.png',
      ]);

    $service = new TestRoomViewImageService(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      $this->createMock(ImageGenerationIntegrationService::class),
      $chat_session_manager,
      $generated_image_repository,
      $this->createMock(FileSystemInterface::class),
    );

    $result = $service->callPersistGalleryGenerationResult([
      'success' => TRUE,
      'provider' => 'vertex',
      'status' => 'ready',
      'output' => [
        'image_data_uri' => 'data:image/png;base64,ZmFrZQ==',
        'mime_type' => 'image/png',
      ],
      'payload' => [],
    ]);

    $this->assertSame('/sites/default/files/generated-images/2026/05/transition-1.png', $result['image_url']);
    $this->assertNull($result['image_data_uri']);
  }

  /**
   * @covers ::persistGalleryGenerationResult
   */
  public function testPersistGalleryGenerationResultLeavesExistingUrlUntouched(): void {
    $chat_session_manager = $this->createMock(ChatSessionManager::class);
    $chat_session_manager->method('ensureRoomSession')
      ->willReturn(['id' => 455]);

    $generated_image_repository = $this->createMock(GeneratedImageRepository::class);
    $generated_image_repository->expects($this->never())
      ->method('persistGeneratedImage');

    $service = new TestRoomViewImageService(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      $this->createMock(ImageGenerationIntegrationService::class),
      $chat_session_manager,
      $generated_image_repository,
      $this->createMock(FileSystemInterface::class),
    );

    $result = $service->callPersistGalleryGenerationResult([
      'success' => TRUE,
      'output' => [
        'image_url' => '/sites/default/files/generated-images/existing.png',
      ],
      'payload' => [],
    ]);

    $this->assertSame('/sites/default/files/generated-images/existing.png', $result['image_url']);
    $this->assertNull($result['image_data_uri']);
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

/**
 * Test double for RoomViewImageService that stubs expensive collaborators.
 */
class TestRoomViewImageService extends RoomViewImageService {

  /**
   * Fake dungeon record returned by the test.
   *
   * @var array<string, mixed>|null
   */
  public ?array $latestDungeonRecord = NULL;

  /**
   * Fake transition gallery entries returned by the test.
   *
   * @var array<int, array<string, mixed>>
   */
  public array $transitionEntries = [];

  /**
   * Fake establishing entry returned by the test.
   *
   * @var array<string, mixed>|null
   */
  public ?array $establishingEntry = NULL;

  /**
   * Fake integration availability flag.
   */
  public bool $vertexAvailable = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function loadLatestDungeonRecord(int $campaign_id): ?array {
    return $this->latestDungeonRecord;
  }

  /**
   * {@inheritdoc}
   */
  protected function resolveCampaignRoomCacheObjectId(int $campaign_id, string $room_id, array $room, array $dungeon_data): string {
    return $room_id;
  }

  /**
   * {@inheritdoc}
   */
  protected function hydrateRoomFromCampaignRecord(int $campaign_id, string $cache_object_id, array $room): array {
    return $room;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildTransitionGalleryEntries(int $campaign_id, string $dungeon_id, string $room_id, array $room, int $room_session_id, array $portrait_references = []): array {
    return $this->transitionEntries;
  }

  /**
   * {@inheritdoc}
   */
  protected function loadRoomPortraitReferences(int $campaign_id, string $room_id, ?string $campaign_room_cache_key = NULL, array $room = []): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function buildEstablishingEntry(int $campaign_id, string $dungeon_id, string $room_id, array $room, ?string $campaign_room_cache_key = NULL, array $portrait_references = []): ?array {
    return $this->establishingEntry;
  }

  /**
   * {@inheritdoc}
   */
  protected function resolveRoomViewProvider(array $portrait_references = []): ?string {
    return $this->vertexAvailable ? 'vertex' : NULL;
  }

  /**
   * Test proxy for protected gallery persistence helper.
   *
   * @param array<string, mixed> $generation_result
   *   Raw generation result payload.
   *
   * @return array{image_url:?string,image_data_uri:?string}
   *   Normalized stored image fields.
   */
  public function callPersistGalleryGenerationResult(array $generation_result): array {
    return $this->persistGalleryGenerationResult($generation_result);
  }

}
