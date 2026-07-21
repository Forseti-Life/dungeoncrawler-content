<?php

namespace Drupal\dungeoncrawler_content\Service\GmSubsystem;

/**
 * Dedicated routing/cache policy service for GM deterministic vs LLM response flow.
 */
class GmResponseRoutingService {

  /**
   * Resolve deterministic response branch and role-boundary reconciliation.
   *
   * @param callable $validate_role_boundary
   *   Signature: fn(string $narrative, ?array $character_data): array
   * @param callable $build_safe_boundary_fallback
   *   Signature: fn(string $player_character_name): string
   * @param callable $extract_player_character_name
   *   Signature: fn(?array $character_data): string
   *
   * @return array{
   *   handled: bool,
   *   checked_response: ?array<string,mixed>,
   *   response_source: string,
   *   debug_meta: array<string,mixed>
   * }
   */
  public function resolveDeterministicBranch(
    ?array $deterministic_response,
    ?array $character_data,
    string $turn_intent,
    callable $validate_role_boundary,
    callable $build_safe_boundary_fallback,
    callable $extract_player_character_name
  ): array {
    if (!is_array($deterministic_response)) {
      return [
        'handled' => FALSE,
        'checked_response' => NULL,
        'response_source' => 'unresolved',
        'debug_meta' => [],
      ];
    }

    $role_boundary_errors = $validate_role_boundary((string) ($deterministic_response['narrative'] ?? ''), $character_data);
    if ($role_boundary_errors !== []) {
      $deterministic_response['narrative'] = $build_safe_boundary_fallback($extract_player_character_name($character_data));
      $deterministic_response['actions'] = [];
      $deterministic_response['dice_rolls'] = [];
      $deterministic_response['validation_errors'] = array_values(array_unique(array_merge(
        $deterministic_response['validation_errors'] ?? [],
        $role_boundary_errors
      )));
    }

    return [
      'handled' => TRUE,
      'checked_response' => $deterministic_response,
      'response_source' => 'deterministic',
      'debug_meta' => [
        'intent' => $turn_intent,
        'narrative_length' => strlen((string) ($deterministic_response['narrative'] ?? '')),
        'action_count' => count($deterministic_response['actions'] ?? []),
        'role_boundary_violation_count' => count($role_boundary_errors),
      ],
    ];
  }

  /**
   * Decide whether the main GM response is cacheable.
   *
   * @param callable $normalize_text
   *   Signature: fn(string $text): string
   * @param callable $text_contains_any
   *   Signature: fn(string $haystack, array $needles): bool
   */
  public function shouldUseResponseCache(
    string $turn_intent,
    string $latest_player_message,
    bool $is_room_entry,
    callable $normalize_text,
    callable $text_contains_any
  ): bool {
    if ($is_room_entry || $turn_intent !== 'gm_narration') {
      return FALSE;
    }

    $normalized = $normalize_text($latest_player_message);
    if ($normalized === '' || strlen($normalized) > 180) {
      return FALSE;
    }

    if ($text_contains_any($normalized, ['attack', 'cast', 'roll', 'stealth', 'initiative', 'search', 'investigate', 'pick lock', 'unlock', 'use', 'skill check'])) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Build a stable cache key for low-variance GM replies.
   *
   * @param array<int,string> $history_lines
   *   Recent chat lines.
   * @param array<string,mixed> $prompt_artifacts
   *   Prompt artifact bundle.
   */
  public function buildResponseCacheKey(
    int $campaign_id,
    string $room_id,
    ?int $character_id,
    string $turn_intent,
    array $history_lines,
    array $prompt_artifacts,
    string $prompt,
    string $system_prompt
  ): string {
    $cache_state = [
      'campaign_id' => $campaign_id,
      'room_id' => $room_id,
      'character_id' => $character_id,
      'turn_intent' => $turn_intent,
      'history_lines' => array_slice($history_lines, -3),
      'prompt_artifacts' => $prompt_artifacts,
      'prompt_hash' => sha1($prompt),
      'system_prompt_hash' => sha1($system_prompt),
    ];

    return 'dungeoncrawler_content:gm_response:' . sha1(json_encode($cache_state));
  }

}

