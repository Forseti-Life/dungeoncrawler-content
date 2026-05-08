<?php

namespace Drupal\dungeoncrawler_content\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for loading initial game content.
 */
class InitialGameContentCommands extends DrushCommands {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $dcLogger;

  /**
   * Constructs an InitialGameContentCommands object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   */
  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    parent::__construct();
    $this->database = $database;
    $this->dcLogger = $logger_factory->get('dungeoncrawler_content');
  }

  /**
   * Load initial game content (Tavern Entrance room, NPCs, objects).
   *
   * @param string $campaign_id
   *   Campaign ID to load content into (or 'all' for setup).
   * @param array $options
   *   Command options.
   *
   * @command dungeoncrawler_content:game:load-initial
   * @option dry-run Show what would be loaded without saving
   * @option force Force reload even if content exists
   * @usage dungeoncrawler_content:game:load-initial 1
   *   Load tavern content for campaign 1
   * @aliases dcg-init
   */
  public function loadInitialContent(
    string $campaign_id = '1',
    array $options = ['dry-run' => FALSE, 'force' => FALSE]
  ): int {
    $this->io()->title('Loading Initial Game Content');

    // For now, campaign_id '1' is assumed to exist (demo campaign).
    if ($campaign_id === 'all') {
      // Load for all active campaigns.
      $campaigns = $this->database->select('dc_campaigns', 'c')
        ->fields('c', ['id'])
        ->condition('status', 'active')
        ->execute()
        ->fetchAllAssoc('id');

      if (empty($campaigns)) {
        $this->io()->warning('No active campaigns found.');
        return self::EXIT_SUCCESS;
      }

      $count = 0;
      foreach ($campaigns as $campaign) {
        $result = $this->loadContentForCampaign($campaign->id, $options['force'], $options['dry-run']);
        if ($result) {
          $count++;
        }
      }
      $this->io()->success("Loaded initial content for {$count} campaigns.");
      return self::EXIT_SUCCESS;
    }

    $campaign_id_int = (int) $campaign_id;
    if ($campaign_id_int <= 0) {
      $this->io()->error('Campaign ID must be a positive integer.');
      return self::EXIT_FAILURE;
    }

    // Load for specific campaign.
    if ($this->loadContentForCampaign($campaign_id_int, $options['force'], $options['dry-run'])) {
      $this->io()->success("Initial content loaded for campaign {$campaign_id}.");
      return self::EXIT_SUCCESS;
    }

    return self::EXIT_FAILURE;
  }

  /**
   * Load content for a specific campaign.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param bool $force
   *   Force reload.
   * @param bool $dry_run
   *   Show what would be done without saving.
   *
   * @return bool
   *   TRUE if successful.
   */
  protected function loadContentForCampaign(
    int $campaign_id,
    bool $force = FALSE,
    bool $dry_run = FALSE
  ): bool {
    // Verify campaign exists.
    $campaign = $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['id', 'name'])
      ->condition('id', $campaign_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$campaign) {
      $this->io()->error("Campaign {$campaign_id} not found.");
      return FALSE;
    }

    $this->io()->writeln("Loading content for campaign: {$campaign['name']} (ID: {$campaign_id})");

    // Load room definition.
    $module_path = \Drupal::service('extension.list.module')->getPath('dungeoncrawler_content');
    $room_file = DRUPAL_ROOT . '/' . $module_path . '/tavern_entrance_room.json';

    if (!file_exists($room_file)) {
      $this->io()->error("Room definition file not found: {$room_file}");
      return FALSE;
    }

    try {
      $room_data = json_decode(file_get_contents($room_file), TRUE);
      if ($room_data === NULL || json_last_error() !== JSON_ERROR_NONE) {
        throw new \Exception('Invalid JSON: ' . json_last_error_msg());
      }

      if ($dry_run) {
        $this->io()->writeln("DRY RUN: Would create room '{$room_data['name']}' (ID: {$room_data['room_id']})");
        return TRUE;
      }

      // Create room.
      $this->createRoom($campaign_id, $room_data, $force);

      // Create content objects.
      $this->createContentObjects($campaign_id, $room_data, $force);

      // Create NPCs.
      $this->createNpcs($campaign_id, $room_data, $force);

      // Initialize room state.
      $this->initializeRoomState($campaign_id, $room_data['room_id']);

      $this->io()->success("Content created successfully.");
      return TRUE;
    }
    catch (\Exception $e) {
      $this->io()->error("Error loading content: " . $e->getMessage());
      $this->dcLogger->error('Failed to load initial content: ' . $e->getMessage());
      return FALSE;
    }
  }

  /**
   * Create the room definition.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param array $room_data
   *   Room definition data.
   * @param bool $force
   *   Force reload.
   */
  protected function createRoom(int $campaign_id, array $room_data, bool $force = FALSE): void {
    $room_id = $room_data['room_id'] ?? 'unknown';

    // Check if room exists.
    $existing = $this->database->select('dc_campaign_rooms', 'r')
      ->fields('r', ['id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('room_id', $room_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if ($existing && !$force) {
      $this->io()->note("Room '{$room_id}' already exists. Use --force to reload.");
      return;
    }

    if ($existing && $force) {
      // Delete existing state and room.
      $this->database->delete('dc_campaign_room_states')
        ->condition('campaign_id', $campaign_id)
        ->condition('room_id', $room_id)
        ->execute();

      $this->database->delete('dc_campaign_rooms')
        ->condition('campaign_id', $campaign_id)
        ->condition('room_id', $room_id)
        ->execute();

      $this->io()->note("Reloading room '{$room_id}'.");
    }

    // Insert room.
    $now = time();
    $this->database->insert('dc_campaign_rooms')
      ->fields([
        'campaign_id' => $campaign_id,
        'room_id' => $room_id,
        'name' => $room_data['name'] ?? 'Unknown Room',
        'description' => $room_data['description'] ?? '',
        'environment_tags' => json_encode($room_data['environment_tags'] ?? []),
        'layout_data' => json_encode($room_data['layout_data'] ?? []),
        'contents_data' => json_encode($room_data['contents_data'] ?? []),
        'created' => $now,
        'updated' => $now,
      ])
      ->execute();

    $this->io()->writeln("  ✓ Room '{$room_id}' created");
  }

  /**
   * Create content objects (items, etc).
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param array $room_data
   *   Room definition data.
   * @param bool $force
   *   Force reload.
   */
  protected function createContentObjects(
    int $campaign_id,
    array $room_data,
    bool $force = FALSE
  ): void {
    $contents = $room_data['contents_data'] ?? [];
    $items = $contents['items'] ?? [];

    if (empty($items)) {
      return;
    }

    // Delete existing content objects if forcing reload.
    if ($force) {
      $this->database->delete('dc_campaign_content_registry')
        ->condition('campaign_id', $campaign_id)
        ->condition('content_type', 'item')
        ->execute();
    }

    $now = time();
    $created = 0;

    foreach ($items as $item) {
      $content_id = $item['content_id'] ?? 'unknown';

      // Check if object exists.
      $existing = $this->database->select('dc_campaign_content_registry', 'c')
        ->fields('c', ['id'])
        ->condition('campaign_id', $campaign_id)
        ->condition('content_type', 'item')
        ->condition('content_id', $content_id)
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();

      if ($existing && !$force) {
        continue;
      }

      if ($existing && $force) {
        $this->database->delete('dc_campaign_content_registry')
          ->condition('campaign_id', $campaign_id)
          ->condition('content_id', $content_id)
          ->execute();
      }

      // Prepare object data.
      $schema_data = [
        'position' => $item['position'] ?? [],
        'description' => $item['description'] ?? '',
        'quest_association' => $item['quest_association'] ?? NULL,
      ];

      // Insert object.
      $this->database->insert('dc_campaign_content_registry')
        ->fields([
          'campaign_id' => $campaign_id,
          'content_type' => 'item',
          'content_id' => $content_id,
          'name' => $item['name'] ?? 'Unknown Item',
          'level' => NULL,
          'rarity' => 'common',
          'tags' => json_encode($item['tags'] ?? []),
          'schema_data' => json_encode($schema_data),
          'created' => $now,
          'updated' => $now,
        ])
        ->execute();

      $created++;
    }

    if ($created > 0) {
      $this->io()->writeln("  ✓ Created {$created} interactive objects");
    }
  }

  /**
   * Create NPCs.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param array $room_data
   *   Room definition data.
   * @param bool $force
   *   Force reload.
   */
  protected function createNpcs(
    int $campaign_id,
    array $room_data,
    bool $force = FALSE
  ): void {
    $contents = $room_data['contents_data'] ?? [];
    $npcs = $contents['npcs'] ?? [];
    $room_id = $room_data['room_id'] ?? 'unknown';

    if (empty($npcs)) {
      return;
    }

    // Delete existing NPCs if forcing reload.
    if ($force) {
      $this->database->delete('dc_campaign_characters')
        ->condition('campaign_id', $campaign_id)
        ->condition('location_ref', $room_id)
        ->condition('type', 'npc')
        ->execute();
    }

    $now = time();
    $created = 0;

    foreach ($npcs as $npc) {
      $instance_id = 'npc_' . ($npc['content_id'] ?? 'unknown');
      $name = $npc['name'] ?? 'Unknown NPC';

      // Check if NPC exists.
      $existing = $this->database->select('dc_campaign_characters', 'c')
        ->fields('c', ['id'])
        ->condition('campaign_id', $campaign_id)
        ->condition('instance_id', $instance_id)
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();

      if ($existing && !$force) {
        continue;
      }

      if ($existing && $force) {
        $this->database->delete('dc_campaign_characters')
          ->condition('campaign_id', $campaign_id)
          ->condition('instance_id', $instance_id)
          ->execute();
      }

      // Prepare NPC state data.
      $state_data = [
        'content_id' => $npc['content_id'] ?? NULL,
        'role' => $npc['role'] ?? 'npc',
        'description' => $npc['description'] ?? '',
        'quests' => $npc['quests'] ?? [],
        'animation_state' => 'idle',
      ];

      // Insert NPC.
      $this->database->insert('dc_campaign_characters')
        ->fields([
          'campaign_id' => $campaign_id,
          'character_id' => 0,  // Generic NPC.
          'name' => $name,
          'level' => 0,
          'ancestry' => 'humanoid',
          'class' => 'npc',
          'hp_current' => 0,
          'hp_max' => 0,
          'armor_class' => 0,
          'experience_points' => 0,
          'position_q' => $npc['position']['q'] ?? 0,
          'position_r' => $npc['position']['r'] ?? 0,
          'last_room_id' => $room_id,
          'instance_id' => $instance_id,
          'type' => 'npc',
          'state_data' => json_encode($state_data),
          'location_type' => 'room',
          'location_ref' => $room_id,
          'is_active' => 1,
          'uid' => 0,
          'role' => 'npc',
          'status' => 1,
          'created' => $now,
          'changed' => $now,
          'joined' => $now,
          'updated' => $now,
        ])
        ->execute();

      $created++;
    }

    if ($created > 0) {
      $this->io()->writeln("  ✓ Created {$created} NPCs");
    }
  }

  /**
   * Initialize room state in dc_campaign_room_states.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $room_id
   *   Room ID.
   */
  protected function initializeRoomState(int $campaign_id, string $room_id): void {
    // Check if state exists.
    $existing = $this->database->select('dc_campaign_room_states', 'r')
      ->fields('r', ['id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('room_id', $room_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if ($existing) {
      return;  // State already initialized.
    }

    // Create initial state.
    $now = time();
    $this->database->insert('dc_campaign_room_states')
      ->fields([
        'campaign_id' => $campaign_id,
        'room_id' => $room_id,
        'is_cleared' => 0,
        'fog_state' => json_encode([
          'visibility' => 'initial',
          'discovered_hexes' => [],
        ]),
        'last_visited' => $now,
        'updated' => $now,
      ])
      ->execute();

    $this->io()->writeln("  ✓ Room state initialized");
  }
}
