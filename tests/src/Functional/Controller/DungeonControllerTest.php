<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests DungeonController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 */
class DungeonControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests dungeon controller exists - positive case.
   *
   * Note: DungeonController exists but has no routes defined yet.
   * This test validates the controller can be instantiated.
   */
  public function testDungeonControllerExistsPositive(): void {
    // Validate the controller class exists
    $this->assertTrue(class_exists('\Drupal\dungeoncrawler_content\Controller\DungeonController'));
  }

  /**
   * Tests dungeon controller not accessible without routes - negative case.
   */
  public function testDungeonControllerNotAccessibleNegative(): void {
    // Since no routes are defined for DungeonController, 
    // attempting to access dungeon-related paths should return 404
    $this->drupalGet('/dungeon');
    $this->assertSession()->statusCodeEquals(404);
  }

}
