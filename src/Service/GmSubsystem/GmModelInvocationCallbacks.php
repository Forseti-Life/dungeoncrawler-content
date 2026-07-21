<?php

namespace Drupal\dungeoncrawler_content\Service\GmSubsystem;

/**
 * Typed callback contract for GM model invocation dependencies.
 */
class GmModelInvocationCallbacks {

  protected \Closure $fitContextBudget;
  protected \Closure $invokeTimedModelCall;
  protected \Closure $logError;
  protected \Closure $logWarning;

  public function __construct(
    callable $fit_context_budget,
    callable $invoke_timed_model_call,
    callable $log_error,
    callable $log_warning
  ) {
    $this->fitContextBudget = \Closure::fromCallable($fit_context_budget);
    $this->invokeTimedModelCall = \Closure::fromCallable($invoke_timed_model_call);
    $this->logError = \Closure::fromCallable($log_error);
    $this->logWarning = \Closure::fromCallable($log_warning);
  }

  public function fitContextBudget(string $prompt, string $system_prompt): array {
    return ($this->fitContextBudget)($prompt, $system_prompt);
  }

  public function invokeTimedModelCall(
    string $prompt,
    string $scope,
    string $operation,
    array $context_data,
    array $options,
    array $debug_meta
  ): array {
    return ($this->invokeTimedModelCall)($prompt, $scope, $operation, $context_data, $options, $debug_meta);
  }

  public function logError(string $message, array $context = []): void {
    ($this->logError)($message, $context);
  }

  public function logWarning(string $message, array $context = []): void {
    ($this->logWarning)($message, $context);
  }

}

