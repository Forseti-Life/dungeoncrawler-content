<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests public legal page controller output.
 *
 * @group dungeoncrawler_content
 * @group controller
 */
class LegalControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the privacy policy page.
   */
  public function testPrivacyPolicyPageDisplayPositive(): void {
    $this->drupalGet('/privacy-policy');
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSession()->pageTextContains('Privacy Policy');
    $this->assertSession()->pageTextContains('How Dungeon Crawler Life handles account, gameplay, and service data.');
    $this->assertSession()->pageTextContains('What we collect');
    $this->assertSession()->pageTextContains('How we use it');
    $this->assertSession()->pageTextContains('What we do not do');
    $this->assertSession()->linkExists('Read the Terms of Service');
  }

  /**
   * Tests the terms of service page.
   */
  public function testTermsOfServicePageDisplayPositive(): void {
    $this->drupalGet('/terms-of-service');
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSession()->pageTextContains('Terms of Service');
    $this->assertSession()->pageTextContains('The practical rules for using Dungeon Crawler Life and its persistent campaign systems.');
    $this->assertSession()->pageTextContains('Using the service');
    $this->assertSession()->pageTextContains('Acceptable behavior');
    $this->assertSession()->pageTextContains('Service boundaries');
    $this->assertSession()->linkExists('Read the Privacy Policy');
  }

  /**
   * Tests legal page cache headers.
   */
  public function testLegalPageCacheHeaders(): void {
    $this->drupalGet('/privacy-policy');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderExists('X-Drupal-Cache-Contexts');

    $cache_control = $this->getSession()->getResponseHeader('Cache-Control');
    $this->assertNotNull($cache_control, 'Cache-Control header should be present');

    $this->drupalGet('/terms-of-service');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderExists('X-Drupal-Cache-Contexts');
  }

}
