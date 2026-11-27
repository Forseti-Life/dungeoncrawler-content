<?php

declare(strict_types=1);

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\GeneratedImageOptimizationService;
use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;
use Drupal\file\FileInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @group dungeoncrawler_content
 */
class GeneratedImageRepositoryUploadTest extends UnitTestCase {

  public function testUploadUsesTheGeneratedImageVersioningPath(): void {
    $path = tempnam(sys_get_temp_dir(), 'dc-image-upload-');
    file_put_contents($path, 'uploaded-image');
    $file = $this->createMock(FileInterface::class);
    $file->method('getFileUri')->willReturn($path);
    $file->method('getMimeType')->willReturn('image/png');
    $file->method('getFilename')->willReturn('portrait.png');
    $captured = NULL;
    $repository = new class(
      $this->createMock(Connection::class),
      $this->createMock(FileUrlGeneratorInterface::class),
      $this->createMock(FileSystemInterface::class),
      $this->createMock(UuidInterface::class),
      $this->createMock(TimeInterface::class),
      $this->createMock(LoggerChannelFactoryInterface::class),
      $this->createMock(GeneratedImageOptimizationService::class),
      $captured,
    ) extends GeneratedImageRepository {

      public function __construct(
        $database,
        $file_url_generator,
        $file_system,
        $uuid,
        $time,
        $logger_factory,
        $image_optimizer,
        private mixed &$captured,
      ) {
        parent::__construct(
          $database,
          $file_url_generator,
          $file_system,
          $uuid,
          $time,
          $logger_factory,
          $image_optimizer,
        );
      }

      public function persistGeneratedImage(array $generation_result, array $link_context = []): array {
        $this->captured = [$generation_result, $link_context];
        return ['stored' => TRUE, 'version_number' => 2];
      }

    };

    try {
      $result = $repository->persistUploadedImage($file, [
        'owner_uid' => 1,
        'table_name' => 'dc_campaign_characters',
        'object_id' => '42',
        'slot' => 'portrait',
        'variant' => 'original',
        'is_primary' => 1,
      ]);
    }
    finally {
      unlink($path);
    }

    $this->assertTrue($result['stored']);
    $this->assertSame('upload', $captured[0]['provider']);
    $this->assertSame('data:image/png;base64,dXBsb2FkZWQtaW1hZ2U=', $captured[0]['output']['image_data_uri']);
    $this->assertSame('42', $captured[1]['object_id']);
    $this->assertSame(1, $captured[1]['is_primary']);
  }

}
