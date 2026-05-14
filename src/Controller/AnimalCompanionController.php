<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\AnimalCompanionService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * API endpoints for the PF2e Animal Companion system.
 */
class AnimalCompanionController extends ControllerBase {

  protected AnimalCompanionService $animalCompanionService;
  protected Connection $database;

  public function __construct(AnimalCompanionService $animal_companion_service, Connection $database) {
    $this->animalCompanionService = $animal_companion_service;
    $this->database = $database;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_content.animal_companion'),
      $container->get('database'),
    );
  }

  /**
   * GET /api/character/{character_id}/animal-companion
   */
  public function getCompanion(string $character_id): JsonResponse {
    if (!$this->hasCharacterAccess($character_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    try {
      $result = $this->animalCompanionService->getCompanion($character_id);
      return new JsonResponse($result, $result['success'] ? 200 : ($result['code'] ?? 400));
    }
    catch (\InvalidArgumentException $e) {
      return $this->errorResponse($e);
    }
    catch (\Exception) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Internal server error'], 500);
    }
  }

  /**
   * GET /api/character/{character_id}/animal-companion/catalog
   */
  public function getCatalog(string $character_id): JsonResponse {
    if (!$this->hasCharacterAccess($character_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    return new JsonResponse([
      'success' => TRUE,
      'species' => $this->animalCompanionService->getSpeciesCatalog(),
      'specializations' => $this->animalCompanionService->getAvailableSpecializations($character_id),
    ]);
  }

  /**
   * POST /api/character/{character_id}/animal-companion
   */
  public function createCompanion(string $character_id, Request $request): JsonResponse {
    if (!$this->hasCharacterAccess($character_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    try {
      $params = json_decode($request->getContent(), TRUE) ?? [];
      $result = $this->animalCompanionService->createCompanion($character_id, $params);
      return new JsonResponse($result, $result['success'] ? 200 : ($result['code'] ?? 400));
    }
    catch (\InvalidArgumentException $e) {
      return $this->errorResponse($e);
    }
    catch (\Exception) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Internal server error'], 500);
    }
  }

  /**
   * POST /api/character/{character_id}/animal-companion/specialization
   */
  public function selectSpecialization(string $character_id, Request $request): JsonResponse {
    if (!$this->hasCharacterAccess($character_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    $data = json_decode($request->getContent(), TRUE) ?? [];
    $specialization = trim((string) ($data['specialization'] ?? $data['selected_specialization'] ?? ''));
    if ($specialization === '') {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Missing required field: specialization',
      ], 400);
    }

    try {
      $result = $this->animalCompanionService->selectSpecialization($character_id, $specialization);
      return new JsonResponse($result, $result['success'] ? 200 : ($result['code'] ?? 400));
    }
    catch (\InvalidArgumentException $e) {
      return $this->errorResponse($e);
    }
    catch (\Exception) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Internal server error'], 500);
    }
  }

  /**
   * Determine whether the current user can access the character.
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
   * Build a normalized invalid-argument response.
   */
  private function errorResponse(\InvalidArgumentException $e): JsonResponse {
    $code = $e->getCode();
    $http_code = ($code >= 400 && $code < 500) ? $code : 400;
    return new JsonResponse(['success' => FALSE, 'error' => $e->getMessage()], $http_code);
  }

}
