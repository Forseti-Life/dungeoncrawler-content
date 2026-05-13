<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\DungeonGeneratorService;
use Drupal\dungeoncrawler_content\Service\MapGeneratorService;
use Drupal\dungeoncrawler_content\Service\NarrationEngine;
use Drupal\dungeoncrawler_content\Service\QuestGeneratorService;
use Drupal\dungeoncrawler_content\Service\RoomGeneratorService;
use Drupal\dungeoncrawler_content\Service\RoomStateService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * GM-only controller for explicit location generation requests.
 */
class LocationGenerationController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The map generation orchestrator.
   *
   * @var \Drupal\dungeoncrawler_content\Service\MapGeneratorService
   */
  protected MapGeneratorService $mapGenerator;

  /**
   * Procedural room generator.
   *
   * @var \Drupal\dungeoncrawler_content\Service\RoomGeneratorService
   */
  protected RoomGeneratorService $roomGenerator;

  /**
   * Location quest generator.
   *
   * @var \Drupal\dungeoncrawler_content\Service\QuestGeneratorService
   */
  protected QuestGeneratorService $questGenerator;

  /**
   * Canonical room-state service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\RoomStateService
   */
  protected RoomStateService $roomStateService;

  /**
   * Complete dungeon generator for remote-site branching.
   *
   * @var \Drupal\dungeoncrawler_content\Service\DungeonGeneratorService
   */
  protected DungeonGeneratorService $dungeonGenerator;
  protected NarrationEngine $narrationEngine;

  /**
   * Constructs a LocationGenerationController.
   */
  public function __construct(
    Connection $database,
    MapGeneratorService $map_generator,
    RoomGeneratorService $room_generator,
    QuestGeneratorService $quest_generator,
    RoomStateService $room_state_service,
    DungeonGeneratorService $dungeon_generator,
    NarrationEngine $narration_engine
  ) {
    $this->database = $database;
    $this->mapGenerator = $map_generator;
    $this->roomGenerator = $room_generator;
    $this->questGenerator = $quest_generator;
    $this->roomStateService = $room_state_service;
    $this->dungeonGenerator = $dungeon_generator;
    $this->narrationEngine = $narration_engine;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('dungeoncrawler_content.map_generator'),
      $container->get('dungeoncrawler_content.room_generator'),
      $container->get('dungeoncrawler_content.quest_generator'),
      $container->get('dungeoncrawler_content.room_state_service'),
      $container->get('dungeoncrawler_content.dungeon_generator'),
      $container->get('dungeoncrawler_content.narration_engine')
    );
  }

  /**
   * POST /api/campaign/{campaign_id}/gm/locations/request
   *
   * Request a new location from the current room. This is GM-only.
   */
  public function requestLocation(Request $request, int $campaign_id): JsonResponse {
    $data = json_decode($request->getContent(), TRUE);
    if (!is_array($data)) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Invalid JSON body',
      ], JsonResponse::HTTP_BAD_REQUEST);
    }

    $destination = trim((string) ($data['destination'] ?? ''));
    $gm_private = $this->resolveGmPrivateRequestContext($data);
    if ($destination === '') {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'destination is required',
      ], JsonResponse::HTTP_BAD_REQUEST);
    }

    $dungeon_record = $this->loadLatestDungeonRecord($campaign_id);
    if (!$dungeon_record) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'No active dungeon found for campaign',
      ], JsonResponse::HTTP_NOT_FOUND);
    }

    $dungeon_data = json_decode((string) $dungeon_record['dungeon_data'], TRUE);
    if (!is_array($dungeon_data)) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Stored dungeon data is invalid',
      ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
    }

    $origin_room_id = $this->resolveOriginRoomId($dungeon_data, (string) ($data['origin_room_id'] ?? ''));
    if ($origin_room_id === '') {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Unable to resolve an origin room for generation',
      ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }

    if (!$this->roomExists($dungeon_data, $origin_room_id)) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'origin_room_id does not exist in the active dungeon payload',
      ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }

    $party_level = max(1, min(20, (int) ($data['party_level']
      ?? $dungeon_data['generation_rules']['party_level_target']
      ?? 1)));

    $narrative_context = [
      'gm_narrative' => trim((string) ($data['gm_narrative'] ?? ('GM requested location: ' . $destination))),
      'campaign_theme' => (string) ($dungeon_data['theme'] ?? $dungeon_data['custom_theme'] ?? 'high fantasy'),
      'party_level' => $party_level,
      'travel_type' => (string) ($data['travel_type'] ?? 'walk'),
      'estimated_distance' => (string) ($data['estimated_distance'] ?? 'short'),
      'time_of_day' => (string) ($data['time_of_day'] ?? 'day'),
    ];

    try {
      $this->recordGmPrivateRequest($campaign_id, $gm_private);
      if ($this->shouldGenerateRemoteDungeon($data, $dungeon_data, $origin_room_id, $destination)) {
        $remote_dungeon = $this->generateRemoteDungeon(
          $campaign_id,
          $destination,
          $origin_room_id,
          $party_level,
          $data,
          $dungeon_data
        );

        $this->recordGmPrivateResponse($campaign_id, $gm_private, (string) ($remote_dungeon['message'] ?? 'Generated remote dungeon site.'), [
          'generated_by' => 'gm_request',
          'request_type' => 'location',
        ]);
        return new JsonResponse([
          'success' => TRUE,
          'data' => $remote_dungeon,
        ], JsonResponse::HTTP_CREATED);
      }

      $result = $this->mapGenerator->generateSetting(
        $campaign_id,
        $destination,
        $origin_room_id,
        $narrative_context
      );

      $navigation = $this->mapGenerator->buildClientNavigationPayload([
        'destination' => $destination,
        'new_room' => $result['room'] ?? [],
        'entities' => $result['entities'] ?? [],
        'dungeon_data' => $result['dungeon_data'] ?? [],
      ]);
      $room_name = (string) ($navigation['room']['name'] ?? $navigation['target_room_id'] ?? 'new location');
      $response_message = sprintf('Generated location: %s.', $room_name);
      $this->recordGmPrivateResponse($campaign_id, $gm_private, $response_message, [
        'generated_by' => 'gm_request',
        'request_type' => 'location',
        'room_id' => $navigation['target_room_id'] ?? '',
      ]);

      return new JsonResponse([
        'success' => TRUE,
        'data' => [
          'message' => $response_message,
          'generated_by' => 'gm_request',
          'destination' => $destination,
          'origin_room_id' => $origin_room_id,
          'room_id' => $navigation['target_room_id'],
          'room_name' => $room_name,
          'source' => $result['source'] ?? 'unknown',
          'navigation' => $navigation,
        ],
      ], JsonResponse::HTTP_CREATED);
    }
    catch (\RuntimeException $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }
    catch (\Throwable $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Location generation failed: ' . $e->getMessage(),
      ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * POST /api/campaign/{campaign_id}/gm/rooms/request
   *
   * Request a procedural room extension from the current room.
   */
  public function requestRoom(Request $request, int $campaign_id): JsonResponse {
    $data = json_decode($request->getContent(), TRUE);
    $gm_private = $this->resolveGmPrivateRequestContext($data);
    if (!is_array($data)) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Invalid JSON body',
      ], JsonResponse::HTTP_BAD_REQUEST);
    }

    $dungeon_record = $this->loadLatestDungeonRecord($campaign_id);
    if (!$dungeon_record) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'No active dungeon found for campaign',
      ], JsonResponse::HTTP_NOT_FOUND);
    }

    $dungeon_data = json_decode((string) $dungeon_record['dungeon_data'], TRUE);
    if (!is_array($dungeon_data)) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Stored dungeon data is invalid',
      ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
    }

    $origin_room_id = $this->resolveOriginRoomId($dungeon_data, (string) ($data['origin_room_id'] ?? ''));
    if ($origin_room_id === '' || !$this->roomExists($dungeon_data, $origin_room_id)) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'A valid origin_room_id is required',
      ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }

    $room_count = count(is_array($dungeon_data['rooms'] ?? NULL) ? $dungeon_data['rooms'] : []);
    $level_id = (string) ($data['level_id'] ?? $dungeon_data['level_id'] ?? '');
    if ($level_id === '') {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Unable to resolve level_id for room generation',
      ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }

    $party_level = max(1, min(20, (int) ($data['party_level']
      ?? $dungeon_data['generation_rules']['party_level_target']
      ?? 1)));

    $context = [
      'campaign_id' => $campaign_id,
      'dungeon_id' => (string) $dungeon_record['dungeon_id'],
      'level_id' => $level_id,
      'depth' => max(1, (int) ($data['depth'] ?? $dungeon_data['depth'] ?? 1)),
      'party_level' => $party_level,
      'room_index' => (int) ($data['room_index'] ?? $room_count),
      'room_size' => (string) ($data['room_size'] ?? 'medium'),
      'room_type' => (string) ($data['room_type'] ?? 'chamber'),
      'terrain_type' => (string) ($data['terrain_type'] ?? 'stone_floor'),
      'theme' => (string) ($data['theme'] ?? $dungeon_data['theme'] ?? 'dungeon'),
      'party_size' => max(1, (int) ($data['party_size'] ?? 4)),
    ];

    try {
      $this->recordGmPrivateRequest($campaign_id, $gm_private);
      $room = $this->roomGenerator->generateRoom($context);
      $dungeon_data['rooms'][] = $room;
      $dungeon_data['entities'] = array_merge(
        is_array($dungeon_data['entities'] ?? NULL) ? $dungeon_data['entities'] : [],
        is_array($room['creatures'] ?? NULL) ? $room['creatures'] : []
      );

      $this->appendRoomConnection($dungeon_data, $origin_room_id, (string) ($room['room_id'] ?? ''));
      $this->appendRoomRegion($dungeon_data, $room);
      $this->persistDungeonPayload($campaign_id, (string) $dungeon_record['dungeon_id'], $dungeon_data);
      $this->roomStateService->setState($campaign_id, (string) $room['room_id'], (string) $dungeon_record['dungeon_id'], [
        'roomId' => (string) $room['room_id'],
        'dungeonId' => (string) $dungeon_record['dungeon_id'],
        'explored' => TRUE,
        'visibility' => 'visible',
        'isCleared' => FALSE,
      ]);

      $navigation = $this->mapGenerator->buildClientNavigationPayload([
        'destination' => (string) ($room['name'] ?? 'Generated Room'),
        'new_room' => $room,
        'entities' => $room['creatures'] ?? [],
        'dungeon_data' => $dungeon_data,
      ]);
      $response_message = sprintf('Generated room: %s.', (string) ($room['name'] ?? $room['room_id'] ?? 'new room'));
      $this->recordGmPrivateResponse($campaign_id, $gm_private, $response_message, [
        'generated_by' => 'gm_room_request',
        'request_type' => 'room',
        'room_id' => $navigation['target_room_id'] ?? '',
      ]);

      return new JsonResponse([
        'success' => TRUE,
        'data' => [
          'message' => $response_message,
          'generated_by' => 'gm_room_request',
          'origin_room_id' => $origin_room_id,
          'room_id' => $navigation['target_room_id'],
          'navigation' => $navigation,
        ],
      ], JsonResponse::HTTP_CREATED);
    }
    catch (\Throwable $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Room generation failed: ' . $e->getMessage(),
      ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * POST /api/campaign/{campaign_id}/gm/quests/request
   *
   * Generate quests for the current/generated location.
   */
  public function requestLocationQuests(Request $request, int $campaign_id): JsonResponse {
    $data = json_decode($request->getContent(), TRUE);
    $gm_private = $this->resolveGmPrivateRequestContext($data);
    if (!is_array($data)) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Invalid JSON body',
      ], JsonResponse::HTTP_BAD_REQUEST);
    }

    $dungeon_record = $this->loadLatestDungeonRecord($campaign_id);
    if (!$dungeon_record) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'No active dungeon found for campaign',
      ], JsonResponse::HTTP_NOT_FOUND);
    }

    $dungeon_data = json_decode((string) $dungeon_record['dungeon_data'], TRUE);
    if (!is_array($dungeon_data)) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Stored dungeon data is invalid',
      ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
    }

    $room_id = $this->resolveOriginRoomId($dungeon_data, (string) ($data['room_id'] ?? ''));
    $room = $this->findRoom($dungeon_data, $room_id);
    if (!$room) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Unable to resolve location for quest generation',
      ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }

    $count = max(1, min(5, (int) ($data['count'] ?? 3)));
    $context = [
      'party_level' => max(1, min(20, (int) ($data['party_level']
        ?? $dungeon_data['generation_rules']['party_level_target']
        ?? 1))),
      'difficulty' => (string) ($data['difficulty'] ?? 'moderate'),
      'location' => (string) ($room['room_id'] ?? $room_id),
      'location_tags' => $this->buildLocationTags($room),
    ];

    try {
      $this->recordGmPrivateRequest($campaign_id, $gm_private);
      $quests = $this->questGenerator->generateQuestsForLocation($campaign_id, $context, $count);
      foreach ($quests as $quest_data) {
        $this->database->insert('dc_campaign_quests')
          ->fields($quest_data)
          ->execute();
      }

      $summary = array_map(static function (array $quest): array {
        return [
          'quest_id' => $quest['quest_id'],
          'name' => $quest['quest_name'],
          'description' => $quest['quest_description'],
          'type' => $quest['quest_type'],
        ];
      }, $quests);
      $response_message = sprintf('Generated %d quest%s for %s.', count($summary), count($summary) === 1 ? '' : 's', (string) ($room['name'] ?? $room_id));
      $this->recordGmPrivateResponse($campaign_id, $gm_private, $response_message, [
        'generated_by' => 'gm_quest_request',
        'request_type' => 'quests',
        'room_id' => (string) ($room['room_id'] ?? $room_id),
        'quest_count' => count($summary),
      ]);

      return new JsonResponse([
        'success' => TRUE,
        'data' => [
          'message' => $response_message,
          'room_id' => (string) ($room['room_id'] ?? $room_id),
          'quests' => $summary,
        ],
      ]);
    }
    catch (\Throwable $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Quest generation failed: ' . $e->getMessage(),
      ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * Loads the latest dungeon record for a campaign.
   */
  protected function loadLatestDungeonRecord(int $campaign_id): ?array {
    $record = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['dungeon_id', 'dungeon_data', 'theme'])
      ->condition('campaign_id', $campaign_id)
      ->orderBy('updated', 'DESC')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    return $record ?: NULL;
  }

  /**
   * Resolves an origin room from request data or dungeon payload.
   */
  protected function resolveOriginRoomId(array $dungeon_data, string $requested_origin): string {
    $requested_origin = trim($requested_origin);
    if ($requested_origin !== '') {
      return $requested_origin;
    }

    $active_room_id = trim((string) ($dungeon_data['active_room_id'] ?? ''));
    if ($active_room_id !== '') {
      return $active_room_id;
    }

    foreach (($dungeon_data['rooms'] ?? []) as $room) {
      if (is_array($room) && !empty($room['room_id'])) {
        return (string) $room['room_id'];
      }
    }

    return '';
  }

  /**
   * Checks whether a room exists in the active dungeon payload.
   */
  protected function roomExists(array $dungeon_data, string $room_id): bool {
    foreach (($dungeon_data['rooms'] ?? []) as $room) {
      if (is_array($room) && (string) ($room['room_id'] ?? '') === $room_id) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Find a room in the stored dungeon payload.
   */
  protected function findRoom(array $dungeon_data, string $room_id): ?array {
    foreach (($dungeon_data['rooms'] ?? []) as $room) {
      if (is_array($room) && (string) ($room['room_id'] ?? '') === $room_id) {
        return $room;
      }
    }

    return NULL;
  }

  /**
   * Persist updated dungeon payload.
   */
  protected function persistDungeonPayload(int $campaign_id, string $dungeon_id, array $dungeon_data): void {
    $this->database->update('dc_campaign_dungeons')
      ->fields([
        'dungeon_data' => json_encode($dungeon_data, JSON_UNESCAPED_UNICODE),
        'updated' => time(),
      ])
      ->condition('campaign_id', $campaign_id)
      ->condition('dungeon_id', $dungeon_id)
      ->execute();
  }

  /**
   * Append a simple bidirectional room connection.
   */
  protected function appendRoomConnection(array &$dungeon_data, string $from_room_id, string $to_room_id): void {
    if (!isset($dungeon_data['hex_map']['connections']) || !is_array($dungeon_data['hex_map']['connections'])) {
      $dungeon_data['hex_map']['connections'] = [];
    }

    $dungeon_data['hex_map']['connections'][] = [
      'from_room' => $from_room_id,
      'to_room' => $to_room_id,
      'type' => 'passage',
      'bidirectional' => TRUE,
    ];
  }

  /**
   * Append a simple region for a generated room.
   */
  protected function appendRoomRegion(array &$dungeon_data, array $room): void {
    if (!isset($dungeon_data['hex_map']['regions']) || !is_array($dungeon_data['hex_map']['regions'])) {
      $dungeon_data['hex_map']['regions'] = [];
    }

    $room_id = (string) ($room['room_id'] ?? '');
    $dungeon_data['hex_map']['regions'][] = [
      'region_id' => 'region-' . $room_id,
      'name' => (string) ($room['name'] ?? 'Generated Room'),
      'description' => (string) ($room['description'] ?? ''),
      'room_ids' => [$room_id],
      'ambient_hazard_level' => 0,
    ];
  }

  /**
   * Build quest tags from room metadata.
   */
  protected function buildLocationTags(array $room): array {
    $tags = [];
    foreach ([
      $room['room_type'] ?? NULL,
      $room['size_category'] ?? NULL,
      $room['terrain']['type'] ?? NULL,
      $room['name'] ?? NULL,
    ] as $value) {
      if (!is_string($value) || trim($value) === '') {
        continue;
      }
      $tags[] = strtolower(trim(str_replace(' ', '_', $value)));
    }

    return array_values(array_unique($tags));
  }

  /**
   * Determines whether the request should branch to a new dungeon site.
   */
  protected function shouldGenerateRemoteDungeon(
    array $request_data,
    array $dungeon_data,
    string $origin_room_id,
    string $destination
  ): bool {
    if (!empty($request_data['force_same_dungeon'])) {
      return FALSE;
    }

    if (!empty($request_data['force_new_dungeon'])) {
      return TRUE;
    }

    $distance = strtolower(trim((string) ($request_data['estimated_distance'] ?? '')));
    if (in_array($distance, ['far', 'very_far', 'distant', 'remote', 'long', 'cross_region'], TRUE)) {
      return TRUE;
    }

    $travel_type = strtolower(trim((string) ($request_data['travel_type'] ?? '')));
    if (in_array($travel_type, ['teleport', 'portal', 'ship', 'boat', 'airship', 'caravan', 'long_journey'], TRUE)) {
      return TRUE;
    }

    if (preg_match('/\b(far|distant|remote|another city|another village|across town|across the sea|beyond|outside town|outside the city|over the mountains|deep in the forest)\b/i', $destination)) {
      return TRUE;
    }

    $origin_room = $this->findRoom($dungeon_data, $origin_room_id) ?? [];
    $current_signature = implode(' ', array_filter([
      (string) ($dungeon_data['theme'] ?? ''),
      (string) ($dungeon_data['custom_theme'] ?? ''),
      (string) ($origin_room['name'] ?? ''),
      (string) ($origin_room['room_type'] ?? ''),
      (string) ($origin_room['description'] ?? ''),
    ]));

    $current_category = $this->inferLocationCategory($current_signature);
    $destination_category = $this->inferLocationCategory($destination);

    return $destination_category !== '' && $current_category !== '' && $destination_category !== $current_category;
  }

  /**
   * Generates a one-room landing dungeon for remote destinations.
   */
  protected function generateRemoteDungeon(
    int $campaign_id,
    string $destination,
    string $origin_room_id,
    int $party_level,
    array $request_data,
    array $current_dungeon_data
  ): array {
    [$location_x, $location_y] = $this->deriveRemoteDungeonCoordinates($campaign_id, $destination);
    $theme = $this->inferRemoteDungeonTheme($destination, $current_dungeon_data);
    $generated = $this->dungeonGenerator->generateDungeon([
      'campaign_id' => $campaign_id,
      'location_x' => $location_x,
      'location_y' => $location_y,
      'party_level' => $party_level,
      'party_size' => max(1, (int) ($request_data['party_size'] ?? 4)),
      'party_composition' => is_array($request_data['party_composition'] ?? NULL) ? $request_data['party_composition'] : [],
      'theme' => $theme,
      'depth_override' => 1,
      'room_count_override' => 1,
      'landing_room_type' => 'entrance',
    ]);

    $landing_room = NULL;
    foreach (($generated['rooms'] ?? []) as $room) {
      if (is_array($room) && !empty($room['room_id'])) {
        $landing_room = $room;
        break;
      }
    }
    if (!$landing_room) {
      throw new \RuntimeException('Remote dungeon generation did not produce a landing room');
    }

    $dungeon_id = (string) ($generated['dungeon_id'] ?? '');
    $landing_room_id = (string) ($landing_room['room_id'] ?? '');
    $this->roomStateService->setState($campaign_id, $landing_room_id, $dungeon_id, [
      'roomId' => $landing_room_id,
      'dungeonId' => $dungeon_id,
      'explored' => TRUE,
      'visibility' => 'visible',
      'isCleared' => FALSE,
    ]);

    return [
      'message' => sprintf('Generated remote dungeon landing for %s.', (string) ($landing_room['name'] ?? $destination)),
      'generated_by' => 'gm_remote_dungeon_request',
      'destination' => $destination,
      'origin_room_id' => $origin_room_id,
      'room_id' => $landing_room_id,
      'room_name' => (string) ($landing_room['name'] ?? $landing_room_id),
      'source' => 'dungeon_generator',
      'navigation' => [
        'target_room_id' => $landing_room_id,
        'destination' => $destination,
        'dungeon_switch' => [
          'dungeon_id' => $dungeon_id,
          'map_id' => (string) ($generated['dungeon_id'] ?? $dungeon_id),
          'dungeon_level_id' => (string) ($generated['level_id'] ?? ''),
          'room_id' => $landing_room_id,
          'next_room_id' => '',
        ],
      ],
    ];
  }

  /**
   * Infer a coarse location category for remote-site branching.
   */
  protected function inferLocationCategory(string $text): string {
    $normalized = strtolower($text);
    $categories = [
      'civilized' => ['town', 'city', 'village', 'market', 'shop', 'inn', 'tavern', 'temple', 'harbor', 'port', 'dock'],
      'wilderness' => ['forest', 'woods', 'swamp', 'marsh', 'desert', 'mountain', 'coast', 'island', 'plains', 'valley'],
      'ruins' => ['ruin', 'fort', 'keep', 'castle', 'tower', 'citadel', 'temple'],
      'underworld' => ['cave', 'mine', 'crypt', 'catacomb', 'sewer', 'underdark', 'abyss', 'dungeon'],
    ];

    foreach ($categories as $category => $keywords) {
      foreach ($keywords as $keyword) {
        if (str_contains($normalized, $keyword)) {
          return $category;
        }
      }
    }

    return '';
  }

  /**
   * Maps a destination to one of the supported dungeon themes.
   */
  protected function inferRemoteDungeonTheme(string $destination, array $current_dungeon_data): string {
    $normalized = strtolower($destination);
    $theme_map = [
      'crypt' => ['crypt', 'cemetery', 'grave', 'catacomb', 'mausoleum'],
      'cave' => ['cave', 'mine', 'tunnel', 'mountain', 'cliff'],
      'ruins' => ['ruin', 'castle', 'fort', 'keep', 'tower', 'temple'],
      'underdark' => ['underdark', 'abyss', 'chasm', 'deep road'],
      'demonic' => ['hell', 'infernal', 'demon', 'abyssal', 'fiend'],
      'underground' => ['sewer', 'basement', 'undercity', 'catacomb'],
    ];

    foreach ($theme_map as $theme => $keywords) {
      foreach ($keywords as $keyword) {
        if (str_contains($normalized, $keyword)) {
          return $theme;
        }
      }
    }

    $current_theme = strtolower((string) ($current_dungeon_data['theme'] ?? ''));
    if (in_array($current_theme, ['dungeon', 'cave', 'crypt', 'ruins', 'underground', 'demonic', 'underdark'], TRUE)) {
      return $current_theme;
    }

    return 'dungeon';
  }

  /**
   * Derive stable synthetic coordinates for a remote generated dungeon.
   *
   * Coordinates are deterministic per destination so repeated requests land on
   * the same campaign dungeon record instead of creating duplicates.
   *
   * @return int[]
   *   [x, y]
   */
  protected function deriveRemoteDungeonCoordinates(int $campaign_id, string $destination): array {
    $seed = sprintf('%d:%s', $campaign_id, strtolower(trim($destination)));
    $x_hash = (int) sprintf('%u', crc32($seed . ':x'));
    $y_hash = (int) sprintf('%u', crc32($seed . ':y'));

    return [
      ($x_hash % 200000) + 1000,
      ($y_hash % 200000) + 1000,
    ];
  }

  /**
   * Extract optional GM-private persistence context from request payload.
   */
  protected function resolveGmPrivateRequestContext(array $data): array {
    return [
      'character_id' => isset($data['character_id']) ? (int) $data['character_id'] : 0,
      'speaker' => trim((string) ($data['speaker'] ?? '')),
      'message' => trim((string) ($data['gm_private_message'] ?? '')),
    ];
  }

  /**
   * Persist the initiating GM-private request when character context is present.
   */
  protected function recordGmPrivateRequest(int $campaign_id, array $gm_private): void {
    if (($gm_private['character_id'] ?? 0) <= 0 || ($gm_private['speaker'] ?? '') === '' || ($gm_private['message'] ?? '') === '') {
      return;
    }

    $this->narrationEngine->recordSecretAction(
      $campaign_id,
      (int) $gm_private['character_id'],
      (string) $gm_private['speaker'],
      (string) $gm_private['message']
    );
  }

  /**
   * Persist the GM response for a GM-private request when character context is present.
   */
  protected function recordGmPrivateResponse(int $campaign_id, array $gm_private, string $response_message, array $metadata = []): void {
    if (($gm_private['character_id'] ?? 0) <= 0 || trim($response_message) === '') {
      return;
    }

    $this->narrationEngine->respondToSecretAction(
      $campaign_id,
      (int) $gm_private['character_id'],
      trim($response_message),
      $metadata
    );
  }

}
