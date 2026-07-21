<?php

namespace Drupal\dungeoncrawler_content\Service\GmSubsystem;

/**
 * Typed response-policy adapter for GM reality-check generation.
 */
class GmRealityCheckPolicyAdapter {

  protected \Closure $parseResponse;
  protected \Closure $validateRoleBoundary;
  protected \Closure $validateActionResources;
  protected \Closure $buildRealitySnapshot;
  protected \Closure $buildRealityRetryPrompt;
  protected \Closure $buildRoleBoundaryRetryPrompt;
  protected \Closure $buildSafeBoundaryFallback;
  protected \Closure $buildValidationFailureSummary;
  protected \Closure $extractPlayerCharacterName;

  public function __construct(
    callable $parse_response,
    callable $validate_role_boundary,
    callable $validate_action_resources,
    callable $build_reality_snapshot,
    callable $build_reality_retry_prompt,
    callable $build_role_boundary_retry_prompt,
    callable $build_safe_boundary_fallback,
    callable $build_validation_failure_summary,
    callable $extract_player_character_name
  ) {
    $this->parseResponse = \Closure::fromCallable($parse_response);
    $this->validateRoleBoundary = \Closure::fromCallable($validate_role_boundary);
    $this->validateActionResources = \Closure::fromCallable($validate_action_resources);
    $this->buildRealitySnapshot = \Closure::fromCallable($build_reality_snapshot);
    $this->buildRealityRetryPrompt = \Closure::fromCallable($build_reality_retry_prompt);
    $this->buildRoleBoundaryRetryPrompt = \Closure::fromCallable($build_role_boundary_retry_prompt);
    $this->buildSafeBoundaryFallback = \Closure::fromCallable($build_safe_boundary_fallback);
    $this->buildValidationFailureSummary = \Closure::fromCallable($build_validation_failure_summary);
    $this->extractPlayerCharacterName = \Closure::fromCallable($extract_player_character_name);
  }

  public function parseResponse(string $response): array {
    return ($this->parseResponse)($response);
  }

  public function validateRoleBoundary(string $narrative, ?array $character_data): array {
    return ($this->validateRoleBoundary)($narrative, $character_data);
  }

  public function validateActionResources(int $character_id, array $actions, int $campaign_id): array {
    return ($this->validateActionResources)($character_id, $actions, $campaign_id);
  }

  public function buildRealitySnapshot(?array $character_data, array $room_inventory): array {
    return ($this->buildRealitySnapshot)($character_data, $room_inventory);
  }

  public function buildRealityRetryPrompt(array $errors, array $snapshot): string {
    return ($this->buildRealityRetryPrompt)($errors, $snapshot);
  }

  public function buildRoleBoundaryRetryPrompt(string $player_character_name, array $role_boundary_errors): string {
    return ($this->buildRoleBoundaryRetryPrompt)($player_character_name, $role_boundary_errors);
  }

  public function buildSafeBoundaryFallback(string $player_character_name): string {
    return ($this->buildSafeBoundaryFallback)($player_character_name);
  }

  public function buildValidationFailureSummary(array $validation_errors): string {
    return ($this->buildValidationFailureSummary)($validation_errors);
  }

  public function extractPlayerCharacterName(?array $character_data): string {
    return ($this->extractPlayerCharacterName)($character_data);
  }

}

