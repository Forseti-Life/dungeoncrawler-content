<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\FamiliarService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * API endpoints for the PF2e Familiar system.
 *
 * Routes:
 *   GET  /api/character/{id}/familiar                — get familiar record
 *   POST /api/character/{id}/familiar                — create/reset familiar
 *   GET  /api/character/{id}/familiar/abilities      — list selectable abilities
 *   POST /api/character/{id}/familiar/daily-abilities — select today's abilities
 *   POST /api/character/{id}/familiar/damage         — apply damage to familiar
 *   POST /api/character/{id}/familiar/replace        — start/complete replacement ritual
 *   POST /api/character/{id}/familiar/touch-spell    — deliver a touch spell via familiar
 *   POST /api/character/{id}/familiar/witch-spells   — store witch prepared spells in familiar
 */
class FamiliarController extends ControllerBase {

  protected FamiliarService $familiarService;
  protected Connection $database;

  public function __construct(FamiliarService $familiar_service, Connection $database) {
    $this->familiarService = $familiar_service;
    $this->database        = $database;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_content.familiar'),
      $container->get('database'),
    );
  }

  // ── GET /api/character/{id}/familiar ─────────────────────────────────────

  public function getFamiliar(string $character_id): JsonResponse {
    if (!$this->hasCharacterAccess($character_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }
    try {
      $result = $this->familiarService->getFamiliar($character_id);
      $code   = $result['code'] ?? 200;
      return new JsonResponse($result, $result['success'] ? 200 : $code);
    }
    catch (\InvalidArgumentException $e) {
      return $this->errorResponse($e);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Internal server error'], 500);
    }
  }

  // ── POST /api/character/{id}/familiar ────────────────────────────────────

  /**
   * Create or reset a familiar.
   *
   * Request body (JSON, all optional):
   *   familiar_type   string  — defaults to 'standard'
   *   has_wings       bool    — prerequisite for Flier ability
   *   is_witch_required bool  — override; auto-set to TRUE for witch class
   */
  public function createFamiliar(string $character_id, Request $request): JsonResponse {
    if (!$this->hasCharacterAccess($character_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }
    try {
      $params = json_decode($request->getContent(), TRUE) ?? [];
      return new JsonResponse($this->familiarService->createFamiliar($character_id, $params));
    }
    catch (\InvalidArgumentException $e) {
      return $this->errorResponse($e);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Internal server error'], 500);
    }
  }

  // ── GET /api/character/{id}/familiar/abilities ───────────────────────────

  public function getAvailableAbilities(string $character_id): JsonResponse {
    if (!$this->hasCharacterAccess($character_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }
    try {
      $result = $this->familiarService->getAvailableAbilities($character_id);
      return new JsonResponse($result, $result['success'] ? 200 : ($result['code'] ?? 400));
    }
    catch (\InvalidArgumentException $e) {
      return $this->errorResponse($e);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Internal server error'], 500);
    }
  }

  // ── POST /api/character/{id}/familiar/daily-abilities ────────────────────

  /**
   * Select today's familiar abilities.
   *
   * Request body (JSON): {"abilities": ["darkvision", "climber"]}
   */
  public function selectDailyAbilities(string $character_id, Request $request): JsonResponse {
    if (!$this->hasCharacterAccess($character_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    $data       = json_decode($request->getContent(), TRUE);
    $ability_ids = $data['abilities'] ?? NULL;
    if (!is_array($ability_ids)) {
      return new JsonResponse([
        'success' => FALSE,
        'error'   => 'Missing required field: abilities (array of ability IDs)',
      ], 400);
    }

    try {
      $result = $this->familiarService->selectDailyAbilities($character_id, $ability_ids);
      $code   = $result['code'] ?? 200;
      return new JsonResponse($result, $result['success'] ? 200 : $code);
    }
    catch (\InvalidArgumentException $e) {
      return $this->errorResponse($e);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Internal server error'], 500);
    }
  }

  // ── POST /api/character/{id}/familiar/damage ─────────────────────────────

  /**
   * Apply damage to the familiar.
   *
   * Request body (JSON): {"damage": 10}
   */
  public function applyDamage(string $character_id, Request $request): JsonResponse {
    if (!$this->hasCharacterAccess($character_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    $data   = json_decode($request->getContent(), TRUE);
    $damage = $data['damage'] ?? NULL;
    if (!is_int($damage) || $damage < 0) {
      return new JsonResponse([
        'success' => FALSE,
        'error'   => 'Missing or invalid field: damage (non-negative integer required)',
      ], 400);
    }

    try {
      $result = $this->familiarService->applyDamage($character_id, $damage);
      $code   = $result['code'] ?? 200;
      return new JsonResponse($result, $result['success'] ? 200 : $code);
    }
    catch (\InvalidArgumentException $e) {
      return $this->errorResponse($e);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Internal server error'], 500);
    }
  }

  // ── POST /api/character/{id}/familiar/replace ────────────────────────────

  /**
   * Begin or complete the familiar replacement ritual.
   *
   * No body required. Call after familiar death; call again when ritual completes.
   */
  public function replaceFamiliar(string $character_id): JsonResponse {
    if (!$this->hasCharacterAccess($character_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }
    try {
      $result = $this->familiarService->startReplacementRitual($character_id);
      $code   = $result['code'] ?? 200;
      return new JsonResponse($result, $result['success'] ? 200 : $code);
    }
    catch (\InvalidArgumentException $e) {
      return $this->errorResponse($e);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Internal server error'], 500);
    }
  }

  // ── POST /api/character/{id}/familiar/touch-spell ────────────────────────

  /**
   * Deliver a touch spell via the familiar.
   *
   * Request body (JSON): {"spell": {"id": "...", "range": "touch", ...}, "target_id": "..."}
   */
  public function deliverTouchSpell(string $character_id, Request $request): JsonResponse {
    if (!$this->hasCharacterAccess($character_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    $data      = json_decode($request->getContent(), TRUE);
    $spell     = $data['spell'] ?? NULL;
    $target_id = $data['target_id'] ?? NULL;

    if (!is_array($spell) || empty($target_id)) {
      return new JsonResponse([
        'success' => FALSE,
        'error'   => 'Missing required fields: spell (object) and target_id (string)',
      ], 400);
    }

    try {
      $result = $this->familiarService->deliverTouchSpell($character_id, $spell, $target_id);
      $code   = $result['code'] ?? 200;
      return new JsonResponse($result, $result['success'] ? 200 : $code);
    }
    catch (\InvalidArgumentException $e) {
      return $this->errorResponse($e);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Internal server error'], 500);
    }
  }

  // ── POST /api/character/{id}/familiar/witch-spells ───────────────────────

  /**
   * Store witch prepared spells in the familiar (patron's vessel).
   *
   * Request body (JSON): {"spells": [...spell definitions...]}
   */
  public function storeWitchSpells(string $character_id, Request $request): JsonResponse {
    if (!$this->hasCharacterAccess($character_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    $data   = json_decode($request->getContent(), TRUE);
    $spells = $data['spells'] ?? NULL;
    if (!is_array($spells)) {
      return new JsonResponse([
        'success' => FALSE,
        'error'   => 'Missing required field: spells (array)',
      ], 400);
    }

    try {
      $result = $this->familiarService->storeWitchSpells($character_id, $spells);
      $code   = $result['code'] ?? 200;
      return new JsonResponse($result, $result['success'] ? 200 : $code);
    }
    catch (\InvalidArgumentException $e) {
      return $this->errorResponse($e);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Internal server error'], 500);
    }
  }

  // ── Helpers ───────────────────────────────────────────────────────────────

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

  private function errorResponse(\InvalidArgumentException $e): JsonResponse {
    $code      = $e->getCode();
    $http_code = ($code >= 400 && $code < 500) ? $code : 400;
    return new JsonResponse(['success' => FALSE, 'error' => $e->getMessage()], $http_code);
  }

}
