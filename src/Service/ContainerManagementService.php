<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Manages containers and storage locations for items.
 *
 * Containers are passive storage locations that can hold items with capacity
 * limits. Examples: backpacks, chests, stashes, rooms, altars.
 *
 * @see docs/dungeoncrawler/INVENTORY_MANAGEMENT_SYSTEM.md
 */
class ContainerManagementService {

  protected Connection $database;

  /**
   * Container types and their default capacities.
   */
  private const CONTAINER_TYPES = [
    'backpack' => 6,
    'chest' => 20,
    'trunk' => 40,
    'pouch' => 2,
    'sack' => 5,
    'bag' => 10,
    'barrel' => 100,
    'stash' => 50,
    'altar' => 100,
    'table' => 50,
    'pedestal' => 20,
    'room' => 1000, // Rooms have essentially unlimited capacity
  ];

  /**
   * Constructor.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * Create a new container.
   *
   * @param string $container_id
   *   Unique container identifier.
   * @param string $container_type
   *   Type of container (backpack, chest, stash, etc.).
   * @param string $container_name
   *   Human-readable name.
   * @param array $metadata
   *   Additional metadata (location, owner, description, etc.).
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return array
   *   Created container info.
   *
   * @throws \InvalidArgumentException
   *   If container already exists or invalid type.
   */
  public function createContainer(
    string $container_id,
    string $container_type,
    string $container_name,
    array $metadata = [],
    ?int $campaign_id = NULL
  ): array {
    if (!isset(self::CONTAINER_TYPES[$container_type])) {
      throw new \InvalidArgumentException("Invalid container type: {$container_type}");
    }

    // Check if container exists
    $existing = $this->database->select('dc_campaign_item_instances', 'i')
      ->fields('i', ['id'])
      ->condition('item_instance_id', $container_id)
      ->execute()
      ->fetchField();

    if ($existing) {
      throw new \InvalidArgumentException("Container already exists: {$container_id}");
    }

    $capacity = $metadata['capacity'] ?? self::CONTAINER_TYPES[$container_type];

    // Create container as special item instance
    $container_data = [
      'id' => $container_id,
      'name' => $container_name,
      'type' => 'container',
      'container_type' => $container_type,
      'capacity' => $capacity,
      'is_container' => TRUE,
      'lock_status' => $metadata['lock_status'] ?? 'unlocked',
      'locked_dc' => $metadata['locked_dc'] ?? NULL,
      'description' => $metadata['description'] ?? '',
      'owner_id' => $metadata['owner_id'] ?? NULL,
      'created_at' => date('c'),
      'metadata' => $metadata,
    ];

    $this->database->insert('dc_campaign_item_instances')
      ->fields([
        'campaign_id' => $campaign_id ?? 0,
        'item_instance_id' => $container_id,
        'item_id' => $container_type,
        'location_type' => 'container',
        'location_ref' => $metadata['location_ref'] ?? '',
        'quantity' => 1,
        'state_data' => json_encode($container_data),
        'created' => time(),
        'updated' => time(),
      ])
      ->execute();

    return [
      'success' => TRUE,
      'container_id' => $container_id,
      'container' => $container_data,
      'message' => "Created {$container_type} container: {$container_name}",
    ];
  }

  /**
   * Get container details.
   *
   * @param string $container_id
   *   Container ID.
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return array
   *   Container information.
   *
   * @throws \InvalidArgumentException
   *   If container not found.
   */
  public function getContainer(string $container_id, ?int $campaign_id = NULL): array {
    $row = $this->database->select('dc_campaign_item_instances', 'i')
      ->fields('i')
      ->condition('item_instance_id', $container_id)
      ->condition('location_type', 'container')
      ->execute()
      ->fetchAssoc();

    if (!$row) {
      throw new \InvalidArgumentException("Container not found: {$container_id}");
    }

    $state = json_decode($row['state_data'], TRUE) ?? [];

    return [
      'container_id' => $container_id,
      'container_type' => $state['container_type'] ?? 'unknown',
      'name' => $state['name'] ?? 'Unknown Container',
      'capacity' => (float) ($state['capacity'] ?? 0),
      'lock_status' => $state['lock_status'] ?? 'unlocked',
      'locked_dc' => $state['locked_dc'] ?? NULL,
      'description' => $state['description'] ?? '',
      'owner_id' => $state['owner_id'] ?? NULL,
      'created_at' => $state['created_at'] ?? NULL,
    ];
  }

