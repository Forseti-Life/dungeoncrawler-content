<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;
use Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleSceneMapStagingService;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(ModuleSceneMapStagingService::class)]
#[Group('dungeoncrawler_content')]
class ModuleSceneMapStagingServiceTest extends UnitTestCase {

  private const NOTICE = <<<'LICENSE'
MIT License

Copyright (c) 2024 mccaffertyt

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
LICENSE;

  private array $archives = [];

  protected function tearDown(): void {
    foreach ($this->archives as $path) {
      unlink($path);
    }
    parent::tearDown();
  }

  public function testStagesOriginalBytesAndCompleteLicense(): void {
    [$path, $inventory, $rights, $binary] = $this->fixture();
    $repository = $this->createMock(GeneratedImageRepository::class);
    $repository->expects($this->once())->method('persistSourceMap')
      ->with(
        $this->identicalTo($binary),
        'image/png',
        1600,
        800,
        $this->callback(static function (array $provenance) use ($binary): bool {
          return ($provenance['license_notice'] ?? NULL) === self::NOTICE
            && ($provenance['source_sha256'] ?? NULL) === hash('sha256', $binary)
            && ($provenance['archive_entry'] ?? NULL) === 'assets/maps/scene.png';
        }),
        3
      )
      ->willReturn(['stored' => TRUE, 'image_id' => 12, 'image_uuid' => 'fixture', 'file_uri' => 'public://generated-images/fixture.png', 'sha256' => hash('sha256', $binary)]);
    $result = (new ModuleSceneMapStagingService($repository))->stageSceneBackground($path, $inventory, 'scene-1', $rights, 3);
    $this->assertSame(1600, $result['width']);
    $this->assertSame(800, $result['height']);
    $this->assertSame('image/png', $result['mime_type']);
    $this->assertSame(self::NOTICE, $result['provenance']['license_notice']);
  }

  public function testStagesAttestedTileByPlacementIndex(): void {
    [$path, $inventory, $rights, $binary] = $this->fixture();
    $inventory['resources'][0]['source_context']['tile_image_refs'] = ['modules/project-authored/assets/maps/scene.png'];
    $inventory['resources'][0]['source_context']['tile_archive_entries'] = ['assets/maps/scene.png'];
    $repository = $this->createMock(GeneratedImageRepository::class);
    $repository->expects($this->once())->method('persistSourceMap')
      ->with($this->identicalTo($binary), 'image/png', 1600, 800, $this->callback(
        static fn(array $provenance): bool => $provenance['map_role'] === 'tile'
          && $provenance['tile_index'] === 0
          && $provenance['archive_entry'] === 'assets/maps/scene.png'
      ), 3)
      ->willReturn(['stored' => TRUE, 'image_id' => 13, 'file_uri' => 'public://generated-images/tile.png', 'sha256' => hash('sha256', $binary)]);
    $result = (new ModuleSceneMapStagingService($repository))->stageSceneTile($path, $inventory, 'scene-1', 0, $rights, 3);
    $this->assertSame('tile', $result['provenance']['map_role']);
    $this->assertSame(0, $result['provenance']['tile_index']);
  }

  public function testRejectsMissingTileWithoutPersistence(): void {
    [$path, $inventory, $rights] = $this->fixture();
    $repository = $this->createMock(GeneratedImageRepository::class);
    $repository->expects($this->never())->method('persistSourceMap');
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('source_map_scene_image_missing');
    (new ModuleSceneMapStagingService($repository))->stageSceneTile($path, $inventory, 'scene-1', 0, $rights, 3);
  }

  public function testSceneAssetBatchCleansStagedBackgroundOnTileFailure(): void {
    [$path, $inventory, $rights, $binary] = $this->fixture();
    $inventory['resources'][0]['source_context']['tile_image_refs'] = ['modules/project-authored/assets/maps/scene.png'];
    $inventory['resources'][0]['source_context']['tile_archive_entries'] = ['assets/maps/scene.png'];
    $inventory['resources'][0]['source_context']['tile_placements'] = [['x' => 20, 'y' => 30]];
    $background = [
      'stored' => TRUE, 'image_id' => 22, 'file_uri' => 'public://generated-images/map.png',
      'license_notice_uri' => 'public://generated-images/map.png.LICENSE.txt',
      'url' => '/sites/default/files/map.png', 'license_notice_url' => '/sites/default/files/map.png.LICENSE.txt',
      'sha256' => hash('sha256', $binary),
    ];
    $repository = $this->createMock(GeneratedImageRepository::class);
    $calls = 0;
    $repository->expects($this->exactly(2))->method('persistSourceMap')
      ->willReturnCallback(static function () use (&$calls, $background): array {
        if (++$calls === 2) {
          throw new \RuntimeException('tile write failed');
        }
        return $background;
      });
    $repository->expects($this->once())->method('discardSourceMap')->with(
      $this->callback(static fn(array $stored): bool => $stored['image_id'] === $background['image_id']
        && $stored['file_uri'] === $background['file_uri'])
    );
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('source_map_repository_failed');
    (new ModuleSceneMapStagingService($repository))->stageSceneAssets($path, $inventory, 'scene-1', [
      'background' => $rights, 'tiles' => [$rights],
    ], 3);
  }

