<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional\Routes;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests character management routes in the dungeon crawler module.
 *
 * @group dungeoncrawler_content
 * @group routes
 */
class CharacterRoutesTest extends BrowserTestBase {

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
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
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
    $user = $this->drupalCreateUser(['create dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/characters/create');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Create Character');
  }

  /**
   * Tests character creation route - negative case (no permission).
   */
  public function testCharacterCreationRouteNegative(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/characters/create');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests character step route - positive case.
   */
  public function testCharacterStepRoutePositive(): void {
    $user = $this->drupalCreateUser(['create dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/characters/create/step/1');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests character step route - negative case (invalid step).
   */
  public function testCharacterStepRouteNegative(): void {
    $user = $this->drupalCreateUser(['create dungeoncrawler characters']);
    $this->drupalLogin($user);

    // Try with non-numeric step
    $this->drupalGet('/characters/create/step/invalid');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests character view route - positive case (with valid character).
   */
  public function testCharacterViewRoutePositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $character_id = $this->createCharacterForUser($user->id());

    $this->drupalGet("/characters/{$character_id}");
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests character view route - negative case (non-numeric ID).
   */
  public function testCharacterViewRouteNegative(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/characters/invalid');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests character edit route - positive case.
   */
  public function testCharacterEditRoutePositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $character_id = $this->createCharacterForUser($user->id());

    $this->drupalGet("/characters/{$character_id}/edit");
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests character edit route - negative case (non-numeric ID).
   */
  public function testCharacterEditRouteNegative(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/characters/invalid/edit');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests character delete route - positive case.
   */
  public function testCharacterDeleteRoutePositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $character_id = $this->createCharacterForUser($user->id());

    $this->drupalGet("/characters/{$character_id}/delete");
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests character delete route - negative case (anonymous user).
   */
  public function testCharacterDeleteRouteNegative(): void {
    $this->drupalGet('/characters/1/delete');
    $this->assertSession()->statusCodeEquals(403);
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
