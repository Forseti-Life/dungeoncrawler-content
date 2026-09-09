<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Exception\LegacyCampaignArchivedException;
use Drupal\dungeoncrawler_content\Service\EncounterStateService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Lightweight combat encounter API for hexmap integration.
 *
 * Phase 2 (object-state authority): this controller is a presentation/read
 * consumer. It no longer injects CombatEncounterStore or assembles encounter
 * projections itself. Every current-state read is delegated to the single
 * canonical owner, EncounterStateService, which owns the raw persistence read,
 * the encounter-map-v1 projection, and the CampaignLifecycleService guard.
 * Mutation endpoints remain disabled in favor of the canonical
 * /api/game/{campaign_id}/action turn/round authority.
 */
class CombatEncounterApiController extends ControllerBase {

  /**
   * Legacy mutation error code.
   */
  protected const LEGACY_MUTATION_DISABLED_CODE = 'legacy_combat_mutation_disabled';

  /**
   * Canonical encounter current-state owner.
   *
   * @var \Drupal\dungeoncrawler_content\Service\EncounterStateService
   */
  protected EncounterStateService $encounterState;

  /**
   * Constructor.
   */
  public function __construct(EncounterStateService $encounter_state) {
    $this->encounterState = $encounter_state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.encounter_state')
    );
  }

  /**
   * Return the current combat/encounter state for a campaign + room.
   *
   * Called periodically by the JS client for server-state sync. Delegates to
   * the canonical owner, which returns either the active-encounter payload or a
   * normalized idle presentation. Archived/legacy campaigns hard-fail.
   */
  public function currentState(Request $request): JsonResponse {
    $campaign_id = (int) $request->query->get('campaignId', 0);
    $room_id = (string) $request->query->get('roomId', '');

    try {
      $data = $this->encounterState->getActiveEncounterStateForContext($campaign_id, $room_id);
    }
    catch (LegacyCampaignArchivedException $e) {
      return $this->legacyCampaignArchivedResponse($e);
    }

    return new JsonResponse([
      'success' => TRUE,
      'data' => $data,
    ]);
  }

  /**
   * Start a new encounter.
   */
  public function start(Request $request): JsonResponse {
    return $this->legacyMutationDisabledResponse('/api/game/{campaign_id}/action');
  }

  /**
   * Advance turn for the active encounter.
   */
  public function endTurn(Request $request): JsonResponse {
    return $this->legacyMutationDisabledResponse('/api/game/{campaign_id}/action');
  }

  /**
   * End an encounter.
   */
  public function end(Request $request): JsonResponse {
    return $this->legacyMutationDisabledResponse('/api/game/{campaign_id}/action');
  }

  /**
   * Get encounter state for a given encounterId.
   */
  public function get(Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE) ?: [];
    $encounter_id = $data['encounterId'] ?? NULL;

    if (!$encounter_id) {
      return new JsonResponse(['error' => 'encounterId is required'], 400);
    }

    try {
      $state = $this->encounterState->tryGetState((int) $encounter_id);
    }
    catch (LegacyCampaignArchivedException $e) {
      return $this->legacyCampaignArchivedResponse($e);
    }

    if ($state === NULL) {
      return new JsonResponse(['error' => 'Encounter not found'], 404);
    }

    return new JsonResponse($state);
  }

  /**
   * Replace encounter state (turn index/status/participants) with optimistic lock.
   */
  public function set(Request $request): JsonResponse {
    return $this->legacyMutationDisabledResponse('/api/game/{campaign_id}/action');
  }

  /**
   * Execute a basic attack (stub).
   */
  public function attack(Request $request): JsonResponse {
    return $this->legacyMutationDisabledResponse('/api/game/{campaign_id}/action');
  }

  /**
   * Execute non-attack combat actions (interact/talk).
   */
  public function action(Request $request): JsonResponse {
    return $this->legacyMutationDisabledResponse('/api/game/{campaign_id}/action');
  }

  /**
   * Return standardized response for disabled legacy mutation endpoints.
   */
  protected function legacyMutationDisabledResponse(string $canonical_path): JsonResponse {
    return new JsonResponse([
      'success' => FALSE,
      'error_code' => self::LEGACY_MUTATION_DISABLED_CODE,
      'error' => sprintf('Legacy combat mutation endpoints are disabled. Use %s as the single canonical turn/round authority.', $canonical_path),
      'canonical_endpoint' => $canonical_path,
    ], 409);
  }

  /**
   * Standardized rejection for archived/legacy campaign reads.
   */
  protected function legacyCampaignArchivedResponse(LegacyCampaignArchivedException $e): JsonResponse {
    return new JsonResponse([
      'success' => FALSE,
      'error_code' => LegacyCampaignArchivedException::CODE,
      'error' => $e->getMessage(),
    ], 409);
  }

}
