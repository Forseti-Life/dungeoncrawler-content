<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * API endpoints for character management.
 */
class CharacterApiController extends ControllerBase {

  protected CharacterManager $characterManager;
  protected CsrfTokenGenerator $csrfToken;

  public function __construct(CharacterManager $character_manager, CsrfTokenGenerator $csrf_token) {
    $this->characterManager = $character_manager;
    $this->csrfToken = $csrf_token;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.character_manager'),
      $container->get('csrf_token'),
    );
  }

  /**
   * Save character progress during wizard creation.
   * 
   * POST /api/character/save
   * 
   * Accepts JSON body:
   * {
   *   "character_id": 123 (optional, for updates),
   *   "step": 2,
   *   "name": "Grok",
   *   "ancestry": "dwarf",
   *   "heritage": "forge",
   *   "background": "miner",
   *   "class": "fighter",
   *   "abilities": {...},
   *   "alignment": "LG",
   *   "inventory": {...},
   *   ...
   * }
   * 
   * Requires X-CSRF-Token header for security.
   */
  public function saveCharacter(Request $request): JsonResponse {
    // Ensure user is logged in
    if (!$this->currentUser()->isAuthenticated()) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Authentication required',
      ], 401);
    }

    // Validate CSRF token
    $token = $request->headers->get('X-CSRF-Token');
    if (!$token || !$this->csrfToken->validate($token, 'rest')) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Invalid or missing CSRF token',
      ], 403);
    }

    // Parse JSON body
    $data = json_decode($request->getContent(), TRUE);
    if (json_last_error() !== JSON_ERROR_NONE) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Invalid JSON',
      ], 400);
    }

    try {
      $character_id = $data['character_id'] ?? NULL;

      // Validate ancestry ID if provided.
      $ancestry_val = $data['ancestry'] ?? NULL;
      if ($ancestry_val !== NULL && $ancestry_val !== '') {
        $canonical = \Drupal\dungeoncrawler_content\Service\CharacterManager::resolveAncestryCanonicalName($ancestry_val);
        if ($canonical === '') {
          return new JsonResponse([
            'success' => FALSE,
            'error' => 'Invalid ancestry: ' . $ancestry_val,
          ], 400);
        }
      }

      // Validate heritage matches ancestry if both are provided.
      $heritage_val = $data['heritage'] ?? NULL;
      if ($heritage_val !== NULL && $heritage_val !== '' && $ancestry_val !== NULL && $ancestry_val !== '') {
        $canonical_for_heritage = \Drupal\dungeoncrawler_content\Service\CharacterManager::resolveAncestryCanonicalName($ancestry_val);
        if ($canonical_for_heritage !== '' && !\Drupal\dungeoncrawler_content\Service\CharacterManager::isValidHeritageForAncestry($canonical_for_heritage, $heritage_val)) {
          return new JsonResponse([
            'success' => FALSE,
            'error' => 'Invalid heritage for selected ancestry.',
          ], 400);
        }
      }

      // Validate class ID if provided.
      $class_val = $data['class'] ?? NULL;
      if ($class_val !== NULL && $class_val !== '') {
        if (!isset(\Drupal\dungeoncrawler_content\Service\CharacterManager::CLASSES[$class_val])) {
          return new JsonResponse([
            'success' => FALSE,
            'error' => 'Invalid class: ' . $class_val,
          ], 400);
        }
      }

      // Prepare character data for storage
      $character_data = [
        'step' => $data['step'] ?? 1,
        'name' => $data['name'] ?? '',
        'concept' => $data['concept'] ?? '',
        'ancestry' => $data['ancestry'] ?? NULL,
        'heritage' => $data['heritage'] ?? NULL,
        'background' => $data['background'] ?? NULL,
        'class' => $data['class'] ?? NULL,
        'abilities' => $data['abilities'] ?? [
          'str' => 10, 'dex' => 10, 'con' => 10,
          'int' => 10, 'wis' => 10, 'cha' => 10,
        ],
        'alignment' => $data['alignment'] ?? '',
        'deity' => $data['deity'] ?? '',
        'age' => $data['age'] ?? NULL,
        'gender' => $data['gender'] ?? '',
        'appearance' => $data['appearance'] ?? '',
        'personality' => $data['personality'] ?? '',
        'roleplay_style' => $data['roleplay_style'] ?? 'balanced',
        'backstory' => $data['backstory'] ?? '',
        'inventory' => $data['inventory'] ?? [],
        'gold' => $data['gold'] ?? 15,
        'wizard_complete' => $data['wizard_complete'] ?? FALSE,
      ];

      // Update existing character or create new one
      if ($character_id) {
        // Update existing draft
        $existing = $this->characterManager->loadCharacter($character_id);
        if (!$existing || $existing->uid != $this->currentUser()->id()) {
          return new JsonResponse([
            'success' => FALSE,
            'error' => 'Character not found or access denied',
          ], 403);
        }

        $fields = [
          'name' => $character_data['name'] ?: 'Unnamed Character',
          'ancestry' => $character_data['ancestry'] ?? $existing->ancestry,
          'class' => $character_data['class'] ?? $existing->class,
          'level' => (int) ($character_data['level'] ?? $existing->level ?? 1),
          'hp_current' => (int) ($character_data['hit_points']['current'] ?? $existing->hp_current ?? 0),
          'hp_max' => (int) ($character_data['hit_points']['max'] ?? $existing->hp_max ?? 0),
          'armor_class' => (int) ($character_data['armor_class'] ?? $existing->armor_class ?? 10),
          'experience_points' => (int) ($character_data['experience_points'] ?? $existing->experience_points ?? 0),
          'position_q' => (int) ($character_data['position']['q'] ?? $existing->position_q ?? 0),
          'position_r' => (int) ($character_data['position']['r'] ?? $existing->position_r ?? 0),
          'last_room_id' => (string) ($character_data['position']['room_id'] ?? $existing->last_room_id ?? ''),
          'character_data' => json_encode($character_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ];

        // Mark as complete if wizard is finished
        if ($character_data['wizard_complete']) {
          $fields['status'] = 1;
        }

        $this->characterManager->updateCharacter($character_id, $fields);
        
        return new JsonResponse([
          'success' => TRUE,
          'character_id' => $character_id,
          'action' => 'updated',
          'step' => $character_data['step'],
        ]);
      }
      else {
        // Create new draft character
        $now = \Drupal::time()->getRequestTime();
        $db = \Drupal::database();
        $instance_id = \Drupal::service('uuid')->generate();
        
        $character_id = $db->insert('dc_campaign_characters')
          ->fields([
            'uuid' => $instance_id,
            'campaign_id' => 0,
            'character_id' => 0,
            'instance_id' => $instance_id,
            'uid' => (int) $this->currentUser()->id(),
            'name' => $character_data['name'] ?: 'Unnamed Character',
            'level' => 1,
            'ancestry' => $character_data['ancestry'] ?? '',
            'class' => $character_data['class'] ?? '',
            'hp_current' => (int) ($character_data['hit_points']['current'] ?? 0),
            'hp_max' => (int) ($character_data['hit_points']['max'] ?? 0),
            'armor_class' => (int) ($character_data['armor_class'] ?? 10),
            'experience_points' => (int) ($character_data['experience_points'] ?? 0),
            'position_q' => (int) ($character_data['position']['q'] ?? 0),
            'position_r' => (int) ($character_data['position']['r'] ?? 0),
            'last_room_id' => (string) ($character_data['position']['room_id'] ?? ''),
            'character_data' => json_encode($character_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'status' => 0, // Draft status until wizard is complete
            'created' => $now,
            'changed' => $now,
          ])
          ->execute();

        return new JsonResponse([
          'success' => TRUE,
          'character_id' => $character_id,
          'action' => 'created',
          'step' => $character_data['step'],
        ]);
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('dungeoncrawler_content')->error('Character save error: @message', [
        '@message' => $e->getMessage(),
      ]);
      
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Failed to save character',
      ], 500);
    }
  }

  /**
   * Load character draft for editing.
   * 
   * GET /api/character/load/{character_id}
   */
  public function loadCharacter(int $character_id): JsonResponse {
    if (!$this->currentUser()->isAuthenticated()) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Authentication required',
      ], 401);
    }

    $character = $this->characterManager->loadCharacter($character_id);
    
    if (!$character) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Character not found',
      ], 404);
    }

    // Check ownership
    if ($character->uid != $this->currentUser()->id()) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Access denied',
      ], 403);
    }

    // Decode character data
    $character_data = json_decode($character->character_data, TRUE);

    return new JsonResponse([
      'success' => TRUE,
      'character' => [
        'id' => $character->id,
        'uuid' => $character->uuid,
        'name' => $character->name,
        'level' => $character->level,
        'ancestry' => $character->ancestry,
        'class' => $character->class,
        'status' => $character->status,
        'data' => $character_data,
      ],
    ]);
  }

  /**
   * GET /character/{character_id}/skills
   *
   * Returns the character's skill list with proficiency rank and current bonus.
   * Anonymous access allowed (character skill data readable in active game session).
   */
  public function getCharacterSkills(Request $request, int $character_id): JsonResponse {
    $character = $this->characterManager->loadCharacter($character_id);
    if (!$character) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Character not found'], 404);
    }

    $skills = $this->characterManager->getCharacterSkills($character_id);
    if (isset($skills['error'])) {
      return new JsonResponse(['success' => FALSE, 'error' => $skills['error']], 404);
    }

    return new JsonResponse([
      'success'      => TRUE,
      'character_id' => $character_id,
      'skills'       => $skills,
    ]);
  }

}
