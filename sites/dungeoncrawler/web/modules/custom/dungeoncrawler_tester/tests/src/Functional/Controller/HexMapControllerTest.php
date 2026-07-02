<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests HexMapController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 */
class HexMapControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests hexmap demo display - positive case.
   */
  public function testHexmapDemoDisplayPositive(): void {
    $this->drupalGet('/hexmap');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Hex Map Demo');
  }

  /**
   * Tests hexmap demo access for authenticated user without special permissions.
   */
  public function testHexmapDemoPublicAccessNegative(): void {
    $user = $this->drupalCreateUser([]);
    $this->drupalLogin($user);

    $this->drupalGet('/hexmap');
    $this->assertSession()->statusCodeEquals(200);
  }

}
