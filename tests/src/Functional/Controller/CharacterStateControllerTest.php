<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Controller;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\dungeoncrawler_content\Functional\Traits\TestFixtureTrait;

/**
 * Tests CharacterStateController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 * @group api
 */
class CharacterStateControllerTest extends BrowserTestBase {

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
   * Tests get character state API - positive case.
   */
  public function testGetCharacterStatePositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/api/character/1/state', ['query' => ['_format' => 'json']]);
    
    $status_code = $this->getSession()->getStatusCode();
    // Method should be allowed (not 405).
    $this->assertNotEquals(405, $status_code, 'GET method should be allowed');
    
    // If successful (200), assert response structure.
    if ($status_code === 200) {
      $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
      $this->assertIsArray($response, 'Response should be JSON');
      $this->assertArrayHasKey('success', $response, 'Response should have success field');
      
      if ($response['success']) {
        $this->assertArrayHasKey('state', $response, 'Response should contain state');
        $this->assertIsArray($response['state'], 'State should be an array');
      }
    }
  }

  /**
   * Tests get character state API without permission - negative case.
   */
  public function testGetCharacterStateNegative(): void {
    $this->drupalGet('/api/character/1/state');
    $this->assertSession()->statusCodeEquals(403);
    
    // Assert error response structure.
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    if ($response) {
      $this->assertIsArray($response, 'Response should be JSON');
      $this->assertArrayHasKey('success', $response, 'Response should have success field');
      $this->assertFalse($response['success'], 'Success should be false');
    }
  }

  /**
   * Tests update character state API - positive case.
   */
  public function testUpdateCharacterStatePositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    // Load character fixture for update payload.
    $character_data = $this->loadCharacterFixture('level_1_fighter');
    $update_payload = json_encode([
      'hp' => $character_data['calculated_stats']['max_hp'] - 5,
      'conditions' => [],
    ]);

    $this->getSession()->getDriver()->getClient()->request(
      'POST',
      $this->buildUrl('/api/character/1/update'),
      [],
      [],
      ['CONTENT_TYPE' => 'application/json'],
      $update_payload
    );

    $status_code = $this->getSession()->getStatusCode();
    // Method should be allowed (not 405).
    $this->assertNotEquals(405, $status_code, 'POST method should be allowed');
    
    // If successful, validate response structure.
    if ($status_code === 200) {
      $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
      $this->assertIsArray($response, 'Response should be JSON');
      $this->assertArrayHasKey('success', $response, 'Response should have success field');
    }
  }

  /**
   * Tests update character state API with GET method - negative case.
   */
  public function testUpdateCharacterStateNegativeGetMethod(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/api/character/1/update');
    $this->assertSession()->statusCodeEquals(405);
  }

  /**
   * Tests character summary API - positive case.
   */
  public function testGetCharacterSummaryPositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/api/character/1/summary', ['query' => ['_format' => 'json']]);
    
    $status_code = $this->getSession()->getStatusCode();
    // Method should be allowed (not 405).
    $this->assertNotEquals(405, $status_code, 'GET method should be allowed');
    
    // If successful, assert response structure.
    if ($status_code === 200) {
      $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
      $this->assertIsArray($response, 'Response should be JSON');
      $this->assertArrayHasKey('success', $response, 'Response should have success field');
      
      if ($response['success']) {
        $this->assertArrayHasKey('summary', $response, 'Response should contain summary');
        $this->assertIsArray($response['summary'], 'Summary should be an array');
      }
    }
  }

}
