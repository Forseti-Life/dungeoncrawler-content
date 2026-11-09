<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\ModuleConversion;

/**
 * Read-only occupancy hints for reviewed black-matte scene PNGs.
 *
 * A raster pixel cannot attest a door, wall, terrain, or passable footprint.
 * Output intentionally has no trace-review cells or canonical room fields.
 */
final class ModuleSceneRasterDiagnosticService {

  private const VERSION = 'black_matte_occupancy_diagnostic_v1';

  public function diagnoseArchive(string $archivePath, array $resource): array {
    $context = $resource['source']['source_context'] ?? NULL;
    $entry = $context['background_archive_entry'] ?? NULL;
    $grid = $context['grid_size'] ?? NULL;
    $width = $context['width'] ?? NULL;
    $height = $context['height'] ?? NULL;
    if (($resource['resource_type'] ?? NULL) !== 'scene'
      || !is_string($resource['resource_id'] ?? NULL) || $resource['resource_id'] === ''
      || !is_array($context) || ($context['grid_type'] ?? NULL) !== 1
      || ($context['grid_distance'] ?? NULL) !== 5 || ($context['grid_units'] ?? NULL) !== 'ft'
      || !is_int($width) || !is_int($height) || !is_int($grid)
      || $width < 1 || $height < 1 || $grid < 1
      || $width % $grid !== 0 || $height % $grid !== 0
      || intdiv($width, $grid) > 100 || intdiv($height, $grid) > 100) {
      throw new \DomainException('raster_diagnostic_square_grid_unsupported');
    }
    if (!is_string($entry) || !preg_match('~^[a-zA-Z0-9_.-]+(?:/[a-zA-Z0-9_.-]+)*\.png$~D', $entry)
      || preg_match('~(?:road|overland|island|world|landscape)~i', basename($entry))
      || (str_starts_with($entry, 'the-island-adventure/')
        && !preg_match('~^the-island-adventure/packs/the-island-adventure/assets/GoblinLair[123]\.png$~D', $entry))
      || !is_string($context['background_src'] ?? NULL)
      || !str_ends_with($context['background_src'], '/' . basename($entry))) {
      throw new \DomainException('raster_diagnostic_background_unsupported');
    }
    $resolved = realpath($archivePath);
    if ($resolved === FALSE || !is_file($resolved) || filesize($resolved) > 536870912) {
      throw new \InvalidArgumentException('raster_diagnostic_archive_invalid');
    }
    $archiveHash = hash_file('sha256', $resolved);
    if (!is_string($archiveHash)) {
      throw new \RuntimeException('raster_diagnostic_archive_read_failed');
    }
    $zip = new \ZipArchive();
    if ($zip->open($resolved, \ZipArchive::RDONLY) !== TRUE) {
      throw new \InvalidArgumentException('raster_diagnostic_archive_invalid');
    }
    try {
      $index = $zip->locateName($entry);
      $stat = $index === FALSE ? FALSE : $zip->statIndex($index);
      if ($index !== FALSE) {
        for ($other = $index + 1; $other < $zip->numFiles; $other++) {
          if ($zip->getNameIndex($other) === $entry) {
            throw new \DomainException('raster_diagnostic_entry_ambiguous');
          }
        }
      }
      if (!is_array($stat) || ($stat['name'] ?? NULL) !== $entry
        || !is_int($stat['size'] ?? NULL) || $stat['size'] < 1 || $stat['size'] > 33554432
        || !is_int($stat['comp_size'] ?? NULL) || $stat['comp_size'] < 1
        || $stat['size'] > $stat['comp_size'] * 300
        || ($zip->getExternalAttributesIndex($index, $opsys, $attributes)
          && $opsys === \ZipArchive::OPSYS_UNIX && (($attributes >> 16) & 0170000) === 0120000)) {
        throw new \DomainException('raster_diagnostic_entry_unsupported');
      }
      $binary = $zip->getFromIndex($index);
      if (!is_string($binary) || strlen($binary) !== $stat['size']) {
        throw new \RuntimeException('raster_diagnostic_image_read_failed');
      }
    }
    finally {
      $zip->close();
    }
    if (!hash_equals($archiveHash, hash_file('sha256', $resolved) ?: '')) {
      throw new \RuntimeException('raster_diagnostic_archive_changed');
    }
    $size = getimagesizefromstring($binary);
    if (!is_array($size) || ($size['mime'] ?? NULL) !== 'image/png'
      || $size[0] > 8192 || $size[1] > 8192 || $size[0] * $size[1] > 33554432
      || $size[0] % intdiv($width, $grid) !== 0
      || $size[1] % intdiv($height, $grid) !== 0) {
      throw new \DomainException('raster_diagnostic_image_alignment_unsupported');
    }
    $columns = intdiv($width, $grid);
    $rows = intdiv($height, $grid);
    $pixelsPerCellX = intdiv($size[0], $columns);
    $pixelsPerCellY = intdiv($size[1], $rows);
    if ($pixelsPerCellX < 4 || $pixelsPerCellY < 4) {
      throw new \DomainException('raster_diagnostic_image_alignment_unsupported');
    }
    $image = @imagecreatefromstring($binary);
    if (!$image instanceof \GdImage) {
      throw new \DomainException('raster_diagnostic_png_invalid');
    }
    try {
      $pixels = $size[0] * $size[1];
      $dark = 0;
      $border = 0;
      $borderDark = 0;
      $cells = [];
      $counts = ['surface_candidate' => 0, 'void_candidate' => 0, 'ambiguous' => 0];
      for ($y = 0; $y < $rows; $y++) {
        for ($x = 0; $x < $columns; $x++) {
          $cellDark = 0;
          $transparent = 0;
          for ($py = $y * $pixelsPerCellY; $py < ($y + 1) * $pixelsPerCellY; $py++) {
            for ($px = $x * $pixelsPerCellX; $px < ($x + 1) * $pixelsPerCellX; $px++) {
              $rgba = imagecolorsforindex($image, imagecolorat($image, $px, $py));
              $opaque = $rgba['alpha'] <= 32;
              $isDark = $opaque && max($rgba['red'], $rgba['green'], $rgba['blue']) <= 48;
              $cellDark += (int) $isDark;
              $dark += (int) $isDark;
              $transparent += (int) !$opaque;
              if ($px < $pixelsPerCellX || $px >= $size[0] - $pixelsPerCellX
                || $py < $pixelsPerCellY || $py >= $size[1] - $pixelsPerCellY) {
                $border++;
                $borderDark += (int) $isDark;
              }
            }
          }
          $area = $pixelsPerCellX * $pixelsPerCellY;
          $fraction = $cellDark / $area;
          $flags = [];
          if ($transparent > 0) {
            $flags[] = 'transparency_present';
          }
          if ($fraction > 0.15 && $fraction < 0.85) {
            $flags[] = 'mixed_dark_and_nondark_pixels';
          }
          $candidate = $flags !== [] ? 'ambiguous'
            : ($fraction >= 0.85 ? 'void_candidate' : 'surface_candidate');
          $counts[$candidate]++;
          $cells[] = [
            'x' => $x,
            'y' => $y,
            'review_status' => 'UNREVIEWED',
            'candidate' => $candidate,
            'dark_fraction' => round($fraction, 4),
            'ambiguity_flags' => $flags,
          ];
        }
      }
      if ($dark / $pixels < 0.25 || $borderDark / $border < 0.85) {
        throw new \DomainException('raster_diagnostic_black_matte_absent');
      }
    }
    finally {
      imagedestroy($image);
    }
    return [
      'schema_version' => self::VERSION,
      'review_status' => 'UNREVIEWED',
      'approvable_as_trace' => FALSE,
      'scene_resource_id' => $resource['resource_id'],
      'archive_sha256' => $archiveHash,
      'image_sha256' => hash('sha256', $binary),
      'archive_entry' => $entry,
      'source_grid' => ['columns' => $columns, 'rows' => $rows],
      'image_pixels' => ['width' => $size[0], 'height' => $size[1]],
      'counts' => $counts + ['total' => count($cells)],
      'ambiguity_flags' => [
        'raster_background_alignment_not_verified',
        'thresholds_do_not_establish_terrain_or_passability',
        'doors_and_roads_not_inferred',
      ],
      'cells' => $cells,
    ];
  }

}
