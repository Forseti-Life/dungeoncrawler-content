<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests CharacterListController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 */
class CharacterListControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests character list display - positive case.
   */
  public function testCharacterListDisplayPositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->createCharacterForUser($user->id(), 'Owned Character');
    $other_user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->createCharacterForUser($other_user->id(), 'Other User Character');

    $this->drupalGet('/characters');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('My Characters');
    $this->assertSession()->pageTextContains('Owned Character');
    $this->assertSession()->pageTextNotContains('Other User Character');
  }

  /**
   * Tests character list access control - negative case (no permission).
   */
  public function testCharacterListAccessControlNegative(): void {
    $this->drupalGet('/characters');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Create a character row for list tests.
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
          'hit_points' => ['current' => 20, 'max' => 20],
        ]),
        'status' => 1,
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();
  }

}
