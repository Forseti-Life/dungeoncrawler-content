<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Wave 2 extraction: navigation transition coordination methods.
 */
trait EncounterNavigationTransitionCoordinatorTrait {

  /**
   * Enters a room and ensures an encounter-framework context is active.
   */
  public function enterRoomFramework(?string $actor_id, string $target_room_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $timing_started_at = microtime(TRUE);
    $timing_breakdown = [];
    $target_room_id = trim($target_room_id);
    if ($target_room_id === '') {
      return ['error' => 'No target room specified.'];
    }

    $rebuild_started_at = microtime(TRUE);
    $dungeon_data = $this->rebuildAuthoritativeRuntimeGraph($campaign_id, $dungeon_data, $target_room_id);
    $timing_breakdown['rebuild_runtime_graph_ms'] = (microtime(TRUE) - $rebuild_started_at) * 1000.0;

    $this->logger->info('Encounter transition requested: campaign={campaign_id} actor={actor} from_room={from_room} target_room={target_room} connection_id={connection_id}', [
      'campaign_id' => $campaign_id,
      'actor' => (string) ($actor_id ?? ''),
      'from_room' => (string) ($dungeon_data['active_room_id'] ?? ''),
      'target_room' => $target_room_id,
      'connection_id' => (string) ($params['connection_id'] ?? ''),
    ]);

    $capability = NULL;
    $capability_resolution_started_at = microtime(TRUE);
    if (!empty($dungeon_data['active_room_id']) && (string) $dungeon_data['active_room_id'] !== $target_room_id) {
      $capability = $this->resolveRoomTransitionCapability($dungeon_data, $target_room_id, $params);
      if ($capability === NULL) {
        $unreachable_diagnostics = $this->buildTransitionUnreachableDiagnostics($dungeon_data, $target_room_id);
        $this->logger->warning('Encounter transition capability missing: campaign={campaign_id} actor={actor} from_room={from_room} target_room={target_room} suggested_via_room={suggested_via_room} available_targets={available_targets}', [
          'campaign_id' => $campaign_id,
          'actor' => (string) ($actor_id ?? ''),
          'from_room' => (string) ($dungeon_data['active_room_id'] ?? ''),
          'target_room' => $target_room_id,
          'suggested_via_room' => (string) ($unreachable_diagnostics['suggested_via_room_id'] ?? ''),
          'available_targets' => implode(', ', (array) ($unreachable_diagnostics['available_targets'] ?? [])),
        ]);
        $error = "Room '$target_room_id' is not reachable from the active room.";
        $suggested_via_room_id = (string) ($unreachable_diagnostics['suggested_via_room_id'] ?? '');
        if ($suggested_via_room_id !== '') {
          $error .= sprintf(
            " Route hint: transition to '%s' first, then to '%s'.",
            $this->resolveRoomLabelById($dungeon_data, $suggested_via_room_id),
            $this->resolveRoomLabelById($dungeon_data, $target_room_id)
          );
        }

        return ['error' => $error];
      }
      if (empty($capability['available'])) {
        $this->logger->warning('Encounter transition capability blocked: campaign={campaign_id} actor={actor} target_room={target_room} blocked_reason={blocked_reason}', [
          'campaign_id' => $campaign_id,
          'actor' => (string) ($actor_id ?? ''),
          'target_room' => $target_room_id,
          'blocked_reason' => (string) ($capability['blocked_reason'] ?? 'blocked'),
        ]);
        return ['error' => sprintf("Room '%s' is not available for transition: %s.", $target_room_id, (string) ($capability['blocked_reason'] ?? 'blocked'))];
      }
    }
    $timing_breakdown['resolve_capability_ms'] = (microtime(TRUE) - $capability_resolution_started_at) * 1000.0;

    $materialize_room_started_at = microtime(TRUE);
    $room = $this->findRoomById($dungeon_data, $target_room_id);
    if ($room === NULL) {
      if (!$this->materializeCanonicalRoomForTransition($campaign_id, $dungeon_data, $target_room_id, $capability)) {
        $this->logger->warning('Encounter transition materialization failed: campaign={campaign_id} target_room={target_room} capability_connection_id={connection_id}', [
          'campaign_id' => $campaign_id,
          'target_room' => $target_room_id,
          'connection_id' => (string) ($capability['connection_id'] ?? ''),
        ]);
        return ['error' => "Room '$target_room_id' does not exist."];
      }
      $this->logger->info('Encounter transition materialized canonical room: campaign={campaign_id} target_room={target_room}', [
        'campaign_id' => $campaign_id,
        'target_room' => $target_room_id,
      ]);
      $room = $this->findRoomById($dungeon_data, $target_room_id);
      if ($room === NULL) {
        throw new \RuntimeException(sprintf('Encounter transition contract violation: materialized room %s was not present in dungeon payload after instantiation.', $target_room_id));
      }
    }
    $timing_breakdown['materialize_room_ms'] = (microtime(TRUE) - $materialize_room_started_at) * 1000.0;

    $neighbor_preseed_started_at = microtime(TRUE);
    $this->enqueueLinkedRoomNeighborPreseed($campaign_id, $dungeon_data, $target_room_id);
    $timing_breakdown['neighbor_preseed_ms'] = (microtime(TRUE) - $neighbor_preseed_started_at) * 1000.0;

    $from_room = $dungeon_data['active_room_id'] ?? NULL;
    $dungeon_data['active_room_id'] = $target_room_id;
    $dungeon_data['current_room_id'] = $target_room_id;
    $runtime_dungeon_id = trim((string) (
      $dungeon_data['dungeon_id']
      ?? $dungeon_data['hex_map']['map_id']
      ?? $dungeon_data['map_id']
      ?? ''
    ));
    if ($runtime_dungeon_id === '') {
      throw new \RuntimeException(sprintf(
        'Encounter transition contract violation: campaign %d target room %s has no runtime dungeon_id for launch-slice provisioning.',
        $campaign_id,
        $target_room_id
      ));
    }
    if (!$this->h3ProjectionQueue instanceof H3ProjectionQueueService) {
      throw new \RuntimeException(sprintf(
        'Encounter transition contract violation: H3 projection queue service is required for campaign %d room %s transition provisioning.',
        $campaign_id,
        $target_room_id
      ));
    }
    $launch_slice_scope = $this->resolveLaunchSliceRoomScopeFromDungeonData($dungeon_data, is_scalar($from_room) ? (string) $from_room : '');
    if ($launch_slice_scope === []) {
      throw new \RuntimeException(sprintf(
        'Encounter transition contract violation: launch-slice scope is empty for campaign %d dungeon %s room %s.',
        $campaign_id,
        $runtime_dungeon_id,
        $target_room_id
      ));
    }
    $launch_slice_started_at = microtime(TRUE);
    $this->h3ProjectionQueue->provisionLaunchSliceNow($campaign_id, $runtime_dungeon_id, $launch_slice_scope);
    $timing_breakdown['launch_slice_ms'] = (microtime(TRUE) - $launch_slice_started_at) * 1000.0;
    $game_state['phase'] = 'encounter';
    $game_state['exploration']['previous_room'] = $from_room;

    $entry_resolution_started_at = microtime(TRUE);
    $entry_hex = $this->resolveTransitionEntryHex($room, $params, $capability);
    $entry_facing = isset($params['entry_facing']) ? (int) $params['entry_facing'] : 0;
    if ($actor_id) {
      $this->moveEntityToRoom(
        $dungeon_data,
        $actor_id,
        $target_room_id,
        $entry_hex,
        $entry_facing
      );
    }
    $timing_breakdown['entry_and_actor_move_ms'] = (microtime(TRUE) - $entry_resolution_started_at) * 1000.0;

    $room_scene_events_started_at = microtime(TRUE);
    $events = [];
    if (!empty($game_state['encounter_id']) && $from_room !== NULL && (string) $from_room !== $target_room_id) {
      $exit_result = $this->onExit($game_state, $dungeon_data, $campaign_id);
      $events = array_merge($events, is_array($exit_result['events'] ?? NULL) ? $exit_result['events'] : []);
    }

    $events[] = GameEventLogger::buildEvent('room_entered', 'encounter', $actor_id, [
      'from_room' => $from_room,
      'to_room' => $target_room_id,
    ], (string) ($room['description'] ?? $room['name'] ?? ''));
    $events = array_values($events);

    $bootstrap_context = $this->resolveBootstrapEncounterInitialization($target_room_id, $game_state, $dungeon_data, $campaign_id, $actor_id);
    if (!empty($bootstrap_context['combat_context']['should_trigger'])) {
      $enter_result = $this->onEnter($bootstrap_context['combat_context'], $game_state, $dungeon_data, $campaign_id);
      $events = array_merge($events, is_array($enter_result['events'] ?? NULL) ? $enter_result['events'] : []);
    }
    else {
      $events = array_merge(
        $events,
        $this->startRoomSceneEncounter($actor_id, $target_room_id, $game_state, $dungeon_data, $campaign_id, $bootstrap_context['room'] ?? $room)
      );
    }
    $timing_breakdown['room_scene_events_ms'] = (microtime(TRUE) - $room_scene_events_started_at) * 1000.0;

    // Persist the room-scene intro into the instantiated room chat so the UI can
    // render the authoritative room description on room entry.
    $room_intro_started_at = microtime(TRUE);
    $this->roomChatService->injectRoomSceneNarratorIntroIfNeeded($dungeon_data, $target_room_id);
    $timing_breakdown['room_intro_ms'] = (microtime(TRUE) - $room_intro_started_at) * 1000.0;

    $receipt_build_started_at = microtime(TRUE);
    $transition_navigation_receipt = $this->buildTransitionNavigationReceipt(
      $dungeon_data,
      is_scalar($from_room) ? (string) $from_room : '',
      $target_room_id,
      $entry_hex,
      $room
    );
    $timing_breakdown['build_navigation_receipt_ms'] = (microtime(TRUE) - $receipt_build_started_at) * 1000.0;

    $total_ms = (microtime(TRUE) - $timing_started_at) * 1000.0;
    if ($total_ms >= self::NAVIGATION_TIMING_SLOW_THRESHOLD_MS) {
      $this->logger->notice(
        'Navigation timing: enterRoomFramework slow (campaign={campaign_id}, actor={actor}, from_room={from_room}, target_room={target_room}, total_ms={total_ms}, rebuild_runtime_graph_ms={rebuild_runtime_graph_ms}, resolve_capability_ms={resolve_capability_ms}, materialize_room_ms={materialize_room_ms}, neighbor_preseed_ms={neighbor_preseed_ms}, launch_slice_ms={launch_slice_ms}, entry_and_actor_move_ms={entry_and_actor_move_ms}, room_scene_events_ms={room_scene_events_ms}, room_intro_ms={room_intro_ms}, build_navigation_receipt_ms={build_navigation_receipt_ms}, event_count={event_count})',
        [
          'campaign_id' => $campaign_id,
          'actor' => (string) ($actor_id ?? ''),
          'from_room' => (string) ($from_room ?? ''),
          'target_room' => $target_room_id,
          'total_ms' => round($total_ms, 2),
          'rebuild_runtime_graph_ms' => round((float) ($timing_breakdown['rebuild_runtime_graph_ms'] ?? 0.0), 2),
          'resolve_capability_ms' => round((float) ($timing_breakdown['resolve_capability_ms'] ?? 0.0), 2),
          'materialize_room_ms' => round((float) ($timing_breakdown['materialize_room_ms'] ?? 0.0), 2),
          'neighbor_preseed_ms' => round((float) ($timing_breakdown['neighbor_preseed_ms'] ?? 0.0), 2),
          'launch_slice_ms' => round((float) ($timing_breakdown['launch_slice_ms'] ?? 0.0), 2),
          'entry_and_actor_move_ms' => round((float) ($timing_breakdown['entry_and_actor_move_ms'] ?? 0.0), 2),
          'room_scene_events_ms' => round((float) ($timing_breakdown['room_scene_events_ms'] ?? 0.0), 2),
          'room_intro_ms' => round((float) ($timing_breakdown['room_intro_ms'] ?? 0.0), 2),
          'build_navigation_receipt_ms' => round((float) ($timing_breakdown['build_navigation_receipt_ms'] ?? 0.0), 2),
          'event_count' => count($events),
        ]
      );
    }

    return [
      'transitioned' => $from_room !== $target_room_id,
      'from_room' => $from_room,
      'to_room' => $target_room_id,
      'entry_hex' => $entry_hex,
      'navigation_capabilities' => $transition_navigation_receipt['navigation_capabilities'],
      'navigation' => $transition_navigation_receipt,
      'events' => $events,
      'time_effects' => $this->buildTransitionTimeEffects($actor_id, $from_room, $target_room_id, $capability, $params),
      'mutations' => $actor_id ? [
        ['entity_id' => $actor_id, 'field' => 'placement.room_id', 'to' => $target_room_id],
        ['entity_id' => $actor_id, 'field' => 'placement.hex', 'to' => $entry_hex],
        ['entity_id' => $actor_id, 'field' => 'placement.facing', 'to' => $this->normalizeFacingDirection($entry_facing)],
        ['entity_id' => $actor_id, 'field' => 'placement.h3_index_res14', 'to' => $this->resolveRoomHexH3IndexRes14($dungeon_data, $target_room_id, $entry_hex)],
      ] : [],
    ];
  }

