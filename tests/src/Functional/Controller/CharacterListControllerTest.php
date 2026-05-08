<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Controller;

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

    $this->drupalGet('/characters');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('My Characters');
  }

  /**
   * Tests character list access control - negative case (no permission).
   */
  public function testCharacterListAccessControlNegative(): void {
    $this->drupalGet('/characters');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests character list for user with no characters - positive case.
   */
  public function testCharacterListEmptyPositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/characters');
    $this->assertSession()->statusCodeEquals(200);
    // Should still display page even with no characters
  }

}
