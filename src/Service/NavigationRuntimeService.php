<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\RoomChat\RoomChatHistoryProjector;
use Drupal\dungeoncrawler_content\Service\RoomChat\RoomLocator;
use Psr\Log\LoggerInterface;

/**
 * Owns runtime execution for navigation actions and transition side-effects.
 *
 * Authority boundary:
 * - dc_campaign_rooms = runtime room authority
 * - dc_campaign_connections = runtime traversal authority
 * - dc_campaign_dungeons.dungeon_data = server-managed delivery snapshot only
 *
 * During the cutover period this service may still help refresh in-memory
 * snapshot payloads, but it must not become an alternate graph authority.
 */
class NavigationRuntimeService {

  protected const NAVIGATION_ACTION_SCHEMA_VERSION = 'navigation-action-v2';
  protected Connection $database;
  protected MapGeneratorService $mapGenerator;
  protected ?ExitConnectorAuthorityService $connectorDefinitionService;
  protected StateValidationService $stateValidationService;
  protected RoomLocator $roomLocator;
  protected RoomChatHistoryProjector $roomChatHistoryProjector;
  protected AiSessionManager $sessionManager;
  protected LoggerInterface $logger;

  public function __construct(
    Connection $database,
    MapGeneratorService $map_generator,
    StateValidationService $state_validation_service,
    RoomLocator $room_locator,
    RoomChatHistoryProjector $room_chat_history_projector,
    AiSessionManager $session_manager,
    LoggerChannelFactoryInterface $logger_factory,
    ?ExitConnectorAuthorityService $connector_definition_service = NULL
  ) {
    $this->database = $database;
    $this->mapGenerator = $map_generator;
    $this->connectorDefinitionService = $connector_definition_service;
    $this->stateValidationService = $state_validation_service;
    $this->roomLocator = $room_locator;
    $this->roomChatHistoryProjector = $room_chat_history_projector;
    $this->sessionManager = $session_manager;
    $this->logger = $logger_factory->get('dungeoncrawler_chat');
  }

  /**
   * Expand the active room into its canonical connector neighborhood.
   */
  public function expandCanonicalRoomNeighborhood(
    int $campaign_id,
    array &$dungeon_data,
    string $root_room_id,
    int $max_depth = 1
  ): void {
    $root_room_id = trim($root_room_id);
    if ($campaign_id <= 0 || $root_room_id === '') {
      return;
    }

    $dungeon_id = trim((string) (
      $dungeon_data['dungeon_id']
      ?? $dungeon_data['hex_map']['map_id']
      ?? $dungeon_data['map_id']
      ?? ''
    ));
    if ($dungeon_id === '') {
      throw new \RuntimeException('Navigation neighborhood expansion contract violation: dungeon_id is required.');
    }

    $known_room_ids = $this->collectDungeonPayloadRoomIds($dungeon_data);
    $queue = [[$root_room_id, 0]];
    $visited = [];
    while ($queue !== []) {
      [$room_id, $depth] = array_shift($queue);
      $room_id = trim((string) $room_id);
      $depth = (int) $depth;
      if ($room_id === '' || isset($visited[$room_id])) {
        continue;
      }
      $visited[$room_id] = TRUE;

      if (!isset($known_room_ids[$room_id])) {
        $materialized = $this->materializeCanonicalRoomForCampaign($campaign_id, $dungeon_data, $room_id, [
          'origin_room_id' => '',
          'visibility' => 'visible',
        ]);
        if (!$materialized) {
          throw new \RuntimeException(sprintf(
            'Navigation neighborhood expansion contract violation: canonical root room %s could not be materialized for campaign %d.',
            $room_id,
            $campaign_id
          ));
        }
        $known_room_ids[$room_id] = TRUE;
      }

      if ($depth >= $max_depth) {
        continue;
      }

      foreach ($this->resolveConnectorDefinitionService()->loadCanonicalConnectorsForRoom($room_id) as $connector_row) {
        if (!is_array($connector_row)) {
          continue;
        }
        $from_room_id = trim((string) ($connector_row['from_room_id'] ?? ''));
        $to_room_id = trim((string) ($connector_row['to_room_id'] ?? ''));
        if ($from_room_id === '' || $to_room_id === '' || $from_room_id === $to_room_id) {
          continue;
        }
        $target_room_id = $from_room_id === $room_id ? $to_room_id : ($to_room_id === $room_id ? $from_room_id : '');
        if ($target_room_id === '') {
          continue;
        }

        if (!isset($known_room_ids[$target_room_id])) {
          $materialized = $this->materializeCanonicalRoomForCampaign($campaign_id, $dungeon_data, $target_room_id, [
            'origin_room_id' => $room_id,
            'require_connector_to_existing_room' => TRUE,
          ]);
          if (!$materialized) {
            throw new \RuntimeException(sprintf(
              'Navigation neighborhood expansion contract violation: canonical adjacent room %s could not be materialized from %s for campaign %d.',
              $target_room_id,
              $room_id,
              $campaign_id
            ));
          }
          $known_room_ids[$target_room_id] = TRUE;
        }

        $this->persistCanonicalConnectorForCampaign($campaign_id, $dungeon_id, $connector_row);
        $this->appendCanonicalConnectorRowsToDungeonPayload($dungeon_data, [$connector_row]);

        if ($depth < $max_depth) {
          $queue[] = [$target_room_id, $depth + 1];
        }
      }
    }
  }

