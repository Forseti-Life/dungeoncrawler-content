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
    $this->assertSession()->pageTextContains('A persistent RPG home for characters, campaigns, and shared history.');
    $this->assertSession()->elementsCount('css', '.about-feature-card', 6);
     
    // Verify key sections exist.
    $this->assertSession()->pageTextContains('Why this world exists');
    $this->assertSession()->pageTextContains('The pillars behind the experience');
    $this->assertSession()->pageTextContains('The campaign loop');
    $this->assertSession()->pageTextContains('The technology and service model');
    $this->assertSession()->pageTextContains('The team and product posture');
     
    // Verify CTA buttons.
    $this->assertSession()->linkExists('Create Legacy Character');
    $this->assertSession()->linkExists('Read Player Guide');
    $this->assertSession()->linkExists('View Campaigns');
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
