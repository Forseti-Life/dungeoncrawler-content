<?php

namespace Drupal\dungeoncrawler_content\Service\GmSubsystem;

/**
 * Typed callback contract for GM reality-check generation dependencies.
 */
class GmRealityCheckCallbacks {

  protected \Closure $invokeModel;
  protected \Closure $recordDebugStage;
  protected \Closure $recordActionBatch;

  public function __construct(
    callable $invoke_model,
    callable $record_debug_stage,
    callable $record_action_batch
  ) {
    $this->invokeModel = \Closure::fromCallable($invoke_model);
    $this->recordDebugStage = \Closure::fromCallable($record_debug_stage);
    $this->recordActionBatch = \Closure::fromCallable($record_action_batch);
  }

  public function invokeModel(string $prompt, string $system_prompt, array $context_data, string $room_id, string $operation, array $debug_meta): ?string {
    return ($this->invokeModel)($prompt, $system_prompt, $context_data, $room_id, $operation, $debug_meta);
  }

  public function recordDebugStage(string $stage, int $started_at, array $meta = []): void {
    ($this->recordDebugStage)($stage, $started_at, $meta);
  }

  public function recordActionBatch(int $campaign_id, array $actions, string $status, array $context = []): void {
    ($this->recordActionBatch)($campaign_id, $actions, $status, $context);
  }

}
