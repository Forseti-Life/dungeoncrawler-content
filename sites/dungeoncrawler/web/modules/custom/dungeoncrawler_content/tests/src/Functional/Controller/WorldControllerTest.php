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
    $this->assertSession()->pageTextContains('The Living Dungeon');
    
    // Verify key lore sections exist.
    $this->assertSession()->pageTextContains('The Endless Depths');
    $this->assertSession()->pageTextContains('AI-Born Creatures');
    $this->assertSession()->pageTextContains('Procedural Treasures');
    $this->assertSession()->pageTextContains('Dynamic Quests');
    $this->assertSession()->pageTextContains('The Hex Realm');
    $this->assertSession()->pageTextContains('Living History');
    
    // Verify CTA button.
    $this->assertSession()->linkExists('View Campaigns');
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
