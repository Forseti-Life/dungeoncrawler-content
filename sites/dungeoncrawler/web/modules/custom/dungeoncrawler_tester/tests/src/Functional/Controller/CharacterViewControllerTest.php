<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests CharacterViewController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 */
class CharacterViewControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests character view with valid character - positive case.
   *
   * Note: This test requires an actual character entity to exist.
   */
  public function testCharacterViewPositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $character_id = $this->createCharacterForUser($user->id(), 'View Test Character');

    $this->drupalGet("/characters/{$character_id}");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('View Test Character');
  }

  /**
   * Tests character view with invalid ID - negative case.
   */
  public function testCharacterViewNegativeInvalidId(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/characters/invalid');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests character view without authentication - negative case.
   */
  public function testCharacterViewNegativeNoAuth(): void {
    $owner = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $character_id = $this->createCharacterForUser($owner->id(), 'Private Character');

    $this->drupalGet("/characters/{$character_id}");
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Create a character row for view tests.
   */
  private function createCharacterForUser(int $uid, string $name): int {
    return (int) $this->container->get('database')->insert('dc_characters')
      ->fields([
        'uuid' => $this->container->get('uuid')->generate(),
        'uid' => $uid,
        'name' => $name,
        'level' => 1,
        'ancestry' => 'human',
        'class' => 'fighter',
        'character_data' => json_encode([
          'name' => $name,
          'level' => 1,
          'class' => 'fighter',
          'abilities' => [
            'str' => 16,
            'dex' => 12,
            'con' => 14,
            'int' => 10,
            'wis' => 12,
            'cha' => 8,
          ],
          'hit_points' => [
            'max' => 20,
            'current' => 20,
            'temp' => 0,
          ],
        ]),
        'status' => 1,
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();
  }

}
