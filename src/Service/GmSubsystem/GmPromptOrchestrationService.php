<?php

namespace Drupal\dungeoncrawler_content\Service\GmSubsystem;

/**
 * Dedicated GM prompt/debug metadata orchestration boundary.
 */
class GmPromptOrchestrationService {

  protected PromptContextAssembler $promptContextAssembler;

  public function __construct(?PromptContextAssembler $prompt_context_assembler = NULL) {
    $this->promptContextAssembler = $prompt_context_assembler ?? new PromptContextAssembler();
  }

  /**
   * Assemble user prompt text and debug metadata for one GM generation turn.
   *
   * @param array<string,mixed> $prompt_artifacts
   *   Cached prompt artifact bundle from room context preparation.
   * @param array<int,string> $scene_parts
   *   Scene context lines.
   * @param array<int,string> $history_lines
   *   Recent room chat transcript lines.
   *
   * @return array{
   *   prompt: string,
   *   user_prompt_debug_meta: array<string,mixed>
   * }
   *   Prompt payload and user-prompt assembly metadata.
   */
  public function buildPromptArtifacts(
    string $session_context,
    array $scene_parts,
    array $prompt_artifacts,
    string $actor_grounding,
    string $room_quest_context,
    string $quest_prompt_context,
    array $history_lines,
    bool $is_room_entry,
    string $turn_intent,
    string $guardrails,
    int $recent_message_count
  ): array {
    $prompt_assembly = $this->promptContextAssembler->assemble([
      'session_context' => $session_context,
      'scene_parts' => $scene_parts,
      'npc_roster_summary' => (string) ($prompt_artifacts['npc_roster_summary'] ?? ''),
      'npc_profile_summary' => (string) ($prompt_artifacts['npc_profile_summary'] ?? ''),
      'actor_grounding' => $actor_grounding,
      'room_quest_context' => $room_quest_context,
      'merchant_summary' => (string) ($prompt_artifacts['merchant_summary'] ?? ''),
      'quest_summary' => (string) ($prompt_artifacts['quest_summary'] ?? ''),
      'quest_prompt_context' => $quest_prompt_context,
      'history_lines' => $history_lines,
      'is_room_entry' => $is_room_entry,
      'turn_intent' => $turn_intent,
      'guardrails' => $guardrails,
      'recent_message_count' => $recent_message_count,
      'artifact_bytes' => strlen(json_encode($prompt_artifacts) ?: ''),
    ]);

    return [
      'prompt' => (string) ($prompt_assembly['prompt'] ?? ''),
      'user_prompt_debug_meta' => is_array($prompt_assembly['debug_meta'] ?? NULL)
        ? $prompt_assembly['debug_meta']
        : [],
    ];
  }

  /**
   * Build compact prompt-debug metadata for reality-check generation tracing.
   *
   * @param array<int,string> $history_lines
   *   Recent room chat transcript lines.
   * @param array<int,string> $scene_parts
   *   Scene context lines.
   *
   * @return array<string,mixed>
   *   Prompt-debug metadata payload.
   */
  public function buildPromptDebugMeta(
    int $recent_message_count,
    array $history_lines,
    string $session_context,
    array $scene_parts,
    bool $is_room_entry,
    string $quest_prompt_context,
    array|string $room_inventory_summary,
    bool $has_character_context
  ): array {
    return [
      'recent_message_count' => $recent_message_count,
      'history_line_count' => count($history_lines),
      'session_context_length' => strlen($session_context),
      'scene_part_count' => count($scene_parts),
      'room_entry' => $is_room_entry,
      'quest_context_length' => strlen($quest_prompt_context),
      'room_inventory' => $room_inventory_summary,
      'has_character_context' => $has_character_context,
    ];
  }

}
