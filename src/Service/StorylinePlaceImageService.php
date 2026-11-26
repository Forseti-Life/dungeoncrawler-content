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
    private readonly ?InstitutionLibraryService $institutions = NULL,
  ) {}

  public function previewUrl(string $kind, string $id): string {
    return (string) ($this->versions($kind, $id)[0]['url'] ?? '');
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  public function versions(string $kind, string $id): array {
    $versions = [];
    foreach ($this->imageTargets($kind, $id) as [$table, $slot, $variant, $object_id]) {
      foreach ($this->repository->loadImagesForObject($table, $object_id, NULL, $slot, $variant) as $row) {
        $url = trim((string) ($this->repository->resolveClientUrl($row) ?? ''));
        if ($url === '') {
          continue;
        }
        $versions[] = [
          'image_id' => (int) ($row['image_id'] ?? 0),
          'image_uuid' => (string) ($row['image_uuid'] ?? ''),
          'version_number' => (int) ($row['version_number'] ?? 1),
          'is_primary' => !empty($row['is_primary']),
          'url' => $url,
          'provider' => (string) ($row['provider'] ?? ''),
          'provider_model' => (string) ($row['provider_model'] ?? ''),
          'created' => (int) ($row['created'] ?? $row['image_created'] ?? 0),
          'table_name' => $table,
          'slot' => $slot,
          'variant' => $variant,
        ];
      }
    }
    usort($versions, static fn(array $a, array $b): int =>
      [(int) !$a['is_primary'], -$a['version_number'], -$a['created'], -$a['image_id']]
      <=> [(int) !$b['is_primary'], -$b['version_number'], -$b['created'], -$b['image_id']]);
    return array_values($versions);
  }

  public function generate(string $kind, string $id, array $payload, int $owner_uid): array {
    $kind = in_array($kind, ['room', 'dungeon', 'institution'], TRUE) ? $kind : 'room';
    [$row, $prompt] = match ($kind) {
      'dungeon' => $this->dungeonPrompt($id, $payload),
      'institution' => $this->institutionPrompt($id, $payload),
      default => $this->roomPrompt($id, $payload),
    };
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
    $targets = $this->imageTargets($kind, $id);
    if ($targets === []) {
      throw new \OutOfBoundsException('place_image_canonical_target_missing');
    }
    [$table, $slot, $variant, $object_id] = $targets[0];
    $stored = $this->repository->persistGeneratedImage($result, [
      'owner_uid' => $owner_uid,
      'scope_type' => 'campaign',
      'campaign_id' => NULL,
      'table_name' => $table,
      'object_id' => $object_id,
      'slot' => $slot,
      'variant' => $variant,
      'visibility' => 'owner',
      'is_primary' => 1,
    ]);
    if (!empty($stored['stored'])) {
      $stored['versions'] = $this->versions($kind, $id);
    }
    return $stored;
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

  private function institutionPrompt(string $id, array $payload): array {
    $row = [];
    foreach ((array) ($payload['objects']['institutions']['entries'] ?? $payload['objects']['institutions'] ?? []) as $candidate) {
      if (is_array($candidate) && (string) ($candidate['institution_id'] ?? '') === $id) {
        $row = $candidate;
        break;
      }
    }
    if ($row === []) {
      return [[], ''];
    }
    return [$row, sprintf(
      "Create an original emblematic establishing image for the fantasy institution '%s'.\nKind: %s\nDescription: %s\nPurpose: %s\nShow recognizable architecture, symbols, members, and atmosphere. No readable text, logos, UI, labels, or third-party insignia.",
      (string) ($row['name'] ?? $id),
      (string) ($row['domain'] ?? 'institution'),
      (string) ($row['description'] ?? 'An established organization in the setting.'),
      (string) ($row['purpose'] ?? ''),
    )];
  }

  private function imageTargets(string $kind, string $id): array {
    if ($kind === 'dungeon') {
      return [['dungeoncrawler_content_dungeons', 'preview', 'original', $id]];
    }
    if ($kind === 'institution') {
      $row = $this->institutions?->loadRow($id);
      if (!is_array($row) || (int) ($row['id'] ?? 0) < 1) {
        return [];
      }
      return [[
        InstitutionLibraryService::TABLE,
        'emblem',
        'original',
        (string) $row['id'],
      ]];
    }
    return [
        ['dungeoncrawler_content_rooms', 'room_view', 'establishing', $id],
        ['dungeoncrawler_content_room_templates', 'background', 'original', $id],
    ];
  }

}
