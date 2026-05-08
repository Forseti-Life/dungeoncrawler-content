<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Traits;

/**
 * Provides test data factory methods for functional tests.
 */
trait TestDataFactoryTrait {

  /**
   * Create a campaign in the database.
   *
   * @param array $options
   *   Options for campaign creation. Available keys:
   *   - uid: User ID (default: current user)
   *   - name: Campaign name (default: 'Test Campaign')
   *   - status: Campaign status (default: 'active')
   *   - state: Initial campaign state (default: basic state)
   *   - version: State version (default: 1)
   *
   * @return int
   *   Campaign ID.
   */
  protected function createTestCampaign(array $options = []): int {
    $defaults = [
      'uid' => $this->loggedInUser ? $this->loggedInUser->id() : 1,
      'name' => 'Test Campaign',
      'status' => 'active',
      'state' => [
        'created_by' => $this->loggedInUser ? $this->loggedInUser->id() : 1,
        'started' => TRUE,
        'progress' => [],
      ],
      'version' => 1,
    ];
    
    $config = array_merge($defaults, $options);
    
    $campaign_data = [
      'state' => $config['state'],
      'state_meta' => [
        'version' => $config['version'],
        'updatedAt' => date('c'),
      ],
    ];
    
    $database = \Drupal::database();
    return $database->insert('dc_campaigns')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'uid' => $config['uid'],
        'name' => $config['name'],
        'status' => $config['status'],
        'campaign_data' => json_encode($campaign_data),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();
  }

  /**
   * Create campaign state data for testing.
   *
   * @param array $overrides
   *   Values to override defaults.
   *
   * @return array
   *   Campaign state data.
   */
  protected function createCampaignState(array $overrides = []): array {
    $defaults = [
      'created_by' => $this->loggedInUser ? $this->loggedInUser->id() : 1,
      'started' => TRUE,
      'progress' => [],
      'active_hex' => 'q0r0',
      'current_location' => 'town',
      'party_gold' => 100,
      'completed_quests' => [],
      'active_quests' => [],
      'discovered_locations' => ['town'],
      'game_time' => [
        'day' => 1,
        'hour' => 8,
      ],
    ];
    
    return array_merge($defaults, $overrides);
  }

  /**
   * Create entity spawn data for testing.
   *
   * @param array $overrides
   *   Values to override defaults.
   *
   * @return array
   *   Entity spawn data.
   */
  protected function createEntitySpawnData(array $overrides = []): array {
    $defaults = [
      'type' => 'npc',
      'instanceId' => 'test-entity-' . uniqid(),
      'characterId' => 999,
      'locationType' => 'room',
      'locationRef' => 'room-1',
      'stateData' => [
        'hp' => 10,
        'maxHp' => 10,
        'hexId' => 'hex-1',
        'name' => 'Test Goblin',
        'level' => 1,
      ],
    ];
    
    return array_merge($defaults, $overrides);
  }

  /**
   * Create entity move data for testing.
   *
   * @param array $overrides
   *   Values to override defaults.
   *
   * @return array
   *   Entity move data.
   */
  protected function createEntityMoveData(array $overrides = []): array {
    $defaults = [
      'locationType' => 'room',
      'locationRef' => 'room-2',
    ];
    
    return array_merge($defaults, $overrides);
  }

}
