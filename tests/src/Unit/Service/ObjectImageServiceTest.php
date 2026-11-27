<?php

declare(strict_types=1);

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;
use Drupal\dungeoncrawler_content\Service\ImageGenerationIntegrationService;
use Drupal\dungeoncrawler_content\Service\ObjectImageService;
use Drupal\Tests\UnitTestCase;

/**
 * @group dungeoncrawler_content
 */
class ObjectImageServiceTest extends UnitTestCase {

  public function testCanonicalDefinitionUsesAStableVersionedImageTarget(): void {
    $definitions = $this->createMock(CanonicalDefinitionService::class);
    $definitions->method('definitionPayload')->willReturn([
      'item_id' => 'healers_tools',
      'name' => 'Healer\'s Tools',
      'description' => 'A compact kit of bandages and herbs.',
    ]);
    $images = $this->createMock(ImageGenerationIntegrationService::class);
    $images->method('generateImage')->willReturn([
      'success' => TRUE,
      'payload' => ['requested_by_uid' => 1, 'prompt' => 'tools'],
      'output' => ['image_data_uri' => 'data:image/png;base64,eA==', 'mime_type' => 'image/png'],
    ]);
    $repository = $this->createMock(GeneratedImageRepository::class);
    $repository->expects($this->once())
      ->method('persistGeneratedImage')
      ->with(
        $this->anything(),
        $this->callback(static fn(array $link): bool => $link['table_name'] === 'dc_dungeon_sprites'
          && $link['object_id'] === 'item_healers_tools'
          && $link['slot'] === 'sprite'
          && $link['is_primary'] === 1),
      )
      ->willReturn(['stored' => TRUE, 'image_id' => 10, 'version_number' => 1, 'url' => '/tools.png']);
    $repository->method('loadImagesForObject')->willReturn([]);
    $service = new ObjectImageService($images, $repository, $definitions);

    $result = $service->generate('item', 'healers_tools', '', 1);

    $this->assertTrue($result['stored']);
    $this->assertSame(1, $result['version_number']);
  }

  public function testOldDefinitionImagesRemainInHistory(): void {
    $definitions = $this->createMock(CanonicalDefinitionService::class);
    $definitions->method('definitionPayload')->willReturn([
      'obstacle_id' => 'column',
      'name' => 'Column',
    ]);
    $repository = $this->createMock(GeneratedImageRepository::class);
    $repository->method('loadImagesForObject')->willReturn([
      ['image_id' => 2, 'image_uuid' => 'two', 'version_number' => 2, 'is_primary' => 1, 'file_uri' => '/two.png', 'created' => 2],
      ['image_id' => 1, 'image_uuid' => 'one', 'version_number' => 1, 'is_primary' => 0, 'file_uri' => '/one.png', 'created' => 1],
    ]);
    $repository->method('resolveClientUrl')->willReturnCallback(
      static fn(array $row): string => (string) $row['file_uri'],
    );
    $service = new ObjectImageService(
      $this->createMock(ImageGenerationIntegrationService::class),
      $repository,
      $definitions,
    );

    $this->assertSame([2, 1], array_column($service->versions('obstacle', 'column'), 'version_number'));
  }

  public function testGeneratedDefinitionUsesRuntimeSpriteIdWithoutMutatingDefinition(): void {
    $definitions = $this->createMock(CanonicalDefinitionService::class);
    $definitions->method('definitionPayload')->willReturn([
      'trap_id' => 'darts',
      'name' => 'Poison Darts',
      'description' => 'Hidden darts.',
      'visual' => [],
    ]);
    $definitions->method('currentVersion')->willReturn('1.0.0');
    $definitions->expects($this->never())->method('saveDefinition');
    $images = $this->createMock(ImageGenerationIntegrationService::class);
    $images->method('generateImage')->willReturn([
      'success' => TRUE,
      'payload' => ['requested_by_uid' => 1, 'prompt' => 'darts'],
      'output' => ['image_data_uri' => 'data:image/png;base64,eA==', 'mime_type' => 'image/png'],
    ]);
    $repository = $this->createMock(GeneratedImageRepository::class);
    $repository->method('persistGeneratedImage')->willReturn([
      'stored' => TRUE,
      'image_id' => 10,
      'version_number' => 1,
      'url' => '/darts.png',
    ]);
    $repository->method('loadImagesForObject')->willReturn([]);
    $service = new ObjectImageService($images, $repository, $definitions);

    $result = $service->generate('trap', 'darts', '', 1);

    $this->assertTrue($result['stored']);
  }

  public function testPromotingAnOldImageCreatesANewCurrentLinkVersion(): void {
    $repository = $this->createMock(GeneratedImageRepository::class);
    $repository->expects($this->once())
      ->method('promoteImageVersion')
      ->with(
        'dungeoncrawler_content_rooms',
        'storm-dais',
        NULL,
        'room_view',
        'establishing',
        'old-image-uuid',
        'global',
        'owner',
      )
      ->willReturn([
        'stored' => TRUE,
        'image_uuid' => 'old-image-uuid',
        'version_number' => 3,
        'url' => '/old-image-now-current.png',
      ]);
    $repository->method('loadImagesForObject')->willReturn([]);
    $service = new ObjectImageService(
      $this->createMock(ImageGenerationIntegrationService::class),
      $repository,
      $this->createMock(CanonicalDefinitionService::class),
    );

    $result = $service->promote('room', 'storm-dais', 'old-image-uuid');

    $this->assertSame(3, $result['version_number']);
    $this->assertSame('old-image-uuid', $result['image_uuid']);
  }

}
