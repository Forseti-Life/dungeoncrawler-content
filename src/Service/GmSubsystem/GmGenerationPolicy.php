<?php

namespace Drupal\dungeoncrawler_content\Service\GmSubsystem;

/**
 * Encapsulates cache-aware GM fallback generation policy.
 *
 * This is a behavior-preserving extraction from RoomChatService::generateGmReply()
 * so fallback generation orchestration can evolve behind a stable boundary.
 */
class GmGenerationPolicy {

  /**
   * Resolve one GM candidate using cache-first policy.
   *
   * @param bool $should_use_cache
   *   Whether cache should be consulted for this turn.
   * @param string|null $cache_key
   *   Candidate cache key when caching is enabled.
   * @param callable $generator
   *   Callback that returns ?array GM candidate.
   *
   * @return array{
   *   checked_response: ?array,
   *   response_source: string,
   *   cache_status: string,
   *   generation_attempted: bool
   * }
   */
  public function resolve(bool $should_use_cache, ?string $cache_key, callable $generator): array {
    $checked_response = NULL;
    $response_source = 'unresolved';
    $cache_status = 'bypass';
    $generation_attempted = FALSE;

    if ($should_use_cache && is_string($cache_key) && $cache_key !== '') {
      $cached_gm_response = \Drupal::cache('default')->get($cache_key);
      if ($cached_gm_response && is_array($cached_gm_response->data)) {
        $checked_response = $cached_gm_response->data;
        $response_source = 'cache';
        $cache_status = 'hit';
      }
      else {
        $cache_status = 'miss';
      }
    }

    if ($checked_response === NULL) {
      $generation_attempted = TRUE;
      $checked_response = $generator();
      if (is_array($checked_response)) {
        $response_source = 'reality_checked_generation';
      }
    }

    return [
      'checked_response' => is_array($checked_response) ? $checked_response : NULL,
      'response_source' => $response_source,
      'cache_status' => $cache_status,
      'generation_attempted' => $generation_attempted,
    ];
  }

}