  /**
   * Bootstrap the active room into room-scene encounter mode without transition flow.
   *
   * This is a read-lane-safe initializer for fresh campaign startup. It avoids
   * transition validation, graph mutation/materialization, and launch-slice
   * provisioning while still establishing the room-scene encounter framework.
   */


  /**
   * Bootstrap the active room into room-scene encounter mode without transition flow.
   *
   * This is a read-lane-safe initializer for fresh campaign startup. It avoids
   * transition validation, graph mutation/materialization, and launch-slice
   * provisioning while still establishing the room-scene encounter framework.
   */
  public function bootstrapRoomSceneFramework(string $room_id, array &$game_state, array &$dungeon_data, int $campaign_id, ?string $preferred_actor_id = NULL): array {
    $room_id = trim($room_id);
    if ($room_id === '') {
      return ['error' => 'No room specified for room-scene bootstrap.'];
    }

    $bootstrap_context = $this->resolveBootstrapEncounterInitialization($room_id, $game_state, $dungeon_data, $campaign_id, $preferred_actor_id);
    $room = $bootstrap_context['room'] ?? NULL;
    if (!is_array($room)) {
      return ['error' => sprintf("Room '%s' does not exist.", $room_id)];
    }

    $dungeon_data['active_room_id'] = $room_id;
    $dungeon_data['current_room_id'] = $room_id;
    $game_state['phase'] = 'encounter';
    if (!isset($game_state['exploration']) || !is_array($game_state['exploration'])) {
      $game_state['exploration'] = [];
    }
    if (!array_key_exists('previous_room', $game_state['exploration'])) {
      $game_state['exploration']['previous_room'] = NULL;
    }

    $events = [
      GameEventLogger::buildEvent('room_entered', 'encounter', NULL, [
        'from_room' => NULL,
        'to_room' => $room_id,
      ], (string) ($room['description'] ?? $room['name'] ?? '')),
    ];

    if (!empty($bootstrap_context['combat_context']['should_trigger'])) {
      $enter_result = $this->onEnter($bootstrap_context['combat_context'], $game_state, $dungeon_data, $campaign_id);
      $events = array_merge($events, is_array($enter_result['events'] ?? NULL) ? $enter_result['events'] : []);
    }
    else {
      $events = array_merge(
        $events,
        $this->startRoomSceneEncounter(NULL, $room_id, $game_state, $dungeon_data, $campaign_id, $room)
      );
      $this->roomChatService->injectRoomSceneNarratorIntroIfNeeded($dungeon_data, $room_id);
    }

    return [
      'success' => TRUE,
      'events' => array_values($events),
      'mutations' => [],
      'time_effects' => [],
      'phase_transition' => NULL,
      'narration' => NULL,
    ];
  }