  /**
   * Execute navigate_to_location actions and return canonical navigation result.
   */
  public function handleNavigationActions(
    array $actions,
    int $campaign_id,
    string $origin_room_id,
    array $dungeon_data,
    string $gm_narrative
  ): ?array {
    $this->logger->notice('Navigation handoff enter: campaign=@campaign_id origin_room_id=@origin_room_id action_count=@action_count gm_narrative_chars=@gm_narrative_chars', [
      '@campaign_id' => $campaign_id,
      '@origin_room_id' => $origin_room_id,
      '@action_count' => count($actions),
      '@gm_narrative_chars' => strlen($gm_narrative),
    ]);

    $nav_actions = array_filter($actions, fn($a) => ($a['type'] ?? '') === 'navigate_to_location');
    if ($nav_actions === []) {
      $this->logger->notice('Navigation handoff exit: campaign=@campaign_id origin_room_id=@origin_room_id result=no_navigation_action', [
        '@campaign_id' => $campaign_id,
        '@origin_room_id' => $origin_room_id,
      ]);
      return NULL;
    }

    $nav = reset($nav_actions);
    $nav_payload = $this->buildCanonicalNavigationActionPayload(
      is_array($nav) ? $nav : [],
      $campaign_id,
      $origin_room_id
    );
    $this->validateNavigationActionPayload($nav_payload);
    $details = $nav_payload['details'];
    $destination = $details['destination'];
    $destination_desc = $details['destination_description'];

    $this->logger->notice('Navigation action parsed: campaign=@campaign_id origin_room_id=@origin_room_id destination=@destination destination_description=@destination_description nav_name=@nav_name', [
      '@campaign_id' => $campaign_id,
      '@origin_room_id' => $origin_room_id,
      '@destination' => $destination,
      '@destination_description' => $destination_desc,
      '@nav_name' => (string) ($nav['name'] ?? ''),
    ]);

    $narrative_context = [
      'gm_narrative' => $gm_narrative,
      'campaign_theme' => $dungeon_data['theme'] ?? 'high fantasy',
      'party_level' => $dungeon_data['generation_rules']['party_level_target'] ?? 1,
      'time_of_day' => $this->inferTimeOfDay($dungeon_data),
      'travel_type' => $details['travel_type'],
      'estimated_distance' => $details['estimated_distance'],
      'destination_description' => $destination_desc,
    ];

    $result = $this->mapGenerator->generateSetting(
      $campaign_id,
      $destination,
      $origin_room_id,
      $narrative_context
    );

    $this->logger->info('Navigation triggered: @dest → room @name (index @idx, @hexes hexes)', [
      '@dest' => $destination,
      '@name' => $result['room']['name'] ?? 'Unknown',
      '@idx' => $result['room_index'] ?? '?',
      '@hexes' => count($result['room']['hexes'] ?? []),
    ]);

    $this->logger->notice('Navigation handoff exit: campaign=@campaign_id origin_room_id=@origin_room_id destination=@destination result_room_id=@result_room_id result_room_name=@result_room_name result_source=@result_source entities_added=@entities_added', [
      '@campaign_id' => $campaign_id,
      '@origin_room_id' => $origin_room_id,
      '@destination' => $destination,
      '@result_room_id' => (string) ($result['room']['room_id'] ?? ''),
      '@result_room_name' => (string) ($result['room']['name'] ?? ''),
      '@result_source' => (string) ($result['source'] ?? 'unknown'),
      '@entities_added' => count($result['entities'] ?? []),
    ]);

    $navigation_result = [
      'type' => 'navigate_to_location',
      'origin_room_id' => $origin_room_id,
      'destination' => $destination,
      'destination_description' => $destination_desc,
      'travel_type' => $details['travel_type'],
      'estimated_distance' => $details['estimated_distance'],
      'new_room' => $result['room'],
      'new_room_index' => $result['room_index'],
      'entities' => $result['entities'] ?? [],
      'entities_added' => count($result['entities'] ?? []),
      'dungeon_data' => $result['dungeon_data'] ?? [],
      'source' => $result['source'] ?? NULL,
      'template_id' => $result['template_id'] ?? NULL,
    ];

    $client_payload = $this->mapGenerator->buildClientNavigationPayload($navigation_result);
    return $client_payload + [
      'new_room' => $navigation_result['new_room'],
      'new_room_index' => $navigation_result['new_room_index'],
      'entities_added' => $navigation_result['entities_added'],
      'dungeon_data' => $navigation_result['dungeon_data'],
      'source' => $navigation_result['source'],
      'template_id' => $navigation_result['template_id'],
    ];
  }

  /**
   * Resolve a room index after transition state mutations.
   */
  public function resolveNavigationTransitionRoomIndex(array $dungeon_data, string $room_id): ?int {
    return $this->roomLocator->findRoomIndex($dungeon_data['rooms'] ?? [], $room_id);
  }

  /**
   * Enforce the canonical navigation action contract.
   */
  public function validateNavigationActionPayload(array $payload): void {
    $validation = $this->stateValidationService->validateNavigationAction($payload);
    if (!empty($validation['valid'])) {
      return;
    }
    throw new \RuntimeException('Navigation action contract violation: ' . implode('; ', $validation['errors'] ?? []));
  }

  /**
   * Record location transition metadata in dungeon_data.
   */
  public function recordLocationTransition(array &$dungeon_data, array $origin_room_meta, array $navigation_result): void {
    $origin_name = $origin_room_meta['name'] ?? 'Unknown';
    $origin_id = $origin_room_meta['room_id'] ?? '';
    $dest_name = $navigation_result['new_room']['name'] ?? $navigation_result['destination'] ?? 'Unknown';
    $dest_id = $navigation_result['new_room']['room_id'] ?? '';
    $timestamp = date('c');

    if (!isset($dungeon_data['location_history'])) {
      $dungeon_data['location_history'] = [];
    }

    if (empty($dungeon_data['location_history'])) {
      $dungeon_data['location_history'][] = [
        'room_id' => $origin_id,
        'room_name' => $origin_name,
        'action' => 'started at',
        'timestamp' => $timestamp,
      ];
    }

    $dungeon_data['location_history'][] = [
      'room_id' => $origin_id,
      'room_name' => $origin_name,
      'action' => 'departed',
      'timestamp' => $timestamp,
    ];
    $dungeon_data['location_history'][] = [
      'room_id' => $dest_id,
      'room_name' => $dest_name,
      'action' => 'arrived at',
      'timestamp' => $timestamp,
    ];

    $dungeon_data['last_navigation'] = [
      'from_room_id' => $origin_id,
      'from_room_name' => $origin_name,
      'to_room_id' => $dest_id,
      'to_room_name' => $dest_name,
      'travel_type' => $navigation_result['travel_type'] ?? 'traveled',
      'timestamp' => $timestamp,
    ];
    if ($dest_id !== '') {
      $dungeon_data['current_room_id'] = $dest_id;
      $dungeon_data['active_room_id'] = $dest_id;
    }

    if (count($dungeon_data['location_history']) > 50) {
      $dungeon_data['location_history'] = array_slice($dungeon_data['location_history'], -50);
    }
  }

