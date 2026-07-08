<?php

namespace Drupal\dungeoncrawler_content\Service\RoomChat;

/**
 * Enforces encounter turn ownership for player room chat.
 */
final class EncounterTurnGuard {

  /**
   * Validate turn ownership for one room chat attempt.
   *
   * @param callable(string,array): ?array $entity_resolver
   *   Resolves the active turn entity from dungeon payload.
   */
  public function validatePlayerTurnForChat(
    array $dungeon_data,
    callable $entity_resolver,
    string $channel = 'room',
    ?int $character_id = NULL,
    string $type = 'player',
    string $speaker = ''
  ): ?string {
    if ($type !== 'player' || $channel !== 'room') {
      return NULL;
    }

    $game_state = is_array($dungeon_data['game_state'] ?? NULL) ? $dungeon_data['game_state'] : [];
    if (($game_state['phase'] ?? '') !== 'encounter') {
      return NULL;
    }

    $turn_entity_id = trim((string) ($game_state['turn']['entity'] ?? ''));
    if ($turn_entity_id === '') {
      return NULL;
    }

    $active_entity = $entity_resolver($turn_entity_id, $dungeon_data);
    if ($active_entity === NULL) {
      return NULL;
    }

    $active_team = strtolower((string) (
      $active_entity['state']['metadata']['team']
      ?? $active_entity['team']
      ?? ''
    ));
    if ($active_team === '') {
      $content_type = strtolower((string) ($active_entity['entity_type'] ?? ($active_entity['entity_ref']['content_type'] ?? '')));
      if ($content_type === 'player_character') {
        $active_team = 'player';
      }
    }
    if ($active_team !== 'player') {
      return "It's not your turn, please wait.";
    }

    if ($character_id === NULL) {
      $active_speaker = trim((string) (
        $active_entity['state']['metadata']['display_name']
        ?? $active_entity['name']
        ?? ''
      ));
      if ($speaker !== '' && $active_speaker !== '' && strcasecmp($active_speaker, $speaker) !== 0) {
        return "It's not your turn, please wait.";
      }
      return NULL;
    }

    $active_character_id = (
      $active_entity['state']['metadata']['campaign_character_id']
      ?? $active_entity['state']['metadata']['source_character_id']
      ?? $active_entity['state']['metadata']['character_id']
      ?? $active_entity['character_id']
      ?? $active_entity['source_character_id']
      ?? $active_entity['state']['character_id']
      ?? ($active_entity['entity_ref']['character_id'] ?? NULL)
      ?? ($active_entity['entity_ref']['content_id'] ?? NULL)
      ?? NULL
    );
    if ($active_character_id === NULL || (string) $active_character_id === '') {
      $active_speaker = trim((string) (
        $active_entity['state']['metadata']['display_name']
        ?? $active_entity['name']
        ?? ''
      ));
      if ($speaker !== '' && $active_speaker !== '' && strcasecmp($active_speaker, $speaker) === 0) {
        return NULL;
      }
      return "It's not your turn, please wait.";
    }

    if ((string) $active_character_id !== (string) $character_id) {
      return "It's not your turn, please wait.";
    }

    return NULL;
  }

}
