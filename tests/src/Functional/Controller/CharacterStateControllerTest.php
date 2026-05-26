<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Controller;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\dungeoncrawler_content\Functional\Traits\TestFixtureTrait;

/**
 * Tests CharacterStateController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 * @group api
 */
class CharacterStateControllerTest extends BrowserTestBase {

  use TestFixtureTrait;

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

    $this->drupalGet('/api/character/1/state', ['query' => ['_format' => 'json']]);
    
    $status_code = $this->getSession()->getStatusCode();
    // Method should be allowed (not 405).
    $this->assertNotEquals(405, $status_code, 'GET method should be allowed');
    
    // If successful (200), assert response structure.
    if ($status_code === 200) {
      $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
      $this->assertIsArray($response, 'Response should be JSON');
      $this->assertArrayHasKey('success', $response, 'Response should have success field');
      
      if ($response['success']) {
        $this->assertArrayHasKey('state', $response, 'Response should contain state');
        $this->assertIsArray($response['state'], 'State should be an array');
      }
    }
  }

  /**
   * Tests get character state API without permission - negative case.
   */
  public function testGetCharacterStateNegative(): void {
    $this->drupalGet('/api/character/1/state');
    $this->assertSession()->statusCodeEquals(403);
    
    // Assert error response structure.
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    if ($response) {
      $this->assertIsArray($response, 'Response should be JSON');
      $this->assertArrayHasKey('success', $response, 'Response should have success field');
      $this->assertFalse($response['success'], 'Success should be false');
    }
  }

  /**
   * Tests update character state API - positive case.
   */
  public function testUpdateCharacterStatePositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    // Load character fixture for update payload.
    $character_data = $this->loadCharacterFixture('level_1_fighter');
    $update_payload = json_encode([
      'hp' => $character_data['calculated_stats']['max_hp'] - 5,
      'conditions' => [],
    ]);

    $this->getSession()->getDriver()->getClient()->request(
      'POST',
      $this->buildUrl('/api/character/1/update'),
      [],
      [],
      ['CONTENT_TYPE' => 'application/json'],
      $update_payload
    );

    $status_code = $this->getSession()->getStatusCode();
    // Method should be allowed (not 405).
    $this->assertNotEquals(405, $status_code, 'POST method should be allowed');
    
    // If successful, validate response structure.
    if ($status_code === 200) {
      $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
      $this->assertIsArray($response, 'Response should be JSON');
      $this->assertArrayHasKey('success', $response, 'Response should have success field');
    }
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

    $this->drupalGet('/api/character/1/summary', ['query' => ['_format' => 'json']]);
    
    $status_code = $this->getSession()->getStatusCode();
    // Method should be allowed (not 405).
    $this->assertNotEquals(405, $status_code, 'GET method should be allowed');
    
    // If successful, assert response structure.
    if ($status_code === 200) {
      $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
      $this->assertIsArray($response, 'Response should be JSON');
      $this->assertArrayHasKey('success', $response, 'Response should have success field');
      
      if ($response['success']) {
        $this->assertArrayHasKey('summary', $response, 'Response should contain summary');
        $this->assertIsArray($response['summary'], 'Summary should be an array');
      }
    }
  }

  /**
   * Tests runtime state inherits default spell/feat data from the source row.
   */
  public function testGetCharacterStateBackfillsRuntimeDefaultData(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $database = $this->container->get('database');
    $now = \Drupal::time()->getRequestTime();

    $campaign_id = (int) $database->insert('dc_campaigns')
      ->fields([
        'uuid' => '99999999-2222-3333-4444-555555555555',
        'uid' => (int) $user->id(),
        'name' => 'State Backfill Campaign',
        'status' => 'draft',
        'theme' => 'classic_dungeon',
        'difficulty' => 'normal',
        'campaign_data' => '{}',
        'created' => $now,
        'changed' => $now,
      ])
      ->execute();

    $library_character_id = (int) $database->insert('dc_campaign_characters')
      ->fields([
        'uuid' => 'bbbbbbbb-cccc-dddd-eeee-ffffffffffff',
        'campaign_id' => 0,
        'character_id' => 0,
        'instance_id' => 'library-runtime-backfill',
        'uid' => (int) $user->id(),
        'name' => 'Runtime Backfill Hero',
        'level' => 1,
        'ancestry' => 'Human',
        'class' => 'Wizard',
        'hp_current' => 18,
        'hp_max' => 18,
        'armor_class' => 15,
        'experience_points' => 0,
        'position_q' => 0,
        'position_r' => 0,
        'last_room_id' => '',
        'type' => 'pc',
        'character_data' => json_encode([
          'name' => 'Runtime Backfill Hero',
          'level' => 1,
          'spells' => [
            'cantrips' => [
              ['id' => 'shield', 'name' => 'Shield', 'rank' => 0],
            ],
          ],
        ], JSON_UNESCAPED_UNICODE),
        'default_character_data' => json_encode([
          'spells' => [
            'cantrips' => [
              ['id' => 'shield', 'name' => 'Shield', 'rank' => 0],
            ],
            'preparedSpells' => [
              ['id' => 'magic-weapon', 'name' => 'Magic Weapon', 'rank' => 1],
            ],
            'slots' => ['1' => 2],
          ],
          'feats' => [
            ['id' => 'reach-spell', 'name' => 'Reach Spell', 'type' => 'class', 'level' => 1],
          ],
        ], JSON_UNESCAPED_UNICODE),
        'status' => 1,
        'role' => 'player',
        'state_data' => '{}',
        'location_type' => 'global',
        'location_ref' => '',
        'is_active' => 1,
        'joined' => $now,
        'created' => $now,
        'changed' => $now,
        'updated' => $now,
      ])
      ->execute();

    $runtime_character_id = (int) $database->insert('dc_campaign_characters')
      ->fields([
        'uuid' => 'cccccccc-dddd-eeee-ffff-000000000000',
        'campaign_id' => $campaign_id,
        'character_id' => $library_character_id,
        'instance_id' => sprintf('pc-%d-%d', $campaign_id, $library_character_id),
        'uid' => (int) $user->id(),
        'name' => 'Runtime Backfill Hero',
        'level' => 1,
        'ancestry' => 'Human',
        'class' => 'Wizard',
        'hp_current' => 18,
        'hp_max' => 18,
        'armor_class' => 15,
        'experience_points' => 0,
        'position_q' => 0,
        'position_r' => 0,
        'last_room_id' => '',
        'type' => 'pc',
        'character_data' => json_encode([
          'name' => 'Runtime Backfill Hero',
          'level' => 1,
          'spells' => [
            'cantrips' => [
              ['id' => 'shield', 'name' => 'Shield', 'rank' => 0],
            ],
          ],
        ], JSON_UNESCAPED_UNICODE),
        'state_data' => json_encode([
          'spells' => [
            'cantrips' => [
              ['id' => 'shield', 'name' => 'Shield', 'rank' => 0],
            ],
          ],
        ], JSON_UNESCAPED_UNICODE),
        'location_type' => 'global',
        'location_ref' => '',
        'is_active' => 1,
        'status' => 1,
        'role' => 'player',
        'joined' => $now,
        'created' => $now,
        'changed' => $now,
        'updated' => $now,
      ])
      ->execute();

    $this->drupalGet("/api/character/{$runtime_character_id}/state", [
      'query' => [
        '_format' => 'json',
        'campaignId' => $campaign_id,
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);

    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertIsArray($response);
    $this->assertTrue($response['success']);
    $this->assertSame('Magic Weapon', $response['data']['spells']['preparedSpells'][0]['name']);
    $this->assertSame('Reach Spell', $response['data']['features']['feats'][0]['name']);
  }

}
