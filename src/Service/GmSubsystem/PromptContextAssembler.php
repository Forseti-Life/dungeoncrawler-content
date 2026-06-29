<?php

namespace Drupal\dungeoncrawler_content\Service\GmSubsystem;

/**
 * Assembles fallback GM user prompt text from precomputed context fragments.
 *
 * This service is intentionally pure string assembly so RoomChatService can
 * keep behavior stable while extracting generateGmReply() concerns.
 */
class PromptContextAssembler {

  /**
   * Build fallback user prompt text and prompt-debug metadata.
   *
   * @param array{
   *   session_context: string,
   *   scene_parts: array,
   *   npc_roster_summary: string,
   *   npc_profile_summary: string,
   *   actor_grounding: string,
   *   room_quest_context: string,
   *   merchant_summary: string,
   *   quest_summary: string,
   *   quest_prompt_context: string,
   *   history_lines: array,
   *   is_room_entry: bool,
   *   turn_intent: string,
   *   guardrails: string,
   *   recent_message_count: int,
   *   artifact_bytes: int
   * } $input
   *
   * @return array{prompt: string, debug_meta: array}
   *   Prompt text plus debug metadata for trace logging.
   */
  public function assemble(array $input): array {
    $session_context = (string) ($input['session_context'] ?? '');
    $scene_parts = is_array($input['scene_parts'] ?? NULL) ? $input['scene_parts'] : [];
    $npc_roster_summary = (string) ($input['npc_roster_summary'] ?? '');
    $npc_profile_summary = (string) ($input['npc_profile_summary'] ?? '');
    $actor_grounding = (string) ($input['actor_grounding'] ?? '');
    $room_quest_context = (string) ($input['room_quest_context'] ?? '');
    $merchant_summary = (string) ($input['merchant_summary'] ?? '');
    $quest_summary = (string) ($input['quest_summary'] ?? '');
    $quest_prompt_context = (string) ($input['quest_prompt_context'] ?? '');
    $history_lines = is_array($input['history_lines'] ?? NULL) ? $input['history_lines'] : [];
    $is_room_entry = !empty($input['is_room_entry']);
    $turn_intent = (string) ($input['turn_intent'] ?? '');
    $guardrails = (string) ($input['guardrails'] ?? '');
    $recent_message_count = (int) ($input['recent_message_count'] ?? 0);
    $artifact_bytes = (int) ($input['artifact_bytes'] ?? 0);

    $prompt = '';
    if ($session_context !== '') {
      $prompt .= $session_context . "\n\n---\n";
    }
    if ($scene_parts !== []) {
      $prompt .= implode("\n", $scene_parts) . "\n\n";
    }
    if ($npc_roster_summary !== '') {
      $prompt .= $npc_roster_summary . "\n\n";
    }
    if ($npc_profile_summary !== '') {
      $prompt .= $npc_profile_summary . "\n\n";
    }
    if ($actor_grounding !== '') {
      $prompt .= $actor_grounding . "\n\n";
    }
    if ($room_quest_context !== '') {
      $prompt .= $room_quest_context . "\n\n";
    }
    if ($merchant_summary !== '' && $turn_intent === 'gm_narration') {
      $prompt .= $merchant_summary . "\n\n";
    }
    if ($quest_summary !== '' && in_array($turn_intent, ['gm_narration', 'quest_query'], TRUE)) {
      $prompt .= $quest_summary . "\n\n";
    }
    if ($quest_prompt_context !== '') {
      $prompt .= $quest_prompt_context . "\n\n";
    }
    $prompt .= "Recent conversation:\n" . implode("\n", $history_lines);
    if ($is_room_entry) {
      $prompt .= "\n\nTHIS IS A ROOM ENTRY — respond as the Game Master with a vivid but concise room-entry description (4-6 sentences, under 140 words). Cover atmosphere, sight, sound, smell/taste, and visible grounded occupants. Keep the primary GM response limited to environmental and setting narration only. Include the JSON action block only if the player triggered a mechanical action.";
    }
    else {
      $prompt .= "\n\nRespond as the Game Master referee. Keep your reply concise (2-4 sentences) and limit it to environmental and setting narration only. If the player is performing a mechanical action (casting a spell, using a skill, using a feat, attacking, exploring), include the JSON action block as instructed in your system prompt.";
    }
    $prompt .= $guardrails;

    return [
      'prompt' => $prompt,
      'debug_meta' => [
        'recent_message_count' => $recent_message_count,
        'history_line_count' => count($history_lines),
        'session_context_length' => strlen($session_context),
        'prompt_length' => strlen($prompt),
        'room_entry' => $is_room_entry,
        'quest_context_length' => strlen($quest_prompt_context),
        'room_quest_context_length' => strlen($room_quest_context),
        'actor_grounding_length' => strlen($actor_grounding),
        'artifact_bytes' => $artifact_bytes,
      ],
    ];
  }

}

