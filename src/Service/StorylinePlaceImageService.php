<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Generates and resolves canonical preview images for storyline places.
 */
final class StorylinePlaceImageService {

  public function __construct(
    private readonly ImageGenerationIntegrationService $images,
    private readonly GeneratedImageRepository $repository,
  ) {}

  public function previewUrl(string $kind, string $id): string {
    foreach ($this->imageTargets($kind, $id) as [$table, $slot, $variant]) {
      $rows = $this->repository->loadImagesForObject($table, $id, NULL, $slot, $variant);
      if ($rows !== []) {
        return trim((string) ($this->repository->resolveClientUrl($rows[0]) ?? ''));
      }
    }
    return '';
  }

  public function generate(string $kind, string $id, array $payload, int $owner_uid): array {
    $kind = $kind === 'dungeon' ? 'dungeon' : 'room';
    [$row, $prompt] = $kind === 'dungeon'
      ? $this->dungeonPrompt($id, $payload)
      : $this->roomPrompt($id, $payload);
    if ($row === []) {
      throw new \OutOfBoundsException('storyline_place_not_found');
    }
    $result = $this->images->generateImage([
      'prompt' => $prompt,
      'style' => 'fantasy illustration',
      'aspect_ratio' => '16:9',
      'campaign_context' => 'storyline_place_preview',
      'requested_by_uid' => $owner_uid,
    ]);
    $output = is_array($result['output'] ?? NULL) ? $result['output'] : [];
    if (empty($result['success'])
      || (trim((string) ($output['image_url'] ?? '')) === ''
        && trim((string) ($output['image_data_uri'] ?? '')) === '')) {
      return [
        'stored' => FALSE,
        'reason' => (string) ($result['status'] ?? 'generation_failed'),
        'message' => (string) ($result['message'] ?? 'Image provider returned no image.'),
      ];
    }
    [$table, $slot, $variant] = $this->imageTargets($kind, $id)[0];
    return $this->repository->persistGeneratedImage($result, [
      'owner_uid' => $owner_uid,
      'scope_type' => 'campaign',
      'campaign_id' => NULL,
      'table_name' => $table,
      'object_id' => $id,
      'slot' => $slot,
      'variant' => $variant,
      'visibility' => 'owner',
      'is_primary' => 1,
    ]);
  }

  private function roomPrompt(string $id, array $payload): array {
    $row = [];
    foreach ((array) ($payload['objects']['rooms']['generation_specs'] ?? []) as $candidate) {
      if (is_array($candidate) && (string) ($candidate['room_id'] ?? '') === $id) {
        $row = $candidate;
        break;
      }
    }
    if ($row === []) {
      return [[], ''];
    }
    return [$row, sprintf(
      "Create a player-facing establishing image for the fantasy RPG room '%s'.\\nPurpose: %s\\nChapter: %s\\nScene: %s\\nShow clear architecture, atmosphere, entrances, and room-defining features. No text, UI, labels, grid, or characters unless essential to the room.",
      $id,
      (string) ($row['narrative'] ?? 'An authored room in the storyline.'),
      (string) ($row['chapter_id'] ?? ''),
      (string) ($row['scene_id'] ?? ''),
    )];
  }

  private function dungeonPrompt(string $id, array $payload): array {
    $row = [];
    foreach ((array) ($payload['objects']['dungeons']['generation_specs'] ?? []) as $candidate) {
      if (is_array($candidate) && (string) ($candidate['dungeon_id'] ?? '') === $id) {
        $row = $candidate;
        break;
      }
    }
    if ($row === []) {
      return [[], ''];
    }
    $rooms = [];
    foreach ((array) ($payload['objects']['rooms']['generation_specs'] ?? []) as $room) {
      if (is_array($room) && (string) ($room['dungeon_id'] ?? '') === $id) {
        $rooms[] = (string) ($room['room_id'] ?? '');
      }
    }
    return [$row, sprintf(
      "Create a cinematic establishing overview for the fantasy RPG dungeon or site '%s'.\\nPurpose: %s\\nChapter: %s\\nFeatured rooms: %s\\nShow the site's identity, entry, scale, connected spaces, and atmosphere. No text, UI, labels, map grid, or readable signs.",
      $id,
      (string) ($row['purpose'] ?? 'An authored adventure site.'),
      (string) ($row['chapter_id'] ?? ''),
      implode(', ', array_filter($rooms)),
    )];
  }

  private function imageTargets(string $kind, string $id): array {
    return $kind === 'dungeon'
      ? [['dungeoncrawler_content_dungeons', 'preview', 'original']]
      : [
        ['dungeoncrawler_content_rooms', 'room_view', 'establishing'],
        ['dungeoncrawler_content_room_templates', 'background', 'original'],
      ];
  }

}
