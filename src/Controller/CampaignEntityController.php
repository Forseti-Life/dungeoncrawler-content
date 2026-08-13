<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;
use Drupal\dungeoncrawler_content\Access\CampaignAccessCheck;
use Drupal\dungeoncrawler_content\Service\CampaignAuthorizationService;
use Drupal\dungeoncrawler_content\Service\HazardService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * API endpoints for campaign entity lifecycle (spawn/move/despawn).
 */
class CampaignEntityController extends ControllerBase {

  private Connection $database;
  private CampaignAccessCheck $campaignAccessCheck;
  private CampaignAuthorizationService $campaignAuthorization;
  private HazardService $hazardService;
  protected $currentUser;

  public function __construct(
    Connection $database,
    CampaignAccessCheck $campaign_access_check,
    CampaignAuthorizationService $campaign_authorization,
    HazardService $hazard_service,
    AccountInterface $current_user
  ) {
    $this->database = $database;
    $this->campaignAccessCheck = $campaign_access_check;
    $this->campaignAuthorization = $campaign_authorization;
    $this->hazardService = $hazard_service;
    $this->currentUser = $current_user;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('dungeoncrawler_content.campaign_access_check'),
      $container->get('dungeoncrawler_content.campaign_authorization'),
      $container->get('dungeoncrawler_content.hazard_service'),
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
          'default_locations' => NULL,
          'portrait' => NULL,
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

    $location_type = (string) ($data['locationType'] ?? '');
    $location_ref = (string) ($data['locationRef'] ?? '');
    $new_state_data = is_array($data['stateData'] ?? NULL) ? $data['stateData'] : NULL;

    // Backward compatibility for the existing hexmap room-transition payload.
    if (($location_type === '' || $location_ref === '') && !empty($data['room_id'])) {
      $location_type = 'room';
      $location_ref = (string) $data['room_id'];
      $placement_state = [
        'placement' => [
          'room_id' => $location_ref,
          'hex' => [
            'q' => (int) ($data['q'] ?? 0),
            'r' => (int) ($data['r'] ?? 0),
          ],
        ],
      ];
      $new_state_data = is_array($new_state_data)
        ? array_replace_recursive($new_state_data, $placement_state)
        : $placement_state;
    }

    // Validate required fields.
    if ($location_type === '') {
      return new JsonResponse(['success' => FALSE, 'error' => 'locationType is required'], 400);
    }
    if ($location_ref === '') {
      return new JsonResponse(['success' => FALSE, 'error' => 'locationRef is required'], 400);
    }

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

    if (!$this->canMoveEntity($campaign_id, $entity)) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'You are not allowed to move this actor.',
      ], 403);
    }

    // Update location and hot columns.
    try {
      $update_fields = [
        'location_type' => $location_type,
        'location_ref' => $location_ref,
        'updated' => time(),
      ];
      $hazard_events = [];
      
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

      // Resolve passive room hazard triggers when movement targets a room hex.
      $moved_q = isset($update_fields['position_q']) ? (int) $update_fields['position_q'] : NULL;
      $moved_r = isset($update_fields['position_r']) ? (int) $update_fields['position_r'] : NULL;
      if ($location_type === 'room' && $moved_q !== NULL && $moved_r !== NULL) {
        $dungeon_record = $this->loadLatestCampaignDungeonRecord($campaign_id);
        if ($dungeon_record !== NULL && is_numeric($dungeon_record['id'] ?? NULL)) {
          $dungeon_data = json_decode((string) ($dungeon_record['dungeon_data'] ?? '{}'), TRUE);
          if (is_array($dungeon_data)) {
            $hazard_events = $this->resolvePassiveRoomMovementHazardEvents(
              $dungeon_data,
              $location_ref,
              $moved_q,
              $moved_r
            );
            $terrain_hazard = $this->resolveTerrainMovementHazardEvent(
              $dungeon_data,
              $location_ref,
              $moved_q,
              $moved_r
            );
            if ($terrain_hazard !== NULL) {
              $hazard_events[] = $terrain_hazard;
            }
            if ($hazard_events !== []) {
              $this->persistCampaignDungeonPayload((int) $dungeon_record['id'], $dungeon_data);
            }
          }
        }
      }

      // Return updated entity data.
      $state_data = json_decode($update_fields['state_data'] ?? $entity['state_data'] ?? '{}', TRUE);
      if (!is_array($state_data)) {
        $state_data = [];
      }
      $hazard_damage = $this->resolveTotalHazardDamage($hazard_events);
      if ($hazard_damage > 0) {
        $damage_applied = $this->applyDamageToStateData($state_data, $hazard_damage);
        if ($damage_applied > 0) {
          foreach ($hazard_events as &$hazard_event) {
            if (!is_array($hazard_event)) {
              continue;
            }
            $effect = is_array($hazard_event['effect'] ?? NULL) ? $hazard_event['effect'] : [];
            if (!isset($effect['resolved_damage']) || !is_numeric($effect['resolved_damage'])) {
              continue;
            }
            $effect['damage_applied'] = (int) $effect['resolved_damage'];
            $hazard_event['effect'] = $effect;
          }
          unset($hazard_event);

          $update_damage_fields = [
            'state_data' => json_encode($state_data, JSON_UNESCAPED_UNICODE),
            'hp_current' => $this->extractCurrentHpFromStateData($state_data),
            'updated' => time(),
          ];
          $this->database->update('dc_campaign_characters')
            ->fields($update_damage_fields)
            ->condition('campaign_id', $campaign_id)
            ->condition('instance_id', $instance_id)
            ->execute();
        }
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
          'hazardEvents' => $hazard_events,
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
   * Enforce actor-scoped movement authority for room drag/drop moves.
   */
  private function canMoveEntity(int $campaign_id, array $entity): bool {
    $uid = (int) $this->currentUser->id();
    if ($uid <= 0) {
      return FALSE;
    }
    if ($this->currentUser->hasPermission('administer dungeoncrawler content')) {
      return TRUE;
    }

    $campaign_access = $this->campaignAuthorization->buildCampaignAccessContext($campaign_id, $uid);
    $current_mode = strtolower(trim((string) ($campaign_access['current_mode'] ?? 'player')));
    if ($current_mode === 'gm' && !empty($campaign_access['can_use_gm_mode'])) {
      return TRUE;
    }

    if (strtolower(trim((string) ($entity['type'] ?? ''))) !== 'pc') {
      $state_data = json_decode((string) ($entity['state_data'] ?? '{}'), TRUE);
      $metadata = [];
      if (is_array($state_data)) {
        $metadata = is_array($state_data['metadata'] ?? NULL)
          ? $state_data['metadata']
          : (is_array($state_data['state']['metadata'] ?? NULL) ? $state_data['state']['metadata'] : []);
      }
      $follower_kind = strtolower(trim((string) ($metadata['follower_kind'] ?? ($metadata['bond_contract']['follower_kind'] ?? ''))));
      if ($follower_kind === '') {
        return FALSE;
      }

      $owner_source_character_id = isset($metadata['owner_source_character_id']) && is_numeric($metadata['owner_source_character_id'])
        ? (int) $metadata['owner_source_character_id']
        : (isset($metadata['bond_contract']['owner_source_character_id']) && is_numeric($metadata['bond_contract']['owner_source_character_id'])
          ? (int) $metadata['bond_contract']['owner_source_character_id']
          : 0);
      $owner_character_id = isset($metadata['owner_character_id']) && is_numeric($metadata['owner_character_id'])
        ? (int) $metadata['owner_character_id']
        : (isset($metadata['bond_contract']['owner_character_id']) && is_numeric($metadata['bond_contract']['owner_character_id'])
          ? (int) $metadata['bond_contract']['owner_character_id']
          : 0);
      foreach ((array) ($campaign_access['playable_principals'] ?? []) as $principal) {
        if (!is_array($principal)) {
          continue;
        }
        $principal_character_id = isset($principal['character_id']) && is_numeric($principal['character_id']) ? (int) $principal['character_id'] : 0;
        if ($principal_character_id > 0 && ($principal_character_id === $owner_source_character_id || $principal_character_id === $owner_character_id)) {
          return TRUE;
        }
      }

      return FALSE;
    }

    $entity_uid = isset($entity['uid']) && is_numeric($entity['uid']) ? (int) $entity['uid'] : 0;
    if ($entity_uid > 0 && $entity_uid === $uid) {
      return TRUE;
    }

    $entity_character_id = isset($entity['character_id']) && is_numeric($entity['character_id']) ? (int) $entity['character_id'] : 0;
    $entity_instance_id = trim((string) ($entity['instance_id'] ?? ''));
    foreach ((array) ($campaign_access['playable_principals'] ?? []) as $principal) {
      if (!is_array($principal)) {
        continue;
      }
      $principal_character_id = isset($principal['character_id']) && is_numeric($principal['character_id']) ? (int) $principal['character_id'] : 0;
      $principal_instance_id = trim((string) ($principal['instance_id'] ?? ''));
      if (($entity_character_id > 0 && $principal_character_id === $entity_character_id) || ($entity_instance_id !== '' && $principal_instance_id === $entity_instance_id)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Load the most recently updated dungeon payload for a campaign.
   */
  private function loadLatestCampaignDungeonRecord(int $campaign_id): ?array {
    $record = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['id', 'dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->orderBy('updated', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    return is_array($record) ? $record : NULL;
  }

  /**
   * Persist dungeon_data JSON after hazard-state mutation.
   */
  private function persistCampaignDungeonPayload(int $row_id, array $dungeon_data): void {
    $this->database->update('dc_campaign_dungeons')
      ->fields([
        'dungeon_data' => json_encode($dungeon_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'updated' => time(),
      ])
      ->condition('id', $row_id)
      ->execute();
  }

  /**
   * Resolve passive hazard triggers for in-room movement placement.
   *
   * @param array<string, mixed> $dungeon_data
   * @return array<int, array<string, mixed>>
   */
  private function resolvePassiveRoomMovementHazardEvents(array &$dungeon_data, string $room_id, int $q, int $r): array {
    if (!is_array($dungeon_data['entities'] ?? NULL)) {
      return [];
    }

    $events = [];
    foreach ($dungeon_data['entities'] as &$entity) {
      if (!is_array($entity)) {
        continue;
      }
      $entity_type = strtolower(trim((string) ($entity['entity_type'] ?? $entity['type'] ?? '')));
      if ($entity_type !== 'hazard') {
        continue;
      }
      $entity_room_id = trim((string) ($entity['placement']['room_id'] ?? ''));
      if ($entity_room_id !== $room_id) {
        continue;
      }
      $hex = is_array($entity['placement']['hex'] ?? NULL) ? $entity['placement']['hex'] : [];
      if (!is_numeric($hex['q'] ?? NULL) || !is_numeric($hex['r'] ?? NULL)) {
        continue;
      }
      if ((int) $hex['q'] !== $q || (int) $hex['r'] !== $r) {
        continue;
      }

      $trigger_type = strtolower(trim((string) ($entity['trigger']['type'] ?? 'passive')));
      if ($trigger_type !== 'passive') {
        continue;
      }

      $trigger_result = $this->hazardService->triggerHazard($entity);
      if (!empty($trigger_result['triggered'])) {
        $events[] = [
          'type' => 'hazard_triggered',
          'instance_id' => (string) ($entity['instance_id'] ?? $entity['id'] ?? ''),
          'name' => (string) ($entity['name'] ?? 'Hazard'),
          'room_id' => $room_id,
          'hex' => ['q' => $q, 'r' => $r],
          'effect' => is_array($trigger_result['effect'] ?? NULL) ? $trigger_result['effect'] : [],
        ];
      }
    }
    unset($entity);

    return $events;
  }

  /**
   * Resolve terrain-based movement hazards (for example lava hex entry).
   *
   * @param array<string, mixed> $dungeon_data
   * @return array<string, mixed>|null
   */
  private function resolveTerrainMovementHazardEvent(array $dungeon_data, string $room_id, int $q, int $r): ?array {
    $terrain = $this->resolveRoomHexTerrainType($dungeon_data, $room_id, $q, $r);
    if ($terrain === '') {
      return NULL;
    }

    if (str_contains($terrain, 'lava')) {
      return [
        'type' => 'hazard_triggered',
        'instance_id' => 'terrain:lava',
        'name' => 'Lava',
        'room_id' => $room_id,
        'hex' => ['q' => $q, 'r' => $r],
        'effect' => [
          'description' => 'Molten terrain scorches the creature.',
          'damage' => 6,
          'damage_type' => 'fire',
          'resolved_damage' => 6,
        ],
      ];
    }

    return NULL;
  }

  /**
   * Resolve room-hex terrain label from dungeon payload.
   */
  private function resolveRoomHexTerrainType(array $dungeon_data, string $room_id, int $q, int $r): string {
    $room_nodes = [];
    if (is_array($dungeon_data['rooms'][$room_id] ?? NULL)) {
      $room_nodes[] = $dungeon_data['rooms'][$room_id];
    }
    foreach ((array) ($dungeon_data['rooms'] ?? []) as $room_node) {
      if (!is_array($room_node)) {
        continue;
      }
      $candidate_room_id = trim((string) ($room_node['room_id'] ?? $room_node['id'] ?? ''));
      if ($candidate_room_id === $room_id) {
        $room_nodes[] = $room_node;
      }
    }

    foreach ($room_nodes as $room_node) {
      foreach ((array) ($room_node['hexes'] ?? []) as $room_hex) {
        if (!is_array($room_hex)) {
          continue;
        }
        if (!is_numeric($room_hex['q'] ?? NULL) || !is_numeric($room_hex['r'] ?? NULL)) {
          continue;
        }
        if ((int) $room_hex['q'] !== $q || (int) $room_hex['r'] !== $r) {
          continue;
        }
        $terrain = strtolower(trim((string) ($room_hex['terrain_type'] ?? $room_hex['terrain'] ?? $room_hex['tile_type'] ?? '')));
        if ($terrain !== '') {
          return $terrain;
        }
      }
    }

    return '';
  }

  /**
   * Resolve aggregate damage from movement-triggered hazard events.
   *
   * @param array<int, array<string, mixed>> $hazard_events
   */
  private function resolveTotalHazardDamage(array &$hazard_events): int {
    $total = 0;
    foreach ($hazard_events as &$event) {
      if (!is_array($event)) {
        continue;
      }
      $effect = is_array($event['effect'] ?? NULL) ? $event['effect'] : [];
      $resolved_damage = $this->resolveHazardDamageValue($effect['damage'] ?? NULL);
      if ($resolved_damage <= 0) {
        continue;
      }
      $total += $resolved_damage;
      $effect['resolved_damage'] = $resolved_damage;
      $event['effect'] = $effect;
    }
    unset($event);

    return max(0, $total);
  }

  /**
   * Parse hazard damage value from numeric/scalar dice expressions.
   */
  private function resolveHazardDamageValue($damage): int {
    if (is_int($damage) || is_float($damage)) {
      return max(0, (int) floor((float) $damage));
    }
    if (!is_string($damage)) {
      return 0;
    }
    $trimmed = trim($damage);
    if ($trimmed === '') {
      return 0;
    }
    if (preg_match('/^\d+$/', $trimmed) === 1) {
      return (int) $trimmed;
    }
    if (preg_match('/^(\d+)d(\d+)(?:\s*([+-])\s*(\d+))?/i', $trimmed, $matches) === 1) {
      $dice_count = max(1, (int) ($matches[1] ?? 1));
      $die_sides = max(2, (int) ($matches[2] ?? 2));
      $modifier_sign = (string) ($matches[3] ?? '+');
      $modifier_value = (int) ($matches[4] ?? 0);
      $total = 0;
      for ($i = 0; $i < $dice_count; $i++) {
        $total += random_int(1, $die_sides);
      }
      $total += ($modifier_sign === '-') ? -$modifier_value : $modifier_value;
      return max(0, $total);
    }
    return 0;
  }

  /**
   * Apply damage to available HP fields in state payload.
   */
  private function applyDamageToStateData(array &$state_data, int $damage): int {
    if ($damage <= 0) {
      return 0;
    }
    $current_hp = $this->extractCurrentHpFromStateData($state_data);
    if ($current_hp <= 0) {
      return 0;
    }

    $next_hp = max(0, $current_hp - $damage);
    if (is_array($state_data['hit_points'] ?? NULL)) {
      $state_data['hit_points']['current'] = $next_hp;
    }
    if (isset($state_data['hp'])) {
      $state_data['hp'] = $next_hp;
    }
    if (is_array($state_data['resources']['hitPoints'] ?? NULL)) {
      $state_data['resources']['hitPoints']['current'] = $next_hp;
    }
    if (is_array($state_data['state']['hit_points'] ?? NULL)) {
      $state_data['state']['hit_points']['current'] = $next_hp;
    }
    if (is_array($state_data['state']['resources']['hitPoints'] ?? NULL)) {
      $state_data['state']['resources']['hitPoints']['current'] = $next_hp;
    }
    if (is_array($state_data['metadata']['stats'] ?? NULL)) {
      $state_data['metadata']['stats']['currentHp'] = $next_hp;
    }
    if (is_array($state_data['state']['metadata']['stats'] ?? NULL)) {
      $state_data['state']['metadata']['stats']['currentHp'] = $next_hp;
    }

    return max(0, $current_hp - $next_hp);
  }

  /**
   * Extract current HP from common runtime state-data shapes.
   */
  private function extractCurrentHpFromStateData(array $state_data): int {
    $candidates = [
      $state_data['hit_points']['current'] ?? NULL,
      $state_data['resources']['hitPoints']['current'] ?? NULL,
      $state_data['state']['hit_points']['current'] ?? NULL,
      $state_data['state']['resources']['hitPoints']['current'] ?? NULL,
      $state_data['metadata']['stats']['currentHp'] ?? NULL,
      $state_data['state']['metadata']['stats']['currentHp'] ?? NULL,
      $state_data['hp'] ?? NULL,
    ];
    foreach ($candidates as $candidate) {
      if (is_numeric($candidate)) {
        return max(0, (int) $candidate);
      }
    }
    return 0;
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
