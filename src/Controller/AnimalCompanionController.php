<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\AnimalCompanionService;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\FollowerSubsystemService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * API endpoints for the PF2e Animal Companion system.
 */
class AnimalCompanionController extends ControllerBase {

  protected AnimalCompanionService $animalCompanionService;
  protected CharacterManager $characterManager;
  protected FollowerSubsystemService $followerSubsystem;
  protected Connection $database;

  public function __construct(AnimalCompanionService $animal_companion_service, CharacterManager $character_manager, FollowerSubsystemService $follower_subsystem, Connection $database) {
    $this->animalCompanionService = $animal_companion_service;
    $this->characterManager = $character_manager;
    $this->followerSubsystem = $follower_subsystem;
    $this->database = $database;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_content.animal_companion'),
      $container->get('dungeoncrawler_content.character_manager'),
      $container->get('dungeoncrawler_content.follower_subsystem'),
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
      if (!empty($result['success'])) {
        $result['actor_record'] = $this->syncCompanionActorRecord($character_id);
      }
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
      if (!empty($result['success'])) {
        $result['actor_record'] = $this->syncCompanionActorRecord($character_id);
      }
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

  /**
   * Recompute and persist canonical animal companion actor record.
   */
  private function syncCompanionActorRecord(string $character_id): array {
    $record = $this->characterManager->loadCharacter((int) $character_id);
    if (!$record) {
      throw new \RuntimeException('Character not found while syncing animal companion actor record.');
    }
    $decoded = $this->characterManager->getCharacterData($record);
    $canonical = $this->characterManager->canonicalizeCharacterData($decoded);
    $canonical['character_id'] = (int) $character_id;
    $actor_record = $this->followerSubsystem->resolveFollowerActorRecord(
      $canonical,
      (string) $character_id,
      FollowerSubsystemService::FOLLOWER_KIND_ANIMAL_COMPANION
    );
    $persistable = $this->followerSubsystem->persistActorRecordOnCharacterData(
      $decoded,
      FollowerSubsystemService::FOLLOWER_KIND_ANIMAL_COMPANION,
      $actor_record
    );
    $saved = $this->characterManager->updateCharacter((int) $character_id, [
      'character_data' => json_encode($persistable, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
    ]);
    if (!$saved) {
      throw new \RuntimeException('Failed to persist animal companion actor record.');
    }
    return $actor_record;
  }

}
