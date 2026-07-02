<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\CharacterLevelingService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * API endpoints for character leveling and advancement (PF2e).
 *
 * Routes:
 *   GET  /api/character/{id}/level-up/status          — check milestone / pending choices
 *   POST /api/character/{id}/level-up                 — trigger level-up (existing route re-impl)
 *   POST /api/character/{id}/level-up/ability-boosts  — submit 4 ability boost choices
 *   POST /api/character/{id}/level-up/skill-increase  — raise one skill proficiency rank
 *   POST /api/character/{id}/level-up/feat            — select a feat for an open slot
 *   GET  /api/character/{id}/level-up/feats           — list eligible feats for a slot type
 *   POST /api/character/{id}/level-up/admin-force     — admin: bypass milestone
 *   POST /api/character/{id}/level-up/admin-reset     — admin: undo last level-up
 *   POST /api/character/{id}/milestone                — GM: set/clear milestone flag
 */
class CharacterLevelingController extends ControllerBase {

  protected CharacterLevelingService $levelingService;
  protected Connection $database;

  public function __construct(CharacterLevelingService $leveling_service, Connection $database) {
    $this->levelingService = $leveling_service;
    $this->database = $database;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_content.character_leveling'),
      $container->get('database'),
    );
  }

  // ── GET /api/character/{id}/level-up/status ──────────────────────────────

  /**
   * Get the level-up status for a character.
   */
  public function getStatus(string $character_id): JsonResponse {
    if (!$this->hasCharacterAccess($character_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }
    try {
      return new JsonResponse($this->levelingService->getStatus($character_id));
    }
    catch (\InvalidArgumentException $e) {
      return $this->errorResponse($e);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Internal server error'], 500);
    }
  }

  // ── POST /api/character/{id}/level-up ────────────────────────────────────

  /**
   * Trigger a level-up. Replaces the stub in CharacterStateController.
   *
   * Request body (JSON): none required; all validation is server-side.
   */
  public function triggerLevelUp(string $character_id, Request $request): JsonResponse {
    if (!$this->hasCharacterAccess($character_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }
    try {
      $result = $this->levelingService->triggerLevelUp($character_id);
      return new JsonResponse($result);
    }
    catch (\InvalidArgumentException $e) {
      return $this->errorResponse($e);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Internal server error'], 500);
    }
  }

  // ── POST /api/character/{id}/level-up/ability-boosts ─────────────────────

  /**
   * Submit ability boost choices.
   *
   * Request body (JSON): {"abilities": ["strength", "dexterity", "constitution", "wisdom"]}
   */
  public function submitAbilityBoosts(string $character_id, Request $request): JsonResponse {
    if (!$this->hasCharacterAccess($character_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    $data = json_decode($request->getContent(), TRUE);
    $abilities = $data['abilities'] ?? NULL;
    if (!is_array($abilities) || empty($abilities)) {
      return new JsonResponse([
        'success' => FALSE,
        'error'   => 'Missing required field: abilities (array of ability names)',
      ], 400);
    }

    try {
      $result = $this->levelingService->submitAbilityBoosts($character_id, $abilities);
      return new JsonResponse($result);
    }
    catch (\InvalidArgumentException $e) {
      return $this->errorResponse($e);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Internal server error'], 500);
    }
  }

  // ── POST /api/character/{id}/level-up/skill-increase ─────────────────────

  /**
   * Submit a skill increase choice.
   *
   * Request body (JSON): {"skill": "arcana"}
   */
  public function submitSkillIncrease(string $character_id, Request $request): JsonResponse {
    if (!$this->hasCharacterAccess($character_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    $data = json_decode($request->getContent(), TRUE);
    $skill = $data['skill'] ?? NULL;
    if (!is_string($skill) || $skill === '') {
      return new JsonResponse([
        'success' => FALSE,
        'error'   => 'Missing required field: skill (string)',
      ], 400);
    }

    try {
      $result = $this->levelingService->submitSkillIncrease($character_id, $skill);
      return new JsonResponse($result);
    }
    catch (\InvalidArgumentException $e) {
      return $this->errorResponse($e);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Internal server error'], 500);
    }
  }

  // ── POST /api/character/{id}/level-up/feat ────────────────────────────────

  /**
   * Submit a feat selection.
   *
   * Request body (JSON): {"slot_type": "class_feat", "feat_id": "power-attack"}
   */
  public function submitFeat(string $character_id, Request $request): JsonResponse {
    if (!$this->hasCharacterAccess($character_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    $data = json_decode($request->getContent(), TRUE);
    $slot_type = $data['slot_type'] ?? NULL;
    $feat_id   = $data['feat_id'] ?? NULL;

    if (!is_string($slot_type) || $slot_type === '' || !is_string($feat_id) || $feat_id === '') {
      return new JsonResponse([
        'success' => FALSE,
        'error'   => 'Missing required fields: slot_type (string), feat_id (string)',
      ], 400);
    }

    try {
      $result = $this->levelingService->submitFeat($character_id, $slot_type, $feat_id);
      return new JsonResponse($result);
    }
    catch (\InvalidArgumentException $e) {
      return $this->errorResponse($e);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Internal server error'], 500);
    }
  }

  // ── GET /api/character/{id}/level-up/feats ────────────────────────────────

  /**
   * List eligible feats for a given slot type.
   *
   * Query params: ?slot_type=class_feat
   */
  public function getEligibleFeats(string $character_id, Request $request): JsonResponse {
    if (!$this->hasCharacterAccess($character_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    $slot_type = $request->query->get('slot_type', 'class_feat');
    $valid_slots = ['class_feat', 'skill_feat', 'general_feat', 'ancestry_feat'];
    if (!in_array($slot_type, $valid_slots, TRUE)) {
      return new JsonResponse([
        'success' => FALSE,
        'error'   => "Invalid slot_type '{$slot_type}'. Valid: " . implode(', ', $valid_slots),
      ], 400);
    }

    try {
      $feats = $this->levelingService->getEligibleFeats($character_id, $slot_type);
      return new JsonResponse([
        'success'   => TRUE,
        'slot_type' => $slot_type,
        'feats'     => $feats,
        'count'     => count($feats),
      ]);
    }
    catch (\InvalidArgumentException $e) {
      return $this->errorResponse($e);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Internal server error'], 500);
    }
  }

  // ── POST /api/character/{id}/level-up/admin-force ─────────────────────────

  /**
   * Admin: force-apply a level-up, bypassing the milestone check.
   *
   * Requires: administer dungeoncrawler content permission.
   */
  public function adminForce(string $character_id): JsonResponse {
    if (!$this->hasAdminAccess()) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }
    try {
      $result = $this->levelingService->adminForceLevelUp($character_id);
      return new JsonResponse($result);
    }
    catch (\InvalidArgumentException $e) {
      return $this->errorResponse($e);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Internal server error'], 500);
    }
  }

  // ── POST /api/character/{id}/level-up/admin-reset ────────────────────────

  /**
   * Admin: reset the last level-up, reverting the character to the previous level.
   *
   * Requires: administer dungeoncrawler content permission.
   */
  public function adminReset(string $character_id): JsonResponse {
    if (!$this->hasAdminAccess()) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }
    try {
      $result = $this->levelingService->adminResetLevelUp($character_id);
      return new JsonResponse($result);
    }
    catch (\InvalidArgumentException $e) {
      return $this->errorResponse($e);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Internal server error'], 500);
    }
  }

  // ── POST /api/character/{id}/milestone ────────────────────────────────────

  /**
   * GM: set or clear the session milestone for a character.
   *
   * Requires: administer dungeoncrawler content permission (GM role).
   * Request body (JSON): {"ready": true}
   */
  public function setMilestone(string $character_id, Request $request): JsonResponse {
    if (!$this->hasAdminAccess()) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    $data = json_decode($request->getContent(), TRUE);
    if (!isset($data['ready']) || !is_bool($data['ready'])) {
      return new JsonResponse([
        'success' => FALSE,
        'error'   => 'Missing required field: ready (boolean)',
      ], 400);
    }

    try {
      $result = $this->levelingService->setMilestone($character_id, $data['ready']);
      return new JsonResponse($result);
    }
    catch (\InvalidArgumentException $e) {
      return $this->errorResponse($e);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Internal server error'], 500);
    }
  }

  // ── Access helpers ────────────────────────────────────────────────────────

  /**
   * Check if the current user has access to the given character.
   * Grants access if the user owns the character OR has admin permission.
   */
  protected function hasCharacterAccess(string $character_id): bool {
    $account = $this->currentUser();
    if ($account->hasPermission('administer dungeoncrawler content')) {
      return TRUE;
    }
    $record = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['uid'])
      ->condition('id', $character_id)
      ->execute()
      ->fetchObject();

    return $record && (string) $record->uid === (string) $account->id();
  }

  /**
   * Check if current user has admin/GM access.
   */
  protected function hasAdminAccess(): bool {
    return $this->currentUser()->hasPermission('administer dungeoncrawler content');
  }

  /**
   * Map InvalidArgumentException to the appropriate HTTP error response.
   *
   * The exception code is used as the HTTP status code when it is a valid
   * 4xx code; defaults to 400 otherwise.
   */
  private function errorResponse(\InvalidArgumentException $e): JsonResponse {
    $code = $e->getCode();
    $http_code = ($code >= 400 && $code < 500) ? $code : 400;
    return new JsonResponse(['success' => FALSE, 'error' => $e->getMessage()], $http_code);
  }

}
