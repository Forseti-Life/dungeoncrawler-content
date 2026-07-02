<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests WorldController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 */
class WorldControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests world page display - positive case.
   */
  public function testWorldPageDisplayPositive(): void {
    $this->drupalGet('/world');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('The Living Dungeon');
  }

  /**
   * Tests world page access for authenticated user without special permissions.
   */
  public function testWorldPagePublicAccessNegative(): void {
    $user = $this->drupalCreateUser([]);
    $this->drupalLogin($user);

    $this->drupalGet('/world');
    $this->assertSession()->statusCodeEquals(200);
  }

}
