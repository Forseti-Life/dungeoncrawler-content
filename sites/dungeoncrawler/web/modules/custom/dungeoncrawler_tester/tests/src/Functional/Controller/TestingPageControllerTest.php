<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests TestingPageController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 */
class TestingPageControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests testing page display - positive case.
   */
  public function testTestingPageDisplayPositive(): void {
    $this->drupalGet('/testing');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Testing Page');
    $this->assertSession()->pageTextContains('This is a test page stub');
  }

  /**
   * Tests testing page access for authenticated user without special permissions.
   */
  public function testTestingPagePublicAccessNegative(): void {
    $user = $this->drupalCreateUser([]);
    $this->drupalLogin($user);

    $this->drupalGet('/testing');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests testing page caching - negative case (should not cache).
   */
  public function testTestingPageCachingNegative(): void {
    $this->drupalGet('/testing');
    $this->assertSession()->statusCodeEquals(200);

    // Testing page should have max-age 0 (no caching)
    $cacheControl = $this->getSession()->getResponseHeader('Cache-Control');
    $this->assertStringContainsString('max-age=0', $cacheControl ?: '');
  }

}
