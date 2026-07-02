<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\dungeoncrawler_content\Access\CampaignAccessCheck;
use Drupal\dungeoncrawler_content\Service\CombatEncounterStore;
use Drupal\dungeoncrawler_content\Service\EncounterAiIntegrationService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Read-only encounter AI recommendation preview endpoints.
 */
class EncounterAiPreviewController extends ControllerBase {

  /**
   * Encounter store.
   */
  protected CombatEncounterStore $encounterStore;

  /**
   * Encounter AI integration service.
   */
  protected EncounterAiIntegrationService $encounterAiIntegration;

  /**
   * Campaign access checker.
   */
  protected CampaignAccessCheck $campaignAccessCheck;

  /**
   * Current account.
   */
  protected AccountInterface $currentAccount;

  /**
   * Constructs controller.
   */
  public function __construct(CombatEncounterStore $encounter_store, EncounterAiIntegrationService $encounter_ai_integration, CampaignAccessCheck $campaign_access_check, AccountInterface $current_account) {
    $this->encounterStore = $encounter_store;
    $this->encounterAiIntegration = $encounter_ai_integration;
    $this->campaignAccessCheck = $campaign_access_check;
    $this->currentAccount = $current_account;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.combat_encounter_store'),
      $container->get('dungeoncrawler_content.encounter_ai_integration'),
      $container->get('dungeoncrawler_content.campaign_access_check'),
      $container->get('current_user'),
    );
  }

  /**
   * POST /api/combat/recommendation-preview
   *
   * Read-only endpoint returning recommendation + validation diagnostics.
   */
  public function preview(Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE);
    if (!is_array($data)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Invalid JSON payload'], 400);
    }

    $encounter_id = isset($data['encounterId']) ? (int) $data['encounterId'] : 0;
    if ($encounter_id <= 0) {
      return new JsonResponse(['success' => FALSE, 'error' => 'encounterId is required'], 400);
    }

    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    if (!$encounter) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Encounter not found'], 404);
    }

    $campaign_id = isset($encounter['campaign_id']) && $encounter['campaign_id'] !== NULL
      ? (int) $encounter['campaign_id']
      : 0;

    if ($campaign_id > 0 && !$this->campaignAccessCheck->access($this->currentAccount, $campaign_id)->isAllowed()) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied to campaign'], 403);
    }

    try {
      $context = $this->encounterAiIntegration->buildEncounterContext($campaign_id, $encounter_id, $encounter);
      $recommendation = $this->encounterAiIntegration->requestNpcActionRecommendation($context);

      $include_narration = !empty($data['includeNarration']);
      $narration = $include_narration
        ? $this->encounterAiIntegration->requestEncounterNarration($context)
        : NULL;

      return new JsonResponse([
        'success' => TRUE,
        'read_only' => TRUE,
        'encounter_id' => $encounter_id,
        'campaign_id' => $campaign_id > 0 ? $campaign_id : NULL,
        'recommendation_preview' => $recommendation,
        'narration_preview' => $narration,
      ]);
    }
    catch (\InvalidArgumentException $exception) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $exception->getMessage(),
      ], 400);
    }
  }

}
