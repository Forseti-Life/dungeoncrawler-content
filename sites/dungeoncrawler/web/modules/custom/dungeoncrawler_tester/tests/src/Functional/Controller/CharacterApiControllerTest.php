<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests CharacterApiController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 * @group api
 */
class CharacterApiControllerTest extends BrowserTestBase {

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

    $response = $this->requestJson(
      'POST',
      '/api/character/save',
      [
        'step' => 2,
        'name' => 'API Test Character',
        'ancestry' => 'human',
        'class' => 'fighter',
        'abilities' => [
          'str' => 16,
          'dex' => 12,
          'con' => 14,
          'int' => 10,
          'wis' => 10,
          'cha' => 8,
        ],
      ],
      ['HTTP_X_CSRF_TOKEN' => $this->container->get('csrf_token')->get('rest')]
    );

    $this->assertSession()->statusCodeEquals(200);
    $this->assertTrue(($response['success'] ?? FALSE) === TRUE);
    $this->assertSame('created', $response['action'] ?? NULL);
    $this->assertArrayHasKey('character_id', $response);
  }

  /**
   * Tests character save API without permission - negative case.
   */
  public function testCharacterSaveApiNegativeNoPermission(): void {
    $user = $this->drupalCreateUser([]);
    $this->drupalLogin($user);

    $this->requestJson(
      'POST',
      '/api/character/save',
      [
        'step' => 1,
        'name' => 'Blocked Character',
      ],
      ['HTTP_X_CSRF_TOKEN' => $this->container->get('csrf_token')->get('rest')]
    );

    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests character load API - positive case.
   */
  public function testCharacterLoadApiPositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $character_id = $this->createCharacterForUser($user->id());

    $this->drupalGet("/api/character/load/{$character_id}", ['query' => ['_format' => 'json']]);
    $this->assertSession()->statusCodeEquals(200);

    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertTrue(($response['success'] ?? FALSE) === TRUE);
    $this->assertSame($character_id, (int) ($response['character']['id'] ?? 0));
    $this->assertSame('Loaded Character', $response['character']['name'] ?? NULL);
  }

  /**
   * Tests character load API without authentication - negative case.
   */
  public function testCharacterLoadApiNegativeNoAuth(): void {
    $this->drupalGet('/api/character/load/1');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Create a character row for API tests.
   */
  private function createCharacterForUser(int $uid): int {
    return (int) $this->container->get('database')->insert('dc_characters')
      ->fields([
        'uuid' => $this->container->get('uuid')->generate(),
        'uid' => $uid,
        'name' => 'Loaded Character',
        'level' => 1,
        'ancestry' => 'human',
        'class' => 'fighter',
        'character_data' => json_encode([
          'name' => 'Loaded Character',
          'step' => 2,
          'ancestry' => 'human',
          'class' => 'fighter',
        ]),
        'status' => 0,
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();
  }

  /**
   * Issue a JSON request and return decoded response.
   */
  private function requestJson(string $method, string $path, array $payload, array $headers = []): array {
    $this->getSession()->getDriver()->getClient()->request(
      $method,
      $this->buildUrl($path),
      [],
      [],
      ['CONTENT_TYPE' => 'application/json'] + $headers,
      json_encode($payload)
    );

    return json_decode($this->getSession()->getPage()->getContent(), TRUE) ?? [];
  }

}
