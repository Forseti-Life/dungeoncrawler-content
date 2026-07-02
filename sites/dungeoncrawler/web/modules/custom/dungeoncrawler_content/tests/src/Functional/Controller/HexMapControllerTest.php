<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Controller;

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
   * Tests hexmap demo public access - negative case (should be public).
   */
  public function testHexmapDemoPublicAccessNegative(): void {
    // Demo should be publicly accessible
    $this->drupalGet('/hexmap');
    $this->assertSession()->statusCodeNotEquals(403);
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests that schema_version is preserved in dungeon payload.
   *
   * Verifies that HexMapController::normalizeDungeonPayload() includes
   * schema_version from the source dungeon data in the normalized output
   * passed to the frontend via drupalSettings.
   *
   * Related to DCC-0255: Schema conformance review.
   */
  public function testSchemaVersionPreservedInPayload(): void {
    $this->drupalGet('/hexmap');
    $this->assertSession()->statusCodeEquals(200);
    
    // Check that drupalSettings contains the dungeon data with schema_version
    $settings_script = $this->getSession()->getPage()->find('css', 'script[data-drupal-selector="drupal-settings-json"]');
    if ($settings_script) {
      $settings_json = $settings_script->getText();
      $settings = json_decode($settings_json, TRUE);
      
      // Verify hexmapDungeonData exists
      $this->assertArrayHasKey('dungeoncrawlerContent', $settings);
      $this->assertArrayHasKey('hexmapDungeonData', $settings['dungeoncrawlerContent']);
      
      // Verify schema_version is present (should be "1.0.0" from tavern-entrance-dungeon.json)
      $dungeon_data = $settings['dungeoncrawlerContent']['hexmapDungeonData'];
      $this->assertArrayHasKey('schema_version', $dungeon_data, 'schema_version field must be preserved in normalized dungeon payload');
      $this->assertNotEmpty($dungeon_data['schema_version'], 'schema_version must not be empty');
    }
  }

}
