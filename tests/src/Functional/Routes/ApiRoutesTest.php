<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Routes;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\dungeoncrawler_content\Functional\Traits\TestDataFactoryTrait;
use Drupal\Tests\dungeoncrawler_content\Functional\Traits\TestFixtureTrait;

/**
 * Tests API routes in the dungeon crawler module.
 *
 * @group dungeoncrawler_content
 * @group routes
 * @group api
 */
class ApiRoutesTest extends BrowserTestBase {

  use TestDataFactoryTrait;
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
   * Tests character save API route - positive case.
   */
  public function testCharacterSaveApiRoutePositive(): void {
    $user = $this->drupalCreateUser(['create dungeoncrawler characters']);
    $this->drupalLogin($user);

    // Load character fixture and POST valid data.
    $character_data = $this->loadCharacterFixture('level_1_fighter');
    $this->getSession()->getDriver()->getClient()->request(
      'POST',
      $this->buildUrl('/api/character/save'),
      [],
      [],
      ['CONTENT_TYPE' => 'application/json'],
      json_encode(['character' => $character_data])
    );

    // Route should exist (not 404).
    $this->assertSession()->statusCodeNotEquals(404);
    
    $status_code = $this->getSession()->getStatusCode();
    // If successful, validate response structure.
    if ($status_code === 200) {
      $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
      $this->assertIsArray($response, 'Response should be JSON');
      $this->assertArrayHasKey('success', $response, 'Response should have success key');
    }
  }

  /**
   * Tests character save API route - negative case (GET method not allowed).
   */
  public function testCharacterSaveApiRouteNegative(): void {
    $user = $this->drupalCreateUser(['create dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/api/character/save');
    // Should return 405 Method Not Allowed for GET
    $this->assertSession()->statusCodeEquals(405);
  }

  /**
   * Tests character load API route - positive case.
   */
  public function testCharacterLoadApiRoutePositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    // GET request
    $this->drupalGet('/api/character/load/1', ['query' => ['_format' => 'json']]);
    
    $status_code = $this->getSession()->getStatusCode();
    // Method should be allowed (not 405).
    $this->assertNotEquals(405, $status_code, 'GET method should be allowed');
    
    // If successful, validate response structure.
    if ($status_code === 200) {
      $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
      $this->assertIsArray($response, 'Response should be JSON');
      $this->assertArrayHasKey('success', $response, 'Response should have success field');
      
      if ($response['success']) {
        $this->assertArrayHasKey('character', $response, 'Response should contain character data');
      }
    }
  }

  /**
   * Tests character load API route - negative case (non-numeric ID).
   */
  public function testCharacterLoadApiRouteNegative(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/api/character/load/invalid');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests character state API route - positive case.
   */
  public function testCharacterStateApiRoutePositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/api/character/1/state', ['query' => ['_format' => 'json']]);
    
    $status_code = $this->getSession()->getStatusCode();
    // Method should be allowed (not 405).
    $this->assertNotEquals(405, $status_code, 'GET method should be allowed');
    
    // If successful, validate response structure.
    if ($status_code === 200) {
      $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
      $this->assertIsArray($response, 'Response should be JSON');
      $this->assertArrayHasKey('success', $response, 'Response should have success field');
      
      if ($response['success']) {
        $this->assertArrayHasKey('state', $response, 'Response should contain state');
      }
    }
  }

  /**
   * Tests character state API route - negative case (POST not allowed).
   */
  public function testCharacterStateApiRouteNegative(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->getSession()->getDriver()->getClient()->request(
      'POST',
      $this->buildUrl('/api/character/1/state'),
      []
    );
    // Should return 405 for POST on GET-only route
    $this->assertSession()->statusCodeEquals(405);
  }

  /**
   * Tests character summary API route - positive case.
   */
  public function testCharacterSummaryApiRoutePositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/api/character/1/summary', ['query' => ['_format' => 'json']]);
    
    $status_code = $this->getSession()->getStatusCode();
    // Method should be allowed (not 405).
    $this->assertNotEquals(405, $status_code, 'GET method should be allowed');
    
