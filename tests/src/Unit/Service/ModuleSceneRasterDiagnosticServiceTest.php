<?php

declare(strict_types=1);

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleSceneRasterDiagnosticService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleSceneRasterDiagnosticService::class)]
final class ModuleSceneRasterDiagnosticServiceTest extends TestCase {

  private array $archives = [];

  protected function tearDown(): void {
    foreach ($this->archives as $path) {
      unlink($path);
    }
    parent::tearDown();
  }

  private function resource(string $entry = 'project-authored/maps/dungeon.png'): array {
    return [
      'resource_id' => 'project-authored-synthetic-scene',
      'resource_type' => 'scene',
      'source' => ['source_context' => [
        'width' => 400, 'height' => 400,
        'grid_size' => 100, 'grid_type' => 1, 'grid_distance' => 5, 'grid_units' => 'ft',
        'background_archive_entry' => $entry,
        'background_src' => 'modules/project-authored/maps/' . basename($entry),
      ]],
    ];
  }

  private function raster(bool $blackMatte = TRUE, int $width = 32): string {
    $image = imagecreatetruecolor($width, 32);
    $black = imagecolorallocate($image, 0, 0, 0);
    $stone = imagecolorallocate($image, 157, 145, 133);
    imagefill($image, 0, 0, $blackMatte ? $black : $stone);
    if ($blackMatte) {
      imagefilledrectangle($image, 8, 8, 23, 23, $stone);
      imagefilledrectangle($image, 16, 8, 19, 11, $black);
    }
    ob_start();
    imagepng($image);
    $binary = ob_get_clean();
    imagedestroy($image);
    self::assertIsString($binary);
    return $binary;
  }

  private function archive(string $binary, string $entry = 'project-authored/maps/dungeon.png'): string {
    $path = dirname(__DIR__, 3) . '/fixtures/module_conversion/diagnostic-' . bin2hex(random_bytes(8)) . '.zip';
    $zip = new \ZipArchive();
    self::assertTrue($zip->open($path, \ZipArchive::CREATE));
    self::assertTrue($zip->addFromString($entry, $binary));
    self::assertTrue($zip->close());
    $this->archives[] = $path;
    return $path;
  }

  public function testOnlyUnreviewedCandidatesAndHashesLeaveSyntheticArchive(): void {
    $binary = $this->raster();
    $path = $this->archive($binary);
    $result = (new ModuleSceneRasterDiagnosticService())->diagnoseArchive($path, $this->resource());
    self::assertSame('black_matte_occupancy_diagnostic_v1', $result['schema_version']);
    self::assertSame('UNREVIEWED', $result['review_status']);
    self::assertFalse($result['approvable_as_trace']);
    self::assertSame(hash_file('sha256', $path), $result['archive_sha256']);
    self::assertSame(hash('sha256', $binary), $result['image_sha256']);
    self::assertSame(['surface_candidate' => 3, 'void_candidate' => 12, 'ambiguous' => 1, 'total' => 16], $result['counts']);
    self::assertSame(['x' => 2, 'y' => 1, 'review_status' => 'UNREVIEWED', 'candidate' => 'ambiguous',
      'dark_fraction' => 0.25, 'ambiguity_flags' => ['mixed_dark_and_nondark_pixels']], $result['cells'][6]);
    foreach ($result['cells'] as $cell) {
      self::assertArrayNotHasKey('in_footprint', $cell);
      self::assertArrayNotHasKey('terrain_type', $cell);
      self::assertSame('UNREVIEWED', $cell['review_status']);
    }
    self::assertArrayNotHasKey('ports', $result);
  }

  public function testRejectsRoadAndOverlandEvenWhenRasterLooksLikeDungeon(): void {
    $path = $this->archive($this->raster(), 'project-authored/maps/ForestRoad.png');
    try {
      (new ModuleSceneRasterDiagnosticService())->diagnoseArchive($path, $this->resource('project-authored/maps/ForestRoad.png'));
      self::fail('A road was treated as a black-void dungeon.');
    }
    catch (\DomainException $exception) {
      self::assertSame('raster_diagnostic_background_unsupported', $exception->getMessage());
    }
    $resource = $this->resource('project-authored/maps/ForestRoad.png');
    $resource['source']['source_context']['grid_type'] = 5;
    $this->expectExceptionMessage('raster_diagnostic_square_grid_unsupported');
    (new ModuleSceneRasterDiagnosticService())->diagnoseArchive($path, $resource);
  }

  public function testRejectsMissingMatteAndMisalignedPixels(): void {
    $path = $this->archive($this->raster(FALSE));
    try {
      (new ModuleSceneRasterDiagnosticService())->diagnoseArchive($path, $this->resource());
      self::fail('A road-colored raster was treated as a black-void map.');
    }
    catch (\DomainException $exception) {
      self::assertSame('raster_diagnostic_black_matte_absent', $exception->getMessage());
    }
    $path = $this->archive($this->raster(TRUE, 31));
    $this->expectExceptionMessage('raster_diagnostic_image_alignment_unsupported');
    (new ModuleSceneRasterDiagnosticService())->diagnoseArchive($path, $this->resource());
  }

  public function testMissingSourceEntryFailsClosed(): void {
    $binary = $this->raster();
    $path = $this->archive($binary);
    $resource = $this->resource('project-authored/maps/other.png');
    $this->expectExceptionMessage('raster_diagnostic_entry_unsupported');
    (new ModuleSceneRasterDiagnosticService())->diagnoseArchive($path, $resource);
  }

  public function testIslandWhitelistRejectsUnreviewedSceneStyles(): void {
    $resource = $this->resource('the-island-adventure/packs/the-island-adventure/assets/OtherDungeon.png');
    $this->expectExceptionMessage('raster_diagnostic_background_unsupported');
    (new ModuleSceneRasterDiagnosticService())->diagnoseArchive('not-read', $resource);
  }

}
