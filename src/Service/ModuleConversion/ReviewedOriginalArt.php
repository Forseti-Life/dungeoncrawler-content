<?php

namespace Drupal\dungeoncrawler_content\Service\ModuleConversion;

final class ReviewedOriginalArt {

  public static function fromScene(array $scene): ?array {
    if (!array_key_exists('original_map_review', $scene)) {
      return NULL;
    }
    $review = $scene['original_map_review'];
    $maps = $scene['map_assets'] ?? NULL;
    $background = is_array($maps) ? ($maps['background'] ?? NULL) : NULL;
    $url = is_array($background) ? ($background['url'] ?? NULL) : NULL;
    if (isset($scene['map_review']) || isset($scene['map_alignment'])
      || !is_array($review) || !is_array($background)
      || ($review['rights_status'] ?? NULL) !== 'project_original'
      || !is_int($review['image_id'] ?? NULL) || $review['image_id'] <= 0
      || !is_int($review['reviewed_by'] ?? NULL) || $review['reviewed_by'] <= 0
      || !is_string($review['sha256'] ?? NULL) || !preg_match('/^[a-f0-9]{64}$/D', $review['sha256'])
      || ($background['image_id'] ?? NULL) !== $review['image_id']
      || ($background['sha256'] ?? NULL) !== $review['sha256']
      || ($background['asset_kind'] ?? NULL) !== 'generated_original_scene_map'
      || !is_int($background['width'] ?? NULL) || $background['width'] <= 0
      || !is_int($background['height'] ?? NULL) || $background['height'] <= 0
      || !is_string($url) || !str_starts_with($url, '/sites/default/files/generated-images/')
      || !is_array($maps['tiles'] ?? NULL) || $maps['tiles'] !== []) {
      throw new \DomainException('reviewed_original_art_invalid');
    }
    return [
      'url' => $url, 'image_id' => $review['image_id'], 'sha256' => $review['sha256'],
      'width' => $background['width'], 'height' => $background['height'],
    ];
  }

}