  /**
   * Resolve unified bootstrap initialization for room entry and cold start.
   *
   * @return array{room:?array,combat_context:array<string,mixed>}
   *   Shared bootstrap context.
   */
  protected function resolveBootstrapEncounterInitialization(string $room_id, array &$game_state, array &$dungeon_data, int $campaign_id, ?string $actor_id = NULL): array {
    $room_id = trim($room_id);
    $room = $room_id !== '' ? $this->findRoomById($dungeon_data, $room_id) : NULL;
    if ($room === NULL && $campaign_id > 0 && $room_id !== '') {
      $dungeon_data = $this->rebuildAuthoritativeRuntimeGraph($campaign_id, $dungeon_data, $room_id);
      $room = $this->findRoomById($dungeon_data, $room_id);
    }

    return [
      'room' => $room,
      'combat_context' => $this->buildCombatEncounterContext($room_id, $dungeon_data, $game_state, $campaign_id, $actor_id),
    ];
  }

  /**
   * Builds a server-authoritative navigation receipt for in-session transitions.
   */


  /**
   * Builds a server-authoritative navigation receipt for in-session transitions.
   */
  protected function buildTransitionNavigationReceipt(
    array $dungeon_data,
    string $from_room_id,
    string $target_room_id,
    array $entry_hex,
    ?array $room = NULL
  ): array {
    $target_room_id = trim($target_room_id);
    if ($target_room_id === '') {
      throw new \RuntimeException('Encounter transition contract violation: target_room_id is required for navigation receipt.');
    }

    $room_payload = is_array($room) ? $room : ($this->findRoomById($dungeon_data, $target_room_id) ?? []);
    $navigation_capabilities = $this->requireNavigationService()
      ->buildNavigationCapabilitiesWithRoadNetwork($dungeon_data, $target_room_id);

    $origin_room_id = trim($from_room_id);
    $receipt = [
      'target_room_id' => $target_room_id,
      'destination' => trim((string) ($room_payload['name'] ?? '')) !== ''
        ? (string) $room_payload['name']
        : $target_room_id,
      'room' => is_array($room_payload) ? $room_payload : [],
      'entities' => $this->collectTransitionRoomEntities($dungeon_data, $target_room_id),
      'connections' => $this->buildTransitionReceiptConnectionsFromCapabilities($navigation_capabilities, $target_room_id),
      'navigation_capabilities' => $navigation_capabilities,
      'entry_hex' => [
        'q' => (int) ($entry_hex['q'] ?? 0),
        'r' => (int) ($entry_hex['r'] ?? 0),
      ],
    ];

    if ($origin_room_id !== '') {
      $receipt['origin_room_id'] = $origin_room_id;
    }

    return $receipt;
  }

  /**
   * Build normalized runtime connections from navigation capabilities.
   *
   * @param array<int, array<string, mixed>> $capabilities
   *   Navigation capabilities authored for one active room.
   * @param string $active_room_id
   *   Active room owning those capabilities.
   *
   * @return array<int, array<string, mixed>>
   *   Connection payload rows keyed for client dedupe.
   */


