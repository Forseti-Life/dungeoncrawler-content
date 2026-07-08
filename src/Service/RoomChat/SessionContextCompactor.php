<?php

namespace Drupal\dungeoncrawler_content\Service\RoomChat;

/**
 * Compacts session-context payloads into prompt-safe bounded sections.
 */
final class SessionContextCompactor {

  /**
   * Compact an entire session-context string into bounded sections.
   */
  public static function compact(
    string $context,
    int $max_recent,
    int $max_chars,
    int $max_summary_chars,
    bool $include_recent_messages
  ): string {
    $sections = preg_split("/\n\s*\n/", $context) ?: [];
    $parts = [];
    foreach ($sections as $section) {
      $compact_section = self::compactSection(
        $section,
        $max_recent,
        $max_summary_chars,
        $include_recent_messages
      );
      if ($compact_section === '') {
        continue;
      }
      $parts[] = $compact_section;
    }

    $compact = implode("\n\n", $parts);
    if (strlen($compact) > $max_chars) {
      $compact = substr($compact, 0, $max_chars - 3) . '...';
    }

    return $compact;
  }

  /**
   * Compact one named session-context section.
   */
  public static function compactSection(
    string $section,
    int $max_recent,
    int $max_summary_chars,
    bool $include_recent_messages
  ): string {
    $section = trim($section);
    if ($section === '') {
      return '';
    }

    if (str_starts_with($section, 'PRIOR SESSION CONTEXT')) {
      [$heading, $body] = array_pad(explode("\n", $section, 2), 2, '');
      $body = trim($body);
      if (strlen($body) > $max_summary_chars) {
        $body = substr($body, 0, $max_summary_chars - 3) . '...';
      }
      return $body !== '' ? $heading . "\n" . $body : '';
    }

    if (str_starts_with($section, 'RECENT CONVERSATION')) {
      if (!$include_recent_messages) {
        return '';
      }
      [$heading, $body] = array_pad(explode("\n", $section, 2), 2, '');
      $lines = preg_split("/\r?\n/", trim($body)) ?: [];
      $lines = array_slice(array_values(array_filter(array_map('trim', $lines))), -$max_recent);
      foreach ($lines as &$line) {
        if (strlen($line) > 180) {
          $line = substr($line, 0, 177) . '...';
        }
      }
      unset($line);
      return $lines !== [] ? $heading . "\n" . implode("\n", $lines) : '';
    }

    return $section;
  }

}
