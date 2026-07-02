<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\GameCoordinatorService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Game Coordinator Controller — the single server entry point for gameplay.
 *
 * Provides 4 JSON API endpoints:
 *   POST /api/game/{campaign_id}/action    — process a player action intent
 *   GET  /api/game/{campaign_id}/state     — get full game state
 *   POST /api/game/{campaign_id}/transition — manually transition game phase
 *   GET  /api/game/{campaign_id}/events    — get events since cursor (polling)
 *
 * All endpoints are server-authoritative — the client sends intents and
 * receives the resolved state. No game logic lives on the client.
 */
class GameCoordinatorController extends ControllerBase {

  /**
   * The game coordinator service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\GameCoordinatorService
   */
  protected GameCoordinatorService $gameCoordinator;

  /**
   * Constructor.
   */
  public function __construct(GameCoordinatorService $game_coordinator) {
    $this->gameCoordinator = $game_coordinator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.game_coordinator')
    );
  }

  /**
   * Process a player action intent.
   *
   * POST /api/game/{campaign_id}/action
   *
   * Request body:
   * {
   *   "type": "strike",
   *   "actor": "char_123",
   *   "target": "entity_goblin_1",
   *   "params": { "weapon": "longsword" },
   *   "client_state_version": 42
   * }
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   * @param int $campaign_id
   *   The campaign ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Unified action response.
   */
  public function action(Request $request, int $campaign_id): JsonResponse {
    $content = $request->getContent();
    $intent = json_decode($content, TRUE);

    if (!$intent || !isset($intent['type'])) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Invalid request body. Required: { "type": "..." }',
      ], 400);
    }

    $result = $this->gameCoordinator->processAction($campaign_id, $intent);

    $status = ($result['success'] ?? FALSE) ? 200 : 422;
    return new JsonResponse($result, $status);
  }

  /**
   * Get the full game state for client sync.
   *
   * GET /api/game/{campaign_id}/state
   *
   * @param int $campaign_id
   *   The campaign ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Full game state payload.
   */
  public function getState(int $campaign_id): JsonResponse {
    $result = $this->gameCoordinator->getFullState($campaign_id);

    $status = ($result['success'] ?? FALSE) ? 200 : 404;
    return new JsonResponse($result, $status);
  }

  /**
   * Manually transition to a new game phase.
   *
   * POST /api/game/{campaign_id}/transition
   *
   * Request body:
   * {
   *   "target_phase": "encounter",
   *   "context": {
   *     "encounter_context": { "enemies": [...], "room_id": "..." }
   *   }
   * }
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   * @param int $campaign_id
   *   The campaign ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Transition result.
   */
  public function transition(Request $request, int $campaign_id): JsonResponse {
    $content = $request->getContent();
    $payload = json_decode($content, TRUE);

    $target_phase = $payload['target_phase'] ?? NULL;
    if (!$target_phase) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Invalid request body. Required: { "target_phase": "..." }',
      ], 400);
    }

    $context = $payload['context'] ?? [];
    $result = $this->gameCoordinator->transitionPhase($campaign_id, $target_phase, $context);

    $status = ($result['success'] ?? FALSE) ? 200 : 422;
    return new JsonResponse($result, $status);
  }

  /**
   * Get events since a cursor (for polling).
   *
   * GET /api/game/{campaign_id}/events?since=42
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   * @param int $campaign_id
   *   The campaign ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Events array.
   */
  public function events(Request $request, int $campaign_id): JsonResponse {
    $since = (int) $request->query->get('since', 0);
    $result = $this->gameCoordinator->getEventsSince($campaign_id, $since);

    return new JsonResponse($result);
  }

}
