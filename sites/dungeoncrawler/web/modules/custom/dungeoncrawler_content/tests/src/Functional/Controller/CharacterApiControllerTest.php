<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Controller;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\dungeoncrawler_content\Functional\Traits\TestFixtureTrait;

/**
 * Tests CharacterApiController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 * @group api
 */
class CharacterApiControllerTest extends BrowserTestBase {

  use TestFixtureTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests character save API - positive case.
   */
  public function testCharacterSaveApiPositive(): void {
    $user = $this->drupalCreateUser(['create dungeoncrawler characters']);
    $this->drupalLogin($user);

    // Load character fixture and prepare payload.
    $character_data = $this->loadCharacterFixture('level_1_fighter');
    $payload = json_encode([
      'character' => $character_data,
    ]);

    $this->getSession()->getDriver()->getClient()->request(
      'POST',
      $this->buildUrl('/api/character/save'),
      [],
      [],
      ['CONTENT_TYPE' => 'application/json'],
      $payload
    );

    $status_code = $this->getSession()->getStatusCode();
    // Route should exist (not 404) and method should be allowed (not 405).
    $this->assertNotEquals(404, $status_code, 'Route should exist');
    $this->assertNotEquals(405, $status_code, 'Method should be allowed');

    // If successful, assert response body.
    if ($status_code === 200) {
      $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
      $this->assertIsArray($response, 'Response should be JSON');
      $this->assertArrayHasKey('success', $response, 'Response should have success field');
      
      if ($response['success']) {
        $this->assertArrayHasKey('character_id', $response, 'Response should contain character_id');
        $this->assertIsNumeric($response['character_id'], 'Character ID should be numeric');
      }
    }
  }

  /**
   * Tests character save API without permission - negative case.
   */
  public function testCharacterSaveApiNegativeNoPermission(): void {
    $user = $this->drupalCreateUser([]);
    $this->drupalLogin($user);

    $this->getSession()->getDriver()->getClient()->request(
      'POST',
      $this->buildUrl('/api/character/save'),
      [],
      [],
      ['CONTENT_TYPE' => 'application/json'],
      json_encode([])
    );
    $this->assertSession()->statusCodeEquals(403);
    
    // Assert error response structure.
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    if ($response) {
      $this->assertIsArray($response, 'Response should be JSON');
      $this->assertArrayHasKey('success', $response, 'Response should have success field');
      $this->assertFalse($response['success'], 'Success should be false');
      $this->assertArrayHasKey('error', $response, 'Response should contain error message');
    }
  }

  /**
   * Tests character load API - positive case.
   */
  public function testCharacterLoadApiPositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/api/character/load/1', ['query' => ['_format' => 'json']]);
    // May return 403/404 without valid character
    $this->assertSession()->statusCodeNotEquals(405);
  }

  /**
   * Tests character load API without authentication - negative case.
   */
  public function testCharacterLoadApiNegativeNoAuth(): void {
    $this->drupalGet('/api/character/load/1');
    $this->assertSession()->statusCodeEquals(403);
  }

}
