<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests DashboardController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 */
class DashboardControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests dashboard page display - positive case.
   */
  public function testDashboardPageDisplayPositive(): void {
    $user = $this->drupalCreateUser(['access content overview']);
    $this->drupalLogin($user);

    $this->drupalGet('/admin/content/dungeoncrawler');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Game Content Dashboard');
  }

  /**
   * Tests dashboard page access control - negative case (no permission).
   */
  public function testDashboardPageAccessControlNegative(): void {
    $user = $this->drupalCreateUser([]);
    $this->drupalLogin($user);

    $this->drupalGet('/admin/content/dungeoncrawler');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests dashboard page anonymous access - negative case.
   */
  public function testDashboardPageAnonymousAccessNegative(): void {
    $this->drupalGet('/admin/content/dungeoncrawler');
    $this->assertSession()->statusCodeEquals(403);
  }

}
