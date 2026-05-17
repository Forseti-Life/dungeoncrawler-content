<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests CharacterCreationStepController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 */
class CharacterCreationStepControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests character creation start - positive case.
   */
  public function testCharacterCreationStartPositive(): void {
    $user = $this->drupalCreateUser(['create dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/characters/create');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Create Character');
  }

  /**
   * Tests character creation access control - negative case.
   */
  public function testCharacterCreationAccessControlNegative(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/characters/create');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests character creation step - positive case.
   */
  public function testCharacterCreationStepPositive(): void {
    $user = $this->drupalCreateUser(['create dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/characters/create/step/1');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('GM Character Guide');
    $this->assertSession()->pageTextContains('Send to GM');
  }

  /**
   * Tests embedded character creation step hides the standalone GM shell.
   */
  public function testCharacterCreationStepEmbeddedMode(): void {
    $user = $this->drupalCreateUser(['create dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/characters/create/step/1?embedded=1&charactersetup=1');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('GM Character Guide');
    $this->assertSession()->pageTextContains('Step 1 of 8');
  }

  /**
   * Tests quick-play shortcut appears on campaign-scoped setup pages.
   */
  public function testCharacterSetupShowsQuickPlayButtonForCampaignFlow(): void {
    $user = $this->drupalCreateUser(['create dungeoncrawler characters', 'access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $campaign_id = \Drupal::database()->insert('dc_campaigns')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'uid' => $user->id(),
        'name' => 'Quick Play Campaign',
        'status' => 'draft',
        'campaign_data' => json_encode([]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    $this->drupalGet("/charactersetup?campaign_id={$campaign_id}");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->buttonExists('I Just Want to Play');
  }

  /**
   * Tests character creation step with invalid step - negative case.
   */
  public function testCharacterCreationStepNegative(): void {
    $user = $this->drupalCreateUser(['create dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/characters/create/step/invalid');
    $this->assertSession()->statusCodeEquals(404);
  }

}
