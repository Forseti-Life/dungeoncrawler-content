<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\CharacterStateService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * API endpoints for the ancestry creature traits system.
 *
 * Provides:
 *   GET /dungeoncrawler/traits              — canonical trait catalog
 *   GET /api/character/{id}/traits          — character's current traits
 *   GET /api/character/{id}/traits/check    — hasTraits query (?traits[]=X)
 *
 * Trait assignment is server-side only (no write endpoints).
 */
class CharacterTraitsController extends ControllerBase {

  protected CharacterStateService $characterStateService;
  protected Connection $database;

  public function __construct(CharacterStateService $character_state_service, Connection $database) {
    $this->characterStateService = $character_state_service;
    $this->database = $database;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_content.character_state_service'),
      $container->get('database'),
    );
  }

  /**
   * Returns the canonical trait catalog.
   *
   * GET /dungeoncrawler/traits
   */
  public function catalog(): JsonResponse {
    $catalog = CharacterManager::TRAIT_CATALOG;
    sort($catalog);
    return new JsonResponse([
      'success' => TRUE,
      'traits' => $catalog,
      'count' => count($catalog),
    ]);
  }

  /**
   * Returns a character's current creature traits.
   *
   * GET /api/character/{character_id}/traits
   *
   * @param string $character_id
   *   The character ID.
   */
  public function getTraits(string $character_id): JsonResponse {
    if (!$this->hasCharacterAccess($character_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    try {
      $state = $this->characterStateService->getState($character_id);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['success' => FALSE, 'error' => "Character not found: {$character_id}"], 404);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Internal server error'], 500);
    }

    $traits = $state['traits'] ?? [];
    return new JsonResponse([
      'success' => TRUE,
      'characterId' => $character_id,
      'traits' => $traits,
      'count' => count($traits),
    ]);
  }

  /**
   * Checks whether a character has all specified traits (hasTraits).
   *
   * GET /api/character/{character_id}/traits/check?traits[]=Humanoid&traits[]=Dwarf
   *
   * Returns: { "success": true, "result": true|false, "checked": [...], "traits": [...] }
   *
   * @param string $character_id
   *   The character ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request (expects ?traits[] query param).
   */
  public function checkTraits(string $character_id, Request $request): JsonResponse {
    if (!$this->hasCharacterAccess($character_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    $requested = $request->query->all('traits');
    if (!is_array($requested) || empty($requested)) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Missing required query parameter: traits[] (e.g. ?traits[]=Humanoid)',
      ], 400);
    }

    // Validate each requested trait against the catalog (case-sensitive).
    $unknown = [];
    foreach ($requested as $t) {
      if (!CharacterManager::isValidTrait($t)) {
        $unknown[] = $t;
      }
    }
    if (!empty($unknown)) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Unknown trait: ' . implode(', ', $unknown),
        'unknownTraits' => $unknown,
      ], 400);
    }

    try {
      $state = $this->characterStateService->getState($character_id);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['success' => FALSE, 'error' => "Character not found: {$character_id}"], 404);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Internal server error'], 500);
    }

    $character_traits = $state['traits'] ?? [];
    $result = CharacterManager::hasTraits($character_traits, $requested);

    return new JsonResponse([
      'success' => TRUE,
      'characterId' => $character_id,
      'result' => $result,
      'checked' => $requested,
      'traits' => $character_traits,
    ]);
  }

  /**
   * Checks whether the current user has access to the given character.
   *
   * Mirrors the access logic in CharacterStateController.
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
    if (!$record) {
      return FALSE;
    }
    return (string) $record->uid === (string) $account->id();
  }

}
