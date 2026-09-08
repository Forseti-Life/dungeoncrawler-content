<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Support\H3SpatialHelper;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalRoomProjectionService;
use Drupal\dungeoncrawler_content\Service\Generation\RuntimeCanonicalRoomService;
use Drupal\dungeoncrawler_content\Service\Generation\RuntimeCanonicalContentResolver;
use Drupal\dungeoncrawler_content\Service\Generation\RuntimeGenerationException;
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
 *
 * Legacy generation entrypoint frozen by ADR-GEN-06: no new callers; use
 * CanonicalGenerationService.
 * Runtime generation failures hard-fail with runtime_generation_failed; no generic
 * pool, cached fallback, or legacy generator on failure.
 */
class MapGeneratorService {

  protected Connection $database;
  protected LoggerInterface $logger;
  protected RoomStateService $roomStateService;
  protected StateValidationService $stateValidationService;
  protected ?NavigationService $navigationService;
  protected ?RuntimeCanonicalContentResolver $runtimeCanonicalContentResolver;
  protected ?CanonicalRoomProjectionService $canonicalRoomProjection;
  protected ?RuntimeCanonicalRoomService $runtimeCanonicalRoom;
  protected ?ConfigFactoryInterface $configFactory;
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
    RoomStateService $room_state_service,
    StateValidationService $state_validation_service,
    ?NavigationService $navigation_service = NULL,
    ?RuntimeCanonicalContentResolver $runtime_canonical_content_resolver = NULL,
    ?CanonicalRoomProjectionService $canonical_room_projection = NULL,
    ?ConfigFactoryInterface $config_factory = NULL,
    ?RuntimeCanonicalRoomService $runtime_canonical_room = NULL
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler_map_gen');
    $this->roomStateService = $room_state_service;
    $this->stateValidationService = $state_validation_service;
    $this->navigationService = $navigation_service;
    $this->runtimeCanonicalContentResolver = $runtime_canonical_content_resolver;
    $this->canonicalRoomProjection = $canonical_room_projection;
    $this->runtimeCanonicalRoom = $runtime_canonical_room;
    $this->configFactory = $config_factory;
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
   *
   * Legacy generation entrypoint frozen by ADR-GEN-06: no new callers; use
   * CanonicalGenerationService.
   * Runtime generation failures hard-fail with runtime_generation_failed; no generic
   * pool, cached fallback, or legacy generator on failure.
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

    if (!$this->canonicalRuntimeGenerationR6Enabled()) {
      throw new RuntimeGenerationException('runtime_generation_failed', [[
        'code' => 'runtime_generation_failed',
        'pointer' => '/canonical_runtime_generation/r6',
        'message' => 'R6 canonical runtime generation is disabled; no legacy fallback is available.',
        'severity' => 'error',
      ]], 503);
    }

