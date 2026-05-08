<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests HomeController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 */
class HomeControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests home page display - positive case (authenticated user).
   */
  public function testHomePageDisplayPositiveAuthenticated(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/home');
    $this->assertSession()->statusCodeEquals(200);
    
    // Verify expected content structure (not just generic text).
    $this->assertSession()->pageTextContains('Start Your Adventure');
    $this->assertSession()->linkExists('Start Your Adventure');
    $this->assertSession()->linkExists('Learn More');
  }

  /**
   * Tests home page display - positive case (anonymous user).
   */
  public function testHomePageDisplayPositiveAnonymous(): void {
    $this->drupalGet('/home');
    $this->assertSession()->statusCodeEquals(200);
    
    // Verify expected content structure for anonymous users.
    $this->assertSession()->pageTextContains('Sign In to Start');
    $this->assertSession()->linkExists('Sign In to Start');
    $this->assertSession()->linkExists('Learn How It Works');
  }

  /**
   * Tests home page caching - validate cache headers.
   */
  public function testHomePageCacheHeaders(): void {
    $this->drupalGet('/home');
    $this->assertSession()->statusCodeEquals(200);
    
    // Verify cache headers are properly configured.
    $this->assertSession()->responseHeaderExists('X-Drupal-Cache-Contexts');
    
    // Home page should have cache max-age of 3600 (1 hour) per controller.
    $cache_control = $this->getSession()->getResponseHeader('Cache-Control');
    $this->assertNotNull($cache_control, 'Cache-Control header should be present');
    
    // Verify max-age is 3600 as configured in controller.
    $this->assertStringContainsString('max-age=3600', $cache_control);
  }

}
