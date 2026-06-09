<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Contract for encounter-master orchestration capabilities.
 */
interface EncounterMasterInterface extends PhaseHandlerInterface {

  /**
   * Starts or resumes encounter room-framework context for a target room.
   *
   * @param string|null $actor_id
   *   Runtime actor ID initiating the room transition, or NULL for bootstrap.
   * @param string $target_room_id
   *   Room identifier to activate.
   * @param array $params
   *   Transition parameters.
   * @param array $game_state
   *   Mutable gameplay state.
   * @param array $dungeon_data
   *   Mutable dungeon payload.
   * @param int $campaign_id
   *   Campaign identifier.
   *
   * @return array
   *   Result payload with events/mutations/time effects and optional error.
   */
  public function enterRoomFramework(?string $actor_id, string $target_room_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array;

}
