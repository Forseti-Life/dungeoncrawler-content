<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Routes;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\dungeoncrawler_content\Functional\Traits\TestDataBuilderTrait;

/**
 * Tests admin routes in the dungeon crawler module.
 *
 * @group dungeoncrawler_content
 * @group routes
 */
class AdminRoutesTest extends BrowserTestBase {

  use TestDataBuilderTrait;

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
    $user = $this->createTestUser(['administer site configuration']);
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
    $user = $this->createTestUser(['access content overview']);
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

  /**
   * Tests settings route - negative case (user with insufficient permissions).
   */
  public function testSettingsRouteInsufficientPermissions(): void {
    $user = $this->drupalCreateUser(['access content overview']);
    $this->drupalLogin($user);

    $this->drupalGet('/admin/config/content/dungeoncrawler');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests dashboard route - negative case (user with wrong permission).
   */
  public function testDashboardRouteWrongPermission(): void {
    $user = $this->drupalCreateUser(['administer site configuration']);
    $this->drupalLogin($user);

    $this->drupalGet('/admin/content/dungeoncrawler');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests settings route - negative case (anonymous user).
   */
  public function testSettingsRouteAnonymous(): void {
    $this->drupalGet('/admin/config/content/dungeoncrawler');
    $this->assertSession()->statusCodeEquals(403);
  }

}
