<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Reports missing generated assets without blocking validation.
 */
final class ObjectImageValidationService {

  public function __construct(
    private readonly GeneratedImageRepository $repository,
  ) {}

  /**
   * @return array<int, array<string, mixed>>
   */
  public function warnings(string $type, string $object_id, array $payload = []): array {
    $object_id = trim($object_id);
    if ($object_id === '') {
      return [];
    }
    [$table_name, $linked_object_id, $slot, $variant] = ObjectImageService::deterministicTarget(
      $type,
      $object_id,
      $payload,
    );
    foreach ($this->repository->loadImagesForObject($table_name, $linked_object_id, NULL, $slot, $variant) as $row) {
      if (trim((string) ($this->repository->resolveClientUrl($row) ?? '')) !== '') {
        return [];
      }
    }
    return [[
      'severity' => 'warning',
      'code' => 'generated_image_missing',
      'message' => sprintf(
        'No generated image is linked to this %s. Publishing is allowed, but visual coverage is incomplete.',
        $type,
      ),
      'route' => 'image_editor',
      'path' => '/image',
      'context' => [
        'object_type' => $type,
        'object_id' => $object_id,
      ],
    ]];
  }

}
