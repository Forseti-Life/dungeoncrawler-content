<?php

namespace Drupal\dungeoncrawler_content\Service\GmSubsystem;

/**
 * Typed callback contract for GM turn-coordination dependencies.
 */
class GmTurnCoordinatorCallbacks {

  protected \Closure $gatherRoomNpcsWithProfiles;
  protected \Closure $resolveDirectlyAddressedNpc;
  protected \Closure $resolveExplicitRoomConversationNpc;
  protected \Closure $resolveActiveDirectConversationNpc;
  protected \Closure $normalizeNpcNameForMatch;
  protected \Closure $shouldContinueActiveRoomConversation;
  protected \Closure $classifyRoomTurnIntent;
  protected \Closure $buildCachedRoomPromptArtifacts;
  protected \Closure $recordDebugStage;

  public function __construct(
    callable $gather_room_npcs_with_profiles,
    callable $resolve_directly_addressed_npc,
    callable $resolve_explicit_room_conversation_npc,
    callable $resolve_active_direct_conversation_npc,
    callable $normalize_npc_name_for_match,
    callable $should_continue_active_room_conversation,
    callable $classify_room_turn_intent,
    callable $build_cached_room_prompt_artifacts,
    callable $record_debug_stage
  ) {
    $this->gatherRoomNpcsWithProfiles = \Closure::fromCallable($gather_room_npcs_with_profiles);
    $this->resolveDirectlyAddressedNpc = \Closure::fromCallable($resolve_directly_addressed_npc);
    $this->resolveExplicitRoomConversationNpc = \Closure::fromCallable($resolve_explicit_room_conversation_npc);
    $this->resolveActiveDirectConversationNpc = \Closure::fromCallable($resolve_active_direct_conversation_npc);
    $this->normalizeNpcNameForMatch = \Closure::fromCallable($normalize_npc_name_for_match);
    $this->shouldContinueActiveRoomConversation = \Closure::fromCallable($should_continue_active_room_conversation);
    $this->classifyRoomTurnIntent = \Closure::fromCallable($classify_room_turn_intent);
    $this->buildCachedRoomPromptArtifacts = \Closure::fromCallable($build_cached_room_prompt_artifacts);
    $this->recordDebugStage = \Closure::fromCallable($record_debug_stage);
  }

  public function gatherRoomNpcsWithProfiles(int $campaign_id, string $room_id, array $dungeon_data): array {
    return ($this->gatherRoomNpcsWithProfiles)($campaign_id, $room_id, $dungeon_data);
  }

  public function resolveDirectlyAddressedNpc(array $room_npcs, string $latest_player_message): ?array {
    return ($this->resolveDirectlyAddressedNpc)($room_npcs, $latest_player_message);
  }

  public function resolveExplicitRoomConversationNpc(array $room_meta, array $room_npcs): ?array {
    return ($this->resolveExplicitRoomConversationNpc)($room_meta, $room_npcs);
  }

  public function resolveActiveDirectConversationNpc(array $chat, array $room_npcs): ?array {
    return ($this->resolveActiveDirectConversationNpc)($chat, $room_npcs);
  }

  public function normalizeNpcNameForMatch(string $text): string {
    return ($this->normalizeNpcNameForMatch)($text);
  }

  public function shouldContinueActiveRoomConversation(string $latest_player_message, string $normalized_player_message, array $active_conversation_npc): bool {
    return ($this->shouldContinueActiveRoomConversation)($latest_player_message, $normalized_player_message, $active_conversation_npc);
  }

  public function classifyRoomTurnIntent(string $latest_player_message, array $room_npcs, ?array $effective_direct_npc, ?array $active_conversation_npc): string {
    return ($this->classifyRoomTurnIntent)($latest_player_message, $room_npcs, $effective_direct_npc, $active_conversation_npc);
  }

  public function buildCachedRoomPromptArtifacts(int $campaign_id, string $room_id, array $room_meta, array $dungeon_data, array $room_npcs): array {
    return ($this->buildCachedRoomPromptArtifacts)($campaign_id, $room_id, $room_meta, $dungeon_data, $room_npcs);
  }

  public function recordDebugStage(string $stage, int $started_at, array $meta = []): void {
    ($this->recordDebugStage)($stage, $started_at, $meta);
  }

}
