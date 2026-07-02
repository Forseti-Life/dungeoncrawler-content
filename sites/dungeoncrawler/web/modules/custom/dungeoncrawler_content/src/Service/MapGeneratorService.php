<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\ai_conversation\Service\AIApiService;
use Psr\Log\LoggerInterface;

/**
 * Generates new map settings dynamically when players navigate to new locations.
 *
 * When a player says "I leave the tavern" or "I head to the market", this service:
 * 1. Uses AI to generate a setting description appropriate to the destination
 * 2. Determines room size, terrain, lighting, and theme from the description
 * 3. Generates a hex grid for the new room
 * 4. Creates appropriate NPCs, objects, and environmental details
 * 5. Wires the new room into dungeon_data with proper connections
 * 6. Returns the new room data so the client can transition to it
 *
 * This bridges the gap between narrative exploration ("I want to go to the
 * blacksmith") and the mechanical hex map system that needs concrete room data.
 */
class MapGeneratorService {

  protected Connection $database;
  protected LoggerInterface $logger;
  protected AIApiService $aiApiService;
  protected NpcPsychologyService $psychologyService;

  /**
   * Size presets: setting type => [cols, rows, hex_count_approx, size_category].
   */
  const SIZE_PRESETS = [
    'tiny'   => ['cols' => 3, 'rows' => 3, 'size' => 'tiny'],       // closet, alcove
    'small'  => ['cols' => 5, 'rows' => 4, 'size' => 'small'],      // shop, cell
    'medium' => ['cols' => 7, 'rows' => 6, 'size' => 'medium'],     // tavern, chapel
    'large'  => ['cols' => 9, 'rows' => 8, 'size' => 'large'],      // great hall, market square
    'huge'   => ['cols' => 12, 'rows' => 10, 'size' => 'huge'],     // arena, cathedral
  ];

  /**
   * Terrain mapping from setting type to terrain properties.
   */
  const TERRAIN_MAP = [
    'tavern'       => ['type' => 'wood_floor',   'difficult' => FALSE, 'ceiling' => 12],
    'shop'         => ['type' => 'wood_floor',   'difficult' => FALSE, 'ceiling' => 10],
    'temple'       => ['type' => 'stone_floor',  'difficult' => FALSE, 'ceiling' => 30],
    'market'       => ['type' => 'cobblestone',  'difficult' => FALSE, 'ceiling' => 0],
    'street'       => ['type' => 'cobblestone',  'difficult' => FALSE, 'ceiling' => 0],
    'forest'       => ['type' => 'natural_earth','difficult' => TRUE,  'ceiling' => 0],
    'cave'         => ['type' => 'natural_rock', 'difficult' => TRUE,  'ceiling' => 15],
    'dungeon'      => ['type' => 'stone_floor',  'difficult' => FALSE, 'ceiling' => 10],
    'library'      => ['type' => 'stone_floor',  'difficult' => FALSE, 'ceiling' => 15],
    'throne_room'  => ['type' => 'stone_floor',  'difficult' => FALSE, 'ceiling' => 25],
    'dock'         => ['type' => 'wood_floor',   'difficult' => FALSE, 'ceiling' => 0],
    'alley'        => ['type' => 'cobblestone',  'difficult' => FALSE, 'ceiling' => 0],
    'sewer'        => ['type' => 'stone_floor',  'difficult' => TRUE,  'ceiling' => 8],
    'garden'       => ['type' => 'natural_earth','difficult' => FALSE, 'ceiling' => 0],
    'arena'        => ['type' => 'sand',         'difficult' => FALSE, 'ceiling' => 0],
    'prison'       => ['type' => 'stone_floor',  'difficult' => FALSE, 'ceiling' => 8],
    'residential'  => ['type' => 'wood_floor',   'difficult' => FALSE, 'ceiling' => 10],
    'wilderness'   => ['type' => 'natural_earth','difficult' => TRUE,  'ceiling' => 0],
    'default'      => ['type' => 'stone_floor',  'difficult' => FALSE, 'ceiling' => 10],
  ];

