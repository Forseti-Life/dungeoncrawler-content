<?php

namespace Drupal\dungeoncrawler_content\Service\RoomChat;

/**
 * Builds and caches room prompt artifacts for GM prompt assembly.
 */
final class GmPromptArtifactCacheBuilder {

  /**
   * Build cached prompt artifacts for a room.
   *
   * @param callable(string,int,float):string $truncate_context
   * @param callable(array):bool $npc_supports_quest_dialogue
   * @param callable(string,array):bool $text_contains_any
   */
  public static function build(
    int $campaign_id,
    string $room_id,
    array $room_meta,
    array $dungeon_data,
    array $room_npcs,
    callable $truncate_context,
    callable $npc_supports_quest_dialogue,
    callable $text_contains_any
  ): array {
    $cache_state = [
      'room_id' => $room_id,
      'room_name' => $room_meta['name'] ?? '',
      'room_description' => $room_meta['description'] ?? '',
      'room_entities' => $room_meta['entities'] ?? [],
      'top_entities' => $dungeon_data['entities'] ?? [],
      'room_npcs' => array_map(static function (array $npc): array {
        return [
          'entity_ref' => $npc['entity_ref'] ?? '',
          'display_name' => $npc['profile']['display_name'] ?? '',
          'role' => $npc['profile']['role'] ?? ($npc['entity']['role'] ?? ''),
          'attitude' => $npc['profile']['attitude'] ?? '',
        ];
      }, $room_npcs),
    ];
    $cache_key = 'dungeoncrawler_content:room_prompt_artifacts:' . $campaign_id . ':' . sha1(json_encode($cache_state));
    $cache = \Drupal::cache('default')->get($cache_key);
    if ($cache && is_array($cache->data)) {
      $cache->data['cache'] = 'hit';
      return $cache->data;
    }

    $scene_parts = [];
    if (!empty($room_meta['name'])) {
      $scene_parts[] = 'Current room: ' . $truncate_context((string) $room_meta['name'], 96, 0.85);
    }
    if (!empty($room_meta['description'])) {
      $scene_parts[] = 'Room description: ' . $truncate_context((string) $room_meta['description'], 240, 0.75);
    }

    $entities = $room_meta['entities'] ?? [];
    $entity_names = [];
    foreach (array_slice($entities, 0, 10) as $ent) {
      $ename = $ent['state']['metadata']['display_name']
        ?? $ent['name']
        ?? NULL;
      if ($ename) {
        $etype = $ent['type'] ?? 'npc';
        $entity_names[] = "{$ename} ({$etype})";
      }
    }
    if (!empty($entity_names)) {
      $scene_parts[] = 'Beings/objects present: ' . $truncate_context(implode(', ', $entity_names), 260, 0.7);
    }

    $npc_names = [];
    $quest_givers = [];
    $merchants = [];
    $npc_profile_lines = [];
    foreach ($room_npcs as $npc) {
      $name = trim((string) ($npc['profile']['display_name'] ?? ''));
      if ($name === '') {
        continue;
      }
      $npc_names[] = $name;
      $descriptor = strtolower($name . ' ' . ($npc['entity_ref'] ?? '') . ' ' . ($npc['profile']['motivations'] ?? ''));
      if ($npc_supports_quest_dialogue($npc)) {
        $quest_givers[] = $name;
      }
      if ($text_contains_any($descriptor, ['keeper', 'merchant', 'vendor', 'shop', 'tavern', 'inn', 'bar', 'sell'])) {
        $merchants[] = $name;
      }
      if (count($npc_profile_lines) < 4) {
        $profile_parts = [];
        $sheet = $npc['profile']['character_sheet'] ?? [];
        $occupation = trim((string) ($sheet['occupation'] ?? ''));
        $attitude = trim((string) ($npc['profile']['attitude'] ?? ''));
        $traits = trim((string) ($npc['profile']['personality_traits'] ?? ''));
        $motivations = trim((string) ($npc['profile']['motivations'] ?? ''));
        $appearance = trim((string) ($sheet['appearance'] ?? $sheet['description'] ?? ''));
        if ($occupation !== '') {
          $profile_parts[] = 'occupation: ' . $truncate_context($occupation, 72, 0.9);
        }
        if ($attitude !== '') {
          $profile_parts[] = 'attitude: ' . $truncate_context($attitude, 72, 0.9);
        }
        if ($traits !== '') {
          $profile_parts[] = 'traits: ' . $truncate_context($traits, 96, 0.8);
        }
        if ($motivations !== '') {
          $profile_parts[] = 'motivations: ' . $truncate_context($motivations, 96, 0.8);
        }
        if ($appearance !== '') {
          $profile_parts[] = 'appearance: ' . $truncate_context($appearance, 120, 0.8);
        }
        if ($profile_parts !== []) {
          $npc_profile_lines[] = '- ' . $name . ' — ' . implode('; ', $profile_parts);
        }
      }
    }

    $artifacts = [
      'scene_parts' => $scene_parts,
      'entity_count' => count($entities),
      'entity_summary_count' => count($entity_names),
      'npc_roster_summary' => $npc_names !== []
        ? $truncate_context('People ready to answer in this room: ' . implode(', ', array_slice($npc_names, 0, 4)) . '.', 180, 0.85)
        : '',
      'npc_profile_summary' => $npc_profile_lines !== []
        ? $truncate_context("NPC profile notes for GM use:\n" . implode("\n", $npc_profile_lines), 520, 0.7)
        : '',
      'merchant_summary' => $merchants !== []
        ? $truncate_context('Likely merchants or practical sellers here: ' . implode(', ', array_slice($merchants, 0, 3)) . '.', 180, 0.85)
        : '',
      'quest_summary' => $quest_givers !== []
        ? $truncate_context('Likely quest or guidance contacts here: ' . implode(', ', array_slice($quest_givers, 0, 3)) . '.', 180, 0.85)
        : '',
      'cache' => 'miss',
    ];

    \Drupal::cache('default')->set($cache_key, $artifacts, time() + 600, [
      'dungeoncrawler_content:campaign:' . $campaign_id,
    ]);

    return $artifacts;
  }

}
