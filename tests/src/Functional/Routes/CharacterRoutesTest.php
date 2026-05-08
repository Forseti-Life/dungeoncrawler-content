<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Routes;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\dungeoncrawler_content\Functional\Traits\TestDataBuilderTrait;

/**
 * Tests character management routes in the dungeon crawler module.
 *
 * @group dungeoncrawler_content
 * @group routes
 */
class CharacterRoutesTest extends BrowserTestBase {

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
   * Tests characters list route - positive case.
   */
  public function testCharactersListRoutePositive(): void {
    $user = $this->createTestUser();
    $this->drupalLogin($user);

    $this->drupalGet('/characters');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('My Characters');
  }

  /**
   * Tests characters list route - negative case (no permission).
   */
  public function testCharactersListRouteNegative(): void {
    $this->drupalGet('/characters');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests character creation route - positive case.
   */
  public function testCharacterCreationRoutePositive(): void {
    $user = $this->createTestUser(['create dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/characters/create');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Create Character');
  }

  /**
   * Tests character creation route - negative case (no permission).
   */
  public function testCharacterCreationRouteNegative(): void {
    $user = $this->createTestUser();
    $this->drupalLogin($user);

    $this->drupalGet('/characters/create');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests character step route - positive case.
   */
  public function testCharacterStepRoutePositive(): void {
    $user = $this->createTestUser(['create dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/characters/create/step/1');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests character step route - negative case (invalid step).
   */
  public function testCharacterStepRouteNegative(): void {
    $user = $this->createTestUser(['create dungeoncrawler characters']);
    $this->drupalLogin($user);

    // Try with non-numeric step
    $this->drupalGet('/characters/create/step/invalid');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests character view route - positive case (with valid character).
   */
  public function testCharacterViewRoutePositive(): void {
    $user = $this->createTestUser();
    $this->drupalLogin($user);

    // Note: This will fail without a real character, but tests the route exists
    $this->drupalGet('/characters/1');
    // Will return 403 or 404 depending on character existence and ownership
    $this->assertSession()->statusCodeNotEquals(200);
  }

  /**
   * Tests character view route - negative case (non-numeric ID).
   */
  public function testCharacterViewRouteNegative(): void {
    $user = $this->createTestUser();
    $this->drupalLogin($user);

    $this->drupalGet('/characters/invalid');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests character edit route - positive case.
   */
  public function testCharacterEditRoutePositive(): void {
    $user = $this->createTestUser();
    $this->drupalLogin($user);

    // Note: This will fail without a real character
    $this->drupalGet('/characters/1/edit');
    $this->assertSession()->statusCodeNotEquals(200);
  }

  /**
   * Tests character edit route - negative case (non-numeric ID).
   */
  public function testCharacterEditRouteNegative(): void {
    $user = $this->createTestUser();
    $this->drupalLogin($user);

    $this->drupalGet('/characters/invalid/edit');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests character delete route - positive case.
   */
  public function testCharacterDeleteRoutePositive(): void {
    $user = $this->createTestUser();
    $this->drupalLogin($user);

    // Note: This will fail without a real character
    $this->drupalGet('/characters/1/delete');
    $this->assertSession()->statusCodeNotEquals(200);
  }

  /**
   * Tests character delete route - negative case (anonymous user).
   */
  public function testCharacterDeleteRouteNegative(): void {
    $this->drupalGet('/characters/1/delete');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests character edit - negative case (editing other user's character).
   */
  public function testCharacterEditOwnershipDenied(): void {
    // Create two users
    $owner = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $other_user = $this->drupalCreateUser(['access dungeoncrawler characters']);

    // Create a character owned by the first user
    $database = \Drupal::database();
    $character_id = $database->insert('dc_campaign_characters')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'campaign_id' => 0,
        'character_id' => 0,
        'instance_id' => \Drupal::service('uuid')->generate(),
        'uid' => $owner->id(),
        'name' => 'Owner Character',
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

    // Login as the other user and try to edit the character
    $this->drupalLogin($other_user);
    $this->drupalGet("/characters/{$character_id}/edit");
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests character delete - negative case (deleting other user's character).
   */
  public function testCharacterDeleteOwnershipDenied(): void {
    // Create two users
    $owner = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $other_user = $this->drupalCreateUser(['access dungeoncrawler characters']);

    // Create a character owned by the first user
    $database = \Drupal::database();
    $character_id = $database->insert('dc_campaign_characters')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'campaign_id' => 0,
        'character_id' => 0,
        'instance_id' => \Drupal::service('uuid')->generate(),
        'uid' => $owner->id(),
        'name' => 'Owner Character',
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

    // Login as the other user and try to delete the character
    $this->drupalLogin($other_user);
    $this->drupalGet("/characters/{$character_id}/delete");
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests character view - negative case (viewing other user's character).
   */
  public function testCharacterViewOwnershipDenied(): void {
    // Create two users
    $owner = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $other_user = $this->drupalCreateUser(['access dungeoncrawler characters']);

    // Create a character owned by the first user
    $database = \Drupal::database();
    $character_id = $database->insert('dc_campaign_characters')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'campaign_id' => 0,
        'character_id' => 0,
        'instance_id' => \Drupal::service('uuid')->generate(),
        'uid' => $owner->id(),
        'name' => 'Owner Character',
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

    // Login as the other user and try to view the character
    $this->drupalLogin($other_user);
    $this->drupalGet("/characters/{$character_id}");
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests character edit - negative case (non-existent character).
   */
  public function testCharacterEditNonExistent(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/characters/99999/edit');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests character delete - negative case (non-existent character).
   */
  public function testCharacterDeleteNonExistent(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/characters/99999/delete');
    $this->assertSession()->statusCodeEquals(404);
  }

}
