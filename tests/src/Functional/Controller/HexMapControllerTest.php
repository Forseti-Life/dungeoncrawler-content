<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests HexMapController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 */
class HexMapControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests hexmap demo display - positive case.
   */
  public function testHexmapDemoDisplayPositive(): void {
    $this->drupalGet('/hexmap');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Map');
    $this->assertSession()->pageTextContains('Chat');
    $this->assertSession()->pageTextContains('Character');
    $this->assertSession()->pageTextContains('Enter Fullscreen');
  }

  /**
   * Tests hexmap demo public access - negative case (should be public).
   */
  public function testHexmapDemoPublicAccessNegative(): void {
    // Demo should be publicly accessible
    $this->drupalGet('/hexmap');
    $this->assertSession()->statusCodeNotEquals(403);
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests that schema_version is preserved in dungeon payload.
   *
   * Verifies that HexMapController::normalizeDungeonPayload() includes
   * schema_version from the source dungeon data in the normalized output
   * passed to the frontend via drupalSettings.
   *
   * Related to DCC-0255: Schema conformance review.
   */
  public function testSchemaVersionPreservedInPayload(): void {
    $account = $this->drupalCreateUser();
    $this->drupalLogin($account);

    $database = $this->container->get('database');
    $now = \Drupal::time()->getRequestTime();
    $campaign_id = (int) $database->insert('dc_campaigns')
      ->fields([
        'uuid' => '99999999-2222-3333-4444-555555555555',
        'uid' => (int) $account->id(),
        'name' => 'Schema Version Campaign',
        'status' => 'draft',
        'theme' => 'classic_dungeon',
        'difficulty' => 'normal',
        'campaign_data' => '{}',
        'created' => $now,
        'changed' => $now,
      ])
      ->execute();

    $database->insert('dc_campaign_dungeons')
      ->fields([
        'campaign_id' => $campaign_id,
        'dungeon_id' => 'schema-check-dungeon',
        'name' => 'Schema Check Dungeon',
        'description' => 'Dungeon used to verify schema_version propagation.',
        'theme' => 'classic_dungeon',
        'dungeon_data' => json_encode([
          'schema_version' => 'test-schema-v1',
          'level_id' => 'schema-level',
          'hex_map' => [
            'map_id' => 'schema-map',
            'connections' => [],
          ],
          'rooms' => [
            [
              'room_id' => 'schema-room',
              'name' => 'Schema Room',
              'description' => 'A room for schema validation.',
              'hexes' => [
                ['q' => 0, 'r' => 0, 'terrain_type' => 'floor', 'objects' => []],
              ],
            ],
          ],
          'entities' => [],
          'object_definitions' => [],
        ], JSON_UNESCAPED_UNICODE),
        'created' => $now,
        'updated' => $now,
      ])
      ->execute();

    $this->drupalGet('/hexmap', [
      'query' => [
        'campaign_id' => $campaign_id,
        'map_id' => 'schema-check-dungeon',
        'room_id' => 'schema-room',
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);

    $settings = $this->getDrupalSettings();
    $this->assertArrayHasKey('dungeoncrawlerContent', $settings);
    $this->assertArrayHasKey('hexmapDungeonData', $settings['dungeoncrawlerContent']);
    $this->assertArrayHasKey('map_visual_state', $settings['dungeoncrawlerContent']);
    $this->assertArrayNotHasKey('hexmapVisualState', $settings['dungeoncrawlerContent']);

    // Verify schema_version is preserved from explicit campaign dungeon data.
    $dungeon_data = $settings['dungeoncrawlerContent']['hexmapDungeonData'];
    $this->assertArrayHasKey('schema_version', $dungeon_data, 'schema_version field must be preserved in normalized dungeon payload');
    $this->assertSame('test-schema-v1', $dungeon_data['schema_version']);
    $this->assertArrayHasKey('fog_mode', $settings['dungeoncrawlerContent']['map_visual_state']['visibility']);
    $this->assertArrayHasKey('legend', $settings['dungeoncrawlerContent']['map_visual_state']['presentation']);
  }

  /**
   * Tests the canonical visual-state API payload.
   */
  public function testVisualStateApiReturnsCanonicalPayload(): void {
    $account = $this->drupalCreateUser();
    $this->drupalLogin($account);

    $database = $this->container->get('database');
    $now = \Drupal::time()->getRequestTime();
    $campaign_id = (int) $database->insert('dc_campaigns')
      ->fields([
        'uuid' => '22222222-3333-4444-5555-666666666666',
        'uid' => (int) $account->id(),
        'name' => 'Visual State API Campaign',
        'status' => 'draft',
        'theme' => 'classic_dungeon',
        'difficulty' => 'normal',
        'campaign_data' => '{}',
        'created' => $now,
        'changed' => $now,
      ])
      ->execute();

    $database->insert('dc_campaign_dungeons')
      ->fields([
        'campaign_id' => $campaign_id,
        'dungeon_id' => 'visual-state-dungeon',
        'name' => 'Visual State Dungeon',
        'description' => 'Dungeon used to verify the map visual state endpoint.',
        'theme' => 'classic_dungeon',
        'dungeon_data' => json_encode([
          'schema_version' => 'test-schema-v2',
          'level_id' => 'visual-level',
          'hex_map' => [
            'map_id' => 'visual-map',
            'connections' => [],
          ],
          'rooms' => [
            [
              'room_id' => 'visual-room',
              'name' => 'Visual Room',
              'description' => 'A room for visual state validation.',
              'hexes' => [
                ['q' => 0, 'r' => 0, 'terrain_type' => 'floor', 'objects' => []],
              ],
            ],
          ],
          'entities' => [],
          'object_definitions' => [],
        ], JSON_UNESCAPED_UNICODE),
        'created' => $now,
        'updated' => $now,
      ])
      ->execute();

    $this->drupalGet('/api/map/visual-state', [
      'query' => [
        'campaign_id' => $campaign_id,
        'map_id' => 'visual-state-dungeon',
        'room_id' => 'visual-room',
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);

    $payload = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertIsArray($payload, 'The visual-state endpoint should return JSON.');
    $this->assertTrue($payload['success']);
    $this->assertArrayHasKey('launch_context', $payload);
    $this->assertArrayHasKey('map_visual_state', $payload);
    $this->assertArrayNotHasKey('hexmapDungeonData', $payload);
    $this->assertSame($campaign_id, (int) $payload['launch_context']['campaign_id']);
    $this->assertSame('visual-room', $payload['map_visual_state']['map_meta']['active_room_id']);
    $this->assertArrayHasKey('fog_mode', $payload['map_visual_state']['visibility']);
    $this->assertArrayHasKey('legend', $payload['map_visual_state']['presentation']);
  }

  /**
   * Tests direct campaign launch from a library character link.
   */
  public function testDirectCampaignLaunchMaterializesRuntimeCharacter(): void {
    $account = $this->drupalCreateUser();
    $this->drupalLogin($account);

    $database = $this->container->get('database');
    $now = \Drupal::time()->getRequestTime();
    $campaign_id = (int) $database->insert('dc_campaigns')
      ->fields([
        'uuid' => '11111111-2222-3333-4444-555555555555',
        'uid' => (int) $account->id(),
        'name' => 'Direct Launch Campaign',
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
        'uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        'campaign_id' => 0,
        'character_id' => 0,
        'instance_id' => 'library-test-hero',
        'uid' => (int) $account->id(),
        'name' => 'Test Hero',
        'level' => 1,
        'ancestry' => 'Human',
        'class' => 'Wizard',
        'hp_current' => 24,
        'hp_max' => 24,
        'armor_class' => 17,
        'experience_points' => 0,
        'position_q' => 0,
        'position_r' => 0,
        'last_room_id' => '',
        'type' => 'pc',
        'character_data' => json_encode([
          'name' => 'Test Hero',
          'level' => 1,
          'hp' => ['current' => 24, 'max' => 24],
          'ac' => 17,
          'spells' => [
            'cantrips' => [
              ['id' => 'detect-magic', 'name' => 'Detect Magic', 'rank' => 0],
            ],
          ],
          'inventory' => ['items' => [], 'currency' => ['gp' => 0, 'sp' => 0, 'cp' => 0]],
        ], JSON_UNESCAPED_UNICODE),
        'default_character_data' => json_encode([
          'spells' => [
            'cantrips' => [
              ['id' => 'detect-magic', 'name' => 'Detect Magic', 'rank' => 0],
            ],
            'preparedSpells' => [
              ['id' => 'magic-missile', 'name' => 'Magic Missile', 'rank' => 1],
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

    $database->insert('dc_campaign_dungeons')
      ->fields([
        'campaign_id' => $campaign_id,
        'dungeon_id' => 'starter-tavern',
        'name' => 'Starter Tavern',
        'description' => 'Test launch dungeon',
        'theme' => 'classic_dungeon',
        'dungeon_data' => json_encode([
          'schema_version' => 'test-v1',
          'level_id' => 'starter-level',
          'hex_map' => [
            'map_id' => 'starter-map',
            'connections' => [],
          ],
          'rooms' => [
            [
              'room_id' => 'tavern_entrance',
              'name' => 'Tavern Entrance',
              'description' => 'A quiet test room.',
              'hexes' => [
                ['q' => 0, 'r' => 0, 'objects' => []],
                ['q' => 1, 'r' => 0, 'objects' => []],
              ],
            ],
          ],
          'entities' => [],
          'object_definitions' => [],
        ], JSON_UNESCAPED_UNICODE),
        'created' => $now,
        'updated' => $now,
      ])
      ->execute();

    $this->drupalGet('/hexmap', [
      'query' => [
        'campaign_id' => $campaign_id,
        'character_id' => $library_character_id,
        'room_id' => 'tavern_entrance',
        'start_q' => 0,
        'start_r' => 0,
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);

    $runtime_row = $database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['id', 'instance_id', 'default_character_data'])
      ->condition('campaign_id', $campaign_id)
      ->condition('character_id', $library_character_id)
      ->condition('type', 'pc')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    $this->assertNotFalse($runtime_row, 'A campaign runtime row should be created for direct launches.');

    $settings = $this->getDrupalSettings();
    $hexmap_settings = $settings['dungeoncrawlerContent'];
    $launch_context = $hexmap_settings['hexmapLaunchContext'];
    $launch_character = $hexmap_settings['hexmapLaunchCharacter'];
    $entities = $hexmap_settings['hexmapDungeonData']['entities'];
    $visual_state = $hexmap_settings['map_visual_state'];

    $this->assertSame((int) $runtime_row['id'], (int) $launch_context['character_id']);
    $this->assertSame('Test Hero', $launch_character['name']);
    $this->assertSame((string) $runtime_row['instance_id'], (string) $launch_character['instanceId']);
    $this->assertStringContainsString('magic-missile', (string) $runtime_row['default_character_data']);
    $this->assertSame('Magic Missile', $launch_character['spells']['preparedSpells'][0]['name']);
    $this->assertSame('Reach Spell', $launch_character['feats'][0]['name']);
    $this->assertSame('tavern_entrance', $visual_state['map_meta']['active_room_id']);
    $this->assertArrayNotHasKey('destroyed', $visual_state['occupants']['party'][0]['state']);

    $player_entities = array_values(array_filter($entities, static function (array $entity): bool {
      return ($entity['entity_type'] ?? '') === 'player_character';
    }));
    $this->assertCount(1, $player_entities, 'The hexmap payload should include the launch player entity.');
    $this->assertSame((int) $runtime_row['id'], (int) ($player_entities[0]['state']['metadata']['character_id'] ?? 0));
  }

  /**
   * Decode drupalSettings from the current page.
   */
  protected function getDrupalSettings(): array {
    $settings_script = $this->getSession()->getPage()->find('css', 'script[data-drupal-selector="drupal-settings-json"]');
    $this->assertNotNull($settings_script, 'drupalSettings script tag should be present.');

    $settings = json_decode($settings_script->getText(), TRUE);
    $this->assertIsArray($settings, 'drupalSettings JSON should decode to an array.');

    return $settings;
  }

}
