<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests CampaignController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 */
class CampaignControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests campaign list display - positive case.
   */
  public function testCampaignListDisplayPositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/campaigns');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('My Campaigns');
    $this->assertSession()->linkByHrefExists('/characters');
  }

  /**
   * Tests campaign list access control - negative case (no permission).
   */
  public function testCampaignListAccessControlNegative(): void {
    $this->drupalGet('/campaigns');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests campaign creation page - positive case.
   */
  public function testCampaignCreationPagePositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/campaigns/create');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Create Campaign');
  }

  /**
   * Tests campaign creation form submit succeeds.
   */
  public function testCampaignCreationSubmitPositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/campaigns/create');
    $this->assertSession()->statusCodeEquals(200);

    $this->submitForm([
      'name' => 'Post Route Campaign',
      'theme' => 'classic_dungeon',
      'difficulty' => 'normal',
    ], 'Create Campaign');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Tavern Entrance');
  }

  /**
   * Tests campaign creation access - negative case (anonymous user).
   */
  public function testCampaignCreationAccessNegative(): void {
    $this->drupalGet('/campaigns/create');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests tavern entrance - negative case (non-existent campaign).
   */
  public function testTavernEntranceNonExistentCampaign(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/campaigns/99999/tavernentrance');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests tavern entrance - negative case (other user's campaign).
   */
  public function testTavernEntranceOwnershipCheck(): void {
    // Create two users
    $owner = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $other_user = $this->drupalCreateUser(['access dungeoncrawler characters']);

    // Create a campaign for owner
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

    // Try to access as other_user
    $this->drupalLogin($other_user);
    $this->drupalGet("/campaigns/{$campaign_id}/tavernentrance");
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests tavern entrance groups characters by their attached campaign.
   */
  public function testTavernEntranceGroupsCharactersByCampaignAttachment(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $database = \Drupal::database();
    $uuid = \Drupal::service('uuid');
    $now = time();

    $current_campaign_id = $database->insert('dc_campaigns')
      ->fields([
        'uuid' => $uuid->generate(),
        'uid' => $user->id(),
        'name' => 'Current Campaign',
        'status' => 'draft',
        'campaign_data' => json_encode([]),
        'created' => $now,
        'changed' => $now,
      ])
      ->execute();

    $other_campaign_id = $database->insert('dc_campaigns')
      ->fields([
        'uuid' => $uuid->generate(),
        'uid' => $user->id(),
        'name' => 'Frozen Keep',
        'status' => 'draft',
        'campaign_data' => json_encode([]),
        'created' => $now,
        'changed' => $now,
      ])
      ->execute();

    $insert_character = function (string $name, int $campaign_id) use ($database, $uuid, $user, $now): void {
      $database->insert('dc_campaign_characters')
        ->fields([
          'uuid' => $uuid->generate(),
          'campaign_id' => $campaign_id,
          'character_id' => 0,
          'instance_id' => $uuid->generate(),
          'uid' => $user->id(),
          'name' => $name,
          'class' => 'fighter',
          'ancestry' => 'human',
          'level' => 1,
          'hp_current' => 10,
          'hp_max' => 10,
          'armor_class' => 16,
          'experience_points' => 0,
          'position_q' => 0,
          'position_r' => 0,
          'last_room_id' => '',
          'type' => 'pc',
          'status' => 1,
          'character_data' => json_encode(['step' => 8, 'personality' => ['alignment' => 'Neutral Good']]),
          'created' => $now,
          'changed' => $now,
        ])
        ->execute();
    };

    $insert_character('Current Hero', (int) $current_campaign_id);
    $insert_character('Free Agent', 0);
    $insert_character('Ice Veteran', (int) $other_campaign_id);

    $this->drupalGet("/campaigns/{$current_campaign_id}/tavernentrance");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Already attached to this campaign');
    $this->assertSession()->pageTextContains('Unattached characters');
    $this->assertSession()->pageTextContains('Attached to Frozen Keep');
    $this->assertSession()->pageTextContains('Current Hero');
    $this->assertSession()->pageTextContains('Free Agent');
    $this->assertSession()->pageTextContains('Ice Veteran');

    $content = $this->getSession()->getPage()->getContent();
    $this->assertLessThan(strpos($content, 'Unattached characters'), strpos($content, 'Already attached to this campaign'));
    $this->assertLessThan(strpos($content, 'Attached to Frozen Keep'), strpos($content, 'Unattached characters'));
  }

  /**
   * Tests select character - negative case (non-existent character).
   */
  public function testSelectCharacterNonExistentCharacter(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    // Create a real campaign
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

    // Try to select non-existent character
    $this->drupalGet("/campaigns/{$campaign_id}/select-character/99999");
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests select character - negative case (other user's character).
   */
  public function testSelectCharacterOwnershipCheck(): void {
    $campaign_owner = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $character_owner = $this->drupalCreateUser(['access dungeoncrawler characters']);

    // Create campaign for campaign_owner
    $database = \Drupal::database();
    $campaign_id = $database->insert('dc_campaigns')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'uid' => $campaign_owner->id(),
        'name' => 'Test Campaign',
        'status' => 'draft',
        'campaign_data' => json_encode([]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    // Create character for character_owner
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

    // Try to select character_owner's character as campaign_owner
    $this->drupalLogin($campaign_owner);
    $this->drupalGet("/campaigns/{$campaign_id}/select-character/{$character_id}");
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests select character - negative case (non-existent campaign).
   */
  public function testSelectCharacterNonExistentCampaign(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/campaigns/99999/select-character/1');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests campaign archive uses standard ConfirmFormBase confirmation only.
   */
  public function testCampaignArchiveCheckboxConfirmation(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $database = \Drupal::database();
    $campaign_id = $database->insert('dc_campaigns')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'uid' => $user->id(),
        'name' => 'Archive Me',
        'status' => 'draft',
        'campaign_data' => json_encode([]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    $this->drupalGet("/campaigns/{$campaign_id}/archive");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('I confirm I want to archive this campaign.');

    $this->submitForm([], 'Archive Campaign');

    $status_after_success = $database->select('dc_campaigns', 'c')
      ->fields('c', ['status'])
      ->condition('id', $campaign_id)
      ->execute()
      ->fetchField();
    $this->assertEquals('archived', $status_after_success);
  }

  /**
   * Tests archived campaigns appear in a dedicated archived section.
   */
  public function testArchivedCampaignSectionOnCampaignList(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $database = \Drupal::database();
    $archived_campaign_id = $database->insert('dc_campaigns')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'uid' => $user->id(),
        'name' => 'Visible Campaign',
        'status' => 'draft',
        'campaign_data' => json_encode([]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    $archived_campaign_id = $database->insert('dc_campaigns')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'uid' => $user->id(),
        'name' => 'Hidden Campaign',
        'status' => 'archived',
        'campaign_data' => json_encode([]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    $this->drupalGet('/campaigns');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Visible Campaign');
    $this->assertSession()->pageTextContains('Archived Campaigns');
    $this->assertSession()->pageTextContains('Hidden Campaign');
    $this->assertSession()->linkByHrefExists("/campaigns/{$archived_campaign_id}/unarchive");
  }

  /**
   * Tests archived campaigns can be unarchived via the unarchive path.
   */
  public function testCampaignUnarchivePath(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $database = \Drupal::database();
    $campaign_id = $database->insert('dc_campaigns')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'uid' => $user->id(),
        'name' => 'Unarchive Me',
        'status' => 'archived',
        'campaign_data' => json_encode([]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    $this->drupalGet('/campaigns');
    $this->assertSession()->pageTextContains('Unarchive Me');

    $this->drupalGet("/campaigns/{$campaign_id}/unarchive");
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([], 'Unarchive Campaign');

    $status_after_unarchive = $database->select('dc_campaigns', 'c')
      ->fields('c', ['status'])
      ->condition('id', $campaign_id)
      ->execute()
      ->fetchField();
    $this->assertEquals('draft', $status_after_unarchive);

    $this->drupalGet('/campaigns');
    $this->assertSession()->pageTextContains('Unarchive Me');
  }

  /**
   * Tests unarchive restores the campaign's previous status.
   */
  public function testCampaignUnarchiveRestoresPreviousStatus(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $database = \Drupal::database();
    $campaign_id = $database->insert('dc_campaigns')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'uid' => $user->id(),
        'name' => 'Restore Status Campaign',
        'status' => 'ready',
        'campaign_data' => json_encode([]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    $this->drupalGet("/campaigns/{$campaign_id}/archive");
    $this->submitForm([], 'Archive Campaign');

    $status_after_archive = $database->select('dc_campaigns', 'c')
      ->fields('c', ['status'])
      ->condition('id', $campaign_id)
      ->execute()
      ->fetchField();
    $this->assertEquals('archived', $status_after_archive);

    $this->drupalGet("/campaigns/{$campaign_id}/unarchive");
    $this->submitForm([], 'Unarchive Campaign');

    $status_after_unarchive = $database->select('dc_campaigns', 'c')
      ->fields('c', ['status'])
      ->condition('id', $campaign_id)
      ->execute()
      ->fetchField();
    $this->assertEquals('ready', $status_after_unarchive);
  }

}
