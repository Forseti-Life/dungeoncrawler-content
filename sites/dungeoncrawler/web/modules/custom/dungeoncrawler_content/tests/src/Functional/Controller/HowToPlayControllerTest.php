<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests HowToPlayController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 */
class HowToPlayControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests how to play page display - positive case.
   */
  public function testHowToPlayPageDisplayPositive(): void {
    $this->drupalGet('/how-to-play');
    $this->assertSession()->statusCodeEquals(200);
    
    // Verify expected content structure/blocks.
    $this->assertSession()->pageTextContains('Welcome, Adventurer!');
    
    // Verify key guide sections exist.
    $this->assertSession()->pageTextContains('Getting Started');
    $this->assertSession()->pageTextContains('Character Classes');
    $this->assertSession()->pageTextContains('Combat System');
    $this->assertSession()->pageTextContains('Dungeon Exploration');
    $this->assertSession()->pageTextContains('Items & Equipment');
    $this->assertSession()->pageTextContains('Character Progression');
    
    // Verify tips section and CTA.
    $this->assertSession()->pageTextContains('Pro Tips');
    $this->assertSession()->linkExists('Start Your Adventure');
  }

  /**
   * Tests how to play page cache headers.
   */
  public function testHowToPlayPageCacheHeaders(): void {
    $this->drupalGet('/how-to-play');
    $this->assertSession()->statusCodeEquals(200);
    
    // Verify cache headers are properly configured.
    $this->assertSession()->responseHeaderExists('X-Drupal-Cache-Contexts');
    
    // How-to-play page should be cacheable as a public content page.
    $cache_control = $this->getSession()->getResponseHeader('Cache-Control');
    $this->assertNotNull($cache_control, 'Cache-Control header should be present');
    
    // Page should be publicly accessible without authentication.
    $this->assertSession()->statusCodeNotEquals(403);
  }

}
