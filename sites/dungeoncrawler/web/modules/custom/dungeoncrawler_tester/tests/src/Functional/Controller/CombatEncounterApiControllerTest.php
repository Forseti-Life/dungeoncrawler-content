<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests CombatEncounterApiController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 * @group api
 */
class CombatEncounterApiControllerTest extends BrowserTestBase {

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

    $response = $this->requestJson('POST', '/api/combat/start', [
      'campaignId' => 42,
      'roomId' => 'room-alpha',
      'entities' => [
        ['entityId' => 'hero-1', 'name' => 'Hero', 'initiative' => 18, 'hp' => 20, 'max_hp' => 20, 'team' => 'player'],
        ['entityId' => 'goblin-1', 'name' => 'Goblin', 'initiative' => 12, 'hp' => 10, 'max_hp' => 10, 'team' => 'enemy'],
      ],
    ]);

    $this->assertSession()->statusCodeEquals(201);
    $this->assertArrayHasKey('encounter_id', $response);
    $this->assertSame('active', $response['status'] ?? NULL);
    $this->assertCount(2, $response['participants'] ?? []);
  }

  /**
   * Tests combat start API without authentication - negative case.
   */
  public function testCombatStartApiNegative(): void {
    $this->requestJson('POST', '/api/combat/start', [
      'entities' => [
        ['entityId' => 'hero-1', 'name' => 'Hero', 'initiative' => 18],
      ],
    ]);
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests combat end turn API - positive case.
   */
  public function testCombatEndTurnApiPositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $encounter_id = $this->startEncounterAndGetId();
    $response = $this->requestJson('POST', '/api/combat/end-turn', [
      'encounterId' => $encounter_id,
    ]);

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSame($encounter_id, (int) ($response['encounter_id'] ?? 0));
    $this->assertArrayHasKey('turn_index', $response);
    $this->assertArrayHasKey('current_round', $response);
  }

  /**
   * Tests combat end API - positive case.
   */
  public function testCombatEndApiPositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $encounter_id = $this->startEncounterAndGetId();
    $response = $this->requestJson('POST', '/api/combat/end', [
      'encounterId' => $encounter_id,
    ]);

    $this->assertSession()->statusCodeEquals(200);
    $this->assertTrue(($response['ended'] ?? FALSE) === TRUE);
    $this->assertSame($encounter_id, (int) ($response['encounter_id'] ?? 0));
  }

  /**
   * Tests combat attack API - positive case.
   */
  public function testCombatAttackApiPositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $encounter_id = $this->startEncounterAndGetId();
    $response = $this->requestJson('POST', '/api/combat/attack', [
      'encounterId' => $encounter_id,
      'attackerId' => 'hero-1',
      'targetId' => 'goblin-1',
      'action' => [
        'damage' => 5,
        'damage_type' => 'slashing',
      ],
    ]);

    $this->assertSession()->statusCodeEquals(200);
    $this->assertTrue(($response['success'] ?? FALSE) === TRUE);
    $this->assertSame(5, (int) ($response['result']['damage'] ?? 0));
    $this->assertArrayHasKey('state', $response);
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
   * Start an encounter and return encounter ID.
   */
  private function startEncounterAndGetId(): int {
    $response = $this->requestJson('POST', '/api/combat/start', [
      'campaignId' => 42,
      'roomId' => 'room-alpha',
      'entities' => [
        ['entityId' => 'hero-1', 'name' => 'Hero', 'initiative' => 18, 'hp' => 20, 'max_hp' => 20, 'team' => 'player'],
        ['entityId' => 'goblin-1', 'name' => 'Goblin', 'initiative' => 12, 'hp' => 10, 'max_hp' => 10, 'team' => 'enemy'],
      ],
    ]);

    $this->assertSession()->statusCodeEquals(201);
    return (int) ($response['encounter_id'] ?? 0);
  }

  /**
   * Issue a JSON request and decode response.
   */
  private function requestJson(string $method, string $path, array $payload): array {
    $this->getSession()->getDriver()->getClient()->request(
      $method,
      $this->buildUrl($path),
      [],
      [],
      ['CONTENT_TYPE' => 'application/json'],
      json_encode($payload)
    );

    return json_decode($this->getSession()->getPage()->getContent(), TRUE) ?? [];
  }

}
