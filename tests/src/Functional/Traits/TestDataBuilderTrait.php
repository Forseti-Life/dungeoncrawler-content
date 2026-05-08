<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Traits;

use Drupal\user\UserInterface;

/**
 * Trait for test data builders.
 *
 * Provides reusable helper methods for creating campaigns, characters, and
 * entities in functional tests. Reduces duplication and standardizes test data
 * creation across the test suite.
 */
trait TestDataBuilderTrait {

  /**
   * Create a test user with dungeoncrawler permissions.
   *
   * @param array $permissions
   *   Optional array of additional permissions. Default includes
   *   'access dungeoncrawler characters'.
   *
   * @return \Drupal\user\UserInterface
   *   The created user entity.
   */
  protected function createTestUser(array $permissions = []): UserInterface {
    $default_permissions = ['access dungeoncrawler characters'];
    $all_permissions = array_merge($default_permissions, $permissions);
    return $this->drupalCreateUser($all_permissions);
  }

  /**
   * Create a test campaign.
   *
   * @param \Drupal\user\UserInterface|null $owner
   *   Campaign owner. If NULL, creates a new test user.
   * @param array $overrides
   *   Optional field overrides for the campaign.
   *
   * @return int
   *   The campaign ID.
   */
  protected function createTestCampaign(?UserInterface $owner = NULL, array $overrides = []): int {
    if ($owner === NULL) {
      $owner = $this->createTestUser();
    }

    $database = \Drupal::database();
    
    $defaults = [
      'uuid' => \Drupal::service('uuid')->generate(),
      'uid' => $owner->id(),
      'name' => 'Test Campaign',
      'status' => 'active',
      'campaign_data' => '{}',
      'created' => time(),
      'changed' => time(),
    ];

    $fields = array_merge($defaults, $overrides);

    return (int) $database->insert('dc_campaigns')
      ->fields($fields)
      ->execute();
  }

  /**
   * Create a test campaign with state data.
   *
   * Convenience method for creating campaigns with pre-initialized state.
   *
   * @param \Drupal\user\UserInterface|null $owner
   *   Campaign owner. If NULL, creates a new test user.
   * @param array $state
   *   Optional state data to initialize.
   * @param int $version
   *   Optional version number. Default is 1.
   *
   * @return int
   *   The campaign ID.
   */
  protected function createTestCampaignWithState(?UserInterface $owner = NULL, array $state = [], int $version = 1): int {
    if ($owner === NULL) {
      $owner = $this->createTestUser();
    }

    $default_state = [
      'created_by' => $owner->id(),
      'started' => TRUE,
      'progress' => [],
    ];

    $merged_state = array_merge($default_state, $state);

    $campaign_data = json_encode([
      'state' => $merged_state,
      'state_meta' => [
        'version' => $version,
        'updatedAt' => date('c'),
      ],
    ]);

    return $this->createTestCampaign($owner, ['campaign_data' => $campaign_data]);
  }

  /**
   * Create a test entity spawn payload.
   *
   * Provides a standardized entity spawn payload with sensible defaults.
   *
   * @param array $overrides
   *   Optional field overrides for the spawn payload.
   *
   * @return array
   *   Entity spawn payload array.
   */
  protected function createTestEntityPayload(array $overrides = []): array {
    $defaults = [
      'type' => 'npc',
      'instanceId' => 'test-entity-' . uniqid(),
      'locationType' => 'room',
      'locationRef' => 'room-1',
      'stateData' => [
        'hp' => 8,
        'maxHp' => 8,
      ],
    ];

    return array_merge($defaults, $overrides);
  }

  /**
   * Create and spawn a test entity in a campaign.
   *
   * @param int $campaign_id
   *   The campaign ID.
   * @param array $payload_overrides
   *   Optional payload overrides.
   *
   * @return array
   *   The API response from spawning the entity.
   */
  protected function createTestEntity(int $campaign_id, array $payload_overrides = []): array {
    $payload = $this->createTestEntityPayload($payload_overrides);
    return $this->requestJson('POST', "/api/campaign/{$campaign_id}/entity/spawn", $payload);
  }

  /**
   * Setup a logged-in test user with a campaign.
   *
   * Common test setup: creates user, logs them in, and creates a campaign.
   *
   * @param array $user_permissions
   *   Optional additional permissions for the user.
   *
   * @return array
   *   Array with 'user' and 'campaign_id' keys.
   */
  protected function setupUserWithCampaign(array $user_permissions = []): array {
    $user = $this->createTestUser($user_permissions);
    $this->drupalLogin($user);
    $campaign_id = $this->createTestCampaign($user);

    return [
      'user' => $user,
      'campaign_id' => $campaign_id,
    ];
  }

}
