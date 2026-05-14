<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\StorylineManagerService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * API endpoints for storyline template and campaign storyline management.
 */
class StorylineController extends ControllerBase {

  protected StorylineManagerService $storylineManager;

  public function __construct(StorylineManagerService $storyline_manager) {
    $this->storylineManager = $storyline_manager;
  }

  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('dungeoncrawler_content.storyline_manager')
    );
  }

  /**
   * Lists stored storyline templates.
   */
  public function listTemplates(): JsonResponse {
    try {
      return new JsonResponse([
        'success' => TRUE,
        'templates' => $this->storylineManager->listTemplates(),
      ]);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['success' => FALSE, 'error' => $e->getMessage()], $e->getCode() ?: 400);
    }
  }

  /**
   * Imports or updates a storyline template.
   */
  public function importTemplate(Request $request): JsonResponse {
    $payload = json_decode($request->getContent(), TRUE);
    if (!is_array($payload)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Invalid JSON'], 400);
    }

    $definition = is_array($payload['template'] ?? NULL) ? $payload['template'] : $payload;

    try {
      $template = $this->storylineManager->saveTemplate($definition);
      return new JsonResponse([
        'success' => TRUE,
        'template' => $template,
      ], 201);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['success' => FALSE, 'error' => $e->getMessage()], $e->getCode() ?: 400);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Internal server error'], 500);
    }
  }

  /**
   * Lists storylines for a campaign.
   */
  public function listCampaignStorylines(int $campaign_id): JsonResponse {
    try {
      return new JsonResponse([
        'success' => TRUE,
        'storylines' => $this->storylineManager->listCampaignStorylines($campaign_id, TRUE),
      ]);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['success' => FALSE, 'error' => $e->getMessage()], $e->getCode() ?: 400);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Internal server error'], 500);
    }
  }

  /**
   * Returns a single campaign storyline.
   */
  public function getCampaignStoryline(int $campaign_id, string $storyline_id): JsonResponse {
    try {
      $storyline = $this->storylineManager->getCampaignStoryline($campaign_id, $storyline_id, TRUE);
      if ($storyline === NULL) {
        return new JsonResponse(['success' => FALSE, 'error' => 'Storyline not found'], 404);
      }

      return new JsonResponse([
        'success' => TRUE,
        'storyline' => $storyline,
      ]);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['success' => FALSE, 'error' => $e->getMessage()], $e->getCode() ?: 400);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Internal server error'], 500);
    }
  }

  /**
   * Creates a campaign storyline from a template or inline definition.
   */
  public function createCampaignStoryline(int $campaign_id, Request $request): JsonResponse {
    $payload = json_decode($request->getContent(), TRUE);
    if (!is_array($payload)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Invalid JSON'], 400);
    }

    try {
      if (!empty($payload['template_id'])) {
        $storyline = $this->storylineManager->instantiateStorylineTemplate(
          $campaign_id,
          (string) $payload['template_id'],
          $payload
        );
      }
      else {
        $definition = is_array($payload['template'] ?? NULL) ? $payload['template'] : $payload;
        $storyline = $this->storylineManager->createCampaignStoryline($campaign_id, $definition, $payload);
      }

      return new JsonResponse([
        'success' => TRUE,
        'storyline' => $storyline,
      ], 201);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['success' => FALSE, 'error' => $e->getMessage()], $e->getCode() ?: 400);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Internal server error'], 500);
    }
  }

  /**
   * Activates a campaign storyline.
   */
  public function activateCampaignStoryline(int $campaign_id, string $storyline_id, Request $request): JsonResponse {
    $payload = json_decode($request->getContent(), TRUE);
    $primary = is_array($payload) && !empty($payload['primary']);

    try {
      $storyline = $this->storylineManager->activateCampaignStoryline($campaign_id, $storyline_id, $primary);
      if ($storyline === NULL) {
        return new JsonResponse(['success' => FALSE, 'error' => 'Storyline not found'], 404);
      }

      return new JsonResponse([
        'success' => TRUE,
        'storyline' => $storyline,
      ]);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['success' => FALSE, 'error' => $e->getMessage()], $e->getCode() ?: 400);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Internal server error'], 500);
    }
  }

  /**
   * Advances or edits storyline runtime state.
   */
  public function advanceCampaignStoryline(int $campaign_id, string $storyline_id, Request $request): JsonResponse {
    $payload = json_decode($request->getContent(), TRUE);
    if (!is_array($payload)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Invalid JSON'], 400);
    }

    try {
      $storyline = $this->storylineManager->advanceCampaignStoryline($campaign_id, $storyline_id, $payload);
      if ($storyline === NULL) {
        return new JsonResponse(['success' => FALSE, 'error' => 'Storyline not found'], 404);
      }

      return new JsonResponse([
        'success' => TRUE,
        'storyline' => $storyline,
      ]);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['success' => FALSE, 'error' => $e->getMessage()], $e->getCode() ?: 400);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Internal server error'], 500);
    }
  }

  /**
   * Returns storyline journal/log entries.
   */
  public function getCampaignStorylineJournal(int $campaign_id, string $storyline_id): JsonResponse {
    try {
      return new JsonResponse([
        'success' => TRUE,
        'journal' => $this->storylineManager->getCampaignStorylineLog($campaign_id, $storyline_id),
      ]);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['success' => FALSE, 'error' => $e->getMessage()], $e->getCode() ?: 400);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Internal server error'], 500);
    }
  }

}
