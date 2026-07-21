<?php

namespace Drupal\dungeoncrawler_content\Service\GmSubsystem;

/**
 * Typed callback contract for GM turn-finalization dependencies.
 */
class GmTurnFinalizationCallbacks {

  protected \Closure $stripPlayerVisibleActionBlocks;
  protected \Closure $trimIncompleteNarrative;
  protected \Closure $sanitizePlayerVisibleNarrative;
  protected \Closure $recordDebugStage;
  protected \Closure $recordCanonicalActionBatch;
  protected \Closure $filterChatBlockedNavigationActions;
  protected \Closure $synchronizeExplicitRoomConversationState;
  protected \Closure $buildVisibleGmNarrative;
  protected \Closure $buildGmRoomResponsePayload;
  protected \Closure $bridgeGmReplyToSessionSystem;

  public function __construct(
    callable $strip_player_visible_action_blocks,
    callable $trim_incomplete_narrative,
    callable $sanitize_player_visible_narrative,
    callable $record_debug_stage,
    callable $record_canonical_action_batch,
    callable $filter_chat_blocked_navigation_actions,
    callable $synchronize_explicit_room_conversation_state,
    callable $build_visible_gm_narrative,
    callable $build_gm_room_response_payload,
    callable $bridge_gm_reply_to_session_system
  ) {
    $this->stripPlayerVisibleActionBlocks = \Closure::fromCallable($strip_player_visible_action_blocks);
    $this->trimIncompleteNarrative = \Closure::fromCallable($trim_incomplete_narrative);
    $this->sanitizePlayerVisibleNarrative = \Closure::fromCallable($sanitize_player_visible_narrative);
    $this->recordDebugStage = \Closure::fromCallable($record_debug_stage);
    $this->recordCanonicalActionBatch = \Closure::fromCallable($record_canonical_action_batch);
    $this->filterChatBlockedNavigationActions = \Closure::fromCallable($filter_chat_blocked_navigation_actions);
    $this->synchronizeExplicitRoomConversationState = \Closure::fromCallable($synchronize_explicit_room_conversation_state);
    $this->buildVisibleGmNarrative = \Closure::fromCallable($build_visible_gm_narrative);
    $this->buildGmRoomResponsePayload = \Closure::fromCallable($build_gm_room_response_payload);
    $this->bridgeGmReplyToSessionSystem = \Closure::fromCallable($bridge_gm_reply_to_session_system);
  }

  public function stripPlayerVisibleActionBlocks(string $narrative): string {
    return ($this->stripPlayerVisibleActionBlocks)($narrative);
  }

  public function trimIncompleteNarrative(string $narrative): string {
    return ($this->trimIncompleteNarrative)($narrative);
  }

  public function sanitizePlayerVisibleNarrative(string $narrative): string {
    return ($this->sanitizePlayerVisibleNarrative)($narrative);
  }

  public function recordDebugStage(string $stage, int $started_at, array $meta = []): void {
    ($this->recordDebugStage)($stage, $started_at, $meta);
  }

  public function recordCanonicalActionBatch(int $campaign_id, array $actions, string $status, array $context = []): void {
    ($this->recordCanonicalActionBatch)($campaign_id, $actions, $status, $context);
  }

  public function filterChatBlockedNavigationActions(array $actions, array &$validation_errors): array {
    return ($this->filterChatBlockedNavigationActions)($actions, $validation_errors);
  }

  public function synchronizeExplicitRoomConversationState(
    array &$dungeon_data,
    int|string $room_index,
    string $turn_intent,
    ?array $conversation_npc = NULL,
    array $room_npcs = [],
    string $player_message = '',
    ?int $character_id = NULL,
    array $response_context = []
  ): void {
    ($this->synchronizeExplicitRoomConversationState)(
      $dungeon_data,
      $room_index,
      $turn_intent,
      $conversation_npc,
      $room_npcs,
      $player_message,
      $character_id,
      $response_context
    );
  }

  public function buildVisibleGmNarrative(string $narrative, array $actions = [], ?array $state_diff = NULL, ?array $navigation_result = NULL): string {
    return ($this->buildVisibleGmNarrative)($narrative, $actions, $state_diff, $navigation_result);
  }

  public function buildGmRoomResponsePayload(
    string $narrative,
    array $actions = [],
    array $dice_rolls = [],
    bool $suppress_npc_interjections = FALSE
  ): array {
    return ($this->buildGmRoomResponsePayload)($narrative, $actions, $dice_rolls, $suppress_npc_interjections);
  }

  public function bridgeGmReplyToSessionSystem(
    int $campaign_id,
    int|string $dungeon_id,
    string $room_id,
    string $narrative,
    array $actions = [],
    array $dice_rolls = []
  ): void {
    ($this->bridgeGmReplyToSessionSystem)($campaign_id, $dungeon_id, $room_id, $narrative, $actions, $dice_rolls);
  }

}