  /**
   * Get all contents of a container.
   *
   * @param string $container_id
   *   Container ID.
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return array
   *   Items in container.
   */
  public function getContainerContents(string $container_id, ?int $campaign_id = NULL): array {
    $items = $this->database->select('dc_campaign_item_instances', 'i')
      ->fields('i')
      ->condition('location_ref', $container_id)
      ->condition('location_type', 'container')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    return array_map(function ($item) {
      return [
        'item_instance_id' => $item['item_instance_id'],
        'item_id' => $item['item_id'],
        'quantity' => (int) $item['quantity'],
        'state' => json_decode($item['state_data'], TRUE),
      ];
    }, $items);
  }

  /**
   * Lock container.
   *
   * @param string $container_id
   *   Container ID.
   * @param int $lock_dc
   *   Lock DC for picking.
   *
   * @return array
   *   Updated container info.
   *
   * @throws \InvalidArgumentException
   *   If container not found.
   */
  public function lockContainer(string $container_id, int $lock_dc = 15): array {
    $row = $this->database->select('dc_campaign_item_instances', 'i')
      ->fields('i')
      ->condition('item_instance_id', $container_id)
      ->condition('location_type', 'container')
      ->execute()
      ->fetchAssoc();

    if (!$row) {
      throw new \InvalidArgumentException("Container not found: {$container_id}");
    }

    $state = json_decode($row['state_data'], TRUE) ?? [];
    $state['lock_status'] = 'locked';
    $state['locked_dc'] = $lock_dc;

    $this->database->update('dc_campaign_item_instances')
      ->fields([
        'state_data' => json_encode($state),
        'updated' => time(),
      ])
      ->condition('item_instance_id', $container_id)
      ->execute();

    return [
      'success' => TRUE,
      'lock_status' => 'locked',
      'locked_dc' => $lock_dc,
      'message' => "Container locked with DC {$lock_dc}",
    ];
  }

  /**
   * Unlock container.
   *
   * @param string $container_id
   *   Container ID.
   *
   * @return array
   *   Updated container info.
   *
   * @throws \InvalidArgumentException
   *   If container not found.
   */
  public function unlockContainer(string $container_id): array {
    $row = $this->database->select('dc_campaign_item_instances', 'i')
      ->fields('i')
      ->condition('item_instance_id', $container_id)
      ->condition('location_type', 'container')
      ->execute()
      ->fetchAssoc();

    if (!$row) {
      throw new \InvalidArgumentException("Container not found: {$container_id}");
    }

    $state = json_decode($row['state_data'], TRUE) ?? [];
    $state['lock_status'] = 'unlocked';
    unset($state['locked_dc']);

    $this->database->update('dc_campaign_item_instances')
      ->fields([
        'state_data' => json_encode($state),
        'updated' => time(),
      ])
      ->condition('item_instance_id', $container_id)
      ->execute();

    return [
      'success' => TRUE,
      'lock_status' => 'unlocked',
      'message' => 'Container unlocked',
    ];
  }

  /**
   * Check if container is locked.
   *
   * @param string $container_id
   *   Container ID.
   *
   * @return bool
   *   TRUE if locked, FALSE otherwise.
   *
   * @throws \InvalidArgumentException
   *   If container not found.
   */
  public function isLocked(string $container_id): bool {
    $state_data = $this->database->select('dc_campaign_item_instances', 'i')
      ->fields('i', ['state_data'])
      ->condition('item_instance_id', $container_id)
      ->condition('location_type', 'container')
      ->execute()
      ->fetchField();

    if (!$state_data) {
      throw new \InvalidArgumentException("Container not found: {$container_id}");
    }

    $state = json_decode($state_data, TRUE) ?? [];
    return ($state['lock_status'] ?? 'unlocked') === 'locked';
  }

