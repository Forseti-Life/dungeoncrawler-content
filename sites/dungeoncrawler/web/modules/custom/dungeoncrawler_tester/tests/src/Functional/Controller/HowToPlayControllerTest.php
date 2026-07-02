<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests HowToPlayController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 */
class HowToPlayControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests how to play page display - positive case.
   */
  public function testHowToPlayPageDisplayPositive(): void {
    $this->drupalGet('/how-to-play');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('How to Play');
  }

  /**
   * Tests how-to-play access for authenticated user without special permissions.
   */
  public function testHowToPlayPagePublicAccessNegative(): void {
    $user = $this->drupalCreateUser([]);
    $this->drupalLogin($user);

    $this->drupalGet('/how-to-play');
    $this->assertSession()->statusCodeEquals(200);
  }

}
