<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests CharacterStateController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 * @group api
 */
class CharacterStateControllerTest extends BrowserTestBase {

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

    $character_id = $this->createCharacterForUser($user->id());

    $this->drupalGet("/api/character/{$character_id}/state", ['query' => ['_format' => 'json']]);
    $this->assertSession()->statusCodeEquals(200);

    $data = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertTrue(($data['success'] ?? FALSE) === TRUE);
    $this->assertArrayHasKey('data', $data);
  }

  /**
   * Tests get character state API without permission - negative case.
   */
  public function testGetCharacterStateNegative(): void {
    $this->drupalGet('/api/character/1/state');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests update character state API - positive case.
   */
  public function testUpdateCharacterStatePositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $character_id = $this->createCharacterForUser($user->id());

    $response = $this->requestJson(
      'POST',
      "/api/character/{$character_id}/update",
      [
        'state' => [
          'basicInfo' => [
            'name' => 'Updated Test Character',
          ],
        ],
      ]
    );

    $this->assertSession()->statusCodeEquals(200);
    $this->assertTrue(($response['success'] ?? FALSE) === TRUE);
    $this->assertArrayHasKey('data', $response);
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

    $character_id = $this->createCharacterForUser($user->id());

    $this->drupalGet("/api/character/{$character_id}/summary", ['query' => ['_format' => 'json']]);
    $this->assertSession()->statusCodeEquals(200);

    $data = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertTrue(($data['success'] ?? FALSE) === TRUE);
    $this->assertArrayHasKey('data', $data);
    $this->assertSame((string) $character_id, (string) ($data['data']['characterId'] ?? ''));
  }

  /**
   * Create a character row for controller tests.
   */
  private function createCharacterForUser(int $uid): int {
    return (int) $this->container->get('database')->insert('dc_characters')
      ->fields([
        'uuid' => $this->container->get('uuid')->generate(),
        'uid' => $uid,
        'name' => 'State Test Character',
        'level' => 1,
        'ancestry' => 'human',
        'class' => 'fighter',
        'character_data' => json_encode([
          'name' => 'State Test Character',
          'level' => 1,
          'class' => 'fighter',
        ]),
        'status' => 1,
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();
  }

  /**
   * Issue a JSON request and return decoded response.
   */
  private function requestJson(string $method, string $path, ?array $payload = NULL): array {
    $body = $payload !== NULL ? json_encode($payload) : '';
    $this->getSession()->getDriver()->getClient()->request(
      $method,
      $this->buildUrl($path),
      [],
      [],
      ['CONTENT_TYPE' => 'application/json'],
      $body
    );

    return json_decode($this->getSession()->getPage()->getContent(), TRUE) ?? [];
  }

}
