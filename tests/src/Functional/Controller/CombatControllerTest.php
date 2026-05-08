<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests CombatController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 */
class CombatControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests combat controller exists - positive case.
   *
   * Note: CombatController exists but has no routes defined yet.
   * This test validates the controller can be instantiated.
   */
  public function testCombatControllerExistsPositive(): void {
    // Validate the controller class exists
    $this->assertTrue(class_exists('\Drupal\dungeoncrawler_content\Controller\CombatController'));
  }

  /**
   * Tests combat controller not accessible without routes - negative case.
   */
  public function testCombatControllerNotAccessibleNegative(): void {
    // Since no routes are defined for CombatController,
    // attempting to access combat-related paths should return 404
    $this->drupalGet('/combat');
    $this->assertSession()->statusCodeEquals(404);
  }

}
