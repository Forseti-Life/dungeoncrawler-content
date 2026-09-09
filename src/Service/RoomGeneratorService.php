<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\Generation\RuntimeCanonicalRoomService;
use Drupal\dungeoncrawler_content\Service\Generation\RuntimeGenerationException;
use Psr\Log\LoggerInterface;

/**
 * Generates individual dungeon rooms with terrain, entities, and encounters.
 *
 * Responsible for:
 * - Generating room hexes with terrain and elevation
 * - Creating AI-driven room descriptions
 * - Placing entities (creatures, items, traps, hazards)
 * - Generating balanced encounters
 * - Validating against room.schema.json
 * - Persisting rooms to database
 *
 * Validation pair: SchemaLoader::validate('room', ...) room contract checks.
 *
 * Runtime generator scheduled for reconciliation into the canonical generation path (Board decision 2026-09-08, item 20260908-dc-editor-generation-tools). No new callers; use CanonicalGenerationService.
 * Runtime generation failures hard-fail with runtime_generation_failed; no generic
 * pool, cached fallback, or legacy generator on failure.
 *
 * @see /docs/dungeoncrawler/ROOM_DUNGEON_GENERATOR_ARCHITECTURE.md
 */
class RoomGeneratorService {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The schema loader service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\SchemaLoader
   */
  protected SchemaLoader $schemaLoader;

  /**
   * The entity placer service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\EntityPlacerService
   */
  protected EntityPlacerService $entityPlacer;

  /**
   * The encounter generator service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\EncounterGeneratorService
   */
  protected EncounterGeneratorService $encounterGenerator;

  /**
   * The hex utility service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\HexUtilityService
   */
  protected HexUtilityService $hexUtility;

  /**
   * The terrain generator service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\TerrainGeneratorService
   */
  protected TerrainGeneratorService $terrainGenerator;

  /**
   * Number generation service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\NumberGenerationService
   */
  protected NumberGenerationService $numberGeneration;

  /**
   * Room library service for caching/reusing generated rooms.
   *
   * @var \Drupal\dungeoncrawler_content\Service\RoomLibraryService
   */
  protected RoomLibraryService $roomLibrary;

  /**
   * Room view image cache service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\RoomViewImageService
   */
  protected RoomViewImageService $roomViewImageService;
  protected ?ConfigFactoryInterface $configFactory;
  protected ?RuntimeCanonicalRoomService $runtimeCanonicalRoom;

  /**
   * Optional AI API service for narrative generation.
   *
   * @var \Drupal\ai_conversation\Service\AIApiService|null
   */
  protected $aiService = NULL;

  /**
   * Constructs a RoomGeneratorService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param \Drupal\dungeoncrawler_content\Service\SchemaLoader $schema_loader
   *   The schema loader service.
   * @param \Drupal\dungeoncrawler_content\Service\EntityPlacerService $entity_placer
   *   The entity placer service.
   * @param \Drupal\dungeoncrawler_content\Service\EncounterGeneratorService $encounter_generator
   *   The encounter generator service.
   * @param \Drupal\dungeoncrawler_content\Service\HexUtilityService $hex_utility
   *   The hex utility service.
   * @param \Drupal\dungeoncrawler_content\Service\TerrainGeneratorService $terrain_generator
   *   The terrain generator service.
   */
  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    SchemaLoader $schema_loader,
    EntityPlacerService $entity_placer,
    EncounterGeneratorService $encounter_generator,
    HexUtilityService $hex_utility,
    TerrainGeneratorService $terrain_generator,
    NumberGenerationService $number_generation,
    RoomLibraryService $room_library,
    RoomViewImageService $room_view_image_service,
    ?ConfigFactoryInterface $config_factory = NULL,
    ?RuntimeCanonicalRoomService $runtime_canonical_room = NULL
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler');
    $this->schemaLoader = $schema_loader;
    $this->entityPlacer = $entity_placer;
    $this->encounterGenerator = $encounter_generator;
    $this->hexUtility = $hex_utility;
    $this->terrainGenerator = $terrain_generator;
    $this->numberGeneration = $number_generation;
    $this->roomLibrary = $room_library;
    $this->roomViewImageService = $room_view_image_service;
    $this->configFactory = $config_factory;
    $this->runtimeCanonicalRoom = $runtime_canonical_room;

