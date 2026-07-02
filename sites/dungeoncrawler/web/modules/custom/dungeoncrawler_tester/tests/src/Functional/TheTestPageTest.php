<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Functional test for /dungeoncrawler/testing/thetest automation toggle.
 */
class TheTestPageTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'dungeoncrawler_tester'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  public function testTheTestPasses(): void {
    // Anonymous can view /dungeoncrawler/testing/thetest by route permission.
    $this->drupalGet('/dungeoncrawler/testing/thetest');
    $this->assertSession()->statusCodeEquals(200);

    // Expect the page to report PASS. If the controller constant is set to fail,
    // this assertion will fail until the code is flipped to PASS.
    $this->assertSession()->pageTextContains('TEST:PASS');
  }

}
