<?php

declare(strict_types=1);

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;
use Drupal\dungeoncrawler_content\Service\ImageGenerationIntegrationService;
use Drupal\dungeoncrawler_content\Service\InstitutionLibraryService;
use Drupal\dungeoncrawler_content\Service\StorylinePlaceImageService;
use Drupal\Tests\UnitTestCase;

/**
 * @group dungeoncrawler_content
 */
class StorylinePlaceImageServiceTest extends UnitTestCase {

  public function testRegenerationCreatesAndReturnsASelectableImageVersion(): void {
    $images = $this->createMock(ImageGenerationIntegrationService::class);
    $images->method('generateImage')->willReturn([
      'success' => TRUE,
      'provider' => 'gemini',
      'payload' => ['requested_by_uid' => 1, 'prompt' => 'room'],
      'output' => ['image_data_uri' => 'data:image/png;base64,eA==', 'mime_type' => 'image/png'],
    ]);
    $repository = $this->createMock(GeneratedImageRepository::class);
    $repository->expects($this->once())
      ->method('persistGeneratedImage')
      ->with(
        $this->callback(static fn(array $result): bool => !empty($result['success'])),
        $this->callback(static fn(array $link): bool => $link['is_primary'] === 1
          && $link['table_name'] === 'dungeoncrawler_content_rooms'
          && $link['slot'] === 'room_view'),
      )
      ->willReturn([
        'stored' => TRUE,
        'image_id' => 22,
        'image_uuid' => '22222222-2222-4222-8222-222222222222',
        'version_number' => 2,
        'url' => '/version-2.png',
      ]);
    $repository->method('loadImagesForObject')->willReturnCallback(
      static fn(string $table): array => $table === 'dungeoncrawler_content_rooms' ? [
        [
          'image_id' => 22,
          'image_uuid' => '22222222-2222-4222-8222-222222222222',
          'version_number' => 2,
          'is_primary' => 1,
          'file_uri' => '/version-2.png',
          'created' => 200,
        ],
        [
          'image_id' => 11,
          'image_uuid' => '11111111-1111-4111-8111-111111111111',
          'version_number' => 1,
          'is_primary' => 0,
          'file_uri' => '/version-1.png',
          'created' => 100,
        ],
      ] : [],
    );
    $repository->method('resolveClientUrl')->willReturnCallback(
      static fn(array $row): string => (string) $row['file_uri'],
    );
    $service = new StorylinePlaceImageService($images, $repository);

    $result = $service->generate('room', 'storm_dais', [
      'objects' => ['rooms' => ['generation_specs' => [[
        'room_id' => 'storm_dais',
        'narrative' => 'A storm-wracked summit platform.',
      ]]]],
    ], 1);

    $this->assertSame(2, $result['version_number']);
    $this->assertSame([2, 1], array_column($result['versions'], 'version_number'));
    $this->assertSame('/version-2.png', $result['versions'][0]['url']);
    $this->assertTrue($result['versions'][0]['is_primary']);
    $this->assertFalse($result['versions'][1]['is_primary']);
  }

  public function testVersionHistoryRemainsAvailableToTheEditor(): void {
    $repository = $this->createMock(GeneratedImageRepository::class);
    $repository->method('loadImagesForObject')->willReturn([
      ['image_id' => 2, 'version_number' => 2, 'is_primary' => 1, 'file_uri' => '/two.png', 'created' => 2],
      ['image_id' => 1, 'version_number' => 1, 'is_primary' => 0, 'file_uri' => '/one.png', 'created' => 1],
    ]);
    $repository->method('resolveClientUrl')->willReturnCallback(
      static fn(array $row): string => (string) $row['file_uri'],
    );
    $service = new StorylinePlaceImageService(
      $this->createMock(ImageGenerationIntegrationService::class),
      $repository,
    );

    $this->assertSame('/two.png', $service->previewUrl('dungeon', 'district-one'));
    $this->assertSame([2, 1], array_column($service->versions('dungeon', 'district-one'), 'version_number'));
  }

  public function testInstitutionImagesUseTheSameGenerationAndVersionedRepository(): void {
    $images = $this->createMock(ImageGenerationIntegrationService::class);
    $images->method('generateImage')->willReturn([
      'success' => TRUE,
      'payload' => ['requested_by_uid' => 1, 'prompt' => 'institution'],
      'output' => ['image_data_uri' => 'data:image/png;base64,eA==', 'mime_type' => 'image/png'],
    ]);
    $institutions = $this->createMock(InstitutionLibraryService::class);
    $institutions->method('loadRow')->with('storm_conclave')->willReturn([
      'id' => 55,
      'source_asset_id' => 'storm_conclave',
    ]);
    $repository = $this->createMock(GeneratedImageRepository::class);
    $repository->expects($this->once())
      ->method('persistGeneratedImage')
      ->with(
        $this->anything(),
        $this->callback(static fn(array $link): bool => $link['table_name'] === InstitutionLibraryService::TABLE
          && $link['object_id'] === '55'
          && $link['slot'] === 'emblem'
          && $link['is_primary'] === 1),
      )
      ->willReturn([
        'stored' => TRUE,
        'image_id' => 5,
        'version_number' => 1,
        'url' => '/institution.png',
      ]);
    $repository->method('loadImagesForObject')->willReturn([]);
    $service = new StorylinePlaceImageService($images, $repository, $institutions);

    $result = $service->generate('institution', 'storm_conclave', [
      'objects' => ['institutions' => [[
        'institution_id' => 'storm_conclave',
        'name' => 'Storm Conclave',
        'domain' => 'guild',
        'description' => 'A council of storm wardens.',
        'purpose' => 'Guard the summit approaches.',
      ]]],
    ], 1);

    $this->assertTrue($result['stored']);
    $this->assertSame(1, $result['version_number']);
  }

}