    return $this->generateSettingFromCanonicalRoom(
      $campaign_id,
      (string) $dungeon_id,
      $destination,
      $origin_room_id,
      $dungeon_data,
      (int) $party_level,
      $narrative_context
    );
  }

  /**
   * Resolve navigation expansion through published canonical room projection.
   *
   * R4 default path: select deterministic canonical content or hard-fail with
   * runtime_selection_failed. It never falls through to the legacy setting
   * template or AI setting generator after a selection miss.
   */
  protected function generateSettingFromCanonicalRoom(
    int $campaign_id,
    string $dungeon_id,
    string $destination,
    string $origin_room_id,
    array $dungeon_data,
    int $party_level,
    array $narrative_context
  ): array {
    if (!$this->runtimeCanonicalRoom || !$this->canonicalRoomProjection) {
      throw new RuntimeGenerationException('runtime_selection_failed', [[
        'code' => 'runtime_selection_failed',
        'pointer' => '/services',
        'message' => 'Runtime canonical room/projection services are required for R4.',
        'severity' => 'error',
      ]], 500);
    }

    $setting_type = $this->inferSettingType($destination) ?: 'default';
    $room_index_seed = count(is_array($dungeon_data['rooms'] ?? NULL) ? $dungeon_data['rooms'] : []);
    $runtime_room = $this->runtimeCanonicalRoom->generateRoom([
      'campaign_id' => $campaign_id,
      'dungeon_id' => $dungeon_id,
      'level_id' => (string) ($dungeon_data['level_id'] ?? $dungeon_data['current_level_id'] ?? 'navigation'),
      'room_index' => $room_index_seed,
      'runtime_room_id' => $this->buildNavigationRuntimeRoomId($dungeon_id, $destination, $room_index_seed),
      'theme' => (string) ($narrative_context['campaign_theme'] ?? $dungeon_data['theme'] ?? 'dungeon'),
      'room_type' => $this->settingTypeToRoomType($setting_type),
      'terrain_type' => (string) ((self::TERRAIN_MAP[$setting_type] ?? self::TERRAIN_MAP['default'])['type'] ?? 'stone_floor'),
      'room_size' => (string) ($narrative_context['room_size'] ?? 'medium'),
      'party_level' => $party_level,
      'prompt' => trim((string) ($narrative_context['prompt'] ?? $narrative_context['destination_description'] ?? $destination)),
      'seed' => $this->runtimeSelectionSeed($destination, $party_level, $narrative_context),
      'required_tags' => array_values(array_unique(array_filter(array_map('strval', (array) ($narrative_context['required_tags'] ?? []))))),
      'defer_room_persistence' => TRUE,
      'canonical_generation_wait' => !empty($narrative_context['canonical_generation_wait']),
      'requested_by_uid' => (int) ($narrative_context['requested_by_uid'] ?? 0),
      'origin_room_id' => $origin_room_id,
    ]);
    $placement_result = $this->placeRoomWithMinimumGap(
      $runtime_room,
      is_array($dungeon_data['rooms'] ?? NULL) ? $dungeon_data['rooms'] : [],
      self::MIN_ROOM_GAP_HEXES
    );
    $room = $this->ensureRoomHexH3Indexes($dungeon_id, $placement_result['room']);
    $room['_layout_data'] = $this->buildCanonicalCampaignRoomLayoutPayload($room);
    $room['_layout_data']['metadata'] = is_array($room['metadata'] ?? NULL) ? $room['metadata'] : [];
    $room['_contents_data'] = is_array($runtime_room['_contents_data'] ?? NULL) ? $runtime_room['_contents_data'] : [];
    $room['_environment_tags'] = is_array($runtime_room['_environment_tags'] ?? NULL) ? $runtime_room['_environment_tags'] : [];

    $setting = [
      'setting_type' => $this->inferSettingType($destination) ?: 'default',
      'size' => (string) ($room['size_category'] ?? 'medium'),
      'lighting' => is_string($room['lighting'] ?? NULL) ? $room['lighting'] : (string) ($room['lighting']['level'] ?? 'normal_light'),
      'theme_tags' => (array) ($room['metadata']['campaign_source']['selection']['criteria']['tags'] ?? []),
      'atmosphere' => '',
      'npcs' => [],
      'objects' => [],
      'source_room_id' => (string) ($room['source_room_id'] ?? ''),
      'source_room_version_id' => (string) ($room['source_room_version_id'] ?? ''),
    ];

    $entities = [];
    $room_index = -1;
    $this->executeNavigationPersistenceTransaction(function () use (
      &$dungeon_data,
      &$room_index,
      $campaign_id,
      $dungeon_id,
      $origin_room_id,
      $room,
      $setting
    ): void {
      $dungeon_data['rooms'][] = $room;
      $room_index = array_key_last($dungeon_data['rooms']);
      $this->createRoomConnection($dungeon_data, $origin_room_id, (string) $room['room_id']);
      $this->syncCampaignConnectionRows($campaign_id, $dungeon_id, $origin_room_id, (string) $room['room_id']);
      $this->assertNavigationConnectionParity(
        $campaign_id,
        $dungeon_id,
        $origin_room_id,
        (string) $room['room_id'],
        $dungeon_data
      );
      $this->addRegionToHexMap($dungeon_data, $room);
      $this->database->update('dc_campaign_dungeons')
        ->fields([
          'dungeon_data' => json_encode($dungeon_data),
          'updated' => time(),
        ])
        ->condition('dungeon_id', $dungeon_id)
        ->condition('campaign_id', $campaign_id)
        ->execute();
      $this->recordCampaignSettingInstance(
        $campaign_id,
        (string) $room['room_id'],
        NULL,
        (string) ($room['name'] ?? $room['room_id']),
        (string) $setting['setting_type'],
        $room_index,
        $setting
      );
      $this->canonicalRoomProjection->persistProjectedRoom($campaign_id, $room);
      $this->roomStateService->setState($campaign_id, (string) $room['room_id'], $dungeon_id, [
        'roomId' => (string) $room['room_id'],
        'dungeonId' => $dungeon_id,
        'explored' => TRUE,
        'visibility' => 'visible',
        'isCleared' => FALSE,
      ], NULL);
    });

    return [
      'room' => $dungeon_data['rooms'][$room_index] ?? $room,
      'room_index' => $room_index,
      'entities' => $entities,
      'dungeon_data' => $dungeon_data,
      'source' => 'canonical_room',
      'template_id' => NULL,
      'room_version_id' => $room['source_room_version_id'] ?? $room['room_version_id'] ?? NULL,
    ];
  }

  protected function canonicalRuntimeGenerationR6Enabled(): bool {
    if (!$this->configFactory) {
      return FALSE;
    }
    return $this->configFactory->get('dungeoncrawler_content.settings')->get('canonical_runtime_generation.r6') !== FALSE;
  }

  protected function runtimeSelectionSeed(string $destination, int $party_level, array $context): int {
    if (isset($context['seed']) && is_numeric($context['seed'])) {
      return max(0, min(2147483647, (int) $context['seed']));
    }
    return (int) (sprintf('%u', crc32(strtolower($destination) . ':' . $party_level)) % 2147483647);
  }

  protected function buildNavigationRuntimeRoomId(string $dungeon_id, string $destination, int $room_index): string {
    $slug = $this->normalizeLocationLabel($destination);
    if ($slug === '') {
      $slug = 'location';
    }
    $slug = preg_replace('/[^a-z0-9_-]+/', '_', $slug) ?: 'location';
    $slug = trim($slug, '_-') !== '' ? trim($slug, '_-') : 'location';
    $dungeon = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $dungeon_id) ?: 'dungeon';
    $hash = substr(hash('sha256', $dungeon_id . ':' . $destination . ':' . $room_index . ':' . microtime(TRUE)), 0, 8);
    return substr(sprintf('room_%s_%s_%d_%s', trim($dungeon, '_-'), $slug, $room_index, $hash), 0, 100);
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
