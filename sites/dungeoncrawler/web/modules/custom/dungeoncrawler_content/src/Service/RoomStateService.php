<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Event\RoomDiscoveredEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Manages per-room runtime state with optimistic versioning.
 */
class RoomStateService {

  private Connection $database;
  private LoggerInterface $logger;
  private EventDispatcherInterface $eventDispatcher;

  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    EventDispatcherInterface $event_dispatcher
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler');
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * Get room state for a campaign.
   *
   * @throws \InvalidArgumentException 404 when not found.
   */
  public function getState(int $campaign_id, string $room_id): array {
    // Static room definition (layout, contents, tags).
    $room = $this->database->select('dc_campaign_rooms', 'c')
      ->fields('c', ['room_id', 'campaign_id', 'name', 'description', 'environment_tags', 'layout_data', 'contents_data'])
      ->condition('campaign_id', $campaign_id)
      ->condition('room_id', $room_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$room) {
      throw new \InvalidArgumentException('Room not found', 404);
    }

    // Runtime state row.
    $record = $this->database->select('dc_campaign_room_states', 'r')
      ->fields('r', ['room_id', 'campaign_id', 'is_cleared', 'fog_state', 'last_visited', 'updated'])
      ->condition('campaign_id', $campaign_id)
      ->condition('room_id', $room_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$record) {
      throw new \InvalidArgumentException('Room state not found', 404);
    }

    $state = json_decode($record['fog_state'] ?? '', TRUE);
    if (!is_array($state)) {
      $state = [];
    }

    $state['isCleared'] = (bool) ($record['is_cleared'] ?? 0);

    $environment_tags = json_decode($room['environment_tags'] ?? '', TRUE);
    if (!is_array($environment_tags)) {
      $environment_tags = [];
    }

    $layout = json_decode($room['layout_data'] ?? '', TRUE);
    if (!is_array($layout)) {
      $layout = [];
    }

    $contents = json_decode($room['contents_data'] ?? '', TRUE);
    if (!is_array($contents)) {
      $contents = [];
    }

    // Fetch runtime entity instances for this room.
    $entities_query = $this->database->select('dc_campaign_characters', 'e')
      ->fields('e', ['instance_id', 'type', 'character_id', 'state_data'])
      ->condition('campaign_id', $campaign_id)
      ->condition('location_type', 'room')
      ->condition('location_ref', $room_id)
      ->execute();

    $runtime_entities = [];
    while ($entity = $entities_query->fetchAssoc()) {
      $entity_state = json_decode($entity['state_data'] ?? '{}', TRUE);
      if (!is_array($entity_state)) {
        $entity_state = [];
      }

      $runtime_entities[] = [
        'instanceId' => $entity['instance_id'],
        'type' => $entity['type'],
        'characterId' => (int) $entity['character_id'],
        'state' => $entity_state,
      ];
    }

    // Merge runtime entities with static contents (keep contents_data as template).
    // Runtime entities take precedence and are added to the entities array.
    if (!isset($contents['entities'])) {
      $contents['entities'] = [];
    }
    $contents['entities'] = array_merge($contents['entities'], $runtime_entities);

    // Limit layout hexes to those currently visible/LOS to the player to avoid leaking fogged areas.
    $visible_ids = [];
    if (isset($state['visibleHexIds']) && is_array($state['visibleHexIds'])) {
      $visible_ids = $state['visibleHexIds'];
    }
    elseif (isset($state['visible_hex_ids']) && is_array($state['visible_hex_ids'])) {
      $visible_ids = $state['visible_hex_ids'];
    }

    if (!empty($visible_ids) && isset($layout['hexes']) && is_array($layout['hexes'])) {
      $layout['hexes'] = array_values(array_filter($layout['hexes'], function ($hex) use ($visible_ids) {
        $id = $hex['id'] ?? $hex['hex_id'] ?? $hex['uuid'] ?? NULL;
        return $id !== NULL && in_array($id, $visible_ids, TRUE);
      }));
    }

    // Filter contents to only those placed in visible hexes, if placement data has hex refs.
    if (!empty($visible_ids) && isset($contents['objects']) && is_array($contents['objects'])) {
      $contents['objects'] = array_values(array_filter($contents['objects'], function ($obj) use ($visible_ids) {
        $hex_ref = $obj['hex_id'] ?? $obj['hexId'] ?? $obj['position']['hexId'] ?? NULL;
        return $hex_ref === NULL || in_array($hex_ref, $visible_ids, TRUE);
      }));
    }

    // Filter entities to only those in visible hexes or without location.
    // Also filter hidden/trap entities unless they are detected.
    if (!empty($visible_ids) && isset($contents['entities']) && is_array($contents['entities'])) {
      $contents['entities'] = array_values(array_filter($contents['entities'], function ($ent) use ($visible_ids) {
        // Check hex visibility.
        $hex_ref = $this->extractHexReference($ent);
        $in_visible_hex = $hex_ref === NULL || in_array($hex_ref, $visible_ids, TRUE);
        
        if (!$in_visible_hex) {
          return FALSE;
        }

        return $this->shouldShowEntity($ent);
      }));
    }
    elseif (isset($contents['entities']) && is_array($contents['entities'])) {
      // Even without visibility filtering, apply detection rules.
      $contents['entities'] = array_values(array_filter($contents['entities'], function ($ent) {
        return $this->shouldShowEntity($ent);
      }));
    }

    return [
      'campaignId' => $campaign_id,
      'roomId' => $record['room_id'],
      'room' => [
        'roomId' => $room['room_id'],
        'name' => $room['name'],
        'description' => $room['description'],
        'environmentTags' => $environment_tags,
        'layout' => $layout,
        'contents' => $contents,
      ],
      'state' => $state,
      'version' => (int) ($record['updated'] ?? 0),
      'updatedAt' => $record['updated'] ? date('c', (int) $record['updated']) : date('c'),
    ];
  }