  /**
   * Append destination arrival narration to room chat/session.
   */
  public function appendDestinationArrivalNarration(
    int $campaign_id,
    int|string $dungeon_id,
    array &$dungeon_data,
    array $navigation_result
  ): void {
    $destination_room = is_array($navigation_result['new_room'] ?? NULL) ? $navigation_result['new_room'] : [];
    $destination_room_id = (string) ($destination_room['room_id'] ?? '');
    if ($destination_room_id === '') {
      return;
    }

    if (!isset($dungeon_data['rooms']) || !is_array($dungeon_data['rooms'])) {
      $dungeon_data['rooms'] = [];
    }

    $room_index = $this->roomLocator->findRoomIndex($dungeon_data['rooms'], $destination_room_id);
    if ($room_index === NULL) {
      throw new \RuntimeException(sprintf(
        'Navigation arrival narration contract violation: destination room %s is not materialized in campaign %d dungeon payload.',
        $destination_room_id,
        $campaign_id
      ));
    }

    if (!isset($dungeon_data['rooms'][$room_index]['chat']) || !is_array($dungeon_data['rooms'][$room_index]['chat'])) {
      $dungeon_data['rooms'][$room_index]['chat'] = [];
    }
    if (trim((string) ($dungeon_data['rooms'][$room_index]['description'] ?? '')) === '') {
      $dungeon_data['rooms'][$room_index]['description'] = (string) ($destination_room['description'] ?? '');
    }
    $this->roomChatHistoryProjector->injectRoomSceneNarratorIntroIfNeeded(
      $dungeon_data,
      $destination_room_id,
      RoomChatService::MAX_MESSAGES_PER_ROOM
    );

    $destination_name = trim((string) ($destination_room['name'] ?? $navigation_result['destination'] ?? $destination_room_id));
    $is_return_trip = $this->hasVisitedRoomId($dungeon_data, $destination_room_id);
    $arrival_text = $is_return_trip
      ? 'You return to ' . $destination_name . '.'
      : 'You arrive at ' . $destination_name . '.';

    $latest = end($dungeon_data['rooms'][$room_index]['chat']);
    if (!is_array($latest) || ($latest['message'] ?? '') !== $arrival_text || ($latest['speaker'] ?? '') !== 'System') {
      $dungeon_data['rooms'][$room_index]['chat'][] = [
        'speaker' => 'System',
        'message' => $arrival_text,
        'type' => 'system',
        'channel' => 'room',
        'timestamp' => date('c'),
        'character_id' => NULL,
        'user_id' => 0,
      ];

      $chat_count = count($dungeon_data['rooms'][$room_index]['chat']);
      if ($chat_count > RoomChatService::MAX_MESSAGES_PER_ROOM) {
        $dungeon_data['rooms'][$room_index]['chat'] = array_slice(
          $dungeon_data['rooms'][$room_index]['chat'],
          $chat_count - RoomChatService::MAX_MESSAGES_PER_ROOM
        );
      }
    }

    $destination_session_key = $this->sessionManager->roomChatSessionKey($campaign_id, $destination_room_id);
    $this->sessionManager->appendMessage($destination_session_key, $campaign_id, 'system', $arrival_text);
  }

