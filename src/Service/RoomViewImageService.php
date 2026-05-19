<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Builds and retrieves cached room-scene images for the hexmap View tab.
 */
class RoomViewImageService {

  /**
   * Gallery mode used for encounter/exploration transition snapshots.
   */
  const GALLERY_MODE_TRANSITION = 'transition';

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
   * File system service.
   */
  protected FileSystemInterface $fileSystem;

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
    GeneratedImageRepository $generated_image_repository,
    FileSystemInterface $file_system
  ) {
    $this->database = $database;
    $this->imageGenerationIntegration = $image_generation_integration;
    $this->generatedImageRepository = $generated_image_repository;
    $this->fileSystem = $file_system;
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
    $portrait_references = $this->loadRoomPortraitReferences($campaign_id, $room_id, $campaign_room_cache_key, $room);
    $provider = $this->resolveRoomViewProvider($portrait_references);

    $gallery_entries = $this->buildTransitionGalleryEntries(
      $campaign_id,
      $dungeon_id,
      $room_id,
      $room,
      (int) $room_session['id'],
      $portrait_references
    );
    $establishing_entry = $this->buildEstablishingEntry(
      $campaign_id,
      $dungeon_id,
      $room_id,
      $room,
      $campaign_room_cache_key,
      $portrait_references
    );

    $entries = $gallery_entries;
    if ($establishing_entry !== NULL) {
      array_unshift($entries, $establishing_entry);
    }

    $generation_available = $provider !== NULL;
    $message = empty($gallery_entries)
      ? 'Scene snapshots appear only when the room transitions between exploration and encounter.'
      : sprintf(
        '%d transition snapshot%s generated from exploration/encounter phase changes.',
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
      'provider' => $provider ?? 'unavailable',
      'mode' => !empty($gallery_entries) ? 'transition_gallery' : 'establishing',
      'message' => $message,
      'room' => $room_meta,
      'message_batch_size' => 0,
      'generated_entry_count' => count($gallery_entries),
      'character_reference_count' => count($portrait_references),
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
  protected function generateRoomViewImage(int $campaign_id, string $dungeon_id, string $room_id, array $room, ?string $campaign_room_cache_key = NULL, array $portrait_references = []): array {
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

    $provider = $this->resolveRoomViewProvider($portrait_references);
    if ($provider === NULL) {
      return [
        'success' => FALSE,
        'available' => FALSE,
        'status' => 'unavailable',
        'provider' => 'unavailable',
        'message' => 'Room view images are unavailable until an image generation provider is configured.',
        'room' => $room_meta,
      ];
    }

    $payload = $this->buildGenerationPayload($campaign_id, $dungeon_id, $room_id, $room, $portrait_references);
    $result = $this->imageGenerationIntegration->generateImage($payload, $provider);
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
   * Build transition-gallery entries for room encounter/exploration changes.
   *
   * @return array<int, array<string, mixed>>
   *   Newest-first gallery entries.
   */
  protected function buildTransitionGalleryEntries(int $campaign_id, string $dungeon_id, string $room_id, array $room, int $room_session_id, array $portrait_references = []): array {
    if (!$this->hasRoomViewGalleryTable()) {
      return [];
    }

    $transitions = $this->loadRoomEncounterTransitions($campaign_id, $room_id, $room);
    if (empty($transitions)) {
      return [];
    }

    $existing_rows = [];
    foreach ($this->loadStoredGalleryEntries($campaign_id, $dungeon_id, $room_id, $room_session_id) as $row) {
      $existing_rows[(int) ($row['window_index'] ?? 0)] = $row;
    }

    $entries = [];
    foreach ($transitions as $transition) {
      $window_index = (int) ($transition['window_index'] ?? 0);
      $existing_row = $existing_rows[$window_index] ?? NULL;
      $image_ready = !empty($existing_row['image_url']) || !empty($existing_row['image_data_uri']);
      if (is_array($existing_row) && $image_ready) {
        $entries[] = $this->normalizeTransitionGalleryEntry($room, $existing_row, $transition);
        continue;
      }

      $generated_entry = $this->generateTransitionGalleryEntry(
        $campaign_id,
        $dungeon_id,
        $room_id,
        $room,
        $room_session_id,
        $transition,
        $portrait_references
      );
      if ($generated_entry !== NULL) {
        $entries[] = $generated_entry;
      }
    }

    usort($entries, static function (array $a, array $b): int {
      $created_compare = ((int) ($b['created'] ?? 0)) <=> ((int) ($a['created'] ?? 0));
      return $created_compare !== 0
        ? $created_compare
        : (($b['message_window']['index'] ?? 0) <=> ($a['message_window']['index'] ?? 0));
    });

    return $entries;
  }

  /**
   * Load exploration/encounter transition records for a room.
   *
   * @return array<int, array<string, mixed>>
   *   Chronological transition definitions.
   */
  protected function loadRoomEncounterTransitions(int $campaign_id, string $room_id, array $room): array {
    $rows = $this->database->select('combat_encounters', 'e')
      ->fields('e', ['id', 'status', 'current_round', 'created', 'updated'])
      ->condition('campaign_id', $campaign_id)
      ->condition('room_id', $room_id)
      ->orderBy('created', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    $transitions = [];
    $window_index = 1;
    foreach ($rows as $row) {
      $status = strtolower(trim((string) ($row['status'] ?? '')));
      if (!in_array($status, ['active', 'ended'], TRUE)) {
        continue;
      }

      $encounter_id = (int) ($row['id'] ?? 0);
      if ($encounter_id <= 0) {
        continue;
      }

      $participants = $this->loadEncounterParticipants($encounter_id);
      $transitions[] = $this->buildEncounterTransitionDefinition(
        $room,
        $row,
        $participants,
        'encounter_start',
        $window_index++
      );

      if ($status === 'ended') {
        $transitions[] = $this->buildEncounterTransitionDefinition(
          $room,
          $row,
          $participants,
          'exploration_return',
          $window_index++
        );
      }
    }

    return $transitions;
  }

  /**
   * Load encounter participants used to summarize the transition.
   *
   * @return array<int, array<string, mixed>>
   *   Participant rows.
   */
  protected function loadEncounterParticipants(int $encounter_id): array {
    $rows = $this->database->select('combat_participants', 'p')
      ->fields('p', ['name', 'team', 'status', 'is_defeated', 'initiative'])
      ->condition('encounter_id', $encounter_id)
      ->orderBy('initiative', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    return is_array($rows) ? $rows : [];
  }

  /**
   * Build a normalized transition definition from an encounter row.
   *
   * @return array<string, mixed>
   *   Transition definition.
   */
  protected function buildEncounterTransitionDefinition(array $room, array $encounter, array $participants, string $transition_type, int $window_index): array {
    $created = $transition_type === 'exploration_return'
      ? (int) ($encounter['updated'] ?? 0)
      : (int) ($encounter['created'] ?? 0);
    $encounter_id = (int) ($encounter['id'] ?? 0);

    return [
      'window_index' => $window_index,
      'transition_type' => $transition_type,
      'phase' => $transition_type === 'exploration_return' ? 'exploration' : 'encounter',
      'title' => $transition_type === 'exploration_return' ? 'Return to Exploration' : 'Encounter Begins',
      'label' => $transition_type === 'exploration_return' ? 'Encounter -> Exploration' : 'Exploration -> Encounter',
      'created' => $created,
      'encounter_id' => $encounter_id,
      'summary' => $this->buildEncounterTransitionSummary($room, $encounter, $participants, $transition_type, $window_index),
    ];
  }

  /**
   * Build the synthetic establishing-shot entry.
   */
  protected function buildEstablishingEntry(int $campaign_id, string $dungeon_id, string $room_id, array $room, ?string $campaign_room_cache_key = NULL, array $portrait_references = []): ?array {
    $result = $this->generateRoomViewImage($campaign_id, $dungeon_id, $room_id, $room, $campaign_room_cache_key, $portrait_references);
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
   * Load stored gallery entries for a room.
   *
   * @return array<int, array<string, mixed>>
   *   Stored rows.
   */
  protected function loadStoredGalleryEntries(int $campaign_id, string $dungeon_id, string $room_id, int $room_session_id): array {
    if (!$this->hasRoomViewGalleryTable()) {
      return [];
    }

    $rows = $this->database->select('dc_room_view_gallery', 'g')
      ->fields('g')
      ->condition('campaign_id', $campaign_id)
      ->condition('dungeon_id', $dungeon_id)
      ->condition('room_id', $room_id)
      ->condition('room_session_id', $room_session_id)
      ->condition('mode', self::GALLERY_MODE_TRANSITION)
      ->orderBy('window_index', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    if (!is_array($rows) || $rows === []) {
      return [];
    }

    foreach ($rows as $index => $row) {
      if (!is_array($row)) {
        continue;
      }
      $rows[$index] = $this->persistStoredGalleryImage($row);
    }

    return $rows;
  }

  /**
   * Generate and store a transition-gallery entry.
   */
  protected function generateTransitionGalleryEntry(int $campaign_id, string $dungeon_id, string $room_id, array $room, int $room_session_id, array $transition, array $portrait_references = []): ?array {
    $provider = $this->resolveRoomViewProvider($portrait_references);
    if (!$this->hasRoomViewGalleryTable() || $provider === NULL) {
      return NULL;
    }

    $payload = $this->buildTransitionGenerationPayload(
      $campaign_id,
      $dungeon_id,
      $room_id,
      $room,
      $transition,
      $portrait_references
    );
    $result = $this->imageGenerationIntegration->generateImage($payload, $provider);
    $output = is_array($result['output'] ?? NULL) ? $result['output'] : [];
    $storage = $this->persistGalleryGenerationResult($result);
    $image_url = $storage['image_url'] ?? ($output['image_url'] ?? NULL);
    $image_data_uri = $storage['image_data_uri'] ?? ($output['image_data_uri'] ?? NULL);
    if (empty($result['success']) || ($image_url === NULL && $image_data_uri === NULL)) {
      return NULL;
    }

    $now = time();
    $fields = [
      'campaign_id' => $campaign_id,
      'dungeon_id' => $dungeon_id,
      'room_id' => $room_id,
      'room_session_id' => $room_session_id,
      'window_index' => (int) ($transition['window_index'] ?? 0),
      'message_start_id' => 0,
      'message_end_id' => 0,
      'message_count' => 0,
      'summary_text' => (string) ($transition['summary'] ?? ''),
      'provider' => (string) ($result['provider'] ?? 'vertex'),
      'mode' => self::GALLERY_MODE_TRANSITION,
      'status' => (string) ($result['status'] ?? 'ready'),
      'image_url' => $image_url,
      'image_data_uri' => $image_data_uri,
      'mime_type' => $output['mime_type'] ?? NULL,
      'created' => (int) ($transition['created'] ?? $now),
      'updated' => $now,
    ];

    $this->database->merge('dc_room_view_gallery')
      ->key('room_session_id', $room_session_id)
      ->key('window_index', (int) ($transition['window_index'] ?? 0))
      ->fields($fields)
      ->execute();

    return $this->normalizeTransitionGalleryEntry($room, $fields, $transition);
  }

  /**
   * Persist a generated transition image into file-backed storage when possible.
   *
   * @param array<string, mixed> $generation_result
   *   Raw generation result payload.
   *
   * @return array{image_url:?string,image_data_uri:?string}
   *   The preferred stored image fields for the gallery row.
   */
  protected function persistGalleryGenerationResult(array $generation_result): array {
    $output = is_array($generation_result['output'] ?? NULL) ? $generation_result['output'] : [];
    $image_url = isset($output['image_url']) && is_string($output['image_url']) ? $output['image_url'] : NULL;
    $image_data_uri = isset($output['image_data_uri']) && is_string($output['image_data_uri']) ? $output['image_data_uri'] : NULL;

    if ($image_data_uri === NULL || $image_data_uri === '') {
      return [
        'image_url' => $image_url,
        'image_data_uri' => $image_data_uri,
      ];
    }

    $stored = $this->generatedImageRepository->persistGeneratedImage($generation_result);
    $stored_url = isset($stored['url']) && is_string($stored['url']) ? $stored['url'] : NULL;
    if ($stored_url !== NULL && $stored_url !== '') {
      return [
        'image_url' => $stored_url,
        'image_data_uri' => NULL,
      ];
    }

    return [
      'image_url' => $image_url,
      'image_data_uri' => $image_data_uri,
    ];
  }

  /**
   * Convert legacy inline gallery rows into file-backed URLs when possible.
   *
   * @param array<string, mixed> $row
   *   Stored gallery row.
   *
   * @return array<string, mixed>
   *   Updated gallery row.
   */
  protected function persistStoredGalleryImage(array $row): array {
    $existing_url = isset($row['image_url']) && is_string($row['image_url']) ? trim($row['image_url']) : '';
    $existing_data_uri = isset($row['image_data_uri']) && is_string($row['image_data_uri']) ? trim($row['image_data_uri']) : '';
    if ($existing_url !== '' || $existing_data_uri === '') {
      return $row;
    }

    $storage = $this->persistGalleryGenerationResult([
      'success' => TRUE,
      'provider' => (string) ($row['provider'] ?? 'vertex'),
      'status' => (string) ($row['status'] ?? 'ready'),
      'output' => [
        'image_url' => NULL,
        'image_data_uri' => $existing_data_uri,
        'mime_type' => $row['mime_type'] ?? NULL,
      ],
      'payload' => [],
    ]);

    $stored_url = isset($storage['image_url']) && is_string($storage['image_url']) ? trim($storage['image_url']) : '';
    if ($stored_url === '') {
      return $row;
    }

    $row['image_url'] = $stored_url;
    $row['image_data_uri'] = NULL;

    $gallery_row_id = isset($row['id']) ? (int) $row['id'] : 0;
    if ($gallery_row_id > 0) {
      $this->database->update('dc_room_view_gallery')
        ->fields([
          'image_url' => $stored_url,
          'image_data_uri' => NULL,
          'updated' => time(),
        ])
        ->condition('id', $gallery_row_id)
        ->execute();
    }

    return $row;
  }

  /**
   * Normalize a transition gallery entry for the frontend.
   */
  protected function normalizeTransitionGalleryEntry(array $room, array $row, array $transition): array {
    $window_index = (int) ($transition['window_index'] ?? 0);
    return [
      'id' => 'transition-' . $window_index,
      'entry_type' => 'phase_transition',
      'title' => (string) ($transition['title'] ?? ('Transition ' . $window_index)),
      'summary' => (string) ($row['summary_text'] ?? ($transition['summary'] ?? '')),
      'status' => (string) ($row['status'] ?? 'ready'),
      'provider' => (string) ($row['provider'] ?? 'vertex'),
      'mode' => self::GALLERY_MODE_TRANSITION,
      'created' => (int) ($transition['created'] ?? ($row['created'] ?? 0)),
      'message_window' => [
        'index' => $window_index,
        'count' => 0,
        'start_id' => 0,
        'end_id' => 0,
        'label' => (string) ($transition['label'] ?? 'Phase transition'),
      ],
      'transition' => [
        'type' => (string) ($transition['transition_type'] ?? ''),
        'phase' => (string) ($transition['phase'] ?? ''),
        'encounter_id' => (int) ($transition['encounter_id'] ?? 0),
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
   * Build a deterministic summary of a room phase transition.
   */
  protected function buildEncounterTransitionSummary(array $room, array $encounter, array $participants, string $transition_type, int $window_index): string {
    $room_name = trim((string) ($room['name'] ?? 'Unknown Room'));
    $encounter_id = (int) ($encounter['id'] ?? 0);
    $current_round = (int) ($encounter['current_round'] ?? 0);
    $active_names = [
      'player' => [],
      'enemy' => [],
      'neutral' => [],
      'other' => [],
    ];
    $defeated_names = [
      'player' => [],
      'enemy' => [],
      'neutral' => [],
      'other' => [],
    ];

    foreach ($participants as $participant) {
      $name = trim((string) ($participant['name'] ?? 'Unknown'));
      if ($name === '') {
        continue;
      }

      $team = strtolower(trim((string) ($participant['team'] ?? 'other')));
      if (!in_array($team, ['player', 'enemy', 'neutral'], TRUE)) {
        $team = 'other';
      }

      $is_defeated = !empty($participant['is_defeated']) || strtolower(trim((string) ($participant['status'] ?? ''))) === 'defeated';
      if ($is_defeated) {
        $defeated_names[$team][] = $name;
      }
      else {
        $active_names[$team][] = $name;
      }
    }

    $summary_parts = [
      sprintf(
        'Room: %s. Transition %d follows encounter %d.',
        $room_name !== '' ? $room_name : 'Unknown Room',
        $window_index,
        $encounter_id
      ),
    ];

    if ($transition_type === 'exploration_return') {
      $summary_parts[] = 'The encounter has ended and the room is settling back into exploration.';
      if (!empty($defeated_names['enemy'])) {
        $summary_parts[] = 'Defeated hostiles: ' . implode(', ', array_values(array_unique($defeated_names['enemy']))) . '.';
      }
      if (!empty($active_names['player'])) {
        $summary_parts[] = 'Surviving heroes: ' . implode(', ', array_values(array_unique($active_names['player']))) . '.';
      }
      if (!empty($active_names['neutral'])) {
        $summary_parts[] = 'Neutrals still present: ' . implode(', ', array_values(array_unique($active_names['neutral']))) . '.';
      }
      if (!empty($active_names['enemy'])) {
        $summary_parts[] = 'Remaining hostiles: ' . implode(', ', array_values(array_unique($active_names['enemy']))) . '.';
      }
      $summary_parts[] = 'Capture the aftermath as tactical combat releases back into exploration.';
    }
    else {
      $summary_parts[] = 'Exploration has just tightened into an encounter.';
      if ($current_round > 0) {
        $summary_parts[] = sprintf('The fight opens in round %d.', $current_round);
      }
      if (!empty($active_names['player'])) {
        $summary_parts[] = 'Heroes entering the fight: ' . implode(', ', array_values(array_unique($active_names['player']))) . '.';
      }
      if (!empty($active_names['enemy'])) {
        $summary_parts[] = 'Hostiles driving the conflict: ' . implode(', ', array_values(array_unique($active_names['enemy']))) . '.';
      }
      if (!empty($active_names['neutral'])) {
        $summary_parts[] = 'Neutrals caught in the scene: ' . implode(', ', array_values(array_unique($active_names['neutral']))) . '.';
      }
      $summary_parts[] = 'Capture the instant the room shifts from exploration into tactical danger.';
    }

    return trim(implode(' ', $summary_parts));
  }

  /**
   * Build image-generation payload for a transition-gallery image.
   *
   * @return array<string, mixed>
   *   Provider payload.
   */
  protected function buildTransitionGenerationPayload(int $campaign_id, string $dungeon_id, string $room_id, array $room, array $transition, array $portrait_references = []): array {
    $terrain_type = '';
    if (is_array($room['terrain'] ?? NULL)) {
      $terrain_type = trim((string) ($room['terrain']['type'] ?? ''));
    }
    else {
      $terrain_type = trim((string) ($room['terrain'] ?? ''));
    }

    return [
      'prompt' => $this->buildTransitionPrompt($room, $transition, $portrait_references),
      'style' => 'cinematic fantasy narrative illustration',
      'aspect_ratio' => '16:9',
      'negative_prompt' => 'text, captions, watermark, user interface, map grid, tactical overlay, split panel, collage, comic panels, character sheet, labels',
      'campaign_id' => $campaign_id,
      'dungeon_id' => $dungeon_id,
      'room_id' => $room_id,
      'terrain_type' => $terrain_type,
      'habitat_name' => trim((string) ($room['name'] ?? 'Unknown Room')),
      'entity_type' => 'room_view_gallery',
      'scene_index' => (int) ($transition['window_index'] ?? 0),
      'reference_images' => $this->normalizePortraitReferencesForPayload($portrait_references),
    ];
  }

  /**
   * Build the prompt for a transition snapshot image.
   */
  protected function buildTransitionPrompt(array $room, array $transition, array $portrait_references = []): string {
    $room_name = trim((string) ($room['name'] ?? 'Unknown Room'));
    $room_type = trim((string) ($room['room_type'] ?? 'room'));
    $size = trim((string) ($room['size_category'] ?? 'medium'));
    $description = trim((string) ($room['description'] ?? ''));
    $title = trim((string) ($transition['title'] ?? 'Phase Transition'));
    $label = trim((string) ($transition['label'] ?? 'Exploration <-> Encounter'));
    $summary = $this->normalizePlainText((string) ($transition['summary'] ?? ''));

    return trim("Create a player-facing fantasy RPG scene image for a major room phase transition.\n"
      . "Scene title: {$room_name} — {$title}.\n"
      . "Transition: {$label}.\n"
      . "Room type: {$room_type}. Scale: {$size}.\n"
      . ($description !== '' ? "Baseline room description: {$description}\n" : '')
      . $this->buildPortraitReferencePromptFragment($portrait_references)
      . "Transition summary: {$summary}\n"
      . "Requirements: depict the single most important visual beat at the instant the room shifts between exploration and encounter, stay grounded in the room's environment, preserve mood and continuity, and do not render visible text or UI.");
  }

  /**
   * Resolve the best provider for room-scene generation.
   */
  protected function resolveRoomViewProvider(array $portrait_references = []): ?string {
    $preferred = !empty($portrait_references) ? 'gemini' : 'vertex';
    return $this->imageGenerationIntegration->getReadyProvider($preferred);
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
  protected function buildGenerationPayload(int $campaign_id, string $dungeon_id, string $room_id, array $room, array $portrait_references = []): array {
    $terrain_type = '';
    if (is_array($room['terrain'] ?? NULL)) {
      $terrain_type = trim((string) ($room['terrain']['type'] ?? ''));
    }
    else {
      $terrain_type = trim((string) ($room['terrain'] ?? ''));
    }

    return [
      'prompt' => $this->buildPrompt($room, $portrait_references),
      'style' => 'cinematic fantasy environment illustration',
      'aspect_ratio' => '16:9',
      'negative_prompt' => 'text, captions, watermark, user interface, map grid, tactical overlay, split panel, collage, comic panels, character sheet, labels',
      'campaign_id' => $campaign_id,
      'dungeon_id' => $dungeon_id,
      'room_id' => $room_id,
      'terrain_type' => $terrain_type,
      'habitat_name' => trim((string) ($room['name'] ?? 'Unknown Room')),
      'entity_type' => 'room_view',
      'reference_images' => $this->normalizePortraitReferencesForPayload($portrait_references),
    ];
  }

  /**
   * Build the prompt for a player-facing room establishing shot.
   */
  protected function buildPrompt(array $room, array $portrait_references = []): string {
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
      . $this->buildPortraitReferencePromptFragment($portrait_references)
      . "Narrative description: {$base_description}\n"
      . "Requirements: depict the room as a grounded in-world scene, preserve environmental mood, avoid UI/map overlays, and do not render visible text.");
  }

  /**
   * Load portrait references for characters currently present in the room.
   *
   * @return array<int, array<string, mixed>>
   *   Portrait references.
   */
  protected function loadRoomPortraitReferences(int $campaign_id, string $room_id, ?string $campaign_room_cache_key = NULL, array $room = []): array {
    $room_refs = array_values(array_unique(array_filter([
      trim($room_id),
      trim((string) ($campaign_room_cache_key ?? '')),
      trim((string) ($room['room_id'] ?? '')),
      trim((string) ($room['id'] ?? '')),
    ])));

    $query = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id', 'character_id', 'name', 'type', 'portrait', 'state_data', 'location_type', 'location_ref'])
      ->condition('campaign_id', $campaign_id);

    $conditions = $query->orConditionGroup();
    if (!empty($room_refs)) {
      $room_conditions = $query->andConditionGroup()
        ->condition('location_type', 'room')
        ->condition('location_ref', $room_refs, 'IN');
      $conditions->condition($room_conditions);
    }
    $conditions->condition('type', 'pc');
    $rows = $query->condition($conditions)
      ->orderBy('name', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    if (!is_array($rows) || empty($rows)) {
      return [];
    }

    $include_binary_data = $this->imageGenerationIntegration->getReadyProvider('gemini') === 'gemini';
    $references = [];
    $seen_names = [];
    foreach ($rows as $row) {
      $portrait = $this->loadCharacterPortraitReference($row, $campaign_id, $include_binary_data);
      if ($portrait === NULL) {
        continue;
      }

      $state = [];
      if (!empty($row['state_data']) && is_string($row['state_data'])) {
        $decoded = json_decode($row['state_data'], TRUE);
        if (is_array($decoded)) {
          $state = $decoded;
        }
      }

      $name = trim((string) ($row['name'] ?? 'Unknown'));
      if ($name !== '' && isset($seen_names[strtolower($name)])) {
        continue;
      }
      $role = trim((string) ($row['type'] ?? 'character'));
      $summary = $this->buildPortraitReferenceSummary($name, $role, $state);
      $references[] = [
        'name' => $name !== '' ? $name : 'Unknown',
        'role' => $role !== '' ? $role : 'character',
        'description' => $summary,
        'url' => $portrait['url'] ?? NULL,
        'mime_type' => $portrait['mime_type'] ?? NULL,
        'data_uri' => $portrait['data_uri'] ?? NULL,
        'fingerprint' => $portrait['fingerprint'] ?? NULL,
      ];
      if ($name !== '') {
        $seen_names[strtolower($name)] = TRUE;
      }

      if (count($references) >= 6) {
        break;
      }
    }

    return $references;
  }

  /**
   * Load the best portrait reference for a campaign character row.
   *
   * @return array<string, mixed>|null
   *   Portrait metadata or NULL.
   */
  protected function loadCharacterPortraitReference(array $row, int $campaign_id, bool $include_binary_data): ?array {
    $candidate_ids = array_values(array_unique(array_filter([
      trim((string) ($row['id'] ?? '')),
      trim((string) ($row['character_id'] ?? '')),
    ])));

    foreach ($candidate_ids as $candidate_id) {
      $portrait_rows = $this->generatedImageRepository->loadImagesForObject(
        'dc_campaign_characters',
        $candidate_id,
        $campaign_id > 0 ? $campaign_id : NULL,
        'portrait',
        'original'
      );
      if (empty($portrait_rows) && $campaign_id > 0) {
        $portrait_rows = $this->generatedImageRepository->loadImagesForObject(
          'dc_campaign_characters',
          $candidate_id,
          NULL,
          'portrait',
          'original'
        );
      }

      if (empty($portrait_rows[0]) || !is_array($portrait_rows[0])) {
        continue;
      }

      $portrait_row = $portrait_rows[0];
      $resolved_url = $this->generatedImageRepository->resolveClientUrl($portrait_row);
      $data_uri = $include_binary_data ? $this->buildDataUriFromGeneratedImageRow($portrait_row) : NULL;
      return [
        'url' => is_string($resolved_url) && $resolved_url !== '' ? $resolved_url : NULL,
        'mime_type' => $portrait_row['mime_type'] ?? NULL,
        'data_uri' => $data_uri,
        'fingerprint' => $portrait_row['sha256'] ?? ($portrait_row['image_uuid'] ?? ($portrait_row['file_uri'] ?? $resolved_url)),
      ];
    }

    $legacy_url = trim((string) ($row['portrait'] ?? ''));
    if ($legacy_url !== '') {
      return [
        'url' => $legacy_url,
        'mime_type' => NULL,
        'data_uri' => NULL,
        'fingerprint' => $legacy_url,
      ];
    }

    $name = trim((string) ($row['name'] ?? ''));
    if ($name !== '') {
      $fallback = $this->loadPortraitReferenceByName($name, $include_binary_data);
      if ($fallback !== NULL) {
        return $fallback;
      }
    }

    return NULL;
  }

  /**
   * Load a fallback portrait by matching the latest portrait-bearing name.
   *
   * @return array<string, mixed>|null
   *   Portrait metadata or NULL.
   */
  protected function loadPortraitReferenceByName(string $name, bool $include_binary_data): ?array {
    $query = $this->database->select('dc_campaign_characters', 'c');
    $query->fields('c', ['id']);
    $query->condition('c.name', $name);
    $query->leftJoin('dc_generated_image_links', 'l', "l.table_name = 'dc_campaign_characters' AND l.object_id = CAST(c.id AS CHAR) AND l.slot = 'portrait' AND l.variant = 'original'");
    $query->leftJoin('dc_generated_images', 'i', 'i.id = l.image_id AND i.deleted = 0 AND i.status = :image_status', [':image_status' => 'ready']);
    $record = $query->isNotNull('i.id')
      ->orderBy('c.id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!is_array($record) || empty($record['id'])) {
      return NULL;
    }

    $portrait_rows = $this->generatedImageRepository->loadImagesForObject(
      'dc_campaign_characters',
      (string) $record['id'],
      NULL,
      'portrait',
      'original'
    );
    if (empty($portrait_rows[0]) || !is_array($portrait_rows[0])) {
      return NULL;
    }

    $portrait_row = $portrait_rows[0];
    $resolved_url = $this->generatedImageRepository->resolveClientUrl($portrait_row);
    $data_uri = $include_binary_data ? $this->buildDataUriFromGeneratedImageRow($portrait_row) : NULL;
    return [
      'url' => is_string($resolved_url) && $resolved_url !== '' ? $resolved_url : NULL,
      'mime_type' => $portrait_row['mime_type'] ?? NULL,
      'data_uri' => $data_uri,
      'fingerprint' => $portrait_row['sha256'] ?? ($portrait_row['image_uuid'] ?? ($portrait_row['file_uri'] ?? $resolved_url)),
    ];
  }

  /**
   * Convert a stored generated image row into a data URI when possible.
   */
  protected function buildDataUriFromGeneratedImageRow(array $row): ?string {
    $file_uri = trim((string) ($row['file_uri'] ?? ''));
    if ($file_uri === '') {
      return NULL;
    }

    $real_path = $this->fileSystem->realpath($file_uri);
    if (!is_string($real_path) || $real_path === '' || !is_readable($real_path)) {
      return NULL;
    }

    $bytes = @file_get_contents($real_path);
    if (!is_string($bytes) || $bytes === '') {
      return NULL;
    }

    $mime_type = trim((string) ($row['mime_type'] ?? 'image/png'));
    if (strpos($mime_type, 'image/') !== 0) {
      $mime_type = 'image/png';
    }

    return 'data:' . $mime_type . ';base64,' . base64_encode($bytes);
  }

  /**
   * Build a short portrait-grounding summary for prompt use.
   */
  protected function buildPortraitReferenceSummary(string $name, string $role, array $state): string {
    $basic_info = is_array($state['basicInfo'] ?? NULL) ? $state['basicInfo'] : [];
    $appearance = trim((string) ($state['appearance'] ?? ($basic_info['appearance'] ?? '')));
    $personality = trim((string) ($state['personality'] ?? ($basic_info['personality'] ?? '')));
    $class = trim((string) ($basic_info['class'] ?? ''));
    $ancestry = trim((string) ($basic_info['ancestry'] ?? ''));

    $parts = array_filter([
      $role !== '' ? $role : NULL,
      $ancestry !== '' ? 'ancestry ' . $ancestry : NULL,
      $class !== '' ? 'class ' . $class : NULL,
      $appearance !== '' ? 'appearance ' . $this->normalizePlainText($appearance) : NULL,
      $personality !== '' ? 'demeanor ' . $this->normalizePlainText($personality) : NULL,
    ]);

    $summary = trim(implode('; ', $parts));
    if ($summary === '') {
      $summary = $role !== '' ? $role : 'character';
    }

    return $name . ': ' . substr($summary, 0, 220);
  }

  /**
   * Build prompt guidance that anchors visible characters to their portraits.
   */
  protected function buildPortraitReferencePromptFragment(array $portrait_references): string {
    if (empty($portrait_references)) {
      return '';
    }

    $lines = [
      'Character portrait references: when any of these named characters appear in frame, match their established portrait likeness, clothing silhouette, species, and overall visual identity.',
    ];
    foreach ($portrait_references as $reference) {
      $description = $this->normalizePlainText((string) ($reference['description'] ?? ''));
      if ($description === '') {
        continue;
      }
      $lines[] = '- ' . $description;
    }

    return implode("\n", $lines) . "\n";
  }

  /**
   * Normalize portrait references for provider payloads.
   *
   * @return array<int, array<string, mixed>>
   *   Payload-safe references.
   */
  protected function normalizePortraitReferencesForPayload(array $portrait_references): array {
    $normalized = [];
    foreach ($portrait_references as $reference) {
      $normalized[] = [
        'name' => trim((string) ($reference['name'] ?? '')),
        'role' => trim((string) ($reference['role'] ?? '')),
        'description' => trim((string) ($reference['description'] ?? '')),
        'url' => !empty($reference['url']) ? (string) $reference['url'] : NULL,
        'mime_type' => !empty($reference['mime_type']) ? (string) $reference['mime_type'] : NULL,
        'data_uri' => !empty($reference['data_uri']) ? (string) $reference['data_uri'] : NULL,
        'fingerprint' => !empty($reference['fingerprint']) ? (string) $reference['fingerprint'] : NULL,
      ];
    }

    return $normalized;
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
    $row = $this->resolveStoredRoomViewRow($campaign_id, $cache_object_id, $room_meta);
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
   * Resolve the best stored room-view row across legacy link conventions.
   */
  protected function resolveStoredRoomViewRow(int $campaign_id, string $cache_object_id, array $room_meta): ?array {
    $object_ids = array_values(array_unique(array_filter([
      trim($cache_object_id),
      trim((string) ($room_meta['room_id'] ?? '')),
    ])));

    foreach ($object_ids as $object_id) {
      $rows = $this->generatedImageRepository->loadImagesForObject(
        'dc_campaign_rooms',
        $object_id,
        $campaign_id,
        'room_view',
        'establishing'
      );
      if (!empty($rows[0]) && is_array($rows[0])) {
        return $rows[0];
      }

      $rows = $this->generatedImageRepository->loadImagesForObject(
        'dc_campaign_rooms',
        $object_id,
        $campaign_id,
        'room_view',
        NULL
      );
      if (!empty($rows[0]) && is_array($rows[0])) {
        return $rows[0];
      }

      $rows = $this->generatedImageRepository->loadImagesForObject(
        'dc_campaign_rooms',
        $object_id,
        $campaign_id,
        NULL,
        NULL
      );
      if (!empty($rows[0]) && is_array($rows[0])) {
        return $rows[0];
      }
    }

    return NULL;
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

  /**
   * Determine whether the optional room-view gallery table exists.
   */
  protected function hasRoomViewGalleryTable(): bool {
    try {
      return $this->database->schema()->tableExists('dc_room_view_gallery');
    }
    catch (\Throwable $exception) {
      $this->logger->warning('Room view gallery availability check failed: @message', [
        '@message' => $exception->getMessage(),
      ]);
      return FALSE;
    }
  }

}
