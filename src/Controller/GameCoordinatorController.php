<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\GameCoordinatorService;
use Drupal\dungeoncrawler_content\Service\PlayerAgentHarnessService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Game Coordinator Controller — the single server entry point for gameplay.
 *
 * Provides game-loop JSON API endpoints:
 *   POST /api/game/{campaign_id}/action    — process a player action intent
 *   GET  /api/game/{campaign_id}/state     — get full game state
 *   POST /api/game/{campaign_id}/transition — manually transition game phase
 *   GET  /api/game/{campaign_id}/events    — get events since cursor (polling)
 *   POST /api/game/{campaign_id}/player-agent/step — run one autonomous step
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
   * The player-agent harness service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\PlayerAgentHarnessService
   */
  protected PlayerAgentHarnessService $playerAgentHarness;

  /**
   * Constructor.
   */
  public function __construct(
    GameCoordinatorService $game_coordinator,
    PlayerAgentHarnessService $player_agent_harness
  ) {
    $this->gameCoordinator = $game_coordinator;
    $this->playerAgentHarness = $player_agent_harness;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.game_coordinator'),
      $container->get('dungeoncrawler_content.player_agent_harness')
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

  /**
   * Run a single autonomous player-agent step for the current campaign.
   *
   * POST /api/game/{campaign_id}/player-agent/step
   *
   * Request body:
   * {
   *   "profile": { "actor_id": "...", "character_id": 123, ... },
   *   "run_state": { ... }
   * }
   *
   * Returns HTTP 200 for valid requests even when the agent step fails so the
   * browser harness can receive the run_state/error payload and decide whether
   * to keep running or stop.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   * @param int $campaign_id
   *   The campaign ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The step result plus an authoritative state sync payload.
   */
  public function playerAgentStep(Request $request, int $campaign_id): JsonResponse {
    $content = $request->getContent();
    $payload = json_decode($content, TRUE);

    if (!is_array($payload)) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Invalid request body. Expected JSON object.',
      ], 400);
    }

    $profile = is_array($payload['profile'] ?? NULL) ? $payload['profile'] : NULL;
    if ($profile === NULL) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Invalid request body. Required: { "profile": { ... } }',
      ], 400);
    }

    $run_state = is_array($payload['run_state'] ?? NULL) ? $payload['run_state'] : [];
    \Drupal::logger('dungeoncrawler_player_agent')->info(
      'Player-agent step request: campaign @campaign actor @actor character @character waits @waits/@max_waits failures @failures/@max_failures step_count @step_count talked=@talked pending=@pending active=@active',
      [
        '@campaign' => $campaign_id,
        '@actor' => (string) ($profile['actor_id'] ?? ''),
        '@character' => (int) ($profile['character_id'] ?? 0),
        '@waits' => (int) ($run_state['guardrails']['consecutive_waits'] ?? 0),
        '@max_waits' => (int) ($run_state['guardrails']['max_consecutive_waits'] ?? 0),
        '@failures' => (int) ($run_state['guardrails']['consecutive_failures'] ?? 0),
        '@max_failures' => (int) ($run_state['guardrails']['max_consecutive_failures'] ?? 0),
        '@step_count' => (int) ($run_state['step_count'] ?? 0),
        '@talked' => implode(',', array_slice(array_map('strval', (array) ($run_state['memory']['talked_entities'] ?? [])), -5)) ?: 'none',
        '@pending' => is_array($run_state['memory']['pending_conversation_lead'] ?? NULL)
          ? json_encode($run_state['memory']['pending_conversation_lead'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
          : 'none',
        '@active' => is_array($run_state['memory']['active_npc_lead'] ?? NULL)
          ? json_encode($run_state['memory']['active_npc_lead'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
          : 'none',
      ]
    );
    $result = $this->playerAgentHarness->runStep($campaign_id, $profile, $run_state);
    $result['state_sync'] = $this->gameCoordinator->getFullState($campaign_id);

    $decision = is_array($result['decision'] ?? NULL) ? $result['decision'] : [];
    $response_result = is_array($result['response']['result'] ?? NULL) ? $result['response']['result'] : [];
    $events = is_array($result['response']['events'] ?? NULL) ? $result['response']['events'] : [];
    $active_room_id = (string) ($result['response']['game_state']['active_room_id'] ?? $result['snapshot']['game_state']['active_room_id'] ?? '');
    \Drupal::logger('dungeoncrawler_player_agent')->info(
      'Player-agent step result: campaign @campaign actor @actor success @success phase @phase room @room decision @decision reason "@reason" talked @talked searched @searched rested @rested events @events waits @waits/@max_waits failures @failures/@max_failures',
      [
        '@campaign' => $campaign_id,
        '@actor' => (string) ($profile['actor_id'] ?? ''),
        '@success' => !empty($result['success']) ? 'yes' : 'no',
        '@phase' => (string) ($result['snapshot']['phase'] ?? ''),
        '@room' => $active_room_id,
        '@decision' => (string) ($decision['type'] ?? ''),
        '@reason' => substr((string) ($decision['reason'] ?? ''), 0, 240),
        '@talked' => !empty($response_result['talked']) ? 'yes' : 'no',
        '@searched' => !empty($response_result['searched']) ? 'yes' : 'no',
        '@rested' => !empty($response_result['rested']) ? 'yes' : 'no',
        '@events' => count($events),
        '@waits' => (int) ($result['run_state']['guardrails']['consecutive_waits'] ?? 0),
        '@max_waits' => (int) ($result['run_state']['guardrails']['max_consecutive_waits'] ?? 0),
        '@failures' => (int) ($result['run_state']['guardrails']['consecutive_failures'] ?? 0),
        '@max_failures' => (int) ($result['run_state']['guardrails']['max_consecutive_failures'] ?? 0),
      ]
    );

    return new JsonResponse($result);
  }

}
