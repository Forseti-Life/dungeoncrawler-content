<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests DungeonGeneratorController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 */
class DungeonGeneratorControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests dungeon generator controller class exists.
   */
  public function testDungeonGeneratorControllerExistsPositive(): void {
    $this->assertTrue(class_exists('\Drupal\dungeoncrawler_content\Controller\DungeonGeneratorController'));
  }

  /**
   * Tests legacy dungeon path remains unavailable.
   */
  public function testLegacyDungeonPathNotAccessibleNegative(): void {
    $this->drupalGet('/dungeon');
    $this->assertSession()->statusCodeEquals(404);
  }

}
