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

    $this->assertSession()->pageTextContains('Start your first campaign without guessing what the game expects from you.');
    $this->assertSession()->pageTextContains('Field guide for your first hour');
    $this->assertSession()->pageTextContains('Campaign');
    $this->assertSession()->pageTextContains('Tavern');
    $this->assertSession()->pageTextContains('Hexmap');

    $this->assertSession()->pageTextContains('Your first campaign, step by step');
    $this->assertSession()->pageTextContains('Create one campaign world');
    $this->assertSession()->pageTextContains('What to focus on early');
    $this->assertSession()->pageTextContains('What success looks like');
    $this->assertSession()->pageTextContains('Beginner Tips');
    $this->assertSession()->linkExists('Start Your First Campaign');
    $this->assertSession()->linkExists('Create a Character');
  }

  /**
   * Tests how to play page cache headers.
   */
  public function testHowToPlayPageCacheHeaders(): void {
    $this->drupalGet('/how-to-play');
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSession()->responseHeaderExists('X-Drupal-Cache-Contexts');

    $cache_control = $this->getSession()->getResponseHeader('Cache-Control');
    $this->assertNotNull($cache_control, 'Cache-Control header should be present');

    $this->assertSession()->statusCodeNotEquals(403);
  }

}
