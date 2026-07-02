<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Controller;

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
    
    // Verify expected content structure/blocks.
    $this->assertSession()->pageTextContains('Testing Page');
    $this->assertSession()->pageTextContains('This is a test page stub');
    $this->assertSession()->pageTextContains('dungeon crawler module');
  }

  /**
   * Tests testing page cache headers - should have max-age=0.
   */
  public function testTestingPageCacheHeaders(): void {
    $this->drupalGet('/testing');
    $this->assertSession()->statusCodeEquals(200);

    // Testing page should have max-age 0 (no caching) per controller.
    $cache_control = $this->getSession()->getResponseHeader('Cache-Control');
    $this->assertNotNull($cache_control, 'Cache-Control header should be present');
    $this->assertStringContainsString('max-age=0', $cache_control);
    
    // Page should be publicly accessible.
    $this->assertSession()->statusCodeNotEquals(403);
  }

}
