<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional\Controller;

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
    $this->assertSession()->pageTextContains('Start Your Adventure');
  }

  /**
   * Tests home page display - positive case (anonymous user).
   */
  public function testHomePageDisplayPositiveAnonymous(): void {
    $this->drupalGet('/home');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Sign In to Start');
  }

  /**
   * Tests home page caching - negative case (ensure cache is configured).
   */
  public function testHomePageCachingNegative(): void {
    $this->drupalGet('/home');
    $this->assertSession()->statusCodeEquals(200);
    
    // Verify page is accessible (negative test would be if cache fails)
    $this->assertSession()->responseHeaderExists('X-Drupal-Cache-Contexts');
  }

}
