<?php

namespace Drupal\dungeoncrawler_content\Service\GmSubsystem;

use Drupal\dungeoncrawler_content\Service\CanonicalActionRegistryService;

/**
 * Entry orchestration boundary for GM reply generation flows.
 */
class GmReplyOrchestrationService {

  protected const FLOW_ROOM_REPLY = 'room_reply';
  protected const FLOW_QUEUED_REPLY = 'queued_reply';

  /**
   * Execute one GM room-reply generation turn through subsystem boundary.
   */
  public function generateRoomReply(callable $generate_reply, array $context = []): ?array {
    return $this->executeGmReplyFlow($generate_reply, $context, self::FLOW_ROOM_REPLY);
  }

  /**
   * Execute one queued-channel continuation generation turn through boundary.
   */
  public function generateQueuedReply(callable $generate_reply, array $context = []): ?array {
    return $this->executeGmReplyFlow($generate_reply, $context, self::FLOW_QUEUED_REPLY);
  }

  /**
   * Run one GM reply generation flow after validating entry contract.
   */
  protected function executeGmReplyFlow(callable $generate_reply, array $context, string $flow): ?array {
    $this->assertGmEntryContext($context, $flow);
    return $generate_reply();
  }

  /**
   * Enforce explicit GM-directed entry contract for reply orchestration.
   */
  protected function assertGmEntryContext(array $context, string $flow): void {
    $campaign_id = (int) ($context['campaign_id'] ?? 0);
    $room_id = trim((string) ($context['room_id'] ?? ''));
    $channel = trim((string) ($context['channel'] ?? ''));
    $speaker_type = strtolower(trim((string) ($context['speaker_type'] ?? '')));
    $is_gm_direct = ($context['is_gm_direct_channel'] ?? FALSE) === TRUE;

    if ($campaign_id <= 0 || $room_id === '') {
      throw new \InvalidArgumentException(sprintf('GM reply orchestration (%s) requires campaign_id and room_id.', $flow), 400);
    }
    if ($channel === '') {
      throw new \InvalidArgumentException(sprintf('GM reply orchestration (%s) requires channel.', $flow), 400);
    }
    if (!$is_gm_direct) {
      throw new \InvalidArgumentException(sprintf('GM reply orchestration (%s) requires gm_direct_channel=true.', $flow), 403);
    }
    if ($speaker_type !== 'player') {
      throw new \InvalidArgumentException(sprintf('GM reply orchestration (%s) only accepts player-originated turns.', $flow), 403);
    }
  }

  /**
   * Append one debug stage entry to an active trace buffer.
   */
  public function recordDebugStage(?array &$active_debug_trace, string $stage, float $duration_ms, array $meta = []): void {
    if ($active_debug_trace === NULL) {
      return;
    }

    $active_debug_trace['stages'][] = [
      'stage' => $stage,
      'duration_ms' => $duration_ms,
      'meta' => $meta,
    ];
  }

  /**
   * Record canonical action usage entries for observability.
   */
  public function recordCanonicalActionBatch(
    CanonicalActionRegistryService $canonical_action_registry,
    int $campaign_id,
    array $actions,
    string $status,
    array $context = []
  ): void {
    foreach ($actions as $action) {
      $action_type = (string) ($action['type'] ?? 'other');
      $canonical_action_registry->recordUsage($campaign_id, $action_type, $status, $context + [
        'action_name' => $action['name'] ?? $action_type,
        'details' => $action['details'] ?? [],
      ]);
    }
  }

}