  /**
   * Set room state with optimistic locking on updated timestamp.
   *
   * @throws \InvalidArgumentException 404 when base row missing and expectedVersion provided.
   * @throws \InvalidArgumentException 409 on version conflict.
   */
  public function setState(int $campaign_id, string $room_id, string $dungeon_id, array $state, ?int $expected_version): array {
    $record = $this->database->select('dc_campaign_room_states', 'r')
      ->fields('r', ['room_id', 'campaign_id', 'is_cleared', 'fog_state', 'last_visited', 'updated'])
      ->condition('campaign_id', $campaign_id)
      ->condition('room_id', $room_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$record && $expected_version !== NULL) {
      throw new \InvalidArgumentException('Room state not found', 404);
    }

    $current_version = (int) ($record['updated'] ?? 0);
    if ($expected_version !== NULL && $expected_version !== $current_version) {
      $this->logger->warning('Room state version conflict for room {room} campaign {campaign}: expected {expected} got {current}', [
        'room' => $room_id,
        'campaign' => $campaign_id,
        'expected' => $expected_version,
        'current' => $current_version,
      ]);
      throw new \InvalidArgumentException('Version conflict', 409);
    }

    $now = time();
    $is_cleared = !empty($state['isCleared']) ? 1 : 0;

    // Persist full state JSON; include dungeonId for enforcement/audit.
    $state_payload = $state;
    $state_payload['dungeonId'] = $dungeon_id;
    $fog_state = json_encode($state_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if ($record) {
      $this->database->update('dc_campaign_room_states')
        ->fields([
          'is_cleared' => $is_cleared,
          'fog_state' => $fog_state,
          'last_visited' => $now,
          'updated' => $now,
        ])
        ->condition('campaign_id', $campaign_id)
        ->condition('room_id', $room_id)
        ->execute();
    }
    else {
      // Room is being discovered for the first time
      $this->database->insert('dc_campaign_room_states')
        ->fields([
          'campaign_id' => $campaign_id,
          'room_id' => $room_id,
          'is_cleared' => $is_cleared,
          'fog_state' => $fog_state,
          'last_visited' => $now,
          'updated' => $now,
        ])
        ->execute();

      // Dispatch room discovered event for first discovery
      try {
        $room = $this->database->select('dc_campaign_rooms', 'c')
          ->fields('c', ['name', 'description', 'environment_tags'])
          ->condition('campaign_id', $campaign_id)
          ->condition('room_id', $room_id)
          ->range(0, 1)
          ->execute()
          ->fetchAssoc();

        if ($room) {
          $environment_tags = json_decode($room['environment_tags'] ?? '', TRUE);
          if (!is_array($environment_tags)) {
            $environment_tags = [];
          }

          $event = new RoomDiscoveredEvent(
            $campaign_id,
            $room_id,
            $dungeon_id,
            $room['name'] ?? 'Unknown Room',
            $room['description'] ?? '',
            $environment_tags,
            (bool) $is_cleared
          );

          $this->eventDispatcher->dispatch($event, RoomDiscoveredEvent::NAME);
        }
      }
      catch (\Exception $e) {
        $this->logger->error('Failed to dispatch room discovered event: @error', [
          '@error' => $e->getMessage(),
        ]);
      }
    }

    // Return fresh combined view with static room data.
    return $this->getState($campaign_id, $room_id);
  }

  /**
   * Extract hex reference from entity data.
   *
   * Supports multiple naming conventions:
   * - hex_id: snake_case (database convention)
   * - hexId: camelCase (API/frontend convention)
   * - position.hexId: nested structure
   * - state.hexId: state-embedded location
   *
   * @param array $entity
   *   Entity data array.
   *
   * @return string|null
   *   Hex reference or NULL if not found.
   */
  private function extractHexReference(array $entity): ?string {
    if (!empty($entity['hex_id'])) {
      return $entity['hex_id'];
    }
    if (!empty($entity['hexId'])) {
      return $entity['hexId'];
    }
    if (isset($entity['position']) && is_array($entity['position']) && !empty($entity['position']['hexId'])) {
      return $entity['position']['hexId'];
    }
    if (isset($entity['state']) && is_array($entity['state']) && !empty($entity['state']['hexId'])) {
      return $entity['state']['hexId'];
    }
    return NULL;
  }

  /**
   * Determine if entity should be shown based on detection rules.
   *
   * @param array $entity
   *   Entity data array.
   *
   * @return bool
   *   TRUE if entity should be visible, FALSE otherwise.
   */
  private function shouldShowEntity(array $entity): bool {
    $entity_type = $entity['type'] ?? '';
    $is_hidden = !empty($entity['hidden']) || !empty($entity['state']['hidden']);
    $is_detected = !empty($entity['detected']) || !empty($entity['state']['detected']);

    // Traps are hidden by default unless detected.
    if ($entity_type === 'trap' && !$is_detected) {
      return FALSE;
    }

    // Hidden entities are not shown unless detected.
    if ($is_hidden && !$is_detected) {
      return FALSE;
    }

    return TRUE;
  }

}
