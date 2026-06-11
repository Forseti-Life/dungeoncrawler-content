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
   * Tests combat start API is disabled in favor of canonical coordinator actions.
   */
  public function testCombatStartApiPositive(): void {
    $user = $this->drupalCreateUser(['administer dungeoncrawler content', 'access dungeoncrawler characters']);
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

    $this->assertSession()->statusCodeEquals(409);
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertIsArray($response, 'Response should be JSON');
    $this->assertFalse((bool) ($response['success'] ?? TRUE));
    $this->assertSame('legacy_combat_mutation_disabled', $response['error_code'] ?? NULL);
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
    $user = $this->drupalCreateUser(['administer dungeoncrawler content', 'access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->getSession()->getDriver()->getClient()->request(
      'POST',
      $this->buildUrl('/api/combat/end-turn'),
      [],
      [],
      ['CONTENT_TYPE' => 'application/json'],
      json_encode([])
    );
    $this->assertSession()->statusCodeEquals(409);
  }

  /**
   * Tests combat end API - positive case.
   */
  public function testCombatEndApiPositive(): void {
    $user = $this->drupalCreateUser(['administer dungeoncrawler content', 'access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->getSession()->getDriver()->getClient()->request(
      'POST',
      $this->buildUrl('/api/combat/end'),
      [],
      [],
      ['CONTENT_TYPE' => 'application/json'],
      json_encode([])
    );
    $this->assertSession()->statusCodeEquals(409);
  }

  /**
   * Tests combat attack API - positive case.
   */
  public function testCombatAttackApiPositive(): void {
    $user = $this->drupalCreateUser(['administer dungeoncrawler content', 'access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->getSession()->getDriver()->getClient()->request(
      'POST',
      $this->buildUrl('/api/combat/attack'),
      [],
      [],
      ['CONTENT_TYPE' => 'application/json'],
      json_encode([])
    );
    $this->assertSession()->statusCodeEquals(409);
  }

  /**
   * Tests combat attack API with GET method - negative case.
   */
  public function testCombatAttackApiNegativeGetMethod(): void {
    $user = $this->drupalCreateUser(['administer dungeoncrawler content', 'access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/api/combat/attack');
    $this->assertSession()->statusCodeEquals(405);
  }

  /**
   * Tests current-state polling reads canonical encounter state.
   */
  public function testCurrentStateAutoPlaysNonPlayerTurns(): void {
    $user = $this->drupalCreateUser(['administer dungeoncrawler content', 'access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->createActiveEncounter(66, 'room-sync-1', [
      [
        'entity_id' => 'pc-hero-1',
        'entity_ref' => 'pc-hero-1',
        'name' => 'Valeros',
        'team' => 'player',
        'initiative' => 18,
        'initiative_roll' => 15,
        'hp' => 20,
        'max_hp' => 20,
        'ac' => 18,
      ],
      [
        'entity_id' => 'npc-goblin-1',
        'entity_ref' => 'npc-goblin-1',
        'name' => 'Goblin Raider',
        'team' => 'enemy',
        'initiative' => 12,
        'initiative_roll' => 9,
        'hp' => 8,
        'max_hp' => 8,
        'ac' => 16,
      ],
    ]);

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
   * Tests current-state polling keeps neutral-only encounters active.
   */
  public function testCurrentStateKeepsNeutralOnlyEncounterActive(): void {
    $user = $this->drupalCreateUser(['administer dungeoncrawler content', 'access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $encounter_id = $this->createActiveEncounter(67, 'room-stale-encounter', [
      [
        'entity_id' => 'npc-goblin-1',
        'entity_ref' => 'npc-goblin-1',
        'name' => 'Goblin Raider',
        'team' => 'enemy',
        'initiative' => 18,
        'initiative_roll' => 15,
        'hp' => 8,
        'max_hp' => 8,
        'ac' => 16,
      ],
      [
        'entity_id' => 'pc-hero-1',
        'entity_ref' => 'pc-hero-1',
        'name' => 'Valeros',
        'team' => 'player',
        'initiative' => 12,
        'initiative_roll' => 9,
        'hp' => 20,
        'max_hp' => 20,
        'ac' => 18,
      ],
    ]);

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

    $encounter_id = $this->createActiveEncounter(NULL, 'room-preview-1', [
      [
        'entity_id' => 'npc-goblin-1',
        'entity_ref' => 'npc-goblin-1',
        'name' => 'Goblin Raider',
        'team' => 'npc',
        'initiative' => 18,
        'initiative_roll' => 15,
        'hp' => 8,
        'max_hp' => 8,
        'ac' => 16,
      ],
      [
        'entity_id' => 'pc-hero-1',
        'entity_ref' => 'pc-hero-1',
        'name' => 'Valeros',
        'team' => 'player',
        'initiative' => 12,
        'initiative_roll' => 9,
        'hp' => 20,
        'max_hp' => 20,
        'ac' => 18,
      ],
    ]);

    $preview_payload = json_encode([
      'encounterId' => $encounter_id,
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

  /**
   * Creates an active encounter row for controller read/diagnostic tests.
   */
  protected function createActiveEncounter(?int $campaign_id, string $room_id, array $participants): int {
    /** @var \Drupal\dungeoncrawler_content\Service\CombatEncounterStore $store */
    $store = \Drupal::service('dungeoncrawler_content.combat_encounter_store');
    return $store->createEncounter($campaign_id, $room_id, $participants, NULL);
  }

}
