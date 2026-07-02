<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional\Routes;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests demo/testing routes in the dungeon crawler module.
 *
 * @group dungeoncrawler_content
 * @group routes
 */
class DemoRoutesTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests hexmap demo route - positive case.
   */
  public function testHexmapDemoRoutePositive(): void {
    $this->drupalGet('/hexmap');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Hex Map Demo');
  }

  /**
   * Tests hexmap demo route - negative case (invalid parameter).
   */
  public function testHexmapDemoRouteNegative(): void {
    $this->drupalGet('/hexmap/invalid');
    $this->assertSession()->statusCodeEquals(404);
  }

}
