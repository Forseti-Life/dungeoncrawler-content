<?php

namespace Drupal\dungeoncrawler_content\Service\RoomChat;

/**
 * Maps internal stream progress stages to client-facing progress payloads.
 */
class RoomChatProgressStageMapper {

  /**
   * @param array<string,mixed> $context
   *   Stage-specific context values.
   *
   * @return array<string,mixed>|null
   *   Stage payload or NULL when stage is not client-visible.
   */
  public function map(string $stage, string $channel, string $client_request_id, array $context = []): ?array {
    switch ($stage) {
      case 'room_request_started':
        return [
          'message' => $channel !== 'room'
            ? 'Reviewing what you just said...'
            : 'Reviewing the room and what you just said...',
          'phase' => 'reviewing-room',
          'speaker' => 'System',
          'client_request_id' => $client_request_id,
        ];

      case 'conversation_persisted':
        return [
          'message' => 'Updating conversation state...',
          'phase' => 'updating-conversation',
          'speaker' => 'System',
          'client_request_id' => $client_request_id,
        ];

      case 'conversation_bridged':
        return [
          'message' => 'Syncing the scene context...',
          'phase' => 'syncing-context',
          'speaker' => 'System',
          'client_request_id' => $client_request_id,
        ];

      case 'npc_context_prepared':
        return [
          'message' => $channel !== 'room'
            ? 'Checking the active participants...'
            : 'Checking who is active in the scene...',
          'phase' => 'checking-reactions',
          'speaker' => 'System',
          'client_request_id' => $client_request_id,
        ];

      case 'gm_reply_generating':
        return [
          'message' => $channel !== 'room'
            ? 'Preparing the reply...'
            : 'Preparing the scene...',
          'phase' => 'drafting-response',
          'speaker' => 'System',
          'client_request_id' => $client_request_id,
        ];

      case 'npc_reactions_generating':
        return [
          'message' => 'Resolving the next actor in turn order...',
          'phase' => 'npc-reactions',
          'speaker' => 'System',
          'client_request_id' => $client_request_id,
        ];

      case 'queued_continuation_started':
      case 'queued_messages_loaded':
        $queued_count = max(1, (int) ($context['queued_player_count'] ?? 1));
        return [
          'message' => $queued_count === 1
            ? 'Thinking about what you just said...'
            : "Thinking about the {$queued_count} things you just said...",
          'phase' => 'reviewing-queue',
          'speaker' => 'System',
          'client_request_id' => $client_request_id,
        ];
    }

    return NULL;
  }

}
