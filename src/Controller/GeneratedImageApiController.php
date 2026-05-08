<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\dungeoncrawler_content\Access\CampaignAccessCheck;
use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;
use Drupal\dungeoncrawler_content\Service\SpriteGenerationService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Read APIs for generated image assets.
 */
class GeneratedImageApiController extends ControllerBase {

  /**
   * Image repository service.
   */
  protected GeneratedImageRepository $imageRepository;

  /**
   * Campaign access checker.
   */
  protected CampaignAccessCheck $campaignAccessCheck;

  /**
   * Current account.
   */
  protected AccountInterface $currentAccount;

  /**
   * Sprite generation service.
   */
  protected SpriteGenerationService $spriteGenerator;

  /**
   * Constructs the controller.
   */
  public function __construct(GeneratedImageRepository $image_repository, CampaignAccessCheck $campaign_access_check, AccountInterface $current_account, SpriteGenerationService $sprite_generator) {
    $this->imageRepository = $image_repository;
    $this->campaignAccessCheck = $campaign_access_check;
    $this->currentAccount = $current_account;
    $this->spriteGenerator = $sprite_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.generated_image_repository'),
      $container->get('dungeoncrawler_content.campaign_access_check'),
      $container->get('current_user'),
      $container->get('dungeoncrawler_content.sprite_generator'),
    );
  }

  /**
   * GET /api/image/{image_uuid}
   */
  public function getImage(string $image_uuid): JsonResponse {
    $image = $this->imageRepository->loadImageByUuid($image_uuid);
    if (!$image || ($image['status'] ?? '') !== 'ready') {
      return new JsonResponse(['success' => FALSE, 'error' => 'Image not found'], 404);
    }

    $links = $this->imageRepository->loadLinksForImageId((int) $image['id']);
    if (!$this->canViewImage($image, $links)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    return new JsonResponse([
      'success' => TRUE,
      'image' => $this->buildImageResponse($image, $links),
    ]);
  }

  /**
   * GET /api/images/object/{table_name}/{object_id}
   */
  public function getObjectImages(string $table_name, string $object_id, Request $request): JsonResponse {
    if (!$this->isAllowedObjectTable($table_name)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Invalid table_name'], 400);
    }

    $campaign_id = $request->query->get('campaign_id');
    $campaign_id = is_numeric($campaign_id) ? (int) $campaign_id : NULL;

    $slot = $request->query->get('slot');
    $slot = is_string($slot) && $slot !== '' ? $slot : NULL;

    $variant = $request->query->get('variant');
    $variant = is_string($variant) && $variant !== '' ? $variant : NULL;

    if ($campaign_id !== NULL && !$this->campaignAccessCheck->access($this->currentAccount, $campaign_id)->isAllowed()) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied to campaign'], 403);
    }

    $rows = $this->imageRepository->loadImagesForObject($table_name, $object_id, $campaign_id, $slot, $variant);
    $items = [];
    foreach ($rows as $row) {
      if (!$this->canViewLinkedRow($row, $campaign_id)) {
        continue;
      }
      $items[] = $this->buildLinkedImageListItem($row);
    }

    return new JsonResponse([
      'success' => TRUE,
      'table_name' => $table_name,
      'object_id' => $object_id,
      'campaign_id' => $campaign_id,
      'count' => count($items),
      'items' => $items,
    ]);
  }

  /**
   * GET /api/campaign/{campaign_id}/images
   */
  public function getCampaignImages(int $campaign_id, Request $request): JsonResponse {
    if (!$this->campaignAccessCheck->access($this->currentAccount, $campaign_id)->isAllowed()) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied to campaign'], 403);
    }

    $table_name = $request->query->get('table_name');
    $table_name = is_string($table_name) && $table_name !== '' ? $table_name : NULL;
    if ($table_name !== NULL && !$this->isAllowedObjectTable($table_name)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Invalid table_name'], 400);
    }

    $object_id = $request->query->get('object_id');
    $object_id = is_string($object_id) && $object_id !== '' ? $object_id : NULL;

    $slot = $request->query->get('slot');
    $slot = is_string($slot) && $slot !== '' ? $slot : NULL;

    $rows = $this->imageRepository->loadCampaignImages($campaign_id, $table_name, $object_id, $slot);
    $items = [];
    foreach ($rows as $row) {
      if (!$this->canViewLinkedRow($row, $campaign_id)) {
        continue;
      }
      $items[] = $this->buildLinkedImageListItem($row);
    }

    return new JsonResponse([
      'success' => TRUE,
      'campaign_id' => $campaign_id,
      'count' => count($items),
      'items' => $items,
    ]);
  }

  /**
   * Determines whether table is allowed for image linkage queries.
   */
  private function isAllowedObjectTable(string $table_name): bool {
    return (bool) preg_match('/^(dc_|dungeoncrawler_content_)[A-Za-z0-9_]+$/', $table_name);
  }

  /**
   * Access control for image by links and ownership.
   */
  private function canViewImage(array $image, array $links): bool {
    $owner_uid = (int) ($image['owner_uid'] ?? 0);
    if ($owner_uid > 0 && $owner_uid === (int) $this->currentAccount->id()) {
      return TRUE;
    }

    foreach ($links as $link) {
      if ($this->canViewLinkedRow($link, NULL)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Access control for linked image row.
   */
  private function canViewLinkedRow(array $row, ?int $resolved_campaign_id): bool {
    $visibility = (string) ($row['visibility'] ?? 'owner');

    if ($visibility === 'public') {
      return TRUE;
    }

    $owner_uid = (int) ($row['owner_uid'] ?? 0);
    if ($owner_uid > 0 && $owner_uid === (int) $this->currentAccount->id()) {
      return TRUE;
    }

    $campaign_id = $resolved_campaign_id;
    if ($campaign_id === NULL && isset($row['campaign_id']) && is_numeric($row['campaign_id'])) {
      $campaign_id = (int) $row['campaign_id'];
    }

    if ($visibility === 'campaign_party' && $campaign_id !== NULL) {
      return $this->campaignAccessCheck->access($this->currentAccount, $campaign_id)->isAllowed();
    }

    return FALSE;
  }

  /**
   * Builds single image API response.
   */
  private function buildImageResponse(array $image, array $links): array {
    $url = $this->imageRepository->resolveClientUrl($image);

    return [
      'image_uuid' => $image['image_uuid'] ?? NULL,
      'provider' => $image['provider'] ?? NULL,
      'provider_model' => $image['provider_model'] ?? NULL,
      'mime_type' => $image['mime_type'] ?? NULL,
      'width' => isset($image['width']) ? (int) $image['width'] : NULL,
      'height' => isset($image['height']) ? (int) $image['height'] : NULL,
      'bytes' => isset($image['bytes']) ? (int) $image['bytes'] : NULL,
      'status' => $image['status'] ?? NULL,
      'url' => $url,
      'links' => array_map(function (array $link): array {
        return [
          'table_name' => $link['table_name'] ?? NULL,
          'object_id' => $link['object_id'] ?? NULL,
          'campaign_id' => isset($link['campaign_id']) ? (int) $link['campaign_id'] : NULL,
          'slot' => $link['slot'] ?? NULL,
          'variant' => $link['variant'] ?? NULL,
          'is_primary' => (bool) ($link['is_primary'] ?? 0),
          'visibility' => $link['visibility'] ?? 'owner',
        ];
      }, $links),
    ];
  }

  /**
   * Builds list item response for linked images.
   */
  private function buildLinkedImageListItem(array $row): array {
    return [
      'image_uuid' => $row['image_uuid'] ?? NULL,
      'table_name' => $row['table_name'] ?? NULL,
      'object_id' => $row['object_id'] ?? NULL,
      'campaign_id' => isset($row['campaign_id']) ? (int) $row['campaign_id'] : NULL,
      'slot' => $row['slot'] ?? NULL,
      'variant' => $row['variant'] ?? NULL,
      'is_primary' => (bool) ($row['is_primary'] ?? 0),
      'visibility' => $row['visibility'] ?? 'owner',
      'provider' => $row['provider'] ?? NULL,
      'provider_model' => $row['provider_model'] ?? NULL,
      'mime_type' => $row['mime_type'] ?? NULL,
      'width' => isset($row['width']) ? (int) $row['width'] : NULL,
      'height' => isset($row['height']) ? (int) $row['height'] : NULL,
      'bytes' => isset($row['bytes']) ? (int) $row['bytes'] : NULL,
      'url' => $this->imageRepository->resolveClientUrl($row),
      'created' => isset($row['image_created']) ? (int) $row['image_created'] : NULL,
    ];
  }

  /**
   * POST /api/sprites/resolve
   *
   * Resolves sprite URLs for a batch of object definitions.
   * Always checks cache first (campaign-scoped, then global/library),
   * and only generates missing sprites. The server controls generation
   * decisions — the client simply requests resolution.
   *
   * Request body:
   * {
   *   "campaign_id": 17,
   *   "object_definitions": {
   *     "wooden_tavern_door": { "label": "Tavern Door", "category": "door", ... },
   *     ...
   *   }
   * }
   */
  public function resolveSprites(Request $request): JsonResponse {
    $body = json_decode((string) $request->getContent(), TRUE);
    if (!is_array($body)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Invalid JSON body'], 400);
    }

    $campaign_id = isset($body['campaign_id']) && is_numeric($body['campaign_id']) ? (int) $body['campaign_id'] : NULL;
    $object_definitions = is_array($body['object_definitions'] ?? NULL) ? $body['object_definitions'] : [];

    if (empty($object_definitions)) {
      return new JsonResponse(['success' => TRUE, 'sprites' => (object) [], 'count' => 0]);
    }

    if ($campaign_id !== NULL && !$this->campaignAccessCheck->access($this->currentAccount, $campaign_id)->isAllowed()) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied to campaign'], 403);
    }

    $owner_uid = (int) $this->currentAccount->id();

    // Step 1: Bulk lookup — campaign-scoped first, then global/library fallback.
    // This is the cache layer. No generation happens here.
    $sprite_ids = [];
    $def_by_sprite = [];
    foreach ($object_definitions as $object_id => $def) {
      $sid = trim((string) ($def['visual']['sprite_id'] ?? ''));
      if ($sid !== '' && !isset($def_by_sprite[$sid])) {
        $sprite_ids[] = $sid;
        $def_by_sprite[$sid] = $def;
      }
    }

    $cached_urls = $this->spriteGenerator->lookupSprites($sprite_ids, $campaign_id);

    // Step 2: Identify truly missing sprites (no campaign image, no library image).
    $sprites = [];
    $missing = [];
    foreach ($sprite_ids as $sid) {
      if (isset($cached_urls[$sid]) && $cached_urls[$sid] !== NULL) {
        $sprites[$sid] = [
          'url' => $cached_urls[$sid],
          'generated' => FALSE,
          'cached' => TRUE,
        ];
      }
      else {
        $missing[$sid] = $def_by_sprite[$sid];
      }
    }

    // Step 3: Generate only the truly missing sprites.
    foreach ($missing as $sid => $def) {
      $result = $this->spriteGenerator->resolveSprite($sid, $def, $campaign_id, $owner_uid);
      $sprites[$sid] = [
        'url' => $result['url'],
        'generated' => $result['generated'] ?? FALSE,
        'cached' => $result['cached'] ?? FALSE,
      ];
    }

    return new JsonResponse([
      'success' => TRUE,
      'sprites' => (object) $sprites,
      'count' => count($sprites),
    ]);
  }

  /**
   * GET /api/sprite/{sprite_id}
   *
   * Returns an existing sprite URL or 404 if none exists.
   */
  public function getSprite(string $sprite_id, Request $request): JsonResponse {
    $campaign_id = $request->query->get('campaign_id');
    $campaign_id = is_numeric($campaign_id) ? (int) $campaign_id : NULL;

    // Delegate to centralized sprite lookup service.
    $url = $this->spriteGenerator->lookupSprite($sprite_id, $campaign_id);

    if ($url === NULL) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Sprite not found', 'sprite_id' => $sprite_id], 404);
    }

    return new JsonResponse([
      'success' => TRUE,
      'sprite_id' => $sprite_id,
      'url' => $url,
    ]);
  }

}
