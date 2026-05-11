<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Builds and retrieves cached room-scene images for the hexmap View tab.
 */
class RoomViewImageService {

  /**
   * Number of room-session messages per generated scene snapshot.
   */
  const GALLERY_BATCH_SIZE = 50;

  /**
   * Database connection.
   */
  protected Connection $database;

  /**
   * Image generation integration layer.
   */
  protected ImageGenerationIntegrationService $imageGenerationIntegration;

  /**
   * Generated image repository.
   */
  protected GeneratedImageRepository $generatedImageRepository;

  /**
   * Chat session manager.
   */
  protected ChatSessionManager $chatSessionManager;

  /**
   * Logger channel.
   */
  protected LoggerInterface $logger;

  /**
   * Constructs the room view image service.
   */
  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    ImageGenerationIntegrationService $image_generation_integration,
    ChatSessionManager $chat_session_manager,
    GeneratedImageRepository $generated_image_repository
  ) {
    $this->database = $database;
    $this->imageGenerationIntegration = $image_generation_integration;
    $this->generatedImageRepository = $generated_image_repository;
    $this->chatSessionManager = $chat_session_manager;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
  }

  /**
   * Resolve or generate the current room-view image for a campaign room.
   *
   * @return array<string, mixed>
   *   Normalized room image payload for the frontend.
   */
  public function getRoomViewImage(int $campaign_id, string $room_id): array {
    $record = $this->loadLatestDungeonRecord($campaign_id);
    if (!$record) {
      throw new \RuntimeException('No active dungeon found for campaign.', 404);
    }

    $dungeon_data = json_decode((string) ($record['dungeon_data'] ?? ''), TRUE);
    if (!is_array($dungeon_data)) {
      throw new \RuntimeException('Stored dungeon data is invalid.', 500);
    }

    $room = $this->resolveRoom($dungeon_data, $room_id);
    if ($room === NULL) {
      throw new \RuntimeException('Room not found in the active dungeon payload.', 404);
    }

    $campaign_room_cache_key = $this->resolveCampaignRoomCacheObjectId($campaign_id, $room_id, $room, $dungeon_data);
    $room = $this->hydrateRoomFromCampaignRecord(
      $campaign_id,
      $campaign_room_cache_key !== '' ? $campaign_room_cache_key : $room_id,
      $room
    );
    $dungeon_id = (string) ($record['dungeon_id'] ?? '');
    $room_meta = $this->buildRoomMeta($room, $room_id);
    $room_session = $this->chatSessionManager->ensureRoomSession(
      $campaign_id,
      $dungeon_id,
      $room_id,
      $room_meta['name'] ?? ''
    );

    $gallery_entries = $this->buildConversationGalleryEntries(
      $campaign_id,
      $dungeon_id,
      $room_id,
      $room,
      (int) $room_session['id']
    );
    $establishing_entry = $this->buildEstablishingEntry(
      $campaign_id,
      $dungeon_id,
      $room_id,
      $room,
      $campaign_room_cache_key
    );

    $entries = $gallery_entries;
    if ($establishing_entry !== NULL) {
      $entries[] = $establishing_entry;
    }

    $generation_available = $this->isVertexAvailable();
    $message = empty($gallery_entries)
      ? sprintf(
        'A new scene snapshot appears every %d room messages.',
        self::GALLERY_BATCH_SIZE
      )
      : sprintf(
        '%d scene snapshot%s generated from room conversation.',
        count($gallery_entries),
        count($gallery_entries) === 1 ? '' : 's'
      );

    if (!$generation_available && empty($entries)) {
      $message = 'Room view images are unavailable until Vertex image generation is configured.';
    }

    return [
      'success' => !empty($entries),
      'available' => $generation_available,
      'status' => !empty($entries) ? 'ready' : ($generation_available ? 'pending' : 'unavailable'),
      'provider' => 'vertex',
      'mode' => !empty($gallery_entries) ? 'gallery' : 'establishing',
      'message' => $message,
      'room' => $room_meta,
      'message_batch_size' => self::GALLERY_BATCH_SIZE,
      'generated_entry_count' => count($gallery_entries),
      'entries' => $entries,
    ];
  }

  /**
   * Prefetch a cached room-view image for a freshly created room.
   */
  public function warmRoomViewImageCache(array $room_data, array $context): ?array {
    $campaign_id = (int) ($context['campaign_id'] ?? 0);
    $room_id = trim((string) ($room_data['room_id'] ?? ($context['room_id'] ?? '')));
    if ($campaign_id <= 0 || $room_id === '') {
      return NULL;
    }

    try {
      return $this->generateRoomViewImage(
        $campaign_id,
        (string) ($context['dungeon_id'] ?? ''),
        $room_id,
        $room_data,
        $room_id
      );
    }
    catch (\Throwable $exception) {
      $this->logger->warning('Room view image prefetch skipped for room @room: @message', [
        '@room' => $room_id,
        '@message' => $exception->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Generate or load a cached room-view image from Vertex.
   *
   * @return array<string, mixed>
   *   Normalized frontend-ready payload.
   */
  protected function generateRoomViewImage(int $campaign_id, string $dungeon_id, string $room_id, array $room, ?string $campaign_room_cache_key = NULL): array {
    $cache_object_id = trim((string) ($campaign_room_cache_key ?? $room_id));
    $room = $this->hydrateRoomFromCampaignRecord(
      $campaign_id,
      $cache_object_id,
      $room
    );
    $room_meta = $this->buildRoomMeta($room, $room_id);
    if ($cache_object_id !== '') {
      $cached = $this->loadStoredRoomViewImage($campaign_id, $cache_object_id, $room_meta);
      if ($cached !== NULL) {
        return $cached;
      }
    }

    if (!$this->isVertexAvailable()) {
      return [
        'success' => FALSE,
        'available' => FALSE,
        'status' => 'unavailable',
        'provider' => 'vertex',
        'message' => 'Room view images are unavailable until Vertex image generation is configured.',
        'room' => $room_meta,
      ];
    }

    $payload = $this->buildGenerationPayload($campaign_id, $dungeon_id, $room_id, $room);
    $result = $this->imageGenerationIntegration->generateImage($payload, 'vertex');
    $output = is_array($result['output'] ?? NULL) ? $result['output'] : [];
    $image_url = $output['image_url'] ?? NULL;
    $image_data_uri = $output['image_data_uri'] ?? NULL;

    if (!empty($result['success']) && $cache_object_id !== '') {
      $stored = $this->storeRoomViewImage($campaign_id, $cache_object_id, $room_meta, $result);
      if ($stored !== NULL) {
        return $stored;
      }
    }

    return [
      'success' => !empty($result['success']) && ($image_url !== NULL || $image_data_uri !== NULL),
      'available' => TRUE,
      'status' => (string) ($result['status'] ?? 'unknown'),
      'provider' => (string) ($result['provider'] ?? 'vertex'),
      'mode' => (string) ($result['mode'] ?? 'live'),
      'message' => (string) ($result['message'] ?? ''),
      'room' => $room_meta,
      'image' => [
        'url' => $image_url,
        'data_uri' => $image_data_uri,
        'mime_type' => $output['mime_type'] ?? NULL,
      ],
    ];
  }

  /**
   * Build conversation-gallery entries for completed 50-message windows.
   *
   * @return array<int, array<string, mixed>>
   *   Newest-first gallery entries.
   */
  protected function buildConversationGalleryEntries(int $campaign_id, string $dungeon_id, string $room_id, array $room, int $room_session_id): array {
    $messages = $this->loadRoomSessionMessages($room_session_id);
    if (count($messages) < self::GALLERY_BATCH_SIZE) {
      return [];
    }

    $existing_rows = $this->loadStoredGalleryEntries($campaign_id, $dungeon_id, $room_id, $room_session_id);
    $entries_by_window = [];
    foreach ($existing_rows as $row) {
      $entries_by_window[(int) $row['window_index']] = $this->normalizeStoredGalleryEntry($row, $room);
    }

    $entries = [];
    $complete_windows = intdiv(count($messages), self::GALLERY_BATCH_SIZE);
    for ($window_index = 1; $window_index <= $complete_windows; $window_index++) {
      if (!empty($entries_by_window[$window_index])) {
        $entries[] = $entries_by_window[$window_index];
        continue;
      }

      $offset = ($window_index - 1) * self::GALLERY_BATCH_SIZE;
      $window_messages = array_slice($messages, $offset, self::GALLERY_BATCH_SIZE);
      $generated_entry = $this->generateConversationGalleryEntry(
        $campaign_id,
        $dungeon_id,
        $room_id,
        $room,
        $room_session_id,
        $window_index,
        $window_messages
      );
      if ($generated_entry !== NULL) {
        $entries[] = $generated_entry;
      }
    }

    usort($entries, static function (array $a, array $b): int {
      return ($b['message_window']['index'] ?? 0) <=> ($a['message_window']['index'] ?? 0);
    });

    return $entries;
  }

  /**
   * Generate and store a conversation-gallery entry.
   */
  protected function generateConversationGalleryEntry(int $campaign_id, string $dungeon_id, string $room_id, array $room, int $room_session_id, int $window_index, array $messages): ?array {
    if (!$this->isVertexAvailable() || count($messages) < self::GALLERY_BATCH_SIZE) {
      return NULL;
    }

    $summary = $this->buildConversationSummary($room, $window_index, $messages);
    $payload = $this->buildConversationGenerationPayload(
      $campaign_id,
      $dungeon_id,
      $room_id,
      $room,
      $window_index,
      $summary
    );
    $result = $this->imageGenerationIntegration->generateImage($payload, 'vertex');
    $output = is_array($result['output'] ?? NULL) ? $result['output'] : [];
    $image_url = $output['image_url'] ?? NULL;
    $image_data_uri = $output['image_data_uri'] ?? NULL;
    if (empty($result['success']) || ($image_url === NULL && $image_data_uri === NULL)) {
      return NULL;
    }

    $first_message = reset($messages) ?: [];
    $last_message = end($messages) ?: [];
    $now = time();
    $fields = [
      'campaign_id' => $campaign_id,
      'dungeon_id' => $dungeon_id,
      'room_id' => $room_id,
      'room_session_id' => $room_session_id,
      'window_index' => $window_index,
      'message_start_id' => (int) ($first_message['id'] ?? 0),
      'message_end_id' => (int) ($last_message['id'] ?? 0),
      'message_count' => count($messages),
      'summary_text' => $summary,
      'provider' => (string) ($result['provider'] ?? 'vertex'),
      'mode' => (string) ($result['mode'] ?? 'live'),
      'status' => (string) ($result['status'] ?? 'ready'),
      'image_url' => $image_url,
      'image_data_uri' => $image_data_uri,
      'mime_type' => $output['mime_type'] ?? NULL,
      'created' => $now,
      'updated' => $now,
    ];

    $this->database->merge('dc_room_view_gallery')
      ->key([
        'room_session_id' => $room_session_id,
        'window_index' => $window_index,
      ])
      ->fields($fields)
      ->execute();

    return $this->normalizeGalleryEntry($room, $fields);
  }

  /**
   * Build the synthetic establishing-shot entry.
   */
  protected function buildEstablishingEntry(int $campaign_id, string $dungeon_id, string $room_id, array $room, ?string $campaign_room_cache_key = NULL): ?array {
    $result = $this->generateRoomViewImage($campaign_id, $dungeon_id, $room_id, $room, $campaign_room_cache_key);
    $image_src = $result['image']['url'] ?? $result['image']['data_uri'] ?? '';
    if ($image_src === '') {
      return NULL;
    }

    return [
      'id' => 'establishing-shot',
      'entry_type' => 'establishing',
      'title' => 'Establishing Shot',
      'summary' => $result['room']['description'] ?: 'Player-facing establishing image for the current room.',
      'status' => (string) ($result['status'] ?? 'ready'),
      'provider' => (string) ($result['provider'] ?? 'vertex'),
      'mode' => (string) ($result['mode'] ?? 'cache'),
      'created' => 0,
      'message_window' => [
        'index' => 0,
        'count' => 0,
        'label' => 'Room opening view',
      ],
      'image' => $result['image'],
    ];
  }

  /**
   * Load room-session messages in chronological order.
   *
   * @return array<int, array<string, mixed>>
   *   Message rows.
   */
  protected function loadRoomSessionMessages(int $room_session_id): array {
    $rows = $this->database->select('dc_chat_messages', 'm')
      ->fields('m', ['id', 'speaker', 'speaker_type', 'message', 'message_type', 'created'])
      ->condition('session_id', $room_session_id)
      ->orderBy('id', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    return array_map(static function (array $row): array {
      $row['id'] = (int) $row['id'];
      $row['created'] = (int) $row['created'];
      return $row;
    }, $rows);
  }

  /**
   * Load stored gallery entries for a room.
   *
   * @return array<int, array<string, mixed>>
   *   Stored rows.
   */
  protected function loadStoredGalleryEntries(int $campaign_id, string $dungeon_id, string $room_id, int $room_session_id): array {
    return $this->database->select('dc_room_view_gallery', 'g')
      ->fields('g')
      ->condition('campaign_id', $campaign_id)
      ->condition('dungeon_id', $dungeon_id)
      ->condition('room_id', $room_id)
      ->condition('room_session_id', $room_session_id)
      ->orderBy('window_index', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
  }

  /**
   * Normalize a stored gallery row for the frontend.
   */
  protected function normalizeStoredGalleryEntry(array $row, array $room): array {
    return $this->normalizeGalleryEntry($room, [
      'window_index' => (int) ($row['window_index'] ?? 0),
      'message_start_id' => (int) ($row['message_start_id'] ?? 0),
      'message_end_id' => (int) ($row['message_end_id'] ?? 0),
      'message_count' => (int) ($row['message_count'] ?? 0),
      'summary_text' => (string) ($row['summary_text'] ?? ''),
      'provider' => (string) ($row['provider'] ?? 'vertex'),
      'mode' => (string) ($row['mode'] ?? 'cache'),
      'status' => (string) ($row['status'] ?? 'ready'),
      'image_url' => $row['image_url'] ?? NULL,
      'image_data_uri' => $row['image_data_uri'] ?? NULL,
      'mime_type' => $row['mime_type'] ?? NULL,
      'created' => (int) ($row['created'] ?? 0),
    ]);
  }

  /**
   * Normalize a gallery entry for the frontend.
   */
  protected function normalizeGalleryEntry(array $room, array $row): array {
    $window_index = (int) ($row['window_index'] ?? 0);
    $message_count = (int) ($row['message_count'] ?? 0);
    $start_position = (($window_index - 1) * self::GALLERY_BATCH_SIZE) + 1;
    $end_position = $start_position + $message_count - 1;

    return [
      'id' => 'conversation-' . $window_index,
      'entry_type' => 'conversation_summary',
      'title' => 'Scene Snapshot ' . $window_index,
      'summary' => (string) ($row['summary_text'] ?? ''),
      'status' => (string) ($row['status'] ?? 'ready'),
      'provider' => (string) ($row['provider'] ?? 'vertex'),
      'mode' => (string) ($row['mode'] ?? 'cache'),
      'created' => (int) ($row['created'] ?? 0),
      'message_window' => [
        'index' => $window_index,
        'count' => $message_count,
        'start_id' => (int) ($row['message_start_id'] ?? 0),
        'end_id' => (int) ($row['message_end_id'] ?? 0),
        'label' => sprintf('Messages %d-%d', $start_position, $end_position),
      ],
      'image' => [
        'url' => $row['image_url'] ?? NULL,
        'data_uri' => $row['image_data_uri'] ?? NULL,
        'mime_type' => $row['mime_type'] ?? NULL,
      ],
      'room' => $this->buildRoomMeta($room, (string) ($room['room_id'] ?? $room['id'] ?? '')),
    ];
  }

  /**
   * Build a deterministic summary of a 50-message room-chat window.
   */
  protected function buildConversationSummary(array $room, int $window_index, array $messages): string {
    $room_name = trim((string) ($room['name'] ?? 'Unknown Room'));
    $beats = [];
    foreach ($messages as $message) {
      $type = (string) ($message['message_type'] ?? 'narrative');
      if (in_array($type, ['mechanical', 'system'], TRUE)) {
        continue;
      }

      $speaker = trim((string) ($message['speaker'] ?? 'Unknown'));
      $body = $this->normalizePlainText((string) ($message['message'] ?? ''));
      if ($body === '') {
        continue;
      }

      if (strlen($body) > 180) {
        $body = substr($body, 0, 177) . '...';
      }

      $beats[] = $speaker . ': ' . $body;
    }

    if (count($beats) > 8) {
      $beats = array_merge(
        array_slice($beats, 0, 5),
        ['...'],
        array_slice($beats, -3)
      );
    }

    if (empty($beats)) {
      $beats[] = 'The room conversation shifts through exploration, observation, and roleplay beats.';
    }

    return trim(sprintf(
      'Room: %s. Snapshot %d covering %d messages. %s',
      $room_name !== '' ? $room_name : 'Unknown Room',
      $window_index,
      count($messages),
      implode(' ', $beats)
    ));
  }

  /**
   * Build image-generation payload for a conversation-gallery image.
   *
   * @return array<string, mixed>
   *   Provider payload.
   */
  protected function buildConversationGenerationPayload(int $campaign_id, string $dungeon_id, string $room_id, array $room, int $window_index, string $summary): array {
    $terrain_type = '';
    if (is_array($room['terrain'] ?? NULL)) {
      $terrain_type = trim((string) ($room['terrain']['type'] ?? ''));
    }
    else {
      $terrain_type = trim((string) ($room['terrain'] ?? ''));
    }

    return [
      'prompt' => $this->buildConversationPrompt($room, $window_index, $summary),
      'style' => 'cinematic fantasy narrative illustration',
      'aspect_ratio' => '16:9',
      'negative_prompt' => 'text, captions, watermark, user interface, map grid, tactical overlay, split panel, collage, comic panels, character sheet, labels',
      'campaign_id' => $campaign_id,
      'dungeon_id' => $dungeon_id,
      'room_id' => $room_id,
      'terrain_type' => $terrain_type,
      'habitat_name' => trim((string) ($room['name'] ?? 'Unknown Room')),
      'entity_type' => 'room_view_gallery',
      'scene_index' => $window_index,
    ];
  }

  /**
   * Build the prompt for a conversation-summary snapshot image.
   */
  protected function buildConversationPrompt(array $room, int $window_index, string $summary): string {
    $room_name = trim((string) ($room['name'] ?? 'Unknown Room'));
    $room_type = trim((string) ($room['room_type'] ?? 'room'));
    $size = trim((string) ($room['size_category'] ?? 'medium'));
    $description = trim((string) ($room['description'] ?? ''));

    return trim("Create a player-facing fantasy RPG scene image inspired by a recent room conversation.\n"
      . "Scene title: {$room_name} — Snapshot {$window_index}.\n"
      . "Room type: {$room_type}. Scale: {$size}.\n"
      . ($description !== '' ? "Baseline room description: {$description}\n" : '')
      . "Conversation summary: {$summary}\n"
      . "Requirements: depict the most visually important conversational or narrative beat from this summary, stay grounded in the room's environment, preserve mood and continuity, and do not render visible text or UI.");
  }

  /**
   * Determine whether Vertex image generation is currently available.
   */
  protected function isVertexAvailable(): bool {
    $provider_status = $this->imageGenerationIntegration->getIntegrationStatus();
    $vertex_status = is_array($provider_status['providers']['vertex'] ?? NULL)
      ? $provider_status['providers']['vertex']
      : [];

    return !empty($vertex_status['enabled']) && (!empty($vertex_status['has_credentials']) || !empty($vertex_status['has_api_key']));
  }

  /**
   * Normalize whitespace and strip newlines for summary/prompt usage.
   */
  protected function normalizePlainText(string $value): string {
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    return $value;
  }

  /**
   * Build image-generation payload for a room-scene image.
   *
   * @return array<string, mixed>
   *   Provider payload.
   */
  protected function buildGenerationPayload(int $campaign_id, string $dungeon_id, string $room_id, array $room): array {
    $terrain_type = '';
    if (is_array($room['terrain'] ?? NULL)) {
      $terrain_type = trim((string) ($room['terrain']['type'] ?? ''));
    }
    else {
      $terrain_type = trim((string) ($room['terrain'] ?? ''));
    }

    return [
      'prompt' => $this->buildPrompt($room),
      'style' => 'cinematic fantasy environment illustration',
      'aspect_ratio' => '16:9',
      'negative_prompt' => 'text, captions, watermark, user interface, map grid, tactical overlay, split panel, collage, comic panels, character sheet, labels',
      'campaign_id' => $campaign_id,
      'dungeon_id' => $dungeon_id,
      'room_id' => $room_id,
      'terrain_type' => $terrain_type,
      'habitat_name' => trim((string) ($room['name'] ?? 'Unknown Room')),
      'entity_type' => 'room_view',
    ];
  }

  /**
   * Build the prompt for a player-facing room establishing shot.
   */
  protected function buildPrompt(array $room): string {
    $room_name = trim((string) ($room['name'] ?? 'Unknown Room'));
    $description = trim((string) ($room['description'] ?? ''));
    $room_type = trim((string) ($room['room_type'] ?? 'room'));
    $size = trim((string) ($room['size_category'] ?? 'medium'));

    $terrain = '';
    if (is_array($room['terrain'] ?? NULL)) {
      $terrain = trim((string) ($room['terrain']['type'] ?? ''));
    }
    else {
      $terrain = trim((string) ($room['terrain'] ?? ''));
    }

    $lighting = '';
    if (is_array($room['lighting'] ?? NULL)) {
      $lighting = trim((string) ($room['lighting']['level'] ?? ''));
    }
    else {
      $lighting = trim((string) ($room['lighting'] ?? ''));
    }

    $environment_bits = array_filter([
      $room_type !== '' ? "Room type: {$room_type}." : '',
      $size !== '' ? "Scale: {$size}." : '',
      $terrain !== '' ? "Terrain: {$terrain}." : '',
      $lighting !== '' ? "Lighting: {$lighting}." : '',
    ]);

    $base_description = $description !== ''
      ? $description
      : 'Show an evocative player-facing establishing shot of the room based on its type, terrain, and lighting.';

    return trim("Create a player-facing establishing image for a tabletop fantasy RPG room.\n"
      . "Scene title: {$room_name}.\n"
      . implode(' ', $environment_bits) . "\n"
      . "Narrative description: {$base_description}\n"
      . "Requirements: depict the room as a grounded in-world scene, preserve environmental mood, avoid UI/map overlays, and do not render visible text.");
  }

  /**
   * Normalize room metadata for the frontend.
   *
   * @return array<string, mixed>
   *   Room metadata.
   */
  protected function buildRoomMeta(array $room, string $room_id): array {
    $lighting = '';
    if (is_array($room['lighting'] ?? NULL)) {
      $lighting = trim((string) ($room['lighting']['level'] ?? ''));
    }
    else {
      $lighting = trim((string) ($room['lighting'] ?? ''));
    }

    $terrain = '';
    if (is_array($room['terrain'] ?? NULL)) {
      $terrain = trim((string) ($room['terrain']['type'] ?? ''));
    }
    else {
      $terrain = trim((string) ($room['terrain'] ?? ''));
    }

    return [
      'room_id' => $room_id,
      'name' => trim((string) ($room['name'] ?? 'Unknown Room')),
      'description' => trim((string) ($room['description'] ?? '')),
      'room_type' => trim((string) ($room['room_type'] ?? '')),
      'size_category' => trim((string) ($room['size_category'] ?? '')),
      'terrain' => $terrain,
      'lighting' => $lighting,
    ];
  }

  /**
   * Fill missing room fields from the persisted campaign-room record.
   *
   * The active dungeon payload may not carry the full authored description even
   * when dc_campaign_rooms does. Use the persisted room record as the first
   * source of truth before falling back to generation.
   */
  protected function hydrateRoomFromCampaignRecord(int $campaign_id, string $cache_object_id, array $room): array {
    if ($campaign_id <= 0 || $cache_object_id === '') {
      return $room;
    }

    $record = $this->database->select('dc_campaign_rooms', 'r')
      ->fields('r', ['name', 'description'])
      ->condition('campaign_id', $campaign_id)
      ->condition('room_id', $cache_object_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!is_array($record)) {
      return $room;
    }

    if (trim((string) ($room['name'] ?? '')) === '' && trim((string) ($record['name'] ?? '')) !== '') {
      $room['name'] = (string) $record['name'];
    }

    if (trim((string) ($room['description'] ?? '')) === '' && trim((string) ($record['description'] ?? '')) !== '') {
      $room['description'] = (string) $record['description'];
    }

    return $room;
  }

  /**
   * Load the latest dungeon record for a campaign.
   *
   * @return array<string, mixed>|null
   *   Latest dungeon record or NULL.
   */
  protected function loadLatestDungeonRecord(int $campaign_id): ?array {
    $record = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['dungeon_id', 'dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->orderBy('updated', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    return is_array($record) ? $record : NULL;
  }

  /**
   * Resolve a room from the dungeon payload.
   */
  protected function resolveRoom(array $dungeon_data, string $room_id): ?array {
    $rooms = $dungeon_data['rooms'] ?? [];
    if (isset($rooms[$room_id]) && is_array($rooms[$room_id])) {
      return $rooms[$room_id];
    }

    if (is_array($rooms)) {
      foreach ($rooms as $candidate_key => $candidate) {
        if (!is_array($candidate)) {
          continue;
        }
        $candidate_room_id = (string) ($candidate['room_id'] ?? $candidate['id'] ?? $candidate_key);
        if ($candidate_room_id === $room_id) {
          return $candidate;
        }
      }
    }

    return NULL;
  }

  /**
   * Resolve the campaign-room slug used for persisted room-view cache rows.
   */
  protected function resolveCampaignRoomCacheObjectId(int $campaign_id, string $room_id, array $room, array $dungeon_data): string {
    $exact = $this->database->select('dc_campaign_rooms', 'r')
      ->fields('r', ['room_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('room_id', $room_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();

    if ($exact !== FALSE) {
      return (string) $exact;
    }

    $room_name = trim((string) ($room['name'] ?? ''));
    if ($room_name !== '') {
      $by_name = $this->database->select('dc_campaign_rooms', 'r')
        ->fields('r', ['room_id'])
        ->condition('campaign_id', $campaign_id)
        ->condition('name', $room_name)
        ->range(0, 1)
        ->execute()
        ->fetchField();

      if ($by_name !== FALSE) {
        return (string) $by_name;
      }
    }

    $payload_room_ids = [];
    foreach (($dungeon_data['rooms'] ?? []) as $candidate_key => $candidate) {
      if (!is_array($candidate)) {
        continue;
      }
      $payload_room_ids[] = (string) ($candidate['room_id'] ?? $candidate['id'] ?? $candidate_key);
    }

    $payload_room_index = array_search($room_id, $payload_room_ids, TRUE);
    if ($payload_room_index !== FALSE) {
      $ordered_room_ids = $this->database->select('dc_campaign_rooms', 'r')
        ->fields('r', ['room_id'])
        ->condition('campaign_id', $campaign_id)
        ->orderBy('id', 'ASC')
        ->execute()
        ->fetchCol();

      if (isset($ordered_room_ids[$payload_room_index])) {
        return (string) $ordered_room_ids[$payload_room_index];
      }
    }

    return $room_id;
  }

  /**
   * Load a persisted establishing-shot image for the campaign room when present.
   */
  protected function loadStoredRoomViewImage(int $campaign_id, string $cache_object_id, array $room_meta): ?array {
    $rows = $this->generatedImageRepository->loadImagesForObject(
      'dc_campaign_rooms',
      $cache_object_id,
      $campaign_id,
      'room_view',
      'establishing'
    );

    $row = $rows[0] ?? NULL;
    if (!is_array($row)) {
      return NULL;
    }

    $image_url = $this->generatedImageRepository->resolveClientUrl($row);
    if ($image_url === NULL || $image_url === '') {
      return NULL;
    }

    return [
      'success' => TRUE,
      'available' => TRUE,
      'status' => (string) ($row['image_status'] ?? 'ready'),
      'provider' => (string) ($row['provider'] ?? 'vertex'),
      'mode' => 'cache',
      'message' => 'Loaded cached room view image.',
      'room' => $room_meta,
      'image' => [
        'url' => $image_url,
        'data_uri' => NULL,
        'mime_type' => $row['mime_type'] ?? NULL,
      ],
    ];
  }

  /**
   * Persist a freshly generated establishing-shot image for future reuse.
   */
  protected function storeRoomViewImage(int $campaign_id, string $cache_object_id, array $room_meta, array $generation_result): ?array {
    $stored = $this->generatedImageRepository->persistGeneratedImage($generation_result, [
      'scope_type' => 'campaign',
      'campaign_id' => $campaign_id,
      'table_name' => 'dc_campaign_rooms',
      'object_id' => $cache_object_id,
      'slot' => 'room_view',
      'variant' => 'establishing',
      'is_primary' => 1,
      'visibility' => 'owner',
    ]);

    if (empty($stored['stored'])) {
      return NULL;
    }

    $image_url = is_string($stored['url'] ?? NULL) ? $stored['url'] : NULL;
    if ($image_url === NULL || $image_url === '') {
      return NULL;
    }

    $output = is_array($generation_result['output'] ?? NULL) ? $generation_result['output'] : [];

    return [
      'success' => TRUE,
      'available' => TRUE,
      'status' => (string) ($generation_result['status'] ?? 'ready'),
      'provider' => (string) ($generation_result['provider'] ?? 'vertex'),
      'mode' => 'cache',
      'message' => (string) ($generation_result['message'] ?? 'Cached room view image ready.'),
      'room' => $room_meta,
      'image' => [
        'url' => $image_url,
        'data_uri' => NULL,
        'mime_type' => $output['mime_type'] ?? NULL,
      ],
    ];
  }

}