    // If successful, validate response structure.
    if ($status_code === 200) {
      $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
      $this->assertIsArray($response, 'Response should be JSON');
      $this->assertArrayHasKey('success', $response, 'Response should have success field');
    }
  }

  /**
   * Tests character state update API route - positive case.
   */
  public function testCharacterStateUpdateApiRoutePositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $update_payload = json_encode(['hp' => 10, 'conditions' => []]);
    $this->getSession()->getDriver()->getClient()->request(
      'POST',
      $this->buildUrl('/api/character/1/update'),
      [],
      [],
      ['CONTENT_TYPE' => 'application/json'],
      $update_payload
    );

    $status_code = $this->getSession()->getStatusCode();
    // Route should exist (not 404).
    $this->assertNotEquals(404, $status_code, 'Route should exist');
    
    // If successful, validate response structure.
    if ($status_code === 200) {
      $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
      $this->assertIsArray($response, 'Response should be JSON');
      $this->assertArrayHasKey('success', $response, 'Response should have success field');
    }
  }

  /**
   * Tests character state update API route - negative case (GET not allowed).
   */
  public function testCharacterStateUpdateApiRouteNegative(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/api/character/1/update');
    $this->assertSession()->statusCodeEquals(405);
  }

  /**
   * Tests combat start API route - positive case.
   */
  public function testCombatStartApiRoutePositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->getSession()->getDriver()->getClient()->request(
      'POST',
      $this->buildUrl('/api/combat/start'),
      [],
      [],
      ['CONTENT_TYPE' => 'application/json'],
      json_encode([])
    );
    $this->assertSession()->statusCodeNotEquals(404);
  }

  /**
   * Tests combat start API route - negative case (GET not allowed).
   */
  public function testCombatStartApiRouteNegative(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/api/combat/start');
    $this->assertSession()->statusCodeEquals(405);
  }

  /**
   * Tests combat end turn API route - positive case.
   */
  public function testCombatEndTurnApiRoutePositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->getSession()->getDriver()->getClient()->request(
      'POST',
      $this->buildUrl('/api/combat/end-turn'),
      [],
      [],
      ['CONTENT_TYPE' => 'application/json'],
      json_encode([])
    );
    $this->assertSession()->statusCodeNotEquals(404);
  }

  /**
   * Tests combat end API route - positive case.
   */
  public function testCombatEndApiRoutePositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->getSession()->getDriver()->getClient()->request(
      'POST',
      $this->buildUrl('/api/combat/end'),
      [],
      [],
      ['CONTENT_TYPE' => 'application/json'],
      json_encode([])
    );
    $this->assertSession()->statusCodeNotEquals(404);
  }

  /**
   * Tests combat attack API route - positive case.
   */
  public function testCombatAttackApiRoutePositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->getSession()->getDriver()->getClient()->request(
      'POST',
      $this->buildUrl('/api/combat/attack'),
      [],
      [],
      ['CONTENT_TYPE' => 'application/json'],
      json_encode([])
    );
    $this->assertSession()->statusCodeNotEquals(404);
  }

  /**
   * Tests API routes - negative case (no authentication).
   */
  public function testApiRoutesNegativeNoAuth(): void {
    $this->drupalGet('/api/character/load/1');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests character summary API - negative case (POST not allowed).
   */
  public function testCharacterSummaryPostNotAllowed(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->getSession()->getDriver()->getClient()->request(
      'POST',
      $this->buildUrl('/api/character/1/summary'),
      [],
      [],
      ['CONTENT_TYPE' => 'application/json'],
      json_encode([])
    );
    $this->assertSession()->statusCodeEquals(405);
  }

  /**
   * Tests combat end turn API - negative case (GET not allowed).
   */
  public function testCombatEndTurnGetNotAllowed(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/api/combat/end-turn');
    $this->assertSession()->statusCodeEquals(405);
  }

  /**
   * Tests combat end API - negative case (GET not allowed).
   */
  public function testCombatEndGetNotAllowed(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/api/combat/end');
    $this->assertSession()->statusCodeEquals(405);
  }

  /**
   * Tests combat attack API - negative case (GET not allowed).
   */
  public function testCombatAttackGetNotAllowed(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/api/combat/attack');
    $this->assertSession()->statusCodeEquals(405);
  }

  /**
   * Tests character load API - negative case (accessing other user's character).
   */
  public function testCharacterLoadOwnershipDenied(): void {
    // Create two users
    $owner = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $other_user = $this->drupalCreateUser(['access dungeoncrawler characters']);

    // Create a character owned by the first user
    $database = \Drupal::database();
    $character_id = $database->insert('dc_campaign_characters')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'campaign_id' => 0,
        'character_id' => 0,
        'instance_id' => \Drupal::service('uuid')->generate(),
        'uid' => $owner->id(),
        'name' => 'Owner Character',
        'class' => 'fighter',
        'ancestry' => 'human',
        'level' => 1,
        'hp_current' => 10,
        'hp_max' => 10,
        'armor_class' => 10,
        'experience_points' => 0,
        'position_q' => 0,
        'position_r' => 0,
        'last_room_id' => '',
        'type' => 'pc',
        'status' => 1,
        'character_data' => json_encode([]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    // Login as the other user and try to load the character
    $this->drupalLogin($other_user);
    $this->drupalGet("/api/character/load/{$character_id}", ['query' => ['_format' => 'json']]);
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests character state API - negative case (accessing other user's character).
   */
  public function testCharacterStateOwnershipDenied(): void {
    // Create two users
    $owner = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $other_user = $this->drupalCreateUser(['access dungeoncrawler characters']);

    // Create a character owned by the first user
    $database = \Drupal::database();
    $character_id = $database->insert('dc_campaign_characters')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'campaign_id' => 0,
        'character_id' => 0,
        'instance_id' => \Drupal::service('uuid')->generate(),
        'uid' => $owner->id(),
        'name' => 'Owner Character',
        'class' => 'fighter',
        'ancestry' => 'human',
        'level' => 1,
        'hp_current' => 10,
        'hp_max' => 10,
        'armor_class' => 10,
        'experience_points' => 0,
        'position_q' => 0,
        'position_r' => 0,
        'last_room_id' => '',
        'type' => 'pc',
        'status' => 1,
        'character_data' => json_encode([]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    // Login as the other user and try to access character state
    $this->drupalLogin($other_user);
    $this->drupalGet("/api/character/{$character_id}/state", ['query' => ['_format' => 'json']]);
    $this->assertSession()->statusCodeEquals(403);
  }

}
