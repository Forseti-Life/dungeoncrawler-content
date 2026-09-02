<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Support\H3SpatialHelper;
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
 *
 * Validation pair: StateValidationService::validateNavigationReceipt() plus
 * navigation connection parity assertions.
 */
class MapGeneratorService {

  protected Connection $database;
  protected LoggerInterface $logger;
  protected AIApiService $aiApiService;
  protected NpcPsychologyService $psychologyService;
  protected RoomStateService $roomStateService;
  protected NpcSheetGenerationService $npcSheetGenerationService;
  protected StateValidationService $stateValidationService;
  protected ?NavigationService $navigationService;
  protected const NAVIGATION_RECEIPT_SCHEMA_VERSION = 'navigation-receipt-v2';
  protected const MIN_ROOM_GAP_HEXES = 5;
  protected const H3_ACTIVE_RESOLUTION = 14;

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
    NpcPsychologyService $psychology_service,
    RoomStateService $room_state_service,
    NpcSheetGenerationService $npc_sheet_generation_service,
    StateValidationService $state_validation_service,
    ?NavigationService $navigation_service = NULL
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler_map_gen');
    $this->aiApiService = $ai_api_service;
    $this->psychologyService = $psychology_service;
    $this->roomStateService = $room_state_service;
    $this->npcSheetGenerationService = $npc_sheet_generation_service;
    $this->stateValidationService = $state_validation_service;
    $this->navigationService = $navigation_service;
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
     * This is the main entry point. Given a canonical destination label (e.g.,
     * "Blacksmith", "Town Square", "Tavern Entrance"), it:
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
     *   Canonical destination label used for room reuse and library matching.
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
    $this->logger->notice('Map generation entry: campaign=@campaign_id origin_room_id=@origin_room_id destination=@destination narrative_context_keys=@narrative_context_keys', [
      '@campaign_id' => $campaign_id,
      '@origin_room_id' => $origin_room_id,
      '@destination' => $destination,
      '@narrative_context_keys' => implode(',', array_keys($narrative_context)),
    ]);
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

    $existing_room_match = $this->findExistingCampaignRoomMatch($campaign_id, $dungeon_data, $destination, $origin_room_id);
    if ($existing_room_match !== NULL) {
      $room = $existing_room_match['room'];
      $room_index = (int) $existing_room_match['room_index'];

      $reused_room_id = (string) ($room['room_id'] ?? '');
      $this->executeNavigationPersistenceTransaction(function () use (
        &$dungeon_data,
        $campaign_id,
        $dungeon_id,
        $room_index,
        $origin_room_id,
        $reused_room_id,
        $room
      ): void {
        if (
          $dungeon_id !== ''
          && isset($dungeon_data['rooms'][$room_index])
          && is_array($dungeon_data['rooms'][$room_index])
        ) {
          $dungeon_data['rooms'][$room_index] = $this->ensureRoomHexH3Indexes(
            $dungeon_id,
            $dungeon_data['rooms'][$room_index]
          );

          if (isset($dungeon_data['hex_map']['rooms']) && is_array($dungeon_data['hex_map']['rooms'])) {
            foreach ($dungeon_data['hex_map']['rooms'] as $hex_room_index => $hex_room) {
              if (!is_array($hex_room) || (string) ($hex_room['room_id'] ?? '') !== $reused_room_id) {
                continue;
              }
              $dungeon_data['hex_map']['rooms'][$hex_room_index] = $dungeon_data['rooms'][$room_index];
              break;
            }
          }
        }

        $this->createRoomConnection($dungeon_data, $origin_room_id, $reused_room_id);
        $this->database->update('dc_campaign_dungeons')
          ->fields([
            'dungeon_data' => json_encode($dungeon_data),
            'updated' => time(),
          ])
          ->condition('dungeon_id', $dungeon_id)
          ->condition('campaign_id', $campaign_id)
          ->execute();
        $this->syncCampaignConnectionRows(
          $campaign_id,
          $dungeon_id,
          $origin_room_id,
          $reused_room_id
        );
        $this->assertNavigationConnectionParity(
          $campaign_id,
          $dungeon_id,
          $origin_room_id,
          $reused_room_id,
          $dungeon_data
        );

        if (!empty($room['room_id'])) {
          $this->roomStateService->setState($campaign_id, $room['room_id'], $dungeon_id, [
            'roomId' => $room['room_id'],
            'dungeonId' => $dungeon_id,
            'explored' => TRUE,
            'visibility' => 'visible',
            'isCleared' => FALSE,
          ], NULL);
        }
      });

      $this->logger->info('Existing setting reused: @name (room_id=@room_id, room_index=@idx, destination=@dest)', [
        '@name' => $room['name'] ?? 'Unknown',
        '@room_id' => $room['room_id'] ?? 'unknown',
        '@idx' => $room_index,
        '@dest' => $destination,
      ]);
      $this->logger->notice('Map generation exit: campaign=@campaign_id destination=@destination result=reused_existing_room room_id=@room_id room_name=@room_name room_index=@room_index', [
        '@campaign_id' => $campaign_id,
        '@destination' => $destination,
        '@room_id' => (string) ($room['room_id'] ?? ''),
        '@room_name' => (string) ($room['name'] ?? ''),
        '@room_index' => $room_index,
      ]);

      return [
        'room' => $dungeon_data['rooms'][$room_index] ?? $room,
        'room_index' => $room_index,
        'entities' => [],
        'dungeon_data' => $dungeon_data,
        'source' => 'existing_campaign_room',
        'template_id' => NULL,
      ];
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
      $this->logger->notice('Map generation branch: campaign=@campaign_id destination=@destination branch=library template_id=@template_id setting_type=@setting_type', [
        '@campaign_id' => $campaign_id,
        '@destination' => $destination,
        '@template_id' => $template_id,
        '@setting_type' => (string) ($setting['setting_type'] ?? ''),
      ]);
    }
    else {
      // No library match — generate via AI.
      $generation_seed = trim((string) ($narrative_context['destination_description'] ?? ''));
      if ($generation_seed === '') {
        $generation_seed = $destination;
      }
      $setting = $this->generateSettingDescription($generation_seed, $narrative_context, $dungeon_data);

      // Cache the AI-generated setting as a new library template.
      $template_id = $this->cacheSettingAsTemplate($setting, $destination, $party_level);
      $this->logger->info('New template cached: @tid', ['@tid' => $template_id]);
      $this->logger->notice('Map generation branch: campaign=@campaign_id destination=@destination branch=ai_generated template_id=@template_id setting_type=@setting_type', [
        '@campaign_id' => $campaign_id,
        '@destination' => $destination,
        '@template_id' => (string) $template_id,
        '@setting_type' => (string) ($setting['setting_type'] ?? ''),
      ]);
    }

    // Step 2: Build the room structure.
    $room = $this->buildRoomFromSetting($setting, $origin_room_id);
    $placement_result = $this->placeRoomWithMinimumGap(
      $room,
      is_array($dungeon_data['rooms'] ?? NULL) ? $dungeon_data['rooms'] : [],
      self::MIN_ROOM_GAP_HEXES
    );
    $room = $placement_result['room'];
    $room = $this->ensureRoomHexH3Indexes($dungeon_id, $room);

    // Finalize generated NPC/item contracts now that the room id is known.
    $setting = $this->finalizeGeneratedSettingContracts($setting, $room['room_id']);

    // Step 3: Generate entities (NPCs, objects, furniture) for the room.
    $entities = $this->generateSettingEntities($setting, $room['room_id'], $campaign_id);
    $entities = $this->offsetGeneratedEntitiesByHex(
      $entities,
      (int) ($placement_result['offset_q'] ?? 0),
      (int) ($placement_result['offset_r'] ?? 0)
    );

    $room_index = -1;
    $this->executeNavigationPersistenceTransaction(function () use (
      &$dungeon_data,
      &$room_index,
      $campaign_id,
      $dungeon_id,
      $origin_room_id,
      $room,
      $entities,
      $template_id,
      $setting
    ): void {
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
      $this->syncCampaignConnectionRows($campaign_id, $dungeon_id, $origin_room_id, $room['room_id']);
      $this->assertNavigationConnectionParity(
        $campaign_id,
        $dungeon_id,
        $origin_room_id,
        (string) $room['room_id'],
        $dungeon_data
      );

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

      // Step 10a.1: Mark the destination as discovered/visited now that this
      // generation path is being used for immediate travel into the new room.
      $this->roomStateService->setState($campaign_id, $room['room_id'], $dungeon_id, [
        'roomId' => $room['room_id'],
        'dungeonId' => $dungeon_id,
        'explored' => TRUE,
        'visibility' => 'visible',
        'isCleared' => FALSE,
      ], NULL);

      // Step 10b: Create NPC psychology profiles for any new NPCs.
      $this->ensureGeneratedNpcPsychologyProfiles($campaign_id, $entities);

      // Step 11: Register AI-generated NPCs in content library + campaign chars.
      $npc_setting_data = $setting['npcs'] ?? [];
      if (!empty($npc_setting_data)) {
        $this->registerGeneratedNpcs($campaign_id, $room['room_id'], $npc_setting_data);
      }
    });

