<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Optimizes generated image binaries for web delivery.
 */
class GeneratedImageOptimizationService {

  /**
   * Maximum output dimension in pixels (longest edge).
   */
  private const MAX_DIMENSION = 1024;

  /**
   * WebP quality for generated output.
   */
  private const WEBP_QUALITY = 82;

  /**
   * Optimizes a data URI image for storage.
   *
   * @return array<string, mixed>
   *   Optimization result.
   */
  public function optimizeDataUri(string $image_data_uri): array {
    if (!extension_loaded('gd')) {
      return ['ok' => FALSE, 'reason' => 'gd_extension_missing'];
    }
    if (!function_exists('imagecreatefromstring')) {
      return ['ok' => FALSE, 'reason' => 'gd_imagecreatefromstring_missing'];
    }
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagecopyresampled')) {
      return ['ok' => FALSE, 'reason' => 'gd_resize_support_missing'];
    }
    if (!function_exists('imagewebp')) {
      return ['ok' => FALSE, 'reason' => 'gd_webp_support_missing'];
    }

    $matches = [];
    if (!preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.+)$/s', $image_data_uri, $matches)) {
      return ['ok' => FALSE, 'reason' => 'invalid_data_uri'];
    }

    $binary = base64_decode($matches[2], TRUE);
    if (!is_string($binary) || $binary === '') {
      return ['ok' => FALSE, 'reason' => 'invalid_base64'];
    }

    $source = imagecreatefromstring($binary);
    if ($source === FALSE) {
      return ['ok' => FALSE, 'reason' => 'unsupported_image_binary'];
    }

    $source_width = imagesx($source);
    $source_height = imagesy($source);
    if ($source_width <= 0 || $source_height <= 0) {
      imagedestroy($source);
      return ['ok' => FALSE, 'reason' => 'invalid_dimensions'];
    }

    $longest_edge = max($source_width, $source_height);
    $scale = $longest_edge > self::MAX_DIMENSION ? (self::MAX_DIMENSION / $longest_edge) : 1.0;
    $target_width = max(1, (int) round($source_width * $scale));
    $target_height = max(1, (int) round($source_height * $scale));

    $target = imagecreatetruecolor($target_width, $target_height);
    if ($target === FALSE) {
      imagedestroy($source);
      return ['ok' => FALSE, 'reason' => 'target_canvas_failed'];
    }

    imagealphablending($target, FALSE);
    imagesavealpha($target, TRUE);
    $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
    imagefilledrectangle($target, 0, 0, $target_width, $target_height, $transparent);

    $copied = imagecopyresampled(
      $target,
      $source,
      0,
      0,
      0,
      0,
      $target_width,
      $target_height,
      $source_width,
      $source_height
    );

    imagedestroy($source);

    if ($copied === FALSE) {
      imagedestroy($target);
      return ['ok' => FALSE, 'reason' => 'resample_failed'];
    }

    ob_start();
    $encoded = imagewebp($target, NULL, self::WEBP_QUALITY);
    $optimized_binary = ob_get_clean();
    imagedestroy($target);

    if ($encoded !== TRUE || !is_string($optimized_binary) || $optimized_binary === '') {
      return ['ok' => FALSE, 'reason' => 'webp_encode_failed'];
    }

    $dimensions = getimagesizefromstring($optimized_binary);
    if (!is_array($dimensions) || !isset($dimensions[0], $dimensions[1])) {
      return ['ok' => FALSE, 'reason' => 'optimized_dimensions_missing'];
    }

    return [
      'ok' => TRUE,
      'binary' => $optimized_binary,
      'mime_type' => 'image/webp',
      'width' => (int) $dimensions[0],
      'height' => (int) $dimensions[1],
      'bytes' => strlen($optimized_binary),
      'sha256' => hash('sha256', $optimized_binary),
      'source_mime_type' => $matches[1],
      'source_width' => $source_width,
      'source_height' => $source_height,
      'resized' => ($target_width !== $source_width || $target_height !== $source_height),
    ];
  }

}
