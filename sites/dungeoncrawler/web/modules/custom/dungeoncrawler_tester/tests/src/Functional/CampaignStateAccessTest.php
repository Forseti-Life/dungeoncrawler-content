<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\dungeoncrawler_tester\Functional\Traits\CampaignStateTestTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests campaign state API access control.
 *
 * @group dungeoncrawler_content
 * @group api
 */
#[RunTestsInSeparateProcesses]
class CampaignStateAccessTest extends BrowserTestBase {

  use CampaignStateTestTrait;

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

    $campaign_id = $this->createCampaignForUser($owner);

    // Test GET /api/campaign/{id}/state - should succeed.
    $this->drupalGet("/api/campaign/{$campaign_id}/state");
    $this->assertSession()->statusCodeEquals(200);
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertTrue($response['success']);
    $this->assertEquals($campaign_id, $response['data']['campaignId']);

    // Test POST /api/campaign/{id}/state - should succeed.
    $state_payload = [
      'expectedVersion' => 1,
      'state' => [
        'created_by' => $owner->id(),
        'started' => TRUE,
        'progress' => [
          ['type' => 'test_event', 'timestamp' => time()],
        ],
      ],
    ];

    $result = $this->requestJson('POST', "/api/campaign/{$campaign_id}/state", $state_payload);
    $this->assertTrue($result['success']);
    $this->assertEquals(2, $result['version']);
  }

  /**
   * Test non-owner gets 403 forbidden.
   */
  public function testNonOwnerDenied() {
    // Create owner and another user.
    $owner = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $other_user = $this->drupalCreateUser(['access dungeoncrawler characters']);

    $campaign_id = $this->createCampaignForUser($owner, 'Owner Campaign');

    // Login as other_user and try to access.
    $this->drupalLogin($other_user);
    
    // Test GET - should get 403.
    $this->drupalGet("/api/campaign/{$campaign_id}/state");
    $this->assertSession()->statusCodeEquals(403);
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertFalse($response['success']);
    $this->assertStringContainsString('Access denied', $response['error']);

    // Test POST - should get 403.
    $state_payload = [
      'expectedVersion' => 1,
      'state' => ['created_by' => $other_user->id(), 'started' => TRUE, 'progress' => []],
    ];

    $result = $this->requestJson('POST', "/api/campaign/{$campaign_id}/state", $state_payload);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Access denied', $result['error']);
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

    $campaign_id = $this->createCampaignForUser($owner, 'Owner Campaign');

    // Login as admin and access should succeed.
    $this->drupalLogin($admin);
    
    $this->drupalGet("/api/campaign/{$campaign_id}/state");
    $this->assertSession()->statusCodeEquals(200);
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertTrue($response['success']);
  }

}
