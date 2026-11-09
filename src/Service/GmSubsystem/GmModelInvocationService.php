<?php

namespace Drupal\dungeoncrawler_content\Service\GmSubsystem;

use Drupal\dungeoncrawler_content\Service\AgentHarness\AgentHarnessManagementService;

/**
 * Dedicated GM model invocation boundary.
 */
class GmModelInvocationService {

  protected AgentHarnessManagementService $harnessManagement;

  public function __construct(?AgentHarnessManagementService $harnessManagement = NULL) {
    $this->harnessManagement = $harnessManagement ?? new AgentHarnessManagementService();
  }

  /**
   * Invoke the GM model through a stable policy boundary.
   *
   * @param \Drupal\dungeoncrawler_content\Service\GmSubsystem\GmModelInvocationCallbacks $callbacks
   *   Typed invocation callbacks adapter.
   */
  public function invoke(
    string $prompt,
    string $system_prompt,
    array $context_data,
    string $room_id,
    string $operation,
    array $debug_meta,
    int $max_tokens,
    GmModelInvocationCallbacks $callbacks
  ): ?string {
    ['prompt' => $prompt, 'system_prompt' => $system_prompt, 'trim_meta' => $trim_meta] = $callbacks->fitContextBudget($prompt, $system_prompt);
    if (!empty($trim_meta['trimmed'])) {
      $debug_meta['context_trim'] = $trim_meta;
    }

    $execution_options = $this->harnessManagement->resolveExecutionOptions(
      AgentHarnessManagementService::PROFILE_RUNTIME_DIALOGUE,
      [
        'system_prompt' => $system_prompt,
        'max_tokens' => $max_tokens,
        'skip_cache' => TRUE,
      ],
    );

    try {
      $result = $callbacks->invokeTimedModelCall(
        $prompt,
        'dungeoncrawler_content',
        $operation,
        $context_data,
        $execution_options,
        $debug_meta
      );
    }
    catch (\Throwable $e) {
      $callbacks->logError('AI API error generating GM reply: @msg', ['@msg' => $e->getMessage()]);
      return NULL;
    }

    if (empty($result['success']) || empty($result['response'])) {
      $callbacks->logWarning('AI API returned unsuccessful or empty response for GM reply in room @room', [
        '@room' => $room_id,
      ]);
      return NULL;
    }

    return (string) $result['response'];
  }

}