  /**
   * Lighting defaults by setting type.
   */
  const LIGHTING_MAP = [
    'tavern'      => 'normal_light',
    'shop'        => 'normal_light',
    'temple'      => 'normal_light',
    'market'      => 'bright_light',
    'street'      => 'normal_light',
    'forest'      => 'dim_light',
    'cave'        => 'darkness',
    'dungeon'     => 'dim_light',
    'library'     => 'normal_light',
    'dock'        => 'normal_light',
    'alley'       => 'dim_light',
    'sewer'       => 'darkness',
    'garden'      => 'bright_light',
    'arena'       => 'bright_light',
    'prison'      => 'dim_light',
    'wilderness'  => 'normal_light',
    'default'     => 'normal_light',
  ];

  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    AIApiService $ai_api_service,
    NpcPsychologyService $psychology_service
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler_map_gen');
    $this->aiApiService = $ai_api_service;
    $this->psychologyService = $psychology_service;
  }

  /**
   * Minimum quality score for a library template to be considered usable.
   */
  const MIN_QUALITY_SCORE = 0.3;

  /**
   * Maximum number of library candidates to consider when matching.
   */
  const MAX_LIBRARY_CANDIDATES = 10;

  // =========================================================================
  // Public API
  // =========================================================================

  /**
   * Generate a new map/setting from a player's navigation intent.
   *
   * This is the main entry point. Given a destination description (e.g., "the
   * blacksmith shop", "the town square", "the forest path outside town"), it:
   * 1. Checks the setting template library for an adequate existing match
   * 2. If no match, calls AI to generate the setting and caches it in library
   * 3. Builds a complete room structure matching dungeon_data schema
   * 4. Records a campaign instance in dc_campaign_settings
   * 5. Appends the room to dungeon_data and creates connections
   * 6. Returns the new room data and updated dungeon_data
   *
   * @param int $campaign_id
   *   The campaign ID.
   * @param string $destination
   *   The player's stated destination (e.g., "the blacksmith", "outside").
   * @param string $origin_room_id
   *   The room_id the player is leaving from.
   * @param array $narrative_context
   *   Additional context for generation:
   *   - gm_narrative: string - GM's transition narrative
   *   - campaign_theme: string - overall campaign theme
   *   - party_level: int - for difficulty calibration
   *   - time_of_day: string - dawn/day/dusk/night
   *
   * @return array
   *   [
   *     'room' => array (the new room structure),
   *     'room_index' => int (index in dungeon_data.rooms),
   *     'dungeon_data' => array (updated full dungeon_data),
   *     'source' => string ('library'|'ai_generated'),
   *     'template_id' => string|null,
   *   ]
   *
   * @throws \RuntimeException
   *   If generation fails.
   */
  public function generateSetting(
    int $campaign_id,
    string $destination,
    string $origin_room_id,
    array $narrative_context = []
  ): array {
    $this->logger->info('Generating new setting for campaign @cid: @dest', [
      '@cid' => $campaign_id,
      '@dest' => $destination,
    ]);

    // Load current dungeon data.
    $record = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['dungeon_id', 'dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->orderBy('updated', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$record) {
      throw new \RuntimeException('No dungeon data found for campaign ' . $campaign_id);
    }

    $dungeon_id = $record['dungeon_id'];
    $dungeon_data = json_decode($record['dungeon_data'], TRUE);
    if (!is_array($dungeon_data)) {
      throw new \RuntimeException('Invalid dungeon data for campaign ' . $campaign_id);
    }

    $party_level = $narrative_context['party_level']
      ?? $dungeon_data['generation_rules']['party_level_target']
      ?? 1;

    // Step 1: Check the setting template library for an adequate match.
    $template_id = NULL;
    $source = 'ai_generated';
    $library_match = $this->findLibraryMatch($destination, $party_level, $campaign_id);

    if ($library_match) {
      // Library hit — use cached template instead of AI generation.
      $setting = $this->hydrateSettingFromTemplate($library_match);
      $template_id = $library_match['template_id'];
      $source = 'library';
      $this->incrementTemplateUsage($template_id);
      $this->logger->info('Library match found: @tid (score=@score, usage=@usage)', [
        '@tid' => $template_id,
        '@score' => $library_match['quality_score'],
        '@usage' => $library_match['usage_count'] + 1,
      ]);
    }
    else {
      // No library match — generate via AI.
      $setting = $this->generateSettingDescription($destination, $narrative_context, $dungeon_data);

      // Cache the AI-generated setting as a new library template.
      $template_id = $this->cacheSettingAsTemplate($setting, $destination, $party_level);
      $this->logger->info('New template cached: @tid', ['@tid' => $template_id]);
    }

    // Step 2: Build the room structure.
    $room = $this->buildRoomFromSetting($setting, $origin_room_id);

    // Step 3: Generate entities (NPCs, objects, furniture) for the room.
    $entities = $this->generateSettingEntities($setting, $room['room_id'], $campaign_id);

    // Step 4: Append room to dungeon_data.
    $dungeon_data['rooms'][] = $room;
    $room_index = array_key_last($dungeon_data['rooms']);

    // Step 5: Add entities to top-level entities array.
    if (!isset($dungeon_data['entities'])) {
      $dungeon_data['entities'] = [];
    }
    foreach ($entities as $entity) {
      $dungeon_data['entities'][] = $entity;
    }

    // Step 6: Create connection from origin room to new room.
    $this->createRoomConnection($dungeon_data, $origin_room_id, $room['room_id']);

    // Step 7: Update hex_map regions.
    $this->addRegionToHexMap($dungeon_data, $room);

    // Step 8: Persist dungeon_data.
    $this->database->update('dc_campaign_dungeons')
      ->fields([
        'dungeon_data' => json_encode($dungeon_data),
        'updated' => time(),
      ])
      ->condition('dungeon_id', $dungeon_id)
      ->condition('campaign_id', $campaign_id)
      ->execute();

    // Step 9: Record campaign setting instance.
    $this->recordCampaignSettingInstance(
      $campaign_id, $room['room_id'], $template_id, $room['name'],
      $setting['setting_type'] ?? 'default', $room_index, $setting
    );

    // Step 10a: Persist room into dc_campaign_rooms so it can be resolved
    // by slug later (prevents tavern NPC bleed into unindexed rooms).
    $this->persistRoomToCampaignRooms($campaign_id, $room, $setting);

    // Step 10b: Create NPC psychology profiles for any new NPCs.
    $room_entities = array_filter($entities, fn($e) => ($e['entity_type'] ?? '') === 'npc');
    if (!empty($room_entities)) {
      $this->psychologyService->ensureRoomNpcProfiles($campaign_id, $room_entities);
    }

    // Step 11: Register AI-generated NPCs in content library + campaign chars.
    $npc_setting_data = $setting['npcs'] ?? [];
    if (!empty($npc_setting_data)) {
      $this->registerGeneratedNpcs($campaign_id, $room['room_id'], $npc_setting_data);
    }

    $this->logger->info('Setting ready: @name (source=@src, template=@tid, room_index=@idx, @hex hexes, @ent entities)', [
      '@name' => $room['name'],
      '@src' => $source,
      '@tid' => $template_id ?? 'none',
      '@idx' => $room_index,
      '@hex' => count($room['hexes']),
      '@ent' => count($entities),
    ]);

    return [
      'room' => $room,
      'room_index' => $room_index,
      'entities' => $entities,
      'dungeon_data' => $dungeon_data,
      'source' => $source,
      'template_id' => $template_id,
    ];
  }

  // =========================================================================
  // Library: template lookup, caching, and campaign instance tracking
  // =========================================================================

  /**
   * Search the setting template library for an adequate existing match.
   *
   * Matching strategy:
   * 1. Extract keywords from the destination string
   * 2. Infer a likely setting_type from the destination
   * 3. Query library by setting_type + level range + quality threshold
   * 4. Score candidates by keyword overlap with search_tags
   * 5. Return the best match if score exceeds threshold, NULL otherwise
   *
   * @param string $destination
   *   Player's stated destination.
   * @param int $party_level
   *   Current party level for level-range filtering.
   * @param int $campaign_id
   *   Campaign ID (to avoid re-using a template already active in this campaign).
   *
   * @return array|null
   *   Library row (with all fields) or NULL if no adequate match.
   */
  protected function findLibraryMatch(string $destination, int $party_level, int $campaign_id): ?array {
    $keywords = $this->extractSearchKeywords($destination);
    $inferred_type = $this->inferSettingType($destination);

    if (empty($keywords) && !$inferred_type) {
      return NULL;
    }

    // Build query: setting_type match (if we can infer) + level range + quality.
    $query = $this->database->select('dungeoncrawler_content_setting_templates', 't')
      ->fields('t')
      ->condition('t.quality_score', self::MIN_QUALITY_SCORE, '>=')
      ->condition('t.level_min', $party_level, '<=')
      ->condition('t.level_max', $party_level, '>=')
      ->orderBy('t.quality_score', 'DESC')
      ->orderBy('t.usage_count', 'ASC')
      ->range(0, self::MAX_LIBRARY_CANDIDATES);

    if ($inferred_type && $inferred_type !== 'default') {
      $query->condition('t.setting_type', $inferred_type);
    }

    $candidates = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

    if (empty($candidates)) {
      return NULL;
    }

    // Collect templates already used in this campaign to avoid duplicates.
    $used_templates = $this->database->select('dc_campaign_settings', 'cs')
      ->fields('cs', ['source_template_id'])
      ->condition('cs.campaign_id', $campaign_id)
      ->isNotNull('cs.source_template_id')
      ->execute()
      ->fetchCol();
    $used_set = array_flip($used_templates);

    // Score candidates by keyword overlap.
    $best = NULL;
    $best_score = 0;

    foreach ($candidates as $candidate) {
      // Skip templates already active in this campaign.
      if (isset($used_set[$candidate['template_id']])) {
        continue;
      }

      $tags = json_decode($candidate['search_tags'] ?? '[]', TRUE) ?: [];
      $overlap = count(array_intersect($keywords, $tags));
      $score = $overlap / max(count($keywords), 1);

      // Boost for exact setting_type match.
      if ($inferred_type && $candidate['setting_type'] === $inferred_type) {
        $score += 0.3;
      }

      // Boost for quality.
      $score += (float) $candidate['quality_score'] * 0.2;

      // Penalize overused templates slightly.
      $score -= min(0.1, (int) $candidate['usage_count'] * 0.01);

      if ($score > $best_score) {
        $best_score = $score;
        $best = $candidate;
      }
    }

    // Require minimum match score of 0.4 to use a library template.
    if ($best_score < 0.4) {
      $this->logger->debug('Library search: best score @score < 0.4 threshold, will generate fresh', [
        '@score' => round($best_score, 2),
      ]);
      return NULL;
    }

    return $best;
  }

  /**
   * Hydrate a full setting array from a library template row.
   */
  protected function hydrateSettingFromTemplate(array $template): array {
    $setting_data = json_decode($template['setting_data'] ?? '{}', TRUE) ?: [];

    return array_merge($setting_data, [
      'name' => $template['name'],
      'description' => $template['description'],
      'setting_type' => $template['setting_type'],
      'size' => $template['size'],
      'lighting' => $template['lighting'],
    ]);
  }

  /**
   * Cache an AI-generated setting as a new library template.
   *
   * @param array $setting
   *   Normalized setting data from generateSettingDescription().
   * @param string $destination
   *   Original destination string (for keyword extraction).
   * @param int $party_level
   *   Party level at time of generation.
   *
   * @return string
   *   The new template_id.
   */
  protected function cacheSettingAsTemplate(array $setting, string $destination, int $party_level): string {
    // Generate a stable template_id from the setting name.
    $base_id = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $setting['name'] ?? 'setting'));
    $base_id = trim($base_id, '_');
    $template_id = substr($base_id, 0, 80) . '_' . substr(md5($base_id . microtime()), 0, 8);

    // Build search tags from destination keywords + setting metadata.
    $keywords = $this->extractSearchKeywords($destination);
    $tags = array_unique(array_merge(
      $keywords,
      $setting['theme_tags'] ?? [],
      [$setting['setting_type'] ?? '', $setting['size'] ?? ''],
      $this->extractSearchKeywords($setting['name'] ?? ''),
      $this->extractSearchKeywords($setting['description'] ?? '')
    ));
    $tags = array_values(array_filter($tags));

    // Separate NPCs/objects/atmosphere into setting_data blob.
    $setting_data = [
      'theme_tags' => $setting['theme_tags'] ?? [],
      'atmosphere' => $setting['atmosphere'] ?? '',
      'npcs' => $setting['npcs'] ?? [],
      'objects' => $setting['objects'] ?? [],
    ];

    $now = time();
    $level_min = max(1, $party_level - 2);
    $level_max = min(20, $party_level + 3);

    try {
      $this->database->insert('dungeoncrawler_content_setting_templates')
        ->fields([
          'template_id' => $template_id,
          'name' => $setting['name'] ?? 'Unknown Setting',
          'description' => $setting['description'] ?? '',
          'setting_type' => $setting['setting_type'] ?? 'default',
          'size' => $setting['size'] ?? 'medium',
          'lighting' => $setting['lighting'] ?? 'normal_light',
          'setting_data' => json_encode($setting_data),
          'search_tags' => json_encode($tags),
          'level_min' => $level_min,
          'level_max' => $level_max,
          'usage_count' => 1,
          'quality_score' => 0.5,
          'source' => 'ai_generated',
          'created' => $now,
          'updated' => $now,
        ])
        ->execute();
    }
    catch (\Exception $e) {
      $this->logger->warning('Failed to cache setting template @tid: @err', [
        '@tid' => $template_id,
        '@err' => $e->getMessage(),
      ]);
    }

    return $template_id;
  }

  /**
   * Increment usage_count on a library template.
   */
  protected function incrementTemplateUsage(string $template_id): void {
    try {
      $this->database->update('dungeoncrawler_content_setting_templates')
        ->expression('usage_count', 'usage_count + 1')
        ->fields(['updated' => time()])
        ->condition('template_id', $template_id)
        ->execute();
    }
    catch (\Exception $e) {
      $this->logger->warning('Failed to increment usage for template @tid', [
        '@tid' => $template_id,
      ]);
    }
  }

  /**
   * Record a campaign-scoped setting instance.
   *
   * This tracks which settings have been instantiated in each campaign,
   * links back to the library template, and records visit history.
   */
  protected function recordCampaignSettingInstance(
    int $campaign_id,
    string $setting_id,
    ?string $source_template_id,
    string $name,
    string $setting_type,
    int $room_index,
    array $setting
  ): void {
    $now = time();
    $instance_data = [
      'setting_type' => $setting_type,
      'size' => $setting['size'] ?? 'medium',
      'lighting' => $setting['lighting'] ?? 'normal_light',
      'theme_tags' => $setting['theme_tags'] ?? [],
      'atmosphere' => $setting['atmosphere'] ?? '',
      'npc_count' => count($setting['npcs'] ?? []),
      'object_count' => count($setting['objects'] ?? []),
    ];

    try {
      $this->database->insert('dc_campaign_settings')
        ->fields([
          'campaign_id' => $campaign_id,
          'setting_id' => $setting_id,
          'source_template_id' => $source_template_id,
          'name' => $name,
          'setting_type' => $setting_type,
          'room_index' => $room_index,
          'instance_data' => json_encode($instance_data),
          'status' => 'active',
          'first_visited' => $now,
          'last_visited' => $now,
          'visit_count' => 1,
          'created' => $now,
          'updated' => $now,
        ])
        ->execute();
    }
    catch (\Exception $e) {
      $this->logger->warning('Failed to record campaign setting instance @sid: @err', [
        '@sid' => $setting_id,
        '@err' => $e->getMessage(),
      ]);
    }
  }

  // =========================================================================
  // NPC and Room persistence helpers
  // =========================================================================

  /**
   * Persist a generated room into dc_campaign_rooms.
   *
   * Rooms created by MapGeneratorService live in dc_campaign_settings but were
   * historically NOT written to dc_campaign_rooms. This method bridges that
   * gap so resolveRoomSlugForQuery() can find them and avoid bleeding tavern
   * NPCs (Eldric etc.) into unrelated rooms.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param array $room
   *   Room data from buildRoomFromSetting().
   * @param array $setting
   *   Normalized setting data.
   */
  protected function persistRoomToCampaignRooms(int $campaign_id, array $room, array $setting): void {
    $room_id = $room['room_id'] ?? '';
    if (!$room_id) {
      return;
    }

    $now = time();
    $layout_data = json_encode([
      'hexes'        => $room['hexes'] ?? [],
      'entry_points' => $room['entry_points'] ?? [],
      'exit_points'  => $room['exit_points'] ?? [],
      'terrain'      => $room['terrain'] ?? [],
      'lighting'     => $room['lighting'] ?? [],
    ]);
    $contents_data = json_encode([
      'creatures'     => [],
      'items'         => [],
      'traps'         => [],
      'hazards'       => [],
      'obstacles'     => [],
      'interactables' => [],
    ]);
    $env_tags = json_encode($setting['theme_tags'] ?? []);

    try {
      // Use INSERT IGNORE (upsert) to avoid duplicates on re-generation.
      $this->database->merge('dc_campaign_rooms')
        ->key(['campaign_id' => $campaign_id, 'room_id' => $room_id])
        ->fields([
          'campaign_id'       => $campaign_id,
          'room_id'           => $room_id,
          'name'              => $room['name'] ?? 'Unknown',
          'description'       => $room['description'] ?? '',
          'environment_tags'  => $env_tags,
          'layout_data'       => $layout_data,
          'contents_data'     => $contents_data,
          'source_room_id'    => '',
          'created'           => $now,
          'updated'           => $now,
        ])
        ->execute();

      // Initialize room state row if the table exists.
      try {
        $this->database->merge('dc_campaign_room_states')
          ->key(['campaign_id' => $campaign_id, 'room_id' => $room_id])
          ->fields([
            'campaign_id'  => $campaign_id,
            'room_id'      => $room_id,
            'is_cleared'   => 0,
            'fog_state'    => json_encode(['explored' => TRUE, 'visibility' => 'visible']),
            'last_visited' => $now,
            'updated'      => $now,
          ])
          ->execute();
      }
      catch (\Exception $e) {
        // dc_campaign_room_states may not exist — non-critical.
      }

      $this->logger->info('Room @id persisted to dc_campaign_rooms (name: @name)', [
        '@id'   => $room_id,
        '@name' => $room['name'] ?? 'Unknown',
      ]);
    }
    catch (\Exception $e) {
      $this->logger->warning('Failed to persist room @id to dc_campaign_rooms: @err', [
        '@id'  => $room_id,
        '@err' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Register AI-generated NPCs in the content library and campaign characters.
   *
   * Each NPC from the AI setting response is:
   * 1. Upserted into dungeoncrawler_content_registry (global library).
   * 2. Upserted into dc_campaign_content_registry (campaign-scoped copy).
   * 3. Inserted into dc_campaign_characters (so loadRoomCampaignNpcRows finds them).
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $room_id
   *   UUID of the room this NPC was placed into.
   * @param array $npcs
   *   Normalized NPC array from setting['npcs'].
   */
  protected function registerGeneratedNpcs(int $campaign_id, string $room_id, array $npcs): void {
    $now = time();

    foreach ($npcs as $npc) {
      $content_id = $npc['content_id'] ?? '';
      $name       = $npc['name'] ?? 'Unknown NPC';
      if (!$content_id) {
        continue;
      }

      $schema_data = json_encode([
        'content_id'  => $content_id,
        'name'        => $name,
        'ancestry'    => $npc['ancestry'] ?? 'Human',
        'class'       => $npc['class'] ?? 'Commoner',
        'role'        => $npc['role'] ?? 'neutral',
        'occupation'  => $npc['occupation'] ?? '',
        'description' => $npc['description'] ?? '',
        'backstory'   => $npc['backstory'] ?? '',
        'attitude'    => $npc['attitude'] ?? 'indifferent',
        'stats'       => $npc['stats'] ?? [],
        'equipment'   => $npc['equipment'] ?? [],
        'source'      => 'ai_generated',
      ]);

      $tags = json_encode(array_filter([
        $npc['role'] ?? NULL,
        $npc['team'] ?? NULL,
        'ai_generated',
      ]));

      // 1. Global library entry (dungeoncrawler_content_registry).
      try {
        $this->database->merge('dungeoncrawler_content_registry')
          ->key(['content_type' => 'npc', 'content_id' => $content_id])
          ->fields([
            'content_type' => 'npc',
            'content_id'   => $content_id,
            'name'         => $name,
            'level'        => 0,
            'rarity'       => 'common',
            'tags'         => $tags,
            'schema_data'  => $schema_data,
            'source_file'  => 'ai_generated',
            'version'      => '1.0',
          ])
          ->execute();
      }
      catch (\Exception $e) {
        $this->logger->warning('Failed to register NPC @id in global library: @err', [
          '@id'  => $content_id,
          '@err' => $e->getMessage(),
        ]);
      }

      // 2. Campaign-scoped copy (dc_campaign_content_registry).
      try {
        $this->database->merge('dc_campaign_content_registry')
          ->key(['campaign_id' => $campaign_id, 'content_type' => 'npc', 'content_id' => $content_id])
          ->fields([
            'campaign_id'      => $campaign_id,
            'content_type'     => 'npc',
            'content_id'       => $content_id,
            'name'             => $name,
            'level'            => 0,
            'rarity'           => 'common',
            'tags'             => $tags,
            'schema_data'      => $schema_data,
            'source_content_id' => $content_id,
            'created'          => $now,
            'updated'          => $now,
          ])
          ->execute();
      }
      catch (\Exception $e) {
        $this->logger->warning('Failed to register NPC @id in campaign content registry: @err', [
          '@id'  => $content_id,
          '@err' => $e->getMessage(),
        ]);
      }

      // 3. Campaign character instance (dc_campaign_characters).
      // Check first — avoid duplicating if this NPC was already registered.
      try {
        $existing = $this->database->select('dc_campaign_characters', 'c')
          ->fields('c', ['id'])
          ->condition('campaign_id', $campaign_id)
          ->condition('instance_id', $content_id)
          ->execute()
          ->fetchField();

        if (!$existing) {
          $state_data = json_encode([
            'content_id'  => $content_id,
            'role'        => $npc['role'] ?? 'neutral',
            'description' => $npc['description'] ?? '',
            'stats'       => $npc['stats'] ?? [],
            'equipment'   => $npc['equipment'] ?? [],
            'attitude'    => $npc['attitude'] ?? 'indifferent',
          ]);

          $this->database->insert('dc_campaign_characters')
            ->fields([
              'campaign_id'   => $campaign_id,
              'character_id'  => 0,
              'uid'           => 0,
              'role'          => $npc['role'] ?? 'npc',
              'is_active'     => 1,
              'joined'        => $now,
              'instance_id'   => $content_id,
              'type'          => 'npc',
              'character_data' => $state_data,
              'location_type' => 'room',
              'location_ref'  => $room_id,
              'updated'       => $now,
              'name'          => $name,
              'level'         => 0,
              'ancestry'      => $npc['ancestry'] ?? 'humanoid',
              'class'         => 'npc',
              'status'        => 1,
              'created'       => $now,
              'changed'       => $now,
              'hp_current'    => $npc['stats']['currentHp'] ?? 0,
              'hp_max'        => $npc['stats']['maxHp'] ?? 0,
              'armor_class'   => $npc['stats']['ac'] ?? 0,
              'experience_points' => 0,
              'position_q'    => 0,
              'position_r'    => 0,
              'last_room_id'  => $room_id,
              'version'       => 0,
            ])
            ->execute();

          $this->logger->info('NPC @name (@id) registered in campaign @cid, room @room', [
            '@name' => $name,
            '@id'   => $content_id,
            '@cid'  => $campaign_id,
            '@room' => $room_id,
          ]);
        }
      }
      catch (\Exception $e) {
        $this->logger->warning('Failed to register NPC @id in dc_campaign_characters: @err', [
          '@id'  => $content_id,
          '@err' => $e->getMessage(),
        ]);
      }
    }
  }

  /**
   * Extract lowercase search keywords from a text string.
   *
   * Filters out common stop words and short words.
   */
  protected function extractSearchKeywords(string $text): array {
    $stop_words = [
      'the', 'a', 'an', 'to', 'in', 'on', 'at', 'of', 'for', 'and', 'or',
      'but', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has',
      'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may',
      'might', 'must', 'shall', 'can', 'need', 'dare', 'ought', 'used',
      'i', 'we', 'you', 'he', 'she', 'it', 'they', 'me', 'us', 'him', 'her',
      'them', 'my', 'our', 'your', 'his', 'its', 'their', 'this', 'that',
      'these', 'those', 'here', 'there', 'where', 'when', 'how', 'what',
      'which', 'who', 'whom', 'whose', 'not', 'no', 'nor', 'so', 'too',
      'very', 'just', 'also', 'than', 'then', 'now', 'only', 'with',
      'let', 'lets', 'go', 'head', 'want', 'like', 'from', 'into',
    ];
    $stop_set = array_flip($stop_words);

    $words = preg_split('/[^a-z0-9]+/', strtolower(trim($text)));
    $words = array_filter($words, function ($w) use ($stop_set) {
      return strlen($w) >= 3 && !isset($stop_set[$w]);
    });

    return array_values(array_unique($words));
  }

  /**
   * Infer a likely setting_type from the destination description.
   *
   * Uses keyword matching against known setting types.
   */
  protected function inferSettingType(string $destination): ?string {
    $lower = strtolower($destination);

    $patterns = [
      'tavern'      => ['tavern', 'inn', 'pub', 'bar', 'ale house', 'taproom'],
      'shop'        => ['shop', 'store', 'merchant', 'blacksmith', 'forge', 'bakery', 'apothecary', 'herbalist', 'armorer', 'weaponsmith', 'jeweler', 'tailor'],
      'temple'      => ['temple', 'church', 'shrine', 'chapel', 'cathedral', 'monastery', 'abbey'],
      'market'      => ['market', 'bazaar', 'trading post', 'marketplace', 'fair', 'auction'],
      'street'      => ['street', 'road', 'lane', 'avenue', 'boulevard', 'path', 'way'],
      'forest'      => ['forest', 'woods', 'grove', 'thicket', 'woodland', 'jungle'],
      'cave'        => ['cave', 'cavern', 'grotto', 'underground', 'mine', 'tunnel', 'warren', 'warrens', 'burrow', 'lair', 'den'],
      'dungeon'     => ['dungeon', 'crypt', 'catacomb', 'tomb', 'vault', 'labyrinth'],
      'library'     => ['library', 'archive', 'study', 'scriptorium', 'bookshop'],
      'throne_room' => ['throne', 'palace', 'castle', 'keep', 'citadel', 'court'],
      'dock'        => ['dock', 'harbor', 'port', 'pier', 'wharf', 'marina', 'shipyard'],
      'alley'       => ['alley', 'alleyway', 'back street', 'backstreet'],
      'sewer'       => ['sewer', 'drain', 'undercity', 'waterway'],
      'garden'      => ['garden', 'park', 'courtyard', 'orchard', 'vineyard', 'greenhouse'],
      'arena'       => ['arena', 'colosseum', 'pit', 'fighting ring', 'gladiator'],
      'prison'      => ['prison', 'jail', 'cell', 'dungeon', 'stockade', 'gaol'],
      'residential' => ['house', 'home', 'cottage', 'mansion', 'apartment', 'dwelling', 'residence', 'quarters'],
      'wilderness'  => ['wilderness', 'wasteland', 'plains', 'field', 'desert', 'tundra', 'swamp', 'marsh', 'moor', 'outside', 'outdoors'],
    ];

    foreach ($patterns as $type => $triggers) {
      foreach ($triggers as $trigger) {
        if (str_contains($lower, $trigger)) {
          return $type;
        }
      }
    }

    return NULL;
  }

  // =========================================================================
  // AI-driven setting generation (fallback when no library match)
  // =========================================================================

  /**
   * Use AI to generate a rich setting description with structured metadata.
   *
   * @param string $destination
   *   Where the player wants to go.
   * @param array $narrative_context
   *   GM narrative, campaign theme, etc.
   * @param array $dungeon_data
   *   Current dungeon data (for world consistency).
   *
   * @return array
   *   Structured setting data:
   *   - name: string
   *   - description: string
   *   - setting_type: string (tavern, shop, market, forest, etc.)
   *   - size: string (tiny, small, medium, large, huge)
   *   - terrain_type: string
   *   - lighting: string
   *   - theme_tags: array
   *   - npcs: array of NPC definitions
   *   - objects: array of furniture/object definitions
   *   - atmosphere: string
   */
  protected function generateSettingDescription(
    string $destination,
    array $narrative_context,
    array $dungeon_data
  ): array {
    $existing_rooms = [];
    foreach ($dungeon_data['rooms'] ?? [] as $r) {
      $existing_rooms[] = $r['name'] ?? 'Unknown';
    }

    $gm_narration = $narrative_context['gm_narrative'] ?? '';
    $time_of_day = $narrative_context['time_of_day'] ?? 'day';
    $party_level = $narrative_context['party_level'] ?? 1;
    $campaign_theme = $narrative_context['campaign_theme'] ?? 'high fantasy';

    $system_prompt = <<<'SYSTEM'
You are the world-builder for a Pathfinder 2e tabletop RPG. Your job is to generate detailed, playable settings when players navigate to new locations.

You must respond with ONLY valid JSON — no markdown, no explanation, no wrapping.

The setting must be:
- Internally consistent with a fantasy world
- Appropriately sized for the location type
- Populated with believable NPCs and objects
- Rich enough for tactical play on a hex grid

CRITICAL NPC RULE: If the DESTINATION or GM NARRATION mentions specific characters by name (e.g., "Gribbles", "a merchant", "the blacksmith"), you MUST include each of them as a fully-defined NPC in the npcs array. Never omit characters that the narrative has already placed in this location.

NAME RULE: The "name" field must be a SHORT location name — 2 to 5 words maximum (e.g., "Gribbles' Cave", "The Ironheart Forge", "Town Market Square"). The full sensory description goes in the "description" field only.
SYSTEM;

    $prompt = <<<PROMPT
Generate a detailed setting for a new location the players are traveling to.

DESTINATION: {$destination}
GM NARRATION: {$gm_narration}
TIME OF DAY: {$time_of_day}
PARTY LEVEL: {$party_level}
CAMPAIGN THEME: {$campaign_theme}
EXISTING LOCATIONS: {implode(', ', $existing_rooms)}

Respond with this exact JSON structure:
{
  "name": "The location name (e.g., 'Ironheart Forge', 'Town Market Square')",
  "description": "A vivid 2-3 sentence description of the location as the players see it when they arrive. Include sensory details — sights, sounds, smells.",
  "setting_type": "One of: tavern, shop, temple, market, street, forest, cave, dungeon, library, throne_room, dock, alley, sewer, garden, arena, prison, residential, wilderness",
  "size": "One of: tiny, small, medium, large, huge — appropriate for the location",
  "lighting": "One of: bright_light, normal_light, dim_light, darkness",
  "theme_tags": ["tag1", "tag2", "tag3"],
  "atmosphere": "A single sentence describing the mood/feeling of the place",
  "npcs": [
    {
      "name": "NPC display name",
      "content_id": "snake_case_unique_id",
      "ancestry": "Human/Elf/Dwarf/etc",
      "class": "Commoner/Fighter/Wizard/etc",
      "role": "neutral/quest_giver/merchant/guard",
      "team": "neutral/friendly/enemy",
      "occupation": "What they do here",
      "description": "1-2 sentence physical description",
      "backstory": "1-2 sentence background",
      "attitude": "friendly/indifferent/unfriendly/hostile",
      "stats": {
        "maxHp": 10,
        "currentHp": 10,
        "ac": 12,
        "speed": 25,
        "perception": 3
      },
      "equipment": ["item1", "item2"]
    }
  ],
  "objects": [
    {
      "object_id": "snake_case_id",
      "label": "Display Name",
      "category": "bar/table/stool/crate/door/decor/wall/custom",
      "description": "Brief description",
      "passable": true,
      "interactable": true
    }
  ]
}

Rules:
- NPCs MUST be included for any characters mentioned in the destination or GM narration
- NPCs should fit the setting (a blacksmith in a forge, a priest in a temple)
- 0-4 NPCs is typical — 1-2 when specific characters are referenced
- 2-8 objects/furniture is typical
- size should match reality: a small shop is "small", a town square is "large"
- content_id must be unique snake_case (e.g., "ironheart_blacksmith")
- The "name" field MUST be 2-5 words only — keep it short
PROMPT;

    try {
      $result = $this->aiApiService->invokeModelDirect(
        $prompt,
        'dungeoncrawler_content',
        'map_setting_generation',
        ['destination' => $destination],
        [
          'system_prompt' => $system_prompt,
          'max_tokens' => 1500,
          'skip_cache' => TRUE,
        ]
      );
    }
    catch (\Exception $e) {
      $this->logger->error('AI setting generation failed: @err', ['@err' => $e->getMessage()]);
      return $this->generateFallbackSetting($destination);
    }

    if (empty($result['success']) || empty($result['response'])) {
      $this->logger->warning('AI returned empty response for setting generation');
      return $this->generateFallbackSetting($destination);
    }

    $response = trim($result['response']);

    // Strip markdown code fences if present.
    $response = preg_replace('/^```(?:json)?\s*\n?/m', '', $response);
    $response = preg_replace('/\n?\s*```\s*$/m', '', $response);

    $setting = json_decode($response, TRUE);
    if (!is_array($setting) || empty($setting['name'])) {
      $this->logger->warning('Failed to parse AI setting response: @resp', [
        '@resp' => substr($response, 0, 500),
      ]);
      return $this->generateFallbackSetting($destination);
    }

    // Validate and normalize.
    return $this->normalizeSetting($setting);
  }

  /**
   * Fallback setting when AI generation fails.
   */
  protected function generateFallbackSetting(string $destination): array {
    $name = ucwords(trim($destination));
    return [
      'name' => $name ?: 'Unknown Location',
      'description' => "You arrive at {$name}. The area is unremarkable but serviceable.",
      'setting_type' => 'street',
      'size' => 'medium',
      'lighting' => 'normal_light',
      'theme_tags' => ['explored'],
      'atmosphere' => 'The air is still.',
      'npcs' => [],
      'objects' => [],
    ];
  }

  /**
   * Normalize and validate AI-generated setting data.
   */
  protected function normalizeSetting(array $setting): array {
    $valid_types = array_keys(self::TERRAIN_MAP);
    $valid_sizes = array_keys(self::SIZE_PRESETS);

    $setting['setting_type'] = in_array($setting['setting_type'] ?? '', $valid_types, TRUE)
      ? $setting['setting_type']
      : 'default';

    $setting['size'] = in_array($setting['size'] ?? '', $valid_sizes, TRUE)
      ? $setting['size']
      : 'medium';

    $valid_lighting = ['bright_light', 'normal_light', 'dim_light', 'darkness'];
    $setting['lighting'] = in_array($setting['lighting'] ?? '', $valid_lighting, TRUE)
      ? $setting['lighting']
      : (self::LIGHTING_MAP[$setting['setting_type']] ?? 'normal_light');

    $setting['theme_tags'] = array_filter(
      $setting['theme_tags'] ?? [],
      fn($t) => is_string($t) && strlen($t) < 50
    );

    // Validate NPCs.
    $setting['npcs'] = array_map(function($npc) {
      return [
        'name' => $npc['name'] ?? 'Unknown NPC',
        'content_id' => $npc['content_id'] ?? strtolower(preg_replace('/[^a-z0-9]+/i', '_', $npc['name'] ?? 'npc_' . uniqid())),
        'ancestry' => $npc['ancestry'] ?? 'Human',
        'class' => $npc['class'] ?? 'Commoner',
        'role' => $npc['role'] ?? 'neutral',
        'team' => $npc['team'] ?? 'neutral',
        'occupation' => $npc['occupation'] ?? '',
        'description' => $npc['description'] ?? '',
        'backstory' => $npc['backstory'] ?? '',
        'attitude' => $npc['attitude'] ?? 'indifferent',
        'stats' => [
          'maxHp' => $npc['stats']['maxHp'] ?? 10,
          'currentHp' => $npc['stats']['currentHp'] ?? $npc['stats']['maxHp'] ?? 10,
          'ac' => $npc['stats']['ac'] ?? 12,
          'speed' => $npc['stats']['speed'] ?? 25,
          'perception' => $npc['stats']['perception'] ?? 3,
          'initiative_bonus' => $npc['stats']['initiative_bonus'] ?? $npc['stats']['perception'] ?? 3,
        ],
        'equipment' => $npc['equipment'] ?? [],
      ];
    }, $setting['npcs'] ?? []);

    // Validate objects.
    $setting['objects'] = array_map(function($obj) {
      return [
        'object_id' => $obj['object_id'] ?? strtolower(preg_replace('/[^a-z0-9]+/i', '_', $obj['label'] ?? 'obj_' . uniqid())),
        'label' => $obj['label'] ?? 'Object',
        'category' => $obj['category'] ?? 'custom',
        'description' => $obj['description'] ?? '',
        'passable' => $obj['passable'] ?? TRUE,
        'interactable' => $obj['interactable'] ?? FALSE,
      ];
    }, $setting['objects'] ?? []);

    return $setting;
  }

  // =========================================================================
  // Step 2: Build room structure from setting
  // =========================================================================

  /**
   * Build a complete room structure from a normalized setting.
   *
   * @param array $setting
   *   Normalized setting data from generateSettingDescription().
   * @param string $origin_room_id
   *   Room the player is coming from (for connection).
   *
   * @return array
   *   Complete room structure matching dungeon_data.rooms[] schema.
   */
  protected function buildRoomFromSetting(array $setting, string $origin_room_id): array {
    $room_id = $this->generateUuid();
    $size_preset = self::SIZE_PRESETS[$setting['size']] ?? self::SIZE_PRESETS['medium'];
    $terrain = self::TERRAIN_MAP[$setting['setting_type']] ?? self::TERRAIN_MAP['default'];

    // Generate hex grid.
    $hexes = $this->generateHexGrid(
      $size_preset['cols'],
      $size_preset['rows'],
      $setting['setting_type']
    );

    // Place objects on hexes.
    $hexes = $this->placeObjectsOnHexes($hexes, $setting['objects']);

    return [
      'room_id' => $room_id,
      'name' => $setting['name'],
      'description' => $setting['description'],
      'hexes' => $hexes,
      'room_type' => $this->settingTypeToRoomType($setting['setting_type']),
      'size_category' => $size_preset['size'],
      'terrain' => [
        'type' => $terrain['type'],
        'difficult_terrain' => $terrain['difficult'],
        'greater_difficult_terrain' => FALSE,
        'hazardous_terrain' => NULL,
        'ceiling_height_ft' => $terrain['ceiling'],
      ],
      'lighting' => [
        'level' => $setting['lighting'],
      ],
      'state' => [
        'explored' => TRUE,
        'explored_at' => date('c'),
        'cleared' => FALSE,
        'looted' => FALSE,
        'traps_disarmed' => FALSE,
        'visibility' => 'visible',
      ],
      'ai_generation' => [
        'theme_tags' => $setting['theme_tags'],
        'difficulty_target' => 'trivial',
        'generation_model' => 'map_generator_ai',
      ],
      'gameplay_state' => [
        'active_effects' => [],
        'explored_hexes' => [],
        'environmental_changes' => [],
      ],
      'connections' => [],
      'chat' => [],
      'entities' => NULL,
    ];
  }

  /**
   * Generate a hex grid for a room.
   *
   * Uses offset-coordinate hex grid (flat-top), matching the existing
   * Gilded Tankard hex layout. Hexes are 5ft each.
   *
   * @param int $cols
   *   Number of columns.
   * @param int $rows
   *   Number of rows.
   * @param string $setting_type
   *   For terrain variation (e.g., forest gets elevation changes).
   *
   * @return array
   *   Array of hex definitions: [{q, r, elevation_ft, objects}, ...].
   */
  protected function generateHexGrid(int $cols, int $rows, string $setting_type): array {
    $hexes = [];
    $half_cols = intdiv($cols, 2);
    $half_rows = intdiv($rows, 2);

    // Natural settings get mild elevation variation.
    $has_elevation = in_array($setting_type, ['forest', 'cave', 'wilderness', 'garden', 'dock'], TRUE);

    for ($q = -$half_cols; $q <= $half_cols; $q++) {
      for ($r = -$half_rows; $r <= $half_rows; $r++) {
        // Skip some edge hexes to create organic shapes for natural settings.
        if ($this->shouldSkipEdgeHex($q, $r, $half_cols, $half_rows, $setting_type)) {
          continue;
        }

        $elevation = 0;
        if ($has_elevation) {
          // Gentle terrain variation.
          $elevation = (int) (sin($q * 0.7 + $r * 0.5) * 2.5);
          $elevation = max(0, $elevation);
        }

        $hexes[] = [
          'q' => $q,
          'r' => $r,
          'elevation_ft' => $elevation,
          'objects' => [],
        ];
      }
    }

    return $hexes;
  }

  /**
   * Skip edge hexes for organic-shaped rooms (forests, caves, etc.).
   */
  protected function shouldSkipEdgeHex(int $q, int $r, int $max_q, int $max_r, string $setting_type): bool {
    $is_edge = abs($q) === $max_q || abs($r) === $max_r;
    if (!$is_edge) {
      return FALSE;
    }

    // Structured settings (buildings) keep their rectangular shape.
    $structured = ['tavern', 'shop', 'temple', 'library', 'prison', 'residential', 'throne_room'];
    if (in_array($setting_type, $structured, TRUE)) {
      return FALSE;
    }

    // Natural settings: remove some corner/edge hexes for organic shape.
    $corner_dist = abs($q) + abs($r);
    $max_dist = $max_q + $max_r;
    if ($corner_dist >= $max_dist) {
      // Always remove extreme corners.
      return TRUE;
    }

    // Pseudo-random edge removal based on coordinates.
    $hash = crc32("{$q},{$r}");
    return ($hash % 4) === 0;
  }

  /**
   * Place furniture/objects on specific hexes.
   */
  protected function placeObjectsOnHexes(array $hexes, array $objects): array {
    if (empty($objects) || empty($hexes)) {
      return $hexes;
    }

    // Distribute objects around the room, avoiding the center and edges.
    $placeable = [];
    foreach ($hexes as $idx => $hex) {
      $dist_from_center = abs($hex['q']) + abs($hex['r']);
      if ($dist_from_center >= 1 && $dist_from_center <= 4) {
        $placeable[] = $idx;
      }
    }

    if (empty($placeable)) {
      $placeable = array_keys($hexes);
    }

    // Shuffle placement indices deterministically.
    shuffle($placeable);

    foreach ($objects as $i => $obj) {
      if (!isset($placeable[$i])) {
        break;
      }
      $hex_idx = $placeable[$i];
      $hexes[$hex_idx]['objects'][] = [
        'ref' => $obj['object_id'],
        'facing' => 0,
      ];
    }

    return $hexes;
  }

  // =========================================================================
  // Step 3: Generate entities
  // =========================================================================

  /**
   * Generate entity structures for NPCs and objects defined in the setting.
   *
   * @param array $setting
   *   Normalized setting with npcs[] and objects[].
   * @param string $room_id
   *   The new room's UUID.
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return array
   *   Array of entity structures for dungeon_data.entities[].
   */
  protected function generateSettingEntities(array $setting, string $room_id, int $campaign_id): array {
    $entities = [];
    $hexes_for_npcs = $this->getNpcPlacementHexes(count($setting['npcs']));

    // Generate NPC entities.
    foreach ($setting['npcs'] as $i => $npc) {
      $hex = $hexes_for_npcs[$i] ?? ['q' => $i, 'r' => 0];

      $entities[] = [
        'entity_instance_id' => $this->generateUuid(),
        'entity_type' => 'npc',
        'entity_ref' => [
          'content_type' => 'npc',
          'content_id' => $npc['content_id'],
        ],
        'placement' => [
          'room_id' => $room_id,
          'hex' => $hex,
          'spawn_type' => 'npc',
          'orientation' => 'n',
        ],
        'state' => [
          'active' => TRUE,
          'metadata' => [
            'display_name' => $npc['name'],
            'team' => $npc['team'],
            'role' => $npc['role'],
            'ancestry' => $npc['ancestry'],
            'class' => $npc['class'],
            'occupation' => $npc['occupation'],
            'description' => $npc['description'],
            'backstory' => $npc['backstory'],
            'stats' => $npc['stats'],
            'equipment' => $npc['equipment'],
            'languages' => ['Common'],
            'senses' => [],
            'abilities' => [],
            'orientation' => 'n',
          ],
        ],
      ];
    }

    // Generate object/furniture entities.
    foreach ($setting['objects'] as $obj) {
      // Objects are placed ON hexes via the hex.objects[] array, but we also
      // add them to object_definitions if they don't exist yet.
      // The hex placement was already handled in placeObjectsOnHexes().
    }

    return $entities;
  }

  /**
   * Get hex coordinates for NPC placement — spread them around the room.
   */
  protected function getNpcPlacementHexes(int $count): array {
    // Place NPCs at various positions around the room.
    $positions = [
      ['q' => 1,  'r' => 0],
      ['q' => -1, 'r' => 1],
      ['q' => 2,  'r' => -1],
      ['q' => -2, 'r' => 0],
      ['q' => 0,  'r' => 2],
      ['q' => 1,  'r' => -2],
      ['q' => -1, 'r' => -1],
      ['q' => 3,  'r' => 0],
    ];

    return array_slice($positions, 0, $count);
  }

  // =========================================================================
  // Step 4-7: Wiring — connections, regions, object_definitions
  // =========================================================================

  /**
   * Create a bidirectional connection between two rooms.
   */
  protected function createRoomConnection(array &$dungeon_data, string $from_room_id, string $to_room_id): void {
    // Add to hex_map connections.
    if (!isset($dungeon_data['hex_map']['connections'])) {
      $dungeon_data['hex_map']['connections'] = [];
    }

    $dungeon_data['hex_map']['connections'][] = [
      'from_room' => $from_room_id,
      'to_room' => $to_room_id,
      'type' => 'passage',
      'bidirectional' => TRUE,
    ];

    // Also set room.connections on both rooms.
    foreach ($dungeon_data['rooms'] as &$room) {
      if (($room['room_id'] ?? '') === $from_room_id) {
        if (!isset($room['connections'])) {
          $room['connections'] = [];
        }
        $room['connections'][] = [
          'target_room_id' => $to_room_id,
          'type' => 'passage',
        ];
      }
      if (($room['room_id'] ?? '') === $to_room_id) {
        if (!isset($room['connections'])) {
          $room['connections'] = [];
        }
        $room['connections'][] = [
          'target_room_id' => $from_room_id,
          'type' => 'passage',
        ];
      }
    }
    unset($room);
  }

  /**
   * Add the new room as a region in hex_map.
   */
  protected function addRegionToHexMap(array &$dungeon_data, array $room): void {
    if (!isset($dungeon_data['hex_map']['regions'])) {
      $dungeon_data['hex_map']['regions'] = [];
    }

    $dungeon_data['hex_map']['regions'][] = [
      'region_id' => $room['room_id'],
      'name' => $room['name'],
      'room_type' => $room['room_type'],
      'hex_count' => count($room['hexes']),
    ];
  }

  // =========================================================================
  // Utility helpers
  // =========================================================================

  /**
   * Map setting_type to room_type enum.
   */
  protected function settingTypeToRoomType(string $setting_type): string {
    $map = [
      'tavern' => 'entrance',
      'shop' => 'chamber',
      'temple' => 'shrine',
      'market' => 'chamber',
      'street' => 'corridor',
      'forest' => 'natural_cavern',
      'cave' => 'natural_cavern',
      'dungeon' => 'chamber',
      'library' => 'chamber',
      'throne_room' => 'boss_room',
      'dock' => 'chamber',
      'alley' => 'corridor',
      'sewer' => 'corridor',
      'garden' => 'natural_cavern',
      'arena' => 'boss_room',
      'prison' => 'cell',
      'residential' => 'chamber',
      'wilderness' => 'natural_cavern',
    ];
    return $map[$setting_type] ?? 'chamber';
  }

  /**
   * Generate a UUID v4.
   */
  protected function generateUuid(): string {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
  }

  /**
   * Backfill per-room connections[] from hex_map connections and regions.
   *
   * Older rooms created before the connection system may have empty
   * connections[] arrays even though hex_map.connections[] and regions
   * describe the topology. This resolves and populates them.
   *
   * @param array &$dungeon_data
   *   Dungeon data (modified in place).
   *
   * @return int
   *   Number of connections backfilled.
   */
  public function backfillRoomConnections(array &$dungeon_data): int {
    $rooms = &$dungeon_data['rooms'];
    $hex_connections = $dungeon_data['hex_map']['connections'] ?? [];
    $regions = $dungeon_data['hex_map']['regions'] ?? [];
    $count = 0;

    // Build room_id lookup.
    $room_by_id = [];
    foreach ($rooms as $idx => &$room) {
      $rid = $room['room_id'] ?? '';
      if ($rid) {
        $room_by_id[$rid] = &$rooms[$idx];
      }
    }
    unset($room);

    // Build hex→room_id index from room hexes.
    $hex_to_room = [];
    foreach ($rooms as $room) {
      $rid = $room['room_id'] ?? '';
      foreach ($room['hexes'] ?? [] as $hex) {
        $q = $hex['q'] ?? NULL;
        $r = $hex['r'] ?? NULL;
        if ($q !== NULL && $r !== NULL) {
          $hex_to_room["{$q},{$r}"] = $rid;
        }
      }
    }

    // Process each hex_map connection.
    foreach ($hex_connections as &$conn) {
      $from_room = $conn['from_room'] ?? NULL;
      $to_room = $conn['to_room'] ?? NULL;

      // If connection uses new format (from_room/to_room), use directly.
      if (!$from_room || !$to_room) {
        // Old format: resolve from hex coordinates.
        $from_key = ($conn['from']['q'] ?? '?') . ',' . ($conn['from']['r'] ?? '?');
        $to_key = ($conn['to']['q'] ?? '?') . ',' . ($conn['to']['r'] ?? '?');
        $from_room = $hex_to_room[$from_key] ?? NULL;
        $to_room = $hex_to_room[$to_key] ?? NULL;

        // If hex resolution failed, try region-based matching.
        if (!$from_room || !$to_room) {
          $region_rooms = [];
          foreach ($regions as $region) {
            foreach ($region['room_ids'] ?? [] as $rid) {
              $region_rooms[] = $rid;
            }
          }
          // If exactly 2 regions with 1 room each, and 1 connection, it's obvious.
          if (count($region_rooms) >= 2 && (!$from_room || !$to_room)) {
            $from_room = $from_room ?? $region_rooms[0];
            $to_room = $to_room ?? $region_rooms[1];
          }
        }

        // Upgrade the connection to new format for future lookups.
        if ($from_room && $to_room) {
          $conn['from_room'] = $from_room;
          $conn['to_room'] = $to_room;
        }
      }

      if (!$from_room || !$to_room || $from_room === $to_room) {
        continue;
      }

      $conn_type = $conn['type'] ?? 'passage';

      // Add to from_room → to_room if not already present.
      if (isset($room_by_id[$from_room])) {
        if (!isset($room_by_id[$from_room]['connections'])) {
          $room_by_id[$from_room]['connections'] = [];
        }
        $already_exists = FALSE;
        foreach ($room_by_id[$from_room]['connections'] as $existing) {
          if (($existing['target_room_id'] ?? '') === $to_room) {
            $already_exists = TRUE;
            break;
          }
        }
        if (!$already_exists) {
          $room_by_id[$from_room]['connections'][] = [
            'target_room_id' => $to_room,
            'type' => $conn_type,
          ];
          $count++;
        }
      }

      // Add reverse: to_room → from_room (bidirectional).
      $bidirectional = $conn['bidirectional'] ?? $conn['is_known'] ?? TRUE;
      if ($bidirectional && isset($room_by_id[$to_room])) {
        if (!isset($room_by_id[$to_room]['connections'])) {
          $room_by_id[$to_room]['connections'] = [];
        }
        $already_exists = FALSE;
        foreach ($room_by_id[$to_room]['connections'] as $existing) {
          if (($existing['target_room_id'] ?? '') === $from_room) {
            $already_exists = TRUE;
            break;
          }
        }
        if (!$already_exists) {
          $room_by_id[$to_room]['connections'][] = [
            'target_room_id' => $from_room,
            'type' => $conn_type,
          ];
          $count++;
        }
      }
    }
    unset($conn);

    if ($count > 0) {
      $this->logger->info('Backfilled @count room connections from hex_map data', [
        '@count' => $count,
      ]);
    }

    return $count;
  }

}
