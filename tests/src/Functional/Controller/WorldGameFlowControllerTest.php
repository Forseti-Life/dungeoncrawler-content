<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the public world game-flow documentation page.
 *
 * @group dungeoncrawler_content
 * @group controller
 */
class WorldGameFlowControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Verifies the page renders its diagrams and key content.
   */
  public function testWorldGameFlowPagePositive(): void {
    $this->drupalGet('/world/game-flow');
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSession()->pageTextContains('How players move, talk, and fight within a persistent campaign.');
    $this->assertSession()->pageTextContains('End-to-end run lifecycle');
    $this->assertSession()->pageTextContains('How the run boots into the first room');
    $this->assertSession()->pageTextContains('Movement, investigation, turn order, and room-state updates');
    $this->assertSession()->pageTextContains('Conversation inside the current room');
    $this->assertSession()->pageTextContains('Turn-based combat from initiation through resolution');
    $this->assertSession()->pageTextContains('POST /api/game/{campaign_id}/action');
    $this->assertSession()->pageTextContains('exploring is not a separate active phase');
    $this->assertSession()->pageTextContains('How client actions become canonical state');
    $this->assertSession()->elementsCount('css', '[data-mermaid-diagram]', 6);
    $this->assertSession()->linkExists('Back to World');
    $this->assertSession()->linkExists('View Campaigns');
  }

  /**
   * Verifies public cache headers remain present.
   */
  public function testWorldGameFlowCacheHeaders(): void {
    $this->drupalGet('/world/game-flow');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderExists('X-Drupal-Cache-Contexts');

    $cache_control = $this->getSession()->getResponseHeader('Cache-Control');
    $this->assertNotNull($cache_control, 'Cache-Control header should be present');
    $this->assertSession()->statusCodeNotEquals(403);
  }

}
