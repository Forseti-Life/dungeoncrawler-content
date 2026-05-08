<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests CharacterCreationController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 */
class CharacterCreationControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests character creation controller access - positive case.
   */
  public function testCharacterCreationAccessPositive(): void {
    $user = $this->drupalCreateUser(['create dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/characters/create');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests character creation controller without permission - negative case.
   */
  public function testCharacterCreationAccessNegative(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/characters/create');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests character creation controller anonymous access - negative case.
   */
  public function testCharacterCreationAnonymousAccessNegative(): void {
    $this->drupalGet('/characters/create');
    $this->assertSession()->statusCodeEquals(403);
  }

}
