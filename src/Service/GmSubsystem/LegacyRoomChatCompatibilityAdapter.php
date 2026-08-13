<?php

namespace Drupal\dungeoncrawler_content\Service\GmSubsystem;

/**
 * Builds explicit legacy/full-state compatibility overlays for room chat.
 */
class LegacyRoomChatCompatibilityAdapter {

  /**
   * Build legacy overlay fields from one room-chat turn result.
   *
   * @param array<string,mixed> $chat_result
   *   Raw room-chat turn result.
   *
   * @return array<string,mixed>
   *   Compatibility overlay payload.
   */
  public function buildOverlay(array $chat_result): array {
    $overlay = [];
    foreach ([
      'dungeon_data',
      'totalMessages',
      'state_diff',
      'canonical_actions',
      'combat_transition',
      'runtime_snapshot',
      'aggression_summary',
      'combat_entry_summary',
      'events',
      'phase',
      'encounter_id',
      'round',
      'turn',
      'state_version',
      'active_room_id',
      'debug_trace',
      'gm_deferred',
      'npc_interjections_deferred',
      'turn_sequence',
      'phase_transition',
    ] as $key) {
      if (array_key_exists($key, $chat_result)) {
        $overlay[$key] = $chat_result[$key];
      }
    }

    return $overlay;
  }

}