  /**
   * Centralized canonical room materialization into campaign dungeon payload.
   *
   * @param array<string, mixed> $options
   *   Supported keys:
   *   - require_connector_to_existing_room: bool
   *   - origin_room_id: string
   *   - origin_hex: array{q:int,r:int}|null
   *   - target_hex: array{q:int,r:int}|null
   *   - explored: bool
   *   - visibility: string
   */
  public function materializeCanonicalRoomForCampaign(
    int $campaign_id,
    array &$dungeon_data,
    string $target_room_id,
    array $options = []
  ): bool {
    $target_room_id = trim($target_room_id);
    if ($campaign_id <= 0 || $target_room_id === '') {
      return FALSE;
    }
    if ($this->roomLocator->findRoomIndex((array) ($dungeon_data['rooms'] ?? []), $target_room_id) !== NULL) {
      return TRUE;
    }

    $canonical_row = $this->loadCanonicalRoomRow($target_room_id);
    if ($canonical_row === NULL) {
      return FALSE;
    }

    $layout_data = json_decode((string) ($canonical_row['layout_data'] ?? '{}'), TRUE);
    if (!is_array($layout_data)) {
      $layout_data = [];
    }
    $contents_data = json_decode((string) ($canonical_row['contents_data'] ?? '{}'), TRUE);
    if (!is_array($contents_data)) {
      $contents_data = [];
    }
    $environment_tags = json_decode((string) ($canonical_row['environment_tags'] ?? '[]'), TRUE);
    if (!is_array($environment_tags)) {
      $environment_tags = [];
    }

    $hexes = is_array($layout_data['hexes'] ?? NULL) ? $layout_data['hexes'] : [];
    if ($hexes === []) {
      throw new \RuntimeException(sprintf('Navigation room instantiation contract violation: canonical room %s has no hexes to instantiate.', $target_room_id));
    }

    $dungeon_id = trim((string) (
      $dungeon_data['dungeon_id']
      ?? $dungeon_data['hex_map']['map_id']
      ?? $dungeon_data['map_id']
      ?? ''
    ));
    $room_for_h3 = [
      'room_id' => $target_room_id,
      'hexes' => $hexes,
    ];
    $room_for_h3 = $this->mapGenerator->requireRoomHexH3Indexes($room_for_h3, 'canonical template room materialization');
    $hexes = is_array($room_for_h3['hexes'] ?? NULL) ? $room_for_h3['hexes'] : $hexes;

    $existing_room_ids = $this->collectDungeonPayloadRoomIds($dungeon_data);
    unset($existing_room_ids[$target_room_id]);
    $connector_rows = $this->loadCanonicalConnectorsLinkingRoomToExistingDungeonRooms($target_room_id, array_keys($existing_room_ids));
    $origin_room_id = trim((string) ($options['origin_room_id'] ?? ($dungeon_data['active_room_id'] ?? '')));
    $origin_can_bridge = $origin_room_id !== '' && isset($existing_room_ids[$origin_room_id]);
    if (!empty($options['require_connector_to_existing_room']) && $existing_room_ids !== [] && $connector_rows === []) {
      throw new \RuntimeException(sprintf(
        'Navigation room instantiation contract violation: canonical room "%s" has no canonical connector to any existing room in campaign %d dungeon payload.',
        $target_room_id,
        $campaign_id
      ));
    }
    if ($existing_room_ids !== [] && $connector_rows === [] && !$origin_can_bridge) {
      throw new \RuntimeException(sprintf(
        'Navigation room instantiation contract violation: canonical room "%s" has no bridge into the active dungeon graph (no canonical connector to existing rooms and no valid origin room transition).',
        $target_room_id
      ));
    }

    $visibility = trim((string) ($options['visibility'] ?? 'hidden'));
    if ($visibility === '') {
      $visibility = 'hidden';
    }
    $is_explored = !empty($options['explored']);
    $room_payload = [
      'room_id' => $target_room_id,
      'source_room_id' => trim((string) ($canonical_row['source_room_id'] ?? '')) !== ''
        ? (string) $canonical_row['source_room_id']
        : (string) ($canonical_row['room_id'] ?? $target_room_id),
      'name' => (string) ($canonical_row['name'] ?? $target_room_id),
      'description' => (string) ($canonical_row['description'] ?? ''),
      'hexes' => $hexes,
      'entry_points' => is_array($layout_data['entry_points'] ?? NULL) ? $layout_data['entry_points'] : [],
      'exit_points' => is_array($layout_data['exit_points'] ?? NULL) ? $layout_data['exit_points'] : [],
      'exits' => is_array($layout_data['exits'] ?? NULL) ? $layout_data['exits'] : [],
      'terrain' => is_array($layout_data['terrain'] ?? NULL) ? $layout_data['terrain'] : [],
      'lighting' => is_array($layout_data['lighting'] ?? NULL) ? $layout_data['lighting'] : [],
      'room_type' => (string) ($layout_data['room_type'] ?? 'room'),
      'state' => [
        'explored' => $is_explored,
        'explored_at' => $is_explored ? gmdate('c') : NULL,
        'cleared' => FALSE,
        'looted' => FALSE,
        'traps_disarmed' => FALSE,
        'visibility' => $visibility,
      ],
      'connections' => [],
      'entities' => NULL,
    ];

    if (!isset($dungeon_data['rooms']) || !is_array($dungeon_data['rooms'])) {
      $dungeon_data['rooms'] = [];
    }
    $dungeon_data['rooms'][] = $room_payload;
    if (!isset($dungeon_data['hex_map']) || !is_array($dungeon_data['hex_map'])) {
      $dungeon_data['hex_map'] = [];
    }
    if (!isset($dungeon_data['hex_map']['rooms']) || !is_array($dungeon_data['hex_map']['rooms'])) {
      $dungeon_data['hex_map']['rooms'] = [];
    }
    $dungeon_data['hex_map']['rooms'][] = $room_payload;
    if (!isset($dungeon_data['hex_map']['metadata']) || !is_array($dungeon_data['hex_map']['metadata'])) {
      $dungeon_data['hex_map']['metadata'] = [];
    }
    $dungeon_data['hex_map']['metadata']['total_rooms'] = count($dungeon_data['hex_map']['rooms']);

    $this->appendCanonicalConnectorRowsToDungeonPayload($dungeon_data, $connector_rows);
    if ($connector_rows !== []) {
      if ($dungeon_id === '') {
        throw new \RuntimeException(sprintf(
          'Navigation room instantiation contract violation: campaign %d target room %s cannot persist campaign connector rows without dungeon_id.',
          $campaign_id,
          $target_room_id
        ));
      }
      foreach ($connector_rows as $connector_row) {
        if (!is_array($connector_row)) {
          continue;
        }
        $this->persistCanonicalConnectorForCampaign($campaign_id, $dungeon_id, $connector_row);
      }
    }

    if ($origin_room_id !== '') {
      $this->appendTransitionConnection(
        $dungeon_data,
        $origin_room_id,
        $target_room_id,
        is_array($options['origin_hex'] ?? NULL) ? $options['origin_hex'] : NULL,
        is_array($options['target_hex'] ?? NULL) ? $options['target_hex'] : NULL
      );
    }

    $layout_record = [
      'hexes' => $hexes,
      'entry_points' => $room_payload['entry_points'],
      'exit_points' => $room_payload['exit_points'],
      'exits' => $room_payload['exits'],
      'terrain' => $room_payload['terrain'],
      'lighting' => $room_payload['lighting'],
    ];
    $this->mapGenerator->persistCanonicalCampaignRoom(
      $campaign_id,
      $target_room_id,
      (string) $room_payload['name'],
      (string) $room_payload['description'],
      $layout_record,
      $contents_data,
      $environment_tags,
      (string) $room_payload['source_room_id']
    );

    return TRUE;
  }

