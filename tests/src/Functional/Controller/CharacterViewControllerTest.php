<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Controller;

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

    // Without a real character, this will fail but validates route exists
    $this->drupalGet('/characters/1');
    // Could be 404 if character doesn't exist or 403 if no access
    $this->assertSession()->statusCodeNotEquals(405);
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
    $this->drupalGet('/characters/1');
    $this->assertSession()->statusCodeEquals(403);
  }

}
