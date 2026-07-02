<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional\Routes;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests campaign routes in the dungeon crawler module.
 *
 * @group dungeoncrawler_content
 * @group routes
 */
class CampaignRoutesTest extends BrowserTestBase {

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
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
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
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
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
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $campaign_id = $this->createCampaignForUser($user->id());

    $this->drupalGet("/campaigns/{$campaign_id}/tavernentrance");
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests campaign tavern entrance route - negative case (non-numeric ID).
   */
  public function testCampaignTavernEntranceRouteNegative(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/campaigns/invalid/tavernentrance');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests campaign select character route - positive case.
   */
  public function testCampaignSelectCharacterRoutePositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $campaign_id = $this->createCampaignForUser($user->id());
    $character_id = $this->createCharacterForUser($user->id());

    $this->drupalGet("/campaigns/{$campaign_id}/select-character/{$character_id}");
    $status = $this->getSession()->getStatusCode();
    $this->assertContains($status, [200, 302], 'Select-character route should succeed for owned campaign/character.');
  }

  /**
   * Tests campaign select character route - negative case (invalid IDs).
   */
  public function testCampaignSelectCharacterRouteNegative(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/campaigns/invalid/select-character/invalid');
    $this->assertSession()->statusCodeEquals(404);
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
   * Create a campaign row for route tests.
   */
  private function createCampaignForUser(int $uid): int {
    return (int) $this->container->get('database')->insert('dc_campaigns')
      ->fields([
        'uuid' => $this->container->get('uuid')->generate(),
        'uid' => $uid,
        'name' => 'Route Test Campaign',
        'status' => 'draft',
        'campaign_data' => json_encode([]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();
  }

  /**
   * Create a character row for route tests.
   */
  private function createCharacterForUser(int $uid): int {
    return (int) $this->container->get('database')->insert('dc_characters')
      ->fields([
        'uuid' => $this->container->get('uuid')->generate(),
        'uid' => $uid,
        'name' => 'Route Test Character',
        'level' => 1,
        'ancestry' => 'human',
        'class' => 'fighter',
        'character_data' => json_encode([]),
        'status' => 1,
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();
  }

}
