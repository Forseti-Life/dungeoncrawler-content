<?php

namespace Drupal\dungeoncrawler_content\Service\GmSubsystem;

/**
 * Projects player-visible GM transcript fields from execution outputs.
 */
class GmTranscriptProjector {

  /**
   * Project visibility flags and visible narrative for a GM turn.
   *
   * @return array{
   *   suppress_visible_gm_response: bool,
   *   suppress_npc_interjections: bool,
   *   visible_gm_narrative: string
   * }
   */
  public function project(
    string $narrative,
    array $actions,
    ?array $state_diff,
    ?array $navigation_result,
    array $checked_response,
    array $dungeon_data,
    callable $build_visible_gm_narrative,
    callable $build_encounter_prefix_for_speaker,
    callable $prefix_encounter_chat_text
  ): array {
    $suppress_visible_gm_response = !empty($checked_response['suppress_visible_gm_response']);
    $visible_gm_narrative = $suppress_visible_gm_response
      ? ''
      : $build_visible_gm_narrative($narrative, $actions, $state_diff, $navigation_result);

    if (!$suppress_visible_gm_response) {
      $gm_encounter_prefix = $build_encounter_prefix_for_speaker($dungeon_data, 'Narrator');
      $visible_gm_narrative = $prefix_encounter_chat_text($visible_gm_narrative, $gm_encounter_prefix);
    }

    return [
      'suppress_visible_gm_response' => $suppress_visible_gm_response,
      'suppress_npc_interjections' => !empty($checked_response['suppress_npc_interjections']),
      'visible_gm_narrative' => $visible_gm_narrative,
    ];
  }

}

