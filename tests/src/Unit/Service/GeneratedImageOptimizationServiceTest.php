<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\GeneratedImageOptimizationService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\GeneratedImageOptimizationService
 * @group dungeoncrawler_content
 */
class GeneratedImageOptimizationServiceTest extends UnitTestCase {

  /**
   * @covers ::optimizeDataUri
   */
  public function testOptimizeDataUriResizesAndConvertsToWebp(): void {
    $service = new GeneratedImageOptimizationService();
    $source_binary = $this->buildPngBinary(1600, 800);
    $data_uri = 'data:image/png;base64,' . base64_encode($source_binary);

    $result = $service->optimizeDataUri($data_uri);

    $this->assertTrue($result['ok']);
    $this->assertSame('image/webp', $result['mime_type']);
    $this->assertSame(1024, $result['width']);
    $this->assertSame(512, $result['height']);
    $this->assertSame(strlen($result['binary']), $result['bytes']);
    $this->assertNotEmpty($result['sha256']);
    $this->assertTrue($result['resized']);
  }

  /**
   * @covers ::optimizeDataUri
   */
  public function testOptimizeDataUriRejectsInvalidDataUri(): void {
    $service = new GeneratedImageOptimizationService();

    $result = $service->optimizeDataUri('not-a-data-uri');

    $this->assertFalse($result['ok']);
    $this->assertSame('invalid_data_uri', $result['reason']);
  }

  /**
   * Builds a PNG binary fixture.
   */
  private function buildPngBinary(int $width, int $height): string {
    $image = imagecreatetruecolor($width, $height);
    $background = imagecolorallocate($image, 24, 87, 196);
    imagefilledrectangle($image, 0, 0, $width, $height, $background);

    $line = imagecolorallocate($image, 255, 255, 255);
    imageline($image, 0, 0, $width - 1, $height - 1, $line);
    imageline($image, 0, $height - 1, $width - 1, 0, $line);

    ob_start();
    imagepng($image, NULL, 6);
    $binary = ob_get_clean();
    imagedestroy($image);

    return is_string($binary) ? $binary : '';
  }

}