  /**
   * Normalize a navigation action payload to canonical runtime contract.
   */
  public function buildCanonicalNavigationActionPayload(
    array $action,
    int $campaign_id = 0,
    string $source_room_id = '',
    ?string $actor_id = NULL
  ): array {
    $details = is_array($action['details'] ?? NULL) ? $action['details'] : [];
    $state_changes = is_array($action['state_changes'] ?? NULL) ? $action['state_changes'] : [];
    $destination = trim((string) ($details['destination'] ?? ''));
    $normalized_source_room_id = $this->normalizeNavigationRoomId(
      $source_room_id !== '' ? $source_room_id : (string) ($details['source_room_id'] ?? $action['source_room_id'] ?? '')
    );
    $target_room_id = $this->normalizeNavigationRoomId(
      (string) ($details['destination_room_id'] ?? $details['target_room_id'] ?? $action['target_room_id'] ?? '')
    );
    if ($target_room_id === '') {
      $target_room_id = $this->buildNavigationRoomIdFromDestination($destination);
    }
    $resolved_actor_id = $this->normalizeNavigationActorId(
      $actor_id
      ?? (string) ($action['actor_id'] ?? $details['actor_id'] ?? ($state_changes['character']['actor_id'] ?? ''))
    );
    if ($resolved_actor_id === '') {
      $resolved_actor_id = 'party_lead';
    }

    $payload = [
      'schema_version' => self::NAVIGATION_ACTION_SCHEMA_VERSION,
      'campaign_id' => max(1, $campaign_id),
      'actor_id' => $resolved_actor_id,
      'source_room_id' => $normalized_source_room_id,
      'target_room_id' => $target_room_id,
      'transition_mode' => 'in_session',
      'type' => 'navigate_to_location',
      'name' => trim((string) ($action['name'] ?? 'Travel')),
      'details' => [
        'destination' => $destination,
        'destination_description' => trim((string) ($details['destination_description'] ?? '')),
        'travel_type' => trim((string) ($details['travel_type'] ?? 'walk')),
        'estimated_distance' => trim((string) ($details['estimated_distance'] ?? 'short')),
        'destination_room_id' => $target_room_id,
      ],
      'state_changes' => [
        'character' => is_array($state_changes['character'] ?? NULL) ? $state_changes['character'] : [],
        'room' => is_array($state_changes['room'] ?? NULL) ? $state_changes['room'] : [],
      ],
    ];

    if ($payload['details']['destination_description'] === '') {
      $payload['details']['destination_description'] = $payload['details']['destination'];
    }
    if ($payload['name'] === '') {
      $payload['name'] = 'Travel to ' . $payload['details']['destination'];
    }

    return $payload;
  }

  protected function normalizeNavigationActorId(string $actor_id): string {
    $actor_id = trim($actor_id);
    if ($actor_id === '') {
      return '';
    }
    $normalized = strtolower($actor_id);
    $normalized = preg_replace('/[^a-z0-9_-]+/', '_', $normalized) ?? '';
    return trim($normalized, '_');
  }

