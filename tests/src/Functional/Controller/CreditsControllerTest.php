<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests CreditsController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 */
class CreditsControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests credits page display - positive case.
   */
  public function testCreditsPageDisplayPositive(): void {
    $this->drupalGet('/credits');
    $this->assertSession()->statusCodeEquals(200);
    
    // Verify expected content structure/blocks.
    $this->assertSession()->pageTextContains('Credits');
    
    // Verify key attribution sections exist (from controller).
    // The controller uses a theme 'credits_page' which should render credits data.
    $this->assertSession()->pageTextContains('PixiJS');
  }

  /**
   * Tests credits page cache headers.
   */
  public function testCreditsPageCacheHeaders(): void {
    $this->drupalGet('/credits');
    $this->assertSession()->statusCodeEquals(200);
    
    // Verify cache headers are properly configured.
    $this->assertSession()->responseHeaderExists('X-Drupal-Cache-Contexts');
    
    // Credits page has max-age of 3600 (1 hour) per controller.
    $cache_control = $this->getSession()->getResponseHeader('Cache-Control');
    $this->assertNotNull($cache_control, 'Cache-Control header should be present');
    
    // Verify max-age is 3600 as configured in controller.
    $this->assertStringContainsString('max-age=3600', $cache_control);
    
    // Page should be publicly accessible without authentication.
    $this->assertSession()->statusCodeNotEquals(403);
  }

}
