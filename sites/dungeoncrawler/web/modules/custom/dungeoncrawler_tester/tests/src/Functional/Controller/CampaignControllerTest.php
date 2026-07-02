<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional\Controller;

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

    $this->createCampaignForUser($user->id(), 'Owned Campaign');
    $other_user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->createCampaignForUser($other_user->id(), 'Other User Campaign');

    $this->drupalGet('/campaigns');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('My Campaigns');
    $this->assertSession()->pageTextContains('Owned Campaign');
    $this->assertSession()->pageTextNotContains('Other User Campaign');
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
    $this->assertSession()->elementExists('css', 'input[name="name"]');
  }

  /**
   * Tests campaign creation access - negative case (anonymous user).
   */
  public function testCampaignCreationAccessNegative(): void {
    $this->drupalGet('/campaigns/create');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Create a campaign row for controller tests.
   */
  private function createCampaignForUser(int $uid, string $name): int {
    return (int) $this->container->get('database')->insert('dc_campaigns')
      ->fields([
        'uuid' => $this->container->get('uuid')->generate(),
        'uid' => $uid,
        'name' => $name,
        'difficulty' => 'normal',
        'theme' => 'classic',
        'status' => 'active',
        'campaign_data' => json_encode(['state' => []]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();
  }

}
