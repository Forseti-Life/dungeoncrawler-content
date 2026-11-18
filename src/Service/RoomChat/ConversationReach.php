<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\RoomChat;

use Drupal\dungeoncrawler_content\Service\ChatChannelManager;

/**
 * The one rule for who a character can hold a private conversation with.
 *
 * A character speaks to actors in the room it stands in. Reaching an actor
 * anywhere else takes a spell or effect that carries speech, such as
 * Message, and only as far as that spell reaches.
 */
final class ConversationReach {

  /**
   * Decides whether a private channel may be opened or used.
   *
   * Distance in feet is not measured between rooms: a spell with a range in
   * feet reaches the rooms of the site the speaker is in, and no further.
   *
   * @param array{type: string, spell_key: ?string} $channel
   *   ChatChannelManager::parseChannelKey() result.
   * @param array{speaker_in_room: bool, target_in_room: bool, target_in_site: bool, target_in_campaign: bool, speaker_spells: string[], speaker_effects: string[]} $facts
   *
   * @return string|null
   *   The refusal shown to the player, or NULL when the conversation is in
   *   reach.
   */
  public static function refusal(array $channel, string $target_name, array $facts): ?string {
    if (!$facts['speaker_in_room']) {
      return 'Your character is not in this room.';
    }
    if ($channel['type'] === 'whisper') {
      return $facts['target_in_room'] ? NULL : sprintf(
        '%s is not in this room. Speaking to someone elsewhere takes a spell or effect that carries your words, such as Message.',
        $target_name,
      );
    }
    if ($channel['type'] !== 'spell') {
      return 'A private conversation is opened as a whisper or through a spell.';
    }
    $spell_key = self::token((string) $channel['spell_key']);
    $spell = ChatChannelManager::SPELL_CHANNEL_DEFS[$spell_key] ?? NULL;
    if ($spell === NULL || $spell_key === 'whisper') {
      return sprintf("'%s' is not a spell or effect that carries speech.", (string) $channel['spell_key']);
    }
    if (!in_array($spell_key, $facts['speaker_spells'], TRUE) && !in_array($spell_key, $facts['speaker_effects'], TRUE)) {
      return sprintf('Your character does not have %s available.', $spell['label']);
    }
    if ($facts['target_in_room']) {
      return NULL;
    }
    $reaches = match (TRUE) {
      $spell['range'] === 'unlimited' => $facts['target_in_campaign'],
      is_int($spell['range']) => $facts['target_in_site'],
      default => FALSE,
    };
    return $reaches ? NULL : sprintf('%s is beyond the reach of %s.', $target_name, $spell['label']);
  }

  /**
   * Every spell a character sheet lists, as tokens.
   *
   * @param mixed $spells
   *   The sheet's `spells` block: lists of spell IDs or of spell records.
   *
   * @return string[]
   */
  public static function spellsOnSheet(mixed $spells): array {
    $found = [];
    $walk = static function (mixed $value) use (&$walk, &$found): void {
      if (is_string($value)) {
        $found[self::token($value)] = TRUE;
        return;
      }
      if (!is_array($value)) {
        return;
      }
      if (array_is_list($value)) {
        array_map($walk, $value);
        return;
      }
      foreach (['id', 'spell_id', 'name'] as $key) {
        if (is_string($value[$key] ?? NULL)) {
          $found[self::token($value[$key])] = TRUE;
        }
      }
      foreach ($value as $child) {
        if (is_array($child)) {
          $walk($child);
        }
      }
    };
    if (is_array($spells)) {
      // Top-level scalars (tradition, casting ability) are not spells.
      foreach ($spells as $child) {
        if (is_array($child)) {
          $walk($child);
        }
      }
    }
    return array_keys($found);
  }

  public static function token(string $value): string {
    return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($value)), '-');
  }

}
