<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests AboutController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 */
class AboutControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests about page display - positive case.
   */
  public function testAboutPageDisplayPositive(): void {
    $this->drupalGet('/about');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('About Dungeon Crawler Life');
  }

  /**
   * Tests about page accessibility - negative case (missing content).
   */
  public function testAboutPageAccessibilityNegative(): void {
    $this->drupalGet('/about');
    $this->assertSession()->statusCodeEquals(200);
    
    // Negative test: ensure we're not showing error content
    $this->assertSession()->pageTextNotContains('Error');
    $this->assertSession()->pageTextNotContains('Page not found');
  }

}
