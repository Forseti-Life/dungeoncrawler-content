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
    $this->assertSession()->linkExists('I Just Want to Play');
  }

  /**
   * Tests campaign setup starts blank instead of auto-resuming an old draft.
   */
  public function testCharacterSetupCampaignFlowDoesNotAutoResumeExistingDraft(): void {
    $user = $this->drupalCreateUser(['create dungeoncrawler characters', 'access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $campaign_id = \Drupal::database()->insert('dc_campaigns')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'uid' => $user->id(),
        'name' => 'Blank Setup Campaign',
        'status' => 'draft',
        'campaign_data' => json_encode([]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    $existing_character_data = [
      'step' => 3,
      'name' => 'Existing Draft Character',
      'concept' => 'Should not auto-load into new setup.',
    ];
    \Drupal::database()->insert('dc_campaign_characters')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'campaign_id' => 0,
        'character_id' => 0,
        'instance_id' => \Drupal::service('uuid')->generate(),
        'uid' => $user->id(),
        'name' => 'Existing Draft Character',
        'level' => 1,
        'ancestry' => '',
        'class' => '',
        'hp_current' => 0,
        'hp_max' => 0,
        'armor_class' => 10,
        'experience_points' => 0,
        'position_q' => 0,
        'position_r' => 0,
        'last_room_id' => '',
        'character_data' => json_encode($existing_character_data),
        'status' => 0,
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    $this->drupalGet("/charactersetup?campaign_id={$campaign_id}");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals('Character Name', '');
    $this->assertSession()->pageTextNotContains('Existing Draft Character');
    $this->assertSession()->addressMatches('#/charactersetup\?campaign_id=' . $campaign_id . '$#');
  }

  /**
   * Tests quick play is hidden once setup is editing a specific character.
   */
  public function testCharacterSetupHidesQuickPlayWhenCharacterAlreadySelected(): void {
    $user = $this->drupalCreateUser(['create dungeoncrawler characters', 'access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $campaign_id = \Drupal::database()->insert('dc_campaigns')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'uid' => $user->id(),
        'name' => 'Character Setup Campaign',
        'status' => 'draft',
        'campaign_data' => json_encode([]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    $character_id = \Drupal::database()->insert('dc_campaign_characters')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'campaign_id' => 0,
        'character_id' => 0,
        'instance_id' => \Drupal::service('uuid')->generate(),
        'uid' => $user->id(),
        'name' => 'Selected Draft Character',
        'level' => 1,
        'ancestry' => '',
        'class' => '',
        'hp_current' => 0,
        'hp_max' => 0,
        'armor_class' => 10,
        'experience_points' => 0,
        'position_q' => 0,
        'position_r' => 0,
        'last_room_id' => '',
        'character_data' => json_encode([
          'step' => 1,
          'name' => 'Selected Draft Character',
          'concept' => 'Existing in-progress setup.',
        ]),
        'status' => 0,
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    $this->drupalGet("/charactersetup?step=1&character_id={$character_id}&campaign_id={$campaign_id}");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('I Just Want to Play');
    $this->assertSession()->buttonExists('Next →');
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
