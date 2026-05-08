<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\dungeoncrawler_content\Functional\Traits\TestDataFactoryTrait;
use Drupal\Tests\dungeoncrawler_content\Functional\Traits\TestFixtureTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests entity lifecycle API (spawn/move/despawn).
 *
 * @group dungeoncrawler_content
 * @group api
 */
#[RunTestsInSeparateProcesses]
class EntityLifecycleTest extends BrowserTestBase {

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
   * Test entity spawn, move, and despawn workflow.
   */
  public function testEntityLifecycle() {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    // Create a campaign using factory.
    $campaign_id = $this->createTestCampaign(['uid' => $user->id()]);

    // Load entity fixture and create spawn data.
    $fixture = $this->loadFixture('entities/goblin_warrior.json');
    $spawn_payload = [
      'type' => $fixture['type'],
      'instanceId' => 'test-goblin-1',
      'characterId' => $fixture['characterId'],
      'locationType' => $fixture['locationType'],
      'locationRef' => $fixture['locationRef'],
      'stateData' => $fixture['stateData'],
    ];

    // 1. Spawn an NPC entity.
    $result = $this->requestJson('POST', "/api/campaign/{$campaign_id}/entity/spawn", $spawn_payload);
    $this->assertTrue($result['success'], 'Entity spawn should succeed');
    $this->assertArrayHasKey('data', $result, 'Response should contain data');
    
    // Assert spawn response fields.
    $this->assertEquals('test-goblin-1', $result['data']['instanceId'], 'Instance ID should match');
    $this->assertEquals('npc', $result['data']['type'], 'Type should be npc');
    $this->assertEquals('room-1', $result['data']['locationRef'], 'Location should be room-1');
    $this->assertArrayHasKey('stateData', $result['data'], 'Response should contain stateData');
    
    // Assert entity state fields.
    $state = $result['data']['stateData'];
    $this->assertEquals(8, $state['hp'], 'HP should match fixture');
    $this->assertEquals(8, $state['maxHp'], 'Max HP should match fixture');
    $this->assertEquals('hex-5', $state['hexId'], 'Hex ID should match');
    $this->assertEquals('Goblin Warrior', $state['name'], 'Name should match');
    $this->assertEquals(1, $state['level'], 'Level should be 1');

    // 2. List entities in room-1.
    $this->drupalGet("/api/campaign/{$campaign_id}/entities?locationType=room&locationRef=room-1");
    $this->assertSession()->statusCodeEquals(200);
    $list_result = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertTrue($list_result['success'], 'Entity list should succeed');
    $this->assertEquals(1, $list_result['count'], 'Should have 1 entity');
    $this->assertIsArray($list_result['data'], 'Data should be an array');
    $this->assertEquals('test-goblin-1', $list_result['data'][0]['instanceId'], 'Entity should be in list');
    $this->assertEquals('npc', $list_result['data'][0]['type'], 'Type should be npc');

    // 3. Move entity to room-2.
    $move_payload = $this->createEntityMoveData(['locationRef' => 'room-2']);

    $result = $this->requestJson('POST', "/api/campaign/{$campaign_id}/entity/test-goblin-1/move", $move_payload);
    $this->assertTrue($result['success'], 'Entity move should succeed');
    $this->assertArrayHasKey('data', $result, 'Response should contain data');
    $this->assertEquals('room-2', $result['data']['locationRef'], 'Location should be updated');
    $this->assertEquals('test-goblin-1', $result['data']['instanceId'], 'Instance ID should remain');

    // 4. Verify entity is now in room-2.
    $this->drupalGet("/api/campaign/{$campaign_id}/entities?locationType=room&locationRef=room-2");
    $list_result = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertTrue($list_result['success'], 'List should succeed');
    $this->assertEquals(1, $list_result['count'], 'Should have 1 entity in room-2');
    $this->assertEquals('test-goblin-1', $list_result['data'][0]['instanceId'], 'Entity should be in room-2');

    // 5. Despawn entity.
    $result = $this->requestJson('DELETE', "/api/campaign/{$campaign_id}/entity/test-goblin-1");
    $this->assertTrue($result['success'], 'Entity despawn should succeed');

    // 6. Verify entity no longer exists.
    $this->drupalGet("/api/campaign/{$campaign_id}/entities");
    $list_result = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertTrue($list_result['success'], 'List should succeed');
    $this->assertEquals(0, $list_result['count'], 'Should have 0 entities after despawn');
  }

  /**
   * Test spawning entity with duplicate instanceId fails.
   */
  public function testDuplicateInstanceIdFails() {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    // Create a campaign using factory.
    $campaign_id = $this->createTestCampaign(['uid' => $user->id()]);

    // Spawn first entity using factory.
    $spawn_payload = $this->createEntitySpawnData([
      'instanceId' => 'duplicate-test',
    ]);

    $result = $this->requestJson('POST', "/api/campaign/{$campaign_id}/entity/spawn", $spawn_payload);
    $this->assertTrue($result['success'], 'First spawn should succeed');

    // Try to spawn another entity with same instanceId.
    $result = $this->requestJson('POST', "/api/campaign/{$campaign_id}/entity/spawn", $spawn_payload);
    $this->assertFalse($result['success'], 'Duplicate instanceId should fail');
    $this->assertArrayHasKey('error', $result, 'Response should contain error');
    $this->assertStringContainsString('already exists', $result['error'], 'Error should mention entity exists');
  }

  /**
   * Test moving non-existent entity returns 404.
   */
  public function testMoveNonExistentEntity() {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    // Create a campaign using factory.
    $campaign_id = $this->createTestCampaign(['uid' => $user->id()]);

    // Try to move non-existent entity.
    $move_payload = $this->createEntityMoveData();

    $result = $this->requestJson('POST', "/api/campaign/{$campaign_id}/entity/non-existent/move", $move_payload);
    $this->assertFalse($result['success'], 'Move should fail for non-existent entity');
    $this->assertArrayHasKey('error', $result, 'Response should contain error');
    $this->assertStringContainsString('not found', $result['error'], 'Error should mention not found');
  }

  /**
   * Test entity type validation.
   */
  public function testInvalidEntityType() {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    // Create a campaign using factory.
    $campaign_id = $this->createTestCampaign(['uid' => $user->id()]);

    // Try to spawn entity with invalid type using factory.
    $spawn_payload = $this->createEntitySpawnData([
      'type' => 'invalid_type',
      'instanceId' => 'test-invalid',
    ]);

    $result = $this->requestJson('POST', "/api/campaign/{$campaign_id}/entity/spawn", $spawn_payload);
    $this->assertFalse($result['success'], 'Invalid type should fail');
    $this->assertArrayHasKey('error', $result, 'Response should contain error');
    $this->assertStringContainsString('Invalid type', $result['error'], 'Error should mention invalid type');
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
