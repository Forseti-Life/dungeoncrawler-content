<?php

namespace Drupal\dungeoncrawler_content\Service\RoomChat;

/**
 * Gathers room NPCs and their psychology profiles from runtime surfaces.
 */
final class RoomNpcProfileGatherer {

  /**
   * Gather all NPCs in the current room that have psychology profiles.
   *
   * @param callable(string):?array $load_profile
   * @param callable(array&,array&,array&,string,array,array):void $register_npc
   * @param callable():array $load_room_campaign_rows
   * @param callable(object,array):array $resolve_campaign_npc_profile
   * @param callable(string,array):void $log_info
   */
  public static function gather(
    int $campaign_id,
    string $room_id,
    array $dungeon_data,
    callable $load_profile,
    callable $register_npc,
    callable $load_room_campaign_rows,
    callable $resolve_campaign_npc_profile,
    callable $log_info
  ): array {
    $result = [];
    $seen_refs = [];
    $seen_names = [];

    foreach ($dungeon_data['entities'] ?? [] as $entity) {
      $ent_room = $entity['placement']['room_id'] ?? '';
      $ent_type = $entity['entity_type'] ?? '';
      if ($ent_room !== $room_id || $ent_type !== 'npc') {
        continue;
      }

      $ref = $entity['entity_ref']['content_id'] ?? '';
      if (!$ref || isset($seen_refs[$ref])) {
        continue;
      }

      $profile = $load_profile($ref);
      if (!$profile) {
        continue;
      }

      $register_npc($result, $seen_refs, $seen_names, $ref, $entity, $profile);
    }

    try {
      foreach ($load_room_campaign_rows() as $row) {
        $resolved = $resolve_campaign_npc_profile($row, $seen_refs);
        if (empty($resolved['entity_ref']) || empty($resolved['profile'])) {
          continue;
        }
        $register_npc($result, $seen_refs, $seen_names, (string) $resolved['entity_ref'], [], $resolved['profile']);
      }
    }
    catch (\Throwable $e) {
      // Non-critical; continue with entities already found.
    }

    return $result;
  }

}
