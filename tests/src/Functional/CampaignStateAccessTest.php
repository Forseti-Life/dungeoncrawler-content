<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\dungeoncrawler_content\Functional\Traits\TestDataFactoryTrait;
use Drupal\Tests\dungeoncrawler_content\Functional\Traits\TestFixtureTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests campaign state API access control.
 *
 * @group dungeoncrawler_content
 * @group api
 */
#[RunTestsInSeparateProcesses]
class CampaignStateAccessTest extends BrowserTestBase {

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
   * Test campaign owner can access their campaign state.
   */
  public function testCampaignOwnerAccess() {
    // Create a user with dungeoncrawler permissions.
    $owner = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($owner);

    // Load campaign state fixture.
    $fixture = $this->loadFixture('campaigns/basic_campaign_state.json');
    $campaign_id = $this->createTestCampaign([
      'uid' => $owner->id(),
      'state' => $fixture['state'],
      'version' => $fixture['state_meta']['version'],
    ]);

    // Test GET /api/campaign/{id}/state - should succeed.
    $this->drupalGet("/api/campaign/{$campaign_id}/state");
    $this->assertSession()->statusCodeEquals(200);
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    
    // Assert response structure and fields.
    $this->assertTrue($response['success'], 'Response should be successful');
    $this->assertArrayHasKey('data', $response, 'Response should have data key');
    $this->assertEquals($campaign_id, $response['data']['campaignId'], 'Campaign ID should match');
    
    // Assert campaign state fields.
    $this->assertArrayHasKey('state', $response['data'], 'Response should contain state');
    $this->assertEquals($owner->id(), $response['data']['state']['created_by'], 'Creator ID should match');
    $this->assertTrue($response['data']['state']['started'], 'Campaign should be started');
    $this->assertIsArray($response['data']['state']['progress'], 'Progress should be an array');
    
    // Assert state metadata.
    $this->assertArrayHasKey('state_meta', $response['data'], 'Response should contain state_meta');
    $this->assertEquals(1, $response['data']['state_meta']['version'], 'Version should be 1');
    $this->assertArrayHasKey('updatedAt', $response['data']['state_meta'], 'Should have updatedAt');

    // Test POST /api/campaign/{id}/state - should succeed.
    $updated_state = $this->createCampaignState([
      'created_by' => $owner->id(),
      'progress' => [
        ['type' => 'quest_started', 'quest_id' => 'test_quest', 'timestamp' => time()],
      ],
      'active_hex' => 'q1r1',
      'party_gold' => 150,
    ]);
    
    $state_payload = [
      'expectedVersion' => 1,
      'state' => $updated_state,
    ];

    $result = $this->requestJson('POST', "/api/campaign/{$campaign_id}/state", $state_payload);
    $this->assertTrue($result['success'], 'State update should succeed');
    $this->assertEquals(2, $result['version'], 'Version should increment to 2');
    $this->assertArrayHasKey('state', $result, 'Response should contain updated state');
    $this->assertEquals('q1r1', $result['state']['active_hex'], 'Active hex should be updated');
    $this->assertEquals(150, $result['state']['party_gold'], 'Party gold should be updated');
  }

  /**
   * Test non-owner gets 403 forbidden.
   */
  public function testNonOwnerDenied() {
    // Create owner and another user.
    $owner = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $other_user = $this->drupalCreateUser(['access dungeoncrawler characters']);

    // Create a campaign owned by owner using factory.
    $campaign_id = $this->createTestCampaign([
      'uid' => $owner->id(),
      'name' => 'Owner Campaign',
    ]);

    // Login as other_user and try to access.
    $this->drupalLogin($other_user);
    
    // Test GET - should get 403.
    $this->drupalGet("/api/campaign/{$campaign_id}/state");
    $this->assertSession()->statusCodeEquals(403);
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertFalse($response['success'], 'Request should fail');
    $this->assertArrayHasKey('error', $response, 'Response should contain error');
    $this->assertStringContainsString('Access denied', $response['error'], 'Error message should mention access denial');

    // Test POST - should get 403.
    $state_payload = [
      'expectedVersion' => 1,
      'state' => $this->createCampaignState(['created_by' => $other_user->id()]),
    ];

    $result = $this->requestJson('POST', "/api/campaign/{$campaign_id}/state", $state_payload);
    $this->assertFalse($result['success'], 'POST request should fail');
    $this->assertArrayHasKey('error', $result, 'Response should contain error');
    $this->assertStringContainsString('Access denied', $result['error'], 'Error message should mention access denial');
  }

  /**
   * Test admin can access any campaign.
   */
  public function testAdminAccess() {
    // Create owner and admin.
    $owner = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $admin = $this->drupalCreateUser([
      'access dungeoncrawler characters',
      'administer dungeoncrawler content',
    ]);

    // Create a campaign owned by owner using factory.
    $campaign_id = $this->createTestCampaign([
      'uid' => $owner->id(),
      'name' => 'Owner Campaign',
    ]);

    // Login as admin and access should succeed.
    $this->drupalLogin($admin);
    
    $this->drupalGet("/api/campaign/{$campaign_id}/state");
    $this->assertSession()->statusCodeEquals(200);
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertTrue($response['success'], 'Admin should have access');
    $this->assertArrayHasKey('data', $response, 'Response should contain data');
    $this->assertEquals($campaign_id, $response['data']['campaignId'], 'Campaign ID should match');
    $this->assertArrayHasKey('state', $response['data'], 'Response should contain state');
    $this->assertArrayHasKey('state_meta', $response['data'], 'Response should contain state_meta');
  }

  /**
   * Issue a JSON request with the given method and payload.
   */
  private function requestJson(string $method, string $path, ?array $payload = NULL): array {
    $body = $payload !== NULL ? json_encode($payload) : NULL;
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
