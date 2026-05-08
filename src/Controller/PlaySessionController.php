<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;
use Drupal\dungeoncrawler_content\Access\CampaignAccessCheck;
use Drupal\dungeoncrawler_content\Service\SessionService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * REST endpoints for individual play sessions (dc-cr-session-structure).
 *
 * Routes:
 *   POST   /api/sessions/start                                  — start a session
 *   GET    /api/sessions/{session_id}                           — get session
 *   POST   /api/sessions/{session_id}/end                       — end + commit state
 *   GET    /api/campaign/{campaign_id}/play-sessions            — list sessions
 *   GET    /api/campaign/{campaign_id}/play-sessions/latest-state — last character state
 *   GET    /api/campaign/{campaign_id}/play-sessions/ai-context — AI GM context
 *   POST   /api/campaign/{campaign_id}/xp-total/{character_id}  — cumulative XP
 *   POST   /api/campaign/{campaign_id}/invite                   — invite player (registered users only)
 */
class PlaySessionController extends ControllerBase {

  private SessionService $sessionService;
  private Connection $database;
  private CampaignAccessCheck $campaignAccessCheck;
  protected $currentUser;

  public function __construct(
    SessionService $session_service,
    Connection $database,
    CampaignAccessCheck $campaign_access_check,
    AccountInterface $current_user
  ) {
    $this->sessionService = $session_service;
    $this->database = $database;
    $this->campaignAccessCheck = $campaign_access_check;
    $this->currentUser = $current_user;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.session_service'),
      $container->get('database'),
      $container->get('dungeoncrawler_content.campaign_access_check'),
      $container->get('current_user')
    );
  }

  // ──────────────────────────────────────────────────────────────────────────
  // POST /api/sessions/start
  // ──────────────────────────────────────────────────────────────────────────

  /**
   * Start a new play session.
   *
   * Body (JSON):
   *   mode: "one-shot" | "campaign-chapter"
   *   campaign_id: int (required for campaign-chapter)
   *   player_uids: int[]
   *   narrative_summary: string (optional)
   *
   * The gm_uid is derived from the authenticated current user.
   */
  public function startSession(Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE);
    if (!is_array($data)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Invalid JSON'], 400);
    }

    // Verify campaign access when a campaign_id is provided.
    $campaign_id = isset($data['campaign_id']) ? (int) $data['campaign_id'] : NULL;
    if ($campaign_id !== NULL) {
      $access = $this->campaignAccessCheck->access($this->currentUser, $campaign_id);
      if (!$access->isAllowed()) {
        return new JsonResponse(['success' => FALSE, 'error' => 'Access denied to campaign'], 403);
      }
    }

    $data['gm_uid'] = (int) $this->currentUser->id();
    try {
      $session = $this->sessionService->startSession($data);
      return new JsonResponse(['success' => TRUE, 'session' => $session], 201);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['success' => FALSE, 'error' => $e->getMessage()], 400);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Internal error'], 500);
    }
  }

  // ──────────────────────────────────────────────────────────────────────────
  // GET /api/sessions/{session_id}
  // ──────────────────────────────────────────────────────────────────────────

  /**
   * Get a session by ID.
   */
  public function getSession(int $session_id): JsonResponse {
    $session = $this->sessionService->getSession($session_id);
    if (!$session) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Session not found'], 404);
    }

    // Check ownership: current user must be GM or a player.
    if (!$this->canAccessSession($session)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    return new JsonResponse(['success' => TRUE, 'session' => $session]);
  }

  // ──────────────────────────────────────────────────────────────────────────
  // POST /api/sessions/{session_id}/end
  // ──────────────────────────────────────────────────────────────────────────

  /**
   * End a session and commit all character state.
   *
   * Body (JSON):
   *   character_states: [{character_id, xp, hp, conditions, inventory}, ...]
   *   session_xp: int
   *   narrative_summary: string
   *   npcs: [{id, name, last_known_state, relationship_status}, ...]
   */
  public function endSession(int $session_id, Request $request): JsonResponse {
    $session = $this->sessionService->getSession($session_id);
    if (!$session) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Session not found'], 404);
    }
    if (!$this->canAccessSession($session)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    $data = json_decode($request->getContent(), TRUE);
    if (!is_array($data)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Invalid JSON'], 400);
    }

    try {
      $updated = $this->sessionService->endSession($session_id, $data);
      return new JsonResponse(['success' => TRUE, 'session' => $updated]);
    }
    catch (\InvalidArgumentException $e) {
      $code = $e->getCode() ?: 400;
      return new JsonResponse(['success' => FALSE, 'error' => $e->getMessage()], $code);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Internal error'], 500);
    }
  }

  // ──────────────────────────────────────────────────────────────────────────
  // GET /api/campaign/{campaign_id}/play-sessions
  // ──────────────────────────────────────────────────────────────────────────

  /**
   * List play sessions for a campaign in chronological order (AC-002).
   */
  public function listCampaignSessions(int $campaign_id, Request $request): JsonResponse {
    $access = $this->campaignAccessCheck->access($this->currentUser, $campaign_id);
    if (!$access->isAllowed()) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    $limit = min((int) ($request->query->get('limit', 50)), 200);
    $offset = (int) $request->query->get('offset', 0);
    $sessions = $this->sessionService->listCampaignSessions($campaign_id, $limit, $offset);

    return new JsonResponse([
      'success' => TRUE,
      'campaign_id' => $campaign_id,
      'sessions' => $sessions,
      'count' => count($sessions),
    ]);
  }

  // ──────────────────────────────────────────────────────────────────────────
  // GET /api/campaign/{campaign_id}/play-sessions/latest-state
  // ──────────────────────────────────────────────────────────────────────────

  /**
   * Get the latest committed character state for a campaign (AC-003, AC-004).
   *
   * Returns the character_state_snapshot from the last ended session so the
   * frontend can restore inventory, HP, conditions, and XP on session resume.
   */
  public function getLatestState(int $campaign_id): JsonResponse {
    $access = $this->campaignAccessCheck->access($this->currentUser, $campaign_id);
    if (!$access->isAllowed()) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    $last = $this->sessionService->getLastEndedSession($campaign_id);
    if (!$last) {
      return new JsonResponse([
        'success' => TRUE,
        'campaign_id' => $campaign_id,
        'has_prior_session' => FALSE,
        'character_state_snapshot' => [],
        'session_id' => NULL,
      ]);
    }

    return new JsonResponse([
      'success' => TRUE,
      'campaign_id' => $campaign_id,
      'has_prior_session' => TRUE,
      'character_state_snapshot' => $last['character_state_snapshot'],
      'session_id' => $last['id'],
      'ended_at' => $last['ended_at'],
    ]);
  }

  // ──────────────────────────────────────────────────────────────────────────
  // GET /api/campaign/{campaign_id}/play-sessions/ai-context
  // ──────────────────────────────────────────────────────────────────────────

  /**
   * Get AI GM context from prior sessions (AC-005).
   *
   * Returns the narrative summary from the last session and the NPC
   * relationship log so the AI GM can inject continuity context.
   */
  public function getAiGmContext(int $campaign_id): JsonResponse {
    $access = $this->campaignAccessCheck->access($this->currentUser, $campaign_id);
    if (!$access->isAllowed()) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    $context = $this->sessionService->buildAiGmContext($campaign_id);
    return new JsonResponse([
      'success' => TRUE,
      'campaign_id' => $campaign_id,
      'prior_session_summary' => $context['prior_session_summary'],
      'npcs' => $context['npcs'],
    ]);
  }

  // ──────────────────────────────────────────────────────────────────────────
  // GET /api/campaign/{campaign_id}/xp-total/{character_id}
  // ──────────────────────────────────────────────────────────────────────────

  /**
   * Get cumulative XP for a character across all campaign sessions (AC-002).
   */
  public function getCampaignCharacterXp(int $campaign_id, int $character_id): JsonResponse {
    $access = $this->campaignAccessCheck->access($this->currentUser, $campaign_id);
    if (!$access->isAllowed()) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    $total_xp = $this->sessionService->getCampaignCharacterXp($campaign_id, $character_id);
    return new JsonResponse([
      'success' => TRUE,
      'campaign_id' => $campaign_id,
      'character_id' => $character_id,
      'total_xp' => $total_xp,
    ]);
  }

  // ──────────────────────────────────────────────────────────────────────────
  // POST /api/campaign/{campaign_id}/invite
  // ──────────────────────────────────────────────────────────────────────────

  /**
   * Invite a player to a campaign (Security AC: invitee must be registered).
   *
   * Body (JSON):
   *   email: string  — email address of the user to invite
   *
   * Validates the email corresponds to an active registered Drupal user.
   * On success, adds the user to dc_campaign_characters (pending membership).
   */
  public function invitePlayer(int $campaign_id, Request $request): JsonResponse {
    $access = $this->campaignAccessCheck->access($this->currentUser, $campaign_id);
    if (!$access->isAllowed()) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    $data = json_decode($request->getContent(), TRUE);
    $email = trim($data['email'] ?? '');
    if ($email === '') {
      return new JsonResponse(['success' => FALSE, 'error' => 'email is required'], 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Invalid email format'], 400);
    }

    // Validate the invited user is a registered account (Security AC).
    $user_storage = \Drupal::entityTypeManager()->getStorage('user');
    $uids = $user_storage->getQuery()
      ->condition('mail', $email)
      ->condition('status', 1)
      ->accessCheck(FALSE)
      ->execute();

    if (empty($uids)) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'No registered account found for that email address',
      ], 422);
    }

    $invited_uid = (int) reset($uids);

    // Check if already a member.
    $existing = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('uid', $invited_uid)
      ->execute()
      ->fetchField();

    if ($existing) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'User is already a member of this campaign',
      ], 409);
    }

    // Add as a pending member. Character will be created on first session join.
    $this->database->insert('dc_campaign_characters')
      ->fields([
        'campaign_id' => $campaign_id,
        'uid' => $invited_uid,
        'character_id' => 0,
        'role' => 'player',
        'is_active' => 0,
        'joined' => time(),
        'instance_id' => '',
        'type' => 'pc',
        'location_type' => '',
        'location_ref' => '',
        'name' => '',
        'level' => 1,
        'ancestry' => '',
        'class' => '',
        'status' => 0,
        'version' => 0,
        'hp_current' => 0,
        'hp_max' => 0,
        'armor_class' => 0,
        'experience_points' => 0,
        'position_q' => 0,
        'position_r' => 0,
        'last_room_id' => '',
        'character_data' => json_encode(['invite_status' => 'pending']),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    return new JsonResponse([
      'success' => TRUE,
      'message' => 'Invitation sent',
      'invited_uid' => $invited_uid,
    ], 201);
  }

  // ──────────────────────────────────────────────────────────────────────────
  // Helpers
  // ──────────────────────────────────────────────────────────────────────────

  /**
   * Check if the current user can access a session.
   *
   * Allowed: GM of the session, or listed as a player, or campaign member.
   */
  private function canAccessSession(array $session): bool {
    $uid = (int) $this->currentUser->id();
    if ($uid === $session['gm_uid']) {
      return TRUE;
    }
    if (in_array($uid, $session['player_uids'], TRUE)) {
      return TRUE;
    }
    if ($session['campaign_id'] !== NULL) {
      $access = $this->campaignAccessCheck->access($this->currentUser, $session['campaign_id']);
      return $access->isAllowed();
    }
    return FALSE;
  }

}
