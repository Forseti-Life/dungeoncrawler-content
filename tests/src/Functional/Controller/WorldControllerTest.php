<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Controller;

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
    
    // Verify expected content structure/blocks.
    $this->assertSession()->pageTextContains('The Living Multiverse');
    $this->assertSession()->elementsCount('css', '.world-lore-card', 6);
      
    // Verify key lore sections exist.
    $this->assertSession()->pageTextContains('Shard Campaigns');
    $this->assertSession()->pageTextContains('Continuity Anchors');
    $this->assertSession()->pageTextContains('Forked Agents');
    $this->assertSession()->pageTextContains('Cross-World Transit');
    $this->assertSession()->pageTextContains('Setting Drift');
    $this->assertSession()->pageTextContains('Living Histories');
    $this->assertSession()->elementExists('css', '.world-lore-card--shard-campaigns');
    $this->assertSession()->elementExists('css', '.world-lore-card--living-histories');
      
    // Verify CTA buttons.
    $this->assertSession()->linkExists('View Campaigns');
    $this->assertSession()->linkExists('View Game Flow');
  }

  /**
   * Tests world page cache headers.
   */
  public function testWorldPageCacheHeaders(): void {
    $this->drupalGet('/world');
    $this->assertSession()->statusCodeEquals(200);
    
    // Verify cache headers are properly configured.
    $this->assertSession()->responseHeaderExists('X-Drupal-Cache-Contexts');
    
    // World page should be cacheable as a public content page.
    $cache_control = $this->getSession()->getResponseHeader('Cache-Control');
    $this->assertNotNull($cache_control, 'Cache-Control header should be present');
    
    // Page should be publicly accessible without authentication.
    $this->assertSession()->statusCodeNotEquals(403);
  }

}
