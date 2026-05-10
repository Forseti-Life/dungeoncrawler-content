<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\RoomViewImageService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * API controller for room-scene view images in the hexmap shell.
 */
class RoomViewImageController extends ControllerBase {

  /**
   * Room view image service.
   */
  protected RoomViewImageService $roomViewImageService;

  /**
   * Constructs the controller.
   */
  public function __construct(RoomViewImageService $room_view_image_service) {
    $this->roomViewImageService = $room_view_image_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.room_view_image')
    );
  }

  /**
   * Return the cached/generated room-scene image for the View tab.
   */
  public function getRoomViewImage(int $campaign_id, string $room_id): JsonResponse {
    try {
      return new JsonResponse([
        'success' => TRUE,
        'data' => $this->roomViewImageService->getRoomViewImage($campaign_id, $room_id),
      ]);
    }
    catch (\RuntimeException $exception) {
      $status = (int) $exception->getCode();
      if ($status < 400 || $status > 599) {
        $status = 500;
      }

      return new JsonResponse([
        'success' => FALSE,
        'error' => $exception->getMessage(),
      ], $status);
    }
    catch (\Throwable $exception) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Unable to load room view image.',
      ], 500);
    }
  }

}