  /**
   * Build normalized runtime connections from navigation capabilities.
   *
   * @param array<int, array<string, mixed>> $capabilities
   *   Navigation capabilities authored for one active room.
   * @param string $active_room_id
   *   Active room owning those capabilities.
   *
   * @return array<int, array<string, mixed>>
   *   Connection payload rows keyed for client dedupe.
   */
  protected function buildTransitionReceiptConnectionsFromCapabilities(array $capabilities, string $active_room_id): array {
    $connections = [];
    $active_room_id = trim($active_room_id);

    foreach ($capabilities as $capability) {
      if (!is_array($capability)) {
        continue;
      }
      $target_room_id = trim((string) ($capability['target_room_id'] ?? ''));
      if ($target_room_id === '') {
        continue;
      }

      $origin_room_id = trim((string) ($capability['origin_room_id'] ?? $active_room_id));
      if ($origin_room_id === '') {
        $origin_room_id = $active_room_id;
      }
      $connection_id = trim((string) ($capability['connection_id'] ?? ''));
      if ($connection_id === '') {
        $connection_id = sprintf('receipt-%s-%s', $origin_room_id, $target_room_id);
      }

      $available = !array_key_exists('available', $capability) || !empty($capability['available']);
      $connection = [
        'connection_id' => $connection_id,
        'from_room' => $origin_room_id,
        'to_room' => $target_room_id,
        'target_room_id' => $target_room_id,
        'available' => $available,
        'blocked' => !$available,
        'blocked_reason' => $available ? '' : (string) ($capability['blocked_reason'] ?? 'blocked'),
        'type' => (string) ($capability['type'] ?? $capability['connection_type'] ?? 'passage'),
      ];

      if (is_array($capability['origin_hex'] ?? NULL)) {
        $connection['from_hex'] = [
          'q' => (int) ($capability['origin_hex']['q'] ?? 0),
          'r' => (int) ($capability['origin_hex']['r'] ?? 0),
        ];
      }
      if (is_array($capability['target_hex'] ?? NULL)) {
        $connection['to_hex'] = [
          'q' => (int) ($capability['target_hex']['q'] ?? 0),
          'r' => (int) ($capability['target_hex']['r'] ?? 0),
        ];
      }

      $connections[] = $connection;
    }

    return $connections;
  }

  /**
   * Collect entities currently placed in one room for transition receipt sync.
   *
   * @return array<int, array<string, mixed>>
   *   Room-local entity payload rows.
   */


  /**
   * Collect entities currently placed in one room for transition receipt sync.
   *
   * @return array<int, array<string, mixed>>
   *   Room-local entity payload rows.
   */
  protected function collectTransitionRoomEntities(array $dungeon_data, string $room_id): array {
    $room_id = trim($room_id);
    if ($room_id === '') {
      return [];
    }

    $entities = [];
    foreach ((array) ($dungeon_data['entities'] ?? []) as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      $entity_room_id = trim((string) ($entity['placement']['room_id'] ?? ''));
      if ($entity_room_id === $room_id) {
        $entities[] = $entity;
      }
    }

    return $entities;
  }

  /**
   * Rebuild runtime graph shape from campaign room and connector authority.
   *
   * This keeps transition consumers off stale payload graph snapshots while the
   * broader cutover away from direct dungeon_data graph ownership is in progress.
   *
   * @param int $campaign_id
   *   Campaign identifier.
   * @param array<string, mixed> $dungeon_data
   *   Current server snapshot payload.
   * @param string $requested_room_id
   *   Target room that may need to be included in the authoritative graph view.
   *
   * @return array<string, mixed>
   *   Rebuilt runtime graph payload.
   */


  /**
   * Rebuild runtime graph shape from campaign room and connector authority.
   *
   * This keeps transition consumers off stale payload graph snapshots while the
   * broader cutover away from direct dungeon_data graph ownership is in progress.
   *
   * @param int $campaign_id
   *   Campaign identifier.
   * @param array<string, mixed> $dungeon_data
   *   Current server snapshot payload.
   * @param string $requested_room_id
   *   Target room that may need to be included in the authoritative graph view.
   *
   * @return array<string, mixed>
   *   Rebuilt runtime graph payload.
   */
  protected function rebuildAuthoritativeRuntimeGraph(int $campaign_id, array $dungeon_data, string $requested_room_id = ''): array {
    if ($campaign_id <= 0 || !$this->runtimeGraphAssembler instanceof RuntimeGraphAssemblerService) {
      return $dungeon_data;
    }

    if (!$this->shouldRebuildTransitionNeighborhood($dungeon_data, $requested_room_id)) {
      return $dungeon_data;
    }

    $dungeon_id = trim((string) (
      $dungeon_data['dungeon_id']
      ?? $dungeon_data['hex_map']['map_id']
      ?? $dungeon_data['map_id']
      ?? ''
    ));
    if ($dungeon_id === '') {
      return $dungeon_data;
    }

    $rebuilt = $this->runtimeGraphAssembler->buildRuntimeGraph($campaign_id, $dungeon_id, $dungeon_data, [
      'active_room_id' => trim((string) ($dungeon_data['active_room_id'] ?? '')),
      'requested_room_id' => trim($requested_room_id),
      // Transition rebuilds are bounded to the active-room frontier.
      'room_scope_depth' => 1,
    ]);
    if (!is_array($rebuilt)) {
      return $dungeon_data;
    }

    // Preserve coordinator persistence metadata across graph rebuild replacement.
    if (array_key_exists('__campaign_dungeon_row_id', $dungeon_data) && !array_key_exists('__campaign_dungeon_row_id', $rebuilt)) {
      $rebuilt['__campaign_dungeon_row_id'] = $dungeon_data['__campaign_dungeon_row_id'];
    }

    return $rebuilt;
  }

  /**
   * Decide whether transition should refresh its bounded neighborhood graph.
   *
   * Rebuild only when the requested transition room or one-hop connected room
   * for the active room is absent from the currently loaded payload.
   */


