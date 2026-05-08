<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;
use Drupal\dungeoncrawler_content\Access\CampaignAccessCheck;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * API endpoints for campaign entity lifecycle (spawn/move/despawn).
 */
class CampaignEntityController extends ControllerBase {

  private Connection $database;
  private CampaignAccessCheck $campaignAccessCheck;
  protected $currentUser;

  public function __construct(
    Connection $database,
    CampaignAccessCheck $campaign_access_check,
    AccountInterface $current_user
  ) {
    $this->database = $database;
    $this->campaignAccessCheck = $campaign_access_check;
    $this->currentUser = $current_user;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('dungeoncrawler_content.campaign_access_check'),
      $container->get('current_user')
    );
  }

  /**
   * POST /api/campaign/{campaignId}/entity/spawn
   * 
   * Body: {
   *   "type": "npc|obstacle|trap|hazard|pc",
   *   "instanceId": "unique-instance-id",
   *   "characterId": 123 (optional, for pc/npc),
   *   "locationType": "room|dungeon|tavern",
   *   "locationRef": "room-id-123",
   *   "stateData": { ... entity-specific state }
   * }
   * 
   * Hot columns (hp_current, hp_max, armor_class, experience_points, 
   * position_q, position_r, last_room_id) are extracted from stateData
   * for query optimization per hybrid columnar storage pattern.
   */
  public function spawnEntity(int $campaign_id, Request $request): JsonResponse {
    // Check campaign access.
    $access = $this->campaignAccessCheck->access($this->currentUser, $campaign_id);
    if (!$access->isAllowed()) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Access denied to campaign',
      ], 403);
    }

    $data = json_decode($request->getContent(), TRUE);
    if (!is_array($data)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Invalid JSON'], 400);
    }

    // Validate required fields.
    if (empty($data['type'])) {
      return new JsonResponse(['success' => FALSE, 'error' => 'type is required'], 400);
    }
    if (empty($data['instanceId'])) {
      return new JsonResponse(['success' => FALSE, 'error' => 'instanceId is required'], 400);
    }
    if (empty($data['locationType'])) {
      return new JsonResponse(['success' => FALSE, 'error' => 'locationType is required'], 400);
    }
    if (empty($data['locationRef'])) {
      return new JsonResponse(['success' => FALSE, 'error' => 'locationRef is required'], 400);
    }

    $type = $data['type'];
    $allowed_types = ['npc', 'obstacle', 'trap', 'hazard', 'pc'];
    if (!in_array($type, $allowed_types, TRUE)) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Invalid type. Allowed: ' . implode(', ', $allowed_types),
      ], 400);
    }

    $instance_id = $data['instanceId'];
    $character_id = isset($data['characterId']) ? (int) $data['characterId'] : NULL;
    $location_type = $data['locationType'];
    $location_ref = $data['locationRef'];
    $state_data = $data['stateData'] ?? [];

    // Check if instance already exists.
    $existing = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('instance_id', $instance_id)
      ->execute()
      ->fetchField();

    if ($existing) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Entity with this instanceId already exists',
      ], 400);
    }

    // Insert entity.
    try {
      // Extract hot columns from state data for hybrid columnar storage.
      $hot_columns = $this->extractHotColumnsFromStateData($state_data, $location_ref);
      
      $id = $this->database->insert('dc_campaign_characters')
        ->fields([
          'campaign_id' => $campaign_id,
          'character_id' => $character_id ?? 0,
          'instance_id' => $instance_id,
          'type' => $type,
          'location_type' => $location_type,
          'location_ref' => $location_ref,
          'state_data' => json_encode($state_data, JSON_UNESCAPED_UNICODE),
          'hp_current' => $hot_columns['hp_current'],
          'hp_max' => $hot_columns['hp_max'],
          'armor_class' => $hot_columns['armor_class'],
          'experience_points' => $hot_columns['experience_points'],
          'position_q' => $hot_columns['position_q'],
          'position_r' => $hot_columns['position_r'],
          'last_room_id' => $hot_columns['last_room_id'],
          'created' => time(),
          'updated' => time(),
        ])
        ->execute();

      return new JsonResponse([
        'success' => TRUE,
        'data' => [
          'id' => (int) $id,
          'campaignId' => $campaign_id,
          'type' => $type,
          'instanceId' => $instance_id,
          'characterId' => $character_id,
          'locationType' => $location_type,
          'locationRef' => $location_ref,
          'stateData' => $state_data,
        ],
      ], 201);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Failed to spawn entity: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * POST /api/campaign/{campaignId}/entity/{instanceId}/move
   * 
   * Body: {
   *   "locationType": "room|dungeon|tavern",
   *   "locationRef": "room-id-456",
   *   "stateData": { ... optional updated state including position }
   * }
   * 
   * Updates location and position hot columns (position_q, position_r, last_room_id)
   * if position data is provided in stateData.
   */
  public function moveEntity(int $campaign_id, string $instance_id, Request $request): JsonResponse {
    // Check campaign access.
    $access = $this->campaignAccessCheck->access($this->currentUser, $campaign_id);
    if (!$access->isAllowed()) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Access denied to campaign',
      ], 403);
    }

    $data = json_decode($request->getContent(), TRUE);
    if (!is_array($data)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Invalid JSON'], 400);
    }

    // Validate required fields.
    if (empty($data['locationType'])) {
      return new JsonResponse(['success' => FALSE, 'error' => 'locationType is required'], 400);
    }
    if (empty($data['locationRef'])) {
      return new JsonResponse(['success' => FALSE, 'error' => 'locationRef is required'], 400);
    }

    $location_type = $data['locationType'];
    $location_ref = $data['locationRef'];
    $new_state_data = $data['stateData'] ?? NULL;

    // Check if entity exists.
    $entity = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c')
      ->condition('campaign_id', $campaign_id)
      ->condition('instance_id', $instance_id)
      ->execute()
      ->fetchAssoc();

    if (!$entity) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Entity not found',
      ], 404);
    }

    // Update location and hot columns.
    try {
      $update_fields = [
        'location_type' => $location_type,
        'location_ref' => $location_ref,
        'updated' => time(),
      ];
      
      // If stateData is provided, merge with existing and update hot columns.
      if ($new_state_data !== NULL && is_array($new_state_data)) {
        $existing_state = json_decode($entity['state_data'] ?? '{}', TRUE);
        if (!is_array($existing_state)) {
          $existing_state = [];
        }
        
        // Merge new state with existing state.
        $merged_state = array_merge($existing_state, $new_state_data);
        $update_fields['state_data'] = json_encode($merged_state, JSON_UNESCAPED_UNICODE);
        
        // Extract and update position hot columns if position data is provided.
        $hot_columns = $this->extractHotColumnsFromStateData($new_state_data, $location_ref);
        
        // Only update position columns if new position data was provided.
        if ($hot_columns['position_q'] !== 0 || $hot_columns['position_r'] !== 0) {
          $update_fields['position_q'] = $hot_columns['position_q'];
          $update_fields['position_r'] = $hot_columns['position_r'];
        }
        
        // Update last_room_id if a room location is provided.
        if (!empty($hot_columns['last_room_id'])) {
          $update_fields['last_room_id'] = $hot_columns['last_room_id'];
        }
        
        // Update other hot columns if provided.
        if ($hot_columns['hp_current'] !== 0 || $hot_columns['hp_max'] !== 0) {
          $update_fields['hp_current'] = $hot_columns['hp_current'];
          $update_fields['hp_max'] = $hot_columns['hp_max'];
        }
        if ($hot_columns['armor_class'] !== 10) {
          $update_fields['armor_class'] = $hot_columns['armor_class'];
        }
        if ($hot_columns['experience_points'] !== 0) {
          $update_fields['experience_points'] = $hot_columns['experience_points'];
        }
      }
      
      $this->database->update('dc_campaign_characters')
        ->fields($update_fields)
        ->condition('campaign_id', $campaign_id)
        ->condition('instance_id', $instance_id)
        ->execute();

      // Return updated entity data.
      $state_data = json_decode($update_fields['state_data'] ?? $entity['state_data'] ?? '{}', TRUE);
      if (!is_array($state_data)) {
        $state_data = [];
      }

      return new JsonResponse([
        'success' => TRUE,
        'data' => [
          'id' => (int) $entity['id'],
          'campaignId' => $campaign_id,
          'type' => $entity['type'],
          'instanceId' => $instance_id,
          'characterId' => (int) $entity['character_id'],
          'locationType' => $location_type,
          'locationRef' => $location_ref,
          'stateData' => $state_data,
        ],
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Failed to move entity: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * DELETE /api/campaign/{campaignId}/entity/{instanceId}
   */
  public function despawnEntity(int $campaign_id, string $instance_id): JsonResponse {
    // Check campaign access.
    $access = $this->campaignAccessCheck->access($this->currentUser, $campaign_id);
    if (!$access->isAllowed()) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Access denied to campaign',
      ], 403);
    }

    // Check if entity exists.
    $entity = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('instance_id', $instance_id)
      ->execute()
      ->fetchAssoc();

    if (!$entity) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Entity not found',
      ], 404);
    }

    // Delete entity.
    try {
      $this->database->delete('dc_campaign_characters')
        ->condition('campaign_id', $campaign_id)
        ->condition('instance_id', $instance_id)
        ->execute();

      return new JsonResponse([
        'success' => TRUE,
        'message' => 'Entity despawned successfully',
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Failed to despawn entity: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Extract hot columns from entity state data for hybrid columnar storage.
   * 
   * Implements the pattern documented in SCHEMA_MAPPING.md:
   * - Hot columns enable fast indexed queries on runtime entity state
   * - Values extracted from stateData JSON payload
   * - Supports both legacy (hp, maxHp) and schema (hit_points.current/max) formats
   * 
   * @param array $state_data
   *   Entity state data from spawn/update request.
   * @param string $location_ref
   *   Location reference for last_room_id extraction.
   * 
   * @return array
   *   Hot column values: hp_current, hp_max, armor_class, experience_points,
   *   position_q, position_r, last_room_id.
   */
  private function extractHotColumnsFromStateData(array $state_data, string $location_ref = ''): array {
    // Extract hit points (support both formats).
    $hp_current = 0;
    $hp_max = 0;
    
    if (isset($state_data['hit_points']) && is_array($state_data['hit_points'])) {
      // entity_instance.schema.json format: {hit_points: {current, max}}
      $hp_current = (int) ($state_data['hit_points']['current'] ?? 0);
      $hp_max = (int) ($state_data['hit_points']['max'] ?? 0);
    }
    elseif (isset($state_data['hp']) || isset($state_data['maxHp'])) {
      // Legacy API format: {hp, maxHp}
      $hp_max = (int) ($state_data['maxHp'] ?? 0);
      $hp_current = (int) ($state_data['hp'] ?? $hp_max);
    }
    
    // Extract armor class.
    $armor_class = (int) ($state_data['armor_class'] ?? $state_data['ac'] ?? 10);
    
    // Extract experience points.
    $experience_points = (int) ($state_data['experience_points'] ?? $state_data['xp'] ?? 0);
    
    // Extract position from placement or hex coordinates.
    $position_q = 0;
    $position_r = 0;
    
    if (isset($state_data['placement']) && is_array($state_data['placement'])) {
      // entity_instance.schema.json format: {placement: {hex: {q, r}}}
      if (isset($state_data['placement']['hex']) && is_array($state_data['placement']['hex'])) {
        $position_q = (int) ($state_data['placement']['hex']['q'] ?? 0);
        $position_r = (int) ($state_data['placement']['hex']['r'] ?? 0);
      }
    }
    elseif (isset($state_data['q']) || isset($state_data['r'])) {
      // Direct q/r coordinates.
      $position_q = (int) ($state_data['q'] ?? 0);
      $position_r = (int) ($state_data['r'] ?? 0);
    }
    
    // Extract last room ID from placement or use location_ref if it's a room.
    $last_room_id = '';
    if (isset($state_data['placement']['room_id'])) {
      $last_room_id = (string) $state_data['placement']['room_id'];
    }
    elseif (isset($state_data['roomId'])) {
      $last_room_id = (string) $state_data['roomId'];
    }
    elseif (!empty($location_ref) && strpos($location_ref, 'room') !== FALSE) {
      $last_room_id = $location_ref;
    }
    
    return [
      'hp_current' => $hp_current,
      'hp_max' => $hp_max,
      'armor_class' => $armor_class,
      'experience_points' => $experience_points,
      'position_q' => $position_q,
      'position_r' => $position_r,
      'last_room_id' => $last_room_id,
    ];
  }

  /**
   * GET /api/campaign/{campaignId}/entities
   * 
   * Query params: locationType, locationRef, type (all optional filters)
   */
  public function listEntities(int $campaign_id, Request $request): JsonResponse {
    // Check campaign access.
    $access = $this->campaignAccessCheck->access($this->currentUser, $campaign_id);
    if (!$access->isAllowed()) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Access denied to campaign',
      ], 403);
    }

    $query = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c')
      ->condition('campaign_id', $campaign_id);

    // Apply optional filters.
    $location_type = $request->query->get('locationType');
    if ($location_type) {
      $query->condition('location_type', $location_type);
    }

    $location_ref = $request->query->get('locationRef');
    if ($location_ref) {
      $query->condition('location_ref', $location_ref);
    }

    $type = $request->query->get('type');
    if ($type) {
      $query->condition('type', $type);
    }

    try {
      $results = $query->execute()->fetchAll();
      
      $entities = [];
      foreach ($results as $entity) {
        $state_data = json_decode($entity->state_data ?? '{}', TRUE);
        if (!is_array($state_data)) {
          $state_data = [];
        }

        $entities[] = [
          'id' => (int) $entity->id,
          'campaignId' => (int) $entity->campaign_id,
          'type' => $entity->type,
          'instanceId' => $entity->instance_id,
          'characterId' => (int) $entity->character_id,
          'locationType' => $entity->location_type,
          'locationRef' => $entity->location_ref,
          'stateData' => $state_data,
        ];
      }

      return new JsonResponse([
        'success' => TRUE,
        'data' => $entities,
        'count' => count($entities),
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Failed to list entities: ' . $e->getMessage(),
      ], 500);
    }
  }

}
