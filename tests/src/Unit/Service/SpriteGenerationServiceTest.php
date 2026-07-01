<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;
use Drupal\dungeoncrawler_content\Service\ImageGenerationIntegrationService;
use Drupal\dungeoncrawler_content\Service\SpriteGenerationService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\SpriteGenerationService
 * @group dungeoncrawler_content
 */
class SpriteGenerationServiceTest extends UnitTestCase {

  /**
   * @covers ::generateAndPersist
   */
  public function testGenerateAndPersistFailsWhenStorageFails(): void {
    $integration = $this->createMock(ImageGenerationIntegrationService::class);
    $integration->method('generateImage')
      ->willReturn([
        'success' => TRUE,
        'provider' => 'vertex',
        'output' => ['image_data_uri' => 'data:image/png;base64,AA=='],
      ]);

    $repository = $this->createMock(GeneratedImageRepository::class);
    $repository->method('persistGeneratedImage')
      ->willReturn([
        'stored' => FALSE,
        'reason' => 'optimization_failed',
      ]);

    $service = new TestSpriteGenerationService(
      $integration,
      $repository,
      $this->buildLoggerFactory(),
    );

    $result = $service->callGenerateAndPersist('door_wood_tavern', [], 123, 7, []);

    $this->assertNull($result['url']);
    $this->assertFalse($result['generated']);
    $this->assertFalse($result['cached']);
    $this->assertSame('storage_failed: optimization_failed', $result['error']);
  }

  /**
   * @covers ::generateAndPersist
   */
  public function testGenerateAndPersistReturnsUrlWhenStorageSucceeds(): void {
    $integration = $this->createMock(ImageGenerationIntegrationService::class);
    $integration->method('generateImage')
      ->willReturn([
        'success' => TRUE,
        'provider' => 'vertex',
        'output' => ['image_data_uri' => 'data:image/png;base64,AA=='],
      ]);

    $repository = $this->createMock(GeneratedImageRepository::class);
    $repository->method('persistGeneratedImage')
      ->willReturn([
        'stored' => TRUE,
        'url' => 'https://example.com/generated/sprite.webp',
      ]);

    $service = new TestSpriteGenerationService(
      $integration,
      $repository,
      $this->buildLoggerFactory(),
    );

    $result = $service->callGenerateAndPersist('door_wood_tavern', [], 123, 7, []);

    $this->assertSame('https://example.com/generated/sprite.webp', $result['url']);
    $this->assertTrue($result['generated']);
    $this->assertFalse($result['cached']);
    $this->assertNull($result['error']);
  }

  /**
   * Builds a logger factory mock.
   */
  private function buildLoggerFactory(): LoggerChannelFactoryInterface {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($logger);

    return $factory;
  }

}

/**
 * Test adapter exposing protected generation method.
 */
class TestSpriteGenerationService extends SpriteGenerationService {

  /**
   * Calls protected generateAndPersist() for unit testing.
   */
  public function callGenerateAndPersist(string $sprite_id, array $object_definition, ?int $campaign_id, int $owner_uid, array $options): array {
    return $this->generateAndPersist($sprite_id, $object_definition, $campaign_id, $owner_uid, $options);
  }

  /**
   * Returns deterministic prompt text for tests.
   */
  protected function buildSpritePrompt(string $sprite_id, array $object_definition): string {
    return 'test prompt for ' . $sprite_id;
  }

}
