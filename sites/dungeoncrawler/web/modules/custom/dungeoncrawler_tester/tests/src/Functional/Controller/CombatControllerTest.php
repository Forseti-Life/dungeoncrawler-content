<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests CombatController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 */
class CombatControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests combat end-turn endpoint with seeded encounter state.
   */
  public function testCombatEndTurnEndpointPositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $encounter_id = $this->startEncounterAndGetId();
    $response = $this->requestJson('POST', '/api/combat/end-turn', ['encounterId' => $encounter_id]);

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSame($encounter_id, (int) ($response['encounter_id'] ?? 0));
    $this->assertArrayHasKey('turn_index', $response);
  }

  /**
   * Tests combat end endpoint contract.
   */
  public function testCombatEndEndpointPositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $encounter_id = $this->startEncounterAndGetId();
    $response = $this->requestJson('POST', '/api/combat/end', ['encounterId' => $encounter_id]);

    $this->assertSession()->statusCodeEquals(200);
    $this->assertTrue(($response['ended'] ?? FALSE) === TRUE);
    $this->assertSame($encounter_id, (int) ($response['encounter_id'] ?? 0));
  }

  /**
   * Tests combat end-turn endpoint requires auth/permission.
   */
  public function testCombatEndTurnEndpointNegativeAnonymous(): void {
    $this->requestJson('POST', '/api/combat/end-turn', ['encounterId' => 123]);
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Start an encounter and return encounter ID.
   */
  private function startEncounterAndGetId(): int {
    $response = $this->requestJson('POST', '/api/combat/start', [
      'entities' => [
        ['entityId' => 'hero-1', 'name' => 'Hero', 'initiative' => 18, 'hp' => 20],
        ['entityId' => 'goblin-1', 'name' => 'Goblin', 'initiative' => 12, 'hp' => 10],
      ],
    ]);

    $this->assertSession()->statusCodeEquals(201);
    return (int) ($response['encounter_id'] ?? 0);
  }

  /**
   * Issue JSON request and decode response.
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