  protected function normalizeNavigationRoomId(string $room_id): string {
    $room_id = trim($room_id);
    if ($room_id === '') {
      return '';
    }
    if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $room_id)) {
      return $room_id;
    }
    $normalized = strtolower($room_id);
    $normalized = preg_replace('/[^a-z0-9_-]+/', '_', $normalized) ?? '';
    return trim($normalized, '_');
  }

  protected function buildNavigationRoomIdFromDestination(string $destination): string {
    $candidate = $this->normalizeNavigationRoomId($destination);
    if ($candidate !== '') {
      return $candidate;
    }
    throw new \RuntimeException('Navigation action contract violation: unable to derive target_room_id from destination text.');
  }

  protected function hasVisitedRoomId(array $dungeon_data, string $room_id): bool {
    if ($room_id === '') {
      return FALSE;
    }
    foreach ($dungeon_data['location_history'] ?? [] as $entry) {
      if (is_array($entry) && (string) ($entry['room_id'] ?? '') === $room_id) {
        return TRUE;
      }
    }
    $room_index = $this->roomLocator->findRoomIndex($dungeon_data['rooms'] ?? [], $room_id);
    return $room_index !== NULL && !empty($dungeon_data['rooms'][$room_index]['chat']);
  }

  protected function inferTimeOfDay(array $dungeon_data): string {
    foreach ($dungeon_data['rooms'] ?? [] as $room) {
      $changes = $room['gameplay_state']['environmental_changes'] ?? [];
      foreach (array_reverse($changes) as $change) {
        $details = $change['details'] ?? [];
        if (!empty($details['time_of_day'])) {
          return $details['time_of_day'];
        }
      }
    }
    return 'day';
  }

  protected function loadCanonicalRoomRow(string $room_id): ?array {
    $room_id = trim($room_id);
    if ($room_id === '') {
      return NULL;
    }

    $canonical_row = $this->database->select('dungeoncrawler_content_rooms', 'r')
      ->fields('r', ['room_id', 'source_room_id', 'name', 'description', 'environment_tags', 'layout_data', 'contents_data'])
      ->condition('room_id', $room_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (is_array($canonical_row)) {
      return $canonical_row;
    }

    $canonical_row = $this->database->select('dungeoncrawler_content_rooms', 'r')
      ->fields('r', ['room_id', 'source_room_id', 'name', 'description', 'environment_tags', 'layout_data', 'contents_data'])
      ->condition('source_room_id', $room_id)
      ->orderBy('updated', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    return is_array($canonical_row) ? $canonical_row : NULL;
  }

  /**
   * @return array<string, bool>
   */
  protected function collectDungeonPayloadRoomIds(array $dungeon_data): array {
    $room_ids = [];
    foreach ((array) ($dungeon_data['rooms'] ?? []) as $room) {
      if (!is_array($room)) {
        continue;
      }
      $room_id = trim((string) ($room['room_id'] ?? ''));
      if ($room_id !== '') {
        $room_ids[$room_id] = TRUE;
      }
    }
    return $room_ids;
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  protected function loadCanonicalConnectorsLinkingRoomToExistingDungeonRooms(string $room_id, array $existing_room_ids): array {
    $room_id = trim($room_id);
    $existing_room_ids = array_values(array_unique(array_filter(
      array_map('strval', $existing_room_ids),
      static fn(string $id): bool => trim($id) !== ''
    )));
    if ($room_id === '' || $existing_room_ids === []) {
      return [];
    }

    $query = $this->database->select('dungeoncrawler_content_connections', 'c')
      ->fields('c', [
        'connection_id',
        'from_room_id',
        'to_room_id',
        'from_hex_q',
        'from_hex_r',
        'to_hex_q',
        'to_hex_r',
        'kind',
        'direction',
        'default_state',
        'is_discovered_default',
      ]);
    $or = $query->orConditionGroup();
    $or->condition(
      $query->andConditionGroup()
        ->condition('from_room_id', $room_id)
        ->condition('to_room_id', $existing_room_ids, 'IN')
    );
    $or->condition(
      $query->andConditionGroup()
        ->condition('to_room_id', $room_id)
        ->condition('from_room_id', $existing_room_ids, 'IN')
    );

    $rows = array_values($query
      ->condition($or)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: []);
    if ($rows !== []) {
      return $rows;
    }

    // Root-cause remediation: canonical room layouts still carry authoritative
    // exit endpoint coordinates. If connector rows are missing, synthesize and
    // persist canonical connector definitions from layout exits, then re-query.
    $synthesized_count = $this->backfillCanonicalConnectorsFromRoomLayouts($room_id, $existing_room_ids);
    if ($synthesized_count <= 0) {
      return [];
    }

    $refresh_query = $this->database->select('dungeoncrawler_content_connections', 'c')
      ->fields('c', [
        'connection_id',
        'from_room_id',
        'to_room_id',
        'from_hex_q',
        'from_hex_r',
        'to_hex_q',
        'to_hex_r',
        'kind',
        'direction',
        'default_state',
        'is_discovered_default',
      ]);
    $refresh_or = $refresh_query->orConditionGroup();
    $refresh_or->condition(
      $refresh_query->andConditionGroup()
        ->condition('from_room_id', $room_id)
        ->condition('to_room_id', $existing_room_ids, 'IN')
    );
    $refresh_or->condition(
      $refresh_query->andConditionGroup()
        ->condition('to_room_id', $room_id)
        ->condition('from_room_id', $existing_room_ids, 'IN')
    );

    return array_values($refresh_query
      ->condition($refresh_or)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: []);
  }

  /**
   * Persist canonical connector rows from room-layout exits for the provided room set.
   */
  protected function backfillCanonicalConnectorsFromRoomLayouts(string $room_id, array $existing_room_ids): int {
    $room_ids = array_values(array_unique(array_filter(
      array_map('strval', array_merge([$room_id], $existing_room_ids)),
      static fn(string $id): bool => trim($id) !== ''
    )));
    if ($room_ids === []) {
      return 0;
    }

    $room_rows = $this->database->select('dungeoncrawler_content_rooms', 'r')
      ->fields('r', ['room_id', 'layout_data'])
      ->condition('room_id', $room_ids, 'IN')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    if (!is_array($room_rows) || $room_rows === []) {
      return 0;
    }

    $layouts_by_room = [];
    foreach ($room_rows as $row) {
      if (!is_array($row)) {
        continue;
      }
      $candidate_room_id = trim((string) ($row['room_id'] ?? ''));
      if ($candidate_room_id === '') {
        continue;
      }
      $layout_data = json_decode((string) ($row['layout_data'] ?? '{}'), TRUE);
      $layouts_by_room[$candidate_room_id] = is_array($layout_data) ? $layout_data : [];
    }
    if ($layouts_by_room === []) {
      return 0;
    }

    $connector_dungeon_id = $this->resolveCanonicalConnectorBackfillDungeonId($room_ids);
    $saved = 0;
    foreach ($layouts_by_room as $from_room_id => $layout_data) {
      $exits = is_array($layout_data['exits'] ?? NULL) ? $layout_data['exits'] : [];
      foreach ($exits as $exit_index => $exit) {
        if (!is_array($exit)) {
          continue;
        }
        $to_room_id = trim((string) ($exit['target_room_id'] ?? ''));
        if ($to_room_id === '' || !in_array($to_room_id, $room_ids, TRUE)) {
          continue;
        }
        if (!is_numeric($exit['q'] ?? NULL) || !is_numeric($exit['r'] ?? NULL)) {
          throw new \RuntimeException(sprintf(
            'Navigation connector backfill contract violation: room %s exit[%d] to %s is missing numeric q/r.',
            $from_room_id,
            $exit_index,
            $to_room_id
          ));
        }

        $to_entry_hex = $this->resolveCanonicalRoomEntryHex($to_room_id, $layouts_by_room[$to_room_id] ?? []);
        $kind = trim((string) ($exit['kind'] ?? $exit['link_type'] ?? ''));
        $normalized_kind = strtolower($kind);
        if (in_array($normalized_kind, ['room_transition', 'city_transition', 'location_transition', 'story_transition', 'travel_transition'], TRUE)) {
          $kind = 'hallway';
        }
        elseif ($normalized_kind === '') {
          $kind = 'hallway';
        }
        elseif (!in_array($normalized_kind, ConnectorDefinitionService::KINDS, TRUE)) {
          $kind = 'hallway';
        }
        else {
          $kind = $normalized_kind;
        }
        $direction = trim((string) ($exit['direction'] ?? ''));
        if ($direction === '') {
          $direction = 'bidirectional';
        }

        $this->resolveConnectorDefinitionService()->saveCanonicalConnector([
          'dungeon_id' => $connector_dungeon_id,
          'from_room_id' => $from_room_id,
          'to_room_id' => $to_room_id,
          'from_hex' => ['q' => (int) $exit['q'], 'r' => (int) $exit['r']],
          'to_hex' => $to_entry_hex,
          'kind' => $kind,
          'direction' => $direction,
          'default_state' => 'open',
          'state' => 'open',
          'travel_cost' => 1,
          'description' => trim((string) ($exit['label'] ?? '')),
          'is_discovered_default' => 1,
        ]);
        $saved++;
      }
    }

    return $saved;
  }

  /**
   * Resolve a stable canonical dungeon id for connector backfill rows.
   */
  protected function resolveCanonicalConnectorBackfillDungeonId(array $room_ids): string {
    if ($room_ids === []) {
      return 'canonical_room_layout_exits';
    }

    $query = $this->database->select('dungeoncrawler_content_connections', 'c')
      ->fields('c', ['dungeon_id'])
      ->range(0, 1)
      ->orderBy('updated', 'DESC');
    $or = $query->orConditionGroup()
      ->condition('from_room_id', $room_ids, 'IN')
      ->condition('to_room_id', $room_ids, 'IN');
    $row = $query->condition($or)->execute()->fetchAssoc();
    $dungeon_id = trim((string) ($row['dungeon_id'] ?? ''));
    return $dungeon_id !== '' ? $dungeon_id : 'canonical_room_layout_exits';
  }

  /**
   * Resolve the canonical entry hex for a room from layout data.
   *
   * @return array{q:int,r:int}
   */
  protected function resolveCanonicalRoomEntryHex(string $room_id, array $layout_data): array {
    $entry_points = is_array($layout_data['entry_points'] ?? NULL) ? $layout_data['entry_points'] : [];
    $candidate = $entry_points[0] ?? NULL;
    if (is_array($candidate) && is_numeric($candidate['q'] ?? NULL) && is_numeric($candidate['r'] ?? NULL)) {
      return ['q' => (int) $candidate['q'], 'r' => (int) $candidate['r']];
    }

    $hexes = is_array($layout_data['hexes'] ?? NULL) ? $layout_data['hexes'] : [];
    $candidate = $hexes[0] ?? NULL;
    if (is_array($candidate) && is_numeric($candidate['q'] ?? NULL) && is_numeric($candidate['r'] ?? NULL)) {
      return ['q' => (int) $candidate['q'], 'r' => (int) $candidate['r']];
    }

    throw new \RuntimeException(sprintf(
      'Navigation connector backfill contract violation: canonical room %s has no entry_points[0] or hexes[0] coordinate.',
      $room_id
    ));
  }

  protected function persistCanonicalConnectorForCampaign(
    int $campaign_id,
    string $dungeon_id,
    array $connector_row
  ): void {
    $from_room_id = trim((string) ($connector_row['from_room_id'] ?? ''));
    $to_room_id = trim((string) ($connector_row['to_room_id'] ?? ''));
    $from_hex = is_array($connector_row['from_hex'] ?? NULL)
      ? $connector_row['from_hex']
      : ((isset($connector_row['from_hex_q'], $connector_row['from_hex_r'])) ? [
        'q' => (int) $connector_row['from_hex_q'],
        'r' => (int) $connector_row['from_hex_r'],
      ] : NULL);
    $to_hex = is_array($connector_row['to_hex'] ?? NULL)
      ? $connector_row['to_hex']
      : ((isset($connector_row['to_hex_q'], $connector_row['to_hex_r'])) ? [
        'q' => (int) $connector_row['to_hex_q'],
        'r' => (int) $connector_row['to_hex_r'],
      ] : NULL);

    if ($from_room_id === '' || $to_room_id === '' || !is_array($from_hex) || !is_array($to_hex)) {
      throw new \RuntimeException('Navigation neighborhood expansion contract violation: canonical connectors require room ids and endpoint hexes.');
    }

    $this->resolveConnectorDefinitionService()->saveCampaignConnector($campaign_id, [
      'dungeon_id' => $dungeon_id,
      'from_room_id' => $from_room_id,
      'to_room_id' => $to_room_id,
      'from_hex' => $from_hex,
      'to_hex' => $to_hex,
      'direction' => (string) ($connector_row['direction'] ?? 'bidirectional'),
      'kind' => (string) ($connector_row['kind'] ?? 'hallway'),
      'default_state' => (string) ($connector_row['state'] ?? $connector_row['default_state'] ?? 'open'),
      'state' => (string) ($connector_row['state'] ?? $connector_row['default_state'] ?? 'open'),
      'trap_data' => is_array($connector_row['trap_data'] ?? NULL) ? $connector_row['trap_data'] : NULL,
      'lock_data' => is_array($connector_row['lock_data'] ?? NULL) ? $connector_row['lock_data'] : NULL,
      'requirements_data' => is_array($connector_row['requirements_data'] ?? NULL) ? $connector_row['requirements_data'] : NULL,
      'description' => (string) ($connector_row['description'] ?? ''),
      'travel_cost' => (int) ($connector_row['travel_cost'] ?? 0),
      'is_discovered_default' => (int) ($connector_row['is_discovered_default'] ?? $connector_row['is_discovered'] ?? 1),
      'connection_id' => (string) ($connector_row['connection_id'] ?? ''),
    ]);
  }

  protected function resolveConnectorDefinitionService(): ExitConnectorAuthorityService {
    if ($this->connectorDefinitionService instanceof ExitConnectorAuthorityService) {
      return $this->connectorDefinitionService;
    }
    $service = \Drupal::service('dungeoncrawler_content.exit_connector_authority');
    if (!$service instanceof ExitConnectorAuthorityService) {
      throw new \RuntimeException('Navigation runtime contract violation: exit_connector_authority is required.');
    }
    $this->connectorDefinitionService = $service;
    return $service;
  }

  protected function appendCanonicalConnectorRowsToDungeonPayload(array &$dungeon_data, array $connector_rows): void {
    if (!isset($dungeon_data['hex_map']) || !is_array($dungeon_data['hex_map'])) {
      $dungeon_data['hex_map'] = [];
    }
    if (!isset($dungeon_data['hex_map']['connections']) || !is_array($dungeon_data['hex_map']['connections'])) {
      $dungeon_data['hex_map']['connections'] = [];
    }

    $existing_keys = [];
    foreach ((array) $dungeon_data['hex_map']['connections'] as $connection) {
      if (!is_array($connection)) {
        continue;
      }
      $existing_id = trim((string) ($connection['connection_id'] ?? ''));
      if ($existing_id !== '') {
        $existing_keys['id:' . $existing_id] = TRUE;
      }
      $from_room_id = trim((string) ($connection['from_room_id'] ?? $connection['from_room'] ?? ''));
      $to_room_id = trim((string) ($connection['to_room_id'] ?? $connection['to_room'] ?? ''));
      if ($from_room_id !== '' && $to_room_id !== '') {
        $existing_keys['edge:' . $from_room_id . '>' . $to_room_id] = TRUE;
        $existing_keys['edge:' . $to_room_id . '>' . $from_room_id] = TRUE;
      }
    }

    foreach ($connector_rows as $row) {
      if (!is_array($row)) {
        continue;
      }
      $from_room_id = trim((string) ($row['from_room_id'] ?? ''));
      $to_room_id = trim((string) ($row['to_room_id'] ?? ''));
      if ($from_room_id === '' || $to_room_id === '') {
        continue;
      }

      $connection_id = trim((string) ($row['connection_id'] ?? ''));
      if ($connection_id !== '' && isset($existing_keys['id:' . $connection_id])) {
        continue;
      }
      if (isset($existing_keys['edge:' . $from_room_id . '>' . $to_room_id])) {
        continue;
      }

      $connection = [
        'connection_id' => $connection_id !== '' ? $connection_id : ($from_room_id . '__' . $to_room_id . '__passage__canonical'),
        'from_room' => $from_room_id,
        'from_room_id' => $from_room_id,
        'to_room' => $to_room_id,
        'to_room_id' => $to_room_id,
        'type' => trim((string) ($row['kind'] ?? '')) !== '' ? (string) $row['kind'] : 'passage',
        'kind' => trim((string) ($row['kind'] ?? '')) !== '' ? (string) $row['kind'] : 'passage',
        'state' => trim((string) ($row['state'] ?? $row['default_state'] ?? '')) !== '' ? (string) ($row['state'] ?? $row['default_state']) : 'open',
        'bidirectional' => strtolower(trim((string) ($row['direction'] ?? 'bidirectional'))) !== 'one_way',
        'is_discovered' => isset($row['is_discovered_default']) ? ((int) $row['is_discovered_default'] === 1) : TRUE,
        'is_passable' => strtolower(trim((string) ($row['state'] ?? $row['default_state'] ?? 'open'))) === 'open',
        'destination_type' => 'room',
        'destination_id' => $to_room_id,
      ];
      if (is_array($row['from_hex'] ?? NULL)) {
        $connection['from_hex'] = [
          'q' => (int) ($row['from_hex']['q'] ?? 0),
          'r' => (int) ($row['from_hex']['r'] ?? 0),
        ];
      }
      elseif (isset($row['from_hex_q'], $row['from_hex_r'])) {
        $connection['from_hex'] = [
          'q' => (int) $row['from_hex_q'],
          'r' => (int) $row['from_hex_r'],
        ];
      }
      if (is_array($row['to_hex'] ?? NULL)) {
        $connection['to_hex'] = [
          'q' => (int) ($row['to_hex']['q'] ?? 0),
          'r' => (int) ($row['to_hex']['r'] ?? 0),
        ];
      }
      elseif (isset($row['to_hex_q'], $row['to_hex_r'])) {
        $connection['to_hex'] = [
          'q' => (int) $row['to_hex_q'],
          'r' => (int) $row['to_hex_r'],
        ];
      }

      $dungeon_data['hex_map']['connections'][] = $connection;
      $existing_keys['id:' . $connection['connection_id']] = TRUE;
      $existing_keys['edge:' . $from_room_id . '>' . $to_room_id] = TRUE;
      $existing_keys['edge:' . $to_room_id . '>' . $from_room_id] = TRUE;
    }
  }

  protected function appendTransitionConnection(
    array &$dungeon_data,
    string $from_room_id,
    string $to_room_id,
    ?array $origin_hex = NULL,
    ?array $target_hex = NULL
  ): void {
    if ($from_room_id === '' || $to_room_id === '') {
      return;
    }
    if (!isset($dungeon_data['hex_map']) || !is_array($dungeon_data['hex_map'])) {
      $dungeon_data['hex_map'] = [];
    }
    if (!isset($dungeon_data['hex_map']['connections']) || !is_array($dungeon_data['hex_map']['connections'])) {
      $dungeon_data['hex_map']['connections'] = [];
    }

    foreach ((array) $dungeon_data['hex_map']['connections'] as $connection) {
      if (!is_array($connection)) {
        continue;
      }
      $existing_from = trim((string) ($connection['from_room'] ?? $connection['from_room_id'] ?? ''));
      $existing_to = trim((string) ($connection['to_room'] ?? $connection['to_room_id'] ?? ''));
      if (
        ($existing_from === $from_room_id && $existing_to === $to_room_id)
        || ($existing_from === $to_room_id && $existing_to === $from_room_id)
      ) {
        return;
      }
    }

    $connection = [
      'connection_id' => $from_room_id . '__' . $to_room_id . '__passage__unscoped',
      'from_room' => $from_room_id,
      'to_room' => $to_room_id,
      'type' => 'passage',
      'bidirectional' => TRUE,
      'is_discovered' => TRUE,
      'is_passable' => TRUE,
      'destination_type' => 'room',
      'destination_id' => $to_room_id,
    ];
    if (is_array($origin_hex) && isset($origin_hex['q'], $origin_hex['r'])) {
      $connection['from_hex'] = [
        'q' => (int) $origin_hex['q'],
        'r' => (int) $origin_hex['r'],
      ];
    }
    if (is_array($target_hex) && isset($target_hex['q'], $target_hex['r'])) {
      $connection['to_hex'] = [
        'q' => (int) $target_hex['q'],
        'r' => (int) $target_hex['r'],
      ];
    }
    $dungeon_data['hex_map']['connections'][] = $connection;
  }

}
