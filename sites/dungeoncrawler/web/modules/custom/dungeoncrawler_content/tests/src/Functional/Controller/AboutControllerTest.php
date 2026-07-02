<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Controller;

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
    
    // Verify expected content structure/blocks.
    $this->assertSession()->pageTextContains('About Dungeon Crawler Life');
    $this->assertSession()->pageTextContains('Dungeon Crawler Life');
    $this->assertSession()->pageTextContains('Where AI Meets Adventure');
    
    // Verify key sections exist.
    $this->assertSession()->pageTextContains('The Vision');
    $this->assertSession()->pageTextContains('AI-Powered Generation');
    $this->assertSession()->pageTextContains('Infinite Replayability');
    $this->assertSession()->pageTextContains('The Technology');
    $this->assertSession()->pageTextContains('The Team');
    
    // Verify CTA buttons.
    $this->assertSession()->linkExists('Create Character');
    $this->assertSession()->linkExists('Learn More');
  }

  /**
   * Tests about page cache headers.
   */
  public function testAboutPageCacheHeaders(): void {
    $this->drupalGet('/about');
    $this->assertSession()->statusCodeEquals(200);
    
    // Verify cache headers are properly configured.
    $this->assertSession()->responseHeaderExists('X-Drupal-Cache-Contexts');
    
    // About page should be cacheable as a public content page.
    $cache_control = $this->getSession()->getResponseHeader('Cache-Control');
    $this->assertNotNull($cache_control, 'Cache-Control header should be present');
    
    // Ensure we're not showing error content.
    $this->assertSession()->pageTextNotContains('Error');
    $this->assertSession()->pageTextNotContains('Page not found');
  }

}