  /**
   * Decide whether transition should refresh its bounded neighborhood graph.
   *
   * Rebuild only when the requested transition room or one-hop connected room
   * for the active room is absent from the currently loaded payload.
   */
  protected function shouldRebuildTransitionNeighborhood(array $dungeon_data, string $requested_room_id): bool {
    $active_room_id = trim((string) ($dungeon_data['active_room_id'] ?? ''));
    $requested_room_id = trim($requested_room_id);
    if ($active_room_id === '') {
      return TRUE;
    }
    if ($this->findRoomById($dungeon_data, $active_room_id) === NULL) {
      return TRUE;
    }
    if ($requested_room_id !== '' && $this->findRoomById($dungeon_data, $requested_room_id) === NULL) {
      return TRUE;
    }

    $neighbor_room_ids = $this->collectActiveRoomNeighborIds($dungeon_data, $active_room_id);
    foreach ($neighbor_room_ids as $neighbor_room_id) {
      if ($this->findRoomById($dungeon_data, $neighbor_room_id) === NULL) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Collect one-hop neighboring room IDs for the active room from connections.
   *
   * @return array<int, string>
   *   Directly connected room ids.
   */


  /**
   * Collect one-hop neighboring room IDs for the active room from connections.
   *
   * @return array<int, string>
   *   Directly connected room ids.
   */
  protected function collectActiveRoomNeighborIds(array $dungeon_data, string $active_room_id): array {
    $active_room_id = trim($active_room_id);
    if ($active_room_id === '') {
      return [];
    }

    $connections = is_array($dungeon_data['hex_map']['connections'] ?? NULL) ? $dungeon_data['hex_map']['connections'] : [];
    $neighbors = [];
    foreach ($connections as $connection) {
      if (!is_array($connection)) {
        continue;
      }
      $from_room_id = trim((string) (
        $connection['from_room']
        ?? $connection['from_room_id']
        ?? ($connection['from']['room_id'] ?? '')
      ));
      $to_room_id = trim((string) (
        $connection['to_room']
        ?? $connection['to_room_id']
        ?? ($connection['to']['room_id'] ?? '')
      ));
      if ($from_room_id === '' || $to_room_id === '' || $from_room_id === $to_room_id) {
        continue;
      }
      if ($from_room_id === $active_room_id) {
        $neighbors[$to_room_id] = TRUE;
      }
      elseif ($to_room_id === $active_room_id) {
        $neighbors[$from_room_id] = TRUE;
      }
    }

    return array_values(array_keys($neighbors));
  }

  /**
   * Materialize a canonical room template into the campaign dungeon on first travel.
   */


  /**
   * Materialize a canonical room template into the campaign dungeon on first travel.
   */
  protected function materializeCanonicalRoomForTransition(
    int $campaign_id,
    array &$dungeon_data,
    string $target_room_id,
    ?array $capability
  ): bool {
    $target_room_id = trim($target_room_id);
    if ($campaign_id <= 0 || $target_room_id === '') {
      return FALSE;
    }
    if ((string) ($capability['destination_type'] ?? 'room') !== 'room') {
      return FALSE;
    }
    return $this->requireNavigationRuntime()->materializeCanonicalRoomForCampaign(
      $campaign_id,
      $dungeon_data,
      $target_room_id,
      [
        'origin_room_id' => trim((string) ($capability['origin_room_id'] ?? ($dungeon_data['active_room_id'] ?? ''))),
        'origin_hex' => is_array($capability['origin_hex'] ?? NULL) ? $capability['origin_hex'] : NULL,
        'target_hex' => is_array($capability['target_hex'] ?? NULL) ? $capability['target_hex'] : NULL,
        'explored' => TRUE,
        'visibility' => 'visible',
      ]
    );
  }

  /**
   * Materialize one-hop linked room neighbors after entering a room.
   *
   * This pre-seeds campaign room state for directly connected destinations to
   * avoid first-visit races on immediate follow-up navigation.
   */


  /**
   * Materialize one-hop linked room neighbors after entering a room.
   *
   * This pre-seeds campaign room state for directly connected destinations to
   * avoid first-visit races on immediate follow-up navigation.
   */
  protected function materializeLinkedRoomNeighborsForCampaign(int $campaign_id, array &$dungeon_data, string $anchor_room_id): void {
    $anchor_room_id = trim($anchor_room_id);
    if ($campaign_id <= 0 || $anchor_room_id === '') {
      return;
    }

    $connections = is_array($dungeon_data['hex_map']['connections'] ?? NULL) ? $dungeon_data['hex_map']['connections'] : [];
    if ($connections === []) {
      return;
    }

    $neighbors = [];
    foreach ($connections as $connection) {
      if (!is_array($connection)) {
        continue;
      }
      $destination_type = strtolower(trim((string) ($connection['destination_type'] ?? 'room')));
      if ($destination_type !== '' && $destination_type !== 'room') {
        continue;
      }

      $from_room_id = trim((string) (
        $connection['from_room']
        ?? $connection['from_room_id']
        ?? ($connection['from']['room_id'] ?? '')
      ));
      $to_room_id = trim((string) (
        $connection['to_room']
        ?? $connection['to_room_id']
        ?? ($connection['to']['room_id'] ?? '')
      ));
      if ($from_room_id === '' || $to_room_id === '') {
        continue;
      }
      if ($from_room_id === $to_room_id) {
        continue;
      }

      if ($from_room_id === $anchor_room_id) {
        $neighbor_room_id = $to_room_id;
        $origin_hex = is_array($connection['from_hex'] ?? NULL) ? $connection['from_hex'] : NULL;
        $target_hex = is_array($connection['to_hex'] ?? NULL) ? $connection['to_hex'] : NULL;
      }
      elseif ($to_room_id === $anchor_room_id) {
        $bidirectional = !array_key_exists('bidirectional', $connection) || !empty($connection['bidirectional']);
        if (!$bidirectional) {
          continue;
        }
        $neighbor_room_id = $from_room_id;
        $origin_hex = is_array($connection['to_hex'] ?? NULL) ? $connection['to_hex'] : NULL;
        $target_hex = is_array($connection['from_hex'] ?? NULL) ? $connection['from_hex'] : NULL;
      }
      else {
        continue;
      }

      if ($neighbor_room_id === '' || $neighbor_room_id === $anchor_room_id) {
        continue;
      }
      $neighbors[$neighbor_room_id] = [
        'origin_hex' => $origin_hex,
        'target_hex' => $target_hex,
      ];
    }

    foreach ($neighbors as $neighbor_room_id => $neighbor) {
      if ($this->findRoomById($dungeon_data, $neighbor_room_id) !== NULL) {
        continue;
      }

      $materialize_capability = [
        'destination_type' => 'room',
        'origin_room_id' => $anchor_room_id,
      ];
      if (is_array($neighbor['origin_hex'] ?? NULL) && isset($neighbor['origin_hex']['q'], $neighbor['origin_hex']['r'])) {
        $materialize_capability['origin_hex'] = [
          'q' => (int) $neighbor['origin_hex']['q'],
          'r' => (int) $neighbor['origin_hex']['r'],
        ];
      }
      if (is_array($neighbor['target_hex'] ?? NULL) && isset($neighbor['target_hex']['q'], $neighbor['target_hex']['r'])) {
        $materialize_capability['target_hex'] = [
          'q' => (int) $neighbor['target_hex']['q'],
          'r' => (int) $neighbor['target_hex']['r'],
        ];
      }

      if (!$this->materializeCanonicalRoomForTransition($campaign_id, $dungeon_data, $neighbor_room_id, $materialize_capability)) {
        throw new \RuntimeException(sprintf(
          'Encounter transition preseed contract violation: linked room %s from anchor room %s could not be materialized from canonical storage.',
          $neighbor_room_id,
          $anchor_room_id
        ));
      }

      $this->logger->info('Encounter transition preseed materialized linked room: campaign={campaign_id} anchor_room={anchor_room} linked_room={linked_room}', [
        'campaign_id' => $campaign_id,
        'anchor_room' => $anchor_room_id,
        'linked_room' => $neighbor_room_id,
      ]);
    }
  }

  /**
   * Queue non-blocking linked-room preseed after successful room entry.
   */


  /**
   * Queue non-blocking linked-room preseed after successful room entry.
   */
  protected function enqueueLinkedRoomNeighborPreseed(int $campaign_id, array $dungeon_data, string $anchor_room_id): void {
    $anchor_room_id = trim($anchor_room_id);
    if ($campaign_id <= 0 || $anchor_room_id === '') {
      return;
    }

    $dungeon_id = trim((string) (
      $dungeon_data['dungeon_id']
      ?? $dungeon_data['hex_map']['map_id']
      ?? $dungeon_data['map_id']
      ?? ''
    ));
    if ($dungeon_id === '') {
      return;
    }

    \Drupal::queue('dungeoncrawler_content.navigation_neighbor_preseed')
      ->createItem([
        'campaign_id' => $campaign_id,
        'dungeon_id' => $dungeon_id,
        'anchor_room_id' => $anchor_room_id,
      ]);
  }

  /**
   * Process one background neighbor-preseed queue item.
   */


  /**
   * Process one background neighbor-preseed queue item.
   */
  public function processLinkedRoomPreseedQueueItem(int $campaign_id, string $dungeon_id, string $anchor_room_id): void {
    $campaign_id = (int) $campaign_id;
    $dungeon_id = trim($dungeon_id);
    $anchor_room_id = trim($anchor_room_id);
    if ($campaign_id <= 0 || $dungeon_id === '' || $anchor_room_id === '') {
      throw new \InvalidArgumentException('Linked-room preseed queue contract violation: campaign_id, dungeon_id, and anchor_room_id are required.');
    }

    $row = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['id', 'dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->condition('dungeon_id', $dungeon_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($row)) {
      throw new \RuntimeException(sprintf(
        'Linked-room preseed queue contract violation: campaign %d dungeon %s not found.',
        $campaign_id,
        $dungeon_id
      ));
    }

    $dungeon_data = json_decode((string) ($row['dungeon_data'] ?? '{}'), TRUE);
    if (!is_array($dungeon_data)) {
      throw new \RuntimeException(sprintf(
        'Linked-room preseed queue contract violation: campaign %d dungeon %s has invalid dungeon_data JSON.',
        $campaign_id,
        $dungeon_id
      ));
    }

    $before_room_count = count((array) ($dungeon_data['rooms'] ?? []));
    $this->materializeLinkedRoomNeighborsForCampaign($campaign_id, $dungeon_data, $anchor_room_id);
    $after_room_count = count((array) ($dungeon_data['rooms'] ?? []));

    if ($after_room_count === $before_room_count) {
      return;
    }

    $this->database->update('dc_campaign_dungeons')
      ->fields([
        'dungeon_data' => json_encode($dungeon_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'updated' => time(),
      ])
      ->condition('id', (int) $row['id'])
      ->execute();
  }

  /**
   * Resolve transition frontier as active room plus direct neighbors.
   *
   * @return array<int, string>
   *   Provisioning scope for launch slice.
   */


  /**
   * Resolve transition frontier as active room plus direct neighbors.
   *
   * @return array<int, string>
   *   Provisioning scope for launch slice.
   */
  protected function resolveLaunchSliceRoomScopeFromDungeonData(array $dungeon_data, string $previous_room_id = ''): array {
    $rooms = [];
    foreach ((array) ($dungeon_data['rooms'] ?? []) as $room) {
      if (!is_array($room)) {
        continue;
      }
      $room_id = trim((string) ($room['room_id'] ?? $room['id'] ?? ''));
      if ($room_id !== '') {
        $rooms[$room_id] = TRUE;
      }
    }

    $active_room_id = trim((string) ($dungeon_data['active_room_id'] ?? $dungeon_data['current_room_id'] ?? ''));
    $previous_room_id = trim($previous_room_id);
    $scope = [];
    if ($active_room_id !== '' && isset($rooms[$active_room_id])) {
      $scope[$active_room_id] = TRUE;
    }
    if ($previous_room_id !== '' && isset($rooms[$previous_room_id])) {
      $scope[$previous_room_id] = TRUE;
    }

    if (
      isset($rooms['ltba-tavern-room'], $rooms['ltba-streets-room'])
      && (isset($scope['ltba-tavern-room']) || isset($scope['ltba-streets-room']))
    ) {
      $scope['ltba-tavern-room'] = TRUE;
      $scope['ltba-streets-room'] = TRUE;
    }

    if ($scope === [] && $active_room_id !== '') {
      $scope[$active_room_id] = TRUE;
    }
    if ($scope === [] && $rooms !== []) {
      $scope[(string) array_key_first($rooms)] = TRUE;
    }

    return array_values(array_keys($scope));
  }

  /**
   * Resolve canonical transition entry hex inside the destination room.
   */


  /**
   * Resolve canonical transition entry hex inside the destination room.
   */
  protected function resolveTransitionEntryHex(array $room, array $params, ?array $capability): array {
    $room_id = trim((string) ($room['room_id'] ?? ''));
    $room_hexes = array_values(array_filter(
      (array) ($room['hexes'] ?? []),
      static fn($hex): bool => is_array($hex) && isset($hex['q'], $hex['r'])
    ));
    if ($room_hexes === []) {
      throw new \RuntimeException(sprintf(
        'Encounter transition contract violation: room %s has no placement hexes.',
        $room_id !== '' ? $room_id : 'unknown'
      ));
    }

    $candidates = [
      $params['entry_hex'] ?? NULL,
      $params['target_hex'] ?? NULL,
      $capability['target_hex'] ?? NULL,
    ];
    foreach ($candidates as $candidate) {
      $normalized = $this->normalizeTransitionHexCandidate($candidate);
      if ($normalized === NULL) {
        continue;
      }
      foreach ($room_hexes as $room_hex) {
        if ((int) $room_hex['q'] === $normalized['q'] && (int) $room_hex['r'] === $normalized['r']) {
          return $normalized;
        }
      }
    }

    foreach ($room_hexes as $room_hex) {
      if (!empty($room_hex['is_entry']) || !empty($room_hex['entry'])) {
        return [
          'q' => (int) $room_hex['q'],
          'r' => (int) $room_hex['r'],
        ];
      }
    }

    return [
      'q' => (int) ($room_hexes[0]['q'] ?? 0),
      'r' => (int) ($room_hexes[0]['r'] ?? 0),
    ];
  }

  /**
   * Normalize one transition-hex candidate payload.
   */


  /**
   * Normalize one transition-hex candidate payload.
   */
  protected function normalizeTransitionHexCandidate(mixed $candidate): ?array {
    if (!is_array($candidate) || !isset($candidate['q'], $candidate['r'])) {
      return NULL;
    }

    return [
      'q' => (int) $candidate['q'],
      'r' => (int) $candidate['r'],
    ];
  }

  /**
   * {@inheritdoc}
   */


  /**
   * {@inheritdoc}
   */
  public function onEnterWithMutationContext(
    array $context,
    RuntimeMutationExecutionContext $mutation_context,
    int $campaign_id
  ): array {
    $game_state =& $mutation_context->gameState;
    $dungeon_data =& $mutation_context->dungeonData;
    return $this->onEnter($context, $game_state, $dungeon_data, $campaign_id);
  }

  /**
   * {@inheritdoc}
   */


  /**
   * {@inheritdoc}
   */
  public function onEnter(array $context, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $lifecycle_snapshot = $this->captureEncounterLifecycleSnapshot($game_state);
    $game_state['phase'] = 'encounter';
    $events = [];

    $encounter_context = $context['encounter_context'] ?? [];
    $room_id = $encounter_context['room_id'] ?? ($dungeon_data['active_room_id'] ?? NULL);
    $game_state['encounter_context'] = $encounter_context + [
      'room_id' => $room_id,
      'mode' => 'hostile_combat',
      'started_at' => $game_state['encounter_context']['started_at'] ?? date('c'),
    ];
    $enemies = $encounter_context['enemies'] ?? [];

    try {
      // Build participant list from entities in the room.
      $participants = $this->buildParticipantList($dungeon_data, $room_id, $enemies);
      $has_player_side = FALSE;
      $has_hostile_side = FALSE;
      foreach ($participants as $participant) {
        if (!is_array($participant)) {
          continue;
        }
        $team = $this->normalizeCombatTeam((string) ($participant['team'] ?? ''));
        if (in_array($team, ['player', 'ally'], TRUE)) {
          $has_player_side = TRUE;
        }
        if ($team === 'enemy') {
          $has_hostile_side = TRUE;
        }
      }
      if (!$has_player_side || !$has_hostile_side) {
        throw new \RuntimeException(sprintf(
          'Combat bootstrap participant contract violation for room %s (players=%s, hostiles=%s, participants=%d).',
          $room_id,
          $has_player_side ? 'yes' : 'no',
          $has_hostile_side ? 'yes' : 'no',
          count($participants)
        ));
      }

      // Create encounter in the combat_encounters table.
      $encounter_id = $this->combatEngine->createEncounter($campaign_id, $room_id, $participants, [
        'room_id' => $room_id,
      ]);
      if (!$encounter_id) {
        throw new \RuntimeException('Combat engine did not return an encounter id.');
      }

      if ($encounter_id) {
        // Start the encounter (rolls initiative, sorts order, starts round 1).
        $start_result = $this->combatEngine->startEncounter($encounter_id);
        if (($start_result['status'] ?? 'error') !== 'ok' || !is_array($start_result['encounter'] ?? NULL)) {
          throw new \RuntimeException((string) ($start_result['message'] ?? 'Combat engine failed to start encounter.'));
        }

        $game_state['encounter_id'] = $encounter_id;
        $game_state['round'] = 1;
        $events = array_merge($events, $this->buildRoundStartEvents(1, $game_state, $dungeon_data, $campaign_id, $room_id));

        // Set up the first turn.
        $initiative_order = $start_result['encounter']['participants'] ?? [];
        if (!empty($initiative_order)) {
          $first = $initiative_order[0];
          $first_entity_id = trim((string) ($first['entity_id'] ?? ''));
          if ($first_entity_id === '') {
            throw new \RuntimeException('Combat bootstrap produced invalid first-turn actor.');
          }
          $game_state['turn'] = [
            'entity' => $first_entity_id,
            'index' => 0,
            'actions_remaining' => 3,
            'attacks_this_turn' => 0,
            'reaction_available' => TRUE,
            'delayed' => FALSE,
          ];
          $events = array_merge($events, $this->buildTurnStartEvents($first_entity_id, $game_state, $dungeon_data, $campaign_id, $room_id));
          $events = array_merge($events, $this->buildTurnStartSearchEvents($first_entity_id, $game_state, $dungeon_data, $campaign_id));
        }

        $game_state['initiative_order'] = $initiative_order;

        $initial_turn_events = [];
        if (!empty($initiative_order)) {
          $first = $initiative_order[0];
          $first_entity = $first['entity_id'] ?? NULL;
          $first_team = $this->normalizeCombatTeam((string) ($first['team'] ?? 'enemy'));
          // Any non-player actor winning initiative must be driven by the actor
          // harness, otherwise the encounter deadlocks on turn 1 waiting for
          // input that no human will ever supply. This mirrors the turn
          // advancement gate in processEndTurn().
          if ($first_entity && $first_team !== 'player') {
            $should_autoplay_in_room_scene = $this->isRoomSceneMode($game_state) && $first_team === 'enemy';
            $npc_result = (!$this->isRoomSceneMode($game_state) || $should_autoplay_in_room_scene)
              ? $this->autoPlayNpcTurn($encounter_id, (string) $first_entity, $game_state, $dungeon_data, $campaign_id)
              : $this->passRoomActorTurn((string) $first_entity, $game_state, $dungeon_data, $campaign_id);
            $initial_turn_events = $npc_result['events'] ?? [];

            $initial_advance = $this->processEndTurn($encounter_id, (string) $first_entity, $game_state, $dungeon_data, $campaign_id);
            $initial_turn_events = array_merge($initial_turn_events, $initial_advance['npc_events'] ?? []);
          }
        }

        $events[] = GameEventLogger::buildEvent('encounter_started', 'encounter', NULL, [
          'encounter_id' => $encounter_id,
          'room_id' => $room_id,
          'participants' => count($participants),
          'initiative_order' => $initiative_order,
        ]);
        if (!empty($initial_turn_events)) {
          $events = array_merge($events, $initial_turn_events);
        }

        // AI GM narration for encounter start.
        $gm_narration = $this->aiGmService->narrateEncounterStart([
          'participants' => $participants,
          'room_name' => $room_id,
          'reason' => $context['reason'] ?? 'Hostile creatures detected',
        ], $dungeon_data, $campaign_id);
        if ($gm_narration) {
          $events[] = GameEventLogger::buildEvent('gm_narration', 'encounter', NULL, [
            'trigger' => 'encounter_start',
          ], $gm_narration);
        }

        // Queue encounter start for perception-filtered narration.
        $this->queueNarrationEvent($campaign_id, $dungeon_data, [
          'type' => 'action',
          'speaker' => 'GM',
          'speaker_type' => 'gm',
          'speaker_ref' => '',
          'content' => sprintf('Combat begins! %s', $context['reason'] ?? 'Hostile creatures detected!'),
          'visibility' => 'public',
          'mechanical_data' => [
            'encounter_id' => $encounter_id,
            'participant_count' => count($participants),
            'round' => 1,
          ],
        ], $room_id);
        $initiative_summary = [];
        foreach ($participants as $participant) {
          $initiative_value = $participant['initiative'] ?? $participant['initiative_total'] ?? NULL;
          if (!is_numeric($initiative_value)) {
            continue;
          }
          $initiative_summary[] = [
            'name' => $participant['name'] ?? $participant['display_name'] ?? ($participant['entity_id'] ?? 'Unknown'),
            'initiative' => (int) $initiative_value,
            'roll' => isset($participant['initiative_roll']) && is_numeric($participant['initiative_roll']) ? (int) $participant['initiative_roll'] : NULL,
          ];
        }
        if ($initiative_summary !== []) {
          $initiative_text = implode(', ', array_map(
            static fn(array $entry): string => sprintf('%s %d', $entry['name'], $entry['initiative']),
            $initiative_summary
          ));
          $this->queueNarrationEvent($campaign_id, $dungeon_data, [
            'type' => 'initiative_set',
            'speaker' => 'System',
            'speaker_type' => 'system',
            'speaker_ref' => '',
            'content' => sprintf('Initiative order: %s.', $initiative_text),
            'mechanical_data' => [
              'encounter_id' => $encounter_id,
              'order' => $initiative_summary,
            ],
            'visibility' => 'public',
          ], $room_id);
        }

        // Mark the room's encounter as triggered.
        $this->markRoomEncounterTriggered($dungeon_data, $room_id);
      }
    }
    catch (\Throwable $e) {
      $this->restoreEncounterLifecycleSnapshot($game_state, $lifecycle_snapshot);
      $this->logger->error('Failed to create encounter: @error', ['@error' => $e->getMessage()]);
      $events[] = GameEventLogger::buildEvent('encounter_start_failed', 'encounter', NULL, [
        'error' => $e->getMessage(),
      ]);
    }

    $lifecycle_mutations = [];
    $normalized_room_id = trim((string) $room_id);
    if ($normalized_room_id !== '') {
      $lifecycle_mutations[] = [
        'type' => 'room_encounter_triggered',
        'field' => 'room.encounter_triggered',
        'room_id' => $normalized_room_id,
      ];
    }

    return [
      'events' => $events,
      'mutation_envelope' => $this->buildMutationEnvelopeFromRuntimeContext($campaign_id, $game_state, $dungeon_data, $lifecycle_mutations),
    ];
  }

  /**
   * Capture the encounter-lifecycle keys that hostile combat startup may mutate.
   */
  protected function captureEncounterLifecycleSnapshot(array $game_state): array {
    $snapshot = [];
    foreach (['phase', 'encounter_context', 'encounter_id', 'round', 'turn', 'initiative_order'] as $key) {
      $snapshot[$key] = [
        'exists' => array_key_exists($key, $game_state),
        'value' => $game_state[$key] ?? NULL,
      ];
    }
    return $snapshot;
  }

  /**
   * Restore encounter-lifecycle keys after a failed hostile combat startup.
   */
  protected function restoreEncounterLifecycleSnapshot(array &$game_state, array $snapshot): void {
    foreach ($snapshot as $key => $entry) {
      if (!is_array($entry) || empty($entry['exists'])) {
        unset($game_state[$key]);
        continue;
      }
      $game_state[$key] = $entry['value'] ?? NULL;
    }
  }

  /**
   * {@inheritdoc}
   */


  /**
   * {@inheritdoc}
   */
  public function onExitWithMutationContext(
    RuntimeMutationExecutionContext $mutation_context,
    int $campaign_id
  ): array {
    $game_state =& $mutation_context->gameState;
    $dungeon_data =& $mutation_context->dungeonData;
    return $this->onExit($game_state, $dungeon_data, $campaign_id);
  }

  /**
   * {@inheritdoc}
   */


  /**
   * {@inheritdoc}
   */
  public function onExit(array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $encounter_id = $game_state['encounter_id'] ?? NULL;
    $events = [];

    if ($encounter_id) {
      try {
        // End the encounter in the combat engine.
        $this->combatEngine->endEncounter(
          $encounter_id,
          'victory',
          'encounter framework cleanup'
        );
      }
      catch (\Throwable $e) {
        $this->logger->error('Failed to end encounter: @error', ['@error' => $e->getMessage()]);
      }

      $events[] = GameEventLogger::buildEvent('encounter_ended', 'encounter', NULL, [
        'encounter_id' => $encounter_id,
        'final_round' => $game_state['round'] ?? NULL,
      ]);

      // AI GM narration for encounter end.
      $gm_narration = $this->aiGmService->narrateEncounterEnd([
        'encounter_id' => $encounter_id,
        'final_round' => $game_state['round'] ?? NULL,
        'victory' => TRUE,
      ], $dungeon_data, $campaign_id);
      if ($gm_narration) {
        $events[] = GameEventLogger::buildEvent('gm_narration', 'encounter', NULL, [
          'trigger' => 'encounter_end',
        ], $gm_narration);
      }

      // Queue encounter end for perception-filtered narration.
      $this->queueNarrationEvent($campaign_id, $dungeon_data, [
        'type' => 'action',
        'speaker' => 'GM',
        'speaker_type' => 'gm',
        'speaker_ref' => '',
        'content' => sprintf('The encounter ends after %d rounds.', $game_state['round'] ?? 0),
        'visibility' => 'public',
        'mechanical_data' => [
          'encounter_id' => $encounter_id,
          'final_round' => $game_state['round'] ?? NULL,
        ],
      ]);
    }

    // Clean up encounter state from game_state, but preserve it for history.
    $game_state['last_encounter'] = [
      'encounter_id' => $encounter_id,
      'final_round' => $game_state['round'] ?? NULL,
      'ended_at' => date('c'),
    ];

    $game_state['encounter_id'] = NULL;
    $game_state['round'] = NULL;
    $game_state['turn'] = NULL;
    $game_state['initiative_order'] = NULL;

    return [
      'events' => $events,
      'mutation_envelope' => $this->buildMutationEnvelopeFromRuntimeContext($campaign_id, $game_state, $dungeon_data, []),
    ];
  }

  /**
   * {@inheritdoc}
   */

}
