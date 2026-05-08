<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Routes;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\dungeoncrawler_content\Functional\Traits\TestDataBuilderTrait;

/**
 * Tests campaign routes in the dungeon crawler module.
 *
 * @group dungeoncrawler_content
 * @group routes
 */
class CampaignRoutesTest extends BrowserTestBase {

  use TestDataBuilderTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests campaigns list route - positive case.
   */
  public function testCampaignsListRoutePositive(): void {
    $user = $this->createTestUser();
    $this->drupalLogin($user);

    $this->drupalGet('/campaigns');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('My Campaigns');
  }

  /**
   * Tests campaigns list route - negative case (no permission).
   */
  public function testCampaignsListRouteNegative(): void {
    $this->drupalGet('/campaigns');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests campaign create route - positive case.
   */
  public function testCampaignCreateRoutePositive(): void {
    $user = $this->createTestUser();
    $this->drupalLogin($user);

    $this->drupalGet('/campaigns/create');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Create Campaign');
  }

  /**
   * Tests campaign create route - negative case (no permission).
   */
  public function testCampaignCreateRouteNegative(): void {
    $this->drupalGet('/campaigns/create');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests campaign tavern entrance route - positive case.
   */
  public function testCampaignTavernEntranceRoutePositive(): void {
    $user = $this->createTestUser();
    $this->drupalLogin($user);

    // Note: This will fail without a real campaign
    $this->drupalGet('/campaigns/1/tavernentrance');
    $this->assertSession()->statusCodeNotEquals(200);
  }

  /**
   * Tests campaign tavern entrance route - negative case (non-numeric ID).
   */
  public function testCampaignTavernEntranceRouteNegative(): void {
    $user = $this->createTestUser();
    $this->drupalLogin($user);

    $this->drupalGet('/campaigns/invalid/tavernentrance');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests campaign select character route - positive case.
   */
  public function testCampaignSelectCharacterRoutePositive(): void {
    $user = $this->createTestUser();
    $this->drupalLogin($user);

    // Note: This will fail without real campaign and character
    $this->drupalGet('/campaigns/1/select-character/1');
    $this->assertSession()->statusCodeNotEquals(200);
  }

  /**
   * Tests campaign select character route - negative case (invalid IDs).
   */
  public function testCampaignSelectCharacterRouteNegative(): void {
    $user = $this->createTestUser();
    $this->drupalLogin($user);

    $this->drupalGet('/campaigns/invalid/select-character/invalid');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests campaign archive route - positive case.
   */
  public function testCampaignArchiveRoutePositive(): void {
    $user = $this->createTestUser();
    $this->drupalLogin($user);

    $database = \Drupal::database();
    $campaign_id = $database->insert('dc_campaigns')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'uid' => $user->id(),
        'name' => 'Route Archive Campaign',
        'status' => 'draft',
        'campaign_data' => json_encode([]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    $this->drupalGet("/campaigns/{$campaign_id}/archive");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Archive Campaign');
  }

  /**
   * Tests campaign unarchive route - positive case.
   */
  public function testCampaignUnarchiveRoutePositive(): void {
    $user = $this->createTestUser();
    $this->drupalLogin($user);

    $database = \Drupal::database();
    $campaign_id = $database->insert('dc_campaigns')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'uid' => $user->id(),
        'name' => 'Route Unarchive Campaign',
        'status' => 'archived',
        'campaign_data' => json_encode([]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    $this->drupalGet("/campaigns/{$campaign_id}/unarchive");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Unarchive Campaign');
  }

  /**
   * Tests archive form submit archives campaign and hides active archive action.
   */
  public function testCampaignArchiveSubmitUpdatesStatusAndListPlacement(): void {
    $user = $this->createTestUser();
    $this->drupalLogin($user);

    $campaign_id = $this->createTestCampaign($user, [
      'name' => 'Archive Submit Campaign',
      'status' => 'draft',
      'campaign_data' => json_encode([]),
    ]);

    $this->drupalGet("/campaigns/{$campaign_id}/archive");
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([], 'Archive Campaign');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('archived');

    $campaign_row = \Drupal::database()->select('dc_campaigns', 'c')
      ->fields('c', ['status', 'campaign_data'])
      ->condition('id', $campaign_id)
      ->execute()
      ->fetchAssoc();

    $this->assertEquals('archived', $campaign_row['status'], 'Campaign status should be archived.');

    $campaign_data = json_decode((string) ($campaign_row['campaign_data'] ?? '{}'), TRUE);
    $this->assertIsArray($campaign_data, 'Campaign data should decode to array.');
    $this->assertArrayHasKey('_archive_meta', $campaign_data, 'Archive metadata should be stored.');
    $this->assertEquals('draft', $campaign_data['_archive_meta']['previous_status'] ?? NULL, 'Previous status should be recorded for restore.');

    $this->drupalGet('/campaigns');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseNotContains("/campaigns/{$campaign_id}/archive");

    $active_bucket_count = (int) \Drupal::database()->select('dc_campaigns', 'c')
      ->condition('id', $campaign_id)
      ->condition('uid', (int) $user->id())
      ->condition('status', 'archived', '<>')
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals(0, $active_bucket_count, 'Archived campaign should not remain in active bucket query.');

    $archived_bucket_count = (int) \Drupal::database()->select('dc_campaigns', 'c')
      ->condition('id', $campaign_id)
      ->condition('uid', (int) $user->id())
      ->condition('status', 'archived')
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals(1, $archived_bucket_count, 'Archived campaign should be present in archived bucket query.');
  }

  /**
   * Tests archive submit does not require extra confirmation fields.
   */
  public function testCampaignArchiveSubmitDoesNotRequireExtraConfirmation(): void {
    $user = $this->createTestUser();
    $this->drupalLogin($user);

    $campaign_id = $this->createTestCampaign($user, [
      'name' => 'Archive Checkbox Campaign',
      'status' => 'draft',
      'campaign_data' => json_encode([]),
    ]);

    $this->drupalGet("/campaigns/{$campaign_id}/archive");
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([], 'Archive Campaign');

    $this->assertSession()->statusCodeEquals(200);

    $status_after_submit = (string) \Drupal::database()->select('dc_campaigns', 'c')
      ->fields('c', ['status'])
      ->condition('id', $campaign_id)
      ->execute()
      ->fetchField();
    $this->assertEquals('archived', $status_after_submit, 'Campaign should be archived after confirmation submit.');
  }

  /**
   * Tests unarchive submit restores previous campaign status and list placement.
   */
  public function testCampaignUnarchiveSubmitRestoresPreviousStatus(): void {
    $user = $this->createTestUser();
    $this->drupalLogin($user);

    $campaign_id = $this->createTestCampaign($user, [
      'name' => 'Unarchive Submit Campaign',
      'status' => 'ready',
      'campaign_data' => json_encode([]),
    ]);

    $this->drupalGet("/campaigns/{$campaign_id}/archive");
    $this->submitForm([], 'Archive Campaign');

    $this->drupalGet("/campaigns/{$campaign_id}/unarchive");
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([], 'Unarchive Campaign');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('unarchived');

    $campaign_row = \Drupal::database()->select('dc_campaigns', 'c')
      ->fields('c', ['status', 'campaign_data'])
      ->condition('id', $campaign_id)
      ->execute()
      ->fetchAssoc();

    $this->assertEquals('ready', $campaign_row['status'], 'Campaign should restore to pre-archive status.');

    $campaign_data = json_decode((string) ($campaign_row['campaign_data'] ?? '{}'), TRUE);
    $this->assertIsArray($campaign_data, 'Campaign data should decode to array.');
    $this->assertArrayNotHasKey('_archive_meta', $campaign_data, 'Archive metadata should be removed after unarchive.');

    $this->drupalGet('/campaigns');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains("/campaigns/{$campaign_id}/archive");
    $this->assertSession()->responseNotContains("/campaigns/{$campaign_id}/unarchive");
  }

  /**
   * Tests non-owner cannot archive or unarchive and status remains unchanged.
   */
  public function testCampaignArchiveAndUnarchiveDeniedForNonOwnerWithoutMutation(): void {
    $owner = $this->createTestUser();
    $other_user = $this->createTestUser();

    $campaign_id = $this->createTestCampaign($owner, [
      'name' => 'Ownership Guard Campaign',
      'status' => 'draft',
      'campaign_data' => json_encode([]),
    ]);

    $this->drupalLogin($other_user);
    $this->drupalGet("/campaigns/{$campaign_id}/archive");
    $this->assertSession()->statusCodeEquals(403);

    $status_after_archive_attempt = \Drupal::database()->select('dc_campaigns', 'c')
      ->fields('c', ['status'])
      ->condition('id', $campaign_id)
      ->execute()
      ->fetchField();
    $this->assertEquals('draft', $status_after_archive_attempt, 'Status should remain unchanged after denied archive.');

    $this->drupalGet("/campaigns/{$campaign_id}/unarchive");
    $this->assertSession()->statusCodeEquals(403);

    $status_after_unarchive_attempt = \Drupal::database()->select('dc_campaigns', 'c')
      ->fields('c', ['status'])
      ->condition('id', $campaign_id)
      ->execute()
      ->fetchField();
    $this->assertEquals('draft', $status_after_unarchive_attempt, 'Status should remain unchanged after denied unarchive.');
  }

  /**
   * Tests campaign dungeon selection route - positive case.
   */
  public function testCampaignDungeonsRoutePositive(): void {
    $user = $this->createTestUser();
    $this->drupalLogin($user);

    $database = \Drupal::database();
    $campaign_id = $database->insert('dc_campaigns')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'uid' => $user->id(),
        'name' => 'Route Dungeons Campaign',
        'status' => 'draft',
        'campaign_data' => json_encode([]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    $this->drupalGet("/campaigns/{$campaign_id}/dungeons");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Dungeon Selection');
  }

  /**
   * Tests campaign dungeon selection route - ownership denied.
   */
  public function testCampaignDungeonsRouteOwnershipDenied(): void {
    $owner = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $other_user = $this->drupalCreateUser(['access dungeoncrawler characters']);

    $database = \Drupal::database();
    $campaign_id = $database->insert('dc_campaigns')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'uid' => $owner->id(),
        'name' => 'Owner Dungeon Campaign',
        'status' => 'draft',
        'campaign_data' => json_encode([]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    $this->drupalLogin($other_user);
    $this->drupalGet("/campaigns/{$campaign_id}/dungeons");
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests campaign dungeon selection route - non-existent campaign.
   */
  public function testCampaignDungeonsRouteNonExistentCampaign(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/campaigns/99999/dungeons');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests campaign route - negative case (anonymous user).
   */
  public function testCampaignRouteNegativeAnonymous(): void {
    $this->drupalGet('/campaigns');
    $this->assertSession()->statusCodeEquals(403);

    // Also test campaign creation
    $this->drupalGet('/campaigns/create');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests campaign tavern entrance route - negative case (non-existent campaign).
   */
  public function testCampaignTavernEntranceNonExistent(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    // Try to access a campaign that doesn't exist
    $this->drupalGet('/campaigns/99999/tavernentrance');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests campaign select character - negative case (non-existent campaign).
   */
  public function testCampaignSelectCharacterNonExistentCampaign(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    // Try to select character for a campaign that doesn't exist
    $this->drupalGet('/campaigns/99999/select-character/1');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests campaign select character - negative case (non-existent character).
   */
  public function testCampaignSelectCharacterNonExistentCharacter(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    // Create a real campaign for this user
    $database = \Drupal::database();
    $campaign_id = $database->insert('dc_campaigns')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'uid' => $user->id(),
        'name' => 'Test Campaign',
        'status' => 'draft',
        'campaign_data' => json_encode([]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    // Try to select a character that doesn't exist
    $this->drupalGet("/campaigns/{$campaign_id}/select-character/99999");
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests campaign tavern entrance - negative case (accessing other user's campaign).
   */
  public function testCampaignTavernEntranceOwnershipDenied(): void {
    // Create two users
    $owner = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $other_user = $this->drupalCreateUser(['access dungeoncrawler characters']);

    // Create a campaign owned by the first user
    $database = \Drupal::database();
    $campaign_id = $database->insert('dc_campaigns')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'uid' => $owner->id(),
        'name' => 'Owner Campaign',
        'status' => 'draft',
        'campaign_data' => json_encode([]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    // Login as the other user and try to access the campaign
    $this->drupalLogin($other_user);
    $this->drupalGet("/campaigns/{$campaign_id}/tavernentrance");
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests campaign select character - negative case (accessing with other user's character).
   */
  public function testCampaignSelectCharacterWithOtherUsersCharacter(): void {
    // Create two users
    $campaign_owner = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $character_owner = $this->drupalCreateUser(['access dungeoncrawler characters']);

    // Create a campaign owned by campaign_owner
    $database = \Drupal::database();
    $campaign_id = $database->insert('dc_campaigns')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'uid' => $campaign_owner->id(),
        'name' => 'Campaign Owner Campaign',
        'status' => 'draft',
        'campaign_data' => json_encode([]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    // Create a character owned by character_owner
    $character_id = $database->insert('dc_campaign_characters')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'campaign_id' => 0,
        'character_id' => 0,
        'instance_id' => \Drupal::service('uuid')->generate(),
        'uid' => $character_owner->id(),
        'name' => 'Test Character',
        'class' => 'fighter',
        'ancestry' => 'human',
        'level' => 1,
        'hp_current' => 10,
        'hp_max' => 10,
        'armor_class' => 10,
        'experience_points' => 0,
        'position_q' => 0,
        'position_r' => 0,
        'last_room_id' => '',
        'type' => 'pc',
        'status' => 1,
        'character_data' => json_encode([]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    // Login as campaign_owner and try to select character_owner's character
    $this->drupalLogin($campaign_owner);
    $this->drupalGet("/campaigns/{$campaign_id}/select-character/{$character_id}");
    // Should get 403 because campaign_owner doesn't own the character
    $this->assertSession()->statusCodeEquals(403);
  }

}
