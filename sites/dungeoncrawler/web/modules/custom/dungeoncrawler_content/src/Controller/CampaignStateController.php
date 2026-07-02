<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\CampaignStateService;
use Drupal\dungeoncrawler_content\Service\StateValidationService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * API endpoints for campaign state get/set with optimistic locking.
 */
class CampaignStateController extends ControllerBase {

  private CampaignStateService $campaignStateService;
  private StateValidationService $validationService;

  public function __construct(
    CampaignStateService $campaign_state_service,
    StateValidationService $validation_service
  ) {
    $this->campaignStateService = $campaign_state_service;
    $this->validationService = $validation_service;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.campaign_state_service'),
      $container->get('dungeoncrawler_content.state_validation_service')
    );
  }

  /**
   * GET /api/campaign/{campaignId}/state
   */
  public function getState(int $campaign_id): JsonResponse {
    try {
      $state = $this->campaignStateService->getState($campaign_id);

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
   * POST /api/campaign/{campaignId}/state
   */
  public function setState(int $campaign_id, Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE);
    if (!is_array($data)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Invalid JSON'], 400);
    }

    $expected_version = isset($data['expectedVersion']) ? (int) $data['expectedVersion'] : NULL;
    $state_payload = $data['state'] ?? NULL;

    if (!is_array($state_payload)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Missing state payload'], 400);
    }

    // Validate state payload against schema.
    $validation = $this->validationService->validateCampaignState($state_payload);
    if (!$validation['valid']) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Invalid state payload',
        'validation_errors' => $validation['errors'],
      ], 400);
    }

    try {
      $updated = $this->campaignStateService->setState($campaign_id, $state_payload, $expected_version);
      return new JsonResponse([
        'success' => TRUE,
        'data' => $updated,
        'version' => $updated['version'] ?? 0,
      ]);
    }
    catch (\InvalidArgumentException $e) {
      $code = $e->getCode() === 409 ? 409 : 400;
      $current = $this->campaignStateService->getState($campaign_id);
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
