<?php

namespace Drupal\dungeoncrawler_content\Service\RoomChat;

use Drupal\dungeoncrawler_content\Service\EncounterTranscriptPrefix;

/**
 * Builds and normalizes encounter transcript prefixes for room chat.
 */
final class EncounterTranscriptPrefixService {

  /**
   * Format the canonical encounter transcript prefix.
   */
  public function format(
    int|string $round_display,
    int|string|null $turn_display,
    string $actor_name,
    int|string|null $actions_remaining = NULL,
    int|string|null $actions_total = NULL
  ): string {
    return EncounterTranscriptPrefix::formatPrefix(
      $round_display,
      $turn_display,
      $actor_name,
      $actions_remaining,
      $actions_total
    );
  }

  /**
   * Build the canonical encounter transcript prefix for a specific speaker.
   *
   * @param callable(string,array): ?array $entity_resolver
   *   Resolves active turn entity from the dungeon payload.
   */
  public function buildForSpeaker(array $dungeon_data, string $speaker, callable $entity_resolver): ?string {
    $game_state = is_array($dungeon_data['game_state'] ?? NULL) ? $dungeon_data['game_state'] : [];
    if (($game_state['phase'] ?? '') !== 'encounter') {
      return NULL;
    }

    $round_raw = $game_state['round'] ?? 1;
    $round_display = is_numeric($round_raw) ? max(0, ((int) $round_raw) - 1) : '?';
    $turn_index_raw = isset($game_state['turn']['index']) && is_numeric($game_state['turn']['index']) ? (int) $game_state['turn']['index'] : NULL;
    $turn_index_human = $turn_index_raw !== NULL ? ($turn_index_raw + 1) : 1;
    $turn_display = is_numeric($turn_index_human) ? (int) $turn_index_human : '?';

    ['remaining' => $actions_remaining, 'total' => $actions_total] = $this->resolveActionStateForSpeaker(
      $dungeon_data,
      (string) $speaker,
      $entity_resolver
    );

    return $this->format($round_display, $turn_display, (string) $speaker, $actions_remaining, $actions_total);
  }

  /**
   * Build the canonical encounter transcript prefix from dungeon data.
   *
   * @param callable(string,array): ?array $entity_resolver
   *   Resolves active turn entity from the dungeon payload.
   */
  public function buildFromDungeonData(array $dungeon_data, callable $entity_resolver): ?string {
    $game_state = is_array($dungeon_data['game_state'] ?? NULL) ? $dungeon_data['game_state'] : [];
    $round_raw = $game_state['round'] ?? 1;
    $round_display = is_numeric($round_raw) ? max(0, ((int) $round_raw) - 1) : '?';
    $turn_index_raw = isset($game_state['turn']['index']) && is_numeric($game_state['turn']['index']) ? (int) $game_state['turn']['index'] : NULL;
    $turn_index_human = $turn_index_raw !== NULL ? ($turn_index_raw + 1) : 1;
    $turn_display = is_numeric($turn_index_human) ? (int) $turn_index_human : '?';

    $turn_entity_id = trim((string) ($game_state['turn']['entity'] ?? ''));
    $active_entity = $turn_entity_id !== '' ? $entity_resolver($turn_entity_id, $dungeon_data) : NULL;
    $actor_name = (string) (
      $active_entity['state']['metadata']['display_name']
      ?? $active_entity['name']
      ?? 'Unknown'
    );

    $actions_total = is_numeric($game_state['turn']['actions_total'] ?? NULL) ? max(0, (int) $game_state['turn']['actions_total']) : 3;
    $actions_remaining = is_numeric($game_state['turn']['actions_remaining'] ?? NULL)
      ? max(0, (int) $game_state['turn']['actions_remaining'])
      : $actions_total;

    return $this->format($round_display, $turn_display, $actor_name, $actions_remaining, $actions_total);
  }

  /**
   * True when a room-chat line already includes an encounter prefix.
   */
  public function isPrefixed(string $content): bool {
    return EncounterTranscriptPrefix::isPrefixed($content);
  }

  /**
   * Normalize legacy turn-order transcript lines that used Turn 0.
   */
  public function normalizeLegacyTurnOrderPrefix(string $content): string {
    $content = trim($content);
    if ($content === '' || stripos($content, 'Turn order:') === FALSE) {
      return $content;
    }

    $updated = preg_replace(
      '/^Round\s+([0-9\?]+):\s+Turn\s+0:\s+Actor\s+System:\s+Actions\s+([0-9\?]+\/[0-9\?]+):\s+Turn order:/u',
      'Round $1: Turn 1: Actor System: Actions $2: Turn order:',
      $content,
      1
    );

    return is_string($updated) ? $updated : $content;
  }

  /**
   * Prefix room chat text with encounter metadata when needed.
   */
  public function prefixChatText(string $content, ?string $encounter_prefix): string {
    $content = trim($content);
    if ($content === '' || $this->isPrefixed($content)) {
      return $content;
    }
    if ($encounter_prefix === NULL || trim($encounter_prefix) === '') {
      return $content;
    }

    $prefix = $encounter_prefix;
    if (!str_ends_with($prefix, ' ')) {
      $prefix .= ' ';
    }

    return $prefix . $content;
  }

  /**
   * Strip encounter transcript prefix from rendered chat text.
   */
  public function stripPrefix(string $content): string {
    $content = trim($content);
    $stripped = preg_replace('/^Round\s+[0-9\?]+:\s+(?:Turn\s+[0-9\?]+:\s+)?(?:Actor\s+)?[^\:]+\:\s+/u', '', $content, 1);
    return is_string($stripped) ? trim($stripped) : $content;
  }

  /**
   * Resolve action counters for canonical transcript prefixes.
   *
   * @param callable(string,array): ?array $entity_resolver
   *   Resolves active turn entity from the dungeon payload.
   */
  private function resolveActionStateForSpeaker(array $dungeon_data, string $speaker, callable $entity_resolver): array {
    $game_state = is_array($dungeon_data['game_state'] ?? NULL) ? $dungeon_data['game_state'] : [];
    $actions_total = is_numeric($game_state['turn']['actions_total'] ?? NULL) ? max(0, (int) $game_state['turn']['actions_total']) : 3;
    $active_remaining = is_numeric($game_state['turn']['actions_remaining'] ?? NULL)
      ? max(0, (int) $game_state['turn']['actions_remaining'])
      : $actions_total;

    $speaker_normalized = strtolower(trim($speaker));
    if ($speaker_normalized === '') {
      return ['remaining' => $actions_total, 'total' => $actions_total];
    }

    $turn_entity_id = trim((string) ($game_state['turn']['entity'] ?? ''));
    $active_actor_names = [];
    if ($turn_entity_id !== '') {
      $active_entity = $entity_resolver($turn_entity_id, $dungeon_data);
      if (is_array($active_entity)) {
        $active_actor_names[] = strtolower(trim((string) ($active_entity['state']['metadata']['display_name'] ?? '')));
        $active_actor_names[] = strtolower(trim((string) ($active_entity['name'] ?? '')));
      }
      foreach (($game_state['initiative_order'] ?? []) as $entry) {
        if (!is_array($entry) || (string) ($entry['entity_id'] ?? '') !== $turn_entity_id) {
          continue;
        }
        $active_actor_names[] = strtolower(trim((string) ($entry['name'] ?? '')));
      }
    }

    foreach ($active_actor_names as $candidate) {
      if ($candidate !== '' && $candidate === $speaker_normalized) {
        return ['remaining' => $active_remaining, 'total' => $actions_total];
      }
    }

    return ['remaining' => $actions_total, 'total' => $actions_total];
  }

}