    // Try to inject AI service if available
    try {
      if (\Drupal::hasService('ai_conversation.ai_api_service')) {
        $this->aiService = \Drupal::service('ai_conversation.ai_api_service');
        $this->logger->info('AI service available for room description generation');
      }
    }
    catch (\Exception $e) {
      $this->logger->info('AI service not available, using template-based descriptions');
    }
  }

  /**
   * Generate a single room.
   *
   * Workflow:
   * 1. Check if room already exists (return cached)
   * 2. Generate hexes (terrain, elevation, obstacles)
   * 3. Generate description (AI-driven narrative)
   * 4. Generate lighting effects
   * 5. Place entities (creatures, items, hazards)
   * 6. Validate against room.schema.json
   * 7. Persist to database
   *
   * @param array $context
   *   Generation context with keys:
   *   - campaign_id: int - Campaign ID
   *   - dungeon_id: int - Dungeon ID
   *   - level_id: int - Level ID
   *   - depth: int - Dungeon depth (1-based), drives difficulty
   *   - party_level: int - Average party level (1-20)
   *   - room_index: int - Index of this room in the level
   *   - theme: string - Dungeon theme (e.g., 'goblin_warrens')
   *   - room_size: string - 'small', 'medium', or 'large'
   *   - room_type: string - e.g., 'chamber', 'corridor', 'library'
   *   - terrain_type: string - e.g., 'stone', 'sand', 'crystal'
   *   - ai_service: object - Optional AI service for description generation
   *
   * @return array
   *   Complete room structure matching room.schema.json:
   *   - room_id: string (stable room identifier)
   *   - name: string
   *   - description: string
   *   - hexes: array of hex objects with terrain
   *   - entities: array of entity_instance objects
   *   - terrain: object with primary_type, secondary_features
   *   - lighting: object with illumination, light_sources
   *   - state: object with explored, cleared flags
   *
   * @throws \Drupal\dungeoncrawler_content\Exception\GenerationException
   *   If generation fails or schema validation fails
   *
   * Legacy generation entrypoint frozen by ADR-GEN-06: no new callers; use
   * CanonicalGenerationService.
   * Runtime generation failures hard-fail with runtime_generation_failed; no generic
   * pool, cached fallback, or legacy generator on failure.
   *
   * @see /docs/dungeoncrawler/ROOM_DUNGEON_GENERATOR_ARCHITECTURE.md
   */
  public function generateRoom(array $context): array {
    $this->logger->warning('Deprecated RoomGeneratorService::generateRoom() invoked; delegating to the canonical runtime generation path. Legacy procedural room generation authority was removed under ADR-GEN-06 (R8): campaign=@campaign level=@level.', [
      '@campaign' => $context['campaign_id'] ?? NULL,
      '@level' => $context['level_id'] ?? NULL,
    ]);

    return $this->generateRoomViaCanonicalRuntime($context);
  }

  /**
   * Delegates room realization to the single canonical runtime path.
   *
   * There is no legacy fallback: when the canonical runtime service is
   * unavailable the request hard-fails with runtime_selection_failed.
   */
  protected function generateRoomViaCanonicalRuntime(array $context): array {
    if (!$this->runtimeCanonicalRoom) {
      throw new RuntimeGenerationException('runtime_selection_failed', [[
        'code' => 'runtime_selection_failed',
        'pointer' => '/services',
        'message' => 'Runtime canonical room service is required; legacy room generation is disabled.',
        'severity' => 'error',
      ]], 500);
    }

    return $this->runtimeCanonicalRoom->generateRoom($context);
  }


  /**
   * Builds the stable room identifier used by room generation and caching.
   *
   * @param array $context
   *   Generation context with dungeon_id, level_id, and room_index.
   */
  protected function buildRoomId(array $context): string {
    return sprintf(
      'room_%s_%s_%s',
      $this->normalizeRoomIdPart($context['dungeon_id'] ?? 0),
      $this->normalizeRoomIdPart($context['level_id'] ?? 0),
      $this->normalizeRoomIdPart($context['room_index'] ?? 0)
    );
  }

  /**
   * Normalizes a room-id segment into a stable URL-safe identifier token.
   *
   * @param mixed $value
   *   Segment source value.
   */
  protected function normalizeRoomIdPart($value): string {
    $normalized = preg_replace('/[^A-Za-z0-9_-]+/', '_', trim((string) $value));
    $normalized = trim((string) $normalized, '_');
    return $normalized !== '' ? $normalized : '0';
  }

}
