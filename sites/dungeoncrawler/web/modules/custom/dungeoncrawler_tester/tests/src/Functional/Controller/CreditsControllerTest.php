<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests CreditsController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 */
class CreditsControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests credits page display - positive case.
   */
  public function testCreditsPageDisplayPositive(): void {
    $this->drupalGet('/credits');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Credits');
  }

  /**
   * Tests credits page access for authenticated user without special permissions.
   */
  public function testCreditsPagePublicAccessNegative(): void {
    $user = $this->drupalCreateUser([]);
    $this->drupalLogin($user);

    $this->drupalGet('/credits');
    $this->assertSession()->statusCodeEquals(200);
  }

}
