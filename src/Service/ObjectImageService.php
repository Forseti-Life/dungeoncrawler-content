<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service;

/**
 * One image-generation contract for canonical object families.
 */
final class ObjectImageService {

  private const TARGET_TYPES = ['room', 'dungeon', 'actor', 'item', 'obstacle', 'trap', 'hazard', 'terrain', 'institution', 'storyline'];

  public function __construct(
    private readonly ImageGenerationIntegrationService $images,
    private readonly GeneratedImageRepository $repository,
    private readonly CanonicalDefinitionService $definitions,
    private readonly ?InstitutionLibraryService $institutions = NULL,
    private readonly ?StorylineAuthoringDraftService $storylines = NULL,
    private readonly ?TerrainImageGenerationService $terrainImages = NULL,
  ) {}

  /**
   * @return array<int, array<string, mixed>>
   */
  public function versions(string $type, string $object_id): array {
    [$table_name, $linked_object_id, $slot, $variant] = $this->target($type, $object_id);
    $versions = [];
    foreach ($this->repository->loadImagesForObject($table_name, $linked_object_id, NULL, $slot, $variant) as $row) {
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
        'created' => (int) ($row['image_created'] ?? $row['created'] ?? 0),
        'table_name' => $table_name,
        'object_id' => $linked_object_id,
        'slot' => $slot,
        'variant' => $variant,
      ];
    }
    return $versions;
  }

  public function generate(string $type, string $object_id, string $prompt, int $owner_uid): array {
    [$table_name, $linked_object_id, $slot, $variant] = $this->target($type, $object_id);
    if ($type === 'terrain') {
      if ($this->terrainImages === NULL) {
        throw new \LogicException('terrain_image_service_unavailable');
      }
      $result = $this->terrainImages->generateTerrainImage([
        'entity_type' => 'floortile',
        'terrain_type' => $object_id,
        'tile_id' => $object_id,
        'notes' => $prompt,
        'requested_by_uid' => $owner_uid,
        'tileable' => TRUE,
        'link_context' => [
          'owner_uid' => $owner_uid,
          'scope_type' => 'global',
          'campaign_id' => NULL,
          'table_name' => $table_name,
          'object_id' => $linked_object_id,
          'slot' => $slot,
          'variant' => $variant,
          'visibility' => 'owner',
          'is_primary' => 1,
        ],
      ], ['force_regenerate' => TRUE]);
      $stored = is_array($result['storage'] ?? NULL) ? $result['storage'] : [];
      if (!empty($stored['stored'])) {
        $stored['versions'] = $this->versions($type, $object_id);
      }
      return $stored + [
        'reason' => (string) ($result['reason'] ?? 'terrain_generation_failed'),
        'message' => (string) ($result['result']['message'] ?? ''),
      ];
    }
    $result = $this->images->generateImage([
      'prompt' => $prompt !== '' ? $prompt : $this->defaultPrompt($type, $object_id),
      'style' => $type === 'actor' ? 'portrait fantasy illustration' : ($type === 'storyline' ? 'cinematic fantasy cover illustration' : 'top-down fantasy game art'),
      'aspect_ratio' => $type === 'actor' ? '3:4' : ($type === 'storyline' ? '16:9' : '1:1'),
      'negative_prompt' => 'text, captions, watermark, logo, user interface, labels, collage',
      'campaign_context' => 'canonical_object_image',
      'requested_by_uid' => $owner_uid,
    ]);
    if (empty($result['success'])) {
      return [
        'stored' => FALSE,
        'reason' => (string) ($result['status'] ?? 'generation_failed'),
        'message' => (string) ($result['message'] ?? 'Image provider returned no image.'),
      ];
    }
    $stored = $this->repository->persistGeneratedImage($result, [
      'owner_uid' => $owner_uid,
      'scope_type' => 'global',
      'campaign_id' => NULL,
      'table_name' => $table_name,
      'object_id' => $linked_object_id,
      'slot' => $slot,
      'variant' => $variant,
      'visibility' => 'owner',
      'is_primary' => 1,
    ]);
    if (!empty($stored['stored'])) {
      $stored['versions'] = $this->versions($type, $object_id);
    }
    return $stored;
  }

  public function promote(string $type, string $object_id, string $image_uuid): array {
    [$table_name, $linked_object_id, $slot, $variant] = $this->target($type, $object_id);
    $stored = $this->repository->promoteImageVersion(
      $table_name,
      $linked_object_id,
      NULL,
      $slot,
      $variant,
      $image_uuid,
      'global',
      'owner',
    );
    $stored['versions'] = $this->versions($type, $object_id);
    return $stored;
  }

  public function defaultPrompt(string $type, string $object_id): string {
    if (in_array($type, ['actor', 'item', 'obstacle', 'trap', 'hazard'], TRUE)) {
      $payload = $this->definitions->definitionPayload($type, $object_id);
      $label = (string) ($payload['name'] ?? $payload['label'] ?? $payload['display_name'] ?? $object_id);
      $description = (string) ($payload['description'] ?? $payload['summary'] ?? $payload['appearance'] ?? '');
      return $type === 'actor'
        ? "Create an original fantasy portrait for {$label}. {$description} No text or third-party likeness."
        : "Create an original top-down hexmap token for {$label}, a canonical {$type}. {$description} Keep the silhouette readable at small scale, no text.";
    }

    if ($type === 'institution') {
      $row = $this->institutions?->loadRow($object_id);
      $payload = is_array($row) ? json_decode((string) ($row['normalized_payload_json'] ?? '{}'), TRUE) : [];
      return sprintf(
        'Create an original emblematic establishing image for %s, %s. No readable text or third-party insignia.',
        (string) ($payload['canonicalLabel'] ?? $object_id),
        (string) ($payload['summary'] ?? 'a fantasy institution'),
      );
    }
    if ($type === 'storyline') {
      $draft = $this->storylines?->getDraftForViewing($object_id);
      if (!is_array($draft)) {
        throw new \OutOfBoundsException('storyline_image_target_missing');
      }
      return sprintf(
        'Create an original fantasy cover image for the storyline "%s". %s Premise: %s. No readable text, logos, UI, or third-party characters.',
        (string) ($draft['payload']['title'] ?? $draft['payload']['storyline_id'] ?? $object_id),
        (string) ($draft['payload']['description'] ?? ''),
        (string) ($draft['payload']['premise'] ?? ''),
      );
    }
    return sprintf(
      'Create an original seamless top-down fantasy tile for canonical terrain type %s. No text, grid, characters, or UI.',
      $object_id,
    );
  }

  /**
   * @return array{string,string,string,string}
   */
  private function target(string $type, string $object_id): array {
    $type = strtolower(trim($type));
    $object_id = trim($object_id);
    if (!in_array($type, self::TARGET_TYPES, TRUE) || $object_id === '') {
      throw new \InvalidArgumentException('object_image_target_invalid');
    }
    if ($type === 'room') {
      return ['dungeoncrawler_content_rooms', $object_id, 'room_view', 'establishing'];
    }
    if ($type === 'dungeon') {
      return ['dungeoncrawler_content_dungeons', $object_id, 'preview', 'original'];
    }
    if ($type === 'actor') {
      $this->definitions->definitionPayload('actor', $object_id);
      return ['dc_canonical_actors', $object_id, 'portrait', 'original'];
    }
    if (in_array($type, ['item', 'obstacle', 'trap', 'hazard'], TRUE)) {
      $payload = $this->definitions->definitionPayload($type, $object_id);
      $existing = trim((string) ($payload['visual']['sprite_id'] ?? ''));
      $sprite_id = $existing !== '' && !str_starts_with($existing, 'generic_')
        ? $existing
        : CanonicalDefinitionService::defaultSpriteId($type, $object_id);
      return ['dc_dungeon_sprites', $sprite_id, 'sprite', 'original'];
    }
    if ($type === 'institution') {
      $row = $this->institutions?->loadRow($object_id);
      if (!is_array($row) || (int) ($row['id'] ?? 0) < 1) {
        throw new \OutOfBoundsException('institution_image_target_missing');
      }
      return [InstitutionLibraryService::TABLE, (string) $row['id'], 'emblem', 'original'];
    }
    if ($type === 'storyline') {
      $this->storylines?->getDraftForViewing($object_id)
        ?? throw new \OutOfBoundsException('storyline_image_target_missing');
      return ['dungeoncrawler_content_storyline_authoring_drafts', $object_id, 'cover', 'original'];
    }
    return ['dungeoncrawler_content_terrain_images', $object_id, 'tile', 'original'];
  }

}
