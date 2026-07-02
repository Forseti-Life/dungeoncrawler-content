<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests CombatActionController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 */
class CombatActionControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests combat attack endpoint with seeded encounter state.
   */
  public function testCombatAttackEndpointPositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $encounter_id = $this->startEncounterAndGetId();

    $response = $this->requestJson('POST', '/api/combat/attack', [
      'encounterId' => $encounter_id,
      'attackerId' => 'hero-1',
      'targetId' => 'goblin-1',
      'action' => ['damage' => 3],
    ]);

    $this->assertSession()->statusCodeEquals(200);
    $this->assertTrue(($response['success'] ?? FALSE) === TRUE);
    $this->assertSame(3, (int) ($response['result']['damage'] ?? 0));
  }

  /**
   * Tests combat attack endpoint method contract.
   */
  public function testCombatAttackEndpointNegativeMethod(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/api/combat/attack');
    $this->assertSession()->statusCodeEquals(405);
  }

  /**
   * Tests combat attack endpoint requires permission/auth.
   */
  public function testCombatAttackEndpointNegativeAnonymous(): void {
    $this->requestJson('POST', '/api/combat/attack', []);
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