  /**
   * Destroy container and move contents.
   *
   * @param string $container_id
   *   Container ID.
   * @param string $destination_location_type
   *   Where to move contents (room, character, etc.).
   * @param string $destination_location_ref
   *   Reference for destination location.
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return array
   *   Operation result.
   *
   * @throws \InvalidArgumentException
   *   If container not found.
   */
  public function destroyContainer(
    string $container_id,
    string $destination_location_type = 'room',
    string $destination_location_ref = '',
    ?int $campaign_id = NULL
  ): array {
    try {
      $this->database->startTransaction();

      // Get contents
      $items = $this->getContainerContents($container_id, $campaign_id);

      // Move all items to destination
      foreach ($items as $item) {
        $this->database->update('dc_campaign_item_instances')
          ->fields([
            'location_type' => $destination_location_type,
            'location_ref' => $destination_location_ref,
            'updated' => time(),
          ])
          ->condition('item_instance_id', $item['item_instance_id'])
          ->execute();
      }

      // Delete container
      $this->database->delete('dc_campaign_item_instances')
        ->condition('item_instance_id', $container_id)
        ->execute();

      $this->database->commit();

      return [
        'success' => TRUE,
        'items_moved' => count($items),
        'message' => "Container destroyed; {count($items)} items moved to {$destination_location_type}",
      ];
    }
    catch (\Exception $e) {
      $this->database->rollBack();
      throw $e;
    }
  }

  /**
   * Calculate current contents bulk for a container.
   *
   * @param string $container_id
   *   Container ID.
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return float
   *   Total bulk of contents.
   */
  public function calculateContainerBulk(string $container_id, ?int $campaign_id = NULL): float {
    $items = $this->database->select('dc_campaign_item_instances', 'i')
      ->fields('i', ['state_data', 'quantity'])
      ->condition('location_ref', $container_id)
      ->condition('location_type', 'container')
      ->execute()
      ->fetchAll();

    $total_bulk = 0.0;
    foreach ($items as $item) {
      $state = json_decode($item->state_data, TRUE) ?? [];
      $bulk_value = $state['bulk'] ?? 'light';
      
      $bulk_map = [
        'negligible' => 0,
        'light' => 0.1,
        'L' => 0.1,
        '1' => 1,
        'medium' => 1,
      ];
      
      $bulk = $bulk_map[$bulk_value] ?? 0;
      $total_bulk += ($bulk * (int) $item->quantity);
    }

    return $total_bulk;
  }

  /**
   * Get container capacity and current fill percentage.
   *
   * @param string $container_id
   *   Container ID.
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return array
   *   Capacity information.
   *
   * @throws \InvalidArgumentException
   *   If container not found.
   */
  public function getContainerCapacity(string $container_id, ?int $campaign_id = NULL): array {
    $container = $this->getContainer($container_id, $campaign_id);
    $current_bulk = $this->calculateContainerBulk($container_id, $campaign_id);
    $capacity = $container['capacity'];

    $percent_filled = ($capacity > 0) ? ($current_bulk / $capacity) * 100 : 0;

    return [
      'capacity' => $capacity,
      'current_bulk' => $current_bulk,
      'percent_filled' => $percent_filled,
      'available_space' => max(0, $capacity - $current_bulk),
      'is_full' => $current_bulk >= $capacity,
    ];
  }

  /**
   * List all container types with their default capacities.
   *
   * @return array
   *   Mapping of container types to capacities.
   */
  public function getContainerTypes(): array {
    return self::CONTAINER_TYPES;
  }

}
