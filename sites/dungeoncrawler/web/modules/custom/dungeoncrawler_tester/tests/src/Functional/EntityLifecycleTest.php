<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\dungeoncrawler_tester\Functional\Traits\CampaignStateTestTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests entity lifecycle API (spawn/move/despawn).
 *
 * @group dungeoncrawler_content
 * @group api
 */
#[RunTestsInSeparateProcesses]
class EntityLifecycleTest extends BrowserTestBase {

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
   * Test entity spawn, move, and despawn workflow.
   */
  public function testEntityLifecycle() {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $campaign_id = $this->createCampaignForUser($user, 'Test Campaign');

    // 1. Spawn an NPC entity.
    $spawn_payload = [
      'type' => 'npc',
      'instanceId' => 'test-goblin-1',
      'characterId' => 999,
      'locationType' => 'room',
      'locationRef' => 'room-1',
      'stateData' => [
        'hp' => 8,
        'maxHp' => 8,
        'hexId' => 'hex-5',
      ],
    ];

    $result = $this->requestJson('POST', "/api/campaign/{$campaign_id}/entity/spawn", $spawn_payload);
    $this->assertTrue($result['success'], 'Entity spawn should succeed');
    $this->assertEquals('test-goblin-1', $result['data']['instanceId']);
    $this->assertEquals('npc', $result['data']['type']);
    $this->assertEquals('room-1', $result['data']['locationRef']);

    // 2. List entities in room-1.
    $this->drupalGet("/api/campaign/{$campaign_id}/entities?locationType=room&locationRef=room-1");
    $this->assertSession()->statusCodeEquals(200);
    $list_result = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertTrue($list_result['success']);
    $this->assertEquals(1, $list_result['count']);
    $this->assertEquals('test-goblin-1', $list_result['data'][0]['instanceId']);

    // 3. Move entity to room-2.
    $move_payload = [
      'locationType' => 'room',
      'locationRef' => 'room-2',
    ];

    $result = $this->requestJson('POST', "/api/campaign/{$campaign_id}/entity/test-goblin-1/move", $move_payload);
    $this->assertTrue($result['success'], 'Entity move should succeed');
    $this->assertEquals('room-2', $result['data']['locationRef']);

    // 4. Verify entity is now in room-2.
    $this->drupalGet("/api/campaign/{$campaign_id}/entities?locationType=room&locationRef=room-2");
    $list_result = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertEquals(1, $list_result['count']);
    $this->assertEquals('test-goblin-1', $list_result['data'][0]['instanceId']);

    // 5. Despawn entity.
    $result = $this->requestJson('DELETE', "/api/campaign/{$campaign_id}/entity/test-goblin-1");
    $this->assertTrue($result['success'], 'Entity despawn should succeed');

    // 6. Verify entity no longer exists.
    $this->drupalGet("/api/campaign/{$campaign_id}/entities");
    $list_result = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertEquals(0, $list_result['count']);
  }

  /**
   * Test spawning entity with duplicate instanceId fails.
   */
  public function testDuplicateInstanceIdFails() {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $campaign_id = $this->createCampaignForUser($user, 'Test Campaign');

    // Spawn first entity.
    $spawn_payload = [
      'type' => 'npc',
      'instanceId' => 'duplicate-test',
      'locationType' => 'room',
      'locationRef' => 'room-1',
      'stateData' => [],
    ];

    $result = $this->requestJson('POST', "/api/campaign/{$campaign_id}/entity/spawn", $spawn_payload);
    $this->assertTrue($result['success']);

    // Try to spawn another entity with same instanceId.
    $result = $this->requestJson('POST', "/api/campaign/{$campaign_id}/entity/spawn", $spawn_payload);
    $this->assertFalse($result['success'], 'Duplicate instanceId should fail');
    $this->assertStringContainsString('already exists', $result['error']);
  }

  /**
   * Test moving non-existent entity returns 404.
   */
  public function testMoveNonExistentEntity() {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $campaign_id = $this->createCampaignForUser($user, 'Test Campaign');

    // Try to move non-existent entity.
    $move_payload = [
      'locationType' => 'room',
      'locationRef' => 'room-2',
    ];

    $result = $this->requestJson('POST', "/api/campaign/{$campaign_id}/entity/non-existent/move", $move_payload);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test entity type validation.
   */
  public function testInvalidEntityType() {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $campaign_id = $this->createCampaignForUser($user, 'Test Campaign');

    // Try to spawn entity with invalid type.
    $spawn_payload = [
      'type' => 'invalid_type',
      'instanceId' => 'test-invalid',
      'locationType' => 'room',
      'locationRef' => 'room-1',
      'stateData' => [],
    ];

    $result = $this->requestJson('POST', "/api/campaign/{$campaign_id}/entity/spawn", $spawn_payload);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid type', $result['error']);
  }

}
