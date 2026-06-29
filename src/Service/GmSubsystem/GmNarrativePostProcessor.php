<?php

namespace Drupal\dungeoncrawler_content\Service\GmSubsystem;

use Drupal\ai_conversation\Service\AIApiService;

/**
 * Normalizes generated GM narrative and handles suggestion-tag side effects.
 */
class GmNarrativePostProcessor {

  protected AIApiService $aiApiService;

  /**
   * Constructor.
   */
  public function __construct(AIApiService $ai_api_service) {
    $this->aiApiService = $ai_api_service;
  }

  /**
   * Process generated narrative and optional CREATE_SUGGESTION payload.
   *
   * @return array{
   *   narrative: string,
   *   suggestion_tag_detected: bool
   * }
   */
  public function process(
    int $campaign_id,
    string $room_id,
    array $chat,
    string $narrative,
    array $actions,
    array $dice_rolls,
    array $validation_errors,
    ?string $cache_key,
    callable $strip_player_visible_action_blocks,
    callable $trim_incomplete_narrative,
    callable $sanitize_player_visible_narrative
  ): array {
    $suggestion_tag_detected = FALSE;
    if (preg_match('/\[CREATE_SUGGESTION\](.*?)\[\/CREATE_SUGGESTION\]/s', $narrative, $suggestion_matches)) {
      $suggestion_tag_detected = TRUE;
      $suggestion_text = $suggestion_matches[1];
      $s_summary = '';
      $s_category = 'general_feedback';
      $s_original = end($chat)['message'] ?? '';
      if (preg_match('/Summary:\s*(.+?)(?=\nCategory:|\nOriginal:|$)/s', $suggestion_text, $match)) {
        $s_summary = trim($match[1]);
      }
      if (preg_match('/Category:\s*(\w+)/i', $suggestion_text, $match)) {
        $s_category = strtolower(trim($match[1]));
      }
      if (preg_match('/Original:\s*(.+?)$/s', $suggestion_text, $match)) {
        $s_original = trim($match[1]);
      }
      if ($s_summary !== '') {
        $this->aiApiService->createBacklogSuggestion(
          $s_summary,
          $s_original,
          $s_category,
          ['campaign_id' => $campaign_id, 'room_id' => $room_id]
        );
      }
      $narrative = trim((string) preg_replace('/\[CREATE_SUGGESTION\].*?\[\/CREATE_SUGGESTION\]/s', '', $narrative));
    }

    $narrative = $strip_player_visible_action_blocks($narrative);
    $narrative = $trim_incomplete_narrative($narrative);
    $narrative = $sanitize_player_visible_narrative($narrative);

    if ($cache_key !== NULL
      && $cache_key !== ''
      && $actions === []
      && $dice_rolls === []
      && $validation_errors === []
      && !$suggestion_tag_detected) {
      \Drupal::cache('default')->set($cache_key, [
        'narrative' => $narrative,
        'actions' => [],
        'dice_rolls' => [],
        'validation_errors' => [],
      ], time() + 300, [
        'dungeoncrawler_content:campaign:' . $campaign_id,
      ]);
    }

    return [
      'narrative' => $narrative,
      'suggestion_tag_detected' => $suggestion_tag_detected,
    ];
  }

}

