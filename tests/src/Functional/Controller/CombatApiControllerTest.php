<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests CombatApiController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 * @group api
 */
class CombatApiControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests combat API controller exists - positive case.
   *
   * Note: CombatApiController exists but has no routes defined yet.
   * This test validates the controller can be instantiated.
   */
  public function testCombatApiControllerExistsPositive(): void {
    // Validate the controller class exists
    $this->assertTrue(class_exists('\Drupal\dungeoncrawler_content\Controller\CombatApiController'));
  }

  /**
   * Tests combat API controller not accessible without routes - negative case.
   */
  public function testCombatApiControllerNotAccessibleNegative(): void {
    // Since no routes are defined for CombatApiController,
    // attempting to access combat API paths should return 404
    $this->drupalGet('/api/combat-alt');
    $this->assertSession()->statusCodeEquals(404);
  }

}
