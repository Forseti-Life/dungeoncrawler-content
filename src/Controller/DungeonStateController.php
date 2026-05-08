<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\dungeoncrawler_content\Access\CampaignAccessCheck;
use Drupal\dungeoncrawler_content\Service\DungeonStateService;
use Drupal\dungeoncrawler_content\Service\StateValidationService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * API endpoints for dungeon state get/set with optimistic locking.
 */
class DungeonStateController extends ControllerBase {

  private DungeonStateService $dungeonStateService;
  private CampaignAccessCheck $campaignAccessCheck;
  protected $currentUser;
  private StateValidationService $validationService;

  public function __construct(
    DungeonStateService $dungeon_state_service,
    CampaignAccessCheck $campaign_access_check,
    AccountInterface $current_user,
    StateValidationService $validation_service
  ) {
    $this->dungeonStateService = $dungeon_state_service;
    $this->campaignAccessCheck = $campaign_access_check;
    $this->currentUser = $current_user;
    $this->validationService = $validation_service;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.dungeon_state_service'),
      $container->get('dungeoncrawler_content.campaign_access_check'),
      $container->get('current_user'),
      $container->get('dungeoncrawler_content.state_validation_service')
    );
  }

  /**
   * GET /api/dungeon/{dungeonId}/state
   */
  public function getState(string $dungeon_id, Request $request): JsonResponse {
    $campaign_id = $request->query->get('campaignId');
    if (!is_numeric($campaign_id)) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'campaignId is required',
      ], 400);
    }
    $campaign_id = (int) $campaign_id;

    // Check campaign access.
    $access = $this->campaignAccessCheck->access($this->currentUser, $campaign_id);
    if (!$access->isAllowed()) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Access denied to campaign',
      ], 403);
    }

    try {
      $state = $this->dungeonStateService->getState($dungeon_id, $campaign_id);
      return new JsonResponse([
        'success' => TRUE,
        'data' => $state,
        'version' => $state['version'] ?? 0,
      ]);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], $e->getCode() === 404 ? 404 : 400);
    }
  }

  /**
   * POST /api/dungeon/{dungeonId}/state
   */
  public function setState(string $dungeon_id, Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE);
    if (!is_array($data)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Invalid JSON'], 400);
    }

    $expected_version = isset($data['expectedVersion']) ? (int) $data['expectedVersion'] : NULL;
    $state_payload = $data['state'] ?? NULL;
    if (!isset($data['campaignId']) || !is_numeric($data['campaignId'])) {
      return new JsonResponse(['success' => FALSE, 'error' => 'campaignId is required'], 400);
    }
    $campaign_id = (int) $data['campaignId'];

    // Check campaign access.
    $access = $this->campaignAccessCheck->access($this->currentUser, $campaign_id);
    if (!$access->isAllowed()) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Access denied to campaign',
      ], 403);
    }

    if (isset($state_payload['dungeonId']) && (string) $state_payload['dungeonId'] !== (string) $dungeon_id) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'dungeonId in payload must match path',
      ], 400);
    }

    if (!is_array($state_payload)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Missing state payload'], 400);
    }

    // Validate dungeon state payload against schema.
    $validation = $this->validationService->validateDungeonState($state_payload);
    if (!$validation['valid']) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Invalid state payload',
        'validation_errors' => $validation['errors'],
      ], 400);
    }

    try {
      $updated = $this->dungeonStateService->setState($dungeon_id, $state_payload, $expected_version, $campaign_id);
      return new JsonResponse([
        'success' => TRUE,
        'data' => $updated,
        'version' => $updated['version'] ?? 0,
      ]);
    }
    catch (\InvalidArgumentException $e) {
      $code = $e->getCode() === 409 ? 409 : 400;
      $current = $this->dungeonStateService->getState($dungeon_id, $campaign_id);
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
        'currentVersion' => $current['version'] ?? 0,
        'data' => $current,
      ], $code);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], 500);
    }
  }

}