    $this->logger->info('Setting ready: @name (source=@src, template=@tid, room_index=@idx, @hex hexes, @ent entities)', [
      '@name' => $room['name'],
      '@src' => $source,
      '@tid' => $template_id ?? 'none',
      '@idx' => $room_index,
      '@hex' => count($room['hexes']),
      '@ent' => count($entities),
    ]);
    $this->logger->notice('Map generation exit: campaign=@campaign_id destination=@destination room_id=@room_id room_name=@room_name source=@source template_id=@template_id room_index=@room_index entity_count=@entity_count', [
      '@campaign_id' => $campaign_id,
      '@destination' => $destination,
      '@room_id' => (string) ($room['room_id'] ?? ''),
      '@room_name' => (string) ($room['name'] ?? ''),
      '@source' => $source,
      '@template_id' => (string) ($template_id ?? ''),
      '@room_index' => $room_index,
      '@entity_count' => count($entities),
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

  /**
   * Ensure every room hex carries canonical Res14 H3 index metadata.
   *
   * @param string $dungeon_id
   *   Authoritative dungeon id used for axial->lat/lng projection.
   * @param array $room
   *   Room payload containing hexes.
   *
   * @return array
   *   Room payload with h3_index_res14 populated on every hex.
   */
  public function ensureRoomHexH3Indexes(string $dungeon_id, array $room): array {
    $dungeon_id = trim($dungeon_id);
    if ($dungeon_id === '') {
      throw new \RuntimeException('H3 room-index contract violation: dungeon_id is required.');
    }

    $room_id = trim((string) ($room['room_id'] ?? ''));
    $hexes = is_array($room['hexes'] ?? NULL) ? $room['hexes'] : [];
    if ($hexes === []) {
      throw new \RuntimeException(sprintf(
        'H3 room-index contract violation: room %s has no hexes to index.',
        $room_id !== '' ? $room_id : 'unknown'
      ));
    }

    foreach ($hexes as $index => &$hex) {
      if (!is_array($hex) || !is_numeric($hex['q'] ?? NULL) || !is_numeric($hex['r'] ?? NULL)) {
        throw new \RuntimeException(sprintf(
          'H3 room-index contract violation: room %s hex[%d] must define numeric q/r.',
          $room_id !== '' ? $room_id : 'unknown',
          $index
        ));
      }

      $q = (int) $hex['q'];
      $r = (int) $hex['r'];
      $existing_h3 = trim((string) ($hex['h3_index_res14'] ?? $hex['h3_index'] ?? ''));
      if ($existing_h3 === '') {
        $latlng = H3SpatialHelper::projectAxialHexToLatLng($dungeon_id, $q, $r);
        $existing_h3 = H3SpatialHelper::latLngToH3Index(
          (float) $latlng['latitude'],
          (float) $latlng['longitude'],
          self::H3_ACTIVE_RESOLUTION
        );
      }

      $hex['h3_index_res14'] = strtolower($existing_h3);
      if (trim((string) ($hex['h3_index'] ?? '')) === '') {
        $hex['h3_index'] = strtolower($existing_h3);
      }
    }
    unset($hex);

    $room['hexes'] = $hexes;
    return $room;
  }

  /**
   * Assert that room hexes already carry canonical Res14 H3 indexes.
   *
   * Use this for template-instantiation flows where runtime H3 computation is
   * forbidden and room payloads must be copied as fixed data.
   *
   * @param array $room
   *   Room payload containing hexes.
   * @param string $context
   *   Contract context used in failure messages.
   *
   * @return array
   *   Room payload with normalized lowercase h3_index/h3_index_res14 values.
   */
  public function requireRoomHexH3Indexes(array $room, string $context = 'room materialization'): array {
    $room_id = trim((string) ($room['room_id'] ?? ''));
    $hexes = is_array($room['hexes'] ?? NULL) ? $room['hexes'] : [];
    if ($hexes === []) {
      throw new \RuntimeException(sprintf(
        'H3 fixed-data contract violation (%s): room %s has no hexes.',
        $context,
        $room_id !== '' ? $room_id : 'unknown'
      ));
    }

    foreach ($hexes as $index => &$hex) {
      if (!is_array($hex) || !is_numeric($hex['q'] ?? NULL) || !is_numeric($hex['r'] ?? NULL)) {
        throw new \RuntimeException(sprintf(
          'H3 fixed-data contract violation (%s): room %s hex[%d] must define numeric q/r.',
          $context,
          $room_id !== '' ? $room_id : 'unknown',
          $index
        ));
      }
      $existing_h3 = trim((string) ($hex['h3_index_res14'] ?? $hex['h3_index'] ?? ''));
      if ($existing_h3 === '') {
        throw new \RuntimeException(sprintf(
          'H3 fixed-data contract violation (%s): room %s hex[%d] is missing h3_index_res14/h3_index.',
          $context,
          $room_id !== '' ? $room_id : 'unknown',
          $index
        ));
      }
      $normalized_h3 = strtolower($existing_h3);
      $hex['h3_index_res14'] = $normalized_h3;
      if (trim((string) ($hex['h3_index'] ?? '')) === '') {
        $hex['h3_index'] = $normalized_h3;
      }
      elseif (strtolower((string) $hex['h3_index']) !== $normalized_h3) {
        $hex['h3_index'] = $normalized_h3;
      }
    }
    unset($hex);

    $room['hexes'] = $hexes;
    return $room;
  }

  /**
   * Build a client-consumable navigation payload for generated locations.
   *
   * @param array $navigation
   *   Generated location data with keys:
   *   - destination
   *   - new_room
   *   - entities
   *   - dungeon_data
   *
   * @return array
   *   Client-ready navigation payload.
   */
  public function buildClientNavigationPayload(array $navigation): array {
    $room = $navigation['new_room'] ?? [];
    $room_id = (string) ($room['room_id'] ?? '');
    $dungeon_data = is_array($navigation['dungeon_data'] ?? NULL) ? $navigation['dungeon_data'] : [];

    foreach (($dungeon_data['rooms'] ?? []) as $candidate) {
      if (is_array($candidate) && (string) ($candidate['room_id'] ?? '') === $room_id) {
        $room = $candidate;
        break;
      }
    }

    $normalized_room = [
      'room_id' => $room_id,
      'name' => (string) ($room['name'] ?? ''),
      'description' => (string) ($room['description'] ?? ''),
      'hexes' => $this->normalizeRoomHexesForNavigationReceipt(
        is_array($room['hexes'] ?? NULL) ? $room['hexes'] : [],
        $room_id
      ),
      'terrain' => is_array($room['terrain'] ?? NULL) ? $room['terrain'] : [],
      'lighting' => is_string($room['lighting'] ?? NULL)
        ? $room['lighting']
        : (is_array($room['lighting'] ?? NULL) && isset($room['lighting']['level'])
          ? (string) ($room['lighting']['level'] ?? 'normal')
          : 'normal'),
      'room_type' => (string) ($room['room_type'] ?? 'unknown'),
      'size_category' => (string) ($room['size_category'] ?? 'medium'),
      'gameplay_state' => is_array($room['gameplay_state'] ?? NULL) ? $room['gameplay_state'] : [],
      'connections' => is_array($room['connections'] ?? NULL) ? $room['connections'] : [],
    ];

    if (!$this->navigationService) {
      throw new \RuntimeException('Navigation receipt contract violation: NavigationService is required for DB-authoritative route projection.');
    }

    $capabilities = $this->navigationService->buildNavigationCapabilitiesWithRoadNetwork($dungeon_data, $room_id);
    $connections = $this->buildNavigationReceiptConnectionsFromCapabilities($capabilities);

    $entry_hex = ['q' => 0, 'r' => 0];
    $source_hex = NULL;
    $origin_room_id = trim((string) ($navigation['origin_room_id'] ?? ''));
    $arrival_capability = $origin_room_id !== ''
      ? $this->findCapabilityToTargetRoom(
        $this->navigationService->buildNavigationCapabilitiesWithRoadNetwork($dungeon_data, $origin_room_id),
        $room_id
      )
      : NULL;
    if (is_array($arrival_capability['target_hex'] ?? NULL)) {
      $entry_hex = [
        'q' => (int) ($arrival_capability['target_hex']['q'] ?? 0),
        'r' => (int) ($arrival_capability['target_hex']['r'] ?? 0),
      ];
    }
    if (is_array($arrival_capability['origin_hex'] ?? NULL)) {
      $source_hex = [
        'q' => (int) ($arrival_capability['origin_hex']['q'] ?? 0),
        'r' => (int) ($arrival_capability['origin_hex']['r'] ?? 0),
      ];
    }

    if ($entry_hex['q'] === 0 && $entry_hex['r'] === 0 && !empty($normalized_room['hexes'][0])) {
      $entry_hex = [
        'q' => (int) ($normalized_room['hexes'][0]['q'] ?? 0),
        'r' => (int) ($normalized_room['hexes'][0]['r'] ?? 0),
      ];
    }

    if ($source_hex === NULL && $origin_room_id !== '') {
      $source_hex = $this->resolveDefaultRoomHex($dungeon_data, $origin_room_id);
    }
    $entry_h3_index_res14 = $this->resolveRoomHexH3IndexRes14($dungeon_data, $room_id, $entry_hex);
    $exit_h3_index_res14 = $origin_room_id !== '' && is_array($source_hex)
      ? $this->resolveRoomHexH3IndexRes14($dungeon_data, $origin_room_id, $source_hex)
      : $entry_h3_index_res14;
    $route_source_room_id = $origin_room_id !== '' ? $origin_room_id : $room_id;
    $route_street_path = [$entry_h3_index_res14];
    if ($exit_h3_index_res14 !== $entry_h3_index_res14) {
      $route_street_path = [$exit_h3_index_res14, $entry_h3_index_res14];
    }
    $room_anchor_h3_indexes = [
      $room_id => $entry_h3_index_res14,
    ];
    if ($origin_room_id !== '') {
      $room_anchor_h3_indexes[$origin_room_id] = $exit_h3_index_res14;
    }

    $payload = [
      'schema_version' => self::NAVIGATION_RECEIPT_SCHEMA_VERSION,
      'target_room_id' => $room_id,
      'destination' => (string) ($navigation['destination'] ?? ''),
      'destination_description' => (string) ($navigation['destination_description'] ?? $navigation['destination'] ?? ''),
      'travel_type' => (string) ($navigation['travel_type'] ?? 'walk'),
      'estimated_distance' => (string) ($navigation['estimated_distance'] ?? 'short'),
      'source' => (string) ($navigation['source'] ?? 'unknown'),
      'authority' => [
        'source' => 'canonical_db',
        'resolution' => 14,
      ],
      'route' => [
        'source_room_id' => $route_source_room_id,
        'target_room_id' => $room_id,
        'segments' => [[
          'from_room_id' => $route_source_room_id,
          'to_room_id' => $room_id,
          'entry_h3_index_res14' => $entry_h3_index_res14,
          'exit_h3_index_res14' => $exit_h3_index_res14,
          'street_path_h3_indexes' => $route_street_path,
          'traversal_cost' => max(1, count($route_street_path) - 1),
          'blocked' => FALSE,
        ]],
      ],
      'placement_contract' => [
        'normalization' => 'global_non_overlapping_axial',
        'active_anchor_resolution' => 14,
        'room_anchor_h3_indexes_res14' => $room_anchor_h3_indexes,
      ],
      'capabilities' => [
        'in_session_transition' => TRUE,
        'server_authoritative' => TRUE,
        'supports_res15' => FALSE,
      ],
      'template_id' => array_key_exists('template_id', $navigation) && $navigation['template_id'] !== NULL
        ? (string) $navigation['template_id']
        : NULL,
      'room' => $normalized_room,
      'entities' => $this->normalizeNavigationEntitiesForReceipt(
        is_array($navigation['entities'] ?? NULL) ? $navigation['entities'] : [],
        $dungeon_data,
        $room_id,
        $entry_hex
      ),
      'connections' => $connections,
      'navigation_capabilities' => $this->navigationService?->buildNavigationCapabilitiesWithRoadNetwork($dungeon_data, $room_id) ?? [],
      'entry_hex' => $entry_hex,
    ];

    if ($origin_room_id !== '') {
      $payload['origin_room_id'] = $origin_room_id;
    }

    $this->validateNavigationReceiptPayload($payload);
    return $payload;
  }

  /**
   * Project room hexes to the strict navigation receipt contract shape.
   *
   * @param array<int, mixed> $hexes
   *   Raw room hex rows.
   * @param string $room_id
   *   Owning room id for error context.
   *
   * @return array<int, array<string, mixed>>
   *   Contract-safe hex payload rows.
   */
  protected function normalizeRoomHexesForNavigationReceipt(array $hexes, string $room_id): array {
    $normalized = [];

    foreach ($hexes as $index => $hex) {
      if (!is_array($hex)) {
        throw new \RuntimeException(sprintf(
          'Navigation receipt contract violation: room %s hexes[%d] must be an object.',
          $room_id,
          $index
        ));
      }
      if (!array_key_exists('q', $hex) || !array_key_exists('r', $hex)) {
        throw new \RuntimeException(sprintf(
          'Navigation receipt contract violation: room %s hexes[%d] must include q/r.',
          $room_id,
          $index
        ));
      }

      $projected = [
        'q' => (int) $hex['q'],
        'r' => (int) $hex['r'],
      ];

      $terrain_override = trim((string) ($hex['terrain_override'] ?? $hex['terrain_type'] ?? ''));
      if ($terrain_override !== '') {
        $projected['terrain_override'] = $terrain_override;
      }

      $elevation_ft = $hex['elevation_ft'] ?? $hex['elevation'] ?? NULL;
      if ($elevation_ft !== NULL && $elevation_ft !== '') {
        $projected['elevation_ft'] = (int) $elevation_ft;
      }

      $h3_index_res14 = trim((string) ($hex['h3_index_res14'] ?? ''));
      if ($h3_index_res14 !== '') {
        $projected['h3_index_res14'] = strtolower($h3_index_res14);
      }

      $h3_index = trim((string) ($hex['h3_index'] ?? ''));
      if ($h3_index !== '') {
        $projected['h3_index'] = strtolower($h3_index);
      }

      if (is_array($hex['objects'] ?? NULL)) {
        $projected['objects'] = $hex['objects'];
      }

      $normalized[] = $projected;
    }

    return $normalized;
  }

  /**
   * Enforce the canonical navigation receipt contract.
   */
  protected function validateNavigationReceiptPayload(array $payload): void {
    $validation = $this->stateValidationService->validateNavigationReceipt($payload);
    if (!empty($validation['valid'])) {
      return;
    }

    throw new \RuntimeException('Navigation receipt contract violation: ' . implode('; ', $validation['errors'] ?? []));
  }

  /**
   * Convert navigation capabilities into receipt connection payload rows.
   *
   * @param array<int, array<string, mixed>> $capabilities
   *   Navigation capabilities from NavigationService.
   *
   * @return array<int, array<string, mixed>>
   *   Receipt connection projections.
   */
  protected function buildNavigationReceiptConnectionsFromCapabilities(array $capabilities): array {
    $connections = [];
    foreach ($capabilities as $capability) {
      if (!is_array($capability)) {
        continue;
      }
      $connections[] = [
        'connection_id' => (string) ($capability['connection_id'] ?? ''),
        'from_room_id' => (string) ($capability['origin_room_id'] ?? ''),
        'to_room_id' => (string) ($capability['target_room_id'] ?? ''),
        'from_hex' => is_array($capability['origin_hex'] ?? NULL) ? $capability['origin_hex'] : NULL,
        'to_hex' => is_array($capability['target_hex'] ?? NULL) ? $capability['target_hex'] : NULL,
        'type' => (string) ($capability['type'] ?? 'passage'),
        'destination_type' => (string) ($capability['destination_type'] ?? 'room'),
        'destination_id' => (string) ($capability['destination_id'] ?? ''),
        'distance' => (int) ($capability['distance'] ?? 0),
        'available' => !empty($capability['available']),
        'blocked_reason' => $capability['blocked_reason'] ?? NULL,
      ];
    }

    return $connections;
  }

  /**
   * Find one capability that targets the requested room.
   */
  protected function findCapabilityToTargetRoom(array $capabilities, string $target_room_id): ?array {
    $target_room_id = trim($target_room_id);
    if ($target_room_id === '') {
      return NULL;
    }

    foreach ($capabilities as $capability) {
      if (!is_array($capability)) {
        continue;
      }
      if (trim((string) ($capability['target_room_id'] ?? '')) === $target_room_id) {
        return $capability;
      }
    }

    return NULL;
  }

  /**
   * Resolve one default room hex coordinate.
   */
  protected function resolveDefaultRoomHex(array $dungeon_data, string $room_id): ?array {
    foreach ((array) ($dungeon_data['rooms'] ?? []) as $room) {
      if (!is_array($room) || (string) ($room['room_id'] ?? '') !== $room_id) {
        continue;
      }
      foreach ((array) ($room['hexes'] ?? []) as $hex) {
        if (!is_array($hex)) {
          continue;
        }
        if (array_key_exists('q', $hex) && array_key_exists('r', $hex)) {
          return [
            'q' => (int) $hex['q'],
            'r' => (int) $hex['r'],
          ];
        }
      }
      break;
    }

    return NULL;
  }

  /**
   * Resolve Res14 H3 index for a room hex coordinate.
   */
  protected function resolveRoomHexH3IndexRes14(array $dungeon_data, string $room_id, array $hex): string {
    $q = (int) ($hex['q'] ?? 0);
    $r = (int) ($hex['r'] ?? 0);
    foreach ((array) ($dungeon_data['rooms'] ?? []) as $room) {
      if (!is_array($room) || (string) ($room['room_id'] ?? '') !== $room_id) {
        continue;
      }

      foreach ((array) ($room['hexes'] ?? []) as $room_hex) {
        if (!is_array($room_hex)) {
          continue;
        }
        if ((int) ($room_hex['q'] ?? 0) !== $q || (int) ($room_hex['r'] ?? 0) !== $r) {
          continue;
        }
        $h3_index = trim((string) ($room_hex['h3_index_res14'] ?? $room_hex['h3_index'] ?? ''));
        if ($h3_index === '') {
          throw new \RuntimeException(sprintf(
            'Navigation receipt contract violation: room %s hex (%d,%d) is missing h3_index_res14.',
            $room_id,
            $q,
            $r
          ));
        }

        return strtolower($h3_index);
      }
      break;
    }

    throw new \RuntimeException(sprintf(
      'Navigation receipt contract violation: room %s missing hex (%d,%d) required for Res14 route authority.',
      $room_id,
      $q,
      $r
    ));
  }

  /**
   * Normalize navigation receipt entities to canonical placement state.
   *
   * Receipt entities are destination-scoped. Each entity must carry canonical
   * placement metadata for room, axial hex, orientation, and Res14 H3 index.
   *
   * @param array<int, mixed> $entities
   *   Raw entities from navigation result payload.
   * @param array<string, mixed> $dungeon_data
   *   Full dungeon payload for H3 resolution.
   * @param string $room_id
   *   Destination room id for the navigation receipt.
   * @param array<string, int> $entry_hex
   *   Destination entry hex fallback.
   *
   * @return array<int, array<string, mixed>>
   *   Canonicalized destination entities.
   */
  protected function normalizeNavigationEntitiesForReceipt(
    array $entities,
    array $dungeon_data,
    string $room_id,
    array $entry_hex
  ): array {
    $normalized_entities = [];
    foreach ($entities as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      $placement = is_array($entity['placement'] ?? NULL) ? $entity['placement'] : [];
      $placement_hex = is_array($placement['hex'] ?? NULL) ? $placement['hex'] : [];
      $resolved_hex = [
        'q' => (int) ($placement_hex['q'] ?? $entry_hex['q'] ?? 0),
        'r' => (int) ($placement_hex['r'] ?? $entry_hex['r'] ?? 0),
      ];
      $facing = isset($placement['facing']) ? (int) $placement['facing'] : 0;
      $facing = $facing % 6;
      if ($facing < 0) {
        $facing += 6;
      }
      $entity['placement'] = $placement;
      $entity['placement']['room_id'] = $room_id;
      $entity['placement']['hex'] = $resolved_hex;
      $entity['placement']['facing'] = $facing;
      $entity['placement']['h3_index_res14'] = $this->resolveRoomHexH3IndexRes14(
        $dungeon_data,
        $room_id,
        $resolved_hex
      );
      $normalized_entities[] = $entity;
    }

    return $normalized_entities;
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
    $this->logger->notice('Library match entry: campaign=@campaign_id destination=@destination inferred_type=@inferred_type keyword_count=@keyword_count', [
      '@campaign_id' => $campaign_id,
      '@destination' => $destination,
      '@inferred_type' => (string) $inferred_type,
      '@keyword_count' => count($keywords),
    ]);

    if (empty($keywords) && !$inferred_type) {
      $this->logger->notice('Library match exit: campaign=@campaign_id destination=@destination result=no_keywords_or_type', [
        '@campaign_id' => $campaign_id,
        '@destination' => $destination,
      ]);
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
      $this->logger->notice('Library match exit: campaign=@campaign_id destination=@destination result=no_candidates', [
        '@campaign_id' => $campaign_id,
        '@destination' => $destination,
      ]);
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
      $this->logger->notice('Library match exit: campaign=@campaign_id destination=@destination result=below_threshold best_score=@best_score best_template_id=@best_template_id candidate_count=@candidate_count', [
        '@campaign_id' => $campaign_id,
        '@destination' => $destination,
        '@best_score' => round($best_score, 4),
        '@best_template_id' => (string) ($best['template_id'] ?? ''),
        '@candidate_count' => count($candidates),
      ]);
      return NULL;
    }

    $this->logger->notice('Library match exit: campaign=@campaign_id destination=@destination result=matched template_id=@template_id best_score=@best_score candidate_count=@candidate_count', [
      '@campaign_id' => $campaign_id,
      '@destination' => $destination,
      '@template_id' => (string) ($best['template_id'] ?? ''),
      '@best_score' => round($best_score, 4),
      '@candidate_count' => count($candidates),
    ]);
    return $best;
  }

  /**
   * Reuse an existing campaign room when the destination already exists.
   */
  protected function findExistingCampaignRoomMatch(int $campaign_id, array &$dungeon_data, string $destination, string $origin_room_id): ?array {
    $this->mergeCampaignRoomRowsIntoDungeonData($campaign_id, $dungeon_data);
    $normalized_destination = $this->normalizeLocationLabel($destination);
    $this->logger->notice('Existing room match entry: origin_room_id=@origin_room_id destination=@destination normalized_destination=@normalized_destination room_count=@room_count', [
      '@origin_room_id' => $origin_room_id,
      '@destination' => $destination,
      '@normalized_destination' => $normalized_destination,
      '@room_count' => count($dungeon_data['rooms'] ?? []),
    ]);
    if ($normalized_destination === '') {
      $this->logger->notice('Existing room match exit: origin_room_id=@origin_room_id result=empty_destination', [
        '@origin_room_id' => $origin_room_id,
      ]);
      return NULL;
    }

    $origin_room = NULL;
    foreach (($dungeon_data['rooms'] ?? []) as $room) {
      if ((string) ($room['room_id'] ?? '') === $origin_room_id) {
        $origin_room = $room;
        break;
      }
    }

    $matches = [];
    foreach (($dungeon_data['rooms'] ?? []) as $index => $room) {
      $room_id = (string) ($room['room_id'] ?? '');
      if ($room_id === '' || $room_id === $origin_room_id) {
        continue;
      }

      $normalized_name = $this->normalizeLocationLabel((string) ($room['name'] ?? ''));
      $normalized_room_id = $this->normalizeLocationLabel((string) ($room['room_id'] ?? ''));
      $normalized_source_room_id = $this->normalizeLocationLabel((string) ($room['source_room_id'] ?? ''));
      if ($normalized_name === '' && $normalized_room_id === '' && $normalized_source_room_id === '') {
        continue;
      }

      $exact_match = $normalized_name === $normalized_destination
        || $normalized_room_id === $normalized_destination
        || $normalized_source_room_id === $normalized_destination;
      $source_id_match = $this->locationLabelsLooselyMatch($normalized_source_room_id, $normalized_destination);
      $partial_match = !$exact_match && (
        $this->locationLabelsLooselyMatch($normalized_name, $normalized_destination)
        || $this->locationLabelsLooselyMatch($normalized_room_id, $normalized_destination)
        || $source_id_match
      );
      if (!$exact_match && !$partial_match) {
        continue;
      }

      $connected = ($origin_room !== NULL && $this->roomHasConnection($origin_room, $room_id))
        || $this->roomHasConnection($room, $origin_room_id);

      $matches[] = [
        'room' => $room,
        'room_index' => $index,
        'exact_match' => $exact_match,
        'connected' => $connected,
        'source_id_match' => $source_id_match,
        'canonical_backed' => $normalized_source_room_id !== '',
      ];
    }

    if ($matches === []) {
      $this->logger->notice('Existing room match exit: origin_room_id=@origin_room_id destination=@destination result=no_match', [
        '@origin_room_id' => $origin_room_id,
        '@destination' => $destination,
      ]);
      return NULL;
    }

    usort($matches, static function (array $a, array $b): int {
      $scoreA = ($a['source_id_match'] ? 200 : 0)
        + ($a['exact_match'] ? 100 : 0)
        + ($a['connected'] ? 10 : 0)
        + ($a['canonical_backed'] ? 5 : 0);
      $scoreB = ($b['source_id_match'] ? 200 : 0)
        + ($b['exact_match'] ? 100 : 0)
        + ($b['connected'] ? 10 : 0)
        + ($b['canonical_backed'] ? 5 : 0);
      if ($scoreA !== $scoreB) {
        return $scoreB <=> $scoreA;
      }
      return ((int) $a['room_index']) <=> ((int) $b['room_index']);
    });

    $selected = $matches[0];
    $this->logger->notice('Existing room match exit: origin_room_id=@origin_room_id destination=@destination result=matched room_id=@room_id room_name=@room_name exact_match=@exact_match connected=@connected candidate_count=@candidate_count', [
      '@origin_room_id' => $origin_room_id,
      '@destination' => $destination,
      '@room_id' => (string) (($selected['room']['room_id'] ?? '')),
      '@room_name' => (string) (($selected['room']['name'] ?? '')),
      '@exact_match' => !empty($selected['exact_match']) ? 'yes' : 'no',
      '@connected' => !empty($selected['connected']) ? 'yes' : 'no',
      '@candidate_count' => count($matches),
    ]);
    return $selected;
  }

  /**
   * Hydrate missing campaign room rows into dungeon_data.rooms for stable reuse matching.
   */
  protected function mergeCampaignRoomRowsIntoDungeonData(int $campaign_id, array &$dungeon_data): void {
    if (!isset($dungeon_data['rooms']) || !is_array($dungeon_data['rooms'])) {
      $dungeon_data['rooms'] = [];
    }

    $known_room_ids = [];
    foreach ($dungeon_data['rooms'] as $room) {
      if (!is_array($room)) {
        continue;
      }
      $room_id = trim((string) ($room['room_id'] ?? ''));
      if ($room_id !== '') {
        $known_room_ids[$room_id] = TRUE;
      }
    }

    $rows = $this->database->select('dc_campaign_rooms', 'r')
      ->fields('r', ['room_id', 'name', 'description', 'layout_data', 'contents_data', 'source_room_id'])
      ->condition('campaign_id', $campaign_id)
      ->execute()
      ->fetchAllAssoc('room_id');
    if ($rows === []) {
      return;
    }

    $pending = [];
    foreach ($rows as $room_id => $row) {
      if (isset($known_room_ids[$room_id])) {
        continue;
      }
      $layout_data = json_decode((string) ($row->layout_data ?? '{}'), TRUE);
      $layout_data = is_array($layout_data) ? $layout_data : [];
      $room_hexes = is_array($layout_data['hexes'] ?? NULL) ? $layout_data['hexes'] : [];
      if ($room_hexes === []) {
        throw new \RuntimeException(sprintf(
          'Campaign room merge contract violation: campaign %d room %s has no layout_data.hexes.',
          $campaign_id,
          (string) $room_id
        ));
      }
      $room_connections = $this->mapLayoutExitsToRoomConnections($layout_data);
      $pending[(string) $room_id] = [
        'room' => [
          'room_id' => (string) ($row->room_id ?? ''),
          'name' => (string) ($row->name ?? ''),
          'description' => (string) ($row->description ?? ''),
          'source_room_id' => (string) ($row->source_room_id ?? ''),
          'hexes' => $room_hexes,
          'entry_points' => is_array($layout_data['entry_points'] ?? NULL) ? $layout_data['entry_points'] : [],
          'exit_points' => is_array($layout_data['exit_points'] ?? NULL) ? $layout_data['exit_points'] : [],
          'exits' => is_array($layout_data['exits'] ?? NULL) ? $layout_data['exits'] : [],
          'terrain' => is_array($layout_data['terrain'] ?? NULL) ? $layout_data['terrain'] : [],
          'lighting' => is_string($layout_data['lighting'] ?? NULL)
            ? $layout_data['lighting']
            : (is_array($layout_data['lighting'] ?? NULL) && isset($layout_data['lighting']['level']) ? (string) $layout_data['lighting']['level'] : 'normal'),
          'room_type' => (string) ($layout_data['room_type'] ?? 'unknown'),
          'size_category' => (string) ($layout_data['size_category'] ?? 'medium'),
          'gameplay_state' => [],
          'connections' => $room_connections,
        ],
        'connections' => $room_connections,
      ];
    }

    while ($pending !== []) {
      $progressed = FALSE;
      foreach ($pending as $room_id => $candidate) {
        $connection_targets = array_values(array_filter(array_map(
          static fn(array $connection): string => trim((string) ($connection['target_room_id'] ?? '')),
          is_array($candidate['connections'] ?? NULL) ? $candidate['connections'] : []
        )));
        $bridges_known_graph = FALSE;
        foreach ($connection_targets as $target_room_id) {
          if (isset($known_room_ids[$target_room_id])) {
            $bridges_known_graph = TRUE;
            break;
          }
        }

        if ($known_room_ids !== [] && !$bridges_known_graph) {
          continue;
        }

        $dungeon_data['rooms'][] = $candidate['room'];
        $known_room_ids[$room_id] = TRUE;
        unset($pending[$room_id]);
        $progressed = TRUE;
      }

      if ($progressed) {
        continue;
      }

      throw new \RuntimeException(sprintf(
        'Campaign room merge contract violation: campaign %d has disconnected room rows with no bridge to active dungeon graph (%s).',
        $campaign_id,
        implode(', ', array_keys($pending))
      ));
    }
  }

  /**
   * Project layout_data.exits to room connection rows.
   *
   * @return array<int, array<string, mixed>>
   */
  protected function mapLayoutExitsToRoomConnections(array $layout_data): array {
    $connections = [];
    foreach ((array) ($layout_data['exits'] ?? []) as $exit) {
      if (!is_array($exit)) {
        continue;
      }
      $target_room_id = trim((string) ($exit['target_room_id'] ?? $exit['destination_id'] ?? $exit['room_id'] ?? ''));
      if ($target_room_id === '') {
        continue;
      }
      $connections[] = [
        'target_room_id' => $target_room_id,
        'type' => (string) ($exit['type'] ?? 'passage'),
      ];
    }
    return $connections;
  }

  /**
   * Looser location matching that accepts token-subset aliases.
   */
  protected function locationLabelsLooselyMatch(string $candidate, string $destination): bool {
    if ($candidate === '' || $destination === '') {
      return FALSE;
    }
    if ($candidate === $destination || str_contains($candidate, $destination) || str_contains($destination, $candidate)) {
      return TRUE;
    }

    $candidate_tokens = $this->tokenizeLocationLabel($candidate);
    $destination_tokens = $this->tokenizeLocationLabel($destination);
    if ($candidate_tokens === [] || $destination_tokens === []) {
      return FALSE;
    }
    foreach ($destination_tokens as $token) {
      if (!in_array($token, $candidate_tokens, TRUE)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Tokenize a normalized location label with simple plural folding.
   *
   * @return array<int, string>
   */
  protected function tokenizeLocationLabel(string $label): array {
    $tokens = array_values(array_filter(explode(' ', trim($label)), static fn(string $token): bool => $token !== ''));
    $normalized = [];
    foreach ($tokens as $token) {
      $normalized[] = $token;
      if (strlen($token) > 3 && str_ends_with($token, 's')) {
        $singular = rtrim($token, 's');
        if ($singular !== '') {
          $normalized[] = $singular;
        }
      }
    }
    return array_values(array_unique($normalized));
  }

  /**
   * Hydrate a full setting array from a library template row.
   */
  protected function hydrateSettingFromTemplate(array $template): array {
    $setting_data = json_decode($template['setting_data'] ?? '{}', TRUE) ?: [];
    $this->logger->notice('Hydrate template exit: template_id=@template_id name=@name setting_type=@setting_type npc_count=@npc_count object_count=@object_count', [
      '@template_id' => (string) ($template['template_id'] ?? ''),
      '@name' => (string) ($template['name'] ?? ''),
      '@setting_type' => (string) ($template['setting_type'] ?? ''),
      '@npc_count' => count($setting_data['npcs'] ?? []),
      '@object_count' => count($setting_data['objects'] ?? []),
    ]);

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
   * Execute navigation persistence writes in one DB transaction.
   */
  protected function executeNavigationPersistenceTransaction(callable $operation): void {
    $transaction = $this->database->startTransaction();
    try {
      $operation();
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /**
   * Ensure psychology profiles exist for generated NPC entities.
   *
   * @param int $campaign_id
   *   Campaign identifier.
   * @param array<int, array<string, mixed>> $entities
   *   Generated entity list for the new room.
   */
  protected function ensureGeneratedNpcPsychologyProfiles(int $campaign_id, array $entities): void {
    $room_entities = array_values(array_filter($entities, static fn($entity): bool => ($entity['entity_type'] ?? '') === 'npc'));
    if ($room_entities === []) {
      return;
    }
    $this->psychologyService->ensureRoomNpcProfiles($campaign_id, $room_entities);
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

    $layout_payload = $this->buildCanonicalCampaignRoomLayoutPayload($room);
    $contents_payload = $this->buildCanonicalCampaignRoomContentsPayload($setting);
    $this->persistCanonicalCampaignRoom(
      $campaign_id,
      (string) $room_id,
      (string) ($room['name'] ?? 'Unknown'),
      (string) ($room['description'] ?? ''),
      $layout_payload,
      $contents_payload,
      is_array($setting['theme_tags'] ?? NULL) ? $setting['theme_tags'] : [],
      $this->resolveGeneratedSourceRoomId($setting)
    );

    $this->logger->info('Room @id persisted to dc_campaign_rooms (name: @name)', [
      '@id'   => $room_id,
      '@name' => $room['name'] ?? 'Unknown',
    ]);
    $this->logger->notice('Room persistence exit: campaign=@campaign_id room_id=@room_id room_name=@room_name source_room_id=@source_room_id setting_type=@setting_type theme_tag_count=@theme_tag_count', [
      '@campaign_id' => $campaign_id,
      '@room_id' => $room_id,
      '@room_name' => (string) ($room['name'] ?? ''),
      '@source_room_id' => $this->resolveGeneratedSourceRoomId($setting),
      '@setting_type' => (string) ($setting['setting_type'] ?? ''),
      '@theme_tag_count' => count($setting['theme_tags'] ?? []),
    ]);
  }

  /**
   * Persist one canonical campaign room row.
   *
   * This is the authoritative campaign-room writer used by all room
   * instantiation paths (generator/bootstrap/navigation/storyline).
   */
  public function persistCanonicalCampaignRoom(
    int $campaign_id,
    string $room_id,
    string $name,
    string $description,
    array $layout_data,
    array $contents_data = [],
    array $environment_tags = [],
    ?string $source_room_id = NULL
  ): void {
    $room_id = trim($room_id);
    if ($campaign_id <= 0 || $room_id === '') {
      throw new \RuntimeException('Campaign room persistence contract violation: campaign_id and room_id are required.');
    }

    $hexes = is_array($layout_data['hexes'] ?? NULL) ? $layout_data['hexes'] : [];
    if ($hexes === []) {
      throw new \RuntimeException(sprintf(
        'Campaign room persistence contract violation: room %s has no layout_data.hexes.',
        $room_id
      ));
    }

    $lighting_raw = $layout_data['lighting'] ?? [];
    $normalized_lighting = is_array($lighting_raw)
      ? $lighting_raw
      : (is_string($lighting_raw) && $lighting_raw !== '' ? ['level' => $lighting_raw] : []);

    $normalized_layout = [
      'hexes' => $hexes,
      'entry_points' => is_array($layout_data['entry_points'] ?? NULL) ? $layout_data['entry_points'] : [],
      'exit_points' => is_array($layout_data['exit_points'] ?? NULL) ? $layout_data['exit_points'] : [],
      'exits' => is_array($layout_data['exits'] ?? NULL) ? $layout_data['exits'] : [],
      'terrain' => is_array($layout_data['terrain'] ?? NULL) ? $layout_data['terrain'] : [],
      'lighting' => $normalized_lighting,
      'room_type' => (string) ($layout_data['room_type'] ?? 'room'),
      'size_category' => (string) ($layout_data['size_category'] ?? 'medium'),
    ];

    if (array_key_exists('hex_manifest', $layout_data)) {
      $normalized_layout['hex_manifest'] = is_array($layout_data['hex_manifest'] ?? NULL) ? $layout_data['hex_manifest'] : [];
    }
    if (array_key_exists('source', $layout_data)) {
      $normalized_layout['source'] = (string) ($layout_data['source'] ?? '');
    }

    $normalized_contents = $this->normalizeCampaignRoomContentsReferences($contents_data, $room_id);
    $encoded_layout = json_encode($normalized_layout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $encoded_contents = json_encode($normalized_contents, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $encoded_environment_tags = json_encode(array_values(array_map('strval', $environment_tags)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded_layout) || !is_string($encoded_contents) || !is_string($encoded_environment_tags)) {
      throw new \RuntimeException(sprintf(
        'Campaign room persistence contract violation: failed to encode payloads for room %s.',
        $room_id
      ));
    }

    $resolved_source_room_id = trim((string) ($source_room_id ?? ''));
    if ($resolved_source_room_id === '') {
      $resolved_source_room_id = $room_id;
    }

    $now = time();
    $this->database->merge('dc_campaign_rooms')
      ->keys([
        'campaign_id' => $campaign_id,
        'room_id' => $room_id,
      ])
      ->fields([
        'name' => $name !== '' ? $name : $room_id,
        'description' => $description,
        'environment_tags' => $encoded_environment_tags,
        'layout_data' => $encoded_layout,
        'contents_data' => $encoded_contents,
        'source_room_id' => $resolved_source_room_id,
        'updated' => $now,
      ])
      ->expression('created', 'COALESCE(created, :created)', [':created' => $now])
      ->execute();
  }

  /**
   * Normalize contents_data into identifier-oriented reference records.
   *
   * Room authority rows must not persist embedded runtime object payloads.
   *
   * @param array<string,mixed> $contents_data
   *   Raw room contents payload.
   * @param string $room_id
   *   Persisted room identifier for error context.
   *
   * @return array<string,mixed>
   *   Identifier-oriented contents payload.
   */
  protected function normalizeCampaignRoomContentsReferences(array $contents_data, string $room_id): array {
    $buckets = [
      'npcs',
      'items',
      'entities',
      'obstacles',
      'hazards',
      'interactables',
      'creatures',
      'traps',
    ];
    $normalized = [];
    foreach ($buckets as $bucket) {
      $normalized[$bucket] = [];
      $entries = is_array($contents_data[$bucket] ?? NULL) ? $contents_data[$bucket] : [];
      foreach ($entries as $index => $entry) {
        if (is_string($entry)) {
          $entry = ['content_id' => trim($entry)];
        }
        if (!is_array($entry)) {
          continue;
        }
        $content_id = trim((string) (
          $entry['content_id']
          ?? $entry['entity_instance_id']
          ?? $entry['instance_id']
          ?? $entry['item_id']
          ?? $entry['npc_id']
          ?? $entry['object_id']
          ?? ''
        ));
        if ($content_id === '') {
          throw new \RuntimeException(sprintf(
            'Campaign room persistence contract violation: room %s contents_data.%s[%d] is missing content identifier.',
            $room_id,
            $bucket,
            (int) $index
          ));
        }

        $normalized_entry = ['content_id' => $content_id];
        foreach ([
          'name',
          'label',
          'role',
          'description',
          'quest_association',
          'team',
          'faction',
          'kind',
          'source',
        ] as $scalar_key) {
          if (!array_key_exists($scalar_key, $entry)) {
            continue;
          }
          $value = trim((string) $entry[$scalar_key]);
          if ($value !== '') {
            $normalized_entry[$scalar_key] = $value;
          }
        }
        if (array_key_exists('quantity', $entry) && is_numeric($entry['quantity'])) {
          $normalized_entry['quantity'] = max(1, (int) $entry['quantity']);
        }
        if (array_key_exists('tags', $entry) && is_array($entry['tags'])) {
          $tags = array_values(array_filter(array_map(
            static fn($tag): string => trim((string) $tag),
            $entry['tags']
          ), static fn(string $tag): bool => $tag !== ''));
          if ($tags !== []) {
            $normalized_entry['tags'] = $tags;
          }
        }
        $normalized[$bucket][] = $normalized_entry;
      }
    }

    return $normalized;
  }

  /**
   * Build canonical layout_data payload for generated campaign rooms.
   */
  protected function buildCanonicalCampaignRoomLayoutPayload(array $room): array {
    $hexes = is_array($room['hexes'] ?? NULL) ? $room['hexes'] : [];
    if ($hexes === []) {
      throw new \RuntimeException(sprintf('Generated room %s has no hexes for campaign persistence.', (string) ($room['room_id'] ?? 'unknown')));
    }

    $entry_points = $this->normalizeLayoutPoints(is_array($room['entry_points'] ?? NULL) ? $room['entry_points'] : []);
    $exit_points = $this->normalizeLayoutPoints(is_array($room['exit_points'] ?? NULL) ? $room['exit_points'] : []);
    if ($entry_points === []) {
      $entry_points[] = [
        'q' => (int) ($hexes[0]['q'] ?? 0),
        'r' => (int) ($hexes[0]['r'] ?? 0),
      ];
    }
    if ($exit_points === []) {
      $fallback_exit_hex = end($hexes);
      $exit_points[] = [
        'q' => (int) ($fallback_exit_hex['q'] ?? 0),
        'r' => (int) ($fallback_exit_hex['r'] ?? 0),
      ];
    }

    $entry_keys = [];
    foreach ($entry_points as $point) {
      $entry_keys[$point['q'] . ':' . $point['r']] = TRUE;
    }
    $terrain_type = trim((string) ($room['terrain']['type'] ?? 'stone_floor'));
    if ($terrain_type === '') {
      $terrain_type = 'stone_floor';
    }
    $lighting_level = trim((string) ($room['lighting']['level'] ?? 'normal_light'));
    if ($lighting_level === '') {
      $lighting_level = 'normal_light';
    }

    $normalized_hexes = [];
    foreach ($hexes as $hex_index => $hex) {
      if (!is_array($hex) || !is_numeric($hex['q'] ?? NULL) || !is_numeric($hex['r'] ?? NULL)) {
        throw new \RuntimeException(sprintf(
          'Generated room %s has invalid hex coordinates at index %d.',
          (string) ($room['room_id'] ?? 'unknown'),
          $hex_index
        ));
      }
      $q = (int) $hex['q'];
      $r = (int) $hex['r'];
      $objects = is_array($hex['objects'] ?? NULL) ? $hex['objects'] : [];
      $normalized_objects = [];
      foreach ($objects as $object_index => $object) {
        if (!is_array($object)) {
          continue;
        }
        $object_id = trim((string) ($object['object_id'] ?? ''));
        if ($object_id === '') {
          $object_id = sprintf('generated-object-%d-%d-%d', $q, $r, $object_index);
        }
        $label = trim((string) ($object['label'] ?? $object_id));
        if ($label === '') {
          $label = 'Object';
        }
        $passable = array_key_exists('passable', $object) ? (bool) $object['passable'] : TRUE;
        $blocks_movement = array_key_exists('blocks_movement', $object)
          ? (bool) $object['blocks_movement']
          : !$passable;
        if ($blocks_movement) {
          $passable = FALSE;
        }
        $normalized_objects[] = [
          'object_id' => $object_id,
          'label' => $label,
          'category' => trim((string) ($object['category'] ?? 'custom')) ?: 'custom',
          'passable' => $passable,
          'blocks_movement' => $blocks_movement,
        ];
      }

      $normalized_hexes[] = [
        'q' => $q,
        'r' => $r,
        'terrain_type' => trim((string) ($hex['terrain_type'] ?? $terrain_type)) ?: $terrain_type,
        'lighting' => trim((string) ($hex['lighting'] ?? $lighting_level)) ?: $lighting_level,
        'is_discovered' => array_key_exists('is_discovered', $hex) ? (bool) $hex['is_discovered'] : TRUE,
        'is_visible' => array_key_exists('is_visible', $hex) ? (bool) $hex['is_visible'] : TRUE,
        'is_entry' => isset($entry_keys[$q . ':' . $r]),
        'elevation_ft' => (int) ($hex['elevation_ft'] ?? 0),
        'objects' => $normalized_objects,
      ];
    }

    return [
      'shape' => trim((string) ($room['size_category'] ?? 'generated')) ?: 'generated',
      'hexes' => $normalized_hexes,
      'entry_points' => $entry_points,
      'exit_points' => $exit_points,
      'exits' => $this->buildCanonicalCampaignRoomExits($room, $exit_points),
      'terrain' => $room['terrain'] ?? [],
      'lighting' => $room['lighting'] ?? [],
    ];
  }

  /**
   * Build canonical contents_data payload for generated campaign rooms.
   */
  protected function buildCanonicalCampaignRoomContentsPayload(array $setting): array {
    $interactables = [];
    foreach ((array) ($setting['objects'] ?? []) as $object) {
      if (!is_array($object)) {
        continue;
      }
      $object_id = trim((string) ($object['object_id'] ?? ''));
      if ($object_id === '') {
        continue;
      }
      $interactables[] = [
        'content_id' => $object_id,
        'label' => trim((string) ($object['label'] ?? $object_id)),
      ];
    }

    return [
      'npcs' => [],
      'items' => [],
      'entities' => [],
      'obstacles' => [],
      'hazards' => [],
      'interactables' => $interactables,
      // Legacy buckets retained for older readers.
      'creatures' => [],
      'traps' => [],
    ];
  }

  /**
   * Normalize room point arrays into canonical [{q,r}] entries.
   *
   * @param array<int, mixed> $points
   *   Raw point payload.
   *
   * @return array<int, array{q:int,r:int}>
   *   Normalized point list.
   */
  protected function normalizeLayoutPoints(array $points): array {
    $normalized = [];
    foreach ($points as $point) {
      if (!is_array($point)) {
        continue;
      }
      $q = $point['q'] ?? ($point['hex']['q'] ?? NULL);
      $r = $point['r'] ?? ($point['hex']['r'] ?? NULL);
      if (!is_numeric($q) || !is_numeric($r)) {
        continue;
      }
      $normalized[] = [
        'q' => (int) $q,
        'r' => (int) $r,
      ];
    }
    return $normalized;
  }

  /**
   * Build canonical exits list from room connection metadata.
   *
   * @param array<string, mixed> $room
   *   Room payload.
   * @param array<int, array{q:int,r:int}> $exit_points
   *   Normalized exit points.
   *
   * @return array<int, array<string, mixed>>
   *   Canonical exits payload.
   */
  protected function buildCanonicalCampaignRoomExits(array $room, array $exit_points): array {
    $room_id = (string) ($room['room_id'] ?? '');
    $connections = is_array($room['connections'] ?? NULL) ? $room['connections'] : [];
    if ($connections === []) {
      return [];
    }

    $exits = [];
    foreach ($connections as $index => $connection) {
      if (!is_array($connection)) {
        continue;
      }
      $target_room_id = trim((string) ($connection['target_room_id'] ?? ''));
      if ($target_room_id === '') {
        continue;
      }
      $exit_point = $exit_points[$index % count($exit_points)];
      $connection_id = sprintf(
        'conn-%s',
        substr(hash('sha256', implode(':', [$room_id, $target_room_id, (string) $index])), 0, 16)
      );
      $exits[] = [
        'exit_id' => $connection_id,
        'connection_id' => $connection_id,
        'room_id' => $room_id,
        'target_room_id' => $target_room_id,
        'leads_to' => $target_room_id,
        'hex' => $exit_point,
        'direction' => 'unknown',
        'type' => (string) ($connection['type'] ?? 'passage'),
        'locked' => FALSE,
        'hidden' => FALSE,
      ];
    }

    return $exits;
  }

  /**
   * Resolve source room id for generated rooms when available.
   */
  protected function resolveGeneratedSourceRoomId(array $setting): string {
    $source_room_id = trim((string) ($setting['source_room_id'] ?? ''));
    if ($source_room_id !== '') {
      return $source_room_id;
    }
    return '';
  }

  /**
   * Persist room connections to dc_campaign_connections for query parity.
   */
  protected function syncCampaignConnectionRows(int $campaign_id, string $dungeon_id, string $from_room_id, string $to_room_id): void {
    $from = trim($from_room_id);
    $to = trim($to_room_id);
    if ($from === '' || $to === '' || $from === $to) {
      return;
    }

    $pair = [$from, $to];
    sort($pair, SORT_STRING);
    $connection_id = sprintf('room-conn-%s', substr(hash('sha256', $campaign_id . ':' . $pair[0] . ':' . $pair[1]), 0, 24));
    $now = time();

    $fields = [
      'dungeon_id' => $dungeon_id,
      'from_room_id' => $from,
      'to_room_id' => $to,
      'direction' => 'bidirectional',
      'kind' => 'hallway',
      'state' => 'open',
      'travel_cost' => 1,
      'is_discovered' => 1,
      'is_passable' => 1,
      'source_connection_id' => NULL,
      'updated' => $now,
    ];

    $updated_rows = $this->database->update('dc_campaign_connections')
      ->fields($fields)
      ->condition('campaign_id', $campaign_id)
      ->condition('connection_id', $connection_id)
      ->execute();

    if ((int) $updated_rows === 0) {
      $this->database->insert('dc_campaign_connections')
        ->fields($fields + [
          'campaign_id' => $campaign_id,
          'connection_id' => $connection_id,
          'created' => $now,
        ])
        ->execute();
    }
  }

  /**
   * Enforce parity between dungeon_data room links and campaign connection rows.
   */
  protected function assertNavigationConnectionParity(
    int $campaign_id,
    string $dungeon_id,
    string $from_room_id,
    string $to_room_id,
    array $dungeon_data
  ): void {
    $from = trim($from_room_id);
    $to = trim($to_room_id);
    if ($from === '' || $to === '' || $from === $to) {
      throw new \RuntimeException('Navigation parity contract violation: connection endpoints must be distinct non-empty room ids.');
    }

    if (!$this->hasDungeonDataConnectionPair($dungeon_data, $from, $to)) {
      throw new \RuntimeException(sprintf(
        'Navigation parity contract violation: dungeon_data is missing room link %s <-> %s.',
        $from,
        $to
      ));
    }

    if (!$this->hasCampaignConnectionPair($campaign_id, $dungeon_id, $from, $to)) {
      throw new \RuntimeException(sprintf(
        'Navigation parity contract violation: dc_campaign_connections is missing room link %s <-> %s.',
        $from,
        $to
      ));
    }
  }

  /**
   * Determine whether dungeon_data contains a bidirectional room pair link.
   */
  protected function hasDungeonDataConnectionPair(array $dungeon_data, string $from_room_id, string $to_room_id): bool {
    $from = trim($from_room_id);
    $to = trim($to_room_id);
    if ($from === '' || $to === '') {
      return FALSE;
    }

    $connection_sources = [];
    if (is_array($dungeon_data['hex_map']['connections'] ?? NULL)) {
      $connection_sources[] = $dungeon_data['hex_map']['connections'];
    }
    if (is_array($dungeon_data['connections'] ?? NULL)) {
      $connection_sources[] = $dungeon_data['connections'];
    }
    foreach ($connection_sources as $connections) {
      foreach ($connections as $connection) {
        if (!is_array($connection)) {
          continue;
        }
        $left = trim((string) ($connection['from_room'] ?? $connection['from_room_id'] ?? ''));
        $right = trim((string) ($connection['to_room'] ?? $connection['to_room_id'] ?? ''));
        if (($left === $from && $right === $to) || ($left === $to && $right === $from)) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * Determine whether dc_campaign_connections contains a room pair link.
   */
  protected function hasCampaignConnectionPair(
    int $campaign_id,
    string $dungeon_id,
    string $from_room_id,
    string $to_room_id
  ): bool {
    $pair = [trim($from_room_id), trim($to_room_id)];
    sort($pair, SORT_STRING);
    $connection_id = sprintf('room-conn-%s', substr(hash('sha256', $campaign_id . ':' . $pair[0] . ':' . $pair[1]), 0, 24));
    $record = $this->database->select('dc_campaign_connections', 'c')
      ->fields('c', ['connection_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('dungeon_id', $dungeon_id)
      ->condition('connection_id', $connection_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();

    return is_string($record) && $record !== '';
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
      $instance_id = $this->buildGeneratedNpcInstanceId((string) $content_id);
      $inventory = is_array($npc['inventory'] ?? NULL) ? $npc['inventory'] : [];
      $equipment_labels = is_array($npc['equipment'] ?? NULL) ? $npc['equipment'] : [];
      $npc_level = max(1, (int) ($npc['level'] ?? ($npc['stats']['level'] ?? 1)));

      $this->registerGeneratedEquipmentItems($campaign_id, $equipment_labels);

      $schema_data = json_encode([
        'schema_version' => '1.0.0',
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
        'inventory'   => $inventory,
        'equipment_labels' => $equipment_labels,
        'source'      => 'ai_generated',
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

      $tags = json_encode(array_filter([
        $npc['role'] ?? NULL,
        $npc['team'] ?? NULL,
        'ai_generated',
      ]));

      // 1. Global library entry (dungeoncrawler_content_registry).
      $this->database->merge('dungeoncrawler_content_registry')
        ->keys(['content_type' => 'npc', 'content_id' => $content_id])
        ->fields([
          'content_type' => 'npc',
          'content_id'   => $content_id,
          'name'         => $name,
          'level'        => $npc_level,
          'rarity'       => 'common',
          'tags'         => $tags,
          'schema_data'  => $schema_data,
          'source_file'  => 'ai_generated',
          'version'      => '1.0',
        ])
        ->execute();

      // 2. Campaign-scoped copy (dc_campaign_content_registry).
      $this->database->merge('dc_campaign_content_registry')
        ->keys(['campaign_id' => $campaign_id, 'content_type' => 'npc', 'content_id' => $content_id])
        ->fields([
          'campaign_id'      => $campaign_id,
          'content_type'     => 'npc',
          'content_id'       => $content_id,
          'name'             => $name,
          'level'            => $npc_level,
          'rarity'           => 'common',
          'tags'             => $tags,
          'schema_data'      => $schema_data,
          'source_content_id' => $content_id,
          'created'          => $now,
          'updated'          => $now,
        ])
        ->execute();

      // 3. Campaign character instance (dc_campaign_characters).
      // Check first — avoid duplicating if this NPC was already registered.
      $existing = $this->database->select('dc_campaign_characters', 'c')
        ->fields('c', ['id'])
        ->condition('campaign_id', $campaign_id)
        ->condition('instance_id', $instance_id)
        ->execute()
        ->fetchField();

      if (!$existing) {
        $state_data = json_encode([
          'content_id'  => $content_id,
          'role'        => $npc['role'] ?? 'neutral',
          'description' => $npc['description'] ?? '',
          'level'       => $npc_level,
          'stats'       => $npc['stats'] ?? [],
          'inventory'   => $inventory,
          'attitude'    => $npc['attitude'] ?? 'indifferent',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        $this->database->insert('dc_campaign_characters')
          ->fields([
            'campaign_id'   => $campaign_id,
            'character_id'  => 0,
            'source_character_id' => NULL,
            'uid'           => 0,
            'role'          => $npc['role'] ?? 'npc',
            'is_active'     => 1,
            'joined'        => $now,
            'instance_id'   => $instance_id,
            'type'          => 'npc',
            'lifecycle_state' => 'campaign_npc',
            'character_data' => $state_data,
            'default_locations' => NULL,
            'portrait' => NULL,
            'location_type' => 'room',
            'location_ref'  => $room_id,
            'updated'       => $now,
            'name'          => $name,
            'level'         => $npc_level,
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
            'position_h3'   => strtolower(trim((string) (
              $npc['position']['h3_index_res14']
              ?? $npc['position']['h3_index']
              ?? ''
            ))),
            'last_room_id'  => $room_id,
            'version'       => 0,
          ])
          ->execute();

        $this->logger->info('NPC @name (@id) registered in campaign @cid, room @room', [
          '@name' => $name,
          '@id'   => $instance_id,
          '@cid'  => $campaign_id,
          '@room' => $room_id,
        ]);
      }

      $this->npcSheetGenerationService->enqueueNpcSheetGeneration($campaign_id, $content_id, [
        'instance_id' => $instance_id,
        'entity_ref' => $content_id,
        'name' => $name,
        'ancestry' => $npc['ancestry'] ?? 'Human',
        'class' => $npc['class'] ?? 'Commoner',
        'role' => $npc['role'] ?? 'neutral',
        'occupation' => $npc['occupation'] ?? '',
        'description' => $npc['description'] ?? '',
        'backstory' => $npc['backstory'] ?? '',
        'attitude' => $npc['attitude'] ?? 'indifferent',
        'stats' => $npc['stats'] ?? [],
        'equipment' => $npc['equipment'] ?? [],
        'languages' => $npc['languages'] ?? ['Common'],
        'senses' => $npc['senses'] ?? [],
      ], FALSE);
    }

    $this->npcSheetGenerationService->launchDetachedWorker();
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
    $existing_room_list = implode(', ', $existing_rooms);

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
EXISTING LOCATIONS: {$existing_room_list}

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
      throw new \RuntimeException('AI setting generation failed: ' . $e->getMessage(), 0, $e);
    }

    if (empty($result['success']) || empty($result['response'])) {
      throw new \RuntimeException('AI returned empty response for setting generation.');
    }

    $response = trim($result['response']);

    // Strip markdown code fences if present.
    $response = preg_replace('/^```(?:json)?\s*\n?/m', '', $response);
    $response = preg_replace('/\n?\s*```\s*$/m', '', $response);

    $setting = json_decode($response, TRUE);
    if (!is_array($setting) || empty($setting['name'])) {
      $this->logger->error('Failed to parse AI setting response: @resp', [
        '@resp' => substr($response, 0, 500),
      ]);
      throw new \RuntimeException('Failed to parse AI setting response.');
    }

    // Validate and normalize.
    return $this->normalizeSetting($setting);
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
    $used_npc_ids = [];
    $setting['npcs'] = array_map(
      function (array $npc) use (&$used_npc_ids): array {
        return $this->normalizeGeneratedNpcContract($npc, $used_npc_ids);
      },
      $setting['npcs'] ?? []
    );

    // Validate objects.
    $used_object_ids = [];
    $setting['objects'] = array_map(
      function (array $obj) use (&$used_object_ids): array {
        return $this->normalizeGeneratedObjectContract($obj, $used_object_ids);
      },
      $setting['objects'] ?? []
    );

    return $setting;
  }

  /**
   * Normalize one generated NPC contract payload with canonical defaults.
   *
   * @param array<string, mixed> $npc
   * @param array<string, bool> $used_npc_ids
   *
   * @return array<string, mixed>
   */
  protected function normalizeGeneratedNpcContract(array $npc, array &$used_npc_ids): array {
    $name = isset($npc['name']) && is_scalar($npc['name']) ? trim((string) $npc['name']) : '';
    $content_id = isset($npc['content_id']) && is_scalar($npc['content_id']) ? trim((string) $npc['content_id']) : '';
    $content_id = $this->buildStableMachineId($content_id !== '' ? $content_id : $name, 'npc', $used_npc_ids);

    return [
      'name' => $name !== '' ? $name : 'Unknown NPC',
      'content_id' => $content_id,
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
  }

  /**
   * Normalize one generated object contract payload with canonical defaults.
   *
   * @param array<string, mixed> $object
   * @param array<string, bool> $used_object_ids
   *
   * @return array<string, mixed>
   */
  protected function normalizeGeneratedObjectContract(array $object, array &$used_object_ids): array {
    $label = isset($object['label']) && is_scalar($object['label']) ? trim((string) $object['label']) : '';
    $object_id = isset($object['object_id']) && is_scalar($object['object_id']) ? trim((string) $object['object_id']) : '';
    $object_id = $this->buildStableMachineId($object_id !== '' ? $object_id : $label, 'object', $used_object_ids);

    return [
      'object_id' => $object_id,
      'label' => $label !== '' ? $label : 'Object',
      'category' => $object['category'] ?? 'custom',
      'description' => $object['description'] ?? '',
      'passable' => $object['passable'] ?? TRUE,
      'interactable' => $object['interactable'] ?? FALSE,
    ];
  }

  /**
   * Finalize generated NPC contracts once the room id is known.
   */
  protected function finalizeGeneratedSettingContracts(array $setting, string $room_id): array {
    if (!is_array($setting['npcs'] ?? NULL)) {
      return $setting;
    }

    $used_content_ids = [];
    foreach ($setting['npcs'] as $index => $npc) {
      if (!is_array($npc)) {
        continue;
      }

      $base_content_id = isset($npc['content_id']) && is_scalar($npc['content_id'])
        ? trim((string) $npc['content_id'])
        : '';
      if ($base_content_id === '') {
        $base_content_id = isset($npc['name']) && is_scalar($npc['name'])
          ? trim((string) $npc['name'])
          : 'npc';
      }

      $equipment_labels = $this->normalizeGeneratedEquipmentLabels(
        is_array($npc['equipment'] ?? NULL) ? $npc['equipment'] : []
      );

      $setting['npcs'][$index]['content_id'] = $this->buildGeneratedNpcContentId($room_id, $base_content_id, $used_content_ids);
      $setting['npcs'][$index]['equipment'] = $equipment_labels;
      $setting['npcs'][$index]['inventory'] = $this->buildGeneratedNpcInventory($equipment_labels);
    }

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
    $entry_points = [];
    $exit_points = [];
    if ($hexes !== []) {
      $entry_points[] = [
        'q' => (int) ($hexes[0]['q'] ?? 0),
        'r' => (int) ($hexes[0]['r'] ?? 0),
      ];
      $last_hex = end($hexes);
      $exit_points[] = [
        'q' => (int) ($last_hex['q'] ?? 0),
        'r' => (int) ($last_hex['r'] ?? 0),
      ];
    }

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
      'entry_points' => $entry_points,
      'exit_points' => $exit_points,
      'exits' => [],
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

    usort($placeable, function (int $a, int $b) use ($hexes): int {
      $hexA = $hexes[$a] ?? ['q' => 0, 'r' => 0];
      $hexB = $hexes[$b] ?? ['q' => 0, 'r' => 0];
      $distanceCompare = (abs((int) $hexA['q']) + abs((int) $hexA['r'])) <=> (abs((int) $hexB['q']) + abs((int) $hexB['r']));
      if ($distanceCompare !== 0) {
        return $distanceCompare;
      }
      $rowCompare = ((int) $hexA['r']) <=> ((int) $hexB['r']);
      if ($rowCompare !== 0) {
        return $rowCompare;
      }
      return ((int) $hexA['q']) <=> ((int) $hexB['q']);
    });

    foreach ($objects as $i => $obj) {
      if (!isset($placeable[$i])) {
        break;
      }
      $hex_idx = $placeable[$i];
      $hexes[$hex_idx]['objects'][] = [
        'object_id' => (string) ($obj['object_id'] ?? ''),
        'label' => (string) ($obj['label'] ?? $obj['object_id'] ?? 'Object'),
        'category' => (string) ($obj['category'] ?? 'custom'),
        'orientation' => 'n',
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
        'schema_version' => '1.0.0',
        'entity_instance_id' => $this->generateUuid(),
        'entity_type' => 'npc',
        'entity_ref' => [
          'content_type' => 'npc',
          'content_id' => $npc['content_id'],
        ],
        'placement' => [
          'room_id' => $room_id,
          'hex' => $hex,
          'spawn_type' => 'permanent',
          'facing' => 0,
        ],
        'state' => [
          'active' => TRUE,
          'hit_points' => [
            'current' => (int) ($npc['stats']['currentHp'] ?? $npc['stats']['maxHp'] ?? 10),
            'max' => (int) ($npc['stats']['maxHp'] ?? 10),
          ],
          'inventory' => is_array($npc['inventory'] ?? NULL) ? array_values($npc['inventory']) : [],
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

  /**
   * Build a stable snake_case identifier and keep it unique within a collection.
   *
   * @param string $source
   *   Preferred source string, such as a name or existing ID.
   * @param string $fallback_prefix
   *   Prefix used when the source normalizes to an empty string.
   * @param array<string, bool> $used_ids
   *   Set of identifiers already used in the current collection.
   */
  protected function buildStableMachineId(string $source, string $fallback_prefix, array &$used_ids): string {
    $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $source), '_'));
    if ($base === '') {
      $base = $fallback_prefix;
    }

    $candidate = $base;
    $suffix = 2;
    while (isset($used_ids[$candidate])) {
      $candidate = $base . '_' . $suffix;
      $suffix++;
    }

    $used_ids[$candidate] = TRUE;
    return $candidate;
  }

  /**
   * Build a room-scoped canonical content id for generated NPCs.
   *
   * @param array<string, bool> $used_ids
   *   Set of ids already used within the generated room payload.
   */
  protected function buildGeneratedNpcContentId(string $room_id, string $source, array &$used_ids): string {
    $normalized_room = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '_', $room_id), '_'));
    $normalized_room = $normalized_room !== '' ? $normalized_room : 'room';

    return $this->buildStableMachineId(
      $normalized_room . '_' . $source,
      'npc_' . $normalized_room,
      $used_ids
    );
  }

  /**
   * Normalize generated equipment labels into trimmed strings.
   *
   * @return string[]
   *   Equipment labels, preserving duplicates for quantity counting.
   */
  protected function normalizeGeneratedEquipmentLabels(array $equipment): array {
    $labels = [];
    foreach ($equipment as $item) {
      if (!is_scalar($item)) {
        continue;
      }
      $label = trim((string) $item);
      if ($label === '') {
        continue;
      }
      $labels[] = $label;
    }
    return $labels;
  }

  /**
   * Build canonical inventory refs from generated equipment labels.
   *
   * @param string[] $equipment_labels
   *   Raw generated equipment labels.
   *
   * @return array<int, array{content_id:string, quantity:int}>
   *   Inventory refs keyed numerically for entity state payloads.
   */
  protected function buildGeneratedNpcInventory(array $equipment_labels): array {
    $inventory = [];

    foreach ($equipment_labels as $label) {
      $content_id = $this->buildGeneratedItemContentId($label);
      if (!isset($inventory[$content_id])) {
        $inventory[$content_id] = [
          'content_id' => $content_id,
          'quantity' => 0,
        ];
      }
      $inventory[$content_id]['quantity']++;
    }

    return array_values($inventory);
  }

  /**
   * Build a stable canonical item content id for generated equipment.
   *
   * Resolves to an existing curated catalog item (by normalized name) when
   * one already exists, instead of always minting a new `generated_item_*`
   * id. Historically this always prefixed a fresh id even when a real
   * catalog entry (e.g. `breastplate`) already existed for the same name,
   * producing duplicate rows (`breastplate` + `generated_item_breastplate`)
   * that both surfaced in catalog/pick lists such as the Room Editor.
   */
  protected function buildGeneratedItemContentId(string $label): string {
    $normalized = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '_', $label), '_'));
    $normalized = preg_replace('/_+/', '_', (string) $normalized);
    if ($normalized === '') {
      return 'generated_item';
    }

    $existing = $this->findCanonicalItemContentId($normalized, $label);
    if ($existing !== NULL) {
      return $existing;
    }

    return 'generated_item_' . $normalized;
  }

  /**
   * Looks up an already-registered, non-generated item by slug or name.
   *
   * @param string $normalized_slug
   *   Slugified label (e.g. "breastplate").
   * @param string $label
   *   Original display label (e.g. "Breastplate").
   *
   * @return string|null
   *   The existing content_id if a curated match is found, otherwise NULL.
   */
  protected function findCanonicalItemContentId(string $normalized_slug, string $label): ?string {
    // Guard for unit-test subclasses / partial constructions that never wire
    // up a database connection (typed property access would otherwise
    // fatal with "must not be accessed before initialization").
    if (!isset($this->database)) {
      return NULL;
    }

    $query = $this->database->select('dungeoncrawler_content_registry', 'r')
      ->fields('r', ['content_id', 'source_file'])
      ->condition('content_type', 'item')
      ->condition('source_file', 'ai_generated', '<>');
    $or = $query->orConditionGroup()
      ->condition('content_id', $normalized_slug)
      ->condition('name', $label);
    $query->condition($or);
    $query->range(0, 1);
    $content_id = $query->execute()->fetchField();
    return $content_id !== FALSE ? (string) $content_id : NULL;
  }

  /**
   * Persist generated equipment items into the library and campaign registries.
   *
   * @param string[] $equipment_labels
   *   Generated equipment labels from the NPC setting.
   */
  protected function registerGeneratedEquipmentItems(int $campaign_id, array $equipment_labels): void {
    $now = time();
    $registered = [];

    foreach ($equipment_labels as $label) {
      $content_id = $this->buildGeneratedItemContentId($label);
      if (isset($registered[$content_id])) {
        continue;
      }
      $registered[$content_id] = TRUE;

      $contract = $this->buildGeneratedItemContract($content_id, $label);
      $schema_data = json_encode($contract);
      $tags = json_encode(array_values(array_filter([
        'item',
        $contract['item_type'] ?? NULL,
        'ai_generated',
      ])));

      $this->database->merge('dungeoncrawler_content_registry')
        ->keys(['content_type' => 'item', 'content_id' => $content_id])
        ->fields([
          'content_type' => 'item',
          'content_id' => $content_id,
          'name' => $contract['name'],
          'level' => $contract['level'],
          'rarity' => $contract['rarity'],
          'tags' => $tags,
          'schema_data' => $schema_data,
          'source_file' => 'ai_generated',
          'version' => '1.0.0',
          'updated' => $now,
        ])
        ->expression('created', 'COALESCE(created, :created)', [':created' => $now])
        ->execute();

      $this->database->merge('dc_campaign_content_registry')
        ->keys(['campaign_id' => $campaign_id, 'content_type' => 'item', 'content_id' => $content_id])
        ->fields([
          'campaign_id' => $campaign_id,
          'content_type' => 'item',
          'content_id' => $content_id,
          'name' => $contract['name'],
          'level' => $contract['level'],
          'rarity' => $contract['rarity'],
          'tags' => $tags,
          'schema_data' => $schema_data,
          'source_content_id' => $content_id,
          'updated' => $now,
        ])
        ->expression('created', 'COALESCE(created, :created)', [':created' => $now])
        ->execute();
    }
  }

  /**
   * Build a minimal canonical item contract for generated equipment.
   */
  protected function buildGeneratedItemContract(string $content_id, string $label): array {
    $item_type = $this->inferGeneratedEquipmentItemType($label);

    return [
      'schema_version' => '1.0.0',
      'item_id' => $content_id,
      'name' => $label,
      'item_type' => $item_type,
      'level' => 0,
      'rarity' => 'common',
      'description' => 'Generated NPC equipment item.',
    ];
  }

  /**
   * Infer a coarse canonical item type from generated equipment text.
   */
  protected function inferGeneratedEquipmentItemType(string $label): string {
    $normalized = strtolower($label);

    foreach ([
      'shield' => 'shield',
      'armor' => 'armor',
      'mail' => 'armor',
      'plate' => 'armor',
      'helm' => 'armor',
      'dagger' => 'weapon',
      'sword' => 'weapon',
      'spear' => 'weapon',
      'axe' => 'weapon',
      'bow' => 'weapon',
      'staff' => 'weapon',
      'mace' => 'weapon',
      'hammer' => 'weapon',
      'crossbow' => 'weapon',
      'club' => 'weapon',
    ] as $needle => $item_type) {
      if (str_contains($normalized, $needle)) {
        return $item_type;
      }
    }

    return 'adventuring_gear';
  }

  /**
   * Builds a stable room-scoped instance id for generated NPC campaign rows.
   */
  protected function buildGeneratedNpcInstanceId(string $content_id): string {
    $normalized_content_id = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '_', $content_id), '_'));
    $candidate = 'npc_instance_' . ($normalized_content_id !== '' ? $normalized_content_id : 'npc');
    if (strlen($candidate) <= 100) {
      return $candidate;
    }

    $hash = substr(hash('sha256', $candidate), 0, 16);
    $prefix_length = 100 - strlen($hash) - 1;
    $prefix = rtrim(substr($candidate, 0, $prefix_length), '_');
    return $prefix . '_' . $hash;
  }

  // =========================================================================
  // Step 4-7: Wiring — connections, regions, object_definitions
  // =========================================================================

  /**
   * Create a bidirectional connection between two rooms.
   */
  protected function createRoomConnection(array &$dungeon_data, string $from_room_id, string $to_room_id): void {
    if ($from_room_id === '' || $to_room_id === '' || $from_room_id === $to_room_id) {
      return;
    }

    $this->logger->notice('Room connection entry: from_room_id=@from_room_id to_room_id=@to_room_id existing_connection_count=@existing_connection_count', [
      '@from_room_id' => $from_room_id,
      '@to_room_id' => $to_room_id,
      '@existing_connection_count' => count($dungeon_data['hex_map']['connections'] ?? []),
    ]);

    // Add to hex_map connections.
    if (!isset($dungeon_data['hex_map']['connections'])) {
      $dungeon_data['hex_map']['connections'] = [];
    }

    $connection_exists = FALSE;
    foreach ($dungeon_data['hex_map']['connections'] as $connection) {
      $from = (string) ($connection['from_room'] ?? '');
      $to = (string) ($connection['to_room'] ?? '');
      if (
        ($from === $from_room_id && $to === $to_room_id)
        || ($from === $to_room_id && $to === $from_room_id)
      ) {
        $connection_exists = TRUE;
        break;
      }
    }
    if (!$connection_exists) {
      $from_room = is_array($dungeon_data['rooms'] ?? NULL)
        ? array_values(array_filter($dungeon_data['rooms'], static fn($room): bool => is_array($room) && (string) ($room['room_id'] ?? '') === $from_room_id))[0] ?? NULL
        : NULL;
      $to_room = is_array($dungeon_data['rooms'] ?? NULL)
        ? array_values(array_filter($dungeon_data['rooms'], static fn($room): bool => is_array($room) && (string) ($room['room_id'] ?? '') === $to_room_id))[0] ?? NULL
        : NULL;
      $from_hex = is_array($from_room) ? $this->resolveConnectionAnchorHex($from_room, TRUE) : ['q' => 0, 'r' => 0];
      $to_hex = is_array($to_room) ? $this->resolveConnectionAnchorHex($to_room, FALSE) : ['q' => 0, 'r' => 0];

      $dungeon_data['hex_map']['connections'][] = [
        'from_room' => $from_room_id,
        'from_room_id' => $from_room_id,
        'to_room' => $to_room_id,
        'to_room_id' => $to_room_id,
        'from_hex' => $from_hex,
        'to_hex' => $to_hex,
        'from' => [
          'room_id' => $from_room_id,
          'q' => (int) $from_hex['q'],
          'r' => (int) $from_hex['r'],
        ],
        'to' => [
          'room_id' => $to_room_id,
          'q' => (int) $to_hex['q'],
          'r' => (int) $to_hex['r'],
        ],
        'type' => 'passage',
        'bidirectional' => TRUE,
      ];
    }

    // Also set room.connections on both rooms.
    foreach ($dungeon_data['rooms'] as &$room) {
      if (($room['room_id'] ?? '') === $from_room_id) {
        if (!isset($room['connections'])) {
          $room['connections'] = [];
        }

        if (!$this->roomHasConnection($room, $to_room_id)) {
          $room['connections'][] = [
            'target_room_id' => $to_room_id,
            'type' => 'passage',
          ];
        }
      }
      if (($room['room_id'] ?? '') === $to_room_id) {
        if (!isset($room['connections'])) {
          $room['connections'] = [];
        }
        if (!$this->roomHasConnection($room, $from_room_id)) {
          $room['connections'][] = [
            'target_room_id' => $from_room_id,
            'type' => 'passage',
          ];
        }
      }
    }
    unset($room);
    $this->logger->notice('Room connection exit: from_room_id=@from_room_id to_room_id=@to_room_id connection_exists=@connection_exists final_connection_count=@final_connection_count', [
      '@from_room_id' => $from_room_id,
      '@to_room_id' => $to_room_id,
      '@connection_exists' => $connection_exists ? 'yes' : 'no',
      '@final_connection_count' => count($dungeon_data['hex_map']['connections'] ?? []),
    ]);
  }

  /**
   * Resolve deterministic anchor hex for a room-connection endpoint.
   *
   * @param array<string,mixed> $room
   * @param bool $prefer_exit
   *   TRUE for source endpoint, FALSE for destination endpoint.
   *
   * @return array{q:int,r:int}
   *   Anchor coordinates.
   */
  protected function resolveConnectionAnchorHex(array $room, bool $prefer_exit): array {
    $valid_hexes = [];
    foreach ((array) ($room['hexes'] ?? []) as $hex) {
      if (!is_array($hex) || !isset($hex['q'], $hex['r'])) {
        continue;
      }
      $q = (int) $hex['q'];
      $r = (int) $hex['r'];
      $valid_hexes[$q . ':' . $r] = [
        'q' => $q,
        'r' => $r,
        'is_entry' => !empty($hex['is_entry']) || !empty($hex['entry']),
      ];
    }

    if ($valid_hexes === []) {
      return ['q' => 0, 'r' => 0];
    }

    $primary_points = is_array($room[$prefer_exit ? 'exit_points' : 'entry_points'] ?? NULL)
      ? $room[$prefer_exit ? 'exit_points' : 'entry_points']
      : [];
    $secondary_points = is_array($room[$prefer_exit ? 'entry_points' : 'exit_points'] ?? NULL)
      ? $room[$prefer_exit ? 'entry_points' : 'exit_points']
      : [];
    foreach ([$primary_points, $secondary_points] as $points) {
      foreach ($points as $point) {
        if (!is_array($point) || !isset($point['q'], $point['r'])) {
          continue;
        }
        $point_key = (int) $point['q'] . ':' . (int) $point['r'];
        if (isset($valid_hexes[$point_key])) {
          return ['q' => (int) $point['q'], 'r' => (int) $point['r']];
        }
      }
    }

    if (!$prefer_exit) {
      foreach ($valid_hexes as $hex_meta) {
        if (!empty($hex_meta['is_entry'])) {
          return ['q' => (int) $hex_meta['q'], 'r' => (int) $hex_meta['r']];
        }
      }
    }

    $fallback = reset($valid_hexes);
    return [
      'q' => (int) ($fallback['q'] ?? 0),
      'r' => (int) ($fallback['r'] ?? 0),
    ];
  }

  /**
   * Normalize destination and room labels for stable matching.
   */
  protected function normalizeLocationLabel(string $label): string {
    $label = strtolower(trim($label));
    $label = str_replace(['’', '`'], "'", $label);
    $label = preg_replace("/'s\\b/u", 's', $label);
    $label = preg_replace('/\b(the|a|an)\b/u', ' ', $label);
    $label = preg_replace('/[^a-z0-9]+/u', ' ', $label);
    return trim(preg_replace('/\s+/u', ' ', $label) ?? '');
  }

  /**
   * Check whether a room already has a connection to a target room.
   */
  protected function roomHasConnection(array $room, string $target_room_id): bool {
    foreach (($room['connections'] ?? []) as $connection) {
      if ((string) ($connection['target_room_id'] ?? '') === $target_room_id) {
        return TRUE;
      }
    }
    return FALSE;
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
      'anchor_q' => is_array($room['placement'] ?? NULL) ? (int) ($room['placement']['anchor_q'] ?? 0) : 0,
      'anchor_r' => is_array($room['placement'] ?? NULL) ? (int) ($room['placement']['anchor_r'] ?? 0) : 0,
    ];
  }

  /**
   * Place one generated room so it keeps minimum spacing from existing rooms.
   *
   * @return array{room:array<string,mixed>,offset_q:int,offset_r:int}
   *   Shifted room payload and applied axial offset.
   */
  protected function placeRoomWithMinimumGap(array $room, array $existing_rooms, int $minimum_gap_hexes): array {
    $new_bounds = $this->calculateRoomHexBounds($room);
    $spatial_existing_rooms = [];
    foreach ($existing_rooms as $existing_room) {
      if (!is_array($existing_room)) {
        continue;
      }
      $hexes = is_array($existing_room['hexes'] ?? NULL) ? $existing_room['hexes'] : [];
      if ($hexes === []) {
        continue;
      }
      $spatial_existing_rooms[] = $existing_room;
    }

    if ($spatial_existing_rooms === []) {
      $room['placement'] = [
        'anchor_q' => $new_bounds['min_q'],
        'anchor_r' => $new_bounds['min_r'],
        'offset_q' => 0,
        'offset_r' => 0,
        'minimum_gap_hexes' => $minimum_gap_hexes,
      ];
      return [
        'room' => $room,
        'offset_q' => 0,
        'offset_r' => 0,
      ];
    }

    $max_existing_q = NULL;
    $min_existing_r = NULL;
    foreach ($spatial_existing_rooms as $existing_room) {
      if (!is_array($existing_room)) {
        continue;
      }
      $existing_bounds = $this->calculateRoomHexBounds($existing_room);
      $max_existing_q = $max_existing_q === NULL ? $existing_bounds['max_q'] : max($max_existing_q, $existing_bounds['max_q']);
      $min_existing_r = $min_existing_r === NULL ? $existing_bounds['min_r'] : min($min_existing_r, $existing_bounds['min_r']);
    }
    if ($max_existing_q === NULL || $min_existing_r === NULL) {
      throw new \RuntimeException('Unable to derive existing room bounds for minimum-gap placement.');
    }

    $target_min_q = (int) $max_existing_q + $minimum_gap_hexes + 1;
    $target_min_r = (int) $min_existing_r;
    $offset_q = $target_min_q - $new_bounds['min_q'];
    $offset_r = $target_min_r - $new_bounds['min_r'];
    $shifted_room = $this->offsetRoomHexCoordinates($room, $offset_q, $offset_r);

    $anchor_hex = (is_array($shifted_room['hexes'] ?? NULL) && is_array($shifted_room['hexes'][0] ?? NULL))
      ? $shifted_room['hexes'][0]
      : ['q' => $target_min_q, 'r' => $target_min_r];
    $shifted_room['placement'] = [
      'anchor_q' => (int) ($anchor_hex['q'] ?? $target_min_q),
      'anchor_r' => (int) ($anchor_hex['r'] ?? $target_min_r),
      'offset_q' => $offset_q,
      'offset_r' => $offset_r,
      'minimum_gap_hexes' => $minimum_gap_hexes,
    ];

    return [
      'room' => $shifted_room,
      'offset_q' => $offset_q,
      'offset_r' => $offset_r,
    ];
  }

  /**
   * Calculate min/max q/r bounds from room hexes.
   *
   * @return array{min_q:int,max_q:int,min_r:int,max_r:int}
   *   Room axial coordinate bounds.
   */
  protected function calculateRoomHexBounds(array $room): array {
    $hexes = is_array($room['hexes'] ?? NULL) ? $room['hexes'] : [];
    if ($hexes === []) {
      throw new \RuntimeException(sprintf('Room %s has no hexes for spacing calculations.', (string) ($room['room_id'] ?? 'unknown')));
    }

    $min_q = NULL;
    $max_q = NULL;
    $min_r = NULL;
    $max_r = NULL;
    foreach ($hexes as $hex_index => $hex) {
      if (!is_array($hex) || !is_numeric($hex['q'] ?? NULL) || !is_numeric($hex['r'] ?? NULL)) {
        throw new \RuntimeException(sprintf(
          'Room %s hexes[%d] must include numeric q/r coordinates.',
          (string) ($room['room_id'] ?? 'unknown'),
          $hex_index
        ));
      }
      $q = (int) $hex['q'];
      $r = (int) $hex['r'];
      $min_q = $min_q === NULL ? $q : min($min_q, $q);
      $max_q = $max_q === NULL ? $q : max($max_q, $q);
      $min_r = $min_r === NULL ? $r : min($min_r, $r);
      $max_r = $max_r === NULL ? $r : max($max_r, $r);
    }

    return [
      'min_q' => (int) $min_q,
      'max_q' => (int) $max_q,
      'min_r' => (int) $min_r,
      'max_r' => (int) $max_r,
    ];
  }

  /**
   * Shift room hexes and per-room coordinate payloads by one axial offset.
   */
  protected function offsetRoomHexCoordinates(array $room, int $offset_q, int $offset_r): array {
    $shifted_hexes = [];
    foreach ((array) ($room['hexes'] ?? []) as $hex) {
      if (!is_array($hex) || !is_numeric($hex['q'] ?? NULL) || !is_numeric($hex['r'] ?? NULL)) {
        throw new \RuntimeException(sprintf('Room %s contains non-numeric room hex coordinates.', (string) ($room['room_id'] ?? 'unknown')));
      }
      $hex['q'] = (int) $hex['q'] + $offset_q;
      $hex['r'] = (int) $hex['r'] + $offset_r;
      $shifted_hexes[] = $hex;
    }
    $room['hexes'] = $shifted_hexes;

    foreach (['entry_points', 'exit_points'] as $point_key) {
      if (!is_array($room[$point_key] ?? NULL)) {
        continue;
      }
      $shifted_points = [];
      foreach ($room[$point_key] as $point) {
        if (!is_array($point)) {
          $shifted_points[] = $point;
          continue;
        }
        if (is_numeric($point['q'] ?? NULL) && is_numeric($point['r'] ?? NULL)) {
          $point['q'] = (int) $point['q'] + $offset_q;
          $point['r'] = (int) $point['r'] + $offset_r;
        }
        if (is_array($point['hex'] ?? NULL) && is_numeric($point['hex']['q'] ?? NULL) && is_numeric($point['hex']['r'] ?? NULL)) {
          $point['hex']['q'] = (int) $point['hex']['q'] + $offset_q;
          $point['hex']['r'] = (int) $point['hex']['r'] + $offset_r;
        }
        $shifted_points[] = $point;
      }
      $room[$point_key] = $shifted_points;
    }

    return $room;
  }

  /**
   * Shift generated entity placement hexes by one axial offset.
   */
  protected function offsetGeneratedEntitiesByHex(array $entities, int $offset_q, int $offset_r): array {
    if ($offset_q === 0 && $offset_r === 0) {
      return $entities;
    }

    $shifted = [];
    foreach ($entities as $entity) {
      if (!is_array($entity)) {
        $shifted[] = $entity;
        continue;
      }
      if (
        is_array($entity['placement'] ?? NULL)
        && is_array($entity['placement']['hex'] ?? NULL)
        && is_numeric($entity['placement']['hex']['q'] ?? NULL)
        && is_numeric($entity['placement']['hex']['r'] ?? NULL)
      ) {
        $entity['placement']['hex']['q'] = (int) $entity['placement']['hex']['q'] + $offset_q;
        $entity['placement']['hex']['r'] = (int) $entity['placement']['hex']['r'] + $offset_r;
      }
      $shifted[] = $entity;
    }

    return $shifted;
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