  public function testSceneAssetBatchRetainsTilePlacementAndNotice(): void {
    [$path, $inventory, $rights, $binary] = $this->fixture();
    $context = &$inventory['resources'][0]['source_context'];
    $context['tile_image_refs'] = ['modules/project-authored/assets/maps/scene.png'];
    $context['tile_archive_entries'] = ['assets/maps/scene.png'];
    $context['tile_placements'] = [['x' => 20, 'y' => 30, 'width' => 80, 'height' => 60]];
    $repository = $this->createMock(GeneratedImageRepository::class);
    $index = 0;
    $repository->expects($this->exactly(2))->method('persistSourceMap')
      ->willReturnCallback(static function () use (&$index, $binary): array {
        $index++;
        return [
          'stored' => TRUE, 'image_id' => $index,
          'file_uri' => "public://generated-images/{$index}.png",
          'license_notice_uri' => "public://generated-images/{$index}.png.LICENSE.txt",
          'license_notice_url' => "/generated-images/{$index}.png.LICENSE.txt",
          'url' => "/generated-images/{$index}.png",
          'sha256' => hash('sha256', $binary),
        ];
      });
    $result = (new ModuleSceneMapStagingService($repository))->stageSceneAssets($path, $inventory, 'scene-1', [
      'background' => $rights, 'tiles' => [$rights],
    ], 3);
    $this->assertCount(2, $result['stored']);
    $this->assertSame('/generated-images/1.png', $result['maps']['background']['url']);
    $this->assertSame(['x' => 20, 'y' => 30, 'width' => 80, 'height' => 60], $result['maps']['tiles'][0]['placement']);
    $this->assertSame('/generated-images/2.png.LICENSE.txt', $result['maps']['tiles'][0]['license_notice_url']);
  }

  #[DataProvider('rejectionCases')]
  public function testRejectsBeforePersistence(string $case, string $expected): void {
    [$path, $inventory, $rights] = $this->fixture();
    switch ($case) {
      case 'no_rights':
        $rights = [];
        break;

      case 'partial_notice':
        $rights['license_notice'] = 'MIT License';
        break;

      case 'other_scene':
        $rights['scene_resource_id'] = 'scene-2';
        break;

      case 'archive_hash':
        $inventory['archive']['sha256'] = str_repeat('f', 64);
        break;

      case 'archive_bytes':
        $inventory['archive']['bytes']++;
        break;

      case 'unsafe_path':
        $inventory['resources'][0]['source_context']['background_archive_entry'] = '../assets/maps/scene.png';
        break;

      case 'token_path':
        $inventory['resources'][0]['source_context']['background_archive_entry'] = 'assets/tokens/scene.png';
        $inventory['resources'][0]['source_context']['background_src'] = 'modules/project-authored/assets/tokens/scene.png';
        break;
    }
    $repository = $this->createMock(GeneratedImageRepository::class);
    $repository->expects($this->never())->method('persistSourceMap');
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage($expected);
    (new ModuleSceneMapStagingService($repository))->stageSceneBackground($path, $inventory, 'scene-1', $rights, 3);
  }

  public static function rejectionCases(): array {
    return [
      'missing attestation' => ['no_rights', 'source_map_rights_attestation_missing'],
      'incomplete license' => ['partial_notice', 'source_map_rights_attestation_missing'],
      'rights for different scene' => ['other_scene', 'source_map_rights_attestation_missing'],
      'archive hash mismatch' => ['archive_hash', 'source_map_archive_mismatch'],
      'archive size mismatch' => ['archive_bytes', 'source_map_archive_mismatch'],
      'unsafe traversal' => ['unsafe_path', 'source_map_unsafe_entry'],
      'third-party token' => ['token_path', 'source_map_unsafe_entry'],
    ];
  }

