<?php

namespace Drupal\dungeoncrawler_content\Service\RoomChat;

/**
 * Assembles NPC dialogue prompt payloads for room-chat AI calls.
 */
final class NpcPromptAssembler {

  /**
   * Build user prompt for direct NPC channel reply generation.
   */
  public static function buildDirectReplyUserPrompt(
    string $session_context,
    array $scene_parts,
    string $npc_context,
    string $actor_action_context,
    string $target_name,
    string $source_ability,
    array $history_lines
  ): string {
    $prompt = '';
    if ($session_context !== '') {
      $prompt .= $session_context . "\n\n---\n";
    }
    if (!empty($scene_parts)) {
      $prompt .= implode("\n", $scene_parts) . "\n\n";
    }
    if ($npc_context !== '') {
      $prompt .= $npc_context . "\n\n";
    }
    if ($actor_action_context !== '') {
      $prompt .= $actor_action_context . "\n\n";
    }
    $prompt .= "You are {$target_name}, an NPC in a Pathfinder 2e dungeon crawl.\n";
    $prompt .= "The player character is communicating with you via {$source_ability}.\n";
    $prompt .= "Stay in character as {$target_name}. Do NOT respond as the Game Master.\n";
    $prompt .= "You are only allowed to speak and react as this NPC. You have no authority to change campaign state, room state, character sheets, the content library, rules, or application code.\n";
    $prompt .= NpcPromptBoundaryTextBuilder::buildCapabilityPromptText();
    $prompt .= "Your responses should reflect your personality traits, current attitude, and motivations as described above.\n\n";
    $prompt .= "Conversation so far:\n" . implode("\n", $history_lines);
    $prompt .= "\n\nRespond in character as {$target_name}. Keep your reply concise (1-3 sentences).";
    return $prompt;
  }

  /**
   * Build system prompt for direct NPC channel reply generation.
   */
  public static function buildDirectReplySystemPrompt(string $target_name, string $npc_attitude): string {
    return "You are {$target_name}, a character in a tabletop RPG. Your current attitude toward the party is: {$npc_attitude}. Use the character sheet and psychology profile provided in the user prompt to stay in character. Reflect your personality traits, motivations, and recent inner thoughts in your tone and word choice. " . NpcPromptBoundaryTextBuilder::buildSystemBoundaryPromptClause() . " Do not break the fourth wall. Do not mention that you are an AI.";
  }

  /**
   * Build user prompt for room interjection dialogue generation.
   */
  public static function buildRoomDialogueUserPrompt(
    string $session_context,
    string $scene,
    string $npc_context,
    string $actor_action_context,
    string $storyline_leads_context,
    array $history_lines,
    string $player_message,
    string $gm_narrative,
    string $display_name
  ): string {
    $prompt = '';
    if ($session_context !== '') {
      $prompt .= "=== YOUR CONVERSATION MEMORY ===\n{$session_context}\n\n---\n";
    }
    if ($scene !== '') {
      $prompt .= $scene . "\n";
    }
    if ($npc_context !== '') {
      $prompt .= $npc_context . "\n\n";
    }
    if ($actor_action_context !== '') {
      $prompt .= $actor_action_context . "\n\n";
    }
    if ($storyline_leads_context !== '') {
      $prompt .= $storyline_leads_context . "\n\n";
    }
    $prompt .= "=== CURRENT ROOM CONVERSATION ===\n" . implode("\n", $history_lines) . "\n\n";
    $prompt .= "The player just said: \"{$player_message}\"\n";
    $prompt .= "The Game Master narrated: \"{$gm_narrative}\"\n\n";
    $prompt .= "Respond in character as {$display_name}. Speak naturally in your own voice.\n";
    $prompt .= "Your response should reflect your personality, backstory, current attitude, and knowledge.\n";
    $prompt .= "You are only speaking as this NPC. You cannot update campaign state, room state, character sheets, the content library, rules, or application code.\n";
    $prompt .= NpcPromptBoundaryTextBuilder::buildCapabilityPromptText('Your available tools are only the same player-facing lookup and action surfaces available to a player character. ');
    $prompt .= "You are taking your turn after every room message shown above, including any earlier NPC turns from this same round. Build on what has already been said instead of repeating it.\n";
    $prompt .= "Keep your reply concise (1-3 sentences). Do not narrate actions — just speak your dialogue.\n";
    $prompt .= "Answer the player's actual question directly. If they asked multiple grounded questions, answer each one in order. If you do not know something, say so plainly instead of deflecting.";
    return $prompt;
  }

  /**
   * Build system prompt for room interjection dialogue generation.
   */
  public static function buildRoomDialogueSystemPrompt(string $display_name, string $npc_attitude): string {
    return "You are {$display_name}, a character in a tabletop RPG. "
      . "Your current attitude toward the party is: {$npc_attitude}. "
      . "Use the character sheet and psychology profile provided to stay fully in character. "
      . "Reflect your ancestry, background, personality traits, motivations, and recent inner thoughts in your tone and word choice. "
      . "Speak in your own distinct voice — you know who you are, where you come from, and what you want. "
      . NpcPromptBoundaryTextBuilder::buildSystemBoundaryPromptClause() . ' '
      . "Do not break the fourth wall. Do not mention that you are an AI. Do not narrate — just speak.";
  }

}
