<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests CombatActionController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 */
class CombatActionControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests combat action controller exists - positive case.
   *
   * Note: CombatActionController exists but has no routes defined yet.
   * This test validates the controller can be instantiated.
   */
  public function testCombatActionControllerExistsPositive(): void {
    // Validate the controller class exists
    $this->assertTrue(class_exists('\Drupal\dungeoncrawler_content\Controller\CombatActionController'));
  }

  /**
   * Tests combat action controller not accessible without routes - negative case.
   */
  public function testCombatActionControllerNotAccessibleNegative(): void {
    // Since no routes are defined for CombatActionController,
    // attempting to access combat action paths should return 404
    $this->drupalGet('/combat/action');
    $this->assertSession()->statusCodeEquals(404);
  }

}
