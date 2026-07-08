<?php

namespace Drupal\dungeoncrawler_content\Service\RoomChat;

/**
 * Shared helper for deterministic roster/adjudication narrative assembly.
 */
final class DeterministicNarrativeHelper {

  /**
   * Build an observational room-roster response without dialogue.
   */
  public static function buildRoomRosterNarrative(
    int $campaign_id,
    string $room_id,
    array $room_meta,
    array $dungeon_data,
    array $room_npcs,
    callable $truncate_context,
    callable $build_actor_grounding
  ): string {
    $descriptions = [];
    foreach ($room_npcs as $npc) {
      $name = trim((string) ($npc['profile']['display_name'] ?? ''));
      if ($name === '') {
        continue;
      }

      $profile = is_array($npc['profile'] ?? NULL) ? $npc['profile'] : [];
      $role = trim((string) ($profile['role'] ?? ''));
      $attitude = trim((string) ($profile['attitude'] ?? ''));
      $personality = trim((string) ($profile['personality'] ?? $profile['personality_traits'] ?? ''));

      $parts = [];
      if ($role !== '') {
        $parts[] = $role;
      }
      if ($attitude !== '') {
        $parts[] = 'demeanor: ' . $truncate_context($attitude, 48, 0.9);
      }
      elseif ($personality !== '') {
        $parts[] = 'demeanor: ' . $truncate_context($personality, 64, 0.8);
      }

      $descriptions[] = $parts === []
        ? $name . ' is present.'
        : $name . ' (' . implode('; ', $parts) . ').';
    }

    if ($descriptions === []) {
      $actor_notes = $build_actor_grounding($campaign_id, $room_id, $dungeon_data);
      if ($actor_notes !== '') {
        return 'Visible named occupants in ' . ($room_meta['name'] ?? 'the room') . ": \n" . preg_replace('/^- /m', '', $actor_notes);
      }
      return '';
    }

    if (count($descriptions) === 1) {
      return 'In ' . ($room_meta['name'] ?? 'the room') . ', the only clearly visible named occupant is ' . $descriptions[0];
    }

    return 'Visible named occupants in ' . ($room_meta['name'] ?? 'the room') . ': ' . implode(' ', $descriptions);
  }

  /**
   * Build a grounded referee answer for explicit GM/adjudication questions.
   */
  public static function buildGmAdjudicationNarrative(
    string $player_message,
    int $campaign_id,
    string $room_id,
    array $room_meta,
    array $dungeon_data,
    array $room_npcs,
    ?array $character_data,
    callable $normalize_name,
    callable $extract_player_name,
    callable $looks_like_expected_occupants_issue,
    callable $build_roster_narrative,
    callable $find_turn_entity,
    callable $text_contains_any,
    callable $truncate_context
  ): string {
    $normalized = $normalize_name($player_message);
    $character_name = $extract_player_name($character_data);
    $subject = $character_name !== '' ? $character_name : 'your character';
    if ($looks_like_expected_occupants_issue($normalized) && $room_npcs === []) {
      return 'No named occupants are grounded in this room state right now, so the expected meetup NPCs are currently missing from the active room roster.';
    }
    $roster_narrative = $build_roster_narrative($campaign_id, $room_id, $room_meta, $dungeon_data, $room_npcs);
    $room_description = trim((string) ($room_meta['description'] ?? ''));
    $turn_entity_id = trim((string) ($dungeon_data['game_state']['turn']['entity'] ?? ''));
    $turn_display_name = $turn_entity_id !== ''
      ? (($find_turn_entity($turn_entity_id, $dungeon_data)['state']['metadata']['display_name'] ?? NULL)
        ?? ($find_turn_entity($turn_entity_id, $dungeon_data)['name'] ?? NULL)
        ?? $turn_entity_id)
      : '';
    $initiative_order = is_array($dungeon_data['game_state']['initiative_order'] ?? NULL)
      ? $dungeon_data['game_state']['initiative_order']
      : [];
    $upcoming_names = [];
    foreach ($initiative_order as $participant) {
      if (!is_array($participant)) {
        continue;
      }
      $name = trim((string) ($participant['name'] ?? ''));
      if ($name !== '') {
        $upcoming_names[] = $name;
      }
    }

    if ($text_contains_any($normalized, ['whose turn', 'who s turn', 'whos turn', 'who is up', 'who goes next', 'my turn', 'your turn', 'what turn is it', 'which npc is getting resolved', 'which npc is being resolved', 'nothing is happening', 'do them one at a time', 'something is wrong', 'something is really fucked up'])) {
      if ($turn_display_name !== '') {
        $narrative = sprintf('It is currently %s\'s turn.', $turn_display_name);
        if ($upcoming_names !== []) {
          $narrative .= ' Initiative order in this room is: ' . implode(' -> ', $upcoming_names) . '.';
        }
        $narrative .= ' NPCs act one at a time only after the current actor ends or delays their turn.';
        return $narrative;
      }
      return 'The current turn is not grounded clearly enough to answer yet.';
    }

    if ($text_contains_any($normalized, ['notice', 'see', 'tell', 'sense', 'spot'])) {
      $parts = ['From what is immediately apparent in the grounded scene,'];
      if ($room_description !== '') {
        $parts[] = $truncate_context($room_description, 140, 1.0);
      }
      if ($roster_narrative !== '') {
        $parts[] = $roster_narrative;
      }
      return implode(' ', $parts);
    }

    $narrative = 'From what is grounded in the current scene, ' . $subject . ' has no additional prior knowledge confirmed yet.';
    if ($roster_narrative !== '') {
      $narrative .= ' ' . $roster_narrative;
    }

    return $narrative;
  }

  /**
   * Determine whether the player is leaving the current location.
   */
  public static function looksLikeNavigationTurn(
    string $normalized_message,
    callable $extract_destination,
    callable $text_contains_any
  ): bool {
    if ($extract_destination($normalized_message) !== NULL) {
      return TRUE;
    }

    return $text_contains_any($normalized_message, [
      'travel to ',
      'move to ',
      'exit via ',
      'leave the ',
      'leave this ',
      'i leave',
      'we leave',
      'go to the next room',
      'head to the next room',
      'move to the next room',
      'go deeper',
      'head deeper',
      'move deeper',
      'press farther',
      'press further',
      'push on',
      'go in',
      'go inside',
      'head in',
      'head inside',
      'enter the ',
      'enter through',
      'open the door',
      'open that door',
      'open this door',
      'break down the door',
      'kick in the door',
      'bust it loose',
      'lets open the door',
      'let us open the door',
      'go outside',
      'head outside',
      'step outside',
      'meet you there',
      'go there',
      'head there',
      'move there',
      'go that way',
      'head that way',
      'lets go there',
      'let us go there',
      'lets go',
      'let us go',
      'do it',
      'proceed',
      'take it',
      'take that exit',
      'take that door',
    ]);
  }

}
