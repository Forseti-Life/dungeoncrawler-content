<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests CombatApiController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 * @group api
 */
class CombatApiControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests combat get endpoint returns seeded encounter state.
   */
  public function testCombatGetEndpointPositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $encounter_id = $this->startEncounterAndGetId();
    $response = $this->requestJson('POST', '/api/combat/get', ['encounterId' => $encounter_id]);

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSame($encounter_id, (int) ($response['encounter_id'] ?? 0));
    $this->assertArrayHasKey('participants', $response);
    $this->assertArrayHasKey('initiative_order', $response);
  }

  /**
   * Tests combat set endpoint updates encounter state.
   */
  public function testCombatSetEndpointPositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $encounter_id = $this->startEncounterAndGetId();
    $response = $this->requestJson('POST', '/api/combat/set', [
      'encounterId' => $encounter_id,
      'status' => 'ended',
      'turn_index' => 0,
      'current_round' => 2,
    ]);

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSame('ended', $response['status'] ?? NULL);
    $this->assertSame(2, (int) ($response['current_round'] ?? 0));
  }

  /**
   * Tests combat get endpoint method contract.
   */
  public function testCombatGetEndpointNegativeMethod(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/api/combat/get');
    $this->assertSession()->statusCodeEquals(405);
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
