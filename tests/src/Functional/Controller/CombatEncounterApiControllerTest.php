<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Controller;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\dungeoncrawler_content\Functional\Traits\TestDataFactoryTrait;

/**
 * Tests CombatEncounterApiController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 * @group api
 */
class CombatEncounterApiControllerTest extends BrowserTestBase {

  use TestDataFactoryTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests combat start API - positive case.
   */
  public function testCombatStartApiPositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    // Create test combat encounter payload.
    $combat_payload = json_encode([
      'encounter_id' => 'test_encounter_1',
      'participants' => [
        ['type' => 'character', 'id' => 1],
        ['type' => 'npc', 'id' => 101],
      ],
    ]);

    $this->getSession()->getDriver()->getClient()->request(
      'POST',
      $this->buildUrl('/api/combat/start'),
      [],
      [],
      ['CONTENT_TYPE' => 'application/json'],
      $combat_payload
    );

    $status_code = $this->getSession()->getStatusCode();
    // Route should exist (not 404) and method allowed (not 405).
    $this->assertNotEquals(404, $status_code, 'Route should exist');
    $this->assertNotEquals(405, $status_code, 'Method should be allowed');
    
    // If successful, validate response structure.
    if ($status_code === 200) {
      $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
      $this->assertIsArray($response, 'Response should be JSON');
      $this->assertArrayHasKey('success', $response, 'Response should have success field');
      
      if ($response['success']) {
        $this->assertArrayHasKey('combat_id', $response, 'Response should contain combat_id');
      }
    }
  }

  /**
   * Tests combat start API without authentication - negative case.
   */
  public function testCombatStartApiNegative(): void {
    $this->getSession()->getDriver()->getClient()->request(
      'POST',
      $this->buildUrl('/api/combat/start'),
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
    }
  }

  /**
   * Tests combat end turn API - positive case.
   */
  public function testCombatEndTurnApiPositive(): void {
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
    $this->assertSession()->statusCodeNotEquals(405);
  }

  /**
   * Tests combat end API - positive case.
   */
  public function testCombatEndApiPositive(): void {
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
    $this->assertSession()->statusCodeNotEquals(405);
  }

  /**
   * Tests combat attack API - positive case.
   */
  public function testCombatAttackApiPositive(): void {
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
    $this->assertSession()->statusCodeNotEquals(405);
  }

  /**
   * Tests combat attack API with GET method - negative case.
   */
  public function testCombatAttackApiNegativeGetMethod(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/api/combat/attack');
    $this->assertSession()->statusCodeEquals(405);
  }

  /**
   * Tests current-state polling auto-plays non-player turns.
   */
  public function testCurrentStateAutoPlaysNonPlayerTurns(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $start_payload = json_encode([
      'campaignId' => 66,
      'roomId' => 'room-sync-1',
      'entities' => [
        [
          'entityId' => 'npc-goblin-1',
          'entityRef' => 'npc-goblin-1',
          'name' => 'Goblin Raider',
          'team' => 'enemy',
          'initiative' => 18,
          'hp' => 8,
          'max_hp' => 8,
          'ac' => 16,
        ],
        [
          'entityId' => 'pc-hero-1',
          'entityRef' => 'pc-hero-1',
          'name' => 'Valeros',
          'team' => 'player',
          'initiative' => 12,
          'hp' => 20,
          'max_hp' => 20,
          'ac' => 18,
        ],
      ],
    ]);

    $this->getSession()->getDriver()->getClient()->request(
      'POST',
      $this->buildUrl('/api/combat/start'),
      [],
      [],
      ['CONTENT_TYPE' => 'application/json'],
      $start_payload
    );
    $this->assertSession()->statusCodeEquals(201);

    $this->drupalGet('/api/combat/state', [
      'query' => [
        'campaignId' => 66,
        'roomId' => 'room-sync-1',
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);

    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertIsArray($response);
    $this->assertTrue($response['success'] ?? FALSE);
    $this->assertIsArray($response['data'] ?? NULL);
    $this->assertSame('player', $response['data']['current_participant']['team'] ?? NULL);
    $this->assertSame('Valeros', $response['data']['current_participant']['name'] ?? NULL);
  }

  /**
   * Tests current-state polling keeps room encounters active until navigation.
   */
  public function testCurrentStateKeepsNeutralOnlyEncounterActive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $start_payload = json_encode([
      'campaignId' => 67,
      'roomId' => 'room-stale-encounter',
      'entities' => [
        [
          'entityId' => 1001,
          'entityRef' => 'npc-goblin-1',
          'name' => 'Goblin Raider',
          'team' => 'enemy',
          'initiative' => 18,
          'hp' => 8,
          'max_hp' => 8,
          'ac' => 16,
        ],
        [
          'entityId' => 2001,
          'entityRef' => 'pc-hero-1',
          'name' => 'Valeros',
          'team' => 'player',
          'initiative' => 12,
          'hp' => 20,
          'max_hp' => 20,
          'ac' => 18,
        ],
      ],
    ]);

    $this->getSession()->getDriver()->getClient()->request(
      'POST',
      $this->buildUrl('/api/combat/start'),
      [],
      [],
      ['CONTENT_TYPE' => 'application/json'],
      $start_payload
    );
    $this->assertSession()->statusCodeEquals(201);

    $start_response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertIsArray($start_response);
    $this->assertArrayHasKey('encounter_id', $start_response);
    $encounter_id = (int) $start_response['encounter_id'];

    $database = \Drupal::database();
    $database->update('combat_participants')
      ->fields(['team' => 'neutral'])
      ->condition('encounter_id', $encounter_id)
      ->condition('entity_ref', 'npc-goblin-1')
      ->execute();
    $this->drupalGet('/api/combat/state', [
      'query' => [
        'campaignId' => 67,
        'roomId' => 'room-stale-encounter',
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);

    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertIsArray($response);
    $this->assertTrue($response['success'] ?? FALSE);
    $this->assertSame('active', $response['data']['status'] ?? NULL);
  }

  /**
   * Tests recommendation preview endpoint requires admin permission.
   */
  public function testRecommendationPreviewPermissionNegative(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $payload = json_encode(['encounterId' => 1]);
    $this->getSession()->getDriver()->getClient()->request(
      'POST',
      $this->buildUrl('/api/combat/recommendation-preview'),
      [],
      [],
      ['CONTENT_TYPE' => 'application/json'],
      $payload
    );

    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests recommendation preview endpoint returns read-only diagnostics.
   */
  public function testRecommendationPreviewPositive(): void {
    $admin = $this->drupalCreateUser(['administer dungeoncrawler content', 'access dungeoncrawler characters']);
    $this->drupalLogin($admin);

    $start_payload = json_encode([
      'campaignId' => NULL,
      'roomId' => 'room-preview-1',
      'entities' => [
        [
          'entityId' => 'npc-goblin-1',
          'name' => 'Goblin Raider',
          'team' => 'npc',
          'initiative' => 18,
          'hp' => 8,
          'max_hp' => 8,
        ],
        [
          'entityId' => 'pc-hero-1',
          'name' => 'Valeros',
          'team' => 'player',
          'initiative' => 12,
          'hp' => 20,
          'max_hp' => 20,
        ],
      ],
    ]);

    $this->getSession()->getDriver()->getClient()->request(
      'POST',
      $this->buildUrl('/api/combat/start'),
      [],
      [],
      ['CONTENT_TYPE' => 'application/json'],
      $start_payload
    );
    $this->assertSession()->statusCodeEquals(201);

    $start_response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertIsArray($start_response);
    $this->assertArrayHasKey('encounter_id', $start_response);

    $preview_payload = json_encode([
      'encounterId' => (int) $start_response['encounter_id'],
      'includeNarration' => TRUE,
    ]);

    $this->getSession()->getDriver()->getClient()->request(
      'POST',
      $this->buildUrl('/api/combat/recommendation-preview'),
      [],
      [],
      ['CONTENT_TYPE' => 'application/json'],
      $preview_payload
    );

    $this->assertSession()->statusCodeEquals(200);
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);

    $this->assertIsArray($response);
    $this->assertTrue($response['success']);
    $this->assertTrue($response['read_only']);
    $this->assertArrayHasKey('recommendation_preview', $response);
    $this->assertArrayHasKey('validation', $response['recommendation_preview']);
    $this->assertArrayHasKey('narration_preview', $response);
  }

}
