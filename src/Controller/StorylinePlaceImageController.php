<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService;
use Drupal\dungeoncrawler_content\Service\StorylinePlaceImageService;
use Drupal\dungeoncrawler_content\Service\RoomEditorService;
use Drupal\dungeoncrawler_content\Service\DungeonEditorService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class StorylinePlaceImageController extends ControllerBase {

  public function __construct(
    private readonly StorylineAuthoringDraftService $drafts,
    private readonly StorylinePlaceImageService $places,
    private readonly AccountProxyInterface $account,
    private readonly RoomEditorService $rooms,
    private readonly DungeonEditorService $dungeons,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('dungeoncrawler_content.storyline_authoring_drafts'),
      $container->get('dungeoncrawler_content.storyline_place_image'),
      $container->get('current_user'),
      $container->get('dungeoncrawler_content.room_editor'),
      $container->get('dungeoncrawler_content.dungeon_editor'),
    );
  }

  public function generate(string $draft_id, string $kind, string $object_id): JsonResponse {
    if (!in_array($kind, ['room', 'dungeon'], TRUE)) {
      throw new AccessDeniedHttpException('storyline_place_kind_invalid');
    }
    $draft = $this->drafts->getDraftForViewing($draft_id);
    $result = $this->places->generate(
      $kind,
      $object_id,
      (array) ($draft['payload'] ?? []),
      (int) $this->account->id(),
    );
    if (empty($result['stored'])) {
      return new JsonResponse([
        'error' => [
          'code' => (string) ($result['reason'] ?? 'place_image_failed'),
          'message' => trim((string) ($result['message'] ?? 'Place image was not stored.')),
        ],
      ], 503);
    }
    return new JsonResponse([
      'data' => [
        'kind' => $kind,
        'object_id' => $object_id,
        'preview_url' => (string) ($result['url'] ?? ''),
      ],
    ]);
  }

  public function editorImage(string $kind, string $draft_id, \Symfony\Component\HttpFoundation\Request $request): JsonResponse {
    if (!in_array($kind, ['room', 'dungeon'], TRUE)) {
      throw new AccessDeniedHttpException('place_image_kind_invalid');
    }
    $editor_draft = $kind === 'room'
      ? $this->rooms->getDraft($draft_id)
      : $this->dungeons->getDraft($draft_id);
    $object_id = (string) ($kind === 'room'
      ? ($editor_draft['room']['room_id'] ?? $editor_draft['room_id'] ?? '')
      : ($editor_draft['dungeon']['dungeon_id'] ?? $editor_draft['dungeon_id'] ?? ''));
    if ($object_id === '') {
      throw new \OutOfBoundsException('place_object_id_missing');
    }
    if ($request->isMethod('GET')) {
      return new JsonResponse([
        'data' => [
          'kind' => $kind,
          'object_id' => $object_id,
          'preview_url' => $this->places->previewUrl($kind, $object_id),
        ],
      ]);
    }
    $payload = $kind === 'room'
      ? ['objects' => ['rooms' => ['generation_specs' => [[
        'room_id' => $object_id,
        'narrative' => (string) ($editor_draft['room']['description'] ?? ''),
        'chapter_id' => '',
        'scene_id' => '',
      ]]]]]
      : ['objects' => [
        'dungeons' => ['generation_specs' => [[
          'dungeon_id' => $object_id,
          'purpose' => (string) ($editor_draft['dungeon']['description'] ?? ''),
          'chapter_id' => '',
        ]]],
        'rooms' => ['generation_specs' => array_map(
          static fn(array $pin): array => [
            'room_id' => (string) ($pin['room_id'] ?? ''),
            'dungeon_id' => $object_id,
          ],
          (array) ($editor_draft['placement_pins'] ?? $editor_draft['dungeon']['placement_pins'] ?? []),
        )],
      ]];
    $result = $this->places->generate(
      $kind,
      $object_id,
      $payload,
      (int) $this->account->id(),
    );
    if (empty($result['stored'])) {
      return new JsonResponse([
        'error' => [
          'code' => (string) ($result['reason'] ?? 'place_image_failed'),
          'message' => (string) ($result['message'] ?? 'Place image was not stored.'),
        ],
      ], 503);
    }
    return new JsonResponse([
      'data' => [
        'kind' => $kind,
        'object_id' => $object_id,
        'preview_url' => (string) ($result['url'] ?? ''),
      ],
    ]);
  }

}
