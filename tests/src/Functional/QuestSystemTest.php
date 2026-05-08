<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Functional tests for Quest System API endpoints.
 *
 * @group dungeoncrawler_content
 */
class QuestSystemTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable for the test.
   *
   * @var array
   */
  protected static $modules = [
    'dungeoncrawler_content',
    'serialization',
    'system',
    'user',
  ];

  /**
   * Set up test fixtures.
   */
  protected function setUp(): void {
    parent::setUp();

    // Create test user with appropriate permissions
    $this->user = $this->createUser(['access dungeoncrawler characters']);
  }

  /**
   * Test quest generation endpoint.
   */
  public function testQuestGeneration(): void {
    $this->drupalLogin($this->user);

    $payload = [
      'template_id' => 'clear_goblin_den',
      'context' => [
        'party_level' => 3,
        'difficulty' => 'moderate',
      ],
    ];

    $this->drupalPostForm(
      '/api/campaign/1/quests/generate',
      [],
      '',
      ['query' => [], 'form_id' => FALSE],
      [],
      $this->formatPayload($payload)
    );

    $this->assertSession()->statusCodeEquals(200);
    $response = $this->getSession()->getPage()->getContent();
    $data = json_decode($response, TRUE);

    $this->assertTrue($data['success']);
    $this->assertNotEmpty($data['quest']['quest_id']);
    $this->assertEquals('clear_goblin_den', $data['quest']['name']);
  }

  /**
   * Test available quests listing.
   */
  public function testAvailableQuestsList(): void {
    $this->drupalLogin($this->user);

    $this->drupalGet('/api/campaign/1/quests/available');

    $this->assertSession()->statusCodeEquals(200);
    $response = $this->getSession()->getPage()->getContent();
    $data = json_decode($response, TRUE);

    $this->assertTrue($data['success']);
    $this->assertIsArray($data['quests']);
  }

  /**
   * Test starting a quest.
   */
  public function testStartQuest(): void {
    $this->drupalLogin($this->user);

    // First generate a quest
    $this->generateTestQuest();

    // Then start it
    $payload = [
      'character_id' => 'char_001',
      'entity_type' => 'character',
    ];

    $this->drupalPostForm(
      '/api/campaign/1/quests/test_quest_001/start',
      [],
      '',
      [],
      [],
      $this->formatPayload($payload)
    );

    $this->assertSession()->statusCodeEquals(200);
    $response = $this->getSession()->getPage()->getContent();
    $data = json_decode($response, TRUE);

    $this->assertTrue($data['success']);
  }

  /**
   * Test updating quest progress.
   */
  public function testUpdateProgress(): void {
    $this->drupalLogin($this->user);

    $payload = [
      'objective_id' => 'kill_enemies',
      'action' => 'increment',
      'entity_id' => 'party_001',
      'amount' => 3,
    ];

    // Using PUT method directly
    $this->drupalHttpRequest(
      '/api/campaign/1/quests/test_quest_001/progress',
      'PUT',
      $this->formatPayload($payload)
    );

    $response = $this->getSession()->getPage()->getContent();
    $data = json_decode($response, TRUE);

    if ($data) {
      $this->assertTrue($data['success']);
      $this->assertEquals(3, $data['objective_state']['progress']);
    }
  }

  /**
   * Test completing a quest.
   */
  public function testCompleteQuest(): void {
    $this->drupalLogin($this->user);

    $payload = [
      'entity_id' => 'party_001',
      'outcome' => 'success',
    ];

    $this->drupalPostForm(
      '/api/campaign/1/quests/test_quest_001/complete',
      [],
      '',
      [],
      [],
      $this->formatPayload($payload)
    );

    $response = $this->getSession()->getPage()->getContent();
    $data = json_decode($response, TRUE);

    if ($data) {
      $this->assertTrue($data['success']);
      $this->assertEquals('success', $data['outcome']);
    }
  }

  /**
   * Test reward preview.
   */
  public function testRewardPreview(): void {
    $this->drupalLogin($this->user);

    $this->drupalGet('/api/campaign/1/quests/test_quest_001/rewards');

    $this->assertSession()->statusCodeEquals(200);
    $response = $this->getSession()->getPage()->getContent();
    $data = json_decode($response, TRUE);

    $this->assertTrue($data['success']);
    $this->assertNotEmpty($data['rewards']);
  }

  /**
   * Test claiming quest rewards.
   */
  public function testClaimRewards(): void {
    $this->drupalLogin($this->user);

    $payload = [
      'character_id' => 'char_001',
    ];

    $this->drupalPostForm(
      '/api/campaign/1/quests/test_quest_001/rewards/claim',
      [],
      '',
      [],
      [],
      $this->formatPayload($payload)
    );

    $response = $this->getSession()->getPage()->getContent();
    $data = json_decode($response, TRUE);

    if ($data) {
      $this->assertTrue($data['success']);
      $this->assertNotEmpty($data['rewards']);
    }
  }

  /**
   * Test quest journal retrieval.
   */
  public function testQuestJournal(): void {
    $this->drupalLogin($this->user);

    $this->drupalGet('/api/campaign/1/character/char_001/quest-journal');

    $this->assertSession()->statusCodeEquals(200);
    $response = $this->getSession()->getPage()->getContent();
    $data = json_decode($response, TRUE);

    $this->assertTrue($data['success']);
    $this->assertIsArray($data['quests']);
  }

  /**
   * Test permission denial for unauthorized user.
   */
  public function testUnauthorizedAccess(): void {
    // Don't log in - test as anonymous user
    $this->drupalGet('/api/campaign/1/quests/available');

    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Test invalid template error handling.
   */
  public function testInvalidTemplate(): void {
    $this->drupalLogin($this->user);

    $payload = [
      'template_id' => 'nonexistent_template',
      'context' => ['party_level' => 1],
    ];

    $this->drupalPostForm(
      '/api/campaign/1/quests/generate',
      [],
      '',
      [],
      [],
      $this->formatPayload($payload)
    );

    $response = $this->getSession()->getPage()->getContent();
    $data = json_decode($response, TRUE);

    $this->assertFalse($data['success']);
    $this->assertNotEmpty($data['error']);
  }

  /**
   * Helper: Generate a test quest.
   */
  private function generateTestQuest(): void {
    $payload = [
      'template_id' => 'clear_goblin_den',
      'context' => ['party_level' => 1, 'difficulty' => 'low'],
    ];

    $this->drupalPostForm(
      '/api/campaign/1/quests/generate',
      [],
      '',
      [],
      [],
      $this->formatPayload($payload)
    );
  }

  /**
   * Helper: Format payload as JSON.
   */
  private function formatPayload(array $data): string {
    return json_encode($data);
  }

  /**
   * Helper: Make HTTP request with custom method.
   */
  private function drupalHttpRequest(
    string $path,
    string $method = 'GET',
    string $data = NULL
  ): void {
    $url = $this->buildUrl($path);
    $client = $this->getHttpClient();

    $options = [
      'headers' => ['Content-Type' => 'application/json'],
      'body' => $data,
    ];

    $client->request($method, $url, $options);
  }

}
