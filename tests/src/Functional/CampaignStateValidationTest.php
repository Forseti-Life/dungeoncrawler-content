<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\dungeoncrawler_content\Functional\Traits\TestDataFactoryTrait;
use Drupal\Tests\dungeoncrawler_content\Functional\Traits\TestFixtureTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests campaign state schema validation.
 *
 * @group dungeoncrawler_content
 * @group api
 */
#[RunTestsInSeparateProcesses]
class CampaignStateValidationTest extends BrowserTestBase {

  use TestDataFactoryTrait;
  use TestFixtureTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * Test valid campaign state payload succeeds.
   */
  public function testValidStateAccepted() {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    // Create a campaign using factory.
    $campaign_id = $this->createTestCampaign(['uid' => $user->id()]);

    // Load and use campaign state fixture.
    $fixture = $this->loadFixture('campaigns/active_campaign_state.json');
    $valid_payload = [
      'expectedVersion' => 1,
      'state' => $fixture['state'],
    ];

    $result = $this->requestJson('POST', "/api/campaign/{$campaign_id}/state", $valid_payload);
    $this->assertTrue($result['success'], 'Valid payload should be accepted');
    $this->assertEquals(2, $result['version'], 'Version should increment');
    $this->assertArrayHasKey('state', $result, 'Response should contain state');
    
    // Assert specific state fields were preserved.
    $this->assertEquals($fixture['state']['active_hex'], $result['state']['active_hex'], 'Active hex should match');
    $this->assertEquals($fixture['state']['party_gold'], $result['state']['party_gold'], 'Party gold should match');
    $this->assertCount(3, $result['state']['progress'], 'Progress should have 3 entries');
    $this->assertIsArray($result['state']['active_quests'], 'Active quests should be an array');
    $this->assertContains('goblin_caves', $result['state']['active_quests'], 'Should contain goblin_caves quest');
  }

  /**
   * Test missing required fields returns 400.
   */
  public function testMissingRequiredFields() {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    // Create a campaign using factory.
    $campaign_id = $this->createTestCampaign(['uid' => $user->id()]);

    // Invalid payload - missing required 'started' field.
    $invalid_payload = [
      'expectedVersion' => 1,
      'state' => [
        'created_by' => $user->id(),
        'progress' => [],
      ],
    ];

    $result = $this->requestJson('POST', "/api/campaign/{$campaign_id}/state", $invalid_payload);
    $this->assertFalse($result['success'], 'Invalid payload should be rejected');
    $this->assertArrayHasKey('error', $result, 'Response should contain error');
    $this->assertStringContainsString('Invalid state payload', $result['error'], 'Error should mention invalid payload');
    $this->assertArrayHasKey('validation_errors', $result, 'Should contain validation errors');
    $this->assertNotEmpty($result['validation_errors'], 'Validation errors should not be empty');
  }

  /**
   * Test invalid JSON returns 400.
   */
  public function testInvalidJson() {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    // Create a campaign using factory.
    $campaign_id = $this->createTestCampaign(['uid' => $user->id()]);

    // Send invalid JSON.
    $result = $this->requestRaw('POST', "/api/campaign/{$campaign_id}/state", '{invalid json}');
    $this->assertFalse($result['success'], 'Invalid JSON should be rejected');
    $this->assertArrayHasKey('error', $result, 'Response should contain error');
    $this->assertStringContainsString('Invalid JSON', $result['error'], 'Error should mention invalid JSON');
  }

  /**
   * Test missing state payload returns 400.
   */
  public function testMissingStatePayload() {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    // Create a campaign using factory.
    $campaign_id = $this->createTestCampaign(['uid' => $user->id()]);

    // Payload without state field.
    $invalid_payload = [
      'expectedVersion' => 1,
    ];

    $result = $this->requestJson('POST', "/api/campaign/{$campaign_id}/state", $invalid_payload);
    $this->assertFalse($result['success'], 'Missing state should be rejected');
    $this->assertArrayHasKey('error', $result, 'Response should contain error');
    $this->assertStringContainsString('Missing state payload', $result['error'], 'Error should mention missing state');
  }

  /**
   * Issue a JSON request with the given method and payload array.
   */
  private function requestJson(string $method, string $path, array $payload): array {
    return $this->requestRaw($method, $path, json_encode($payload));
  }

  /**
   * Issue a JSON request with raw body content.
   */
  private function requestRaw(string $method, string $path, string $body): array {
    $this->getSession()->getDriver()->getClient()->request(
      $method,
      $this->buildUrl($path),
      [],
      [],
      ['CONTENT_TYPE' => 'application/json'],
      $body
    );

    $content = $this->getSession()->getPage()->getContent();
    return json_decode($content, TRUE) ?? [];
  }

}
