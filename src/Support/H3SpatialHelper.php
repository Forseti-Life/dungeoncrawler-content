<?php

namespace Drupal\dungeoncrawler_content\Support;

/**
 * Shared H3 + spatial projection helpers for runtime and update hooks.
 */
final class H3SpatialHelper {

  /**
   * Active res14 hex size in meters for axial projection.
   */
  public const H3_HEX_SIZE_METERS = 2.2;

  /**
   * Earth meters-per-degree latitude approximation.
   */
  public const METERS_PER_DEGREE_LATITUDE = 111320.0;

  /**
   * Shared libh3 FFI handle.
   *
   * @var \FFI|null
   */
  private static ?\FFI $h3Ffi = NULL;

  /**
   * Get shared libh3 FFI handle.
   */
  public static function getH3Ffi(): \FFI {
    if (self::$h3Ffi instanceof \FFI) {
      return self::$h3Ffi;
    }
    if (!extension_loaded('ffi')) {
      throw new \RuntimeException('True H3 index generation requires PHP FFI extension (ext-ffi).');
    }

    try {
      self::$h3Ffi = \FFI::cdef(
        'typedef unsigned long long H3Index;
         typedef int H3Error;
         typedef struct { double lat; double lng; } LatLng;
         H3Error latLngToCell(const LatLng* g, int res, H3Index* out);',
        'libh3.so.1'
      );
    }
    catch (\Throwable $e) {
      throw new \RuntimeException('True H3 index generation requires libh3.so.1 to be installed and loadable.', 0, $e);
    }

    return self::$h3Ffi;
  }

  /**
   * Convert WGS84 degrees lat/lng into canonical H3 index string.
   */
  public static function latLngToH3Index(float $latitude, float $longitude, int $resolution): string {
    if ($resolution < 0 || $resolution > 15) {
      throw new \RuntimeException(sprintf('H3 resolution %d is out of range (expected 0-15).', $resolution));
    }

    $ffi = self::getH3Ffi();
    $coord = $ffi->new('LatLng');
    $coord->lat = deg2rad($latitude);
    $coord->lng = deg2rad($longitude);
    $out = $ffi->new('H3Index[1]');
    $error = (int) $ffi->latLngToCell(\FFI::addr($coord), $resolution, $out);
    if ($error !== 0) {
      throw new \RuntimeException(sprintf(
        'libh3 latLngToCell failed with error code %d for lat=%0.8f lng=%0.8f res=%d.',
        $error,
        $latitude,
        $longitude,
        $resolution
      ));
    }

    $raw = \FFI::string(\FFI::cast('char *', \FFI::addr($out[0])), 8);
    $hex = ltrim(bin2hex(strrev($raw)), '0');
    if ($hex === '') {
      throw new \RuntimeException(sprintf(
        'libh3 returned empty H3 index for lat=%0.8f lng=%0.8f res=%d.',
        $latitude,
        $longitude,
        $resolution
      ));
    }

    return strtolower($hex);
  }

  /**
   * Deterministically project one axial coordinate into WGS84 lat/lng.
   *
   * @return array{latitude: float, longitude: float}
   *   Projected coordinates.
   */
  public static function projectAxialHexToLatLng(string $dungeon_id, int $q, int $r): array {
    $hash = sprintf('%u', crc32($dungeon_id));
    $origin_lat = ((int) $hash % 1000) / 1000000.0;
    $origin_lng = ((int) floor(((int) $hash / 1000) % 1000)) / 1000000.0;

    $x_meters = 1.5 * self::H3_HEX_SIZE_METERS * $q;
    $y_meters = sqrt(3.0) * self::H3_HEX_SIZE_METERS * ($r + ($q / 2.0));

    $latitude = $origin_lat + ($y_meters / self::METERS_PER_DEGREE_LATITUDE);
    $cos_lat = cos(deg2rad(max(min($origin_lat, 89.9999), -89.9999)));
    $meters_per_degree_lng = self::METERS_PER_DEGREE_LATITUDE * ($cos_lat === 0.0 ? 0.000001 : $cos_lat);
    $longitude = $origin_lng + ($x_meters / $meters_per_degree_lng);

    return [
      'latitude' => round($latitude, 8),
      'longitude' => round($longitude, 8),
    ];
  }

}
