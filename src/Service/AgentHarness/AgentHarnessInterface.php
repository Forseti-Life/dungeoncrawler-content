<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\AgentHarness;

/**
 * Canonical contract for every interactive and autonomous agent harness.
 */
interface AgentHarnessInterface {

  public const SCOPE_DRAFT = 'draft';
  public const SCOPE_SUITE = 'suite';
  public const SCOPE_DEFINITION = 'definition';
  public const SCOPE_CAMPAIGN_ROOM = 'campaign_room';
  public const SCOPE_ENCOUNTER = 'encounter';
  public const SCOPE_CHARACTER_DRAFT = 'character_draft';

  /**
   * Stable machine identifier for the agent harness.
   */
  public function agentId(): string;

  /**
   * Human-readable agent harness label.
   */
  public function label(): string;

  /**
   * Authoritative state scope governed by this agent harness.
   */
  public function scope(): string;

  /**
   * Default model execution profile (`AgentHarnessManagementService::PROFILE_*`).
   */
  public function defaultExecutionProfile(): string;

  /**
   * Lists internal tool capabilities exposed to this agent harness.
   *
   * @return array<string, mixed>
   */
  public function listAgentTools(string $profile = 'default'): array;

  /**
   * Formats a user-visible chat status line (`['tool' => ..., 'message' => 'Now ...']`).
   *
   * @param string $tool_name
   *   The internal tool identifier.
   * @param array<string, mixed> $arguments
   *   The tool invocation arguments.
   *
   * @return array{tool: string, message: string}
   */
  public function describeToolCall(string $tool_name, array $arguments = []): array;

}
