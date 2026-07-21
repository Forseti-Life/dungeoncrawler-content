<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Entry harness for privileged GM actor orchestration.
 */
class GmActorHarnessService {

  protected const HARNESS_CONTRACT_VERSION = 'gm-actor-harness-v1';

  protected GmActorRuntimeService $runtime;
  protected GmToolExecutionService $toolExecution;

  public function __construct(GmActorRuntimeService $runtime, GmToolExecutionService $tool_execution) {
    $this->runtime = $runtime;
    $this->toolExecution = $tool_execution;
  }

  /**
   * Execute one player-room-chat turn routed through the GM actor runtime.
   */
  public function handlePlayerRoomChat(
    int $campaign_id,
    string $room_id,
    string $actor_id,
    int $character_id,
    string $message,
    bool $suppress_gm = FALSE,
    string $speaker = '',
    array $route = []
  ): array {
    $result = $this->runtime->handlePlayerRoomChat(
      $campaign_id,
      $room_id,
      $actor_id,
      $character_id,
      $message,
      $suppress_gm,
      $speaker,
      $route
    );
    $result['gm_actor_harness'] = [
      'contract_version' => self::HARNESS_CONTRACT_VERSION,
      'runtime_contract_version' => 'gm-actor-runtime-v1',
    ];

    return $result;
  }

  /**
   * Execute one privileged GM tool call.
   */
  public function runTool(string $tool, array $payload, array $context = []): array {
    $result = $this->toolExecution->execute($tool, $payload, $context);
    if (!is_array($result)) {
      throw new \RuntimeException('GM tool execution returned invalid result payload.');
    }
    $result['harness_contract_version'] = self::HARNESS_CONTRACT_VERSION;

    return $result;
  }

  /**
   * List canonical GM tool capabilities.
   */
  public function listTools(): array {
    return [
      'contract_version' => GmToolExecutionService::TOOL_CONTRACT_VERSION,
      'tools' => $this->toolExecution->listTools(),
    ];
  }

  /**
   * Replay one or more GM actor room-chat turns using a stable payload.
   */
  public function replayRoomChat(array $payload, int $iterations = 1): array {
    $iterations = max(1, $iterations);
    $required = ['campaign_id', 'room_id', 'actor_id', 'character_id', 'message'];
    foreach ($required as $key) {
      if (!array_key_exists($key, $payload)) {
        throw new \InvalidArgumentException(sprintf('Replay payload missing required key: %s', $key), 400);
      }
    }

    $results = [];
    for ($index = 0; $index < $iterations; $index++) {
      $results[] = $this->handlePlayerRoomChat(
        (int) $payload['campaign_id'],
        (string) $payload['room_id'],
        (string) $payload['actor_id'],
        (int) $payload['character_id'],
        (string) $payload['message'],
        !empty($payload['suppress_gm']),
        (string) ($payload['speaker'] ?? ''),
        is_array($payload['route'] ?? NULL) ? $payload['route'] : []
      );
    }

    return [
      'contract_version' => self::HARNESS_CONTRACT_VERSION,
      'iterations' => $iterations,
      'results' => $results,
    ];
  }

}