  public function testRejectsOversizedAndUnsupportedImages(): void {
    foreach (['oversized', 'unsupported'] as $case) {
      [$path, $inventory, $rights] = $this->fixture($case === 'oversized' ? str_repeat('a', 33554433) : 'not an image');
      $repository = $this->createMock(GeneratedImageRepository::class);
      $repository->expects($this->never())->method('persistSourceMap');
      try {
        (new ModuleSceneMapStagingService($repository))->stageSceneBackground($path, $inventory, 'scene-1', $rights, 3);
        $this->fail('Invalid raster was accepted.');
      }
      catch (\InvalidArgumentException $exception) {
        $this->assertSame($case === 'oversized' ? 'source_map_entry_missing_or_oversized' : 'source_map_image_unsupported', $exception->getMessage());
      }
    }
  }

  public function testRepositoryFailurePropagates(): void {
    [$path, $inventory, $rights] = $this->fixture();
    $repository = $this->createMock(GeneratedImageRepository::class);
    $repository->method('persistSourceMap')->willThrowException(new \RuntimeException('write failed'));
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('source_map_repository_failed');
    (new ModuleSceneMapStagingService($repository))->stageSceneBackground($path, $inventory, 'scene-1', $rights, 3);
  }

  public function testIslandRequiresPinnedLicenseAndOriginalMapAttestation(): void {
    [$path, $inventory, $rights, $content] = $this->fixture();
    $inventory['manifest']['id'] = 'the-island-adventure';
    $inventory['manifest']['version'] = '1.1.0';
    $inventory['resources'][0]['source_context']['background_src'] = 'modules/the-island-adventure/assets/maps/scene.png';
    $rights['author'] = 'Tom McCafferty';
    $rights['source_url'] = 'https://github.com/mccaffertyt/the-island-adventure/tree/1.1.0';
    $rights['license_url'] = 'https://raw.githubusercontent.com/mccaffertyt/the-island-adventure/1.1.0/LICENSE';
    $repository = $this->createMock(GeneratedImageRepository::class);
    $repository->expects($this->once())->method('persistSourceMap')
      ->with($this->identicalTo($content), 'image/png', 1600, 800, $this->callback(
        static fn(array $provenance): bool => $provenance['license_notice'] === self::NOTICE
          && $provenance['author'] === 'Tom McCafferty'
          && $provenance['license_url'] === 'https://raw.githubusercontent.com/mccaffertyt/the-island-adventure/1.1.0/LICENSE'
      ), 3)
      ->willReturn(['stored' => TRUE, 'image_id' => 3, 'file_uri' => 'public://generated-images/map.png', 'sha256' => hash('sha256', $content)]);
    $result = (new ModuleSceneMapStagingService($repository))->stageSceneBackground($path, $inventory, 'scene-1', $rights, 3);
    $this->assertSame(self::NOTICE, $result['provenance']['license_notice']);
    $rights['license_notice'] = str_replace('sublicense', 'sub-license', self::NOTICE);
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('source_map_island_rights_invalid');
    (new ModuleSceneMapStagingService($repository))->stageSceneBackground($path, $inventory, 'scene-1', $rights, 3);
  }

  private function fixture(?string $content = NULL): array {
    if ($content === NULL) {
      $image = imagecreatetruecolor(1600, 800);
      ob_start();
      imagepng($image);
      $content = ob_get_clean();
      imagedestroy($image);
    }
    $path = dirname(__DIR__, 3) . '/fixtures/module_conversion/project-authored-map-' . bin2hex(random_bytes(8)) . '.zip';
    $zip = new \ZipArchive();
    $this->assertTrue($zip->open($path, \ZipArchive::CREATE | \ZipArchive::EXCL));
    $this->assertTrue($zip->addFromString('assets/maps/scene.png', $content));
    $this->assertTrue($zip->close());
    $this->archives[] = $path;
    $hash = hash_file('sha256', $path);
    $inventory = [
      'schema_version' => 'module-conversion-inventory-v1',
      'archive' => ['path' => realpath($path), 'sha256' => $hash, 'bytes' => filesize($path), 'module_root' => ''],
      'manifest' => ['id' => 'project-authored', 'version' => '1.0.0'],
      'resources' => [[
        'resource_id' => 'scene-1',
        'resource_type' => 'scene',
        'source_context' => [
          'background_src' => 'modules/project-authored/assets/maps/scene.png',
          'background_archive_entry' => 'assets/maps/scene.png',
        ],
      ]],
    ];
    $rights = [
      'attested' => TRUE,
      'asset_kind' => 'original_scene_map',
      'archive_sha256' => $hash,
      'entry' => 'assets/maps/scene.png',
      'scene_resource_id' => 'scene-1',
      'source_url' => 'https://example.org/project-authored/LICENSE',
      'license_url' => 'https://example.org/project-authored/LICENSE',
      'author' => 'Project author',
      'license_id' => 'MIT',
      'license_notice' => self::NOTICE,
    ];
    return [$path, $inventory, $rights, $content];
  }

}
