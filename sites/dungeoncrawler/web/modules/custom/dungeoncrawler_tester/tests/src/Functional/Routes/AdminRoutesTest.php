<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional\Routes;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests admin routes in the dungeon crawler module.
 *
 * @group dungeoncrawler_content
 * @group routes
 */
class AdminRoutesTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests settings route - positive case.
   */
  public function testSettingsRoutePositive(): void {
    $user = $this->drupalCreateUser(['administer site configuration']);
    $this->drupalLogin($user);

    $this->drupalGet('/admin/config/content/dungeoncrawler');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Dungeon Crawler Settings');
  }

  /**
   * Tests settings route - negative case (no permission).
   */
  public function testSettingsRouteNegative(): void {
    // Try to access without permission
    $this->drupalGet('/admin/config/content/dungeoncrawler');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests dashboard route - positive case.
   */
  public function testDashboardRoutePositive(): void {
    $user = $this->drupalCreateUser(['access content overview']);
    $this->drupalLogin($user);

    $this->drupalGet('/admin/content/dungeoncrawler');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Game Content Dashboard');
  }

  /**
   * Tests dashboard route - negative case (no permission).
   */
  public function testDashboardRouteNegative(): void {
    // Try to access without permission
    $this->drupalGet('/admin/content/dungeoncrawler');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests dashboard route - negative case (anonymous user).
   */
  public function testDashboardRouteNegativeAnonymous(): void {
    $this->drupalGet('/admin/content/dungeoncrawler');
    $this->assertSession()->statusCodeEquals(403);
  }

}
